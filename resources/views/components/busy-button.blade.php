@props(['target' => null])

{{--
    Primary action button with the standard busy treatment (brief: "do not
    allow users to click the same action repeatedly while an operation is
    still processing"): disables itself and shows a spinner while the
    Livewire action runs. Pass `target` (the action name) so multiple
    buttons in one component don't all spin together.
--}}
@php
    // Reassign $attributes so the component-tag compiler's literal
    // `{{ $attributes }}` spread (the only expression it supports inside a
    // component tag) carries the busy attributes onto the button.
    $attributes = $attributes->merge(array_filter([
        'wire:loading.attr' => 'disabled',
        'wire:target' => $target,
    ]));
@endphp

<x-button {{ $attributes }}>
    <svg
        wire:loading
        @if ($target) wire:target="{{ $target }}" @endif
        class="animate-spin -ms-1 me-2 size-4 text-[var(--ink)]"
        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true"
    >
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
    {{ $slot }}
</x-button>
