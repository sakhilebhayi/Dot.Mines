{{-- Skeleton for list/table pages: toolbar line then repeated rows. --}}
<div class="p-4 sm:p-6 space-y-4">
    <x-skeleton variant="text" :lines="1" class="max-w-xs" />
    <div class="rounded-lg border border-[var(--line)] bg-[var(--ink-soft)] p-4 space-y-1">
        <x-skeleton variant="row" />
        <x-skeleton variant="row" />
        <x-skeleton variant="row" />
        <x-skeleton variant="row" />
        <x-skeleton variant="row" />
        <x-skeleton variant="row" />
    </div>
</div>
