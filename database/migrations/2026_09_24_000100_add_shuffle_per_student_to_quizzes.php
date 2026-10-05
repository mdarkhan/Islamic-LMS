<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional per-student question/option order for OFFICIAL attempts (less copying in a
 * shared room). Off by default so every existing quiz behaves exactly as before. The
 * order itself is never stored: it is derived from the attempt id (see
 * ExamAttemptPresenter), so it is stable across reloads and costs no extra rows.
 * Scoring is by option id, so it is unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->boolean('shuffle_per_student')->default(false)->after('counts_toward_overall');
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn('shuffle_per_student');
        });
    }
};
