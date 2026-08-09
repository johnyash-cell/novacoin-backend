<?php

namespace App\Http\Requests\Api\Admin;

use App\Enums\UserAccountRestrictionLogAction;
use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class IndexUserAccountRestrictionLogsRequest extends ApiFormRequest
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
            'action' => ['sometimes', 'nullable', 'string', Rule::in(UserAccountRestrictionLogAction::values())],
            'start_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'required_with:end_date'],
            'end_date' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d',
                'required_with:start_date',
                'after_or_equal:start_date',
            ],
        ];
    }
}
