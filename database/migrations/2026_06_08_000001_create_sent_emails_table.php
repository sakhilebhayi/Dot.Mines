<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sent_emails', function (Blueprint $table) {
            $table->id();
            // The Mailable class name, set via X-Mines-Mailable header
            $table->string('mailable_class', 150)->nullable()->index();
            $table->string('to_email');
            $table->string('subject');
            // Timestamps for delivery lifecycle
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            // Bounce / delivery tracking (M5 / M6) — populated via webhook
            $table->string('provider_message_id', 255)->nullable()->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('bounced_at')->nullable()->index();
            $table->string('bounce_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['to_email', 'mailable_class']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_emails');
    }
};
