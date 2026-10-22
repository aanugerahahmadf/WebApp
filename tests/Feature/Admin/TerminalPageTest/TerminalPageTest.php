<?php

namespace Tests\Feature\Admin\TerminalPageTest;

use App\Filament\Admin\Pages\TerminalPage\TerminalPage;
use App\Models\Admin\Admin;
use App\Support\Terminal\ArtisanCommandInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TerminalPageTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->superAdmin = Admin::factory()->create([
            'email'         => 'superadmin.terminal@test.local',
            'active_status' => true,
        ]);
        $this->superAdmin->assignRole('super_admin');

        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
    }

    /* ───────────────── Page Access ───────────────── */

    public function test_super_admin_can_access_terminal_page(): void
    {
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->get('/admin/terminal');

        $response->assertSuccessful();
    }

    public function test_unauthenticated_user_cannot_access_terminal(): void
    {
        $response = $this->get('/admin/terminal');

        $response->assertRedirect();
    }

    /* ───────────────── Form Rendering ───────────────── */

    public function test_terminal_page_renders_form_fields(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(TerminalPage::class)
            ->assertFormExists()
            ->assertFormFieldExists('quick_command')
            ->assertFormFieldExists('command');
    }

    /* ───────────────── Command Execution ───────────────── */

    public function test_can_execute_artisan_command_successfully(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(TerminalPage::class)
            ->fillForm(['command' => 'migrate:status'])
            ->call('executeCommand')
            ->assertSet('hasExecuted', true)
            ->assertSet('exitCode', 0)
            ->assertSet('lastCommand', 'migrate:status');
    }

    public function test_command_output_is_captured(): void
    {
        $component = Livewire::actingAs($this->superAdmin, 'admin')
            ->test(TerminalPage::class)
            ->fillForm(['command' => 'about'])
            ->call('executeCommand');

        // 'about' selalu menghasilkan output
        $this->assertNotEmpty($component->get('commandOutput'));
    }

    /* ───────────────── Blocked Commands ───────────────── */

    public function test_blocked_command_is_rejected(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(TerminalPage::class)
            ->fillForm(['command' => 'tinker'])
            ->call('executeCommand')
            ->assertSet('hasExecuted', false);
    }

    public function test_serve_command_is_blocked(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(TerminalPage::class)
            ->fillForm(['command' => 'serve'])
            ->call('executeCommand')
            ->assertSet('hasExecuted', false);
    }

    public function test_env_command_is_blocked(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(TerminalPage::class)
            ->fillForm(['command' => 'env'])
            ->call('executeCommand')
            ->assertSet('hasExecuted', false);
    }

    public function test_key_generate_command_is_blocked(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(TerminalPage::class)
            ->fillForm(['command' => 'key:generate'])
            ->call('executeCommand')
            ->assertSet('hasExecuted', false);
    }

    /* ───────────────── Command History ───────────────── */

    public function test_command_history_is_tracked(): void
    {
        $component = Livewire::actingAs($this->superAdmin, 'admin')
            ->test(TerminalPage::class)
            ->fillForm(['command' => 'migrate:status'])
            ->call('executeCommand');

        $history = $component->get('commandHistory');

        $this->assertCount(1, $history);
        $this->assertEquals('migrate:status', $history[0]['command']);
        $this->assertTrue($history[0]['success']);
        $this->assertArrayHasKey('timestamp', $history[0]);
    }

    public function test_multiple_commands_build_history(): void
    {
        $component = Livewire::actingAs($this->superAdmin, 'admin')
            ->test(TerminalPage::class)
            ->fillForm(['command' => 'migrate:status'])
            ->call('executeCommand')
            ->fillForm(['command' => 'about'])
            ->call('executeCommand');

        $this->assertCount(2, $component->get('commandHistory'));
    }

    /* ───────────────── Rerun from History ───────────────── */

    public function test_can_rerun_command_from_history(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(TerminalPage::class)
            ->fillForm(['command' => 'about'])
            ->call('executeCommand')
            ->call('rerunFromHistory', 0)
            ->assertSet('lastCommand', 'about')
            ->assertSet('hasExecuted', true);
    }

    public function test_rerun_invalid_index_does_nothing(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(TerminalPage::class)
            ->fillForm(['command' => 'about'])
            ->call('executeCommand')
            ->call('rerunFromHistory', 999)
            ->assertSet('lastCommand', 'about'); // unchanged
    }

    /* ───────────────── Clear Output & History ───────────────── */

    public function test_can_clear_output(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(TerminalPage::class)
            ->fillForm(['command' => 'migrate:status'])
            ->call('executeCommand')
            ->assertSet('hasExecuted', true)
            ->call('clearOutput')
            ->assertSet('hasExecuted', false)
            ->assertSet('commandOutput', '')
            ->assertSet('lastCommand', '');
    }

    public function test_can_clear_history(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(TerminalPage::class)
            ->fillForm(['command' => 'migrate:status'])
            ->call('executeCommand')
            ->call('clearHistory')
            ->assertSet('commandHistory', []);
    }

    /* ───────────────── Error Handling ───────────────── */

    public function test_invalid_command_is_handled_gracefully(): void
    {
        $component = Livewire::actingAs($this->superAdmin, 'admin')
            ->test(TerminalPage::class)
            ->fillForm(['command' => 'nonexistent:command:that:does:not:exist'])
            ->call('executeCommand');

        // Harus tetap hasExecuted = true (error ditangkap, bukan crash)
        $this->assertTrue($component->get('hasExecuted'));
        $this->assertNotEmpty($component->get('commandOutput'));
    }

    /* ───────────────── Normalisasi Prefiks Shell ───────────────── */

    /**
     * Admin yang terbiasa di terminal sungguhan mengetik `php artisan migrate`.
     * Itu harus tetap jalan, bukan error "command php does not exist".
     */
    public function test_php_artisan_prefix_is_stripped(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(TerminalPage::class)
            ->fillForm(['command' => 'php artisan migrate:status'])
            ->call('executeCommand')
            ->assertSet('lastCommand', 'migrate:status')
            ->assertSet('exitCode', 0);
    }

    public function test_artisan_prefix_is_stripped(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(TerminalPage::class)
            ->fillForm(['command' => 'artisan about'])
            ->call('executeCommand')
            ->assertSet('lastCommand', 'about');
    }

    public function test_prefix_with_arguments_is_preserved(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(TerminalPage::class)
            ->fillForm(['command' => 'php artisan migrate:status --database=sqlite'])
            ->call('executeCommand')
            ->assertSet('lastCommand', 'migrate:status --database=sqlite');
    }

    public function test_whitespace_excess_is_normalized(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(TerminalPage::class)
            ->fillForm(['command' => '  php   artisan    about  '])
            ->call('executeCommand')
            ->assertSet('lastCommand', 'about');
    }

    public function test_blocked_command_is_blocked_even_with_prefix(): void
    {
        // `php artisan tinker` harus tetap diblokir -- kalau normalisasi
        // gagal, prefiks ini akan lolos dari blacklist.
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(TerminalPage::class)
            ->fillForm(['command' => 'php artisan tinker'])
            ->call('executeCommand')
            ->assertSet('hasExecuted', false);
    }

    public function test_prefix_only_input_is_rejected_as_empty(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(TerminalPage::class)
            ->fillForm(['command' => 'php artisan'])
            ->call('executeCommand')
            ->assertSet('hasExecuted', false)
            ->assertSet('lastCommand', '');
    }

    /* ───────────────── Unit Test Helper Normalisasi ───────────────── */

    public function test_normalize_helper(): void
    {
        // Prefix + argumen
        $this->assertSame('migrate', ArtisanCommandInput::normalize('php artisan migrate'));
        $this->assertSame('migrate', ArtisanCommandInput::normalize('artisan migrate'));
        $this->assertSame('migrate', ArtisanCommandInput::normalize('./artisan migrate'));
        $this->assertSame('migrate', ArtisanCommandInput::normalize('php8.3 artisan migrate'));
        $this->assertSame('migrate', ArtisanCommandInput::normalize('php artisan artisan migrate'));

        // Tanpa prefix: harus utuh
        $this->assertSame('migrate:status', ArtisanCommandInput::normalize('migrate:status'));

        // Argumen tidak boleh dirusak
        $this->assertSame(
            'migrate:status --database=mysql',
            ArtisanCommandInput::normalize('php artisan migrate:status --database=mysql'),
        );
        $this->assertSame(
            'migrate --path="database/migrations"',
            ArtisanCommandInput::normalize('php artisan migrate --path="database/migrations"'),
        );

        // Whitespace berlebih
        $this->assertSame('about', ArtisanCommandInput::normalize('   about   '));
        $this->assertSame('a b', ArtisanCommandInput::normalize("  a\t\tb  "));

        // Kosong
        $this->assertSame('', ArtisanCommandInput::normalize(''));
        $this->assertSame('', ArtisanCommandInput::normalize('    '));
        $this->assertSame('', ArtisanCommandInput::normalize('php artisan'));
        $this->assertSame('', ArtisanCommandInput::normalize('artisan'));

        // Nama command untuk blacklist
        $this->assertSame('tinker', ArtisanCommandInput::commandName('php artisan tinker'));
        $this->assertSame('migrate', ArtisanCommandInput::commandName('php artisan migrate --force'));
    }
}
