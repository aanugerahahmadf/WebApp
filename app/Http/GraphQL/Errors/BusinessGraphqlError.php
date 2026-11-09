<?php

declare(strict_types=1);

namespace App\Http\GraphQL\Errors;

use GraphQL\Error\ClientAware;
use RuntimeException;

/**
 * Penanda bahwa sebuah pesan adalah penolakan bisnis yang boleh dibaca client.
 *
 * Resolver GraphQL di proyek ini melempar `\Exception('...')` polos untuk
 * penolakan yang memang ditulis untuk pengguna ("Invalid email or password",
 * "Insufficient stock", "Unauthorized"). graphql-php hanya mengizinkan pesan
 * keluar dari server kalau previous-nya mengimplementasi ClientAware, sehingga
 * tanpa penanda ini semuanya tampil sebagai "Internal server error" dan
 * mobile_app tidak bisa menampilkan alasan yang sebenarnya.
 *
 * Pesan yang dibungkus di sini adalah teks bisnis, bukan detail sistem: tidak
 * ada stack trace, SQL, path file, token, atau kredensial.
 */
final class BusinessGraphqlError extends RuntimeException implements ClientAware
{
    public function isClientSafe(): bool
    {
        return true;
    }
}
