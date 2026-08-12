<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();

            $table->string('slug', 160)->unique();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->json('summary')->nullable();
            $table->text('syllabus')->nullable();

            // Both the parsed date and the original Bengali label are kept: 17 legacy
            // lessons have the literal label "সংগৃহীত" and no real date, and re-rendering
            // a parsed date would change what students have always seen.
            $table->date('held_on')->nullable();
            $table->string('date_label', 100)->nullable();

            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->string('duration_label', 100)->nullable();

            // Provider is stored so another storage backend needs no migration later.
            $table->enum('media_provider', ['google_drive', 'external', 'none'])->default('google_drive');
            $table->string('media_url', 500)->nullable();
            $table->string('media_file_id', 120)->nullable();

            $table->integer('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->integer('legacy_id')->nullable();

            $table->timestamps();

            $table->index(['course_id', 'sort_order']);
            $table->index('is_published');
            $table->index('held_on');
        });

        Schema::create('lesson_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('label', 500);

            // Nullable on purpose: every legacy resource has the placeholder "#".
            // A resource with no URL renders as plain text, not a dead link.
            $table->string('url', 500)->nullable();

            $table->enum('kind', ['book', 'link', 'file', 'note'])->default('link');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['lesson_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_resources');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('courses');
    }
};
