<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->decimal('accrued_return_usd', 12, 2)
                ->default(0)
                ->after('expected_payout_amount_usd');
            $table->timestamp('payout_completed_at')
                ->nullable()
                ->after('ended_at');
        });
    }

    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn(['accrued_return_usd', 'payout_completed_at']);
        });
    }
};
