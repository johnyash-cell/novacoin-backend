<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\WalletWithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\ApproveWalletWithdrawalRequest;
use App\Http\Requests\Api\Admin\DeclineWalletWithdrawalRequest;
use App\Http\Requests\Api\Admin\IndexAdminWalletWithdrawalsRequest;
use App\Http\Resources\AdminWalletWithdrawalResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\Admin;
use App\Models\User;
use App\Models\WalletWithdrawal;
use App\Services\Wallet\ApprovesWalletWithdrawal;
use App\Services\Wallet\BuildsWalletReviewOutcomeMemberMessage;
use App\Services\Wallet\DeclinesWalletWithdrawal;
use App\Services\Wallet\NotifiesMemberAboutWalletReviewOutcome;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class AdminWalletWithdrawalController extends Controller
{
    use RespondsWithApiEnvelope;

    public function filterOptions(): JsonResponse
    {
        $statusOptions = collect(WalletWithdrawalStatus::cases())
            ->map(fn (WalletWithdrawalStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])
            ->values()
            ->all();

        return $this->successResponse(
            message: 'Filter options retrieved successfully',
            data: [
                'filters' => [
                    [
                        'key' => 'status',
                        'label' => 'Status',
                        'description' => 'Filter withdrawals by review status',
                        'type' => 'single-select',
                        'options' => $statusOptions,
                    ],
                ],
                'total_available_filters' => 1,
            ],
        );
    }

    public function index(IndexAdminWalletWithdrawalsRequest $request): JsonResponse
    {
        return $this->withdrawalIndexResponse($request->validated());
    }

    public function indexForUser(IndexAdminWalletWithdrawalsRequest $request, User $user): JsonResponse
    {
        return $this->withdrawalIndexResponse(
            validated: $request->validated(),
            scopedUser: $user,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function withdrawalIndexResponse(array $validated, ?User $scopedUser = null): JsonResponse
    {
        $sortBy = $validated['sort_by'] ?? 'newest';
        $perPage = $validated['per_page'] ?? 10;

        $withdrawals = WalletWithdrawal::query()
            ->with(['user', 'platformCryptoWallet'])
            ->when(
                $scopedUser !== null,
                fn ($query) => $query->where('user_id', $scopedUser->id),
            )
            ->search($validated['search'] ?? null)
            ->when(
                filled($validated['status'] ?? null),
                fn ($query) => $query->where('status', $validated['status']),
            )
            ->orderBy('created_at', $sortBy === 'newest' ? 'desc' : 'asc')
            ->paginate($perPage);

        return $this->successResponse(
            message: 'Wallet withdrawals fetched successfully',
            data: AdminWalletWithdrawalResource::collection($withdrawals->items())->resolve(),
            meta: [
                'pagination' => [
                    'current_page' => $withdrawals->currentPage(),
                    'per_page' => $withdrawals->perPage(),
                    'total' => $withdrawals->total(),
                    'last_page' => $withdrawals->lastPage(),
                ],
                'filters' => [
                    'user_id' => $scopedUser?->id,
                    'search' => $validated['search'] ?? null,
                    'status' => $validated['status'] ?? null,
                    'sort_by' => $sortBy,
                ],
            ],
        );
    }

    public function show(WalletWithdrawal $walletWithdrawal): JsonResponse
    {
        $walletWithdrawal->load(['user', 'platformCryptoWallet', 'reviewedByAdmin']);

        return $this->successResponse(
            message: 'Wallet withdrawal fetched successfully',
            data: (new AdminWalletWithdrawalResource($walletWithdrawal))->resolve(),
        );
    }

    public function approve(
        ApproveWalletWithdrawalRequest $request,
        WalletWithdrawal $walletWithdrawal,
        ApprovesWalletWithdrawal $approvesWalletWithdrawal,
        BuildsWalletReviewOutcomeMemberMessage $buildsWalletReviewOutcomeMemberMessage,
        NotifiesMemberAboutWalletReviewOutcome $notifiesMemberAboutWalletReviewOutcome,
    ): JsonResponse {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();
        $wasPendingApproval = $walletWithdrawal->status === WalletWithdrawalStatus::PendingApproval->value;

        try {
            $approvedWithdrawal = $approvesWalletWithdrawal->approve(
                walletWithdrawal: $walletWithdrawal,
                admin: $admin,
                outboundTransactionReference: $request->validated('outbound_transaction_reference'),
            );
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: 422,
            );
        }

        if ($wasPendingApproval) {
            $approvedWithdrawal->loadMissing('user');
            $copy = $buildsWalletReviewOutcomeMemberMessage->forApprovedWithdrawal($approvedWithdrawal);

            if ($approvedWithdrawal->user !== null) {
                $notifiesMemberAboutWalletReviewOutcome->notify(
                    admin: $admin,
                    user: $approvedWithdrawal->user,
                    sendEmail: $request->shouldSendEmail(),
                    sendInAppNotification: $request->shouldSendInAppNotification(),
                    title: $copy['title'],
                    message: $copy['message'],
                );
            }
        }

        return $this->successResponse(
            message: 'Wallet withdrawal approved successfully',
            data: (new AdminWalletWithdrawalResource($approvedWithdrawal))->resolve(),
        );
    }

    public function decline(
        DeclineWalletWithdrawalRequest $request,
        WalletWithdrawal $walletWithdrawal,
        DeclinesWalletWithdrawal $declinesWalletWithdrawal,
        BuildsWalletReviewOutcomeMemberMessage $buildsWalletReviewOutcomeMemberMessage,
        NotifiesMemberAboutWalletReviewOutcome $notifiesMemberAboutWalletReviewOutcome,
    ): JsonResponse {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();
        $wasPendingApproval = $walletWithdrawal->status === WalletWithdrawalStatus::PendingApproval->value;

        try {
            $declinedWithdrawal = $declinesWalletWithdrawal->decline(
                walletWithdrawal: $walletWithdrawal,
                admin: $admin,
                declineReason: $request->validated('decline_reason'),
            );
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: 422,
            );
        }

        if ($wasPendingApproval) {
            $declinedWithdrawal->loadMissing('user');
            $copy = $buildsWalletReviewOutcomeMemberMessage->forDeclinedWithdrawal($declinedWithdrawal);

            if ($declinedWithdrawal->user !== null) {
                $notifiesMemberAboutWalletReviewOutcome->notify(
                    admin: $admin,
                    user: $declinedWithdrawal->user,
                    sendEmail: $request->shouldSendEmail(),
                    sendInAppNotification: $request->shouldSendInAppNotification(),
                    title: $copy['title'],
                    message: $copy['message'],
                );
            }
        }

        return $this->successResponse(
            message: 'Wallet withdrawal declined successfully',
            data: (new AdminWalletWithdrawalResource($declinedWithdrawal))->resolve(),
        );
    }
}
