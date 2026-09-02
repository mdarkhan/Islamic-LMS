<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 200)->unique();
            $table->string('title', 250);
            $table->string('author', 200);
            $table->string('publisher', 200)->nullable();
            $table->unsignedSmallInteger('page_count')->nullable();

            // Free-text: prerequisites, edition, language, whatever else the admin wants to
            // note — one flexible field rather than a pile of speculative columns.
            $table->text('details')->nullable();

            $table->string('cover_path', 500)->nullable();

            $table->integer('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });

        Schema::create('book_purchase_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('website_name', 150);
            $table->string('url', 500);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['book_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_purchase_links');
        Schema::dropIfExists('books');
    }
};
