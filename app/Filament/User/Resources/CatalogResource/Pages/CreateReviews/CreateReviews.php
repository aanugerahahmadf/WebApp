<?php

namespace App\Filament\User\Resources\CatalogResource\Pages\CreateReviews;

use App\Filament\User\Pages\Home\Home;
use App\Filament\User\Resources\CatalogResource\CatalogResource;
use App\Filament\User\Resources\ReviewResource\ReviewResource;
use App\Models\Catalog\Catalog;
use App\Models\Review\Review;
use Filament\Facades\Filament;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Halaman penuh (bukan modal) untuk menulis ulasan sebuah katalog.
 *
 * Dipakai lewat tombol "Tulis Ulasan" di halaman detail katalog, yang
 * sebelumnya membuka slideOver. Field form-nya tetap diambil dari
 * `ReviewResource::itemReviewFields()` supaya review dari detail katalog,
 * detail paket, dan pesanan memakai definisi field yang sama.
 *
 * DUA MODE, satu kelas:
 *
 *   1. FORM     -> route /flowerdecorationscatalog/{record}/reviews
 *      Form ulasan untuk SATU katalog.
 *
 *   2. PILIH    -> route /flowerdecorationscatalog/reviews
 *      Halaman ini sendiri yang menampilkan daftar katalog yang bisa diulas.
 *      Dipakai oleh tombol "Tulis Ulasan" di daftar ulasan, yang tidak punya
 *      satu katalog tunggal. Dulu itu diselesaikan dengan MODAL berisi
 *      dropdown katalog -- modalnya dihapus: sekarang user langsung masuk ke
 *      halaman (penuh) dan memilih katalog di dalamnya.
 *
 * Kenapa satu kelas dan bukan dua halaman: halaman CreateReviews milik satu
 * katalog, tapi tombol "Tulis Ulasan" harus mengarah ke HALAMAN INI juga.
 * Kalau mode PILIH punya kelas sendiri, tombolnya akan mengarah ke kelas lain
 * dan users complain "itu bukan halaman CreateReviews".
 */
class CreateReviews extends Page implements HasForms
{
    use InteractsWithForms;
    use InteractsWithRecord {
        // Di mode PILIH belum ada record, jadi pemeriksaan akses record harus
        // dilewati. Metode aslinya disimpan sebagai alias supaya mode FORM
        // tetap memakai aturan Filament yang biasa.
        mountCanAuthorizeAccess as protected mountCanAuthorizeRecordAccess;
    }

    protected static string $resource = CatalogResource::class;

    protected static string $view = 'User.pages.create-reviews.create-reviews';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    /**
     * Mode PILIH katalog.
     *
     * True kalau halaman dibuka tanpa record (route /reviews). False untuk
     * mode FORM (route /{record}/reviews).
     */
    public bool $choosingCatalog = false;

    /**
     * Kata kunci pencarian katalog di mode PILIH.
     */
    public string $catalogSearch = '';

    /**
     * Video yang dipilih dari galeri/file.
     *
     * Komponen TakePicture mengunggah video ke property yang bernama
     * `cameraUpload` -- jadi halaman ini HARUS punya property itu, kalau
     * tidak `$wire.upload()` gagal dan video tidak pernah masuk ke form.
     */
    public ?TemporaryUploadedFile $cameraUpload = null;

    public function mount(int|string|null $record = null): void
    {
        // Tanpa record -> mode PILIH katalog.
        //
        // Halaman CreateReviews milik SATU katalog (route /{record}/reviews),
        // tapi tombol "Tulis Ulasan" di daftar ulasan tidak punya katalog
        // tunggal. Daripada membuka modal pemilih katalog, halamannya
        // sendiri yang menampilkannya -- tetap halaman penuh, tanpa modal.
        if ($record === null) {
            $this->choosingCatalog = true;

            return;
        }

        // Resolve dulu: Filament menjalankan mountCanAuthorizeAccess() setelah
        // mount() dan ia memanggil getRecord() yang bertipe Model.
        $this->record = $this->resolveRecord($record);

        // Sudah pernah ulasan? Form-nya tidak akan pernah bisa dikirim, jadi
        // lempar saja dengan pesan jelas daripada menampilkan form yang
        // pasti gagal.
        if ($this->hasReviewed()) {
            Notification::make()
                ->title(__('Anda sudah menulis ulasan untuk katalog ini.'))
                ->warning()
                ->send();

            $this->redirect(static::getResource()::getUrl('view', ['record' => $this->getRecord()]), navigate: true);

            return;
        }

        $this->form->fill();
    }

    /**
     * Record katalog yang sedang diulas.
     *
     * Trait `InteractsWithRecord` mewajibkan return type `Model`, sedangkan di
     * mode PILIH memang belum ada record. Karena itu dikembalikan model
     * KOSONG (tidak pernah disimpan, tidak pernah dirender) supaya panggilan
     * internal Filament (mis. `getRecordTitle()`) tidak meledak.
     */
    public function getRecord(): Model
    {
        return $this->record ?? new Catalog();
    }

    /**
     * Pemeriksaan akses record.
     *
     * Di mode PILIH tidak ada record yang bisa diotorisasi, jadi dilewati.
     * Mode FORM tetap memakai aturan Filament bawaan (alias).
     */
    public function mountCanAuthorizeAccess(): void
    {
        if ($this->choosingCatalog) {
            return;
        }

        $this->mountCanAuthorizeRecordAccess();
    }

