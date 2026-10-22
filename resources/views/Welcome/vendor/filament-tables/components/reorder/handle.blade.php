<x-filament::icon-button
    color="gray"
    icon="/images/icons/menu.svg"
    {{-- @deprecated Use `tables::reorder.handle` instead of `tables::reorder.button`. --}}
    :icon-alias="['tables::reorder.handle', 'tables::reorder.button']"
    :attributes="
        \Filament\Support\prepare_inherited_attributes($attributes)
            ->class(['cursor-move'])
    "
/>
