<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\UploadFileRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class UploadController extends Controller
{
    public function store(UploadFileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs('uploads/pending/'.$user->id, $filename, 'local');

        return response()->json([
            'data' => [
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ],
        ], 201);
    }
}
