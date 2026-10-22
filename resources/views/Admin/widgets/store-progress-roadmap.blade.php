<x-filament-widgets::widget class="fi-filament-store-progress-roadmap">
    <x-filament::section
        :heading="__('Roadmap Pengelolaan Toko')"
        :description="$statusLabel"
        icon="/images/icons/map.svg"
        icon-color="primary"
    >
        <x-slot name="headerEnd">
            <div class="flex items-center gap-x-3">
                <x-filament::badge color="primary" size="lg">
                    {{ __('Progress Setup') }}: {{ $doneCount }}/{{ $totalCount }} ({{ $progressPct }}%)
                </x-filament::badge>
            </div>
        </x-slot>

        {{-- Progress Bar Bawaan Filament Theme --}}
        <div class="w-full h-2 rounded-full bg-gray-200 dark:bg-gray-800 overflow-hidden mb-6">
            <div class="h-full bg-primary-600 dark:bg-primary-500 transition-all duration-500 rounded-full"
                 style="width: {{ $progressPct }}%"></div>
        </div>

        {{-- Wizard Step Navigation Bar (Fitur Desain Bawaan Filament Wizard) --}}
        <div class="fi-fo-wizard overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <ol role="list" class="fi-fo-wizard-header grid divide-y divide-gray-200 dark:divide-white/5 md:grid-cols-4 md:divide-y-0 md:divide-x dark:md:divide-white/5 rtl:md:divide-x-reverse">
                @foreach ($steps as $index => $step)
                    @php
                        $isDone = $step['done'];
                        $isNext = !$isDone && ($index === 0 || $steps[$index - 1]['done']);
                    @endphp
                    <li class="fi-fo-wizard-header-step relative flex flex-col justify-between p-4 transition-colors hover:bg-gray-50 dark:hover:bg-white/5
                               {{ $isDone ? 'bg-primary-50/20 dark:bg-primary-950/10' : ($isNext ? 'bg-amber-50/30 dark:bg-amber-950/10' : '') }}">
                        <div>
                            {{-- Step Header: Indicator + Badges Bawaan Filament --}}
                            <div class="flex items-center justify-between gap-x-2 mb-3">
                                <div class="fi-fo-wizard-header-step-icon-ctn flex h-9 w-9 shrink-0 items-center justify-center rounded-full
                                            {{ $isDone
                                                ? 'bg-primary-600 text-white dark:bg-primary-500'
                                                : ($isNext
                                                    ? 'border-2 border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400 font-bold'
                                                    : 'border-2 border-gray-300 text-gray-500 dark:border-gray-600 dark:text-gray-400') }}">
                                    @if ($isDone)
                                        <x-filament::icon
                                            icon="/images/icons/check.svg"
                                            class="h-5 w-5 text-white"
                                        />
                                    @else
                                        <span class="text-xs font-semibold">
                                            {{ str_pad($step['step_num'], 2, '0', STR_PAD_LEFT) }}
                                        </span>
                                    @endif
                                </div>

                                <div>
                                    @if ($isDone)
                                        <x-filament::badge color="success" size="sm" icon="/images/icons/check.svg">
                                            {{ __('Selesai') }}
                                        </x-filament::badge>
                                    @elseif ($isNext)
                                        <x-filament::badge color="warning" size="sm" icon="/images/icons/arrow_right.svg">
                                            {{ __('Langkah Berikutnya') }}
                                        </x-filament::badge>
                                    @else
                                        <x-filament::badge color="gray" size="sm">
                                            {{ __('Menunggu') }}
                                        </x-filament::badge>
                                    @endif
                                </div>
                            </div>

                            {{-- Step Title & Description --}}
                            <h4 class="text-sm font-semibold text-gray-950 dark:text-white flex items-center gap-1.5">
                                <x-filament::icon
                                    :icon="$step['icon']"
                                    class="h-4 w-4 text-gray-400 dark:text-gray-500"
                                />
                                {{ $step['title'] }}
                            </h4>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 line-clamp-2">
                                {{ $step['description'] }}
                            </p>
                        </div>

                        {{-- Footer Action Link Bawaan Filament --}}
                        <div class="mt-4 pt-3 border-t border-gray-100 dark:border-white/5 flex items-center justify-between text-xs">
                            @if (!empty($step['count_label']))
                                <span class="font-medium text-gray-700 dark:text-gray-300 font-mono">
                                    {{ $step['count_label'] }}
                                </span>
                            @else
                                <span class="text-gray-400 dark:text-gray-500">
                                    {{ __('Konfigurasi') }}
                                </span>
                            @endif

                            <x-filament::link
                                :href="$step['url']"
                                color="primary"
                                icon="/images/icons/external.svg"
                                icon-position="after"
                                size="sm"
                            >
                                {{ __('Buka') }}
                            </x-filament::link>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>

        {{-- Footer Guidance menggunakan callout section info Filament --}}
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs text-gray-500 dark:text-gray-400">
            <div class="flex items-center gap-x-4">
                <span class="inline-flex items-center gap-x-1.5">
                    <span class="h-2 w-2 rounded-full bg-success-500"></span>
                    <span>{{ __('Selesai') }}</span>
                </span>
                <span class="inline-flex items-center gap-x-1.5">
                    <span class="h-2 w-2 rounded-full bg-warning-500"></span>
                    <span>{{ __('Langkah Prioritas') }}</span>
                </span>
                <span class="inline-flex items-center gap-x-1.5">
                    <span class="h-2 w-2 rounded-full bg-gray-400"></span>
                    <span>{{ __('Menunggu') }}</span>
                </span>
            </div>
            <div class="flex items-center gap-1.5">
                <x-filament::icon icon="/images/icons/bulb.svg" class="h-4 w-4 text-warning-500" />
                <span>{{ __('Setiap langkah terhubung langsung ke fitur manajemen terkait pada admin panel.') }}</span>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
