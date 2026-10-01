<x-filament-panels::page>
    <x-filament::section
        :heading="__('Unduh Semua Data Aplikasi')"
        :description="__('Klik tombol Unduh Data, lalu aktif/nonaktifkan toggle per dataset. Dataset yang aktif digabung ke satu file ZIP berisi satu XLSX per dataset.')"
        icon="heroicon-o-arrow-down-tray"
        icon-color="primary"
    >
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @php($counts = \App\Services\DataExportService\DataExportService::counts())
            @foreach (\App\Services\DataExportService\DataExportService::datasets() as $key => $dataset)
                @php($count = $counts[$key] ?? 0)
                <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 px-3 py-2 dark:border-white/10">
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ $dataset['label'] }}</span>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ number_format($count) }}</span>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-panels::page>
