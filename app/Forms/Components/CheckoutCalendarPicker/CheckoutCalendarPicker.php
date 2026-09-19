<?php

namespace App\Forms\Components\CheckoutCalendarPicker;

use Carbon\Carbon;
use Closure;
use Filament\Forms\Components\Field;

class CheckoutCalendarPicker extends Field
{
    protected string $view = 'User.components.checkout-calendar-picker.checkout-calendar-picker';

    protected string|Closure|null $minDate = null;

    protected string|Closure|null $maxDate = null;

    protected string|Closure|null $firstDayOfWeek = null;

    public function minDate(string|Closure|null $date): static
    {
        $this->minDate = $date;

        $this->rule(static function (CheckoutCalendarPicker $component) {
            return "after_or_equal:{$component->getMinDate()}";
        }, static fn (CheckoutCalendarPicker $component): bool => (bool) $component->getMinDate());

        return $this;
    }

    public function maxDate(string|Closure|null $date): static
    {
        $this->maxDate = $date;

        $this->rule(static function (CheckoutCalendarPicker $component) {
            return "before_or_equal:{$component->getMaxDate()}";
        }, static fn (CheckoutCalendarPicker $component): bool => (bool) $component->getMaxDate());

        return $this;
    }

    public function getMinDate(): ?string
    {
        $minDate = $this->evaluate($this->minDate);

        if ($minDate instanceof Carbon) {
            return $minDate->format('Y-m-d');
        }

        if ($minDate) {
            return Carbon::parse($minDate)->format('Y-m-d');
        }

        return null;
    }

    public function getMaxDate(): ?string
    {
        $maxDate = $this->evaluate($this->maxDate);

        if ($maxDate instanceof Carbon) {
            return $maxDate->format('Y-m-d');
        }

        if ($maxDate) {
            return Carbon::parse($maxDate)->format('Y-m-d');
        }

        return null;
    }

    public function firstDayOfWeek(string|Closure|null $day): static
    {
        $this->firstDayOfWeek = $day;

        return $this;
    }

    public function getFirstDayOfWeek(): int
    {
        $day = $this->evaluate($this->firstDayOfWeek) ?? 'monday';

        return match (strtolower($day)) {
            'monday', '1', 'mon' => 1,
            'saturday', '6' => 6,
            'friday', '5' => 5,
            'thursday', '4' => 4,
            'wednesday', '3' => 3,
            'tuesday', '2' => 2,
            default => 0,
        };
    }
}