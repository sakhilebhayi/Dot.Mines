<?php

namespace Tests\Feature;

use App\Livewire\Concerns\NotifiesUser;
use App\Livewire\OperatorFatigueTracker;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * The toast contract, frozen.
 *
 * The blank popups reported from Operator Fatigue were a payload-shape
 * mismatch: the host reads `detail.type` / `detail.message` (Livewire's
 * NAMED-args shape), but 128 call sites across 15 components passed a
 * positional array, which Livewire wraps one level deeper -- so the host
 * read undefined twice and rendered an empty pill. Every toast now goes
 * through NotifiesUser::notify(); these tests keep both the shape and the
 * single entry point from drifting back.
 */
class NotificationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_component_dispatches_notify_outside_the_trait(): void
    {
        $violations = [];

        foreach ($this->phpFiles(app_path()) as $path => $contents) {
            if (str_ends_with($path, 'Concerns/NotifiesUser.php')) {
                continue;
            }

            foreach (explode("\n", $contents) as $index => $line) {
                if (str_contains($line, "dispatch('notify'")) {
                    $violations[] = sprintf('%s:%d', $path, $index + 1);
                }
            }
        }

        $this->assertSame([], $violations,
            'Toasts go through NotifiesUser::notify() -- a raw dispatch can reintroduce the wrapped-array shape that rendered blank pills.');
    }

    public function test_no_view_dispatches_notify_with_the_wrapped_array_shape(): void
    {
        $violations = [];

        foreach ($this->phpFiles(resource_path('views')) as $path => $contents) {
            foreach (explode("\n", $contents) as $index => $line) {
                // Alpine-side dispatches must use the object shape
                // {type, message}; an array literal lands as detail[0].
                if (preg_match("/dispatch\('notify',\s*\[/", $line)) {
                    $violations[] = sprintf('%s:%d', $path, $index + 1);
                }
            }
        }

        $this->assertSame([], $violations);
    }

    public function test_both_layouts_share_one_toast_host(): void
    {
        foreach (['layouts/app.blade.php', 'components/layouts/app.blade.php'] as $layout) {
            $contents = (string) file_get_contents(resource_path('views/'.$layout));

            $this->assertSame(1, substr_count($contents, '<x-toast-host />'),
                "{$layout} must include the shared toast host exactly once -- the duplicated copies drifted apart twice before.");
        }

        $host = (string) file_get_contents(resource_path('views/components/toast-host.blade.php'));
        $this->assertStringContainsString('$event.detail.type, $event.detail.message', $host);
        $this->assertStringContainsString('error: 10000', $host, 'Errors must outlive success toasts.');
    }

    public function test_a_blank_message_throws_instead_of_rendering_an_empty_pill(): void
    {
        $component = new class
        {
            use NotifiesUser;

            /** @var array<string, mixed>|null */
            public ?array $dispatched = null;

            /**
             * @param  mixed  ...$params
             */
            public function dispatch(string $event, ...$params): void
            {
                $this->dispatched = ['event' => $event, 'params' => $params];
            }

            public function fire(string $message, string $type = 'success'): void
            {
                $this->notify($message, $type);
            }
        };

        try {
            $component->fire('   ');
            $this->fail('A blank toast must throw.');
        } catch (InvalidArgumentException) {
        }

        try {
            $component->fire('Valid message', 'sparkles');
            $this->fail('An unknown toast type must throw.');
        } catch (InvalidArgumentException) {
        }

        $component->fire('Machine assigned successfully.', 'success');
        $this->assertSame('notify', $component->dispatched['event'] ?? null);
    }

    public function test_operator_fatigue_recording_emits_one_meaningful_named_toast(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->forceFill(['current_team_id' => $team->id, 'two_factor_confirmed_at' => now()])->save();
        TeamRoleProvisioner::assignRole($user, $team, 'admin');
        $this->actingAs($user->fresh());

        $component = Livewire::test(OperatorFatigueTracker::class)
            ->set('operatorId', $user->id)
            ->set('shiftDate', now()->toDateString())
            ->set('shiftType', 'morning')
            ->set('shiftStart', '06:00')
            ->set('shiftEnd', '18:00')
            ->set('hoursWorked', 12)
            ->set('consecutiveDays', 3)
            ->set('breakTimeMinutes', 60)
            ->set('incidentsCount', 0)
            ->call('submitShift');

        // The named shape the host actually reads -- and a message that says
        // what happened, not just that something did.
        $component->assertDispatched('notify', function (string $name, array $params): bool {
            return ($params['type'] ?? null) === 'success'
                && str_contains($params['message'] ?? '', 'Fatigue score recorded')
                && str_contains($params['message'] ?? '', '/100');
        });

        // Exactly one toast per action: no duplicate stacking.
        $notifies = collect($component->effects['dispatches'] ?? [])
            ->filter(fn (array $d): bool => ($d['name'] ?? null) === 'notify');
        $this->assertCount(1, $notifies, 'One action, one toast.');
    }

    /**
     * @return iterable<string, string>
     */
    private function phpFiles(string $root): iterable
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            $path = (string) $file;

            if (str_ends_with($path, '.php')) {
                yield $path => (string) file_get_contents($path);
            }
        }
    }
}
