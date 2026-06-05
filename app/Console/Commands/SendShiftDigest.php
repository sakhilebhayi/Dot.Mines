<?php

namespace App\Console\Commands;

use App\Mail\ShiftDigestMail;
use App\Models\DigestSubscription;
use App\Models\FeedPost;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendShiftDigest extends Command
{
    /**
     * Signature: optionally pass which shift to summarise (A, B, or C).
     * When run by the scheduler it auto-detects the shift that just ended.
     */
    protected $signature = 'feed:digest {--shift= : Shift to summarise (A, B, or C)}';

    protected $description = 'Send shift summary email digest to subscribed supervisors/managers';

    /** @var array<string, array{start: array{H: int, i: int}, end: array{H: int, i: int}}> */
    private array $shiftWindows = [
        'A' => ['start' => ['H' => 6,  'i' => 0],  'end' => ['H' => 14, 'i' => 0]],
        'B' => ['start' => ['H' => 14, 'i' => 0],  'end' => ['H' => 22, 'i' => 0]],
        'C' => ['start' => ['H' => 22, 'i' => 0],  'end' => ['H' => 6,  'i' => 0]],
    ];

    public function handle(): int
    {
        $shift = strtoupper(is_string($this->option('shift')) ? $this->option('shift') : $this->detectCompletedShift());

        if (! array_key_exists($shift, $this->shiftWindows)) {
            $this->error("Invalid shift '{$shift}'. Use A, B or C.");

            return self::FAILURE;
        }

        [$from, $to] = $this->shiftTimeRange($shift);

        $this->info("Sending {$shift} shift digest ({$from->format('H:i')} – {$to->format('H:i')})");

        $teams = Team::all();

        foreach ($teams as $team) {
            try {
                $this->sendDigestForTeam($team, $shift, $from, $to);
            } catch (\Exception $e) {
                Log::error('Shift digest failed for team', [
                    'team_id' => $team->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info('Digest dispatch complete.');

        return self::SUCCESS;
    }

    private function sendDigestForTeam(Team $team, string $shift, Carbon $from, Carbon $to): void
    {
        // Build stats
        $posts = FeedPost::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('shift', $shift)
            ->whereBetween('created_at', [$from, $to])
            ->with(['author:id,name', 'approval'])
            ->withCount(['acknowledgements', 'likes', 'comments as comment_count'])
            ->get();

        if ($posts->isEmpty()) {
            return; // No posts this shift — skip
        }

        // Stats by category
        $byCategory = [];
        foreach ($posts as $post) {
            $cat = $post->category;
            if (! isset($byCategory[$cat])) {
                $byCategory[$cat] = ['count' => 0, 'acknowledged' => 0];
            }
            $byCategory[$cat]['count']++;
            if ($post->acknowledgements_count > 0) {
                $byCategory[$cat]['acknowledged']++;
            }
        }

        $unacknowledgedCritical = $posts
            ->where('priority', 'critical')
            ->where('acknowledgements_count', 0)
            ->count();

        $breakdownCount = $posts->where('category', 'breakdown')->count();

        $stats = [
            'by_category' => $byCategory,
            'unacknowledged_critical' => $unacknowledgedCritical,
            'breakdown_count' => $breakdownCount,
        ];

        // Top posts by engagement
        $topPosts = $posts
            ->sortByDesc(fn ($p) => $p->likes_count + $p->comment_count)
            ->take(5)
            ->map(fn ($p) => [
                'category' => $p->category,
                'body' => $p->body,
                'author' => $p->author?->name,
                'likes' => $p->likes_count,
                'comments' => $p->comment_count,
            ])
            ->values()
            ->toArray();

        // Pending approvals (posts awaiting review)
        $pendingApprovals = $posts
            ->filter(fn ($p) => $p->approval?->status === 'pending' || $p->approval === null)
            ->take(10)
            ->map(fn ($p) => [
                'category' => $p->category,
                'body' => $p->body,
                'author' => $p->author?->name,
            ])
            ->values()
            ->toArray();

        // Resolve recipient emails (digest subscribers on this team)
        $subscribers = DigestSubscription::where('team_id', $team->id)
            ->where('enabled', true)
            ->pluck('user_id');

        if ($subscribers->isEmpty()) {
            return;
        }

        $recipients = User::whereIn('id', $subscribers)->get(['id', 'name', 'email']);

        foreach ($recipients as $recipient) {
            Mail::to($recipient->email)->queue(new ShiftDigestMail(
                shift: $shift,
                teamName: $team->name,
                stats: $stats,
                topPosts: $topPosts,
                pendingApprovals: $pendingApprovals,
            ));
        }

        $this->line("  Team [{$team->name}] → {$recipients->count()} recipients");
    }

    /**
     * Determine which shift just ended based on the current hour.
     */
    private function detectCompletedShift(): string
    {
        $hour = (int) now()->format('H');

        // At 06:00 → shift C just ended
        // At 14:00 → shift A just ended
        // At 22:00 → shift B just ended
        return match (true) {
            $hour === 6 => 'C',
            $hour === 14 => 'A',
            $hour === 22 => 'B',
            default => 'A',
        };
    }

    /**
     * Return [Carbon $from, Carbon $to] for the shift on today's date.
     *
     * @return array<mixed>
     */
    private function shiftTimeRange(string $shift): array
    {
        $now = now();
        $today = $now->toDateString();

        return match ($shift) {
            'A' => [
                Carbon::parse("{$today} 06:00:00"),
                Carbon::parse("{$today} 14:00:00"),
            ],
            'B' => [
                Carbon::parse("{$today} 14:00:00"),
                Carbon::parse("{$today} 22:00:00"),
            ],
            'C' => [
                // Shift C spans midnight: yesterday 22:00 → today 06:00
                Carbon::parse("{$today} 22:00:00")->subDay(),
                Carbon::parse("{$today} 06:00:00"),
            ],
            default => [Carbon::parse("{$today} 06:00:00"), Carbon::parse("{$today} 14:00:00")],
        };
    }
}
