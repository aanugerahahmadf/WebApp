<?php

namespace App\Filament\Welcome\Resources\CatalogResource\Pages\CheckoutCatalog;

use App\Filament\Welcome\Pages\Home\Home;
use App\Filament\Welcome\Resources\CatalogResource\CatalogResource;
use App\Http\Middleware\AuthenticateWelcome\AuthenticateWelcome;
use Filament\Facades\Filament;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\HtmlString;

class CheckoutCatalog extends Page implements HasForms
{
    use InteractsWithForms;
    use InteractsWithRecord;

    protected static string $resource = CatalogResource::class;

    protected static string $view = 'Welcome.pages.checkout-catalog.checkout-catalog';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    /**
     * Tier paket yang dipilih di halaman detail katalog.
     *
     * URL checkout membawa `?tier={id}` dari tombol "Pesan Sekarang" supaya
     * breadcrumb bisa menyebut paket yang dibeli. Kalau URL dibuka langsung
     * (tanpa parameter) atau id-nya tidak lagi dimiliki katalog ini, jatuh ke
     * tier pertama supaya breadcrumb TETAP punya nama paket.
     */
    public ?int $selectedTierId = null;

    public function mount(int|string $record): void
    {
        // Resolve first: Filament runs mountCanAuthorizeAccess() after mount()
        // and it calls getRecord(), which is typed to return a Model.
        $this->record = $this->resolveRecord($record);

        $this->selectedTierId = $this->resolveSelectedTierId();

        // This URL sits under the storefront's public path, so a guest can type
        // it in directly. Checking out writes an order against a user, so bounce
        // to the auth landing page (/auth, not the form) -- Laravel keeps
        // the intended URL and brings the guest back here once they have signed in.
        if (! Filament::auth()->check()) {
            $this->redirect(route(AuthenticateWelcome::LOGIN_ROUTE), navigate: false);

            return;
        }

        $this->form->fill([
            'quantity' => 1,
            'customer_name' => auth()->user()?->name,
            'whatsapp' => auth()->user()?->whatsapp,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make()
                    ->steps(CatalogResource::getCheckoutWizardSteps($this->record))
                    ->submitAction(new HtmlString(
                        '<div class="flex justify-end mt-4">'.
                        '<button type="submit" class="fi-btn fi-btn-size-md fi-btn-color-success relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg px-5 py-3 cursor-pointer shadow-sm" style="background-color:#d97706 !important;color:#ffffff !important;border:1px solid #b45309 !important;box-shadow:0 2px 4px rgb(0 0 0 / 15%) !important;">'.
                        __('Konfirmasi & Bayar').
                        '</button>'.
                        '</div>'
                    )),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        CatalogResource::handleCheckout($this->record, $data, $this);
    }

    public function getTitle(): string
    {
        return $this->record->name;
    }

    /**
     * Tier yang sedang dibeli, atau null kalau katalog ini tidak punya tier.
     */
    public function selectedTier()
    {
        if ($this->selectedTierId === null) {
            return null;
        }

        return $this->record->tiers()->whereKey($this->selectedTierId)->first();
    }

    protected function resolveSelectedTierId(): ?int
    {
        $requested = (int) request()->query('tier', 0);

        if ($requested > 0 && $this->record->tiers()->whereKey($requested)->exists()) {
            return $requested;
        }

        $fallback = $this->record->tiers()
            ->where('name_translations->en', 'Standard Flower Decoration Package')
            ->value('id')
            ?? $this->record->tiers()->value('id');

        return $fallback !== null ? (int) $fallback : null;
    }

    /**
     * Trail lengkap: Beranda > Katalog > nama katalog > nama tier > Checkout.
     *
     * Tier selalu ikut kalau katalog punya tier -- bukan hanya saat URL membawa
     * `?tier=`. Halaman ini juga menampilkan nama katalog sebagai judul, jadi
     * crumb katalog dibuat eksplisit agar tidak bergantung pada trail bawaan
     * Filament (yang dulu berhenti di nama katalog dan menghilangkan tier).
     */
    public function getBreadcrumbs(): array
    {
        $viewUrl = CatalogResource::getUrl('view', ['record' => $this->record]);

        $crumbs = [
            Home::getUrl() => __('Beranda'),
            CatalogResource::getUrl() => CatalogResource::getBreadcrumb(),
            $viewUrl => __($this->record->name),
        ];

        if ($tier = $this->selectedTier()) {
            $crumbs[CatalogResource::getUrl('view', [
                'record' => $this->record,
                'tier' => $tier->getKey(),
            ])] = $tier->name;
        }

        // Crumb terakhir tanpa URL (key numerik) = halaman saat ini.
        $crumbs[] = __('Checkout');

        return $crumbs;
    }
}
