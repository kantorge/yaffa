<?php

namespace App\Services;

use App\Exceptions\UnsafeEndpointUrlException;
use Illuminate\Support\Str;

/**
 * Blocks outbound requests to loopback, private, and link-local hosts to
 * prevent SSRF. Shared by every provider that fetches a user-supplied URL
 * (generic API price provider, web scraping price provider, ...).
 */
class PublicEndpointUrlValidator
{
    /**
     * @return array<int, string> the resolved IP addresses for the host, or an empty
     *                             array if the host could not be resolved (not blocked in that case)
     *
     * @throws UnsafeEndpointUrlException
     */
    public static function assertPublic(string $url): array
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || Str::trim($host) === '') {
            throw new UnsafeEndpointUrlException('Endpoint URL must include a valid host.');
        }

        $normalizedHost = Str::lower(mb_trim($host, '[]'));

        if ($normalizedHost === 'localhost') {
            throw new UnsafeEndpointUrlException('Endpoint URL must resolve to a public IP address.');
        }

        $resolvedIps = self::resolveIps($normalizedHost);

        foreach ($resolvedIps as $resolvedIp) {
            if (self::isDisallowedIp($resolvedIp)) {
                throw new UnsafeEndpointUrlException('Endpoint URL must resolve to a public IP address.');
            }
        }

        return $resolvedIps;
    }

    /**
     * @return array<int, string>
     */
    private static function resolveIps(string $host): array
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

    private static function isDisallowedIp(string $ipAddress): bool
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
}
