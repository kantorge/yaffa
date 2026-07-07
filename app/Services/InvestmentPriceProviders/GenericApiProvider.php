<?php

namespace App\Services\InvestmentPriceProviders;

use App\Contracts\InvestmentPriceProvider;
use App\Exceptions\InvalidPriceDataException;
use App\Exceptions\PriceProviderException;
use App\Exceptions\UnsafeEndpointUrlException;
use App\Models\Investment;
use App\Services\PublicEndpointUrlValidator;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

class GenericApiProvider implements InvestmentPriceProvider
{
    public function __construct(private Client $httpClient)
    {
    }

    public function fetchPrices(Investment $investment, ?Carbon $from = null, bool $refill = false): array
    {
        $cutoff = $from
            ? $from->copy()->startOfDay()
            : ($refill ? null : Carbon::now()->subDays(5)->startOfDay());

        $credentials = $investment->provider_credentials;

        $endpointUrl = $this->requiredStringCredential($credentials, 'endpoint_url', $investment);

        $method = mb_strtoupper((string) ($credentials['http_method'] ?? 'GET'));
        if (! in_array($method, ['GET', 'POST'], true)) {
            throw new InvalidPriceDataException(
                'Unsupported HTTP method. Allowed values are GET and POST.',
                'generic_api',
                $investment->symbol
            );
        }

        $placeholders = [
            '{symbol}' => $investment->symbol,
            '{from}' => $from?->copy()->startOfDay()->format('Y-m-d') ?? '',
            '{to}' => Carbon::now()->format('Y-m-d'),
        ];

        $resolvedEndpointUrl = $this->interpolate((string) $endpointUrl, $placeholders);
        $resolvedIps = $this->assertPublicEndpointUrl($resolvedEndpointUrl, $investment->symbol);

        $headers = $this->parseJsonObjectCredential($credentials, 'headers_json', $placeholders, $investment);
        $query = $this->parseJsonObjectCredential($credentials, 'query_json', $placeholders, $investment);
        $body = $this->parseJsonObjectCredential($credentials, 'body_json', $placeholders, $investment);

        try {
            $requestOptions = [
                'query' => $query,
                'headers' => $headers,
                'timeout' => 30,
                // Never follow redirects: a validated public host could redirect the
                // actual request to an internal address, bypassing the check above.
                'allow_redirects' => false,
            ];

            $pinnedCurlOptions = $this->buildDnsPinningCurlOptions($resolvedEndpointUrl, $resolvedIps);
            if ($pinnedCurlOptions !== []) {
                $requestOptions['curl'] = $pinnedCurlOptions;
            }

            if ($method === 'POST' && $body !== []) {
                $requestOptions['json'] = $body;
            }

            $response = $this->httpClient->request($method, $resolvedEndpointUrl, $requestOptions);
            $decodedBody = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($decodedBody)) {
                throw new InvalidPriceDataException(
                    'Generic API returned an invalid JSON payload.',
                    'generic_api',
                    $investment->symbol
                );
            }

            $prices = [];
            $dateFormat = isset($credentials['date_format']) && is_string($credentials['date_format'])
                ? Str::trim($credentials['date_format'])
                : 'auto';

            $dateValuesPath = isset($credentials['date_values_path']) && is_string($credentials['date_values_path'])
                ? Str::trim($credentials['date_values_path'])
                : '';
            $priceValuesPath = isset($credentials['price_values_path']) && is_string($credentials['price_values_path'])
                ? Str::trim($credentials['price_values_path'])
                : '';

            if ($dateValuesPath !== '' || $priceValuesPath !== '') {
                $dateValues = data_get($decodedBody, $dateValuesPath);
                $priceValues = data_get($decodedBody, $priceValuesPath);

                if (! is_array($dateValues) || ! is_array($priceValues) || $dateValues === [] || $priceValues === []) {
                    throw new InvalidPriceDataException(
                        'No usable date/price arrays found at the configured parallel paths.',
                        'generic_api',
                        $investment->symbol
                    );
                }

                $dateValues = array_values($dateValues);
                $priceValues = array_values($priceValues);

                foreach ($dateValues as $index => $rawDate) {
                    $rawPrice = $priceValues[$index] ?? null;

                    $normalizedPrice = $this->normalizeDatePrice(
                        $rawDate,
                        $rawPrice,
                        $dateFormat,
                        $cutoff,
                        $investment,
                    );

                    if ($normalizedPrice !== null) {
                        $prices[] = $normalizedPrice;
                    }
                }
            } else {
                $datePath = $this->requiredStringCredential($credentials, 'date_path', $investment);
                $pricePath = $this->requiredStringCredential($credentials, 'price_path', $investment);

                $itemsPath = isset($credentials['items_path']) && is_string($credentials['items_path'])
                    ? Str::trim($credentials['items_path'])
                    : '';

                $items = $itemsPath === ''
                    ? (isset($decodedBody[0]) && is_array($decodedBody[0]) ? $decodedBody : [$decodedBody])
                    : data_get($decodedBody, $itemsPath);

                if (! is_array($items) || $items === []) {
                    throw new InvalidPriceDataException(
                        'No price records found at the configured items path.',
                        'generic_api',
                        $investment->symbol
                    );
                }

                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $rawDate = data_get($item, $datePath);
                    $rawPrice = data_get($item, $pricePath);

                    $normalizedPrice = $this->normalizeDatePrice(
                        $rawDate,
                        $rawPrice,
                        $dateFormat,
                        $cutoff,
                        $investment,
                    );

                    if ($normalizedPrice !== null) {
                        $prices[] = $normalizedPrice;
                    }
                }
            }

