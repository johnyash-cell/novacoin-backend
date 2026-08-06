<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlatformCryptoWalletResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\PlatformCryptoWallet;
use Illuminate\Http\JsonResponse;

class PlatformCryptoWalletController extends Controller
{
    use RespondsWithApiEnvelope;

    public function index(): JsonResponse
    {
        $wallets = PlatformCryptoWallet::query()
            ->availableForFunding()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $this->successResponse(
            message: 'Platform crypto wallets fetched successfully',
            data: PlatformCryptoWalletResource::collection($wallets)->resolve(),
        );
    }
}
