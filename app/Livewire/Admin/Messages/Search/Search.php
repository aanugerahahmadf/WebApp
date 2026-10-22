<?php

namespace App\Livewire\Admin\Messages\Search;

use App\Livewire\Admin\Traits\ChatIdentity\ChatIdentity;
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
    use ChatIdentity;

    public $search = '';

    public Collection $messages;

    public string $panelId = 'admin';

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
        $this->messages = collect();

        // Identitas chat panel admin = users.id staf (ChatStaff::adminUser()),
        // bukan admins.id dari Auth::id() -- pencarian dulu selalu kosong.
        $chatUserId = $this->chatUserId();

        if (! empty($search) && $chatUserId) {
            /** @var Builder $query */
            $query = Message::query();

            $this->messages = $query->with(['inbox'])
                ->whereHas('inbox', function (Builder $q) use ($chatUserId): void {
                    $q->whereJsonContains('user_ids', $chatUserId, 'and', false);
                })
                ->where('message', 'like', "%$search%")
                ->limit(5)
                ->latest()
                ->get(['*']);
        }
    }

    public function render(): Application|Factory|View|\Illuminate\View\View
    {
        return view('Admin.livewire.messages.search.search', [
            'messages' => $this->messages,
        ]);
    }
}
