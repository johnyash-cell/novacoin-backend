<?php

namespace App\Http\Requests\Api\Admin;

use App\Enums\ReferralRewardPayoutMode;
use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateReferralSettingsRequest extends ApiFormRequest
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
            'reward_amount_usd' => ['sometimes', 'numeric', 'gt:0'],
            'payout_mode' => ['sometimes', 'string', Rule::in(ReferralRewardPayoutMode::values())],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                ! $this->exists('reward_amount_usd')
                && ! $this->exists('payout_mode')
            ) {
                $validator->errors()->add(
                    'reward_amount_usd',
                    'Provide at least one of reward_amount_usd or payout_mode.',
                );
            }
        });
    }
}
