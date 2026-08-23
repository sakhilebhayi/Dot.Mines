<?php

namespace App\Services\Webhooks;

/**
 * Resolves a hostname to the IP addresses a request to it would reach.
 *
 * Separated from WebhookUrlGuard so the guard can be tested against chosen
 * addresses without touching DNS -- a test that has to resolve a real domain
 * is a test that fails when the network does.
 */
class HostResolver
{
    /**
     * @return list<string> every address the host resolves to, empty if none
     */
    public function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $addresses = [];

        $v4 = gethostbynamel($host);

        if (is_array($v4)) {
            foreach ($v4 as $address) {
                $addresses[] = $address;
            }
        }

        $v6 = @dns_get_record($host, DNS_AAAA);

        if (is_array($v6)) {
            foreach ($v6 as $record) {
                if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($addresses));
    }
}
