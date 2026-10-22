#!/usr/bin/env python3
"""Unduh vektor dari https://fonts.google.com/icons (Material Symbols) lalu
timpa semua file ikon di public/images/icons/.

Sumber     : repo google/material-design-icons (sumber web fonts.google.com).
Gaya       : ROUNDED + FILL1 (isi solid, paling mirip tampilan emoji) dengan
              fallback OUTLINE kalau varian fill1 tidak ada.
Recolor    : vektor asli Google adalah monokrom `currentColor` -- warnanya
             diterapkan lewat template gradien + drop shadow + animasi CSS
             (satu template, dipakai semua ikon).

Setiap file yang gagal diunduh DIBIARKAN memakai versi lamanya dan
dilaporkan -- jadi script ini aman dijalankan ulang.
"""

import re
import sys
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / 'public' / 'images' / 'icons'

RAW = 'https://raw.githubusercontent.com/google/material-design-icons/master/symbols/web/{s}/materialsymbolsrounded/{s}_fill1_24px.svg'
FALLBACKS = [
    'https://raw.githubusercontent.com/google/material-design-icons/master/symbols/web/{s}/materialsymbolsrounded/{s}_24px.svg',
    'https://raw.githubusercontent.com/google/material-design-icons/master/symbols/web/{s}/materialsymbolsoutlined/{s}_fill1_24px.svg',
    'https://raw.githubusercontent.com/google/material-design-icons/master/symbols/web/{s}/materialsymbolsoutlined/{s}_24px.svg',
]

