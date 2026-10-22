<?php

use App\Filament\User\Resources\CatalogResource\Pages\ViewCatalog\ViewCatalog;
use App\Jobs\SendBotReply\SendBotReply;
use App\Models\Inbox\Inbox;
use App\Models\Message\Message;
use App\Models\Catalog\Catalog;
use App\Models\Review\Review;
use App\Models\User\User;
use App\Services\ChatService\ChatService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    // `super_admin` juga dibuat di guard `web`: user di bawah adalah
    // `App\Models\User\User`, dan `assignRole()` mencari role di guard model itu
    // sendiri. Role versi `admin` milik tabel `admins` tetap dibuat juga.
    seedRoles(adminRoles: ['super_admin'], webRoles: ['super_admin', 'customer']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');

    $this->customer = User::factory()->create();
    $this->customer->assignRole('customer');

    Filament::setCurrentPanel(Filament::getPanel('user'));

    Queue::fake();
});

/*
 * Test "renders the package detail page ..." DIHAPUS.
 *
 *
 * Yang hilang bukan aturan yang diuji: Bagikan, Lapor, DISKON, Fitur, Ulasan,
 * dan nama reviewer semuanya diperiksa ulang oleh test katalog tepat di bawahnya
 * dengan record `Catalog`. Menyalinnya ke "tier paket" hanya menambah duplikat
 * yang menguji halaman katalog sambil menyebut paket.
 *
 * Kalau halaman detail paket dihidupkan lagi, `Package` sekarang adalah TIER
 * dari katalog (`Catalog::tiers()`), jadi tidak ada resource terpisah yang bisa
 * menyalin struktur `ViewCatalog`. Test perlu ditulis ulang sesuai route tier
 * yang benar-benar dibuat.
 */

it('renders the catalog detail page with the same parity blocks', function () {
    actingAs($this->customer);

    // `name_translations`, bukan `name`: accessor `Catalog::getNameAttribute()`
    // membaca translations lebih dulu, jadi override kolom `name` tidak
    // terlihat di halaman. Lihat catatan di `CatalogFactory`.
    $catalog = Catalog::factory()->create([
        'features'          => ['Buket segar', 'Dibuat custom'],
        'discount_price'    => 100000,
        'name_translations' => ['id' => 'Produk Bunga Mewah', 'en' => 'Luxury Flower Product'],
    ]);

    $reviewer = User::factory()->create(['full_name' => 'Budi Santoso']);
    Review::create([
        'user_id' => $reviewer->id,
        'package_id' => null,
        'catalog_id' => $catalog->id,
        'rating' => 4,
        'comment' => 'Bagus.',
    ]);

    Livewire::test(ViewCatalog::class, ['record' => $catalog->id])
        ->assertSuccessful()
        ->assertSee('Produk Bunga Mewah')
        ->assertSee('Bagikan')
        ->assertSee('Lapor')
        ->assertSee('DISKON')
        ->assertSee('Fitur')
        ->assertSee('Buket segar')
        ->assertSee('Ulasan')
        ->assertSee('Budi Santoso');
});

it('sends a libs report message through the chat service', function () {
    Queue::fake();
    actingAs($this->customer);

    $inbox = Inbox::create(['user_ids' => [$this->customer->id, $this->admin->id]]);

    $message = app(ChatService::class)->sendReportMessage(
        inbox: $inbox,
        category: 'package',
        itemName: 'Paket Bunga Mewah',
        meta: [
            'type' => 'package',
            'id' => 99,
            'name' => 'Paket Bunga Mewah',
            'is_report' => true,
        ],
    );

    expect($message)->toBeInstanceOf(Message::class);
    expect($message->meta['is_report'])->toBeTrue();
    expect($message->meta['type'])->toBe('package');
    expect($message->message)->toContain('Saya ingin melaporkan ini');
    expect($message->message)->toContain('Jenis: Paket');
    expect($message->message)->toContain('Mohon bantu tindak lanjuti laporan ini, terima kasih.');

    $inbox->refresh();
    expect($inbox->meta['cs_category'])->toBe('bug_report');

    Queue::assertPushed(SendBotReply::class);
});

it('maps order-related report categories to the order help channel', function () {
    Queue::fake();
    actingAs($this->customer);

    $inbox = Inbox::create(['user_ids' => [$this->customer->id, $this->admin->id]]);

    app(ChatService::class)->sendReportMessage(
        inbox: $inbox,
        category: 'order',
        itemName: 'Pesanan #123',
    );

    $inbox->refresh();
    expect($inbox->meta['cs_category'])->toBe('order_help');
});
