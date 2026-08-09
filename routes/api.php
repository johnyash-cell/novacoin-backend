<?php

use App\Http\Controllers\Api\Activity\UserPageVisitController;
use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\AdminAuthenticationLoginLogController;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\AdminInvestmentPackageController;
use App\Http\Controllers\Api\Admin\AdminNotificationController;
use App\Http\Controllers\Api\Admin\AdminPlatformCryptoWalletController;
use App\Http\Controllers\Api\Admin\AdminReferralRewardPayoutController;
use App\Http\Controllers\Api\Admin\AdminReferralSettingsController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\AdminUserInvestmentController;
use App\Http\Controllers\Api\Admin\AdminUserPageVisitLogController;
use App\Http\Controllers\Api\Admin\AdminWalletDepositController;
use App\Http\Controllers\Api\Admin\AdminWalletWithdrawalController;
use App\Http\Controllers\Api\Auth\UserAuthController;
use App\Http\Controllers\Api\InvestmentController;
use App\Http\Controllers\Api\InvestmentPackageController;
use App\Http\Controllers\Api\PlatformCryptoWalletController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\UserNotificationController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\WalletDepositController;
use App\Http\Controllers\Api\WalletWithdrawalController;
use App\Http\Middleware\EnsureMemberAccountIsNotRestricted;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [UserAuthController::class, 'register'])
        ->middleware('throttle:auth');
    Route::post('login', [UserAuthController::class, 'login'])
        ->middleware('throttle:auth');
    Route::post('google', [UserAuthController::class, 'google'])
        ->middleware('throttle:auth');

    Route::middleware('auth:api')->group(function (): void {
        Route::post('logout', [UserAuthController::class, 'logout']);

        Route::middleware(EnsureMemberAccountIsNotRestricted::class)->group(function (): void {
            Route::post('refresh', [UserAuthController::class, 'refresh']);
            Route::get('me', [UserAuthController::class, 'me']);
        });
    });
});

Route::middleware(['auth:api', EnsureMemberAccountIsNotRestricted::class])->group(function (): void {
    Route::get('notifications/filter-options', [UserNotificationController::class, 'filterOptions']);
    Route::get('notifications/unread-count', [UserNotificationController::class, 'unreadCount']);
    Route::post('notifications/mark-all-as-read', [UserNotificationController::class, 'markAllAsRead']);
    Route::get('notifications', [UserNotificationController::class, 'index']);
    Route::get('notifications/{notification}', [UserNotificationController::class, 'show']);
    Route::post('notifications/{notification}/read', [UserNotificationController::class, 'markAsRead']);

    Route::get('wallet', [WalletController::class, 'show']);
    Route::get('platform-crypto-wallets', [PlatformCryptoWalletController::class, 'index']);
    Route::get('wallet/deposit-quote', [WalletDepositController::class, 'quote']);
    Route::get('wallet/deposits', [WalletDepositController::class, 'index']);
    Route::post('wallet/deposits', [WalletDepositController::class, 'store']);
    Route::get('wallet/withdrawals/filter-options', [WalletWithdrawalController::class, 'filterOptions']);
    Route::get('wallet/withdrawals', [WalletWithdrawalController::class, 'index']);
    Route::post('wallet/withdrawals', [WalletWithdrawalController::class, 'store']);

    Route::get('investment-packages', [InvestmentPackageController::class, 'index']);
    Route::get('investment-packages/{investment_package}', [InvestmentPackageController::class, 'show']);
    Route::post('investment-packages/{investment_package}/invest', [InvestmentPackageController::class, 'invest']);

    Route::get('investments', [InvestmentController::class, 'index']);
    Route::get('investments/{investment}/daily-earnings', [InvestmentController::class, 'dailyEarnings']);
    Route::get('investments/{investment}', [InvestmentController::class, 'show']);

    Route::get('referral', [ReferralController::class, 'show']);
    Route::get('referral/referred-users', [ReferralController::class, 'referredUsers']);
});

Route::prefix('activity')->group(function (): void {
    Route::post('page-visits', [UserPageVisitController::class, 'store'])
        ->middleware('throttle:page-visits');
});

