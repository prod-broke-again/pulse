<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class ListQuickLinksRequest extends FormRequest
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
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'scope_type' => ['sometimes', 'nullable', 'string', 'in:source,department'],
            'scope_id' => ['sometimes', 'nullable', 'integer'],
            'visibility' => ['sometimes', 'nullable', 'string', 'in:mine,shared,all'],
            'chat_context' => ['sometimes', 'boolean'],
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],
            'include_inactive' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('include_inactive')) {
            $this->merge([
                'include_inactive' => filter_var($this->input('include_inactive'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ]);
        }
        if ($this->has('chat_context')) {
            $this->merge([
                'chat_context' => filter_var($this->input('chat_context'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ]);
        }
    }
}
