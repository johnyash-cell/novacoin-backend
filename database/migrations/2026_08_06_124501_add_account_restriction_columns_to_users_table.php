<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_status')->default('active')->after('google_id')->index();
            $table->text('account_status_reason')->nullable()->after('account_status');
            $table->timestamp('account_status_changed_at')->nullable()->after('account_status_reason');
            $table->unsignedBigInteger('account_status_changed_by_admin_id')
                ->nullable()
                ->after('account_status_changed_at')
                ->comment('Admin ID from admins table (same DB; stored without FK for audit flexibility)');
            $table->timestamp('suspended_until')->nullable()->after('account_status_changed_by_admin_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'account_status',
                'account_status_reason',
                'account_status_changed_at',
                'account_status_changed_by_admin_id',
                'suspended_until',
            ]);
        });
    }
};
