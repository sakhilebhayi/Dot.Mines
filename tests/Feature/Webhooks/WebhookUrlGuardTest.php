<?php

namespace Tests\Feature\Webhooks;

use App\Services\Webhooks\HostResolver;
use App\Services\Webhooks\WebhookUrlGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A webhook feature is a server-side request forgery primitive unless it is
 * stopped from being one: the user names a URL and the server fetches it,
 * from inside the network, with whatever that network trusts.
 *
 * DNS is stubbed so these assertions are about the rules, not about what
 * example.com happens to resolve to on the CI runner today.
 */
class WebhookUrlGuardTest extends TestCase
{
    private function guardResolving(string ...$addresses): WebhookUrlGuard
    {
        $resolver = new class(...$addresses) extends HostResolver
        {
            /** @var list<string> */
            private array $addresses;

            public function __construct(string ...$addresses)
            {
                $this->addresses = array_values($addresses);
            }

            public function resolve(string $host): array
            {
                return $this->addresses;
            }
        };

        return new WebhookUrlGuard($resolver);
    }

    public function test_a_public_https_url_is_allowed(): void
    {
        $guard = $this->guardResolving('203.0.113.10');

        $this->assertNull($guard->rejectionReason('https://hooks.example.com/mines'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function internalAddresses(): array
    {
        return [
            'loopback' => ['127.0.0.1', 'localhost'],
            'private 10/8' => ['10.0.0.5', 'internal.example.com'],
            'private 172.16/12' => ['172.16.4.9', 'internal.example.com'],
            'private 192.168/16' => ['192.168.1.20', 'router.example.com'],
            'cloud metadata' => ['169.254.169.254', 'metadata.example.com'],
            'ipv6 loopback' => ['::1', 'localhost6'],
            'ipv6 unique local' => ['fd00::1', 'internal6.example.com'],
        ];
    }

    #[DataProvider('internalAddresses')]
    public function test_a_host_resolving_inside_the_network_is_refused(string $address, string $host): void
    {
        $guard = $this->guardResolving($address);

        $reason = $guard->rejectionReason("https://{$host}/hook");

        $this->assertNotNull($reason, "{$address} must not be reachable through a webhook.");
        $this->assertStringContainsString('not a public address', $reason);
    }

    public function test_a_host_is_refused_when_any_of_its_addresses_is_internal(): void
    {
        // A hostname with both a public and a private A record would
        // otherwise be a way in, depending on which address was picked.
        $guard = $this->guardResolving('203.0.113.10', '10.1.2.3');

        $this->assertNotNull($guard->rejectionReason('https://mixed.example.com/hook'));
    }

    public function test_a_bare_internal_ip_is_refused(): void
    {
        $guard = $this->guardResolving('127.0.0.1');

        $this->assertNotNull($guard->rejectionReason('https://127.0.0.1/hook'));
    }

    public function test_credentials_in_the_url_are_refused(): void
    {
        $guard = $this->guardResolving('203.0.113.10');

        $reason = $guard->rejectionReason('https://user:pass@hooks.example.com/hook');

        $this->assertNotNull($reason);
        $this->assertStringContainsString('signing secret', $reason);
    }

    public function test_a_host_that_does_not_resolve_is_refused(): void
    {
        $guard = $this->guardResolving();

        $this->assertNotNull($guard->rejectionReason('https://nowhere.example.com/hook'));
    }

    public function test_non_http_schemes_are_refused(): void
    {
        $guard = $this->guardResolving('203.0.113.10');

        foreach (['file:///etc/passwd', 'gopher://example.com/x', 'ftp://example.com/x'] as $url) {
            $this->assertNotNull($guard->rejectionReason($url), "{$url} must be refused.");
        }
    }

    public function test_production_requires_https(): void
    {
        $guard = $this->guardResolving('203.0.113.10');

        // http is allowed while developing against a local receiver, but a
        // real payload signed with a real secret must not cross the wire in
        // the clear.
        $this->assertNull($guard->rejectionReason('http://hooks.example.com/hook'));

        app()->detectEnvironment(fn (): string => 'production');

        $reason = $guard->rejectionReason('http://hooks.example.com/hook');

        $this->assertNotNull($reason);
        $this->assertStringContainsString('https', $reason);
    }
}
