@php
    $livewire = (isset($getLivewire) && is_callable($getLivewire)) ? $getLivewire() : ($this ?? null);
    $actions = $livewire ? $livewire->getCachedFormActions() : [];
@endphp

<div class="w-full flex flex-col gap-3 pt-3 border-t border-gray-100 dark:border-gray-800">
    @if (count($actions))
        <div class="w-full">
            <x-filament::actions
                :actions="$actions"
                :full-width="true"
            />
        </div>
    @endif

    <div class="w-full text-center text-sm text-gray-600 dark:text-gray-400 mt-2"
         x-data="{
             timeLeft: @js($livewire->resendCooldown ?? 0),
             interval: null,
             
             formatTime(seconds) {
                 let m = Math.floor(seconds / 60);
                 let s = seconds % 60;
                 return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
             },
             startTimer() {
                 clearInterval(this.interval);
                 if (this.timeLeft > 0) {
                     this.interval = setInterval(() => {
                         if (this.timeLeft > 0) {
                             this.timeLeft--;
                         } else {
                             clearInterval(this.interval);
                         }
                     }, 1000);
                 }
             },
             init() {
                 this.startTimer();
             }
         }"
         x-on:otp-resent.window="timeLeft = 300; startTimer();"
    >
        <p class="mb-2">{{ __('Tidak menerima email?') }}</p>

        <template x-if="timeLeft > 0">
            <div class="flex items-center justify-center gap-2 text-primary-600 font-medium">
                <x-filament::loading-indicator class="h-4 w-4" />
                <span>{{ __('Kirim ulang tersedia dalam') }} <span x-text="formatTime(timeLeft)"></span></span>
            </div>
        </template>

        <div x-show="timeLeft <= 0">
            {{ $livewire->resendNotificationAction }}
        </div>

        <div class="mt-4 flex justify-center">
            <x-filament::button
                type="button"
                wire:click="logoutAndReturnToLogin"
                wire:confirm="{{ __('Keluar dan kembali ke halaman login?') }}"
                color="gray"
                icon="heroicon-m-arrow-left-on-rectangle"
            >
                {{ __('Keluar dan Kembali ke Login') }}
            </x-filament::button>
        </div>
    </div>
</div>
