<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendFeedNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 30;

    /** @var array<int> */
    public array $backoff = [30, 120];

    /**
     * @param  int[]  $recipientIds  User IDs to notify
     * @param  array{team_id:int, type:string, title:string, message:string, alert_level:string, data:array, action_url:string|null}  $payload
     */
    public function __construct(
        protected array $recipientIds,
        protected array $payload,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        if (empty($this->recipientIds)) {
            return;
        }

        try {
            // Create a single in-app notification record for the team
            $notification = Notification::create([
                'team_id' => $this->payload['team_id'],
                'type' => $this->payload['type'],
                'title' => $this->payload['title'],
                'message' => $this->payload['message'],
                'alert_level' => $this->payload['alert_level'],
                'data' => $this->payload['data'] ?? null,
                'action_url' => $this->payload['action_url'] ?? null,
                'is_read' => false,
            ]);

            Log::info('Feed notification created', [
                'notification_id' => $notification->id,
                'recipients' => count($this->recipientIds),
                'type' => $this->payload['type'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send feed notification', [
                'error' => $e->getMessage(),
                'payload' => $this->payload,
            ]);
            throw $e;
        }
    }
}
