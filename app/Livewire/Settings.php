<?php

namespace App\Livewire;

use App\Livewire\Concerns\NotifiesUser;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use App\Support\ApiPayload;
use App\Support\CurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\InvitesTeamMembers;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Settings extends Component
{
    use NotifiesUser;

    public string $activeTab = 'general';

    // General Settings
    #[Validate('required|string|max:255')]
    public string $teamName = '';

    #[Validate('nullable|string|max:255')]
    public string $teamEmail = '';

    #[Validate('required|timezone')]
    public string $timezone = 'UTC';

    #[Validate('required|in:en,es,fr,de,pt,zh,ar,af,zu')]
    public string $language = 'en';

    #[Validate('required|in:USD,EUR,GBP,ZAR,AUD,CAD,JPY,CNY,INR,BRL')]
    public string $currency = 'USD';

    // Users & Roles
    /** @var array<int|string, mixed> */
    public array $teamMembers = [];

    public string $inviteEmail = '';

    public string $selectedRole = 'operator';

    public bool $showInviteForm = false;

    // Notification Settings
    public bool $emailAlerts = true;

    public bool $emailReports = true;

    public bool $inAppAlerts = true;

    public string $quietHoursStart = '22:00';

    public string $quietHoursEnd = '08:00';

    public bool $quietHoursEnabled = false;

    /**
     * Filters what severity of alert/fuel-alert/AI-alert appears in the
     * notification bell. 'critical' is never filterable -- AINotifications
     * always shows it regardless of this setting, so a user can't
     * accidentally hide something that genuinely needs immediate attention.
     */
    public string $notificationMinSeverity = 'low';

    /** @var array<string, string> */
    protected array $rules = [
        'teamEmail' => 'nullable|email|max:255',
        'timezone' => 'required|timezone',
        'language' => 'required|in:en,es,fr,de,pt,zh,ar,af,zu',
        'currency' => 'required|in:USD,EUR,GBP,ZAR,AUD,CAD,JPY,CNY,INR,BRL',
        'notificationMinSeverity' => 'required|in:low,medium,high,critical',
    ];

    public function mount(): void
    {
        $team = CurrentUser::team();
        $this->teamName = $team->name;
        $this->teamEmail = $team->email ?? '';
        $this->timezone = $team->timezone;
        $this->language = $team->language;
        $this->currency = $team->currency;

        $preferences = CurrentUser::get()?->notification_preferences ?? [];
        $this->emailAlerts = (bool) ($preferences['email_alerts'] ?? true);
        $this->emailReports = (bool) ($preferences['email_reports'] ?? true);
        $this->inAppAlerts = (bool) ($preferences['in_app_alerts'] ?? true);
        $this->quietHoursEnabled = (bool) ($preferences['quiet_hours_enabled'] ?? false);
        $this->quietHoursStart = ApiPayload::str($preferences['quiet_hours_start'] ?? null, '22:00');
        $this->quietHoursEnd = ApiPayload::str($preferences['quiet_hours_end'] ?? null, '08:00');
        $this->notificationMinSeverity = ApiPayload::str($preferences['min_severity'] ?? null, 'low');

        $this->loadTeamMembers();
    }

    public function render(): View
    {
        return view('livewire.settings', [
            'roles' => $this->getRoles(),
            'timezones' => $this->getTimezones(),
            'languages' => $this->getLanguages(),
            'currencies' => $this->getCurrencies(),
        ]);
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    // ==================== GENERAL SETTINGS ====================

    public function saveGeneralSettings(): void
    {
        $this->validate();

        $team = CurrentUser::team();
        $this->authorize('update', $team);

        $team->update([
            'name' => $this->teamName,
            'email' => $this->teamEmail,
            'timezone' => $this->timezone,
            'language' => $this->language,
            'currency' => $this->currency,
        ]);

        $this->notify('General settings updated', 'success');
    }

    // ==================== USERS & ROLES ====================

    public function loadTeamMembers(): void
    {
        $team = CurrentUser::team();
        $this->teamMembers = $team->users()
            ->with('roles')
            ->get()
            ->map(function (User $user): array {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()?->name ?? 'No Role',
                    // Jetstream aliases the membership pivot to 'membership',
                    // so $user->pivot is never set on this relation.
                    'joined_at' => data_get($user, 'membership.created_at') ?? 'N/A',
                ];
            })
            ->toArray();
    }

    public function toggleInviteForm(): void
    {
        $this->showInviteForm = ! $this->showInviteForm;
        if (! $this->showInviteForm) {
            $this->inviteEmail = '';
            $this->selectedRole = 'operator';
        }
    }

    public function inviteUser(): void
    {
        $this->validate([
            'inviteEmail' => 'required|email|max:255',
            'selectedRole' => 'required|string',
        ]);

        $inviter = CurrentUser::get();
        $team = CurrentUser::team();

        if ($inviter === null) {
            return;
        }

        try {
            $this->authorize('addTeamMember', $team);

            // Delegate to Jetstream's own invitation action (also used by
            // teams.show) instead of creating the account ourselves: it
            // creates a real TeamInvitation row and queues the actual
            // invitation email. The account used to be created here directly
            // with a hardcoded literal password and no email ever sent --
            // the invited person had no way to learn it or sign in.
            app(InvitesTeamMembers::class)->invite(
                $inviter,
                $team,
                $this->inviteEmail,
                $this->selectedRole
            );

            $this->notify("Invitation sent to {$this->inviteEmail}", 'success');
            $this->showInviteForm = false;
            $this->inviteEmail = '';
            $this->selectedRole = 'operator';
            $this->loadTeamMembers();
        } catch (ValidationException $e) {
            $this->notify(ApiPayload::str(collect($e->errors())->flatten()->first(), 'Failed to invite user'), 'error');
        } catch (\Throwable $e) {
            Log::error('Failed to invite team member', ['team_id' => $team->id, 'error' => $e->getMessage()]);
            $this->notify("We couldn't send that invitation. Please check the email address and try again.", 'error');
        }
    }

    /**
     * @param  int|string  $userId
     */
    public function removeUser($userId): void
    {
        try {
            $team = CurrentUser::team();
            $this->authorize('removeTeamMember', $team);
            $currentUser = CurrentUser::get();

            // Prevent removing self
            if ((int) $userId === (int) $currentUser?->id) {
                $this->notify('Cannot remove yourself from the team', 'error');

                return;
            }

            $team->users()->detach($userId);
            $this->notify('User removed from team', 'success');
            $this->loadTeamMembers();
        } catch (\Exception $e) {
            $this->notify('Failed to remove user', 'error');
        }
    }

    /**
     * @param  string  $newRole
     * @param  int|string  $userId
     */
    public function updateUserRole($userId, $newRole): void
    {
        try {
            $team = CurrentUser::team();
            $this->authorize('updateTeamMember', $team);

            // Ensure the user is a member of this team
            if (! $team->users()->where('id', $userId)->exists()) {
                $this->notify('User is not a member of this team', 'error');

                return;
            }

            $user = $team->users()->findOrFail($userId);

            TeamRoleProvisioner::assignRole($user, $team, $newRole);

            $this->notify('User role updated', 'success');
            $this->loadTeamMembers();
        } catch (\Exception $e) {
            $this->notify('Failed to update role', 'error');
        }
    }

    // ==================== NOTIFICATION SETTINGS ====================

    public function saveNotificationSettings(): void
    {
        $user = CurrentUser::get();

        if ($user === null) {
            return;
        }

        try {
            // Store in user preferences
            $user->update([
                'notification_preferences' => [
                    'email_alerts' => $this->emailAlerts,
                    'email_reports' => $this->emailReports,
                    'in_app_alerts' => $this->inAppAlerts,
                    'quiet_hours_enabled' => $this->quietHoursEnabled,
                    'quiet_hours_start' => $this->quietHoursStart,
                    'quiet_hours_end' => $this->quietHoursEnd,
                    'min_severity' => $this->notificationMinSeverity,
                ],
            ]);

            $this->notify('Notification settings saved', 'success');
        } catch (\Exception $e) {
            $this->notify('Failed to save settings', 'error');
        }
    }

    // ==================== HELPER METHODS ====================

    /**
     * @return array<string, string>
     */
    private function getRoles(): array
    {
        return [
            'admin' => 'Administrator',
            'fleet_manager' => 'Fleet Manager',
            'operator' => 'Operator',
            'viewer' => 'Viewer',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getTimezones(): array
    {
        return [
            'UTC' => 'UTC',
            'America/New_York' => 'Eastern Time',
            'America/Chicago' => 'Central Time',
            'America/Denver' => 'Mountain Time',
            'America/Los_Angeles' => 'Pacific Time',
            'Europe/London' => 'London',
            'Europe/Paris' => 'Paris',
            'Australia/Sydney' => 'Sydney',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getLanguages(): array
    {
        return [
            'en' => 'English',
            'es' => 'Español (Spanish)',
            'fr' => 'Français (French)',
            'de' => 'Deutsch (German)',
            'pt' => 'Português (Portuguese)',
            'zh' => '中文 (Chinese)',
            'ar' => 'العربية (Arabic)',
            'af' => 'Afrikaans',
            'zu' => 'isiZulu',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getCurrencies(): array
    {
        return [
            'USD' => 'US Dollar ($)',
            'EUR' => 'Euro (€)',
            'GBP' => 'British Pound (£)',
            'ZAR' => 'South African Rand (R)',
            'AUD' => 'Australian Dollar (A$)',
            'CAD' => 'Canadian Dollar (C$)',
            'JPY' => 'Japanese Yen (¥)',
            'CNY' => 'Chinese Yuan (¥)',
            'INR' => 'Indian Rupee (₹)',
            'BRL' => 'Brazilian Real (R$)',
        ];
    }
}
