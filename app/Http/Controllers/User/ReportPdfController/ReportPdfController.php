<?php

namespace App\Http\Controllers\User\ReportPdfController;

use App\Http\Controllers\Controller;
use App\Models\Report\Report;
use Dompdf\Dompdf;
use Symfony\Component\HttpFoundation\Response;

class ReportPdfController extends Controller
{
    public function download(Report $report): Response
    {
        $user = auth()->user();

        abort_unless(
            $user && ((int) $report->user_id === (int) $user->id || $user->hasRole('super_admin')),
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
        $categoryLabels = [
            'bug_report' => __('Lapor Bug'),
            'account_issue' => __('Masalah Akun'),
            'order_help' => __('Bantuan Pesanan'),
            'payment_issue' => __('Masalah Pembayaran'),
            'decor_consultation' => __('Konsultasi Dekorasi'),
            'general_question' => __('Pertanyaan Umum'),
        ];

        $userName = e($report->user?->full_name ?? '-');
        $category = e($categoryLabels[$report->category] ?? $report->category);
        $reason = e($report->reason ?? '-');
        $description = nl2br(e($report->description ?? '-'));
        $createdAt = e($report->created_at?->format('d/m/Y H:i') ?? '-');
        $attachments = collect($report->attachment_urls)
            ->map(fn ($url) => '<p><a href="'.e($url).'">'.e($url).'</a></p>')
            ->implode('');
        $html = "<html><meta charset='utf-8'><style>body{font-family:DejaVu Sans,sans-serif;font-size:12px}h1{font-size:20px}</style><h1>Formulir Laporan #{$report->id}</h1><p><b>Pelapor:</b> {$userName}</p><p><b>Kategori:</b> {$category}</p><p><b>Judul:</b> {$reason}</p><p><b>Tanggal:</b> {$createdAt}</p><p><b>Detail:</b><br>{$description}</p><h3>Lampiran</h3>{$attachments}</html>";

        $dompdf = new Dompdf;
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="laporan-'.$report->id.'.pdf"',
        ]);
    }
}