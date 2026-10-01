@php
    $statePath = $getStatePath();
    $isDisabled = $isDisabled();
    $labels = [
        1 => __('Sangat buruk'),
        2 => __('Buruk'),
        3 => __('Biasa saja'),
        4 => __('Bagus'),
        5 => __('Luar biasa'),
    ];
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{
            rating: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            hover: 0,
            labels: @js($labels),
        }"
        @class([
            'flex flex-wrap items-center gap-x-3 gap-y-2',
            'pointer-events-none opacity-70' => $isDisabled,
        ])
    >
        <div
            id="{{ $getId() }}"
            class="flex items-center gap-1"
            role="radiogroup"
            aria-label="{{ $field->getLabel() }}"
        >
            <template x-for="star in [1, 2, 3, 4, 5]" :key="star">
                <button
                    type="button"
                    @click="rating = star"
                    @mouseenter="hover = star"
                    @mouseleave="hover = 0"
                    @focus="hover = star"
                    @blur="hover = 0"
                    :disabled="@js($isDisabled)"
                    :class="(hover || rating || 0) >= star ? 'text-amber-400' : 'text-gray-300 dark:text-gray-600'"
                    :style="(hover || rating || 0) >= star ? 'color: #fbbf24' : ''"
                    class="rounded transition hover:scale-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                    :aria-label="labels[star]"
                    :aria-pressed="rating === star"
                >
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" width="36" height="36" class="h-9 w-9"><path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd"/></svg>
                </button>
            </template>
        </div>
        <span
            class="text-sm font-medium text-gray-700 dark:text-gray-200"
            x-text="(hover || rating) ? labels[hover || rating] : '{{ __('Ketuk bintang untuk menilai') }}'"
        ></span>
    </div>
</x-dynamic-component>
