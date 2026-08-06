@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-[var(--sand)]']) }}>
    {{ $value ?? $slot }}
</label>
