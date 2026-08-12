<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Students authenticate by roll number. Staff accounts have no roll and
            // authenticate by email, so both columns are nullable but unique.
            $table->string('roll', 50)->nullable()->unique();
            $table->string('name', 150);
            $table->string('guardian_name', 150)->nullable();
            $table->string('email', 190)->nullable()->unique();
            $table->string('phone', 30)->nullable();

            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            $table->enum('status', ['active', 'suspended', 'archived'])->default('active');

            // A temporary password issued by an admin forces a change at next login.
            $table->boolean('force_password_change')->default(false);

            // Cached mirror of SUM(point_transactions.amount). Written only inside the
            // same transaction as the ledger row — see PointService.
            $table->integer('points_balance')->default(0);

            $table->boolean('is_legacy_import')->default(false);
            $table->timestamp('last_login_at')->nullable();

            $table->rememberToken();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
