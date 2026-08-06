<?php

namespace App\Http\Requests\Api\Auth;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterUserRequest extends ApiFormRequest
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
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:50'],
            'referral_code' => [
                'sometimes',
                'nullable',
                'string',
                'max:32',
                Rule::exists('users', 'referral_code'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'referral_code.exists' => 'This referral code is invalid.',
        ];
    }
}
