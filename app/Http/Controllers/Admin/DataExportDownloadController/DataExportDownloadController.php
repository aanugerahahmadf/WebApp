<?php

namespace App\Http\Controllers\Admin\DataExportDownloadController;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DataExportDownloadController extends Controller
{
    public function download(string $file): BinaryFileResponse
    {
        $name = basename($file);

        abort_unless(preg_match('/^[A-Za-z0-9\-]+\.zip$/', $name) === 1, 404);

        $path = storage_path('app/private/data-exports/'.$name);

        abort_unless(is_file($path), 404);

        // Hapus direktori kerja ekstraksi (XLSX sudah terbungkus di ZIP).
        File::deleteDirectory(substr($path, 0, -4));

        return response()->download($path, 'data-aplikasi-'.date('Ymd-His').'.zip')->deleteFileAfterSend(true);
    }
}
