<?php

namespace App\Filament\User\Resources\PackageResource\Pages\CreateReviews;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Filament\User\Resources\PackageResource\PackageResource;
use App\Filament\User\Resources\ReviewResource\ReviewResource;
use App\Models\Review\Review;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/**
 * Halaman penuh (bukan modal) untuk menulis ulasan sebuah paket.
 *
 * Dipakai lewat tombol "Tulis Ulasan" di halaman detail paket, yang
 * sebelumnya membuka slideOver. Field form-nya tetap diambil dari
 * `ReviewResource::itemReviewFields()` supaya review dari detail paket,
 * detail produk, dan pesanan memakai definisi field yang sama.
 */
class CreateReviews extends Page implements HasForms
{
    use HasDynamicBreadcrumbs;
    use InteractsWithForms;
    use InteractsWithRecord;

    protected static string $resource = PackageResource::class;

    protected static string $view = 'User.pages.create-reviews.create-reviews';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public function mount(int|string $record): void
    {
        // Resolve dulu: Filament menjalankan mountCanAuthorizeAccess() setelah
        // mount() dan ia memanggil getRecord() yang bertipe Model.
        $this->record = $this->resolveRecord($record);

        // Sudah pernah ulasan? Form-nya tidak akan pernah bisa dikirim, jadi
        // lempar saja dengan pesan jelas daripada menampilkan form yang
        // pasti gagal.
        if ($this->hasReviewed()) {
            Notification::make()
                ->title(__('Anda sudah menulis ulasan untuk paket ini.'))
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
            ->schema(ReviewResource::itemReviewFields('package_id', (int) $this->record->getKey()))
            ->statePath('data');
    }

    public function submit(): void
    {
        // Dicek ulang di submit, bukan hanya di mount(): dua tab yang terbuka
        // bersamaan bisa lolos pemeriksaan mount() tapi bentrok di sini.
        if ($this->hasReviewed()) {
            Notification::make()
                ->title(__('Anda sudah menulis ulasan untuk paket ini.'))
                ->warning()
                ->send();

            $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]), navigate: true);

            return;
        }

        $data = $this->form->getState();

        Review::create([
            'user_id' => auth()->id(),
            'package_id' => $this->record->getKey(),
            'rating' => $data['rating'],
            'title' => $data['title'] ?? null,
            'comment' => $data['comment'],
            'photo' => $data['photo'] ?? null,
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
            ->where('package_id', $this->record->getKey())
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
        return __('Bagikan pengalaman Anda tentang paket ini.');
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            $this->getTitle(),
        ];
    }
}
