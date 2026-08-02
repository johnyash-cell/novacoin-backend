<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiFormRequest;

class IndexUserPageVisitLogsRequest extends ApiFormRequest
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
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'page_path' => ['sometimes', 'string', 'max:2048'],
            'start_date' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d', 'required_with:end_date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d', 'required_with:start_date', 'after_or_equal:start_date'],
        ];
    }
}
