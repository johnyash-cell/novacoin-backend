<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiFormRequest;

class RemoveAdminBackofficeAccessFromUserRequest extends ApiFormRequest
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
        return [];
    }
}
