<?php

return [
    /**
     * Supported manufacturers and their configurations
     */
    'manufacturers' => [
        'volvo' => [
            'name' => 'Volvo',
            'api_system' => 'CareTrack',
            'base_url' => env('VOLVO_API_BASE_URL', 'https://api.volvoce.com'),
            'api_version' => 'v1',
            'api_key_env' => 'VOLVO_API_KEY',
            'api_secret_env' => 'VOLVO_API_SECRET',
            'client_id_env' => 'VOLVO_CLIENT_ID',
            'client_secret_env' => 'VOLVO_CLIENT_SECRET',
            'webhook_url_env' => 'VOLVO_WEBHOOK_URL',
            'auth_type' => 'oauth2', // OAuth 2.0 Client Credentials
            'token_endpoint' => '/auth/oauth2/token',
            'supported_endpoints' => [
                'machines' => '/connected-machines/v1/machines',
                'location' => '/connected-machines/v1/machines/{id}/location',
                'telemetry' => '/connected-machines/v1/machines/{id}/telemetry',
                'health' => '/connected-machines/v1/machines/{id}/health',
                'utilization' => '/connected-machines/v1/machines/{id}/utilization',
                'fuel' => '/connected-machines/v1/machines/{id}/fuel',
            ],
            'sync_interval' => 300, // 5 minutes
            'retry_attempts' => 3,
            'rate_limit' => 100, // requests per minute
            'documentation' => 'https://developer.volvoce.com/caretrack-api',
        ],
        'cat' => [
            'name' => 'Caterpillar',
            'api_system' => 'VisionLink / Product Link',
            'base_url' => env('CAT_API_BASE_URL', 'https://api.cat.com/visionlink'),
            'api_version' => 'v2',
            'api_key_env' => 'CAT_API_KEY',
            'api_secret_env' => 'CAT_API_SECRET',
            'dealer_code_env' => 'CAT_DEALER_CODE', // Requires dealer authorization
            'subscription_id_env' => 'CAT_SUBSCRIPTION_ID',
            'webhook_url_env' => 'CAT_WEBHOOK_URL',
            'auth_type' => 'api_key', // API Key in header
            'auth_header' => 'X-API-Key',
            'supported_endpoints' => [
                'assets' => '/assets',
                'location' => '/assets/{assetId}/location',
                'diagnostics' => '/assets/{assetId}/diagnostics',
                'fuelUsed' => '/assets/{assetId}/fuelUsed',
                'engineHours' => '/assets/{assetId}/engineHours',
                'productivityData' => '/assets/{assetId}/productivity',
            ],
            'sync_interval' => 300,
            'retry_attempts' => 3,
            'rate_limit' => 60, // requests per minute
            'requires_dealer_auth' => true,
            'documentation' => 'https://developer.cat.com/api-catalog/visionlink',
            'notes' => 'Requires dealer authorization and subscription ID',
        ],
        'komatsu' => [
            'name' => 'Komatsu',
            'api_system' => 'KOMTRAX',
            'base_url' => env('KOMATSU_API_BASE_URL', 'https://api.komtrax.com'),
            'api_version' => 'v2',
            'api_key_env' => 'KOMATSU_API_KEY',
            'api_secret_env' => 'KOMATSU_API_SECRET',
            'customer_id_env' => 'KOMATSU_CUSTOMER_ID',
            'webhook_url_env' => 'KOMATSU_WEBHOOK_URL',
            'auth_type' => 'oauth2', // OAuth 2.0
            'token_endpoint' => '/oauth/token',
            'supported_endpoints' => [
                'machines' => '/api/v2/machines',
                'location' => '/api/v2/machines/{machineId}/location',
                'operatingHours' => '/api/v2/machines/{machineId}/operating-hours',
                'fuelConsumption' => '/api/v2/machines/{machineId}/fuel-consumption',
                'cautions' => '/api/v2/machines/{machineId}/cautions',
                'workingModes' => '/api/v2/machines/{machineId}/working-modes',
                'status' => '/api/v2/machines/{machineId}/status',
            ],
            'sync_interval' => 300,
            'retry_attempts' => 3,
            'rate_limit' => 50, // requests per minute
            'requires_representative_contact' => true,
            'documentation' => 'Contact Komatsu representative for API access',
            'notes' => 'Requires customer ID and Komatsu representative approval',
        ],
        'bell' => [
            'name' => 'Bell',
            'api_system' => 'Fleetmatic',
            'team_id' => (int) env('BELL_TEAM_ID', 0),
            'base_url' => env('BELL_API_BASE_URL', 'https://b-fleet03.bellequipment.com:8080'),
            'api_version' => 'v1',
            'api_key_env' => 'BELL_API_KEY',
            'api_secret_env' => 'BELL_API_SECRET',
            'account_id_env' => 'BELL_ACCOUNT_ID',
            'webhook_url_env' => 'BELL_WEBHOOK_URL',
            'auth_type' => 'bearer_token', // Bearer Token Authentication
            'fleet_endpoint' => '/Fleet',
            'supported_endpoints' => [
                // Pattern: /Fleet/Equipment/{OEM ISO Identifier}/{Signal}/{startDateUTC}/{endDateUTC}
                'fleet_snapshot' => '/Fleet',
                'locations' => '/Fleet/Equipment/{id}/Locations/{from}/{to}',
                'operating_hours' => '/Fleet/Equipment/{id}/CumulativeOperatingHours/{from}/{to}',
                'fuel_used_cumulative' => '/Fleet/Equipment/{id}/CumulativeFuelUsed/{from}/{to}',
                'fuel_used_24h' => '/Fleet/Equipment/{id}/FuelUsedInThePreceding24Hours/{from}/{to}',
                'distance' => '/Fleet/Equipment/{id}/Distance/{from}/{to}',
                'caution_codes' => '/Fleet/Equipment/{id}/CautionCodes/{from}/{to}',
                'idle_hours' => '/Fleet/Equipment/{id}/CumulativeIdleHours/{from}/{to}',
                'fuel_remaining_ratio' => '/Fleet/Equipment/{id}/FuelRemainingRatio/{from}/{to}',
                'def_remaining' => '/Fleet/Equipment/{id}/DEFRemaining/{from}/{to}',
                'engine_condition' => '/Fleet/Equipment/{id}/EngineCondition/{from}/{to}',
                'load_count' => '/Fleet/Equipment/{id}/CumulativeLoadCount/{from}/{to}',
                'payload_totals' => '/Fleet/Equipment/{id}/CumulativePayloadTotals/{from}/{to}',
                'active_regen_hours' => '/Fleet/Equipment/{id}/CumulativeActiveRegenerationHours/{from}/{to}',
            ],
            'sync_interval' => 300,
            'retry_attempts' => 3,
            'rate_limit' => 80, // requests per minute
            'requires_bell_contact' => true,
            'documentation' => 'Contact Bell Equipment for API access',
            'notes' => 'Requires Bell account ID and API access approval',
        ],
        'hitachi' => [
            'name' => 'Hitachi Construction Machinery',
            'api_system' => 'ConSite',
            'base_url' => env('HITACHI_API_BASE_URL', 'https://api.consite.com'),
            'api_version' => 'v2',
            'api_key_env' => 'HITACHI_API_KEY',
            'api_secret_env' => 'HITACHI_API_SECRET',
            'customer_code_env' => 'HITACHI_CUSTOMER_CODE',
            'webhook_url_env' => 'HITACHI_WEBHOOK_URL',
            'auth_type' => 'oauth2',
            'token_endpoint' => '/api/v2/oauth/token',
            'supported_endpoints' => [
                'machines' => '/api/v2/machines',
                'location' => '/api/v2/machines/{machineId}/location',
                'status' => '/api/v2/machines/{machineId}/status',
                'operating_hours' => '/api/v2/machines/{machineId}/operating-hours',
                'maintenance' => '/api/v2/machines/{machineId}/maintenance',
                'diagnostics' => '/api/v2/machines/{machineId}/diagnostics',
                'alerts' => '/api/v2/machines/{machineId}/alerts',
            ],
            'sync_interval' => 300,
            'retry_attempts' => 3,
            'rate_limit' => 60,
            'documentation' => 'https://www.consite.com/api-docs',
            'notes' => 'Requires Hitachi customer code',
        ],
        'jcb' => [
            'name' => 'JCB',
            'api_system' => 'LiveLink',
            'base_url' => env('JCB_API_BASE_URL', 'https://api.jcblivelink.com'),
            'api_version' => 'v1',
            'api_key_env' => 'JCB_API_KEY',
            'api_secret_env' => 'JCB_API_SECRET',
            'dealer_id_env' => 'JCB_DEALER_ID',
            'webhook_url_env' => 'JCB_WEBHOOK_URL',
            'auth_type' => 'api_key',
            'auth_header' => 'X-API-Key',
            'supported_endpoints' => [
                'machines' => '/livelink/v1/machines',
                'location' => '/livelink/v1/machines/{machineId}/location',
                'telemetry' => '/livelink/v1/machines/{machineId}/telemetry',
                'utilization' => '/livelink/v1/machines/{machineId}/utilization',
                'service' => '/livelink/v1/machines/{machineId}/service',
                'alerts' => '/livelink/v1/machines/{machineId}/alerts',
            ],
            'sync_interval' => 300,
            'retry_attempts' => 3,
            'rate_limit' => 100,
            'documentation' => 'https://developer.jcb.com/livelink-api',
            'notes' => 'Requires JCB dealer ID',
        ],
        'liebherr' => [
            'name' => 'Liebherr',
            'api_system' => 'LiDAT',
            'base_url' => env('LIEBHERR_API_BASE_URL', 'https://api.lidat.com'),
            'api_version' => 'v2',
            'api_key_env' => 'LIEBHERR_API_KEY',
            'api_secret_env' => 'LIEBHERR_API_SECRET',
            'customer_id_env' => 'LIEBHERR_CUSTOMER_ID',
            'webhook_url_env' => 'LIEBHERR_WEBHOOK_URL',
            'auth_type' => 'oauth2',
            'token_endpoint' => '/api/v2/auth/token',
            'supported_endpoints' => [
                'machines' => '/api/v2/equipment',
                'location' => '/api/v2/equipment/{equipmentId}/position',
                'operating_data' => '/api/v2/equipment/{equipmentId}/operating-data',
                'service_intervals' => '/api/v2/equipment/{equipmentId}/service-intervals',
                'error_codes' => '/api/v2/equipment/{equipmentId}/error-codes',
                'telemetry' => '/api/v2/equipment/{equipmentId}/telemetry',
            ],
            'sync_interval' => 300,
            'retry_attempts' => 3,
            'rate_limit' => 50,
            'documentation' => 'https://www.liebherr.com/lidat-api',
            'notes' => 'Requires Liebherr customer ID',
        ],
        'sany' => [
            'name' => 'Sany Heavy Industry',
            'api_system' => 'SUMS (Sany Unique Management System)',
            'base_url' => env('SANY_API_BASE_URL', 'https://api.sanycloud.com'),
            'api_version' => 'v1',
            'api_key_env' => 'SANY_API_KEY',
            'api_secret_env' => 'SANY_API_SECRET',
            'enterprise_id_env' => 'SANY_ENTERPRISE_ID',
            'webhook_url_env' => 'SANY_WEBHOOK_URL',
            'auth_type' => 'api_key',
            'auth_header' => 'Authorization',
            'supported_endpoints' => [
                'devices' => '/open/v1/devices',
                'location' => '/open/v1/devices/{deviceId}/location',
                'realtime_data' => '/open/v1/devices/{deviceId}/realtime',
                'working_hours' => '/open/v1/devices/{deviceId}/working-hours',
                'alarms' => '/open/v1/devices/{deviceId}/alarms',
                'statistics' => '/open/v1/devices/{deviceId}/statistics',
            ],
            'sync_interval' => 300,
            'retry_attempts' => 3,
            'rate_limit' => 60,
            'documentation' => 'Contact Sany representative for API access',
            'notes' => 'Requires Sany enterprise ID',
        ],
        'doosan' => [
            'name' => 'Doosan Infracore',
            'api_system' => 'DoosanCONNECT',
            'base_url' => env('DOOSAN_API_BASE_URL', 'https://api.doosanconnect.com'),
            'api_version' => 'v2',
            'api_key_env' => 'DOOSAN_API_KEY',
            'api_secret_env' => 'DOOSAN_API_SECRET',
            'account_id_env' => 'DOOSAN_ACCOUNT_ID',
            'webhook_url_env' => 'DOOSAN_WEBHOOK_URL',
            'auth_type' => 'bearer_token',
            'token_endpoint' => '/api/v2/auth/token',
            'supported_endpoints' => [
                'machines' => '/api/v2/machines',
                'location' => '/api/v2/machines/{machineId}/location',
                'operation' => '/api/v2/machines/{machineId}/operation',
                'fuel' => '/api/v2/machines/{machineId}/fuel',
                'maintenance' => '/api/v2/machines/{machineId}/maintenance',
                'warnings' => '/api/v2/machines/{machineId}/warnings',
            ],
            'sync_interval' => 300,
            'retry_attempts' => 3,
            'rate_limit' => 80,
            'documentation' => 'https://developer.doosan.com/connect-api',
            'notes' => 'Requires Doosan account ID',
        ],
        'hyundai' => [
            'name' => 'Hyundai Construction Equipment',
            'api_system' => 'Hi-MATE',
            'base_url' => env('HYUNDAI_API_BASE_URL', 'https://api.hi-mate.com'),
            'api_version' => 'v1',
            'api_key_env' => 'HYUNDAI_API_KEY',
            'api_secret_env' => 'HYUNDAI_API_SECRET',
            'dealer_code_env' => 'HYUNDAI_DEALER_CODE',
            'webhook_url_env' => 'HYUNDAI_WEBHOOK_URL',
            'auth_type' => 'oauth2',
            'token_endpoint' => '/oauth/v1/token',
            'supported_endpoints' => [
                'equipment' => '/api/v1/equipment',
                'location' => '/api/v1/equipment/{equipmentId}/location',
                'working_info' => '/api/v1/equipment/{equipmentId}/working-info',
                'engine_data' => '/api/v1/equipment/{equipmentId}/engine-data',
                'service_info' => '/api/v1/equipment/{equipmentId}/service-info',
                'notifications' => '/api/v1/equipment/{equipmentId}/notifications',
            ],
            'sync_interval' => 300,
            'retry_attempts' => 3,
            'rate_limit' => 70,
            'documentation' => 'https://www.hi-mate.com/api-documentation',
            'notes' => 'Requires Hyundai dealer code',
        ],
        'xcmg' => [
            'name' => 'XCMG',
            'api_system' => 'Xrea (XCMG Remote Expert Assistant)',
            'base_url' => env('XCMG_API_BASE_URL', 'https://api.xcmg-iot.com'),
            'api_version' => 'v1',
            'api_key_env' => 'XCMG_API_KEY',
            'api_secret_env' => 'XCMG_API_SECRET',
            'company_id_env' => 'XCMG_COMPANY_ID',
            'webhook_url_env' => 'XCMG_WEBHOOK_URL',
            'auth_type' => 'api_key',
            'auth_header' => 'X-API-Key',
            'supported_endpoints' => [
                'devices' => '/iot/v1/devices',
                'location' => '/iot/v1/devices/{deviceId}/location',
                'status' => '/iot/v1/devices/{deviceId}/status',
                'parameters' => '/iot/v1/devices/{deviceId}/parameters',
                'faults' => '/iot/v1/devices/{deviceId}/faults',
                'work_data' => '/iot/v1/devices/{deviceId}/work-data',
            ],
            'sync_interval' => 300,
            'retry_attempts' => 3,
            'rate_limit' => 60,
            'documentation' => 'Contact XCMG for API access',
            'notes' => 'Requires XCMG company ID',
        ],
        'epiroc' => [
            'name' => 'Epiroc (Atlas Copco)',
            'api_system' => 'Certiq',
            'base_url' => env('EPIROC_API_BASE_URL', 'https://api.certiq.com'),
            'api_version' => 'v2',
            'api_key_env' => 'EPIROC_API_KEY',
            'api_secret_env' => 'EPIROC_API_SECRET',
            'customer_id_env' => 'EPIROC_CUSTOMER_ID',
            'webhook_url_env' => 'EPIROC_WEBHOOK_URL',
            'auth_type' => 'oauth2',
            'token_endpoint' => '/api/v2/oauth/token',
            'supported_endpoints' => [
                'equipment' => '/api/v2/equipment',
                'location' => '/api/v2/equipment/{equipmentId}/location',
                'performance' => '/api/v2/equipment/{equipmentId}/performance',
                'production' => '/api/v2/equipment/{equipmentId}/production',
                'maintenance' => '/api/v2/equipment/{equipmentId}/maintenance',
                'events' => '/api/v2/equipment/{equipmentId}/events',
            ],
            'sync_interval' => 300,
            'retry_attempts' => 3,
            'rate_limit' => 50,
            'documentation' => 'https://certiq.com/api-documentation',
            'notes' => 'Requires Epiroc customer ID and Certiq subscription',
        ],
        'kubota' => [
            'name' => 'Kubota',
            'api_system' => 'Kubota Diagnostics',
            'base_url' => env('KUBOTA_API_BASE_URL', 'https://api.kubota-eu.com'),
            'api_version' => 'v1',
            'api_key_env' => 'KUBOTA_API_KEY',
            'api_secret_env' => 'KUBOTA_API_SECRET',
            'dealer_id_env' => 'KUBOTA_DEALER_ID',
            'webhook_url_env' => 'KUBOTA_WEBHOOK_URL',
            'auth_type' => 'api_key',
            'auth_header' => 'Authorization',
            'supported_endpoints' => [
                'machines' => '/api/v1/machines',
                'location' => '/api/v1/machines/{machineId}/location',
                'telemetry' => '/api/v1/machines/{machineId}/telemetry',
                'diagnostics' => '/api/v1/machines/{machineId}/diagnostics',
                'service' => '/api/v1/machines/{machineId}/service-history',
            ],
            'sync_interval' => 300,
            'retry_attempts' => 3,
            'rate_limit' => 50,
            'documentation' => 'Contact Kubota dealer for API access',
            'notes' => 'Requires Kubota dealer ID',
        ],
        'kobelco' => [
            'name' => 'Kobelco',
            'api_system' => 'KIMS (Kobelco Information Management System)',
            'base_url' => env('KOBELCO_API_BASE_URL', 'https://api.kobelco-kims.com'),
            'api_version' => 'v1',
            'api_key_env' => 'KOBELCO_API_KEY',
            'api_secret_env' => 'KOBELCO_API_SECRET',
            'customer_code_env' => 'KOBELCO_CUSTOMER_CODE',
            'webhook_url_env' => 'KOBELCO_WEBHOOK_URL',
            'auth_type' => 'bearer_token',
            'token_endpoint' => '/api/v1/auth/token',
            'supported_endpoints' => [
                'machines' => '/api/v1/machines',
                'location' => '/api/v1/machines/{machineId}/location',
                'operating_status' => '/api/v1/machines/{machineId}/operating-status',
                'work_records' => '/api/v1/machines/{machineId}/work-records',
                'alerts' => '/api/v1/machines/{machineId}/alerts',
            ],
            'sync_interval' => 300,
            'retry_attempts' => 3,
            'rate_limit' => 60,
            'documentation' => 'Contact Kobelco for KIMS API access',
            'notes' => 'Requires Kobelco customer code',
        ],
        'ctrack' => [
            'name' => 'C-Track',
            'api_system' => 'C-Track Fleet Management',
            'base_url' => env('CTRACK_API_BASE_URL', 'https://www.ctrack.com/api'),
            'api_version' => 'v3',
            'api_key_env' => 'CTRACK_API_KEY',
            'api_secret_env' => 'CTRACK_API_SECRET',
            'account_id_env' => 'CTRACK_ACCOUNT_ID',
            'webhook_url_env' => 'CTRACK_WEBHOOK_URL',
            'auth_type' => 'basic_auth',
            'supported_endpoints' => [
                'vehicles' => '/v3/vehicles',
                'location' => '/v3/vehicles/{vehicleId}/location',
                'history' => '/v3/vehicles/{vehicleId}/history',
                'events' => '/v3/vehicles/{vehicleId}/events',
                'geofences' => '/v3/geofences',
            ],
            'sync_interval' => 300,
            'retry_attempts' => 3,
            'rate_limit' => 100,
            'documentation' => 'https://www.ctrack.com/api-documentation',
            'notes' => 'GPS tracking and fleet management system',
        ],
        'roundebult' => [
            'name' => 'Roundebult',
            'api_system' => 'Roundebult Fleet Management',
            'base_url' => env('ROUNDEBULT_API_BASE_URL', 'https://api.roundebult.com'),
            'api_version' => 'v1',
            'api_key_env' => 'ROUNDEBULT_API_KEY',
            'api_secret_env' => 'ROUNDEBULT_API_SECRET',
            'client_id_env' => 'ROUNDEBULT_CLIENT_ID',
            'webhook_url_env' => 'ROUNDEBULT_WEBHOOK_URL',
            'auth_type' => 'bearer_token',
            'token_endpoint' => '/api/v1/auth/token',
            'supported_endpoints' => [
                'machines' => '/api/v1/machines',
                'location' => '/api/v1/machines/{machineId}/location',
                'metrics' => '/api/v1/machines/{machineId}/metrics',
                'operations' => '/api/v1/machines/{machineId}/operations',
                'alerts' => '/api/v1/machines/{machineId}/alerts',
            ],
            'sync_interval' => 300,
            'retry_attempts' => 3,
            'rate_limit' => 60,
            'documentation' => 'Contact Roundebult for API access',
            'notes' => 'South African fleet management provider',
        ],
    ],

    /**
     * Global integration settings
     */
    'global' => [
        'default_retry_attempts' => 3,
        'default_timeout' => 30,
        'default_sync_interval' => 300,
        'enable_webhooks' => env('INTEGRATION_WEBHOOKS_ENABLED', true),
        'enable_background_jobs' => env('INTEGRATION_BACKGROUND_JOBS_ENABLED', true),
        'log_api_calls' => env('INTEGRATION_LOG_API_CALLS', false),
    ],

    /**
     * Sync job configurations
     */
    'jobs' => [
        'machines_sync_interval' => env('SYNC_MACHINES_INTERVAL', 300), // 5 minutes
        'metrics_sync_interval' => env('SYNC_METRICS_INTERVAL', 60), // 1 minute
        'alerts_sync_interval' => env('SYNC_ALERTS_INTERVAL', 60), // 1 minute
        'retry_on_failure' => true,
        'queue_name' => env('INTEGRATION_QUEUE', 'default'),
    ],

    /**
     * Alert mappings from manufacturer formats to standard
     */
    'alert_mappings' => [
        'types' => [
            'temperature' => ['temp', 'temperature_warning', 'overtemp'],
            'fuel' => ['fuel_level', 'fuel_warning', 'low_fuel'],
            'maintenance' => ['maintenance_due', 'service_due', 'maintenance_alert'],
            'sensor' => ['sensor_fault', 'error', 'warning'],
            'geofence' => ['geofence_breach', 'zone_breach', 'boundary_breach'],
            'downtime' => ['downtime', 'idle', 'offline'],
        ],
        'priorities' => [
            'critical' => ['critical', 'emergency', 'severe'],
            'high' => ['high', 'warning', 'alert'],
            'medium' => ['medium', 'caution', 'notice'],
            'low' => ['low', 'info', 'informational'],
        ],
    ],

    /**
     * Bell Equipment SSO — OAuth2 Password Credentials grant.
     *
     * Used to obtain a bearer token before calling any Bell API endpoint.
     * Credentials are sent as a Basic Authentication header (client_id:client_secret).
     *
     * Token name: SSO_Token
     * Grant type: password
     * Scope:      ISO_Exports
     */
    'bell_sso' => [
        'token_url' => env('BELL_SSO_TOKEN_URL', 'https://sso.bellequipment.com/connect/token'),
        'grant_type' => env('BELL_SSO_GRANT_TYPE', 'password'),
        'client_id' => env('BELL_SSO_CLIENT_ID', 'ISO_Export_Service'),
        'client_secret' => env('BELL_SSO_CLIENT_SECRET', ''),
        'username' => env('BELL_SSO_USERNAME', ''),
        'password' => env('BELL_SSO_PASSWORD', ''),
        'scope' => env('BELL_SSO_SCOPE', 'ISO_Exports'),
    ],

    /**
     * Bell ISO15143-3 (AEMP) fleet API configuration.
     *
     * Set BELL_ISO15143_API_URL, BELL_ISO15143_USERNAME, and
     * BELL_ISO15143_PASSWORD in your .env file.
     */
    'bell_iso15143' => [
        'api_url' => env('BELL_ISO15143_API_URL', ''),
        'client_id' => env('BELL_ISO15143_CLIENT_ID', 'ISO_Export_Service'),
        'api_username' => env('BELL_ISO15143_USERNAME', ''),
        'api_password' => env('BELL_ISO15143_PASSWORD', ''),
        'client_secret' => env('BELL_ISO15143_CLIENT_SECRET', ''),
    ],

    /**
     * Bell Fleetmatic REST API – historical telemetry endpoints.
     *
     * Used by SyncBellHistoricalDataJob (hourly) to backfill location trail,
     * fuel usage, operating hours, idle hours, and load count per machine.
     *
     * Set BELL_HISTORICAL_BASE_URL, BELL_HISTORICAL_USERNAME, and
     * BELL_HISTORICAL_PASSWORD in your .env file.
     * Defaults to the same Fleetmatic base URL as the main Bell integration.
     */
    'bell_historical' => [
        'base_url' => env('BELL_HISTORICAL_BASE_URL', env('BELL_API_BASE_URL', '')),
        'api_username' => env('BELL_HISTORICAL_USERNAME', env('BELL_ISO15143_USERNAME', '')),
        'api_password' => env('BELL_HISTORICAL_PASSWORD', env('BELL_ISO15143_PASSWORD', '')),
    ],
];
