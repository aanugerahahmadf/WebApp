<x-filament-panels::page>
    <div class="space-y-4">
        @if ($subtitle)
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
        @endif

        @forelse ($faqs as $item)
            <x-filament::section collapsible collapsed>
                <x-slot name="heading">
                    {{ $item['question'] ?? $item['q'] ?? $item['heading'] ?? '' }}
                </x-slot>
                <div class="text-sm text-gray-600 dark:text-gray-300">
                    {{ $item['answer'] ?? $item['a'] ?? $item['body'] ?? '' }}
                </div>
            </x-filament::section>
        @empty
            <x-filament::section>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Konten belum tersedia.') }}</p>
            </x-filament::section>
        @endforelse

        @if (! empty($contactOptions))
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Kontak') }}
                </x-slot>
                <dl class="grid gap-2 text-sm">
                    @foreach ($contactOptions as $option)
                        @php
                            $label = $option['label'] ?? $option[0] ?? '';
                            $value = $option['subLabel'] ?? $option['value'] ?? $option[1] ?? '';
                            $url = $option['url'] ?? null;
                        @endphp
                        @if ($label || $value)
                            <div>
                                <dt class="font-medium text-gray-900 dark:text-white">
                                    @if ($url)
                                        <a href="{{ $url }}" class="text-primary-600 hover:underline">{{ $label }}</a>
                                    @else
                                        {{ $label }}
                                    @endif
                                </dt>
                                @if ($value)
                                    <dd class="text-gray-600 dark:text-gray-300">{{ $value }}</dd>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </dl>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>