<?php

namespace App\Http\Controllers\Welcome\ReportPdfController;

use App\Http\Controllers\User\ReportPdfController\ReportPdfController as UserReportPdfController;
use App\Models\Report\Report;
use App\Services\GuestIdentity\GuestIdentity;
use Symfony\Component\HttpFoundation\Response;

class ReportPdfController extends UserReportPdfController
{
    /**
     * Panel Welcome melayani tamu, jadi auth()->user() bisa null -- dan
     * laporan pun bisa dibuat oleh tamu lewat formulir di halaman Messages.
     *
     * Route-nya sengaja TANPA middleware auth (lihat routes/web/web.php):
     * dengan auth, tamu yang lapor bug akan mendarat di halaman login
     * alih-alih menerima PDF-nya, dan window.location.assign() dari Livewire
     * ikut ter-follow ke sana.
     *
     * Karena begitu, kepemilikan tidak boleh dititipkan ke middleware.
     * Diperiksa di sini lewat GuestIdentity, yang mengembalikan user id
     * untuk pengunjung yang sudah login maupun baris tamu milik peramban
     * ini -- pola yang sama dengan InteractsWithGuestIdentity di komponen
     * Messages. Pelapor lain tetap 403.
     */
    public function download(Report $report): Response
    {
        if (auth()->user() !== null) {
            return parent::download($report);
        }

        $identityId = app(GuestIdentity::class)->id();

        abort_unless(
            $identityId !== null && (int) $report->user_id === (int) $identityId,
            403
        );

        return $this->renderPdf($report);
    }
}