<?php

/**
 * Penulis ulang seluruh ikon navigasi sebagai SVG berwarna.
 *
 * Setiap ikon punya DUA warna:
 *   - bentuk utama  = warna utama (mid-tone, terbaca di terang & gelap)
 *   - aksen/detail  = warna sekunder
 *
 * Warna mid-tone dipilih supaya ikon tetap terbaca di dark mode tanpa perlu
 * filter CSS, dan tetap Tcocok di light mode.
 */

$dir = __DIR__.'/../public/images/icons';

@mkdir($dir, 0777, true);

function svg(string $body, string $main, string $accent): string
{
    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none">
{$body}
</svg>
SVG;
}

$icons = [];

// ---------------------------------------------------------------- storefront

$icons['flower'] = [
    'main' => '#E11D48', 'accent' => '#FBBF24',
    'body' => <<<'B'
  <g stroke="#E11D48" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    <ellipse cx="12" cy="7.6" rx="2.1" ry="3.3"/>
    <ellipse cx="12" cy="7.6" rx="2.1" ry="3.3" transform="rotate(72 12 12)"/>
    <ellipse cx="12" cy="7.6" rx="2.1" ry="3.3" transform="rotate(144 12 12)"/>
    <ellipse cx="12" cy="7.6" rx="2.1" ry="3.3" transform="rotate(216 12 12)"/>
    <ellipse cx="12" cy="7.6" rx="2.1" ry="3.3" transform="rotate(288 12 12)"/>
  </g>
  <circle cx="12" cy="12" r="2" fill="#FBBF24"/>
  <path d="M12 14v5.2" stroke="#E11D48" stroke-width="1.6" stroke-linecap="round"/>
  <path d="M12 16.8c2.3 0 3.6-1.4 3.6-3.2" stroke="#E11D48" stroke-width="1.6" stroke-linecap="round"/>
B,
];

$icons['order'] = [
    'main' => '#D97706', 'accent' => '#FBBF24',
    'body' => <<<'B'
  <path d="M5.4 8h13.2l1 12.2H4.4L5.4 8Z" stroke="#D97706" stroke-width="1.6" stroke-linejoin="round"/>
  <path d="M8.6 10.4V6.9a3.4 3.4 0 0 1 6.8 0v3.5" stroke="#D97706" stroke-width="1.6" stroke-linecap="round"/>
  <path d="M8.6 12.6c1.2 1.3 2.3 1.9 3.4 1.9s2.2-.6 3.4-1.9" stroke="#FBBF24" stroke-width="1.6" stroke-linecap="round"/>
B,
];

$icons['history'] = [
    'main' => '#0284C7', 'accent' => '#38BDF8',
    'body' => <<<'B'
  <circle cx="12" cy="12" r="8.4" stroke="#0284C7" stroke-width="1.6"/>
  <path d="M12 7.2V12l3.4 2.2" stroke="#0284C7" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
  <path d="M12 3.6a8.4 8.4 0 0 1 6.6 3.2" stroke="#38BDF8" stroke-width="1.6" stroke-linecap="round"/>
B,
];

$icons['review'] = [
    'main' => '#D97706', 'accent' => '#FBBF24',
    'body' => <<<'B'
  <path d="m12 3.6 2.5 5.2 5.7.8-4.1 4 1 5.7-5.1-2.7-5.1 2.7 1-5.7-4.1-4 5.7-.8L12 3.6Z" stroke="#D97706" stroke-width="1.6" stroke-linejoin="round"/>
  <path d="m12 8.4 1.2 2.5 2.7.4-2 1.9.5 2.7-2.4-1.3-2.4 1.3.5-2.7-2-1.9 2.7-.4L12 8.4Z" fill="#FBBF24" stroke="none"/>
B,
];

$icons['voucher'] = [
    'main' => '#7C3AED', 'accent' => '#A78BFA',
    'body' => <<<'B'
  <path d="M4 7.4h16v9.2H4z" stroke="#7C3AED" stroke-width="1.6" stroke-linejoin="round"/>
  <path d="M4 7.4 2.6 4.8M20 7.4l1.4-2.6" stroke="#7C3AED" stroke-width="1.6" stroke-linecap="round"/>
  <path d="M12 7.4v9.2" stroke="#A78BFA" stroke-width="1.6" stroke-dasharray="2 2.2"/>
  <circle cx="12" cy="12" r="1.6" fill="#A78BFA" stroke="none"/>
B,
];

