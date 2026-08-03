<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Mines — Mining Fleet Management Platform</title>
        <meta name="description" content="Enterprise-grade fleet management for mining operations. Real-time tracking, maintenance scheduling, fuel management, and production analytics.">
        
        <!-- Favicon -->
        <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' fill='%23f59e0b' rx='15'/><path d='M20 45 L20 30 L35 37 L35 52 L20 45 M35 52 L50 45 L50 60 L35 67 L35 52 M50 60 L65 53 L65 68 L50 75 L50 60 M35 37 L50 30 L50 45 L35 52 L35 37 M50 45 L65 38 L65 53 L50 60 L50 45 M50 30 L65 23 L80 30 L65 38 L50 30' fill='%231e293b' stroke='%231e293b' stroke-width='2'/></svg>">
        
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gray-900 text-gray-100 antialiased">
        
        <!-- Header -->
        <header class="fixed top-0 left-0 right-0 z-50 transition-all duration-200" x-data="{ scrolled: false, mobileMenuOpen: false }" 
                @scroll.window="scrolled = window.pageYOffset > 50"
                :class="scrolled ? 'bg-gray-900 shadow-md border-b border-gray-800' : 'bg-transparent'">
            <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between">
                    <!-- Logo -->
                    <a href="{{ route('home') }}" class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-amber-500 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-white leading-none">Mines</h1>
                            <p class="text-xs text-gray-400 mt-0.5">Fleet Management</p>
                        </div>
                    </a>

                    <!-- Desktop Navigation -->
                    <div class="hidden md:flex items-center gap-8">
                        <a href="#features" class="text-gray-400 hover:text-white transition-colors text-sm font-medium">Features</a>
                        <a href="#capabilities" class="text-gray-400 hover:text-white transition-colors text-sm font-medium">Capabilities</a>
                        <a href="#pricing" class="text-gray-400 hover:text-white transition-colors text-sm font-medium">Pricing</a>
                    </div>

                    <!-- Auth Links -->
                    @if (Route::has('login'))
                        <div class="flex items-center gap-3">
                            @auth
                                <a href="{{ route('dashboard') }}" class="hidden sm:flex items-center gap-2 px-5 py-2 bg-amber-500 hover:bg-amber-600 text-gray-900 font-semibold rounded-lg transition-colors text-sm">
                                    <span>Go to Dashboard</span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                    </svg>
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="hidden sm:block px-4 py-2 text-gray-400 hover:text-white transition-colors text-sm font-medium">
                                    Sign In
                                </a>
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="flex items-center gap-2 px-5 py-2 bg-amber-500 hover:bg-amber-600 text-gray-900 font-semibold rounded-lg transition-colors text-sm">
                                        <span>Get Started</span>
                                    </a>
                                @endif
                            @endauth
                            
                            <!-- Mobile menu button -->
                            <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden p-2 text-gray-400 hover:text-white">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path x-show="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                                    <path x-show="mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    @endif
                </div>
                
                <!-- Mobile Menu -->
                <div x-show="mobileMenuOpen" 
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="md:hidden mt-4 py-4 border-t border-gray-800"
                     style="display: none;">
                    <div class="flex flex-col gap-1">
                        <a href="#features" class="px-3 py-2 text-gray-400 hover:text-white hover:bg-gray-800 rounded-md transition-colors text-sm">Features</a>
                        <a href="#capabilities" class="px-3 py-2 text-gray-400 hover:text-white hover:bg-gray-800 rounded-md transition-colors text-sm">Capabilities</a>
                        @guest
                            <a href="{{ route('login') }}" class="px-3 py-2 text-gray-400 hover:text-white hover:bg-gray-800 rounded-md transition-colors text-sm">Sign In</a>
                        @endguest
                    </div>
                </div>
            </nav>
        </header>

        <!-- Hero Section -->
        <section class="relative min-h-screen flex items-center justify-center pt-20 bg-gray-900">
            <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZGVmcz48cGF0dGVybiBpZD0iZ3JpZCIgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiBwYXR0ZXJuVW5pdHM9InVzZXJTcGFjZU9uVXNlIj48cGF0aCBkPSJNIDQwIDAgTCAwIDAgMCA0MCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSJyZ2JhKDI1NSwyNTUsMjU1LDAuMDMpIiBzdHJva2Utd2lkdGg9IjEiLz48L3BhdHRlcm4+PC9kZWZzPjxyZWN0IHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiIGZpbGw9InVybCgjZ3JpZCkiLz48L3N2Zz4=')] opacity-50"></div>
            
            <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
                <div class="grid lg:grid-cols-2 gap-16 items-center">
                    <!-- Left Column -->
                    <div class="space-y-8">
                        <div>
                            <p class="text-amber-500 text-xs font-semibold uppercase tracking-widest mb-4">Mining Fleet Management</p>
                            <h2 class="text-5xl lg:text-6xl font-bold leading-tight text-white">
                                Operational visibility<br>for mining fleets
                            </h2>
                        </div>
                        
                        <p class="text-lg text-gray-400 leading-relaxed max-w-xl">
                            Real-time fleet tracking, predictive maintenance, fuel monitoring, and production analytics — built for the operational demands of modern mining.
                        </p>

                        <!-- Key Stats -->
                        <div class="grid grid-cols-3 gap-6 py-6 border-t border-b border-gray-800">
                            <div>
                                <div class="text-2xl font-bold text-white">24/7</div>
                                <div class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Live Monitoring</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-white">99.9%</div>
                                <div class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Uptime SLA</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-white">&lt;40ms</div>
                                <div class="text-xs text-gray-500 mt-1 uppercase tracking-wide">Response Time</div>
                            </div>
                        </div>

                        @guest
                            <div class="flex flex-wrap gap-4">
                                <a href="{{ route('register') }}" class="flex items-center gap-2 px-7 py-3 bg-amber-500 hover:bg-amber-600 text-gray-900 font-bold rounded-lg transition-colors text-sm">
                                    <span>Start Free Trial</span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                    </svg>
                                </a>
                                <a href="#features" class="flex items-center gap-2 px-7 py-3 bg-gray-800 hover:bg-gray-700 text-white font-semibold rounded-lg transition-colors border border-gray-700 text-sm">
                                    <span>View Features</span>
                                </a>
                            </div>
                            <p class="text-xs text-gray-600">No credit card required &mdash; 14-day free trial</p>
                        @endguest
                    </div>

                    <!-- Right Column - Dashboard Preview -->
                    <div class="hidden lg:block">
                        <div class="bg-gray-800 rounded-xl border border-gray-700 shadow-2xl overflow-hidden">
                            <div class="p-5">
                                <!-- Dashboard header -->
                                <div class="flex items-center justify-between mb-5">
                                    <h3 class="text-sm font-semibold text-white">Fleet Dashboard</h3>
                                    <div class="flex items-center gap-1.5 text-xs text-green-400">
                                        <span class="w-1.5 h-1.5 bg-green-400 rounded-full"></span>
                                        <span>Live</span>
                                    </div>
                                </div>
                                
                                <!-- Stats Grid -->
                                <div class="grid grid-cols-2 gap-3 mb-5">
                                    <div class="bg-gray-900 rounded-lg p-4 border border-gray-700">
                                        <p class="text-xs text-gray-500 mb-2">Active Fleet</p>
                                        <p class="text-2xl font-bold text-white">47</p>
                                        <p class="text-xs text-green-400 mt-1">↑ 12% this week</p>
                                    </div>
                                    <div class="bg-gray-900 rounded-lg p-4 border border-gray-700">
                                        <p class="text-xs text-gray-500 mb-2">Fleet Efficiency</p>
                                        <p class="text-2xl font-bold text-white">94.2%</p>
                                        <p class="text-xs text-amber-400 mt-1">Target: 90%</p>
                                    </div>
                                    <div class="bg-gray-900 rounded-lg p-4 border border-gray-700">
                                        <p class="text-xs text-gray-500 mb-2">Production Today</p>
                                        <p class="text-2xl font-bold text-white">2,400 t</p>
                                        <p class="text-xs text-gray-500 mt-1">BCM recorded</p>
                                    </div>
                                    <div class="bg-gray-900 rounded-lg p-4 border border-gray-700">
                                        <p class="text-xs text-gray-500 mb-2">Active Alerts</p>
                                        <p class="text-2xl font-bold text-white">3</p>
                                        <p class="text-xs text-red-400 mt-1">Requires attention</p>
                                    </div>
                                </div>

                                <!-- Mini Chart -->
                                <div class="bg-gray-900 rounded-lg p-4 border border-gray-700">
                                    <p class="text-xs text-gray-500 font-medium mb-3">Weekly Performance</p>
                                    <div class="flex items-end justify-between h-20 gap-1.5">
                                        <div class="bg-amber-500/50 rounded-sm w-full" style="height: 45%"></div>
                                        <div class="bg-amber-500/50 rounded-sm w-full" style="height: 62%"></div>
                                        <div class="bg-amber-500/50 rounded-sm w-full" style="height: 55%"></div>
                                        <div class="bg-amber-500/50 rounded-sm w-full" style="height: 78%"></div>
                                        <div class="bg-amber-500/50 rounded-sm w-full" style="height: 88%"></div>
                                        <div class="bg-amber-500/50 rounded-sm w-full" style="height: 95%"></div>
                                        <div class="bg-amber-500 rounded-sm w-full" style="height: 100%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="py-24 px-4 sm:px-6 lg:px-8 bg-gray-950">
            <div class="max-w-7xl mx-auto">
                <div class="mb-14">
                    <p class="text-amber-500 text-xs font-semibold uppercase tracking-widest mb-3">Platform Features</p>
                    <h2 class="text-3xl lg:text-4xl font-bold text-white">
                        Everything you need to manage your fleet
                    </h2>
                    <p class="text-gray-400 mt-4 max-w-2xl text-sm leading-relaxed">
                        Purpose-built tools for mining operations — from individual machines to multi-site fleets.
                    </p>
                </div>

                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <!-- Feature 1 -->
                    <div class="bg-gray-800 p-7 rounded-xl border border-gray-700 hover:border-gray-600 transition-colors">
                        <div class="w-9 h-9 bg-blue-500/10 border border-blue-500/20 rounded-lg flex items-center justify-center mb-5">
                            <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-sm font-bold text-white mb-2">Live Fleet Tracking</h3>
                        <p class="text-sm text-gray-400 leading-relaxed">Real-time GPS tracking with interactive maps, geofence monitoring, route history, and location analytics.</p>
                    </div>

                    <!-- Feature 2 -->
                    <div class="bg-gray-800 p-7 rounded-xl border border-gray-700 hover:border-gray-600 transition-colors">
                        <div class="w-9 h-9 bg-purple-500/10 border border-purple-500/20 rounded-lg flex items-center justify-center mb-5">
                            <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-sm font-bold text-white mb-2">Predictive Analytics</h3>
                        <p class="text-sm text-gray-400 leading-relaxed">Data-driven insights for productivity optimisation, predictive maintenance, and operational efficiency improvements.</p>
                    </div>

                    <!-- Feature 3 -->
                    <div class="bg-gray-800 p-7 rounded-xl border border-gray-700 hover:border-gray-600 transition-colors">
                        <div class="w-9 h-9 bg-amber-500/10 border border-amber-500/20 rounded-lg flex items-center justify-center mb-5">
                            <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <h3 class="text-sm font-bold text-white mb-2">Fuel Management</h3>
                        <p class="text-sm text-gray-400 leading-relaxed">Track consumption, monitor costs, configure threshold alerts, and analyse fuel efficiency across the entire fleet.</p>
                    </div>

                    <!-- Feature 4 -->
                    <div class="bg-gray-800 p-7 rounded-xl border border-gray-700 hover:border-gray-600 transition-colors">
                        <div class="w-9 h-9 bg-green-500/10 border border-green-500/20 rounded-lg flex items-center justify-center mb-5">
                            <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-sm font-bold text-white mb-2">Maintenance Scheduling</h3>
                        <p class="text-sm text-gray-400 leading-relaxed">Hour- and calendar-based maintenance reminders, service history tracking, and preventive maintenance workflows.</p>
                    </div>

                    <!-- Feature 5 -->
                    <div class="bg-gray-800 p-7 rounded-xl border border-gray-700 hover:border-gray-600 transition-colors">
                        <div class="w-9 h-9 bg-red-500/10 border border-red-500/20 rounded-lg flex items-center justify-center mb-5">
                            <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                        </div>
                        <h3 class="text-sm font-bold text-white mb-2">Operational Alerts</h3>
                        <p class="text-sm text-gray-400 leading-relaxed">Configurable notifications for maintenance, geofence breaches, sensor anomalies, and critical operational events.</p>
                    </div>

                    <!-- Feature 6 -->
                    <div class="bg-gray-800 p-7 rounded-xl border border-gray-700 hover:border-gray-600 transition-colors">
                        <div class="w-9 h-9 bg-indigo-500/10 border border-indigo-500/20 rounded-lg flex items-center justify-center mb-5">
                            <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-sm font-bold text-white mb-2">Reporting & Export</h3>
                        <p class="text-sm text-gray-400 leading-relaxed">Generate detailed reports for any period, export to PDF or Excel, and schedule automated delivery to stakeholders.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Capabilities Section -->
        <section id="capabilities" class="py-24 px-4 sm:px-6 lg:px-8 bg-gray-900">
            <div class="max-w-7xl mx-auto">
                <div class="mb-14">
                    <p class="text-amber-500 text-xs font-semibold uppercase tracking-widest mb-3">Platform Capabilities</p>
                    <h2 class="text-3xl lg:text-4xl font-bold text-white">
                        Built for large-scale mining operations
                    </h2>
                    <p class="text-gray-400 mt-4 max-w-2xl text-sm leading-relaxed">
                        Infrastructure and tooling designed to handle operational complexity across multiple sites and large fleets.
                    </p>
                </div>

                <div class="grid lg:grid-cols-2 gap-5">
                    <!-- Left Column -->
                    <div class="space-y-4">
                        <div class="bg-gray-800 p-7 rounded-xl border border-gray-700">
                            <div class="flex items-start gap-4">
                                <div class="w-9 h-9 bg-blue-500/10 border border-blue-500/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-white mb-2">Cloud Infrastructure</h3>
                                    <p class="text-sm text-gray-400 leading-relaxed">Secure cloud hosting with no on-premise hardware required. Automatic backups and 99.9% uptime SLA.</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-800 p-7 rounded-xl border border-gray-700">
                            <div class="flex items-start gap-4">
                                <div class="w-9 h-9 bg-purple-500/10 border border-purple-500/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-white mb-2">Multi-Site Support</h3>
                                    <p class="text-sm text-gray-400 leading-relaxed">Manage multiple mining sites from a single dashboard with team-based access control and data isolation.</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-800 p-7 rounded-xl border border-gray-700">
                            <div class="flex items-start gap-4">
                                <div class="w-9 h-9 bg-emerald-500/10 border border-emerald-500/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-white mb-2">Audit & Compliance</h3>
                                    <p class="text-sm text-gray-400 leading-relaxed">Full audit trails for all activities, compliance reporting, safety incident tracking, and operational logs.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-4">
                        <div class="bg-gray-800 p-7 rounded-xl border border-gray-700">
                            <div class="flex items-start gap-4">
                                <div class="w-9 h-9 bg-orange-500/10 border border-orange-500/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-white mb-2">Data Retention</h3>
                                    <p class="text-sm text-gray-400 leading-relaxed">Long-term storage with no capacity limits. Full historical data for compliance requirements and trend analysis.</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-800 p-7 rounded-xl border border-gray-700">
                            <div class="flex items-start gap-4">
                                <div class="w-9 h-9 bg-pink-500/10 border border-pink-500/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-white mb-2">24/7 Support</h3>
                                    <p class="text-sm text-gray-400 leading-relaxed">Dedicated support team available at all hours. Onboarding, training, and ongoing operational assistance included.</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-800 p-7 rounded-xl border border-gray-700">
                            <div class="flex items-start gap-4">
                                <div class="w-9 h-9 bg-cyan-500/10 border border-cyan-500/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-white mb-2">Custom Dashboards</h3>
                                    <p class="text-sm text-gray-400 leading-relaxed">Build dashboards and reports tailored to your operational KPIs. Export in multiple formats for external analysis.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-24 px-4 sm:px-6 lg:px-8 bg-gray-950 border-t border-gray-800">
            <div class="max-w-3xl mx-auto text-center">
                <h2 class="text-3xl lg:text-4xl font-bold text-white mb-4">
                    Ready to get started?
                </h2>
                <p class="text-gray-400 mb-10">
                    Set up your fleet and start monitoring operations within minutes.
                </p>
                
                @guest
                    <div class="flex flex-wrap justify-center gap-4 mb-6">
                        <a href="{{ route('register') }}" class="flex items-center gap-2 px-8 py-3 bg-amber-500 hover:bg-amber-600 text-gray-900 font-bold rounded-lg transition-colors text-sm">
                            <span>Start 14-Day Free Trial</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                        </a>
                        <a href="{{ route('login') }}" class="flex items-center gap-2 px-8 py-3 bg-gray-800 hover:bg-gray-700 text-white font-semibold rounded-lg transition-colors border border-gray-700 text-sm">
                            <span>Sign In</span>
                        </a>
                    </div>
                    <p class="text-xs text-gray-600">No credit card required &mdash; full feature access &mdash; cancel anytime</p>
                @endguest
            </div>
        </section>

        <!-- Footer -->
        <footer class="py-10 px-4 sm:px-6 lg:px-8 border-t border-gray-800 bg-gray-900">
            <div class="max-w-7xl mx-auto">
                <div class="grid md:grid-cols-4 gap-8 mb-8">
                    <!-- Brand -->
                    <div class="col-span-1">
                        <div class="flex items-center gap-2.5 mb-4">
                            <div class="w-8 h-8 bg-amber-500 rounded-md flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                            </div>
                            <span class="text-base font-bold text-white">Mines</span>
                        </div>
                        <p class="text-gray-500 text-sm leading-relaxed">
                            Fleet management for mining operations.
                        </p>
                    </div>

                    <!-- Links -->
                    <div>
                        <h3 class="text-white text-sm font-semibold mb-4">Product</h3>
                        <ul class="space-y-2 text-sm">
                            <li><a href="#features" class="text-gray-500 hover:text-gray-300 transition-colors">Features</a></li>
                            <li><a href="#capabilities" class="text-gray-500 hover:text-gray-300 transition-colors">Capabilities</a></li>
                            <li><a href="#pricing" class="text-gray-500 hover:text-gray-300 transition-colors">Pricing</a></li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="text-white text-sm font-semibold mb-4">Company</h3>
                        <ul class="space-y-2 text-sm">
                            <li><a href="#" class="text-gray-500 hover:text-gray-300 transition-colors">About</a></li>
                            <li><a href="#" class="text-gray-500 hover:text-gray-300 transition-colors">Contact</a></li>
                            <li><a href="#" class="text-gray-500 hover:text-gray-300 transition-colors">Careers</a></li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="text-white text-sm font-semibold mb-4">Legal</h3>
                        <ul class="space-y-2 text-sm">
                            <li><a href="#" class="text-gray-500 hover:text-gray-300 transition-colors">Privacy Policy</a></li>
                            <li><a href="#" class="text-gray-500 hover:text-gray-300 transition-colors">Terms of Service</a></li>
                            <li><a href="#" class="text-gray-500 hover:text-gray-300 transition-colors">Cookie Policy</a></li>
                        </ul>
                    </div>
                </div>

                <div class="pt-6 border-t border-gray-800 flex flex-col md:flex-row items-center justify-between gap-4">
                    <p class="text-gray-600 text-xs">
                        &copy; {{ date('Y') }} Mines. All rights reserved.
                    </p>
                    <div class="flex items-center gap-4">
                        <a href="#" class="text-gray-600 hover:text-gray-400 transition-colors">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                        </a>
                        <a href="#" class="text-gray-600 hover:text-gray-400 transition-colors">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </footer>

    </body>
</html>
