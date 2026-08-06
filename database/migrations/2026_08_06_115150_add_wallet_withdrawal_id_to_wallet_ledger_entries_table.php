<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_ledger_entries', function (Blueprint $table) {
            $table->foreignId('wallet_withdrawal_id')
                ->nullable()
                ->after('investment_id')
                ->constrained('wallet_withdrawals')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wallet_ledger_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wallet_withdrawal_id');
        });
    }
};