            if ($prices === []) {
                throw new InvalidPriceDataException(
                    'No valid prices found in the API response.',
                    'generic_api',
                    $investment->symbol
                );
            }

            return $prices;
        } catch (InvalidPriceDataException $exception) {
            throw $exception;
        } catch (JsonException $exception) {
            throw new PriceProviderException(
                "Generic API invalid JSON response: {$exception->getMessage()}",
                'generic_api',
                $investment->symbol,
                $exception
            );
        } catch (GuzzleException $exception) {
            throw new PriceProviderException(
                "Generic API request failed: {$exception->getMessage()}",
                'generic_api',
                $investment->symbol,
                $exception
            );
        }
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function validateCredentials(array $credentials): void
    {
        $endpointUrl = isset($credentials['endpoint_url']) && is_string($credentials['endpoint_url'])
            ? Str::trim($credentials['endpoint_url'])
            : '';

        if ($endpointUrl === '') {
            throw new PriceProviderException('Endpoint URL is required.', 'generic_api');
        }

        $resolvedEndpointUrl = strtr($endpointUrl, [
            '{symbol}' => 'AAPL',
            '{from}' => '2024-01-01',
            '{to}' => '2024-01-02',
        ]);

        if (! filter_var($resolvedEndpointUrl, FILTER_VALIDATE_URL)) {
            throw new PriceProviderException('Endpoint URL must be a valid URL.', 'generic_api');
        }

        $this->assertPublicEndpointUrl($resolvedEndpointUrl, null);

        $datePath = isset($credentials['date_path']) && is_string($credentials['date_path'])
            ? Str::trim($credentials['date_path'])
            : '';

        $pricePath = isset($credentials['price_path']) && is_string($credentials['price_path'])
            ? Str::trim($credentials['price_path'])
            : '';

        $dateValuesPath = isset($credentials['date_values_path']) && is_string($credentials['date_values_path'])
            ? Str::trim($credentials['date_values_path'])
            : '';
        $priceValuesPath = isset($credentials['price_values_path']) && is_string($credentials['price_values_path'])
            ? Str::trim($credentials['price_values_path'])
            : '';

        if ($dateValuesPath !== '' || $priceValuesPath !== '') {
            if ($dateValuesPath === '' || $priceValuesPath === '') {
                throw new PriceProviderException('Both date_values_path and price_values_path are required when using parallel array mode.', 'generic_api');
            }
        } elseif ($datePath === '' || $pricePath === '') {
            throw new PriceProviderException('Date path and price path are required.', 'generic_api');
        }

        $method = isset($credentials['http_method']) && is_string($credentials['http_method'])
            ? Str::upper(Str::trim($credentials['http_method']))
            : 'GET';

        if (! in_array($method, ['GET', 'POST'], true)) {
            throw new PriceProviderException('HTTP method must be GET or POST.', 'generic_api');
        }

        $this->assertJsonObjectCredential($credentials, 'headers_json');
        $this->assertJsonObjectCredential($credentials, 'query_json');
        $this->assertJsonObjectCredential($credentials, 'body_json');
    }

    public function getName(): string
    {
        return 'generic_api';
    }

    public function getDisplayName(): string
    {
        return __('Custom API (Advanced)');
    }

    public function getDescription(): string
    {
        return __('Use the custom JSON API endpoint by defining request and parsing settings.');
    }

    public function getInstructions(): string
    {
        return __('Configure endpoint URL, optional headers/query/body JSON, and response paths for date and price. You can use placeholders like {symbol}, {from}, and {to} in URL and JSON fields.');
    }

    /**
     * @return array<string, mixed>
     */
    public function getInvestmentSettingsSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getUserSettingsSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['endpoint_url'],
            'properties' => [
                'endpoint_url' => [
                    'type' => 'string',
                    'format' => 'url',
                    'allowPlaceholders' => true,
                    'label' => __('Endpoint URL'),
                    'minLength' => 8,
                    'maxLength' => 2048,
                ],
                'http_method' => [
                    'type' => 'string',
                    'label' => __('HTTP method'),
                    'enum' => ['GET', 'POST'],
                    'maxLength' => 4,
                ],
                'headers_json' => [
                    'type' => 'string',
                    'label' => __('Headers (JSON object)'),
                    'maxLength' => 10000,
                ],
                'query_json' => [
                    'type' => 'string',
                    'label' => __('Query params (JSON object)'),
                    'maxLength' => 10000,
                ],
                'body_json' => [
                    'type' => 'string',
                    'label' => __('Body (JSON object, POST only)'),
                    'maxLength' => 20000,
                ],
                'items_path' => [
                    'type' => 'string',
                    'label' => __('Items path'),
                    'maxLength' => 255,
                ],
                'date_path' => [
                    'type' => 'string',
                    'label' => __('Date path'),
                    'minLength' => 1,
                    'maxLength' => 255,
                ],
                'price_path' => [
                    'type' => 'string',
                    'label' => __('Price path'),
                    'minLength' => 1,
                    'maxLength' => 255,
                ],
                'date_values_path' => [
                    'type' => 'string',
                    'label' => __('Date values path (parallel array mode)'),
                    'maxLength' => 255,
                ],
                'price_values_path' => [
                    'type' => 'string',
                    'label' => __('Price values path (parallel array mode)'),
                    'maxLength' => 255,
                ],
                'date_format' => [
                    'type' => 'string',
                    'label' => __('Date format'),
                    'enum' => ['auto', 'Y-m-d', 'timestamp_seconds', 'timestamp_milliseconds'],
                    'maxLength' => 24,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getRateLimitPolicy(): array
    {
        return [
            'perSecond' => null,
            'perMinute' => 10,
            'perDay' => 1000,
            'reserve' => 0,
            'overrideable' => true,
        ];
    }

    public function supportsHistoricalSync(): bool
    {
        return true;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function requiredStringCredential(array $credentials, string $key, Investment $investment): string
    {
        $value = isset($credentials[$key]) && is_string($credentials[$key])
            ? Str::trim($credentials[$key])
            : '';

        if ($value === '') {
            throw new InvalidPriceDataException(
                __('Missing required configuration field: :field', ['field' => $key]),
                'generic_api',
                $investment->symbol
            );
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, string>  $placeholders
     * @return array<string, mixed>
     */
    private function parseJsonObjectCredential(
        array $credentials,
        string $key,
        array $placeholders,
        Investment $investment
    ): array {
        $value = isset($credentials[$key]) && is_string($credentials[$key])
            ? Str::trim($credentials[$key])
            : '';

        if ($value === '') {
            return [];
        }

        $resolvedValue = $this->interpolate($value, $placeholders);

        try {
            $decoded = json_decode($resolvedValue, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidPriceDataException(
                __('Invalid JSON in :field: :message', ['field' => $key, 'message' => $exception->getMessage()]),
                'generic_api',
                $investment->symbol,
                $exception
            );
        }

        if (! is_array($decoded)) {
            throw new InvalidPriceDataException(
                __('Field :field must contain a JSON object.', ['field' => $key]),
                'generic_api',
                $investment->symbol
            );
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function assertJsonObjectCredential(array $credentials, string $key): void
    {
        $value = isset($credentials[$key]) && is_string($credentials[$key])
            ? Str::trim($credentials[$key])
            : '';

        if ($value === '') {
            return;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new PriceProviderException(
                __('Invalid JSON in :field: :message', ['field' => $key, 'message' => $exception->getMessage()]),
                'generic_api',
                null,
                $exception
            );
        }

        if (! is_array($decoded)) {
            throw new PriceProviderException(
                __('Field :field must contain a JSON object.', ['field' => $key]),
                'generic_api'
            );
        }
    }

    /**
     * @param  array<string, string>  $placeholders
     */
    private function interpolate(string $value, array $placeholders): string
    {
        return strtr($value, $placeholders);
    }

    /**
     * @return array<int, string> the IP addresses the host resolved to, for DNS pinning
     */
    private function assertPublicEndpointUrl(string $endpointUrl, ?string $investmentSymbol): array
    {
        try {
            return PublicEndpointUrlValidator::assertPublic($endpointUrl);
        } catch (UnsafeEndpointUrlException $exception) {
            throw new PriceProviderException(
                $exception->getMessage(),
                'generic_api',
                $investmentSymbol,
                $exception
            );
        }
    }

    /**
     * Pins the connection to the already-validated IP address(es) so a short-TTL DNS
     * record can't resolve to a different (internal) address between validation and
     * the actual request (DNS-rebinding TOCTOU).
     *
     * @param  array<int, string>  $resolvedIps
     * @return array<int, mixed>
     */
    private function buildDnsPinningCurlOptions(string $endpointUrl, array $resolvedIps): array
    {
        if ($resolvedIps === []) {
            return [];
        }

        $host = parse_url($endpointUrl, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return [];
        }

        $scheme = parse_url($endpointUrl, PHP_URL_SCHEME);
        $port = parse_url($endpointUrl, PHP_URL_PORT) ?? (Str::lower((string) $scheme) === 'http' ? 80 : 443);

        $addresses = array_map(
            static fn (string $ip): string => str_contains($ip, ':') ? "[{$ip}]" : $ip,
            $resolvedIps
        );

        return [
            CURLOPT_RESOLVE => [sprintf('%s:%d:%s', mb_trim($host, '[]'), $port, implode(',', $addresses))],
        ];
    }

    /**
     * @param  mixed  $rawDate
     * @param  mixed  $rawPrice
     * @return array{date: string, price: float}|null
     */
    private function normalizeDatePrice(
        mixed $rawDate,
        mixed $rawPrice,
        string $dateFormat,
        ?Carbon $cutoff,
        Investment $investment,
    ): ?array {
        if ($rawDate === null || $rawPrice === null || ! is_numeric($rawPrice)) {
            return null;
        }

        $day = $this->parseDate($rawDate, $dateFormat, $investment);

        if ($cutoff !== null && $day->lt($cutoff)) {
            return null;
        }

        $price = (float) $rawPrice;
        if ($price <= 0) {
            return null;
        }

        return [
            'date' => $day->format('Y-m-d'),
            'price' => $price,
        ];
    }

    /**
     * @param  mixed  $rawDate
     */
    private function parseDate(mixed $rawDate, string $dateFormat, Investment $investment): Carbon
    {
        try {
            if ($dateFormat === 'timestamp_seconds') {
                return Carbon::createFromTimestamp((int) $rawDate)->startOfDay();
            }

            if ($dateFormat === 'timestamp_milliseconds') {
                return Carbon::createFromTimestampMs((int) $rawDate)->startOfDay();
            }

            if ($dateFormat === 'Y-m-d') {
                return Carbon::createFromFormat('Y-m-d', (string) $rawDate)->startOfDay();
            }

            if (is_numeric($rawDate)) {
                $strDate = (string) $rawDate;
                if (preg_match('/^\d{8}$/', $strDate)) {
                    return Carbon::createFromFormat('Ymd', $strDate)->startOfDay();
                }

                $timestamp = (float) $rawDate;
                if ($timestamp > 9999999999) {
                    return Carbon::createFromTimestampMs((int) $timestamp)->startOfDay();
                }

                return Carbon::createFromTimestamp((int) $timestamp)->startOfDay();
            }

            return Carbon::parse((string) $rawDate)->startOfDay();
        } catch (Throwable $exception) {
            throw new InvalidPriceDataException(
                'Unable to parse date value returned by Generic API.',
                'generic_api',
                $investment->symbol,
                $exception
            );
        }
    }
}
