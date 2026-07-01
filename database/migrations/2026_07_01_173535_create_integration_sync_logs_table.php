<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_sync_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->unsignedBigInteger('team_id')->index();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->enum('status', ['running', 'success', 'partial', 'failed'])->default('running');
            $table->unsignedInteger('machines_synced')->default(0);
            $table->unsignedInteger('records_inserted')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['integration_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_sync_logs');
    }
};
