<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiFormRequest;

class IndexAuthenticationLoginLogsRequest extends ApiFormRequest
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
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'actor_type' => ['sometimes', 'string', 'in:user,admin'],
            'login_method' => ['sometimes', 'string', 'in:password,google'],
            'was_successful' => ['sometimes', 'boolean'],
            'start_date' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d', 'required_with:end_date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d', 'required_with:start_date', 'after_or_equal:start_date'],
        ];
    }
}
