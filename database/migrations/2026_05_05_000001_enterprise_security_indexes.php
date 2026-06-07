<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enterprise security hardening indexes.
 *
 * Adds the remaining performance indexes required for:
 *  1. Fast user-by-team lookups (auth, dashboard queries)
 *  2. Feed post author/approval-status filtering
 *  3. Upload (feed_attachments) file-type filtering for security scans
 *  4. Audit log IP-based lookup (incident response queries)
 *  5. Session cleanup (last_activity-indexed already — just verifies users FK)
 *  6. Maintenance records — machine_id for work-order views
 *  7. Machines — mine_area_id for spatial scoping
 *
 * All operations are idempotent across MySQL/MariaDB, PostgreSQL, and SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        $indexExists = function (string $table, string $indexName) use ($driver): bool {
            try {
                if ($driver === 'sqlite') {
                    return count(DB::select(
                        "SELECT name FROM sqlite_master WHERE type='index' AND name=?",
                        [$indexName]
                    )) > 0;
                }
                if (in_array($driver, ['mysql', 'mariadb'])) {
                    return count(DB::select(
                        "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
                        [$indexName]
                    )) > 0;
                }
                if ($driver === 'pgsql') {
                    return count(DB::select(
                        'SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?',
                        [$table, $indexName]
                    )) > 0;
                }
            } catch (Throwable) {
                // Silently skip if the check itself fails (e.g., table not yet created).
            }

            return false;
        };

        // ── 1. users: current_team_id ──────────────────────────────────────
        if (Schema::hasTable('users')
            && ! $indexExists('users', 'idx_users_current_team')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('current_team_id', 'idx_users_current_team');
            });
        }

        // ── 2. users: email_verified_at (for filtering unverified users) ───
        if (Schema::hasTable('users')
            && ! $indexExists('users', 'idx_users_email_verified')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('email_verified_at', 'idx_users_email_verified');
            });
        }

        // ── 3. feed_posts: author_id + created_at (activity feed queries) ──
        if (Schema::hasTable('feed_posts')
            && ! $indexExists('feed_posts', 'idx_feed_posts_author_time')) {
            Schema::table('feed_posts', function (Blueprint $table) {
                $table->index(['author_id', 'created_at'], 'idx_feed_posts_author_time');
            });
        }

        // ── 4. feed_posts: deleted_at (soft-delete filtering) ─────────────
        if (Schema::hasTable('feed_posts')
            && ! $indexExists('feed_posts', 'idx_feed_posts_deleted')) {
            Schema::table('feed_posts', function (Blueprint $table) {
                $table->index('deleted_at', 'idx_feed_posts_deleted');
            });
        }

        // ── 5. feed_approvals: status (pending queue dashboard) ────────────
        if (Schema::hasTable('feed_approvals')
            && ! $indexExists('feed_approvals', 'idx_feed_approvals_status')) {
            Schema::table('feed_approvals', function (Blueprint $table) {
                $table->index('status', 'idx_feed_approvals_status');
            });
        }

        // ── 6. feed_attachments: file_type (MIME security scans) ──────────
        if (Schema::hasTable('feed_attachments')
            && ! $indexExists('feed_attachments', 'idx_feed_attachments_type')) {
            Schema::table('feed_attachments', function (Blueprint $table) {
                $table->index('file_type', 'idx_feed_attachments_type');
            });
        }

        // ── 7. audit_logs: ip_address (incident response / IP lookups) ─────
        if (Schema::hasTable('audit_logs')
            && ! $indexExists('audit_logs', 'idx_audit_ip')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->index('ip_address', 'idx_audit_ip');
            });
        }

        // ── 8. maintenance_records: machine_id + status ────────────────────
        if (Schema::hasTable('maintenance_records')
            && ! $indexExists('maintenance_records', 'idx_maintenance_machine_status')) {
            Schema::table('maintenance_records', function (Blueprint $table) {
                $table->index(['machine_id', 'status'], 'idx_maintenance_machine_status');
            });
        }

        // ── 9. machines: mine_area_id (spatial/area scoping queries) ───────
        if (Schema::hasTable('machines')
            && Schema::hasColumn('machines', 'mine_area_id')
            && ! $indexExists('machines', 'idx_machines_mine_area')) {
            Schema::table('machines', function (Blueprint $table) {
                $table->index('mine_area_id', 'idx_machines_mine_area');
            });
        }

        // ── 10. machines: team_id + created_at (fleet listing with sort) ───
        if (Schema::hasTable('machines')
            && ! $indexExists('machines', 'idx_machines_team_created')) {
            Schema::table('machines', function (Blueprint $table) {
                $table->index(['team_id', 'created_at'], 'idx_machines_team_created');
            });
        }
    }

    public function down(): void
    {
        $drops = [
            'users' => ['idx_users_current_team', 'idx_users_email_verified'],
            'feed_posts' => ['idx_feed_posts_author_time', 'idx_feed_posts_deleted'],
            'feed_approvals' => ['idx_feed_approvals_status'],
            'feed_attachments' => ['idx_feed_attachments_type'],
            'audit_logs' => ['idx_audit_ip'],
            'maintenance_records' => ['idx_maintenance_machine_status'],
            'machines' => ['idx_machines_mine_area', 'idx_machines_team_created'],
        ];

        foreach ($drops as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($indexes as $index) {
                try {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($index));
                } catch (Throwable) {
                    // Index may not exist in all environments.
                }
            }
        }
    }
};
