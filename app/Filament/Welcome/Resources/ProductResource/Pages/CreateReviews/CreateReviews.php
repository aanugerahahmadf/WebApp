<?php

namespace App\Filament\Welcome\Resources\ProductResource\Pages\CreateReviews;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Filament\Welcome\Resources\ProductResource\ProductResource;
use App\Filament\Welcome\Resources\ReviewResource\ReviewResource;
use App\Http\Middleware\AuthenticateWelcome\AuthenticateWelcome;
use App\Models\Review\Review;
use Filament\Facades\Filament;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/**
 * Halaman penuh (bukan modal) untuk menulis ulasan sebuah produk.
 *
 * Dipakai lewat tombol "Tulis Ulasan" di halaman detail produk, yang
 * sebelumnya membuka slideOver. Field form-nya tetap diambil dari
 * `ReviewResource::itemReviewFields()` supaya review dari detail produk,
 * detail paket, dan pesanan memakai definisi field yang sama.
 */
class CreateReviews extends Page implements HasForms
{
    use HasDynamicBreadcrumbs;
    use InteractsWithForms;
    use InteractsWithRecord;

    protected static string $resource = ProductResource::class;

    protected static string $view = 'Welcome.pages.create-reviews.create-reviews';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public function mount(int|string $record): void
    {
        // Resolve dulu: Filament menjalankan mountCanAuthorizeAccess() setelah
        // mount() dan ia memanggil getRecord() yang bertipe Model.
        $this->record = $this->resolveRecord($record);

        // URL ini berada di bawah path publik storefront, jadi tamu bisa
        // mengetiknya langsung. Ulasan butuh user_id, jadi arahkan ke halaman
        // auth (/user/auth, bukan form) -- Laravel menyimpan intended URL dan
        // mengembalikan tamu ke sini setelah mereka masuk.
        if (! Filament::auth()->check()) {
            session()->put('url.intended', static::getUrl(['record' => $this->record->getKey()]));

            $this->redirect(route(AuthenticateWelcome::LOGIN_ROUTE), navigate: false);

            return;
        }

        // Sudah pernah ulasan? Form-nya tidak akan pernah bisa dikirim, jadi
        // lempar saja dengan pesan jelas daripada menampilkan form yang
        // pasti gagal.
        if ($this->hasReviewed()) {
            Notification::make()
                ->title(__('Anda sudah menulis ulasan untuk produk ini.'))
                ->warning()
                ->send();

            $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]), navigate: true);

            return;
        }

        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema(ReviewResource::itemReviewFields('product_id', (int) $this->record->getKey()))
            ->statePath('data');
    }

    public function submit(): void
    {
        // Halaman ini bisa diakses lewat URL langsung, jadi tamu yang lolos
        // mount() maupun request Livewire yang tegaknya sama-sama ditolak di
        // sini sebelum menyentuh apa pun.
        if (! Filament::auth()->check()) {
            $this->redirect(route(AuthenticateWelcome::LOGIN_ROUTE), navigate: false);

            return;
        }

        // Dicek ulang di submit, bukan hanya di mount(): dua tab yang terbuka
        // bersamaan bisa lolos pemeriksaan mount() tapi bentrok di sini.
        if ($this->hasReviewed()) {
            Notification::make()
                ->title(__('Anda sudah menulis ulasan untuk produk ini.'))
                ->warning()
                ->send();

            $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]), navigate: true);

            return;
        }

        $data = $this->form->getState();

        // Panel Welcome mengumpulkan foto lewat Repeater `photos`, bukan satu
        // field `photo` seperti panel User. Foto pertama ikut diisi ke kolom
        // `photo` supaya kode lama yang hanya membaca kolom itu -- termasuk
        // `Review::getPhotoUrlAttribute()` -- tetap melihat gambar.
        $photos = collect($data['photos'] ?? [])
            ->pluck('photo')
            ->filter()
            ->values()
            ->all();

        Review::create([
            'user_id' => auth()->id(),
            'product_id' => $this->record->getKey(),
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

        $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]), navigate: true);
    }

    protected function hasReviewed(): bool
    {
        return Review::query()
            ->where('user_id', auth()->id())
            ->where('product_id', $this->record->getKey())
            ->exists();
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
        return __('Bagikan pengalaman Anda tentang produk ini.');
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            $this->getTitle(),
        ];
    }
}
