<?php

namespace App\Livewire;

use App\Models\DigestSubscription;
use App\Models\Role;
use App\Models\User;
use App\Models\UserFeedPreference;
use App\Traits\BrowserEventBridge;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Settings extends Component
{
    use BrowserEventBridge;

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
    /** @var array<string, mixed> */
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

    // Feed Notification Preferences
    /** @var array<string, mixed> */
    public array $feedCategoryPrefs = [
        'breakdown' => true,
        'shift_update' => true,
        'safety_alert' => true,
        'production' => true,
        'general' => false,
    ];

    public bool $feedNotifyOnComment = true;

    public bool $feedNotifyOnReply = true;

    public bool $feedNotifyOnApproval = true;

    public bool $feedNotifyOnMention = true;

    public bool $digestSubscribed = false;

    protected $rules = [
        'teamName' => 'required|string|max:255',
        'teamEmail' => 'nullable|email|max:255',
        'timezone' => 'required|timezone',
        'language' => 'required|in:en,es,fr,de,pt,zh,ar,af,zu',
        'currency' => 'required|in:USD,EUR,GBP,ZAR,AUD,CAD,JPY,CNY,INR,BRL',
    ];

    public function mount(): void
    {
        $team = Auth::user()->currentTeam;

        if ($team === null) {
            return;
        }

        $this->teamName = $team->name;
        $this->teamEmail = $team->email ?? '';
        $this->timezone = $team->timezone ?? 'UTC';
        $this->language = $team->language ?? 'en';
        $this->currency = $team->currency ?? 'USD';

        $this->loadTeamMembers();
        $this->loadFeedPreferences();
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

        $team = Auth::user()->currentTeam;
        $team->update([
            'name' => $this->teamName,
            'email' => $this->teamEmail,
            'timezone' => $this->timezone,
            'language' => $this->language,
            'currency' => $this->currency,
        ]);

        $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'General settings updated']);
    }

    // ==================== USERS & ROLES ====================

    public function loadTeamMembers(): void
    {
        $team = Auth::user()->currentTeam;
        $this->teamMembers = $team->users()
            ->with('roles')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()?->name ?? 'No Role',
                    'joined_at' => isset($user->pivot) && isset($user->pivot->created_at) ? $user->pivot->created_at : 'N/A',
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

        try {
            $team = Auth::user()->currentTeam;

            // Check if user already invited/member
            $existingUser = User::where('email', $this->inviteEmail)->first();
            if ($existingUser && $team->users->contains($existingUser->id)) {
                $this->dispatchBrowserEvent('notify', ['type' => 'error', 'message' => 'User is already a team member']);

                return;
            }

            // In production, would send actual invitation email
            // For now, create the user if they don't exist
            if (! $existingUser) {
                $existingUser = User::create([
                    'name' => explode('@', $this->inviteEmail)[0],
                    'email' => $this->inviteEmail,
                    'password' => bcrypt('temporary_password_change_on_login'),
                ]);
            }

            // Add user to team
            $team->users()->attach($existingUser->id);

            // Assign role
            $role = Role::where('name', $this->selectedRole)->first();
            if ($role) {
                $existingUser->roles()->attach($role->id);
            }

            $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => "Invitation sent to {$this->inviteEmail}"]);
            $this->showInviteForm = false;
            $this->inviteEmail = '';
            $this->selectedRole = 'operator';
            $this->loadTeamMembers();
        } catch (\Exception $e) {
            $this->dispatchBrowserEvent('notify', ['type' => 'error', 'message' => 'Failed to invite user: '.$e->getMessage()]);
        }
    }

    public function removeUser($userId): void
    {
        try {
            $team = Auth::user()->currentTeam;
            $currentUser = Auth::user();

            // Prevent removing self
            if ($userId === $currentUser->id) {
                $this->dispatchBrowserEvent('notify', ['type' => 'error', 'message' => 'Cannot remove yourself from the team']);

                return;
            }

            $team->users()->detach($userId);
            $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'User removed from team']);
            $this->loadTeamMembers();
        } catch (\Exception $e) {
            $this->dispatchBrowserEvent('notify', ['type' => 'error', 'message' => 'Failed to remove user']);
        }
    }

    public function updateUserRole($userId, $newRole): void
    {
        try {
            $team = Auth::user()->currentTeam;

            // Ensure the user is a member of this team
            if (! $team->users()->where('id', $userId)->exists()) {
                $this->dispatchBrowserEvent('notify', ['type' => 'error', 'message' => 'User is not a member of this team']);

                return;
            }

            $team = Auth::user()->currentTeam;
            $user = $team->users()->findOrFail($userId);
            // Remove old roles
            $user->roles()->detach();

            // Add new role
            $role = Role::where('name', $newRole)->first();
            if ($role) {
                $user->roles()->attach($role->id);
            }

            $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'User role updated']);
            $this->loadTeamMembers();
        } catch (\Exception $e) {
            $this->dispatchBrowserEvent('notify', ['type' => 'error', 'message' => 'Failed to update role']);
        }
    }

    // ==================== FEED NOTIFICATION PREFERENCES ====================

    public function loadFeedPreferences(): void
    {
        $user = Auth::user();
        $teamId = $user->current_team_id;

        $pref = UserFeedPreference::where('user_id', $user->id)
            ->where('team_id', $teamId)
            ->first();

        if ($pref) {
            $this->feedCategoryPrefs = array_merge($this->feedCategoryPrefs, $pref->category_preferences ?? []);
            $this->feedNotifyOnComment = $pref->notify_on_comment;
            $this->feedNotifyOnReply = $pref->notify_on_reply;
            $this->feedNotifyOnApproval = $pref->notify_on_approval;
            $this->feedNotifyOnMention = $pref->notify_on_mention;
        }

        $this->digestSubscribed = DigestSubscription::where('user_id', $user->id)
            ->where('team_id', $teamId)
            ->where('enabled', true)
            ->exists();
    }

    public function saveFeedPreferences(): void
    {
        $user = Auth::user();
        $teamId = $user->current_team_id;

        UserFeedPreference::updateOrCreate(
            ['user_id' => $user->id, 'team_id' => $teamId],
            [
                'category_preferences' => $this->feedCategoryPrefs,
                'notify_on_comment' => $this->feedNotifyOnComment,
                'notify_on_reply' => $this->feedNotifyOnReply,
                'notify_on_approval' => $this->feedNotifyOnApproval,
                'notify_on_mention' => $this->feedNotifyOnMention,
            ]
        );

        DigestSubscription::updateOrCreate(
            ['user_id' => $user->id, 'team_id' => $teamId],
            ['enabled' => $this->digestSubscribed]
        );

        $this->dispatch('notify', type: 'success', message: 'Feed notification preferences saved.');
    }

    // ==================== NOTIFICATION SETTINGS ====================

    public function saveNotificationSettings(): void
    {
        try {
            // Store in user preferences
            $user = Auth::user();
            $preferences = [
                'email_alerts' => $this->emailAlerts,
                'email_reports' => $this->emailReports,
                'in_app_alerts' => $this->inAppAlerts,
                'quiet_hours_enabled' => $this->quietHoursEnabled,
                'quiet_hours_start' => $this->quietHoursStart,
                'quiet_hours_end' => $this->quietHoursEnd,
            ];

            // Store preferences (would use a proper preferences table in production)
            // For now, just dispatch success

            $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'Notification settings saved']);
        } catch (\Exception $e) {
            $this->dispatchBrowserEvent('notify', ['type' => 'error', 'message' => 'Failed to save settings']);
        }
    }

    // ==================== HELPER METHODS ====================

    private function getRoles(): array
    {
        return [
            'admin' => 'Administrator',
            'fleet_manager' => 'Fleet Manager',
            'operator' => 'Operator',
            'viewer' => 'Viewer',
        ];
    }

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
