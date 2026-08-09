<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_account_restriction_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('action');
            $table->string('previous_account_status');
            $table->string('new_account_status');
            $table->text('reason')->nullable();
            $table->timestamp('suspended_until')->nullable();
            $table->unsignedBigInteger('performed_by_admin_id')
                ->nullable()
                ->comment('Admin ID from admins table (same DB; no FK for audit flexibility)');
            $table->timestamp('created_at')->useCurrent();

            $table->index('action');
            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_account_restriction_logs');
    }
};
