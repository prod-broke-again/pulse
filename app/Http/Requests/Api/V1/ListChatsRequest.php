<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class ListChatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tab' => ['sometimes', 'string', 'in:my,unassigned,all'],
            'source_id' => ['sometimes', 'nullable', 'integer'],
            'source_ids' => ['sometimes', 'nullable', 'array'],
            'source_ids.*' => ['integer'],
            'department_id' => ['sometimes', 'nullable', 'integer'],
            'department_ids' => ['sometimes', 'nullable', 'array'],
            'department_ids.*' => ['integer'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:open,closed,all'],
            'channels' => ['sometimes', 'array'],
            'channels.*' => ['string', 'in:tg,vk,web,max'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
