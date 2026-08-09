<?php

namespace App\Http\Requests\Api\Admin\Concerns;

trait ValidatesOptionalMemberNotifyFlags
{
    /**
     * @return array<string, mixed>
     */
    protected function optionalMemberNotifyFlagRules(): array
    {
        return [
            'send_email' => ['sometimes', 'boolean'],
            'send_in_app_notification' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareOptionalMemberNotifyFlagsForValidation(): void
    {
        $this->mergeBooleanBodyParam('send_email');
        $this->mergeBooleanBodyParam('send_in_app_notification');
    }

    private function mergeBooleanBodyParam(string $key): void
    {
        if (! $this->has($key)) {
            return;
        }

        $value = $this->input($key);

        if (is_bool($value)) {
            return;
        }

        if (is_string($value) || is_int($value)) {
            $this->merge([
                $key => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }

    public function shouldSendEmail(): bool
    {
        // Default on — money review outcomes should email unless admin opts out.
        return (bool) ($this->validated('send_email') ?? true);
    }

    public function shouldSendInAppNotification(): bool
    {
        return (bool) ($this->validated('send_in_app_notification') ?? false);
    }
}
