<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class IndexUserNotificationsRequest extends ApiFormRequest
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
            'unread_only' => ['sometimes', 'boolean'],
        ];
    }
}
