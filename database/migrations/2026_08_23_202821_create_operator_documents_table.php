<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files backing an operator's record: licence scans, certificates, IDs.
 *
 * Stored on a private disk and served only through an authorised, audited
 * controller -- never a public URL. `kind` drives access: medical documents
 * are readable only behind the medical permission, everything else behind
 * plain operator view.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_id')->constrained()->cascadeOnDelete();

            $table->string('kind');  // licence, medical, training, identification, employment, competency, other
            $table->string('title');
            $table->string('disk');
            $table->string('path', 1024);
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['operator_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_documents');
    }
};
