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
use App\Services\Auth\RecordsAuthenticationLoginAttempt;
use App\Services\Auth\ResolvesUserAccountAccessRestrictionMessage;
use App\Services\Mail\ComposesMemberLifecycleEmailCopy;
use App\Services\Mail\SendsMemberTransactionalEmail;
use App\Services\Referral\AttachesReferrerFromReferralCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserAuthController extends Controller
{
    use RespondsWithApiEnvelope;

    public function register(
        RegisterUserRequest $request,
        AttachesReferrerFromReferralCode $attachesReferrerFromReferralCode,
        ComposesMemberLifecycleEmailCopy $composesMemberLifecycleEmailCopy,
        SendsMemberTransactionalEmail $sendsMemberTransactionalEmail,
    ): JsonResponse {
        $validated = $request->validated();
        $referralCode = $validated['referral_code'] ?? null;
        unset($validated['referral_code']);

        // Create + referral attach must commit together so invalid attach cannot leave an orphan member.
        $user = DB::transaction(function () use ($validated, $referralCode, $attachesReferrerFromReferralCode): User {
            $user = User::query()->create($validated);

            if (filled($referralCode)) {
                $attachesReferrerFromReferralCode->attach($user, $referralCode);
            }

            return $user->fresh() ?? $user;
        });

        $sendsMemberTransactionalEmail->sendCopy(
            $user,
            $composesMemberLifecycleEmailCopy->welcome($user),
        );

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

    public function login(
        LoginUserRequest $request,
        RecordsAuthenticationLoginAttempt $recordsAuthenticationLoginAttempt,
        ResolvesUserAccountAccessRestrictionMessage $resolvesUserAccountAccessRestrictionMessage,
    ): JsonResponse {
        $credentials = $request->only(['email', 'password']);

        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user !== null && ! $user->hasPasswordSet()) {
            $recordsAuthenticationLoginAttempt->recordFailedUserPasswordLogin(
                email: $credentials['email'],
                request: $request,
                failureReason: 'This account uses Google sign-in. Please continue with Google.',
            );

            return $this->errorResponse(
                message: 'This account uses Google sign-in. Please continue with Google.',
                statusCode: 401,
            );
        }

        if ($user !== null) {
            $restrictionMessage = $resolvesUserAccountAccessRestrictionMessage
                ->restrictionMessageOrNull($user);

            if ($restrictionMessage !== null) {
                $recordsAuthenticationLoginAttempt->recordFailedUserPasswordLogin(
                    email: $credentials['email'],
                    request: $request,
                    failureReason: $restrictionMessage,
                );

                return $this->errorResponse(
                    message: $restrictionMessage,
                    statusCode: 403,
                );
            }
        }

        $token = Auth::guard('api')->attempt($credentials);

        if ($token === false) {
            $recordsAuthenticationLoginAttempt->recordFailedUserPasswordLogin(
                email: $credentials['email'],
                request: $request,
                failureReason: 'Invalid email or password provided',
            );

            return $this->errorResponse(
                message: 'Invalid email or password provided',
                statusCode: 401,
            );
        }

        /** @var User $authenticatedUser */
        $authenticatedUser = Auth::guard('api')->user();

        $recordsAuthenticationLoginAttempt->recordSuccessfulUserPasswordLogin(
            user: $authenticatedUser,
            request: $request,
        );

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
        RecordsAuthenticationLoginAttempt $recordsAuthenticationLoginAttempt,
        ResolvesUserAccountAccessRestrictionMessage $resolvesUserAccountAccessRestrictionMessage,
    ): JsonResponse {
        try {
            $user = $googleUserAuthenticationService->authenticateWithIdToken(
                idToken: $request->validated('id_token'),
                referralCode: $request->validated('referral_code'),
            );
        } catch (InvalidGoogleIdTokenException $exception) {
            $recordsAuthenticationLoginAttempt->recordFailedUserGoogleLogin(
                email: 'unknown',
                request: $request,
                failureReason: $exception->getMessage(),
            );

            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: 401,
            );
        }

        $restrictionMessage = $resolvesUserAccountAccessRestrictionMessage
            ->restrictionMessageOrNull($user);

        if ($restrictionMessage !== null) {
            $recordsAuthenticationLoginAttempt->recordFailedUserGoogleLogin(
                email: $user->email,
                request: $request,
                failureReason: $restrictionMessage,
            );

            return $this->errorResponse(
                message: $restrictionMessage,
                statusCode: 403,
            );
        }

        $token = Auth::guard('api')->login($user);

        $recordsAuthenticationLoginAttempt->recordSuccessfulUserGoogleLogin(
            user: $user,
            request: $request,
        );

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
