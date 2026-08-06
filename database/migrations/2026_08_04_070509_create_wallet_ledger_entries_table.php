<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_wallet_id')
                ->constrained('user_wallets')
                ->cascadeOnDelete();
            $table->string('entry_type');
            $table->decimal('amount', 16, 2);
            $table->decimal('balance_after', 16, 2);
            $table->unsignedBigInteger('wallet_deposit_id')->nullable()->index();
            $table->string('description')->nullable();
            $table->unsignedBigInteger('created_by_admin_id')->nullable()->comment('Admin ID from admins table (no FK required)');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_ledger_entries');
    }
};
