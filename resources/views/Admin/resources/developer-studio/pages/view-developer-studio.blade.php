@php
    $record = $this->getRecord();
    $typeClass = \App\Filament\Admin\Resources\DeveloperStudioResource\DeveloperStudioResource::class;

    $middleware = is_array($record->middleware) ? implode(', ', $record->middleware) : (string) $record->middleware;

    $fileExists = $record->file_path && \Illuminate\Support\Facades\File::isFile($record->file_path);
    $fileContent = $fileExists ? \Illuminate\Support\Facades\File::get($record->file_path) : null;

    $hasHttpSnippet = in_array($record->type, ['api', 'web', 'admin'], true);

    // Status diterjemahkan di sini. Sebelum ini view menulis `ucfirst($status)`
    // mentah, jadi panel Bahasa Indonesia menampilkan "Active"/"Inactive".
    $statusLabel = match ($record->status) {
        'active' => __('Aktif'),
        'inactive' => __('Tidak Aktif'),
        null, '' => '-',
        default => $record->status,
    };

    $snippets = [
        'cURL' => $record->getCurlCommand(),
    ];

    if ($hasHttpSnippet) {
        $snippets['Flutter (Dart)'] = $record->getFlutterSnippet();
        $snippets['Capacitor (TypeScript)'] = $record->getCapacitorSnippet();
    }

    if ($record->type === 'python') {
        $snippets['Python (AI Core)'] = $record->getPythonSnippet();
    }
@endphp

<x-filament-panels::page>
    <div class="space-y-6">

        {{-- RINGKASAN ------------------------------------------------------ --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ $record->name ?: $record->uri }}
            </x-slot>

            <x-slot name="description">
                {{ $record->description ?: __('Tidak ada deskripsi untuk endpoint ini.') }}
            </x-slot>

            <div class="flex flex-wrap items-center gap-2">
                <x-filament::badge :color="$typeClass::typeColor($record->type)">
                    {{ $typeClass::typeLabel($record->type) }}
                </x-filament::badge>

                <x-filament::badge color="gray">
                    {{ $record->method ?: '-' }}
                </x-filament::badge>

                <x-filament::badge :color="$record->status === 'active' ? 'success' : 'gray'">
                    {{ $statusLabel }}
                </x-filament::badge>

                <span class="font-mono text-sm text-gray-700 dark:text-gray-200">
                    {{ $record->uri }}
                </span>
            </div>
        </x-filament::section>

        {{-- DETAIL --------------------------------------------------------- --}}
        <x-filament::section
            :heading="__('Detail Endpoint')"
            :description="__('Seluruh informasi yang tercatat untuk endpoint ini.')"
        >
            <dl class="grid grid-cols-1 gap-x-8 gap-y-4 md:grid-cols-2">
                @foreach ([
                    __('Nama') => $record->name ?: '-',
                    __('Tipe') => $typeClass::typeLabel($record->type),
                    __('Metode') => $record->method ?: '-',
                    __('Status') => $statusLabel,
                    __('URI / Alamat') => $record->uri ?: '-',
                    __('Domain') => $record->domain ?: '-',
                    __('Aksi / Handler') => $record->action ?: '-',
                    __('Controller') => $record->controller_class ?: '-',
                    __('Middleware') => $middleware ?: '-',
                    __('Berkas') => $record->file_path ?: '-',
                    __('Ukuran Berkas') => $record->getFileSize(),
                    __('Terakhir Diperbarui') => $record->updated_at?->format('d/m/Y H:i') ?? '-',
                ] as $label => $value)
                    <div class="min-w-0">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ $label }}
                        </dt>
                        <dd class="mt-1 break-words text-sm text-gray-900 dark:text-gray-100">
                            {{ $value }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        </x-filament::section>

        {{-- CONTOH PEMANGGILAN -------------------------------------------- --}}
        <x-filament::section
            :heading="__('Contoh Pemanggilan')"
            :description="__('Salin salah satu contoh di bawah untuk memanggil endpoint ini dari aplikasi lain.')"
        >
            <div class="space-y-6">
                @foreach ($snippets as $snippetTitle => $snippetCode)
                    <div
                        x-data="{ copied: false }"
                        class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10"
                    >
                        <div class="flex items-center justify-between gap-3 bg-gray-50 px-4 py-2 dark:bg-white/5">
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                {{ $snippetTitle }}
                            </span>

                            <x-filament::button
                                size="xs"
                                color="gray"
                                icon="/images/icons/clipboard.svg"
                                x-on:click="
                                    window.navigator.clipboard.writeText($refs.code.innerText);
                                    copied = true;
                                    setTimeout(() => copied = false, 2000);
                                "
                            >
                                <span x-show="! copied">{{ __('Salin') }}</span>
                                <span x-show="copied" x-cloak>{{ __('Tersalin!') }}</span>
                            </x-filament::button>
                        </div>

                        <pre
                            x-ref="code"
                            class="max-h-96 overflow-auto bg-gray-950 p-4 text-xs leading-relaxed text-gray-100"
                        >{{ $snippetCode }}</pre>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        {{-- ISI BERKAS ---------------------------------------------------- --}}
        @if ($fileExists)
            <x-filament::section
                :heading="__('Isi Berkas')"
                :description="$record->file_path"
                collapsible
                collapsed
            >
                <pre class="max-h-[32rem] overflow-auto rounded-xl bg-gray-950 p-4 text-xs leading-relaxed text-gray-100">{{ $fileContent }}</pre>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
