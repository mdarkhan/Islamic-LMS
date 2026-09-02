<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (student, lesson) they have opened — the basis for "continue watching",
 * per-course progress bars and course-completion badges. `viewed_at` is updated (not
 * re-inserted) on every visit, so it always reflects the most recent open.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->timestamp('viewed_at');

            $table->unique(['user_id', 'lesson_id']);
            $table->index(['user_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_views');
    }
};
