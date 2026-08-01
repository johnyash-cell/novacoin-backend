<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\LoginAdminRequest;
use App\Http\Resources\AdminResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AdminAuthController extends Controller
{
    use RespondsWithApiEnvelope;

    public function login(LoginAdminRequest $request): JsonResponse
    {
        $token = Auth::guard('admin')->attempt($request->only(['email', 'password']));

        if ($token === false) {
            return $this->errorResponse(
                message: 'Invalid email or password provided',
                statusCode: 401,
            );
        }

        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        return $this->successResponse(
            message: 'Admin logged in successfully',
            data: [
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => Auth::guard('admin')->factory()->getTTL() * 60,
                'admin' => (new AdminResource($admin))->resolve(),
            ],
        );
    }

    public function logout(): JsonResponse
    {
        Auth::guard('admin')->logout();

        return $this->successResponse(
            message: 'Admin logged out successfully',
            data: null,
        );
    }

    public function refresh(): JsonResponse
    {
        $token = Auth::guard('admin')->refresh();

        /** @var Admin $admin */
        $admin = Auth::guard('admin')->setToken($token)->user();

        return $this->successResponse(
            message: 'Token refreshed successfully',
            data: [
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => Auth::guard('admin')->factory()->getTTL() * 60,
                'admin' => (new AdminResource($admin))->resolve(),
            ],
        );
    }

    public function me(): JsonResponse
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        return $this->successResponse(
            message: 'Admin profile fetched successfully',
            data: (new AdminResource($admin))->resolve(),
        );
    }
}
