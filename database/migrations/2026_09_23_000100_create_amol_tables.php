<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily amol (deed) tracker — "দৈনন্দিন আমলনামা". Digitises the institution's paper
 * checklist (fajr sunnah, five takbir-e-oola, witr, morning/evening adhkar, etc.).
 *
 * amols          the fixed checklist catalog (seeded, one row per deed).
 * amol_entries   one student's checkmark for one deed on one calendar date.
 * amol_day_notes the ustaz's comment on one student's whole day (not per-deed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amols', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();   // stable identifier, e.g. "fajr_prayer"
            $table->string('label', 150);           // Bengali display text — content, never localised
            // Groups the five daily prayers' sub-items (sunnah/prayer/takbir-e-oola) under
            // one dropdown in the UI, e.g. "fajr". Null for every other, ungrouped item.
            $table->string('group', 30)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('amol_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('amol_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->boolean('is_done')->default(false);
            $table->timestamps();

            // One checkmark per student per deed per day; the pair also serves the
            // "load one day's checklist" query.
            $table->unique(['user_id', 'amol_id', 'date']);
            $table->index(['user_id', 'date']);
        });

        Schema::create('amol_day_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->text('note');
            $table->foreignId('commented_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('commented_at')->nullable();
            // Null = the student has not opened this day since the note was last written.
            // Reset to null on every edit, so a revised comment re-surfaces as new.
            $table->timestamp('seen_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amol_day_notes');
        Schema::dropIfExists('amol_entries');
        Schema::dropIfExists('amols');
    }
};
