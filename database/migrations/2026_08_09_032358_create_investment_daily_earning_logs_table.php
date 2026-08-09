<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_daily_earning_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')
                ->constrained('investments')
                ->cascadeOnDelete();
            $table->date('earning_date');
            $table->decimal('amount_usd', 12, 2);
            $table->decimal('accrued_return_after_usd', 12, 2);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['investment_id', 'earning_date']);
            $table->index('earning_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_daily_earning_logs');
    }
};
