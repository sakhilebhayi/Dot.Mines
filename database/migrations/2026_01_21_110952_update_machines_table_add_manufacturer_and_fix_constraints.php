<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            // Add manufacturer column
            $table->string('manufacturer')->nullable()->after('machine_type');

            // Make registration_number and serial_number nullable and not unique
            $table->dropUnique(['registration_number']);
            $table->dropUnique(['serial_number']);
            $table->string('registration_number')->nullable()->change();
            $table->string('serial_number')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            // Remove manufacturer column
            $table->dropColumn('manufacturer');

            // Restore registration_number and serial_number as unique and not nullable
            $table->string('registration_number')->nullable(false)->change();
            $table->string('serial_number')->nullable(false)->change();
            $table->unique('registration_number');
            $table->unique('serial_number');
        });
    }
};
