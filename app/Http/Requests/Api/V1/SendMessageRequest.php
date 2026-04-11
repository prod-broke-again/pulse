<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Infrastructure\Persistence\Eloquent\ChatModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'reply_to_message_id' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                Rule::exists('messages', 'id')->where(function ($query): void {
                    $chat = $this->route('chat');
                    if ($chat instanceof ChatModel) {
                        $query->where('chat_id', $chat->id);
                    }
                }),
            ],
        ];
    }
}
