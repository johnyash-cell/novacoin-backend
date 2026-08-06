<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiFormRequest;

class IndexAdminReferralRewardPayoutsRequest extends ApiFormRequest
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
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['sometimes', 'string', 'in:newest,oldest'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'start_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'required_with:end_date'],
            'end_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'required_with:start_date', 'after_or_equal:start_date'],
            'referrer_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'referred_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
