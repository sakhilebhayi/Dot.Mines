@php
    // The product areas, grouped by the question they answer (R-52: 14 flat items became five labelled groups; every route is unchanged).
    $minesNav = [
        'Operate' => [
            ['route' => 'dashboard', 'match' => 'dashboard', 'label' => 'Dashboard', 'icon' => ['M3 12l2-3m0 0l7-4 7 4M5 9v10a1 1 0 001 1h12a1 1 0 001-1V9m-9 11l4-4m-4 4L7 15m6-6l4 4m0-11V9a2 2 0 00-2-2H7a2 2 0 00-2 2v6']],
            ['route' => 'fleet', 'match' => 'fleet*', 'label' => 'Fleet', 'icon' => ['M13 10V3L4 14h7v7l9-11h-7z']],
            ['route' => 'map', 'match' => 'map*', 'label' => 'Live Map', 'icon' => ['M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6 3m-6-3v-13m6 3l5.553-2.776A1 1 0 0121 5.618v10.764a1 1 0 01-1.447.894L15 20m0-13v13']],
            ['route' => 'alerts', 'match' => 'alerts*', 'label' => 'Alerts', 'icon' => ['M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9']],
            ['route' => 'feed', 'match' => 'feed', 'label' => 'Feed', 'icon' => ['M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z']],
        ],
        'Sites' => [
            ['route' => 'mine-areas', 'match' => 'mine-areas*', 'label' => 'Mine Areas', 'icon' => ['M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6 3m-6-3v-13m6 3l5.553-2.776A1 1 0 0121 5.618v10.764a1 1 0 01-1.447.894L15 20m0-13v13']],
            ['route' => 'geofences', 'match' => 'geofences*', 'label' => 'Geofences', 'icon' => ['M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01']],
        ],
        'Crew' => [
            ['route' => 'operators.index', 'match' => 'operators.*', 'label' => 'Operators', 'icon' => ['M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z']],
            ['route' => 'operator-fatigue', 'match' => 'operator-fatigue*', 'label' => 'Operator Fatigue', 'icon' => ['M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z']],
        ],
        'Resources' => [
            ['route' => 'production', 'match' => 'production*', 'label' => 'Production', 'icon' => ['M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4']],
            ['route' => 'fuel', 'match' => 'fuel*', 'label' => 'Fuel Management', 'icon' => ['M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z']],
            ['route' => 'maintenance', 'match' => 'maintenance*', 'label' => 'Maintenance', 'icon' => ['M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z', 'M15 12a3 3 0 11-6 0 3 3 0 016 0z']],
        ],
        'Insight' => [
            ['route' => 'reports', 'match' => 'reports*', 'label' => 'Reports', 'icon' => ['M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z']],
            ['route' => 'ai-optimization', 'match' => 'ai-optimization*', 'label' => 'AI Optimization', 'icon' => ['M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z']],
        ],
    ];
@endphp
<aside id="mines-sidebar" class="mines-sidebar" aria-label="Main navigation" data-mines-sidebar>
    {{-- Phone and tablet drawer header (dot-ui adopt mode): the name and the close button. The mark itself is in the header, once. --}}
    <div class="mines-sidebar__head">
        <a href="{{ route('dashboard') }}" class="mines-sidebar__brand">
            <span class="mines-sidebar__name">Dot.Mines<small>Fleet Manager</small></span>
        </a>
        <button type="button" data-dh-close class="mines-sidebar__close" aria-label="Close navigation">
            <svg class="mines-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
    </div>

    <nav class="mines-nav" aria-label="Product areas">
        @foreach ($minesNav as $group => $links)
            <div class="mines-nav__group" role="group" aria-label="{{ $group }}">
                <div class="mines-nav__label" aria-hidden="true">{{ $group }}</div>
                @foreach ($links as $link)
                    <a href="{{ route($link['route']) }}"
                       class="mines-nav__link"
                       title="{{ $link['label'] }}"
                       @if (request()->routeIs($link['match'])) aria-current="page" @endif>
                        <svg class="mines-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            @foreach ($link['icon'] as $d)<path d="{{ $d }}"/>@endforeach
                        </svg>
                        <span class="mines-nav__text">{{ $link['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>

    {{-- Desktop collapse to an icon rail. The choice is remembered in localStorage (key sidebarOpen, as before) and applied before first paint. --}}
    <button type="button" class="mines-rail-toggle" data-rail-toggle aria-pressed="false" aria-label="Collapse the sidebar" title="Collapse or expand the sidebar">
        <svg class="mines-ic mines-rail-toggle__ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 19l-7-7 7-7"/></svg>
        <span class="mines-nav__text">Collapse</span>
    </button>
</aside>