# file stem -> nama simbol Material Symbols (fonts.google.com/icons)
SYMBOLS = {
    'admin-shield': 'admin_panel_settings', 'alert': 'error',
    'api-key': 'key', 'archive': 'archive', 'archive_box': 'inventory_2',
    'arrow-right': 'arrow_forward', 'arrow_right': 'arrow_forward',
    'arrow_left': 'arrow_back', 'arrow_up': 'arrow_upward',
    'arrow_down': 'arrow_downward', 'arrow_circle': 'arrow_circle_right',
    'arrow_right_start': 'exit_to_app',
    'arrows_lr': 'swap_horiz', 'arrows_pointing_out': 'open_in_full',
    'arrows_up_down': 'unfold_more', 'at': 'alternate_email',
    'backspace': 'backspace', 'bag': 'shopping_bag',
    'banknotes': 'payments', 'beaker': 'science', 'bell': 'notifications',
    'bell_alert': 'notifications_active', 'bell_slash': 'notifications_off',
    'bold': 'format_bold', 'bolt': 'bolt', 'book': 'menu_book',
    'bookmark': 'bookmark', 'briefcase': 'work', 'brush': 'brush',
    'bug': 'bug_report', 'bulb': 'lightbulb', 'calendar': 'calendar_month',
    'calendar_days': 'calendar_month', 'camera': 'photo_camera',
    'card': 'credit_card', 'cart': 'shopping_cart',
    'cart_add': 'add_shopping_cart', 'chart': 'bar_chart', 'chat': 'chat',
    'chat_bubble': 'chat_bubble', 'chat_dots': 'forum', 'check': 'check',
    'check_badge': 'verified', 'check_circle': 'check_circle',
    'chevron_double_left': 'keyboard_double_arrow_left',
    'chevron_double_right': 'keyboard_double_arrow_right',
    'chevron_down': 'keyboard_arrow_down', 'chevron_left': 'chevron_left',
    'chevron_right': 'chevron_right',
    'chevron_right_left': 'unfold_less',
    'chevron_up': 'keyboard_arrow_up', 'chevron_up_down': 'unfold_more',
    'clipboard': 'content_paste', 'clipboard_check': 'task',
    'clock': 'schedule', 'cloud': 'cloud', 'code': 'code',
    'cog': 'settings', 'computer_desktop': 'desktop_windows',
    'cpu': 'memory', 'cube': 'view_in_ar', 'customizer': 'palette',
    'database': 'database', 'discount': 'sell', 'document': 'description',
    'dollar': 'attach_money', 'dots': 'more_horiz', 'download': 'download',
    'envelope': 'mail', 'export': 'cloud_download',
    'external-link': 'open_in_new', 'external': 'open_in_new',
    'eye': 'visibility', 'eye_slash': 'visibility_off',
    'filter': 'filter_alt', 'flag': 'flag', 'flower': 'local_florist',
    'flower_alt': 'local_florist', 'folder': 'folder',
    'general-document': 'description', 'gift': 'redeem', 'globe': 'public',
    'h1': 'format_h1', 'h2': 'format_h2', 'h3': 'format_h3',
    'hand': 'pan_tool', 'hashtag': 'numbers', 'heart': 'favorite',
    'help': 'help', 'history': 'history', 'home': 'home',
    'id_card': 'badge', 'info': 'info', 'italic': 'format_italic',
    'key': 'key', 'language': 'translate', 'library': 'local_library',
    'lifebuoy': 'support', 'link': 'link', 'list': 'list', 'lock': 'lock',
    'unlock': 'lock_open', 'maintenance': 'handyman', 'map': 'map',
    'map_pin': 'location_on', 'megaphone': 'campaign', 'menu': 'menu',
    'menu_alt': 'menu', 'messages': 'chat', 'microphone': 'mic',
    'minus': 'remove', 'minus_circle': 'do_not_disturb_on',
    'mobile': 'smartphone', 'moon': 'dark_mode', 'more': 'more_vert',
    'numbered_list': 'format_list_numbered', 'order': 'receipt_long',
    'paperclip': 'attach_file', 'payment-gateway': 'public',
    'payment-method': 'account_balance_wallet', 'pencil': 'edit',
    'percent': 'percent', 'phone': 'call', 'photo': 'image',
    'play': 'play_arrow', 'plus': 'add', 'plus_circle': 'add_circle',
    'privacy': 'verified_user', 'profile': 'account_circle',
    'puzzle': 'extension', 'qr': 'qr_code', 'qr_frame': 'qr_code_scanner',
    'question': 'help', 'reference': 'list_alt', 'refresh': 'refresh',
    'report': 'report', 'review': 'reviews', 'search': 'search',
    'send': 'send', 'server': 'dns', 'settings': 'settings', 'share': 'share',
    'shield_alert': 'gpp_maybe', 'shield_check': 'verified_user',
    'sign-out': 'logout', 'sign_in': 'login', 'sign_out': 'logout',
    'signal': 'signal_cellular_alt', 'smile': 'mood',
    'sparkles': 'auto_awesome', 'speaker': 'volume_up',
    'squares': 'grid_view', 'stack': 'layers', 'star': 'star',
    'strike': 'format_strikethrough', 'sun': 'light_mode',
    'swatch': 'palette', 'table': 'table_chart', 'tag': 'label',
    'tag_percent': 'sell', 'terms': 'article', 'text': 'text_fields',
    'text_badge': 'touch_app', 'thumb_up': 'thumb_up',
    'ticket': 'local_activity', 'transaction': 'currency_exchange',
    'trash': 'delete', 'trending_up': 'trending_up',
    'underline': 'format_underlined', 'upload': 'upload', 'user': 'person',
    'user_circle': 'account_circle', 'users': 'group',
    'uturn_left': 'u_turn_left', 'uturn_right': 'u_turn_right',
    'variable': 'data_object', 'video': 'videocam',
    'voucher': 'confirmation_number', 'wallet': 'account_balance_wallet',
    'warning': 'warning', 'wedding-policy': 'policy', 'wishlist': 'favorite',
    'wrench': 'build', 'x': 'close', 'x_circle': 'cancel',
    'zoom_in': 'zoom_in', 'zoom_out': 'zoom_out',
}

