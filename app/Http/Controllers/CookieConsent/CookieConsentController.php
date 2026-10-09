<?php

namespace App\Http\Controllers\CookieConsent;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller untuk menangani cookie consent.
 * Menyimpan preferensi: 'accepted' (semua), 'essential_only' (hanya wajib), 'rejected' (tolak semua non-essential).
 */
class CookieConsentController extends Controller
{
    /**
     * Terima semua cookie (analytics, marketing, dll).
     */
    public function accept(Request $request): JsonResponse
    {
        $response = response()->json([
            'success' => true,
            'message' => __('Semua cookie diterima.'),
            'consent' => 'accepted',
        ]);

        return $this->setConsentCookie($response, 'accepted');
    }

    /**
     * Terima hanya cookie essential (wajib untuk fungsi situs).
     */
    public function essentialOnly(Request $request): JsonResponse
    {
        $response = response()->json([
            'success' => true,
            'message' => __('Hanya cookie wajib yang diterima.'),
            'consent' => 'essential_only',
        ]);

        return $this->setConsentCookie($response, 'essential_only');
    }

    /**
     * Tolak cookie non-essential.
     */
    public function reject(Request $request): JsonResponse
    {
        $response = response()->json([
            'success' => true,
            'message' => __('Cookie non-essential ditolak.'),
            'consent' => 'rejected',
        ]);

        return $this->setConsentCookie($response, 'rejected');
    }

    /**
     * Ambil status consent saat ini.
     */
    public function status(Request $request): JsonResponse
    {
        $consent = $request->cookie('cookie_consent', 'not_set');

        return response()->json([
            'consent' => $consent,
            'can_track' => in_array($consent, ['accepted']),
            'can_analyze' => in_array($consent, ['accepted']),
            'can_market' => in_array($consent, ['accepted']),
        ]);
    }

    /**
     * Reset consent (untuk testing atau user mau ubah preferensi).
     */
    public function reset(Request $request): JsonResponse
    {
        $response = response()->json([
            'success' => true,
            'message' => __('Preferensi cookie direset.'),
        ]);

        return $response->cookie('cookie_consent', '', -1, '/', null, true, true, false, 'Lax');
    }

    /**
     * Set cookie consent dengan parameter yang konsisten.
     */
    protected function setConsentCookie(Response $response, string $value): Response
    {
        return $response->cookie(
            'cookie_consent',
            $value,
            365 * 24 * 60, // 1 tahun dalam menit
            '/',
            null, // domain
            true, // secure (HTTPS only)
            true, // httponly
            false, // raw
            'Lax' // same_site
        );
    }
}