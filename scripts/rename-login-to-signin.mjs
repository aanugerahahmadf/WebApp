/**
 * Ganti teks "Login" -> "Sign In" pada string-string alur sign-in.
 *
 * Panel memakai "Sign In" (tombol "Sign In To Account", "Sign In" di
 * Filament), jadi notifikasi yang stubbornly berbunyi "Login Failed" bikin
 * tidak konsisten.
 *
 * Yang DILEWATI: string yang bukan alur sign-in -- "Login Method" di panel
 * admin, "Manajemen login, keamanan, dan perizinan.", teks privasi yang
 * menyebut "Google Login", dan seterusnya. Mengganti semuanya jadi
 * "Sign In" justru membuat kalimatnya aneh.
 *
 * Jalankan: node scripts/rename-login-to-signin.mjs
 */

import { readFileSync, writeFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');

/**
 * Kunci -> nilai baru.
 *
 * Kunci Bahasa Indonesia, karena file terjemahan itu berpasangan dengan
 * sumbernya.
 */
const replacements = {
    'Gagal Masuk': 'Sign In Failed',
    'Berhasil Masuk': 'Sign In Successful',
    'Berhasil Masuk!': 'Sign In Successfully!',
    'Gagal login dengan Google.': 'Failed to sign in with Google.',
    'Gagal login dengan Google. Tidak ada kode otorisasi.': 'Failed to sign in with Google. No authorization code.',
    'Data login tidak valid': 'Invalid sign in data',
    'Kembali ke Halaman Masuk': 'Return to Sign In Page',
    'Kembali ke Login': 'Return to Sign In',
    'Keluar dan Kembali ke Login': 'Exit and Return to Sign In',
    'Keluar dan kembali ke halaman login?': 'Log out and return to the sign in page?',
    'Back to Login': 'Back to Sign In',
    'I Agree & Login': 'I Agree & Sign In',
    'Masukkan akun Anda': 'Sign in to your account',
    'Dengan login, kamu menyetujui kebijakan penyelenggara.': 'By signing in, you agree to the store policies.',
    'Account Anda Sudah Terdaftar Silahkan Ke Halaman Login.': 'Your account is already registered. Please go to the sign in page.',

    // Key vendor Filament. Yang dibaca user adalah NILAINYA, jadi ikut
    // diganti -- "Login" sebagai judul halaman auth terasa aneh, karena
    // tombolnya sendiri sudah "Sign In".
    'filament-panels::pages/auth/login.title': 'Sign In',
    'filament-panels::pages/auth/login.heading': 'Sign In',
    'filament-panels::pages/auth/login.form.actions.authenticate.label': 'Sign In',
    'filament-panels::pages/auth/login.notifications.throttled.title': 'Too many sign in attempts',
    'filament-panels::pages/auth/password-reset/request-password-reset.actions.login.label': 'back to sign in',
    'filament-panels::pages/auth/register.actions.login.label': 'Sign In',
    'filament-panels::pages/auth/email-verification/prompt.actions.login.label': 'Sign In',
};

for (const file of ['lang/en.json', 'lang/en_US.json']) {
    const path = resolve(root, file);
    const original = readFileSync(path, 'utf8');
    const data = JSON.parse(original);

    let changed = 0;

    for (const [key, value] of Object.entries(replacements)) {
        if (Object.prototype.hasOwnProperty.call(data, key) && data[key] !== value) {
            data[key] = value;
            changed++;
        }
    }

    if (changed === 0) {
        console.log(`${file}: tidak ada yang berubah`);

        continue;
    }

    // Pertahankan format 4 spasi + newline di akhir file, seperti aslinya.
    writeFileSync(path, `${JSON.stringify(data, null, 4)}\n`, 'utf8');

    console.log(`${file}: ${changed} string diubah`);
}