# Palet: sama seperti generator (terang -> base) supaya satu keluarga warna.
PALETTE = {
    'flower': ('#F9A8D4', '#DB2777', '#831843'), 'gift': ('#FCA5A5', '#DC2626', '#7F1D1D'),
    'bag': ('#FDBA74', '#EA580C', '#7C2D12'), 'cart': ('#6EE7B7', '#059669', '#064E3B'),
    'clock': ('#93C5FD', '#2563EB', '#1E3A8A'), 'star': ('#FDE68A', '#F59E0B', '#B45309'),
    'ticket': ('#F9A8D4', '#EC4899', '#831843'), 'heart': ('#FDA4AF', '#E11D48', '#881337'),
    'cog': ('#CBD5E1', '#64748B', '#1E293B'), 'user_circle': ('#93C5FD', '#3B82F6', '#1E3A8A'),
    'user': ('#93C5FD', '#3B82F6', '#1E3A8A'), 'users': ('#A5B4FC', '#6366F1', '#312E81'),
    'shield_check': ('#6EE7B7', '#10B981', '#065F46'),
    'shield_alert': ('#FCA5A5', '#EF4444', '#7F1D1D'),
    'question': ('#A5B4FC', '#6366F1', '#312E81'),
    'external': ('#7DD3FC', '#0EA5E9', '#0C4A6E'), 'sign_out': ('#FCA5A5', '#F87171', '#7F1D1D'),
    'sign_in': ('#6EE7B7', '#34D399', '#065F46'), 'home': ('#F9A8D4', '#EC4899', '#831843'),
    'chat': ('#93C5FD', '#3B82F6', '#1E3A8A'), 'chat_dots': ('#93C5FD', '#2563EB', '#1E3A8A'),
    'chat_bubble': ('#93C5FD', '#2563EB', '#1E3A8A'), 'review': ('#FDE68A', '#F59E0B', '#B45309'),
    'download': ('#6EE7B7', '#10B981', '#065F46'), 'upload': ('#7DD3FC', '#0EA5E9', '#0C4A6E'),
    'sparkles': ('#FDE68A', '#F59E0B', '#B45309'), 'key': ('#FDE68A', '#D97706', '#78350F'),
    'database': ('#A5B4FC', '#818CF8', '#312E81'), 'code': ('#6EE7B7', '#10B981', '#064E3B'),
    'dollar': ('#A7F3D0', '#10B981', '#064E3B'), 'wrench': ('#FDBA74', '#D97706', '#78350F'),
    'server': ('#A5B4FC', '#818CF8', '#1E1B4B'), 'card': ('#93C5FD', '#2563EB', '#1E3A8A'),
    'list': ('#CBD5E1', '#64748B', '#1E293B'), 'numbered_list': ('#CBD5E1', '#64748B', '#1E293B'),
    'flag': ('#FCA5A5', '#EF4444', '#7F1D1D'), 'document': ('#CBD5E1', '#94A3B8', '#1E293B'),
    'banknotes': ('#A7F3D0', '#059669', '#064E3B'), 'brush': ('#F9A8D4', '#EC4899', '#831843'),
    'swatch': ('#FBCFE8', '#DB2777', '#831843'), 'stack': ('#C4B5FD', '#8B5CF6', '#4C1D95'),
    'id_card': ('#93C5FD', '#3B82F6', '#1E3A8A'), 'archive': ('#FDBA74', '#D97706', '#78350F'),
    'archive_box': ('#FDBA74', '#EA580C', '#7C2D12'), 'refresh': ('#7DD3FC', '#3B82F6', '#1E3A8A'),
    'calendar': ('#93C5FD', '#3B82F6', '#1E3A8A'), 'calendar_days': ('#93C5FD', '#2563EB', '#1E3A8A'),
    'wallet': ('#FDE68A', '#F59E0B', '#78350F'), 'at': ('#6EE7B7', '#10B981', '#064E3B'),
    'library': ('#D8B4FE', '#A855F7', '#581C87'), 'globe': ('#7DD3FC', '#0284C7', '#0C4A6E'),
    'lifebuoy': ('#6EE7B7', '#10B981', '#065F46'), 'qr': ('#CBD5E1', '#64748B', '#1E293B'),
    'signal': ('#93C5FD', '#3B82F6', '#1E3A8A'), 'map': ('#F9A8D4', '#EC4899', '#831843'),
    'map_pin': ('#FCA5A5', '#EF4444', '#7F1D1D'), 'puzzle': ('#FDBA74', '#F59E0B', '#78350F'),
    'table': ('#A5B4FC', '#6366F1', '#312E81'), 'variable': ('#6EE7B7', '#14B8A6', '#134E4A'),
    'beaker': ('#A5B4FC', '#6366F1', '#312E81'), 'bulb': ('#FDE68A', '#FBBF24', '#92400E'),
    'play': ('#6EE7B7', '#10B981', '#065F46'), 'squares': ('#93C5FD', '#3B82F6', '#1E3A8A'),
    'briefcase': ('#FDBA74', '#B45309', '#78350F'), 'cube': ('#C4B5FD', '#8B5CF6', '#4C1D95'),
    'phone': ('#6EE7B7', '#10B981', '#065F46'), 'microphone': ('#FCA5A5', '#EF4444', '#7F1D1D'),
    'clipboard': ('#FDE68A', '#D97706', '#78350F'), 'clipboard_check': ('#6EE7B7', '#10B981', '#065F46'),
    'smile': ('#FDE68A', '#FBBF24', '#92400E'), 'hand': ('#7DD3FC', '#3B82F6', '#1E3A8A'),
    'bookmark': ('#F9A8D4', '#EC4899', '#831843'), 'link': ('#7DD3FC', '#0EA5E9', '#0C4A6E'),
    'share': ('#7DD3FC', '#0EA5E9', '#0C4A6E'), 'send': ('#6EE7B7', '#10B981', '#065F46'),
    'pencil': ('#FDE68A', '#D97706', '#78350F'), 'photo': ('#93C5FD', '#3B82F6', '#1E3A8A'),
    'camera': ('#CBD5E1', '#64748B', '#1E293B'), 'video': ('#FCA5A5', '#EF4444', '#7F1D1D'),
    'speaker': ('#93C5FD', '#3B82F6', '#1E3A8A'), 'mobile': ('#A5B4FC', '#6366F1', '#312E81'),
    'computer_desktop': ('#93C5FD', '#3B82F6', '#1E3A8A'), 'search': ('#7DD3FC', '#0EA5E9', '#0C4A6E'),
    'zoom_in': ('#7DD3FC', '#0EA5E9', '#0C4A6E'), 'zoom_out': ('#7DD3FC', '#0EA5E9', '#0C4A6E'),
    'filter': ('#A5B4FC', '#6366F1', '#312E81'), 'trash': ('#FCA5A5', '#EF4444', '#7F1D1D'),
    'plus': ('#6EE7B7', '#10B981', '#065F46'), 'plus_circle': ('#6EE7B7', '#10B981', '#065F46'),
    'minus': ('#CBD5E1', '#64748B', '#1E293B'), 'minus_circle': ('#CBD5E1', '#64748B', '#1E293B'),
    'check': ('#6EE7B7', '#34D399', '#065F46'), 'check_circle': ('#6EE7B7', '#10B981', '#065F46'),
    'check_badge': ('#6EE7B7', '#059669', '#064E3B'), 'x': ('#FCA5A5', '#EF4444', '#7F1D1D'),
    'x_circle': ('#FCA5A5', '#EF4444', '#7F1D1D'), 'warning': ('#FDE68A', '#F59E0B', '#92400E'),
    'alert': ('#FCA5A5', '#EF4444', '#7F1D1D'), 'info': ('#93C5FD', '#3B82F6', '#1E3A8A'),
    'bell': ('#FDE68A', '#F59E0B', '#78350F'), 'bell_alert': ('#FCA5A5', '#EF4444', '#7F1D1D'),
    'bell_slash': ('#CBD5E1', '#64748B', '#1E293B'), 'folder': ('#FDE68A', '#FBBF24', '#92400E'),
    'language': ('#7DD3FC', '#0EA5E9', '#0C4A6E'), 'lock': ('#FDE68A', '#D97706', '#78350F'),
    'unlock': ('#6EE7B7', '#10B981', '#065F46'), 'paperclip': ('#CBD5E1', '#64748B', '#1E293B'),
    'cpu': ('#A5B4FC', '#818CF8', '#1E1B4B'), 'bolt': ('#FDE68A', '#FBBF24', '#92400E'),
    'menu': ('#CBD5E1', '#64748B', '#1E293B'), 'menu_alt': ('#CBD5E1', '#64748B', '#1E293B'),
    'more': ('#CBD5E1', '#64748B', '#1E293B'), 'dots': ('#CBD5E1', '#64748B', '#1E293B'),
    'moon': ('#C4B5FD', '#8B5CF6', '#4C1D95'), 'sun': ('#FDE68A', '#FBBF24', '#B45309'),
    'eye': ('#7DD3FC', '#0EA5E9', '#0C4A6E'), 'eye_slash': ('#CBD5E1', '#64748B', '#1E293B'),
    'tag': ('#FBCFE8', '#EC4899', '#831843'), 'tag_percent': ('#FDE68A', '#F59E0B', '#78350F'),
    'arrow_right': ('#6EE7B7', '#34D399', '#065F46'), 'arrow_left': ('#CBD5E1', '#64748B', '#1E293B'),
    'arrow_up': ('#6EE7B7', '#34D399', '#065F46'), 'arrow_down': ('#CBD5E1', '#64748B', '#1E293B'),
    'arrow_circle': ('#6EE7B7', '#10B981', '#065F46'),
    'uturn_left': ('#7DD3FC', '#0EA5E9', '#0C4A6E'), 'uturn_right': ('#7DD3FC', '#0EA5E9', '#0C4A6E'),
    'arrows_lr': ('#93C5FD', '#3B82F6', '#1E3A8A'),
    'arrows_pointing_out': ('#A5B4FC', '#6366F1', '#312E81'),
    'arrows_up_down': ('#7DD3FC', '#0EA5E9', '#0C4A6E'),
    'backspace': ('#FDBA74', '#EA580C', '#7C2D12'), 'trending_up': ('#6EE7B7', '#10B981', '#065F46'),
    'chart': ('#6EE7B7', '#10B981', '#065F46'), 'hashtag': ('#7DD3FC', '#0EA5E9', '#0C4A6E'),
    'envelope': ('#93C5FD', '#3B82F6', '#1E3A8A'), 'bug': ('#FCA5A5', '#EF4444', '#7F1D1D'),
    'megaphone': ('#F9A8D4', '#EC4899', '#831843'), 'thumb_up': ('#FDE68A', '#FBBF24', '#92400E'),
    'cloud': ('#A5F3FC', '#22D3EE', '#155E75'), 'book': ('#F9A8D4', '#EC4899', '#831843'),
    'percent': ('#FDE68A', '#F59E0B', '#78350F'), 'qr_frame': ('#CBD5E1', '#64748B', '#1E293B'),
    'cart_add': ('#6EE7B7', '#10B981', '#064E3B'), 'bold': ('#CBD5E1', '#64748B', '#1E293B'),
    'italic': ('#CBD5E1', '#64748B', '#1E293B'), 'underline': ('#CBD5E1', '#64748B', '#1E293B'),
    'strike': ('#CBD5E1', '#64748B', '#1E293B'), 'h1': ('#93C5FD', '#3B82F6', '#1E3A8A'),
    'h2': ('#93C5FD', '#3B82F6', '#1E3A8A'), 'h3': ('#93C5FD', '#3B82F6', '#1E3A8A'),
    'text': ('#CBD5E1', '#64748B', '#1E293B'), 'text_badge': ('#7DD3FC', '#0EA5E9', '#0C4A6E'),
    # nama file semantik AppIcon
    'order': ('#6EE7B7', '#059669', '#064E3B'), 'history': ('#CBD5E1', '#64748B', '#1E293B'),
    'voucher': ('#F9A8D4', '#EC4899', '#831843'), 'wishlist': ('#FDA4AF', '#E11D48', '#881337'),
    'settings': ('#CBD5E1', '#64748B', '#1E293B'), 'profile': ('#93C5FD', '#3B82F6', '#1E3A8A'),
    'privacy': ('#6EE7B7', '#10B981', '#065F46'), 'help': ('#A5B4FC', '#6366F1', '#312E81'),
    'external-link': ('#7DD3FC', '#0EA5E9', '#0C4A6E'), 'messages': ('#93C5FD', '#3B82F6', '#1E3A8A'),
    'export': ('#A5F3FC', '#22D3EE', '#155E75'), 'arrow-right': ('#6EE7B7', '#34D399', '#065F46'),
    'sign-out': ('#FCA5A5', '#F87171', '#7F1D1D'), 'admin-shield': ('#C4B5FD', '#8B5CF6', '#4C1D95'),
    'api-key': ('#FDE68A', '#D97706', '#78350F'), 'discount': ('#FDE68A', '#F59E0B', '#78350F'),
    'maintenance': ('#FDBA74', '#D97706', '#78350F'), 'payment-gateway': ('#A5F3FC', '#22D3EE', '#155E75'),
    'payment-method': ('#FDBA74', '#B45309', '#78350F'), 'reference': ('#CBD5E1', '#64748B', '#1E293B'),
    'report': ('#FCA5A5', '#EF4444', '#7F1D1D'), 'terms': ('#CBD5E1', '#94A3B8', '#1E293B'),
    'transaction': ('#A7F3D0', '#10B981', '#064E3B'), 'customizer': ('#F9A8D4', '#EC4899', '#831843'),
    'wedding-policy': ('#FDE68A', '#F59E0B', '#92400E'),
    'general-document': ('#CBD5E1', '#94A3B8', '#1E293B'),
    'chevron_left': ('#CBD5E1', '#94A3B8', '#334155'), 'chevron_right': ('#6EE7B7', '#34D399', '#065F46'),
    'chevron_up': ('#CBD5E1', '#64748B', '#1E293B'), 'chevron_down': ('#CBD5E1', '#64748B', '#1E293B'),
    'chevron_double_left': ('#CBD5E1', '#64748B', '#1E293B'),
    'chevron_double_right': ('#6EE7B7', '#34D399', '#065F46'),
    'chevron_up_down': ('#CBD5E1', '#64748B', '#1E293B'), 'urut': ('#CBD5E1', '#64748B', '#1E293B'),
    'stream': ('#93C5FD', '#3B82F6', '#1E3A8A'), 'bot': ('#CBD5E1', '#64748B', '#1E293B'),
}

