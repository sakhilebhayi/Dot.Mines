<?php

namespace App\Jobs;

use App\Models\GdprRequest;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExportUserDataJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public bool $afterCommit = true;

    public function __construct(public readonly GdprRequest $gdprRequest) {}

    public function handle(): void
    {
        $this->gdprRequest->update(['status' => GdprRequest::STATUS_PROCESSING]);

        try {
            $user = User::findOrFail($this->gdprRequest->user_id);

            // Collect all user data
            $data = [
                'profile' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'created_at' => $user->created_at?->toIso8601String(),
                ],
                'teams' => $user->allTeams()->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'role' => $user->teamRole($t)?->key,
                ])->toArray(),
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

            // Notify user by email
            Mail::raw(
                'Your data export is ready. Download it within 7 days from your account settings.',
                fn ($m) => $m->to($user->email)
                    ->from(
                        (string) config('mail.addresses.privacy'),
                        (string) config('app.name'),
                    )
                    ->subject('Your Data Export is Ready – Mines')
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
