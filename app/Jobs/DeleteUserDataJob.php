<?php

namespace App\Jobs;

use App\Models\GdprRequest;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Jetstream\Contracts\DeletesUsers;

/**
 * GDPR right-to-erasure: permanently deletes the requesting user's account
 * and personal data.
 *
 * What happens to linked records:
 * - alerts.acknowledged_by / resolved_by are nulled FIRST -- their foreign
 *   keys are cascadeOnDelete, so without this the team's entire alert
 *   history would silently vanish with the departing user.
 * - activity_logs and operator_fatigue rows cascade with the user by
 *   schema design: both are personal trails and fall under erasure.
 * - Account teardown itself (team detach, owned-team deletion, profile
 *   photo, API tokens) is delegated to Jetstream's DeleteUser action --
 *   the single source of truth for account deletion.
 * - The GdprRequest row survives (user_id nulls out) as the compliance
 *   record, identified by the stored email.
 */
class DeleteUserDataJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public readonly GdprRequest $gdprRequest)
    {
        $this->afterCommit();
    }

    public function handle(DeletesUsers $deleter): void
    {
        $this->gdprRequest->update(['status' => GdprRequest::STATUS_PROCESSING]);

        try {
            $user = User::findOrFail($this->gdprRequest->user_id);
            $email = $user->email;

            DB::transaction(function () use ($user, $deleter) {
                DB::table('alerts')
                    ->where('acknowledged_by', $user->id)
                    ->update(['acknowledged_by' => null]);

                DB::table('alerts')
                    ->where('resolved_by', $user->id)
                    ->update(['resolved_by' => null]);

                $deleter->delete($user);
            });

            $this->gdprRequest->update([
                'status' => GdprRequest::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            Mail::raw(
                'Your account and personal data have been permanently deleted from '.config('app.name').' as requested.',
                fn ($m) => $m->to($email)
                    ->from(
                        (string) config('mail.addresses.privacy'),
                        (string) config('app.name'),
                    )
                    ->subject('Account Deleted – '.config('app.name'))
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
}
