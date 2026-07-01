<?php

namespace App\Livewire;

use App\Jobs\SyncIntegrationJob;
use App\Jobs\TestIntegrationConnectionJob;
use App\Models\Integration;
use App\Models\IntegrationSyncLog;
use App\Services\Integration\AdapterRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class IntegrationManager extends Component
{
    public mixed $team = null;

    /** @var array<string, mixed> */
    public array $integrations = [];

    /** @var array<string, mixed> */
    public array $availableProviders = [];

    public bool $showAddModal = false;

    public bool $showDetailPanel = false;

    public ?int $detailIntegrationId = null;

    /** @var array<string, mixed> */
    public array $syncLogs = [];

    /** @var array<string, mixed> */
    public array $credentialFields = [];

    /** @var array<string, mixed> */
    public array $formData = [
        'provider' => '',
        'name' => '',
        'sync_frequency' => 'every_5_minutes',
        'credentials' => [],
    ];

    /** @var array<string, string> */
    protected $listeners = ['refresh' => '$refresh'];

    public function mount(): void
    {
        $this->team = Auth::user()->currentTeam;

        if (! $this->team) {
            abort(403, 'No team context available.');
        }

        $this->loadAvailableProviders();
        $this->loadIntegrations();
    }

    public function loadIntegrations(): void
    {
        if (! $this->team) {
            return;
        }

        $this->integrations = Integration::where('team_id', $this->team->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'provider' => $i->provider,
                'name' => $i->name,
                'status' => $i->status,
                'machines_count' => $i->machines_count,
                'last_sync_at' => $i->last_sync_at?->diffForHumans() ?? 'Never',
                'last_sync_status' => $i->last_sync_status ?? 'pending',
                'last_error' => $i->last_error,
                'created_at' => $i->created_at->format('d M Y'),
                'sync_frequency' => $i->config['sync_frequency'] ?? 'every_5_minutes',
            ])
            ->toArray();
    }

    public function loadAvailableProviders(): void
    {
        $this->availableProviders = app(AdapterRegistry::class)->all();
    }

    // ── Form helpers ────────────────────────────────────────────────────────────

    public function updatedFormDataProvider(string $provider): void
    {
        if (! $provider || ! isset($this->availableProviders[$provider])) {
            $this->credentialFields = [];

            return;
        }

        $this->credentialFields = $this->availableProviders[$provider]['credential_schema'] ?? [];
        $this->formData['name'] = $this->availableProviders[$provider]['name'] ?? $provider;

        // Reset credential fields for the new provider
        $this->formData['credentials'] = [];
    }

    public function openAddModal(): void
    {
        $this->showAddModal = true;
        $this->resetForm();
    }

    public function closeAddModal(): void
    {
        $this->showAddModal = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->formData = [
            'provider' => '',
            'name' => '',
            'sync_frequency' => 'every_5_minutes',
            'credentials' => [],
        ];
        $this->credentialFields = [];
    }

    // ── CRUD ────────────────────────────────────────────────────────────────────

    public function createIntegration(): void
    {
        if (! $this->team) {
            return;
        }

        $this->validate([
            'formData.provider' => 'required|string',
            'formData.name' => 'required|string|max:120',
            'formData.sync_frequency' => 'required|string|in:manual,every_5_minutes,every_15_minutes,hourly,every_6_hours,daily',
        ]);

        // Validate required credential fields from schema
        foreach ($this->credentialFields as $field) {
            if (($field['required'] ?? false) && empty($this->formData['credentials'][$field['key']] ?? '')) {
                $this->addError("credentials.{$field['key']}", "{$field['label']} is required.");
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        try {
            $integration = Integration::create([
                'team_id' => $this->team->id,
                'provider' => $this->formData['provider'],
                'name' => $this->formData['name'],
                'credentials' => $this->formData['credentials'] ?: [],
                'status' => 'testing',
                'config' => ['sync_frequency' => $this->formData['sync_frequency']],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to create integration', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->addError('general', app()->isProduction()
                ? 'Failed to save integration. Please try again.'
                : 'Error: '.$e->getMessage()
            );

            return;
        }

        // Dispatch asynchronous connection test in its own try-catch so a
        // sync-queue failure never masks the successful save to the user.
        try {
            TestIntegrationConnectionJob::dispatch($integration->id);
        } catch (\Throwable $e) {
            Log::warning('TestIntegrationConnectionJob dispatch failed', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);
            // Integration was saved — user can re-test manually
        }

        // Immediately pull machines from the API rather than waiting for the
        // 5-minute scheduler cycle.
        try {
            SyncIntegrationJob::dispatch($integration->id);
        } catch (\Throwable $e) {
            Log::warning('SyncIntegrationJob dispatch failed on initial save', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->dispatch('notify', type: 'success', message: 'Integration added! Testing connection...');
        $this->closeAddModal();
        $this->loadIntegrations();
    }

    public function deleteIntegration(int $integrationId): void
    {
        if (! $this->team) {
            return;
        }

        Integration::where('team_id', $this->team->id)->findOrFail($integrationId)->delete();
        $this->dispatch('notify', type: 'success', message: 'Integration deleted.');

        if ($this->detailIntegrationId === $integrationId) {
            $this->showDetailPanel = false;
            $this->detailIntegrationId = null;
        }

        $this->loadIntegrations();
    }

    // ── Sync controls ───────────────────────────────────────────────────────────

    public function syncNow(int $integrationId): void
    {
        if (! $this->team) {
            return;
        }

        $integration = Integration::where('team_id', $this->team->id)->findOrFail($integrationId);
        SyncIntegrationJob::dispatch($integration->id);
        $this->dispatch('notify', type: 'success', message: 'Sync queued — results will appear shortly.');
        $this->loadIntegrations();
    }

    public function retestConnection(int $integrationId): void
    {
        if (! $this->team) {
            return;
        }

        $integration = Integration::where('team_id', $this->team->id)->findOrFail($integrationId);
        TestIntegrationConnectionJob::dispatch($integration->id);
        Integration::where('id', $integrationId)->update(['status' => 'testing']);
        $this->dispatch('notify', type: 'info', message: 'Re-testing connection...');
        $this->loadIntegrations();
    }

    public function updateSyncFrequency(int $integrationId, string $frequency): void
    {
        if (! $this->team) {
            return;
        }

        $integration = Integration::where('team_id', $this->team->id)->findOrFail($integrationId);
        $config = $integration->config ?? [];
        $config['sync_frequency'] = $frequency;
        $integration->update(['config' => $config]);
        $this->dispatch('notify', type: 'success', message: 'Sync frequency updated.');
        $this->loadIntegrations();
    }

    // ── Detail panel ────────────────────────────────────────────────────────────

    public function openDetailPanel(int $integrationId): void
    {
        $this->detailIntegrationId = $integrationId;
        $this->showDetailPanel = true;
        $this->loadSyncLogs($integrationId);
    }

    public function closeDetailPanel(): void
    {
        $this->showDetailPanel = false;
        $this->detailIntegrationId = null;
        $this->syncLogs = [];
    }

    private function loadSyncLogs(int $integrationId): void
    {
        $this->syncLogs = IntegrationSyncLog::where('integration_id', $integrationId)
            ->orderByDesc('started_at')
            ->limit(15)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'started_at' => $log->started_at->format('d M H:i'),
                'duration' => $log->finished_at
                    ? $log->started_at->diffInSeconds($log->finished_at).'s'
                    : '—',
                'status' => $log->status,
                'machines_synced' => $log->machines_synced,
                'records_inserted' => $log->records_inserted,
                'error_message' => $log->error_message,
            ])
            ->toArray();
    }

    public function render(): View
    {
        return view('livewire.integration-manager');
    }
}
