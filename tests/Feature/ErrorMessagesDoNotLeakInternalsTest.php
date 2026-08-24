<?php

namespace Tests\Feature;

use App\Livewire\MineAreaManager;
use App\Livewire\Settings;
use App\Models\MineArea;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\MineAreaService;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Laravel\Jetstream\Contracts\InvitesTeamMembers;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 19 places across the app concatenated $e->getMessage() (or Stripe
 * exceptions, DB constraint violations, etc.) directly into a toast,
 * session flash message, API JSON response, or a stored Report::error_message
 * later shown in a tooltip -- all reaching the end user verbatim. Besides
 * being confusing jargon for a normal user, this is a real information
 * disclosure: SQL error text can reveal table/column names, file-system
 * errors can reveal paths, and third-party API errors (Stripe, manufacturer
 * integrations) can reveal internal request details. The real message is
 * still logged server-side in every case; only what reaches the browser
 * changed.
 */
class ErrorMessagesDoNotLeakInternalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mine_area_save_failure_shows_a_friendly_message_not_the_raw_exception(): void
    {
        Log::spy();

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);
        TeamRoleProvisioner::assignRole($user, $team, 'admin');

        $this->partialMock(MineAreaService::class, function ($mock) {
            $mock->shouldReceive('create')
                ->andThrow(new \RuntimeException('SQLSTATE[23505]: duplicate key value violates unique constraint "mine_areas_pkey" on connection pgsql'));
        });

        Livewire::actingAs($user)
            ->test(MineAreaManager::class)
            ->set('name', 'Test Area')
            ->set('status', 'active')
            ->call('saveMineArea')
            ->assertDispatched('notify', function ($name, $params) {
                // Named-args dispatch: the payload IS the assoc array.
                $event = $params;

                return $event['type'] === 'error'
                    && ! str_contains($event['message'], 'SQLSTATE')
                    && ! str_contains($event['message'], 'constraint')
                    && str_contains($event['message'], "couldn't save");
            });

        Log::shouldHaveReceived('error')->withArgs(function ($message, $context) {
            return str_contains($context['error'] ?? '', 'SQLSTATE');
        })->once();
    }

    public function test_mine_area_delete_failure_shows_a_friendly_message_not_the_raw_exception(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);
        TeamRoleProvisioner::assignRole($user, $team, 'admin');
        $mineArea = MineArea::create([
            'team_id' => $team->id,
            'name' => 'Test Area',
            'status' => 'active',
        ]);

        $this->partialMock(MineAreaService::class, function ($mock) {
            $mock->shouldReceive('delete')
                ->andThrow(new \RuntimeException('SQLSTATE[23503]: foreign key violation on table "geofences"'));
        });

        Livewire::actingAs($user)
            ->test(MineAreaManager::class)
            ->call('deleteMineArea', $mineArea)
            ->assertDispatched('notify', function ($name, $params) {
                // Named-args dispatch: the payload IS the assoc array.
                $event = $params;

                return $event['type'] === 'error'
                    && ! str_contains($event['message'], 'SQLSTATE')
                    && ! str_contains($event['message'], 'foreign key');
            });
    }

    public function test_invite_failure_shows_a_friendly_message(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->update(['current_team_id' => $team->id]);
        Role::factory()->create(['name' => 'operator', 'team_id' => $team->id]);

        // Force the underlying Jetstream invite action to blow up with a
        // message that would previously have been shown verbatim.
        $this->mock(InvitesTeamMembers::class, function ($mock) {
            $mock->shouldReceive('invite')
                ->andThrow(new \RuntimeException('SQLSTATE[23505]: duplicate key value violates unique constraint "team_invitations_email_team_id_unique"'));
        });

        Livewire::actingAs($owner)
            ->test(Settings::class)
            ->set('inviteEmail', 'newperson@example.com')
            ->set('selectedRole', 'operator')
            ->call('inviteUser')
            ->assertDispatched('notify', function ($name, $params) {
                // Named-args dispatch: the payload IS the assoc array.
                $event = $params;

                return $event['type'] === 'error'
                    && ! str_contains($event['message'], 'SQLSTATE')
                    && ! str_contains($event['message'], 'constraint');
            });
    }

    /**
     * Systemic guardrail: no Livewire component, controller, or job may
     * concatenate a caught exception's message directly into user-facing
     * output (a notify/flash/JSON response). This greps for the exact
     * pattern fixed in this change and fails if it -- or a new instance of
     * the same shape -- reappears anywhere in app/.
     */
    public function test_no_raw_exception_message_reaches_user_facing_output_anywhere_in_app(): void
    {
        $offenders = [];

        $files = collect(File::allFiles(app_path()))
            ->filter(fn ($f) => $f->getExtension() === 'php');

        foreach ($files as $file) {
            $contents = File::get($file->getPathname());
            $lines = explode("\n", $contents);

            foreach ($lines as $lineNumber => $line) {
                $isUserFacingCall = str_contains($line, "dispatchBrowserEvent('notify'")
                    || str_contains($line, '->flash(')
                    || (str_contains($line, "'error' =>") && str_contains($line, 'json'));

                $hasRawExceptionMessage = preg_match('/\$e(?:xception)?->getMessage\(\)/', $line) === 1;

                if ($isUserFacingCall && $hasRawExceptionMessage) {
                    $offenders[] = $file->getRelativePathname().':'.($lineNumber + 1).' — '.trim($line);
                }
            }
        }

        $this->assertEmpty($offenders, "Raw exception messages reaching user-facing output:\n".implode("\n", $offenders));
    }
}
