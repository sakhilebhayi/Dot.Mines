<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only entitlement ledger: a team's machine capacity per class is
 * SUM(delta) over its rows, and the rows themselves are the allocation
 * audit history (purchases, trial grants, refunds, admin adjustments) --
 * one table, no separate history to drift out of sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machine_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('class', 20); // 'adt' | 'heavy'
            $table->integer('delta');
            $table->string('source', 30); // purchase|refund|cancellation|expiry|restore|admin
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['team_id', 'class']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_allocations');
    }
};
