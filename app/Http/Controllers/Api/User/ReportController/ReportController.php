<?php

namespace App\Http\Controllers\Api\User\ReportController;

use App\Http\Controllers\Controller;
use App\Models\Report\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Dompdf\Dompdf;

class ReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', Rule::in(['product', 'package', 'vendor', 'order', 'review', 'general', 'bug_report', 'account_issue', 'order_help', 'payment_issue', 'decor_consultation', 'general_question'])],
            'reportable_type' => ['required_unless:category,general,bug_report,account_issue,order_help,payment_issue,decor_consultation,general_question', 'nullable', 'string'],
            'reportable_id' => ['required_unless:category,general,bug_report,account_issue,order_help,payment_issue,decor_consultation,general_question', 'nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,mp4,mov,webm', 'max:51200'],
        ]);

        try {
            if (in_array($data['category'], ['general', 'bug_report', 'account_issue', 'order_help', 'payment_issue', 'decor_consultation', 'general_question'], true)) {
                $data['reportable_type'] = null;
                $data['reportable_id'] = null;
            } else {
                $type = $data['reportable_type'];
                if (! class_exists($type) || ! is_subclass_of($type, 'Illuminate\Database\Eloquent\Model')) {
                    return response()->json([
                        'status' => 'error',
                        'message' => __('Tipe target laporan tidak valid'),
                    ], 422);
                }
            }

            $attachments = collect($request->file('attachments', []))
                ->map(fn ($file) => $file->store('reports', 'public'))
                ->all();

            $report = Report::create([
                'user_id' => $request->user()->id,
                'reportable_type' => $data['reportable_type'] ?? null,
                'reportable_id' => $data['reportable_id'] ?? null,
                'category' => $data['category'],
                'reason' => $data['reason'] ?? null,
                'description' => $data['description'] ?? null,
                'attachments' => $attachments,
                'status' => \App\Enums\ReportStatus\ReportStatus::OPEN,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => __('Laporan berhasil dikirim'),
                'data' => [
                    'id' => $report->id,
                    'category' => $report->category,
                    'status' => $report->status->value,
                    'attachments' => $report->attachment_urls,
                    'created_at' => $report->created_at?->toISOString(),
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengirim laporan'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $reports = $request->user()->reports()
            ->latest()
            ->get()
            ->map(fn (Report $r) => [
                'id' => $r->id,
                'category' => $r->category,
                'reason' => $r->reason,
                'description' => $r->description,
                'attachments' => $r->attachment_urls,
                'status' => $r->status->value,
                'created_at' => $r->created_at?->toISOString(),
            ]);

        return response()->json([
            'status' => 'success',
            'data' => $reports,
        ]);
    }

    public function downloadPdf(Request $request, Report $report)
    {
        abort_unless((int) $report->user_id === (int) $request->user()->id, 403);

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

        $pdf = new Dompdf;
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="laporan-'.$report->id.'.pdf"',
        ]);
    }
}
