<?php

namespace App\Support\Search;

use App\Models\Alert;
use App\Models\Geofence;
use App\Models\Machine;
use App\Models\MaintenanceRecord;
use App\Models\MineArea;
use App\Models\Operator;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The header search (R-52). A real search over what Dot.Mines holds, scoped the way the pages are scoped, and then some:
 *
 *  - TEAM: every query is pinned to the signed-in person's current team with an explicit `team_id = ?`. It does NOT lean on the
 *    models' global `team` scope: that scope silently drops its filter when a request has no team, which is correct for a job
 *    and wrong for a search box. A person with no current team gets nothing.
 *  - PERMISSION: each group appears only when the person holds the permission the matching page and policy ask for
 *    (view_machines, view_alerts, view_operators, view_mine_areas, view_geofences), so a viewer sees what a viewer sees and
 *    no operator register leaks to a role that cannot open it. Operator medical details are never searched.
 *  - SHAPE: parameterised, lower-cased on both sides (SQLite and PostgreSQL agree), LIKE wildcards in the query are removed
 *    (a `%` is not a match-everything), every word must match somewhere in the row, each group is capped by the caller.
 *
 * Nothing is invented: every row is a stored record and links to a page that already shows it.
 */
class MinesSearch
{
    public const MIN_LENGTH = 2;

    public const MAX_LENGTH = 80;

    /** @return list<string> the words of the query, with LIKE wildcards and control characters removed */
    public static function words(string $term): array
    {
        $term = mb_strtolower(mb_substr(trim($term), 0, self::MAX_LENGTH));
        $term = preg_replace('/[%_\\\\\p{C}]+/u', ' ', $term) ?? '';
        $words = array_values(array_filter(preg_split('/\s+/u', $term) ?: [], fn (string $w): bool => $w !== ''));

        return array_slice($words, 0, 5);
    }

    public static function isSearchable(string $term): bool
    {
        return mb_strlen(trim($term)) >= self::MIN_LENGTH && self::words($term) !== [];
    }

    /**
     * @return array<string, list<SearchHit>> group label => hits, empty groups left out
     */
    public function search(User $user, string $term, int $perGroup = 4): array
    {
        $teamId = $user->current_team_id;
        if (! self::isSearchable($term) || $teamId === null || $teamId === 0) {
            return [];
        }

        $words = self::words($term);
        $can = $this->permissions($user);

        $groups = [
            'Go to' => $this->pages($user, $words, $perGroup),
            'Machines' => $can('view_machines') ? $this->machines($teamId, $words, $perGroup) : [],
            'Alerts' => $can('view_alerts') ? $this->alerts($teamId, $words, $perGroup) : [],
            'Maintenance' => $can('view_machines') ? $this->maintenance($teamId, $words, $perGroup) : [],
            'Operators' => $can('view_operators') ? $this->operators($teamId, $words, $perGroup) : [],
            'Zones' => $this->zones($teamId, $words, $perGroup, $can('view_mine_areas'), $can('view_geofences')),
        ];

        return array_filter($groups, fn (array $hits): bool => $hits !== []);
    }

    /**
     * The pages a person can go to, for the empty box and for a typed match. A page the person's role cannot open is not offered.
     *
     * @param  list<string>|null  $words  null: all of them (the empty box)
     * @return list<SearchHit>
     */
    public function pages(User $user, ?array $words = null, int $limit = 8): array
    {
        $can = $this->permissions($user);

        // label, route, the permission the page asks for (null: any member), keywords
        $pages = [
            ['Dashboard', 'dashboard', null, 'home overview fleet now'],
            ['Fleet', 'fleet', 'view_machines', 'machines equipment trucks'],
            ['Live map', 'map', 'view_live_map', 'position gps where'],
            ['Alerts', 'alerts', 'view_alerts', 'alarms warnings'],
            ['Feed', 'feed', 'view_feed', 'announcements activity'],
            ['Mine areas', 'mine-areas', 'view_mine_areas', 'sites pits zones'],
            ['Geofences', 'geofences', 'view_geofences', 'zones boundaries'],
            ['Operators', 'operators.index', 'view_operators', 'crew drivers people'],
            ['Operator fatigue', 'operator-fatigue', null, 'crew hours rest'],
            ['Production', 'production', null, 'tonnes output target'],
            ['Fuel', 'fuel', null, 'diesel tanks'],
            ['Maintenance', 'maintenance', null, 'service work orders'],
            ['Reports', 'reports', 'view_reports', 'export'],
            ['AI optimization', 'ai-optimization', 'view_recommendations', 'recommendations'],
        ];

        $hits = [];
        foreach ($pages as [$label, $route, $permission, $keywords]) {
            if ($permission !== null && ! $can($permission)) {
                continue;
            }
            $haystack = mb_strtolower($label.' '.$keywords);
            if ($words !== null && collect($words)->contains(fn (string $w): bool => ! str_contains($haystack, $w))) {
                continue;
            }
            $hits[] = new SearchHit('Go to', $label, null, route($route), null);
        }

        return array_slice($hits, 0, $limit);
    }

