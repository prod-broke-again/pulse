<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class UploadFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:20480',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,application/pdf,'.
                'audio/aac,audio/x-aac,audio/mp4,audio/m4a,audio/x-m4a,audio/webm,audio/ogg,'.
                'audio/mpeg,audio/wav,audio/x-wav,video/webm',
            ],
        ];
    }
}
