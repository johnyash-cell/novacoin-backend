<?php

namespace App\Services\Wallet;

use App\Mail\WalletReviewOutcomeMail;
use App\Models\Admin;
use App\Models\User;
use App\Services\Admin\SendsAdminInAppNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotifiesMemberAboutWalletReviewOutcome
{
    public function __construct(
        private SendsAdminInAppNotification $sendsAdminInAppNotification,
    ) {}

    public function notify(
        Admin $admin,
        User $user,
        bool $sendEmail,
        bool $sendInAppNotification,
        string $title,
        string $message,
    ): void {
        if ($sendInAppNotification) {
            $this->sendInAppNotificationSafely($admin, $user, $title, $message);
        }

        if ($sendEmail) {
            $this->sendEmailSafely($user, $title, $message);
        }
    }

    private function sendInAppNotificationSafely(Admin $admin, User $user, string $title, string $message): void
    {
        try {
            $this->sendsAdminInAppNotification->send($admin, [
                'title' => $this->truncate($title, 120),
                'message' => $this->truncate($message, 500),
                'audience_mode' => 'selected_users',
                'user_ids' => [$user->id],
                'delivery' => 'send_now',
            ]);
        } catch (Throwable $exception) {
            // Notify failure must not block the admin approve/decline response.
            Log::warning('Failed to send in-app notification for wallet review outcome', [
                'user_id' => $user->id,
                'admin_id' => $admin->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function sendEmailSafely(User $user, string $title, string $message): void
    {
        if (! filled($user->email)) {
            return;
        }

        try {
            Mail::to($user->email)->send(new WalletReviewOutcomeMail(
                emailSubject: $title,
                emailHeading: $title,
                emailBody: $message,
            ));
        } catch (Throwable $exception) {
            // Email failure must not block the admin approve/decline response.
            Log::warning('Failed to queue wallet review outcome email', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function truncate(string $value, int $maxLength): string
    {
        if (strlen($value) <= $maxLength) {
            return $value;
        }

        return rtrim(substr($value, 0, $maxLength - 1)).'…';
    }
}
