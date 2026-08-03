<div class="min-h-screen bg-base-100">
    <div class="flex">

        {{-- SIDEBAR --}}
        <aside class="w-72 bg-base-200 min-h-screen sticky top-0 flex flex-col" style="max-height:100vh;">
            <div class="p-5 border-b border-base-300">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-2xl">📚</span>
                    <h2 class="text-xl font-bold">Help &amp; Docs</h2>
                </div>
                <input
                    wire:model.live="search"
                    type="text"
                    placeholder="🔍 Search topics…"
                    class="input input-sm input-bordered w-full"
                />
            </div>

            {{-- Setup Progress Bar --}}
            <div class="px-5 py-3 border-b border-base-300">
                <p class="text-xs font-semibold text-base-content/60 uppercase tracking-wider mb-2">Your Setup Progress</p>
                <div class="flex items-center gap-2">
                    <progress
                        class="progress progress-primary flex-1 h-2"
                        value="{{ $this->completedCount() }}"
                        max="{{ count($checklist) }}"
                    ></progress>
                    <span class="text-xs font-bold text-primary">{{ $this->completedCount() }}/{{ count($checklist) }}</span>
                </div>
                @if($this->completedCount() === count($checklist))
                    <p class="text-xs text-success font-semibold mt-1">🎉 Setup complete!</p>
                @else
                    <p class="text-xs text-base-content/50 mt-1">{{ count($checklist) - $this->completedCount() }} steps remaining</p>
                @endif
            </div>

            <nav class="overflow-y-auto flex-1 p-3">
                @php
                    $navItems = [
                        'Getting Started' => [
                            ['onboarding',       '🚀', 'Start Here (New Users)'],
                            ['getting-started',  '🏠', 'Platform Overview'],
                            ['quick-start',      '⚡', 'Quick Start Checklist'],
                            ['dashboard',        '📊', 'Dashboard Guide'],
                        ],
                        'Team & Users' => [
                            ['team-management',  '👥', 'Managing Your Team'],
                            ['user-roles',       '🔐', 'Roles & Permissions'],
                        ],
                        'Fleet & Machines' => [
                            ['fleet',            '🚛', 'Adding Machines'],
                            ['machine-tracking', '📍', 'Tracking Machines'],
                            ['live-map',         '🗺️',  'Live Map'],
                        ],
                        'Operations' => [
                            ['mine-areas',       '⛏️',  'Mine Areas'],
                            ['geofences',        '🔶', 'Geofences'],
                            ['fuel-management',  '⛽', 'Fuel Tanks & Tracking'],
                            ['maintenance',      '🔧', 'Maintenance'],
                            ['ai-maintenance',   '🤖', 'AI Maintenance'],
                        ],
                        'Reports & Alerts' => [
                            ['reports',          '📈', 'Reports'],
                            ['alerts',           '🔔', 'Alerts'],
                        ],
                        'Integrations' => [
                            ['integrations-overview', '🔌', 'Integrations'],
                            ['api-access',       '💻', 'API Access'],
                        ],
                    ];
                @endphp

                @foreach($navItems as $group => $items)
                    @php
                        $visible = collect($items)->filter(function($item) use ($search) {
                            if (empty($search)) {
                                return true;
                            }
                            return str_contains(strtolower($item[2]), strtolower($search))
                                || str_contains(strtolower($item[0]), strtolower($search));
                        });
                    @endphp

                    @if($visible->isNotEmpty())
                        <p class="text-xs font-bold text-base-content/50 uppercase tracking-wider px-2 mt-4 mb-1">{{ $group }}</p>
                        @foreach($visible as [$slug, $icon, $label])
                            <button
                                wire:click="setSection('{{ $slug }}')"
                                class="w-full text-left flex items-center gap-2 px-3 py-2 rounded-lg text-sm transition-all
                                    {{ $activeSection === $slug
                                        ? 'bg-primary text-primary-content font-semibold'
                                        : 'hover:bg-base-300 text-base-content' }}"
                            >
                                <span>{{ $icon }}</span>
                                <span>{{ $label }}</span>
                            </button>
                        @endforeach
                    @endif
                @endforeach
            </nav>

            <div class="p-4 border-t border-base-300">
                <a href="mailto:support@{{ parse_url(config('app.url'), PHP_URL_HOST) }}"
                   class="btn btn-sm btn-outline btn-primary w-full">
                    💬 Contact Support
                </a>
            </div>
        </aside>

        {{-- MAIN CONTENT --}}
        <main class="flex-1 p-8 max-w-5xl overflow-y-auto">

            {{-- ============== ONBOARDING ============== --}}
            @if($activeSection === 'onboarding')
                <div class="rounded-xl p-6 mb-8 border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                    <div class="flex items-start gap-4">
                        <div>
                            <h1 class="text-2xl font-bold mb-1">{{ config('app.name') }} Setup Guide</h1>
                            <p class="text-base-content/70 text-base leading-relaxed">
                                Welcome to <strong>{{ config('app.name') }}</strong>. This guide will walk you through the initial platform configuration, step by step.
                            </p>
                        </div>
                    </div>
                </div>

                <h2 class="text-xl font-bold mb-4">Setup Checklist</h2>
                <p class="text-base-content/60 mb-5">Complete each step to get your platform fully configured. Click "Show me" for detailed instructions.</p>

                <div class="space-y-3 mb-10">
                    @php
                        $onboardingSteps = [
                            ['add_user',        '👥', 'Invite your first team member',   'team-management',  'Add the people who will use the platform — your operators, fleet managers, and supervisors.'],
                            ['add_machine',     '🚛', 'Add your first machine',           'fleet',            'Register your trucks, excavators, and equipment so you can track them.'],
                            ['add_fuel_tank',   '⛽', 'Set up a fuel tank',              'fuel-management',  'Add your fuel storage tanks so you can track how much fuel you have and use.'],
                            ['add_geofence',    '🔶', 'Create a geofence zone',           'geofences',        'Draw a boundary on the map around an important area — like a loading zone or restricted area.'],
                            ['add_mine_area',   '⛏️', 'Define a mine area',              'mine-areas',       'Group your machines and geofences into organised sections of your mine site.'],
                            ['add_maintenance', '🔧', 'Schedule your first maintenance', 'maintenance',      'Set up a service schedule so your machines never miss a service!'],
                        ];
                    @endphp

                    @foreach($onboardingSteps as [$key, $icon, $title, $section, $desc])
                        <div class="card bg-base-200 border {{ $checklist[$key] ? 'border-success/50 bg-success/5' : 'border-base-300' }} transition-all">
                            <div class="card-body p-4 flex flex-row items-center gap-4">
                                <button wire:click="toggleCheck('{{ $key }}')" class="shrink-0">
                                    @if($checklist[$key])
                                        <div class="w-8 h-8 rounded-full bg-success flex items-center justify-center text-success-content font-bold text-lg">✓</div>
                                    @else
                                        <div class="w-8 h-8 rounded-full border-2 border-base-content/30 flex items-center justify-center text-base-content/30 text-lg">○</div>
                                    @endif
                                </button>
                                <span class="text-2xl shrink-0">{{ $icon }}</span>
                                <div class="flex-1">
                                    <p class="font-semibold {{ $checklist[$key] ? 'line-through text-base-content/40' : '' }}">{{ $title }}</p>
                                    <p class="text-sm text-base-content/60">{{ $desc }}</p>
                                </div>
                                <button wire:click="setSection('{{ $section }}')"
                                        class="btn btn-sm {{ $checklist[$key] ? 'btn-ghost' : 'btn-primary' }} shrink-0">
                                    {{ $checklist[$key] ? 'Review' : 'Show me →' }}
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($this->completedCount() === count($checklist))
                    <div class="alert alert-success text-lg font-bold">
                        <span>🎉</span>
                        <span>Amazing! You've completed the setup! Your mining platform is ready to go. Explore the full docs from the sidebar anytime.</span>
                    </div>
                @else
                    <h2 class="text-xl font-bold mb-4">🗺️ What can this platform do?</h2>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">
                        @foreach([
                            ['🚛', 'Track Machines', 'See where every truck and machine is, right now, on a live map.'],
                            ['⛽', 'Manage Fuel', 'Know how much fuel you have, who used it, and spot waste early.'],
                            ['🔧', 'Schedule Services', 'Never miss a service — the system reminds you automatically.'],
                            ['🔶', 'Geofence Zones', 'Get alerts when a machine enters or leaves an area it should not.'],
                            ['🤖', 'AI Predictions', 'Our AI detects problems before they happen and alerts your team.'],
                            ['📊', 'Smart Reports', 'One click to generate reports for management or compliance.'],
                        ] as [$ico, $t, $d])
                            <div class="card bg-base-200 border border-base-300 hover:border-primary/50 transition-all">
                                <div class="card-body p-4 text-center">
                                    <div class="text-3xl mb-2">{{ $ico }}</div>
                                    <p class="font-semibold text-sm">{{ $t }}</p>
                                    <p class="text-xs text-base-content/60 mt-1">{{ $d }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif

            {{-- ============== GETTING STARTED ============== --}}
            @if($activeSection === 'getting-started')
                <div class="prose max-w-none">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-4xl">🏠</span>
                        <h1 class="mb-0">Platform Overview</h1>
                    </div>
                    <p class="text-lg">{{ config('app.name') }} is your all-in-one command centre for managing a mining fleet. Think of it as the "control room" for all your trucks, excavators, fuel, and maintenance.</p>

                    <div class="alert alert-info not-prose mb-6">
                        <span class="text-xl">💡</span>
                        <span><strong>Think of it like this:</strong> If your mine site is a city, {{ config('app.name') }} is the city's traffic control system — you can see everything, plan everything, and get notified about anything unusual.</span>
                    </div>

                    <h2>What's inside the platform?</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 mt-4">
                    @foreach([
                        ['📊', 'Dashboard', 'Your homepage. Shows live machine counts, fuel levels, alerts, and the key numbers your team needs every day.'],
                        ['🚛', 'Fleet', 'The list of all your machines — trucks, excavators, dozers, and more. Add new ones, edit details, see their history.'],
                        ['⛽', 'Fuel Management', 'Track your fuel tanks and every litre that goes in or out. Spot waste, manage budgets, and never run dry.'],
                        ['🔧', 'Maintenance', 'Schedule services, log work orders, and track machine health. The AI can even predict problems before they happen!'],
                        ['🔶', 'Geofences', 'Draw invisible boundaries on the map. Get an instant alert if a machine crosses a line it should not.'],
                        ['⛏️', 'Mine Areas', 'Organise your mine site into named sections and track productivity per area.'],
                        ['🔔', 'Alerts', 'All critical notifications in one place — engine issues, fuel theft, geofence breaches, and more.'],
                        ['📈', 'Reports', 'Generate PDF and CSV reports on fleet performance, fuel use, and maintenance costs in seconds.'],
                    ] as [$ico, $t, $d])
                        <div class="card bg-base-200 border border-base-300">
                            <div class="card-body p-4">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-2xl">{{ $ico }}</span>
                                    <h3 class="font-bold text-base m-0">{{ $t }}</h3>
                                </div>
                                <p class="text-sm text-base-content/70">{{ $d }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="prose max-w-none">
                    <h2>Who uses what?</h2>
                    <div class="not-prose overflow-x-auto">
                        <table class="table table-zebra">
                            <thead><tr><th>Your Job</th><th>What You'll Use Most</th></tr></thead>
                            <tbody>
                                <tr><td><span class="badge badge-error">Admin</span></td><td>Team settings, billing, all reports, all features</td></tr>
                                <tr><td><span class="badge badge-warning">Fleet Manager</span></td><td>Adding machines, scheduling maintenance, fuel budgets, geofences</td></tr>
                                <tr><td><span class="badge badge-info">Operator</span></td><td>Recording fuel transactions, viewing machine status, logging shift notes</td></tr>
                                <tr><td><span class="badge">Viewer</span></td><td>Reading dashboards, reports, and alerts — read only</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- ============== QUICK START ============== --}}
            @if($activeSection === 'quick-start')
                <div class="prose max-w-none">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-4xl">⚡</span>
                        <h1 class="mb-0">Quick Start — Your First 30 Minutes</h1>
                    </div>
                    <p>Follow these 6 simple steps and your platform will be fully set up. Each step takes only 2–5 minutes!</p>
                </div>

                <div class="space-y-6 mt-6">
                    @php
                        $quickSteps = [
                            [1, '👥', 'Invite Your Team', 'team-management', 'success',
                                'Before you do anything else, add the people who will use the platform.',
                                ['Go to ⚙️ Settings in the left sidebar', 'Click the "Users & Roles" tab', 'Type an email address and choose their role', 'Click "Send Invitation" — they\'ll get an email with a link to join'],
                                '🎓 Roles explained: Admin = full control. Fleet Manager = manages machines & fuel. Operator = records fuel & updates status. Viewer = read-only.'],
                            [2, '🚛', 'Add Your First Machine', 'fleet', 'info',
                                'Register your trucks, excavators, and other equipment.',
                                ['Click Fleet in the sidebar', 'Click the green "+ Add Machine" button', 'Enter the machine name (e.g. "Dump Truck 01")', 'Choose the type (Haul Truck, Excavator, Dozer, etc.)', 'Add the serial number and year if you have it', 'Click Save — your machine is now on the platform!'],
                                '💡 Don\'t worry about having all details — you can always edit a machine later.'],
                            [3, '⛽', 'Set Up a Fuel Tank', 'fuel-management', 'warning',
                                'Tell the platform about your fuel storage so you can track every litre.',
                                ['Go to ⛽ Fuel Management in the sidebar', 'Click "Add Tank"', 'Give it a name (e.g. "Main Diesel Tank")', 'Enter the capacity in litres (e.g. 50,000)', 'Set a low-level warning threshold (e.g. 5,000 litres)', 'Choose the fuel type (Diesel is most common)', 'Click Save'],
                                '⚠️ Setting a minimum level is important — the system will automatically alert you when fuel is running low!'],
                            [4, '🔶', 'Create a Geofence', 'geofences', 'error',
                                'Draw a boundary on the map around an important area.',
                                ['Click Geofences in the sidebar', 'Click "Create Geofence"', 'Zoom to your mine site on the map', 'Click the draw button and trace the boundary by clicking on the map', 'Give it a name (e.g. "Loading Zone A")', 'Choose the type (Loading, Restricted, Parking, etc.)', 'Click Save'],
                                '🔶 Great first geofences: your main loading zone, the fuel bay, and any restricted no-go areas.'],
                            [5, '⛏️', 'Define a Mine Area', 'mine-areas', 'secondary',
                                'Group machines and geofences into named sections of your mine site.',
                                ['Go to Mine Areas in the sidebar', 'Click "Create Area"', 'Enter the area name (e.g. "Pit A" or "North Waste Dump")', 'Choose the status (Active, Planning, Closed)', 'Add a manager name and contact (optional)', 'Draw the boundary on the map (or skip for now)', 'Click Save'],
                                '⛏️ Mine areas are like folders — they help you organise everything by location.'],
                            [6, '🔧', 'Schedule Your First Maintenance', 'maintenance', 'neutral',
                                'Set up a service reminder so your machine never misses a service.',
                                ['Go to 🔧 Maintenance in the sidebar', 'Click "Create Work Order"', 'Select the machine you added in Step 2', 'Choose the maintenance type (e.g. "Preventive")', 'Give it a title (e.g. "500-Hour Service")', 'Set the scheduled date', 'Set the priority (Medium is fine to start)', 'Click Save'],
                                '🤖 Pro tip: The AI will automatically predict when your machines need service based on usage hours.'],
                        ];
                    @endphp

                    @foreach($quickSteps as [$num, $icon, $title, $section, $color, $intro, $steps, $tip])
                        <div class="card bg-base-200 border border-base-300">
                            <div class="card-body p-6">
                                <div class="flex items-start gap-4">
                                    <div class="badge badge-{{ $color }} badge-lg font-bold w-10 h-10 rounded-full flex items-center justify-center shrink-0 p-0 text-base">{{ $num }}</div>
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between flex-wrap gap-2 mb-2">
                                            <h3 class="font-bold text-lg flex items-center gap-2">
                                                <span>{{ $icon }}</span> {{ $title }}
                                            </h3>
                                            <button wire:click="setSection('{{ $section }}')" class="btn btn-sm btn-{{ $color }}">
                                                Full Guide →
                                            </button>
                                        </div>
                                        <p class="text-base-content/70 text-sm mb-3">{{ $intro }}</p>

                                        <ol class="space-y-1 mb-3">
                                            @foreach($steps as $i => $step)
                                                <li class="flex items-start gap-2 text-sm">
                                                    <span class="badge badge-sm badge-outline mt-0.5 shrink-0">{{ $i + 1 }}</span>
                                                    <span>{{ $step }}</span>
                                                </li>
                                            @endforeach
                                        </ol>

                                        <div class="bg-base-100 rounded-lg p-3 text-sm text-base-content/70 border border-base-300">
                                            {{ $tip }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="alert alert-success mt-8">
                    <span class="text-2xl">🎉</span>
                    <div>
                        <p class="font-bold">You're all set!</p>
                        <p class="text-sm">Once you've completed these steps, your platform is fully operational. Head to the Dashboard to see everything in action.</p>
                    </div>
                    <a href="/dashboard" class="btn btn-success btn-sm">Go to Dashboard →</a>
                </div>
            @endif

            {{-- ============== TEAM MANAGEMENT ============== --}}
            @if($activeSection === 'team-management')
                <div class="prose max-w-none">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-4xl">👥</span>
                        <h1 class="mb-0">Managing Your Team</h1>
                    </div>
                    <p class="text-lg">Your team is the group of people who use the platform. Adding people is easy — you just send them an email invitation and they join your team.</p>

                    <div class="alert alert-info not-prose mb-6">
                        <span class="text-xl">🤖</span>
                        <span><strong>AI Tip:</strong> New users receive a welcome email automatically. They'll be guided through logging in for the first time.</span>
                    </div>

                    <h2>How to Add a New User — Step by Step</h2>
                </div>

                <div class="space-y-3 mt-4 mb-8">
                    @foreach([
                        ['1', '⚙️', 'Go to Settings', 'Click the ⚙️ Settings icon in the left sidebar at the bottom of the page.'],
                        ['2', '👥', 'Open "Users & Roles" tab', 'At the top of the Settings page, click on the "Users & Roles" tab. You\'ll see a list of current team members.'],
                        ['3', '✉️', 'Click "Invite User"', 'Click the blue "Invite User" or "+ Add Member" button. A form will appear.'],
                        ['4', '📧', 'Enter their email address', 'Type the email address of the person you want to add. Make sure it\'s correct!'],
                        ['5', '🔐', 'Choose their role', 'Select what they\'re allowed to do (see role guide below). When in doubt, choose "Operator".'],
                        ['6', '📨', 'Send the invitation', 'Click "Send Invitation". They\'ll receive an email within a few minutes with a link to join.'],
                        ['7', '✅', 'They accept and join', 'The new user clicks the link in their email, creates their password, and they\'re in!'],
                    ] as [$num, $icon, $title, $desc])
                        <div class="flex gap-4">
                            <div class="flex flex-col items-center shrink-0">
                                <div class="w-10 h-10 rounded-full bg-primary text-primary-content flex items-center justify-center font-bold">{{ $num }}</div>
                                @if($num < '7')<div class="w-0.5 bg-base-300 flex-1 my-1 min-h-4"></div>@endif
                            </div>
                            <div class="pb-4 flex-1">
                                <div class="card bg-base-200 border border-base-300">
                                    <div class="card-body p-4">
                                        <p class="font-bold flex items-center gap-2 mb-1"><span>{{ $icon }}</span> {{ $title }}</p>
                                        <p class="text-sm text-base-content/70">{{ $desc }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="prose max-w-none">
                    <h2>🔐 Understanding Roles — What Can Each Person Do?</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 mb-8">
                    @foreach([
                        ['Admin', 'badge-error', '👑', 'Your most powerful user. Can do everything — manage the team, change settings, access all reports, delete machines.', ['Manage team members', 'Change all settings', 'View all reports', 'Add/remove machines', 'Manage billing']],
                        ['Fleet Manager', 'badge-warning', '🚛', 'Your operations supervisor. Manages machines, fuel, and maintenance but cannot change team settings.', ['Add/edit machines', 'Manage fuel tanks', 'Schedule maintenance', 'Create geofences', 'Generate reports']],
                        ['Operator', 'badge-info', '👷', 'Your day-to-day worker. Can record what they do but cannot change the platform setup.', ['Record fuel transactions', 'Update machine status', 'Log maintenance notes', 'View dashboard', 'View alerts']],
                        ['Viewer', 'badge-ghost', '👀', 'Someone who just needs to see the data — like a manager or auditor. Cannot change anything.', ['View dashboard', 'View reports', 'View alerts', 'View machine list', 'Read-only access']],
                    ] as [$role, $badge, $icon, $desc, $perms])
                        <div class="card bg-base-200 border border-base-300">
                            <div class="card-body p-5">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-2xl">{{ $icon }}</span>
                                    <span class="badge {{ $badge }} text-sm font-bold">{{ $role }}</span>
                                </div>
                                <p class="text-sm text-base-content/70 mb-3">{{ $desc }}</p>
                                <ul class="space-y-1">
                                    @foreach($perms as $p)
                                        <li class="text-sm flex items-center gap-2"><span class="text-success">✓</span> {{ $p }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="prose max-w-none">
                    <h2>How to Remove a Team Member</h2>
                    <ol>
                        <li>Go to <strong>⚙️ Settings → Users &amp; Roles</strong></li>
                        <li>Find the person you want to remove</li>
                        <li>Click the <strong>🗑️ Remove</strong> button next to their name</li>
                        <li>Confirm the removal</li>
                    </ol>
                    <div class="alert alert-warning not-prose">
                        <span>⚠️</span>
                        <span>You cannot remove yourself from the team. If you need to transfer ownership, contact support.</span>
                    </div>

                    <h2>How to Change Someone's Role</h2>
                    <ol>
                        <li>Go to <strong>⚙️ Settings → Users &amp; Roles</strong></li>
                        <li>Find the person</li>
                        <li>Click the role dropdown next to their name</li>
                        <li>Select the new role — it takes effect immediately</li>
                    </ol>
                </div>
            @endif

            {{-- ============== USER ROLES ============== --}}
            @if($activeSection === 'user-roles')
                <div class="prose max-w-none">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-4xl">🔐</span>
                        <h1 class="mb-0">Roles &amp; Permissions</h1>
                    </div>
                    <p>Roles control what each person can see and do. Here's a full breakdown of every permission.</p>

                    <div class="not-prose overflow-x-auto">
                        <table class="table table-zebra table-sm">
                            <thead>
                                <tr>
                                    <th>What you can do</th>
                                    <th class="text-center">👑 Admin</th>
                                    <th class="text-center">🚛 Fleet Mgr</th>
                                    <th class="text-center">👷 Operator</th>
                                    <th class="text-center">👀 Viewer</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach([
                                    ['View the dashboard', true, true, true, true],
                                    ['View machine list', true, true, true, true],
                                    ['View alerts', true, true, true, true],
                                    ['Record fuel transactions', true, true, true, false],
                                    ['Add/edit machines', true, true, false, false],
                                    ['Delete machines', true, false, false, false],
                                    ['Create geofences', true, true, false, false],
                                    ['Create mine areas', true, true, false, false],
                                    ['Schedule maintenance', true, true, false, false],
                                    ['Generate reports', true, true, false, false],
                                    ['Invite team members', true, false, false, false],
                                    ['Change team settings', true, false, false, false],
                                ] as [$action, $a, $fm, $op, $vw])
                                    <tr>
                                        <td>{{ $action }}</td>
                                        <td class="text-center">{{ $a ? '✅' : '—' }}</td>
                                        <td class="text-center">{{ $fm ? '✅' : '—' }}</td>
                                        <td class="text-center">{{ $op ? '✅' : '—' }}</td>
                                        <td class="text-center">{{ $vw ? '✅' : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- ============== FLEET ============== --}}
            @if($activeSection === 'fleet')
                <div class="prose max-w-none">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-4xl">🚛</span>
                        <h1 class="mb-0">Adding &amp; Managing Machines</h1>
                    </div>
                    <p class="text-lg">Machines are the heart of the platform. Every truck, excavator, and piece of equipment you register can be tracked, maintained, and analysed.</p>

                    <div class="alert alert-info not-prose mb-6">
                        <span class="text-xl">🤖</span>
                        <span><strong>AI Tip:</strong> Once a machine is added, our AI starts monitoring it automatically — watching for unusual engine hours, fuel patterns, and maintenance needs.</span>
                    </div>

                    <h2>How to Add a New Machine — Step by Step</h2>
                </div>

                <div class="space-y-3 mt-4 mb-8">
                    @foreach([
                        ['1', '🚛', 'Click Fleet in the sidebar', 'This opens your Fleet Management page where all your machines are listed.'],
                        ['2', '➕', 'Click "Add Machine"', 'Find the green "+ Add Machine" button at the top right of the page and click it.'],
                        ['3', '📝', 'Enter the machine name', 'Give it a clear name that everyone will recognise. For example: "Dump Truck 01" or "CAT 785F-01".'],
                        ['4', '🏷️', 'Choose the machine type', 'Select from the list: Haul Truck, Excavator, Dozer, Loader, Grader, Drill, or Support Vehicle.'],
                        ['5', '🔢', 'Add the serial number', 'This is usually found on a plate inside the cab or on the machine frame. It helps identify the machine uniquely.'],
                        ['6', '🏭', 'Add the manufacturer', 'For example: Caterpillar, Komatsu, Bell, Volvo, Hitachi. This helps with maintenance schedules.'],
                        ['7', '⛏️', 'Assign to a Mine Area (optional)', 'If you\'ve set up mine areas, you can place the machine in the right section now. You can also do this later.'],
                        ['8', '💾', 'Click Save', 'Your machine is now registered! It will appear in the fleet list and on the dashboard.'],
                    ] as [$num, $icon, $title, $desc])
                        <div class="flex gap-3 items-start">
                            <div class="w-8 h-8 rounded-full bg-primary text-primary-content flex items-center justify-center font-bold text-sm shrink-0 mt-1">{{ $num }}</div>
                            <div class="flex-1 bg-base-200 rounded-xl p-4 border border-base-300">
                                <p class="font-semibold flex items-center gap-2 mb-1"><span>{{ $icon }}</span> {{ $title }}</p>
                                <p class="text-sm text-base-content/70">{{ $desc }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="prose max-w-none">
                    <h2>🚦 Machine Status — What Do the Colours Mean?</h2>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-3 mb-6">
                    @foreach([
                        ['🟢', 'Active', 'success', 'Machine is running and moving. Everything is normal.'],
                        ['🟡', 'Idle', 'warning', 'Machine is on but not moving. Possible fuel waste.'],
                        ['🔵', 'Maintenance', 'info', 'Machine is booked in for a service.'],
                        ['🔴', 'Offline', 'error', 'No GPS signal. Machine may be shut down or have a problem.'],
                    ] as [$dot, $lbl, $badge, $desc])
                        <div class="card bg-base-200 border border-base-300 text-center">
                            <div class="card-body p-4">
                                <div class="text-2xl mb-1">{{ $dot }}</div>
                                <span class="badge badge-{{ $badge }} mb-2">{{ $lbl }}</span>
                                <p class="text-xs text-base-content/60">{{ $desc }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="prose max-w-none">
                    <h2>🏎️ Machine Types Explained</h2>
                    <table class="table">
                        <thead><tr><th>Type</th><th>What it does</th><th>Examples</th></tr></thead>
                        <tbody>
                            <tr><td>🚛 Haul Truck</td><td>Carries ore or waste material around the mine</td><td>CAT 785, Komatsu 785, Bell B50</td></tr>
                            <tr><td>🦾 Excavator</td><td>Digs and loads material into haul trucks</td><td>Hitachi EX1900, CAT 6060</td></tr>
                            <tr><td>🏗️ Dozer</td><td>Pushes material, levels roads</td><td>Komatsu D475, CAT D11</td></tr>
                            <tr><td>🚜 Loader</td><td>Scoops and carries material</td><td>Liebherr L586, CAT 994</td></tr>
                            <tr><td>🛣️ Grader</td><td>Smooths and levels roads</td><td>CAT 24M, Komatsu GD825</td></tr>
                            <tr><td>🛠️ Support Vehicle</td><td>Water trucks, lube trucks, service vehicles</td><td>Water tanker, service truck</td></tr>
                        </tbody>
                    </table>

                    <div class="alert alert-warning not-prose">
                        <span>⚠️</span>
                        <span><strong>Before deleting a machine:</strong> All its maintenance records, fuel transactions, and location history will also be removed. Export any reports you need first.</span>
                    </div>
                </div>
            @endif

            {{-- ============== FUEL MANAGEMENT ============== --}}
            @if($activeSection === 'fuel-management')
                <div class="prose max-w-none">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-4xl">⛽</span>
                        <h1 class="mb-0">Fuel Tanks &amp; Tracking</h1>
                    </div>
                    <p class="text-lg">Fuel is one of the biggest costs in mining. This section helps you track every litre — from your storage tanks, to your machines, to your costs.</p>

                    <div class="alert alert-warning not-prose mb-6">
                        <span class="text-xl">🤖</span>
                        <span><strong>AI Alert:</strong> The AI automatically watches fuel consumption patterns. If it spots unusual usage (like a machine using 2× its normal amount), it will send you an alert immediately.</span>
                    </div>

                    <h2>⛽ Part 1: Setting Up Fuel Tanks</h2>
                    <p>Before you can track fuel, you need to tell the system about your tanks. A fuel tank is a physical storage container at your mine site.</p>
                </div>

                <div class="space-y-3 mt-4 mb-8">
                    @foreach([
                        ['1', '⛽', 'Go to Fuel Management', 'Click "⛽ Fuel Management" in the left sidebar.'],
                        ['2', '➕', 'Click "Add Tank"', 'Look for the "+ Add Tank" button and click it.'],
                        ['3', '📝', 'Name your tank', 'Use a clear, descriptive name. E.g. "Main Diesel Tank", "Pit B Fuel Bowser".'],
                        ['4', '🔢', 'Enter the capacity', 'How many litres can it hold when completely full? (e.g. 50,000 litres)'],
                        ['5', '⚠️', 'Set the minimum level', 'This is the level where you want a low fuel warning. Set it to about 10% of capacity.'],
                        ['6', '⛽', 'Choose fuel type', 'Usually "Diesel". Other options: Petrol, Avgas, AdBlue.'],
                        ['7', '📍', 'Set the location (optional)', 'You can link it to a Mine Area so it shows on maps correctly.'],
                        ['8', '💾', 'Click Save', 'Your tank is now being tracked! Its level will update every time you record a transaction.'],
                    ] as [$num, $icon, $title, $desc])
                        <div class="flex gap-3 items-start">
                            <div class="w-8 h-8 rounded-full bg-warning text-warning-content flex items-center justify-center font-bold text-sm shrink-0 mt-1">{{ $num }}</div>
                            <div class="flex-1 bg-base-200 rounded-xl p-4 border border-base-300">
                                <p class="font-semibold flex items-center gap-2 mb-1"><span>{{ $icon }}</span> {{ $title }}</p>
                                <p class="text-sm text-base-content/70">{{ $desc }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="prose max-w-none">
                    <h2>📋 Part 2: Recording Fuel Transactions</h2>
                    <p>A transaction is recorded every time fuel moves — from a supplier into your tank, or from your tank into a machine.</p>

                    <h3>Filling a Machine from Your Tank (Most Common)</h3>
                    <ol>
                        <li>Go to ⛽ Fuel Management</li>
                        <li>Click <strong>"Record Transaction"</strong></li>
                        <li>Choose type: <strong>Dispensing</strong> (you're giving fuel to a machine)</li>
                        <li>Select which tank the fuel is coming from</li>
                        <li>Select which machine you're filling</li>
                        <li>Enter the number of litres you dispensed</li>
                        <li>Enter the date and time, then click <strong>Save</strong></li>
                    </ol>

                    <h3>Refilling Your Tank (Fuel Delivery)</h3>
                    <ol>
                        <li>Click <strong>"Record Transaction"</strong></li>
                        <li>Choose type: <strong>Refill</strong></li>
                        <li>Select the tank that was filled</li>
                        <li>Enter litres delivered and cost per litre (optional)</li>
                        <li>Add the supplier name and invoice number if you have them</li>
                        <li>Click <strong>Save</strong></li>
                    </ol>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mt-4 mb-6">
                    @foreach([
                        ['Dispensing', 'info', '🔽', 'Giving fuel from your tank to a machine.'],
                        ['Refill', 'success', '🔼', 'Adding fuel into your tank from a delivery.'],
                        ['Transfer', 'secondary', '↔️', 'Moving fuel between two of your tanks.'],
                        ['Adjustment', 'warning', '⚖️', 'Correcting the recorded level after a dip measurement.'],
                        ['Spillage', 'error', '💧', 'Recording accidental fuel loss.'],
                        ['Theft', 'error', '🚨', 'Recording suspected or confirmed theft.'],
                    ] as [$type, $badge, $icon, $desc])
                        <div class="card bg-base-200 border border-base-300">
                            <div class="card-body p-4">
                                <div class="flex items-center gap-2 mb-1">
                                    <span>{{ $icon }}</span>
                                    <span class="badge badge-{{ $badge }} text-xs">{{ $type }}</span>
                                </div>
                                <p class="text-xs text-base-content/70">{{ $desc }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="alert alert-error">
                    <span class="text-xl">🚨</span>
                    <div>
                        <p class="font-bold">If you record a Theft or Spillage:</p>
                        <p class="text-sm">The AI will flag it for review and send an alert to your Fleet Manager and Admin. This creates an automatic audit trail for insurance and compliance.</p>
                    </div>
                </div>
            @endif

            {{-- ============== GEOFENCES ============== --}}
            @if($activeSection === 'geofences')
                <div class="prose max-w-none">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-4xl">🔶</span>
                        <h1 class="mb-0">Geofences — Invisible Boundaries on the Map</h1>
                    </div>
                    <p class="text-lg">A geofence is like drawing a circle or shape on a map. When a machine crosses that line, the platform instantly notifies you. Super useful for safety, productivity, and compliance!</p>

                    <div class="alert alert-info not-prose mb-6">
                        <span class="text-xl">💡</span>
                        <span><strong>Real-world example:</strong> Draw a geofence around your fuel bay. Every time a haul truck enters, the system records it automatically. When it leaves, it logs the exit too. You get a perfect productivity record!</span>
                    </div>

                    <h2>🗺️ How to Create a Geofence</h2>
                </div>

                <div class="space-y-3 mt-4 mb-8">
                    @foreach([
                        ['1', '🔶', 'Go to Geofences', 'Click "🔶 Geofences" in the left sidebar.'],
                        ['2', '➕', 'Click "Create Geofence"', 'Find the "+ Create Geofence" button and click it. A map will open.'],
                        ['3', '🗺️', 'Find your location on the map', 'Use the scroll wheel to zoom in to your mine site. You can also type an address or coordinates to jump there quickly.'],
                        ['4', '✏️', 'Draw your boundary', 'Click the draw/polygon tool. Then click on the map to place each corner of your boundary. When finished, click back on your first point to close the shape.'],
                        ['5', '📝', 'Name the geofence', 'Give it a clear name. Examples: "Loading Zone A", "No-Go Zone 1", "Fuel Bay".'],
                        ['6', '🏷️', 'Choose the type', 'Select the type that best describes this area (Loading Zone, Dumping Zone, Restricted, Parking, Maintenance, Safety).'],
                        ['7', '⛏️', 'Link to a Mine Area (optional)', 'If this geofence is inside one of your mine areas, link them here for cleaner reports.'],
                        ['8', '💾', 'Click Save', 'Your geofence is now live! The system will immediately start detecting machines entering and leaving.'],
                    ] as [$num, $icon, $title, $desc])
                        <div class="flex gap-3 items-start">
                            <div class="w-8 h-8 rounded-full bg-warning text-warning-content flex items-center justify-center font-bold text-sm shrink-0 mt-1">{{ $num }}</div>
                            <div class="flex-1 bg-base-200 rounded-xl p-4 border border-base-300">
                                <p class="font-semibold flex items-center gap-2 mb-1"><span>{{ $icon }}</span> {{ $title }}</p>
                                <p class="text-sm text-base-content/70">{{ $desc }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mt-3 mb-6">
                    @foreach([
                        ['Loading Zone', 'success', '🪣', 'Where excavators load material into trucks. Tracks loading cycles automatically.'],
                        ['Dumping Zone', 'warning', '🪣', 'Where trucks tip their loads. Tracks dump cycles automatically.'],
                        ['Restricted', 'error', '🚫', 'No-entry zones. Instant alert if any machine enters.'],
                        ['Parking', 'secondary', '🅿️', 'End-of-shift parking areas. Tracks which machines are parked.'],
                        ['Maintenance', 'info', '🔧', 'Workshop or service bay area.'],
                        ['Safety', 'error', '⛑️', 'Special safety zones — like areas near blasting.'],
                    ] as [$type, $badge, $icon, $desc])
                        <div class="card bg-base-200 border border-base-300">
                            <div class="card-body p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <span>{{ $icon }}</span>
                                    <span class="badge badge-{{ $badge }}">{{ $type }}</span>
                                </div>
                                <p class="text-xs text-base-content/70">{{ $desc }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="prose max-w-none">
                    <h2>📱 What Happens When a Machine Crosses a Geofence?</h2>
                    <ol>
                        <li>The GPS tracker on the machine detects it has crossed the boundary</li>
                        <li>The platform logs an <strong>Entry Event</strong> or <strong>Exit Event</strong> with the exact time</li>
                        <li>The time the machine spent inside is calculated automatically</li>
                        <li>If it's a <strong>Restricted Zone</strong>, your Fleet Manager gets an instant alert (email + in-app notification)</li>
                        <li>All events are saved and can be viewed in the <strong>Reports</strong> section</li>
                    </ol>
                </div>
            @endif

            {{-- ============== MINE AREAS ============== --}}
            @if($activeSection === 'mine-areas')
                <div class="prose max-w-none">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-4xl">⛏️</span>
                        <h1 class="mb-0">Mine Areas — Organising Your Site</h1>
                    </div>
                    <p class="text-lg">Mine Areas are the named sections of your mine site. Think of them like rooms in a building — each one has a name, a purpose, and you can see what's happening in each one.</p>

                    <div class="alert alert-info not-prose mb-6">
                        <span class="text-xl">💡</span>
                        <span><strong>Example:</strong> You might have "Pit A", "Pit B North", "South Waste Dump", "Crushing Plant", and "Workshop". Each is a Mine Area. This makes questions like "How many tonnes came from Pit A this week?" easy to answer.</span>
                    </div>

                    <h2>How to Create a Mine Area</h2>
                </div>

                <div class="space-y-3 mt-4 mb-8">
                    @foreach([
                        ['1', '⛏️', 'Go to Mine Areas', 'Click "⛏️ Mine Areas" in the left sidebar.'],
                        ['2', '➕', 'Click "Create Area"', 'Find the "+ Create Area" button and click it.'],
                        ['3', '📝', 'Enter the area name', 'Use a clear name everyone will recognise. E.g. "Pit A", "North Waste Dump", "Crushing Plant".'],
                        ['4', '📋', 'Add a description (optional)', 'A short note about what happens in this area. E.g. "Primary production pit for gold-bearing ore."'],
                        ['5', '🏷️', 'Set the status', '"Active" means it\'s currently in use. "Planning" means it\'s being set up. "Closed" means no longer used.'],
                        ['6', '👤', 'Add a manager (optional)', 'Enter the name and contact of the person responsible for this area.'],
                        ['7', '🗺️', 'Draw the boundary on the map (optional)', 'Just like a geofence, you can outline this area on the map. You can skip this and add it later.'],
                        ['8', '💾', 'Click Save', 'Your mine area is ready! You can now assign machines to it and link geofences inside it.'],
                    ] as [$num, $icon, $title, $desc])
                        <div class="flex gap-3 items-start">
                            <div class="w-8 h-8 rounded-full bg-secondary text-secondary-content flex items-center justify-center font-bold text-sm shrink-0 mt-1">{{ $num }}</div>
                            <div class="flex-1 bg-base-200 rounded-xl p-4 border border-base-300">
                                <p class="font-semibold flex items-center gap-2 mb-1"><span>{{ $icon }}</span> {{ $title }}</p>
                                <p class="text-sm text-base-content/70">{{ $desc }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="prose max-w-none">
                    <h2>Assigning Machines to a Mine Area</h2>
                    <ol>
                        <li>Open the mine area (click on it from the Mine Areas list)</li>
                        <li>Click the <strong>"Machines"</strong> tab</li>
                        <li>Click <strong>"Assign Machine"</strong></li>
                        <li>Select the machine from the dropdown</li>
                        <li>Click Confirm — the machine is now assigned!</li>
                    </ol>
                    <div class="alert alert-info not-prose">
                        <span>💡</span>
                        <span>A machine can only be assigned to <strong>one mine area at a time</strong>. If you move a machine to a different area, the old assignment updates automatically.</span>
                    </div>
                </div>
            @endif

            {{-- ============== MAINTENANCE ============== --}}
            @if($activeSection === 'maintenance')
                <div class="prose max-w-none">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-4xl">🔧</span>
                        <h1 class="mb-0">Maintenance — Keeping Machines Running</h1>
                    </div>
                    <p class="text-lg">Regular maintenance is how you keep machines from breaking down. This section lets you schedule services, track work orders, and see which machines are due for a service.</p>

                    <div class="alert alert-success not-prose mb-6">
                        <span class="text-xl">🤖</span>
                        <span><strong>AI does most of the work!</strong> The AI monitors your machines constantly and predicts when they'll need service — before a breakdown happens. You'll get a notification automatically. See the <button wire:click="setSection('ai-maintenance')" class="underline font-bold cursor-pointer">AI Maintenance Guide</button> for more.</span>
                    </div>

                    <h2>🔧 Part 1: Scheduling a Maintenance Service</h2>
                    <p>This is how you book a machine in for a service — like a car's annual check-up.</p>
                </div>

                <div class="space-y-3 mt-4 mb-8">
                    @foreach([
                        ['1', '🔧', 'Go to Maintenance', 'Click "🔧 Maintenance" in the left sidebar.'],
                        ['2', '➕', 'Click "Create Work Order"', 'A work order is the official record of a maintenance job. Click the button to create one.'],
                        ['3', '🚛', 'Select the machine', 'Choose which machine needs the service from the dropdown list.'],
                        ['4', '🏷️', 'Choose the maintenance type', '"Preventive" = regular scheduled service. "Corrective" = fixing a breakdown. "Inspection" = routine check.'],
                        ['5', '📝', 'Give it a title', 'E.g. "500-Hour Engine Service", "Tyre Replacement". Be clear so your technicians know what to do.'],
                        ['6', '📅', 'Set the scheduled date', 'When should the work happen? Pick the date from the calendar.'],
                        ['7', '⚡', 'Set the priority', '"Low" = can wait. "Medium" = do this month. "High" = do this week. "Critical" = do immediately.'],
                        ['8', '👤', 'Assign to a technician (optional)', 'If you know who will do the work, you can assign it to them. They\'ll get a notification.'],
                        ['9', '💾', 'Click Save', 'The work order is created! The machine\'s status will automatically change to "Maintenance".'],
                    ] as [$num, $icon, $title, $desc])
                        <div class="flex gap-3 items-start">
                            <div class="w-8 h-8 rounded-full bg-info text-info-content flex items-center justify-center font-bold text-sm shrink-0 mt-1">{{ $num }}</div>
                            <div class="flex-1 bg-base-200 rounded-xl p-4 border border-base-300">
                                <p class="font-semibold flex items-center gap-2 mb-1"><span>{{ $icon }}</span> {{ $title }}</p>
                                <p class="text-sm text-base-content/70">{{ $desc }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="prose max-w-none">
                    <h2>✅ Part 2: Completing a Work Order</h2>
                    <p>When the maintenance is done, close the work order so the records are updated correctly.</p>
                    <ol>
                        <li>Go to <strong>🔧 Maintenance</strong></li>
                        <li>Find the work order in the list</li>
                        <li>Click on it to open it</li>
                        <li>Click <strong>"Mark In Progress"</strong> when you start work</li>
                        <li>When finished, click <strong>"Complete"</strong></li>
                        <li>Fill in hours of labour, parts used, and notes on what was done</li>
                        <li>Click <strong>Save</strong> — the machine's status changes back to "Idle" automatically</li>
                    </ol>

                    <div class="alert alert-warning not-prose">
                        <span>⚠️</span>
                        <span><strong>Important:</strong> Always complete work orders when maintenance is done! If you leave a work order as "Scheduled", the machine will show as being in maintenance even after it's back to work.</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4 mb-6">
                    @foreach([
                        ['Scheduled', 'neutral', '📅', 'Work is booked but hasn\'t started yet.'],
                        ['In Progress', 'warning', '🔧', 'Technician is actively working on the machine.'],
                        ['Completed', 'success', '✅', 'All work done. Machine is back in service.'],
                        ['Cancelled', 'error', '❌', 'Work was cancelled.'],
                    ] as [$status, $badge, $icon, $desc])
                        <div class="card bg-base-200 border border-base-300 text-center">
                            <div class="card-body p-4">
                                <div class="text-2xl mb-1">{{ $icon }}</div>
                                <span class="badge badge-{{ $badge }} mb-2 text-xs">{{ $status }}</span>
                                <p class="text-xs text-base-content/60">{{ $desc }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- ============== AI MAINTENANCE ============== --}}
            @if($activeSection === 'ai-maintenance')
                <div class="prose max-w-none">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-4xl">🤖</span>
                        <h1 class="mb-0">AI Maintenance — Let the AI Do the Work</h1>
                    </div>
                    <p class="text-lg">The AI in {{ config('app.name') }} watches your machines 24/7. It learns their patterns and predicts problems <em>before</em> they cause a breakdown — saving you time, money, and headaches.</p>

                    <div class="alert alert-success not-prose mb-6">
                        <span class="text-xl">🧠</span>
                        <span><strong>Nothing to turn on!</strong> The AI is active automatically from the moment you add your machines. It runs in the background all the time.</span>
                    </div>

                    <h2>What does the AI watch for?</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 mb-8">
                    @foreach([
                        ['🌡️', 'High Engine Temperature', 'If an engine runs hotter than normal, the AI flags it before it becomes a serious problem.'],
                        ['⛽', 'Abnormal Fuel Consumption', 'If a machine suddenly uses 30% more fuel than usual, the AI detects it immediately.'],
                        ['📳', 'Excessive Vibration', 'Unusual vibration patterns can mean worn parts or loose components — AI catches these early.'],
                        ['🔋', 'Battery Health', 'Monitors battery voltage and alerts you when it\'s time for a replacement.'],
                        ['💧', 'Hydraulic Issues', 'Tracks hydraulic pressure and temperature to catch leaks or pump problems.'],
                        ['⏱️', 'Hours-Based Predictions', 'Based on how many hours your machine has run, AI predicts when specific services will be due.'],
                        ['🔴', 'Fault Codes', 'When a machine\'s computer logs an error code, AI looks it up and explains what it means.'],
                        ['📉', 'Productivity Drops', 'If a machine is doing fewer loads per shift than usual, AI flags it as a potential mechanical issue.'],
                    ] as [$icon, $title, $desc])
                        <div class="card bg-base-200 border border-primary/20">
                            <div class="card-body p-4">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-2xl">{{ $icon }}</span>
                                    <p class="font-semibold">{{ $title }}</p>
                                </div>
                                <p class="text-sm text-base-content/70">{{ $desc }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="prose max-w-none">
                    <h2>🔔 What happens when the AI spots a problem?</h2>
                    <ol>
                        <li>The AI sends an <strong>in-app notification</strong> to your Fleet Manager and Admin</li>
                        <li>An <strong>email alert</strong> is also sent so nobody misses it</li>
                        <li>The alert appears in your <strong>🔔 Alerts</strong> section with full details</li>
                        <li>You can click <strong>"Schedule Maintenance"</strong> directly from the alert to create a work order in one click</li>
                    </ol>

                    <h2>How to Act on an AI Alert</h2>
                </div>

                <div class="space-y-3 mt-4 mb-8">
                    @foreach([
                        ['1', '🔔', 'Check your Alerts', 'AI alerts appear in the 🔔 Alerts section in the sidebar. Critical alerts also show as a banner on your dashboard.'],
                        ['2', '🔍', 'Read the alert details', 'Click on the alert to see exactly what the AI detected, which machine it affects, and how urgent it is.'],
                        ['3', '✅', 'Acknowledge the alert', 'Click "Acknowledge" to confirm you\'ve seen it. This stops it from re-notifying the same people.'],
                        ['4', '🔧', 'Create a work order if needed', 'If the AI recommends maintenance, click "Schedule Maintenance" directly from the alert. A work order is created automatically.'],
                        ['5', '✅', 'Resolve the alert', 'Once the maintenance is done, go back to the alert and click "Resolve". This closes the loop.'],
                    ] as [$num, $icon, $title, $desc])
                        <div class="flex gap-3 items-start">
                            <div class="w-8 h-8 rounded-full bg-primary text-primary-content flex items-center justify-center font-bold text-sm shrink-0 mt-1">{{ $num }}</div>
                            <div class="flex-1 bg-base-200 rounded-xl p-4 border border-base-300">
                                <p class="font-semibold flex items-center gap-2 mb-1"><span>{{ $icon }}</span> {{ $title }}</p>
                                <p class="text-sm text-base-content/70">{{ $desc }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                    @foreach([
                        ['🔴 Critical', 'error', 'Stop the machine now. Serious risk of failure. Requires immediate action.'],
                        ['🟠 High', 'warning', 'Act within 24 hours. Significant issue detected that will get worse.'],
                        ['🟡 Warning', 'warning', 'Plan action within the week. Early signs of a developing issue.'],
                        ['🔵 Info', 'info', 'No immediate action needed. Useful data for future planning.'],
                    ] as [$level, $badge, $desc])
                        <div class="card bg-base-200 border border-base-300">
                            <div class="card-body p-4 text-center">
                                <span class="badge badge-{{ $badge }} mb-2 text-xs font-bold">{{ $level }}</span>
                                <p class="text-xs text-base-content/70">{{ $desc }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="alert alert-info">
                    <span class="text-xl">🤖</span>
                    <div>
                        <p class="font-bold">The AI gets smarter over time</p>
                        <p class="text-sm">The more data it collects about your specific machines and conditions, the more accurate its predictions become. After 3–6 months, it will know your fleet better than anyone.</p>
                    </div>
                </div>
            @endif

            {{-- ============== DASHBOARD ============== --}}
            @if($activeSection === 'dashboard')
                <div class="prose max-w-none">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-4xl">📊</span>
                        <h1 class="mb-0">The Dashboard</h1>
                    </div>
                    <p class="text-lg">The Dashboard is the first thing you see when you log in. It gives you a quick snapshot of everything happening at your mine right now.</p>

                    <h2>What's on the Dashboard?</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 mb-8">
                    @foreach([
                        ['🟢 Active Machines', 'How many machines are currently running. Click to see which ones.'],
                        ['🔴 Critical Alerts', 'Any urgent problems that need your attention right now.'],
                        ['⛽ Fuel Levels', 'A quick view of your tank levels. Red means a tank is running low.'],
                        ['🔧 Maintenance Due', 'Machines that have a service coming up soon.'],
                        ['🗺️ Live Map', 'A mini-map showing where all your machines are at this moment.'],
                        ['📈 Performance', 'Key numbers for today, this week, or this month.'],
                    ] as [$t, $d])
                        <div class="card bg-base-200 border border-base-300">
                            <div class="card-body p-4">
                                <p class="font-semibold mb-1">{{ $t }}</p>
                                <p class="text-sm text-base-content/70">{{ $d }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="alert alert-info">
                    <span>💡</span>
                    <span><strong>Quick Tip:</strong> The dashboard updates automatically. You don't need to refresh the page — data arrives in real-time.</span>
                </div>
            @endif

            {{-- ============== LIVE MAP ============== --}}
            @if($activeSection === 'live-map')
                <div class="prose max-w-none">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-4xl">🗺️</span>
                        <h1 class="mb-0">Live Map</h1>
                    </div>
                    <p>The live map shows you exactly where every machine is, right now. It updates every few seconds automatically.</p>

                    <h2>How to use the map</h2>
                    <ul>
                        <li><strong>Zoom in/out:</strong> Scroll your mouse wheel, or use the + / – buttons on the map</li>
                        <li><strong>Move around:</strong> Click and drag to pan the map</li>
                        <li><strong>Click a machine marker:</strong> See that machine's name, status, speed, and last update time</li>
                        <li><strong>Click "View Details":</strong> Jump straight to that machine's full profile page</li>
                    </ul>

                    <h2>Machine Marker Colours</h2>
                    <ul>
                        <li>🟢 <strong>Green marker:</strong> Machine is active and moving</li>
                        <li>🟡 <strong>Yellow marker:</strong> Machine is idle (engine on, not moving)</li>
                        <li>🔵 <strong>Blue marker:</strong> Machine is in for maintenance</li>
                        <li>🔴 <strong>Red marker:</strong> Machine is offline or has an alert</li>
                    </ul>

                    <h2>Geofence Overlays</h2>
                    <p>Your geofences are drawn on the map as coloured shapes. This makes it easy to see which machines are in which zones at a glance.</p>

                    <div class="alert alert-info not-prose">
                        <span>💡</span>
                        <span><strong>Pro Tip:</strong> Use the filter buttons at the top of the map to show only certain machine types or statuses.</span>
                    </div>
                </div>
            @endif

            {{-- ============== MACHINE TRACKING ============== --}}
            @if($activeSection === 'machine-tracking')
                <div class="prose max-w-none">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-4xl">📍</span>
                        <h1 class="mb-0">Tracking Your Machines</h1>
                    </div>
                    <p>Every machine with a GPS tracker sends its location to the platform. Here's how to use that data.</p>

                    <h2>Viewing a Machine's Location History</h2>
                    <ol>
                        <li>Go to <strong>Fleet</strong> and click on a machine</li>
                        <li>Click the <strong>"Location History"</strong> tab</li>
                        <li>Choose a date range (today, yesterday, last 7 days, or custom)</li>
                        <li>The map shows the machine's full route as a trail of dots</li>
                        <li>Click any dot to see the exact time and speed at that point</li>
                    </ol>

                    <h2>Playback Mode</h2>
                    <p>You can replay a machine's entire day like watching a video:</p>
                    <ol>
                        <li>Select the date you want to review</li>
                        <li>Click the <strong>▶ Play</strong> button</li>
                        <li>Watch the machine move on the map through the day</li>
                        <li>Use the speed control to watch in fast-forward (2×, 5×, 10×)</li>
                        <li>Pause at any point to check details</li>
                    </ol>

                    <div class="alert alert-success not-prose">
                        <span>💡</span>
                        <span>Location history is useful for investigating incidents, verifying productivity claims, and understanding why fuel usage was high on a particular day.</span>
                    </div>
                </div>
            @endif

            {{-- ============== REPORTS ============== --}}
            @if($activeSection === 'reports')
                <div class="prose max-w-none">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-4xl">📈</span>
                        <h1 class="mb-0">Reports</h1>
                    </div>
                    <p class="text-lg">Generate professional reports for management, compliance, or analysis. One click and you have a PDF or spreadsheet ready.</p>

                    <h2>How to Generate a Report</h2>
                    <ol>
                        <li>Click <strong>📈 Reports</strong> in the sidebar</li>
                        <li>Choose the report type (Fuel, Fleet, Maintenance, Geofence)</li>
                        <li>Select your date range</li>
                        <li>Apply any filters (specific machines, areas, etc.)</li>
                        <li>Click <strong>"Generate Report"</strong></li>
                        <li>Wait a few seconds while it processes</li>
                        <li>Download as <strong>PDF</strong> or <strong>CSV</strong></li>
                    </ol>

                    <h2>Available Reports</h2>
                    <ul>
                        <li>⛽ <strong>Fuel Consumption Report:</strong> How much fuel each machine used, and what it cost</li>
                        <li>🚛 <strong>Fleet Utilisation Report:</strong> Which machines were active vs. idle vs. offline</li>
                        <li>🔧 <strong>Maintenance Cost Report:</strong> Labour and parts costs per machine</li>
                        <li>🔶 <strong>Geofence Activity Report:</strong> How many times machines entered/exited each zone</li>
                        <li>📊 <strong>Shift Report:</strong> Daily summary of production activity</li>
                    </ul>

                    <div class="alert alert-info not-prose">
                        <span>💡</span>
                        <span><strong>Automated Reports:</strong> You can schedule reports to be automatically emailed to your management team every day, week, or month. Contact support to set this up.</span>
                    </div>
                </div>
            @endif

            {{-- ============== ALERTS ============== --}}
            @if($activeSection === 'alerts')
                <div class="prose max-w-none">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-4xl">🔔</span>
                        <h1 class="mb-0">Alerts &amp; Notifications</h1>
                    </div>
                    <p class="text-lg">Alerts are your early warning system. They tell you about problems before they become expensive breakdowns or dangerous situations.</p>

                    <h2>Types of Alerts</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 mb-8">
                    @foreach([
                        ['🔴', 'Critical — Act Now', 'error', 'Machine breakdown, geofence breach into restricted zone, fuel theft detected. Stop what you\'re doing and deal with this.'],
                        ['🟠', 'High — Act Today', 'warning', 'Significant issue that will get worse. Examples: engine temp running hot, brake system warning, tank critically low.'],
                        ['🟡', 'Medium — Act This Week', 'warning', 'Something to monitor. Examples: machine idle too long, fuel efficiency dropping, maintenance coming due.'],
                        ['🔵', 'Info — Note Only', 'info', 'General information. Examples: machine entered geofence, shift handover, scheduled maintenance reminder.'],
                    ] as [$icon, $title, $badge, $desc])
                        <div class="card bg-base-200 border border-base-300">
                            <div class="card-body p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-2xl">{{ $icon }}</span>
                                    <span class="badge badge-{{ $badge }} font-bold">{{ $title }}</span>
                                </div>
                                <p class="text-sm text-base-content/70">{{ $desc }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="prose max-w-none">
                    <h2>What to do with an Alert</h2>
                    <ol>
                        <li>Click on the alert to read the full details</li>
                        <li>Click <strong>"Acknowledge"</strong> to confirm you've seen it (stops repeat notifications)</li>
                        <li>Take action — fix the problem or schedule maintenance</li>
                        <li>Click <strong>"Resolve"</strong> when the issue is fixed</li>
                    </ol>

                    <div class="alert alert-warning not-prose">
                        <span>⚠️</span>
                        <span>Don't ignore unresolved alerts! Old unresolved alerts make it hard to see new important ones. Make it a habit to resolve alerts once they're dealt with.</span>
                    </div>
                </div>
            @endif

            {{-- ============== INTEGRATIONS ============== --}}
            @if($activeSection === 'integrations-overview')
                <div class="prose max-w-none">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-4xl">🔌</span>
                        <h1 class="mb-0">Integrations</h1>
                    </div>
                    <p>Connect {{ config('app.name') }} with your existing systems to avoid double data entry and get a complete picture of your operations.</p>

                    <h2>Common Integrations</h2>
                    <ul>
                        <li>🛰️ <strong>GPS Devices:</strong> Automatically receive machine location from tracking hardware</li>
                        <li>⛽ <strong>Fuel Systems:</strong> Automatic fuel transaction recording from dispensing systems</li>
                        <li>🏭 <strong>OEM Telematics:</strong> Direct data from machine manufacturers (CAT, Komatsu, Bell, etc.)</li>
                        <li>📊 <strong>ERP Systems:</strong> Sync work orders and costs with SAP, Oracle, or Dynamics</li>
                    </ul>

                    <h2>Setting Up an Integration</h2>
                    <ol>
                        <li>Go to <strong>🔌 Integrations</strong> in the sidebar</li>
                        <li>Click <strong>"Add Integration"</strong></li>
                        <li>Choose your system type</li>
                        <li>Enter your API credentials (your IT team or system vendor will have these)</li>
                        <li>Click <strong>"Test Connection"</strong></li>
                        <li>If it says "Connected ✅", click <strong>Save &amp; Activate</strong></li>
                    </ol>

                    <div class="alert alert-info not-prose">
                        <span>💡</span>
                        <span>Not sure which integration to set up first? Contact our support team — we'll guide you through connecting your specific hardware and systems.</span>
                    </div>
                </div>
            @endif

            {{-- ============== API ACCESS ============== --}}
            @if($activeSection === 'api-access')
                <div class="prose max-w-none">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-4xl">💻</span>
                        <h1 class="mb-0">API Access</h1>
                    </div>
                    <p>The API lets your own software systems talk to {{ config('app.name') }} automatically. This is for technical users and developers.</p>

                    <h2>Getting an API Token</h2>
                    <ol>
                        <li>Go to <strong>⚙️ Settings</strong></li>
                        <li>Click <strong>"API Tokens"</strong></li>
                        <li>Click <strong>"Create New Token"</strong></li>
                        <li>Give it a descriptive name (e.g. "GPS Integration" or "Reporting System")</li>
                        <li>Select what permissions this token needs</li>
                        <li>Click Create and <strong>copy the token immediately</strong> — it's only shown once!</li>
                    </ol>

                    <div class="alert alert-error not-prose">
                        <span>🔐</span>
                        <span><strong>Security warning:</strong> API tokens are like passwords. Never share them in emails or put them in public code repositories. If a token is compromised, delete it immediately and create a new one.</span>
                    </div>

                    <h2>Common Endpoints</h2>
                    <div class="mockup-code not-prose text-sm">
                        <pre data-prefix="GET"><code>  /api/machines              — List all machines</code></pre>
                        <pre data-prefix="GET"><code>  /api/machines/{id}         — Get one machine</code></pre>
                        <pre data-prefix="POST"><code> /api/machines              — Create a machine</code></pre>
                        <pre data-prefix="GET"><code>  /api/fuel/tanks            — List fuel tanks</code></pre>
                        <pre data-prefix="POST"><code> /api/fuel/transactions     — Record fuel transaction</code></pre>
                        <pre data-prefix="GET"><code>  /api/maintenance/records   — List work orders</code></pre>
                    </div>
                </div>
            @endif

        </main>
    </div>
</div>
