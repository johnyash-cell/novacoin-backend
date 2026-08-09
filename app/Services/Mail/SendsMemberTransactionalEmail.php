<?php

namespace App\Services\Mail;

use App\Mail\MemberTransactionalMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendsMemberTransactionalEmail
{
    /**
     * @param  array{subject: string, heading: string, body: string}  $copy
     */
    public function sendCopy(User $user, array $copy): void
    {
        $this->send($user, $copy['subject'], $copy['heading'], $copy['body']);
    }

    public function send(User $user, string $subject, string $heading, string $body): void
    {
        if (! filled($user->email)) {
            return;
        }

        try {
            Mail::to($user->email)->send(new MemberTransactionalMail(
                emailSubject: $subject,
                emailHeading: $heading,
                emailBody: $body,
            ));
        } catch (Throwable $exception) {
            // Email failure must not block the primary business action.
            Log::warning('Failed to queue member transactional email', [
                'user_id' => $user->id,
                'subject' => $subject,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
