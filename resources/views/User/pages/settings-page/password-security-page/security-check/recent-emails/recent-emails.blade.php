{{-- Email terbaru ke akun, 30 hari terakhir. --}}
<x-filament-panels::page>
    <div class="mx-auto max-w-4xl space-y-6">
        <x-filament::section
            :heading="__('Ubah Email')"
            :description="__('Masukkan email baru, lalu verifikasi kode OTP yang dikirimkan ke email tersebut.')"
        >
            <x-filament-panels::form wire:submit="{{ $emailOtpSent ? 'verifyEmailChangeOtp' : 'sendEmailChangeOtp' }}">
                {{ $this->changeEmailForm }}

                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    @if ($emailOtpSent)
                        <x-filament::button type="button" wire:click="sendEmailChangeOtp" color="gray" icon="heroicon-m-arrow-path">
                            {{ __('Kirim Ulang OTP') }}
                        </x-filament::button>
                    @endif

                    <x-filament::button type="submit" :icon="$emailOtpSent ? 'heroicon-m-check-circle' : 'heroicon-m-paper-airplane'">
                        {{ $emailOtpSent ? __('Verifikasi OTP') : __('Kirim Kode OTP') }}
                    </x-filament::button>
                </div>
            </x-filament-panels::form>
        </x-filament::section>

        <x-filament::section :heading="__('Email Terbaru')">
            @forelse ($emails as $email)
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 py-3 last:border-0 dark:border-white/10">
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ $email->subject }}</p>
                        <p class="text-xs text-gray-500">
                            {{ $email->sent_at?->translatedFormat('d M Y H:i') }}
                        </p>
                    </div>

                    <span class="shrink-0 text-xs text-gray-500">{{ $email->type === 'email_changed' ? __('Keamanan') : __('Lainnya') }}</span>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-gray-500">{{ __('Tidak ada email dalam periode ini.') }}</p>
            @endforelse

            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                {{ __('Hanya menampilkan email dari 30 hari terakhir.') }}
            </p>
        </x-filament::section>
    </div>
</x-filament-panels::page>