$icons['wishlist'] = [
    'main' => '#E11D48', 'accent' => '#FDA4AF',
    'body' => <<<'B'
  <path d="M12 20.2S4 15.4 4 9.9A4.4 4.4 0 0 1 12 7a4.4 4.4 0 0 1 8 2.9c0 5.5-8 10.3-8 10.3Z" stroke="#E11D48" stroke-width="1.6" stroke-linejoin="round"/>
  <path d="M8.4 9.4c.3-1.3 1.2-2 2.6-2.2" stroke="#FDA4AF" stroke-width="1.6" stroke-linecap="round"/>
B,
];

$icons['cart'] = [
    'main' => '#059669', 'accent' => '#34D399',
    'body' => <<<'B'
  <path d="M2.8 4.4h2.6l2.3 10.4h9.9l2-7.2H6.2" stroke="#059669" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
  <circle cx="9.4" cy="19" r="1.6" stroke="#059669" stroke-width="1.6"/>
  <circle cx="16.8" cy="19" r="1.6" stroke="#059669" stroke-width="1.6"/>
  <circle cx="9.4" cy="19" r="1.6" fill="#34D399" stroke="none"/>
B,
];

$icons['settings'] = [
    'main' => '#475569', 'accent' => '#F59E0B',
    'body' => <<<'B'
  <circle cx="12" cy="12" r="3.1" stroke="#475569" stroke-width="1.6"/>
  <path d="M12 2.9v2.4M12 18.7v2.4M21.1 12h-2.4M5.3 12H2.9M18.4 5.6l-1.7 1.7M7.3 16.7l-1.7 1.7M18.4 18.4l-1.7-1.7M7.3 7.3 5.6 5.6" stroke="#475569" stroke-width="1.6" stroke-linecap="round"/>
  <circle cx="12" cy="12" r="1.2" fill="#F59E0B" stroke="none"/>
B,
];

$icons['profile'] = [
    'main' => '#4F46E5', 'accent' => '#818CF8',
    'body' => <<<'B'
  <circle cx="12" cy="8.4" r="3.8" stroke="#4F46E5" stroke-width="1.6"/>
  <path d="M4.8 20.2a7.4 7.4 0 0 1 14.4 0" stroke="#4F46E5" stroke-width="1.6" stroke-linecap="round"/>
  <path d="M12 5.9a2.5 2.5 0 0 0 0 5 2.5 2.5 0 0 0 0-5Z" fill="#818CF8" stroke="none"/>
B,
];

$icons['privacy'] = [
    'main' => '#0D9488', 'accent' => '#2DD4BF',
    'body' => <<<'B'
  <path d="M12 2.9 4.6 6v6.1c0 4.4 3 7.9 7.4 9 4.4-1.1 7.4-4.6 7.4-9V6L12 2.9Z" stroke="#0D9488" stroke-width="1.6" stroke-linejoin="round"/>
  <path d="m8.9 11.9 2.2 2.2 4-4.2" stroke="#2DD4BF" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
B,
];

$icons['help'] = [
    'main' => '#0284C7', 'accent' => '#38BDF8',
    'body' => <<<'B'
  <circle cx="12" cy="12" r="8.4" stroke="#0284C7" stroke-width="1.6"/>
  <path d="M9.6 9.5a2.5 2.5 0 0 1 4.9.7c0 1.7-2.5 1.9-2.5 3.5" stroke="#0284C7" stroke-width="1.6" stroke-linecap="round"/>
  <circle cx="12" cy="16.6" r="1" fill="#38BDF8" stroke="none"/>
B,
];

