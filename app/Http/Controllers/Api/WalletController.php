<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserWalletResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\User;
use App\Services\Wallet\ResolvesUserWallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    use RespondsWithApiEnvelope;

    public function show(ResolvesUserWallet $resolvesUserWallet): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();
        $wallet = $resolvesUserWallet->resolveForUser($user);

        return $this->successResponse(
            message: 'Wallet fetched successfully',
            data: (new UserWalletResource($wallet))->resolve(),
        );
    }
}
