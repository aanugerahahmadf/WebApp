<?php

namespace App\Http\Controllers\User\ReviewVoteController;

use App\Http\Controllers\Controller;
use App\Models\Review\Review;
use App\Models\ReviewVote\ReviewVote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewVoteController extends Controller
{
    /**
     * Toggle "Membantu" pada ulasan via form POST biasa (bukan Livewire),
     * supaya guest tidak pernah memicu POST /livewire/update yang rawan 419.
     */
    public function toggle(Request $request, Review $review): RedirectResponse
    {
        if (! Auth::check()) {
            $prev = url()->previous();
            if ($prev && ! str_contains($prev, 'livewire')) {
                session()->put('url.intended', $prev);
            }

            return redirect()->route('filament.user.auth.login');
        }

        $userId = Auth::id();
        $existing = ReviewVote::query()
            ->where('user_id', $userId)
            ->where('review_id', $review->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $review->decrement('helpful_count');
        } else {
            ReviewVote::create(['user_id' => $userId, 'review_id' => $review->id]);
            $review->increment('helpful_count');
        }

        return back();
    }
}