Route::prefix('admin')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('login', [AdminAuthController::class, 'login'])
            ->middleware('throttle:auth');

        Route::middleware('auth:admin')->group(function (): void {
            Route::post('logout', [AdminAuthController::class, 'logout']);
            Route::post('refresh', [AdminAuthController::class, 'refresh']);
            Route::get('me', [AdminAuthController::class, 'me']);
        });
    });

    Route::middleware('auth:admin')->group(function (): void {
        Route::get('admins', [AdminController::class, 'index']);
        Route::post('admins', [AdminController::class, 'store']);

        Route::get('users/filter-options', [AdminUserController::class, 'filterOptions']);
        Route::get('users/{user}/authentication-login-logs', [AdminAuthenticationLoginLogController::class, 'indexForUser']);
        Route::get('users/{user}/page-visit-logs', [AdminUserPageVisitLogController::class, 'indexForUser']);
        Route::get('users/{user}/wallet-deposits', [AdminWalletDepositController::class, 'indexForUser']);
        Route::get('users/{user}/wallet-withdrawals', [AdminWalletWithdrawalController::class, 'indexForUser']);
        Route::get('users/{user}/investments/filter-options', [AdminUserInvestmentController::class, 'filterOptions']);
        Route::get('users/{user}/investments', [AdminUserInvestmentController::class, 'index']);
        Route::post('users/{user}/ban', [AdminUserController::class, 'ban']);
        Route::post('users/{user}/suspend', [AdminUserController::class, 'suspend']);
        Route::post('users/{user}/unsuspend', [AdminUserController::class, 'unsuspend']);
        Route::post('users/{user}/reactivate', [AdminUserController::class, 'reactivate']);
        Route::apiResource('users', AdminUserController::class);
        Route::post('users/{user}/promote-to-admin', [AdminUserController::class, 'promoteToAdmin']);

        Route::get('authentication-login-logs/filter-options', [AdminAuthenticationLoginLogController::class, 'filterOptions']);
        Route::get('authentication-login-logs', [AdminAuthenticationLoginLogController::class, 'index']);

        Route::get('user-page-visit-logs/filter-options', [AdminUserPageVisitLogController::class, 'filterOptions']);
        Route::get('user-page-visit-logs/overview', [AdminUserPageVisitLogController::class, 'overview']);
        Route::get('user-page-visit-logs', [AdminUserPageVisitLogController::class, 'index']);

        Route::get('notifications/filter-options', [AdminNotificationController::class, 'filterOptions']);
        Route::get('notifications', [AdminNotificationController::class, 'index']);
        Route::post('notifications', [AdminNotificationController::class, 'store']);

        Route::get('investment-packages/filter-options', [AdminInvestmentPackageController::class, 'filterOptions']);
        Route::patch('investment-packages/{investment_package}/availability-status', [AdminInvestmentPackageController::class, 'updateAvailabilityStatus']);
        Route::patch('investment-packages/{investment_package}/featured', [AdminInvestmentPackageController::class, 'updateFeatured']);
        Route::apiResource('investment-packages', AdminInvestmentPackageController::class);

        Route::get('platform-crypto-wallets/asset-options', [AdminPlatformCryptoWalletController::class, 'assetOptions']);
        Route::get('platform-crypto-wallets/filter-options', [AdminPlatformCryptoWalletController::class, 'filterOptions']);
        Route::apiResource('platform-crypto-wallets', AdminPlatformCryptoWalletController::class);

        Route::get('wallet-deposits/filter-options', [AdminWalletDepositController::class, 'filterOptions']);
        Route::get('wallet-deposits', [AdminWalletDepositController::class, 'index']);
        Route::get('wallet-deposits/{wallet_deposit}', [AdminWalletDepositController::class, 'show']);
        Route::post('wallet-deposits/{wallet_deposit}/approve', [AdminWalletDepositController::class, 'approve']);
        Route::post('wallet-deposits/{wallet_deposit}/decline', [AdminWalletDepositController::class, 'decline']);

        Route::get('wallet-withdrawals/filter-options', [AdminWalletWithdrawalController::class, 'filterOptions']);
        Route::get('wallet-withdrawals', [AdminWalletWithdrawalController::class, 'index']);
        Route::get('wallet-withdrawals/{wallet_withdrawal}', [AdminWalletWithdrawalController::class, 'show']);
        Route::post('wallet-withdrawals/{wallet_withdrawal}/approve', [AdminWalletWithdrawalController::class, 'approve']);
        Route::post('wallet-withdrawals/{wallet_withdrawal}/decline', [AdminWalletWithdrawalController::class, 'decline']);

        Route::get('referral-settings', [AdminReferralSettingsController::class, 'show']);
        Route::put('referral-settings', [AdminReferralSettingsController::class, 'update']);
        Route::get('referral-reward-payouts/filter-options', [AdminReferralRewardPayoutController::class, 'filterOptions']);
        Route::get('referral-reward-payouts', [AdminReferralRewardPayoutController::class, 'index']);
    });
});