    /**
     * File yang dipilih dari galeri atau file picker masuk ke baris foto
     * PERTAMA yang masih kosong (baris baru dibuat kalau semuanya sudah
     * terisi).
     */
    public function updatedCameraUpload(): void
    {
        $file = $this->cameraUpload;
        $this->cameraUpload = null;

        if (! $file) {
            return;
        }

        $path = $file->store('review-photos', 'public');

        $photos = $this->data['photos'] ?? [];

        $filled = false;

        foreach ($photos as $index => $row) {
            if (empty($row['photo'])) {
                $photos[$index]['photo'] = $path;
                $filled = true;

                break;
            }
        }

        if (! $filled) {
            $photos[] = ['photo' => $path];
        }

        $this->data['photos'] = $photos;
    }

    public function form(Form $form): Form
    {
        return $form
            // Di mode PILIH formnya tidak dipakai (halaman menampilkan daftar
            // katalog), jadi skemanya kosong. PENTING: pakai `getRecord()`,
            // bukan `$this->record` -- di mode PILIH property itu belum
            // pernah diisi, dan typed property yang belum diinisialisasi
            // melempar "must not be accessed before initialization" saat
            // Filament membangun form di tiap render.
            ->schema($this->choosingCatalog
                ? []
                : ReviewResource::itemReviewFields('catalog_id', (int) $this->getRecord()->getKey()))
            ->statePath('data');
    }

    public function submit(): void
    {
        // Mode PILIH tidak punya form -- tidak ada yang bisa dikirim.
        if ($this->choosingCatalog) {
            return;
        }

        // Dicek ulang di submit, bukan hanya di mount(): dua tab yang terbuka
        // bersamaan bisa lolos pemeriksaan mount() tapi bentrok di sini.
        if ($this->hasReviewed()) {
            Notification::make()
                ->title(__('Anda sudah menulis ulasan untuk katalog ini.'))
                ->warning()
                ->send();

            $this->redirect(static::getResource()::getUrl('view', ['record' => $this->getRecord()]), navigate: true);

            return;
        }

        $data = $this->form->getState();

        // Foto diambil dari Repeater `photos`, sama seperti panel Welcome.
        // Foto pertama ikut diisi ke kolom `photo` supaya kode lama yang hanya
        // membaca kolom itu -- termasuk `Review::getPhotoUrlAttribute()` --
        // tetap melihat gambar.
        $photos = collect($data['photos'] ?? [])
            ->pluck('photo')
            ->filter()
            ->values()
            ->all();

        Review::create([
            'user_id' => auth()->id(),
            'catalog_id' => $this->getRecord()->getKey(),
            'rating' => $data['rating'],
            'title' => $data['title'] ?? null,
            'comment' => $data['comment'],
            'photos' => $photos,
            'photo' => $photos[0] ?? null,
        ]);

        Notification::make()
            ->title(__('Terima kasih atas ulasan Anda!'))
            ->success()
            ->send();

        $this->redirect(static::getResource()::getUrl('view', ['record' => $this->getRecord()]), navigate: true);
    }

    protected function hasReviewed(): bool
    {
        return Review::query()
            ->where('user_id', auth()->id())
            ->where('catalog_id', $this->getRecord()->getKey())
            ->exists();
    }

    /**
     * Katalog yang bisa dipilih di mode PILIH.
     *
     * DIBALIKKAN sebagai model (bukan array) supaya blade bisa memakai
     * komponen grid katalog yang SAMA dengan halaman katalog -- ukuran kartu
     * dan isinya (kategori, nama, harga, rating, stok) jadi identik.
     *
     * @return Collection<int, Catalog>
     */
    public function reviewableCatalogs(): Collection
    {
        $search = trim($this->catalogSearch);

        return ReviewResource::reviewableCatalogs()
            ->when($search !== '', fn (Collection $catalogs) => $catalogs->filter(
                fn (Catalog $catalog): bool => str_contains(
                    mb_strtolower((string) $catalog->name),
                    mb_strtolower($search),
                )
            ))
            ->values();
    }

    /**
     * URL halaman CreateReviews milik sebuah katalog.
     */
    public function reviewUrl(Catalog $catalog): string
    {
        return static::getResource()::getUrl('create-reviews', ['record' => $catalog->getKey()]);
    }

    /**
     * URL katalog untuk tombol "Lihat Katalog" saat tidak ada yang bisa diulas.
     */
    public function catalogIndexUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    public function getTitle(): string
    {
        return __('Tulis Ulasan');
    }

    public function getHeading(): string
    {
        return __('Tulis Ulasan');
    }

    public function getSubheading(): ?string
    {
        if ($this->choosingCatalog) {
            return __('Pilih katalog yang ingin Anda ulas.');
        }

        return __('Bagikan pengalaman Anda tentang katalog ini.');
    }

    public function getBreadcrumbs(): array
    {
        if ($this->choosingCatalog) {
            return [
                Home::getUrl() => __('Beranda'),
                ReviewResource::getUrl('index') => ReviewResource::getNavigationLabel(),
                $this->getTitle(),
            ];
        }

        return [
            Home::getUrl() => __('Beranda'),
            CatalogResource::getUrl('index') => CatalogResource::getNavigationLabel(),
            CatalogResource::getUrl('view', ['record' => $this->getRecord()]) => $this->getRecord()->name,
            $this->getTitle(),
        ];
    }
}
