<?php

namespace App\Services\InvestmentPriceProviders;

use App\Contracts\InvestmentPriceProvider;
use App\Exceptions\InvalidPriceDataException;
use App\Exceptions\PriceProviderException;
use App\Models\Investment;
use Illuminate\Support\Carbon;
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
        throw_unless(in_array($method, ['GET', 'POST'], true), new InvalidPriceDataException(
                'Unsupported HTTP method. Allowed values are GET and POST.',
                'generic_api',
                $investment->symbol
            ));

        $placeholders = [
            '{symbol}' => $investment->symbol,
            '{from}' => $from?->copy()->startOfDay()->format('Y-m-d') ?? '',
            '{to}' => Carbon::now()->format('Y-m-d'),
        ];

        $resolvedEndpointUrl = $this->interpolate((string) $endpointUrl, $placeholders);
        $this->assertPublicEndpointUrl($resolvedEndpointUrl, $investment->symbol);

        $headers = $this->parseJsonObjectCredential($credentials, 'headers_json', $placeholders, $investment);
        $query = $this->parseJsonObjectCredential($credentials, 'query_json', $placeholders, $investment);
        $body = $this->parseJsonObjectCredential($credentials, 'body_json', $placeholders, $investment);

        try {
            $requestOptions = [
                'query' => $query,
                'headers' => $headers,
                'timeout' => 30,
            ];

            if ($method === 'POST' && $body !== []) {
                $requestOptions['json'] = $body;
            }

            $response = $this->httpClient->request($method, $resolvedEndpointUrl, $requestOptions);
            $decodedBody = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

            throw_unless(is_array($decodedBody), new InvalidPriceDataException(
                    'Generic API returned an invalid JSON payload.',
                    'generic_api',
                    $investment->symbol
                ));

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

                throw_if(! is_array($dateValues) || ! is_array($priceValues) || $dateValues === [] || $priceValues === [], new InvalidPriceDataException(
                        'No usable date/price arrays found at the configured parallel paths.',
                        'generic_api',
                        $investment->symbol
                    ));

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

                throw_if(! is_array($items) || $items === [], new InvalidPriceDataException(
                        'No price records found at the configured items path.',
                        'generic_api',
                        $investment->symbol
                    ));

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

            throw_if($prices === [], new InvalidPriceDataException(
                    'No valid prices found in the API response.',
                    'generic_api',
                    $investment->symbol
                ));

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

        throw_if($endpointUrl === '', new PriceProviderException('Endpoint URL is required.', 'generic_api'));

        $resolvedEndpointUrl = strtr($endpointUrl, [
            '{symbol}' => 'AAPL',
            '{from}' => '2024-01-01',
            '{to}' => '2024-01-02',
        ]);

        throw_unless(filter_var($resolvedEndpointUrl, FILTER_VALIDATE_URL), new PriceProviderException('Endpoint URL must be a valid URL.', 'generic_api'));

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
            throw_if($dateValuesPath === '' || $priceValuesPath === '', new PriceProviderException('Both date_values_path and price_values_path are required when using parallel array mode.', 'generic_api'));
        } elseif ($datePath === '' || $pricePath === '') {
            throw new PriceProviderException('Date path and price path are required.', 'generic_api');
        }

        $method = isset($credentials['http_method']) && is_string($credentials['http_method'])
            ? Str::upper(Str::trim($credentials['http_method']))
            : 'GET';

        throw_unless(in_array($method, ['GET', 'POST'], true), new PriceProviderException('HTTP method must be GET or POST.', 'generic_api'));

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

        throw_if($value === '', new InvalidPriceDataException(
                __('Missing required configuration field: :field', ['field' => $key]),
                'generic_api',
                $investment->symbol
            ));

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

        throw_unless(is_array($decoded), new InvalidPriceDataException(
                __('Field :field must contain a JSON object.', ['field' => $key]),
                'generic_api',
                $investment->symbol
            ));

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

        throw_unless(is_array($decoded), new PriceProviderException(
                __('Field :field must contain a JSON object.', ['field' => $key]),
                'generic_api'
            ));
    }

    /**
     * @param  array<string, string>  $placeholders
     */
    private function interpolate(string $value, array $placeholders): string
    {
        return strtr($value, $placeholders);
    }

    private function assertPublicEndpointUrl(string $endpointUrl, ?string $investmentSymbol): void
    {
        $host = parse_url($endpointUrl, PHP_URL_HOST);

        throw_if(! is_string($host) || Str::trim($host) === '', new PriceProviderException(
                'Endpoint URL must include a valid host.',
                'generic_api',
                $investmentSymbol
            ));

        $normalizedHost = Str::lower(mb_trim($host, '[]'));

        throw_if($normalizedHost === 'localhost', new PriceProviderException(
                'Endpoint URL must resolve to a public IP address.',
                'generic_api',
                $investmentSymbol
            ));

        $resolvedIps = $this->resolveEndpointIps($normalizedHost);

        if ($resolvedIps === []) {
            return;
        }

        foreach ($resolvedIps as $resolvedIp) {
            throw_if($this->isDisallowedIp($resolvedIp), new PriceProviderException(
                    'Endpoint URL must resolve to a public IP address.',
                    'generic_api',
                    $investmentSymbol
                ));
        }
    }

    /**
     * @return array<int, string>
     */
    private function resolveEndpointIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $resolvedIps = [];

        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                $ipAddress = $record['ip'] ?? $record['ipv6'] ?? null;

                if (is_string($ipAddress) && $ipAddress !== '') {
                    $resolvedIps[] = $ipAddress;
                }
            }
        }

        if ($resolvedIps === []) {
            $ipv4Addresses = gethostbynamel($host);

            if (is_array($ipv4Addresses)) {
                foreach ($ipv4Addresses as $ipv4Address) {
                    if ($ipv4Address !== '') {
                        $resolvedIps[] = $ipv4Address;
                    }
                }
            }
        }

        return array_values(array_unique($resolvedIps));
    }

    private function isDisallowedIp(string $ipAddress): bool
    {
        $normalizedIpAddress = Str::lower($ipAddress);

        if (in_array($normalizedIpAddress, ['::1', '0:0:0:0:0:0:0:1'], true)) {
            return true;
        }

        return filter_var(
            $ipAddress,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
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
