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
            'reply_markup.*.url' => ['sometimes', 'nullable', 'url'],
            'reply_markup.*.callback_data' => ['sometimes', 'nullable', 'string', 'max:64'],
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
            if (is_array($markup)) {
                foreach ($markup as $i => $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $url = isset($row['url']) && is_string($row['url']) && $row['url'] !== '';
                    $cb = isset($row['callback_data']) && is_string($row['callback_data']) && $row['callback_data'] !== '';
                    if ($url && $cb) {
                        $validator->errors()->add("reply_markup.$i", 'Provide either url or callback_data, not both.');
                    }
                    if (! $url && ! $cb) {
                        $validator->errors()->add("reply_markup.$i", 'Each button must have url or callback_data.');
                    }
                }
            }
        });
    }
}
