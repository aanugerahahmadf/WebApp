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
    protected string $panel = 'user';

    /**
     * Laporkan sebuah ulasan langsung ke thread chat via form POST biasa
     * (bukan Livewire) — aman untuk guest maupun member, tanpa 419.
     */
    public function store(Request $request, Review $review): RedirectResponse
    {
        $senderId = Auth::id() ?? app(GuestIdentity::class)->id();

        if ($senderId === null) {
            return redirect()->route('filament.user.auth.login');
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

        $messagesPage = $this->panel === 'welcome'
            ? \App\Filament\Welcome\Pages\MessagesPage\MessagesPage::class
            : \App\Filament\User\Pages\MessagesPage\MessagesPage::class;

        return redirect($messagesPage::getUrl(['id' => $inbox->getKey()]));
    }
}
