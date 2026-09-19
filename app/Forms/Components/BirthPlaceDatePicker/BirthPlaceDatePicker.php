<?php

namespace App\Forms\Components\BirthPlaceDatePicker;

use Carbon\Carbon;
use Closure;
use Filament\Forms\Components\Field;
use Illuminate\Support\Collection;

class BirthPlaceDatePicker extends Field
{
    protected string $view = 'User.components.birth-place-date-picker.birth-place-date-picker';

    protected string|Closure|null $minDate = null;

    protected string|Closure|null $maxDate = null;

    protected string|Closure|null $firstDayOfWeek = null;

    protected bool|Closure $semiboldAll = false;

    // ── semiboldAll (sama persis CalendarPicker) ─────────────────────────────

    public function semiboldAll(bool|Closure $condition = true): static
    {
        $this->semiboldAll = $condition;

        return $this;
    }

    public function getSemiboldAll(): bool
    {
        return (bool) $this->evaluate($this->semiboldAll);
    }

    // ── minDate (sama persis CalendarPicker) ─────────────────────────────────

    public function minDate(string|Closure|null $date): static
    {
        $this->minDate = $date;

        $this->rule(static function (BirthPlaceDatePicker $component) {
            return "after_or_equal:{$component->getMinDate()}";
        }, static fn (BirthPlaceDatePicker $component): bool => (bool) $component->getMinDate());

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

    // ── maxDate (sama persis CalendarPicker) ─────────────────────────────────

    public function maxDate(string|Closure|null $date): static
    {
        $this->maxDate = $date;

        $this->rule(static function (BirthPlaceDatePicker $component) {
            return "before_or_equal:{$component->getMaxDate()}";
        }, static fn (BirthPlaceDatePicker $component): bool => (bool) $component->getMaxDate());

        return $this;
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

    // ── firstDayOfWeek (sama persis CalendarPicker) ──────────────────────────

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
            'saturday', '6'      => 6,
            'friday', '5'        => 5,
            'thursday', '4'      => 4,
            'wednesday', '3'     => 3,
            'tuesday', '2'       => 2,
            default              => 0,
        };
    }

    // ── Label helpers untuk Blade ─────────────────────────────────────────────

    public function getMonthLabels(): array
    {
        $now = Carbon::now();

        return Collection::make(range(1, 12))
            ->map(fn ($m) => Carbon::createFromDate($now->year, $m, 1)->translatedFormat('F'))
            ->values()
            ->all();
    }

    public function getDayLabels(): array
    {
        $now   = Carbon::now();
        $start = $this->getFirstDayOfWeek();

        $labels = [];

        for ($i = 0; $i < 7; $i++) {
            $dayOfWeek = ($start + $i) % 7;
            $labels[]  = Carbon::createFromDate($now->year, 1, 1 + $dayOfWeek)->translatedFormat('D');
        }

        return $labels;
    }
}
