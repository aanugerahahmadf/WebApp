@php
    $vouchers = collect();
    if (isset($records) && $records->isNotEmpty()) {
        $vouchers = $records;
    }
@endphp

@if($vouchers->isNotEmpty())
    <div class="flex overflow-x-auto gap-3 pb-2 snap-x snap-mandatory" style="-webkit-overflow-scrolling: touch;">
        @foreach($vouchers as $voucher)
            @php
                $discountAmount = $voucher->discount_amount;
                $discountType = $voucher->discount_type;
                $minPurchase = $voucher->min_purchase;
                $expiresAt = $voucher->expires_at;
                $isPercentage = $discountType === 'percentage';
            @endphp
            <div class="snap-start shrink-0 w-64 rounded-xl p-4 bg-gradient-to-br from-primary-500 to-primary-600 text-white relative overflow-hidden">
                <div class="absolute top-0 right-0 w-16 h-16 bg-white/10 rounded-full -translate-y-1/2 translate-x-1/2"></div>
                <div class="absolute bottom-0 left-0 w-10 h-10 bg-white/10 rounded-full translate-y-1/2 -translate-x-1/2"></div>

                <div class="relative z-10">
                    <div class="text-2xl font-bold mb-0.5">
                        @if($isPercentage)
                            {{ number_format($discountAmount, 0, ',', '.') }}%
                        @else
                            Rp{{ number_format($discountAmount, 0, ',', '.') }}
                        @endif
                    </div>
                    <div class="text-xs font-medium text-white/80 mb-2">
                        {{ $isPercentage ? __('Persen') : __('Fixed') }}
                    </div>

                    @if($minPurchase > 0)
                        <div class="text-[10px] text-white/70 mb-1">
                            {{ __('Min. pembelian') }}: Rp{{ number_format($minPurchase, 0, ',', '.') }}
                        </div>
                    @endif

                    @if($expiresAt)
                        <div class="text-[10px] text-white/70 mb-2">
                            {{ __('Berlaku hingga') }}: {{ $expiresAt->format('d M Y') }}
                        </div>
                    @endif

                    <div class="flex items-center justify-between mt-2 pt-2 border-t border-white/20">
                        <code class="text-xs font-mono font-bold tracking-wider">{{ $voucher->code }}</code>
                        <button
                            type="button"
                            onclick="navigator.clipboard.writeText('{{ $voucher->code }}').then(() => { this.textContent = '{{ __("Copied!") }}'; setTimeout(() => { this.textContent = 'Copy'; }, 1500); })"
                            class="text-[10px] bg-white/20 hover:bg-white/30 rounded-md px-2 py-0.5 font-medium transition-colors"
                        >
                            {{ __('Copy') }}
                        </button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@else
    <div class="text-center py-4 text-sm text-gray-400 dark:text-gray-500">
        {{ __('Belum ada promo') }}
    </div>
@endif
