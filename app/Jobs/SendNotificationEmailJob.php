<?php

namespace App\Jobs;

use App\Mail\NotificationAlertMail;
use App\Models\Notification;
use App\Models\NotificationDeliveryLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNotificationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var array<int> */
    public array $backoff = [30, 120, 300];

    /**
     * @param  array<int>  $userIds
     */
    public function __construct(
        public readonly int $notificationId,
        public readonly array $userIds,
    ) {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    public function handle(): void
    {
        $notification = Notification::find($this->notificationId);

        if (! $notification) {
            return;
        }

        $users = User::whereIn('id', $this->userIds)->get();

        foreach ($users as $user) {
            // User notification preferences (email toggle, quiet hours,
            // mandatory-critical floor) are the single source of truth --
            // same gate the in-app bell applies.
            if (! $user->wantsEmailAlert($notification->alert_level)) {
                continue;
            }

            $log = NotificationDeliveryLog::create([
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'channel' => 'email',
                'status' => 'queued',
            ]);

            try {
                Mail::to($user->email)->queue(new NotificationAlertMail($notification, $user));

                $log->update(['status' => 'sent', 'sent_at' => now()]);
            } catch (\Exception $e) {
                $log->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);

                Log::error('SendNotificationEmailJob: failed to queue email', [
                    'notification_id' => $this->notificationId,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendNotificationEmailJob permanently failed', [
            'notification_id' => $this->notificationId,
            'error' => $exception->getMessage(),
        ]);

        // L3: Forward to Sentry so the on-call team is alerted immediately.
        if (app()->bound('sentry')) {
            \Sentry\captureException($exception);
        }
    }
}
