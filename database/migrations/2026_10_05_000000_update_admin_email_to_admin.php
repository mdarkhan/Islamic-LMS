<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('email', 'admin@masudalimi.test')
            ->update(['email' => 'admin']);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('email', 'admin')
            ->update(['email' => 'admin@masudalimi.test']);
    }
};
