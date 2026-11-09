<?php

namespace App\Services\GoogleAppsScript;

use RuntimeException;

/**
 * Apps Script menolak permintaan (atau responsnya bukan envelope JSON).
 *
 * `errorCode()` memetakan ke kode di Router.gs, jadi panel dan test bisa
 * membedakan masalah konfigurasi (`not_configured`, `bad_envelope`), masalah
 * tanda tangan (`bad_signature`, `stale_request`, `replayed_nonce`), dan masalah
 * data (`handler_error`).
 *
 * Pesan tidak pernah memuat secret atau isi payload.
 */
class AppsScriptException extends RuntimeException
{
    /** Kode yang dianggap masalah konfigurasi: operator harus bertindak, bukan retry. */
    public const CONFIGURATION_CODES = ['not_configured', 'bad_envelope', 'unknown_source', 'action_disabled'];

    public function __construct(
        string $message,
        protected string $errorCode = 'rejected',
        protected string $requestId = '',
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function isConfigurationProblem(): bool
    {
        return in_array($this->errorCode, self::CONFIGURATION_CODES, true);
    }

    /**
     * Tanda tangan / replay / skew waktu hampir selalu berarti jam perangkat
     * atau secret tidak sinkron -- percuma di-retry oleh queue.
     */
    public function isSignatureProblem(): bool
    {
        return in_array($this->errorCode, ['bad_signature', 'stale_request', 'replayed_nonce', 'missing_signature'], true);
    }
}
