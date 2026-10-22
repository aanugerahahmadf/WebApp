# Katalog & Keranjang (Panel Welcome & User)

> **CATATAN ARSITEKTUR (2026-10-21).** Halaman ini ditulis saat masih ada DUA
> panel terpisah: `user` di `/user` dan `welcome` di `/welcome`. Sekarang
> panel `user` sudah **dilebur** ke panel `welcome` di root, jadi:
>
> - hanya ada dua panel: `welcome` (path `/`) dan `admin` (path `/admin`)
> - prefix nama route berubah dari `filament.user.*` ke `filament.welcome.*`
> - bookmark lama dilayani redirect: `/user/*` -> root, `/login` -> `/signin`,
>   `/register` -> `/signup`
> - bottom nav HP dan chrome ikut memakai panel `welcome`
>
> Ringkasan terbaru ada di `AGENTS.md` -> bagian "SATU PANEL: 'user' DILEBUR KE
> 'welcome'". Nama route dan URL yang disebut di bawah sudah disesuaikan;
> bagian yang men_**describe** dua panel terpisah masih perlu dibaca sebagai
> sejarah.

Berlaku untuk `PackageResource` dan `ProductResource` di panel Welcome
(storefront publik) dan panel User. Slug resource katalog saat ini memakai
nama panjang (`flowerdecorationspackagecatalog`, `flowerdecorationscatalog`);
jangan hardcode path di kode/test baru — pakai `Resource::getUrl()`.

## Matriks Aksi Halaman Detail

| Aksi | Form Modal | Guest | User Login |
|---|---|---|---|
| `share_item` | Ya (link bagi) | Buka modal | Buka modal |
| `report_item` | Tidak | Ke login | Kirim pesan laporan + ke Messages |
| `buy_now_detail` | Tidak | Ke login | Ke halaman checkout |
| `add_to_cart_detail` | Ya (qty) | **Form tetap muncul**, Submit = link ke login | Submit normal, cart tersimpan |
| `chat_admin` | Tidak | Chat sebagai guest (`GuestIdentity`) | Chat sebagai user |
| `wishlist_detail` | Tidak | Ke login | Tambah/hapus favorit |
| `write_review` | Ya (rating) | Form muncul, submit ke login | Simpan ulasan (sekali per item) |

## Alur Guest Add to Cart (Anti-419)

Desain ini menjawab error lama `419 Page Expired` saat guest menekan Submit:

1. Guest menekan **Add to Cart** → modal form qty **tetap muncul** (sama seperti user login).
2. Di dalam modal, untuk guest:
   - `modalSubmitAction` dinonaktifkan (`false`), jadi **tidak ada tombol
     Submit Livewire** dan tidak ada `POST /livewire/update`.
   - Sebagai gantinya tampil `extraModalFooterActions` berupa `StaticAction`
     link biasa (`<a href>`) ke halaman auth pertama panel user
     (`AuthenticateWelcome::LOGIN_ROUTE` = `/auth`: Sign In **dan** Google).
     Klik = `GET` biasa, sehingga error 419 **mustahil** terjadi.
   - `url.intended` disimpan ke session saat modal dibuka, supaya tombol
     kembali di halaman login bisa pulang ke detail yang diklik.
3. `action()` di server tetap punya guard `redirectGuestToLogin()` sebagai
   pengaman lapis dua (mis. sesi berubah saat modal terbuka). Redirect ini
   memakai `$livewire->redirect($url, navigate: false)` — full reload karena
   pindah antar panel (`welcome` → `user`); `navigate: true` (SPA) tidak andal
   untuk lintas panel. Pola yang sama dipakai halaman checkout.

Untuk user login, modal dan Submit berperilaku normal (Livewire penuh).

## Perilaku Quantity Cart yang Perlu Diketahui

`Cart::updateOrCreate()` memakai `DB::raw('quantity + N')` dengan default
kolom `quantity = 1`. Akibatnya penambahan **pertama** tercatat sebesar
`1 + N` (contoh: minta 2 → tersimpan 3). Ini perilaku kode saat ini, bukan
bagian dari desain anti-419; test `GuestAddToCartTest` mengunci perilaku
tersebut supaya perubahan di masa depan terdeteksi.

## Checkout, Wishlist, Ulasan

- **Checkout** (`CheckoutPackage` / `CheckoutProduct`): wizard tanggal acara,
  validasi stok, dan redirect guest ke login dengan `navigate: false`.
- **Wishlist**: toggle tambah/hapus per user; guest diarahkan ke login.
- **Ulasan**: satu user satu ulasan per item; tombol tulis ulasan tetap
  terlihat oleh guest (submit-nya yang mengarah ke login).
