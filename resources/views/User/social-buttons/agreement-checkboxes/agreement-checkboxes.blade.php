<div class="w-full flex flex-col gap-3 my-3 px-1">
    <div class="flex items-center gap-3 group">
        <input type="checkbox" id="remember-me-checkbox" name="remember" x-model="remembered"
            class="fi-checkbox rounded border-gray-300 text-primary-500 shadow-sm focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:checked:bg-primary-500 transition duration-75 cursor-pointer" />
        <label for="remember-me-checkbox"
            class="text-sm font-semibold text-gray-500 dark:text-gray-400 leading-relaxed cursor-pointer select-none">
            {{ __('Ingat Saya') }}
        </label>
    </div>

    <div class="flex items-start gap-3 group">
        <div class="pt-0.5">
            <input type="checkbox" id="agreement-checkbox" name="agreement" x-model="agreed"
                x-on:change="if(agreed) $dispatch('open-agreement', { mode: 'wizard', step: 1 })"
                class="fi-checkbox rounded border-gray-300 text-primary-500 shadow-sm focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:checked:bg-primary-500 transition duration-75 cursor-pointer" />
        </div>
        <div class="text-sm font-semibold text-justify leading-relaxed text-gray-500 dark:text-gray-400 select-none"
            style="text-justify: inter-word; -webkit-hyphens: auto; hyphens: auto;">
            <label for="agreement-checkbox"
                class="cursor-pointer">{{ __('Dengan mencentang Setuju & Bergabung atau Lanjutkan, Anda menyetujui') }}</label>
            <x-filament::link color="primary" tag="button" type="button"
                class="text-sm font-bold hover:underline focus:underline active:underline"
                @click.stop="$dispatch('open-agreement', { mode: 'terms' })">{{ __('Perjanjian Pengguna') }}</x-filament::link>,
            <x-filament::link color="primary" tag="button" type="button"
                class="text-sm font-bold hover:underline focus:underline active:underline"
                @click.stop="$dispatch('open-agreement', { mode: 'privacy' })">{{ __('Kebijakan Privasi') }}</x-filament::link>
            <span>{{ __('dan') }}</span>
            <x-filament::link color="primary" tag="button" type="button"
                class="text-sm font-bold hover:underline focus:underline active:underline"
                @click.stop="$dispatch('open-agreement', { mode: 'wedding_policy' })">{{ __('Kebijakan Aplikasi') }}</x-filament::link>.
        </div>
    </div>
</div>
