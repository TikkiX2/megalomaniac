<?php

declare(strict_types=1);

namespace App\Inspiration\Support;

/**
 * SSRF guard for server-side downloads.
 *
 * The inspiration flow fetches user-supplied image URLs from queued jobs, so
 * both the save validation (App\Rules\SecureHttpUrl) and the job redirect guard
 * share the same host classification. A host is "private" when it is a
 * loopback/link-local/RFC1918/CGNAT/multicast/reserved literal, a reserved
 * hostname suffix (`localhost`, `*.local`, `*.internal`), or a hostname that
 * fails to resolve or resolves to any blocked address.
 */
final class UrlSafety
{
    /**
     * Non-routable / internal IPv4 blocks that must never be fetched.
     *
     * @var array<int, string>
     */
    private const BLOCKED_IPV4 = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '224.0.0.0/4',
        '240.0.0.0/4',
    ];

    /**
     * Non-routable / internal IPv6 blocks that must never be fetched.
     *
     * @var array<int, string>
     */
    private const BLOCKED_IPV6 = [
        '::/128',
        '::1/128',
        'fc00::/7',
        'fe80::/10',
    ];

    /**
     * @var array<int, string>
     */
    private const BLOCKED_HOSTS = ['localhost'];

    /**
     * @var array<int, string>
     */
    private const BLOCKED_HOST_SUFFIXES = ['.localhost', '.local', '.internal'];

    public static function hasHttpScheme(string $url): bool
    {
        $scheme = parse_url(trim($url), PHP_URL_SCHEME);

        return is_string($scheme) && in_array(strtolower($scheme), ['http', 'https'], true);
    }

    /**
     * @param  (callable(string): array<int, string>)|null  $resolver
     */
    public static function isPublicHttpUrl(string $url, ?callable $resolver = null): bool
    {
        if (! self::hasHttpScheme($url)) {
            return false;
        }

        return self::hostIsPublic((string) parse_url(trim($url), PHP_URL_HOST), $resolver);
    }

    /**
     * @param  (callable(string): array<int, string>)|null  $resolver
     */
    public static function hostIsPublic(?string $host, ?callable $resolver = null): bool
    {
        return ! self::hostIsPrivate($host, $resolver);
    }

    /**
     * @param  (callable(string): array<int, string>)|null  $resolver
     */
    public static function hostIsPrivate(?string $host, ?callable $resolver = null): bool
    {
        if ($host === null) {
            return true;
        }

        $host = strtolower(trim($host, "[] \t\n\r\0\x0B"));

        if ($host === '' || in_array($host, self::BLOCKED_HOSTS, true)) {
            return true;
        }

        foreach (self::BLOCKED_HOST_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return self::ipv4IsBlocked($host);
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return self::ipv6IsBlocked($host);
        }

        // Hostname: reject when it cannot be resolved (DNS rebinding is cheaper
        // to refuse than to chase) or when any A/AAAA record is blocked.
        $addresses = self::resolve($host, $resolver);

        if ($addresses === []) {
            return true;
        }

        foreach ($addresses as $address) {
            if (self::hostIsPrivate($address)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a request that ended on the given effective URI escaped to a
     * private/reserved host. `null` means transfer stats are unavailable (for
     * example a faked HTTP client), so the original save-time validation stands.
     */
    public static function redirectTargetIsPrivate(?string $effectiveUri): bool
    {
        if ($effectiveUri === null || $effectiveUri === '') {
            return false;
        }

        $host = parse_url($effectiveUri, PHP_URL_HOST);

        return ! is_string($host) || self::hostIsPrivate($host);
    }

    private static function ipv4IsBlocked(string $ip): bool
    {
        return self::matchesAnyCidr($ip, self::BLOCKED_IPV4);
    }

    private static function ipv6IsBlocked(string $ip): bool
    {
        $mapped = self::mappedIpv4($ip);

        if ($mapped !== null) {
            return self::ipv4IsBlocked($mapped);
        }

        return self::matchesAnyCidr($ip, self::BLOCKED_IPV6);
    }

    /**
     * Extract the IPv4 address embedded in an IPv4-mapped IPv6 literal.
     */
    private static function mappedIpv4(string $ip): ?string
    {
        $packed = @inet_pton($ip);

        if ($packed === false || strlen($packed) !== 16) {
            return null;
        }

        if (substr($packed, 0, 10) !== str_repeat("\x00", 10) || substr($packed, 10, 2) !== "\xff\xff") {
            return null;
        }

        $mapped = @inet_ntop(substr($packed, 12, 4));

        return is_string($mapped) ? $mapped : null;
    }

    /**
     * @param  array<int, string>  $cidrs
     */
    private static function matchesAnyCidr(string $ip, array $cidrs): bool
    {
        foreach ($cidrs as $cidr) {
            if (self::inCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    private static function inCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, '32');

        $ipBinary = @inet_pton($ip);
        $subnetBinary = @inet_pton($subnet);

        if ($ipBinary === false || $subnetBinary === false || strlen($ipBinary) !== strlen($subnetBinary)) {
            return false;
        }

        $prefix = (int) $bits;
        $fullBytes = intdiv($prefix, 8);

        if ($fullBytes > 0 && substr($ipBinary, 0, $fullBytes) !== substr($subnetBinary, 0, $fullBytes)) {
            return false;
        }

        $partialBits = $prefix % 8;

        if ($partialBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $partialBits)) & 0xFF;

        return (ord($ipBinary[$fullBytes]) & $mask) === (ord($subnetBinary[$fullBytes]) & $mask);
    }

    /**
     * @param  (callable(string): array<int, string>)|null  $resolver
     * @return array<int, string>
     */
    private static function resolve(string $host, ?callable $resolver): array
    {
        if ($resolver !== null) {
            return array_values(array_filter(
                (array) $resolver($host),
                static fn (mixed $ip): bool => is_string($ip) && $ip !== '',
            ));
        }

        $addresses = [];

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        foreach (is_array($records) ? $records : [] as $record) {
            foreach (['ip', 'ipv6'] as $key) {
                if (isset($record[$key]) && is_string($record[$key])) {
                    $addresses[] = $record[$key];
                }
            }
        }

        if ($addresses === []) {
            $legacy = @gethostbynamel($host);

            if (is_array($legacy)) {
                $addresses = $legacy;
            }
        }

        return array_values(array_unique($addresses));
    }
}
