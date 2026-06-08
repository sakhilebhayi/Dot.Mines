<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * An entry in the organisational knowledge graph.
 *
 * Stores structured knowledge as subject → predicate → object triples.
 * Used by the MEGA V2 "Organisational Memory" and "Reality Alignment" scoring domains.
 *
 * @property int $id
 * @property string $entry_type
 * @property string $subject
 * @property string $predicate
 * @property string $object
 * @property float $confidence 0–100
 * @property string $source_agent
 * @property Carbon $valid_from
 * @property Carbon|null $valid_until
 * @property bool $is_active
 * @property array<mixed>|null $metadata
 */
class KnowledgeGraphEntry extends Model
{
    protected $table = 'knowledge_graph_entries';

    protected $fillable = [
        'entry_type',
        'subject',
        'predicate',
        'object',
        'confidence',
        'source_agent',
        'valid_from',
        'valid_until',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'is_active' => 'boolean',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Scope to entries that are currently active (is_active = true and within valid date range).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>', now());
            });
    }
}
