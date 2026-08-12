<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog / Fatwa / Q&A, notices and settings.
 *
 * Note there is deliberately NO table for Ask Ustaz submissions — those are
 * emailed only and never persisted (brief §29, SECURITY.md §2.8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('name', 150);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 200)->unique();
            $table->foreignId('post_category_id')->constrained()->restrictOnDelete();

            $table->string('title', 250);
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->string('featured_image', 500)->nullable();

            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->dateTime('published_at')->nullable();

            $table->string('seo_title', 250)->nullable();
            $table->string('seo_description', 500)->nullable();

            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index('post_category_id');
        });

        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->string('body', 1000);
            $table->boolean('is_active')->default(true);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->smallInteger('priority')->default(0);
            $table->enum('audience', ['public', 'students', 'all'])->default('all');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 120)->primary();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string');
            $table->string('group', 50)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();

            // Drives the "last updated" stamp required on reference rates (brief §30).
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('action', 100);

            $table->string('auditable_type', 100)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();

            // A redaction allow-list keeps password hashes and tokens out of these.
            $table->json('before')->nullable();
            $table->json('after')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('notices');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('post_categories');
    }
};
