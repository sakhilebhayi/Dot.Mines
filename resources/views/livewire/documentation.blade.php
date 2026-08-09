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
                        <span>Need help? Contact our support team at <a href="mailto:support@mines.com">support@mines.com</a></span>
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
                        <li><strong>Haul Truck:</strong> Large dump trucks for material transport</li>
                        <li><strong>Excavator:</strong> Digging and loading equipment</li>
                        <li><strong>Dozer:</strong> Bulldozers for pushing material</li>
                        <li><strong>Loader:</strong> Front-end loaders</li>
                        <li><strong>Grader:</strong> Motor graders for surface preparation</li>
                        <li><strong>Drill:</strong> Drilling equipment</li>
                        <li><strong>Support Vehicle:</strong> Service trucks, water trucks, etc.</li>
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

                    <div class="alert alert-info">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span><strong>Pro Tip:</strong> Use keyboard shortcuts: Press 'F' to toggle fullscreen, 'M' to toggle map layers.</span>
                    </div>
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
                        <li><strong>Loading Zone:</strong> Areas where material is loaded</li>
                        <li><strong>Dumping Zone:</strong> Waste or stockpile areas</li>
                        <li><strong>Restricted:</strong> No-access areas</li>
                        <li><strong>Parking:</strong> Equipment parking areas</li>
                        <li><strong>Maintenance:</strong> Workshop and service areas</li>
                        <li><strong>Safety:</strong> Special safety zones</li>
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

                    <h2>Available Integrations</h2>
                    
                    <h3>GPS Tracking Devices</h3>
                    <p>Mines supports major GPS tracking providers:</p>
                    <ul>
                        <li><strong>Trimble:</strong> Fleet management hardware</li>
                        <li><strong>Geotab:</strong> Telematics devices</li>
                        <li><strong>CalAmp:</strong> GPS trackers</li>
                        <li><strong>Topcon:</strong> 3D machine control</li>
                        <li><strong>Generic NMEA:</strong> Standard GPS protocols</li>
                    </ul>

                    <h3>ERP Systems</h3>
                    <p>Integrate with enterprise resource planning systems:</p>
                    <ul>
                        <li><strong>SAP:</strong> Bi-directional sync for work orders and inventory</li>
                        <li><strong>Oracle NetSuite:</strong> Financial and operational data</li>
                        <li><strong>Microsoft Dynamics:</strong> Equipment and maintenance records</li>
                    </ul>

                    <h3>Fuel Management Systems</h3>
                    <ul>
                        <li><strong>FuelMaster:</strong> Automated fuel dispensing</li>
                        <li><strong>OPW:</strong> Tank monitoring systems</li>
                        <li><strong>Banlaw:</strong> Fluid management</li>
                    </ul>

                    <h3>Maintenance Systems</h3>
                    <ul>
                        <li><strong>Maximo:</strong> IBM asset management</li>
                        <li><strong>Infor EAM:</strong> Enterprise asset management</li>
                        <li><strong>Maintenance Connection:</strong> CMMS integration</li>
                    </ul>

                    <h2>Integration Methods</h2>
                    
                    <h3>REST API</h3>
                    <p>Full REST API for all platform features. See <a href="#" wire:click="setSection('api-access')">API Access</a> for details.</p>

                    <h3>Webhooks</h3>
                    <p>Receive real-time notifications for events. See <a href="#" wire:click="setSection('webhooks')">Webhooks</a> for setup.</p>

                    <h3>File Import/Export</h3>
                    <p>Bulk data operations via CSV/Excel files:</p>
                    <ul>
                        <li>Import machine lists</li>
                        <li>Export transaction history</li>
                        <li>Import maintenance schedules</li>
                        <li>Export reports</li>
                    </ul>

                    <h3>Direct Database Connection</h3>
                    <p>Enterprise customers can request direct database access for advanced integrations.</p>

                    <h2>Setting Up Integrations</h2>
                    <ol>
                        <li>Navigate to <strong>Integrations</strong></li>
                        <li>Click <strong>"Add Integration"</strong></li>
                        <li>Select integration type</li>
                        <li>Enter credentials/API keys</li>
                        <li>Configure sync settings</li>
                        <li>Test connection</li>
                        <li>Activate</li>
                    </ol>

                    <div class="alert alert-info">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>Need a custom integration? Contact our integration team at <a href="mailto:integrations@mines.com">integrations@mines.com</a></span>
                    </div>
                </div>
            @endif

            @if($activeSection === 'api-access')
                <div class="prose prose-invert max-w-none">
                    <h1>API Access</h1>
                    <p>Complete REST API for programmatic access to Mines.</p>

                    <h2>Getting Started</h2>
                    
                    <h3>1. Generate API Token</h3>
                    <ol>
                        <li>Go to <strong>Settings</strong> → <strong>API Tokens</strong></li>
                        <li>Click <strong>"Create New Token"</strong></li>
                        <li>Name your token (e.g., "Production Integration")</li>
                        <li>Select permissions</li>
                        <li>Copy the token (shown only once!)</li>
                    </ol>

                    <h3>2. Authentication</h3>
                    <p>Include your token in the Authorization header:</p>
                    <div class="mockup-code">
                        <pre data-prefix="$"><code>curl -H "Authorization: Bearer REPLACE_WITH_YOUR_API_TOKEN" \</code></pre>
                        <pre data-prefix=""><code>     -H "Accept: application/json" \</code></pre>
                        <pre data-prefix=""><code>     https://api.mines.com/api/machines</code></pre>
                    </div>

                    <h2>Base URL</h2>
                    <div class="mockup-code">
                        <pre data-prefix=""><code>https://api.mines.com/api</code></pre>
                    </div>

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
                        <pre data-prefix="$"><code>curl -X POST https://api.mines.com/api/machines \</code></pre>
                        <pre data-prefix=""><code>  -H "Authorization: Bearer REPLACE_WITH_YOUR_API_TOKEN" \</code></pre>
                        <pre data-prefix=""><code>  -H "Content-Type: application/json" \</code></pre>
                        <pre data-prefix=""><code>  -d '{</code></pre>
                        <pre data-prefix=""><code>    "name": "Haul Truck 01",</code></pre>
                        <pre data-prefix=""><code>    "machine_type": "haul_truck",</code></pre>
                        <pre data-prefix=""><code>    "serial_number": "HT-12345"</code></pre>
                        <pre data-prefix=""><code>  }'</code></pre>
                    </div>

                    <h3>Record Fuel Transaction</h3>
                    <div class="mockup-code">
                        <pre data-prefix="$"><code>curl -X POST https://api.mines.com/api/fuel/transactions \</code></pre>
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
                        <li><strong>Standard:</strong> 60 requests per minute</li>
                        <li><strong>Enterprise:</strong> 300 requests per minute</li>
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
                    <p>Receive real-time notifications when events occur in Mines.</p>

                    <h2>Setting Up Webhooks</h2>
                    <ol>
                        <li>Go to <strong>Integrations</strong> → <strong>Webhooks</strong></li>
                        <li>Click <strong>"Create Webhook"</strong></li>
                        <li>Enter your endpoint URL</li>
                        <li>Select events to subscribe to</li>
                        <li>Save (webhook secret will be generated)</li>
                    </ol>

                    <h2>Available Events</h2>
                    
                    <h3>Machine Events</h3>
                    <ul>
                        <li><code>machine.created</code> - New machine added</li>
                        <li><code>machine.updated</code> - Machine details changed</li>
                        <li><code>machine.status_changed</code> - Status update (active/idle/offline)</li>
                        <li><code>machine.location_updated</code> - GPS location changed</li>
                        <li><code>machine.offline</code> - Machine went offline</li>
                    </ul>

                    <h3>Geofence Events</h3>
                    <ul>
                        <li><code>geofence.entry</code> - Machine entered geofence</li>
                        <li><code>geofence.exit</code> - Machine exited geofence</li>
                        <li><code>geofence.violation</code> - Restricted area violation</li>
                    </ul>

                    <h3>Fuel Events</h3>
                    <ul>
                        <li><code>fuel.transaction_created</code> - New fuel transaction</li>
                        <li><code>fuel.tank_low</code> - Tank below minimum level</li>
                        <li><code>fuel.tank_critical</code> - Tank critically low</li>
                        <li><code>fuel.budget_exceeded</code> - Budget limit exceeded</li>
                    </ul>

                    <h3>Maintenance Events</h3>
                    <ul>
                        <li><code>maintenance.schedule_due</code> - Maintenance due</li>
                        <li><code>maintenance.schedule_overdue</code> - Maintenance overdue</li>
                        <li><code>maintenance.health_warning</code> - Health score below threshold</li>
                        <li><code>maintenance.health_critical</code> - Critical health issue</li>
                        <li><code>maintenance.fault_code</code> - Fault code detected</li>
                        <li><code>maintenance.work_order_completed</code> - Work order finished</li>
                    </ul>

                    <h2>Webhook Payload</h2>
                    <p>All webhooks have this structure:</p>
                    <div class="mockup-code">
                        <pre data-prefix=""><code>{</code></pre>
                        <pre data-prefix=""><code>  "event": "machine.status_changed",</code></pre>
                        <pre data-prefix=""><code>  "timestamp": "2026-01-20T14:30:00Z",</code></pre>
                        <pre data-prefix=""><code>  "team_id": 1,</code></pre>
                        <pre data-prefix=""><code>  "data": {</code></pre>
                        <pre data-prefix=""><code>    "machine_id": 5,</code></pre>
                        <pre data-prefix=""><code>    "old_status": "active",</code></pre>
                        <pre data-prefix=""><code>    "new_status": "idle"</code></pre>
                        <pre data-prefix=""><code>  }</code></pre>
                        <pre data-prefix=""><code>}</code></pre>
                    </div>

                    <h2>Verifying Webhooks</h2>
                    <p>Each webhook includes a signature in the <code>X-Webhook-Signature</code> header. Verify it:</p>
                    <div class="mockup-code">
                        <pre data-prefix=""><code>const crypto = require('crypto');</code></pre>
                        <pre data-prefix=""><code></code></pre>
                        <pre data-prefix=""><code>const signature = req.headers['x-webhook-signature'];</code></pre>
                        <pre data-prefix=""><code>const payload = JSON.stringify(req.body);</code></pre>
                        <pre data-prefix=""><code>const secret = 'your_webhook_secret';</code></pre>
                        <pre data-prefix=""><code></code></pre>
                        <pre data-prefix=""><code>const hash = crypto</code></pre>
                        <pre data-prefix=""><code>  .createHmac('sha256', secret)</code></pre>
                        <pre data-prefix=""><code>  .update(payload)</code></pre>
                        <pre data-prefix=""><code>  .digest('hex');</code></pre>
                        <pre data-prefix=""><code></code></pre>
                        <pre data-prefix=""><code>if (hash !== signature) {</code></pre>
                        <pre data-prefix=""><code>  throw new Error('Invalid signature');</code></pre>
                        <pre data-prefix=""><code>}</code></pre>
                    </div>

                    <h2>Best Practices</h2>
                    <ul>
                        <li><strong>Return 200 quickly:</strong> Process webhook asynchronously</li>
                        <li><strong>Verify signatures:</strong> Always validate the signature</li>
                        <li><strong>Handle retries:</strong> Make your endpoint idempotent</li>
                        <li><strong>Use HTTPS:</strong> Webhook URLs must use HTTPS</li>
                        <li><strong>Monitor failures:</strong> Check webhook logs regularly</li>
                    </ul>

                    <h2>Retry Policy</h2>
                    <ul>
                        <li>Failed webhooks retry up to 3 times</li>
                        <li>Exponential backoff: 5s, 30s, 5m</li>
                        <li>After 3 failures, webhook is disabled</li>
                        <li>Re-enable in webhook settings</li>
                    </ul>
                </div>
            @endif

            @if($activeSection === 'team-management')
                <div class="prose prose-invert max-w-none">
                    <h1>Team Management</h1>
                    <p>Manage your team members and their access to Mines.</p>

                    <h2>Adding Team Members</h2>
                    <ol>
                        <li>Go to <strong>Settings</strong> → <strong>Team</strong></li>
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
                                    <td>Full access including billing and team management</td>
                                </tr>
                                <tr>
                                    <td><strong>Admin</strong></td>
                                    <td>Full access except billing</td>
                                </tr>
                                <tr>
                                    <td><strong>Manager</strong></td>
                                    <td>View all data, create/edit machines, schedules, transactions</td>
                                </tr>
                                <tr>
                                    <td><strong>Operator</strong></td>
                                    <td>View data, record transactions, update machine status</td>
                                </tr>
                                <tr>
                                    <td><strong>Viewer</strong></td>
                                    <td>Read-only access to all data</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h2>Managing Members</h2>
                    <ul>
                        <li><strong>Edit Role:</strong> Change member permissions</li>
                        <li><strong>Deactivate:</strong> Temporarily remove access</li>
                        <li><strong>Remove:</strong> Permanently remove from team</li>
                        <li><strong>Resend Invite:</strong> Send invitation email again</li>
                    </ul>
                </div>
            @endif

            @if($activeSection === 'reports')
                <div class="prose prose-invert max-w-none">
                    <h1>Reports</h1>
                    <p>Generate comprehensive reports on fleet operations.</p>

                    <h2>Available Reports</h2>
                    
                    <h3>Fleet Reports</h3>
                    <ul>
                        <li>Fleet utilization summary</li>
                        <li>Machine activity logs</li>
                        <li>Distance and hours by machine</li>
                        <li>Status history</li>
                    </ul>

                    <h3>Fuel Reports</h3>
                    <ul>
                        <li>Fuel consumption by machine</li>
                        <li>Tank level history</li>
                        <li>Transaction history</li>
                        <li>Cost analysis</li>
                        <li>Efficiency trends</li>
                    </ul>

                    <h3>Maintenance Reports</h3>
                    <ul>
                        <li>Work order summary</li>
                        <li>Maintenance costs</li>
                        <li>Downtime analysis</li>
                        <li>Component replacement history</li>
                        <li>Health score trends</li>
                    </ul>

                    <h3>Geofence Reports</h3>
                    <ul>
                        <li>Entry/exit logs</li>
                        <li>Dwell time analysis</li>
                        <li>Productivity by zone</li>
                        <li>Tonnage reports</li>
                    </ul>

                    <h2>Generating Reports</h2>
                    <ol>
                        <li>Navigate to <strong>Reports</strong></li>
                        <li>Select report type</li>
                        <li>Choose date range</li>
                        <li>Apply filters (machines, areas, etc.)</li>
                        <li>Click <strong>"Generate"</strong></li>
                        <li>Export in desired format (PDF, CSV, Excel)</li>
                    </ol>

                    <h2>Scheduled Reports</h2>
                    <p>Automate report generation:</p>
                    <ol>
                        <li>Create a report configuration</li>
                        <li>Set schedule (daily, weekly, monthly)</li>
                        <li>Add email recipients</li>
                        <li>Reports automatically emailed on schedule</li>
                    </ol>
                </div>
            @endif

            @if($activeSection === 'alerts')
                <div class="prose prose-invert max-w-none">
                    <h1>Alerts & Notifications</h1>
                    <p>Stay informed about critical events and conditions.</p>

                    <h2>Alert Types</h2>
                    
                    <h3>Machine Alerts</h3>
                    <ul>
                        <li>Machine offline</li>
                        <li>Status change</li>
                        <li>Speeding violations</li>
                        <li>Harsh braking</li>
                    </ul>

                    <h3>Fuel Alerts</h3>
                    <ul>
                        <li>Tank low</li>
                        <li>Tank critical</li>
                        <li>High consumption</li>
                        <li>Unusual patterns</li>
                        <li>Budget exceeded</li>
                    </ul>

                    <h3>Maintenance Alerts</h3>
                    <ul>
                        <li>Service due</li>
                        <li>Service overdue</li>
                        <li>Health warning</li>
                        <li>Critical health</li>
                        <li>Fault codes</li>
                    </ul>

                    <h3>Geofence Alerts</h3>
                    <ul>
                        <li>Unauthorized entry</li>
                        <li>Excessive dwell time</li>
                        <li>After-hours activity</li>
                    </ul>

                    <h2>Configuring Alerts</h2>
                    <ol>
                        <li>Go to <strong>Alerts</strong></li>
                        <li>Click <strong>"Configure Alerts"</strong></li>
                        <li>Select alert type</li>
                        <li>Set thresholds/conditions</li>
                        <li>Choose notification method (email, SMS, push)</li>
                        <li>Add recipients</li>
                        <li>Save</li>
                    </ol>

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
                        <li><strong>Snooze:</strong> Temporarily silence</li>
                        <li><strong>Escalate:</strong> Notify additional people</li>
                    </ul>
                </div>
            @endif

            @if($activeSection === 'mine-areas')
                <div class="prose prose-invert max-w-none">
                    <h1>Mine Areas</h1>
                    <p>Define and manage operational areas within your mine site.</p>

                    <h2>Area Types</h2>
                    <ul>
                        <li><strong>Pit:</strong> Open pit mining areas</li>
                        <li><strong>Stockpile:</strong> Material storage areas</li>
                        <li><strong>Processing:</strong> Crushing, screening, processing facilities</li>
                        <li><strong>Dump:</strong> Waste dump areas</li>
                        <li><strong>Facility:</strong> Administrative and support buildings</li>
                    </ul>

                    <h2>Creating Mine Areas</h2>
                    <ol>
                        <li>Navigate to <strong>Mine Areas</strong></li>
                        <li>Click <strong>"Create Area"</strong></li>
                        <li>Draw boundary on map</li>
                        <li>Set area type and details</li>
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

                    <h2>Dashboard Widgets</h2>
                    <ul>
                        <li><strong>Fleet Status:</strong> Active/idle/offline machine counts</li>
                        <li><strong>Live Map:</strong> Real-time machine locations</li>
                        <li><strong>Recent Alerts:</strong> Latest notifications</li>
                        <li><strong>Fuel Summary:</strong> Current fuel levels and consumption</li>
                        <li><strong>Maintenance Due:</strong> Upcoming service schedules</li>
                        <li><strong>Performance Metrics:</strong> KPIs and statistics</li>
                    </ul>

                    <h2>Customizing Dashboard</h2>
                    <ol>
                        <li>Click <strong>"Customize"</strong> button</li>
                        <li>Drag widgets to rearrange</li>
                        <li>Click widget settings to configure</li>
                        <li>Add/remove widgets as needed</li>
                        <li>Save layout</li>
                    </ol>
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
                        <li>Breadcrumb trails</li>
                        <li>Stop detection</li>
                        <li>Distance calculations</li>
                        <li>Export to KML/GPX</li>
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
                                    <th>Admin</th>
                                    <th>Manager</th>
                                    <th>Operator</th>
                                    <th>Viewer</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>View dashboard</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                </tr>
                                <tr>
                                    <td>View machines</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                </tr>
                                <tr>
                                    <td>Create/edit machines</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>-</td>
                                    <td>-</td>
                                </tr>
                                <tr>
                                    <td>Delete machines</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                </tr>
                                <tr>
                                    <td>Record fuel transactions</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>-</td>
                                </tr>
                                <tr>
                                    <td>Create work orders</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>-</td>
                                    <td>-</td>
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
                                    <td>Manage team</td>
                                    <td>✓</td>
                                    <td>✓</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                </tr>
                                <tr>
                                    <td>Manage billing</td>
                                    <td>✓</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if($activeSection === 'settings')
                <div class="prose prose-invert max-w-none">
                    <h1>Settings</h1>
                    <p>Configure your Mines platform.</p>

                    <h2>General Settings</h2>
                    <ul>
                        <li><strong>Company Name:</strong> Your organization name</li>
                        <li><strong>Time Zone:</strong> Local time zone for reports</li>
                        <li><strong>Date Format:</strong> Preferred date display format</li>
                        <li><strong>Units:</strong> Metric or Imperial measurements</li>
                    </ul>

                    <h2>Notification Settings</h2>
                    <ul>
                        <li>Email notification preferences</li>
                        <li>SMS alert settings</li>
                        <li>Push notification configuration</li>
                        <li>Alert frequency</li>
                    </ul>

                    <h2>API Settings</h2>
                    <ul>
                        <li>Generate API tokens</li>
                        <li>View API usage</li>
                        <li>Configure webhooks</li>
                        <li>API rate limits</li>
                    </ul>

                    <h2>Security Settings</h2>
                    <ul>
                        <li>Two-factor authentication</li>
                        <li>Session timeout</li>
                        <li>IP whitelist</li>
                        <li>Audit logs</li>
                    </ul>
                </div>
            @endif
        </div>
    </div>
</div>
