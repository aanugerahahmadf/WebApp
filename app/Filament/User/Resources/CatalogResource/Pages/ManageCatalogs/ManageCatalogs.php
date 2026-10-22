<?php

namespace App\Filament\User\Resources\CatalogResource\Pages\ManageCatalogs;

use App\Filament\User\Pages\Home\Home;
use App\Filament\User\Resources\CatalogResource\CatalogResource;
use App\Models\Cart\Cart;
use App\Models\Catalog\Catalog;
use App\Models\Wishlist\Wishlist;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ManageRecords;
use App\Support\AppIcon\AppIcon;
use Illuminate\Database\Eloquent\Builder;

class ManageCatalogs extends ManageRecords
{
    protected static string $resource = CatalogResource::class;

    // public function getTabs(): array
    // {
    //     $cbirCount = session()->has('cbir_catalog_results_ids') ? count(session('cbir_catalog_results_ids')) : null;

    //     return [
    //         'all' => Tab::make(__('Semua Catalog'))
    //             ->icon('/images/icons/squares.svg')
    //             ->badge(fn () => $cbirCount ?? Catalog::count())
    //             ->badgeColor($cbirCount ? 'primary' : 'gray'),
    //         'wishlist' => Tab::make(__('Favorit Saya'))
    //             ->icon('/images/icons/heart.svg')
    //             ->badge(fn () => Catalog::whereHas('wishlists', fn ($q) => $q->where('user_id', Filament::auth()->id()))->count())
    //             ->badgeColor('danger')
    //             ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('wishlists', fn ($q) => $q->where('user_id', Filament::auth()->id()))),
    //         'orders' => Tab::make(__('Pesanan Saya'))
    //             ->icon('/images/icons/bag.svg')
    //             ->badge(fn () => Catalog::whereHas('orders', fn ($q) => $q->where('user_id', Filament::auth()->id()))->count())
    //             ->badgeColor('info')
    //             ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('orders', fn ($q) => $q->where('user_id', Filament::auth()->id()))),
    //     ];
    // }

    protected function modifyQueryUsing(Builder $query): Builder
    {
        // Handle direct ID from preview link
        if ($id = request()->query('cbir_id')) {
            session()->put('cbir_catalog_results_ids', [(int) $id]);
        }

        if ($ids = session()->get('cbir_catalog_results_ids')) {
            return $query->whereIn('id', $ids)
                ->orderByRaw('FIELD(id, '.implode(',', $ids).')');
        }

        return $query;
    }

    public function bookNow($id)
    {
        // Set the session filter to only this catalog
        session()->put('cbir_catalog_results_ids', [(int) $id]);

        Notification::make()
            ->title(__('Menuju halaman pemesanan...'))
            ->success()
            ->send();

        return redirect()->to(CatalogResource::getUrl('checkout', ['record' => $id]));
    }

    public function toggleWishlist($id)
    {
        $user = Filament::auth()->user();
        $deleted = Wishlist::query()->where('user_id', $user->id)
            ->where('catalog_id', $id)
            ->delete();

        if ($deleted) {
            $msg = __('Dihapus dari Favorit');
            Notification::make()->title($msg)->warning()->send();
        } else {
            Wishlist::create([
                'user_id' => $user->id,
                'catalog_id' => $id,
            ]);
            $msg = __('Berhasil disimpan ke Favorit!');
            Notification::make()->title($msg)->success()->icon('/images/icons/heart.svg')->iconColor('danger')->send();
        }

        // Refresh session results to update heart icon
        $results = session('cbir_mixed_results', []);
        foreach ($results as &$res) {
            if (($res['type'] ?? '') === 'catalog' && ($res['data']['id'] ?? 0) == $id) {
                $res['data']['is_wishlisted'] = ! $deleted;
            }
        }
        session()->put('cbir_mixed_results', $results);
    }

    public function addToCart($id)
    {
        $user = Filament::auth()->user();

        Cart::incrementQuantity($user->id, $id, null);

        Notification::make()
            ->title(__('Berhasil masuk keranjang'))
            ->success()
            ->icon('/images/icons/cart.svg')
            ->send();
    }

    public function clearVisualSearch()
    {
        session()->forget(['cbir_mixed_results', 'cbir_catalog_results_ids', 'cbir_search_time']);
        $this->dispatch('refresh_items');
        $this->dispatch('refresh_catalog');
    }

    protected function getListeners(): array
    {
        return [
            'refresh_items' => '$refresh',
            'refresh_catalog' => '$refresh',
            'toggle_wishlist' => 'toggleWishlist',
            'book_now' => 'bookNow',
            'clear_visual_search' => 'clearVisualSearch',
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            Home::getUrl() => __('Beranda'),
            CatalogResource::getUrl('index') => CatalogResource::getNavigationLabel(),
        ];
    }
}
