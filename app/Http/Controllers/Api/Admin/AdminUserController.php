<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\IndexUsersRequest;
use App\Http\Requests\Api\Admin\PromoteUserToAdminRequest;
use App\Http\Requests\Api\Admin\StoreUserRequest;
use App\Http\Requests\Api\Admin\UpdateUserRequest;
use App\Http\Resources\AdminDirectoryMemberResource;
use App\Http\Resources\AdminResource;
use App\Http\Resources\AdminUserProfileResource;
use App\Http\Resources\AdminUserResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\Admin;
use App\Models\User;
use App\Services\Admin\BuildsAdminUserProfileSummary;
use App\Services\Admin\PaginatesAdminUserDirectoryListing;
use Illuminate\Http\JsonResponse;

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

    public function promoteToAdmin(PromoteUserToAdminRequest $request, User $user): JsonResponse
    {
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

        return $this->successResponse(
            message: 'User promoted to admin successfully',
            data: (new AdminResource($admin))->resolve(),
            statusCode: 201,
        );
    }
}
