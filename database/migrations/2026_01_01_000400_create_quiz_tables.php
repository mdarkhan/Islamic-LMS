<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->nullable()->constrained()->restrictOnDelete();

            // Quizzes may precede or outlive a lesson (the workbook has seerat-26/27
            // with no lesson), so this link is optional and nulls rather than blocks.
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();

            $table->string('slug', 160)->unique();
            $table->string('title', 200);
            $table->text('description')->nullable();

            $table->enum('status', ['draft', 'scheduled', 'published', 'archived'])->default('draft');
            $table->boolean('practice_enabled')->default(false);

            $table->unsignedSmallInteger('point_cost')->default(1);

            // NULL = no per-attempt time limit.
            $table->unsignedInteger('duration_seconds')->nullable();

            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();

            // Result release is independent of the exam end (brief §39).
            $table->dateTime('result_release_at')->nullable();
            $table->dateTime('results_released_at')->nullable();

            $table->boolean('leaderboard_visible')->default(true);
            $table->unsignedTinyInteger('max_official_attempts')->default(1);

            // Cached SUM(quiz_questions.marks WHERE is_active). Recalculated on every
            // question change and after each regrade; the questions remain the truth.
            $table->unsignedInteger('total_marks')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['starts_at', 'ends_at']);
            $table->index('course_id');
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->integer('sort_order')->default(0);

            // Stored rather than inferred from the correct-option count, so an admin can
            // author a multiple-choice question that currently has one correct answer
            // without it silently behaving as single-choice.
            $table->enum('type', ['single', 'multiple'])->default('single');

            $table->text('body');
            $table->text('explanation')->nullable();

            // The "Mega Question" value from workbook column W.
            $table->unsignedSmallInteger('marks')->default(1);

            // Withdraw a bad question without deleting stored student answers.
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['quiz_id', 'sort_order']);
        });

        Schema::create('quiz_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('quiz_questions')->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->text('body');

            // The answer key. NEVER serialised into a response while an official
            // attempt is open — see SECURITY.md §2.3.
            $table->boolean('is_correct')->default(false);

            $table->timestamps();

            $table->index(['question_id', 'sort_order']);
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            $table->enum('kind', ['official', 'practice']);
            $table->unsignedSmallInteger('attempt_no')->default(1);
            $table->enum('status', ['in_progress', 'submitted', 'expired', 'voided'])->default('in_progress');

            $table->dateTime('started_at');
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->unsignedInteger('time_taken_seconds')->nullable();

            $table->integer('calculated_score')->default(0);
            $table->integer('manual_adjustment')->default(0);
            $table->integer('final_score')->default(0);
            $table->unsignedInteger('total_marks_snapshot')->default(0);

            $table->unsignedInteger('submission_seq')->nullable();
            $table->boolean('counts_toward_cumulative')->default(true);

            $table->boolean('is_legacy_import')->default(false);
            $table->boolean('answer_details_available')->default(true);

            // Links the attempt to its point debit. Makes "has this attempt already
            // charged the student?" a single nullable-FK check, which is what makes
            // resume idempotent.
            $table->foreignId('point_transaction_id')->nullable()
                ->constrained('point_transactions')->restrictOnDelete();

            $table->timestamps();

            // Backstop against duplicate official attempts. The fast path is a row
            // lock in QuizAttemptService; this makes a race a database error, never
            // a silent second attempt.
            $table->unique(['quiz_id', 'user_id', 'kind', 'attempt_no'], 'qa_unique_attempt');

            // Integrity backstop for the per-quiz submission serial. The service
            // assigns it under a quiz-row lock; this makes any collision a database
            // error. NULLs (practice attempts, in-progress attempts) are exempt in
            // MySQL, which permits multiple NULLs in a unique index.
            $table->unique(['quiz_id', 'submission_seq'], 'qa_unique_submission_seq');

            $table->index(['quiz_id', 'status']);
            $table->index('user_id');
            $table->index(['quiz_id', 'kind', 'status', 'final_score', 'time_taken_seconds'], 'qa_leaderboard_index');
            $table->index(['quiz_id', 'submitted_at']);
        });

        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('quiz_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('quiz_questions')->restrictOnDelete();

            $table->boolean('is_correct')->nullable();   // NULL until graded
            $table->integer('marks_awarded')->default(0);
            $table->dateTime('answered_at')->nullable();

            $table->timestamps();

            // Makes autosave idempotent: each save is an upsert on this key, so a
            // retried or duplicated request cannot create a second row.
            $table->unique(['attempt_id', 'question_id'], 'qan_attempt_question_unique');
            $table->index('question_id');
        });

        Schema::create('quiz_answer_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('answer_id')->constrained('quiz_answers')->cascadeOnDelete();
            $table->foreignId('option_id')->constrained('quiz_options')->restrictOnDelete();

            // One row per selected option. Retaining raw selections independently of
            // the answer key is precisely what makes regrading possible.
            $table->unique(['answer_id', 'option_id'], 'qao_answer_option_unique');
            $table->index('option_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_answer_options');
        Schema::dropIfExists('quiz_answers');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_options');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
    }
};
