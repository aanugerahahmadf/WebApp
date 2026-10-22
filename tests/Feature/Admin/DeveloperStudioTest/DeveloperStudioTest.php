<?php

use App\Filament\Admin\Resources\DeveloperStudioResource\DeveloperStudioResource;
use App\Filament\Admin\Resources\DeveloperStudioResource\Pages\ListDeveloperStudio\ListDeveloperStudio;
use App\Models\Admin\Admin;
use App\Models\DeveloperStudio\DeveloperStudioEndpoint;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();

    $this->admin = Admin::factory()->create();
    $this->admin->assignRole('super_admin');

    $this->actingAs($this->admin, 'admin');

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->routeEndpoint = DeveloperStudioEndpoint::create([
        'type' => 'api',
        'method' => 'GET',
        'uri' => 'api/contoh',
        'name' => 'contoh.index',
        'action' => 'App\\Http\\Controllers\\Api\\ContohController@index',
        'controller_class' => 'App\\Http\\Controllers\\Api\\ContohController',
        'middleware' => ['api', 'auth:sanctum'],
        'domain' => null,
        'file_path' => null,
        'description' => 'Endpoint contoh untuk pengujian.',
        'status' => 'active',
    ]);

    DeveloperStudioEndpoint::create([
        'type' => 'graphql',
        'method' => 'QUERY',
        'uri' => 'graphql.query.contoh',
        'name' => 'contohQuery',
        'action' => 'ContohQuery',
        'controller_class' => null,
        'middleware' => null,
        'domain' => null,
        'file_path' => null,
        'description' => null,
        'status' => 'active',
    ]);
});

it('menampilkan tab per tipe endpoint dengan jumlahnya', function () {
    $component = Livewire::test(ListDeveloperStudio::class);

    $tabs = $component->instance()->getTabs();

    expect($tabs)->toHaveKey('api')
        ->and($tabs)->toHaveKey('graphql')
        ->and($tabs['api']->getBadge())->toBe(1)
        ->and($tabs['graphql']->getBadge())->toBe(1)
        ->and($tabs['web']->getBadge())->toBe(0);
});

it('mempunyai kolom tabel yang lengkap dan bisa dicari', function () {
    Livewire::test(ListDeveloperStudio::class)
        ->assertTableColumnExists('id')
        ->assertTableColumnExists('type')
        ->assertTableColumnExists('method')
        ->assertTableColumnExists('uri')
        ->assertTableColumnExists('name')
        ->assertTableColumnExists('action')
        ->assertTableColumnExists('status')
        ->assertTableColumnExists('updated_at')
        ->assertCanSeeTableRecords([$this->routeEndpoint]);
});

it('mempunyai filter tipe, metode, dan status', function () {
    Livewire::test(ListDeveloperStudio::class)
        ->assertTableFilterExists('type')
        ->assertTableFilterExists('method')
        ->assertTableFilterExists('status');
});

it('menampilkan detail endpoint beserta contoh pemanggilannya', function () {
    $response = $this->get(
        DeveloperStudioResource::getUrl('view', ['record' => $this->routeEndpoint])
    );

    $response->assertOk()
        ->assertSee('contoh.index')
        ->assertSee('api/contoh')
        ->assertSee(__('Detail Endpoint'))
        ->assertSee(__('Contoh Pemanggilan'))
        ->assertSee('Example Calls', false)
        ->assertSee('curl -X GET', false)
        ->assertSee('auth:sanctum');
});

it('memakai host request yang sedang berjalan pada contoh cURL', function () {
    expect($this->routeEndpoint->getCurlCommand())
        ->toContain(url('/'))
        ->toContain('api/contoh');
});

it('membuka aksi edit dengan label yang diterjemahkan', function () {
    Livewire::test(ListDeveloperStudio::class)
        ->mountTableAction('edit', (string) $this->routeEndpoint->getKey())
        ->assertSee(__('Detail Endpoint'))
        ->assertSee(__('Aksi / Handler'))
        ->assertSee(__('Satu middleware per baris.'))
        ->assertDontSee('>action</span>', false);
});

it('menyimpan perubahan endpoint dari aksi edit (middleware per baris)', function () {
    Livewire::test(ListDeveloperStudio::class)
        ->callTableAction('edit', $this->routeEndpoint, data: [
            'type' => 'api',
            'method' => 'GET',
            'uri' => 'api/contoh-baru',
            'name' => 'contoh.index',
            'action' => 'App\\Http\\Controllers\\Api\\ContohController@index',
            'controller_class' => 'App\\Http\\Controllers\\Api\\ContohController',
            'domain' => null,
            'status' => 'active',
            'file_path' => null,
            'description' => 'Endpoint contoh untuk pengujian.',
            'middleware' => "api\nauth:sanctum\nthrottle:60,1",
        ]);

    $fresh = $this->routeEndpoint->fresh();

    expect($fresh->uri)->toBe('api/contoh-baru')
        ->and($fresh->middleware)->toBe(['api', 'auth:sanctum', 'throttle:60,1']);
});
