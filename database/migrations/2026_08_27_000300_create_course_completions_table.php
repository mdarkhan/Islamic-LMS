<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A pure recognition badge — set once a student has opened every published lesson in a
 * course. Deliberately separate from `reward_grants` (which pays real points and already
 * has a unique(user_id, course_id) index for the course-topper bonus): this is not a
 * monetary bonus, so it never touches PointService or the points ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at');

            $table->unique(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_completions');
    }
};
