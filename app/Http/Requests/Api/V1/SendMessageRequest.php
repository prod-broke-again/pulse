<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'text' => ['required_without:attachments', 'nullable', 'string', 'max:10000'],
            'attachments' => ['sometimes', 'array', 'max:10'],
            'attachments.*' => ['string'],
            'client_message_id' => ['sometimes', 'nullable', 'string', 'uuid', 'max:36'],
        ];
    }
}
