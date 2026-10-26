@php
    // Satu-satunya tempat rating dirender sebagai bintang. Dipakai kartu
    // katalog, pilihan "Rating minimum", dan chip filter aktif.
    //
    // `max` membatasi jumlah bintang yang digambar: kartu memakai 5 (skala
    // penuh), sedangkan pilihan "Rating minimum" hanya menggambar bintang yang
    // dicakup threshold (3+, 4+, 4,5+) supaya tidak ada angka sama sekali.
    $starsMax = max(1, (int) ($max ?? 5));
    $starsValue = max(0.0, min((float) $starsMax, (float) ($value ?? 0)));
    $fullStars = (int) floor($starsValue);
    $fraction = $starsValue - $fullStars;
    $hasHalfStar = $fraction >= 0.25 && $fraction < 0.75;
    $fullStars += $fraction >= 0.75 ? 1 : 0;
@endphp

<span class="catalog-stars" aria-hidden="true"@if(! empty($size)) style="font-size: {{ $size }};"@endif>
    @for($star = 1; $star <= $starsMax; $star++)
        @if($star <= $fullStars)
            <span class="catalog-star is-filled">&#9733;</span>
        @elseif($hasHalfStar && $star === $fullStars + 1)
            <span class="catalog-star is-half">&#9733;</span>
        @else
            <span class="catalog-star">&#9733;</span>
        @endif
    @endfor
</span>
