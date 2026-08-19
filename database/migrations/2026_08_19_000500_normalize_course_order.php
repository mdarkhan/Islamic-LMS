<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Course ordering is now managed by up/down arrows, so it must be a clean 1..N sequence
 * (no duplicates, no zeros). Renumber existing rows by their current order, then id.
 */
return new class extends Migration
{
    public function up(): void
    {
        $position = 1;

        foreach (DB::table('courses')->orderBy('sort_order')->orderBy('id')->get() as $course) {
            DB::table('courses')->where('id', $course->id)->update(['sort_order' => $position++]);
        }
    }

    public function down(): void
    {
        // One-way data normalisation; nothing to restore.
    }
};
