@php
    $locals = [
        'ar'    => ['flag' => 'sa', 'label' => __('Arabic')],
        'de'    => ['flag' => 'de', 'label' => __('German')],
        'en'    => ['flag' => 'gb', 'label' => __('English')],
        'en_US' => ['flag' => 'us', 'label' => __('English (USA)')],
        'es'    => ['flag' => 'es', 'label' => __('Spanish')],
        'fr'    => ['flag' => 'fr', 'label' => __('French')],
        'id'    => ['flag' => 'id', 'label' => __('Indonesian')],
        'it'    => ['flag' => 'it', 'label' => __('Italian')],
        'ja'    => ['flag' => 'jp', 'label' => __('Japanese')],
        'ko'    => ['flag' => 'kr', 'label' => __('Korean')],
        'pt_BR' => ['flag' => 'br', 'label' => __('Portuguese (Brazil)')],
        'pt_PT' => ['flag' => 'pt', 'label' => __('Portuguese (Portugal)')],
        'ru'    => ['flag' => 'ru', 'label' => __('Russian')],
        'nl'    => ['flag' => 'nl', 'label' => __('Dutch')],
        'zh'    => ['flag' => 'cn', 'label' => __('Chinese')],
        'tr'    => ['flag' => 'tr', 'label' => __('Turkish')],
        'hi'    => ['flag' => 'in', 'label' => __('Hindi')],
        'my'    => ['flag' => 'mm', 'label' => __('Burmese')],
        'th'    => ['flag' => 'th', 'label' => __('Thai')],
        'vi'    => ['flag' => 'vn', 'label' => __('Vietnamese')],
        'ms'    => ['flag' => 'my', 'label' => __('Malay')],
        'fa'    => ['flag' => 'ir', 'label' => __('Persian')],
        'ur'    => ['flag' => 'pk', 'label' => __('Urdu')],
        'bn'    => ['flag' => 'bd', 'label' => __('Bengali')],
        'fil'   => ['flag' => 'ph', 'label' => __('Filipino')],
        'pl'    => ['flag' => 'pl', 'label' => __('Polish')],
        'uk'    => ['flag' => 'ua', 'label' => __('Ukrainian')],
        'ro'    => ['flag' => 'ro', 'label' => __('Romanian')],
        'cs'    => ['flag' => 'cz', 'label' => __('Czech')],
        'hu'    => ['flag' => 'hu', 'label' => __('Hungarian')],
        'el'    => ['flag' => 'gr', 'label' => __('Greek')],
        'sv'    => ['flag' => 'se', 'label' => __('Swedish')],
        'da'    => ['flag' => 'dk', 'label' => __('Danish')],
        'fi'    => ['flag' => 'fi', 'label' => __('Finnish')],
        'no'    => ['flag' => 'no', 'label' => __('Norwegian')],
        'hr'    => ['flag' => 'hr', 'label' => __('Croatian')],
        'sk'    => ['flag' => 'sk', 'label' => __('Slovak')],
        'bg'    => ['flag' => 'bg', 'label' => __('Bulgarian')],
        'lt'    => ['flag' => 'lt', 'label' => __('Lithuanian')],
        'lv'    => ['flag' => 'lv', 'label' => __('Latvian')],
        'et'    => ['flag' => 'ee', 'label' => __('Estonian')],
        'sr'    => ['flag' => 'rs', 'label' => __('Serbian')],
        'he'    => ['flag' => 'il', 'label' => __('Hebrew')],
        'sw'    => ['flag' => 'ke', 'label' => __('Swahili')],
        'am'    => ['flag' => 'et', 'label' => __('Amharic')],
    ];

    $currentLocale = app()->getLocale();
    $currentFlagImage = $locals[$currentLocale]['flag'] ?? 'gb';
@endphp

<div x-data="{
        toggle: function (event) {
            $refs.panel.toggle(event)
        },
        open: function (event) {
            $refs.panel.open(event)
        },
        close: function (event) {
            $refs.panel.close(event)
        },
    }">

    <button
        @class([
            'block hover:opacity-75',
        ])
        id="filament-language-switcher"
        x-on:click="toggle"
    >
        <div
            x-tooltip="{
                content: '{{ __('Change Language') }}',
                theme: $store.theme,
            }"
            @class([
                'flex items-center justify-center rounded-sm bg-cover bg-center rounded-md',
                'w-11 h-8 bg-gray-200 dark:bg-gray-900 border border-gray-200 dark:border-gray-700'
            ])
            style="background-image: url('https://cdn.jsdelivr.net/gh/hampusborgos/country-flags@main/svg/{{$currentFlagImage}}.svg')"
        >
        </div>
    </button>

    <div x-ref="panel" x-float.placement.bottom-end.flip.offset="{ offset: 8 }" x-transition:enter-start="opacity-0 scale-95" x-transition:leave-end="opacity-0 scale-95" class="ffi-dropdown-panel absolute z-10 divide-y divide-gray-100 rounded-lg bg-white shadow-lg ring-1 ring-gray-950/5 transition dark:divide-white/5 dark:bg-gray-900 dark:ring-white/10 max-w-56" style="display: none;">
        <div class="filament-dropdown-list p-1 max-h-96 overflow-y-auto w-56">
            @foreach ($locals as $key => $language)
                @php $isCurrent = $currentLocale === $key; @endphp
                <a
                    @class([
                        'filament-dropdown-list-item filament-dropdown-item group flex items-center whitespace-nowrap rounded-md p-2 text-sm outline-none text-gray-500 dark:text-gray-200',
                        'hover:bg-gray-50 focus:bg-gray-50 dark:hover:bg-white/5 dark:focus:bg-white/5 hover:text-gray-700 focus:text-gray-500 dark:hover:text-gray-200 dark:focus:text-gray-400' => !$isCurrent,
                        'cursor-default' => $isCurrent,
                    ])
                    @if (!$isCurrent)
                        href="{{ route('language.switch', ['locale' => $key]) }}"
                    @endif
                >
                    <span class="filament-dropdown-list-item-label truncate text-start flex justify-content-start gap-3 items-center">
                       <div
                            @class([
                                'w-6 h-4 rounded-sm bg-cover bg-center border border-gray-200 dark:border-gray-700 shadow-sm',
                            ])
                            style="background-image: url('https://cdn.jsdelivr.net/gh/hampusborgos/country-flags@main/svg/{{$language['flag']}}.svg'); background-repeat: no-repeat"
                        ></div>
                        <span @class(['font-semibold' => $isCurrent])>{{ $language['label'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</div>
