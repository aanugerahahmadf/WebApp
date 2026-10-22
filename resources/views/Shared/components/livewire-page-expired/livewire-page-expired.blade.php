{{--
    Pemulihan 419 untuk Livewire: ambil token CSRF baru, lalu ulangi request.

    Masalah yang ditutup skrip ini: begitu tab sudah terbuka, token CSRF di
    dalam halamannya bisa basi. Penyebabnya di aplikasi ini adalah login --
    OTP/2FA memanggil `session()->regenerateToken()`, sehingga setiap login
    membuat token baru, sementara tab lain masih memegang token yang lama.
    Request Livewire berikutnya (klik tombol, simpan form, unduh data) lalu
    kena 419, dan livewire.js menampilkan dialog bawaan:

        This page has expired. Would you like to refresh the page?

    Dialog itu muncul mendadak di tengah alur yang sedang berjalan,
    termasuk tepat setelah Pusat Unduhan Data selesai membuat berkas.

    Yang dilakukan skrip ini:

      1. `preventDefault()` -- dialog bawaan dibatalkan, sehingga user tidak
         pernah melihat "page has expired" untuk kasus yang sebenarnya bisa
         diperbaiki sendiri.
      2. Ambil token baru dari route `livewire.csrf-token`, timpa
         `<meta name="csrf-token">` dan atribut `data-csrf` pada tag skrip
         Livewire (livewire.js membacanya ulang di setiap request), lalu
         panggil `retry()` untuk request yang sama dengan token baru.
      3. Satu kali percobaan diam-diam per halaman. Kalau percobaan kedua
         tetap 419, berarti sesinya memang habis -- baru saat itu halaman
         dimuat ulang, karena memuat ulang lebih baik daripada membiarkan
         form menggantung selamanya dalam keadaan gagal.

    Dipasang lewat render hook `panels::scripts.after` (setelah
    `@filamentScripts`), jadi `window.Livewire` sudah ada. Event
    `livewire:init` tetap didengarkan sebagai jaring pengaman kalau urutan
    itu berubah suatu saat.

    Catatan: ini lapisan kedua. Lapisan pertama ada di
    `bootstrap/app.php` -- `livewire/*` dikecualikan dari verifikasi CSRF,
    sehingga request Livewire tidak lagi kena 419 karena token basi. Skrip
    ini menutup kasus sisanya (misalnya sesi benar-benar kedaluwarsa setelah
    aplikasi ditinggal lama tanpa aktivitas).
--}}
@once
    <script>
        (function () {
            const tokenUrl = @js(route('livewire.csrf-token'));

            let sudahTerpasang = false;

            const install = function () {
                if (sudahTerpasang || ! window.Livewire) {
                    return;
                }

                sudahTerpasang = true;

                let sudahDicoba = false;

                Livewire.hook('request', ({ fail }) => {
                    fail(async ({ status, preventDefault, retry }) => {
                        if (status !== 419) {
                            return;
                        }

                        preventDefault();

                        if (sudahDicoba) {
                            window.location.reload();

                            return;
                        }

                        sudahDicoba = true;

                        try {
                            const response = await fetch(tokenUrl, {
                                headers: { Accept: 'application/json' },
                                credentials: 'same-origin',
                            });

                            const { token } = await response.json();

                            document
                                .querySelector('meta[name="csrf-token"]')
                                ?.setAttribute('content', token);

                            document
                                .querySelectorAll('[data-csrf]')
                                .forEach((element) =>
                                    element.setAttribute('data-csrf', token),
                                );

                            retry();
                        } catch (error) {
                            window.location.reload();
                        }
                    });
                });
            };

            install();

            window.addEventListener('livewire:init', install);
        })();
    </script>
@endonce