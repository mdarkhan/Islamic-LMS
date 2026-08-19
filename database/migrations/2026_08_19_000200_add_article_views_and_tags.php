<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Article page enhancements: a view counter on posts, plus a tag vocabulary
 * (`tags`) and its many-to-many pivot (`post_tag`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->unsignedBigInteger('views_count')->default(0)->after('body');
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 200)->unique();
            $table->string('name', 150);
            $table->timestamps();
        });

        Schema::create('post_tag', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['post_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_tag');
        Schema::dropIfExists('tags');
        Schema::table('posts', fn (Blueprint $table) => $table->dropColumn('views_count'));
    }
};
