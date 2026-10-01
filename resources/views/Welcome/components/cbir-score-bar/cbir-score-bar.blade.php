@props([
    'score' => 0,
    'pct'   => 0,
])

@php
    $barColor  = $score >= 0.85 ? 'bg-emerald-500' : ($score >= 0.65 ? 'bg-amber-500' : 'bg-gray-400');
    $textColor = $score >= 0.85 ? 'text-emerald-600' : ($score >= 0.65 ? 'text-amber-600' : 'text-gray-400');
@endphp

<div class="flex items-center gap-2 mt-1">
    <div class="flex-1 h-1.5 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
        <div class="{{ $barColor }} h-full rounded-full transition-all duration-700"
             style="width: {{ $pct }}%">
        </div>
    </div>
    <span class="text-[10px] font-bold {{ $textColor }}">
        {{ $pct }}% MIRIP
    </span>
</div>
