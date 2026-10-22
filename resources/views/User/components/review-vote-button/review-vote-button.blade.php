@php
    $voted = $review->isVotedBy(auth()->id());
@endphp
<form method="POST" action="{{ route('reviews.vote', $review) }}" @click.stop class="inline-block">
    @csrf
    <x-filament::button
        type="submit"
        :color="$voted ? 'primary' : 'gray'"
        outlined
        size="sm"
        :icon="$voted ? '/images/icons/thumb_up.svg' : '/images/icons/thumb_up.svg'"
    >
        {{ __('Membantu') }} ({{ number_format((int) $review->helpful_count) }})
    </x-filament::button>
</form>
