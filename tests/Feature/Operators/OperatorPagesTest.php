<?php

namespace Tests\Feature\Operators;

use App\Livewire\OperatorDetail;
use App\Livewire\OperatorManager;
use App\Models\Operator;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use App\Support\EquipmentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The operator management and detail pages: create, credential capture,
 * filtering, tenancy, and who may see the medical section.
 */
class OperatorPagesTest extends TestCase
{
    use RefreshDatabase;

    private function actingAs2FA(string $role): User
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->forceFill(['current_team_id' => $team->id, 'two_factor_confirmed_at' => now()])->save();
        TeamRoleProvisioner::assignRole($user, $team, $role);
        $this->actingAs($user->fresh());

        return $user->fresh();
    }

    public function test_the_management_page_loads_and_lists_operators(): void
    {
        $user = $this->actingAs2FA('admin');
        Operator::factory()->compliantFor()->create([
            'team_id' => $user->current_team_id,
            'first_name' => 'John',
            'last_name' => 'Dlamini',
        ]);

        $this->get(route('operators.index'))->assertOk()->assertSeeLivewire(OperatorManager::class);

        Livewire::test(OperatorManager::class)
            ->assertSee('John Dlamini')
            ->assertSee('Compliant');
    }

    public function test_operators_from_another_team_are_invisible(): void
    {
        $this->actingAs2FA('admin');

        $otherTeam = Team::factory()->create();
        Operator::factory()->create([
            'team_id' => $otherTeam->id,
            'first_name' => 'Hidden',
            'last_name' => 'Person',
        ]);

        Livewire::test(OperatorManager::class)->assertDontSee('Hidden Person');
    }

    public function test_an_operator_can_be_created_with_a_unique_employee_number(): void
    {
        $user = $this->actingAs2FA('admin');

        Livewire::test(OperatorManager::class)
            ->set('form.employee_number', 'EMP-100')
            ->set('form.first_name', 'Thabo')
            ->set('form.last_name', 'Nkosi')
            ->call('create');

        $this->assertDatabaseHas('operators', [
            'team_id' => $user->current_team_id,
            'employee_number' => 'EMP-100',
        ]);

        // Same number again in the same team is refused.
        Livewire::test(OperatorManager::class)
            ->set('form.employee_number', 'EMP-100')
            ->set('form.first_name', 'Different')
            ->set('form.last_name', 'Person')
            ->call('create')
            ->assertHasErrors('form.employee_number');
    }

    public function test_the_same_employee_number_is_fine_in_a_different_team(): void
    {
        $otherTeam = Team::factory()->create();
        Operator::factory()->create(['team_id' => $otherTeam->id, 'employee_number' => 'EMP-9']);

        $this->actingAs2FA('admin');

        Livewire::test(OperatorManager::class)
            ->set('form.employee_number', 'EMP-9')
            ->set('form.first_name', 'Ours')
            ->set('form.last_name', 'Now')
            ->call('create')
            ->assertHasNoErrors();
    }

    public function test_compliance_counts_and_filter_agree_with_the_engine(): void
    {
        $user = $this->actingAs2FA('admin');

        Operator::factory()->compliantFor()->create(['team_id' => $user->current_team_id]);
        $lapsed = Operator::factory()->compliantFor()->create([
            'team_id' => $user->current_team_id,
            'first_name' => 'Lapsed',
            'last_name' => 'Licence',
        ]);
        $lapsed->qualifications()->update(['expires_on' => now()->subDay()->toDateString()]);

        $component = Livewire::test(OperatorManager::class);

        $counts = $component->instance()->getCountsProperty();
        $this->assertSame(2, $counts['total']);
        $this->assertSame(1, $counts['compliant']);
        $this->assertSame(1, $counts['non_compliant']);

        $component->set('complianceFilter', 'non_compliant')
            ->assertSee('Lapsed Licence');
    }

    public function test_the_detail_page_shows_compliance_and_the_authorisation_matrix(): void
    {
        $user = $this->actingAs2FA('admin');
        $operator = Operator::factory()->compliantFor(EquipmentType::ADT)->create(['team_id' => $user->current_team_id]);

        $this->get(route('operators.show', $operator))->assertOk()->assertSeeLivewire(OperatorDetail::class);

        $component = Livewire::test(OperatorDetail::class, ['operator' => $operator]);
        $authorisations = collect($component->instance()->getAuthorisationsProperty());

        $this->assertTrue($authorisations->firstWhere('type', EquipmentType::ADT)['authorised']);
        $this->assertFalse($authorisations->firstWhere('type', EquipmentType::DOZER)['authorised']);
    }

    public function test_another_teams_operator_detail_is_not_reachable(): void
    {
        $this->actingAs2FA('admin');
        $otherTeam = Team::factory()->create();
        $theirs = Operator::factory()->create(['team_id' => $otherTeam->id]);

        // The team scope makes it a 404, which also avoids confirming the id.
        $this->get(route('operators.show', $theirs->id))->assertNotFound();
    }

    public function test_a_qualification_can_be_added_from_the_detail_page(): void
    {
        $user = $this->actingAs2FA('admin');
        $operator = Operator::factory()->create(['team_id' => $user->current_team_id]);

        Livewire::test(OperatorDetail::class, ['operator' => $operator])
            ->set('qualificationForm.title', 'ADT Operator')
            ->set('qualificationForm.licence_number', 'ZA-12345')
            ->set('qualificationForm.equipment_type', EquipmentType::ADT)
            ->set('qualificationForm.issued_on', now()->subMonth()->toDateString())
            ->set('qualificationForm.expires_on', now()->addYears(2)->toDateString())
            ->call('addQualification');

        $this->assertDatabaseHas('operator_qualifications', [
            'operator_id' => $operator->id,
            'team_id' => $operator->team_id,
            'equipment_type' => EquipmentType::ADT,
        ]);
    }

    public function test_the_medical_section_is_hidden_without_the_medical_permission(): void
    {
        // fleet_manager has manage_operators but NOT the medical permissions.
        $user = $this->actingAs2FA('fleet_manager');
        $operator = Operator::factory()->compliantFor()->create(['team_id' => $user->current_team_id]);
        $operator->medicals()->update(['restrictions' => 'No confined spaces', 'has_restrictions' => true]);

        $component = Livewire::test(OperatorDetail::class, ['operator' => $operator]);

        $this->assertFalse($component->instance()->getCanViewMedicalProperty());
        $component->assertDontSee('Occupational Medical')->assertDontSee('No confined spaces');

        // The compliance row still answers the operational question.
        $component->assertSee('Medical Fitness');
    }

    public function test_recording_a_medical_requires_the_medical_permission(): void
    {
        $user = $this->actingAs2FA('fleet_manager');
        $operator = Operator::factory()->create(['team_id' => $user->current_team_id]);

        Livewire::test(OperatorDetail::class, ['operator' => $operator])
            ->set('medicalForm.fitness', 'fit')
            ->call('addMedical')
            ->assertForbidden();

        $this->assertSame(0, $operator->medicals()->count());
    }

    public function test_an_admin_can_record_a_medical_with_restrictions(): void
    {
        $user = $this->actingAs2FA('admin');
        $operator = Operator::factory()->create(['team_id' => $user->current_team_id]);

        Livewire::test(OperatorDetail::class, ['operator' => $operator])
            ->set('medicalForm.fitness', 'fit_with_restrictions')
            ->set('medicalForm.expires_on', now()->addYear()->toDateString())
            ->set('medicalForm.restrictions', 'No night shift')
            ->call('addMedical');

        $this->assertDatabaseHas('operator_medicals', [
            'operator_id' => $operator->id,
            'fitness' => 'fit_with_restrictions',
            'has_restrictions' => true,
        ]);
    }

    public function test_a_viewer_cannot_open_the_operators_page(): void
    {
        $this->actingAs2FA('viewer');

        $this->get(route('operators.index'))->assertForbidden();
    }

    public function test_an_operator_role_user_can_view_but_not_create(): void
    {
        $user = $this->actingAs2FA('operator');
        Operator::factory()->create(['team_id' => $user->current_team_id, 'first_name' => 'Visible']);

        $this->get(route('operators.index'))->assertOk();

        Livewire::test(OperatorManager::class)
            ->set('form.employee_number', 'X-1')
            ->set('form.first_name', 'No')
            ->set('form.last_name', 'Permission')
            ->call('create')
            ->assertForbidden();
    }
}
