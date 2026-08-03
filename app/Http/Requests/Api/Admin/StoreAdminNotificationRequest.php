<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreAdminNotificationRequest extends ApiFormRequest
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
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:500'],
            'audience_mode' => ['required', 'string', Rule::in(['all_users', 'selected_users'])],
            'user_ids' => [
                'required_if:audience_mode,selected_users',
                'array',
                'min:1',
            ],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'delivery' => ['required', 'string', Rule::in(['send_now'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_ids.required_if' => 'Select at least one user when audience mode is selected users.',
            'user_ids.min' => 'Select at least one user when audience mode is selected users.',
        ];
    }
}
