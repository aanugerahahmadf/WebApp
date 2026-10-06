<?php

use App\Filament\User\Resources\PackageResource\Pages\ViewPackage\ViewPackage;
use App\Filament\User\Resources\ProductResource\Pages\ViewProduct\ViewProduct;
use App\Jobs\SendBotReply\SendBotReply;
use App\Models\Inbox\Inbox;
use App\Models\Message\Message;
use App\Models\Package\Package;
use App\Models\Product\Product;
use App\Models\Review\Review;
use App\Models\User\User;
use App\Services\ChatService\ChatService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin']);
    Role::firstOrCreate(['name' => 'customer']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');

    $this->customer = User::factory()->create();
    $this->customer->assignRole('customer');

    Filament::setCurrentPanel(Filament::getPanel('user'));

    Queue::fake();
});

it('renders the package detail page with share, report, rating, discount, features, and reviews', function () {
    actingAs($this->customer);

    $package = Package::factory()->create([
        'name' => 'Paket Bunga Mewah',
        'features' => ['Podium mewah', 'Kursi tamu', 'Dekorasi panggung'],
        'discount_price' => 500000,
    ]);

    $reviewer = User::factory()->create(['full_name' => 'Siti Aminah']);
    Review::create([
        'user_id' => $reviewer->id,
        'package_id' => $package->id,
        'product_id' => null,
        'rating' => 5,
        'comment' => 'Sangat memuaskan!',
    ]);

    Livewire::test(ViewPackage::class, ['record' => $package->id])
        ->assertSuccessful()
        ->assertSee('Paket Bunga Mewah')
        ->assertSee('Bagikan')
        ->assertSee('Lapor')
        ->assertSee('DISKON')
        ->assertSee('Fitur')
        ->assertSee('Podium mewah')
        ->assertSee('Ulasan')
        ->assertSee('Siti Aminah');
});

it('renders the product detail page with the same parity blocks', function () {
    actingAs($this->customer);

    $product = Product::factory()->create([
        'name' => 'Produk Bunga Mewah',
        'features' => ['Buket segar', 'Dibuat custom'],
        'discount_price' => 100000,
    ]);

    $reviewer = User::factory()->create(['full_name' => 'Budi Santoso']);
    Review::create([
        'user_id' => $reviewer->id,
        'package_id' => null,
        'product_id' => $product->id,
        'rating' => 4,
        'comment' => 'Bagus.',
    ]);

    Livewire::test(ViewProduct::class, ['record' => $product->id])
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