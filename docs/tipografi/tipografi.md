# Tipografi & Gaya Teks

Kontrol tipografi form admin yang mempengaruhi TAMPILAN semua panel
(Admin, User/Welcome, Livewire, dan blade views).

## Dua Lapisan

| Lapisan | Kelas | Bentuk kontrol | Penyimpanan |
|---|---|---|---|
| 1. Per halaman (per-peran) | `App\Support\AppTypography\AppTypography` | section "Tipografi Halaman Ini" sekali per form | `fm_app_settings.key = 'typography'` |
| 2. Per-field (pengecualian) | `App\Support\TextStyles\TextStyles` | section "Gaya Teks — <label>" di ATAS tiap TextInput/Textarea/RichEditor | `fm_app_settings.key = 'text_styles'` |

Lapisan 1 menata seluruh halaman lintas panel: **4 peran** teks
(`heading`, `subheading`, `description`, `input`), masing-masing **6
pengaturan**: Font, Ukuran (px), Perataan (kiri/tengah/kanan/justify),
Ketebalan (400/600/700/800), Mode Warna (`theme`/`light`/`dark`), Kapital
(`uppercase`/`lowercase`/`capitalize`).

Lapisan 2 memungkinkan SATU field berbeda dari perannya — mis. kolom
`title` harus KAPITAL semua tanpa mengubah seluruh halaman.

## Cara Kerja

1. **Kontrol form**: `App\Filament\Shared\Typography\TypographyControls`
   (section per-peran) dan trait
   `App\Filament\Shared\Concerns\HasTextStyles\HasTextStyles::styledSchema()`
   (decorator per-field). Pasangnya lewat `HasModelColumns::formSchema()`
   (mencakup resource Admin dengan skema kolom generik) plus tiga resource
   yang menulis form manual: `AdminResource`, `CatalogResource`,
   `DeveloperStudioResource`, kedua halaman Create Studio
   (`CreateDatabaseStudio`, `CreateDeveloperStudio`), dan dua relation
   manager tab katalog (`CategoriesRelationManager`, `TiersRelationManager`
   -- tabel diberikan eksplisit karena RelationManager tidak punya
   `getModel()`). Form login/OTP dan Terminal SENGAJA tidak diberi kontrol:
   halaman itu bisa dibuka TAMU tanpa login, jadi kontrol yang menyimpan
   pengaturan global tidak boleh ada di sana (CSS tipografinya tetap
   mengikat halaman-nya secara otomatis lewat hook panel).
2. **State select** `dehydrated(false)` — TIDAK ada kolom model baru.
   State path di-root khusus yang tak pernah nama kolom:
   `__typo.<role>.<key>` dan `__ga_teks__<tabel>__<kolom>.<properti>`.
   Penulisan terjadi langsung di `afterStateUpdated`
   (`AppTypography::setRole()` / `TextStyles::apply()`), jadi perubahan
   tersimpan tanpa menekan tombol Save record.
3. **CSS**: `AppTypography::styles()` + `TextStyles::stylesheet()` dikabungkan
   di belakang `AppFont::styles()` (hook `panels::styles.after` milik
   AdminPanelProvider / UserPanelProvider / WelcomePanelProvider — tiga
   panel, satu titik injeksi). Selector per-peran: class Filament
   (`.fi-header-heading`, dst.) **plus** `[data-typo="<role>"]` untuk blade;
   override per-field menarget `[data-field="<tabel>.<kolom>"]` dan di-emit
   SETELAH blok per-peran agar selalu menang di cascade.
4. **Whitelist**: font hanya dari `AppFont::families()`; ukuran/weight/
   transform di-validasi. Nilai di luar whitelist jatuh ke "ikut tema" —
   satu isian buruk tidak boleh mematikan CSS halaman. `AppTypography::
   styles()` mengemit string kosong ketika semua default (halaman bersih).

## Pemakai Blade (lapisan per-field / per-peran)

```blade
{{-- per-peran: blok judul halaman ikut aturan Judul --}}
<h2 data-typo="heading">{{ $title }}</h2>

{{-- per-field: override khusus kolom ini --}}
<div {{ \App\Support\TextStyles\TextStyles::attrs('privacy_policies', 'title') }}>
    {{ $title }}
</div>
```

Sudah dipasang di: halaman dokumen (Privacy/Terms/Wedding Policy/Help,
versi User & Welcome + versi legal `LegalWebController`), komponen katalog
(`catalog-grid` -> `catalogs.name`/`categories.name`,
`catalog-tier-selector` -> `packages.name`/`packages.description`), dan
komponen ulasan (`review-list`, `review-detail-modal` ->
`reviews.title`/`reviews.comment`). Tambahkan pola yang sama di blade baru
yang menampilkan konten hasil form admin supaya "Gaya Teks"-nya ikut.

## Test

- `tests/Feature/Admin/TypographyControlsTest/` — cakupan lapisan per-peran
  (section lengkap, persistence, perataan terpisah, kapital ikut panel,
  whitelist, CSS bersih saat default).
- `tests/Feature/Admin/TextStylesTest/` — cakupan lapisan per-field
  (persistence, pruning nilai netral, whitelist, urutan cascade,
  atribut `data-field`, section di atas tiap input).
