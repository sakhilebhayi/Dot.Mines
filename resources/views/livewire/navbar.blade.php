@php
    // The account menu: the seven destinations that used to sit in one flat list are two labelled groups.
    $accountMenu = $user ? [
        'name' => $user->name,
        'email' => $user->email,
        'team' => $team?->name,
        'links' => array_values(array_filter([
            ['heading' => 'Account'],
            ['label' => 'My profile', 'href' => route('profile.show'), 'current' => request()->routeIs('profile.show')],
            $team ? ['label' => 'Team', 'href' => route('teams.show', $team), 'current' => request()->routeIs('teams.*')] : null,
            ['label' => 'Billing', 'href' => route('billing.index'), 'current' => request()->routeIs('billing.*')],
            ['heading' => 'Workspace'],
            ['label' => 'Settings', 'href' => route('settings'), 'current' => request()->routeIs('settings')],
            ['label' => 'Integrations', 'href' => route('integrations'), 'current' => request()->routeIs('integrations*')],
            ['label' => 'API tokens', 'href' => route('api-tokens.index'), 'current' => request()->routeIs('api-tokens.*')],
            ['label' => 'Documentation', 'href' => route('documentation'), 'current' => request()->routeIs('documentation')],
        ])),
        'signOut' => ['action' => route('logout'), 'label' => 'Log out'],
    ] : null;
@endphp
{{-- Livewire needs one root; it steps out of the layout (display: contents) so the header stays sticky against the page. --}}
<div class="mines-hdr">
    {{-- One shared header (dot-ui): brand, the live state, the centred search, the Dot Apps switcher, alerts and the account.
         Mines is a sidebar shell, so there is no primary nav here: the menu button opens the sidebar (adopt mode). --}}
    <x-dot.header id="dh" brand-href="{{ route('dashboard') }}" brand-label="Dot.Mines, dashboard" :account="$accountMenu"
        drawer-target="mines-sidebar" menu-label="Open navigation" search-label="Search Dot.Mines"
        :context="$team?->name">
        <x-slot:brand>
            <span class="mines-mark"><img src="{{ asset('images/mark.png') }}" alt="" width="34" height="34"></span>
            <span class="dh-brand__name">Dot.Mines</span>
        </x-slot:brand>

        <x-slot:status>
            {{-- Two honest states, both client-side: the realtime socket (driven by the 'realtime-connection-changed' event from
                 resources/js/livewire-realtime.js) and the sync state (resources/js/local/connectivity.js). They sit in the bar
                 instead of floating over the page. Under 520px only the lamps show; the words are in the accessible name. --}}
            <div
                x-data="{ state: 'connecting' }"
                x-init="window.addEventListener('realtime-connection-changed', (e) => { state = e.detail.state })"
                class="mines-lamp"
                role="img"
                :aria-label="{
                    connected: 'Live updates connected',
                    connecting: 'Connecting to live updates',
                    reconnecting: 'Reconnecting to live updates',
                    disconnected: 'Live updates disconnected',
                }[state]"
                :title="{
                    connected: 'Live updates connected',
                    connecting: 'Connecting to live updates…',
                    reconnecting: 'Reconnecting to live updates…',
                    disconnected: 'Live updates disconnected',
                }[state]"
            >
                <span class="mines-lamp__dot" :data-state="state"></span>
            </div>
            <div data-connectivity-pill class="hidden mines-cp" role="status" aria-live="polite">
                <span data-connectivity-dot class="inline-block w-2 h-2 rounded-full bg-zinc-500"></span>
                <span data-connectivity-label class="dh-status__text">Connecting</span>
            </div>
        </x-slot:status>

        <x-slot:search>
            <livewire:header-search />
        </x-slot:search>

        <x-slot:tools>
            {{-- Shared Dot Apps switcher. Mines has its own sign-in and cannot ask InfoDot what a company holds, so the
                 model is the plain one: core platforms through InfoDot, with a visible line saying so. --}}
            <x-dot.switcher
                platform="mines"
                :model="\App\Support\PlainSwitcherModel::build()"
                logos="/dot-ui/img/platforms"
                :notice="\App\Support\PlainSwitcherModel::NOTICE"
                :notice-detail="\App\Support\PlainSwitcherModel::NOTICE_DETAIL"
                wire:ignore.self />
            <livewire:ai-notifications />
        </x-slot:tools>
    </x-dot.header>
</div>
