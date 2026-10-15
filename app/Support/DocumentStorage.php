<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class DocumentStorage
{
    public static function url(string $path): string
    {
        return URL::temporarySignedRoute('documents.show', now()->addMinutes(15), ['path' => $path]);
    }

    public static function extension(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip'];

        if (! in_array($extension, $allowed, true)) {
            throw ValidationException::withMessages(['documents' => [__('messages.document_extension_invalid')]]);
        }

        return $extension;
    }
}
