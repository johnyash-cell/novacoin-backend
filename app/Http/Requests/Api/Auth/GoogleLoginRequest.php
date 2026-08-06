<?php

namespace App\Http\Requests\Api\Auth;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Support\Str;

class GoogleLoginRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('referral_code')) {
            $this->merge([
                'referral_code' => Str::upper(trim((string) $this->input('referral_code'))),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Do not Rule::exists here — existing Google logins may send a stale code; attach only runs for new users.
        return [
            'id_token' => ['required', 'string'],
            'referral_code' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
