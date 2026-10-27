<x-filament-panels::page>
    {{-- ═══════════════════ Formulir Input Perintah ═══════════════════ --}}
    <div>
        {{ $this->form }}
    </div>

    {{-- ═══════════════════ Tombol Aksi ═══════════════════ --}}
    <div class="flex flex-wrap items-center gap-3">
        <x-filament::button
            wire:click="executeCommand"
            icon="/images/icons/play.svg"
            color="success"
            size="lg"
        >
            {{ __('Eksekusi Perintah') }}
        </x-filament::button>

        @if ($hasExecuted)
            <x-filament::button
                wire:click="clearOutput"
                icon="/images/icons/x.svg"
                color="gray"
                outlined
            >
                {{ __('Bersihkan Output') }}
            </x-filament::button>
        @endif
    </div>

    {{-- ═══════════════════ Loading State ═══════════════════ --}}
    <div wire:loading wire:target="executeCommand, rerunFromHistory">
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-950 p-10">
            <div class="flex flex-col items-center justify-center gap-3">
                <x-filament::loading-indicator class="h-8 w-8 text-primary-400" />
                <span class="font-mono text-sm text-gray-400">{{ __('Menjalankan perintah...') }}</span>
            </div>
        </div>
    </div>

    {{-- ═══════════════════ Terminal Output / Empty State ═══════════════════ --}}
    <div wire:loading.remove wire:target="executeCommand, rerunFromHistory">
        @if ($hasExecuted)
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-lg">
                {{-- ─── Terminal Header Bar ─── --}}
                <div class="flex items-center gap-2 px-4 py-2.5 bg-gray-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    {{-- Traffic Light Dots --}}
                    <div class="flex gap-1.5">
                        <span class="w-3 h-3 rounded-full bg-red-500 opacity-80"></span>
                        <span class="w-3 h-3 rounded-full bg-yellow-500 opacity-80"></span>
                        <span class="w-3 h-3 rounded-full bg-green-500 opacity-80"></span>
                    </div>
                    <span class="text-xs font-mono text-gray-500 dark:text-gray-400 ml-2 truncate">
                        Terminal — php artisan {{ $lastCommand }}
                    </span>
                    {{-- Status Badge --}}
                    <div class="ml-auto">
                        @if ($exitCode === 0)
                            <span class="inline-flex items-center gap-1 rounded-full bg-green-100 dark:bg-green-900/30 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:text-green-400">
                                ✅ {{ __('Berhasil') }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-red-100 dark:bg-red-900/30 px-2.5 py-0.5 text-xs font-medium text-red-700 dark:text-red-400">
                                ❌ {{ __('Gagal') }}
                            </span>
                        @endif
                    </div>
                </div>

                {{-- ─── Terminal Body ─── --}}
                <div
                    class="bg-gray-950 p-5 font-mono text-sm overflow-auto max-h-[600px] min-h-[120px]"
                    style="scrollbar-width: thin; scrollbar-color: #374151 #111827;"
                >
                    {{-- Prompt (Kali Linux style) --}}
                    <div class="mb-1 select-all">
                        <span class="text-emerald-400">┌──(</span><span class="text-blue-400 font-semibold">admin</span><span class="text-emerald-400">)─[</span><span class="text-yellow-300">~/artisan</span><span class="text-emerald-400">]</span>
                    </div>
                    <div class="mb-4">
                        <span class="text-emerald-400">└─</span><span class="text-red-400 font-semibold">$</span>
                        <span class="text-white ml-1">php artisan {{ $lastCommand }}</span>
                    </div>

                    {{-- Output --}}
                    @if ($commandOutput)
                        <pre class="text-gray-300 whitespace-pre-wrap break-words leading-relaxed">{{ $commandOutput }}</pre>
                    @else
                        <span class="text-gray-600 italic">{{ __('(tidak ada output)') }}</span>
                    @endif

                    {{-- Footer --}}
                    <div class="mt-4 pt-3 border-t border-gray-800 flex items-center justify-between">
                        <span class="text-gray-600 text-xs">
                            {{ __('Kode keluar') }}:
                            <span class="{{ $exitCode === 0 ? 'text-green-500' : 'text-red-500' }} font-semibold">{{ $exitCode }}</span>
                        </span>
                        <span class="text-gray-700 text-xs">
                            {{ now()->format('Y-m-d H:i:s') }}
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- ═══════════════════ Riwayat Perintah ═══════════════════ --}}
    @if (count($commandHistory) > 0)
        <x-filament::section
            :heading="__('Riwayat Perintah') . ' (' . count($commandHistory) . ')'"
            icon="/images/icons/clock.svg"
            collapsible
        >
            <div class="space-y-2">
                @foreach (array_reverse($commandHistory, true) as $realIndex => $entry)
                    <div class="group flex flex-col sm:flex-row sm:items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 hover:border-primary-300 dark:hover:border-primary-700 transition-colors gap-2 sm:gap-3">
                        {{-- Command Info --}}
                        <div class="flex items-center gap-3 font-mono text-sm min-w-0 flex-1">
                            @if ($entry['success'])
                                <span class="shrink-0 text-lg">✅</span>
                            @else
                                <span class="shrink-0 text-lg">❌</span>
                            @endif
                            <div class="min-w-0">
                                <code class="block truncate text-gray-700 dark:text-gray-300">
                                    php artisan {{ $entry['command'] }}
                                </code>
                                <span class="text-xs text-gray-400">
                                    {{ __('Kode keluar') }}: {{ $entry['exit_code'] }}
                                </span>
                            </div>
                        </div>
                        {{-- Actions --}}
                        <div class="flex items-center gap-3 shrink-0 pl-9 sm:pl-0">
                            <span class="text-xs text-gray-500 dark:text-gray-400 tabular-nums">
                                {{ $entry['timestamp'] }}
                            </span>
                            <x-filament::button
                                size="xs"
                                color="gray"
                                wire:click="rerunFromHistory({{ $realIndex }})"
                            >
                                🔁 {{ __('Ulang') }}
                            </x-filament::button>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
