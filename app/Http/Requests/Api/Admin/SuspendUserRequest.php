<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiFormRequest;

class SuspendUserRequest extends ApiFormRequest
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
            'suspended_until' => ['required', 'date', 'after:now'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
