<?php

namespace App\Http\Controllers\User\ReviewReportController;

use App\Http\Controllers\Controller;
use App\Models\Review\Review;
use App\Services\ChatService\ChatService;
use App\Services\GuestIdentity\GuestIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewReportController extends Controller
{
    /**
     * Panel yang menyajikan halaman Messages.
     *
     * SATU RUANG URL (shopee-style): panel 'user' sudah dilebur ke panel
     * 'welcome' di root, jadi nilai bakunya 'welcome'. Dibiarkan sebagai
     * properti supaya subclass (Welcome/Admin) tetap bisa menimpanya tanpa
     * menyentuh method-nya.
     */
    protected string $panel = 'welcome';

    /**
     * Laporkan sebuah ulasan langsung ke thread chat via form POST biasa
     * (bukan Livewire) — aman untuk guest maupun member, tanpa 419.
     */
    public function store(Request $request, Review $review): RedirectResponse
    {
        $senderId = Auth::id() ?? app(GuestIdentity::class)->id();

        if ($senderId === null) {
            return redirect()->route('filament.welcome.auth.login');
        }

        $inbox = ChatService::getOrCreateInboxWithAdmin($senderId);

        if (! $inbox) {
            return back()->with('error', __('Fitur chat belum tersedia.'));
        }

        ChatService::sendReportMessage(
            $inbox,
            'review',
            (string) ($review->user?->full_name ?? __('Pengguna')),
            null,
            [],
            $senderId
        );

        // Hanya SATU halaman Messages yang terdaftar sekarang: milik panel
        // 'welcome' di root. Kelas User\MessagesPage tidak lagi punya route,
        // jadi jangan pernah dipakai untuk getUrl() -- RouteNotFoundException.
        $messagesPage = \App\Filament\Welcome\Pages\MessagesPage\MessagesPage::class;

        return redirect($messagesPage::getUrl(['id' => $inbox->getKey()]));
    }
}
