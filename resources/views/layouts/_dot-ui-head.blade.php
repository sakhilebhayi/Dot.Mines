{{-- Shared Dot ecosystem UI (public/dot-ui, self-hosted, hash-pinned): the in-page filter and the platform
     switcher. dot-ui-host.css maps its tokens onto Mines' own. --}}
<link rel="stylesheet" href="{{ asset('dot-ui/css/dot-fonts.css') }}">
<link rel="stylesheet" href="{{ asset('dot-ui/css/dot-tokens.css') }}">
<link rel="stylesheet" href="{{ asset('dot-ui/css/dot-search.css') }}">
<link rel="stylesheet" href="{{ asset('dot-ui/css/dot-switcher-fonts.css') }}">
<link rel="stylesheet" href="{{ asset('dot-ui/css/dot-switcher.css') }}">
<link rel="stylesheet" href="{{ asset('dot-ui-host.css') }}">
<script src="{{ asset('dot-ui/js/dot-switcher.js') }}" nonce="{{ request()->attributes->get('csp_nonce') }}" defer></script>
