<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace Stripe-specific columns with Paystack equivalents.
     * Uses add → copy → drop to avoid SQLite renameColumn limitations.
     */
    public function up(): void
    {
        // Drop all stale indexes that reference non-existent columns.
        // SQLite validates the entire schema during any ALTER TABLE operation,
        // so these must be removed before any column add/drop can succeed.
        $this->dropIndexIfExists('alerts', 'idx_alerts_severity');
        $this->dropIndexIfExists('reports', 'idx_reports_team_type');
        $this->dropIndexIfExists('maintenance_schedules', 'idx_maintenance_scheduled');
        // Drop Stripe-column uniques/indexes so the columns can be dropped.
        // On SQLite a ->unique() is a standalone index (DROP INDEX works);
        // on Postgres it is a table CONSTRAINT whose backing index cannot be
        // dropped directly (SQLSTATE 2BP01), so the constraint must be
        // dropped instead. Handle both so this runs on production Postgres.
        $this->dropUniqueOrIndex('subscriptions', 'subscriptions_stripe_subscription_id_unique');
        $this->dropUniqueOrIndex('subscriptions', 'subscriptions_stripe_subscription_id_index');
        $this->dropUniqueOrIndex('payments', 'payments_stripe_payment_intent_id_unique');
        $this->dropUniqueOrIndex('payments', 'payments_stripe_payment_intent_id_index');
        $this->dropUniqueOrIndex('invoices', 'invoices_stripe_invoice_id_unique');

        // ── subscription_plans ──────────────────────────────────────────────
        Schema::table('subscription_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('subscription_plans', 'paystack_plan_code')) {
                $table->string('paystack_plan_code')->nullable()->after('yearly_price');
            }
            if (! Schema::hasColumn('subscription_plans', 'paystack_yearly_plan_code')) {
                $table->string('paystack_yearly_plan_code')->nullable()->after('paystack_plan_code');
            }
        });
        if (Schema::hasColumn('subscription_plans', 'stripe_price_id')) {
            DB::table('subscription_plans')->update([
                'paystack_plan_code' => DB::raw('stripe_price_id'),
                'paystack_yearly_plan_code' => DB::raw('stripe_yearly_price_id'),
            ]);
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->dropColumn(['stripe_price_id', 'stripe_yearly_price_id']);
            });
        }

        // ── subscriptions ────────────────────────────────────────────────────
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'paystack_subscription_code')) {
                $table->string('paystack_subscription_code')->nullable()->unique()->after('subscription_plan_id');
            }
            if (! Schema::hasColumn('subscriptions', 'paystack_customer_code')) {
                $table->string('paystack_customer_code')->nullable()->after('paystack_subscription_code');
            }
            if (! Schema::hasColumn('subscriptions', 'paystack_email_token')) {
                $table->string('paystack_email_token')->nullable()->after('paystack_customer_code');
            }
        });
        if (Schema::hasColumn('subscriptions', 'stripe_subscription_id')) {
            DB::table('subscriptions')->update([
                'paystack_subscription_code' => DB::raw('stripe_subscription_id'),
                'paystack_customer_code' => DB::raw('stripe_customer_id'),
            ]);
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropColumn(['stripe_subscription_id', 'stripe_customer_id']);
            });
        }

        // ── payments ─────────────────────────────────────────────────────────
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'paystack_reference')) {
                $table->string('paystack_reference')->nullable()->unique()->after('subscription_id');
            }
            if (! Schema::hasColumn('payments', 'paystack_invoice_id')) {
                $table->string('paystack_invoice_id')->nullable()->after('paystack_reference');
            }
        });
        if (Schema::hasColumn('payments', 'stripe_payment_intent_id')) {
            DB::table('payments')->update([
                'paystack_reference' => DB::raw('stripe_payment_intent_id'),
                'paystack_invoice_id' => DB::raw('stripe_invoice_id'),
            ]);
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn(['stripe_payment_intent_id', 'stripe_invoice_id']);
            });
        }

        // ── invoices ──────────────────────────────────────────────────────────
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'paystack_invoice_code')) {
                $table->string('paystack_invoice_code')->nullable()->unique()->after('payment_id');
            }
        });
        if (Schema::hasColumn('invoices', 'stripe_invoice_id')) {
            DB::table('invoices')->update([
                'paystack_invoice_code' => DB::raw('stripe_invoice_id'),
            ]);
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('stripe_invoice_id');
            });
        }
    }

    /**
     * Drop a unique whether the driver stored it as a constraint (Postgres)
     * or a standalone index (SQLite). Both statements are IF EXISTS, so a
     * name that only exists in one form is dropped and the other is a no-op.
     */
    private function dropUniqueOrIndex(string $table, string $name): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name}");
        }

        $this->dropIndexIfExists($table, $name);
    }

    /**
     * Cross-engine DROP INDEX: MySQL/MariaDB have no bare
     * "DROP INDEX IF EXISTS name" form (the table is mandatory), so
     * existence is checked in information_schema first.
     */
    private function dropIndexIfExists(string $table, string $name): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $exists = DB::selectOne(
                'select 1 as x from information_schema.statistics where table_schema = database() and table_name = ? and index_name = ? limit 1',
                [$table, $name],
            );

            if ($exists !== null) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$name}`");
            }

            return;
        }

        DB::statement("DROP INDEX IF EXISTS {$name}");
    }

    public function down(): void
    {
        // invoices
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('stripe_invoice_id')->nullable()->unique()->after('payment_id');
        });
        DB::table('invoices')->update(['stripe_invoice_id' => DB::raw('paystack_invoice_code')]);
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('paystack_invoice_code');
        });

        // payments
        Schema::table('payments', function (Blueprint $table) {
            $table->string('stripe_payment_intent_id')->nullable()->unique()->after('subscription_id');
            $table->string('stripe_invoice_id')->nullable()->after('stripe_payment_intent_id');
        });
        DB::table('payments')->update([
            'stripe_payment_intent_id' => DB::raw('paystack_reference'),
            'stripe_invoice_id' => DB::raw('paystack_invoice_id'),
        ]);
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['paystack_reference', 'paystack_invoice_id']);
        });

        // subscriptions
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('stripe_subscription_id')->nullable()->unique()->after('subscription_plan_id');
            $table->string('stripe_customer_id')->nullable()->after('stripe_subscription_id');
        });
        DB::table('subscriptions')->update([
            'stripe_subscription_id' => DB::raw('paystack_subscription_code'),
            'stripe_customer_id' => DB::raw('paystack_customer_code'),
        ]);
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['paystack_subscription_code', 'paystack_customer_code', 'paystack_email_token']);
        });

        // subscription_plans
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->string('stripe_price_id')->nullable()->after('yearly_price');
            $table->string('stripe_yearly_price_id')->nullable()->after('stripe_price_id');
        });
        DB::table('subscription_plans')->update([
            'stripe_price_id' => DB::raw('paystack_plan_code'),
            'stripe_yearly_price_id' => DB::raw('paystack_yearly_plan_code'),
        ]);
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['paystack_plan_code', 'paystack_yearly_plan_code']);
        });
    }
};
