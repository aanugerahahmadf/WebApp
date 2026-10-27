<?php

namespace App\Filament\Admin\Pages\MessagesPage;

use App\Models\Inbox\Inbox;
use App\Support\AppIcon\AppIcon;
use App\Support\AdminAccess\AdminAccess;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Halaman pesan panel admin.
 *
 * Isinya komponen Livewire `fm-admin-inbox` dan `fm-admin-messages` yang
 * sudah terdaftar di AppServiceProvider; halaman ini hanya kerangkanya.
 *
 * Percakapan yang tampil bukan milik admin yang sedang login: admin
 * sebagai STAFF melihat semua percakapan shopper, jadi daftar inboxnya
 * diambil dari seluruh inbox, bukan dari milik admin.
 */
class MessagesPage extends Page
{

    protected static string $view = 'Admin.pages.messages.messages';

    protected static ?string $navigationIcon = AppIcon::MESSAGES;

    protected static ?string $activeNavigationIcon = AppIcon::MESSAGES;

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return AdminAccess::allows('manage_support_tickets');
    }

    public ?Inbox $selectedConversation;

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
        return __('Pesan');
    }

    public static function getNavigationLabel(): string
    {
        return __(config('messages.navigation.navigation_label', 'Messages'));
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Cache::remember(
            'admin_unread_messages_count',
            now()->addSeconds(30),
            function (): int {
                return Inbox::query()
                    ->whereHas('messages', function (Builder $query): void {
                        if (DB::getDriverName() === 'sqlite') {
                            $query->whereRaw('read_by NOT LIKE ?', ['%"1"%']);
                        } else {
                            $query->whereRaw('JSON_SEARCH(read_by, "one", ?) IS NULL', ['1']);
                        }
                    })
                    ->count();
            }
        );

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Pesan belum dibaca');
    }

    public function mount(?int $id = null): void
    {
        $this->selectedConversation = $id
            ? Inbox::query()->findOrFail($id, ['*'])
            : null;
    }

    /**
     * Crumb parent TIDAK mengikuti halaman asal klik: header chat sudah punya
     * tombol panah ke Inbox.
     */
    protected function followsReferrerInBreadcrumb(): bool
    {
        return false;
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

    public function hasHeader(): bool
    {
        return false;
    }

        public function getBreadcrumbs(): array
    {
        return [];
    }
}
