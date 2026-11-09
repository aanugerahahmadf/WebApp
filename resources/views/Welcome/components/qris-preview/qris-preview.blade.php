@php
    /*
     * Preview QRIS di form metode pembayaran.
     *
     * Gambarnya satu: public/images/qris/qris.jpg lewat QrisImage (bukan
     * kolom `qris_image` / `image_url` yang selalu NULL, yang dulu membuat
     * komponen ini hanya menampilkan badge "Gambar QRIS tidak tersedia").
     *
     * NOMINAL diteruskan kalau pemanggil menyediakannya. QRIS toko ini
     * statis: pembeli memindai lalu mengetik sendiri nominalnya, jadi angka
     * di layar harus sama dengan `transactions.total_amount` yang dicocokkan
     * webhook BRI.
     */
    $method = \App\Models\PaymentMethod\PaymentMethod::find($get('payment_method_id') ?? null);
@endphp

@if ($method?->qris_image_url)
    @include('Shared.components.qris-card.qris-card', [
        'method' => $method,
        'amount' => $amount ?? null,
        'deadline' => $deadline ?? null,
    ])
@else
    <x-filament::badge color="danger">
        {{ __('Gambar QRIS tidak tersedia.') }}
    </x-filament::badge>
@endif
