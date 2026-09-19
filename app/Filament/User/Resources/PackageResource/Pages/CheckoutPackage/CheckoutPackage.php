<?php

namespace App\Filament\User\Resources\PackageResource\Pages\CheckoutPackage;

use App\Filament\User\Resources\PackageResource\PackageResource;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\HtmlString;

class CheckoutPackage extends Page implements HasForms
{
    use InteractsWithForms;
    use InteractsWithRecord;

    protected static string $resource = PackageResource::class;

    protected static string $view = 'User.pages.checkout-package.checkout-package';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

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
                    ->steps(PackageResource::getCheckoutWizardSteps($this->record))
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
        PackageResource::handleCheckout($this->record, $data, $this);
    }

    public function getTitle(): string
    {
        return $this->record->name;
    }
}
