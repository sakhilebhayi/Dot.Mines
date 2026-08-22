{{--
    Connectivity pill (hybrid spec Slice 2, brief §10): the app-wide answer
    to "is this live or cached?". Rendered hidden; resources/js/local/
    connectivity.js reveals and drives it once the sync client boots, so
    guests and pages without a sync context never see a dead pill.
    z-[40]: below the mobile-nav backdrop (45) on the documented layer scale.
--}}
<div
    data-connectivity-pill
    class="hidden fixed bottom-4 left-4 z-[40] flex items-center gap-2 rounded-full border bg-[var(--ink-soft)]/90 px-3 py-1.5 text-xs font-medium shadow-lg"
    role="status"
    aria-live="polite"
>
    <span data-connectivity-dot class="inline-block w-2 h-2 rounded-full bg-zinc-500"></span>
    <span data-connectivity-label>Connecting</span>
</div>
