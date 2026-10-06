<?php

namespace App\Filament\Welcome\Resources\ProductResource\Pages\CheckoutProduct;

use App\Filament\Welcome\Pages\Home\Home;
use App\Filament\Welcome\Resources\ProductResource\ProductResource;
use App\Http\Middleware\AuthenticateWelcome\AuthenticateWelcome;
use Filament\Facades\Filament;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\HtmlString;

class CheckoutProduct extends Page implements HasForms
{
    use InteractsWithForms;
    use InteractsWithRecord;

    protected static string $resource = ProductResource::class;

    protected static string $view = 'Welcome.pages.checkout-product.checkout-product';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public function mount(int|string $record): void
    {
        // Resolve first: Filament runs mountCanAuthorizeAccess() after mount()
        // and it calls getRecord(), which is typed to return a Model.
        $this->record = $this->resolveRecord($record);

        // This URL sits under the storefront's public path, so a guest can type
        // it in directly. Checking out writes an order against a user, so bounce
        // to the auth landing page (/user/auth, not the form) -- Laravel keeps
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
                    ->steps(ProductResource::getCheckoutWizardSteps($this->record))
                    ->submitAction(new HtmlString(
                        '<div class="flex justify-end mt-4">'.
                        '<button type="submit" class="fi-btn fi-btn-size-md fi-btn-color-success relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm ring-1 ring-emerald-600 px-4 py-2 cursor-pointer">'.
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
        ProductResource::handleCheckout($this->record, $data, $this);
    }

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getBreadcrumbs(): array
    {
        return [
            Home::getUrl() => __('Beranda'),
            ...parent::getBreadcrumbs(),
        ];
    }
}
