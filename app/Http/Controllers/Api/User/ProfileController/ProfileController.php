<?php

namespace App\Http\Controllers\Api\User\ProfileController;

use App\Http\Controllers\Controller;
use App\Models\User\User;
use App\Models\WhatsappOtp\WhatsappOtp;
use App\Services\FaceService\FaceService;
use App\Services\StorageService\StorageService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /**
     * Get user profile with comprehensive details
     */
    public function show()
    {
        try {
            /** @var User $user */
            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Pengguna tidak terautentikasi'),
                ], 401);
            }

            // Prepare user data
            $data = $user->toArray();
            $data['roles'] = $user->roles()->pluck('name')->toArray();

            return response()->json([
                'status' => 'success',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil profil pengguna'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update user profile with validation
     */
    public function update(Request $request)
    {
        try {
            /** @var User $user */
            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated',
                ], 401);
            }

            $rules = [
                'full_name' => 'sometimes|string|max:255',
                'first_name' => 'nullable|string|max:100',
                'mid_name' => 'nullable|string|max:100',
                'last_name' => 'nullable|string|max:100',
                'username' => [
                    'nullable',
                    'string',
                    'max:100',
                    Rule::unique('users')->ignore($user->id),
                ],
                'whatsapp' => 'nullable|string|max:20',
                'gender' => 'nullable|string|max:20',
                'religion' => 'nullable|string|max:50',
                'marital_status' => 'nullable|string|max:50',
                'mother_name' => 'nullable|string|max:255',
                'occupation' => 'nullable|string|max:100',
                'income_range' => 'nullable|string|max:50',
                'source_of_funds' => 'nullable|string|max:100',
                'birth_place' => 'nullable|string|max:255',
                'birth_date' => 'nullable|date',
                'country' => 'nullable|string|max:255',
                'province_id' => 'nullable|exists:indonesia_provinces,id',
                'city_id' => 'nullable|exists:indonesia_cities,id',
                'district_id' => 'nullable|exists:indonesia_districts,id',
                'village_id' => 'nullable|exists:indonesia_villages,id',
                'province_name' => 'nullable|string|max:255',
                'city_name' => 'nullable|string|max:255',
                'district_name' => 'nullable|string|max:255',
                'village_name' => 'nullable|string|max:255',
                'postal_code' => 'nullable|string|max:10',
                'address' => 'nullable|string|max:500',
                'wedding_date' => 'nullable|date',
                'budget' => 'nullable|numeric|min:0',
                'theme_preference' => 'nullable|string|max:100',
                'color_preference' => 'nullable|string|max:100',
                'event_concept' => 'nullable|string|max:255',
                'dream_venue' => 'nullable|string|max:255',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'email' => [
                    'nullable',
                    'email',
                    'max:255',
                    Rule::unique('users')->ignore($user->id),
                ],
                'identity_type' => 'nullable|string|in:ktp,passport,sim,npwp|max:20',
                'ktp_number' => [
                    'nullable', 'string', 'max:20',
                    Rule::unique('users')->ignore($user->id),
                ],
                'passport_number' => [
                    'nullable', 'string', 'max:20',
                    Rule::unique('users')->ignore($user->id),
                ],
                'sim_number' => [
                    'nullable', 'string', 'max:20',
                    Rule::unique('users')->ignore($user->id),
                ],
                'npwp_number' => [
                    'nullable', 'string', 'max:20',
                    Rule::unique('users')->ignore($user->id),
                ],
            ];

            if ($request->has('password') && filled($request->password)) {
                $rules['password'] = 'string|min:12';
            }

            $validatedData = $request->validate($rules);

            // Enforce WhatsApp OTP verification before saving/updating whatsapp
            if (isset($validatedData['whatsapp']) && filled($validatedData['whatsapp'])) {
                $phone = $this->normalizeWhatsapp($validatedData['whatsapp']);
                $existingPhone = $user->whatsapp ? $this->normalizeWhatsapp($user->whatsapp) : null;

                // Only require OTP when the number actually changes
                if ($existingPhone !== null && $existingPhone === $phone) {
                    $validatedData['whatsapp_verified_at'] = $user->whatsapp_verified_at ?: now();
                } else {
                    $verified = WhatsappOtp::where('whatsapp', $phone)
                        ->whereNotNull('verified_at')
                        ->where('verified_at', '>', now()->subMinutes(10))
                        ->first();

                    if (! $verified) {
                        return response()->json([
                            'status' => 'error',
                            'message' => __('Nomor WhatsApp belum diverifikasi. Silakan verifikasi kode OTP terlebih dahulu.'),
                        ], 422);
                    }

                    $verified->delete();
                    $validatedData['whatsapp_verified_at'] = now();

                    SecurityController::recordSecurityEmail(
                        $user->id,
                        'account_update',
                        'securityEmailWhatsappChange',
                        'whatsapp_changed_at:'.now()->toIso8601String()
                    );
                }
            }

            $user->update($validatedData);

            return response()->json([
                'status' => 'success',
                'message' => __('Profil berhasil diperbarui'),
                'data' => $user->fresh(),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Validasi gagal'),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal memperbarui profil'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update user avatar
     */
    public function updateAvatar(Request $request)
    {
        try {
            /** @var User $user */
            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated',
                ], 401);
            }

            $request->validate([
                'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240', // 10MB max
            ]);

            // Delete old avatar if exists
            if ($user->avatar_url) {
                StorageService::delete($user->avatar_url);
            }

            // Store new avatar with unique name
            $fileName = 'avatar_'.$user->id.'_'.time().'.'.$request->file('avatar')->getClientOriginalExtension();
            $path = StorageService::uploadWithCustomName($request->file('avatar'), 'avatars', $fileName);

            $user->update(['avatar_url' => $path]);

            return response()->json([
                'status' => 'success',
                'message' => __('Avatar berhasil diperbarui'),
                'data' => [
                    'avatar_url' => $user->avatar_url,
                    'avatar_full_url' => $user->getFilamentAvatarUrl(),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Validasi gagal'),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal memperbarui avatar'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Change password with enhanced validation
     */
    public function changePassword(Request $request)
    {
        try {
            $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:12|confirmed',
            ]);

            /** @var User $user */
            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated',
                ], 401);
            }

            if (! Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Kata sandi saat ini salah'),
                ], 422);
            }

            $user->update([
                'password' => $request->new_password,
            ]);

            // Record security email (key-based, frontend translates)
            SecurityController::recordSecurityEmail(
                $user->id,
                'password_change',
                'securityEmailPasswordChange',
                'password_changed_at:'.now()->toIso8601String()
            );

            return response()->json([
                'status' => 'success',
                'message' => __('Kata sandi berhasil diubah'),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Validasi gagal'),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengubah kata sandi'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user dashboard statistics
     */
    public function dashboard()
    {
        try {
            /** @var User $user */
            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated',
                ], 401);
            }

            $data = [
                'user' => $user,
                'stats' => [
                    'total_orders' => $user->orders()->count(),
                    'completed_orders' => $user->orders()->where('status', 'completed')->count(),
                    'pending_orders' => $user->orders()->where('status', 'pending')->count(),
                    'confirmed_orders' => $user->orders()->where('status', 'confirmed')->count(),
                    'cancelled_orders' => $user->orders()->where('status', 'cancelled')->count(),
                    'pending_payments' => $user->orders()
                        ->where('payment_status', 'pending')
                        ->whereIn('status', ['pending', 'confirmed'])
                        ->count(),
                    'paid_orders' => $user->orders()->where('payment_status', 'paid')->count(),
                    'wishlist_count' => $user->wishlists()->count(),
                    'unread_notifications' => $user->unreadNotifications()->count(),
                    'total_spent' => $user->orders()->sum('total_price'),
                ],
                'upcoming_events' => $user->orders()->with(['package.vendor'])
                    ->where('booking_date', '>=', now())
                    ->whereNotIn('status', ['cancelled', 'completed'])
                    ->orderBy('booking_date')
                    ->limit(5)
                    ->get(['*']),
                'recent_orders' => $user->orders()->with(['package.vendor'])
                    ->latest()
                    ->limit(5)
                    ->get(['*']),
                'recent_activity' => $user->notifications()
                    ->latest()
                    ->limit(5)
                    ->get(['*']),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil data dashboard'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user's order history
     */
    public function getOrderHistory(Request $request)
    {
        try {
            /** @var User $user */
            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated',
                ], 401);
            }

            $query = $user->orders()->with(['package.media']);

            // Apply filters
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }

            if ($request->filled('from_date') && $request->filled('to_date')) {
                $query->whereBetween('booking_date', [$request->from_date, $request->to_date]);
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');

            $allowedSortFields = ['created_at', 'booking_date', 'total_price', 'status'];
            if (! in_array($sortBy, $allowedSortFields)) {
                $sortBy = 'created_at';
            }

            $allowedDirections = ['asc', 'desc'];
            if (! in_array(strtolower($sortDirection), $allowedDirections)) {
                $sortDirection = 'desc';
            }

            $query->orderBy($sortBy, $sortDirection);

            $orders = $query->paginate($request->get('per_page', 10));

            return response()->json([
                'status' => 'success',
                'data' => $orders->items(),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'has_more_pages' => $orders->hasMorePages(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil riwayat pesanan'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update KTP (Nomor KTP)
     */
    public function updateKtp(Request $request)
    {
        try {
            $request->validate([
                'ktp_number' => [
                    'required', 'string', 'size:16',
                    Rule::unique('users')->ignore($user->id),
                ],
            ]);

            /** @var User $user */
            $user = Auth::user();
            $user->update(['ktp_number' => $request->ktp_number]);

            return response()->json([
                'status' => 'success',
                'message' => __('Nomor KTP berhasil diperbarui'),
                'data' => $user->fresh(),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Validasi gagal'),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal memperbarui Nomor KTP'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload KTP photo (foto atau video; frame video diekstrak server-side)
     */
    public function uploadKtp(Request $request, FaceService $faceService)
    {
        try {
            /** @var User $user */
            $user = Auth::user();

            $request->validate([
                'ktp_photo' => 'required|file|mimes:jpeg,png,jpg,mp4,mov,m4v,webm,3gp|max:51200', // foto 5MB / video 50MB
            ]);

            if ($user->ktp_photo) {
                StorageService::delete($user->ktp_photo);
            }

            [$path, $ai] = $this->storeKycUpload(
                $request->file('ktp_photo'),
                'ktp-photos',
                'ktp_'.$user->id.'_'.time(),
                $faceService,
                fn (string $realPath) => $faceService->verifyKtp($realPath)
            );

            $user->update(['ktp_photo' => $path]);

            $ktpVerified = ($ai['success'] ?? false) && ($ai['verified'] ?? false);

            return response()->json([
                'status' => 'success',
                'message' => $ktpVerified
                    ? __('Foto KTP tervalidasi komputer visi')
                    : __('Foto KTP berhasil diunggah'),
                'data' => [
                    'ktp_photo' => $user->ktp_photo,
                    'ktp_photo_url' => $user->ktp_photo_url,
                    'ktp_ai_verified' => (bool) $ktpVerified,
                    'ktp_ai_score' => $ai['score'] ?? null,
                    'ktp_ai_reason' => $ai['reason'] ?? ($ai['message'] ?? 'AI_UNAVAILABLE'),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Validasi gagal'),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengunggah foto KTP'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload selfie photo for face verification
     */
    public function uploadSelfie(Request $request, FaceService $faceService)
    {
        try {
            /** @var User $user */
            $user = Auth::user();

            $request->validate([
                'selfie_photo' => 'required|file|mimes:jpeg,png,jpg,mp4,mov,m4v,webm,3gp|max:51200',
                'liveness_completed' => 'sometimes|boolean',
            ]);

            if ($user->selfie_photo) {
                StorageService::delete($user->selfie_photo);
            }

            // Computer Vision: bandingkan selfie dengan KTP milik user (jika ada)
            $reference = null;
            if ($user->ktp_photo) {
                $absolute = StorageService::path($user->ktp_photo);
                if (file_exists($absolute)) {
                    $reference = $absolute;
                }
            }

            [$path, $ai] = $this->storeKycUpload(
                $request->file('selfie_photo'),
                'selfies',
                'selfie_'.$user->id.'_'.time(),
                $faceService,
                fn (string $realPath) => $faceService->verifyFace($realPath, $reference)
            );

            $verified = ($ai['success'] ?? false) && ($ai['verified'] ?? false);

            $updateData = array_merge(
                ['selfie_photo' => $path],
                $this->faceVerificationUpdate($user, $ai, (bool) $request->boolean('liveness_completed'))
            );
            $user->forceFill($updateData)->save();

            return response()->json([
                'status' => 'success',
                'message' => $verified
                    ? __('Foto selfie terverifikasi komputer visi')
                    : __('Foto selfie berhasil diunggah'),
                'data' => [
                    'selfie_photo' => $user->selfie_photo,
                    'selfie_photo_url' => $user->selfie_photo_url,
                    'identity_verified_at' => $user->identity_verified_at,
                    'face_verified' => (bool) $verified,
                    'similarity' => $ai['similarity'] ?? null,
                    'face_reason' => $ai['reason'] ?? ($ai['message'] ?? 'AI_UNAVAILABLE'),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Validasi gagal'),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengunggah foto selfie'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload face scan photo for identity verification
     */
    public function uploadFaceScan(Request $request, FaceService $faceService)
    {
        try {
            /** @var User $user */
            $user = Auth::user();

            $request->validate([
                'face_scan_photo' => 'required|file|mimes:jpeg,png,jpg,mp4,mov,m4v,webm,3gp|max:51200',
                'liveness_completed' => 'sometimes|boolean',
            ]);

            if (! $user->ktp_photo) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Unggah foto KTP terlebih dahulu untuk verifikasi wajah'),
                ], 422);
            }

            if ($user->face_scan_photo) {
                StorageService::delete($user->face_scan_photo);
            }

            // Computer Vision: bandingkan face scan dengan KTP milik user
            $reference = StorageService::path($user->ktp_photo);

            [$path, $ai] = $this->storeKycUpload(
                $request->file('face_scan_photo'),
                'face-scans',
                'face_scan_'.$user->id.'_'.time(),
                $faceService,
                fn (string $realPath) => $faceService->verifyFace($realPath, $reference)
            );

            $verified = ($ai['success'] ?? false) && ($ai['verified'] ?? false);

            $updateData = array_merge(
                ['face_scan_photo' => $path],
                $this->faceVerificationUpdate($user, $ai, (bool) $request->boolean('liveness_completed'))
            );
            $user->forceFill($updateData)->save();

            return response()->json([
                'status' => 'success',
                'message' => $verified
                    ? __('Identitas berhasil diverifikasi komputer visi')
                    : __('Face scan disimpan, verifikasi belum berhasil. Coba lagi dengan pencahayaan yang baik.'),
                'data' => [
                    'face_scan_photo' => $user->face_scan_photo,
                    'face_scan_photo_url' => $user->face_scan_photo_url,
                    'identity_verified_at' => $user->identity_verified_at,
                    'face_verified' => (bool) $verified,
                    'similarity' => $ai['similarity'] ?? null,
                    'face_reason' => $ai['reason'] ?? ($ai['message'] ?? 'AI_UNAVAILABLE'),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Validasi gagal'),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengunggah face scan'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get profile completion percentage
     */
    public function completion()
    {
        try {
            /** @var User $user */
            $user = Auth::user();

            $profileItems = [
                'full_name' => 10,
                'username' => 5,
                'whatsapp' => 5,
                'avatar_url' => 5,
                'email_verified_at' => 5,
                'gender' => 5,
                'religion' => 5,
                'marital_status' => 5,
                'mother_name' => 5,
                'occupation' => 5,
                'income_range' => 5,
                'source_of_funds' => 5,
                'ktp_photo' => 10,
                'selfie_photo' => 10,
            ];

            $score = 0;
            foreach ($profileItems as $field => $weight) {
                if ($field === 'email_verified_at') {
                    if ($user->email_verified_at) {
                        $score += $weight;
                    }
                } elseif (! empty($user->$field)) {
                    $score += $weight;
                }
            }

            if (! empty($user->ktp_number) || ! empty($user->passport_number) || ! empty($user->sim_number) || ! empty($user->npwp_number)) {
                $score += 10;
            }

            if ($user->identity_verified_at) {
                $score += 5;
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'completion_percent' => min($score, 100),
                    'total_weight' => 100,
                    'items' => [
                        'full_name' => ! empty($user->full_name),
                        'username' => ! empty($user->username),
                        'whatsapp' => ! empty($user->whatsapp),
                        'avatar_url' => ! empty($user->avatar_url),
                        'email_verified' => ! empty($user->email_verified_at),
                        'gender' => ! empty($user->gender),
                        'religion' => ! empty($user->religion),
                        'marital_status' => ! empty($user->marital_status),
                        'mother_name' => ! empty($user->mother_name),
                        'occupation' => ! empty($user->occupation),
                        'income_range' => ! empty($user->income_range),
                        'source_of_funds' => ! empty($user->source_of_funds),
                        'ktp_number' => ! empty($user->ktp_number) || ! empty($user->passport_number) || ! empty($user->sim_number) || ! empty($user->npwp_number),
                        'ktp_photo' => ! empty($user->ktp_photo),
                        'selfie_photo' => ! empty($user->selfie_photo),
                        'identity_verified' => ! empty($user->identity_verified_at),
                    ],
                    'social_type' => $user->social_type,
                    'needs_completion' => ! $user->identity_type || ! $user->whatsapp || ! $user->birth_date,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil data kelengkapan profil'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user's wishlist products
     */
    public function getWishlist(Request $request)
    {
        try {
            /** @var User $user */
            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated',
                ], 401);
            }

            $query = $user->wishlists()->with(['package.vendor', 'package.category', 'package.reviews']);

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');

            $allowedSortFields = ['created_at', 'package.name', 'package.price'];
            if (! in_array($sortBy, $allowedSortFields)) {
                $sortBy = 'created_at';
            }

            $allowedDirections = ['asc', 'desc'];
            if (! in_array(strtolower($sortDirection), $allowedDirections)) {
                $sortDirection = 'desc';
            }

            $query->orderBy($sortBy, $sortDirection);

            $wishlistItems = $query->paginate($request->get('per_page', 10));

            return response()->json([
                'status' => 'success',
                'data' => $wishlistItems->items(),
                'pagination' => [
                    'current_page' => $wishlistItems->currentPage(),
                    'last_page' => $wishlistItems->lastPage(),
                    'per_page' => $wishlistItems->perPage(),
                    'total' => $wishlistItems->total(),
                    'has_more_pages' => $wishlistItems->hasMorePages(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil wishlist'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Susun kolom hasil verifikasi wajah (AI Core) untuk disimpan ke user.
     * kyc_status direset agar admin melakukan review ulang ketika foto baru diunggah.
     *
     * @param  array<string, mixed>  $ai
     * @return array<string, mixed>
     */
    private function faceVerificationUpdate(User $user, array $ai, bool $livenessCompleted): array
    {
        $verified = ($ai['success'] ?? false) && ($ai['verified'] ?? false);

        $update = [
            'liveness_completed' => $livenessCompleted,
        ];

        if ($ai['success'] ?? false) {
            $update['kyc_status'] = null;
            $update['face_similarity'] = $ai['similarity'] ?? null;
            $update['face_deep_similarity'] = $ai['deep_similarity'] ?? null;
            $update['face_reason'] = $ai['reason'] ?? null;
            $update['face_liveness'] = $ai['liveness_checks'] ?? null;
            $update['face_verified_at'] = $verified ? now() : null;
            $update['identity_verified_at'] = $verified ? now() : $user->identity_verified_at;
        }

        return $update;
    }

    /**
     * Simpan unggahan KYC (foto atau video). Video diproses AI Core server-side:
     * frame diekstrak lalu disimpan sebagai gambar kanonik. Mengembalikan [path, ai].
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function storeKycUpload(UploadedFile $file, string $directory, string $fileNameBase, FaceService $faceService, callable $verify): array
    {
        $realPath = $file->getRealPath();
        $extension = $file->getClientOriginalExtension();

        if ($faceService->isVideoFile($realPath)) {
            $ai = $verify($realPath);
            $frame = $faceService->storeVideoFrame($ai['frame_base64_selfie'] ?? $ai['frame_base64'] ?? null, $directory, $fileNameBase.'.jpg');

            return [
                $frame ?? StorageService::uploadWithCustomName($file, $directory, $fileNameBase.'.'.$extension),
                $ai,
            ];
        }

        $path = StorageService::uploadWithCustomName($file, $directory, $fileNameBase.'.'.$extension);
        $ai = $verify($realPath);

        return [$path, $ai];
    }

    /**
     * Normalize phone to international format (628xxx).
     */
    private function normalizeWhatsapp(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (empty($phone)) {
            return '';
        }
        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        }

        return $phone;
    }
}
