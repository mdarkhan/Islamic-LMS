<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Once-only ledger for exam reminder / result-published notifications, so the scheduled
 * sweep never notifies the same student about the same quiz twice, however often it
 * runs. Holds no message content. `users.notify_by_email` lets a student opt out of the
 * email copy (the in-app bell notification is unaffected).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30);
            $table->boolean('emailed')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'quiz_id', 'kind']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_by_email')->default(true)->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notify_by_email');
        });
        Schema::dropIfExists('notification_deliveries');
    }
};