DEFAULT_PALETTES = {
    'default': ('#93C5FD', '#3B82F6', '#1E3A8A'),
}

TEMPLATE = '''<svg xmlns="http://www.w3.org/2000/svg"{viewbox}>
  <defs>
    <linearGradient id="g1" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="{light}"/>
      <stop offset="1" stop-color="{base}"/>
    </linearGradient>
    <filter id="sh" x="-40%" y="-40%" width="180%" height="190%">
      <feDropShadow dx="0" dy="20" stdDeviation="18" flood-color="{dark}" flood-opacity="0.42"/>
    </filter>
  </defs>
  <style>
    @keyframes ikon-terbang {{ 0%, 100% {{ transform: translateY(0); }} 50% {{ transform: translateY(-1.2%); }} }}
    .ikon {{ animation: ikon-terbang 3.6s ease-in-out infinite; }}
    @media (prefers-reduced-motion: reduce) {{ .ikon {{ animation: none; }} }}
  </style>
  <g class="ikon" fill="url(#g1)" filter="url(#sh)">{content}</g>
</svg>
'''


def unduh(url: str) -> str:
    req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
    with urllib.request.urlopen(req, timeout=45) as resp:
        data = resp.read()
    return data.decode('utf-8')


def recolor(symbol: str, stem: str, svg_text: str) -> str:
    light, base, dark = PALETTE.get(stem, DEFAULT_PALETTES['default'])

    m = re.search(r'viewBox="([^"]+)"', svg_text)
    viewbox = f' viewBox="{m.group(1)}"' if m else ''

    inner = re.sub(r'<svg\b[^>]*>(.*)</svg>', r'\1', svg_text, flags=re.S)
    # currentColor dilepas supaya warnanya ikut dari <g> (gradien).
    inner = inner.replace(' fill="currentColor"', '').replace(" fill='currentColor'", '')
    inner = inner.strip()

    return TEMPLATE.format(viewbox=viewbox, light=light, base=base, dark=dark, content=inner)


