<?php

namespace Tests\Feature\Operators;

use App\Models\Operator;
use App\Models\Team;
use App\Services\Operators\OperatorCompliance;
use App\Support\EquipmentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Can this operator legally/operationally perform their role?" -- computed
 * from the credential rows, never stored, so no screen can disagree with the
 * dates behind it.
 */
class OperatorComplianceTest extends TestCase
{
    use RefreshDatabase;

    private function compliance(): OperatorCompliance
    {
        return app(OperatorCompliance::class);
    }

    private function operator(): Operator
    {
        return Operator::factory()->compliantFor(EquipmentType::ADT)->create([
            'team_id' => Team::factory()->create()->id,
        ]);
    }

    public function test_an_operator_with_current_credentials_is_compliant(): void
    {
        $summary = $this->compliance()->summarise($this->operator());

        $this->assertSame(OperatorCompliance::COMPLIANT, $summary['verdict']);

        $requirements = array_column($summary['items'], 'requirement');
        $this->assertContains('licence', $requirements);
        $this->assertContains('medical', $requirements);
        $this->assertContains('induction', $requirements);
    }

    public function test_an_expired_licence_makes_the_operator_non_compliant(): void
    {
        $operator = $this->operator();
        $operator->qualifications()->update(['expires_on' => now()->subDay()->toDateString()]);

        $summary = $this->compliance()->summarise($operator->fresh());

        $this->assertSame(OperatorCompliance::NON_COMPLIANT, $summary['verdict']);

        $licence = collect($summary['items'])->firstWhere('requirement', 'licence');
        $this->assertSame('expired', $licence['status']);
        $this->assertStringContainsString('expired on', $licence['detail']);
    }

    public function test_a_licence_expiring_within_the_window_downgrades_to_expiring(): void
    {
        $operator = $this->operator();
        $operator->qualifications()->update(['expires_on' => now()->addDays(20)->toDateString()]);

        $summary = $this->compliance()->summarise($operator->fresh());

        $this->assertSame(OperatorCompliance::EXPIRING, $summary['verdict']);

        $licence = collect($summary['items'])->firstWhere('requirement', 'licence');
        $this->assertStringContainsString('expires in', $licence['detail']);
    }

    public function test_a_missing_medical_is_non_compliant(): void
    {
        $operator = $this->operator();
        $operator->medicals()->forceDelete();

        $summary = $this->compliance()->summarise($operator->fresh());

        $this->assertSame(OperatorCompliance::NON_COMPLIANT, $summary['verdict']);
        $medical = collect($summary['items'])->firstWhere('requirement', 'medical');
        $this->assertSame('missing', $medical['status']);
    }

    public function test_an_unfit_finding_fails_the_medical_gate_regardless_of_date(): void
    {
        $operator = $this->operator();
        $operator->medicals()->update(['fitness' => 'unfit']);

        $this->assertSame(
            OperatorCompliance::NON_COMPLIANT,
            $this->compliance()->verdict($operator->fresh()),
            'A doctor saying unfit beats a certificate that has not expired yet.'
        );
    }

    public function test_fit_with_restrictions_passes_the_gate_but_is_flagged_for_review(): void
    {
        $operator = $this->operator();
        $operator->medicals()->update([
            'fitness' => 'fit_with_restrictions',
            'has_restrictions' => true,
            'restrictions' => 'No night shift.',
        ]);

        $summary = $this->compliance()->summarise($operator->fresh());

        // Restrictions are a human decision, not a machine block.
        $this->assertSame(OperatorCompliance::COMPLIANT, $summary['verdict']);
        $medical = collect($summary['items'])->firstWhere('requirement', 'medical');
        $this->assertStringContainsString('review before assignment', $medical['detail']);
    }

    public function test_the_newest_medical_answers_not_the_first_loaded(): void
    {
        $operator = $this->operator();

        // An old expired medical alongside the current one must not fail the gate.
        $operator->medicals()->create([
            'team_id' => $operator->team_id,
            'examined_on' => now()->subYears(2)->toDateString(),
            'expires_on' => now()->subYear()->toDateString(),
            'fitness' => 'fit',
        ]);

        $this->assertSame(OperatorCompliance::COMPLIANT, $this->compliance()->verdict($operator->fresh()));
    }

    public function test_a_suspended_licence_authorises_nothing_before_its_date(): void
    {
        $operator = $this->operator();
        $operator->qualifications()->update(['standing' => 'suspended']);

        $this->assertSame(OperatorCompliance::NON_COMPLIANT, $this->compliance()->verdict($operator->fresh()));
    }

    public function test_a_suspended_operator_is_non_compliant_whatever_their_credentials_say(): void
    {
        $operator = $this->operator();
        $operator->update(['employment_status' => Operator::STATUS_SUSPENDED]);

        $this->assertSame(OperatorCompliance::NON_COMPLIANT, $this->compliance()->verdict($operator->fresh()));
    }

    public function test_an_ended_employment_is_non_compliant(): void
    {
        $operator = $this->operator();
        $operator->update(['employed_until' => now()->subDay()->toDateString()]);

        $this->assertSame(OperatorCompliance::NON_COMPLIANT, $this->compliance()->verdict($operator->fresh()));
    }

    public function test_missing_site_induction_is_non_compliant(): void
    {
        $operator = $this->operator();
        $operator->trainings()->forceDelete();

        $summary = $this->compliance()->summarise($operator->fresh());

        $this->assertSame(OperatorCompliance::NON_COMPLIANT, $summary['verdict']);
        $induction = collect($summary['items'])->firstWhere('requirement', 'induction');
        $this->assertSame('missing', $induction['status']);
    }

    public function test_requirements_can_be_switched_off_in_config(): void
    {
        config(['operators.required.induction' => false]);

        $operator = $this->operator();
        $operator->trainings()->forceDelete();

        // With induction not required, its absence no longer fails anyone.
        $this->assertSame(OperatorCompliance::COMPLIANT, $this->compliance()->verdict($operator->fresh()));
    }

    public function test_a_failed_machine_competency_surfaces_but_a_missing_one_does_not(): void
    {
        $operator = $this->operator();

        $operator->trainings()->create([
            'team_id' => $operator->team_id,
            'course' => 'Dozer Competency',
            'category' => 'machine_competency',
            'equipment_type' => EquipmentType::DOZER,
            'competency' => 'failed',
            'completed_on' => now()->subMonth()->toDateString(),
        ]);

        $summary = $this->compliance()->summarise($operator->fresh());

        $dozer = collect($summary['items'])->firstWhere('requirement', 'competency:dozer');
        $this->assertNotNull($dozer, 'A recorded competency must appear in the summary.');
        $this->assertSame('expired', $dozer['status']);

        // No excavator competency recorded -> no item, and no penalty.
        $this->assertNull(collect($summary['items'])->firstWhere('requirement', 'competency:excavator'));
    }

    public function test_a_qualification_without_equipment_does_not_satisfy_the_licence_requirement(): void
    {
        $team = Team::factory()->create();
        $operator = Operator::factory()->create(['team_id' => $team->id]);

        // First aid is a qualification, but it does not license any machine.
        $operator->qualifications()->create([
            'team_id' => $team->id,
            'title' => 'First Aid Level 1',
            'expires_on' => now()->addYear()->toDateString(),
        ]);

        $summary = $this->compliance()->summarise($operator->fresh());
        $licence = collect($summary['items'])->firstWhere('requirement', 'licence');

        $this->assertSame('missing', $licence['status']);
    }
}
