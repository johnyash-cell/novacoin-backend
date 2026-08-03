<?php

namespace App\Http\Requests\Api\Admin\Concerns;

use App\Enums\InvestmentPackageAvailabilityStatus;
use Illuminate\Validation\Validator;

trait ValidatesInvestmentPackagePayload
{
    protected function validateInvestmentPackageCapacityAgainstAvailability(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $joinedCount = $this->integer('joined_count');
            $maxParticipants = $this->integer('max_participants');
            $availabilityStatus = (string) $this->input('availability_status');

            if ($joinedCount === null || $maxParticipants === null || $availabilityStatus === '') {
                return;
            }

            $statusEnum = InvestmentPackageAvailabilityStatus::tryFrom($availabilityStatus);

            if ($statusEnum?->isJoinableIntent() && $joinedCount >= $maxParticipants) {
                $validator->errors()->add(
                    'availability_status',
                    'This investment package is at capacity and cannot be set to open or limited.',
                );
            }
        });
    }
}
