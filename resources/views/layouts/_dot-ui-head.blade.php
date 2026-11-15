{{-- Shared Dot ecosystem UI (public/dot-ui, self-hosted, hash-pinned): Roboto, the type scale, the header, the pill search and the
     platform switcher. Their CSS and JS are bundled and minified into the Vite app entry (resources/css/app.css, resources/js/app.js),
     with the Mines token mapping from public/dot-ui-host.css: one stylesheet and one script instead of eleven requests. Roboto is the one
     face (R-52): no font host is linked from any layout. --}}
{{-- The one Roboto file the first paint needs, fetched beside the stylesheet instead of after it (the hashed file the bundle emits). --}}
<link rel="preload" href="{{ Vite::asset('public/dot-ui/fonts/roboto/roboto-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
{{-- The rail / drawer choice is applied before first paint so the sidebar never jumps: remembered choice, else the rail
     below 1280px (a tablet starts with room for the page, a wide screen with the labelled list). --}}
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
    try {
        var r = localStorage.getItem('sidebarOpen');
        if (r === 'false' || (r === null && window.innerWidth < 1280)) document.documentElement.setAttribute('data-rail', 'true');
    } catch (e) { if (window.innerWidth < 1280) document.documentElement.setAttribute('data-rail', 'true'); }
</script>
