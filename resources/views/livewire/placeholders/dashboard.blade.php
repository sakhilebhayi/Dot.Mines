{{-- Skeleton for KPI-and-cards pages (dashboard-family). Shapes match the
     real layout so the swap-in doesn't jump (brief: skeletons resemble the
     content they replace). --}}
<div class="p-4 sm:p-6 space-y-6">
    {{-- No KPI row: the counts are in the landing above, already painted. --}}
    <x-skeleton variant="chart" />
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-skeleton variant="card" />
        <x-skeleton variant="card" />
    </div>
</div>
