<?php

namespace Tests\Feature;

use App\Livewire\AINotifications;
use App\Livewire\Fleet;
use App\Models\Alert;
use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pins for the platform QA pass: fleet cards never show a bare N/A (they
 * show capacity, or telemetry recency, or nothing), and the notification
 * bell badge caps its count at 99+ so it stays readable and contained.
 */
class GlobalUiQaTest extends TestCase
{
    use RefreshDatabase;

    public function test_fleet_card_shows_telemetry_recency_when_capacity_is_unknown(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $machine = Machine::factory()->create([
            'team_id' => $team->id,
            'capacity' => 0,
            'status' => 'active',
        ]);
        MachineMetric::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'operating_hours' => 1234.5,
            'recorded_at' => now()->subHours(3),
        ]);

        Livewire::actingAs($user)
            ->test(Fleet::class)
            ->assertSee('Seen')
            ->assertDontSee('>N/A<', false);
    }

    public function test_notification_badge_caps_at_ninety_nine_plus(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        Alert::factory()->count(101)->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'status' => 'active',
        ]);

        Livewire::actingAs($user)
            ->test(AINotifications::class)
            ->assertSee('99+');
    }
}
