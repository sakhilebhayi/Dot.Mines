<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EcosystemErrorPagesTest extends TestCase
{
    #[DataProvider('errorCodes')]
    public function test_error_page_renders_branded_content(int $code, string $expectedHeading): void
    {
        $response = $this->get("/_test-only-error-render/{$code}");

        $response->assertStatus($code);
        $response->assertSee($expectedHeading);
        $response->assertSee('Dot.Mines', false);
        $response->assertSee('the rest of the Dot Ecosystem', false);
    }

    public static function errorCodes(): array
    {
        return [
            '404 not found' => [404, "We couldn't find that page"],
            '403 forbidden' => [403, "You don't have access to this"],
            '419 page expired' => [419, 'Your session timed out'],
            '429 too many requests' => [429, 'Slow down a little'],
            '500 server error' => [500, 'Something went wrong on our end'],
            '503 unavailable' => [503, "We'll be right back"],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->get('/_test-only-error-render/{code}', function (int $code) {
            return response()->view("errors.{$code}", [], $code);
        });
    }

    /**
     * The "Contact support" link was guarded by Route::has('contact') -- a
     * route that has never existed, so error pages silently offered no
     * support path at all. It now links the real support mailbox.
     */
    public function test_error_pages_offer_a_real_contact_path(): void
    {
        config(['mail.addresses.support' => 'support@example.test']);

        $response = $this->get('/_test-only-error-render/500');

        $response->assertSee('Contact support');
        $response->assertSee('mailto:support@example.test', false);
    }
}
