<?php

namespace App\Jobs;

use App\Models\GdprRequest;
use App\Models\OperatorFatigue;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * GDPR right-of-access: assembles everything the platform stores about the
 * requesting user into a JSON export behind a 7-day tokenised download.
 * Covers profile, team memberships, operator fatigue history (personal
 * safety data), and the user's activity log trail.
 */
class ExportUserDataJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public readonly GdprRequest $gdprRequest)
    {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $this->gdprRequest->update(['status' => GdprRequest::STATUS_PROCESSING]);

        try {
            $user = User::findOrFail($this->gdprRequest->user_id);

            $data = [
                'profile' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'created_at' => $user->created_at?->toIso8601String(),
                ],
                'teams' => $user->allTeams()->map(fn ($team) => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'role' => $user->teamRole($team)?->key,
                ])->toArray(),
                'operator_fatigue_records' => OperatorFatigue::query()
                    ->where('user_id', $user->id)
                    ->get()
                    ->map(fn (OperatorFatigue $fatigue) => [
                        'shift_date' => $fatigue->shift_date?->toDateString(),
                        'shift_type' => $fatigue->shift_type,
                        'hours_worked' => $fatigue->hours_worked,
                        'consecutive_days' => $fatigue->consecutive_days,
                        'fatigue_score' => $fatigue->fatigue_score,
                        'alert_level' => $fatigue->alert_level,
                    ])->toArray(),
                'activity_log' => DB::table('activity_logs')
                    ->where('user_id', $user->id)
                    ->orderByDesc('created_at')
                    ->get(['action', 'description', 'created_at'])
                    ->map(fn ($row) => (array) $row)
                    ->toArray(),
                'exported_at' => now()->toIso8601String(),
            ];

            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $filename = 'gdpr-exports/user-'.$user->id.'-'.now()->format('Ymd-His').'.json';

            Storage::disk('local')->put($filename, (string) $json);

            $token = Str::random(64);

            $this->gdprRequest->update([
                'status' => GdprRequest::STATUS_COMPLETED,
                'file_path' => $filename,
                'download_token' => $token,
                'token_expires_at' => now()->addDays(7),
                'completed_at' => now(),
            ]);

            Mail::raw(
                'Your data export is ready. Download it within 7 days from your Privacy & Data page.',
                fn ($m) => $m->to($user->email)
                    ->from(
                        (string) config('mail.addresses.privacy'),
                        (string) config('app.name'),
                    )
                    ->subject('Your Data Export is Ready – '.config('app.name'))
            );
        } catch (\Throwable $e) {
            Log::error('GDPR export failed', [
                'gdpr_request_id' => $this->gdprRequest->id,
                'user_id' => $this->gdprRequest->user_id,
                'error' => $e->getMessage(),
            ]);

            $this->gdprRequest->update(['status' => GdprRequest::STATUS_FAILED]);

            throw $e;
        }
    }
}
