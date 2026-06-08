<?php

namespace App\Services;

use App\Models\KnowledgeGraphEntry;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * MEGA V2 — Organisational Memory Service
 *
 * Manages the platform's knowledge graph of agent-sourced facts.
 * Provides remember/recall/forget operations and a memory health score
 * for the MEGA V2 "Organisational Memory" (+2%) and "Reality Alignment" (+3%) domains.
 */
class OrganisationalMemoryService
{
    /**
     * Store a new knowledge fact, invalidating any previous active entry
     * for the same (entry_type, subject, predicate) triple.
     *
     * @param  array<mixed>|null  $metadata
     */
    public function remember(
        string $entryType,
        string $subject,
        string $predicate,
        string $object,
        string $sourceAgent,
        float $confidence = 100.0,
        ?Carbon $validUntil = null,
        ?array $metadata = null,
    ): KnowledgeGraphEntry {
        // Expire previous active entries for this triple
        KnowledgeGraphEntry::where('entry_type', $entryType)
            ->where('subject', $subject)
            ->where('predicate', $predicate)
            ->where('is_active', true)
            ->update(['is_active' => false, 'valid_until' => now()]);

        return KnowledgeGraphEntry::create([
            'entry_type' => $entryType,
            'subject' => $subject,
            'predicate' => $predicate,
            'object' => $object,
            'confidence' => $confidence,
            'source_agent' => $sourceAgent,
            'valid_from' => now(),
            'valid_until' => $validUntil,
            'is_active' => true,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Retrieve the most recent active object for a triple, or null if not known.
     */
    public function recall(string $entryType, string $subject, string $predicate): ?string
    {
        $entry = KnowledgeGraphEntry::where('entry_type', $entryType)
            ->where('subject', $subject)
            ->where('predicate', $predicate)
            ->active()
            ->orderByDesc('valid_from')
            ->first();

        return $entry?->object;
    }

    /**
     * Retrieve all active facts for a subject.
     *
     * @return Collection<int, KnowledgeGraphEntry>
     */
    public function recallAll(string $entryType, string $subject): Collection
    {
        return KnowledgeGraphEntry::where('entry_type', $entryType)
            ->where('subject', $subject)
            ->active()
            ->orderByDesc('valid_from')
            ->get();
    }

    /**
     * Mark a specific triple as no longer valid (soft-delete).
     */
    public function forget(string $entryType, string $subject, string $predicate): int
    {
        return KnowledgeGraphEntry::where('entry_type', $entryType)
            ->where('subject', $subject)
            ->where('predicate', $predicate)
            ->where('is_active', true)
            ->update(['is_active' => false, 'valid_until' => now()]);
    }

    /**
     * Memory health score (0–100) for MEGA V2.
     *
     * Based on:
     * - Volume: active entries (saturation signal)
     * - Freshness: % entries updated in the last 7 days
     * - Coverage: distinct agents contributing facts (diversity signal)
     */
    public function memoryHealthScore(): float
    {
        $totalActive = KnowledgeGraphEntry::active()->count();

        if ($totalActive === 0) {
            return 0.0; // No memory at all
        }

        $recentlyUpdated = KnowledgeGraphEntry::active()
            ->where('valid_from', '>=', now()->subDays(7))
            ->count();

        $distinctAgents = KnowledgeGraphEntry::active()
            ->distinct('source_agent')
            ->count('source_agent');

        $freshnessRate = $totalActive > 0 ? ($recentlyUpdated / $totalActive) : 0;
        $agentDiversityScore = min($distinctAgents / 5, 1.0); // 5 agents = full diversity score

        // Volume signal: scale to a max of 500 active entries = 100 on volume dimension
        $volumeScore = min($totalActive / 500, 1.0);

        return round(($volumeScore * 40) + ($freshnessRate * 40) + ($agentDiversityScore * 20), 2);
    }
}
