<template x-teleport="body">
<div
    x-cloak
    x-show="openReview"
    x-transition.opacity
    @click.self="openReview = false"
    @keydown.escape.window="openReview = false"
    class="fixed inset-0 z-[70] flex items-center justify-center overflow-y-auto bg-gray-950/60 p-4 backdrop-blur-sm sm:p-6"
    role="presentation"
>
    <section
        x-show="openReview"
        x-transition.scale.95
        @click.stop
        class="my-auto max-h-[min(85vh,48rem)] w-full max-w-2xl overflow-y-auto rounded-xl bg-white shadow-xl ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10"
        role="dialog"
        aria-modal="true"
        aria-label="{{ __('Detail ulasan') }}"
    >
        <div class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-gray-200 bg-white/95 px-5 py-4 backdrop-blur dark:border-white/10 dark:bg-gray-900/95 sm:px-6">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                {{ __('Ulasan dari :name', ['name' => $review->user?->full_name ?? __('Pengguna')]) }}
            </h2>
            <x-filament::icon-button icon="heroicon-m-x-mark" color="gray" size="md" :label="__('Tutup')" x-on:click.stop="openReview = false" />
        </div>

        <div class="p-5 sm:p-6">
            @include('User.components.review-detail-modal.review-detail-modal', ['review' => $review])
        </div>
    </section>
</div>
</template>
