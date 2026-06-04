<?php

namespace App\Livewire;

use App\Mail\FeedOnboardingInvite;
use App\Models\FeedAuditLog;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class WhatsAppMigration extends Component
{
    public string $goLiveDate = '';

    public string $goLiveTime = '06:00';

    public string $inviteMessage = '';

    public bool $inviteSending = false;

    public function mount(): void
    {
        abort_if(! Auth::user()?->hasRole('admin'), 403);

        $team = Auth::user()->currentTeam;
        if ($team->feed_go_live_at) {
            $this->goLiveDate = $team->feed_go_live_at->format('Y-m-d');
            $this->goLiveTime = $team->feed_go_live_at->format('H:i');
        }
        $this->inviteMessage = "You're invited to join the Operations Feed — our new platform replacing WhatsApp for mine communications. Sign in to get started.";
    }

    public function saveGoLiveDate(): void
    {
        $this->validate([
            'goLiveDate' => 'required|date',
            'goLiveTime' => 'required|date_format:H:i',
        ]);

        $dt = Carbon::parse("{$this->goLiveDate} {$this->goLiveTime}");
        $team = Auth::user()->currentTeam;
        $team->update(['feed_go_live_at' => $dt]);

        FeedAuditLog::create([
            'team_id' => $team->id,
            'actor_id' => Auth::id(),
            'action' => 'go_live_set',
            'subject_type' => Team::class,
            'subject_id' => $team->id,
            'meta' => ['go_live_at' => $dt->toIso8601String()],
        ]);

        $this->dispatch('notify', type: 'success', message: 'Go-live date saved: '.$dt->format('M d, Y H:i'));
    }

    public function sendInvites(): void
    {
        $this->validate(['inviteMessage' => 'required|string|max:1000']);

        $team = Auth::user()->currentTeam;
        $users = User::whereHas('teams', fn ($q) => $q->where('teams.id', $team->id))
            ->where('id', '!=', Auth::id())
            ->get();

        $sent = 0;
        $failed = 0;
        foreach ($users as $user) {
            try {
                Mail::to($user->email)->queue(new FeedOnboardingInvite($user, $team, $this->inviteMessage));
                FeedAuditLog::create([
                    'team_id' => $team->id,
                    'actor_id' => Auth::id(),
                    'action' => 'invite_sent',
                    'subject_type' => User::class,
                    'subject_id' => $user->id,
                    'meta' => ['email' => $user->email],
                ]);
                $sent++;
            } catch (\Exception $e) {
                Log::error('Failed to queue onboarding invite', ['user_id' => $user->id, 'error' => $e->getMessage()]);
                $failed++;
            }
        }

        $msg = "Invites queued for {$sent} team member(s).";
        if ($failed) {
            $msg .= " {$failed} failed — check logs.";
        }
        $this->dispatch('notify', type: 'success', message: $msg);
    }

    public function render(): \Illuminate\View\View
    {
        $team = Auth::user()->currentTeam;
        $users = User::whereHas('teams', fn ($q) => $q->where('teams.id', $team->id))
            ->select('id', 'name', 'email', 'last_login_at')
            ->orderByDesc('last_login_at')
            ->get();

        $invitesSent = FeedAuditLog::where('team_id', $team->id)
            ->where('action', 'invite_sent')
            ->pluck('subject_id')
            ->unique();

        return view('livewire.whatsapp-migration', [
            'team' => $team,
            'users' => $users,
            'invitesSent' => $invitesSent,
        ])->layout('layouts.app');
    }
}
