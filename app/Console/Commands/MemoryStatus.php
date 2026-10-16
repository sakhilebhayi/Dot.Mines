<?php

namespace App\Console\Commands;

use App\Models\MemoryOutboxRow;
use App\Services\Memory\MemoryClientFactory;
use Illuminate\Console\Command;

/**
 * Read-only view of the Dot.Memory outbox, for the server-logs workflow:
 * is the loop configured, what waits, what was sent, what failed last.
 * Never prints the token or a payload.
 */
class MemoryStatus extends Command
{
    protected $signature = 'memory:status';

    protected $description = 'Show whether the Dot.Memory loop is configured and the state of its outbox.';

    public function handle(): int
    {
        $this->line('Configured: '.(MemoryClientFactory::configured() ? 'yes' : 'no (DOT_MEMORY_URL / DOT_MEMORY_TOKEN unset)'));

        $pending = MemoryOutboxRow::query()->whereNull('sent_at')->where('attempts', '<', MemoryOutboxRow::MAX_ATTEMPTS)->count();
        $stuck = MemoryOutboxRow::query()->whereNull('sent_at')->where('attempts', '>=', MemoryOutboxRow::MAX_ATTEMPTS)->count();
        $sent = MemoryOutboxRow::query()->whereNotNull('sent_at')->count();
        $this->line("Rows: pending {$pending}, stuck {$stuck}, sent {$sent}");

        $lastSent = MemoryOutboxRow::query()->whereNotNull('sent_at')->orderByDesc('sent_at')->first();
        $this->line('Last sent: '.($lastSent?->sent_at?->toDateTimeString() ?? 'never').($lastSent !== null ? ' ('.$lastSent->stage.' '.$lastSent->event_id.')' : ''));

        $newest = MemoryOutboxRow::query()->orderByDesc('id')->first();
        if ($newest === null) {
            $this->line('Newest row: none (no loop event has been recorded yet)');
        } else {
            $this->line('Newest row: '.$newest->stage.' '.$newest->event_id.' created '.$newest->created_at?->toDateTimeString().' attempts '.$newest->attempts.' sent '.($newest->sent_at?->toDateTimeString() ?? 'no'));
        }

        $failed = MemoryOutboxRow::query()->whereNotNull('last_error')->orderByDesc('updated_at')->first();
        if ($failed !== null) {
            $this->line('Last error: '.mb_substr((string) $failed->last_error, 0, 200).' (row '.$failed->event_id.', attempts '.$failed->attempts.')');
        }

        return self::SUCCESS;
    }
}
