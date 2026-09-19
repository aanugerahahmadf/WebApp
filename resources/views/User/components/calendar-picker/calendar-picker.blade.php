@php
    $statePath = $getStatePath();
    $minDate = $getMinDate();
    $maxDate = $getMaxDate();
    $isDisabled = $isDisabled();
    $firstDayOfWeek = $getFirstDayOfWeek();
    $currentValue = $getState();
    $semiboldAll = $getSemiboldAll();

    $now = \Illuminate\Support\Carbon::now();

    $monthLabels = collect(range(1, 12))
        ->map(fn ($m) => \Illuminate\Support\Carbon::createFromDate($now->year, $m, 1)->translatedFormat('F'))
        ->values()
        ->all();

    $dayLabels = [];
    $start = $firstDayOfWeek;
    for ($i = 0; $i < 7; $i++) {
        $dayOfWeek = ($start + $i) % 7;
        $dayLabels[] = \Illuminate\Support\Carbon::createFromDate($now->year, 1, 1 + $dayOfWeek)->translatedFormat('D');
    }
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="calendarPicker({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            minDate: @js($minDate),
            maxDate: @js($maxDate),
            semiboldAll: @js($semiboldAll),
            monthLabels: @js($monthLabels),
            dayLabels: @js($dayLabels),
            firstDayOfWeek: @js($firstDayOfWeek),
            initialValue: @js($currentValue),
        })"
        x-init="initCalendar()"
        @click.away="showMonthPicker = false; showYearPicker = false"
        wire:ignore
        wire:key="calendar-picker.{{ $statePath }}"
        @class([
            'fi-fo-calendar-picker relative w-full',
            'opacity-70 pointer-events-none' => $isDisabled,
        ])
    >
        {{-- Display trigger --}}
        <div
            class="fi-fo-text-input w-full flex items-center overflow-hidden rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-950 shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white cursor-pointer select-none"
            :class="{ 'ring-1 ring-primary-600 border-primary-600': showPanel }"
            @click="togglePanel()"
            role="button"
            :aria-expanded="showPanel ? 'true' : 'false'"
            aria-haspopup="dialog"
        >
            <svg class="h-5 w-5 shrink-0 text-gray-500 dark:text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
            </svg>
            <span class="ml-2 flex-1 truncate" x-text="displayText"></span>
            <span class="ml-2 text-xs text-gray-400 dark:text-gray-500" x-text="placeholderText" x-show="!displayText"></span>
        </div>

        {{-- Calendar panel --}}
        <div
            x-show="showPanel"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-cloak
            class="absolute z-50 mt-2 w-72 origin-top rounded-lg border border-gray-200 bg-white p-3 shadow-2xl ring-1 ring-gray-950/5 dark:border-gray-700 dark:bg-gray-900 dark:ring-white/10"
        >
            {{-- Header: Prev / Month&Year buttons / Next --}}
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
                        class="px-2 py-1 transition"
                        x-text="monthLabels[viewMonth]"
                    ></button>
                    <button
                        type="button"
                        @click="showYearPicker = !showYearPicker; showMonthPicker = false"
                        class="px-2 py-1 transition"
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

            {{-- Month picker overlay --}}
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
                            class="flex h-12 items-center justify-center text-xs bg-transparent transition disabled:cursor-not-allowed disabled:opacity-40"
                            :class="[
                                index === viewMonth
                                    ? 'font-bold text-primary-600 dark:text-primary-400'
                                    : (semiboldAll || !isMonthDisabled(index))
                                        ? 'font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-gray-100'
                                        : 'text-gray-400 dark:text-gray-600'
                            ]"
                            x-text="label"
                        ></button>
                    </template>
                </div>
            </div>

            {{-- Year picker overlay --}}
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
                            class="flex h-8 items-center justify-center text-xs bg-transparent transition disabled:cursor-not-allowed disabled:opacity-40"
                            :class="[
                                year === viewYear
                                    ? 'font-bold text-primary-600 dark:text-primary-400'
                                    : (semiboldAll || !isYearDisabled(year))
                                        ? 'font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-gray-100'
                                        : 'text-gray-400 dark:text-gray-600'
                            ]"
                            x-text="year"
                        ></button>
                    </template>
                </div>
            </div>

            {{-- Day-of-week header --}}
            <div
                x-show="!showMonthPicker && !showYearPicker"
                x-cloak
                class="mt-2 grid grid-cols-7 gap-1"
            >
                <template x-for="(day, index) in dayLabels" :key="index">
                    <div class="py-1 text-center text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500" x-text="day"></div>
                </template>
            </div>

            {{-- Day grid --}}
            <div
                x-show="!showMonthPicker && !showYearPicker"
                x-cloak
                class="mt-1 grid grid-cols-7 gap-1"
            >
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
                                    'font-bold text-primary-600 dark:text-primary-400': cell.selected,
                                    'font-semibold text-primary-600 dark:text-primary-400': cell.isToday && !cell.selected,
                                    'font-semibold text-gray-700 hover:text-gray-900 dark:text-gray-300 dark:hover:text-gray-100': !cell.isToday && !cell.selected && !cell.disabled,
                                    'font-semibold text-gray-400 dark:text-gray-600': !cell.isToday && !cell.selected && cell.disabled && semiboldAll,
                                    'text-gray-400 dark:text-gray-600': !cell.isToday && !cell.selected && cell.disabled && !semiboldAll,
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
                <span class="text-xs text-gray-500 dark:text-gray-400" x-text="displayText ? displayText : ''"></span>
                <button
                    type="button"
                    @click="clearSelection()"
                    class="text-xs font-medium text-danger-600 hover:underline dark:text-danger-400"
                    x-show="displayText"
                >{{ __('Delete') }}</button>
            </div>
        </div>
    </div>

    @once
        @push('scripts')
            <script>
                function calendarPicker(config) {
                    return {
                        state: null,
                        minDate: config.minDate,
                        maxDate: config.maxDate,
                        minDateParsed: null,
                        maxDateParsed: null,
                        semiboldAll: config.semiboldAll,
                        monthLabels: config.monthLabels,
                        dayLabels: config.dayLabels,
                        firstDayOfWeek: config.firstDayOfWeek,
                        initialValue: config.initialValue,

                        showPanel: false,
                        showMonthPicker: false,
                        showYearPicker: false,

                        viewYear: new Date().getFullYear(),
                        viewMonth: new Date().getMonth(),
                        yearRangeStart: new Date().getFullYear() - 6,

                        displayText: '',
                        grid: [],

                        today: new Date(),
                        placeholderText: '{{ __('Pilih tanggal') }}...',

                        initCalendar() {
                            this.minDateParsed = this.minDate ? this.parseDate(this.minDate) : null;
                            this.maxDateParsed = this.maxDate ? this.parseDate(this.maxDate) : null;

                            if (this.state !== null && this.state !== undefined && this.state !== '') {
                                const parsed = this.parseDate(this.state);
                                if (parsed) this.selectedDate = parsed;
                            } else if (this.initialValue) {
                                const parsed = this.parseDate(this.initialValue);
                                if (parsed) {
                                    this.state = this.initialValue;
                                    this.selectedDate = parsed;
                                }
                            }

                            if (this.selectedDate) {
                                this.viewYear = this.selectedDate.getFullYear();
                                this.viewMonth = this.selectedDate.getMonth();
                            } else if (this.minDateParsed) {
                                this.viewYear = this.minDateParsed.getFullYear();
                                this.viewMonth = this.minDateParsed.getMonth();
                            } else if (this.maxDateParsed) {
                                this.viewYear = this.maxDateParsed.getFullYear();
                                this.viewMonth = this.maxDateParsed.getMonth();
                            }

                            this.yearRangeStart = this.viewYear - 6;
                            this.renderDayGrid();
                            this.updateDisplay();
                        },

                        parseDate(value) {
                            if (!value) return null;
                            const parts = String(value).split('-');
                            if (parts.length !== 3) return null;
                            const year = parseInt(parts[0], 10);
                            const month = parseInt(parts[1], 10) - 1;
                            const day = parseInt(parts[2], 10);
                            if (isNaN(year) || isNaN(month) || isNaN(day)) return null;
                            return new Date(year, month, day);
                        },

                        toDateString(date) {
                            if (!date) return null;
                            const y = date.getFullYear();
                            const m = String(date.getMonth() + 1).padStart(2, '0');
                            const d = String(date.getDate()).padStart(2, '0');
                            return `${y}-${m}-${d}`;
                        },

                        sameDay(a, b) {
                            return a && b &&
                                a.getFullYear() === b.getFullYear() &&
                                a.getMonth() === b.getMonth() &&
                                a.getDate() === b.getDate();
                        },

                        isDisabled(date) {
                            const dateOnly = new Date(date.getFullYear(), date.getMonth(), date.getDate());
                            if (this.minDateParsed) {
                                const minOnly = new Date(this.minDateParsed.getFullYear(), this.minDateParsed.getMonth(), this.minDateParsed.getDate());
                                if (dateOnly < minOnly) return true;
                            }
                            if (this.maxDateParsed) {
                                const maxOnly = new Date(this.maxDateParsed.getFullYear(), this.maxDateParsed.getMonth(), this.maxDateParsed.getDate());
                                if (dateOnly > maxOnly) return true;
                            }
                            return false;
                        },

                        isMonthDisabled(month) {
                            if (this.semiboldAll || !this.minDateParsed) return false;
                            const minYear = this.minDateParsed.getFullYear();
                            const minMonth = this.minDateParsed.getMonth();
                            return this.viewYear < minYear || (this.viewYear === minYear && month < minMonth);
                        },

                        isYearDisabled(year) {
                            if (this.semiboldAll || !this.minDateParsed) return false;
                            return year < this.minDateParsed.getFullYear();
                        },

                        renderDayGrid() {
                            const firstDay = new Date(this.viewYear, this.viewMonth, 1);
                            const startOffset = (firstDay.getDay() - this.firstDayOfWeek + 7) % 7;
                            const daysInMonth = new Date(this.viewYear, this.viewMonth + 1, 0).getDate();
                            const cells = [];

                            for (let i = 0; i < startOffset; i++) cells.push(null);

                            for (let day = 1; day <= daysInMonth; day++) {
                                const date = new Date(this.viewYear, this.viewMonth, day);
                                cells.push({
                                    day,
                                    dateString: this.toDateString(date),
                                    disabled: this.isDisabled(date),
                                    isToday: this.sameDay(date, this.today),
                                    selected: this.selectedDate ? this.sameDay(date, this.selectedDate) : false,
                                });
                            }

                            this.grid = cells;
                        },

                        updateDisplay() {
                            this.displayText = this.selectedDate
                                ? this.selectedDate.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
                                : '';
                        },

                        togglePanel() {
                            this.showPanel = !this.showPanel;
                            if (this.showPanel) {
                                this.showMonthPicker = false;
                                this.showYearPicker = false;
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
                            this.viewMonth = index;
                            this.showMonthPicker = false;
                            this.renderDayGrid();
                        },

                        selectYear(year) {
                            if (this.isYearDisabled(year)) return;
                            this.viewYear = year;
                            this.showYearPicker = false;
                            this.renderDayGrid();
                        },

                        selectDate(cell) {
                            if (cell.disabled) return;
                            this.selectedDate = this.parseDate(cell.dateString);
                            this.state = cell.dateString;
                            this.viewYear = this.selectedDate.getFullYear();
                            this.viewMonth = this.selectedDate.getMonth();
                            this.renderDayGrid();
                            this.updateDisplay();
                            this.showPanel = false;
                        },

                        clearSelection() {
                            this.selectedDate = null;
                            this.state = null;
                            this.displayText = '';
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