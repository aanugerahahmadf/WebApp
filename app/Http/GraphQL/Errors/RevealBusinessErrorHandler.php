<?php

declare(strict_types=1);

namespace App\Http\GraphQL\Errors;

use Closure;
use Exception;
use GraphQL\Error\Error;
use Nuwave\Lighthouse\Execution\ErrorHandler;
use Throwable;

/**
 * Membuka pesan penolakan bisnis yang dilempar resolver GraphQL.
 *
 * Resolver lama di `app/Http/GraphQL/Resolvers/**` melempar `\Exception` polos
 * dengan pesan yang memang ditujukan ke pengguna. Tanpa handler ini graphql-php
 * menganggapnya error internal sehingga mobile_app hanya menerima
 * "Internal server error" untuk kasus seperti password salah, stok habis,
 * atau akun ditangguhkan.
 *
 * Batas aman: hanya `\Exception` persis (bukan subclass) yang boleh dibuka.
 * `QueryException`, `ModelNotFoundException`, `ViewException`, dan turunannya
 * tetap ditutupi karena pesannya memuat SQL, nama tabel, atau path file.
 *
 * Handler ini harus berada sebelum `ReportingErrorHandler` supaya penolakan
 * bisnis tidak ikut dilaporkan sebagai error ke log aplikasi.
 */
final class RevealBusinessErrorHandler implements ErrorHandler
{
    /**
     * extensions yang selalu diberikan ke client untuk penolakan bisnis,
     * supaya mobile bisa membedakan "gagal karena aturan" dari "server rusak"
     * tanpa membaca teks pesan.
     */
    private const EXTENSIONS = ['category' => 'business'];

    public function __invoke(?Error $error, Closure $next): ?array
    {
        if ($error !== null) {
            $previous = $error->getPrevious();

            if ($this->isBusinessRejection($previous)) {
                $error = new Error(
                    $previous->getMessage(),
                    $error->getNodes(),
                    $error->getSource(),
                    $error->getPositions(),
                    $error->getPath(),
                    new BusinessGraphqlError($previous->getMessage()),
                    $error->getExtensions() ?? self::EXTENSIONS,
                    $error->getUnaliasedPath(),
                );
            }
        }

        return $next($error);
    }

    private function isBusinessRejection(?Throwable $previous): bool
    {
        // `\Exception` persis: hanya resolver yang melempar kelas dasar, jadi
        // subclass bawaan framework tidak pernah lolos ke sini.
        return $previous !== null
            && get_class($previous) === Exception::class
            && trim($previous->getMessage()) !== '';
    }
}
