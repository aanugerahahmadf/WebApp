<?php

namespace App\Http\Controllers\User\MediaController;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    public function serve(string $path): BinaryFileResponse
    {
        $path = ltrim(str_replace('\\', '/', rawurldecode($path)), '/');
        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")
            || preg_match('/(^|\/)(ktp|selfie|face|identity|document|private|proof|messages?)(\/|$)/i', $path)) {
            abort(403);
        }

        $root = realpath(storage_path('app/public'));
        $file = realpath(storage_path('app/public/'.$path));

        if ($root === false || $file === false
            || ! str_starts_with($file, $root.DIRECTORY_SEPARATOR)
            || ! is_file($file)) {
            abort(404);
        }

        $mime = File::mimeType($file);
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'application/pdf'];
        if (! in_array($mime, $allowed, true)) {
            abort(403);
        }

        return response()->file($file, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
