<?php

namespace App\Services;

use InvalidArgumentException;

class UrlSafetyService
{
    /** @var array<string, bool> */
    private array $safeHostCache = [];

    /**
     * Validate a URL before the server connects to it.
     *
     * @param string[] $allowedHosts Empty means any publicly routable host is allowed.
     */
    public function assertSafeUrl(string $url, array $allowedHosts = []): string
    {
        $parts = parse_url($url);
        if (!is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
        ) {
            throw new InvalidArgumentException('Only absolute HTTP and HTTPS URLs are allowed.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('URLs containing credentials are not allowed.');
        }

        $host = $this->normalizeHost((string)$parts['host']);
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            throw new InvalidArgumentException('Localhost URLs are not allowed.');
        }

        if ($allowedHosts !== [] && !$this->matchesAllowedHost($host, $allowedHosts)) {
            throw new InvalidArgumentException("URL host '{$host}' is not allowed for this source.");
        }

        $this->assertPublicHost($host);
        return $url;
    }

    /** @param string[] $allowedHosts */
    private function matchesAllowedHost(string $host, array $allowedHosts): bool
    {
        foreach ($allowedHosts as $allowedHost) {
            $allowedHost = $this->normalizeHost($allowedHost);
            if ($allowedHost !== ''
                && ($host === $allowedHost || str_ends_with($host, '.' . $allowedHost))
            ) {
                return true;
            }
        }

        return false;
    }

    private function assertPublicHost(string $host): void
    {
        if (array_key_exists($host, $this->safeHostCache)) {
            if (!$this->safeHostCache[$host]) {
                throw new InvalidArgumentException("URL host '{$host}' resolves to a non-public address.");
            }
            return;
        }

        $addresses = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $addresses[] = $host;
        } else {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (isset($record['ip'])) {
                        $addresses[] = $record['ip'];
                    }
                    if (isset($record['ipv6'])) {
                        $addresses[] = $record['ipv6'];
                    }
                }
            }

            if ($addresses === []) {
                $ipv4Addresses = gethostbynamel($host);
                if (is_array($ipv4Addresses)) {
                    $addresses = array_merge($addresses, $ipv4Addresses);
                }
            }
        }

        $safe = $addresses !== [];
        foreach (array_unique($addresses) as $address) {
            if (!filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            )) {
                $safe = false;
                break;
            }
        }

        $this->safeHostCache[$host] = $safe;
        if (!$safe) {
            throw new InvalidArgumentException("URL host '{$host}' does not resolve exclusively to public addresses.");
        }
    }

    private function normalizeHost(string $host): string
    {
        $host = trim(strtolower(rtrim(trim($host), '.')), '[]');
        if (function_exists('idn_to_ascii') && !filter_var($host, FILTER_VALIDATE_IP)) {
            $asciiHost = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if (is_string($asciiHost)) {
                $host = strtolower($asciiHost);
            }
        }

        return $host;
    }
}
