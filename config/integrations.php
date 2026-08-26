<?php

return [
    /**
     * How far back a machine's first production sync reaches into the
     * provider's historical cumulative-counter time series (days). Later
     * syncs only re-fetch from the machine's most recent production
     * record forward.
     */
    'production_backfill_days' => env('INTEGRATIONS_PRODUCTION_BACKFILL_DAYS', 14),

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
            'api_system' => 'ISO 15143-3 (AEMP 2.0) Fleet API',
            // Real endpoints from Bell's own "BELL_ISO15143-3 SSO" Postman
            // collection -- the previous 'Fleetmatic' base_url/paths below it
            // were guessed and never matched a real Bell endpoint.
            'base_url' => env('BELL_API_BASE_URL', 'https://b-fleet03.bellequipment.com:8080'),
            'token_url' => env('BELL_TOKEN_URL', 'https://sso.bellequipment.com/connect/token'),
            'api_version' => null, // ISO 15143-3 itself is the versioning; no /v1 path segment.
            // The fleet snapshot is PAGINATED: /Fleet/{page}, 1-based. Bare
            // /Fleet answers 405 to every verb. This account returns its
            // whole fleet on page 1 and answers 400 for page 2, so the
            // default fetches one page -- each extra page costs a live
            // request against a limiter that has throttled this server
            // before (2026-08-21). Raise it if a fleet outgrows one page.
            'max_fleet_pages' => (int) env('BELL_MAX_FLEET_PAGES', 1),
            // Bell rate-limits HARD and answers 405 (not 429) when it
            // throttles: measured live on 2026-08-26, a second /Fleet/1
            // call 30 seconds after a successful one was rejected, and two
            // concurrent calls were both rejected. Bell's own data cadence
            // is 15 minutes, so caching a successful snapshot for that long
            // costs no freshness and keeps the whole app inside ~4 calls an
            // hour -- the location, status and sync jobs all share it.
            'fleet_cache_seconds' => (int) env('BELL_FLEET_CACHE_SECONDS', 900),
            'client_id_env' => 'BELL_CLIENT_ID', // Bell issues 'ISO_Export_Service' to every ISO export consumer.
            'scope' => 'ISO_Exports',
            'username_env' => 'BELL_USERNAME',
            'password_env' => 'BELL_PASSWORD',
            'client_secret_env' => 'BELL_CLIENT_SECRET',
            // OAuth2 Resource Owner Password Credentials (RFC 6749 §4.3):
            // grant_type=password, plus client_id/client_secret, against
            // token_url above. Distinct from the 'oauth2' (client credentials)
            // and 'bearer_token' (pre-issued static token) auth types used by
            // other manufacturers in this file.
            'auth_type' => 'oauth2_password',
            'supported_endpoints' => [
                'fleet' => '/Fleet/{page}', // paginated, 1-based; bare /Fleet answers 405
                'locations' => '/Fleet/Equipment/{equipmentId}/Locations/{startDateUTC}/{endDateUTC}',
                'operatingHours' => '/Fleet/Equipment/{equipmentId}/CumulativeOperatingHours/{startDateUTC}/{endDateUTC}',
                'idleHours' => '/Fleet/Equipment/{equipmentId}/CumulativeIdleHours/{startDateUTC}/{endDateUTC}',
                'fuelUsed' => '/Fleet/Equipment/{equipmentId}/CumulativeFuelUsed/{startDateUTC}/{endDateUTC}',
                'fuelUsedLast24Hours' => '/Fleet/Equipment/{equipmentId}/FuelUsedInThePreceding24Hours/{startDateUTC}/{endDateUTC}',
                'fuelRemainingRatio' => '/Fleet/Equipment/{equipmentId}/FuelRemainingRatio/{startDateUTC}/{endDateUTC}',
                'defRemaining' => '/Fleet/Equipment/{equipmentId}/DEFRemaining/{startDateUTC}/{endDateUTC}',
                'distance' => '/Fleet/Equipment/{equipmentId}/Distance/{startDateUTC}/{endDateUTC}',
                'cautionCodes' => '/Fleet/Equipment/{equipmentId}/CautionCodes/{startDateUTC}/{endDateUTC}',
                'engineCondition' => '/Fleet/Equipment/{equipmentId}/EngineCondition/{startDateUTC}/{endDateUTC}',
                'loadCount' => '/Fleet/Equipment/{equipmentId}/CumulativeLoadCount/{startDateUTC}/{endDateUTC}',
                'payloadTotals' => '/Fleet/Equipment/{equipmentId}/CumulativePayloadTotals/{startDateUTC}/{endDateUTC}',
                'regenerationHours' => '/Fleet/Equipment/{equipmentId}/CumulativeActiveRegenerationHours/{startDateUTC}/{endDateUTC}',
            ],
            'sync_interval' => 900, // Bell's own reference spec suggests polling every 15 minutes.
            'retry_attempts' => 3,
            'rate_limit' => 80, // requests per minute
            'requires_bell_contact' => true,
            'documentation' => 'Contact Bell Equipment to be issued an ISO 15143-3 export account (username/password + client secret for the ISO_Export_Service client)',
            'notes' => 'Requires a Bell-issued ISO 15143-3 export account. Implemented against Bell\'s published Postman collection; response XML shape has not yet been confirmed against a live sync -- see BellService docblock.',
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
];
