<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\IndexAuthenticationLoginLogsRequest;
use App\Http\Resources\AuthenticationLoginLogResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\AuthenticationLoginLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminAuthenticationLoginLogController extends Controller
{
    use RespondsWithApiEnvelope;

    public function index(IndexAuthenticationLoginLogsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $loginLogs = $this->buildLoginLogQuery($validated)->paginate(
            $validated['per_page'] ?? 10,
        );

        return $this->loginLogIndexResponse($loginLogs, $validated);
    }

    public function indexForUser(IndexAuthenticationLoginLogsRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        $loginLogs = $this->applyUserDirectoryMemberLoginLogScope(
            $this->buildLoginLogQuery($validated),
            $user,
        )->paginate($validated['per_page'] ?? 10);

        return $this->loginLogIndexResponse(
            loginLogs: $loginLogs,
            validated: $validated,
            scopedUser: $user,
        );
    }

    public function filterOptions(): JsonResponse
    {
        $distinctActorTypes = AuthenticationLoginLog::query()
            ->distinct()
            ->pluck('actor_type')
            ->all();

        $distinctLoginMethods = AuthenticationLoginLog::query()
            ->distinct()
            ->pluck('login_method')
            ->all();

        $actorTypeLabels = [
            'user' => 'User',
            'admin' => 'Admin',
        ];

        $loginMethodLabels = [
            'password' => 'Password',
            'google' => 'Google',
        ];

        $actorTypeOptions = [];
        foreach ($distinctActorTypes as $actorType) {
            $actorTypeOptions[] = [
                'value' => $actorType,
                'label' => $actorTypeLabels[$actorType] ?? ucfirst(str_replace('_', ' ', (string) $actorType)),
            ];
        }

        $loginMethodOptions = [];
        foreach ($distinctLoginMethods as $loginMethod) {
            $loginMethodOptions[] = [
                'value' => $loginMethod,
                'label' => $loginMethodLabels[$loginMethod] ?? ucfirst(str_replace('_', ' ', (string) $loginMethod)),
            ];
        }

        return $this->successResponse(
            message: 'Filter options retrieved successfully',
            data: [
                'filters' => [
                    [
                        'key' => 'actor_type',
                        'label' => 'Actor type',
                        'description' => 'Filter by whether the login attempt was for a user or admin account',
                        'type' => 'single-select',
                        'options' => $actorTypeOptions,
                    ],
                    [
                        'key' => 'login_method',
                        'label' => 'Login method',
                        'description' => 'Filter by how the actor attempted to sign in',
                        'type' => 'single-select',
                        'options' => $loginMethodOptions,
                    ],
                    [
                        'key' => 'was_successful',
                        'label' => 'Outcome',
                        'description' => 'Filter by whether the login attempt succeeded',
                        'type' => 'single-select',
                        'options' => [
                            ['value' => '1', 'label' => 'Successful'],
                            ['value' => '0', 'label' => 'Failed'],
                        ],
                    ],
                    [
                        'key' => 'date_range',
                        'label' => 'Date range',
                        'description' => 'Filter by login attempt date (start_date and end_date)',
                        'type' => 'date-range',
                        'options' => null,
                    ],
                ],
                'total_available_filters' => 4,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function buildLoginLogQuery(array $validated): Builder
    {
        $sortBy = $validated['sort_by'] ?? 'newest';

        $startDate = filled($validated['start_date'] ?? null)
            ? Carbon::parse($validated['start_date'])->startOfDay()
            : null;
        $endDate = filled($validated['end_date'] ?? null)
            ? Carbon::parse($validated['end_date'])->endOfDay()
            : null;

        return AuthenticationLoginLog::query()
            ->when(
                filled($validated['user_id'] ?? null),
                function (Builder $query) use ($validated): void {
                    $user = User::query()->findOrFail($validated['user_id']);

                    $this->applyUserDirectoryMemberLoginLogScope($query, $user);
                },
            )
            ->when(
                filled($validated['actor_type'] ?? null),
                fn (Builder $query) => $query->where('actor_type', $validated['actor_type']),
            )
            ->when(
                filled($validated['login_method'] ?? null),
                fn (Builder $query) => $query->where('login_method', $validated['login_method']),
            )
            ->when(
                array_key_exists('was_successful', $validated),
                fn (Builder $query) => $query->where('was_successful', $validated['was_successful']),
            )
            ->when(
                $startDate !== null && $endDate !== null,
                fn (Builder $query) => $query->whereBetween('created_at', [$startDate, $endDate]),
            )
            ->orderBy('created_at', $sortBy === 'newest' ? 'desc' : 'asc');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function loginLogIndexResponse(
        LengthAwarePaginator $loginLogs,
        array $validated,
        ?User $scopedUser = null,
    ): JsonResponse {
        $filters = [
            'user_id' => $scopedUser?->id ?? (isset($validated['user_id']) ? (int) $validated['user_id'] : null),
        
            'login_method' => $validated['login_method'] ?? null,
            'was_successful' => $validated['was_successful'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
        ];

        return $this->successResponse(
            message: 'Authentication login logs fetched successfully',
            data: AuthenticationLoginLogResource::collection($loginLogs->items())->resolve(),
            meta: [
                'pagination' => [
                    'current_page' => $loginLogs->currentPage(),
                    'per_page' => $loginLogs->perPage(),
                    'total' => $loginLogs->total(),
                    'last_page' => $loginLogs->lastPage(),
                ],
                'filters' => $filters,
            ],
        );
    }

    private function applyUserDirectoryMemberLoginLogScope(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $scopeQuery) use ($user): void {
            $scopeQuery
                ->where('email', $user->email)
                ->orWhere(function (Builder $userActorQuery) use ($user): void {
                    $userActorQuery
                        ->where('actor_type', 'user')
                        ->where('actor_id', $user->id);
                });
        });
    }
}
