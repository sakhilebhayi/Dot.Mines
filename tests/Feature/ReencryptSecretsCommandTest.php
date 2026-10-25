<?php

namespace Tests\Feature;

use App\Models\Integration;
use App\Models\User;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Finishing an APP_KEY rotation: nothing may stay readable only by the old (leaked) key. */
class ReencryptSecretsCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $oldKey;

    private string $newKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->oldKey = 'base64:'.base64_encode(Encrypter::generateKey(config('app.cipher')));
        $this->newKey = 'base64:'.base64_encode(Encrypter::generateKey(config('app.cipher')));
    }

    private function useKeys(string $key, array $previous = []): void
    {
        config(['app.key' => $key, 'app.previous_keys' => $previous]);
        $this->app->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');
    }

    public function test_values_written_under_the_old_key_move_to_the_new_key(): void
    {
        $this->useKeys($this->oldKey);
        $integration = Integration::factory()->create(['credentials' => ['client_secret' => 'hunter2-not-real']]);
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['two_factor_secret' => Crypt::encryptString('TOTPSECRET')]);

        $this->useKeys($this->newKey, [$this->oldKey]);

        $this->artisan('secrets:reencrypt', ['--check' => true])
            ->expectsOutputToContain('not readable with the current key alone')
            ->assertFailed();

        $this->artisan('secrets:reencrypt')->expectsOutputToContain('Done')->assertSuccessful();
        $this->artisan('secrets:reencrypt', ['--check' => true])->expectsOutputToContain('Safe to remove APP_PREVIOUS_KEYS')->assertSuccessful();

        // Readable with the new key alone, and the application still sees the same data.
        $this->useKeys($this->newKey);
        $this->assertSame(['client_secret' => 'hunter2-not-real'], $integration->fresh()->credentials);
        $this->assertSame('TOTPSECRET', Crypt::decryptString((string) DB::table('users')->where('id', $user->id)->value('two_factor_secret')));
    }

    public function test_a_value_neither_key_can_read_is_left_alone_and_reported(): void
    {
        $this->useKeys($this->oldKey);
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['two_factor_secret' => Crypt::encryptString('x')]);
        $before = DB::table('users')->where('id', $user->id)->value('two_factor_secret');

        $this->useKeys($this->newKey);

        $this->artisan('secrets:reencrypt')->expectsOutputToContain('could not be handled')->assertFailed();
        $this->assertSame($before, DB::table('users')->where('id', $user->id)->value('two_factor_secret'));
    }

    public function test_nothing_to_do_is_a_clean_pass(): void
    {
        $this->artisan('secrets:reencrypt', ['--check' => true])->expectsOutputToContain('Safe to remove')->assertSuccessful();
    }
}
