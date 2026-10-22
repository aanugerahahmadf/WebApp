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
                    {{-- Bintang yang BELUM diklik dan BELUM dikursorin, serta teks label di
                         sebelahnya, berwarna hitam di mode Light.

                         Catatan jujur soal kondisi sebelumnya: di Light,
                         bintang yang belum dipilih sudah tampil hitam
                         (rgb 3 7 18) walau kelasnya `text-gray-300`.
                         Kelas itu terdefinisi sebagai
                         `rgba(var(--gray-300),.5)` dan tidak menang atas
                         aturan warna yang diwarisi, jadi hasilnya bukan
                         abu pucat melainkan hitam. Warna itu sekarang
                         ditulis eksplisit di sini supaya tidak bergantung
                         pada urutan cascade, dan tidak berubah diam-diam
                         kalau utility Tailwind lain ditambahkan.

                         Yang benar-benar masih abu adalah TEKS label di
                         bawah, karena `text-gray-700` memang tebal dan
                         menang normal. Itu yang diganti ke hitam.

                         Night sengaja tidak ikut digelapkan: di atas modal
                         gelap, hitam membuat bintang dan teks hilang
                         total. Night memakai abu terang agar tetap
                         terbaca. --}}
                    :class="(hover || rating || 0) >= star ? 'text-amber-400' : 'text-black dark:text-gray-300'"
                    :style="(hover || rating || 0) >= star ? 'color: #fbbf24' : ''"
                    class="rounded transition hover:scale-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                    :aria-label="labels[star]"
                    :aria-pressed="rating === star"
                >
                    <x-filament::icon icon="/images/icons/star.svg" class="h-9 w-9" />
                </button>
            </template>
        </div>
        <span
            class="text-sm font-medium text-black dark:text-gray-200"
            x-text="(hover || rating) ? labels[hover || rating] : '{{ __('Ketuk bintang untuk menilai') }}'"
        ></span>
    </div>
</x-dynamic-component>
