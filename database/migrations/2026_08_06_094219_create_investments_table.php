<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('investment_package_id')
                ->constrained('investment_packages')
                ->restrictOnDelete();
            $table->string('package_name');
            $table->decimal('amount_usd', 12, 2);
            $table->decimal('expected_return_percent', 8, 2);
            $table->unsignedInteger('term_days');
            $table->decimal('expected_return_amount_usd', 12, 2);
            $table->decimal('expected_payout_amount_usd', 12, 2);
            $table->string('status');
            $table->timestamp('started_at');
            $table->timestamp('matures_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('started_at');
            $table->index('matures_at');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};
