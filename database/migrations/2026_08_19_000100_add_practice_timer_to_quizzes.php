<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-quiz admin option: does Practice Mode run with a timer?
 *
 * Default false preserves the existing behaviour (practice is untimed). When enabled,
 * a practice attempt uses the quiz's own duration_seconds as its countdown; the official
 * ends_at is still never reused for practice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->boolean('practice_timer_enabled')->default(false)->after('practice_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn('practice_timer_enabled');
        });
    }
};