$icons['external-link'] = [
    'main' => '#7C3AED', 'accent' => '#A78BFA',
    'body' => <<<'B'
  <path d="M13.4 4.6H19a.9.9 0 0 1 .9.9v5.6" stroke="#7C3AED" stroke-width="1.6" stroke-linecap="round"/>
  <path d="M19.9 5.1 11.6 13.4" stroke="#7C3AED" stroke-width="1.6" stroke-linecap="round"/>
  <path d="M17.4 14.3v4.3a.9.9 0 0 1-.9.9H4.9a.9.9 0 0 1-.9-.9V6.6a.9.9 0 0 1 .9-.9h4.3" stroke="#7C3AED" stroke-width="1.6" stroke-linecap="round"/>
  <circle cx="16.2" cy="16.2" r="2.6" fill="#A78BFA" stroke="none" opacity="0.3"/>
B,
];

$icons['home'] = [
    'main' => '#D97706', 'accent' => '#FBBF24',
    'body' => <<<'B'
  <path d="M3.4 10.4 12 3.6l8.6 6.8V19a1.4 1.4 0 0 1-1.4 1.4H4.8A1.4 1.4 0 0 1 3.4 19v-8.6Z" stroke="#D97706" stroke-width="1.6" stroke-linejoin="round"/>
  <path d="M9.6 20.4v-5.2h4.8v5.2" stroke="#FBBF24" stroke-width="1.6" stroke-linejoin="round"/>
B,
];

$icons['messages'] = [
    'main' => '#0284C7', 'accent' => '#38BDF8',
    'body' => <<<'B'
  <path d="M4 5.6h16a1 1 0 0 1 1 1v8.8a1 1 0 0 1-1 1H9.4L5 19.8V16.4H4a1 1 0 0 1-1-1V6.6a1 1 0 0 1 1-1Z" stroke="#0284C7" stroke-width="1.6" stroke-linejoin="round"/>
  <path d="M7.8 9.1h8.4M7.8 12.2h5.4" stroke="#38BDF8" stroke-width="1.6" stroke-linecap="round"/>
B,
];

$icons['export'] = [
    'main' => '#059669', 'accent' => '#34D399',
    'body' => <<<'B'
  <path d="M12 3.6v10.8" stroke="#059669" stroke-width="1.6" stroke-linecap="round"/>
  <path d="m7.8 10.2 4.2 4.2 4.2-4.2" stroke="#059669" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
  <path d="M4.4 15.4v3.2a1.8 1.8 0 0 0 1.8 1.8h11.6a1.8 1.8 0 0 0 1.8-1.8v-3.2" stroke="#34D399" stroke-width="1.6" stroke-linecap="round"/>
B,
];

$icons['gift'] = [
    'main' => '#BE123C', 'accent' => '#FBBF24',
    'body' => <<<'B'
  <path d="M3.6 11.2h16.8v9.2H3.6v-9.2Z" stroke="#BE123C" stroke-width="1.6" stroke-linejoin="round"/>
  <path d="M2.4 7.6h19.2v3.6H2.4V7.6Z" stroke="#BE123C" stroke-width="1.6" stroke-linejoin="round"/>
  <path d="M12 7.6v12.8" stroke="#BE123C" stroke-width="1.6"/>
  <path d="M12 7.6C10.6 4.4 9 2.9 7.2 3.3 5.4 3.7 5 5.8 6.2 7.2c.9 1 2.9 1.4 5.8.4Z" stroke="#BE123C" stroke-width="1.6" stroke-linejoin="round"/>
  <path d="M12 7.6c1.4-3.2 3-4.7 4.8-4.3 1.8.4 2.2 2.5 1 3.9-.9 1-2.9 1.4-5.8.4Z" stroke="#BE123C" stroke-width="1.6" stroke-linejoin="round"/>
  <circle cx="12" cy="9.4" r="1.3" fill="#FBBF24" stroke="none"/>
B,
];

$icons['sparkles'] = [
    'main' => '#D97706', 'accent' => '#FBBF24',
    'body' => <<<'B'
  <path d="m12 2.8 1.9 5.1 5.1 1.9-5.1 1.9L12 16.8l-1.9-5.1L5 9.8l5.1-1.9L12 2.8Z" stroke="#D97706" stroke-width="1.6" stroke-linejoin="round"/>
  <path d="m18.6 14.4.9 2.3 2.3.9-2.3.9-.9 2.3-.9-2.3-2.3-.9 2.3-.9.9-2.3Z" stroke="#FBBF24" stroke-width="1.4" stroke-linejoin="round"/>
  <path d="m5.2 15 .7 1.8 1.8.7-1.8.7-.7 1.8-.7-1.8-1.8-.7 1.8-.7.7-1.8Z" stroke="#FBBF24" stroke-width="1.3" stroke-linejoin="round"/>
B,
];

