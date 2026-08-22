<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // mine_area_id on alerts may already exist from a previous migration attempt
        // Only add if missing — use raw SQL to avoid SQLite table recreation issues
        if (! Schema::hasColumn('alerts', 'mine_area_id')) {
            if (DB::connection()->getDriverName() === 'sqlite') {
                // Raw SQL avoids SQLite's table-recreation on ALTER; the loose
                // INTEGER type is fine there because SQLite ignores FK types.
                DB::statement('ALTER TABLE alerts ADD COLUMN mine_area_id INTEGER NULL REFERENCES mine_areas(id) ON DELETE SET NULL');

                try {
                    DB::statement('CREATE INDEX alerts_mine_area_id_index ON alerts (mine_area_id)');
                } catch (Exception $e) {
                    // Index may already exist
                }
            } else {
                // MySQL requires the FK column type to match users of BIGINT
                // UNSIGNED ids exactly (errno 150 otherwise); the builder gets
                // it right, and the FK's implicit index covers lookups.
                Schema::table('alerts', function (Blueprint $table) {
                    $table->foreignId('mine_area_id')->nullable()->constrained('mine_areas')->nullOnDelete();
                });
            }
        }

        // Create mine plan uploads table
        if (! Schema::hasTable('mine_plan_uploads')) {
            Schema::create('mine_plan_uploads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->constrained()->cascadeOnDelete();
                $table->foreignId('mine_area_id')->constrained('mine_areas')->cascadeOnDelete();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('file_name');
                $table->string('file_path');
                $table->string('file_type'); // pdf, dwg, dxf, kml, kmz, shapefile, image
                $table->unsignedBigInteger('file_size')->default(0); // in bytes
                $table->string('version')->default('1.0');
                $table->enum('status', ['draft', 'active', 'superseded', 'archived'])->default('draft');
                $table->date('effective_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->json('metadata')->nullable(); // extra data like scale, coordinate system, etc.
                $table->timestamps();
                $table->softDeletes();

                $table->index('team_id');
                $table->index('mine_area_id');
                $table->index('status');
                $table->index('file_type');
            });
        }

        // Create machine_mine_area_history for tracking assignment history
        if (! Schema::hasTable('machine_mine_area_assignments')) {
            Schema::create('machine_mine_area_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->constrained()->cascadeOnDelete();
                $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
                $table->foreignId('mine_area_id')->constrained('mine_areas')->cascadeOnDelete();
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('assigned_at');
                $table->timestamp('unassigned_at')->nullable();
                $table->string('reason')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['team_id', 'mine_area_id']);
                $table->index(['machine_id', 'mine_area_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_mine_area_assignments');
        Schema::dropIfExists('mine_plan_uploads');

        if (Schema::hasColumn('alerts', 'mine_area_id')) {
            Schema::table('alerts', function (Blueprint $table) {
                $table->dropForeign(['mine_area_id']);
                $table->dropColumn('mine_area_id');
            });
        }
    }
};
