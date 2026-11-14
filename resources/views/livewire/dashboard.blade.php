{{-- 30s, visible-only: the board re-reads on the alert-generation cadence at
     fastest, and background tabs cost the server nothing. Rows are swapped in
     place; the selected machine is held in Alpine by id, so a poll never
     moves it. Alert and critical states have no motion beyond appearing. --}}
@php
    $rows = $fleet['rows'];
    $counts = $fleet['counts'];
    $canAck = $this->canAcknowledge;
    $freshAt = $this->telemetryFreshAt;
    $firstId = $rows[0]['id'] ?? 'null';

    $countLine = collect([
        'travelling' => 'travelling', 'loading' => 'loading', 'dumping' => 'dumping',
        'idling' => 'idle', 'parked' => 'parked', 'no_telemetry' => 'no telemetry',
    ])->filter(fn ($label, $key) => ($counts[$key] ?? 0) > 0)
        ->map(fn ($label, $key) => $counts[$key].' '.$label)->implode(', ');
@endphp

<div class="dm-stack" wire:poll.visible.30s>

    {{-- Open alerts: stored priority word, Acknowledge only for people who may. --}}
    @if ($alerts['total'] > 0)
        <section aria-labelledby="dm-alerts-h" data-region="open-alerts">
            <div class="dm-head">
                <h3 id="dm-alerts-h">Open alerts</h3>
                <span class="dm-meta">{{ $alerts['total'] }} open</span>
                <span class="dm-right"><a class="dm-link dm-link--tap" href="{{ route('alerts') }}">All alerts</a></span>
            </div>
            <ul class="dm-alerts">
                @foreach ($alerts['rows'] as $alert)
                    <li wire:key="alert-{{ $alert['id'] }}" @class(['dm-alerts--noack' => ! $canAck])>
                        <span @class(['dm-prio', 'dm-signal' => $alert['priority']['signal']]) data-priority>{{ $alert['priority']['label'] }}</span>
                        <span class="dm-what">
                            <b>{{ $alert['title'] }}</b>
                            <span>{{ $alert['type'] }}@if ($alert['machine']), {{ $alert['machine'] }}@endif</span>
                        </span>
                        <span class="dm-when dm-num">{{ $alert['age'] }} ago</span>
                        @if ($canAck)
                            <button type="button" wire:click="acknowledgeAlert({{ $alert['id'] }})" wire:loading.attr="disabled"
                                    class="dm-btn dm-btn--quiet" data-acknowledge>Acknowledge</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Fleet now: table plus the selected machine. Filter and selection are
         client-side over the rows already loaded; no query per keystroke. --}}
    <section aria-labelledby="dm-fleet-h" data-region="fleet-now" class="dm-md"
             x-data="{
                 sel: {{ $firstId }},
                 q: '',
                 phone() { return window.matchMedia('(max-width: 767px)').matches },
                 open(row, e) { if (this.phone()) { window.location.assign(row.dataset.href) } else { this.sel = Number(row.dataset.id) } },
                 move(dir, row) { const r = [...$root.querySelectorAll('tr[data-id]')].filter(x => x.offsetParent !== null); const i = r.indexOf(row) + dir; if (r[i]) { r[i].focus(); this.sel = Number(r[i].dataset.id) } },
                 get none() { return this.q !== '' && ![...$root.querySelectorAll('tr[data-f]')].some(r => r.dataset.f.includes(this.q.toLowerCase())) },
             }"
             x-on:keydown.window="if ($event.key === '/' && !['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) { $event.preventDefault(); $refs.filter.focus() }">
        <div>
            <div class="dm-head">
                <h3 id="dm-fleet-h">Fleet now</h3>
                <span class="dm-meta">{{ $countLine !== '' ? $countLine : 'no machines yet' }}</span>
                @if (count($rows) > 0)
                    <span class="dm-right">
                        <label class="dm-sr" for="dm-filter">Filter machines</label>
                        <input id="dm-filter" x-ref="filter" x-model="q" type="search" class="dm-filter" placeholder="Filter machines" autocomplete="off">
                    </span>
                @endif
            </div>

            @if (count($rows) === 0)
                <p class="dm-empty-line">No machines in the fleet yet. <a class="dm-link" href="{{ route('fleet') }}">Add machines</a> to see where they are and what they are doing.</p>
            @else
                <table class="dm-tbl" aria-label="Fleet now">
                    <thead>
                        <tr>
                            <th scope="col">Machine</th>
                            <th scope="col">Now</th>
                            <th scope="col">Where or speed</th>
                            <th scope="col" class="dm-r">Fuel</th>
                            <th scope="col" class="dm-r">Alerts</th>
                            <th scope="col" class="dm-r">Heard</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $i => $m)
                            <tr wire:key="fleet-{{ $m['id'] }}" tabindex="0"
                                data-id="{{ $m['id'] }}" data-f="{{ $m['filter'] }}" data-href="{{ route('fleet.show', $m['id']) }}"
                                x-show="q === '' || $el.dataset.f.includes(q.toLowerCase())"
                                x-bind:aria-selected="sel === {{ $m['id'] }} ? 'true' : 'false'"
                                x-on:click="open($el, $event)"
                                x-on:keydown.arrow-down.prevent="move(1, $el)"
                                x-on:keydown.arrow-up.prevent="move(-1, $el)"
                                x-on:keydown.enter.prevent="window.location.assign($el.dataset.href)"
                                @class(['dm-row', 'dm-row--attn' => $m['alerts'] > 0])>
                                <td data-c="name">
                                    <a class="dm-name" href="{{ route('fleet.show', $m['id']) }}" x-on:click="if (!phone()) $event.preventDefault()">{{ $m['name'] }}</a>
                                    <span class="dm-sub">{{ $m['type'] }}</span>
                                </td>
                                <td data-c="now"><span class="dm-state" data-s="{{ $m['mark'] }}">{{ $m['stateLabel'] }}</span></td>
                                <td data-c="where" @class(['dm-soft' => $m['where'] === null])>{{ $m['where'] ?? 'No reading' }}</td>
                                <td data-c="fuel" @class(['dm-r', 'dm-num' => $m['fuel'] !== null, 'dm-soft' => $m['fuel'] === null])>{{ $m['fuel'] ?? 'No reading' }}</td>
                                <td data-c="alerts" class="dm-num dm-r">{{ $m['alerts'] }}</td>
                                <td data-c="heard" @class(['dm-num', 'dm-r', 'dm-signal' => $m['stale'] && $m['heard'] !== null])>{{ $m['heard'] ?? 'Never' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="dm-empty-line" x-show="none" x-cloak>No machine matches your filter. <button type="button" class="dm-link dm-linkbtn" x-on:click="q = ''">Clear the filter</button></p>
            @endif
        </div>

        @if (count($rows) > 0)
            <div class="dm-side" data-region="selected-machine">
                @foreach ($rows as $m)
                    <div class="dm-panel" wire:key="panel-{{ $m['id'] }}" data-detail="{{ $m['id'] }}" x-show="sel === {{ $m['id'] }}" @if (! $loop->first) x-cloak @endif>
                        <h4>{{ $m['name'] }}</h4>
                        <p class="dm-basis">{{ $m['basis'] }}</p>
                        <dl class="dm-kv">
                            <div><dt>Marked</dt><dd>{{ $m['marked'] }}</dd><span class="dm-age"></span></div>
                            <div><dt>Operator</dt><dd>{{ $m['operator'] ?? 'Not assigned' }}</dd><span class="dm-age"></span></div>
                            <div><dt>Last heard</dt><dd @class(['dm-signal' => $m['stale']])>{{ $m['heardLong'] ?? 'Never' }}</dd><span class="dm-age"></span></div>
                            <div><dt>Position</dt><dd class="dm-num">@if ($m['latitude'] !== null && $m['longitude'] !== null){{ number_format((float) $m['latitude'], 4, '.', '') }}, {{ number_format((float) $m['longitude'], 4, '.', '') }}@else<span class="dm-soft">No reading</span>@endif</dd><span class="dm-age">{{ $m['positionAge'] }}</span></div>
                            <div><dt>Speed</dt><dd @class(['dm-num' => $m['speed'] !== null, 'dm-soft' => $m['speed'] === null])>{{ $m['speed'] ?? 'No reading' }}</dd><span class="dm-age"></span></div>
                            <div><dt>Fuel</dt><dd @class(['dm-num' => $m['fuel'] !== null, 'dm-soft' => $m['fuel'] === null])>{{ $m['fuel'] ?? 'No reading' }}</dd><span class="dm-age">{{ $m['fuelAge'] }}</span></div>
                            <div><dt>Engine hours</dt><dd @class(['dm-num' => $m['hours'] !== null, 'dm-soft' => $m['hours'] === null])>{{ $m['hours'] ?? 'No reading' }}</dd><span class="dm-age">{{ $m['hoursAge'] }}</span></div>
                            <div><dt>Open alerts</dt>
                                @if ($m['topAlert'])
                                    <dd @class(['dm-signal' => in_array($m['topAlert']['priority'], ['critical', 'high'], true)])>{{ $m['alerts'] }}, {{ $m['topAlert']['priority'] }}: {{ $m['topAlert']['title'] }}</dd>
                                @else
                                    <dd>None</dd>
                                @endif
                                <span class="dm-age"></span></div>
                        </dl>
                        <div class="dm-act">
                            <a class="dm-btn dm-btn--quiet" href="{{ route('fleet.show', $m['id']) }}">Open machine</a>
                            <a class="dm-btn dm-btn--quiet" href="{{ route('map') }}">Live map</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <div class="dm-two">
        {{-- Production: one chart, bars of the day against its target tick. --}}
        <section aria-labelledby="dm-prod-h" data-region="production">
            <div class="dm-head">
                <h3 id="dm-prod-h">Production, last 7 days</h3>
                <span class="dm-meta">{{ $production['unit'] ?? '' }}</span>
                <span class="dm-right"><a class="dm-link dm-link--tap" href="{{ route('production') }}">Production</a></span>
            </div>
            @if ($production === null)
                <p class="dm-empty-line">No production recorded in the last 7 days. Records are added on the <a class="dm-link" href="{{ route('production') }}">Production page</a>.</p>
            @else
                @php
                    $days = $production['days'];
                    $n = count($days);
                    $max = max(1.0, ...array_map(fn ($d) => max($d['produced'], $d['target']), $days));
                    $plotTop = 16; $plotBottom = 160; $plotH = $plotBottom - $plotTop;
                    $slot = 630 / max($n, 1);
                    $bw = min(48, $slot * 0.55);
                    $unit = $production['unit'];
                    $fmt = fn ($v) => number_format($v, 0, '.', ' ');
                @endphp
                <svg class="dm-chart" viewBox="0 0 660 188" role="img" aria-labelledby="dm-ct dm-cd">
                    <title id="dm-ct">Production per day, last {{ $n }} {{ $n === 1 ? 'day' : 'days' }} with records</title>
                    <desc id="dm-cd">{{ $n }} daily {{ $n === 1 ? 'bar' : 'bars' }} against each day's target; {{ $fmt($production['produced']) }} {{ $unit }} produced in total{{ $production['target'] > 0 ? ' against '.$fmt($production['target']).' '.$unit : '' }}.</desc>
                    <line class="dm-axis" x1="30" x2="660" y1="{{ $plotBottom }}" y2="{{ $plotBottom }}"/>
                    <g class="dm-bars">
                        @foreach ($days as $k => $d)
                            @php
                                $h = $d['produced'] / $max * $plotH;
                                $x = 30 + $slot * $k + ($slot - $bw) / 2;
                            @endphp
                            <rect class="dm-bar" x="{{ round($x, 1) }}" y="{{ round($plotBottom - $h, 1) }}" width="{{ round($bw, 1) }}" height="{{ round($h, 1) }}"><title>{{ $d['label'] }}: {{ $fmt($d['produced']) }} {{ $unit }} produced{{ $d['target'] > 0 ? ', target '.$fmt($d['target']).' '.$unit : '' }}</title></rect>
                        @endforeach
                    </g>
                    @foreach ($days as $k => $d)
                        @php $x = 30 + $slot * $k + ($slot - $bw) / 2; @endphp
                        @if ($d['target'] > 0)
                            @php $ty = $plotBottom - $d['target'] / $max * $plotH; @endphp
                            <line class="dm-tgt" x1="{{ round($x - 6, 1) }}" x2="{{ round($x + $bw + 6, 1) }}" y1="{{ round($ty, 1) }}" y2="{{ round($ty, 1) }}"/>
                        @endif
                        <text x="{{ round($x + $bw / 2, 1) }}" y="178" text-anchor="middle">{{ $d['label'] }}</text>
                    @endforeach
                </svg>
                <div class="dm-legend"><span><i class="k1"></i>Produced</span><span><i class="k2"></i>Target for the day</span></div>
                <div class="dm-sumrow" data-production-total>
                    <span class="dm-num dm-big">{{ $fmt($production['produced']) }} {{ $unit }}</span>
                    @if ($production['target'] > 0)
                        <span class="dm-soft">of {{ $fmt($production['target']) }} {{ $unit }} target, {{ $production['percent'] }} %</span>
                    @else
                        <span class="dm-soft">no target set on these records</span>
                    @endif
                </div>
            @endif
        </section>

        <div>
            <section aria-labelledby="dm-shift-h" data-region="on-shift">
                <div class="dm-head"><h3 id="dm-shift-h">On shift</h3></div>
                <dl class="dm-kv">
                    <div><dt>Fuel reserve</dt><dd>{{ $shift['reserve'] ?? 'No tank or dispensing record yet' }}</dd><span class="dm-age"></span></div>
                    <div><dt>Maintenance</dt><dd><a class="dm-link" href="{{ route('maintenance') }}">{{ $shift['inProgress'] }} in progress, {{ $shift['overdue'] }} overdue</a></dd><span class="dm-age"></span></div>
                </dl>
                @if ($shift['reserve'] !== null)
                    <p class="dm-note">Reserve is tank levels over the average daily dispensing of the last 30 days of data.</p>
                @endif
            </section>

            @if ($this->recentFeed->isNotEmpty())
                <section aria-labelledby="dm-feed-h" data-region="mine-activity" class="dm-feedsec">
                    <div class="dm-head">
                        <h3 id="dm-feed-h">Mine activity</h3>
                        <span class="dm-right"><a class="dm-link dm-link--tap" href="{{ route('feed') }}">All activity</a></span>
                    </div>
                    <ul class="dm-feed">
                        @foreach ($this->recentFeed as $feedItem)
                            <li wire:key="dash-feed-{{ $feedItem->id }}">
                                <span class="dm-num dm-soft" title="{{ $feedItem->occurred_at->toDayDateTimeString() }}">{{ $feedItem->occurred_at->format('H:i') }}</span>
                                <span>{{ $feedItem->title }}</span>
                                <span class="dm-soft">{{ \App\Models\FeedItem::CATEGORIES[$feedItem->category] ?? ucfirst($feedItem->category) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>

    {{-- The status row: each figure with its own age, never "live" from render time. --}}
    <footer class="dm-foot" data-region="status-row">
        <span>
            @if ($freshAt)
                Telemetry recorded <span @class(['dm-num', 'dm-signal' => $freshAt->diffInMinutes(now()) > \App\Livewire\Dashboard::STALE_AFTER_MINUTES])>{{ $freshAt->diffForHumans(short: true) }}</span> ago
            @else
                No telemetry recorded yet
            @endif
        </span>
        <span><span class="dm-num" data-reading="machines">{{ count($rows) }}</span> {{ count($rows) === 1 ? 'machine' : 'machines' }}</span>
        <span><span class="dm-num" data-reading="active-alerts">{{ $alerts['total'] }}</span> {{ $alerts['total'] === 1 ? 'alert' : 'alerts' }} open</span>
    </footer>
</div>
