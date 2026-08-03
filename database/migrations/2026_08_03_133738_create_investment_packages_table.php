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
        Schema::create('investment_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_pitch', 160);
            $table->text('description');
            $table->decimal('expected_return_percent', 8, 2);
            $table->unsignedInteger('term_days');
            $table->decimal('minimum_amount_usd', 12, 2);
            $table->decimal('maximum_amount_usd', 12, 2)->nullable();
            $table->unsignedInteger('max_participants');
            $table->unsignedInteger('joined_count')->default(0);
            $table->string('risk_level');
            $table->string('availability_status');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->json('highlights')->nullable();
            $table->timestamps();

            $table->index('availability_status');
            $table->index('risk_level');
            $table->index('is_featured');
            $table->index('created_at');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investment_packages');
    }
};
