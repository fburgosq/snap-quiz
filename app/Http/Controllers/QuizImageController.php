<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuizImageController extends Controller
{
    /**
     * Stream the original source image for a quiz when authorized.
     */
    public function show(Request $request, Quiz $quiz): StreamedResponse
    {
        $currentUserId = $request->user()?->id;

        if ($quiz->user_id !== $currentUserId) {
            abort(403);
        }

        $path = $quiz->source_image_path;

        if (! is_string($path) || $path === '') {
            abort(404);
        }

        $diskName = (string) config('filesystems.default', 'local');
        $disk = Storage::disk($diskName);

        if (! $disk->exists($path)) {
            abort(404);
        }

        $mimeType = $disk->mimeType($path);

        if (! is_string($mimeType) || $mimeType === '') {
            $mimeType = 'application/octet-stream';
        }

        return $disk->response($path, null, [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
            'Content-Disposition' => 'inline',
        ]);
    }
}
