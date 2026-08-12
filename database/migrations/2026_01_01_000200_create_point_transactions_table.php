<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only points ledger. Rows are never updated or deleted — a mistaken grant
 * is corrected by an opposing `adjustment` row so history stays intact.
 *
 * Invariant: users.points_balance === SUM(point_transactions.amount) per user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();

            // RESTRICT: a student with point history cannot be hard-deleted.
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            $table->enum('type', ['grant', 'deduction', 'adjustment', 'refund', 'import']);

            // Signed: positive credits, negative debits. Never zero (app-enforced).
            $table->integer('amount');

            // Balance immediately after this row — the audit trail that lets drift
            // be located precisely rather than just detected.
            $table->integer('balance_after');

            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            // Admin who performed a manual action; null for system-driven movements.
            $table->foreignId('performed_by')->nullable()->constrained('users')->restrictOnDelete();

            $table->string('reason', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'id']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_transactions');
    }
};
