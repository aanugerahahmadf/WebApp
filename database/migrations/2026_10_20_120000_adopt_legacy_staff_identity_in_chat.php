<?php

use App\Models\Inbox\Inbox;
use App\Models\Message\Message;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pindahkan referensi identitas staf LAMA di tabel chat ke identitas staf
 * yang sekarang tertaut.
 *
 * Kenapa perlu, dan kenapa bukan sekadar kosmetik:
 *
 *   Sebelum tabel `admins` ada, super admin adalah baris `users` biasa (mis.
 *   id 1), dan percakapan menyimpan id itu di `inboxes.user_ids` --
 *   `messages.user_id` pun memakai id yang sama. Setelah pemisahan, identitas
 *   chat staf adalah BARIS BARU di `users` yang tertaut lewat `admins.user_id`
 *   (dibuat oleh `admin:grant-storefront`), mis. id 69.
 *
 *   Kalau referensi lama dibiarkan, percakapan yang sudah ada menjadi TAK
 *   TERLIHAT oleh staf: `ChatStaff::adminUser()` dan seluruh layar inbox
 *   panel admin mencari `user_ids` yang memuat id staf SEKARANG, sementara
 *   baris itu masih menyimpan id lama. Gejalanya persis seperti yang pernah
 *   dilaporkan: "pesan dari tamu tidak masuk" -- tanpa satu pun error, karena
 *   dari sudut SQL semuanya wajar-wajar saja.
 *
 * Definisi "identitas staf lama" mengikuti migrasi
 * `2026_10_17_110000_purge_legacy_admin_roles_from_users`: baris `users` yang
 * emailnya (atau username-nya) sama dengan sebuah baris `admins`, TETAPI belum
 * ditautkan ke admin mana pun. Hanya baris seperti itu yang dipindahkan --
 * pemetaannya per admin, jadi akun shopper/tamu tidak pernah tersentuh.
 *
 * Yang dipindahkan:
 *   - `inboxes.user_ids`                          (daftar peserta, JSON)
 *   - `messages.user_id`                          (pengirim)
 *   - `messages.read_by`, `messages.notified`     (daftar id, JSON)
 *   - `messages.meta.starred_by`                  (daftar id, JSON)
 *   - `messages.meta.reactions[].user_id`         (daftar id, JSON)
 *
 * Idempoten: baris yang tidak memuat id lama tidak ditulis ulang, dan map-nya
 * kosong kalau tidak ada identitas lama yang tertinggal.
 */
return new class extends Migration
{
    public function up(): void
    {
        $map = $this->legacyToLinkedStaff();

        if ($map === []) {
            return;
        }

        $this->remapInboxes($map);
        $this->remapMessages($map);
    }

    public function down(): void
    {
        // Sengaja kosong: memindahkan referensi kembali ke akun lama berarti
        // mengembalikan bug "pesan tamu tidak terlihat oleh staf". Kalau benar-
        // benar perlu, pemulihannya manual (data lama tidak disimpan di sini).
    }

    /**
     * Peta `id users lama -> id users staf yang tertaut`.
     *
     * @return array<int, int>
     */
    private function legacyToLinkedStaff(): array
    {
        $map = [];

        $admins = DB::table('admins')
            ->whereNotNull('user_id')
            ->get(['email', 'username', 'user_id']);

        foreach ($admins as $admin) {
            $legacyIds = DB::table('users')
                ->where('id', '!=', $admin->user_id)
                ->where(function ($query) use ($admin): void {
                    $query->where('email', $admin->email)
                        ->orWhere('username', $admin->username);
                })
                ->pluck('id');

            foreach ($legacyIds as $legacyId) {
                $map[(int) $legacyId] = (int) $admin->user_id;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, int>  $map
     */
    private function remapInboxes(array $map): void
    {
        $table = (new Inbox)->getTable();

        DB::table($table)
            ->select(['id', 'user_ids'])
            ->orderBy('id')
            ->chunkById(200, function ($inboxes) use ($map, $table): void {
                foreach ($inboxes as $inbox) {
                    $ids = $this->decodeIntList($inbox->user_ids);
                    $remapped = $this->remapIntList($ids, $map);

                    if ($remapped !== $ids) {
                        DB::table($table)->where('id', $inbox->id)->update([
                            'user_ids' => json_encode($remapped),
                        ]);
                    }
                }
            });
    }

    /**
     * @param  array<int, int>  $map
     */
    private function remapMessages(array $map): void
    {
        $table = (new Message)->getTable();

        DB::table($table)
            ->select(['id', 'user_id', 'read_by', 'notified', 'meta'])
            ->orderBy('id')
            ->chunkById(200, function ($messages) use ($map, $table): void {
                foreach ($messages as $message) {
                    $update = [];

                    if (isset($map[(int) $message->user_id])) {
                        $update['user_id'] = $map[(int) $message->user_id];
                    }

                    foreach (['read_by', 'notified'] as $column) {
                        $ids = $this->decodeIntList($message->$column);
                        $remapped = $this->remapIntList($ids, $map);

                        if ($remapped !== $ids) {
                            $update[$column] = json_encode($remapped);
                        }
                    }

                    $meta = json_decode((string) $message->meta, true);

                    if (is_array($meta)) {
                        $remappedMeta = $this->remapMeta($meta, $map);

                        if ($remappedMeta !== $meta) {
                            $update['meta'] = json_encode($remappedMeta);
                        }
                    }

                    if ($update !== []) {
                        DB::table($table)->where('id', $message->id)->update($update);
                    }
                }
            });
    }

    /**
     * @return list<int>
     */
    private function decodeIntList(mixed $value): array
    {
        $decoded = json_decode((string) $value, true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_map('intval', $decoded));
    }

    /**
     * Urutan dipertahankan, dan hasil ganda dibuang -- supaya "id lama + id
     * baru" di satu daftar tidak menyisakan satu peserta kembar.
     *
     * @param  list<int>  $ids
     * @param  array<int, int>  $map
     * @return list<int>
     */
    private function remapIntList(array $ids, array $map): array
    {
        return array_values(array_unique(array_map(
            fn (int $id): int => $map[$id] ?? $id,
            $ids,
        )));
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<int, int>  $map
     * @return array<string, mixed>
     */
    private function remapMeta(array $meta, array $map): array
    {
        if (isset($meta['starred_by']) && is_array($meta['starred_by'])) {
            $meta['starred_by'] = array_values(array_unique(array_map(
                fn ($id): string => (string) ($map[(int) $id] ?? (int) $id),
                $meta['starred_by'],
            )));
        }

        if (isset($meta['reactions']) && is_array($meta['reactions'])) {
            foreach ($meta['reactions'] as $index => $reaction) {
                if (isset($reaction['user_id'], $map[(int) $reaction['user_id']])) {
                    $meta['reactions'][$index]['user_id'] = (string) $map[(int) $reaction['user_id']];
                }
            }
        }

        return $meta;
    }
};
