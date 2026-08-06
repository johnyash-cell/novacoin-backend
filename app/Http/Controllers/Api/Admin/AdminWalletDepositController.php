<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\WalletDepositStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\ApproveWalletDepositRequest;
use App\Http\Requests\Api\Admin\DeclineWalletDepositRequest;
use App\Http\Requests\Api\Admin\IndexAdminWalletDepositsRequest;
use App\Http\Resources\AdminWalletDepositResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\Admin;
use App\Models\User;
use App\Models\WalletDeposit;
use App\Services\Wallet\BuildsWalletReviewOutcomeMemberMessage;
use App\Services\Wallet\CreditsUserWalletFromApprovedDeposit;
use App\Services\Wallet\NotifiesMemberAboutWalletReviewOutcome;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class AdminWalletDepositController extends Controller
{
    use RespondsWithApiEnvelope;

    public function filterOptions(): JsonResponse
    {
        $statusOptions = collect(WalletDepositStatus::cases())
            ->map(fn (WalletDepositStatus $status) => [
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
                        'description' => 'Filter deposits by review status',
                        'type' => 'single-select',
                        'options' => $statusOptions,
                    ],
                ],
                'total_available_filters' => 1,
            ],
        );
    }

    public function index(IndexAdminWalletDepositsRequest $request): JsonResponse
    {
        return $this->depositIndexResponse($request->validated());
    }

    public function indexForUser(IndexAdminWalletDepositsRequest $request, User $user): JsonResponse
    {
        return $this->depositIndexResponse(
            validated: $request->validated(),
            scopedUser: $user,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function depositIndexResponse(array $validated, ?User $scopedUser = null): JsonResponse
    {
        $sortBy = $validated['sort_by'] ?? 'newest';
        $perPage = $validated['per_page'] ?? 10;

        $deposits = WalletDeposit::query()
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
            message: 'Wallet deposits fetched successfully',
            data: AdminWalletDepositResource::collection($deposits->items())->resolve(),
            meta: [
                'pagination' => [
                    'current_page' => $deposits->currentPage(),
                    'per_page' => $deposits->perPage(),
                    'total' => $deposits->total(),
                    'last_page' => $deposits->lastPage(),
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

    public function show(WalletDeposit $walletDeposit): JsonResponse
    {
        $walletDeposit->load(['user', 'platformCryptoWallet', 'reviewedByAdmin']);

        return $this->successResponse(
            message: 'Wallet deposit fetched successfully',
            data: (new AdminWalletDepositResource($walletDeposit))->resolve(),
        );
    }

    public function approve(
        ApproveWalletDepositRequest $request,
        WalletDeposit $walletDeposit,
        CreditsUserWalletFromApprovedDeposit $creditsUserWalletFromApprovedDeposit,
        BuildsWalletReviewOutcomeMemberMessage $buildsWalletReviewOutcomeMemberMessage,
        NotifiesMemberAboutWalletReviewOutcome $notifiesMemberAboutWalletReviewOutcome,
    ): JsonResponse {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();
        $wasPendingApproval = $walletDeposit->status === WalletDepositStatus::PendingApproval->value;

        try {
            $approvedDeposit = $creditsUserWalletFromApprovedDeposit->approve($walletDeposit, $admin);
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: 422,
            );
        }

        if ($wasPendingApproval) {
            $approvedDeposit->loadMissing('user');
            $copy = $buildsWalletReviewOutcomeMemberMessage->forApprovedDeposit($approvedDeposit);

            if ($approvedDeposit->user !== null) {
                $notifiesMemberAboutWalletReviewOutcome->notify(
                    admin: $admin,
                    user: $approvedDeposit->user,
                    sendEmail: $request->shouldSendEmail(),
                    sendInAppNotification: $request->shouldSendInAppNotification(),
                    title: $copy['title'],
                    message: $copy['message'],
                );
            }
        }

        return $this->successResponse(
            message: 'Wallet deposit approved successfully',
            data: (new AdminWalletDepositResource($approvedDeposit))->resolve(),
        );
    }

    public function decline(
        DeclineWalletDepositRequest $request,
        WalletDeposit $walletDeposit,
        BuildsWalletReviewOutcomeMemberMessage $buildsWalletReviewOutcomeMemberMessage,
        NotifiesMemberAboutWalletReviewOutcome $notifiesMemberAboutWalletReviewOutcome,
    ): JsonResponse {
        if ($walletDeposit->status === WalletDepositStatus::Approved->value) {
            return $this->errorResponse(
                message: 'Approved deposits cannot be declined',
                statusCode: 422,
            );
        }

        if ($walletDeposit->status === WalletDepositStatus::Declined->value) {
            return $this->successResponse(
                message: 'Wallet deposit declined successfully',
                data: (new AdminWalletDepositResource($walletDeposit->load(['user', 'platformCryptoWallet'])))->resolve(),
            );
        }

        if ($walletDeposit->status !== WalletDepositStatus::PendingApproval->value) {
            return $this->errorResponse(
                message: 'Only pending deposits can be declined',
                statusCode: 422,
            );
        }

        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        $walletDeposit->update([
            'status' => WalletDepositStatus::Declined->value,
            'decline_reason' => $request->validated('decline_reason'),
            'reviewed_by_admin_id' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $declinedDeposit = $walletDeposit->fresh(['user', 'platformCryptoWallet']);
        $declinedDeposit?->loadMissing('user');
        $copy = $buildsWalletReviewOutcomeMemberMessage->forDeclinedDeposit($declinedDeposit ?? $walletDeposit);

        if ($declinedDeposit?->user !== null) {
            $notifiesMemberAboutWalletReviewOutcome->notify(
                admin: $admin,
                user: $declinedDeposit->user,
                sendEmail: $request->shouldSendEmail(),
                sendInAppNotification: $request->shouldSendInAppNotification(),
                title: $copy['title'],
                message: $copy['message'],
            );
        }

        return $this->successResponse(
            message: 'Wallet deposit declined successfully',
            data: (new AdminWalletDepositResource($declinedDeposit ?? $walletDeposit))->resolve(),
        );
    }
}
