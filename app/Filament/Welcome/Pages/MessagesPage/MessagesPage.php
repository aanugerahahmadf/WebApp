<?php

namespace App\Filament\Welcome\Pages\MessagesPage;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Filament\Welcome\Pages\Home\Home;
use App\Filament\Welcome\Pages\SettingsPage\SettingsPage;
use App\Filament\Welcome\Resources\PackageResource\PackageResource;
use App\Filament\Welcome\Resources\ProductResource\ProductResource;
use App\Models\Inbox\Inbox;
use App\Models\Package\Package;
use App\Models\Product\Product;
use App\Services\GuestIdentity\GuestIdentity;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class MessagesPage extends Page
{
    use HasDynamicBreadcrumbs;

    protected static string $view = 'Welcome.pages.messages.messages';

    protected static ?string $activeNavigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = 1;

    public ?Inbox $selectedConversation;

    #[Url(as: 'returnTo')]
    public ?string $returnTo = null;

    public static function getSlug(): string
    {
        return config('messages.slug', 'messages').'/{id?}';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return config('messages.navigation.show_in_menu', true);
    }

    public static function getNavigationGroup(): ?string
    {
        return __(config('messages.navigation.navigation_group'));
    }

    public static function getNavigationLabel(): string
    {
        return __(config('messages.navigation.navigation_label', 'Messages'));
    }

    public static function getNavigationBadge(): ?string
    {
        $guestIdentity = app(GuestIdentity::class);
        $userId = $guestIdentity->id();

        if ($userId === null) {
            return null;
        }

        $count = Cache::remember(
            "user_{$userId}_unread_messages_count",
            now()->addSeconds(30),
            function () use ($userId) {
                return Inbox::query()
                    ->whereJsonContains('user_ids', $userId)
                    ->whereHas('messages', function (Builder $query) use ($userId) {
                        // Pesan yang read_by-nya tidak mengandung userId ini
                        if (DB::getDriverName() === 'sqlite') {
                            $query->whereRaw('read_by NOT LIKE ?', ["%\"{$userId}\"%"]);
                        } else {
                            $query->whereRaw(
                                'JSON_SEARCH(read_by, "one", ?) IS NULL',
                                [(string) $userId]
                            );
                        }
                        $query->where('user_id', '!=', $userId); // hanya pesan dari orang lain
                    })
                    ->count();
            }
        );

        // Badge selalu tampil, termasuk "0".
        //
        // Sebelumnya di sini ada kondisi `$count > 0 ? ... : null`, jadi
        // badge hilang begitu tidak ada pesan belum dibaca. Untuk tamu atau
        // pengunjung yang memang belum pernah chatted, itu membuat item
        // "Messages" di sidebar/topnav terlihat tidak punya badge sama
        // sekali, padahal item lain (My Review, Transaction History)
        // tetap menampilkan "0" pada kondisi yang sama.
        //
        // Side effect yang disengaja: cache 30 detiknya ikut berubah,
        // karena nilai "0" sekarang ikut tersimpan dan tidak lagi
        // melewati jalur return null.
        return (string) $count;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Pesan belum dibaca');
    }

    public static function getNavigationIcon(): string|Htmlable|null
    {
        return config('messages.navigation.navigation_icon', static::$activeNavigationIcon);
    }

    public static function getNavigationSort(): ?int
    {
        return config('messages.navigation.navigation_sort', static::$navigationSort);
    }

    public function mount(?int $id = null): void
    {
        $userId = app(GuestIdentity::class)->id();

        if ($id && $userId !== null) {
            $inbox = Inbox::query()->findOrFail($id, ['*']);

            abort_unless(
                in_array($userId, array_map('intval', (array) $inbox->user_ids), true),
                403,
                __('Anda tidak memiliki akses ke percakapan ini.')
            );

            $this->selectedConversation = $inbox;
        } else {
            $this->selectedConversation = null;
        }
    }

    public function getTitle(): string
    {
        return __(config('messages.navigation.navigation_label', 'Messages'));
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return config('messages.max_content_width', MaxWidth::Full);
    }

    public function getHeading(): string|Htmlable
    {
        return __('Messages');
    }

    /**
     * Remove the default Filament page header (title + breadcrumbs) on desktop
     * so the chat container gets the full available vertical space.
     * The page title is still used for the browser tab / navigation badge.
     */
    public function hasHeader(): bool
    {
        if ($this->settingsReturnUrl()) {
            return true;
        }

        // Keep heading visible on mobile (â‰¤ 1023px) because the layout is single-column
        // and the user needs context. On desktop the topnav already shows the page name.
        return request()->header('X-Filament-Mobile', false) || (
            isset($_SERVER['HTTP_USER_AGENT']) &&
            preg_match('/Mobile|Android|iPhone|iPad/i', $_SERVER['HTTP_USER_AGENT'] ?? '')
        );
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        $settingsUrl = $this->settingsReturnUrl();

        if (! $settingsUrl) {
            return [];
        }

        return [
            Action::make('backToSettings')
                ->label(__('Kembali ke Pengaturan'))
                ->icon('heroicon-m-arrow-left')
                ->color('gray')
                ->url($settingsUrl),
        ];
    }

    private function settingsReturnUrl(): ?string
    {
        $settingsUrl = SettingsPage::getUrl(panel: 'welcome');

        return $this->returnTo === $settingsUrl ? $settingsUrl : null;
    }

    public function getBreadcrumbs(): array
    {
        $breadcrumbs = [
            ...$this->breadcrumbParentCrumb(),
        ];

        if ($origin = $this->resolveOriginItem()) {
            $resource = $origin['type'] === 'product' ? ProductResource::class : PackageResource::class;
            $breadcrumbs[$resource::getUrl('index')] = $resource::getNavigationLabel();
            $breadcrumbs[$resource::getUrl('view', ['record' => $origin['item']])] = $origin['item']->name;
        }

        $breadcrumbs[static::getUrl()] = __('Inbox');

        if ($this->selectedConversation) {
            $breadcrumbs[] = $this->selectedConversation->inbox_title ?: __('Pesan');
        }

        return $breadcrumbs;
    }

    /**
     * Lacak item katalog asal percakapan dari kartu konteks / laporan item
     * terbaru (meta type + id). Kartu pesanan (is_order) hanya jadi cadangan.
     *
     * @return array{type: string, item: Package|Product}|null
     */
    protected function resolveOriginItem(): ?array
    {
        $inbox = $this->selectedConversation;

        if (! $inbox) {
            return null;
        }

        $fallback = null;

        $messages = $inbox->messages()->latest('id')->limit(50)->get(['id', 'meta']);

        foreach ($messages as $message) {
            $meta = $message->meta ?? [];

            if (! in_array($meta['type'] ?? null, ['package', 'product'], true) || empty($meta['id'])) {
                continue;
            }

            $item = $meta['type'] === 'product'
                ? Product::query()->find($meta['id'])
                : Package::query()->find($meta['id']);

            if (! $item) {
                continue;
            }

            if (! empty($meta['is_order'])) {
                $fallback ??= ['type' => $meta['type'], 'item' => $item];

                continue;
            }

            return ['type' => $meta['type'], 'item' => $item];
        }

        return $fallback;
    }
}