def main() -> None:
    # Filter opsional: `python scripts/fetch-material-symbols.py tag_percent`
    # cuma memproses file yang stem-nya memuat string itu -- dipakai untuk
    # memperbaiki satu-dua file tanpa mengunduh ulang semua.
    hanya = sys.argv[1] if len(sys.argv) > 1 else ''

    files = sorted(p for p in OUT.glob('*.svg') if hanya in p.stem)
    dipakai = 0
    gagal = []
    fallback = []

    for path in files:
        stem = path.stem
        symbol = SYMBOLS.get(stem, stem)

        svg = None
        for url in [RAW.format(s=symbol), *(f.format(s=symbol) for f in FALLBACKS)]:
            try:
                svg = unduh(url)
                if '<path' in svg or '<g' in svg or '<circle' in svg or '<rect' in svg:
                    break
                svg = None
            except Exception:
                svg = None

        if svg is None:
            gagal.append(f'{stem} -> {symbol}')
            continue

        matter = recolor(symbol, stem, svg)
        path.write_text(matter, encoding='utf-8')
        dipakai += 1
        print(f'  {stem} <= {symbol}')

    print()
    print(f'ditimpa : {dipakai}/{len(files)}')
    if fallback:
        print(f'fallback varian: {len(fallback)} -- {", ".join(fallback)}')
    if gagal:
        print('GAGAL (dipertahankan versi lama):')
        print('\\n'.join('  ' + g for g in gagal))
        sys.exit(1)

    print('SELESAI: semua ikon = vektor fonts.google.com/icons.')


if __name__ == '__main__':
    main()
