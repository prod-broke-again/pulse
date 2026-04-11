<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class SsoExchangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'access_token' => ['required_without:code', 'string'],
            'code' => ['required_without:access_token', 'string'],
            'code_verifier' => ['required_with:code', 'string'],
            'redirect_uri' => ['required_with:code', 'string', 'max:2048'],
            'state' => ['nullable', 'string', 'max:4096'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($this->filled('access_token') && $this->filled('code')) {
                $v->errors()->add('code', __('Do not send access_token together with authorization code.'));
            }
        });
    }
}
