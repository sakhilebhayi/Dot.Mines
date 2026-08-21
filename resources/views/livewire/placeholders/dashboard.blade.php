{{-- Skeleton for KPI-and-cards pages (dashboard-family). Shapes match the
     real layout so the swap-in doesn't jump (brief: skeletons resemble the
     content they replace). --}}
<div class="p-4 sm:p-6 space-y-6">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <x-skeleton variant="kpi" />
        <x-skeleton variant="kpi" />
        <x-skeleton variant="kpi" />
        <x-skeleton variant="kpi" />
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-skeleton variant="card" />
        <x-skeleton variant="card" />
    </div>
    <x-skeleton variant="chart" />
</div>
