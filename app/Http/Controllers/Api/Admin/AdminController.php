<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\IndexAdminsRequest;
use App\Http\Requests\Api\Admin\StoreAdminRequest;
use App\Http\Resources\AdminResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    use RespondsWithApiEnvelope;

    public function index(IndexAdminsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $sortBy = $validated['sort_by'] ?? 'newest';
        $perPage = $validated['per_page'] ?? 10;

        $admins = Admin::query()
            ->orderBy('created_at', $sortBy === 'newest' ? 'desc' : 'asc')
            ->paginate($perPage);

        return $this->successResponse(
            message: 'Admins fetched successfully',
            data: AdminResource::collection($admins->items())->resolve(),
            meta: [
                'pagination' => [
                    'current_page' => $admins->currentPage(),
                    'per_page' => $admins->perPage(),
                    'total' => $admins->total(),
                    'last_page' => $admins->lastPage(),
                ],
            ],
        );
    }

    public function store(StoreAdminRequest $request): JsonResponse
    {
        $admin = Admin::query()->create($request->validated());
        $admin->is_super_admin = false;
        $admin->save();

        return $this->successResponse(
            message: 'Admin created successfully',
            data: (new AdminResource($admin->fresh()))->resolve(),
            statusCode: 201,
        );
    }
}
