<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The bell-icon notification feed, shared by every logged-in user (student or admin).
 *
 * Named `app_notifications` — not `notifications` — deliberately: Laravel's own
 * Notifiable trait (already on User, unused elsewhere) expects a `notifications` table
 * with a different shape (uuid id, polymorphic notifiable columns, a `data` JSON blob).
 * A same-named table here would collide with that convention. This is a plain,
 * hand-rolled feed table instead, written only by NotificationService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);              // 'message' | 'amol_note' — icon/styling only
            // What this notification is ABOUT, for idempotent upsert (see NotificationService::notify):
            // 'conversation' + conversations.id, or 'amol_day_note' + amol_day_notes.id.
            $table->string('subject_type', 40)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('title', 255);
            $table->string('body', 500)->nullable();
            $table->string('url', 255);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->unique(['user_id', 'subject_type', 'subject_id'], 'app_notifications_user_subject_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
