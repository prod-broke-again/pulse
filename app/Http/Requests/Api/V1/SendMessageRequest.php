<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Infrastructure\Persistence\Eloquent\ChatModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'text' => [
                'nullable',
                'string',
                'max:10000',
            ],
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
            'reply_markup' => ['sometimes', 'nullable', 'array'],
            'reply_markup.*.text' => ['required', 'string', 'max:40'],
            'reply_markup.*.url' => ['required', 'url'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $text = $this->input('text');
            $hasText = is_string($text) && trim($text) !== '';
            $attachments = $this->input('attachments');
            $hasAttachments = is_array($attachments) && count($attachments) > 0;
            $markup = $this->input('reply_markup');
            $hasMarkup = is_array($markup) && count($markup) > 0;

            if (! $hasText && ! $hasAttachments && ! $hasMarkup) {
                $validator->errors()->add(
                    'text',
                    __('validation.required', ['attribute' => 'text']),
                );
            }
        });
    }
}
