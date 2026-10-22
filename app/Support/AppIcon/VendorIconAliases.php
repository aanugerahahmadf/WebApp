<?php

namespace App\Support\AppIcon;

use Filament\Support\Facades\FilamentIcon;

/**
 * Penimpa ikon INTERNAL vendor Filament dengan aset SVG berwarna kita.
 *
 * Filament secara internal memakai ikon monokrom di banyak tempat:
 * tombol aksi bawaan (delete/edit/view/replicate...), tombol editor file
 * upload (crop/rotate/zoom), checkbox boolean, notifikasi, filter tabel, dan
 * alias `x::y` lain. Semua itu ditimpa lewat `FilamentIcon::register()`.
 *
 * TIDAK ADA PENGECUALIAN LAGI: semua alias -- termasuk chevron bawaan
 * (pagination, dropdown, breadcrumbs, navigator) -- memakai vektor dari
 * fonts.google.com/icons (Material Symbols) yang diwarnai template kita
 * (keputusan pengguna, 2026-10-21: "SEMUA ... HARUS PAKAI
 * fonts.google.com/icons").
 */
class VendorIconAliases
{
    /**
     * Alias ikon internal Filament -> gambar. Nilainya SAMA dengan konstanta
     * AppIcon (FOLDER + nama file).
     *
     * PENGECUALIAN: `forms::components.file-upload.editor.actions.*` TIDAK
     * ADA di sini, dan SENGAJA TIDAK boleh ditambahkan.
     *
     * Kenapa: Filament merender ikon editor file upload dengan
     * `svg(FilamentIcon::resolve(...))` -- helper BLADE-ICONS, bukan
     * `<x-filament::icon>`. `svg()` mencari nama ikon di dalam SET
     * ("default" = Heroicons), dan nama ikon di sana harus NAMA, bukan path.
     * Kalau aliasnya diisi path (`/images/icons/arrows_lr.svg`), hasilnya:
     *
     *     Svg by name "/images/icons/arrows_lr.svg" from set "default" not found.
     *
     * dan halaman yang memuat file upload jadi 500 -- sign up, complete
     * profile, katalog admin, dan apa pun yang punya upload avatar/gambar.
     *
     * Alias lain aman karena Filament merendernya lewat `<x-filament::icon>`,
     * yang memilih `<img>` kalau namanya mengandung "/".
     *
     * Kalau toolbar editor file upload harus berwarna juga, jalurnya bukan
     * alias: override kelas `FileUpload` Filament.
     *
     * @var array<string, string>
     */
    public const ICONS = [
        'actions::action-group' => self::I.'more.svg',
        'actions::create-action.grouped' => self::I.'dots.svg',
        'actions::delete-action' => self::I.'trash.svg',
        'actions::delete-action.grouped' => self::I.'dots.svg',
        'actions::delete-action.modal' => self::I.'trash.svg',
        'actions::detach-action' => self::I.'link.svg',
        'actions::detach-action.modal' => self::I.'link.svg',
        'actions::dissociate-action' => self::I.'link.svg',
        'actions::dissociate-action.modal' => self::I.'link.svg',
        'actions::edit-action' => self::I.'pencil.svg',
        'actions::edit-action.grouped' => self::I.'dots.svg',
        'actions::export-action.grouped' => self::I.'dots.svg',
        'actions::force-delete-action' => self::I.'trash.svg',
        'actions::force-delete-action.grouped' => self::I.'dots.svg',
        'actions::force-delete-action.modal' => self::I.'trash.svg',
        'actions::import-action.grouped' => self::I.'dots.svg',
        'actions::modal.confirmation' => self::I.'warning.svg',
        'actions::replicate-action' => self::I.'document.svg',
        'actions::replicate-action.grouped' => self::I.'dots.svg',
        'actions::restore-action' => self::I.'refresh.svg',
        'actions::restore-action.grouped' => self::I.'dots.svg',
        'actions::restore-action.modal' => self::I.'refresh.svg',
        'actions::view-action' => self::I.'eye.svg',
        'actions::view-action.grouped' => self::I.'dots.svg',
        'forms::components.builder.actions.clone' => self::I.'document.svg',
        'forms::components.builder.actions.collapse' => self::I.'arrow_up.svg',
        'forms::components.builder.actions.delete' => self::I.'trash.svg',
        'forms::components.builder.actions.expand' => self::I.'arrow_down.svg',
        'forms::components.builder.actions.move-down' => self::I.'arrow_down.svg',
        'forms::components.builder.actions.move-up' => self::I.'arrow_up.svg',
        'forms::components.builder.actions.reorder' => self::I.'arrows_up_down.svg',
        'forms::components.key-value.actions.delete' => self::I.'trash.svg',
        'forms::components.key-value.actions.reorder' => self::I.'arrows_up_down.svg',
        'forms::components.repeater.actions.clone' => self::I.'document.svg',
        'forms::components.repeater.actions.collapse' => self::I.'arrow_up.svg',
        'forms::components.repeater.actions.delete' => self::I.'trash.svg',
        'forms::components.repeater.actions.expand' => self::I.'arrow_down.svg',
        'forms::components.repeater.actions.move-down' => self::I.'arrow_down.svg',
        'forms::components.repeater.actions.move-up' => self::I.'arrow_up.svg',
        'forms::components.repeater.actions.reorder' => self::I.'arrows_up_down.svg',
        'forms::components.select.actions.create-option' => self::I.'plus.svg',
        'forms::components.select.actions.edit-option' => self::I.'pencil.svg',
        'forms::components.text-input.actions.hide-password' => self::I.'eye_slash.svg',
        'forms::components.text-input.actions.show-password' => self::I.'eye.svg',
        'forms::components.toggle-buttons.boolean.false' => self::I.'x_circle.svg',
        'forms::components.toggle-buttons.boolean.true' => self::I.'check_circle.svg',
        'infolists::components.icon-entry.false' => self::I.'x_circle.svg',
        'infolists::components.icon-entry.true' => self::I.'check_circle.svg',
        'notifications::notification.close-button' => self::I.'x.svg',
        'notifications::database.modal.empty-state' => self::I.'bell_slash.svg',
        'modal.close-button' => self::I.'x.svg',
        'notifications::notification.danger' => self::I.'x_circle.svg',
        'notifications::notification.info' => self::I.'info.svg',
        'notifications::notification.success' => self::I.'check_circle.svg',
        'notifications::notification.warning' => self::I.'warning.svg',
        'panels::pages.dashboard.actions.filter' => self::I.'filter.svg',
        'panels::pages.dashboard.navigation-item' => self::I.'sparkles.svg',
        'panels::pages.password-reset.request-password-reset.actions.login' => self::I.'sign_in.svg',
        'panels::pages.password-reset.request-password-reset.actions.login.rtl' => self::I.'sign_in.svg',
        'panels::resources.pages.edit-record.navigation-item' => self::I.'sparkles.svg',
        'panels::resources.pages.manage-related-records.navigation-item' => self::I.'sparkles.svg',
        'panels::resources.pages.view-record.navigation-item' => self::I.'sparkles.svg',
        'panels::tenant-menu.billing-button' => self::I.'banknotes.svg',
        'panels::tenant-menu.profile-button' => self::I.'user_circle.svg',
        'panels::tenant-menu.registration-button' => self::I.'sign_in.svg',
        'panels::user-menu.logout-button' => self::I.'sign_out.svg',
        'panels::user-menu.profile-item' => self::I.'user_circle.svg',
        'tables::actions.disable-reordering' => self::I.'arrows_up_down.svg',
        'tables::actions.enable-reordering' => self::I.'arrows_up_down.svg',
        'tables::actions.filter' => self::I.'filter.svg',
        'tables::actions.group' => self::I.'squares.svg',
        'tables::actions.open-bulk-actions' => self::I.'list.svg',
        'tables::actions.toggle-columns' => self::I.'table.svg',
        'tables::columns.icon-column.false' => self::I.'x_circle.svg',
        'tables::columns.icon-column.true' => self::I.'check_circle.svg',
        'tables::empty-state' => self::I.'search.svg',
        'tables::filters.query-builder.constraints.boolean' => self::I.'check_circle.svg',
        'tables::filters.query-builder.constraints.date' => self::I.'calendar.svg',
        'tables::filters.query-builder.constraints.number' => self::I.'hashtag.svg',
        'tables::filters.query-builder.constraints.relationship' => self::I.'link.svg',
        'tables::filters.query-builder.constraints.select' => self::I.'list.svg',
        'tables::filters.query-builder.constraints.text' => self::I.'text.svg',
    ];

    /** Prefix folder ikon publik. */
    public const I = '/images/icons/';

    /**
     * Daftar lengkap: alias internal vendor (di atas) + SEMUA nama ikon yang
     * dikenal (dari peta generator) ditimpa dengan path SVG berwarna.
     * TIDAK ADA pengecualian lagi: chevron ikut dipakai vektor
     * fonts.google.com/icons (keputusan pengguna, 2026-10-21).
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        $map = require __DIR__.'/icon-map.php';

        $overrides = [];
        foreach ($map as $name => $file) {
            $overrides[$name] = self::I.$file.'.svg';
        }

        return [
            ...$overrides,
            ...self::ICONS,
        ];
    }

    /**
     * Daftarkan semuanya ke Filament.
     */
    public static function register(): void
    {
        FilamentIcon::register(static::all());
    }
}
