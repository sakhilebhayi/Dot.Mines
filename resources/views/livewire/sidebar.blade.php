<nav id="mobile-sidebar"
     role="navigation"
     aria-label="Main navigation"
     class="bg-[var(--ink-soft)] border-r border-[var(--line)] overflow-y-auto overflow-x-hidden transition-all duration-300 shrink-0 fixed inset-y-0 left-0 z-50 w-72 -translate-x-full md:relative md:inset-y-auto md:left-auto md:z-auto md:translate-x-0"
     :class="{
         'md:w-64': sidebarOpen, 'md:w-20': !sidebarOpen,
         'translate-x-0': mobileOpen, '-translate-x-full': !mobileOpen,
     }"
     x-data="{
         sidebarOpen: localStorage.getItem('sidebarOpen') === 'false' ? false : true,
         // Kept in sync with the navbar's hamburger button and the page
         // backdrop via a plain window CustomEvent -- see resources/js/app.js
         // for why this isn't an Alpine.store().
         mobileOpen: false,
         // True whenever the sidebar should render its full labeled state --
         // either the desktop user has it expanded, or the mobile drawer is
         // open (which always shows the full/labeled layout; there's no
         // icon-only mode on a touch drawer).
         get expanded() { return this.sidebarOpen || this.mobileOpen; },
         init() {
             this.$watch('sidebarOpen', value => {
                 localStorage.setItem('sidebarOpen', value);
                 @this.set('sidebarOpen', value);
             });
         }
     }"
     @mobile-nav-changed.window="mobileOpen = $event.detail.open">
    <!-- Logo Section -->
    <div class="p-6 border-b border-[var(--line)]">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 mb-3" :class="{ 'justify-center': !expanded }">
            <div class="w-10 h-10 bg-[var(--stone)] rounded-lg flex items-center justify-center flex-shrink-0 overflow-hidden">
                <img src="{{ asset('images/mark.png') }}" alt="Dot.Mines" class="w-full h-full object-contain">
            </div>
            <div class="flex flex-col overflow-hidden transition-all duration-300" x-show="expanded">
                <span class="font-display font-semibold text-[var(--stone)] text-lg whitespace-nowrap">Dot.Mines</span>
                <span class="text-xs text-[var(--sand)] whitespace-nowrap">Fleet Manager</span>
            </div>
        </a>

        <!-- Mobile close button (the desktop collapse toggle below has no
             equivalent meaning for a binary open/closed touch drawer) -->
        <div class="flex md:hidden justify-end mb-2">
            <button
                @click="window.mobileNav.close()"
                type="button"
                class="p-2 -mr-2 rounded-lg text-[var(--sand)] hover:bg-white/5 hover:text-[var(--stone)] transition-colors"
                aria-label="Close navigation menu"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Desktop collapse toggle -->
        <div class="hidden md:flex" :class="{ 'justify-center': !expanded }">
            <button
                @click="sidebarOpen = !sidebarOpen"
                class="w-full py-2 bg-[var(--gold)]/10 hover:bg-[var(--gold)]/20 rounded-lg flex items-center justify-center transition-colors"
                title="Toggle Sidebar"
            >
                <svg class="w-4 h-4 text-[var(--gold)] transition-transform duration-300" :class="{ 'rotate-180': !sidebarOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"></path>
                </svg>
            </button>
        </div>
    </div>

    <!-- Navigation Menu -->
    <div class="p-4 space-y-2">
        <!-- Dashboard -->
        <a href="{{ route('dashboard') }}" 
           class="nav-link px-4 py-3 rounded-lg transition-colors flex items-center gap-3 {{ request()->routeIs('dashboard') ? 'bg-[var(--gold)] text-[var(--ink)]' : 'text-[var(--sand)] hover:bg-white/5 hover:text-[var(--stone)]' }}"
           :class="{ 'justify-center': !expanded }"
           :title="!expanded ? 'Dashboard' : ''">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-3m0 0l7-4 7 4M5 9v10a1 1 0 001 1h12a1 1 0 001-1V9m-9 11l4-4m-4 4L7 15m6-6l4 4m0-11V9a2 2 0 00-2-2H7a2 2 0 00-2 2v6"></path>
            </svg>
            <span class="whitespace-nowrap overflow-hidden transition-all duration-300" x-show="expanded">Dashboard</span>
        </a>

        <!-- Fleet Management -->
        <a href="{{ route('fleet') }}" 
           class="nav-link px-4 py-3 rounded-lg transition-colors flex items-center gap-3 {{ request()->routeIs('fleet*') ? 'bg-[var(--gold)] text-[var(--ink)]' : 'text-[var(--sand)] hover:bg-white/5 hover:text-[var(--stone)]' }}"
           :class="{ 'justify-center': !expanded }"
           :title="!expanded ? 'Fleet' : ''">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
            </svg>
            <span class="whitespace-nowrap overflow-hidden transition-all duration-300" x-show="expanded">Fleet</span>
        </a>

        <!-- Live Map -->
        <a href="{{ route('map') }}" 
           class="nav-link px-4 py-3 rounded-lg transition-colors flex items-center gap-3 {{ request()->routeIs('map*') ? 'bg-[var(--gold)] text-[var(--ink)]' : 'text-[var(--sand)] hover:bg-white/5 hover:text-[var(--stone)]' }}"
           :class="{ 'justify-center': !expanded }"
           :title="!expanded ? 'Live Map' : ''">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6 3m-6-3v-13m6 3l5.553-2.776A1 1 0 0121 5.618v10.764a1 1 0 01-1.447.894L15 20m0-13v13"></path>
            </svg>
            <span class="whitespace-nowrap overflow-hidden transition-all duration-300" x-show="expanded">Live Map</span>
        </a>

        <!-- Geofences -->
        <a href="{{ route('geofences') }}" 
           class="nav-link px-4 py-3 rounded-lg transition-colors flex items-center gap-3 {{ request()->routeIs('geofences*') ? 'bg-[var(--gold)] text-[var(--ink)]' : 'text-[var(--sand)] hover:bg-white/5 hover:text-[var(--stone)]' }}"
           :class="{ 'justify-center': !expanded }"
           :title="!expanded ? 'Geofences' : ''">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
            </svg>
            <span class="whitespace-nowrap overflow-hidden transition-all duration-300" x-show="expanded">Geofences</span>
        </a>

        <!-- Mine Areas -->
        <a href="{{ route('mine-areas') }}" 
           class="nav-link px-4 py-3 rounded-lg transition-colors flex items-center gap-3 {{ request()->routeIs('mine-areas*') ? 'bg-[var(--gold)] text-[var(--ink)]' : 'text-[var(--sand)] hover:bg-white/5 hover:text-[var(--stone)]' }}"
           :class="{ 'justify-center': !expanded }"
           :title="!expanded ? 'Mine Areas' : ''">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6 3m-6-3v-13m6 3l5.553-2.776A1 1 0 0121 5.618v10.764a1 1 0 01-1.447.894L15 20m0-13v13"></path>
            </svg>
            <span class="whitespace-nowrap overflow-hidden transition-all duration-300" x-show="expanded">Mine Areas</span>
        </a>

        <!-- Reports -->
        <a href="{{ route('reports') }}" 
           class="nav-link px-4 py-3 rounded-lg transition-colors flex items-center gap-3 {{ request()->routeIs('reports*') ? 'bg-[var(--gold)] text-[var(--ink)]' : 'text-[var(--sand)] hover:bg-white/5 hover:text-[var(--stone)]' }}"
           :class="{ 'justify-center': !expanded }"
           :title="!expanded ? 'Reports' : ''">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
            <span class="whitespace-nowrap overflow-hidden transition-all duration-300" x-show="expanded">Reports</span>
        </a>

        <!-- Production Dashboard -->
        <a href="{{ route('production') }}" 
           class="nav-link px-4 py-3 rounded-lg transition-colors flex items-center gap-3 {{ request()->routeIs('production*') ? 'bg-[var(--gold)] text-[var(--ink)]' : 'text-[var(--sand)] hover:bg-white/5 hover:text-[var(--stone)]' }}"
           :class="{ 'justify-center': !expanded }"
           :title="!expanded ? 'Production' : ''">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
            </svg>
            <span class="whitespace-nowrap overflow-hidden transition-all duration-300" x-show="expanded">Production</span>
        </a>

        <!-- Alerts -->
        <a href="{{ route('alerts') }}" 
           class="nav-link px-4 py-3 rounded-lg transition-colors flex items-center gap-3 {{ request()->routeIs('alerts*') ? 'bg-[var(--gold)] text-[var(--ink)]' : 'text-[var(--sand)] hover:bg-white/5 hover:text-[var(--stone)]' }}"
           :class="{ 'justify-center': !expanded }"
           :title="!expanded ? 'Alerts' : ''">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
            </svg>
            <span class="whitespace-nowrap overflow-hidden transition-all duration-300" x-show="expanded">Alerts</span>
        </a>

        <!-- Operator Fatigue -->
        <a href="{{ route('operator-fatigue') }}"
           class="nav-link px-4 py-3 rounded-lg transition-colors flex items-center gap-3 {{ request()->routeIs('operator-fatigue*') ? 'bg-[var(--gold)] text-[var(--ink)]' : 'text-[var(--sand)] hover:bg-white/5 hover:text-[var(--stone)]' }}"
           :class="{ 'justify-center': !expanded }"
           :title="!expanded ? 'Operator Fatigue' : ''">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z"></path>
            </svg>
            <span class="whitespace-nowrap overflow-hidden transition-all duration-300" x-show="expanded">Operator Fatigue</span>
        </a>

        <!-- Fuel Management -->
        <a href="{{ route('fuel') }}" 
           class="nav-link px-4 py-3 rounded-lg transition-colors flex items-center gap-3 {{ request()->routeIs('fuel*') ? 'bg-[var(--gold)] text-[var(--ink)]' : 'text-[var(--sand)] hover:bg-white/5 hover:text-[var(--stone)]' }}"
           :class="{ 'justify-center': !expanded }"
           :title="!expanded ? 'Fuel Management' : ''">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
            </svg>
            <span class="whitespace-nowrap overflow-hidden transition-all duration-300" x-show="expanded">Fuel Management</span>
        </a>

        <!-- Maintenance -->
        <a href="{{ route('maintenance') }}" 
           class="nav-link px-4 py-3 rounded-lg transition-colors flex items-center gap-3 {{ request()->routeIs('maintenance*') ? 'bg-[var(--gold)] text-[var(--ink)]' : 'text-[var(--sand)] hover:bg-white/5 hover:text-[var(--stone)]' }}"
           :class="{ 'justify-center': !expanded }"
           :title="!expanded ? 'Maintenance' : ''">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            <span class="whitespace-nowrap overflow-hidden transition-all duration-300" x-show="expanded">Maintenance</span>
        </a>

        <!-- AI Optimization (NEW!) -->
        <a href="{{ route('ai-optimization') }}" 
           class="nav-link px-4 py-3 rounded-lg transition-colors flex items-center gap-3 {{ request()->routeIs('ai-optimization*') ? 'bg-gradient-to-r from-[var(--gold)] to-[var(--umber)] text-[var(--ink)]' : 'text-[var(--sand)] hover:bg-gradient-to-r hover:from-[var(--gold)] hover:to-[var(--umber)] hover:text-[var(--ink)]' }}"
           :class="{ 'justify-center': !expanded }"
           :title="!expanded ? 'AI Optimization' : ''">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
            </svg>
            <span class="whitespace-nowrap overflow-hidden transition-all duration-300 font-medium" x-show="expanded">AI Optimization</span>
        </a>
    </div>
</nav>
