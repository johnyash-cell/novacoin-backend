<?php

namespace App\Http\Requests\Api\Admin;

use App\Enums\InvestmentPackageAvailabilityStatus;
use App\Http\Requests\Api\ApiFormRequest;
use App\Models\InvestmentPackage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateInvestmentPackageAvailabilityStatusRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'availability_status' => ['required', 'string', Rule::in(InvestmentPackageAvailabilityStatus::values())],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var InvestmentPackage $investmentPackage */
            $investmentPackage = $this->route('investment_package');
            $investmentPackage->expireIfDue();
            $investmentPackage->refresh();

            $availabilityStatus = (string) $this->input('availability_status');
            $statusEnum = InvestmentPackageAvailabilityStatus::tryFrom($availabilityStatus);

            if ($statusEnum?->isJoinableIntent() && $investmentPackage->isAtParticipantCapacity()) {
                $validator->errors()->add(
                    'availability_status',
                    'This investment package is at capacity and cannot be set to open or limited.',
                );
            }
        });
    }
}
