<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor R4: MaintenanceSchedule and ComponentReplacement read
 * machines.operating_hours / odometer / total_distance_km, but no
 * migration created them -- they existed only as legacy drift in the old
 * production SQLite (all NULL) and were excluded from the MySQL cutover
 * copy. Restoring them makes the schema match the code that reads them
 * (service-interval and component-lifespan checks) instead of those
 * checks silently comparing against Eloquent's null-for-missing-column.
 * Guarded for databases that still carry the legacy columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            foreach (['operating_hours', 'odometer', 'total_distance_km'] as $column) {
                if (! Schema::hasColumn('machines', $column)) {
                    $table->double($column)->nullable();
                }
            }

            // Read by the MachineOffline broadcast event; same legacy-drift
            // origin as the columns above.
            if (! Schema::hasColumn('machines', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Additive and nullable; leaving them in place on rollback is safe.
    }
};
