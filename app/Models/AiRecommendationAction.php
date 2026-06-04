<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * AiRecommendationAction Model
 *
 * @property \Carbon\Carbon|null $actioned_at
 * @property mixed|null $actioned_by
 * @property \Carbon\Carbon $created_at
 * @property int $id
 * @property array|null $performance_impact
 * @property array $recommendation
 * @property string $recommendation_hash
 * @property string|null $reject_reason
 * @property string $status
 * @property mixed $team_id
 * @property \Carbon\Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|AiRecommendationAction where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder|AiRecommendationAction whereIn(string $column, array $values)
 * @method static \Illuminate\Database\Eloquent\Builder|AiRecommendationAction orderBy(string $column, string $direction = 'asc')
 * @method static AiRecommendationAction|null find(mixed $id, array $columns = ['*'])
 * @method static AiRecommendationAction findOrFail(mixed $id, array $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection all(array $columns = ['*'])
 */
