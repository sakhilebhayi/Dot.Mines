<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conversation depth for feed threads: replies under a comment, and a
 * closed reaction vocabulary on the comments themselves (like /
 * acknowledge / reject) -- so a supervisor's instruction in a thread can be
 * answered without another sentence.
 *
 * Replies are one level deep by design: a reply to a reply attaches to the
 * root comment, which keeps the panel readable on a phone in a haul truck.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_comments', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('feed_item_id')
                ->constrained('feed_comments')
                ->cascadeOnDelete();

            $table->index(['parent_id']);
        });

        Schema::create('feed_comment_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feed_comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 16);
            $table->timestamps();

            $table->unique(['feed_comment_id', 'user_id', 'emoji']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_comment_reactions');
        Schema::table('feed_comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
