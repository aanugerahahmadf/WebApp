<?php

namespace App\Filament\User\Resources\ProductResource\Pages\ManageProducts;

use App\Filament\User\Concerns\HasMobilePagination\HasMobilePagination;
use App\Filament\User\Resources\ProductResource\ProductResource;
use App\Models\Cart\Cart;
use App\Models\Product\Product;
use App\Models\Wishlist\Wishlist;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ManageProducts extends ManageRecords
{
    use HasMobilePagination;

    protected static string $resource = ProductResource::class;

    // public function getTabs(): array
    // {
    //     $cbirCount = session()->has('cbir_product_results_ids') ? count(session('cbir_product_results_ids')) : null;

    //     return [
    //         'all' => Tab::make(__('Semua Product'))
    //             ->icon('heroicon-m-squares-2x2')
    //             ->badge(fn () => $cbirCount ?? Product::count())
    //             ->badgeColor($cbirCount ? 'primary' : 'gray'),
    //         'wishlist' => Tab::make(__('Favorit Saya'))
    //             ->icon('heroicon-m-heart')
    //             ->badge(fn () => Product::whereHas('wishlists', fn ($q) => $q->where('user_id', Filament::auth()->id()))->count())
    //             ->badgeColor('danger')
    //             ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('wishlists', fn ($q) => $q->where('user_id', Filament::auth()->id()))),
    //         'orders' => Tab::make(__('Pesanan Saya'))
    //             ->icon('heroicon-m-shopping-bag')
    //             ->badge(fn () => Product::whereHas('orders', fn ($q) => $q->where('user_id', Filament::auth()->id()))->count())
    //             ->badgeColor('info')
    //             ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('orders', fn ($q) => $q->where('user_id', Filament::auth()->id()))),
    //     ];
    // }

    protected function modifyQueryUsing(Builder $query): Builder
    {
        // Handle direct ID from preview link
        if ($id = request()->query('cbir_id')) {
            session()->put('cbir_product_results_ids', [(int) $id]);
        }

        if ($ids = session()->get('cbir_product_results_ids')) {
            return $query->whereIn('id', $ids)
                ->orderByRaw('FIELD(id, '.implode(',', $ids).')');
        }

        return $query;
    }

    public function bookNow($id)
    {
        // Set the session filter to only this product
        session()->put('cbir_product_results_ids', [(int) $id]);

        Notification::make()
            ->title(__('Menuju halaman pemesanan...'))
            ->success()
            ->send();

        return redirect()->to(ProductResource::getUrl('checkout', ['record' => $id]));
    }

    public function toggleWishlist($id)
    {
        $user = Filament::auth()->user();
        $deleted = Wishlist::query()->where('user_id', $user->id)
            ->where('product_id', $id)
            ->delete();

        if ($deleted) {
            $msg = __('Dihapus dari Favorit');
            Notification::make()->title($msg)->warning()->send();
        } else {
            Wishlist::create([
                'user_id' => $user->id,
                'product_id' => $id,
            ]);
            $msg = __('Berhasil disimpan ke Favorit!');
            Notification::make()->title($msg)->success()->icon('heroicon-s-heart')->iconColor('danger')->send();
        }

        // Refresh session results to update heart icon
        $results = session('cbir_mixed_results', []);
        foreach ($results as &$res) {
            if (($res['type'] ?? '') === 'product' && ($res['data']['id'] ?? 0) == $id) {
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
            'product_id' => $id,
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
        session()->forget(['cbir_mixed_results', 'cbir_product_results_ids', 'cbir_search_time']);
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
}
