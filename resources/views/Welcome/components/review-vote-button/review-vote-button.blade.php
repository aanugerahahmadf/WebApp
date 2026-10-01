@php
    $voted = $review->isVotedBy(auth()->id());
@endphp
<form method="POST" action="{{ route('welcome.reviews.vote', $review) }}" @click.stop class="inline-block">
    @csrf
    <x-filament::button
        type="submit"
        :color="$voted ? 'primary' : 'gray'"
        outlined
        size="sm"
        :icon="$voted ? 'heroicon-s-hand-thumb-up' : 'heroicon-o-hand-thumb-up'"
    >
        {{ __('Membantu') }} ({{ number_format((int) $review->helpful_count) }})
    </x-filament::button>
</form>
