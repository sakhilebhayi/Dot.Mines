<?php

namespace App\Livewire;

use App\Models\Integration;
use App\Services\Integration\IntegrationService;
use App\Traits\BrowserEventBridge;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

class IntegrationManager extends Component
{
    use BrowserEventBridge;

    public mixed $team = null;

    public array $integrations = [];

    public array $availableManufacturers = [];

    public bool $showAddModal = false;

    public bool $showTestModal = false;

    public mixed $selectedIntegration = null;

    public mixed $testResult = null;

    public array $formData = [
        'provider' => '',
        'name' => '',
        'endpoint' => '',
        'connection_type' => '',
        'sync_frequency' => 'manual',
        'notification_email' => '',
        'credentials' => [
            'api_key' => '',
            'api_secret' => '',
        ],
    ];

    /** @var array<string, string> */
    protected $listeners = ['refresh' => '$refresh'];

    public function mount()
    {
        $this->team = Auth::user()->currentTeam;

        if (! $this->team) {
            abort(403, 'No team context available.');
        }

        $this->loadIntegrations();
        $this->loadAvailableManufacturers();
    }

    public function loadIntegrations()
    {
        if (! $this->team) {
            return;
        }

        $this->integrations = Integration::where('team_id', $this->team->id)
            ->get()
            ->map(function ($integration) {
                return [
                    'id' => $integration->id,
                    'provider' => $integration->provider,
                    'status' => $integration->status,
                    'created_at' => $integration->created_at->format('M d, Y'),
                    'last_sync_at' => $integration->last_sync_at?->format('M d, Y H:i') ?? 'Never',
                    'last_sync_status' => $integration->last_sync_status ?? 'pending',
                    'machines_count' => $integration->machines_count ?? 0,
                    'last_error' => $integration->last_error,
                ];
            })
            ->toArray();
    }

    public function loadAvailableManufacturers()
    {
        $service = app(IntegrationService::class);
        $this->availableManufacturers = $service->getAvailableManufacturers();
    }

    public function openAddModal()
    {
        $this->showAddModal = true;
        $this->resetForm();
    }

    public function closeAddModal()
    {
        $this->showAddModal = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->formData = [
            'provider' => '',
            'name' => '',
            'endpoint' => '',
            'connection_type' => '',
            'sync_frequency' => 'manual',
            'notification_email' => '',
            'credentials' => [
                'api_key' => '',
                'api_secret' => '',
            ],
        ];
    }

