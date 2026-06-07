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
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('team_id');
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();

            // Notification type categories matching NotificationService::TYPE_* constants
            $table->string('notification_type', 100);

            // Channel opt-in flags — defaults all channels on
            $table->boolean('email_enabled')->default(true);
            $table->boolean('in_app_enabled')->default(true);

            // Minimum alert level to receive: info, warning, high, critical
            $table->string('min_alert_level', 20)->default('info');

            $table->timestamps();

            $table->unique(['user_id', 'team_id', 'notification_type']);
            $table->index(['team_id', 'notification_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
