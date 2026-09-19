@php
    use App\Support\PlatformContext\PlatformContext;
    use App\Providers\NativeServiceProvider\NativeServiceProvider;

    $currentLocale = app()->getLocale();
    $locals = config('filament-language-switcher.locals', []);
    $platform = PlatformContext::current();

    $emojiFlags = [
        'id' => '🇮🇩',
        'en' => '🇬🇧',
        'en_US' => '🇺🇸',
        'ar' => '🇸🇦',
        'de' => '🇩🇪',
        'es' => '🇪🇸',
        'fr' => '🇫🇷',
        'it' => '🇮🇹',
        'ja' => '🇯🇵',
        'ko' => '🇰🇷',
        'zh' => '🇨🇳',
        'ru' => '🇷🇺',
    ];

    $currentEmoji = $emojiFlags[$currentLocale] ?? '🌐';
    $currentLabel = match ($currentLocale) {
        'en_US' => 'US',
        'en' => 'UK',
        default => strtoupper($currentLocale),
    };

    $currentPanelId = filament()->getCurrentPanel()?->getId() ?? '';
    $isAdmin = $currentPanelId === 'admin' || str_contains(request()->url(), '/admin');
    $isUser = $currentPanelId === 'user' || str_contains(request()->url(), '/user');
    $activeColorClass = 'text-[#e91e63]';
    if ($isAdmin)
        $activeColorClass = 'text-[#6366f1]';
    if ($isUser)
        $activeColorClass = 'text-[#fbbf24]';
@endphp

{{-- Rendered only for website and desktop app requests by UserPanelProvider. --}}
<div x-data="{
        isLanguageSwitcherOpen: false,
        toggleDropdown() {
            this.isLanguageSwitcherOpen = !this.isLanguageSwitcherOpen;
            if (this.isLanguageSwitcherOpen) {
                this.$nextTick(() => this.positionDropdown());
            }
        },
        closeDropdown() { this.isLanguageSwitcherOpen = false },
        positionDropdown() {
            const btn = this.$refs.langButton;
            const panel = this.$refs.langPanel;
            if (!btn || !panel) return;
            const rect = btn.getBoundingClientRect();
            const panelWidth = panel.offsetWidth || 220;
            const panelHeight = panel.offsetHeight || 220;
            let left = rect.right - panelWidth;
            left = Math.max(8, Math.min(left, window.innerWidth - panelWidth - 8));
            let top = rect.bottom + 8;
            if (top + panelHeight > window.innerHeight - 8) {
                top = Math.max(8, rect.top - panelHeight - 8);
            }
            panel.style.left = left + 'px';
            panel.style.top = top + 'px';
        },
    }" class="relative inline-block text-left" x-on:click.outside="closeDropdown()">

    {{-- Trigger Button --}}
    <button type="button" id="filament-language-switcher" x-ref="langButton" x-on:click="toggleDropdown()"
        class="flex items-center justify-center gap-2 h-10 px-3 min-w-10 rounded-md ring-1 ring-gray-950/10 dark:ring-white/20 transition hover:bg-gray-50 dark:hover:bg-white/5"
        x-tooltip="{
                content: '{{ __('Change Language') }}',
                theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light'
            }">
        <div class="w-6 h-4 bg-cover bg-center rounded-sm shadow-sm border border-gray-200 dark:border-gray-700 shrink-0"
            style="background-image: url('https://cdn.jsdelivr.net/gh/hampusborgos/country-flags@main/svg/{{ $locals[$currentLocale]['flag'] ?? 'gb' }}.svg')">
        </div>
            <span @class(['text-xs font-bold uppercase tracking-wider', $activeColorClass])>
            {{ $currentLabel }}
        </span>
    </button>

    {{-- Dropdown Panel — di-teleport ke <body> agar tidak terpotong oleh
         overflow:hidden pada nav topbar serta bebas dari stacking-context halaman. --}}
    <template x-teleport="body">
        <div x-ref="langPanel" x-show="isLanguageSwitcherOpen"
            x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
            class="lang-dd fixed rounded-lg shadow-2xl divide-y ring-1 bg-white divide-gray-100 ring-gray-950/10 dark:bg-gray-900 dark:divide-white/5 dark:ring-white/20"
            style="z-index:100000; min-width:220px; max-height:220px; overflow-y:scroll; scrollbar-width:none; -ms-overflow-style:none;"
            x-cloak>
        <style>
            .lang-dd::-webkit-scrollbar {
                display: none !important;
                width: 0 !important;
            }
        </style>
        <div class="p-1 w-full">
            @foreach($locals as $key => $language)
                @php
                    $isCurrent = $currentLocale === $key;
                    $flag = $language['flag'] ?? 'gb';
                    $label = match ($key) { 'en_US' => 'US', 'en' => 'UK', default => strtoupper($key)};
                @endphp
                <a href="{{ $isCurrent ? 'javascript:void(0)' : NativeServiceProvider::normalizeUrl(route('language.switch', ['locale' => $key])) }}" @class([
                    'group flex items-center w-full justify-between gap-3 whitespace-nowrap rounded-md p-2 text-sm outline-none transition-all',
                    'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-white/5' => !$isCurrent,
                        "$activeColorClass font-bold cursor-default" => $isCurrent,
                ])>
                    <span class="truncate flex-1 text-start">{{ __($language['label']) }}</span>
                    <div class="w-6 h-4 shrink-0 bg-cover bg-center rounded-sm border border-gray-200 dark:border-gray-700 shadow-sm"
                        style="background-image: url('https://cdn.jsdelivr.net/gh/hampusborgos/country-flags@main/svg/{{ $flag }}.svg');">
                    </div>
                </a>
            @endforeach
        </div>
        </div>
    </template>
</div>
