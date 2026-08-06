<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiFormRequest;

class IndexPlatformCryptoWalletsRequest extends ApiFormRequest
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
            'is_available_for_funding' => ['sometimes', 'nullable', 'boolean'],
            'is_available_for_withdrawal' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->mergeBooleanQueryParam('is_available_for_funding');
        $this->mergeBooleanQueryParam('is_available_for_withdrawal');
    }

    private function mergeBooleanQueryParam(string $key): void
    {
        if ($this->has($key) && is_string($this->input($key))) {
            $this->merge([
                $key => filter_var(
                    $this->input($key),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE,
                ),
            ]);
        }
    }
}
