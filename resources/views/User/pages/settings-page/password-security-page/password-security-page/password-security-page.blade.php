{{--
    Daftar pengaturan keamanan. Murni penavigasi.

    Tiap butir punya halaman sendiri -- bukan ?section= dalam satu file.
    Alasannya: satu blade 150 baris dengan 7 cabang if/elseif membuat
    mustahil mencari bagian mana milik halaman tertentu, dan mengubah satu
    bagian berisiko merusak yang lain karena semuanya berbagi satu file.

    Label kelompok di bawah TIDAK ditulis di sini. Takenya dari
    HandlesPasswordSecurity::securityGroups() yang sama dipakai breadcrumb
    halaman detail -- kalau dua sumber terpisah, heading di daftar dan crumb
    di detail akan mulai berbeda begitu salah satunya diedit.
--}}
<x-filament-panels::page>
    <div class="mx-auto max-w-4xl space-y-6">
        @foreach ($this->securityGroups() as $group)
            <section>
                {{--
                    Label kelompok, bukan judul halaman: teksnya tampil
                    apa adanya hasil __() supaya ikut language switcher, dan
                    TIDAK diberi uppercase -- CSS akan memaksa huruf besar
                    pada teks yang sudah diterjemahkan, hasilnya bukan
                    "Sign In and Recovery" tapi "SIGN IN AND RECOVERY".

                    Warna gelap-terang (bukan abu-abu) supaya kontrasnya
                    sama di light, dark, dan mode sistem.
                --}}
                <h2 class="mb-3 text-lg font-bold tracking-wide text-gray-950 dark:text-white">
                    {{ $group['label'] }}
                </h2>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                    @foreach ($group['items'] as [$page, $icon, $label, $description])
                        <a
                            wire:navigate
                            href="{{ $page::getUrl(panel: 'user') }}"
                            class="flex items-center gap-4 border-b border-gray-100 p-4 last:border-0 transition hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5"
                        >
                            <x-filament::icon :icon="$icon" class="h-6 w-6 text-primary-500" />

                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-gray-950 dark:text-white">{{ $label }}</p>
                                <p class="text-sm text-gray-500">{{ $description }}</p>
                            </div>

                            <x-filament::icon icon="heroicon-o-chevron-right" class="h-5 w-5 text-gray-400" />
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>