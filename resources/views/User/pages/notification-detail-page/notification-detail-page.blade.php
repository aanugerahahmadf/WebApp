<x-filament-panels::page>
    <div class="mx-auto max-w-2xl">
        <x-filament::section
            :heading="$notificationDetail['title']"
            :description="__('Diterima pada :time', ['time' => $notificationDetail['received_at']])"
            :icon="$notificationDetail['icon']"
            icon-color="warning"
        >
            <p>{{ $notificationDetail['body'] }}</p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
