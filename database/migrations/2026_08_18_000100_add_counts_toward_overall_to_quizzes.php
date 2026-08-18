<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quiz-level inclusion flag for the cumulative (overall) leaderboard.
 *
 * The per-attempt `counts_toward_cumulative` already distinguishes practice (0) from
 * official (1). This adds the ORTHOGONAL, admin-controllable dimension the cumulative
 * leaderboard needs (brief §21): a trial / diagnostic / special non-ranking official
 * quiz can be excluded from the overall standings without affecting its own per-quiz
 * leaderboard or any attempt's score. Defaults to 1 so existing quizzes keep counting.
 *
 * Historical-integrity note (brief §4): no answer snapshot columns are added. Student
 * selections are already retained immutably as quiz_answer_options.option_id references
 * (RESTRICT), and the Builder scoring-lock freezes all question/option TEXT the moment
 * official attempts exist — so what the student saw can never drift. The Regrade
 * workflow changes only the key/marks/explanation, never body text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->boolean('counts_toward_overall')->default(true)->after('leaderboard_visible');
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn('counts_toward_overall');
        });
    }
};
