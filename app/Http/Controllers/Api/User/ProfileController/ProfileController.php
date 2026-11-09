<?php

namespace App\Http\Controllers\Api\User\ProfileController;
use App\Http\Controllers\Api\User\SecurityController\SecurityController;

use App\Http\Controllers\Controller;
use App\Models\User\User;
use App\Models\IdentityDocument\IdentityDocument;
use App\Models\WhatsappOtp\WhatsappOtp;
use App\Services\FaceService\FaceService;
use App\Services\StorageService\StorageService;
use App\Support\PasswordPolicy\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
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
            unset(
                $data['ktp_number'], $data['sim_number'], $data['npwp_number'],
                $data['passport_number'], $data['ktp_photo'], $data['ktp_photo_url'],
            );
            $data['roles'] = $user->roles()->pluck('name')->toArray();
            $data['identity_documents'] = $this->identityDocumentsPayload($user);

            return response()->json([
                'status' => 'success',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil profil pengguna'),
                'error' => null,
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
                'document_number' => 'nullable|string|max:50',
            ];

            if ($request->has('password') && filled($request->password)) {
                $rules['password'] = PasswordPolicy::rules();
            }

            $validatedData = $request->validate($rules);

            // Identity numbers belong to the document model, not to users.
            // Accept the old field names from existing mobile builds, but store
            // them exactly once in identity_documents.
            $numberField = 'document_number';
            $documentType = $validatedData['identity_type'] ?? $user->identity_type ?? 'ktp';
            if (array_key_exists($numberField, $validatedData) && filled($validatedData[$numberField])) {
                    $document = $user->identityDocuments()->firstOrNew(['document_type' => $documentType]);
                    $document->document_number = $validatedData[$numberField];
                    $document->save();
            }
            unset($validatedData[$numberField]);

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
                'error' => null,
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
                'error' => null,
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
                'new_password' => PasswordPolicy::confirmedRules(),
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

            // "Logout dari perangkat lain" -- checkbox yang sama dengan punya
            // HandlesPasswordSecurity::updatePassword() di panel Filament.
            //
            // Sebelumnya field ini dikirim aplikasi tapi TIDAK dibaca di sini,
            // jadi checkbox-nya terlihat berfungsi padahal tidak pernah
            // melakukan apa pun. Kini benar-benar membuang token Sanctum dan
            // baris session milik user ini, kecuali yang sedang dipakai
            // request ini -- kalau tidak, pemanggil ikut logout di detik
            // yang sama dengan mengetik kata sandi barunya.
            $loggedOut = $request->boolean('logout_others')
                ? $user->revokeOtherSessions()
                : 0;

            return response()->json([
                'status' => 'success',
                'message' => __('Kata sandi berhasil diubah'),
                'data' => [
                    'logged_out_other_devices' => $loggedOut,
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
                'message' => __('Gagal mengubah kata sandi'),
                'error' => null,
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
                    'Favorite_count' => $user->Favorites()->count(),
                    'unread_notifications' => $user->unreadNotifications()->count(),
                    'total_spent' => $user->orders()->sum('total_price'),
                ],
                'upcoming_events' => $user->orders()
                    ->where('booking_date', '>=', now())
                    ->whereNotIn('status', ['cancelled', 'completed'])
                    ->orderBy('booking_date')
                    ->limit(5)
                    ->get(['*']),
                'recent_orders' => $user->orders()
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
                'error' => null,
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
                'error' => null,
            ], 500);
        }
    }

    /**
     * Update one identity document number.
     */
    public function updateDocument(Request $request, ?string $documentType = null)
    {
        try {
            /** @var User $user */
            $user = Auth::user();
            $documentType = strtolower((string) ($documentType ?: $request->input('document_type', $user->identity_type ?: 'ktp')));
            $fields = [
                'ktp' => ['number' => 'ktp_number', 'message' => 'Nomor KTP', 'rules' => ['digits:16']],
                'sim' => ['number' => 'sim_number', 'message' => 'Nomor SIM', 'rules' => ['string', 'min:6', 'max:50']],
                'npwp' => ['number' => 'npwp_number', 'message' => 'Nomor NPWP', 'rules' => ['string', 'min:15', 'max:20']],
                'passport' => ['number' => 'passport_number', 'message' => 'Nomor Passport', 'rules' => ['string', 'min:6', 'max:20', 'regex:/^[A-Za-z0-9]+$/']],
            ];
            if (! isset($fields[$documentType])) {
                throw ValidationException::withMessages(['document_type' => __('Jenis dokumen tidak didukung.')]);
            }
            $field = $fields[$documentType];
            $number = $request->input('document_number', $request->input($field['number'], $request->input('ktp_number')));
            $request->merge([$field['number'] => $number]);
            $document = $user->identityDocuments()->firstOrNew(['document_type' => $documentType]);

            $request->validate([
                'document_type' => ['nullable', Rule::in(array_keys($fields))],
                $field['number'] => array_merge(['required'], $field['rules'], [Rule::unique('identity_documents', 'document_number')->ignore($document->id)]),
            ]);

            $user->update(['identity_type' => $documentType]);
            $document->document_number = $number;
            $document->save();

            return response()->json([
                'status' => 'success',
                'message' => __($field['message'].' berhasil diperbarui'),
                'data' => [
                    'document_type' => $documentType,
                    'identity_document' => $document->fresh()->toArray(),
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
                'message' => __('Gagal memperbarui nomor dokumen'),
                'error' => null,
            ], 500);
        }
    }

    /**
     * Upload one identity document photo or video.
     */
    public function uploadDocument(Request $request, FaceService $faceService, ?string $documentType = null)
    {
        try {
            /** @var User $user */
            $user = Auth::user();
            $documentType = strtolower((string) ($documentType ?: $request->input('document_type', $user->identity_type ?: 'ktp')));
            if (! in_array($documentType, ['ktp', 'sim', 'npwp', 'passport'], true)) {
                throw ValidationException::withMessages(['document_type' => __('Jenis dokumen tidak didukung.')]);
            }

            $file = $request->file('document_photo')
                ?: $request->file($documentType.'_photo')
                ?: $request->file('ktp_photo');

            $request->validate([
                'document_type' => 'nullable|in:ktp,sim,npwp,passport',
            ]);
            if (! $file) {
                throw ValidationException::withMessages(['document_photo' => __('Foto dokumen wajib diunggah.')]);
            }
            Validator::make(['document_photo' => $file], [
                'document_photo' => 'required|file|mimes:jpeg,png,jpg,mp4,mov,m4v,webm,3gp|max:51200',
            ])->validate();

            $document = $user->identityDocuments()->firstOrNew(['document_type' => $documentType]);
            if ($document->photo_path) {
                StorageService::delete($document->photo_path);
            }

            /* Kirim identity_type sebagai doc_type hint. Tanpa ini AI Core hanya
             * auto-detect dari OCR, padahal client sudah tahu dokumen apa yang
             * sedang dipindai — akurat untuk KTP, dan jauh lebih andal untuk
             * SIM/NPWP/Pasport yang layout-nya mirip satu sama lain. */
            $docType = $documentType;

            [$path, $ai] = $this->storeKycUpload(
                $file,
                'identity-documents/'.$documentType,
                $documentType.'_'.$user->id.'_'.time(),
                $faceService,
                    fn (string $realPath) => $faceService->verifyKtp($realPath, $docType)
            );

            $docUpdate = $this->documentVerificationUpdate($user, $ai, $document);

            $document->fill([
                'user_id' => $user->id,
                'photo_path' => $path,
                'ai_document_type' => $ai['document_type'] ?? $documentType,
                'ai_document_number' => $ai['document_number'] ?? null,
                'ai_reason' => $ai['reason'] ?? null,
                'ai_reason_code' => $ai['reason_code'] ?? null,
                'ai_blocking_issue' => $ai['blocking_issue'] ?? null,
                'ai_score' => $ai['score'] ?? null,
                'ai_verified' => (bool) ($ai['verified'] ?? false),
                'ai_number_matches_profile' => $docUpdate['doc_ai_number_matches_profile'] ?? null,
                'ai_verified_at' => ($ai['verified'] ?? false) ? now() : null,
            ])->save();

            // Identity data lives in identity_documents only. Do not mirror it
            // into the legacy ktp_* columns.
            $user->forceFill(['identity_type' => $documentType])->save();

            $ktpVerified = ($ai['success'] ?? false) && ($ai['verified'] ?? false);

            return response()->json([
                'status' => 'success',
                'message' => $ktpVerified
                    ? __('Foto dokumen tervalidasi komputer visi')
                    : __('Foto dokumen berhasil diunggah'),
                'data' => [
                    'document_type' => $documentType,
                    'identity_documents' => $this->identityDocumentsPayload($user->fresh()),
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
                'message' => __('Gagal mengunggah foto dokumen'),
                'error' => null,
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
            $referenceDocument = $user->identityDocuments()
                ->where('document_type', 'ktp')
                ->whereNotNull('photo_path')
                ->first();
            if ($referenceDocument?->photo_path) {
                $absolute = StorageService::path($referenceDocument->photo_path);
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
                'error' => null,
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

            $referenceDocument = $user->identityDocuments()
                ->where('document_type', 'ktp')
                ->whereNotNull('photo_path')
                ->first();
            if (! $referenceDocument) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Unggah foto KTP terlebih dahulu untuk verifikasi wajah'),
                ], 422);
            }

            if ($user->face_scan_photo) {
                StorageService::delete($user->face_scan_photo);
            }

            // Computer Vision: bandingkan face scan dengan KTP milik user
            $reference = StorageService::path($referenceDocument->photo_path);

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
                'error' => null,
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
                'identity_document' => 10,
                'selfie_photo' => 10,
            ];
            $hasIdentityDocument = $user->identityDocuments()
                ->where(function ($query): void {
                    $query->whereNotNull('document_number')->orWhereNotNull('photo_path');
                })->exists();

            $score = 0;
            foreach ($profileItems as $field => $weight) {
                if ($field === 'email_verified_at') {
                    if ($user->email_verified_at) {
                        $score += $weight;
                    }
                } elseif ($field === 'identity_document' ? $hasIdentityDocument : ! empty($user->$field)) {
                    $score += $weight;
                }
            }

            if ($hasIdentityDocument) {
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
                        // UI compatibility key; the value is backed by the
                        // identity_documents relation, not users.ktp_number.
                        'document_number' => $hasIdentityDocument,
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
                'error' => null,
            ], 500);
        }
    }

    /**
     * Get user's Favorite catalogs
     */
    public function getFavorite(Request $request)
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

            $query = $user->Favorites()->with(['package.category', 'package.reviews']);

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

            $FavoriteItems = $query->paginate($request->get('per_page', 10));

            return response()->json([
                'status' => 'success',
                'data' => $FavoriteItems->items(),
                'pagination' => [
                    'current_page' => $FavoriteItems->currentPage(),
                    'last_page' => $FavoriteItems->lastPage(),
                    'per_page' => $FavoriteItems->perPage(),
                    'total' => $FavoriteItems->total(),
                    'has_more_pages' => $FavoriteItems->hasMorePages(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil Favorite'),
                'error' => null,
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
     * Susun kolom hasil verifikasi dokumen identitas (AI Core) untuk disimpan.
     *
     * kyc_status direset agar admin melakukan review ulang, persis seperti
     * faceVerificationUpdate(). Sebelumnya upload ulang KTP TIDAK mereset apa pun,
     * sehingga user yang KYC-nya sudah di-approve admin bisa mengganti dokumen
     * dan tetap berstatus verified.
     *
     * @param  array<string, mixed>  $ai
     * @return array<string, mixed>
     */
    private function documentVerificationUpdate(User $user, array $ai, ?IdentityDocument $document = null): array
    {
        // Layanan gagal (offline / timeout): jangan sentuh data yang ada.
        // Menyimpan null di sini akan menghapus bukti hasil scan sebelumnya.
        if (! ($ai['success'] ?? false)) {
            return [];
        }

        $verified = (bool) ($ai['verified'] ?? false);

        return [
            'doc_ai_document_type' => $ai['document_type'] ?? null,
            'doc_ai_document_number' => $ai['document_number'] ?? null,
            'doc_ai_reason' => $ai['reason'] ?? null,
            'doc_ai_reason_code' => $ai['reason_code'] ?? null,
            'doc_ai_blocking_issue' => $ai['blocking_issue'] ?? null,
            'doc_ai_score' => $ai['score'] ?? null,
            'doc_ai_verified_at' => $verified ? now() : null,
            'doc_ai_number_matches_profile' => $this->documentNumberMatchesProfile($user, $ai, $document),
            'kyc_status' => null,
        ];
    }

    /**
     * Bandingkan nomor identitas hasil OCR dengan nomor yang tersimpan di profil.
     *
     *AI Core membaca nomor dari dokumen yang difoto; profil menyimpan nomor yang
     * diketik user. Keduanya bisa berbeda karena salah ketik atau dokumen lain,
     * jadi ketidakcocokan perlu tercatat, bukan dibuang.
     *
     * @param  array<string, mixed>  $ai
     */
    private function documentNumberMatchesProfile(User $user, array $ai, ?IdentityDocument $document = null): ?bool
    {
        $found = trim((string) ($ai['document_number'] ?? ''));
        if ($found === '') {
            return null; // Tidak ada nomor terbaca: tidak bisa disimpulkan apa pun.
        }

        // Read legacy columns only as a migration fallback for existing users;
        // all new writes go to identity_documents.
        $profile = $document?->document_number ?? match ($ai['document_type'] ?? null) {
            'ktp' => $user->ktp_number,
            'passport' => $user->passport_number,
            'sim' => $user->sim_number,
            'npwp' => $user->npwp_number,
            default => null,
        };

        $profile = trim((string) $profile);
        if ($profile === '') {
            return null; // Profil belum punya nomor untuk tipe ini.
        }

        /* Bandingkan hanya digit, TAPI hanya untuk dokumen bernomor numerik.
         * Nomor paspor alphanumeric ("C1234567"): aturan preg_replace('\D')
         * akan membuang hurufnya, sehingga "C1234567" dan "1234567" terbaca sama —
         * persis kebalikan dari yang kita inginkan. */
        if (($ai['document_type'] ?? null) !== 'passport') {
            $profile = preg_replace('/\D+/', '', $profile);
            $found = preg_replace('/\D+/', '', $found);
        }

        return strtoupper($profile) === strtoupper($found);
    }

    /**
     * Return a stable document shape for all supported identity types.
     * The old ktp_* response fields remain only as compatibility aliases.
     *
     * @return array<string, array<string, mixed>>
     */
    private function identityDocumentsPayload(User $user): array
    {
        $documents = $user->identityDocuments()->get()->keyBy('document_type');
        return collect(['ktp', 'sim', 'npwp', 'passport'])->mapWithKeys(function (string $type) use ($documents): array {
            $document = $documents->get($type);

            return [$type => [
                'document_type' => $type,
                'document_number' => $document?->document_number,
                'photo' => $document?->photo_path,
                'photo_url' => $document?->photo_url,
                'ai_document_type' => $document?->ai_document_type,
                'ai_document_number' => $document?->ai_document_number,
                'ai_reason' => $document?->ai_reason,
                'ai_reason_code' => $document?->ai_reason_code,
                'ai_blocking_issue' => $document?->ai_blocking_issue,
                'ai_verified' => $document?->ai_verified,
                'ai_score' => $document?->ai_score,
                'ai_number_matches_profile' => $document?->ai_number_matches_profile,
                'ai_verified_at' => $document?->ai_verified_at,
            ]];
        })->all();
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
