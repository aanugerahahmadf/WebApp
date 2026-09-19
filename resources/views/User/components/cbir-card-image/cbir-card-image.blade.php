@props([
    'imageUrl' => null,
    'name'     => '',
])

<img
    src="{{ $imageUrl ? asset('storage/' . $imageUrl) : asset('images/placeholder.png') }}"
    alt="{{ $name }}"
    class="w-16 h-16 rounded-xl object-cover shadow-md"
/>
