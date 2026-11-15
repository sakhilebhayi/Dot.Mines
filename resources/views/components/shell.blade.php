@props(['header' => null, 'transition' => false])
{{-- The signed-in shell, written once for both layouts (resources/views/layouts/app.blade.php and
     components/layouts/app.blade.php used to each carry their own copy of the sidebar, the navbar and the backdrop). The header
     (Livewire navbar) sits above; the sidebar and the page share the row under it. The document scrolls; the header and
     the sidebar are sticky. --}}
<div class="mines-shell">
    @livewire('sidebar')

    <main id="main-content" class="mines-main" tabindex="-1">
        @if ($header !== null && trim((string) $header) !== '')
            <div class="mines-main__head">{{ $header }}</div>
        @endif

        <div @class(['mines-page', 'page-transition' => $transition])>
            {{ $slot }}
        </div>
    </main>
</div>
