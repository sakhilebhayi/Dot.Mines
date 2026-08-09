<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-[var(--ink)] border border-[var(--line)] rounded-md font-semibold text-xs text-[var(--stone)] uppercase tracking-widest shadow-sm hover:bg-white/5 focus:outline-none focus:ring-2 focus:ring-[var(--gold)] focus:ring-offset-2 focus:ring-offset-[var(--ink-soft)] disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
