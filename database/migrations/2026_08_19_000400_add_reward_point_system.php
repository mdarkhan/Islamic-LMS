<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reward point system:
 *  - per-quiz achievement bonus (a full-marks or admin-set threshold grants bonus points);
 *  - per-course topper rewards (position → points), stored on the course;
 *  - reward_grants: the idempotency + "congratulations screen" ledger. One row per award,
 *    referencing the point_transactions row, with a seen_at that drives the congrats modal.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Bonus rewards are a new kind of point movement.
        DB::statement("ALTER TABLE point_transactions MODIFY type ENUM('grant','deduction','adjustment','refund','import','bonus') NOT NULL");

        Schema::table('quizzes', function (Blueprint $table) {
            $table->boolean('bonus_enabled')->default(false)->after('counts_toward_overall');
            // 'full' = the quiz's full marks; 'marks' = the admin-set bonus_threshold_marks.
            $table->string('bonus_threshold_type', 10)->default('full')->after('bonus_enabled');
            $table->unsignedInteger('bonus_threshold_marks')->nullable()->after('bonus_threshold_type');
            $table->unsignedInteger('bonus_points')->default(0)->after('bonus_threshold_marks');
        });

        Schema::table('courses', function (Blueprint $table) {
            // [{ "position": 1, "points": 100 }, …] — set by the admin, applied on "Award".
            $table->json('topper_rewards')->nullable()->after('is_published');
        });

        Schema::create('reward_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);                       // quiz_achievement | course_topper
            $table->foreignId('quiz_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('position')->nullable();  // course toppers only
            $table->unsignedInteger('points');
            $table->foreignId('point_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('seen_at')->nullable();         // null = not yet shown in the congrats screen
            $table->timestamp('created_at')->nullable();

            // Idempotency: at most one quiz-achievement per (user, quiz) and one
            // topper reward per (user, course). NULLs don't collide, so the two
            // constraints never interfere with each other.
            $table->unique(['user_id', 'quiz_id']);
            $table->unique(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_grants');
        Schema::table('courses', fn (Blueprint $t) => $t->dropColumn('topper_rewards'));
        Schema::table('quizzes', fn (Blueprint $t) => $t->dropColumn([
            'bonus_enabled', 'bonus_threshold_type', 'bonus_threshold_marks', 'bonus_points',
        ]));
        DB::statement("ALTER TABLE point_transactions MODIFY type ENUM('grant','deduction','adjustment','refund','import') NOT NULL");
    }
};
