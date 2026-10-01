<?php

namespace App\Http\Controllers\User\MediaController;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    public function serve(string $path): BinaryFileResponse
    {
        if (str_contains($path, '..') || str_contains($path, "\0")) {
            abort(403);
        }

        $file = storage_path('app/public/'.$path);

        if (! file_exists($file)) {
            abort(404);
        }

        return response()->file($file, ['Content-Type' => File::mimeType($file)]);
    }
}