    /**
     * @return \Closure(string): bool the person's permissions, read ONCE (an admin: all of them; anyone else: the names their roles in
     *                                this team grant). The header search runs on every page, so this is two queries, not one per page link.
     */
    private function permissions(User $user): \Closure
    {
        $admin = null;
        $granted = null;

        return function (string $permission) use ($user, &$admin, &$granted): bool {
            $admin ??= $user->hasRole('admin');
            if ($admin) {
                return true;
            }
            $granted ??= $user->permissions()->pluck('permissions.name')->all();

            return in_array($permission, $granted, true);
        };
    }

    /**
     * @param  list<string>  $words
     * @return list<SearchHit>
     */
    private function machines(int $teamId, array $words, int $limit): array
    {
        $query = Machine::query()->withoutGlobalScope('team')->where('machines.team_id', $teamId);

        /** @var Collection<int, Machine> $rows */
        $rows = $this->like($query, ['name', 'machine_type', 'manufacturer', 'model', 'serial_number', 'registration_number'], $words)
            ->orderBy('name')->limit($limit)->get();

        return array_values($rows->map(fn (Machine $m): SearchHit => new SearchHit(
            'Machines',
            $m->name,
            $this->sub([ucfirst(str_replace('_', ' ', $m->machine_type)), $this->text($m->model ?? $m->manufacturer), $m->status]),
            route('fleet.show', $m->id),
            filled($m->serial_number) ? $m->serial_number : $m->registration_number,
            ucfirst($m->status),
        ))->all());
    }

    /**
     * @param  list<string>  $words
     * @return list<SearchHit>
     */
    private function alerts(int $teamId, array $words, int $limit): array
    {
        $query = Alert::query()->withoutGlobalScope('team')->where('alerts.team_id', $teamId);
        foreach ($words as $word) {
            $like = '%'.$word.'%';
            $query->where(function (Builder $q) use ($like, $teamId): void {
                $q->whereRaw('LOWER('.$this->wrap('alerts.title').') LIKE ?', [$like])
                    ->orWhereRaw('LOWER('.$this->wrap('alerts.description').') LIKE ?', [$like])
                    ->orWhereRaw('LOWER('.$this->wrap('alerts.type').') LIKE ?', [$like])
                    // the machine's name too, still inside the same team
                    ->orWhereIn('alerts.machine_id', $this->machineIdsNamed($teamId, $like));
            });
        }

        /** @var Collection<int, Alert> $rows */
        $rows = $query->with('machine:id,name')->orderByDesc('triggered_at')->limit($limit)->get();

        return array_values($rows->map(fn (Alert $a): SearchHit => new SearchHit(
            'Alerts',
            $a->title,
            $this->sub([$a->machine?->name, ucfirst($a->status)]),
            route('alerts', ['q' => $a->title]),
            null,
            ucfirst($a->priority),
        ))->all());
    }

    /**
     * @param  list<string>  $words
     * @return list<SearchHit>
     */
    private function maintenance(int $teamId, array $words, int $limit): array
    {
        $query = MaintenanceRecord::query()->withoutGlobalScope('team')->where('maintenance_records.team_id', $teamId);
        foreach ($words as $word) {
            $like = '%'.$word.'%';
            $query->where(function (Builder $q) use ($like, $teamId): void {
                $q->whereRaw('LOWER('.$this->wrap('maintenance_records.title').') LIKE ?', [$like])
                    ->orWhereRaw('LOWER('.$this->wrap('maintenance_records.work_order_number').') LIKE ?', [$like])
                    ->orWhereRaw('LOWER('.$this->wrap('maintenance_records.description').') LIKE ?', [$like])
                    ->orWhereIn('maintenance_records.machine_id', $this->machineIdsNamed($teamId, $like));
            });
        }

        /** @var Collection<int, MaintenanceRecord> $rows */
        $rows = $query->with('machine:id,name')->orderByDesc('scheduled_date')->limit($limit)->get();

        return array_values($rows->map(fn (MaintenanceRecord $r): SearchHit => new SearchHit(
            'Maintenance',
            $r->title,
            $this->sub([$r->machine?->name, ucfirst(str_replace('_', ' ', $r->status))]),
            route('maintenance'),
            $r->work_order_number,
            ucfirst($r->priority),
        ))->all());
    }