$icons['arrow-right'] = [
    'main' => '#D97706', 'accent' => '#FBBF24',
    'body' => <<<'B'
  <path d="M4.2 12h14.2" stroke="#D97706" stroke-width="1.8" stroke-linecap="round"/>
  <path d="m13.4 7 4.6 5-4.6 5" stroke="#D97706" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
  <circle cx="19.8" cy="12" r="1.5" fill="#FBBF24" stroke="none"/>
B,
];

// ------------------------------------------------------------------- admin

$icons['admin-shield'] = [
    'main' => '#4F46E5', 'accent' => '#818CF8',
    'body' => <<<'B'
  <path d="M12 2.9 4.6 6v6.1c0 4.4 3 7.9 7.4 9 4.4-1.1 7.4-4.6 7.4-9V6L12 2.9Z" stroke="#4F46E5" stroke-width="1.6" stroke-linejoin="round"/>
  <circle cx="12" cy="10.2" r="2.1" stroke="#818CF8" stroke-width="1.5"/>
  <path d="M8.6 15.6a3.6 3.6 0 0 1 6.8 0" stroke="#818CF8" stroke-width="1.5" stroke-linecap="round"/>
B,
];

$icons['api-key'] = [
    'main' => '#D97706', 'accent' => '#FBBF24',
    'body' => <<<'B'
  <circle cx="8" cy="8" r="4" stroke="#D97706" stroke-width="1.6"/>
  <path d="m11 11 8.2 8.2M16.4 16.4l2.2-2.2M18.6 18.6l1.8-1.8" stroke="#D97706" stroke-width="1.6" stroke-linecap="round"/>
  <circle cx="8" cy="8" r="1.4" fill="#FBBF24" stroke="none"/>
B,
];

$icons['database'] = [
    'main' => '#4F46E5', 'accent' => '#818CF8',
    'body' => <<<'B'
  <ellipse cx="12" cy="6" rx="7.4" ry="3" stroke="#4F46E5" stroke-width="1.6"/>
  <path d="M4.6 6v12c0 1.7 3.3 3 7.4 3s7.4-1.3 7.4-3V6" stroke="#4F46E5" stroke-width="1.6"/>
  <path d="M4.6 12c0 1.7 3.3 3 7.4 3s7.4-1.3 7.4-3" stroke="#818CF8" stroke-width="1.6"/>
B,
];

$icons['code'] = [
    'main' => '#059669', 'accent' => '#34D399',
    'body' => <<<'B'
  <path d="m8.6 8.2-4 3.8 4 3.8M15.4 8.2l4 3.8-4 3.8" stroke="#059669" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
  <path d="m13.4 5.4-2.8 13.2" stroke="#34D399" stroke-width="1.6" stroke-linecap="round"/>
B,
];

$icons['discount'] = [
    'main' => '#BE123C', 'accent' => '#FDA4AF',
    'body' => <<<'B'
  <path d="M11.6 3.4H5.9a2 2 0 0 0-2 2v13.2a2 2 0 0 0 2 2h4.5" stroke="#BE123C" stroke-width="1.6" stroke-linecap="round"/>
  <path d="M12.4 3.4h5.7a2 2 0 0 1 2 2v13.2a2 2 0 0 1-2 2h-4.5" stroke="#BE123C" stroke-width="1.6" stroke-linecap="round"/>
  <circle cx="12" cy="12" r="2.4" stroke="#FDA4AF" stroke-width="1.6"/>
  <path d="m13.4 10.6 2.6-2.6M10.6 13.4 8 16" stroke="#FDA4AF" stroke-width="1.4" stroke-linecap="round"/>
B,
];

$icons['maintenance'] = [
    'main' => '#D97706', 'accent' => '#FBBF24',
    'body' => <<<'B'
  <path d="M15.6 3.6a4.9 4.9 0 0 0-6 6L3.9 15.3a1.6 1.6 0 0 0 0 2.3l2.5 2.5a1.6 1.6 0 0 0 2.3 0l5.7-5.7a4.9 4.9 0 0 0 6-6l-3.1 3.1-2.9-.7-.7-2.9 3.1-3.1Z" stroke="#D97706" stroke-width="1.6" stroke-linejoin="round"/>
  <circle cx="15.4" cy="8.6" r="1.5" fill="#FBBF24" stroke="none"/>
B,
];

