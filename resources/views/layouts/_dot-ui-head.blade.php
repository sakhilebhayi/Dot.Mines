{{-- Shared Dot ecosystem UI (public/dot-ui, self-hosted, hash-pinned): Roboto, the type scale, the header, the pill search and
     the platform switcher. dot-ui-host.css maps its tokens onto Mines' own. Roboto is the one face (R-52): no font host is
     linked from any layout. A signed-out page passes $dotUiHeader = false to skip the header files. --}}
@include('layouts._roboto-head')
<link rel="stylesheet" href="{{ asset('dot-ui/css/dot-tokens.css') }}">
<link rel="stylesheet" href="{{ asset('dot-ui/css/dot-search.css') }}">
<link rel="stylesheet" href="{{ asset('dot-ui/css/dot-switcher.css') }}">
<link rel="stylesheet" href="{{ asset('dot-ui/css/dot-header.css') }}">
<link rel="stylesheet" href="{{ asset('dot-ui-host.css') }}">
<script src="{{ asset('dot-ui/js/dot-search.js') }}" nonce="{{ request()->attributes->get('csp_nonce') }}" defer></script>
<script src="{{ asset('dot-ui/js/dot-search-results.js') }}" nonce="{{ request()->attributes->get('csp_nonce') }}" defer></script>
<script src="{{ asset('dot-ui/js/dot-switcher.js') }}" nonce="{{ request()->attributes->get('csp_nonce') }}" defer></script>
<script src="{{ asset('dot-ui/js/dot-nav.js') }}" nonce="{{ request()->attributes->get('csp_nonce') }}" defer></script>
{{-- The rail / drawer choice is applied before first paint so the sidebar never jumps: remembered choice, else the rail
     below 1280px (a tablet starts with room for the page, a wide screen with the labelled list). --}}
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
    try {
        var r = localStorage.getItem('sidebarOpen');
        if (r === 'false' || (r === null && window.innerWidth < 1280)) document.documentElement.setAttribute('data-rail', 'true');
    } catch (e) { if (window.innerWidth < 1280) document.documentElement.setAttribute('data-rail', 'true'); }
</script>
