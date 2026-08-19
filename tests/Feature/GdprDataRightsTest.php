<?php

namespace Tests\Feature;

use App\Jobs\DeleteUserDataJob;
use App\Jobs\ExportUserDataJob;
use App\Models\Alert;
use App\Models\GdprRequest;
use App\Models\Machine;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Jetstream\Contracts\DeletesUsers;
use Tests\TestCase;

/**
 * C5 slice of the #27 split: GDPR data-subject rights. Export produces a
 * tokenised 7-day download of everything stored about the user; deletion
 * tears the account down via Jetstream's DeleteUser action while
 * preserving the team's operational alert history and the compliance
 * record of the request itself.
 */
class GdprDataRightsTest extends TestCase
{
    use RefreshDatabase;

    private function userWithTeam(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->update(['current_team_id' => $user->personalTeam()->id]);

        return $user;
    }

    public function test_requesting_an_export_creates_a_request_row_and_queues_the_job(): void
    {
        Queue::fake();
        $user = $this->userWithTeam();

        $response = $this->actingAs($user)->post('/gdpr/export');

        $response->assertRedirect();
        $this->assertDatabaseHas('gdpr_requests', [
            'user_id' => $user->id,
            'type' => GdprRequest::TYPE_EXPORT,
            'status' => GdprRequest::STATUS_PENDING,
        ]);
        Queue::assertPushed(ExportUserDataJob::class);
    }

    public function test_export_job_writes_the_file_and_issues_a_download_token(): void
    {
        Storage::fake('local');
        Mail::fake();
        $user = $this->userWithTeam();
        $gdprRequest = GdprRequest::create([
            'user_id' => $user->id,
            'type' => GdprRequest::TYPE_EXPORT,
            'status' => GdprRequest::STATUS_PENDING,
            'email' => $user->email,
        ]);

        (new ExportUserDataJob($gdprRequest))->handle();

        $gdprRequest->refresh();
        $this->assertSame(GdprRequest::STATUS_COMPLETED, $gdprRequest->status);
        $this->assertNotNull($gdprRequest->download_token);
        Storage::disk('local')->assertExists($gdprRequest->file_path);

        $export = json_decode(Storage::disk('local')->get($gdprRequest->file_path), true);
        $this->assertSame($user->email, $export['profile']['email']);
        $this->assertArrayHasKey('operator_fatigue_records', $export);
        $this->assertArrayHasKey('activity_log', $export);
    }

    public function test_download_requires_the_owning_user_and_an_unexpired_token(): void
    {
        Storage::fake('local');
        Mail::fake();
        $user = $this->userWithTeam();
        $gdprRequest = GdprRequest::create([
            'user_id' => $user->id,
            'type' => GdprRequest::TYPE_EXPORT,
            'status' => GdprRequest::STATUS_PENDING,
            'email' => $user->email,
        ]);
        (new ExportUserDataJob($gdprRequest))->handle();
        $token = $gdprRequest->refresh()->download_token;

        $this->actingAs($user)->get("/gdpr/download/{$token}")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json');

        $stranger = $this->userWithTeam();
        $this->actingAs($stranger)->get("/gdpr/download/{$token}")->assertNotFound();

        $gdprRequest->update(['token_expires_at' => now()->subDay()]);
        $this->actingAs($user)->get("/gdpr/download/{$token}")->assertRedirect(route('profile.show'));
    }

    public function test_deletion_removes_the_user_but_preserves_alert_history_and_the_compliance_record(): void
    {
        Mail::fake();
        $user = $this->userWithTeam();

        // The alert lives on a team the user MERELY BELONGS TO -- that team
        // survives the account deletion (owned teams are torn down with the
        // account, taking their own data with them by design).
        $owner = $this->userWithTeam();
        $team = $owner->personalTeam();
        $team->users()->attach($user, ['role' => 'operator']);
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        $alert = Alert::create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'type' => 'sensor',
            'title' => 'Engine overheat',
            'description' => 'Engine temperature exceeded the safe threshold.',
            'priority' => 'high',
            'status' => 'acknowledged',
            'triggered_at' => now(),
            'acknowledged_at' => now(),
            'acknowledged_by' => $user->id,
        ]);

        $gdprRequest = GdprRequest::create([
            'user_id' => $user->id,
            'type' => GdprRequest::TYPE_DELETE,
            'status' => GdprRequest::STATUS_PENDING,
            'email' => $user->email,
        ]);

        (new DeleteUserDataJob($gdprRequest))->handle(app(DeletesUsers::class));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);

        // The alert must survive with the personal reference removed --
        // acknowledged_by is cascadeOnDelete, so without the pre-null the
        // team's alert history would vanish with the departing user.
        $this->assertDatabaseHas('alerts', ['id' => $alert->id, 'acknowledged_by' => null]);

        // The compliance record survives the user (user_id nulls out).
        $gdprRequest->refresh();
        $this->assertSame(GdprRequest::STATUS_COMPLETED, $gdprRequest->status);
        $this->assertNull($gdprRequest->user_id);
        $this->assertSame($user->email, $gdprRequest->email);
    }

    public function test_deletion_endpoint_requires_typed_confirmation_and_logs_the_user_out(): void
    {
        Queue::fake();
        $user = $this->userWithTeam();

        $this->actingAs($user)->post('/gdpr/delete', ['confirmation' => 'nope'])
            ->assertSessionHasErrors('confirmation');
        Queue::assertNotPushed(DeleteUserDataJob::class);

        $response = $this->actingAs($user)->post('/gdpr/delete', ['confirmation' => 'DELETE']);

        $response->assertRedirect('/');
        Queue::assertPushed(DeleteUserDataJob::class);
        $this->assertGuest();
    }

    public function test_privacy_page_renders_request_history(): void
    {
        $user = $this->userWithTeam();
        GdprRequest::create([
            'user_id' => $user->id,
            'type' => GdprRequest::TYPE_EXPORT,
            'status' => GdprRequest::STATUS_COMPLETED,
            'email' => $user->email,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/gdpr');

        $response->assertOk();
        $response->assertSee('Privacy');
        $response->assertSee('Export');
    }
}
