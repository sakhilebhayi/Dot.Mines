<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gdpr_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // export | delete
            $table->string('status')->default('pending'); // pending | processing | completed | failed
            $table->string('email');
            $table->text('reason')->nullable();
            $table->string('download_token', 64)->nullable()->unique();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gdpr_requests');
    }
};
