<?php

namespace App\Filament\User\Pages\MessagesPage;

use App\Filament\User\Pages\Home\Home;
use App\Filament\User\Pages\SettingsPage\SettingsPage;
use App\Filament\User\Resources\PackageResource\PackageResource;
use App\Filament\User\Resources\ProductResource\ProductResource;
use App\Models\Inbox\Inbox;
use App\Models\Package\Package;
use App\Models\Product\Product;
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
    protected static string $view = 'User.pages.messages.messages';

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
        $userId = Auth::id();
        if (! $userId) {
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

        // Jangan tampilkan badge kalau 0
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Pesan belum dibaca');
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
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
        $this->selectedConversation = $id
            ? Inbox::query()->findOrFail($id, ['*'])
            : null;
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
        $settingsUrl = SettingsPage::getUrl(panel: 'user');

        return $this->returnTo === $settingsUrl ? $settingsUrl : null;
    }

    public function getBreadcrumbs(): array
    {
        $breadcrumbs = [
            Home::getUrl() => __('Beranda'),
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
