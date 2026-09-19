<?php

namespace App\Services\FaceService;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FaceService
{
    protected string $baseUrl;

    protected int $timeoutSeconds;

    /** Ekstensi video yang didukung AI Core (frame diekstrak server-side). */
    protected const VIDEO_EXTENSIONS = ['mp4', 'mov', 'm4v', 'webm', '3gp', 'avi', 'mkv'];

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.ai_core_url', 'http://127.0.0.1:5000'), '/');
        $this->timeoutSeconds = (int) config('services.ai_core_timeout', 15) + 30;
    }

    /**
     * True bila berkas adalah video (ekstensi video yang didukung).
     */
    public function isVideoFile(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($ext, self::VIDEO_EXTENSIONS, true);
    }

    /**
     * Simpan frame hasil ekstraksi video (base64 JPEG) sebagai berkas gambar.
     * Mengembalikan path relatif disk 'public', atau null bila gagal.
     */
    public function storeVideoFrame(?string $base64, string $directory, string $filename): ?string
    {
        if (empty($base64)) {
            return null;
        }

        $bytes = base64_decode($base64, true);
        if ($bytes === false || strlen($bytes) === 0) {
            return null;
        }

        $relative = trim($directory, '/').'/'.ltrim($filename, '/');
        Storage::disk('public')->put($relative, $bytes);

        return $relative;
    }

    /**
     * Verifikasi wajah (selfie vs referensi KTP) via AI Core.
     *
     * @return array<string, mixed>
     */
    public function verifyFace(string $selfiePath, ?string $referencePath = null): array
    {
        try {
            if (! file_exists($selfiePath) || @filesize($selfiePath) === 0) {
                return $this->errorResponse(__('Foto selfie tidak valid.'));
            }

            $request = Http::timeout($this->timeoutSeconds)
                ->retry(1, 300, throw: false)
                ->attach('selfie', file_get_contents($selfiePath), basename($selfiePath));

            if ($referencePath !== null && file_exists($referencePath) && @filesize($referencePath) > 0) {
                $request = $request->attach('reference', file_get_contents($referencePath), basename($referencePath));
            }

            /** @var Response $response */
            $response = $request->post("{$this->baseUrl}/api/face/verify");

            if ($response->successful()) {
                $data = $response->json();
                if (! is_array($data)) {
                    return $this->errorResponse(__('Format respons AI tidak valid.'));
                }

                return [
                    'success' => (bool) ($data['success'] ?? false),
                    'verified' => (bool) ($data['verified'] ?? false),
                    'reason' => (string) ($data['reason'] ?? 'UNKNOWN'),
                    'similarity' => isset($data['similarity']) ? (float) $data['similarity'] : null,
                    'deep_similarity' => isset($data['deep_similarity']) ? (float) $data['deep_similarity'] : null,
                    'orb_ratio' => isset($data['orb_ratio']) ? (float) $data['orb_ratio'] : null,
                    'threshold' => (float) ($data['threshold'] ?? 0),
                    'face_detected_selfie' => (bool) ($data['face_detected_selfie'] ?? false),
                    'faces_selfie' => (int) ($data['faces_selfie'] ?? 0),
                    'face_detected_reference' => $data['face_detected_reference'] ?? null,
                    'blur_score_selfie' => isset($data['blur_score_selfie']) ? (float) $data['blur_score_selfie'] : null,
                    'eyes_open_selfie' => $data['eyes_open_selfie'] ?? null,
                    'liveness_checks' => $data['liveness_checks'] ?? null,
                    'frame_base64_selfie' => $data['frame_base64_selfie'] ?? $data['frame_base64'] ?? null,
                ];
            }

            Log::error('AI Core face verify error: '.$response->body());

            return $this->errorResponse(__('Layanan AI Scanner sedang sibuk. Coba lagi nanti.'));
        } catch (\Exception $e) {
            Log::error('AI Core face verify connection error: '.$e->getMessage());

            return $this->errorResponse(__('Layanan AI Scanner sedang offline. Silakan coba beberapa saat lagi.'));
        }
    }

    /**
     * Validasi bukti pembayaran via AI Core (OCR + Computer Vision).
     *
     * @param  float|int|string|null  $expectedAmount  Nominal yang diharapkan (opsional)
     * @return array<string, mixed>
     */
    public function verifyProof(string $imagePath, float|int|string|null $expectedAmount = null): array
    {
        try {
            if (! file_exists($imagePath) || @filesize($imagePath) === 0) {
                return $this->errorResponse(__('Bukti pembayaran tidak valid.'));
            }

            /** @var Response $response */
            $response = Http::timeout($this->timeoutSeconds)
                ->retry(1, 300, throw: false)
                ->attach('image', file_get_contents($imagePath), basename($imagePath))
                ->post("{$this->baseUrl}/api/proof/verify", $expectedAmount !== null ? ['expected_amount' => (string) $expectedAmount] : []);

            if ($response->successful()) {
                $data = $response->json();
                if (! is_array($data)) {
                    return $this->errorResponse(__('Format respons AI tidak valid.'));
                }

                return [
                    'success' => (bool) ($data['success'] ?? false),
                    'verified' => (bool) ($data['verified'] ?? false),
                    'reason' => (string) ($data['reason'] ?? 'UNKNOWN'),
                    'amounts' => $data['amounts'] ?? [],
                    'matched_amount' => isset($data['matched_amount']) ? (float) $data['matched_amount'] : null,
                    'expected_amount' => isset($data['expected_amount']) ? (float) $data['expected_amount'] : null,
                    'blur_score' => isset($data['blur_score']) ? (float) $data['blur_score'] : null,
                    'frame_base64' => $data['frame_base64'] ?? null,
                ];
            }

            Log::error('AI Core proof verify error: '.$response->body());

            return $this->errorResponse(__('Gagal memvalidasi bukti pembayaran. Coba lagi nanti.'));
        } catch (\Exception $e) {
            Log::error('AI Core proof verify connection error: '.$e->getMessage());

            return $this->errorResponse(__('Layanan AI Scanner sedang offline. Silakan coba beberapa saat lagi.'));
        }
    }

    /**
     * Validasi dokumen KTP via AI Core (Computer Vision).
     *
     * @return array<string, mixed>
     */
    public function verifyKtp(string $imagePath): array
    {
        try {
            if (! file_exists($imagePath) || @filesize($imagePath) === 0) {
                return $this->errorResponse(__('Foto KTP tidak valid.'));
            }

            /** @var Response $response */
            $response = Http::timeout($this->timeoutSeconds)
                ->retry(1, 300, throw: false)
                ->attach(
                    'image',
                    file_get_contents($imagePath),
                    basename($imagePath)
                )->post("{$this->baseUrl}/api/ktp/verify");

            if ($response->successful()) {
                $data = $response->json();
                if (! is_array($data)) {
                    return $this->errorResponse(__('Format respons AI tidak valid.'));
                }

                return [
                    'success' => (bool) ($data['success'] ?? false),
                    'verified' => (bool) ($data['verified'] ?? false),
                    'score' => isset($data['score']) ? (float) $data['score'] : null,
                    'is_ktp_like' => (bool) ($data['is_ktp_like'] ?? false),
                    'face_detected' => (bool) ($data['face_detected'] ?? false),
                    'aspect_ratio' => isset($data['aspect_ratio']) ? (float) $data['aspect_ratio'] : null,
                    'reason' => (string) ($data['reason'] ?? 'UNKNOWN'),
                    'threshold' => (float) ($data['threshold'] ?? 0),
                    'frame_base64' => $data['frame_base64'] ?? null,
                ];
            }

            Log::error('AI Core KTP verify error: '.$response->body());

            return $this->errorResponse(__('Gagal memvalidasi KTP. Coba lagi nanti.'));
        } catch (\Exception $e) {
            Log::error('AI Core KTP verify connection error: '.$e->getMessage());

            return $this->errorResponse(__('Layanan AI Scanner sedang offline. Silakan coba beberapa saat lagi.'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function errorResponse(string $message): array
    {
        return [
            'success' => false,
            'error' => true,
            'verified' => false,
            'reason' => 'AI_UNAVAILABLE',
            'message' => $message,
        ];
    }
}
