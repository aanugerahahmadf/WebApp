<?php

namespace App\Forms\Components\StarRating;

use Filament\Forms\Components\Field;

class StarRating extends Field
{
    protected string $view = 'Shared.components.star-rating.star-rating';

    protected function setUp(): void
    {
        parent::setUp();

        $this->required()->rules(['integer', 'min:1', 'max:5']);
    }
}
