{{-- Skeleton for a single-record detail page: identity block first, then
     stats, then charts -- mirrors the brief's progressive priority order. --}}
<div class="p-4 sm:p-6 space-y-6">
    <x-skeleton variant="text" :lines="2" class="max-w-md" />
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <x-skeleton variant="kpi" />
        <x-skeleton variant="kpi" />
        <x-skeleton variant="kpi" />
        <x-skeleton variant="kpi" />
    </div>
    <x-skeleton variant="chart" />
    <div class="space-y-1">
        <x-skeleton variant="row" />
        <x-skeleton variant="row" />
        <x-skeleton variant="row" />
    </div>
</div>
