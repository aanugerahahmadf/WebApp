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

        abort_unless(preg_match('/^[A-Za-z0-9\-]+\.(zip|xlsx|pdf)$/', $name) === 1, 404);

        $path = storage_path('app/private/data-exports/'.$name);

        abort_unless(is_file($path), 404);

        if (str_ends_with($name, '.zip')) {
            // Hapus direktori kerja ekstraksi (XLSX sudah terbungkus di ZIP).
            File::deleteDirectory(substr($path, 0, -4));
            $downloadName = 'data-aplikasi-'.date('Ymd-His').'.zip';
        } elseif (str_ends_with($name, '.pdf')) {
            $downloadName = 'data-aplikasi-'.date('Ymd-His').'.pdf';
        } else {
            $downloadName = 'data-aplikasi-'.date('Ymd-His').'.xlsx';
        }

        return response()->download($path, $downloadName)->deleteFileAfterSend(true);
    }
}
