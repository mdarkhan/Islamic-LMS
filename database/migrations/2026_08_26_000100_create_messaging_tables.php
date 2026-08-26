<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authenticated student ↔ ustaz messaging.
 *
 * This is NOT the public "Ask Ustaz" form, which stays email-only and is still never
 * persisted (CLAUDE.md rule 1). A thread cannot exist without storing its messages, so
 * this feature deliberately stores them — but only for logged-in, identified students,
 * and never for the anonymous public form. Do not route ask-ustaz submissions here.
 *
 * One conversation per student (a single thread with the ustaz side, like a chat app);
 * either side may open it. Unread state is derived from messages.read_at rather than a
 * counter column, so there is no cached total that can drift out of sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            // The student side of the thread. Unique: one thread per student.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // Denormalised purely for inbox ordering; written only by MessageService.
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index('last_message_at');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            // Whoever wrote it — a student, or any admin replying as the ustaz side.
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            // Set when the OTHER side has read it. The ustaz side is a shared inbox, so
            // the first admin to open the thread marks it read for all of them.
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
            $table->index(['read_at', 'sender_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
