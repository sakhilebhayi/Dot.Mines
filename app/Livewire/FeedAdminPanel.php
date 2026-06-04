<?php

namespace App\Livewire;

use App\Models\FeedAuditLog;
use App\Models\MineArea;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class FeedAdminPanel extends Component
{
    use WithPagination;

    public string $activeTab = 'audit';

    // ── Audit log filters ──────────────────────────────────────────────────────
    public string $auditAction = '';

    // ── Active shifts state ────────────────────────────────────────────────────
    public array $activeShifts = ['A', 'B', 'C'];

    public function mount(): void
    {
        abort_if(! Auth::user()?->hasRole('admin'), 403);

        $team = Auth::user()->currentTeam;
        $stored = $team->active_shifts ? json_decode($team->active_shifts, true) : ['A', 'B', 'C'];
        $this->activeShifts = is_array($stored) ? $stored : ['A', 'B', 'C'];
    }

    // ── Mine area: toggle active ───────────────────────────────────────────────

    public function toggleMineArea(int $areaId): void
    {
        $team = Auth::user()->currentTeam;
        $area = MineArea::where('team_id', $team->id)->findOrFail($areaId);
        $area->update(['is_active' => ! $area->is_active]);
        $this->dispatch('notify', type: 'success', message: 'Section updated.');
    }

    // ── Active shifts ──────────────────────────────────────────────────────────

    public function toggleShift(string $shift): void
    {
        abort_if(! in_array($shift, ['A', 'B', 'C'], true), 422);
        if (in_array($shift, $this->activeShifts, true)) {
            $this->activeShifts = array_values(array_filter($this->activeShifts, fn ($s) => $s !== $shift));
        } else {
            $this->activeShifts[] = $shift;
            sort($this->activeShifts);
        }
    }

    public function saveShifts(): void
    {
        $team = Auth::user()->currentTeam;
        $team->update(['active_shifts' => json_encode($this->activeShifts)]);
        $this->dispatch('notify', type: 'success', message: 'Active shifts saved.');
    }

    // ── Render ─────────────────────────────────────────────────────────────────

    public function render(): \Illuminate\View\View
    {
        $team = Auth::user()->currentTeam;

        $auditLogs = FeedAuditLog::where('team_id', $team->id)
            ->with('actor:id,name')
            ->when($this->auditAction, fn ($q) => $q->where('action', $this->auditAction))
            ->orderByDesc('created_at')
            ->paginate(25);

        $query = MineArea::where('team_id', $team->id);

        // Only count machines if the mine_area_id column exists
        if (Schema::hasColumn('machines', 'mine_area_id')) {
            $query->withCount('machines');
        }

        $mineAreas = $query->orderBy('name')->get();

        return view('livewire.feed-admin-panel', [
            'auditLogs' => $auditLogs,
            'mineAreas' => $mineAreas,
        ])->layout('layouts.app');
    }
}
