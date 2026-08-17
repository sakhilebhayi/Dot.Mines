@props(['id' => null, 'maxWidth' => null])

<x-modal :id="$id" :maxWidth="$maxWidth" {{ $attributes }}>
    <div class="px-6 py-4">
        <div class="text-lg font-medium text-[var(--stone)]">
            {{ $title }}
        </div>

        <div class="mt-4 text-sm text-[var(--sand)]">
            {{ $content }}
        </div>
    </div>

    <div class="flex flex-row justify-end px-6 py-4 bg-[var(--ink)]/60 border-t border-[var(--line)] text-end">
        {{ $footer }}
    </div>
</x-modal>
