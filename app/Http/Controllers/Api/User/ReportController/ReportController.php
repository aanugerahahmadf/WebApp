<?php

namespace App\Http\Controllers\Api\User\ReportController;

use App\Http\Controllers\Controller;
use App\Models\Report\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', Rule::in(['product', 'package', 'vendor', 'order', 'review', 'general'])],
            'reportable_type' => ['required_unless:category,general', 'nullable', 'string'],
            'reportable_id' => ['required_unless:category,general', 'nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            if ($data['category'] === 'general') {
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

            $report = Report::create([
                'user_id' => $request->user()->id,
                'reportable_type' => $data['reportable_type'] ?? null,
                'reportable_id' => $data['reportable_id'] ?? null,
                'category' => $data['category'],
                'reason' => $data['reason'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => \App\Enums\ReportStatus\ReportStatus::OPEN,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => __('Laporan berhasil dikirim'),
                'data' => [
                    'id' => $report->id,
                    'category' => $report->category,
                    'status' => $report->status->value,
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
                'status' => $r->status->value,
                'created_at' => $r->created_at?->toISOString(),
            ]);

        return response()->json([
            'status' => 'success',
            'data' => $reports,
        ]);
    }
}
