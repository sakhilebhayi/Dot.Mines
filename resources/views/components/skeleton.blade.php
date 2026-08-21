@props(['variant' => 'text', 'lines' => 3])

{{--
    Shaped loading placeholder. Pick the variant that matches the content it
    stands in for (brief: "skeletons should resemble the shape of the content
    they are replacing"):

      text  - {lines} staggered text lines (paragraphs, list content)
      card  - media block + heading + two lines (fleet/machine cards)
      kpi   - small label + large value (dashboard stat tiles)
      row   - leading dot + two stacked lines (table rows, feed items)
      chart - baseline-aligned bar strip (chart areas)

    All variants pulse token-surface blocks, so they read correctly on any
    ink/ink-soft background without hardcoding theme colors.
--}}
<div {{ $attributes->merge(['class' => 'animate-pulse']) }} role="status" aria-label="Loading">
    <span class="sr-only">Loading…</span>

    @if ($variant === 'card')
        <div class="rounded-lg border border-[var(--line)] bg-[var(--ink-soft)] p-4 space-y-3">
            <div class="h-24 rounded-md bg-white/5"></div>
            <div class="h-4 w-2/3 rounded bg-white/5"></div>
            <div class="h-3 w-full rounded bg-white/5"></div>
            <div class="h-3 w-5/6 rounded bg-white/5"></div>
        </div>
    @elseif ($variant === 'kpi')
        <div class="rounded-lg border border-[var(--line)] bg-[var(--ink-soft)] p-4 space-y-2">
            <div class="h-3 w-1/3 rounded bg-white/5"></div>
            <div class="h-7 w-1/2 rounded bg-white/5"></div>
        </div>
    @elseif ($variant === 'row')
        <div class="flex items-center gap-3 py-2">
            <div class="size-8 shrink-0 rounded-full bg-white/5"></div>
            <div class="flex-1 space-y-2">
                <div class="h-3 w-1/2 rounded bg-white/5"></div>
                <div class="h-3 w-3/4 rounded bg-white/5"></div>
            </div>
        </div>
    @elseif ($variant === 'chart')
        <div class="flex items-end gap-2 h-28">
            @foreach ([40, 65, 30, 80, 55, 70, 45, 60] as $height)
                <div class="flex-1 rounded-t bg-white/5" style="height: {{ $height }}%"></div>
            @endforeach
        </div>
    @else
        <div class="space-y-2">
            @for ($i = 0; $i < max(1, (int) $lines); $i++)
                <div class="h-3 rounded bg-white/5 {{ $i === max(1, (int) $lines) - 1 ? 'w-2/3' : 'w-full' }}"></div>
            @endfor
        </div>
    @endif
</div>
