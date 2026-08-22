<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-machine pricing becomes the billing source of truth (design
 * decision 2026-08-22): the two per-machine prices the BillingPortal
 * hardcoded as class constants move into subscription_plans rows, and
 * the three legacy tier plans are deactivated. Paystack plan codes are
 * fixed-amount, so variable allocation totals bill through dynamically
 * created plans tracked in paystack_dynamic_plans.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paystack_dynamic_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('amount'); // smallest currency unit (kobo/cents)
            $table->string('interval', 20); // monthly | annually
            $table->string('plan_code');
            $table->timestamps();

            $table->unique(['amount', 'interval']);
        });

        $now = now();

        foreach ([
            [
                'slug' => 'adt-allocation',
                'name' => 'ADT Machine Allocation',
                'description' => 'One articulated dump truck registered and live on the platform.',
                'price' => 1500.00,
                'yearly_price' => 16200.00, // 12 months at the house 10% yearly discount
                'sort_order' => 1,
            ],
            [
                'slug' => 'heavy-allocation',
                'name' => 'Heavy Machine Allocation',
                'description' => 'One excavator, dozer, loader or grader registered and live on the platform.',
                'price' => 2500.00,
                'yearly_price' => 27000.00,
                'sort_order' => 2,
            ],
        ] as $plan) {
            DB::table('subscription_plans')->updateOrInsert(
                ['slug' => $plan['slug']],
                $plan + [
                    'max_machines' => 1, // one allocation = one machine
                    'max_users' => 999,
                    'max_geofences' => 999,
                    'max_mine_areas' => 999,
                    'is_active' => true,
                    'features' => json_encode([]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        // The tier plans are retired, not deleted: existing subscription
        // rows keep their foreign keys and history intact.
        DB::table('subscription_plans')
            ->whereIn('slug', ['basic', 'professional', 'enterprise'])
            ->update(['is_active' => false, 'updated_at' => $now]);
    }

    public function down(): void
    {
        Schema::dropIfExists('paystack_dynamic_plans');

        DB::table('subscription_plans')
            ->whereIn('slug', ['adt-allocation', 'heavy-allocation'])
            ->delete();

        DB::table('subscription_plans')
            ->whereIn('slug', ['basic', 'professional', 'enterprise'])
            ->update(['is_active' => true]);
    }
};
