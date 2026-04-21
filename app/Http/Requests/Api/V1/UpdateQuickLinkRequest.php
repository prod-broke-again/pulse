<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateQuickLinkRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:500'],
            'url' => ['sometimes', 'string', 'max:2048', 'url'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $scopeType = $this->input('scope_type');
            $scopeId = $this->input('scope_id');

            if ($scopeType === 'source' && ($scopeId === null || $scopeId === '')) {
                $v->errors()->add('scope_id', 'Укажите источник для scope_type=source.');
            }

            if ($scopeType === 'department' && ($scopeId === null || $scopeId === '')) {
                $v->errors()->add('scope_id', 'Укажите отдел для scope_type=department.');
            }
        });
    }
}
