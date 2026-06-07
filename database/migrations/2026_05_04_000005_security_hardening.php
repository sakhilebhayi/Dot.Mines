<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Security hardening migration.
 *
 * 1. Creates a comprehensive audit_logs table for platform-wide event tracing.
 * 2. Adds ip_address to feed_audit_logs for feed admin action traceability.
 * 3. Adds missing performance indexes:
 *    – feed_attachments (post_id, uploaded_at) for listing queries
 *    – feed_attachments (storage_type) for backward-compat filtering
 *    – maintenance_records (team_id, status) for work-order dashboards
 *    – maintenance_records (scheduled_at) for due-date queries
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Platform-wide audit log ──────────────────────────────────────
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Nullable so system/webhook events (no authenticated user) can be logged.
            $table->unsignedBigInteger('team_id')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();

            $table->string('action', 100);
            $table->text('description')->nullable();

            // IPv4 (15) or IPv6 (39) +  safety margin → 45 chars
            $table->string('ip_address', 45)->nullable();

            // Polymorphic reference to the affected record
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            // Flexible key/value bag for additional context (old/new values, etc.)
            $table->json('meta')->nullable();

            // Audit logs are append-only; no updated_at
            $table->timestamp('created_at')->useCurrent();

            // Indexes for audit dashboard queries
            $table->index(['team_id', 'created_at'], 'idx_audit_team_time');
            $table->index(['actor_id', 'created_at'], 'idx_audit_actor_time');
            $table->index(['subject_type', 'subject_id'], 'idx_audit_subject');
            $table->index('action', 'idx_audit_action');
        });

        // ── 2. Add ip_address to feed_audit_logs ────────────────────────────
        if (Schema::hasTable('feed_audit_logs')
            && ! Schema::hasColumn('feed_audit_logs', 'ip_address')
        ) {
            Schema::table('feed_audit_logs', function (Blueprint $table) {
                $table->string('ip_address', 45)->nullable()->after('meta');
            });
        }

        // ── 3. Performance indexes (idempotent) ─────────────────────────────
        $driver = DB::getDriverName();

        $indexExists = function (string $tableName, string $indexName) use ($driver): bool {
            if ($driver === 'sqlite') {
                return count(DB::select(
                    "SELECT name FROM sqlite_master WHERE type='index' AND name=?",
                    [$indexName]
                )) > 0;
            }
            if (in_array($driver, ['mysql', 'mariadb'])) {
                return count(DB::select(
                    "SHOW INDEX FROM `{$tableName}` WHERE Key_name = ?",
                    [$indexName]
                )) > 0;
            }
            // pgsql: pg_indexes
            if ($driver === 'pgsql') {
                return count(DB::select(
                    'SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?',
                    [$tableName, $indexName]
                )) > 0;
            }

            return false;
        };

        // feed_attachments: composite (post_id, uploaded_at)
        if (Schema::hasTable('feed_attachments')
            && ! $indexExists('feed_attachments', 'idx_feed_attachments_post_uploaded')
        ) {
            Schema::table('feed_attachments', function (Blueprint $table) {
                $table->index(['post_id', 'uploaded_at'], 'idx_feed_attachments_post_uploaded');
            });
        }

        // feed_attachments: storage_type (for S3 vs DB queries)
        if (Schema::hasTable('feed_attachments')
            && ! $indexExists('feed_attachments', 'idx_feed_attachments_storage')
        ) {
            Schema::table('feed_attachments', function (Blueprint $table) {
                $table->index('storage_type', 'idx_feed_attachments_storage');
            });
        }

        // maintenance_records: (team_id, status)
        if (Schema::hasTable('maintenance_records')
            && ! $indexExists('maintenance_records', 'idx_maintenance_team_status')
        ) {
            Schema::table('maintenance_records', function (Blueprint $table) {
                $table->index(['team_id', 'status'], 'idx_maintenance_team_status');
            });
        }

        // maintenance_records: scheduled_at
        if (Schema::hasTable('maintenance_records')
            && ! $indexExists('maintenance_records', 'idx_maintenance_scheduled')
        ) {
            Schema::table('maintenance_records', function (Blueprint $table) {
                $table->index('scheduled_at', 'idx_maintenance_scheduled');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');

        if (Schema::hasTable('feed_audit_logs')
            && Schema::hasColumn('feed_audit_logs', 'ip_address')
        ) {
            Schema::table('feed_audit_logs', function (Blueprint $table) {
                $table->dropColumn('ip_address');
            });
        }

        if (Schema::hasTable('feed_attachments')) {
            if (Schema::hasIndex('feed_attachments', 'idx_feed_attachments_post_uploaded')) {
                Schema::table('feed_attachments', function (Blueprint $table) {
                    $table->dropIndex('idx_feed_attachments_post_uploaded');
                });
            }
            if (Schema::hasIndex('feed_attachments', 'idx_feed_attachments_storage')) {
                Schema::table('feed_attachments', function (Blueprint $table) {
                    $table->dropIndex('idx_feed_attachments_storage');
                });
            }
        }

        if (Schema::hasTable('maintenance_records')) {
            if (Schema::hasIndex('maintenance_records', 'idx_maintenance_team_status')) {
                Schema::table('maintenance_records', function (Blueprint $table) {
                    $table->dropIndex('idx_maintenance_team_status');
                });
            }
            if (Schema::hasIndex('maintenance_records', 'idx_maintenance_scheduled')) {
                Schema::table('maintenance_records', function (Blueprint $table) {
                    $table->dropIndex('idx_maintenance_scheduled');
                });
            }
        }
    }
};
