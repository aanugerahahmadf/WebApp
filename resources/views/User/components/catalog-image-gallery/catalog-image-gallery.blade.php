@php
    $record = $getRecord();
    $images = $record->getMedia($mediaCollection)
        ->filter(fn ($media) => str_starts_with((string) $media->mime_type, 'image/'))
        ->map(fn ($media) => $media->getUrl())
        ->values();

    if ($images->isEmpty()) {
        $images->push($record->image_url);
    }
@endphp

<div
    x-data="{
        active: 0,
        images: @js($images->all()),
        itemName: @js($record->name),
        photoLabel: @js(__('Foto')),
        forLabel: @js(__('untuk')),
        choosePhotoLabel: @js(__('Pilih foto')),
        thumbnailLabel: @js(__('Thumbnail foto')),
    }"
    class="space-y-3"
>
    <div class="relative flex h-80 items-center justify-center overflow-hidden rounded-3xl border border-white/10 bg-white/5 shadow-inner sm:h-96">
        <img
            x-bind:src="images[active]"
            x-bind:alt="photoLabel + ' ' + (active + 1) + ' ' + forLabel + ' ' + itemName"
            class="h-full w-full object-contain p-2 transition duration-300"
        >

        <div x-show="images.length > 1" class="absolute right-3 top-3 rounded-full bg-black/60 px-3 py-1 text-xs font-semibold text-white" x-text="(active + 1) + ' / ' + images.length"></div>

        <button type="button" x-show="images.length > 1" x-on:click="active = (active - 1 + images.length) % images.length" class="absolute left-3 rounded-full bg-black/50 p-2 text-white transition hover:bg-black/70" aria-label="{{ __('Foto sebelumnya') }}">
            <x-heroicon-m-chevron-left class="h-5 w-5" />
        </button>
        <button type="button" x-show="images.length > 1" x-on:click="active = (active + 1) % images.length" class="absolute right-3 rounded-full bg-black/50 p-2 text-white transition hover:bg-black/70" aria-label="{{ __('Foto berikutnya') }}">
            <x-heroicon-m-chevron-right class="h-5 w-5" />
        </button>
    </div>

    <div x-show="images.length > 1" class="flex gap-2 overflow-x-auto pb-1">
        <template x-for="(image, index) in images" x-bind:key="image">
            <button type="button" x-on:click="active = index" x-bind:class="active === index ? 'ring-2 ring-primary-500' : 'opacity-70 hover:opacity-100'" class="h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-white/5 transition" x-bind:aria-label="choosePhotoLabel + ' ' + (index + 1)">
                <img x-bind:src="image" x-bind:alt="thumbnailLabel + ' ' + (index + 1)" class="h-full w-full object-cover">
            </button>
        </template>
    </div>
</div>