    public function createIntegration()
    {
        if (! $this->team) {
            $this->addError('general', 'No team context available');

            return;
        }

        $rules = [
            'formData.provider' => 'required|string',
            'formData.name' => 'required|string|max:100',
            'formData.connection_type' => 'required|string',
            'formData.sync_frequency' => 'required|string',
            'formData.notification_email' => 'nullable|email',
            'formData.endpoint' => 'nullable|string',
        ];

        // Bell uses OAuth2 Resource Owner Password Credentials (username,
        // password, client secret), not the api_key/api_secret pair every
        // other provider's form collects -- requiring those here would
        // reject every real Bell submission before it reached the service.
        if ($this->formData['provider'] === 'bell') {
            $rules['formData.credentials.username'] = 'required|string';
            $rules['formData.credentials.password'] = 'required|string';
            $rules['formData.credentials.client_secret'] = 'required|string';
        } else {
            $rules['formData.credentials.api_key'] = 'required|string';
            $rules['formData.credentials.api_secret'] = 'required|string';
        }

        $this->validate($rules);

        // Bell issues this same client_id to every ISO 15143-3 export
        // consumer -- default it so a user who doesn't touch that field
        // (it's optional in the form) still gets a working credential set.
        if ($this->formData['provider'] === 'bell' && empty($this->formData['credentials']['client_id'])) {
            $this->formData['credentials']['client_id'] = 'ISO_Export_Service';
        }

        try {
            $this->authorize('create', Integration::class);

            $integration = Integration::create([
                'team_id' => $this->team->id,
                'provider' => $this->formData['provider'],
                'name' => $this->formData['name'],
                // Both fields are cast ('credentials' => 'encrypted:json', 'config' =>
                // 'json') -- Eloquent encodes them on save. Pre-encoding here used to
                // double-encode: the DB ended up storing a JSON string containing an
                // escaped JSON string, so every ->credentials/->config read back a
                // PHP string instead of an array and every manufacturer service's
                // $credentials['...'] access silently failed.
                'credentials' => $this->formData['credentials'],
                'status' => 'pending',
                // 'webhook.receive' isn't registered -- there's no generic per-provider inbound
                // webhook endpoint built yet (only the outbound Stripe billing webhook exists,
                // at a different route). Guarded so choosing "webhook" here degrades to a null
                // URL instead of throwing a RouteNotFoundException.
                'webhook_url' => $this->formData['connection_type'] === 'webhook' && Route::has('webhook.receive')
                    ? route('webhook.receive', ['provider' => $this->formData['provider']])
                    : null,
                'config' => [
                    'endpoint' => $this->formData['endpoint'],
                    'connection_type' => $this->formData['connection_type'],
                    'sync_frequency' => $this->formData['sync_frequency'],
                    'notification_email' => $this->formData['notification_email'],
                ],
            ]);

            $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'Integration created successfully!']);
            $this->closeAddModal();
            $this->loadIntegrations();
        } catch (\Throwable $e) {
            Log::error('Failed to create integration', ['error' => $e->getMessage()]);
            $this->addError('general', 'Failed to create integration. Please try again.');
        }
    }

    /**
     * The spec's single "Connect" action: validate -> save -> authenticate
     * -> fetch -> store -> dispatch ongoing sync, all in one click. Shares
     * createIntegration()'s own validation rules exactly (including Bell's
     * separate credential fields) so a submission that would have been
     * accepted by the old two-step flow is accepted here too.
     */
    public function connectIntegration()
    {
        if (! $this->team) {
            $this->addError('general', 'No team context available');

            return;
        }

        $rules = [
            'formData.provider' => 'required|string',
            'formData.name' => 'required|string|max:100',
            'formData.connection_type' => 'required|string',
            'formData.sync_frequency' => 'required|string',
            'formData.notification_email' => 'nullable|email',
            'formData.endpoint' => 'nullable|string',
        ];

        if ($this->formData['provider'] === 'bell') {
            $rules['formData.credentials.username'] = 'required|string';
            $rules['formData.credentials.password'] = 'required|string';
            $rules['formData.credentials.client_secret'] = 'required|string';
        } else {
            $rules['formData.credentials.api_key'] = 'required|string';
            $rules['formData.credentials.api_secret'] = 'required|string';
        }

        $this->validate($rules);

        if ($this->formData['provider'] === 'bell' && empty($this->formData['credentials']['client_id'])) {
            $this->formData['credentials']['client_id'] = 'ISO_Export_Service';
        }

        try {
            $this->authorize('create', Integration::class);

            $integration = Integration::create([
                'team_id' => $this->team->id,
                'provider' => $this->formData['provider'],
                'name' => $this->formData['name'],
                'credentials' => $this->formData['credentials'],
                'status' => 'pending',
                'webhook_url' => $this->formData['connection_type'] === 'webhook' && Route::has('webhook.receive')
                    ? route('webhook.receive', ['provider' => $this->formData['provider']])
                    : null,
                'config' => [
                    'endpoint' => $this->formData['endpoint'],
                    'connection_type' => $this->formData['connection_type'],
                    'sync_frequency' => $this->formData['sync_frequency'],
                    'notification_email' => $this->formData['notification_email'],
                ],
            ]);

            $this->testResult = app(IntegrationService::class)->connect($integration);
            $this->showTestModal = true;
            $this->closeAddModal();
            $this->loadIntegrations();
        } catch (\Throwable $e) {
            Log::error('Failed to connect integration', ['error' => $e->getMessage()]);
            $this->addError('general', 'Failed to connect integration. Please try again.');
        }
    }

    /**
     * Re-runs the same deep check against an already-saved integration's
     * existing credentials -- the spec's "allow the user to retry without
     * re-entering credentials unnecessarily." Distinct from testConnection()
     * (the shallow, pre-existing action left untouched for backward
     * compatibility) so both remain independently callable.
     */
    public function retestConnection($integrationId)
    {
        if (! $this->team) {
            $this->testResult = ['success' => false, 'message' => 'No team context available'];
            $this->showTestModal = true;

            return;
        }

        try {
            $integration = Integration::where('team_id', $this->team->id)->findOrFail($integrationId);
            $this->authorize('test', $integration);

            $this->testResult = app(IntegrationService::class)->connect($integration);
            $this->selectedIntegration = $integrationId;
            $this->showTestModal = true;
            $this->loadIntegrations();
        } catch (\Throwable $e) {
            Log::error('Retest connection failed', ['error' => $e->getMessage()]);
            $this->testResult = ['success' => false, 'message' => 'Error testing connection'];
            $this->showTestModal = true;
        }
    }

    public function testConnection($integrationId)
    {
        if (! $this->team) {
            $this->testResult = [
                'success' => false,
                'message' => 'No team context available',
            ];
            $this->showTestModal = true;

            return;
        }

        try {
            $integration = Integration::where('team_id', $this->team->id)
                ->findOrFail($integrationId);
            $this->authorize('test', $integration);

            $service = app(IntegrationService::class);
            $result = $service->testConnection($integration);

            if ($result['success']) {
                $integration->update(['status' => 'connected']);
                $this->testResult = [
                    'success' => true,
                    'message' => 'Connection successful!',
                ];
            } else {
                $this->testResult = [
                    'success' => false,
                    'message' => $result['error'] ?? 'Connection failed',
                ];
            }

            $this->selectedIntegration = $integrationId;
            $this->showTestModal = true;
            $this->loadIntegrations();
        } catch (\Throwable $e) {
            Log::error('Test connection failed', ['error' => $e->getMessage()]);
            $this->testResult = [
                'success' => false,
                'message' => 'Error testing connection',
            ];
            $this->showTestModal = true;
        }
    }

    public function syncMachines($integrationId)
    {
        if (! $this->team) {
            $this->dispatchBrowserEvent('notify', ['type' => 'error', 'message' => 'No team context available']);

            return;
        }

        try {
            $integration = Integration::where('team_id', $this->team->id)
                ->findOrFail($integrationId);
            $this->authorize('sync', $integration);

            $service = app(IntegrationService::class);
            $result = $service->syncMachines($integration);

            if ($result['success']) {
                $integration->update([
                    'last_sync_at' => now(),
                    'last_sync_status' => 'success',
                ]);
                $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'Sync started successfully!']);
            } else {
                $integration->update(['last_sync_status' => 'failed']);
                $this->dispatchBrowserEvent('notify', ['type' => 'error', 'message' => 'Sync failed: '.$result['error']]);
            }

            $this->loadIntegrations();
        } catch (\Throwable $e) {
            Log::error('Sync machines failed', ['error' => $e->getMessage()]);
            $this->dispatchBrowserEvent('notify', ['type' => 'error', 'message' => 'Error starting sync']);
        }
    }

    public function deleteIntegration($integrationId)
    {
        if (! $this->team) {
            $this->dispatchBrowserEvent('notify', ['type' => 'error', 'message' => 'No team context available']);

            return;
        }

        try {
            $integration = Integration::where('team_id', $this->team->id)
                ->findOrFail($integrationId);
            $this->authorize('delete', $integration);
            $integration->delete();

            $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'Integration deleted successfully!']);
            $this->loadIntegrations();
        } catch (\Throwable $e) {
            Log::error('Delete integration failed', ['error' => $e->getMessage()]);
            $this->dispatchBrowserEvent('notify', ['type' => 'error', 'message' => 'Error deleting integration']);
        }
    }

    public function render()
    {
        return view('livewire.integration-manager');
    }
}
