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
        // Change type and alert_level from restricted enums to open strings
        // so new event types (machine_event, geofence_breach, etc.) can be used.
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('type', 100)->change();
            $table->string('alert_level', 20)->default('info')->change();
        });

        // Delivery log — tracks every notification sent per user per channel
        Schema::create('notification_delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 20)->default('email'); // email | in_app | sms
            $table->string('status', 20)->default('queued'); // queued | sent | failed
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['notification_id', 'channel']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_delivery_logs');
    }
};
