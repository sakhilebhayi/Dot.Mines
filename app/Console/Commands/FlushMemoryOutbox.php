<?php

namespace App\Console\Commands;

use App\Models\MemoryOutboxRow;
use App\Services\Memory\Exceptions\MemoryCredentialRejected;
use App\Services\Memory\Exceptions\MemoryRefused;
use App\Services\Memory\Exceptions\MemoryUnavailable;
use App\Services\Memory\MemoryClientFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Sends pending intelligence-loop records in order, from the once-a-minute
 * scheduler tick (no queued job: the queue runs --tries=1 with no resident
 * worker). An unreachable Memory backs the row off and ends the tick; a
 * refused payload is given up on; a rejected credential ends the tick
 * with a warning until the token is replaced.
 */
class FlushMemoryOutbox extends Command
{
    protected $signature = 'memory:flush {--limit=200 : Rows per tick}';

    protected $description = 'Send pending Dot.Memory intelligence-loop records from the outbox.';

    public function handle(MemoryClientFactory $clients): int
    {
        $client = $clients->fromConfig();

        if ($client === null) {
            $this->line('Dot.Memory is not configured; nothing sent.');

            return self::SUCCESS;
        }

        $limitOption = $this->option('limit');
        $limit = is_numeric($limitOption) ? max(1, (int) $limitOption) : 200;
        $sent = 0;
        $stuck = 0;

        foreach (MemoryOutboxRow::query()->due()->limit($limit)->get() as $row) {
            try {
                $client->record($row->stage, $row->payload);
                $row->forceFill(['sent_at' => now(), 'last_error' => null])->save();
                $sent++;
            } catch (MemoryRefused $e) {
                $row->forceFill(['attempts' => MemoryOutboxRow::MAX_ATTEMPTS, 'last_error' => $e->getMessage()])->save();
                $stuck++;
            } catch (MemoryCredentialRejected $e) {
                $row->forceFill(['attempts' => $row->attempts + 1, 'last_error' => $e->getMessage()])->save();
                $row->forceFill(['next_attempt_at' => $row->backoff()])->save();
                Log::warning('Dot.Memory rejected the service token; the loop is paused until it is replaced.');
                $this->warn($e->getMessage());

                return self::FAILURE;
            } catch (MemoryUnavailable $e) {
                $row->forceFill(['attempts' => $row->attempts + 1, 'last_error' => $e->getMessage()])->save();
                $row->forceFill(['next_attempt_at' => $row->backoff()])->save();
                $this->line('Dot.Memory unreachable; will retry at '.$row->backoff()->toDateTimeString().'.');

                break;
            }
        }

        $this->info("Sent {$sent} record(s)".($stuck > 0 ? ", {$stuck} given up on" : '').'.');

        return self::SUCCESS;
    }
}
