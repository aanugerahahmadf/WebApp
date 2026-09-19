@php
    $statePath      = $getStatePath();
    $minDate        = $getMinDate();
    $maxDate        = $getMaxDate();
    $isDisabled     = $isDisabled();
    $firstDayOfWeek = $getFirstDayOfWeek();
    $currentValue   = $getState(); // "Jakarta, 01/01/1990" atau null
    $semiboldAll    = $getSemiboldAll();

    $now = \Illuminate\Support\Carbon::now();

    $monthLabels = collect(range(1, 12))
        ->map(fn ($m) => \Illuminate\Support\Carbon::createFromDate($now->year, $m, 1)->translatedFormat('F'))
        ->values()
        ->all();

    $dayLabels = [];
    $start = $firstDayOfWeek;
    for ($i = 0; $i < 7; $i++) {
        $dayOfWeek   = ($start + $i) % 7;
        $dayLabels[] = \Illuminate\Support\Carbon::createFromDate($now->year, 1, 1 + $dayOfWeek)->translatedFormat('D');
    }
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="birthPlaceDatePicker({
            state: $wire.{{ $applyStateBindingModifiers('$entangle(\'' . $statePath . '\')') }},
            minDate: @js($minDate),
            maxDate: @js($maxDate),
            semiboldAll: @js($semiboldAll),
            monthLabels: @js($monthLabels),
            dayLabels: @js($dayLabels),
            firstDayOfWeek: @js($firstDayOfWeek),
            initialValue: @js($currentValue),
        })"
        x-init="init()"
        @click.away="showPanel = false; showMonthPicker = false; showYearPicker = false"
        wire:ignore
        wire:key="birth-place-date-picker.{{ $statePath }}"
        class="relative w-full"
    >
        {{-- ── 100% Native Filament Input Wrapper & Input (Tanpa garis pemisah | dan tanpa space jauh) ── --}}
        <x-filament::input.wrapper
            :disabled="$isDisabled"
            :valid="! $errors->has($statePath)"
        >
            <x-filament::input
                type="text"
                x-model="combinedText"
                @input.debounce.300ms="onInput()"
                placeholder="{{ __('Contoh: Jakarta, 01/01/1990') }}"
                :disabled="$isDisabled"
            />

            <x-slot name="suffix">
                <button
                    type="button"
                    @click="togglePanel()"
                    :class="{
                        'text-primary-600 dark:text-primary-400': showPanel,
                        'text-gray-400 hover:text-primary-600 dark:text-gray-500 dark:hover:text-primary-400': !showPanel
                    }"
                    class="flex items-center justify-center p-1 transition focus:outline-none"
                    :aria-expanded="showPanel ? 'true' : 'false'"
                    aria-haspopup="dialog"
                    title="{{ __('Pilih Tanggal') }}"
                >
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                    </svg>
                </button>
            </x-slot>
        </x-filament::input.wrapper>

        {{-- ── Calendar dropdown overlay (langsung muncul di bawah input saat klik icon) ── --}}
        <div
            x-show="showPanel"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0 -translate-y-1"
            x-cloak
            class="absolute right-0 z-50 mt-2 w-72 origin-top-right rounded-lg border border-gray-200 bg-white p-3 shadow-2xl ring-1 ring-gray-950/5 dark:border-gray-700 dark:bg-gray-900 dark:ring-white/10"
        >
            {{-- Header: Prev / Month+Year / Next --}}
            <div class="flex items-center justify-between">
                <button
                    type="button"
                    @click="prevMonth()"
                    class="rounded p-1 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                </button>

                <div class="flex items-center gap-1 text-sm font-semibold text-primary-600 dark:text-primary-400">
                    <button
                        type="button"
                        @click="showMonthPicker = !showMonthPicker; showYearPicker = false"
                        class="px-2 py-1 transition hover:underline"
                        x-text="monthLabels[viewMonth]"
                    ></button>
                    <button
                        type="button"
                        @click="showYearPicker = !showYearPicker; showMonthPicker = false"
                        class="px-2 py-1 transition hover:underline"
                        x-text="viewYear"
                    ></button>
                </div>

                <button
                    type="button"
                    @click="nextMonth()"
                    class="rounded p-1 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                </button>
            </div>

            {{-- Month picker --}}
            <div x-show="showMonthPicker" x-cloak class="mt-2">
                <div class="mb-1 flex items-center justify-between">
                    <button type="button" @click="viewYear--; renderDayGrid()" class="rounded p-1 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                    </button>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400" x-text="viewYear"></span>
                    <button type="button" @click="viewYear++; renderDayGrid()" class="rounded p-1 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                    </button>
                </div>
                <div class="grid grid-cols-3 gap-1">
                    <template x-for="(label, index) in monthLabels" :key="label">
                        <button
                            type="button"
                            @click="selectMonth(index)"
                            :disabled="isMonthDisabled(index)"
                            class="flex h-10 items-center justify-center rounded text-xs bg-transparent transition disabled:cursor-not-allowed disabled:opacity-40"
                            :class="[
                                index === viewMonth
                                    ? 'font-bold bg-primary-50 text-primary-600 dark:bg-primary-950/50 dark:text-primary-400'
                                    : (semiboldAll || !isMonthDisabled(index))
                                        ? 'font-semibold text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-gray-100'
                                        : 'text-gray-400 dark:text-gray-600'
                            ]"
                            x-text="label"
                        ></button>
                    </template>
                </div>
            </div>

            {{-- Year picker --}}
            <div x-show="showYearPicker" x-cloak class="mt-2">
                <div class="mb-1 flex items-center justify-center gap-4">
                    <button type="button" @click="yearRangeStart -= 12" class="rounded p-1 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                    </button>
                    <span class="text-center text-xs font-semibold text-gray-500 dark:text-gray-400" x-text="(yearRangeStart + 6)"></span>
                    <button type="button" @click="yearRangeStart += 12" class="rounded p-1 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                    </button>
                </div>
                <div class="grid grid-cols-3 gap-1">
                    <template x-for="year in visibleYears" :key="year">
                        <button
                            type="button"
                            @click="selectYear(year)"
                            :disabled="isYearDisabled(year)"
                            class="flex h-8 items-center justify-center rounded text-xs bg-transparent transition disabled:cursor-not-allowed disabled:opacity-40"
                            :class="[
                                year === viewYear
                                    ? 'font-bold bg-primary-50 text-primary-600 dark:bg-primary-950/50 dark:text-primary-400'
                                    : (semiboldAll || !isYearDisabled(year))
                                        ? 'font-semibold text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-gray-100'
                                        : 'text-gray-400 dark:text-gray-600'
                            ]"
                            x-text="year"
                        ></button>
                    </template>
                </div>
            </div>

            {{-- Day-of-week header --}}
            <div x-show="!showMonthPicker && !showYearPicker" x-cloak class="mt-2 grid grid-cols-7 gap-1">
                <template x-for="(day, index) in dayLabels" :key="index">
                    <div class="py-1 text-center text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500" x-text="day"></div>
                </template>
            </div>

            {{-- Day grid --}}
            <div x-show="!showMonthPicker && !showYearPicker" x-cloak class="mt-1 grid grid-cols-7 gap-1">
                <template x-for="(cell, cellIndex) in grid" :key="cell ? cell.dateString : 'empty-' + cellIndex">
                    <div class="flex items-center justify-center">
                        <template x-if="cell !== null">
                            <button
                                type="button"
                                @click="selectDate(cell)"
                                :disabled="cell.disabled"
                                class="flex h-8 w-8 items-center justify-center text-sm bg-transparent transition disabled:cursor-not-allowed disabled:opacity-30"
                                :class="{
                                    'pointer-events-none': cell.disabled,
                                    'rounded-full bg-primary-600 text-white font-bold': cell.selected,
                                    'font-semibold text-primary-600 dark:text-primary-400': cell.isToday && !cell.selected,
                                    'font-semibold text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-full dark:text-gray-300 dark:hover:text-gray-100': !cell.isToday && !cell.selected && !cell.disabled,
                                    'text-gray-400 dark:text-gray-600': !cell.isToday && !cell.selected && cell.disabled,
                                }"
                                x-text="cell.day"
                            ></button>
                        </template>
                    </div>
                </template>
            </div>

            {{-- Footer --}}
            <div
                x-show="!showMonthPicker && !showYearPicker"
                x-cloak
                class="mt-2 flex items-center justify-between border-t border-gray-100 pt-2 dark:border-gray-800"
            >
                <span class="text-xs text-gray-500 dark:text-gray-400" x-text="selectedDate ? formatDmy(selectedDate) : ''"></span>
                <button
                    type="button"
                    @click="clearDate()"
                    class="text-xs font-medium text-danger-600 hover:underline dark:text-danger-400"
                    x-show="selectedDate"
                >{{ __('Hapus Tanggal') }}</button>
            </div>
        </div>
    </div>

    @once
        @push('scripts')
            <script>
                function birthPlaceDatePicker(config) {
                    return {
                        state:          config.state ?? null,
                        combinedText:   '',
                        selectedDate:   null,

                        minDate:        config.minDate,
                        maxDate:        config.maxDate,
                        minDateParsed:  null,
                        maxDateParsed:  null,
                        semiboldAll:    config.semiboldAll,
                        monthLabels:    config.monthLabels,
                        dayLabels:      config.dayLabels,
                        firstDayOfWeek: config.firstDayOfWeek,
                        initialValue:   config.initialValue,

                        showPanel:       false,
                        showMonthPicker: false,
                        showYearPicker:  false,

                        viewYear:       new Date().getFullYear(),
                        viewMonth:      new Date().getMonth(),
                        yearRangeStart: new Date().getFullYear() - 6,
                        grid:           [],
                        today:          new Date(),

                        init() {
                            this.minDateParsed = this.minDate ? this.parseYmd(this.minDate) : null;
                            this.maxDateParsed = this.maxDate ? this.parseYmd(this.maxDate) : null;

                            const raw = this.state ?? this.initialValue ?? '';
                            this.combinedText = raw;

                            if (raw) {
                                this.extractDateFromText(raw);
                            }

                            this.yearRangeStart = this.viewYear - 6;
                            this.renderDayGrid();
                        },

                        extractDateFromText(text) {
                            if (!text) return;
                            const parts = text.split(',');
                            if (parts.length > 1) {
                                const datePart = parts.slice(1).join(',').trim();
                                const d = this.parseDmy(datePart) || this.parseYmd(datePart);
                                if (d) {
                                    this.selectedDate = d;
                                    this.viewYear    = d.getFullYear();
                                    this.viewMonth   = d.getMonth();
                                }
                            } else {
                                const d = this.parseDmy(text.trim()) || this.parseYmd(text.trim());
                                if (d) {
                                    this.selectedDate = d;
                                    this.viewYear    = d.getFullYear();
                                    this.viewMonth   = d.getMonth();
                                }
                            }
                        },

                        getCityPart() {
                            if (!this.combinedText) return '';
                            const parts = this.combinedText.split(',');
                            if (parts.length > 1) {
                                return (parts[0] ?? '').trim();
                            }
                            // Jika belum ada koma tapi seluruh teks adalah tanggal
                            if (this.parseDmy(this.combinedText.trim()) || this.parseYmd(this.combinedText.trim())) {
                                return '';
                            }
                            return this.combinedText.trim();
                        },

                        onInput() {
                            this.extractDateFromText(this.combinedText);
                            this.state = this.combinedText ? this.combinedText.trim() : null;
                            if (this.selectedDate) {
                                this.renderDayGrid();
                            }
                        },

                        parseYmd(value) {
                            if (!value) return null;
                            const p = String(value).split('-');
                            if (p.length !== 3) return null;
                            const y = parseInt(p[0], 10), m = parseInt(p[1], 10) - 1, d = parseInt(p[2], 10);
                            if (isNaN(y) || isNaN(m) || isNaN(d)) return null;
                            return new Date(y, m, d);
                        },

                        parseDmy(value) {
                            if (!value) return null;
                            const p = String(value).split('/');
                            if (p.length !== 3) return null;
                            const d = parseInt(p[0], 10), m = parseInt(p[1], 10) - 1, y = parseInt(p[2], 10);
                            if (isNaN(d) || isNaN(m) || isNaN(y)) return null;
                            return new Date(y, m, d);
                        },

                        formatDmy(date) {
                            if (!date) return '';
                            const d = String(date.getDate()).padStart(2, '0');
                            const m = String(date.getMonth() + 1).padStart(2, '0');
                            const y = date.getFullYear();
                            return `${d}/${m}/${y}`;
                        },

                        toYmd(date) {
                            if (!date) return null;
                            const y = date.getFullYear();
                            const m = String(date.getMonth() + 1).padStart(2, '0');
                            const d = String(date.getDate()).padStart(2, '0');
                            return `${y}-${m}-${d}`;
                        },

                        sameDay(a, b) {
                            return a && b &&
                                a.getFullYear() === b.getFullYear() &&
                                a.getMonth()    === b.getMonth()    &&
                                a.getDate()     === b.getDate();
                        },

                        isDisabled(date) {
                            const d = new Date(date.getFullYear(), date.getMonth(), date.getDate());
                            if (this.minDateParsed) {
                                const min = new Date(this.minDateParsed.getFullYear(), this.minDateParsed.getMonth(), this.minDateParsed.getDate());
                                if (d < min) return true;
                            }
                            if (this.maxDateParsed) {
                                const max = new Date(this.maxDateParsed.getFullYear(), this.maxDateParsed.getMonth(), this.maxDateParsed.getDate());
                                if (d > max) return true;
                            }
                            return false;
                        },

                        isMonthDisabled(month) {
                            if (this.semiboldAll || !this.minDateParsed) return false;
                            const minY = this.minDateParsed.getFullYear();
                            const minM = this.minDateParsed.getMonth();
                            return this.viewYear < minY || (this.viewYear === minY && month < minM);
                        },

                        isYearDisabled(year) {
                            if (this.semiboldAll || !this.minDateParsed) return false;
                            return year < this.minDateParsed.getFullYear();
                        },

                        renderDayGrid() {
                            const firstDay   = new Date(this.viewYear, this.viewMonth, 1);
                            const startOffset = (firstDay.getDay() - this.firstDayOfWeek + 7) % 7;
                            const daysInMonth = new Date(this.viewYear, this.viewMonth + 1, 0).getDate();
                            const cells = [];

                            for (let i = 0; i < startOffset; i++) cells.push(null);

                            for (let day = 1; day <= daysInMonth; day++) {
                                const date = new Date(this.viewYear, this.viewMonth, day);
                                cells.push({
                                    day,
                                    dateString: this.toYmd(date),
                                    disabled:   this.isDisabled(date),
                                    isToday:    this.sameDay(date, this.today),
                                    selected:   this.selectedDate ? this.sameDay(date, this.selectedDate) : false,
                                });
                            }

                            this.grid = cells;
                        },

                        togglePanel() {
                            this.showPanel = !this.showPanel;
                            if (this.showPanel) {
                                this.showMonthPicker = false;
                                this.showYearPicker  = false;
                                this.renderDayGrid();
                            }
                        },

                        prevMonth() {
                            this.viewMonth--;
                            if (this.viewMonth < 0) { this.viewMonth = 11; this.viewYear--; }
                            this.renderDayGrid();
                        },

                        nextMonth() {
                            this.viewMonth++;
                            if (this.viewMonth > 11) { this.viewMonth = 0; this.viewYear++; }
                            this.renderDayGrid();
                        },

                        selectMonth(index) {
                            if (this.isMonthDisabled(index)) return;
                            this.viewMonth      = index;
                            this.showMonthPicker = false;
                            this.renderDayGrid();
                        },

                        selectYear(year) {
                            if (this.isYearDisabled(year)) return;
                            this.viewYear       = year;
                            this.showYearPicker = false;
                            this.renderDayGrid();
                        },

                        selectDate(cell) {
                            if (cell.disabled) return;
                            this.selectedDate = this.parseYmd(cell.dateString);
                            this.viewYear     = this.selectedDate.getFullYear();
                            this.viewMonth    = this.selectedDate.getMonth();

                            const formattedDate = this.formatDmy(this.selectedDate);
                            const city = this.getCityPart();

                            if (city) {
                                this.combinedText = city + ', ' + formattedDate;
                            } else {
                                this.combinedText = formattedDate;
                            }

                            this.state = this.combinedText;
                            this.renderDayGrid();
                            this.showPanel = false;
                        },

                        clearDate() {
                            this.selectedDate = null;
                            const city = this.getCityPart();
                            this.combinedText = city;
                            this.state = city ? city : null;
                            this.renderDayGrid();
                        },

                        get visibleYears() {
                            const years = [];
                            for (let i = 0; i < 12; i++) years.push(this.yearRangeStart + i);
                            return years;
                        },
                    };
                }
            </script>
        @endpush
    @endonce
</x-dynamic-component>
