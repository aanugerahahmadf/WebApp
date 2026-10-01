<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div>
        <div class="">
            <div class="text-sm text-gray-600">
                <div class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                    {{ __('Jika perlu, Anda dapat keluar dari semua sesi browser di semua perangkat Anda. Beberapa sesi terbaru Anda tercantum di bawah ini; namun, daftar ini mungkin tidak lengkap. Jika Anda merasa akun Anda telah disusupi, Anda juga harus memperbarui kata sandi Anda.') }}
                </div>
                @if (count($data) > 0)
                    {{-- Filament v3 Table Header Style --}}
                    @php
                        $otherDevicesCount = collect($data)->where('is_current_device', false)->count();
                        $selectedCount = count($this->selectedSessions ?? []);
                    @endphp

                    <div class="-mx-4 px-4 bg-white dark:bg-gray-900 mb-6">
                        <div class="h-12 flex items-center">
                            @if ($selectedCount > 0)
                                {{-- Filament v3 Selection Bar Style --}}
                                <div class="flex-1 flex items-center justify-between gap-x-3 px-3 py-1.5 rounded-lg bg-primary-50 dark:bg-primary-500/10 border border-primary-200 dark:border-primary-500/30">
                                    <div class="flex items-center gap-x-3">
                                        <span class="text-sm font-bold text-primary-700 dark:text-primary-400">
                                            {{ __(':count terpilih', ['count' => $selectedCount]) }}
                                        </span>
                                        <div class="h-4 w-px bg-primary-200 dark:bg-primary-500/30"></div>
                                        <button 
                                            type="button"
                                            wire:click="$set('selectedSessions', [])"
                                            class="text-xs font-semibold text-primary-600 dark:text-primary-500 hover:underline"
                                        >
                                            {{ __('Batalkan semua') }}
                                        </button>
                                    </div>

                                    <x-filament::dropdown placement="bottom-end">
                                        <x-slot name="trigger">
                                            <x-filament::button
                                                color="gray"
                                                size="sm"
                                                icon="heroicon-m-chevron-down"
                                                icon-position="after"
                                                class="bg-white dark:bg-gray-800 shadow-sm ring-1 ring-gray-950/10 dark:ring-white/20"
                                            >
                                                {{ __('Tindakan massal') }}
                                            </x-filament::button>
                                        </x-slot>

                                        <x-filament::dropdown.list>
                                            <x-filament::dropdown.list.item
                                                color="danger"
                                                icon="heroicon-m-trash"
                                                wire:click="deleteSelectedSessions"
                                                wire:confirm="{{ __('Apakah Anda yakin ingin keluar dari :count sesi yang terpilih?', ['count' => $selectedCount]) }}"
                                            >
                                                {{ __('Keluar dari sesi terpilih') }}
                                            </x-filament::dropdown.list.item>
                                        </x-filament::dropdown.list>
                                    </x-filament::dropdown>
                                </div>
                            @else
                                {{-- Default State --}}
                                <div class="flex items-center gap-x-3">
                                    <x-filament::input.checkbox
                                        wire:model.live="selectAll"
                                        class="rounded"
                                    />
                                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        {{ __(':count sesi lainnya', ['count' => $otherDevicesCount]) }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="">
                        @foreach ($data as $session)
                            @php
                                $isSelected = in_array($session->id, $this->selectedSessions ?? []);
                            @endphp
                            <div 
                                @class([
                                    'flex items-center justify-between p-3 transition-colors group',
                                    'bg-primary-50/30 dark:bg-primary-400/5' => $isSelected && !$session->is_current_device,
                                    'hover:bg-gray-50/50 dark:hover:bg-white/5' => !$isSelected,
                                ])
                            >
                                <div class="flex items-center flex-1">
                                    {{-- Checkbox --}}
                                    <div class="me-4 @if($session->is_current_device) invisible @endif">
                                        <x-filament::input.checkbox
                                            wire:model.live="selectedSessions"
                                            value="{{ $session->id }}"
                                            class="rounded"
                                        />
                                    </div>

                                    <div class="flex items-center">
                                        <div class="p-2 rounded-lg @if($session->is_current_device) bg-primary-100 dark:bg-primary-500/20 @else bg-gray-100 dark:bg-gray-800 @endif">
                                            @if ($session->device['desktop'])
                                                <x-filament::icon
                                                    icon="heroicon-o-computer-desktop"
                                                    @class([
                                                        'w-5 h-5',
                                                        'text-primary-600 dark:text-primary-400' => $session->is_current_device,
                                                        'text-gray-500 dark:text-gray-400' => !$session->is_current_device,
                                                    ])
                                                />
                                            @else
                                                <x-filament::icon
                                                    icon="heroicon-o-device-phone-mobile"
                                                    @class([
                                                        'w-5 h-5',
                                                        'text-primary-600 dark:text-primary-400' => $session->is_current_device,
                                                        'text-gray-500 dark:text-gray-400' => !$session->is_current_device,
                                                    ])
                                                />
                                            @endif
                                        </div>

                                        <div class="ms-3">
                                            <div class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-x-2">
                                                @if ($session->device['device_name'])
                                                    {{ $session->device['device_name'] }}
                                                @else
                                                    {{ $session->device['platform'] }} {{ $session->device['desktop'] ? __('Desktop') : ($session->device['mobile'] ? __('Seluler') : __('Perangkat')) }}
                                                @endif
                                                
                                                @if ($session->is_current_device)
                                                    <span class="flex items-center gap-x-1 text-[11px] font-medium text-green-600 dark:text-green-400">
                                                        <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                                                        {{ __('Aktif sekarang') }}
                                                    </span>
                                                @endif
                                            </div>

                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                {{ $session->device['browser'] }} • {{ $session->ip_address }} 
                                                @if (!$session->is_current_device)
                                                    • {{ __('Terakhir aktif :time', ['time' => $session->last_active]) }}
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-x-3">
                                    @if (!$session->is_current_device)
                                        <x-filament::button
                                            color="danger"
                                            size="sm"
                                            outlined
                                            icon="heroicon-m-arrow-left-on-rectangle"
                                            wire:click="deleteSession('{{ $session->id }}')"
                                            wire:confirm="{{ __('Apakah Anda yakin ingin keluar dari sesi ini?') }}"
                                            class="text-xs font-bold"
                                        >
                                            {{ __('Keluar') }}
                                        </x-filament::button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                @endif

            </div>
        </div>
    </div>
</x-dynamic-component>
