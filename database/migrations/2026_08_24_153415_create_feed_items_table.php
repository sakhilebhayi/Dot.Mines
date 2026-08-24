<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Mine Operations Feed: one stream of what is happening across the mine.
 *
 * Two sources share the table and the page -- system events normalised from
 * the domain events the platform already fires, and posts written by people.
 * `source` keeps them visually and semantically distinct.
 *
 * The feed CONSUMES operational data; it never computes its own. A row
 * carries the text and the references (machine, operator, link), and
 * `occurred_at` is the moment the underlying event happened -- not the
 * moment the row was written -- so the stream cannot pretend stale data is
 * live.
 *
 * `dedupe_key` is how the same operational event, delivered twice by an
 * integration, appears once: unique per team, enforced by the database
 * rather than remembered by the code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            $table->string('source');           // system | user
            $table->string('category');         // fleet, production, maintenance, alerts, fuel, operators, announcement
            $table->string('type');             // machine.offline, geofence.entered, announcement, ...

            $table->string('title');
            $table->text('body')->nullable();

            // Where this item points: machine detail, operator page, live map.
            $table->string('action_url')->nullable();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();      // author, for posts
            $table->foreignId('machine_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained()->nullOnDelete();

            $table->json('data')->nullable();
            $table->string('dedupe_key')->nullable();

            $table->timestamp('occurred_at');

            $table->timestamp('pinned_until')->nullable();
            $table->foreignId('pinned_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'dedupe_key']);
            $table->index(['team_id', 'occurred_at']);
            $table->index(['team_id', 'category', 'occurred_at']);
            $table->index(['team_id', 'machine_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_items');
    }
};
