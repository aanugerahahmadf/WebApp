{{--
    Phone-only table transformation.

    Filament v3 renders every table as a desktop <table> grid, so a resource with
    many columns (Admin/UserResource has 38) becomes an unusable horizontal
    scroll on a phone. This script copies each header cell's label onto the
    matching body cell as `data-label` and flags the table with `.fi-table-cards`,
    which resources/css/Shared/Mobile.css turns into stacked label/value cards.

    Registered globally by PlatformSupportServiceProvider on
    `panels::body.end`. Emits nothing on desktop.

    NOTE: values handed to `@json()` are assigned to a variable first. Blade
    compiles `@json(...)` by exploding the expression on `,`, so an inline array
    literal or a two-argument `config('a', [])` call would become invalid PHP.
--}}
@php
    $tablesEnabled = (bool) config('app-platform.responsive_tables.enabled', true)
        && \App\Support\AppPlatform\AppPlatform::isMobile();

    $breakpoint = (int) config('app-platform.responsive_tables.breakpoint', 1023);

    $skipWhen = array_values((array) config('app-platform.responsive_tables.skip_when', []));
@endphp

@if ($tablesEnabled)
    <script>
    (function () {
        'use strict';

        var BREAKPOINT = @json($breakpoint);
        var SKIP_SELECTOR = @json($skipWhen);

        /*
         * Layout\Stack / Layout\Split columns hide themselves on small screens
         * through `hidden sm:table-cell`. Those tables are already responsive,
         * so converting them to cards would undo that work.
         */
        var RESPONSIVE_COLUMN_RE = /(^|\s)(hidden|sm:table-cell|md:table-cell|lg:table-cell|xl:table-cell)(\s|$)/;

        /* Below this many labelled columns a card adds chrome, not clarity. */
        var MIN_COLUMNS = 4;

        function isMobileViewport() {
            return window.matchMedia('(max-width: ' + BREAKPOINT + 'px)').matches;
        }

        function hasResponsiveColumns(table) {
            var cells = table.querySelectorAll('thead th, tbody tr:first-child td');

            for (var i = 0; i < cells.length; i++) {
                if (RESPONSIVE_COLUMN_RE.test(cells[i].className || '')) return true;
            }

            return false;
        }

        function isSkipped(table) {
            for (var i = 0; i < SKIP_SELECTOR.length; i++) {
                if (table.closest(SKIP_SELECTOR[i])) return true;
            }

            return false;
        }

        /**
         * Header cells carry a sort button, a select-all checkbox and the column
         * toggle. None of that belongs in a card label, so strip the widgets
         * before reading the text.
         */
        function headerLabel(th) {
            var clone = th.cloneNode(true);

            clone.querySelectorAll(
                'input, button, svg, .fi-ta-header-cell__sort-icon, .fi-ta-actions-header-cell'
            ).forEach(function (element) {
                element.remove();
            });

            return (clone.textContent || '').replace(/\s+/g, ' ').trim();
        }

        function clearLabels(table) {
            table.querySelectorAll('tbody td[data-label]').forEach(function (cell) {
                cell.removeAttribute('data-label');
            });

            table.classList.remove('fi-table-cards');
        }

        function processTable(table) {
            var headRow = table.querySelector('thead tr');

            if (!headRow) {
                clearLabels(table);

                return;
            }

            var headers = headRow.querySelectorAll('th');
            var labels = [];
            var labelled = 0;

            for (var h = 0; h < headers.length; h++) {
                var label = headerLabel(headers[h]);

                labels.push(label);

                if (label !== '') labelled++;
            }

            if (labelled < MIN_COLUMNS || hasResponsiveColumns(table)) {
                clearLabels(table);

                return;
            }

            table.querySelectorAll('tbody tr').forEach(function (row) {
                var cells = row.children;

                for (var c = 0; c < cells.length; c++) {
                    cells[c].setAttribute('data-label', labels[c] || '');
                }
            });

            table.classList.add('fi-table-cards');
        }

        function processAll(root) {
            var scope = root && root.querySelectorAll ? root : document;

            scope.querySelectorAll('.fi-ta-content table, .fi-wi-table table').forEach(function (table) {
                if (isSkipped(table)) return;

                if (isMobileViewport()) {
                    processTable(table);
                } else {
                    clearLabels(table);
                }
            });
        }

        document.addEventListener('livewire:navigated', function () { processAll(); });
        document.addEventListener('DOMContentLoaded', function () { processAll(); });

        document.addEventListener('livewire:initialized', function () {
            if (window.Livewire && window.Livewire.hook) {
                /* Livewire replaces the table markup on every commit. */
                window.Livewire.hook('morph.updated', function () { processAll(); });
            }
        });

        /*
         * Rotating a tablet, or resizing a browser across the breakpoint, has to
         * re-evaluate: otherwise a card table stays carded at desktop width.
         */
        var query = window.matchMedia('(max-width: ' + BREAKPOINT + 'px)');

        if (query.addEventListener) {
            query.addEventListener('change', function () { processAll(); });
        } else if (query.addListener) {
            query.addListener(function () { processAll(); });
        }

        /*
         * Defer the first pass: Filament renders the table inside a Livewire
         * component that is not always in the DOM at DOMContentLoaded.
         */
        setTimeout(processAll, 300);
    })();
    </script>
@endif
