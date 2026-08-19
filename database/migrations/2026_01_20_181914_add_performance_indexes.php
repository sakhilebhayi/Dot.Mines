<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add performance indexes to optimize frequently queried columns
     */
    public function up(): void
    {
        // Skip if critical tables don't exist (e.g., during selective test migrations)
        if (! Schema::hasTable('machines') || ! Schema::hasTable('alerts')) {
            return;
        }

        // Helper function to check if an index exists, portable across DB drivers
        // (this used to run a raw sqlite_master query, which only worked on SQLite
        // and threw "relation sqlite_master does not exist" on PostgreSQL/MySQL —
        // Schema::hasIndex() is Laravel's driver-agnostic equivalent).
        $indexExists = function ($table, $indexName) {
            return Schema::hasIndex($table, $indexName);
        };

        // Helper function to check if table exists
        $tableExists = function ($table) {
            return Schema::hasTable($table);
        };

        // Machines table indexes
        if ($tableExists('machines')) {
            if (! $indexExists('machines', 'idx_machines_status')) {
                Schema::table('machines', function (Blueprint $table) {
                    $table->index('status', 'idx_machines_status');
                });
            }
            if (! $indexExists('machines', 'idx_machines_team_status')) {
                Schema::table('machines', function (Blueprint $table) {
                    $table->index(['team_id', 'status'], 'idx_machines_team_status');
                });
            }
            if (! $indexExists('machines', 'idx_machines_location')) {
                Schema::table('machines', function (Blueprint $table) {
                    $table->index(['last_location_latitude', 'last_location_longitude'], 'idx_machines_location');
                });
            }
            if (! $indexExists('machines', 'idx_machines_type')) {
                Schema::table('machines', function (Blueprint $table) {
                    $table->index('machine_type', 'idx_machines_type');
                });
            }
        }

        // Alerts table indexes
        if ($tableExists('alerts')) {
            if (! $indexExists('alerts', 'idx_alerts_team_status')) {
                Schema::table('alerts', function (Blueprint $table) {
                    $table->index(['team_id', 'status'], 'idx_alerts_team_status');
                });
            }
            if (! $indexExists('alerts', 'idx_alerts_machine')) {
                Schema::table('alerts', function (Blueprint $table) {
                    $table->index('machine_id', 'idx_alerts_machine');
                });
            }
            if (! $indexExists('alerts', 'idx_alerts_severity')) {
                Schema::table('alerts', function (Blueprint $table) {
                    // Real bug: this referenced a nonexistent 'alert_level' column.
                    // create_alerts_table's actual severity column is 'priority'.
                    $table->index('priority', 'idx_alerts_severity');
                });
            }
            if (! $indexExists('alerts', 'idx_alerts_status')) {
                Schema::table('alerts', function (Blueprint $table) {
                    $table->index('status', 'idx_alerts_status');
                });
            }
            if (! $indexExists('alerts', 'idx_alerts_created')) {
                Schema::table('alerts', function (Blueprint $table) {
                    $table->index('created_at', 'idx_alerts_created');
                });
            }
        }

        // Machine Metrics table indexes
        if ($tableExists('machine_metrics')) {
            if (! $indexExists('machine_metrics', 'idx_metrics_machine_time')) {
                Schema::table('machine_metrics', function (Blueprint $table) {
                    $table->index(['machine_id', 'created_at'], 'idx_metrics_machine_time');
                });
            }
            if (! $indexExists('machine_metrics', 'idx_metrics_team')) {
                Schema::table('machine_metrics', function (Blueprint $table) {
                    $table->index('team_id', 'idx_metrics_team');
                });
            }
        }

        // Geofence Entries table indexes
        if ($tableExists('geofence_entries')) {
            if (! $indexExists('geofence_entries', 'idx_geofence_entries_machine')) {
                Schema::table('geofence_entries', function (Blueprint $table) {
                    $table->index('machine_id', 'idx_geofence_entries_machine');
                });
            }
            if (! $indexExists('geofence_entries', 'idx_geofence_entries_geofence')) {
                Schema::table('geofence_entries', function (Blueprint $table) {
                    $table->index('geofence_id', 'idx_geofence_entries_geofence');
                });
            }
            if (! $indexExists('geofence_entries', 'idx_geofence_entries_entry')) {
                Schema::table('geofence_entries', function (Blueprint $table) {
                    $table->index('entry_time', 'idx_geofence_entries_entry');
                });
            }
            if (! $indexExists('geofence_entries', 'idx_geofence_entries_machine_time')) {
                Schema::table('geofence_entries', function (Blueprint $table) {
                    $table->index(['machine_id', 'entry_time'], 'idx_geofence_entries_machine_time');
                });
            }
        }

        // Geofences table indexes
        if ($tableExists('geofences')) {
            if (! $indexExists('geofences', 'idx_geofences_team_status')) {
                Schema::table('geofences', function (Blueprint $table) {
                    $table->index(['team_id', 'status'], 'idx_geofences_team_status');
                });
            }
            if (! $indexExists('geofences', 'idx_geofences_type')) {
                Schema::table('geofences', function (Blueprint $table) {
                    // Real bug: this referenced a nonexistent 'fence_type' column.
                    // create_geofences_table's actual type column is just 'type'.
                    $table->index('type', 'idx_geofences_type');
                });
            }
        }

        // Integrations table indexes
        if ($tableExists('integrations')) {
            if (! $indexExists('integrations', 'idx_integrations_team_status')) {
                Schema::table('integrations', function (Blueprint $table) {
                    $table->index(['team_id', 'status'], 'idx_integrations_team_status');
                });
            }
            if (! $indexExists('integrations', 'idx_integrations_provider')) {
                Schema::table('integrations', function (Blueprint $table) {
                    $table->index('provider', 'idx_integrations_provider');
                });
            }
            if (! $indexExists('integrations', 'idx_integrations_last_sync')) {
                Schema::table('integrations', function (Blueprint $table) {
                    $table->index('last_sync_at', 'idx_integrations_last_sync');
                });
            }
        }

        // Reports table indexes
        if ($tableExists('reports')) {
            if (! $indexExists('reports', 'idx_reports_team_type')) {
                Schema::table('reports', function (Blueprint $table) {
                    // Real bug: this referenced a nonexistent 'report_type' column.
                    // create_reports_table's actual type column is just 'type'.
                    $table->index(['team_id', 'type'], 'idx_reports_team_type');
                });
            }
            if (! $indexExists('reports', 'idx_reports_created')) {
                Schema::table('reports', function (Blueprint $table) {
                    $table->index('created_at', 'idx_reports_created');
                });
            }
        }

        // Mine Areas table indexes
        if ($tableExists('mine_areas')) {
            if (! $indexExists('mine_areas', 'idx_mine_areas_team_status')) {
                Schema::table('mine_areas', function (Blueprint $table) {
                    $table->index(['team_id', 'status'], 'idx_mine_areas_team_status');
                });
            }
            // Real bug: this used to index a nonexistent 'area_type' column —
            // create_mine_areas_table has no such column (only 'status'), and no
            // other migration ever adds one. Removed rather than inventing a
            // column/feature that was never actually built.
        }

        // Mine Plans table indexes
        if ($tableExists('mine_plans')) {
            if (! $indexExists('mine_plans', 'idx_mine_plans_area')) {
                Schema::table('mine_plans', function (Blueprint $table) {
                    $table->index('mine_area_id', 'idx_mine_plans_area');
                });
            }
            if (! $indexExists('mine_plans', 'idx_mine_plans_current')) {
                Schema::table('mine_plans', function (Blueprint $table) {
                    $table->index('is_current', 'idx_mine_plans_current');
                });
            }
            if (! $indexExists('mine_plans', 'idx_mine_plans_version')) {
                Schema::table('mine_plans', function (Blueprint $table) {
                    $table->index('version', 'idx_mine_plans_version');
                });
            }
        }

        // IoT Sensors table indexes
        if ($tableExists('iot_sensors')) {
            if (! $indexExists('iot_sensors', 'idx_iot_sensors_team_status')) {
                Schema::table('iot_sensors', function (Blueprint $table) {
                    $table->index(['team_id', 'status'], 'idx_iot_sensors_team_status');
                });
            }
            if (! $indexExists('iot_sensors', 'idx_iot_sensors_type')) {
                Schema::table('iot_sensors', function (Blueprint $table) {
                    $table->index('sensor_type', 'idx_iot_sensors_type');
                });
            }
            if (! $indexExists('iot_sensors', 'idx_iot_sensors_area')) {
                Schema::table('iot_sensors', function (Blueprint $table) {
                    $table->index('mine_area_id', 'idx_iot_sensors_area');
                });
            }
        }

        // Users table indexes (for team operations)
        if ($tableExists('users')) {
            if (! $indexExists('users', 'idx_users_current_team')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->index('current_team_id', 'idx_users_current_team');
                });
            }
        }

        // Team User pivot table indexes
        if ($tableExists('team_user')) {
            if (! $indexExists('team_user', 'idx_team_user_user')) {
                Schema::table('team_user', function (Blueprint $table) {
                    $table->index('user_id', 'idx_team_user_user');
                });
            }
            if (! $indexExists('team_user', 'idx_team_user_team')) {
                Schema::table('team_user', function (Blueprint $table) {
                    $table->index('team_id', 'idx_team_user_team');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop all indexes in reverse order
        Schema::table('team_user', function (Blueprint $table) {
            $table->dropIndex('idx_team_user_team');
            $table->dropIndex('idx_team_user_user');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_current_team');
        });

        Schema::table('iot_sensors', function (Blueprint $table) {
            $table->dropIndex('idx_iot_sensors_area');
            $table->dropIndex('idx_iot_sensors_type');
            $table->dropIndex('idx_iot_sensors_team_status');
        });

        Schema::table('mine_plans', function (Blueprint $table) {
            $table->dropIndex('idx_mine_plans_version');
            $table->dropIndex('idx_mine_plans_current');
            $table->dropIndex('idx_mine_plans_area');
        });

        Schema::table('mine_areas', function (Blueprint $table) {
            $table->dropIndex('idx_mine_areas_team_status');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex('idx_reports_created');
            $table->dropIndex('idx_reports_team_type');
        });

        Schema::table('integrations', function (Blueprint $table) {
            $table->dropIndex('idx_integrations_last_sync');
            $table->dropIndex('idx_integrations_provider');
            $table->dropIndex('idx_integrations_team_status');
        });

        Schema::table('geofences', function (Blueprint $table) {
            $table->dropIndex('idx_geofences_type');
            $table->dropIndex('idx_geofences_team_status');
        });

        Schema::table('geofence_entries', function (Blueprint $table) {
            $table->dropIndex('idx_geofence_entries_machine_time');
            $table->dropIndex('idx_geofence_entries_entry');
            $table->dropIndex('idx_geofence_entries_geofence');
            $table->dropIndex('idx_geofence_entries_machine');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_level');
            $table->dropIndex('idx_notifications_created');
            $table->dropIndex('idx_notifications_team_read');
        });

        Schema::table('machine_metrics', function (Blueprint $table) {
            $table->dropIndex('idx_metrics_team');
            $table->dropIndex('idx_metrics_machine_time');
        });

        // SQLite-safe index drops for alerts table
        $dropAlertIndexes = [
            'idx_alerts_created',
            'idx_alerts_status',
            'idx_alerts_severity',
            'idx_alerts_machine',
            'idx_alerts_team_status',
        ];
        foreach ($dropAlertIndexes as $idx) {
            try {
                DB::statement("DROP INDEX IF EXISTS $idx");
            } catch (Throwable $e) {
                // Ignore errors if index or column is missing
            }
        }

        Schema::table('machines', function (Blueprint $table) {
            $table->dropIndex('idx_machines_type');
            $table->dropIndex('idx_machines_location');
            $table->dropIndex('idx_machines_team_status');
            $table->dropIndex('idx_machines_status');
        });
    }
};
