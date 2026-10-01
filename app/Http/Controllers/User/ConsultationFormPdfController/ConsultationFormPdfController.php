<?php

namespace App\Http\Controllers\User\ConsultationFormPdfController;

use App\Http\Controllers\Controller;
use App\Models\Inbox\Inbox;
use Dompdf\Dompdf;
use Symfony\Component\HttpFoundation\Response;

class ConsultationFormPdfController extends Controller
{
    public function download(Inbox $inbox): Response
    {
        $userId = auth()->id();

        if (! in_array($userId, $inbox->user_ids ?? []) && ! auth()->user()?->hasRole('super_admin')) {
            abort(403);
        }

        $forms = $inbox->meta['consultation_forms'] ?? [];
        $form = $forms[(string) $userId] ?? null;

        if (! is_array($form)) {
            abort(404, 'Formulir belum diisi.');
        }

        $html = view('User.pdf.consultation-form.consultation-form', compact('form', 'inbox'))->render();

        $dompdf = new Dompdf;
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="formulir-kebutuhan-acara-'.$inbox->id.'.pdf"',
        ]);
    }
}
