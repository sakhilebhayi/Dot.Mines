<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Logging a theft or spillage transaction used to silently decrement the
 * tank level with no alert at all -- the same treatment as a normal
 * dispensing entry. 'fuel_loss_reported' is the new alert_type
 * FuelManagementService fires for both, so a real loss actually surfaces
 * instead of disappearing into the transaction log.
 */
return new class extends Migration
{
    private const OLD_VALUES = [
        'low_fuel', 'critical_fuel', 'tank_low', 'tank_critical',
        'high_consumption', 'unusual_pattern', 'overdue_refill', 'leak_detected',
    ];

    private const NEW_VALUES = [
        'low_fuel', 'critical_fuel', 'tank_low', 'tank_critical',
        'high_consumption', 'unusual_pattern', 'overdue_refill',
        'leak_detected', 'fuel_loss_reported',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->setAllowedValues(self::NEW_VALUES);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->setAllowedValues(self::OLD_VALUES);
    }

    /**
     * Laravel's own `enum(...)->change()` generates invalid SQL on Postgres
     * for this exact case -- it tries to combine `TYPE varchar(255)` and
     * `CHECK (...)` in a single ALTER COLUMN clause, which Postgres rejects
     * with a syntax error (confirmed live). Postgres needs the check
     * constraint dropped and re-added as separate statements instead.
     */
    private function setAllowedValues(array $values): void
    {
        $driver = DB::getDriverName();
        $quoted = collect($values)->map(fn ($v) => "'{$v}'")->implode(', ');

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE fuel_alerts DROP CONSTRAINT IF EXISTS fuel_alerts_alert_type_check');
            DB::statement("ALTER TABLE fuel_alerts ADD CONSTRAINT fuel_alerts_alert_type_check CHECK (alert_type IN ({$quoted}))");

            return;
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE fuel_alerts MODIFY alert_type ENUM({$quoted}) NOT NULL");

            return;
        }

        // SQLite has no native enum type -- Laravel already models it as a
        // plain column with a CHECK constraint, and ->change() rebuilds the
        // table correctly here (unlike the pgsql grammar bug above).
        Schema::table('fuel_alerts', function (Blueprint $table) use ($values) {
            $table->enum('alert_type', $values)->change();
        });
    }
};
