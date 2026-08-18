<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            $table->string('source_name', 255);
            $table->char('source_sha256', 64);
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->unsignedInteger('source_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('summary')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'source_sha256'], 'lib_type_source_unique');
            $table->index(['type', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('legacy_import_batch_id')->nullable()->after('is_legacy_import')
                ->constrained('legacy_import_batches')->restrictOnDelete();
            $table->char('legacy_source_key', 64)->nullable()->after('legacy_import_batch_id')->unique();
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->foreignId('legacy_import_batch_id')->nullable()->after('created_by')
                ->constrained('legacy_import_batches')->restrictOnDelete();
            $table->char('legacy_source_key', 64)->nullable()->after('legacy_import_batch_id')->unique();
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->foreignId('legacy_import_batch_id')->nullable()->after('answer_details_available')
                ->constrained('legacy_import_batches')->restrictOnDelete();
            $table->char('legacy_source_key', 64)->nullable()->after('legacy_import_batch_id')->unique();
            $table->string('legacy_quiz_id', 200)->nullable()->after('legacy_source_key');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('legacy_import_batch_id');
            $table->dropUnique(['legacy_source_key']);
            $table->dropColumn(['legacy_source_key', 'legacy_quiz_id']);
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('legacy_import_batch_id');
            $table->dropUnique(['legacy_source_key']);
            $table->dropColumn('legacy_source_key');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('legacy_import_batch_id');
            $table->dropUnique(['legacy_source_key']);
            $table->dropColumn('legacy_source_key');
        });

        Schema::dropIfExists('legacy_import_batches');
    }
};
