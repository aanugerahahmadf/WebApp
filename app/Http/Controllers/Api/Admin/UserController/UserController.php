<?php

namespace App\Http\Controllers\Api\Admin\UserController;

use App\Http\Controllers\Controller;
use App\Models\User\User;
use App\Models\Vendor\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = User::query();

            if ($request->filled('search')) {
                $s = $request->search;
                $query->where(function ($q) use ($s): void {
                    $q->where('full_name', 'like', "%{$s}%")
                        ->orWhere('email', 'like', "%{$s}%")
                        ->orWhere('username', 'like', "%{$s}%");
                });
            }

            if ($request->filled('role')) {
                $query->role($request->role);
            }

            if ($request->filled('is_active')) {
                $query->where('active_status', $request->boolean('is_active'));
            }

            if ($request->filled('kyc_status')) {
                $kycStatus = $request->kyc_status;
                if ($kycStatus === 'pending') {
                    $query->whereNull('kyc_status')
                        ->where(function ($q): void {
                            $q->whereNotNull('ktp_photo')
                                ->orWhereNotNull('selfie_photo')
                                ->orWhereNotNull('face_scan_photo');
                        });
                } else {
                    $query->where('kyc_status', $kycStatus);
                }
            }

            $users = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));

            $data = collect($users->items())->map(fn ($u) => [
                // Akun & akses
                'id' => $u->id,
                'username' => $u->username,
                'email' => $u->email,
                'avatar_url' => $u->avatar_url,
                'roles' => $u->getRoleNames(),
                'active_status' => $u->active_status,
                'email_verified_at' => $u->email_verified_at?->toISOString(),
                'created_at' => $u->created_at?->toISOString(),
                'updated_at' => $u->updated_at?->toISOString(),
                'social_type' => $u->social_type,
                'social_id' => $u->social_id,
                // Data pribadi
                'full_name' => $u->full_name,
                'first_name' => $u->first_name,
                'mid_name' => $u->mid_name,
                'last_name' => $u->last_name,
                'mother_name' => $u->mother_name,
                'gender' => $u->gender,
                'religion' => $u->religion,
                'marital_status' => $u->marital_status,
                'occupation' => $u->occupation,
                'income_range' => $u->income_range,
                'source_of_funds' => $u->source_of_funds,
                'phone' => $u->phone,
                'whatsapp' => $u->whatsapp,
                'address' => $u->address,
                // Identitas & KYC
                'identity_type' => $u->identity_type,
                'ktp_number' => $u->ktp_number,
                'passport_number' => $u->passport_number,
                'sim_number' => $u->sim_number,
                'npwp_number' => $u->npwp_number,
                'ktp_photo_url' => $u->ktp_photo_url,
                'selfie_photo_url' => $u->selfie_photo_url,
                'face_scan_photo_url' => $u->face_scan_photo_url,
                'kyc_status' => $u->kyc_status,
                'kyc_notes' => $u->kyc_notes,
                'kyc_reviewer' => $u->kycReviewer?->full_name,
                'kyc_reviewed_at' => $u->kyc_reviewed_at?->toISOString(),
                'identity_verified_at' => $u->identity_verified_at?->toISOString(),
                'face_similarity' => $u->face_similarity,
                'liveness_completed' => $u->liveness_completed,
                'has_kyc_documents' => (bool) ($u->ktp_photo || $u->selfie_photo || $u->face_scan_photo),
                // Kelahiran & alamat
                'birth_place' => $u->birth_place,
                'birth_date' => $u->birth_date?->toISOString(),
                'country' => $u->country,
                'province_name' => $u->province_name,
                'city_name' => $u->city_name,
                'district_name' => $u->district_name,
                'village_name' => $u->village_name,
                'postal_code' => $u->postal_code,
                // Kunci aplikasi
                'app_lock_fingerprint_enabled' => $u->app_lock_fingerprint_enabled,
                'app_lock_face_enabled' => $u->app_lock_face_enabled,
                'app_lock_pin_enabled' => $u->app_lock_pin_enabled,
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengambil data pengguna'), 'error' => $e->getMessage()], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'avatar_url' => $user->avatar_url,
                    'whatsapp' => $user->whatsapp,
                    'phone' => $user->phone,
                    'gender' => $user->gender,
                    'religion' => $user->religion,
                    'marital_status' => $user->marital_status,
                    'address' => $user->address,
                    'active_status' => $user->active_status,
                    'email_verified_at' => $user->email_verified_at,
                    'roles' => $user->getRoleNames(),
                    'created_at' => $user->created_at?->toISOString(),
                    'updated_at' => $user->updated_at?->toISOString(),
                    // KYC
                    'identity_type' => $user->identity_type,
                    'ktp_number' => $user->ktp_number,
                    'passport_number' => $user->passport_number,
                    'sim_number' => $user->sim_number,
                    'npwp_number' => $user->npwp_number,
                    'birth_place' => $user->birth_place,
                    'birth_date' => $user->birth_date?->toISOString(),
                    'occupation' => $user->occupation,
                    'income_range' => $user->income_range,
                    'source_of_funds' => $user->source_of_funds,
                    'kyc_status' => $user->kyc_status,
                    'kyc_notes' => $user->kyc_notes,
                    'kyc_reviewed_by' => $user->kycReviewer?->full_name,
                    'kyc_reviewed_at' => $user->kyc_reviewed_at?->toISOString(),
                    'identity_verified_at' => $user->identity_verified_at?->toISOString(),
                    'face_similarity' => $user->face_similarity,
                    'liveness_completed' => $user->liveness_completed,
                    'ktp_photo_url' => $user->ktp_photo_url,
                    'selfie_photo_url' => $user->selfie_photo_url,
                    'face_scan_photo_url' => $user->face_scan_photo_url,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Pengguna tidak ditemukan')], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'full_name' => 'required|string|max:255',
                'username' => 'required|string|max:255|unique:users,username',
                'email' => 'required|email|max:255|unique:users,email',
                'password' => 'required|string|min:8',
                'whatsapp' => 'nullable|string|max:20',
                'roles' => 'nullable|array',
                'roles.*' => 'string|exists:roles,name',
                'active_status' => 'boolean',
            ]);

            $user = User::create([
                'full_name' => $validated['full_name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'whatsapp' => $validated['whatsapp'] ?? null,
                'active_status' => $validated['active_status'] ?? true,
            ]);

            if (! empty($validated['roles'])) {
                $user->syncRoles($validated['roles']);
            } else {
                $user->assignRole('customer');
            }

            return response()->json([
                'status' => 'success',
                'message' => __('Pengguna berhasil dibuat'),
                'data' => ['id' => $user->id],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal membuat pengguna'), 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            $validated = $request->validate([
                'full_name' => 'sometimes|string|max:255',
                'username' => ['sometimes', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
                'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
                'password' => 'sometimes|string|min:8',
                'whatsapp' => 'nullable|string|max:20',
                'roles' => 'nullable|array',
                'roles.*' => 'string|exists:roles,name',
                'active_status' => 'boolean',
            ]);

            $updateData = collect($validated)->except(['password', 'roles'])->toArray();
            if (! empty($validated['password'])) {
                $updateData['password'] = Hash::make($validated['password']);
            }
            $user->update($updateData);

            if (array_key_exists('roles', $validated)) {
                $user->syncRoles($validated['roles'] ?? []);
            }

            return response()->json(['status' => 'success', 'message' => __('Pengguna berhasil diperbarui')]);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal memperbarui pengguna'), 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);
            if ($user->hasRole('super_admin')) {
                return response()->json(['status' => 'error', 'message' => __('Tidak dapat menghapus Super Admin')], 403);
            }
            $user->delete();

            return response()->json(['status' => 'success', 'message' => __('Pengguna berhasil dihapus')]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal menghapus pengguna')], 500);
        }
    }

    public function toggleActive(int $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);
            if ($user->hasRole('super_admin')) {
                return response()->json(['status' => 'error', 'message' => __('Tidak dapat menonaktifkan Super Admin')], 403);
            }
            $user->update(['active_status' => ! $user->active_status]);

            return response()->json([
                'status' => 'success',
                'message' => $user->active_status ? __('Pengguna diaktifkan') : __('Pengguna dinonaktifkan'),
                'data' => ['active_status' => $user->active_status],
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengubah status pengguna')], 500);
        }
    }

    public function roles(): JsonResponse
    {
        try {
            $roles = Role::pluck('name');

            return response()->json(['status' => 'success', 'data' => $roles]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengambil roles')], 500);
        }
    }

    /**
     * Daftar vendor.
     */
    public function vendors(): JsonResponse
    {
        try {
            $vendors = Vendor::withCount(['packages', 'products'])->orderBy('store_name')->get();

            $data = $vendors->map(fn ($v) => [
                'id' => $v->id,
                'user_id' => $v->user_id,
                'store_name' => $v->store_name,
                'contact_person' => $v->contact_person,
                'no_telp' => $v->no_telp,
                'store_description' => $v->store_description,
                'logo' => $v->logo ? asset('storage/'.$v->logo) : null,
                'is_active' => $v->is_active,
                'is_partner' => $v->is_partner,
                'package_count' => (int) $v->packages_count,
                'product_count' => (int) $v->products_count,
                'created_at' => $v->created_at?->toISOString(),
                'updated_at' => $v->updated_at?->toISOString(),
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function approveKyc(int $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);
            if (! $user->ktp_photo && ! $user->selfie_photo && ! $user->face_scan_photo) {
                return response()->json(['status' => 'error', 'message' => __('Pengguna belum mengunggah dokumen KYC')], 422);
            }
            $user->forceFill([
                'kyc_status' => 'verified',
                'kyc_notes' => null,
                'kyc_reviewed_by' => auth()->id(),
                'kyc_reviewed_at' => now(),
            ])->save();

            return response()->json(['status' => 'success', 'message' => __('KYC disetujui')]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal menyetujui KYC'), 'error' => $e->getMessage()], 500);
        }
    }

    public function rejectKyc(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'kyc_notes' => 'required|string|max:1000',
            ]);

            $user = User::findOrFail($id);
            $user->forceFill([
                'kyc_status' => 'rejected',
                'kyc_notes' => $validated['kyc_notes'],
                'kyc_reviewed_by' => auth()->id(),
                'kyc_reviewed_at' => now(),
            ])->save();

            return response()->json(['status' => 'success', 'message' => __('KYC ditolak')]);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal menolak KYC'), 'error' => $e->getMessage()], 500);
        }
    }
}
