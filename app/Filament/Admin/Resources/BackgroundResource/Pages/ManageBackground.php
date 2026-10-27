<?php

namespace App\Filament\Admin\Resources\BackgroundResource\Pages;

use App\Filament\Admin\Resources\BackgroundResource\BackgroundResource;
use App\Models\FmAppSetting\FmAppSetting;
use Filament\Resources\Pages\ManageRecords;

class ManageBackground extends ManageRecords
{
    protected static string $resource = BackgroundResource::class;

    public function mount(): void
    {
        FmAppSetting::firstOrCreate([
            'key' => 'background',
        ], [
            'value' => FmAppSetting::backgroundDefaults(),
        ]);

        parent::mount();
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function afterSave(): void
    {
        parent::afterSave();

        // Keep the JSON record valid even when Filament omits a conditionally
        // hidden upload field from the submitted form state.
        FmAppSetting::saveBackground(is_array($this->record->value) ? $this->record->value : []);
    }
}
