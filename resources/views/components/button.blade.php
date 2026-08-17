<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-6 py-2.5 bg-[var(--gold)] border border-transparent rounded-lg font-display font-semibold text-sm text-[var(--ink)] hover:bg-[var(--gold-soft)] focus:bg-[var(--gold-soft)] active:bg-[var(--gold-soft)] focus:outline-none focus:ring-2 focus:ring-[var(--gold)] focus:ring-offset-2 focus:ring-offset-[var(--ink-soft)] disabled:opacity-50 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
