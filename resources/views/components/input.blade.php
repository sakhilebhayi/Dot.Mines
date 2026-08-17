@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'bg-[var(--ink)] border-[var(--line)] text-[var(--stone)] placeholder-[var(--sand)]/50 focus:border-[var(--gold)] focus:ring-[var(--gold)] rounded-lg shadow-sm']) !!}>
