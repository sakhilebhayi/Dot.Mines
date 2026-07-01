<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_error_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('error_id', 36)->unique();           // UUID — safe to surface to users
            $table->string('level', 20)->default('error');      // error|warning|critical|info
            $table->string('category', 60)->default('app');     // app|api|integration|queue|frontend
            $table->string('http_method', 10)->nullable();
            $table->text('url')->nullable();
            $table->string('route_name', 120)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('exception_class', 250)->nullable();
            $table->text('message');
            $table->longText('stack_trace')->nullable();        // never exposed to end-users
            $table->json('context')->nullable();               // PII-stripped request params
            $table->string('user_id')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('environment', 20)->default('production');
            $table->string('app_version', 40)->nullable();
            $table->boolean('resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['level', 'created_at']);
            $table->index(['category', 'created_at']);
            $table->index(['team_id', 'created_at']);
            $table->index(['exception_class', 'created_at']);
            $table->index(['resolved', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_error_logs');
    }
};
