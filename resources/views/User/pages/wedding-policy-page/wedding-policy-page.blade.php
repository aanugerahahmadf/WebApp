<x-filament-panels::page>
    <div class="space-y-4">
        <x-filament::section>
            <x-slot name="heading">
                {{ $document['title'] }}
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
                            <h3 class="mb-1 font-semibold text-gray-900 dark:text-white">{{ $heading }}</h3>
                        @endif
                        <p class="{{ ! empty($item['is_italic']) ? 'italic' : '' }}">{{ $body }}</p>
                    </div>
                @empty
                    <p>{{ __('Konten belum tersedia.') }}</p>
                @endforelse
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>