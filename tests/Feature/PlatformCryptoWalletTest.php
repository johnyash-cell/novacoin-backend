<?php

use App\Models\Admin;
use App\Models\PlatformCryptoWallet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function adminPlatformWalletToken(): string
{
    return auth('admin')->login(Admin::factory()->create());
}

function userPlatformWalletToken(): string
{
    return auth('api')->login(User::factory()->create());
}

it('rejects unauthenticated admin platform crypto wallet list', function () {
    $this->getJson('/api/admin/platform-crypto-wallets')
        ->assertUnauthorized();
});

it('returns friendly asset options for admins', function () {
    $token = adminPlatformWalletToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/platform-crypto-wallets/asset-options')
        ->assertSuccessful()
        ->assertJsonPath('data.assets.0.value', 'bitcoin')
        ->assertJsonPath('data.assets.0.label', 'Bitcoin')
        ->assertJsonPath('data.assets.0.asset_symbol', 'BTC')
        ->assertJsonMissingPath('data.assets.0.coingecko_asset_id');
});

it('lets an admin create update and list platform crypto wallets using asset_key', function () {
    $token = adminPlatformWalletToken();

    $createResponse = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/admin/platform-crypto-wallets', [
            'asset_key' => 'bitcoin',
            'network_name' => 'Bitcoin',
            'wallet_address' => 'bc1qadminsetupaddress',
            'is_available_for_funding' => true,
            'sort_order' => 1,
            'notes' => 'Treasury hot wallet',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Bitcoin')
        ->assertJsonPath('data.asset_key', 'bitcoin')
        ->assertJsonPath('data.asset_symbol', 'BTC')
        ->assertJsonPath('data.coingecko_asset_id', 'bitcoin')
        ->assertJsonPath('data.notes', 'Treasury hot wallet');

    $walletId = $createResponse->json('data.id');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson('/api/admin/platform-crypto-wallets/'.$walletId, [
            'asset_key' => 'bitcoin',
            'name' => 'Bitcoin Main',
            'network_name' => 'Bitcoin',
            'wallet_address' => 'bc1qadminsetupaddress',
            'is_available_for_funding' => false,
            'sort_order' => 2,
            'notes' => null,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Bitcoin Main')
        ->assertJsonPath('data.is_available_for_funding', false);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/platform-crypto-wallets')
        ->assertSuccessful()
        ->assertJsonPath('meta.pagination.total', 1);
});

it('rejects unknown asset keys', function () {
    $token = adminPlatformWalletToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/admin/platform-crypto-wallets', [
            'asset_key' => 'porncoin',
            'network_name' => 'MadeUp',
            'wallet_address' => 'abc123',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['asset_key']);
});

it('only returns available wallets to members without coingecko internals', function () {
    PlatformCryptoWallet::factory()->create([
        'name' => 'Bitcoin',
        'is_available_for_funding' => true,
        'sort_order' => 1,
    ]);
    PlatformCryptoWallet::factory()->unavailableForFunding()->create([
        'name' => 'Hidden Eth',
        'sort_order' => 2,
    ]);

    $token = userPlatformWalletToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/platform-crypto-wallets')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Bitcoin')
        ->assertJsonMissingPath('data.0.notes')
        ->assertJsonMissingPath('data.0.coingecko_asset_id')
        ->assertJsonMissingPath('data.0.asset_key');
});

it('returns filter options for admin platform crypto wallets', function () {
    $token = adminPlatformWalletToken();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/admin/platform-crypto-wallets/filter-options')
        ->assertSuccessful()
        ->assertJsonPath('data.total_available_filters', 1)
        ->assertJsonPath('data.filters.0.key', 'is_available_for_funding');
});
