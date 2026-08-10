<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\BanUserRequest;
use App\Http\Requests\Api\Admin\IndexUsersRequest;
use App\Http\Requests\Api\Admin\PromoteUserToAdminRequest;
use App\Http\Requests\Api\Admin\ReactivateUserRequest;
use App\Http\Requests\Api\Admin\RemoveAdminBackofficeAccessFromUserRequest;
use App\Http\Requests\Api\Admin\StoreUserRequest;
use App\Http\Requests\Api\Admin\SuspendUserRequest;
use App\Http\Requests\Api\Admin\UnsuspendUserRequest;
use App\Http\Requests\Api\Admin\UpdateAdminUserWalletRequest;
use App\Http\Requests\Api\Admin\UpdateUserRequest;
use App\Http\Resources\AdminDirectoryMemberResource;
use App\Http\Resources\AdminResource;
use App\Http\Resources\AdminUserProfileResource;
use App\Http\Resources\AdminUserResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\Admin;
use App\Models\User;
use App\Services\Admin\BuildsAdminUserProfileSummary;
use App\Services\Admin\ManagesUserAccountRestrictionStatus;
use App\Services\Admin\PaginatesAdminUserDirectoryListing;
use App\Services\Admin\SetsMemberWalletAvailableBalanceAbsolute;
use App\Services\Mail\ComposesMemberLifecycleEmailCopy;
use App\Services\Mail\SendsMemberTransactionalEmail;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class AdminUserController extends Controller
{
    use RespondsWithApiEnvelope;

    public function index(
        IndexUsersRequest $request,
        PaginatesAdminUserDirectoryListing $paginatesAdminUserDirectoryListing,
    ): JsonResponse {
        $validated = $request->validated();

        $directoryMembers = $paginatesAdminUserDirectoryListing->paginate($validated);

        return $this->successResponse(
            message: 'Users fetched successfully',
            data: AdminDirectoryMemberResource::collection($directoryMembers->items())->resolve(),
            meta: [
                'pagination' => [
                    'current_page' => $directoryMembers->currentPage(),
                    'per_page' => $directoryMembers->perPage(),
                    'total' => $directoryMembers->total(),
                    'last_page' => $directoryMembers->lastPage(),
                ],
            ],
        );
    }

    public function filterOptions(): JsonResponse
    {
        return $this->successResponse(
            message: 'Filter options retrieved successfully',
            data: [
                'filters' => [
                    [
                        'key' => 'has_admin_access',
                        'label' => 'Role',
                        'description' => 'Filter directory members by whether they have backoffice admin access',
                        'type' => 'single-select',
                        'options' => [
                            ['value' => '1', 'label' => 'Admin access'],
                            ['value' => '0', 'label' => 'User only'],
                        ],
                    ],
                ],
                'total_available_filters' => 1,
            ],
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::query()->create($request->validated());

        return $this->successResponse(
            message: 'User created successfully',
            data: (new AdminUserResource($user->load('adminBackofficeAccount')))->resolve(),
            statusCode: 201,
        );
    }

    public function show(
        User $user,
        BuildsAdminUserProfileSummary $buildsAdminUserProfileSummary,
    ): JsonResponse {
        $user->load('adminBackofficeAccount');

        return $this->successResponse(
            message: 'User fetched successfully',
            data: (new AdminUserProfileResource(
                $user,
                $buildsAdminUserProfileSummary->build($user),
            ))->resolve(),
        );
    }

    public function updateWallet(
        UpdateAdminUserWalletRequest $request,
        User $user,
        SetsMemberWalletAvailableBalanceAbsolute $setsMemberWalletAvailableBalanceAbsolute,
        BuildsAdminUserProfileSummary $buildsAdminUserProfileSummary,
    ): JsonResponse {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        $setsMemberWalletAvailableBalanceAbsolute->set(
            user: $user,
            admin: $admin,
            availableBalanceUsd: (float) $request->validated('available_balance'),
        );

        $user->load('adminBackofficeAccount');

        return $this->successResponse(
            message: 'Wallet balance updated successfully',
            data: (new AdminUserProfileResource(
                $user,
                $buildsAdminUserProfileSummary->build($user),
            ))->resolve(),
        );
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        if (array_key_exists('password', $validated) && blank($validated['password'])) {
            unset($validated['password']);
        }

        $user->update($validated);

        return $this->successResponse(
            message: 'User updated successfully',
            data: (new AdminUserResource($user->fresh()->load('adminBackofficeAccount')))->resolve(),
        );
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return $this->successResponse(
            message: 'User deleted successfully',
            data: null,
        );
    }

    public function promoteToAdmin(
        PromoteUserToAdminRequest $request,
        User $user,
        ComposesMemberLifecycleEmailCopy $composesMemberLifecycleEmailCopy,
        SendsMemberTransactionalEmail $sendsMemberTransactionalEmail,
    ): JsonResponse {
        if ($user->hasAdminBackofficeAccess()) {
            return $this->errorResponse(
                message: 'This user already has backoffice admin access',
                statusCode: 422,
            );
        }

        if (Admin::query()->where('email', $user->email)->exists()) {
            return $this->errorResponse(
                message: 'An admin account with this email already exists',
                statusCode: 422,
            );
        }

        $admin = Admin::query()->create([
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => $request->validated('password'),
            'is_super_admin' => false,
        ]);

        $sendsMemberTransactionalEmail->sendCopy(
            $user,
            $composesMemberLifecycleEmailCopy->promotedToAdmin($user),
        );

        return $this->successResponse(
            message: 'User promoted to admin successfully',
            data: (new AdminResource($admin))->resolve(),
            statusCode: 201,
        );
    }

    public function removeAdmin(
        RemoveAdminBackofficeAccessFromUserRequest $request,
        User $user,
    ): JsonResponse {
        $adminAccount = $user->adminBackofficeAccount;

        if ($adminAccount === null) {
            return $this->errorResponse(
                message: 'This user does not have backoffice admin access',
                statusCode: 422,
            );
        }

        // Super admins must not be demoted through the member directory action.
        if ($adminAccount->is_super_admin) {
            return $this->errorResponse(
                message: 'Super admin access cannot be removed from this screen',
                statusCode: 422,
            );
        }

        /** @var Admin $actingAdmin */
        $actingAdmin = Auth::guard('admin')->user();

        // Admins must not strip their own backoffice access mid-session.
        if (strcasecmp((string) $actingAdmin->email, (string) $adminAccount->email) === 0) {
            return $this->errorResponse(
                message: 'You cannot remove your own admin access',
                statusCode: 422,
            );
        }

        $adminAccount->delete();

        return $this->successResponse(
            message: 'Admin access removed successfully',
            data: (new AdminUserResource($user->fresh()->load('adminBackofficeAccount')))->resolve(),
        );
    }

    public function ban(
        BanUserRequest $request,
        User $user,
        ManagesUserAccountRestrictionStatus $managesUserAccountRestrictionStatus,
        BuildsAdminUserProfileSummary $buildsAdminUserProfileSummary,
    ): JsonResponse {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        try {
            $bannedUser = $managesUserAccountRestrictionStatus->ban(
                user: $user,
                admin: $admin,
                reason: $request->validated('reason'),
            );
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: 422,
            );
        }

        return $this->userProfileRestrictionResponse(
            message: 'User banned successfully',
            user: $bannedUser,
            buildsAdminUserProfileSummary: $buildsAdminUserProfileSummary,
        );
    }

    public function suspend(
        SuspendUserRequest $request,
        User $user,
        ManagesUserAccountRestrictionStatus $managesUserAccountRestrictionStatus,
        BuildsAdminUserProfileSummary $buildsAdminUserProfileSummary,
    ): JsonResponse {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        try {
            $suspendedUser = $managesUserAccountRestrictionStatus->suspend(
                user: $user,
                admin: $admin,
                suspendedUntil: Carbon::parse($request->validated('suspended_until')),
                reason: $request->validated('reason'),
            );
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: 422,
            );
        }

        return $this->userProfileRestrictionResponse(
            message: 'User suspended successfully',
            user: $suspendedUser,
            buildsAdminUserProfileSummary: $buildsAdminUserProfileSummary,
        );
    }

    public function unsuspend(
        UnsuspendUserRequest $request,
        User $user,
        ManagesUserAccountRestrictionStatus $managesUserAccountRestrictionStatus,
        BuildsAdminUserProfileSummary $buildsAdminUserProfileSummary,
    ): JsonResponse {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        try {
            $unsuspendedUser = $managesUserAccountRestrictionStatus->unsuspend(
                user: $user,
                admin: $admin,
                reason: $request->validated('reason'),
            );
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: 422,
            );
        }

        return $this->userProfileRestrictionResponse(
            message: 'User unsuspended successfully',
            user: $unsuspendedUser,
            buildsAdminUserProfileSummary: $buildsAdminUserProfileSummary,
        );
    }

    public function reactivate(
        ReactivateUserRequest $request,
        User $user,
        ManagesUserAccountRestrictionStatus $managesUserAccountRestrictionStatus,
        BuildsAdminUserProfileSummary $buildsAdminUserProfileSummary,
    ): JsonResponse {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        try {
            $reactivatedUser = $managesUserAccountRestrictionStatus->reactivate(
                user: $user,
                admin: $admin,
                reason: $request->validated('reason'),
            );
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: 422,
            );
        }

        return $this->userProfileRestrictionResponse(
            message: 'User reactivated successfully',
            user: $reactivatedUser,
            buildsAdminUserProfileSummary: $buildsAdminUserProfileSummary,
        );
    }

    private function userProfileRestrictionResponse(
        string $message,
        User $user,
        BuildsAdminUserProfileSummary $buildsAdminUserProfileSummary,
    ): JsonResponse {
        $user->load('adminBackofficeAccount');

        return $this->successResponse(
            message: $message,
            data: (new AdminUserProfileResource(
                $user,
                $buildsAdminUserProfileSummary->build($user),
            ))->resolve(),
        );
    }
}
