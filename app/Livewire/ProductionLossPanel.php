<?php

namespace App\Livewire;

use App\Livewire\Concerns\NotifiesUser;
use App\Models\Machine;
use App\Models\ProductionLossEvent;
use App\Models\User;
use App\Services\ProductionLossService;
use App\Support\ApiPayload;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Production Loss Accountability panel on the Machine Details page: lost
 * hours, loss events (telemetry-detected and user-recorded), classification
 * of detected events, manual entry, and estimated production impact.
 *
 * @psalm-suppress MissingConstructor -- Livewire injects state via mount()
 */
class ProductionLossPanel extends Component
{
    use NotifiesUser;

    public Machine $machine;

    public bool $showRecordModal = false;

    public ?int $classifyingEventId = null;

    // Manual entry form
    public string $lossDate = '';

    public string $startTime = '';

    public string $endTime = '';

    public string $category = 'mechanical';

    public string $reason = 'breakdown';

    public string $notes = '';

    public function mount(Machine $machine): void
    {
        if ($machine->team_id !== $this->currentUser()->current_team_id) {
            abort(403);
        }

        $this->machine = $machine;
        $this->lossDate = now()->toDateString();
    }

    public function updatedCategory(string $value): void
    {
        // Keep the reason valid for the newly selected category.
        $this->reason = ProductionLossEvent::REASONS[$value][0] ?? 'other';
    }

    public function openRecordModal(): void
    {
        $this->authorizeManage();
        $this->resetErrorBag();
        $this->showRecordModal = true;
    }

    public function openClassify(int $eventId): void
    {
        $this->authorizeManage();
        $this->resetErrorBag();
        $this->classifyingEventId = $eventId;
    }

    public function cancelDialogs(): void
    {
        $this->showRecordModal = false;
        $this->classifyingEventId = null;
    }

    public function recordLoss(ProductionLossService $service): void
    {
        $this->authorizeManage();

        $this->validate([
            'lossDate' => 'required|date',
            'startTime' => 'required|date_format:H:i',
            'endTime' => 'required|date_format:H:i',
            'category' => 'required|string',
            'reason' => 'required|string',
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            $event = $service->recordManualLoss($this->machine, $this->currentUser(), [
                'started_at' => "{$this->lossDate} {$this->startTime}",
                'ended_at' => "{$this->lossDate} {$this->endTime}",
                'category' => $this->category,
                'reason' => $this->reason,
                'notes' => $this->notes ?: null,
            ]);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
            $message = ApiPayload::str(collect($e->errors())->flatten()->first(), 'Validation failed');
            $this->notify($message, 'error');

            return;
        }

        $this->showRecordModal = false;
        $this->reset(['startTime', 'endTime', 'notes']);
        $this->notify("Recorded {$event->lost_hours}h production loss ({$event->reasonLabel()}).", 'success');
    }

    public function classifyEvent(ProductionLossService $service): void
    {
        $this->authorizeManage();

        $event = ProductionLossEvent::query()->where('team_id', $this->machine->team_id)
            ->where('machine_id', $this->machine->id)
            ->findOrFail($this->classifyingEventId);

        try {
            $service->classify($event, $this->currentUser(), $this->category, $this->reason, $this->notes ?: null);
        } catch (ValidationException $e) {
            $message = ApiPayload::str(collect($e->errors())->flatten()->first(), 'Validation failed');
            $this->notify($message, 'error');

            return;
        }

        $this->classifyingEventId = null;
        $this->reset(['notes']);
        $this->notify('Loss event classified.', 'success');
    }

    public function render(): View
    {
        $service = app(ProductionLossService::class);
        $summary = $service->summaryForMachine($this->machine);

        $events = ProductionLossEvent::query()->where('machine_id', $this->machine->id)
            ->with(['creator', 'classifier'])
            ->orderByDesc('started_at')
            ->limit(20)
            ->get();

        return view('livewire.production-loss-panel', [
            'summary' => $summary,
            'events' => $events,
            'impact' => $service->estimateImpact($this->machine, $summary['month_hours']),
            'canManage' => $this->userCanManage(),
            'reasonTaxonomy' => ProductionLossEvent::REASONS,
        ]);
    }

    private function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }

    private function userCanManage(): bool
    {
        $user = $this->currentUser();

        return $user->hasPermission('update_machines') || $this->machine->team?->user_id === $user->id;
    }

    private function authorizeManage(): void
    {
        abort_unless($this->userCanManage(), 403, 'You are not authorised to manage production loss records.');
    }
}
