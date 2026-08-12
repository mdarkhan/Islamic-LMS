<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regrade contract: recompute calculated_score from stored answers against the
 * current key, leave manual_adjustment untouched, then
 * final_score = calculated_score + manual_adjustment.
 *
 * An admin's goodwill mark therefore survives an answer-key correction (brief §20).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('quiz_attempts')->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users')->restrictOnDelete();

            $table->integer('old_manual');
            $table->integer('new_manual');
            $table->integer('old_final');
            $table->integer('new_final');

            $table->string('reason', 500);   // required — enforced by the form request
            $table->timestamp('created_at')->useCurrent();

            $table->index('attempt_id');
        });

        Schema::create('regrade_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users')->restrictOnDelete();

            $table->string('reason', 500);
            $table->integer('attempts_affected')->default(0);
            $table->integer('attempts_skipped')->default(0);   // legacy imports cannot be regraded

            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('regrade_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regrade_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attempt_id')->constrained('quiz_attempts')->cascadeOnDelete();

            $table->integer('old_calculated');
            $table->integer('new_calculated');
            $table->integer('old_final');
            $table->integer('new_final');

            $table->index('regrade_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regrade_entries');
        Schema::dropIfExists('regrade_runs');
        Schema::dropIfExists('score_adjustments');
    }
};
