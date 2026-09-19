<?php

namespace App\Filament\User\Resources\PackageResource\Pages\ManagePackages;

use App\Enums\OrderStatus\OrderStatus;
use App\Filament\User\Concerns\HasMobilePagination\HasMobilePagination;
use App\Filament\User\Resources\PackageResource\PackageResource;
use App\Models\Cart\Cart;
use App\Models\Package\Package;
use App\Models\Wishlist\Wishlist;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ManagePackages extends ManageRecords
{
    use HasMobilePagination;

    protected static string $resource = PackageResource::class;

    // public function getTabs(): array
    // {
    //     $cbirCount = session()->has('cbir_package_results_ids') ? count(session('cbir_package_results_ids')) : null;

    //     return [
    //         'all' => Tab::make(__('Semua Layanan'))
    //             ->icon('heroicon-m-squares-2x2')
    //             ->badge(fn () => $cbirCount ?? Package::query()->count('id'))
    //             ->badgeColor($cbirCount ? 'primary' : 'gray'),
    //         'wishlist' => Tab::make(__('Favorit Saya'))
    //             ->icon('heroicon-m-heart')
    //             ->badge(fn () => Package::query()->whereHas('wishlists', fn ($q) => $q->where('user_id', Filament::auth()->id()))->count('id'))
    //             ->badgeColor('danger')
    //             ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('wishlists', fn ($q) => $q->where('user_id', Filament::auth()->id()))),
    //         'orders' => Tab::make(__('Pesanan Saya'))
    //             ->icon('heroicon-m-shopping-bag')
    //             ->badge(fn () => Package::query()->whereHas('orders', fn ($q) => $q->where('user_id', Filament::auth()->id()))->count('id'))
    //             ->badgeColor('info')
    //             ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('orders', fn ($q) => $q->where('user_id', Filament::auth()->id()))),
    //         'payments' => Tab::make(__('Konfirmasi Bayar'))
    //             ->icon('heroicon-m-credit-card')
    //             ->badge(fn () => Package::query()->whereHas('orders', fn ($q) => $q->where('user_id', Filament::auth()->id())->whereIn('status', [OrderStatus::PENDING]))->count('id'))
    //             ->badgeColor('warning')
    //             ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('orders', fn ($q) => $q->where('user_id', Filament::auth()->id())->whereIn('status', [OrderStatus::PENDING]))),
    //         'history' => Tab::make(__('Riwayat'))
    //             ->icon('heroicon-m-clock')
    //             ->badge(fn () => Package::query()->whereHas('orders', fn ($q) => $q->where('user_id', Filament::auth()->id())->whereIn('status', [OrderStatus::COMPLETED, OrderStatus::CANCELLED]))->count('id'))
    //             ->badgeColor('gray')
    //             ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('orders', fn ($q) => $q->where('user_id', Filament::auth()->id())->whereIn('status', [OrderStatus::COMPLETED, OrderStatus::CANCELLED]))),
    //     ];
    // }

    protected function modifyQueryUsing(Builder $query): Builder
    {
        // Handle direct ID from preview link
        if ($id = request()->query('cbir_id')) {
            session()->put('cbir_package_results_ids', [(int) $id]);
        }

        if ($ids = session()->get('cbir_package_results_ids')) {
            return $query->whereIn('id', $ids)
                ->orderByRaw('FIELD(id, '.implode(',', $ids).')');
        }

        return $query;
    }

    public function bookNow($id)
    {
        // Set the session filter to only this package
        session()->put('cbir_package_results_ids', [(int) $id]);

        Notification::make()
            ->title(__('Menuju halaman pemesanan...'))
            ->success()
            ->send();

        return redirect()->to(PackageResource::getUrl('checkout', ['record' => $id]));
    }

    public function toggleWishlist($id)
    {
        $user = Filament::auth()->user();
        $deleted = Wishlist::query()->where('user_id', $user->id)
            ->where('package_id', $id)
            ->delete();

        if ($deleted) {
            $msg = __('Dihapus dari Favorit');
            Notification::make()->title($msg)->warning()->send();
        } else {
            Wishlist::create([
                'user_id' => $user->id,
                'package_id' => $id,
            ]);
            $msg = __('Berhasil disimpan ke Favorit!');
            Notification::make()->title($msg)->success()->icon('heroicon-s-heart')->iconColor('danger')->send();
        }

        // Refresh session results to update heart icon
        $results = session('cbir_mixed_results', []);
        foreach ($results as &$res) {
            if (($res['type'] ?? '') === 'package' && ($res['data']['id'] ?? 0) == $id) {
                $res['data']['is_wishlisted'] = ! $deleted;
            }
        }
        session()->put('cbir_mixed_results', $results);
    }

    public function addToCart($id)
    {
        $user = Filament::auth()->user();

        Cart::updateOrCreate([
            'user_id' => $user->id,
            'package_id' => $id,
        ], [
            'quantity' => DB::raw('quantity + 1'),
        ]);

        Notification::make()
            ->title(__('Berhasil masuk keranjang'))
            ->success()
            ->icon('heroicon-o-shopping-cart')
            ->send();
    }

    public function clearVisualSearch()
    {
        session()->forget(['cbir_mixed_results', 'cbir_package_results_ids', 'cbir_search_time']);
        $this->dispatch('refresh_catalog');
    }

    protected function getListeners(): array
    {
        return [
            'refresh_catalog' => '$refresh',
            'book_now' => 'bookNow',
            'toggle_wishlist' => 'toggleWishlist',
            'clear_visual_search' => 'clearVisualSearch',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            // Removed for table integration
        ];
    }
}
