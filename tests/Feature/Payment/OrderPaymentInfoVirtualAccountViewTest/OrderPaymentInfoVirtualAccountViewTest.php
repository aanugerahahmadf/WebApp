<?php

use App\Models\Order\Order;
use App\Models\PaymentMethod\PaymentMethod;
use App\Models\Transaction\Transaction;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Blade;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/*
|--------------------------------------------------------------------------
| View informasi pembayaran - Virtual Account BRI
|--------------------------------------------------------------------------
|
| View ini yang dipakai user untuk tahu "ke mana saya transfer". Salah nomor
| di sini berarti uang hilang, jadi yang diuji adalah nomor yang benar-benar
| tampil.
|
|   1. BRIVA harus menampilkan nomor VA milik transaksi, bukan rekening statis
|      `payment_methods.account_number` (= rekening admin).
|   2. "Atas Nama" harus nama pemesan.
|   3. Kalau VA gagal dibuat, halaman harus bilang terus -- bukan diam saja
|      sambil menampilkan rekening admin seolah-olah itu tujuan transfer.
|
*/

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('user'));

    $this->user = User::factory()->create(['full_name' => 'Rangga Wicaksono']);
    $this->user->assignRole(Role::firstOrCreate(['name' => 'customer']));
    actingAs($this->user, 'web');
});

afterEach(function () {
    Filament::setCurrentPanel(null);
});

/**
 * Render view payment-info untuk sebuah order.
 *
 * View aslinya dipanggil sebagai komponen Filament, jadi `$getRecord` disuplai
 * lewat closure. `$paymentStatus` juga datang dari parent (dipakai untuk
 * blok hitung mundur) dan harus disuplai di sini.
 */
function renderPaymentInfo(Order $order): string
{
    return (string) Blade::render(
        (string) file_get_contents(base_path('resources/views/User/components/order-payment-info/order-payment-info.blade.php')),
        [
            'getRecord' => fn () => $order,
            'paymentStatus' => true,
        ],
    );
}

function brivaMethod(): PaymentMethod
{
    return PaymentMethod::create([
        'name' => 'Virtual Account BRI',
        'type' => 'virtual_account',
        'code' => 'bri_va',
        'bank_name' => 'BRI',
        'account_number' => '421201032041536',
        'account_holder' => 'Anugerah Ahmad Fachrurochim',
        'instructions' => 'Transfer ke nomor Virtual Account di atas.',
        'fee' => 0,
        'is_active' => true,
    ]);
}

function manualTransferMethod(): PaymentMethod
{
    return PaymentMethod::create([
        'name' => 'Transfer Bank Manual',
        'type' => 'bank_transfer',
        'code' => 'manual_transfer',
        'bank_name' => 'BRI',
        'account_number' => '421201032041536',
        'account_holder' => 'Anugerah Ahmad Fachrurochim',
        'fee' => 0,
        'is_active' => true,
    ]);
}

function orderWithTransaction(User $user, PaymentMethod $pm, array $transactionOverrides = []): Order
{
    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => 'ORD-VA-'.uniqid(),
        'total_price' => 1000000,
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'booking_date' => now()->addDays(7),
    ]);

    Transaction::create(array_merge([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'type' => 'order',
        'reference_number' => 'TRX-VA-'.uniqid(),
        'amount' => 1000000,
        'total_amount' => 1000000,
        'status' => 'pending',
        'payment_method_id' => $pm->id,
        'payment_method' => $pm->name,
        'payment_gateway' => $pm->type,
    ], $transactionOverrides));

    return $order->fresh();
}

test('BRIVA menampilkan nomor Virtual Account milik transaksi', function () {
    $order = orderWithTransaction($this->user, brivaMethod(), [
        'virtual_account_no' => '880800000009876',
        'virtual_account_expiry' => now()->addDay(),
        'metadata' => [
            'bri_va' => [
                'virtualAccountData' => ['virtualAccountName' => 'Rangga Wicaksono'],
            ],
        ],
    ]);

    $html = renderPaymentInfo($order);

    expect($html)->toContain('880800000009876')
        // Rekening admin TIDAK boleh muncul sebagai tujuan transfer BRIVA.
        ->and($html)->not->toContain('421201032041536');
});

test('BRIVA menampilkan nama pemesan sebagai Atas Nama', function () {
    $order = orderWithTransaction($this->user, brivaMethod(), [
        'virtual_account_no' => '880800000009876',
        'metadata' => [
            'bri_va' => [
                'virtualAccountData' => ['virtualAccountName' => 'Rangga Wicaksono'],
            ],
        ],
    ]);

    $html = renderPaymentInfo($order);

    expect($html)->toContain('Rangga Wicaksono')
        // Nama merchant tidak boleh jadi "Atas Nama" untuk BRIVA.
        ->and($html)->not->toContain('Anugerah Ahmad Fachrurochim');
});

test('BRIVA tanpa nomor Virtual Account memberi tahu, bukan diam', function () {
    // VA gagal dibuat, jadi nomornya kosong.
    $order = orderWithTransaction($this->user, brivaMethod());

    $html = renderPaymentInfo($order);

    expect($html)->not->toContain('421201032041536');
});

test('transfer manual tetap menampilkan rekening statis', function () {
    $order = orderWithTransaction($this->user, manualTransferMethod(), [
        // VA nyasar sengaja: untuk transfer manual rekening statis yang benar.
        'virtual_account_no' => '880800000009876',
    ]);

    $html = renderPaymentInfo($order);

    expect($html)->toContain('421201032041536')
        ->and($html)->toContain('Anugerah Ahmad Fachrurochim')
        ->and($html)->not->toContain('880800000009876');
});
