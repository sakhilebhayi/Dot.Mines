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
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            
            $table->string('provider'); // volvo, cat, komatsu, bell, c_track
            $table->string('name');
            $table->string('api_key');
            $table->string('api_secret');
            
            $table->string('webhook_url')->nullable();
            $table->string('webhook_secret')->nullable();
            
            $table->string('status')->default('active'); // active, inactive, error
            $table->timestamp('last_sync_at')->nullable();
            $table->text('last_error')->nullable();
            $table->integer('machines_count')->default(0);
            
            $table->json('config')->nullable();
            
            $table->timestamps();
            
            $table->index('team_id');
            $table->index('provider');
            $table->index('status');
            $table->unique(['team_id', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
