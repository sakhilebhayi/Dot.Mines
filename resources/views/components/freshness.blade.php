@props(['timestamp' => null, 'staleAfter' => 300, 'label' => 'Updated'])

{{--
    Honest data-age badge (brief: "every important real-time component should
    expose its data freshness"). Pass the data's OWN timestamp (e.g. a
    MachineMetric recorded_at -- Bell's telemetry time), never the moment the
    page rendered. `staleAfter` is seconds appropriate to the data type
    (locations ~minutes, KPIs ~an hour, reports never -> omit the badge).

    The label re-derives client-side every 10s from the epoch, so it stays
    truthful while the page sits open; the server-rendered text is the no-JS
    fallback and initial paint.
--}}
@php
    $carbon = $timestamp instanceof \DateTimeInterface
        ? \Illuminate\Support\Carbon::instance($timestamp)
        : ($timestamp ? \Illuminate\Support\Carbon::parse($timestamp) : null);

    $isStale = $carbon !== null && $carbon->diffInSeconds(now()) > $staleAfter;
@endphp

@if ($carbon === null)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 text-xs text-[var(--sand)]/60']) }}>No data yet</span>
@else
    <span
        {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 text-xs']) }}
        x-data="{
            epoch: {{ $carbon->getTimestamp() }},
            staleAfter: {{ (int) $staleAfter }},
            age: 0,
            tick() { this.age = Math.max(0, Math.floor(Date.now() / 1000) - this.epoch); },
            get stale() { return this.age > this.staleAfter; },
            get text() {
                if (this.age < 60) { return '{{ $label }} ' + this.age + 's ago'; }
                if (this.age < 3600) { return '{{ $label }} ' + Math.floor(this.age / 60) + 'm ago'; }
                if (this.age < 86400) { return '{{ $label }} ' + Math.floor(this.age / 3600) + 'h ago'; }
                return '{{ $label }} ' + Math.floor(this.age / 86400) + 'd ago';
            },
        }"
        x-init="tick(); setInterval(() => tick(), 10000)"
        :class="stale ? 'text-amber-400' : 'text-[var(--sand)]'"
    >
        <span class="size-1.5 rounded-full" :class="stale ? 'bg-amber-400' : 'bg-emerald-400'" aria-hidden="true"></span>
        <span x-show="stale" x-cloak>Stale ·</span>
        <time datetime="{{ $carbon->toIso8601String() }}" x-text="text" class="{{ $isStale ? 'text-amber-400' : '' }}">{{ $label }} {{ $carbon->diffForHumans(short: true) }}</time>
    </span>
@endif
