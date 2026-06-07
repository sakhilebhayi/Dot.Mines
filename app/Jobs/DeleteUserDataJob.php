<?php

namespace App\Jobs;

use App\Models\GdprRequest;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DeleteUserDataJob implements ShouldQueue
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
            $email = $user->email;

            DB::transaction(function () use ($user) {
                // Anonymise audit logs rather than hard-delete (legal requirement)
                DB::table('audit_logs')
                    ->where('user_id', $user->id)
                    ->update([
                        'user_id' => null,
                        'description' => '[DELETED USER]',
                    ]);

                // Remove personal data from feed posts (keep for operational records)
                DB::table('feed_posts')
                    ->where('author_id', $user->id)
                    ->update(['author_id' => null]);

                // Delete user tokens, sessions, notifications
                DB::table('personal_access_tokens')
                    ->where('tokenable_id', $user->id)
                    ->where('tokenable_type', get_class($user))
                    ->delete();

                DB::table('notifications')
                    ->where('notifiable_id', $user->id)
                    ->where('notifiable_type', get_class($user))
                    ->delete();

                // Hard-delete the user account
                $user->delete();
            });

            $this->gdprRequest->update([
                'status' => GdprRequest::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            Mail::raw(
                'Your account and personal data have been permanently deleted from Mines as requested.',
                fn ($m) => $m->to($email)
                    ->from(
                        (string) config('mail.addresses.privacy'),
                        (string) config('app.name'),
                    )
                    ->subject('Account Deleted – Mines')
            );
        } catch (\Throwable $e) {
            Log::error('GDPR deletion failed', [
                'gdpr_request_id' => $this->gdprRequest->id,
                'user_id' => $this->gdprRequest->user_id,
                'error' => $e->getMessage(),
            ]);

            $this->gdprRequest->update(['status' => GdprRequest::STATUS_FAILED]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('DeleteUserDataJob permanently failed', [
            'gdpr_request_id' => $this->gdprRequest->id,
            'user_id' => $this->gdprRequest->user_id,
            'error' => $exception->getMessage(),
        ]);

        $this->gdprRequest->update(['status' => GdprRequest::STATUS_FAILED]);
    }
}
