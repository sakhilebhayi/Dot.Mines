<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\WebhookSignature;
use App\Services\Webhooks\WebhookUrlGuard;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * POSTs one delivery to one endpoint, and decides what happens next.
 *
 * This job never throws. The queue worker on this host runs `--tries=1` from
 * a once-a-minute cron tick (there is no resident worker on shared hosting),
 * so an exception escaping here would fail the delivery permanently on the
 * first connection blip. Retries are therefore explicit: a failed attempt
 * records what happened and dispatches its own successor with a delay.
 */
class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The queue name must also appear in the scheduler's `queue:work --queue=`
     * list in routes/console.php. A queue nothing drains is a queue that
     * silently fills up -- that has happened here before.
     */
    public function __construct(public readonly int $deliveryId)
    {
        $this->onQueue('webhooks');
    }

    public function handle(WebhookUrlGuard $guard): void
    {
        $delivery = WebhookDelivery::find($this->deliveryId);

        if ($delivery === null || $delivery->status !== WebhookDelivery::STATUS_PENDING) {
            return;
        }

        $query = WebhookEndpoint::withoutTeamFilter();
        $query->whereKey($delivery->webhook_endpoint_id);
        $endpoint = $query->first();

        if ($endpoint === null || ! $endpoint->isDeliverable()) {
            $this->giveUp($delivery, 'The endpoint is no longer active.');

            return;
        }

        // Re-checked on every attempt, not just when the URL was saved: a
        // hostname that resolved publicly yesterday can point at an internal
        // address today, and this job runs with the network's trust.
        $rejection = $guard->rejectionReason($endpoint->url);

        if ($rejection !== null) {
            $this->giveUp($delivery, $rejection);
            $this->recordFailure($endpoint, $rejection);

            return;
        }

        $delivery->attempts++;

        $body = (string) json_encode($delivery->payload, JSON_UNESCAPED_SLASHES);
        $startedAt = microtime(true);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => 'Mines-Webhooks/1.0',
                'X-Mines-Event' => $delivery->event,
                'X-Mines-Delivery' => (string) $delivery->id,
                WebhookSignature::HEADER => WebhookSignature::header($body, $endpoint->secret),
            ])
                ->withOptions(['allow_redirects' => false]) // a redirect is a way back inside the network
                ->connectTimeout(5)
                ->timeout(10)
                ->withBody($body, 'application/json')
                ->post($endpoint->url);

            $delivery->duration_ms = (int) round((microtime(true) - $startedAt) * 1000.0);
            $delivery->response_status = $response->status();

            if ($response->successful()) {
                $delivery->status = WebhookDelivery::STATUS_DELIVERED;
                $delivery->delivered_at = Carbon::now();
                $delivery->error = null;
                $delivery->next_attempt_at = null;
                $delivery->save();

                $this->recordSuccess($endpoint);

                return;
            }

            $this->scheduleRetryOrFail($delivery, $endpoint, "The endpoint responded {$response->status()}.");
        } catch (Throwable $e) {
            $delivery->duration_ms = (int) round((microtime(true) - $startedAt) * 1000.0);

            $this->scheduleRetryOrFail($delivery, $endpoint, $this->summarize($e));
        }
    }

    private function scheduleRetryOrFail(WebhookDelivery $delivery, WebhookEndpoint $endpoint, string $error): void
    {
        $delivery->error = $error;
        $delay = $delivery->attempts >= WebhookDelivery::MAX_ATTEMPTS
            ? null
            : $delivery->delayBeforeNextAttempt();

        if ($delay === null) {
            $delivery->status = WebhookDelivery::STATUS_FAILED;
            $delivery->next_attempt_at = null;
            $delivery->save();

            $this->recordFailure($endpoint, $error);

            return;
        }

        $delivery->next_attempt_at = Carbon::now()->addSeconds($delay);
        $delivery->save();

        self::dispatch($delivery->id)->delay(now()->addSeconds($delay));
    }

    private function giveUp(WebhookDelivery $delivery, string $error): void
    {
        $delivery->status = WebhookDelivery::STATUS_FAILED;
        $delivery->error = $error;
        $delivery->next_attempt_at = null;
        $delivery->save();
    }

    private function recordSuccess(WebhookEndpoint $endpoint): void
    {
        $endpoint->consecutive_failures = 0;
        $endpoint->last_success_at = Carbon::now();
        $endpoint->last_failure_reason = null;
        $endpoint->save();
    }

    /**
     * A receiver that has failed every retry, repeatedly, is not coming back
     * on its own. Switching it off stops every future event queuing a job
     * that cannot succeed, and makes the breakage visible to its owner
     * instead of silently absent.
     */
    private function recordFailure(WebhookEndpoint $endpoint, string $error): void
    {
        $endpoint->consecutive_failures++;
        $endpoint->last_failure_at = Carbon::now();
        $endpoint->last_failure_reason = $error;

        if ($endpoint->consecutive_failures >= WebhookEndpoint::FAILURES_BEFORE_AUTO_DISABLE) {
            $endpoint->is_active = false;
            $endpoint->auto_disabled_at = Carbon::now();

            Log::warning('Webhook endpoint auto-disabled after repeated failures.', [
                'endpoint_id' => $endpoint->id,
                'team_id' => $endpoint->team_id,
                'last_error' => $error,
            ]);
        }

        $endpoint->save();
    }

    /**
     * Exception messages from HTTP clients carry the full URL and sometimes
     * headers; the delivery log is shown in the UI, so keep it to the class
     * and a trimmed message.
     */
    private function summarize(Throwable $e): string
    {
        $message = trim($e->getMessage());

        if (mb_strlen($message) > 180) {
            $message = mb_substr($message, 0, 177).'...';
        }

        return class_basename($e).': '.$message;
    }
}
