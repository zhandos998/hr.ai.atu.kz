<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends Controller
{
    public function __invoke(string $path): BinaryFileResponse
    {
        abort_unless(preg_match('#^(applications|resumes)/#', $path) && ! str_contains($path, '..') && ! str_contains($path, '\\'), 404);

        $disk = Storage::disk('documents');
        abort_unless($disk->exists($path), 404);
        $mime = $disk->mimeType($path) ?: 'application/octet-stream';
        $preview = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true);
        $response = response()->file($disk->path($path), [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "sandbox; default-src 'none'",
        ]);
        $response->setContentDisposition($preview ? 'inline' : 'attachment', basename($path));

        return $response;
    }
}
