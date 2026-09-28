@php
    use Filament\Support\Enums\IconPosition;
    use Filament\Support\Facades\FilamentView;

    $chartColor = $getChartColor() ?? 'gray';
    $descriptionColor = $getDescriptionColor() ?? 'gray';
    $descriptionIcon = $getDescriptionIcon();
    $descriptionIconPosition = $getDescriptionIconPosition();
    $url = $getUrl();
    $tag = $url ? 'a' : 'div';
    $dataChecksum = $generateDataChecksum();

    // Derive card color from ->color() set on the Stat
    $cardColor = $getColor() ?? $getChartColor() ?? 'gray';
    if (is_array($cardColor)) $cardColor = 'gray'; // fallback for custom color arrays

    // Icon & label color maps — used regardless of panel
    $colorIconMap = [
        'warning' => 'text-warning-500 dark:text-warning-400',
        'danger'  => 'text-danger-500 dark:text-danger-400',
        'primary' => 'text-primary-500 dark:text-primary-400',
        'success' => 'text-success-500 dark:text-success-400',
        'info'    => 'text-info-500 dark:text-info-400',
        'purple'  => 'text-purple-500 dark:text-purple-400',
        'gray'    => 'text-gray-400 dark:text-gray-500',
    ];

    $colorLabelMap = [
        'warning' => 'text-warning-700 dark:text-warning-300',
        'danger'  => 'text-danger-700 dark:text-danger-300',
        'primary' => 'text-primary-700 dark:text-primary-300',
        'success' => 'text-success-700 dark:text-success-300',
        'info'    => 'text-info-700 dark:text-info-300',
        'purple'  => 'text-purple-700 dark:text-purple-300',
        'gray'    => 'text-gray-500 dark:text-gray-400',
    ];

    $iconClass  = $colorIconMap[$cardColor]  ?? $colorIconMap['gray'];
    $labelClass = $colorLabelMap[$cardColor] ?? $colorLabelMap['gray'];

    // Detect if this is a profile-stat-card (centered layout, colored bg)
    $extraAttrs    = $getExtraAttributes();
    $extraClass    = $extraAttrs['class'] ?? '';
    $isProfileCard = str_contains($extraClass, 'profile-stat-card');

    $descriptionIconClasses = \Illuminate\Support\Arr::toCssClasses([
        'fi-wi-stats-overview-stat-description-icon h-5 w-5',
        match ($descriptionColor) {
            'gray' => 'text-gray-400 dark:text-gray-500',
            default => 'text-custom-500',
        },
    ]);

    $descriptionIconStyles = \Illuminate\Support\Arr::toCssStyles([
        \Filament\Support\get_color_css_variables(
            $descriptionColor,
            shades: [500],
            alias: 'widgets::stats-overview-widget.stat.description.icon',
        ) => $descriptionColor !== 'gray',
    ]);
@endphp

<{!! $tag !!}
    @if ($url)
        {{ \Filament\Support\generate_href_html($url, $shouldOpenUrlInNewTab()) }}
    @endif
    {{
        $getExtraAttributeBag()
            ->class([
                // Default Filament background — white/gray-900, same as original vendor
                'fi-wi-stats-overview-stat relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 transition hover:brightness-110',
            ])
    }}
>
    <div @class(['grid gap-y-2', 'flex flex-col items-center justify-center text-center h-full' => $isProfileCard])>
        <div @class(['flex items-center gap-x-2', 'flex-col gap-y-2 items-center justify-center' => $isProfileCard])>
            @if ($icon = $getIcon())
                <x-filament::icon
                    :icon="$icon"
                    @class(['fi-wi-stats-overview-stat-icon h-5 w-5', $iconClass])
                />
            @endif

            <span
                @class(['fi-wi-stats-overview-stat-label text-sm font-medium', $labelClass])
            >
                {{ $getLabel() }}
            </span>
        </div>

        <div
            class="fi-wi-stats-overview-stat-value text-3xl font-semibold tracking-tight text-gray-950 dark:text-white"
        >
            {{ $getValue() }}
        </div>

        @if ($description = $getDescription())
            <div class="flex items-center gap-x-1">
                @if ($descriptionIcon && in_array($descriptionIconPosition, [IconPosition::Before, 'before']))
                    <x-filament::icon
                        :icon="$descriptionIcon"
                        :class="$descriptionIconClasses"
                        :style="$descriptionIconStyles"
                    />
                @endif

                <span
                    @class([
                        'fi-wi-stats-overview-stat-description text-sm',
                        match ($descriptionColor) {
                            'gray' => 'text-gray-500 dark:text-gray-400',
                            default => 'fi-color-custom text-custom-600 dark:text-custom-400',
                        },
                        is_string($descriptionColor) ? "fi-color-{$descriptionColor}" : null,
                    ])
                    @style([
                        \Filament\Support\get_color_css_variables(
                            $descriptionColor,
                            shades: [400, 600],
                            alias: 'widgets::stats-overview-widget.stat.description',
                        ) => $descriptionColor !== 'gray',
                    ])
                >
                    {{ $description }}
                </span>

                @if ($descriptionIcon && in_array($descriptionIconPosition, [IconPosition::After, 'after']))
                    <x-filament::icon
                        :icon="$descriptionIcon"
                        :class="$descriptionIconClasses"
                        :style="$descriptionIconStyles"
                    />
                @endif
            </div>
        @endif
    </div>

    @if ($chart = $getChart())
        {{-- An empty function to initialize the Alpine component with until it's loaded with `x-load`. This removes the need for `x-ignore`, allowing the chart to be updated via Livewire polling. --}}
        <div x-data="{ statsOverviewStatChart: function () {} }">
            <div
                @if (FilamentView::hasSpaMode())
                    x-load="visible"
                @else
                    x-load
                @endif
                x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('stats-overview/stat/chart', 'filament/widgets') }}"
                x-data="statsOverviewStatChart({
                            dataChecksum: @js($dataChecksum),
                            labels: @js(array_keys($chart)),
                            values: @js(array_values($chart)),
                        })"
                @class([
                    'fi-wi-stats-overview-stat-chart absolute inset-x-0 bottom-0 overflow-hidden rounded-b-xl',
                    match ($chartColor) {
                        'gray' => null,
                        default => 'fi-color-custom',
                    },
                    is_string($chartColor) ? "fi-color-{$chartColor}" : null,
                ])
                @style([
                    \Filament\Support\get_color_css_variables(
                        $chartColor,
                        shades: [50, 400, 500],
                        alias: 'widgets::stats-overview-widget.stat.chart',
                    ) => $chartColor !== 'gray',
                ])
            >
                <canvas x-ref="canvas" class="h-6"></canvas>

                <span
                    x-ref="backgroundColorElement"
                    @class([
                        match ($chartColor) {
                            'gray' => 'text-gray-100 dark:text-gray-800',
                            default => 'text-custom-50 dark:text-custom-400/10',
                        },
                    ])
                ></span>

                <span
                    x-ref="borderColorElement"
                    @class([
                        match ($chartColor) {
                            'gray' => 'text-gray-400',
                            default => 'text-custom-500 dark:text-custom-400',
                        },
                    ])
                ></span>
            </div>
        </div>
    @endif
</{!! $tag !!}>
