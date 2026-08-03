<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class IndexAdminNotificationsRequest extends ApiFormRequest
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
            'sort_by' => ['sometimes', 'string', Rule::in(['newest', 'oldest'])],
            'audience_mode' => ['sometimes', 'string', Rule::in(['all_users', 'selected_users'])],
            'start_date' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d', 'required_with:end_date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d', 'required_with:start_date', 'after_or_equal:start_date'],
        ];
    }
}
