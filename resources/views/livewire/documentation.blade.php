<div class="min-h-screen bg-base-100">
    <div class="flex">
        <!-- Sidebar Navigation -->
        <div class="w-64 bg-base-200 min-h-screen p-4 sticky top-0 overflow-y-auto" style="max-height: 100vh;">
            <h2 class="text-2xl font-bold mb-6">Documentation</h2>
            
            <div class="menu">
                <li class="menu-title">Getting Started</li>
                <ul>
                    <li><a wire:click="setSection('getting-started')" class="{{ $activeSection === 'getting-started' ? 'active' : '' }}">Overview</a></li>
                    <li><a wire:click="setSection('quick-start')" class="{{ $activeSection === 'quick-start' ? 'active' : '' }}">Quick Start Guide</a></li>
                    <li><a wire:click="setSection('dashboard')" class="{{ $activeSection === 'dashboard' ? 'active' : '' }}">Dashboard</a></li>
                </ul>

                <li class="menu-title mt-4">Fleet Management</li>
                <ul>
                    <li><a wire:click="setSection('fleet')" class="{{ $activeSection === 'fleet' ? 'active' : '' }}">Fleet Overview</a></li>
                    <li><a wire:click="setSection('machine-tracking')" class="{{ $activeSection === 'machine-tracking' ? 'active' : '' }}">Machine Tracking</a></li>
                    <li><a wire:click="setSection('live-map')" class="{{ $activeSection === 'live-map' ? 'active' : '' }}">Live Map</a></li>
                </ul>

                <li class="menu-title mt-4">Operations</li>
                <ul>
                    <li><a wire:click="setSection('geofences')" class="{{ $activeSection === 'geofences' ? 'active' : '' }}">Geofences</a></li>
                    <li><a wire:click="setSection('mine-areas')" class="{{ $activeSection === 'mine-areas' ? 'active' : '' }}">Mine Areas</a></li>
                    <li><a wire:click="setSection('fuel-management')" class="{{ $activeSection === 'fuel-management' ? 'active' : '' }}">Fuel Management</a></li>
                    <li><a wire:click="setSection('maintenance')" class="{{ $activeSection === 'maintenance' ? 'active' : '' }}">Maintenance</a></li>
                </ul>

                <li class="menu-title mt-4">Analytics & Reports</li>
                <ul>
                    <li><a wire:click="setSection('reports')" class="{{ $activeSection === 'reports' ? 'active' : '' }}">Reports</a></li>
                    <li><a wire:click="setSection('alerts')" class="{{ $activeSection === 'alerts' ? 'active' : '' }}">Alerts</a></li>
                </ul>

                <li class="menu-title mt-4">Integrations</li>
                <ul>
                    <li><a wire:click="setSection('integrations-overview')" class="{{ $activeSection === 'integrations-overview' ? 'active' : '' }}">Overview</a></li>
                    <li><a wire:click="setSection('api-access')" class="{{ $activeSection === 'api-access' ? 'active' : '' }}">API Access</a></li>
                    <li><a wire:click="setSection('webhooks')" class="{{ $activeSection === 'webhooks' ? 'active' : '' }}">Webhooks</a></li>
                </ul>

                <li class="menu-title mt-4">Administration</li>
                <ul>
                    <li><a wire:click="setSection('team-management')" class="{{ $activeSection === 'team-management' ? 'active' : '' }}">Team Management</a></li>
                    <li><a wire:click="setSection('user-roles')" class="{{ $activeSection === 'user-roles' ? 'active' : '' }}">User Roles</a></li>
                    <li><a wire:click="setSection('settings')" class="{{ $activeSection === 'settings' ? 'active' : '' }}">Settings</a></li>
                </ul>

                <li class="menu-title mt-4">Engineering</li>
                <ul>
                    <li><a wire:click="setSection('engineering-reports')" class="{{ $activeSection === 'engineering-reports' ? 'active' : '' }}">Engineering Reports</a></li>
                </ul>
            </div>
        </div>

        <!-- Content Area -->
        <div class="flex-1 p-8 max-w-5xl">
            @if($activeSection === 'getting-started')
                <div class="prose prose-invert max-w-none">
                    <h1>Welcome to Mines Fleet Manager</h1>
                    <p class="lead">Mines is a comprehensive fleet management platform designed specifically for mining operations. Track your equipment in real-time, manage fuel consumption, schedule maintenance, and optimize your fleet operations.</p>

                    <div class="alert alert-info mt-6">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>This documentation is organized by feature. Use the sidebar navigation to jump to specific topics.</span>
                    </div>

                    <h2>Key Features</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 not-prose">
                        <div class="card bg-base-200">
                            <div class="card-body">
                                <h3 class="card-title">Real-Time Tracking</h3>
                                <p>Monitor your entire fleet on a live map with GPS tracking, speed monitoring, and location history.</p>
                            </div>
                        </div>
                        <div class="card bg-base-200">
                            <div class="card-body">
                                <h3 class="card-title">Fuel Management</h3>
                                <p>Track fuel consumption, manage tanks, record transactions, and analyze fuel efficiency across your fleet.</p>
                            </div>
                        </div>
                        <div class="card bg-base-200">
                            <div class="card-body">
                                <h3 class="card-title">Maintenance Tracking</h3>
                                <p>Schedule preventative maintenance, track machine health, manage work orders, and reduce downtime.</p>
                            </div>
                        </div>
                        <div class="card bg-base-200">
                            <div class="card-body">
                                <h3 class="card-title">Geofencing</h3>
                                <p>Define zones, track entry/exit events, monitor tonnage, and analyze productivity by area.</p>
                            </div>
                        </div>
                    </div>

                    <h2>Platform Requirements</h2>
                    <ul>
                        <li><strong>Browser:</strong> Chrome, Firefox, Safari, or Edge (latest versions)</li>
                        <li><strong>Internet:</strong> Stable internet connection required for real-time features</li>
                        <li><strong>GPS Hardware:</strong> Compatible GPS tracking devices for machine monitoring</li>
                        <li><strong>Team Account:</strong> Active team subscription required</li>
                    </ul>
                </div>
            @endif

            @if($activeSection === 'quick-start')
                <div class="prose prose-invert max-w-none">
                    <h1>Quick Start Guide</h1>
                    <p>Get up and running with Mines in 5 easy steps.</p>

                    <h2>Step 1: Access Your Dashboard</h2>
                    <p>After logging in, you'll be directed to your main dashboard. This is your command center for monitoring your entire fleet.</p>
                    <div class="alert alert-success">
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span><strong>Tip:</strong> Use the sidebar navigation to access different features quickly.</span>
                    </div>

                    <h2>Step 2: Add Your First Machine</h2>
                    <ol>
                        <li>Click <strong>Fleet</strong> in the sidebar</li>
                        <li>Click the <strong>"Add Machine"</strong> button</li>
                        <li>Fill in machine details:
                            <ul>
                                <li>Machine name and serial number</li>
                                <li>Machine type (Haul Truck, Excavator, Dozer, etc.)</li>
                                <li>GPS device ID (if applicable)</li>
                            </ul>
                        </li>
                        <li>Click <strong>Save</strong></li>
                    </ol>

                    <h2>Step 3: Set Up Geofences</h2>
                    <ol>
                        <li>Navigate to <strong>Geofences</strong></li>
                        <li>Click <strong>"Create Geofence"</strong></li>
                        <li>Draw your geofence on the map by clicking to add points</li>
                        <li>Name your geofence and set its type (loading, dumping, maintenance, etc.)</li>
                        <li>Save the geofence</li>
                    </ol>

                    <h2>Step 4: Configure Fuel Tracking</h2>
                    <ol>
                        <li>Go to <strong>Fuel Management</strong></li>
                        <li>Add fuel tanks with capacity and location</li>
                        <li>Record your first fuel transaction</li>
                        <li>Set up fuel alerts for low levels</li>
                    </ol>

                    <h2>Step 5: Schedule Maintenance</h2>
                    <ol>
                        <li>Open <strong>Maintenance</strong></li>
                        <li>Select a machine</li>
                        <li>Create a maintenance schedule (hours-based, km-based, or calendar-based)</li>
                        <li>System will alert you when maintenance is due</li>
                    </ol>

                    <div class="alert alert-info mt-6">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>Need help? Reach out to your team's administrator or owner -- they can be found on the <a href="#" wire:click="setSection('team-management')">Team Management</a> page.</span>
                    </div>
                </div>
            @endif

            @if($activeSection === 'fleet')
                <div class="prose prose-invert max-w-none">
                    <h1>Fleet Management</h1>
                    <p>Manage your entire fleet of mining equipment from a single interface.</p>

                    <h2>Adding Machines</h2>
                    <p>To add a new machine to your fleet:</p>
                    <ol>
                        <li>Navigate to <strong>Fleet</strong> in the sidebar</li>
                        <li>Click <strong>"Add Machine"</strong></li>
                        <li>Enter machine details</li>
                        <li>Click <strong>Save</strong></li>
                    </ol>

                    <h3>Machine Types</h3>
                    <ul>
                        <li><strong>ADT:</strong> Articulated dump truck</li>
                        <li><strong>Excavator:</strong> Digging and loading equipment</li>
                        <li><strong>Dozer:</strong> Bulldozers for pushing material</li>
                        <li><strong>Loader:</strong> Front-end loaders</li>
                        <li><strong>Grader:</strong> Motor graders for surface preparation</li>
                        <li><strong>Drill:</strong> Drilling equipment</li>
                        <li><strong>Truck:</strong> General haul trucks</li>
                        <li><strong>LDV:</strong> Light delivery vehicle</li>
                        <li><strong>Other</strong></li>
                    </ul>

                    <h2>Machine Status</h2>
                    <p>Each machine can have one of these statuses:</p>
                    <ul>
                        <li><span class="badge badge-success">Active</span> - Currently operating</li>
                        <li><span class="badge badge-warning">Idle</span> - Not moving but online</li>
                        <li><span class="badge badge-error">Offline</span> - No recent GPS data</li>
                        <li><span class="badge badge-info">Maintenance</span> - Under service</li>
                    </ul>

                    <h2>Viewing Machine Details</h2>
                    <p>Click on any machine to view:</p>
                    <ul>
                        <li>Real-time location and status</li>
                        <li>Operating hours and distance traveled</li>
                        <li>Fuel consumption</li>
                        <li>Maintenance history</li>
                        <li>Health status</li>
                        <li>Recent alerts</li>
                    </ul>

                    <h2>Machine Metrics</h2>
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Metric</th>
                                    <th>Description</th>
                                    <th>Usage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Operating Hours</td>
                                    <td>Total engine hours</td>
                                    <td>Maintenance scheduling</td>
                                </tr>
                                <tr>
                                    <td>Distance Traveled</td>
                                    <td>Total kilometers</td>
                                    <td>Efficiency analysis</td>
                                </tr>
                                <tr>
                                    <td>Fuel Efficiency</td>
                                    <td>Liters per hour/km</td>
                                    <td>Cost optimization</td>
                                </tr>
                                <tr>
                                    <td>Health Score</td>
                                    <td>Overall condition (0-100)</td>
                                    <td>Predictive maintenance</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if($activeSection === 'live-map')
                <div class="prose prose-invert max-w-none">
                    <h1>Live Map</h1>
                    <p>Monitor your entire fleet in real-time on an interactive map.</p>

                    <h2>Map Features</h2>
                    <ul>
                        <li><strong>Real-Time Tracking:</strong> See machine locations update every few seconds</li>
                        <li><strong>Status Indicators:</strong> Color-coded markers show machine status</li>
                        <li><strong>Geofence Overlay:</strong> View all defined zones on the map</li>
                        <li><strong>Historical Trails:</strong> Show movement history</li>
                        <li><strong>Clustering:</strong> Automatic grouping of nearby machines</li>
                    </ul>

                    <h2>Using the Map</h2>
                    <h3>Navigation</h3>
                    <ul>
                        <li><strong>Zoom:</strong> Scroll wheel or +/- buttons</li>
                        <li><strong>Pan:</strong> Click and drag</li>
                        <li><strong>Reset View:</strong> Click home icon to center on all machines</li>
                    </ul>

                    <h3>Machine Markers</h3>
                    <p>Click on any machine marker to view:</p>
                    <ul>
                        <li>Machine name and type</li>
                        <li>Current status</li>
                        <li>Speed and heading</li>
                        <li>Last update time</li>
                        <li>Quick actions (view details, create alert)</li>
                    </ul>

                    <h3>Filters</h3>
                    <p>Use the filter panel to:</p>
                    <ul>
                        <li>Show/hide specific machine types</li>
                        <li>Filter by status</li>
                        <li>Show only machines in specific areas</li>
                        <li>Display custom date ranges</li>
                    </ul>

                </div>
            @endif

            @if($activeSection === 'geofences')
                <div class="prose prose-invert max-w-none">
                    <h1>Geofences</h1>
                    <p>Define virtual boundaries and track machine activity within specific zones.</p>

                    <h2>Creating Geofences</h2>
                    <ol>
                        <li>Navigate to <strong>Geofences</strong></li>
                        <li>Click <strong>"Create Geofence"</strong></li>
                        <li>Choose geofence type</li>
                        <li>Draw the boundary on the map</li>
                        <li>Configure settings and save</li>
                    </ol>

                    <h2>Geofence Types</h2>
                    <ul>
                        <li><strong>Pit:</strong> Open pit mining areas</li>
                        <li><strong>Stockpile:</strong> Material storage areas</li>
                        <li><strong>Dump:</strong> Waste dump areas</li>
                        <li><strong>Facility:</strong> Administrative and support buildings</li>
                    </ul>

                    <h2>Entry/Exit Tracking</h2>
                    <p>The system automatically tracks:</p>
                    <ul>
                        <li>Entry time and machine</li>
                        <li>Exit time and duration</li>
                        <li>Tonnage hauled (for relevant zones)</li>
                        <li>Material type</li>
                        <li>Number of cycles</li>
                    </ul>

                    <h2>Geofence Analytics</h2>
                    <p>View detailed analytics for each geofence:</p>
                    <ul>
                        <li>Total entries/exits</li>
                        <li>Average dwell time</li>
                        <li>Machine activity distribution</li>
                        <li>Productivity metrics</li>
                        <li>Peak usage times</li>
                    </ul>

                    <h2>Alerts & Notifications</h2>
                    <p>Configure alerts for:</p>
                    <ul>
                        <li>Unauthorized entry into restricted zones</li>
                        <li>Excessive dwell time</li>
                        <li>After-hours activity</li>
                        <li>Capacity thresholds</li>
                    </ul>
                </div>
            @endif

            @if($activeSection === 'fuel-management')
                <div class="prose prose-invert max-w-none">
                    <h1>Fuel Management</h1>
                    <p>Comprehensive fuel tracking and analysis for your fleet.</p>

                    <h2>Fuel Tanks</h2>
                    <h3>Adding Tanks</h3>
                    <ol>
                        <li>Go to <strong>Fuel Management</strong></li>
                        <li>Click <strong>"Add Tank"</strong></li>
                        <li>Enter tank details:
                            <ul>
                                <li>Tank name and location</li>
                                <li>Capacity (liters)</li>
                                <li>Fuel type (Diesel, Petrol, etc.)</li>
                                <li>Minimum level threshold</li>
                            </ul>
                        </li>
                        <li>Save</li>
                    </ol>

                    <h3>Tank Monitoring</h3>
                    <p>The dashboard shows:</p>
                    <ul>
                        <li>Current fill level (%)</li>
                        <li>Remaining capacity</li>
                        <li>Days until empty (estimated)</li>
                        <li>Low fuel warnings</li>
                    </ul>

                    <h2>Fuel Transactions</h2>
                    <h3>Transaction Types</h3>
                    <ul>
                        <li><strong>Refill:</strong> Adding fuel to a tank</li>
                        <li><strong>Dispensing:</strong> Filling a machine from a tank</li>
                        <li><strong>Delivery:</strong> External fuel delivery</li>
                        <li><strong>Transfer:</strong> Moving fuel between tanks</li>
                        <li><strong>Adjustment:</strong> Inventory correction</li>
                        <li><strong>Theft:</strong> Recording fuel loss</li>
                        <li><strong>Spillage:</strong> Accidental loss</li>
                    </ul>

                    <h3>Recording Transactions</h3>
                    <ol>
                        <li>Click <strong>"Record Transaction"</strong></li>
                        <li>Select transaction type</li>
                        <li>Choose tank and/or machine</li>
                        <li>Enter quantity and cost</li>
                        <li>Upload receipt (optional)</li>
                        <li>Save</li>
                    </ol>

                    <h2>Fuel Analytics</h2>
                    <p>Access detailed reports on:</p>
                    <ul>
                        <li><strong>Consumption by Machine:</strong> Which machines use the most fuel</li>
                        <li><strong>Efficiency Trends:</strong> L/h or L/km over time</li>
                        <li><strong>Cost Analysis:</strong> Total fuel costs by period</li>
                        <li><strong>Idle Time:</strong> Fuel wasted during idle periods</li>
                        <li><strong>Anomaly Detection:</strong> Unusual consumption patterns</li>
                    </ul>

                    <h2>Fuel Budgets</h2>
                    <p>Set and track fuel budgets:</p>
                    <ol>
                        <li>Create a budget for a period (monthly, quarterly, annual)</li>
                        <li>Set volume and cost limits</li>
                        <li>Monitor utilization percentage</li>
                        <li>Receive alerts when approaching limits</li>
                    </ol>

                    <h2>Export & Reports</h2>
                    <p>Export data in multiple formats:</p>
                    <ul>
                        <li><strong>CSV:</strong> For spreadsheet analysis</li>
                        <li><strong>PDF:</strong> For management reports</li>
                        <li><strong>JSON:</strong> For system integration</li>
                    </ul>
                </div>
            @endif

            @if($activeSection === 'maintenance')
                <div class="prose prose-invert max-w-none">
                    <h1>Maintenance & Health Monitoring</h1>
                    <p>Preventative maintenance scheduling and machine health tracking.</p>

                    <h2>Machine Health Status</h2>
                    <h3>Health Score</h3>
                    <p>Each machine has a health score (0-100) based on:</p>
                    <ul>
                        <li>Engine condition</li>
                        <li>Transmission health</li>
                        <li>Hydraulic system</li>
                        <li>Electrical system</li>
                        <li>Braking system</li>
                        <li>Cooling system</li>
                    </ul>

                    <h3>Health Status Categories</h3>
                    <ul>
                        <li><span class="badge badge-success">Excellent</span> (90-100): Optimal condition</li>
                        <li><span class="badge badge-info">Good</span> (75-89): Normal operation</li>
                        <li><span class="badge badge-warning">Fair</span> (60-74): Monitor closely</li>
                        <li><span class="badge badge-warning">Poor</span> (40-59): Service recommended</li>
                        <li><span class="badge badge-error">Critical</span> (&lt;40): Immediate attention required</li>
                    </ul>

                    <h2>Maintenance Schedules</h2>
                    <h3>Schedule Types</h3>
                    <ul>
                        <li><strong>Hours-Based:</strong> Service after X operating hours</li>
                        <li><strong>Kilometers-Based:</strong> Service after X kilometers</li>
                        <li><strong>Calendar-Based:</strong> Service every X days</li>
                        <li><strong>Condition-Based:</strong> Service based on health metrics</li>
                    </ul>

                    <h3>Creating a Schedule</h3>
                    <ol>
                        <li>Go to <strong>Maintenance</strong></li>
                        <li>Select a machine</li>
                        <li>Click <strong>"Create Schedule"</strong></li>
                        <li>Choose schedule type and interval</li>
                        <li>Set priority (low, medium, high, critical)</li>
                        <li>Add required parts and tools</li>
                        <li>Save</li>
                    </ol>

                    <h2>Work Orders</h2>
                    <h3>Creating Work Orders</h3>
                    <ol>
                        <li>Navigate to maintenance records</li>
                        <li>Click <strong>"Create Work Order"</strong></li>
                        <li>Select machine and maintenance type</li>
                        <li>Assign technician</li>
                        <li>Schedule date and time</li>
                        <li>Save (auto-generates WO number)</li>
                    </ol>

                    <h3>Work Order Statuses</h3>
                    <ul>
                        <li><span class="badge">Scheduled</span> - Awaiting start</li>
                        <li><span class="badge badge-warning">In Progress</span> - Being worked on</li>
                        <li><span class="badge badge-success">Completed</span> - Finished</li>
                        <li><span class="badge badge-error">Cancelled</span> - Cancelled</li>
                    </ul>

                    <h3>Completing Work Orders</h3>
                    <ol>
                        <li>Open the work order</li>
                        <li>Click <strong>"Complete"</strong></li>
                        <li>Enter:
                            <ul>
                                <li>Labor hours</li>
                                <li>Parts used and costs</li>
                                <li>Work performed details</li>
                                <li>Fault codes cleared</li>
                                <li>Technician notes</li>
                            </ul>
                        </li>
                        <li>Confirm machine is operational</li>
                        <li>Save</li>
                    </ol>

                    <h2>Component Tracking</h2>
                    <p>Track individual component lifecycles:</p>
                    <ul>
                        <li>Replacement date and mileage/hours</li>
                        <li>Expected lifespan</li>
                        <li>Warranty information</li>
                        <li>Supplier details</li>
                        <li>Remaining lifespan %</li>
                    </ul>

                    <h2>Maintenance Analytics</h2>
                    <p>View comprehensive maintenance metrics:</p>
                    <ul>
                        <li>Total maintenance cost by machine</li>
                        <li>Average repair time</li>
                        <li>Most common issues</li>
                        <li>Downtime analysis</li>
                        <li>Preventative vs. corrective ratio</li>
                    </ul>

                    <div class="alert alert-warning">
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <span><strong>Important:</strong> Always complete maintenance records to keep accurate schedules and health tracking.</span>
                    </div>
                </div>
            @endif

            @if($activeSection === 'integrations-overview')
                <div class="prose prose-invert max-w-none">
                    <h1>Integrations Overview</h1>
                    <p>Connect Mines with your existing systems and hardware.</p>

                    <h2>Manufacturer Integrations</h2>
                    <p>Mines is building direct connections to major heavy equipment manufacturers' telematics systems, so machine data can eventually flow in automatically instead of being entered by hand. This is in active development — until a given manufacturer connection is verified against a real account, machine data should be tracked via the REST API or entered manually:</p>
                    <ul>
                        <li><strong>Bell:</strong> Implemented against Bell Equipment's published ISO 15143-3 fleet API (real OAuth2 + endpoints, unit-tested) — pending a first live sync confirmation</li>
                        <li><strong>Volvo, Caterpillar, Komatsu, Hitachi:</strong> Heavy equipment telematics</li>
                        <li><strong>Liebherr, Hyundai, Doosan, JCB:</strong> Construction equipment</li>
                        <li><strong>Sany, XCMG, Kobelco, Kubota:</strong> Additional manufacturer integrations</li>
                        <li><strong>Epiroc, Roundebult, Kawasaki, C-Track:</strong> Drilling, mining equipment, and GPS tracking</li>
                        <li><strong>John Deere, CASE, New Holland, Takeuchi, Bobcat, Yanmar, Atlas Copco, Sandvik:</strong> Not yet available</li>
                    </ul>
                    <p>See the <a href="{{ route('integrations') }}">Integrations</a> page in your account for the current, real connection status of each provider — a manufacturer being listed here means a connector exists, not that a live connection has been confirmed.</p>

                    <h2>Integration Methods</h2>

                    <h3>REST API</h3>
                    <p>Full REST API for all platform features. See <a href="#" wire:click="setSection('api-access')">API Access</a> for details.</p>

                    <h3>File Import/Export</h3>
                    <p>Bulk data operations via CSV/Excel files:</p>
                    <ul>
                        <li>Import machine lists</li>
                        <li>Export transaction history</li>
                        <li>Import maintenance schedules</li>
                        <li>Export reports</li>
                    </ul>

                    <h2>Setting Up Integrations</h2>
                    <ol>
                        <li>Navigate to <strong>Integrations</strong></li>
                        <li>Click <strong>"Add Integration"</strong></li>
                        <li>Select a manufacturer</li>
                        <li>Enter credentials/API keys</li>
                        <li>Test the connection</li>
                        <li>Sync machines</li>
                    </ol>
                </div>
            @endif

            @if($activeSection === 'api-access')
                <div class="prose prose-invert max-w-none">
                    <h1>API Access</h1>
                    <p>Complete REST API for programmatic access to Mines.</p>

                    <h2>Getting Started</h2>
                    
                    <h3>1. Generate API Token</h3>
                    <ol>
                        <li>Open <strong>API Tokens</strong> from your account menu (top right)</li>
                        <li>Click <strong>"Create"</strong></li>
                        <li>Name your token (e.g., "Production Integration")</li>
                        <li>Select permissions</li>
                        <li>Copy the token (shown only once!)</li>
                    </ol>
                    <p>Permissions are enforced per request: <strong>read</strong> covers all <code>GET</code> requests, <strong>create</strong> and <strong>update</strong> cover <code>POST</code> and <code>PUT</code>, and <strong>delete</strong> covers <code>DELETE</code>. A read-only token cannot modify anything — pick the write permissions only for tokens that need them.</p>

                    <h3>2. Authentication</h3>
                    <p>Include your token in the Authorization header:</p>
                    <div class="mockup-code">
                        <pre data-prefix="$"><code>curl -H "Authorization: Bearer REPLACE_WITH_YOUR_API_TOKEN" \</code></pre>
                        <pre data-prefix=""><code>     -H "Accept: application/json" \</code></pre>
                        <pre data-prefix=""><code>     {{ config('app.url') }}/api/machines</code></pre>
                    </div>

                    <h2>Base URL</h2>
                    <div class="mockup-code">
                        <pre data-prefix=""><code>{{ config('app.url') }}/api</code></pre>
                    </div>

                    <h2>List responses</h2>
                    <p>Every endpoint that returns a list uses the same envelope, so one handler works everywhere. Rows are always under <code>data</code>; paging information is always under <code>meta</code> and <code>links</code>.</p>
                    <div class="mockup-code">
                        <pre data-prefix=""><code>{</code></pre>
                        <pre data-prefix=""><code>  "data": [ ... ],</code></pre>
                        <pre data-prefix=""><code>  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },</code></pre>
                        <pre data-prefix=""><code>  "meta":  { "current_page": 1, "from": 1, "to": 15,</code></pre>
                        <pre data-prefix=""><code>             "per_page": 15, "last_page": 4, "total": 52 }</code></pre>
                        <pre data-prefix=""><code>}</code></pre>
                    </div>
                    <p>Pass <code>?page=</code> and <code>?per_page=</code> to page through results. Endpoints that return a short, bounded list (for example a machine's recent alerts) omit <code>links</code> and return <code>meta.total</code> only.</p>

                    <h2>Field names are stable</h2>
                    <p>Responses are an explicit, versioned set of fields — not a dump of the underlying tables. Internal columns (replication counters, entitlement state, storage paths, provider credentials) are never returned, and a column added or renamed inside Mines will not change your payload. Timestamps are ISO-8601 everywhere, and a referenced person appears as <code>{ "id": 1, "name": "..." }</code> rather than a full user record.</p>

                    <h2>Common Endpoints</h2>
                    
                    <h3>Machines</h3>
                    <div class="overflow-x-auto">
                        <table class="table table-compact">
                            <thead>
                                <tr>
                                    <th>Method</th>
                                    <th>Endpoint</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="badge badge-info">GET</span></td>
                                    <td>/machines</td>
                                    <td>List all machines</td>
                                </tr>
                                <tr>
                                    <td><span class="badge badge-info">GET</span></td>
                                    <td>/machines/{id}</td>
                                    <td>Get machine details</td>
                                </tr>
                                <tr>
                                    <td><span class="badge badge-success">POST</span></td>
                                    <td>/machines</td>
                                    <td>Create machine</td>
                                </tr>
                                <tr>
                                    <td><span class="badge badge-warning">PUT</span></td>
                                    <td>/machines/{id}</td>
                                    <td>Update machine</td>
                                </tr>
                                <tr>
                                    <td><span class="badge badge-error">DELETE</span></td>
                                    <td>/machines/{id}</td>
                                    <td>Delete machine</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h3>Fuel Management</h3>
                    <div class="overflow-x-auto">
                        <table class="table table-compact">
                            <thead>
                                <tr>
                                    <th>Method</th>
                                    <th>Endpoint</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="badge badge-info">GET</span></td>
                                    <td>/fuel/tanks</td>
                                    <td>List fuel tanks</td>
                                </tr>
                                <tr>
                                    <td><span class="badge badge-success">POST</span></td>
                                    <td>/fuel/transactions</td>
                                    <td>Record fuel transaction</td>
                                </tr>
                                <tr>
                                    <td><span class="badge badge-info">GET</span></td>
                                    <td>/fuel/transactions/statistics</td>
                                    <td>Get fuel analytics</td>
                                </tr>
                                <tr>
                                    <td><span class="badge badge-info">GET</span></td>
                                    <td>/fuel/transactions/export</td>
                                    <td>Export transactions</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h3>Maintenance</h3>
                    <div class="overflow-x-auto">
                        <table class="table table-compact">
                            <thead>
                                <tr>
                                    <th>Method</th>
                                    <th>Endpoint</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="badge badge-info">GET</span></td>
                                    <td>/maintenance/health/{machine}</td>
                                    <td>Get machine health</td>
                                </tr>
                                <tr>
                                    <td><span class="badge badge-success">POST</span></td>
                                    <td>/maintenance/records</td>
                                    <td>Create work order</td>
                                </tr>
                                <tr>
                                    <td><span class="badge badge-success">POST</span></td>
                                    <td>/maintenance/records/{id}/complete</td>
                                    <td>Complete work order</td>
                                </tr>
                                <tr>
                                    <td><span class="badge badge-info">GET</span></td>
                                    <td>/maintenance/records/analytics</td>
                                    <td>Get maintenance analytics</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h2>Example Requests</h2>
                    
                    <h3>Create Machine</h3>
                    <div class="mockup-code">
                        <pre data-prefix="$"><code>curl -X POST {{ config('app.url') }}/api/machines \</code></pre>
                        <pre data-prefix=""><code>  -H "Authorization: Bearer REPLACE_WITH_YOUR_API_TOKEN" \</code></pre>
                        <pre data-prefix=""><code>  -H "Content-Type: application/json" \</code></pre>
                        <pre data-prefix=""><code>  -d '{</code></pre>
                        <pre data-prefix=""><code>    "name": "Haul Truck 01",</code></pre>
                        <pre data-prefix=""><code>    "machine_type": "truck",</code></pre>
                        <pre data-prefix=""><code>    "serial_number": "HT-12345"</code></pre>
                        <pre data-prefix=""><code>  }'</code></pre>
                    </div>

                    <h3>Record Fuel Transaction</h3>
                    <div class="mockup-code">
                        <pre data-prefix="$"><code>curl -X POST {{ config('app.url') }}/api/fuel/transactions \</code></pre>
                        <pre data-prefix=""><code>  -H "Authorization: Bearer REPLACE_WITH_YOUR_API_TOKEN" \</code></pre>
                        <pre data-prefix=""><code>  -H "Content-Type: application/json" \</code></pre>
                        <pre data-prefix=""><code>  -d '{</code></pre>
                        <pre data-prefix=""><code>    "fuel_tank_id": 1,</code></pre>
                        <pre data-prefix=""><code>    "machine_id": 5,</code></pre>
                        <pre data-prefix=""><code>    "transaction_type": "dispensing",</code></pre>
                        <pre data-prefix=""><code>    "quantity_liters": 200,</code></pre>
                        <pre data-prefix=""><code>    "transaction_date": "2026-01-20T10:30:00Z"</code></pre>
                        <pre data-prefix=""><code>  }'</code></pre>
                    </div>

                    <h2>Rate Limits</h2>
                    <ul>
                        <li>60 requests per minute, per user</li>
                        <li>Rate limit headers included in all responses</li>
                    </ul>

                    <h2>Error Codes</h2>
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>200</td>
                                    <td>Success</td>
                                </tr>
                                <tr>
                                    <td>401</td>
                                    <td>Unauthorized - Invalid token</td>
                                </tr>
                                <tr>
                                    <td>403</td>
                                    <td>Forbidden - Insufficient permissions</td>
                                </tr>
                                <tr>
                                    <td>404</td>
                                    <td>Not Found</td>
                                </tr>
                                <tr>
                                    <td>422</td>
                                    <td>Validation Error</td>
                                </tr>
                                <tr>
                                    <td>429</td>
                                    <td>Rate Limit Exceeded</td>
                                </tr>
                                <tr>
                                    <td>500</td>
                                    <td>Server Error</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if($activeSection === 'webhooks')
                <div class="prose prose-invert max-w-none">
                    <h1>Webhooks</h1>
                    <div class="alert alert-warning">
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <span><strong>Not yet available.</strong> Outbound webhooks (Mines pushing event notifications to your own endpoint) aren't built yet. If you need to react to events in real time today, poll the relevant <a href="#" wire:click="setSection('api-access')">REST API</a> endpoint on an interval.</span>
                    </div>
                </div>
            @endif

            @if($activeSection === 'team-management')
                <div class="prose prose-invert max-w-none">
                    <h1>Team Management</h1>
                    <p>Manage your team members and their access to Mines.</p>

                    <h2>Adding Team Members</h2>
                    <ol>
                        <li>Open <strong>Team</strong> from your account menu, or go to <strong>Settings</strong> → <strong>Users &amp; Roles</strong></li>
                        <li>Click <strong>"Invite Member"</strong></li>
                        <li>Enter email address</li>
                        <li>Select role</li>
                        <li>Send invitation</li>
                    </ol>

                    <h2>User Roles</h2>
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Role</th>
                                    <th>Permissions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Owner</strong></td>
                                    <td>The person who created the team. Full access, including billing.</td>
                                </tr>
                                <tr>
                                    <td><strong>Administrator</strong></td>
                                    <td>Full access to every feature and data set. Team membership and billing are Owner-only, regardless of role.</td>
                                </tr>
                                <tr>
                                    <td><strong>Fleet Manager</strong></td>
                                    <td>Can manage machines, geofences, and reports, but not team or billing settings</td>
                                </tr>
                                <tr>
                                    <td><strong>Operator</strong></td>
                                    <td>Can view machines and maps, and acknowledge alerts</td>
                                </tr>
                                <tr>
                                    <td><strong>Viewer</strong></td>
                                    <td>Read-only access to dashboards, machines, and reports</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h2>Managing Members</h2>
                    <ul>
                        <li><strong>Edit Role:</strong> Change member permissions</li>
                        <li><strong>Remove:</strong> Remove from team</li>
                        <li><strong>Cancel Invitation:</strong> Withdraw a pending invite before it's accepted</li>
                    </ul>
                </div>
            @endif

            @if($activeSection === 'reports')
                <div class="prose prose-invert max-w-none">
                    <h1>Reports</h1>
                    <p>Generate comprehensive reports on fleet operations.</p>

                    <h2>Available Reports</h2>
                    <ul>
                        <li><strong>Production Summary:</strong> Loads, cycles, tonnage, and BCM across your fleet</li>
                        <li><strong>Fleet Utilization:</strong> Machine activity and status history</li>
                        <li><strong>Maintenance Schedule:</strong> Upcoming and completed service work</li>
                        <li><strong>Fuel Consumption:</strong> Transaction history and cost analysis</li>
                        <li><strong>Material Tracking:</strong> Material moved by area and machine</li>
                        <li><strong>Downtime Analysis:</strong> Machine downtime by cause</li>
                    </ul>

                    <h2>Generating Reports</h2>
                    <ol>
                        <li>Navigate to <strong>Reports</strong></li>
                        <li>Select report type</li>
                        <li>Choose date range</li>
                        <li>Click <strong>"Generate"</strong></li>
                        <li>Export in your chosen format (PDF, CSV, Excel)</li>
                    </ol>

                    <div class="alert alert-info">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>The report form lets you mark a report to auto-repeat on a schedule, but automatic recurring generation and emailing isn't wired up yet -- for now, generate reports on demand.</span>
                    </div>
                </div>
            @endif

            @if($activeSection === 'alerts')
                <div class="prose prose-invert max-w-none">
                    <h1>Alerts & Notifications</h1>
                    <p>Stay informed about critical events and conditions.</p>

                    <h2>Alert Types</h2>
                    <p>Alerts are generated automatically by the platform's monitoring services -- there's currently no way to configure custom thresholds. Types include:</p>
                    <ul>
                        <li><strong>Machine:</strong> Offline, status changes</li>
                        <li><strong>Fuel:</strong> Tank low, tank critical, budget exceeded</li>
                        <li><strong>Maintenance:</strong> Service due, service overdue, health warnings</li>
                        <li><strong>Geofence:</strong> Unauthorized entry, excessive dwell time</li>
                        <li><strong>Operator Fatigue:</strong> High or critical fatigue score during a shift</li>
                    </ul>

                    <h2>Alert Priority</h2>
                    <ul>
                        <li><span class="badge badge-error">Critical</span> - Immediate action required</li>
                        <li><span class="badge badge-warning">High</span> - Urgent attention needed</li>
                        <li><span class="badge badge-info">Medium</span> - Monitor situation</li>
                        <li><span class="badge">Low</span> - Informational</li>
                    </ul>

                    <h2>Managing Alerts</h2>
                    <ul>
                        <li><strong>Acknowledge:</strong> Mark alert as seen</li>
                        <li><strong>Resolve:</strong> Mark issue as fixed</li>
                        <li><strong>Dismiss:</strong> Close an alert with a required reason</li>
                    </ul>

                    <div class="alert alert-info">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>You can turn email delivery for alerts on or off under <strong>Settings</strong> → <strong>Notifications</strong>.</span>
                    </div>
                </div>
            @endif

            @if($activeSection === 'mine-areas')
                <div class="prose prose-invert max-w-none">
                    <h1>Mine Areas</h1>
                    <p>Define and manage operational areas within your mine site.</p>

                    <h2>Creating Mine Areas</h2>
                    <ol>
                        <li>Navigate to <strong>Mine Areas</strong></li>
                        <li>Click <strong>"Create Area"</strong></li>
                        <li>Draw the boundary on the map</li>
                        <li>Set name, manager, and status (Active, Inactive, or Planning)</li>
                        <li>Assign machines (optional)</li>
                        <li>Save</li>
                    </ol>

                    <h2>Area Management</h2>
                    <ul>
                        <li>View assigned machines</li>
                        <li>Track productivity metrics</li>
                        <li>Monitor activity</li>
                        <li>Generate area-specific reports</li>
                    </ul>
                </div>
            @endif

            @if($activeSection === 'dashboard')
                <div class="prose prose-invert max-w-none">
                    <h1>Dashboard</h1>
                    <p>Your central command center for fleet operations.</p>

                    <h2>Dashboard Sections</h2>
                    <ul>
                        <li><strong>Get set up:</strong> A checklist guiding a new team through creating its first mine area and machine (disappears once both exist)</li>
                        <li><strong>Stat cards:</strong> Total machines, active machines, active alerts, and geofences</li>
                        <li><strong>Recent Alerts:</strong> The latest unresolved alerts, with a link to view all</li>
                        <li><strong>Machine Status &amp; Quick Actions:</strong> A breakdown of machine statuses, plus shortcuts to Fleet, the Live Map, and AI Insights</li>
                    </ul>
                    <p>The dashboard refreshes itself automatically every 10 seconds.</p>
                </div>
            @endif

            @if($activeSection === 'machine-tracking')
                <div class="prose prose-invert max-w-none">
                    <h1>Machine Tracking</h1>
                    <p>Real-time and historical tracking of your equipment.</p>

                    <h2>Live Tracking</h2>
                    <ul>
                        <li>Real-time GPS location updates</li>
                        <li>Current speed and heading</li>
                        <li>Status indicators</li>
                        <li>Battery/fuel level</li>
                    </ul>

                    <h2>Historical Playback</h2>
                    <ol>
                        <li>Select a machine</li>
                        <li>Choose date range</li>
                        <li>Click <strong>"Playback"</strong></li>
                        <li>Use controls to play/pause/speed up</li>
                    </ol>

                    <h2>Location History</h2>
                    <p>Access complete location history with:</p>
                    <ul>
                        <li>Breadcrumb trails on the map</li>
                        <li>Total distance travelled</li>
                        <li>Export to CSV</li>
                    </ul>
                </div>
            @endif

            @if($activeSection === 'user-roles')
                <div class="prose prose-invert max-w-none">
                    <h1>User Roles & Permissions</h1>
                    <p>Detailed breakdown of role capabilities.</p>

                    <h2>Role Comparison</h2>
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Permission</th>
                                    <th>Owner</th>
                                    <th>Administrator</th>
                                    <th>Fleet Manager</th>
                                    <th>Operator</th>
                                    <th>Viewer</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>View dashboard, machines, live map, geofences</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                </tr>
                                <tr>
                                    <td>Create/update machines and geofences</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>-</td>
                                    <td>-</td>
                                </tr>
                                <tr>
                                    <td>Delete machines and geofences</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                </tr>
                                <tr>
                                    <td>View reports</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>-</td>
                                    <td>✓</td>
                                </tr>
                                <tr>
                                    <td>Generate reports</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>-</td>
                                    <td>-</td>
                                </tr>
                                <tr>
                                    <td>Acknowledge alerts</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>-</td>
                                </tr>
                                <tr>
                                    <td>Resolve alerts</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>-</td>
                                    <td>-</td>
                                </tr>
                                <tr>
                                    <td>Manage integrations</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                </tr>
                                <tr>
                                    <td>Manage team members &amp; billing</td>
                                    <td>✓</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-info mt-4">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>Managing team members, renaming the team, and billing are restricted to the team's Owner (the person who created it) regardless of any role assigned to other members.</span>
                    </div>
                </div>
            @endif

            @if($activeSection === 'settings')
                <div class="prose prose-invert max-w-none">
                    <h1>Settings</h1>
                    <p>The Settings page has three tabs.</p>

                    <h2>General</h2>
                    <ul>
                        <li><strong>Team Name</strong> and <strong>Team Email</strong></li>
                        <li><strong>Time Zone</strong></li>
                        <li><strong>Language</strong></li>
                        <li><strong>Currency</strong></li>
                    </ul>

                    <h2>Users &amp; Roles</h2>
                    <p>Invite members, change roles, and remove members. See <a href="#" wire:click="setSection('team-management')">Team Management</a>.</p>

                    <h2>Notifications</h2>
                    <ul>
                        <li><strong>Email Alerts:</strong> Receive alert notifications by email</li>
                        <li><strong>Email Reports:</strong> Receive report notifications by email</li>
                        <li><strong>In-App Alerts:</strong> Show notifications inside the app</li>
                        <li><strong>Quiet Hours:</strong> A window during which notifications are held back</li>
                    </ul>

                    <p>Two-factor authentication is managed from your <strong>Profile</strong> page, not Settings.</p>
                </div>
            @endif

            @if($activeSection === 'engineering-reports')
                <div class="prose prose-invert max-w-none">
                    <h1>Engineering Reports</h1>
                    <p class="lead">Longer-form engineering write-ups about the platform itself — what was changed, what was measured, and which safeguards now hold it in place.</p>

                    <div class="card bg-base-200 mt-6">
                        <div class="card-body">
                            <h3 class="card-title">Ledger Zero — refactor &amp; AI-readiness program (Aug 2026)</h3>
                            <p>Completion report for the R0–R9 program: both static-analysis debt ledgers (phpstan 1,898 findings, psalm 7,754) burned to zero and their baselines deleted, ~45 latent bugs found and fixed along the way, per-page query budgets frozen in CI, and the full slice-by-slice log with carry-forward lessons.</p>
                            <div class="card-actions justify-end mt-2">
                                <a href="https://claude.ai/code/artifact/9dc41690-1fb0-491c-a861-e2be4caf2a18" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm">Read the report</a>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mt-6">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>The report is hosted as a private page. If it asks you to request access, the document owner can grant it from the page's share menu.</span>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
