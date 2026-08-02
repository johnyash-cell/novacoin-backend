<?php

namespace App\Http\Requests\Api\Activity;

use App\Http\Requests\Api\ApiFormRequest;

class StoreUserPageVisitRequest extends ApiFormRequest
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
            'page_path' => ['required', 'string', 'max:2048', 'regex:/^\//'],
            'page_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'referrer' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'traffic_source' => ['sometimes', 'nullable', 'string', 'in:direct,app,referral,organic,email'],
        ];
    }
}
