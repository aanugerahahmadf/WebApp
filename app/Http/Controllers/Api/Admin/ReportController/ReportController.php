<?php

namespace App\Http\Controllers\Api\Admin\ReportController;

use App\Enums\ReportStatus\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\Report\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Report::with('user:id,full_name,email,avatar_url');

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('category')) {
                $query->where('category', $request->category);
            }

            if ($request->filled('search')) {
                $s = $request->search;
                $query->where(function ($q) use ($s): void {
                    $q->where('description', 'like', "%{$s}%")
                        ->orWhere('reason', 'like', "%{$s}%")
                        ->orWhereHas('user', fn ($u) => $u->where('full_name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
                });
            }

            $reports = $query->latest()->paginate($request->get('per_page', 20));

            $data = collect($reports->items())->map(fn (Report $r) => [
                'id' => $r->id,
                'user' => [
                    'id' => $r->user?->id,
                    'full_name' => $r->user?->full_name,
                    'email' => $r->user?->email,
                    'avatar_url' => $r->user?->avatar_url,
                ],
                'category' => $r->category,
                'reason' => $r->reason,
                'description' => $r->description,
                'status' => $r->status->value,
                'reportable_type' => $r->reportable_type,
                'reportable_id' => $r->reportable_id,
                'reportable_label' => $r->reportable?->name ?? $r->reportable?->store_name ?? $r->reportable?->comment ?? $r->reportable?->order_number ?? '-',
                'resolved_at' => $r->resolved_at?->toISOString(),
                'created_at' => $r->created_at?->toISOString(),
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'pagination' => [
                    'current_page' => $reports->currentPage(),
                    'last_page' => $reports->lastPage(),
                    'per_page' => $reports->perPage(),
                    'total' => $reports->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal memuat laporan'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $report = Report::with('user:id,full_name,email,avatar_url', 'resolver:id,full_name,email')->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'id' => $report->id,
                    'user' => [
                        'id' => $report->user?->id,
                        'full_name' => $report->user?->full_name,
                        'email' => $report->user?->email,
                    ],
                    'category' => $report->category,
                    'reason' => $report->reason,
                    'description' => $report->description,
                    'status' => $report->status->value,
                    'reportable_type' => $report->reportable_type,
                    'reportable_id' => $report->reportable_id,
                    'reportable_label' => $report->reportable?->name ?? $report->reportable?->store_name ?? $report->reportable?->comment ?? $report->reportable?->order_number ?? '-',
                    'resolver' => $report->resolver?->full_name,
                    'resolved_at' => $report->resolved_at?->toISOString(),
                    'created_at' => $report->created_at?->toISOString(),
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => __('Laporan tidak ditemukan')], 404);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal memuat laporan')], 500);
        }
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_column(ReportStatus::cases(), 'value'))],
        ]);

        try {
            $report = Report::findOrFail($id);
            $report->status = ReportStatus::from($data['status']);

            if ($data['status'] === ReportStatus::RESOLVED->value) {
                $report->resolved_at = now();
                $report->resolved_by = $request->user()->id;
            } else {
                $report->resolved_at = null;
                $report->resolved_by = null;
            }

            $report->save();

            return response()->json([
                'status' => 'success',
                'message' => __('Status laporan diperbarui'),
                'data' => [
                    'id' => $report->id,
                    'status' => $report->status->value,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => __('Laporan tidak ditemukan')], 404);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal memperbarui laporan')], 500);
        }
    }

    public function stats(): JsonResponse
    {
        try {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'open' => Report::where('status', ReportStatus::OPEN)->count(),
                    'in_progress' => Report::where('status', ReportStatus::IN_PROGRESS)->count(),
                    'resolved' => Report::where('status', ReportStatus::RESOLVED)->count(),
                    'rejected' => Report::where('status', ReportStatus::REJECTED)->count(),
                    'total' => Report::count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal memuat statistik')], 500);
        }
    }
}
