<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two purely-display tracking columns for the student dashboard:
 *  - users.last_seen_overall_rank: the overall leaderboard rank the student last saw on
 *    their dashboard, so the next visit can show a movement arrow. Never used for ranking
 *    itself (LeaderboardService remains the only source of a live rank).
 *  - quiz_attempts.results_seen_at: when the student first viewed their released result,
 *    so the dashboard can nudge them toward an unseen one. Independent of `status` — the
 *    one-way terminal-state rule is untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('last_seen_overall_rank')->nullable()->after('locale');
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->timestamp('results_seen_at')->nullable()->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_seen_overall_rank');
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropColumn('results_seen_at');
        });
    }
};
