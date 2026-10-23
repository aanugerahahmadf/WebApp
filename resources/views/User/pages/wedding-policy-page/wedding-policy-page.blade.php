<x-filament-panels::page>
    <div class="space-y-4">
        <x-filament::section>
            <x-slot name="heading">
                {{-- Gaya Teks: styling per-field "wedding_decoration_policies.title" dari
                     form admin (App\Support\TextStyles) -- input Kapital dkk.
                     di form ikut ke sini. --}}
                <div>
                    {{ $document['title'] }}
                </div>
            </x-slot>
            @if ($document['updated_at'])
                <x-slot name="description">
                    {{ __('Diperbarui') }}: {{ $document['updated_at']->format('d M Y') }}
                </x-slot>
            @endif
            <div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">
                @forelse ($document['content'] as $item)
                    @php
                        $heading = $item['heading'] ?? null;
                        $body = $item['body'] ?? '';
                    @endphp
                    <div>
                        @if ($heading)
                            {{-- data-typo="subheading": tipografi per-peran; data-field: override per-field "content". --}}
                            <h3 class="mb-1 font-semibold text-gray-900 dark:text-white" data-typo="subheading">{{ $heading }}</h3>
                        @endif
                        <p class="text-justify not-italic normal-case" style="text-align: justify !important; font-style: normal !important; text-transform: none !important;" data-typo="description">{{ $body }}</p>
                    </div>
                @empty
                    <p>{{ __('Konten belum tersedia.') }}</p>
                @endforelse
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
