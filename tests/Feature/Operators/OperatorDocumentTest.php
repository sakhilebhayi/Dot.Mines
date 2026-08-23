<?php

namespace Tests\Feature\Operators;

use App\Livewire\OperatorDetail;
use App\Models\Operator;
use App\Models\OperatorDocument;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Operator documents: private storage, permissioned reads, audited downloads.
 */
class OperatorDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function actingAs2FA(string $role): User
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->forceFill(['current_team_id' => $team->id, 'two_factor_confirmed_at' => now()])->save();
        TeamRoleProvisioner::assignRole($user, $team, $role);
        $this->actingAs($user->fresh());

        return $user->fresh();
    }

    private function upload(Operator $operator, string $kind = 'licence', string $title = 'ADT licence scan'): OperatorDocument
    {
        Livewire::test(OperatorDetail::class, ['operator' => $operator])
            ->set('documentTitle', $title)
            ->set('documentKind', $kind)
            ->set('documentFile', UploadedFile::fake()->create('scan.pdf', 200, 'application/pdf'))
            ->call('uploadDocument')
            ->assertHasNoErrors();

        return $operator->documents()->latest('id')->firstOrFail();
    }

    public function test_a_document_uploads_to_private_storage_not_a_public_url(): void
    {
        $user = $this->actingAs2FA('admin');
        $operator = Operator::factory()->create(['team_id' => $user->current_team_id]);

        $document = $this->upload($operator);

        Storage::disk('local')->assertExists($document->path);
        $this->assertStringStartsWith('operator-documents/', $document->path);

        // The serialised row never leaks where the file lives.
        $payload = $document->toArray();
        $this->assertArrayNotHasKey('path', $payload);
        $this->assertArrayNotHasKey('disk', $payload);
    }

    public function test_the_download_route_serves_the_file_and_audits_it(): void
    {
        $user = $this->actingAs2FA('admin');
        $operator = Operator::factory()->create(['team_id' => $user->current_team_id]);
        $document = $this->upload($operator);

        $response = $this->get(route('operators.documents.download', $document));

        $response->assertOk();
        $response->assertDownload('scan.pdf');

        $this->assertDatabaseHas('activity_logs', [
            'team_id' => $operator->team_id,
            'user_id' => $user->id,
            'action' => 'operator_document_downloaded',
        ]);
    }

    public function test_an_executable_or_html_upload_is_refused(): void
    {
        $user = $this->actingAs2FA('admin');
        $operator = Operator::factory()->create(['team_id' => $user->current_team_id]);

        Livewire::test(OperatorDetail::class, ['operator' => $operator])
            ->set('documentTitle', 'Nasty')
            ->set('documentKind', 'other')
            ->set('documentFile', UploadedFile::fake()->create('evil.html', 5, 'text/html'))
            ->call('uploadDocument')
            ->assertHasErrors('documentFile');

        $this->assertSame(0, $operator->documents()->count());
    }

    public function test_another_teams_document_is_unreachable(): void
    {
        $otherTeam = Team::factory()->create();
        $theirOperator = Operator::factory()->create(['team_id' => $otherTeam->id]);
        $theirDocument = OperatorDocument::create([
            'team_id' => $otherTeam->id,
            'operator_id' => $theirOperator->id,
            'kind' => 'licence',
            'title' => 'Theirs',
            'disk' => 'local',
            'path' => 'operator-documents/x/y/theirs.pdf',
            'original_name' => 'theirs.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
        ]);

        $this->actingAs2FA('admin');

        $this->get(route('operators.documents.download', $theirDocument->id))->assertNotFound();
    }

    public function test_a_medical_document_needs_the_medical_permission_to_download(): void
    {
        // Admin uploads a medical scan...
        $admin = $this->actingAs2FA('admin');
        $operator = Operator::factory()->create(['team_id' => $admin->current_team_id]);
        $document = $this->upload($operator, 'medical', 'Medical certificate scan');

        // ...a fleet_manager on the same team can manage operators but has no
        // medical permission: the document is invisible AND undownloadable.
        $manager = User::factory()->create();
        $manager->forceFill(['current_team_id' => $admin->current_team_id, 'two_factor_confirmed_at' => now()])->save();
        TeamRoleProvisioner::assignRole($manager, Team::findOrFail($admin->current_team_id), 'fleet_manager');
        $this->actingAs($manager->fresh());

        $this->get(route('operators.documents.download', $document))->assertForbidden();

        $visible = Livewire::test(OperatorDetail::class, ['operator' => $operator->fresh()])
            ->instance()->getVisibleDocumentsProperty();
        $this->assertTrue($visible->isEmpty(), 'Medical documents must not be listed without the permission.');
    }

    public function test_a_fleet_manager_cannot_upload_a_medical_document(): void
    {
        $user = $this->actingAs2FA('fleet_manager');
        $operator = Operator::factory()->create(['team_id' => $user->current_team_id]);

        Livewire::test(OperatorDetail::class, ['operator' => $operator])
            ->set('documentTitle', 'Medical scan')
            ->set('documentKind', 'medical')
            ->set('documentFile', UploadedFile::fake()->create('med.pdf', 10, 'application/pdf'))
            ->call('uploadDocument')
            ->assertForbidden();

        $this->assertSame(0, $operator->documents()->count());
    }

    public function test_deleting_keeps_the_row_for_audit(): void
    {
        $user = $this->actingAs2FA('admin');
        $operator = Operator::factory()->create(['team_id' => $user->current_team_id]);
        $document = $this->upload($operator);

        Livewire::test(OperatorDetail::class, ['operator' => $operator])
            ->call('deleteDocument', $document->id);

        $this->assertSoftDeleted('operator_documents', ['id' => $document->id]);
        $this->get(route('operators.documents.download', $document->id))->assertNotFound();
    }
}
