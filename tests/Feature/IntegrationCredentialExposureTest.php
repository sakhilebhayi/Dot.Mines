<?php

namespace Tests\Feature;

use App\Models\Integration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifies that integration credentials are never exposed in HTTP responses
 * or DB plaintext.
 */
class IntegrationCredentialExposureTest extends TestCase
{
    use RefreshDatabase;

    private Integration $integration;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $this->integration = Integration::factory()->create([
            'team_id' => $team->id,
            'provider' => 'bell',
            'name' => 'Test Bell',
            'credentials' => ['username' => 'secret_user', 'password' => 'secret_pass', 'client_secret' => 'secret_key'],
            'status' => 'connected',
        ]);
    }

    #[Test]
    public function credentials_are_encrypted_in_database(): void
    {
        $raw = DB::table('integrations')->where('id', $this->integration->id)->value('credentials');

        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('secret_user', (string) $raw);
        $this->assertStringNotContainsString('secret_pass', (string) $raw);
        $this->assertStringNotContainsString('secret_key', (string) $raw);
    }

    #[Test]
    public function credentials_cast_decrypts_correctly(): void
    {
        $fresh = Integration::find($this->integration->id);

        $this->assertIsArray($fresh->credentials);
        $this->assertSame('secret_user', $fresh->credentials['username']);
        $this->assertSame('secret_pass', $fresh->credentials['password']);
    }

    #[Test]
    public function credentials_are_hidden_from_json_serialisation(): void
    {
        $array = $this->integration->toArray();

        $this->assertArrayNotHasKey('credentials', $array);
        $this->assertArrayNotHasKey('api_key', $array);
        $this->assertArrayNotHasKey('api_secret', $array);
        $this->assertArrayNotHasKey('webhook_secret', $array);
    }
}
