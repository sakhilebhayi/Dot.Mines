<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Slice 0 of the hybrid architecture spec: the engine-copy command that
 * migrates the production authority from SQLite to MySQL. CI exercises it
 * sqlite -> sqlite (a second in-memory connection); the engine-specific
 * rehearsal happens on the production host against the real MySQL target.
 */
class CopyDatabaseEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.engine_target' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
    }

    protected function tearDown(): void
    {
        DB::purge('engine_target');

        parent::tearDown();
    }

    public function test_copies_all_application_data_and_verifies(): void
    {
        User::factory()->withPersonalTeam()->count(3)->create();

        $this->artisan('db:engine-copy', [
            '--from' => config('database.default'),
            '--to' => 'engine_target',
        ])->assertSuccessful();

        $this->assertSame(3, DB::connection('engine_target')->table('users')->count());
        $this->assertSame(
            DB::table('teams')->count(),
            DB::connection('engine_target')->table('teams')->count(),
        );
        $this->assertSame(
            (int) DB::table('users')->sum('id'),
            (int) DB::connection('engine_target')->table('users')->sum('id'),
        );
    }

    public function test_refuses_a_non_empty_target_without_fresh(): void
    {
        User::factory()->withPersonalTeam()->create();

        $options = ['--from' => config('database.default'), '--to' => 'engine_target'];

        $this->artisan('db:engine-copy', $options)->assertSuccessful();
        $this->artisan('db:engine-copy', $options)->assertFailed();

        // --fresh wipes the target and the copy succeeds again.
        $this->artisan('db:engine-copy', $options + ['--fresh' => true])->assertSuccessful();
        $this->assertSame(1, DB::connection('engine_target')->table('users')->count());
    }

    public function test_transient_tables_are_never_copied(): void
    {
        User::factory()->withPersonalTeam()->create();
        DB::table('cache')->insert(['key' => 'probe', 'value' => 'x', 'expiration' => time() + 60]);

        $this->artisan('db:engine-copy', [
            '--from' => config('database.default'),
            '--to' => 'engine_target',
        ])->assertSuccessful();

        $this->assertSame(0, DB::connection('engine_target')->table('cache')->count());
    }

    public function test_rejects_missing_or_identical_connections(): void
    {
        $this->artisan('db:engine-copy', ['--from' => 'sqlite', '--to' => 'sqlite'])->assertFailed();
        $this->artisan('db:engine-copy', ['--from' => config('database.default'), '--to' => 'nope'])->assertFailed();
    }
}
