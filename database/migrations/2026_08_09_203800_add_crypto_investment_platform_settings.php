<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $defaults = [
            'crypto_investment_is_enabled' => '1',
            'crypto_investment_term_days' => '30',
            'crypto_investment_minimum_amount_usd' => '50.00',
            'crypto_investment_maximum_amount_usd' => '100000.00',
            'crypto_investment_fee_type' => 'percent',
            'crypto_investment_fee_value' => '1.0000',
            'crypto_investment_max_loss_enabled' => '1',
            'crypto_investment_max_loss_percent' => '50.00',
            'crypto_investment_supported_assets' => json_encode([
                [
                    'coingecko_asset_id' => 'bitcoin',
                    'asset_symbol' => 'BTC',
                    'asset_label' => 'Bitcoin',
                ],
                [
                    'coingecko_asset_id' => 'ethereum',
                    'asset_symbol' => 'ETH',
                    'asset_label' => 'Ethereum',
                ],
                [
                    'coingecko_asset_id' => 'tether',
                    'asset_symbol' => 'USDT',
                    'asset_label' => 'Tether',
                ],
                [
                    'coingecko_asset_id' => 'binancecoin',
                    'asset_symbol' => 'BNB',
                    'asset_label' => 'BNB',
                ],
                [
                    'coingecko_asset_id' => 'solana',
                    'asset_symbol' => 'SOL',
                    'asset_label' => 'Solana',
                ],
            ], JSON_THROW_ON_ERROR),
        ];

        foreach ($defaults as $key => $value) {
            $exists = DB::table('platform_settings')->where('key', $key)->exists();

            if ($exists) {
                continue;
            }

            DB::table('platform_settings')->insert([
                'key' => $key,
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('platform_settings')
            ->whereIn('key', [
                'crypto_investment_is_enabled',
                'crypto_investment_term_days',
                'crypto_investment_minimum_amount_usd',
                'crypto_investment_maximum_amount_usd',
                'crypto_investment_fee_type',
                'crypto_investment_fee_value',
                'crypto_investment_max_loss_enabled',
                'crypto_investment_max_loss_percent',
                'crypto_investment_supported_assets',
            ])
            ->delete();
    }
};