$icons['payment-gateway'] = [
    'main' => '#059669', 'accent' => '#34D399',
    'body' => <<<'B'
  <rect x="3.4" y="4.2" width="17.2" height="5.2" rx="1.2" stroke="#059669" stroke-width="1.6"/>
  <rect x="3.4" y="14.6" width="17.2" height="5.2" rx="1.2" stroke="#059669" stroke-width="1.6"/>
  <circle cx="7" cy="6.8" r="1.2" fill="#34D399" stroke="none"/>
  <circle cx="7" cy="17.2" r="1.2" fill="#34D399" stroke="none"/>
  <path d="M13 6.8h4M13 17.2h4" stroke="#059669" stroke-width="1.6" stroke-linecap="round"/>
B,
];

$icons['payment-method'] = [
    'main' => '#7C3AED', 'accent' => '#A78BFA',
    'body' => <<<'B'
  <rect x="2.6" y="5" width="18.8" height="14" rx="2.2" stroke="#7C3AED" stroke-width="1.6"/>
  <path d="M2.6 9.8h18.8" stroke="#7C3AED" stroke-width="1.6"/>
  <path d="M6.2 14.8h3.4" stroke="#A78BFA" stroke-width="1.8" stroke-linecap="round"/>
B,
];

$icons['reference'] = [
    'main' => '#475569', 'accent' => '#94A3B8',
    'body' => <<<'B'
  <path d="M8.6 6.4h11.8M8.6 12h11.8M8.6 17.6h11.8" stroke="#475569" stroke-width="1.6" stroke-linecap="round"/>
  <circle cx="4.6" cy="6.4" r="1.4" fill="#94A3B8" stroke="none"/>
  <circle cx="4.6" cy="12" r="1.4" fill="#94A3B8" stroke="none"/>
  <circle cx="4.6" cy="17.6" r="1.4" fill="#94A3B8" stroke="none"/>
B,
];

$icons['report'] = [
    'main' => '#D97706', 'accent' => '#FBBF24',
    'body' => <<<'B'
  <path d="M5.4 3.8v16.4" stroke="#D97706" stroke-width="1.6" stroke-linecap="round"/>
  <path d="M5.4 4.6c4.6-1.4 8.4-.6 12.2.4v9.6c-3.8-1-7.6-1.8-12.2-.4V4.6Z" stroke="#D97706" stroke-width="1.6" stroke-linejoin="round"/>
  <path d="M8.6 8.2c1.8-.4 3.4-.3 5 .1" stroke="#FBBF24" stroke-width="1.6" stroke-linecap="round"/>
B,
];

$icons['terms'] = [
    'main' => '#0284C7', 'accent' => '#38BDF8',
    'body' => <<<'B'
  <path d="M6 3.4h7.6L19 8.8v11.8H6V3.4Z" stroke="#0284C7" stroke-width="1.6" stroke-linejoin="round"/>
  <path d="M13.4 3.6v5.2H19" stroke="#0284C7" stroke-width="1.6" stroke-linejoin="round"/>
  <path d="M9 12.4h6.4M9 15.8h4.4" stroke="#38BDF8" stroke-width="1.6" stroke-linecap="round"/>
B,
];

$icons['general-document'] = [
    'main' => '#0284C7', 'accent' => '#38BDF8',
    'body' => <<<'B'
  <path d="M6 3.4h7.6L19 8.8v11.8H6V3.4Z" stroke="#0284C7" stroke-width="1.6" stroke-linejoin="round"/>
  <path d="M13.4 3.6v5.2H19" stroke="#0284C7" stroke-width="1.6" stroke-linejoin="round"/>
  <path d="M9.2 12.6h5.6M9.2 15.8h3.4" stroke="#38BDF8" stroke-width="1.6" stroke-linecap="round"/>
B,
];

