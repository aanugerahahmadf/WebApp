<?php

namespace App\Livewire\Welcome\Messages\Search;

use App\Livewire\Welcome\Traits\InteractsWithGuestIdentity\InteractsWithGuestIdentity;
use App\Models\Message\Message;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @mixin Component
 */
class Search extends Component
{
    use InteractsWithGuestIdentity;

    public $search = '';

    public Collection $messages;

    public string $panelId = 'welcome';

    public function mount(): void
    {
        $this->messages = collect();
    }

    #[On('close-modal')]
    public function clearSearch(): void
    {
        $this->search = '';
        $this->updatedSearch();
    }

    public function updatedSearch(): void
    {
        $search = trim($this->search);
        $userId = $this->chatUserId();

        $this->messages = collect();

        // Skip the query entirely when there is no identity: whereJsonContains
        // against null is either a no-op or a match-all depending on the
        // driver, and neither is worth the gamble on a public page.
        if ($search === '' || $userId === null) {
            return;
        }

        /** @var Builder $query */
        $query = Message::query();

        $this->messages = $query->with(['inbox'])
            ->whereHas('inbox', function (Builder $q) use ($userId): void {
                $q->whereJsonContains('user_ids', $userId, 'and', false);
            })
            ->where('message', 'like', "%$search%")
            ->limit(5)
            ->latest()
            ->get(['*']);
    }

    public function render(): Application|Factory|View|\Illuminate\View\View
    {
        return view('Welcome.livewire.messages.search.search', [
            'messages' => $this->messages,
        ]);
    }
}
