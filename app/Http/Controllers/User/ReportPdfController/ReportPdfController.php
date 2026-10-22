<?php

namespace App\Http\Controllers\User\ReportPdfController;

use App\Http\Controllers\Controller;
use App\Models\Report\Report;
use App\Support\ChatStaff\ChatStaff;
use App\Support\PdfLayout\PdfLayout;
use App\Support\PdfLayout\PdfText;
use App\Support\ReportCategory\ReportCategory;



use Dompdf\Dompdf;
use Symfony\Component\HttpFoundation\Response;

class ReportPdfController extends Controller
{
    public function download(Report $report): Response
    {
        $user = auth()->user();

        abort_unless(
            $user && ((int) $report->user_id === (int) $user->id || ChatStaff::isStaff($user)),
            403
        );

        return $this->renderPdf($report);
    }

    /**
     * Render PDF laporan.
     *
     * Dipisah dari download() supaya panel Welcome -- yang juga melayani tamu --
     * bisa memakai render yang sama setelah memeriksa kepemilikan lewat
     * GuestIdentity, bukan menggandakan seluruh blok HTML di bawah.
     */
    protected function renderPdf(Report $report): Response
    {
        $userName = e($report->user?->full_name ?? '-');
        // Kategori & tanggal TIDAK boleh tampil mentah: "general_question"
        // dan "21/10/2026" itu nilai teknis, bukan isi dokumen.
        $category = e(ReportCategory::label($report->category));
        $reason = e($report->reason ?? '-');
        $description = nl2br(e($report->description ?? '-'));
        $createdAt = e(PdfText::date($report->created_at));
        // Lampiran: cukup nama filenya -- URL penuh bring garis miring +
        // garis bawah ke dokumen resmi.
        $attachments = collect($report->attachment_urls)
            ->map(fn ($url) => '<p>'.e(PdfText::fileName($url)).'</p>')
            ->implode('');
        $html = "<html><meta charset='utf-8'><style>"
            .\App\Support\PdfLayout\PdfLayout::pageCss('portrait', 'body{font-size:12px}h1{font-size:20px}')
            ."</style>"
            .\App\Support\PdfLayout\PdfLayout::header("Formulir Laporan #{$report->id}")
            .\App\Support\PdfLayout\PdfLayout::footer()
            ."<h1>Formulir Laporan #{$report->id}</h1><p><b>Pelapor:</b> {$userName}</p><p><b>Kategori:</b> {$category}</p><p><b>Judul:</b> {$reason}</p><p><b>Tanggal:</b> {$createdAt}</p><p><b>Detail:</b><br>{$description}</p><h3>Lampiran</h3>{$attachments}</html>";

        $dompdf = \App\Support\PdfLayout\PdfLayout::apply(new Dompdf);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="laporan-'.$report->id.'.pdf"',
        ]);
    }
}