$icons['transaction'] = [
    'main' => '#059669', 'accent' => '#34D399',
    'body' => <<<'B'
  <rect x="2.6" y="6.2" width="18.8" height="11.6" rx="1.8" stroke="#059669" stroke-width="1.6"/>
  <circle cx="12" cy="12" r="2.6" stroke="#059669" stroke-width="1.6"/>
  <path d="M6 9.2v1M18 13.8v1" stroke="#34D399" stroke-width="1.8" stroke-linecap="round"/>
B,
];

$icons['users'] = [
    'main' => '#4F46E5', 'accent' => '#818CF8',
    'body' => <<<'B'
  <circle cx="9.4" cy="8.4" r="3.6" stroke="#4F46E5" stroke-width="1.6"/>
  <path d="M2.9 20a6.6 6.6 0 0 1 13 0" stroke="#4F46E5" stroke-width="1.6" stroke-linecap="round"/>
  <path d="M16.4 5.2a3.4 3.4 0 0 1 0 6.6" stroke="#818CF8" stroke-width="1.6" stroke-linecap="round"/>
  <path d="M17.6 14.4a5.4 5.4 0 0 1 3.5 5" stroke="#818CF8" stroke-width="1.6" stroke-linecap="round"/>
B,
];

$icons['customizer'] = [
    'main' => '#BE123C', 'accent' => '#FDA4AF',
    'body' => <<<'B'
  <path d="M16.8 3.4c1.7 1.3 2.6 3.3 2.6 5.6 0 2.2-.9 4.2-2.6 5.5-1.1-1.1-1.7-2.8-1.7-5.5 0-2.6.6-4.3 1.7-5.6Z" stroke="#BE123C" stroke-width="1.6" stroke-linejoin="round"/>
  <path d="M19.4 14.6c1.4 1.2 2.2 2.9 2.2 4.9H15.9" stroke="#BE123C" stroke-width="1.6" stroke-linecap="round"/>
  <path d="M4.6 19.5c0-3.4 2.2-6 5-6s5 2.6 5 6" stroke="#BE123C" stroke-width="1.6" stroke-linecap="round"/>
  <path d="M7.4 3.6c1.1 1.2 1.7 2.9 1.7 5.4 0 2.7-.6 4.4-1.7 5.6-1.7-1.3-2.6-3.3-2.6-5.6 0-2.3.9-4.3 2.6-5.4Z" stroke="#FDA4AF" stroke-width="1.6" stroke-linejoin="round"/>
B,
];

$icons['wedding-policy'] = [
    'main' => '#7C3AED', 'accent' => '#A78BFA',
    'body' => <<<'B'
  <path d="M5.6 2.9h8.2l4.6 4.6v13.6H5.6V2.9Z" stroke="#7C3AED" stroke-width="1.6" stroke-linejoin="round"/>
  <path d="M13.4 3.1v4.6h4.6" stroke="#7C3AED" stroke-width="1.6" stroke-linejoin="round"/>
  <circle cx="10.4" cy="15" r="2.6" stroke="#A78BFA" stroke-width="1.6"/>
  <circle cx="14.4" cy="15" r="2.6" stroke="#A78BFA" stroke-width="1.6"/>
B,
];

$icons['sign-out'] = [
    'main' => '#BE123C', 'accent' => '#FDA4AF',
    'body' => <<<'B'
  <path d="M14.4 4.4H6.6a1.8 1.8 0 0 0-1.8 1.8v11.6a1.8 1.8 0 0 0 1.8 1.8h7.8" stroke="#BE123C" stroke-width="1.6" stroke-linecap="round"/>
  <path d="M10.4 12h9.2" stroke="#BE123C" stroke-width="1.6" stroke-linecap="round"/>
  <path d="m16.4 8.8 3.2 3.2-3.2 3.2" stroke="#BE123C" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
  <circle cx="19.8" cy="12" r="1.2" fill="#FDA4AF" stroke="none"/>
B,
];

foreach ($icons as $name => $spec) {
    $svg = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\" fill=\"none\">\n"
        .$spec['body']."\n</svg>\n";

    file_put_contents($dir.'/'.$name.'.svg', $svg);

    printf("%-18s main=%-8s accent=%s\n", $name.'.svg', $spec['main'], $spec['accent']);
}

echo 'TOTAL = '.count($icons)."\n";