    /**
     * @param  list<string>  $words
     * @return list<SearchHit>
     */
    private function operators(int $teamId, array $words, int $limit): array
    {
        // The operator register is personal data: the fields are the name and the employee number a supervisor already sees
        // in the list. Medical, licence and contact details are never searched or shown here.
        $query = Operator::query()->withoutGlobalScope('team')->where('operators.team_id', $teamId);

        /** @var Collection<int, Operator> $rows */
        $rows = $this->like($query, ['first_name', 'last_name', 'preferred_name', 'employee_number', 'job_title'], $words)
            ->orderBy('last_name')->orderBy('first_name')->limit($limit)->get();

        return array_values($rows->map(fn (Operator $o): SearchHit => new SearchHit(
            'Operators',
            $o->name,
            $this->sub([$o->job_title, $o->employment_status !== 'active' ? $o->employment_status : null]),
            route('operators.show', $o->id),
            $o->employee_number,
            null,
        ))->all());
    }

    /**
     * Zones: the mine areas and the geofences (pits, stockpiles, dumps), each only for a role that may open its page.
     *
     * @param  list<string>  $words
     * @return list<SearchHit>
     */
    private function zones(int $teamId, array $words, int $limit, bool $areas, bool $fences): array
    {
        $hits = [];
        if ($areas) {
            $query = MineArea::query()->where('mine_areas.team_id', $teamId);
            /** @var Collection<int, MineArea> $areaRows */
            $areaRows = $this->like($query, ['name', 'description', 'location'], $words)->orderBy('name')->limit($limit)->get();
            foreach ($areaRows as $area) {
                $hits[] = new SearchHit('Zones', $area->name, $this->sub(['Mine area', $area->location]), route('mine-areas.show', $area->id), null, 'Area');
            }
        }
        if ($fences) {
            $query = Geofence::query()->withoutGlobalScope('team')->where('geofences.team_id', $teamId);
            /** @var Collection<int, Geofence> $fenceRows */
            $fenceRows = $this->like($query, ['name', 'description', 'type'], $words)->orderBy('name')->limit($limit)->get();
            foreach ($fenceRows as $fence) {
                $hits[] = new SearchHit('Zones', $fence->name, $this->sub([ucfirst($fence->type)]), route('geofences.show', $fence->id), null, 'Geofence');
            }
        }

        return array_slice($hits, 0, $limit);
    }

    /**
     * The ids of this team's machines whose name matches (a subquery, so the machine's name can be searched without leaving the team).
     *
     * @return \Closure(QueryBuilder): QueryBuilder
     */
    private function machineIdsNamed(int $teamId, string $like): \Closure
    {
        return fn (QueryBuilder $sub): QueryBuilder => $sub->select('id')->from('machines')->where('team_id', $teamId)->whereRaw('LOWER(name) LIKE ?', [$like]);
    }

    /**
     * Every word must appear in at least one of the columns (a lower-cased, parameterised LIKE).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $columns
     * @param  list<string>  $words
     * @return Builder<TModel>
     */
    private function like(Builder $query, array $columns, array $words): Builder
    {
        $table = $query->getModel()->getTable();
        foreach ($words as $word) {
            $like = '%'.$word.'%';
            $query->where(function (Builder $q) use ($columns, $like, $table): void {
                foreach ($columns as $column) {
                    $q->orWhereRaw('LOWER('.$this->wrap($table.'.'.$column).') LIKE ?', [$like]);
                }
            });
        }

        return $query;
    }

    private function wrap(string $identifier): string
    {
        return DB::connection()->getQueryGrammar()->wrap($identifier);
    }

    /** a column the model does not type: its string, or null */
    private function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param  list<string|null>  $parts */
    private function sub(array $parts): ?string
    {
        $parts = array_values(array_filter($parts, fn ($p): bool => $p !== null && $p !== ''));

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
