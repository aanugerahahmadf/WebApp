<?php

namespace App\Filament\Admin\Concerns\HasMobilePagination;

use App\Support\AppPlatform\AppPlatform;
use Filament\Tables\Table;

/**
 * Page sizes for the admin panel tables on a phone.
 *
 * The admin panel is the one surface that keeps pagination: it lists the whole
 * business (users, orders, transactions, and a UserResource wide enough to need
 * 38 columns), so an unpaginated table is not an option here.
 *
 * On a phone the table gets one larger page and no per-page picker. A dropdown
 * of page sizes is a poor control on a small screen, and each row is a tall
 * stacked card below the breakpoint, so Filament's 5-per-page would mean an
 * endless scroll through a list the admin is trying to scan.
 *
 * Deliberately does nothing on a desktop. Every admin Resource already declares
 * its own page size (`->paginated([5])` on most, `[10, 25, 50]` on
 * ReferenceOptionResource), and those are per-table decisions about how much of
 * a wide table fits a screen. Overriding them here would be the trait deciding
 * something the Resource already decided, and it would fail silently anyway:
 * getDefaultPaginationPageOption() drops an option that is not in the list, so a
 * blanket default of 10 collapses back to the resource's first option.
 *
 * The user panel deliberately does NOT use this: every one of its resources
 * declares `->paginated(false)` in its own table(), so a customer's own data is
 * shown in full with no paginator and no per-page trait to keep in sync.
 */
trait HasMobilePagination
{
    /**
     * Configures the table rather than overriding
     * getTableRecordsPerPageSelectOptions(), because that method is deprecated
     * and, more importantly, dead on these pages: its only caller is
     * InteractsWithTable::table(), which ListRecords::table() replaces with a
     * straight delegation to the Resource and never calls parent::table().
     * An override of it would silently do nothing here.
     */
    public function table(Table $table): Table
    {
        $table = parent::table($table);

        if (! AppPlatform::isMobile()) {
            return $table;
        }

        // paginated() takes an array, so this sets the single allowed page size
        // and keeps the paginator on. One option means the picker renders a
        // dropdown with nothing to choose, which is why it is set at all.
        return $table
            ->defaultPaginationPageOption(20)
            ->paginated([20]);
    }
}
