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
use Illuminate\Database\Eloquent\Model;

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
        if (! self::isSearchable($term) || $teamId === null || (int) $teamId === 0) {
            return [];
        }

        $teamId = (int) $teamId;
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
        return $this->like($this->team(Machine::query(), $teamId), ['name', 'machine_type', 'manufacturer', 'model', 'serial_number', 'registration_number'], $words)
            ->orderBy('name')->limit($limit)->get()
            ->map(fn (Machine $m): SearchHit => new SearchHit(
                'Machines',
                (string) $m->name,
                $this->sub([$m->machine_type ? ucfirst(str_replace('_', ' ', (string) $m->machine_type)) : null, $m->model ?? $m->manufacturer, $m->status]),
                route('fleet.show', $m->id),
                $m->serial_number ?: $m->registration_number,
                $m->status ? ucfirst((string) $m->status) : null,
            ))->all();
    }

    /**
     * @param  list<string>  $words
     * @return list<SearchHit>
     */
    private function alerts(int $teamId, array $words, int $limit): array
    {
        $query = $this->team(Alert::query(), $teamId);
        foreach ($words as $word) {
            $like = '%'.$word.'%';
            $query->where(function (Builder $q) use ($like, $teamId): void {
                $q->whereRaw('LOWER('.$this->wrap($q, 'title').') LIKE ?', [$like])
                    ->orWhereRaw('LOWER('.$this->wrap($q, 'description').') LIKE ?', [$like])
                    ->orWhereRaw('LOWER('.$this->wrap($q, 'type').') LIKE ?', [$like])
                    // the machine's name too, still inside the same team
                    ->orWhereIn('machine_id', fn ($m) => $m->select('id')->from('machines')->where('team_id', $teamId)->whereRaw('LOWER(name) LIKE ?', [$like]));
            });
        }

        return $query->with('machine:id,name')->orderByDesc('triggered_at')->limit($limit)->get()
            ->map(fn (Alert $a): SearchHit => new SearchHit(
                'Alerts',
                (string) $a->title,
                $this->sub([$a->machine?->name, ucfirst((string) $a->status)]),
                route('alerts', ['q' => $a->title]),
                null,
                ucfirst((string) $a->priority),
            ))->all();
    }

    /**
     * @param  list<string>  $words
     * @return list<SearchHit>
     */
    private function maintenance(int $teamId, array $words, int $limit): array
    {
        $query = $this->team(MaintenanceRecord::query(), $teamId);
        foreach ($words as $word) {
            $like = '%'.$word.'%';
            $query->where(function (Builder $q) use ($like, $teamId): void {
                $q->whereRaw('LOWER('.$this->wrap($q, 'title').') LIKE ?', [$like])
                    ->orWhereRaw('LOWER('.$this->wrap($q, 'work_order_number').') LIKE ?', [$like])
                    ->orWhereRaw('LOWER('.$this->wrap($q, 'description').') LIKE ?', [$like])
                    ->orWhereIn('machine_id', fn ($m) => $m->select('id')->from('machines')->where('team_id', $teamId)->whereRaw('LOWER(name) LIKE ?', [$like]));
            });
        }

        return $query->with('machine:id,name')->orderByDesc('scheduled_date')->limit($limit)->get()
            ->map(fn (MaintenanceRecord $r): SearchHit => new SearchHit(
                'Maintenance',
                (string) $r->title,
                $this->sub([$r->machine?->name, $r->status ? ucfirst(str_replace('_', ' ', (string) $r->status)) : null]),
                route('maintenance'),
                $r->work_order_number,
                $r->priority ? ucfirst((string) $r->priority) : null,
            ))->all();
    }

    /**
     * @param  list<string>  $words
     * @return list<SearchHit>
     */
    private function operators(int $teamId, array $words, int $limit): array
    {
        // The operator register is personal data: the fields are the name and the employee number a supervisor already sees
        // in the list. Medical, licence and contact details are never searched or shown here.
        return $this->like($this->team(Operator::query(), $teamId), ['first_name', 'last_name', 'preferred_name', 'employee_number', 'job_title'], $words)
            ->orderBy('last_name')->orderBy('first_name')->limit($limit)->get()
            ->map(fn (Operator $o): SearchHit => new SearchHit(
                'Operators',
                $o->name,
                $this->sub([$o->job_title, $o->employment_status && $o->employment_status !== 'active' ? (string) $o->employment_status : null]),
                route('operators.show', $o->id),
                $o->employee_number,
                null,
            ))->all();
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
            foreach ($this->like($this->team(MineArea::query(), $teamId), ['name', 'description', 'location'], $words)->orderBy('name')->limit($limit)->get() as $area) {
                $hits[] = new SearchHit('Zones', (string) $area->name, $this->sub(['Mine area', $area->location]), route('mine-areas.show', $area->id), null, 'Area');
            }
        }
        if ($fences) {
            foreach ($this->like($this->team(Geofence::query(), $teamId), ['name', 'description', 'type'], $words)->orderBy('name')->limit($limit)->get() as $fence) {
                $hits[] = new SearchHit('Zones', (string) $fence->name, $this->sub([$fence->type ? ucfirst((string) $fence->type) : 'Geofence']), route('geofences.show', $fence->id), null, 'Geofence');
            }
        }

        return array_slice($hits, 0, $limit);
    }

    /**
     * The explicit team pin. `withoutGlobalScope('team')` drops the trait's own filter (which skips itself when the request has no
     * team) so the one `team_id = ?` below is the only rule, and cannot be silently absent. Soft-delete scopes stay.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function team(Builder $query, int $teamId): Builder
    {
        return $query->withoutGlobalScope('team')->where($query->getModel()->getTable().'.team_id', $teamId);
    }

    /**
     * Every word must match one of the columns (a lower-cased, parameterised LIKE).
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
        foreach ($words as $word) {
            $like = '%'.$word.'%';
            $query->where(function (Builder $q) use ($columns, $like): void {
                foreach ($columns as $column) {
                    $q->orWhereRaw('LOWER('.$this->wrap($q, $column).') LIKE ?', [$like]);
                }
            });
        }

        return $query;
    }

    /** @param  Builder<Model>  $query */
    private function wrap(Builder $query, string $column): string
    {
        return $query->getQuery()->getGrammar()->wrap($column);
    }

    /** @param  list<string|null>  $parts */
    private function sub(array $parts): ?string
    {
        $parts = array_values(array_filter($parts, fn ($p): bool => $p !== null && $p !== ''));

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
