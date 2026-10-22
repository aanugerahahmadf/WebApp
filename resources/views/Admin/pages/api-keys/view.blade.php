<div class="space-y-3 text-sm">
    <div class="flex items-start gap-2">
        <span class="font-semibold min-w-[120px]">{{ __('Nama') }}</span>
        <span class="flex-1">{{ $record->name }}</span>
    </div>
    <div class="flex items-start gap-2">
        <span class="font-semibold min-w-[120px]">{{ __('Target Sistem') }}</span>
        <span class="flex-1">{{ class_basename($record->tokenable_type) }}</span>
    </div>
    <div class="flex items-start gap-2">
        <span class="font-semibold min-w-[120px]">{{ __('Hak Akses') }}</span>
        <span class="flex-1 flex flex-wrap gap-1">
            @foreach ((array) ($record->abilities ?? []) as $ability)
                <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200">{{ $ability }}</span>
            @endforeach
        </span>
    </div>
    <div class="flex items-start gap-2">
        <span class="font-semibold min-w-[120px]">{{ __('Dibuat Pada') }}</span>
        <span class="flex-1">{{ $record->created_at?->format('d/m/Y H:i') ?? '-' }}</span>
    </div>
    <div class="flex items-start gap-2">
        <span class="font-semibold min-w-[120px]">{{ __('Terakhir Digunakan') }}</span>
        <span class="flex-1">{{ $record->last_used_at?->diffForHumans() ?? __('Belum pernah') }}</span>
    </div>
    <div class="flex items-start gap-2">
        <span class="font-semibold min-w-[120px]">{{ __('Kedaluwarsa') }}</span>
        <span class="flex-1">{{ $record->expires_at?->format('d/m/Y H:i') ?? __('Tanpa batas') }}</span>
    </div>
    <p class="pt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('Token rahasia tidak ditampilkan lagi setelah dibuat. Jika hilang, buat API Key baru.') }}</p>
</div>
