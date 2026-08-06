<div class="min-h-screen flex flex-col sm:justify-center items-center pt-10 sm:pt-0 px-5 bg-[var(--ink)]">
    <div>
        {{ $logo }}
    </div>

    <div class="w-full sm:max-w-md mt-8 px-6 sm:px-8 py-8 bg-[var(--ink-soft)] border border-[var(--line)] shadow-2xl overflow-hidden sm:rounded-xl">
        {{ $slot }}
    </div>
</div>
