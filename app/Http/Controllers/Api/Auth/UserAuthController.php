<?php

namespace App\Http\Controllers\Api\Auth;

use App\Exceptions\InvalidGoogleIdTokenException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\GoogleLoginRequest;
use App\Http\Requests\Api\Auth\LoginUserRequest;
use App\Http\Requests\Api\Auth\RegisterUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\User;
use App\Services\Auth\GoogleUserAuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UserAuthController extends Controller
{
    use RespondsWithApiEnvelope;

    public function register(RegisterUserRequest $request): JsonResponse
    {
        $user = User::query()->create($request->validated());

        $token = Auth::guard('api')->login($user);

        return $this->successResponse(
            message: 'User registered successfully',
            data: [
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
                'user' => (new UserResource($user))->resolve(),
            ],
            statusCode: 201,
        );
    }

    public function login(LoginUserRequest $request): JsonResponse
    {
        $credentials = $request->only(['email', 'password']);

        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user !== null && ! $user->hasPasswordSet()) {
            return $this->errorResponse(
                message: 'This account uses Google sign-in. Please continue with Google.',
                statusCode: 401,
            );
        }

        $token = Auth::guard('api')->attempt($credentials);

        if ($token === false) {
            return $this->errorResponse(
                message: 'Invalid email or password provided',
                statusCode: 401,
            );
        }

        /** @var User $authenticatedUser */
        $authenticatedUser = Auth::guard('api')->user();

        return $this->successResponse(
            message: 'User logged in successfully',
            data: [
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
                'user' => (new UserResource($authenticatedUser))->resolve(),
            ],
        );
    }

    public function google(
        GoogleLoginRequest $request,
        GoogleUserAuthenticationService $googleUserAuthenticationService,
    ): JsonResponse {
        try {
            $user = $googleUserAuthenticationService->authenticateWithIdToken(
                $request->validated('id_token'),
            );
        } catch (InvalidGoogleIdTokenException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: 401,
            );
        }

        $token = Auth::guard('api')->login($user);

        return $this->successResponse(
            message: 'User logged in with Google successfully',
            data: [
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
                'user' => (new UserResource($user))->resolve(),
            ],
        );
    }

    public function logout(): JsonResponse
    {
        Auth::guard('api')->logout();

        return $this->successResponse(
            message: 'User logged out successfully',
            data: null,
        );
    }

    public function refresh(): JsonResponse
    {
        $token = Auth::guard('api')->refresh();

        /** @var User $user */
        $user = Auth::guard('api')->setToken($token)->user();

        return $this->successResponse(
            message: 'Token refreshed successfully',
            data: [
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
                'user' => (new UserResource($user))->resolve(),
            ],
        );
    }

    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        return $this->successResponse(
            message: 'User profile fetched successfully',
            data: (new UserResource($user))->resolve(),
        );
    }
}
