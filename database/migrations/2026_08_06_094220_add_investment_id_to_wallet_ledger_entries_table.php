<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_ledger_entries', function (Blueprint $table) {
            $table->foreignId('investment_id')
                ->nullable()
                ->after('wallet_deposit_id')
                ->constrained('investments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wallet_ledger_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('investment_id');
        });
    }
};
