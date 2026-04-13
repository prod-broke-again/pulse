<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

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
            'source_id' => ['nullable', 'integer', 'exists:sources,id'],
            'code' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:500'],
            'text' => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
