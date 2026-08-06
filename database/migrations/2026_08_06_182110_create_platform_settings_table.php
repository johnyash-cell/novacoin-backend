<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
            $table->timestamps();
        });

        $now = now();

        DB::table('platform_settings')->insert([
            [
                'key' => 'referral_reward_amount_usd',
                'value' => '10.00',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'referral_reward_payout_mode',
                'value' => 'first_approved_deposit_only',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
