<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreCannedResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'source_id' => ['sometimes', 'nullable', 'integer', 'exists:sources,id'],
            'scope_type' => ['sometimes', 'nullable', 'string', 'in:source,department'],
            'scope_id' => ['sometimes', 'nullable', 'integer'],
            'is_shared' => ['sometimes', 'boolean'],
            'code' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:500'],
            'text' => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $scopeType = $this->input('scope_type');
            $scopeId = $this->input('scope_id');
            $legacySource = $this->input('source_id');

            if (($scopeType === null || $scopeType === '') && $legacySource === null) {
                return;
            }

            if (($scopeType === null || $scopeType === '') && $legacySource !== null) {
                return;
            }

            if ($scopeType === 'source' && ($scopeId === null || $scopeId === '')) {
                $v->errors()->add('scope_id', 'Укажите источник для scope_type=source.');
            }

            if ($scopeType === 'department' && ($scopeId === null || $scopeId === '')) {
                $v->errors()->add('scope_id', 'Укажите отдел для scope_type=department.');
            }
        });
    }
}
