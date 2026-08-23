<?php

namespace App\Services\Webhooks;

/**
 * Decides whether a user-supplied URL is safe for the server to POST to.
 *
 * A webhook feature is a request forgery primitive if you let it be one: the
 * user names a URL and the server fetches it, from inside the network, with
 * whatever the network trusts. Point one at 127.0.0.1, at a private subnet,
 * or at a cloud metadata address and the response comes back through the
 * delivery log. So the URL is checked here at two moments -- when it is saved
 * and again immediately before each send, because DNS can change in between.
 *
 * The rules:
 *   - https only in production (http is allowed in dev so a local receiver
 *     can be used while building an integration)
 *   - no credentials in the URL
 *   - every address the host resolves to must be a public one
 */
class WebhookUrlGuard
{
    public function __construct(private readonly HostResolver $resolver) {}

    /**
     * @return string|null the reason it was rejected, or null if it is allowed
     */
    public function rejectionReason(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host']) || ! isset($parts['scheme'])) {
            return 'The URL could not be parsed.';
        }

        $scheme = strtolower($parts['scheme']);
        $allowed = app()->isProduction() ? ['https'] : ['https', 'http'];

        if (! in_array($scheme, $allowed, true)) {
            return app()->isProduction()
                ? 'Webhook URLs must use https.'
                : 'Webhook URLs must use http or https.';
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'Webhook URLs must not contain credentials. Use the signing secret instead.';
        }

        $host = $parts['host'];
        $addresses = $this->resolver->resolve($host);

        if ($addresses === []) {
            return "The host {$host} could not be resolved.";
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicAddress($address)) {
                return "The host {$host} resolves to {$address}, which is not a public address.";
            }
        }

        return null;
    }

    /**
     * Private, loopback, link-local and reserved ranges are all off limits --
     * FILTER_FLAG_NO_PRIV_RANGE and NO_RES_RANGE between them cover 10/8,
     * 172.16/12, 192.168/16, 127/8, 169.254/16 (the cloud metadata address
     * lives here), ::1 and fc00::/7.
     */
    private function isPublicAddress(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
