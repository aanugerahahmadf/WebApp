#!/usr/bin/env python3
"""Generator ikon SVG untuk ikon navigation, menu item, dan halaman.

Kenapa SVG dan bukan PNG:

  - PNG versi percobaan sebelumnya gagal dua kali. Versi pertama "gumpalan"
    karena efek 3D-nya ditimpa lewat MASK (siluet), jadi semua detail di
    dalam bentuk hilang. Versi kedua masih ATL 3MB karena alpha hasil
    GaussianBlur hampir acak per piksel -- PNG tidak bisa mengompresnya.
  - Jalan keluarnya adalah SVG. SVG itu TEKS, jadi ekor filter (feDropShadow)
    dan animasinya tidak menyimpan piksel sama sekali. Satu ikon rusak 2-5 KB.

Ciri tiap file:

  1. FULL WARNA. Bentuk isinya berwarna langsung -- tidak ada `currentColor`,
     jadi warnanya tidak mengikuti warna teks Filament (yang bikin ikon
     abu-abu/kelabu gelap).
    '  2. Kes-an 3D lewat DUA hal murah:
     - `<linearGradient>`: bagian atas bentuk lebih terang, bagian bawah lebih
       gelap -> bentuk terasa bervolume.
     - `<feDropShadow>`: bayangan jatuh di bawah bentuk -> bentuk terasa
       "mengambang" di atas dasar.
  3. ANIMASI lewat `<style>` + CSS keyframes DI DALAM file SVG. `<img>`
       merender animasi itu (browser menjalankan CSS pemicu di dalam
       dokumen SVG yang dimuat sebagai gambar). Ikonnya bergerak pelan
       (mengapung) tanpa file ekstra dan tanpa JavaScript.
       Increment animasi:  goyang naik-turun pelan (translateY) + "kilau"
       putih yang berdenyut di bagian atas bentuk.
  4. Bentuknya ISI PENUH (filled) seperti emoji, bukan outline.

Keluaran:
  - public/images/icons/<nama>.svg -- satu untuk tiap konstanta AppIcon
    (ikon navigation item, menu item, dan halaman). Chevron dan ikon kecil
    in-page yang lain TIDAK diganti -- tetap Heroicon bawaan Filament.
  Ukuran per file: 2-5 KB.

Jalankan:
    python scripts/generate-icons.py
"""

import math
import os
import re
from pathlib import Path

HERE = Path(__file__).resolve().parent
OUT = HERE.parent / 'public' / 'images' / 'icons'
MAP_OUT = HERE.parent / 'app' / 'Support' / 'AppIcon' / 'icon-map.php'

# ---------------------------------------------------------------------------
# Palet (base, terang, gelap)
# ---------------------------------------------------------------------------

PAL = {
    'rose':    ('#E11D48', '#FDA4AF', '#831843'),
    'amber':   ('#D97706', '#FCD34D', '#7C2D12'),
    'gold':    ('#EAB308', '#FDE68A', '#854D0E'),
    'sky':     ('#0284C7', '#7DD3FC', '#075985'),
    'violet':  ('#7C3AED', '#C4B5FD', '#4C1D95'),
    'emerald': ('#059669', '#6EE7B7', '#064E3B'),
    'indigo':  ('#4F46E5', '#A5B4FC', '#312E81'),
    'teal':    ('#0D9488', '#5EEAD4', '#134E4A'),
    'slate':   ('#475569', '#CBD5E1', '#1E293B'),
    'pink':    ('#DB2777', '#F9A8D4', '#831843'),
    'orange':  ('#EA580C', '#FDBA74', '#7C2D12'),
    'lime':    ('#65A30D', '#D9F99D', '#365314'),
    'cyan':    ('#0891B2', '#67E8F9', '#155E75'),
}

# ---------------------------------------------------------------------------
# Bantuan geometri (titik-titik bentuk, angka kasar ala emoji)
# ---------------------------------------------------------------------------


def shifted(pts, dx, dy):
    return [(x + dx, y + dy) for x, y in pts]


def star_pts(cx, cy, r_out, r_in, n=5, rot=-90):
    pts = []
    for i in range(n * 2):
        a = math.radians(rot + i * 180.0 / n)
        r = r_out if i % 2 == 0 else r_in
        pts.append((cx + r * math.cos(a), cy + r * math.sin(a)))
    return pts


def heart_pts(cx, cy, s):
    pts = []
    for i in range(97):
        t = math.pi * 2 * i / 96
        x = 16 * math.sin(t) ** 3
        y = 13 * math.cos(t) - 5 * math.cos(2 * t) - 2 * math.cos(3 * t) - math.cos(4 * t)
        pts.append((cx + x * s, cy - y * s))
    return pts


def pts_of_arc(cx, cy, r, a0, a1, steps=22):
    pts = []
    for i in range(steps + 1):
        a = math.radians(a0 + (a1 - a0) * i / steps)
        pts.append((cx + r * math.cos(a), cy + r * math.sin(a)))
    return pts


def arrow_pts(cx, cy, length, w, direction='right', head=1.9):
    """Panah tebal ala emoji."""
    h = length / 2
    t = w / 2
    hw = length * head / 2
    if direction == 'right':
        return ([(cx - h, cy - t), (cx + h - hw, cy - t), (cx + h - hw, cy - hw * 0.72),
                 (cx + h, cy), (cx + h - hw, cy + hw * 0.72), (cx + h - hw, cy + t), (cx - h, cy + t)])
    if direction == 'left':
        return ([(cx + h, cy - t), (cx - h + hw, cy - t), (cx - h + hw, cy - hw * 0.72),
                 (cx - h, cy), (cx - h + hw, cy + hw * 0.72), (cx - h + hw, cy + t), (cx + h, cy + t)])
    if direction == 'up':
        return ([(cx - t, cy + h), (cx - t, cy - h + hw), (cx - hw * 0.72, cy - h + hw),
                 (cx, cy - h), (cx + hw * 0.72, cy - h + hw), (cx + t, cy - h + hw), (cx + t, cy + h)])
    return ([(cx - t, cy - h), (cx - t, cy + h - hw), (cx - hw * 0.72, cy + h - hw),
             (cx, cy + h), (cx + hw * 0.72, cy + h - hw), (cx + t, cy + h - hw), (cx + t, cy - h)])


def chevron_pts(cx, cy, size, direction='right'):
    if direction == 'right':
        return [(cx - size * 0.32, cy - size * 0.85), (cx + size * 0.32, cy), (cx - size * 0.32, cy + size * 0.85)]
    if direction == 'left':
        return [(cx + size * 0.32, cy - size * 0.85), (cx - size * 0.32, cy), (cx + size * 0.32, cy + size * 0.85)]
    if direction == 'up':
        return [(cx - size * 0.85, cy + size * 0.32), (cx, cy - size * 0.32), (cx + size * 0.85, cy + size * 0.32)]
    return [(cx - size * 0.85, cy - size * 0.32), (cx, cy + size * 0.32), (cx + size * 0.85, cy - size * 0.32)]


# ---------------------------------------------------------------------------
# Bentuk: kembalikan daftar primitif.
#   ('c', cx, cy, r, warna)                    lingkaran
#   ('e', cx, cy, rx, ry, warna)               elips
#   ('r', x, y, w, h, r, warna)                rounded rect
#   ('p', titik, warna)                        polygon
#   ('l', titik, tebal, warna)                 polyline stroke
#   ('a', cx, cy, r, a0, a1, tebal, warna)     busur stroke
#   ('t', x, y, teks, ukuran, warna)           teks
#   ('k', cx, cy, rx, ry)                      kilau putih (berdenyut)
#
# warna: 'm' = gradasi utama, 'l' = warna terang, 'd' = warna gelap,
#        'W' = putih, atau '#RRGGBB' literal.
# ---------------------------------------------------------------------------


def cloud_pts(cy=70):
    pts = [(12, cy + 18), (12, cy - 2)]
    pts += pts_of_arc(34, cy - 6, 22, -140, -20)
    pts += pts_of_arc(64, cy - 12, 26, -170, -8)
    pts += pts_of_arc(94, cy + 2, 18, -160, -40)
    pts += [(116, cy + 18)]
    return pts


def sparkle_pts(cx, cy, r, n=4):
    return star_pts(cx, cy, r, r * 0.34, n, -90)


SHAPES = {}


def shape(name):
    def deco(fn):
        SHAPES[name] = fn
        return fn
    return deco


@shape('flower')
def _(m, l, d, W):
    prims = []
    for i in range(5):
        a = math.radians(-90 + i * 72)
        prims.append(('c', 64 + 30 * math.cos(a), 56 + 30 * math.sin(a), 19, m))
    prims.append(('c', 64, 56, 12.5, '#FDE68A'))
    prims.append(('c', 60, 52, 4, W))
    prims.append(('l', [(62, 78), (62, 112)], 7, d))
    prims.append(('p', [(64, 97), (90, 86), (92, 100), (64, 107)], l))
    return prims


@shape('flower_alt')
def _(m, l, d, W):
    prims = []
    for i in range(6):
        a = math.radians(-90 + i * 60)
        prims.append(('c', 64 + 28 * math.cos(a), 54 + 28 * math.sin(a), 17, m))
    prims.append(('c', 64, 54, 12, '#FDE68A'))
    prims.append(('l', [(60, 76), (60, 112)], 7, d))
    prims.append(('c', 58, 50, 4, W))
    return prims


@shape('gift')
def _(m, l, d, W):
    return [
        ('r', 16, 48, 96, 62, 10, m),
        ('r', 9, 36, 110, 22, 9, l),
        ('r', 56, 36, 16, 74, 5, d),
        ('c', 46, 31, 13, l),
        ('c', 82, 31, 13, l),
        ('c', 64, 31, 10, l),
    ]


@shape('bag')
def _(m, l, d, W):
    return [
        ('p', [(26, 46), (102, 46), (110, 112), (18, 112)], m),
        ('a', 64, 52, 24, 180, 356, 9, d),
        ('r', 36, 68, 56, 12, 6, l),
    ]


@shape('cart')
def _(m, l, d, W):
    return [
        ('p', [(18, 28), (32, 28), (47, 82), (104, 82), (114, 44), (38, 44)], m),
        ('l', [(47, 92), (104, 92)], 8, d),
        ('c', 53, 107, 9, d),
        ('c', 98, 107, 9, d),
    ]


@shape('cart_add')
def _(m, l, d, W):
    return [
        ('p', [(14, 28), (28, 28), (43, 80), (98, 80), (108, 44), (34, 44)], m),
        ('c', 49, 104, 9, d),
        ('c', 92, 104, 9, d),
        ('l', [(105, 18), (105, 44)], 7, W),
        ('l', [(92, 31), (118, 31)], 7, W),
    ]


@shape('clock')
def _(m, l, d, W):
    return [
        ('c', 64, 64, 48, m),
        ('c', 64, 64, 36, l),
        ('l', [(64, 64), (64, 38)], 7, d),
        ('l', [(64, 64), (84, 74)], 7, d),
        ('c', 64, 64, 5, d),
    ]


@shape('star')
def _(m, l, d, W):
    return [
        ('p', star_pts(64, 68, 52, 21), m),
        ('p', star_pts(64, 68, 33, 13), l),
        ('k', 50, 50, 11, 8),
    ]


@shape('ticket')
def _(m, l, d, W):
    prims = [
        ('r', 10, 40, 108, 48, 10, m),
        ('r', 24, 52, 80, 24, 6, l),
    ]
    for y in range(50, 80, 7):
        prims.append(('l', [(88, y), (96, y)], 5, d))
    return prims


@shape('heart')
def _(m, l, d, W):
    return [
        ('p', heart_pts(64, 60, 3.4), m),
        ('c', 46, 46, 9, W),
        ('k', 58, 38, 6, 4),
    ]


@shape('cog')
def _(m, l, d, W):
    prims = []
    for i in range(8):
        a = math.radians(i * 45)
        prims.append(('c', 64 + 39 * math.cos(a), 64 + 39 * math.sin(a), 13, m))
    prims.append(('c', 64, 64, 32, m))
    prims.append(('c', 64, 64, 15, l))
    prims.append(('c', 64, 64, 7, d))
    return prims


@shape('user_circle')
def _(m, l, d, W):
    return [
        ('c', 64, 64, 47, m),
        ('c', 64, 52, 19, l),
        ('p', pts_of_arc(64, 112, 30, 182, 358) + [(64, 112)], l),
    ]


@shape('user')
def _(m, l, d, W):
    return [
        ('c', 64, 44, 21, m),
        ('p', pts_of_arc(64, 114, 38, 182, 358) + [(64, 114)], m),
        ('k', 58, 38, 6, 4),
    ]


@shape('users')
def _(m, l, d, W):
    return [
        ('c', 48, 48, 19, m),
        ('p', pts_of_arc(48, 106, 32, 182, 358) + [(48, 106)], m),
        ('c', 91, 54, 15, l),
        ('p', pts_of_arc(91, 104, 25, 182, 358) + [(91, 104)], l),
    ]


@shape('shield_check')
def _(m, l, d, W):
    return [
        ('p', [(64, 12), (110, 30), (106, 72), (64, 114), (22, 72), (18, 30)], m),
        ('l', [(44, 64), (60, 80), (86, 44)], 12, W),
    ]


@shape('shield_alert')
def _(m, l, d, W):
    return [
        ('p', [(64, 12), (110, 30), (106, 72), (64, 114), (22, 72), (18, 30)], m),
        ('l', [(64, 42), (64, 74)], 12, W),
        ('c', 64, 90, 7, W),
    ]


@shape('question')
def _(m, l, d, W):
    return [
        ('c', 64, 64, 47, m),
        ('t', 64, 96, '?', 76, W),
    ]


@shape('external')
def _(m, l, d, W):
    return [
        ('r', 14, 40, 76, 74, 11, m),
        ('l', [(84, 44), (112, 16)], 12, l),
        ('l', [(84, 16), (112, 16)], 11, l),
        ('l', [(112, 16), (112, 44)], 11, l),
    ]


@shape('sign_out')
def _(m, l, d, W):
    return [
        ('r', 16, 16, 62, 96, 10, m),
        ('r', 28, 32, 38, 64, 6, l),
        ('l', [(66, 64), (112, 64)], 12, l),
        ('l', [(94, 42), (112, 64), (94, 86)], 9, l),
    ]


@shape('sign_in')
def _(m, l, d, W):
    return [
        ('r', 44, 16, 68, 96, 10, m),
        ('l', [(58, 64), (14, 64)], 12, l),
        ('l', [(32, 44), (14, 64), (32, 86)], 9, l),
    ]


@shape('home')
def _(m, l, d, W):
    return [
        ('p', [(64, 12), (115, 58), (104, 58), (104, 112), (24, 112), (24, 58), (13, 58)], m),
        ('r', 49, 72, 30, 40, 6, l),
        ('k', 46, 40, 7, 5),
    ]


@shape('chat')
def _(m, l, d, W):
    return [
        ('r', 8, 22, 106, 62, 17, m),
        ('p', [(24, 76), (54, 76), (20, 108)], m),
        ('l', [(26, 44), (94, 44)], 9, l),
        ('l', [(26, 64), (72, 64)], 9, W),
    ]


@shape('chat_dots')
def _(m, l, d, W):
    return [
        ('r', 8, 22, 112, 62, 17, m),
        ('p', [(20, 76), (50, 76), (16, 108)], m),
        ('c', 34, 53, 8, W),
        ('c', 60, 53, 8, W),
        ('c', 86, 53, 8, W),
    ]


@shape('chat_bubble')
def _(m, l, d, W):
    return [
        ('r', 10, 22, 104, 62, 17, m),
        ('p', [(24, 78), (54, 78), (22, 106)], m),
        ('l', [(28, 40), (92, 40)], 9, W),
    ]


@shape('review')
def _(m, l, d, W):
    return [
        ('r', 6, 18, 116, 74, 18, m),
        ('p', [(18, 86), (56, 86), (14, 116)], m),
        ('p', star_pts(64, 54, 26, 34, 5, 180), W),
    ]


@shape('download')
def _(m, l, d, W):
    return [
        ('p', arrow_pts(64, 50, 62, 16, 'down', 1.7), m),
        ('r', 18, 84, 92, 30, 10, l),
        ('l', [(64, 14), (64, 62)], 14, m),
    ]


@shape('upload')
def _(m, l, d, W):
    return [
        ('p', arrow_pts(64, 78, 62, 16, 'up', 1.7), m),
        ('r', 18, 84, 92, 30, 10, l),
        ('l', [(64, 66), (64, 88)], 10, d),
    ]


@shape('sparkles')
def _(m, l, d, W):
    return [
        ('p', sparkle_pts(44, 56, 40), m),
        ('p', sparkle_pts(94, 92, 22), l),
        ('p', sparkle_pts(97, 34, 18), W),
    ]


@shape('key')
def _(m, l, d, W):
    return [
        ('c', 40, 64, 25, l),
        ('c', 40, 64, 12, d),
        ('l', [(60, 64), (112, 64)], 15, m),
        ('l', [(88, 64), (88, 86)], 12, m),
        ('l', [(106, 64), (106, 82)], 12, m),
    ]


@shape('database')
def _(m, l, d, W):
    return [
        ('e', 64, 34, 48, 18, m),
        ('r', 16, 34, 96, 62, 0, m),
        ('e', 64, 94, 48, 18, m),
        ('e', 64, 34, 34, 11, l),
        ('a', 64, 64, 44, 0, 180, 6, l),
        ('a', 64, 92, 44, 180, 0, 6, d),
    ]


@shape('code')
def _(m, l, d, W):
    return [
        ('l', [(46, 28), (14, 64), (46, 100)], 14, m),
        ('l', [(82, 28), (114, 64), (82, 100)], 14, l),
        ('l', [(72, 14), (56, 114)], 11, d),
    ]


@shape('dollar')
def _(m, l, d, W):
    return [
        ('c', 64, 64, 47, m),
        ('t', 64, 100, '$', 78, W),
    ]


@shape('wrench')
def _(m, l, d, W):
    return [
        ('l', [(42, 88), (96, 34)], 17, m),
        ('c', 40, 92, 19, m),
        ('c', 40, 92, 8, d),
        ('p', [(78, 16), (112, 24), (106, 60), (72, 34)], l),
    ]


@shape('server')
def _(m, l, d, W):
    return [
        ('r', 12, 18, 104, 40, 10, m),
        ('r', 12, 66, 104, 40, 10, l),
        ('c', 32, 38, 7, W),
        ('c', 32, 86, 7, l),
        ('l', [(92, 38), (104, 38)], 6, d),
        ('l', [(92, 86), (104, 86)], 6, d),
    ]


@shape('card')
def _(m, l, d, W):
    return [
        ('r', 8, 28, 112, 72, 13, m),
        ('r', 8, 42, 112, 18, 0, d),
        ('r', 22, 74, 34, 10, 5, W),
    ]


@shape('list')
def _(m, l, d, W):
    prims = []
    for y in (26, 58, 90):
        prims.append(('c', 28, y + 6, 8, m))
        prims.append(('r', 46, y, 62, 12, 6, l))
    return prims


@shape('numbered_list')
def _(m, l, d, W):
    prims = []
    for i, y in enumerate((24, 56, 88)):
        prims.append(('t', 18, y + 24, str(i + 1), 26, m))
        prims.append(('r', 46, y, 62, 14, 7, l))
    return prims


@shape('flag')
def _(m, l, d, W):
    return [
        ('r', 22, 10, 14, 106, 6, m),
        ('p', [(38, 18), (106, 18), (86, 46), (106, 74), (38, 74)], l),
    ]


@shape('document')
def _(m, l, d, W):
    return [
        ('p', [(22, 8), (78, 8), (106, 38), (106, 120), (22, 120)], m),
        ('p', [(78, 8), (106, 36), (78, 36)], l),
        ('l', [(40, 62), (90, 62)], 7, d),
        ('l', [(40, 82), (90, 82)], 7, d),
        ('l', [(40, 102), (74, 102)], 7, d),
    ]


@shape('banknotes')
def _(m, l, d, W):
    return [
        ('r', 6, 30, 116, 56, 11, m),
        ('r', 22, 42, 84, 32, 7, l),
        ('c', 64, 58, 13, m),
        ('l', [(34, 92), (94, 92)], 9, d),
    ]


@shape('brush')
def _(m, l, d, W):
    return [
        ('l', [(44, 84), (90, 38)], 15, m),
        ('p', [(80, 20), (108, 26), (98, 52)], l),
        ('r', 24, 90, 34, 26, 8, d),
    ]


@shape('swatch')
def _(m, l, d, W):
    return [
        ('r', 12, 12, 104, 104, 15, l),
        ('c', 44, 44, 14, m),
        ('c', 86, 44, 14, '#F472B6'),
        ('c', 44, 88, 14, '#38BDF8'),
        ('c', 86, 88, 14, '#A3E635'),
    ]


@shape('stack')
def _(m, l, d, W):
    return [
        ('p', [(64, 12), (112, 34), (64, 56), (16, 34)], l),
        ('p', [(16, 56), (64, 78), (112, 56), (112, 76), (64, 98), (16, 76)], m),
        ('p', [(16, 92), (64, 114), (112, 92), (112, 106), (64, 128), (16, 106)], d),
    ]


@shape('id_card')
def _(m, l, d, W):
    return [
        ('r', 6, 24, 116, 80, 12, m),
        ('c', 42, 56, 14, l),
        ('p', pts_of_arc(42, 100, 20, 182, 358) + [(42, 100)], l),
        ('r', 68, 44, 40, 9, 4, d),
        ('r', 68, 62, 34, 9, 4, d),
    ]


@shape('archive')
def _(m, l, d, W):
    return [
        ('r', 10, 20, 108, 30, 9, d),
        ('r', 20, 52, 88, 58, 9, m),
        ('l', [(64, 62), (64, 92)], 12, W),
        ('l', [(48, 80), (64, 96), (80, 80)], 9, W),
    ]


@shape('archive_box')
def _(m, l, d, W):
    return [
        ('r', 10, 18, 108, 28, 8, d),
        ('r', 22, 48, 84, 62, 9, m),
        ('r', 52, 60, 24, 12, 4, l),
    ]


@shape('refresh')
def _(m, l, d, W):
    return [
        ('a', 64, 64, 44, 320, 252, 14, m),
        ('p', [(96, 24), (120, 46), (88, 56)], l),
    ]


@shape('calendar')
def _(m, l, d, W):
    return [
        ('r', 12, 22, 104, 92, 13, m),
        ('r', 12, 22, 104, 28, 13, l),
        ('r', 30, 10, 14, 30, 6, d),
        ('r', 84, 10, 14, 30, 6, d),
    ]


@shape('calendar_days')
def _(m, l, d, W):
    prims = [
        ('r', 12, 22, 104, 92, 13, m),
        ('r', 12, 22, 104, 28, 13, l),
        ('r', 30, 10, 14, 30, 6, d),
        ('r', 84, 10, 14, 30, 6, d),
    ]
    for x in (36, 64, 92):
        prims.append(('c', x, 76, 8, W))
    prims.append(('k', 36, 52, 5, 4))
    return prims


@shape('wallet')
def _(m, l, d, W):
    return [
        ('r', 8, 28, 112, 74, 14, m),
        ('r', 64, 52, 56, 28, 9, l),
        ('c', 88, 66, 8, d),
    ]


@shape('at')
def _(m, l, d, W):
    return [
        ('a', 60, 64, 44, -155, 170, 12, m),
        ('c', 64, 64, 18, l),
        ('l', [(64, 46), (64, 92)], 10, l),
    ]


@shape('library')
def _(m, l, d, W):
    return [
        ('p', [(10, 106), (34, 16), (60, 24), (38, 110)], m),
        ('p', [(54, 110), (76, 22), (102, 30), (80, 112)], l),
        ('l', [(14, 114), (116, 114)], 9, d),
    ]


@shape('globe')
def _(m, l, d, W):
    return [
        ('c', 64, 64, 47, m),
        ('e', 64, 64, 20, 47, l),
        ('l', [(17, 64), (111, 64)], 7, l),
        ('a', 64, 44, 42, 245, 295, 6, l),
        ('a', 64, 84, 42, 65, 115, 6, l),
    ]


@shape('lifebuoy')
def _(m, l, d, W):
    prims = [
        ('c', 64, 64, 47, m),
        ('c', 64, 64, 20, l),
    ]
    for a in (45, 135, 225, 315):
        r = math.radians(a)
        prims.append(('l', [(64 + 24 * math.cos(r), 64 + 24 * math.sin(r)),
                            (64 + 44 * math.cos(r), 64 + 44 * math.sin(r))], 11, d))
    return prims


@shape('qr')
def _(m, l, d, W):
    prims = [
        ('r', 10, 10, 40, 40, 7, m),
        ('r', 19, 19, 22, 22, 4, l),
        ('r', 78, 10, 40, 40, 7, m),
        ('r', 87, 19, 22, 22, 4, l),
        ('r', 10, 78, 40, 40, 7, m),
        ('r', 19, 87, 22, 22, 4, l),
    ]
    for x, y in ((78, 106), (95, 106), (106, 106), (106, 84), (106, 95), (87, 78), (95, 87)):
        prims.append(('c', x, y, 4.5, m))
    return prims


@shape('signal')
def _(m, l, d, W):
    return [
        ('a', 62, 98, 44, 215, 325, 11, l),
        ('a', 62, 98, 30, 220, 320, 11, m),
        ('c', 62, 98, 10, d),
    ]


@shape('map')
def _(m, l, d, W):
    return [
        ('p', [(10, 30), (44, 16), (84, 34), (118, 18), (118, 100), (84, 112), (44, 96), (10, 110)], m),
        ('l', [(44, 20), (44, 94)], 6, l),
        ('l', [(84, 36), (84, 110)], 6, d),
    ]


@shape('map_pin')
def _(m, l, d, W):
    return [
        ('p', heart_pts(64, 52, 3.0) + [(64, 116)], m),
        ('c', 64, 50, 17, W),
    ]


@shape('puzzle')
def _(m, l, d, W):
    return [
        ('r', 18, 22, 92, 92, 14, m),
        ('c', 64, 24, 15, l),
        ('c', 22, 64, 15, l),
        ('c', 106, 64, 15, d),
        ('c', 64, 104, 15, l),
    ]


@shape('table')
def _(m, l, d, W):
    return [
        ('r', 10, 20, 108, 88, 10, m),
        ('r', 10, 42, 108, 14, 0, l),
        ('r', 56, 20, 12, 88, 0, l),
        ('l', [(10, 74), (118, 74)], 6, d),
    ]


@shape('variable')
def _(m, l, d, W):
    return [
        ('t', 34, 96, 'x', 82, m),
        ('t', 78, 96, 'y', 82, l),
    ]


@shape('beaker')
def _(m, l, d, W):
    return [
        ('p', [(44, 14), (84, 14), (84, 46), (108, 96), (20, 96), (44, 46)], m),
        ('p', [(30, 78), (98, 78), (108, 96), (20, 96)], l),
        ('r', 38, 8, 52, 10, 5, d),
    ]


@shape('bulb')
def _(m, l, d, W):
    return [
        ('c', 64, 50, 33, m),
        ('p', [(49, 74), (79, 74), (77, 96), (51, 96)], l),
        ('r', 50, 96, 28, 12, 5, d),
        ('k', 54, 40, 8, 6),
    ]


@shape('play')
def _(m, l, d, W):
    return [
        ('c', 64, 64, 46, m),
        ('p', [(52, 40), (92, 64), (52, 88)], W),
    ]


@shape('squares')
def _(m, l, d, W):
    return [
        ('r', 12, 12, 44, 44, 10, m),
        ('r', 72, 12, 44, 44, 10, d),
        ('r', 12, 72, 44, 44, 10, l),
        ('r', 72, 72, 44, 44, 10, m),
    ]


@shape('briefcase')
def _(m, l, d, W):
    return [
        ('r', 46, 18, 36, 24, 7, l),
        ('r', 8, 42, 112, 66, 12, m),
        ('r', 8, 64, 112, 16, 0, l),
        ('k', 40, 52, 6, 4),
    ]


@shape('cube')
def _(m, l, d, W):
    return [
        ('p', [(64, 10), (114, 36), (64, 62), (14, 36)], l),
        ('p', [(14, 36), (64, 62), (64, 118), (14, 92)], m),
        ('p', [(114, 36), (64, 62), (64, 118), (114, 92)], d),
    ]


@shape('phone')
def _(m, l, d, W):
    return [
        ('r', 40, 6, 48, 116, 12, m),
        ('r', 46, 20, 36, 82, 5, l),
        ('c', 64, 112, 5, d),
    ]


@shape('microphone')
def _(m, l, d, W):
    return [
        ('r', 50, 10, 28, 62, 14, m),
        ('a', 64, 72, 27, 0, 180, 9, l),
        ('l', [(64, 99), (64, 112)], 8, d),
        ('l', [(52, 112), (76, 112)], 9, d),
    ]


@shape('clipboard')
def _(m, l, d, W):
    return [
        ('r', 18, 20, 92, 98, 11, m),
        ('r', 42, 8, 44, 26, 8, l),
        ('l', [(38, 52), (90, 52)], 7, d),
        ('l', [(38, 72), (90, 72)], 7, d),
        ('l', [(38, 92), (74, 92)], 7, d),
    ]


@shape('clipboard_check')
def _(m, l, d, W):
    return [
        ('r', 18, 20, 92, 98, 11, m),
        ('r', 42, 8, 44, 26, 8, l),
        ('l', [(40, 62), (54, 78), (88, 44)], 10, W),
    ]


@shape('smile')
def _(m, l, d, W):
    return [
        ('c', 64, 64, 47, m),
        ('c', 47, 52, 8, W),
        ('c', 81, 52, 8, W),
        ('a', 64, 62, 27, 25, 155, 9, W),
    ]


@shape('hand')
def _(m, l, d, W):
    return [
        ('r', 30, 40, 60, 60, 16, m),
        ('r', 26, 30, 15, 34, 7, l),
        ('r', 42, 12, 15, 40, 7, m),
        ('r', 60, 14, 15, 38, 7, m),
        ('r', 78, 26, 15, 30, 7, l),
    ]


@shape('bookmark')
def _(m, l, d, W):
    return [
        ('p', [(28, 10), (100, 10), (100, 116), (64, 86), (28, 116)], m),
        ('l', [(44, 26), (84, 26)], 10, l),
    ]


@shape('link')
def _(m, l, d, W):
    return [
        ('a', 42, 62, 17, 190, 80, 14, m),
        ('a', 86, 66, 17, 10, 260, 14, l),
        ('l', [(54, 28), (88, 100)], 13, d),
    ]


@shape('share')
def _(m, l, d, W):
    return [
        ('c', 98, 26, 15, m),
        ('c', 30, 64, 15, m),
        ('c', 98, 102, 15, l),
        ('l', [(42, 57), (84, 33)], 9, d),
        ('l', [(42, 71), (84, 95)], 9, d),
    ]


@shape('send')
def _(m, l, d, W):
    return [
        ('p', [(10, 58), (118, 10), (76, 116), (54, 76)], m),
        ('p', [(54, 76), (118, 10), (70, 78)], l),
    ]


@shape('pencil')
def _(m, l, d, W):
    return [
        ('p', [(18, 100), (26, 74), (88, 12), (112, 36), (50, 98)], m),
        ('p', [(18, 100), (28, 70), (42, 84)], W),
    ]


@shape('photo')
def _(m, l, d, W):
    return [
        ('r', 8, 24, 112, 80, 12, m),
        ('c', 42, 52, 10, '#FCD34D'),
        ('p', [(18, 92), (58, 50), (84, 76), (100, 60), (110, 92)], l),
    ]


@shape('camera')
def _(m, l, d, W):
    return [
        ('r', 42, 12, 44, 22, 8, l),
        ('r', 6, 26, 116, 84, 14, m),
        ('c', 64, 68, 25, l),
        ('c', 64, 68, 14, '#94A3B8'),
        ('k', 56, 60, 6, 4),
    ]


@shape('video')
def _(m, l, d, W):
    return [
        ('r', 6, 34, 84, 60, 13, m),
        ('p', [(94, 48), (122, 30), (122, 98), (94, 80)], l),
    ]


@shape('speaker')
def _(m, l, d, W):
    return [
        ('p', [(12, 42), (42, 42), (72, 16), (72, 112), (42, 86), (12, 86)], m),
        ('a', 82, 64, 22, 300, 60, 9, l),
        ('a', 82, 64, 36, 300, 60, 9, W),
    ]


@shape('mobile')
def _(m, l, d, W):
    return [
        ('r', 32, 8, 64, 112, 13, m),
        ('r', 40, 22, 48, 80, 5, l),
        ('c', 64, 112, 5, d),
    ]


@shape('computer_desktop')
def _(m, l, d, W):
    return [
        ('r', 8, 18, 112, 66, 11, m),
        ('r', 18, 28, 92, 46, 5, l),
        ('r', 52, 88, 24, 14, 4, d),
        ('r', 30, 104, 68, 14, 6, d),
    ]


@shape('search')
def _(m, l, d, W):
    return [
        ('c', 56, 54, 34, m),
        ('c', 56, 54, 20, W),
        ('l', [(82, 80), (114, 112)], 16, l),
    ]


@shape('zoom_in')
def _(m, l, d, W):
    return [
        ('c', 56, 54, 34, m),
        ('l', [(56, 40), (56, 68)], 9, W),
        ('l', [(42, 54), (70, 54)], 9, W),
        ('l', [(82, 80), (114, 112)], 15, l),
    ]


@shape('zoom_out')
def _(m, l, d, W):
    return [
        ('c', 56, 54, 34, m),
        ('l', [(42, 54), (70, 54)], 9, W),
        ('l', [(82, 80), (114, 112)], 15, l),
    ]


@shape('filter')
def _(m, l, d, W):
    return [
        ('p', [(8, 18), (120, 18), (82, 66), (82, 112), (46, 94), (46, 66)], m),
        ('k', 40, 30, 14, 7),
    ]


@shape('trash')
def _(m, l, d, W):
    return [
        ('r', 22, 32, 84, 82, 10, m),
        ('r', 12, 18, 104, 18, 8, l),
        ('r', 48, 4, 32, 18, 6, l),
        ('l', [(50, 48), (50, 96)], 7, d),
        ('l', [(78, 48), (78, 96)], 7, d),
    ]


@shape('plus')
def _(m, l, d, W):
    return [
        ('r', 50, 14, 28, 100, 12, m),
        ('r', 14, 50, 100, 28, 12, d),
    ]


@shape('plus_circle')
def _(m, l, d, W):
    return [
        ('c', 64, 64, 47, m),
        ('l', [(64, 38), (64, 90)], 13, W),
        ('l', [(38, 64), (90, 64)], 13, W),
    ]


@shape('minus_circle')
def _(m, l, d, W):
    return [
        ('c', 64, 64, 47, m),
        ('l', [(38, 64), (90, 64)], 13, W),
    ]


@shape('minus')
def _(m, l, d, W):
    return [('r', 14, 56, 100, 24, 11, d), ('r', 14, 52, 100, 24, 11, m), ('k', 64, 58, 10, 3)]


@shape('check')
def _(m, l, d, W):
    return [('l', [(19, 71), (55, 105), (110, 28)], 21, d),
            ('l', [(16, 68), (52, 102), (112, 26)], 20, l)]


@shape('check_circle')
def _(m, l, d, W):
    return [
        ('c', 64, 64, 47, m),
        ('l', [(40, 66), (57, 83), (90, 44)], 14, W),
    ]


@shape('check_badge')
def _(m, l, d, W):
    return [
        ('p', star_pts(64, 64, 50, 40, 12, -90), m),
        ('l', [(44, 64), (58, 78), (86, 50)], 12, W),
    ]


@shape('x')
def _(m, l, d, W):
    return [
        ('l', [(33, 33), (98, 98)], 20, d),
        ('l', [(95, 33), (30, 98)], 20, d),
        ('l', [(30, 30), (95, 95)], 18, l),
        ('l', [(95, 30), (30, 95)], 18, W),
    ]


@shape('x_circle')
def _(m, l, d, W):
    return [
        ('c', 64, 64, 47, m),
        ('l', [(46, 46), (82, 82)], 13, W),
        ('l', [(82, 46), (46, 82)], 13, W),
    ]


@shape('warning')
def _(m, l, d, W):
    return [
        ('p', [(64, 10), (120, 112), (8, 112)], m),
        ('l', [(64, 48), (64, 86)], 12, W),
        ('c', 64, 100, 7, W),
    ]


@shape('alert')
def _(m, l, d, W):
    return [
        ('c', 64, 64, 47, m),
        ('l', [(64, 32), (64, 74)], 13, W),
        ('c', 64, 92, 7, W),
    ]


@shape('info')
def _(m, l, d, W):
    return [
        ('c', 64, 64, 47, m),
        ('c', 64, 38, 7, W),
        ('l', [(64, 56), (64, 92)], 13, W),
    ]


@shape('bell')
def _(m, l, d, W):
    return [
        ('p', pts_of_arc(64, 58, 42, 180, 360) + [(106, 58), (22, 58)], m),
        ('r', 18, 56, 92, 18, 9, l),
        ('c', 64, 92, 12, d),
        ('c', 64, 14, 8, l),
    ]


@shape('bell_alert')
def _(m, l, d, W):
    prims = [
        ('p', pts_of_arc(64, 58, 42, 180, 360) + [(106, 58), (22, 58)], m),
        ('r', 18, 56, 92, 18, 9, l),
        ('c', 64, 92, 12, d),
        ('c', 64, 14, 8, l),
        ('c', 99, 30, 21, '#E11D48'),
    ]
    prims.append(('l', [(99, 20), (99, 40)], 6, W))
    prims.append(('c', 99, 46, 3, W))
    return prims


@shape('bell_slash')
def _(m, l, d, W):
    return [
        ('p', pts_of_arc(64, 58, 42, 180, 357) + [(64, 58)], m),
        ('r', 18, 56, 92, 18, 9, l),
        ('c', 64, 92, 12, d),
        ('l', [(16, 114), (112, 14)], 12, '#E11D48'),
    ]


@shape('folder')
def _(m, l, d, W):
    return [
        ('p', [(10, 28), (52, 28), (64, 42), (118, 42), (118, 106), (10, 106)], m),
        ('p', [(10, 24), (52, 24), (60, 34), (16, 34)], l),
    ]


@shape('language')
def _(m, l, d, W):
    return [
        ('c', 64, 64, 47, m),
        ('e', 64, 64, 19, 47, W),
        ('l', [(17, 64), (111, 64)], 7, W),
    ]


@shape('lock')
def _(m, l, d, W):
    return [
        ('a', 64, 60, 26, 180, 350, 15, l),
        ('r', 24, 58, 80, 56, 13, m),
        ('c', 64, 86, 9, d),
        ('l', [(64, 92), (64, 100)], 9, d),
    ]


@shape('unlock')
def _(m, l, d, W):
    return [
        ('a', 88, 58, 26, 90, 250, 15, l),
        ('r', 24, 58, 80, 56, 13, m),
        ('c', 64, 86, 9, d),
    ]


@shape('paperclip')
def _(m, l, d, W):
    return [
        ('l', [(80, 114), (78, 70), (78, 70)], 13, d),
        ('l', [(78, 112), (36, 70), (68, 34), (104, 68), (64, 108), (44, 88)], 13, m),
        ('k', 46, 60, 6, 4),
    ]


@shape('cpu')
def _(m, l, d, W):
    prims = [
        ('r', 30, 30, 68, 68, 12, m),
        ('r', 48, 48, 32, 32, 7, l),
    ]
    for i, p in enumerate((40, 64, 88)):
        prims.append(('l', [(p, 8), (p, 30)], 8, d))
        prims.append(('l', [(p, 98), (p, 120)], 8, d))
        prims.append(('l', [(8, p), (30, p)], 8, d))
        prims.append(('l', [(98, p), (120, p)], 8, d))
    return prims


@shape('bolt')
def _(m, l, d, W):
    return [
        ('p', [(74, 8), (32, 72), (58, 72), (48, 120), (96, 52), (68, 52)], m),
        ('p', [(68, 22), (46, 66), (63, 66)], W),
    ]


@shape('menu')
def _(m, l, d, W):
    return [
        ('r', 14, 24, 100, 18, 9, m),
        ('r', 14, 55, 100, 18, 9, m),
        ('r', 14, 86, 100, 18, 9, l),
    ]


@shape('menu_alt')
def _(m, l, d, W):
    return [
        ('r', 14, 26, 100, 16, 8, m),
        ('r', 14, 56, 100, 16, 8, m),
        ('r', 14, 86, 100, 16, 8, l),
        ('c', 26, 34, 0, d),
    ]


@shape('more')
def _(m, l, d, W):
    return [
        ('c', 64, 28, 13, m),
        ('c', 64, 64, 13, l),
        ('c', 64, 100, 13, d),
    ]


@shape('dots')
def _(m, l, d, W):
    return [
        ('c', 28, 64, 13, m),
        ('c', 64, 64, 13, l),
        ('c', 100, 64, 13, d),
    ]


@shape('moon')
def _(m, l, d, W):
    outer = pts_of_arc(64, 64, 46, 250, 120, 30)
    pts = outer + pts_of_arc(46, 46, 36, -25, 208, 30)
    return [
        ('p', pts, m),
        ('k', 60, 52, 6, 5),
    ]


@shape('sun')
def _(m, l, d, W):
    prims = [('c', 64, 64, 27, m)]
    for i in range(8):
        a = math.radians(i * 45)
        prims.append(('l', [(64 + 34 * math.cos(a), 64 + 34 * math.sin(a)),
                            (64 + 50 * math.cos(a), 64 + 50 * math.sin(a))], 12, l))
    return prims


@shape('eye')
def _(m, l, d, W):
    return [
        ('p', pts_of_arc(64, 58, 48, 226, 314) + pts_of_arc(64, 84, 48, 34, 146) + [(64, 58)], m),
        ('c', 64, 66, 20, W),
        ('c', 64, 66, 9, d),
    ]


@shape('eye_slash')
def _(m, l, d, W):
    return [
        ('p', pts_of_arc(64, 58, 48, 226, 314) + pts_of_arc(64, 84, 48, 34, 146) + [(64, 58)], m),
        ('c', 64, 66, 20, W),
        ('c', 64, 66, 9, d),
        ('l', [(20, 104), (108, 24)], 12, '#E11D48'),
    ]


@shape('tag')
def _(m, l, d, W):
    return [
        ('p', [(56, 10), (114, 10), (114, 68), (64, 116), (10, 62)], m),
        ('c', 92, 32, 9, W),
    ]


@shape('tag_percent')
def _(m, l, d, W):
    return [
        ('p', [(56, 10), (114, 10), (114, 68), (64, 116), (10, 62)], m),
        ('c', 48, 76, 11, W),
        ('c', 88, 36, 11, W),
        ('l', [(48, 90), (88, 22)], 8, W),
    ]


@shape('arrow_right')
def _(m, l, d, W):
    return [('p', shifted(arrow_pts(64, 67, 98, 24, 'right'), 0, 3.5), d),
            ('p', arrow_pts(64, 64, 98, 24, 'right'), m)]


@shape('arrow_left')
def _(m, l, d, W):
    return [('p', shifted(arrow_pts(64, 67, 98, 24, 'left'), 0, 3.5), d),
            ('p', arrow_pts(64, 64, 98, 24, 'left'), m)]


@shape('arrow_up')
def _(m, l, d, W):
    return [('p', shifted(arrow_pts(67, 64, 98, 24, 'up'), 3.5, 0), d),
            ('p', arrow_pts(64, 64, 98, 24, 'up'), m)]


@shape('arrow_down')
def _(m, l, d, W):
    return [('p', shifted(arrow_pts(61, 64, 98, 24, 'down'), -3.5, 0), d),
            ('p', arrow_pts(64, 64, 98, 24, 'down'), m)]


@shape('arrow_circle')
def _(m, l, d, W):
    return [
        ('c', 64, 64, 47, m),
        ('a', 64, 62, 26, 320, 250, 13, W),
        ('p', [(96, 52), (102, 68), (80, 58)], W),
    ]


@shape('chevron_right')
def _(m, l, d, W):
    return [('l', [(48, 27), (94, 67), (48, 107)], 21, d), ('l', [(46, 24), (92, 64), (46, 104)], 20, m)]


@shape('chevron_left')
def _(m, l, d, W):
    return [('l', [(80, 27), (34, 67), (80, 107)], 21, d), ('l', [(82, 24), (36, 64), (82, 104)], 20, m)]


@shape('chevron_up')
def _(m, l, d, W):
    return [('l', [(27, 84), (67, 38), (107, 84)], 21, d), ('l', [(24, 82), (64, 36), (104, 82)], 20, m)]


@shape('chevron_down')
def _(m, l, d, W):
    return [('l', [(27, 44), (67, 90), (107, 44)], 21, d), ('l', [(24, 46), (64, 92), (104, 46)], 20, m)]


@shape('chevron_right_left')
def _(m, l, d, W):
    return [('l', [(30, 44), (60, 64), (30, 84)], 18, m),
            ('l', [(68, 44), (98, 64), (68, 84)], 18, l)]


@shape('chevron_double_right')
def _(m, l, d, W):
    return [('l', [(22, 24), (60, 64), (22, 104)], 17, m), ('l', [(68, 24), (106, 64), (68, 104)], 17, l)]


@shape('chevron_double_left')
def _(m, l, d, W):
    return [('l', [(106, 24), (68, 64), (106, 104)], 17, m), ('l', [(60, 24), (22, 64), (60, 104)], 17, l)]


@shape('chevron_up_down')
def _(m, l, d, W):
    return [('l', [(32, 54), (64, 20), (96, 54)], 17, m), ('l', [(32, 74), (64, 108), (104, 74)].copy(), 17, l)]


@shape('uturn_left')
def _(m, l, d, W):
    return [
        ('a', 66, 70, 32, 180, 360, 14, m),
        ('l', [(98, 102), (98, 70)], 14, m),
        ('p', arrow_pts(30, 70, 36, 14, 'left', 1.85), l),
    ]


@shape('uturn_right')
def _(m, l, d, W):
    return [
        ('a', 62, 70, 32, 180, 360, 14, m),
        ('l', [(30, 102), (30, 70)], 14, m),
        ('p', arrow_pts(98, 70, 36, 14, 'right', 1.85), l),
    ]


@shape('arrows_lr')
def _(m, l, d, W):
    return [
        ('p', arrow_pts(34, 40, 58, 16, 'left', 1.8), m),
        ('p', arrow_pts(94, 88, 58, 16, 'right', 1.8), l),
    ]


@shape('arrows_pointing_out')
def _(m, l, d, W):
    return [
        ('l', [(14, 14), (60, 60)], 15, m),
        ('p', arrow_pts(44, 20, 42, 11, 'down', 1.8), m),
        ('p', arrow_pts(20, 44, 42, 11, 'right', 1.8), m),
        ('l', [(114, 114), (68, 68)], 15, l),
        ('p', arrow_pts(84, 108, 42, 11, 'up', 1.8), l),
        ('p', arrow_pts(108, 84, 42, 11, 'left', 1.8), l),
    ]


@shape('arrows_up_down')
def _(m, l, d, W):
    return [
        ('p', arrow_pts(30, 56, 80, 17, 'up', 1.75), m),
        ('p', arrow_pts(98, 72, 80, 17, 'down', 1.75), l),
    ]


@shape('arrow_right_start')
def _(m, l, d, W):
    return [
        ('r', 10, 30, 66, 68, 11, m),
        ('r', 22, 44, 42, 40, 6, l),
        ('p', arrow_pts(92, 64, 46, 13, 'right', 1.8), d),
    ]


@shape('backspace')
def _(m, l, d, W):
    return [
        ('p', [(40, 24), (118, 24), (118, 104), (40, 104), (10, 64)], m),
        ('l', [(62, 46), (98, 82)], 11, W),
        ('l', [(98, 46), (62, 82)], 11, W),
    ]


@shape('trending_up')
def _(m, l, d, W):
    return [
        ('l', [(12, 96), (52, 58), (78, 82), (112, 48)], 14, m),
        ('p', [(104, 30), (120, 46), (96, 56)], l),
    ]


@shape('chart')
def _(m, l, d, W):
    return [
        ('r', 18, 58, 22, 56, 7, l),
        ('r', 54, 34, 22, 80, 7, m),
        ('r', 90, 14, 22, 96, 7, d),
    ]


@shape('hashtag')
def _(m, l, d, W):
    return [
        ('l', [(44, 14), (36, 114)], 13, m),
        ('l', [(84, 14), (76, 114)], 13, m),
        ('l', [(20, 44), (112, 44)], 13, l),
        ('l', [(16, 84), (108, 84)], 13, l),
    ]


@shape('envelope')
def _(m, l, d, W):
    return [
        ('r', 10, 26, 108, 76, 12, m),
        ('p', [(14, 32), (64, 72), (114, 32)], W),
    ]


@shape('bug')
def _(m, l, d, W):
    return [
        ('e', 64, 74, 30, 38, m),
        ('c', 64, 34, 20, l),
        ('l', [(32, 54), (28, 36)], 8, d),
        ('l', [(100, 54), (104, 36)], 8, d),
        ('l', [(30, 82), (10, 86)], 8, d),
        ('l', [(98, 82), (118, 86)], 8, d),
        ('l', [(36, 102), (22, 116)], 8, d),
        ('l', [(92, 102), (106, 116)], 8, d),
        ('l', [(64, 12), (64, 20)], 8, d),
    ]


@shape('megaphone')
def _(m, l, d, W):
    return [
        ('p', [(14, 46), (84, 30), (84, 94), (14, 78)], m),
        ('p', [(84, 32), (116, 18), (116, 106), (84, 92)], l),
        ('r', 22, 78, 26, 34, 8, d),
        ('c', 104, 62, 5, W),
    ]


@shape('thumb_up')
def _(m, l, d, W):
    return [
        ('r', 12, 50, 30, 60, 9, l),
        ('p', [(46, 112), (46, 62), (58, 22), (74, 30), (70, 58), (104, 52), (112, 68), (94, 112)], m),
    ]


@shape('cloud')
def _(m, l, d, W):
    return [('p', cloud_pts(62), m), ('k', 52, 46, 7, 5)]


@shape('book')
def _(m, l, d, W):
    return [
        ('p', [(12, 28), (58, 40), (58, 104), (12, 92)], m),
        ('p', [(116, 28), (70, 40), (70, 104), (116, 92)], l),
        ('l', [(64, 38), (64, 104)], 8, d),
    ]


@shape('percent')
def _(m, l, d, W):
    return [
        ('c', 40, 40, 14, l),
        ('c', 88, 88, 14, l),
        ('l', [(28, 100), (100, 28)], 14, m),
    ]


@shape('qr_frame')
def _(m, l, d, W):
    prims = [
        ('l', [(16, 44), (16, 16), (44, 16)], 12, m),
        ('l', [(84, 16), (112, 16), (112, 44)], 12, m),
        ('l', [(16, 84), (16, 112), (44, 112)], 12, m),
    ]
    for y in (52, 64, 76, 88):
        prims.append(('l', [(16, y), (44, y)], 9, l))
    for y in (52, 64, 76, 88):
        prims.append(('l', [(84, y), (112, y)], 9, l))
    return prims


@shape('bold')
def _(m, l, d, W):
    return [('t', 62, 104, 'B', 96, m), ('l', [(24, 20), (24, 108)], 8, l)]


@shape('italic')
def _(m, l, d, W):
    return [('t', 64, 104, 'I', 92, m), ('l', [(20, 22), (108, 22)], 8, d), ('l', [(20, 106), (108, 106)], 8, l)]


@shape('underline')
def _(m, l, d, W):
    return [('t', 62, 92, 'U', 86, m), ('l', [(24, 112), (100, 112)], 9, l)]


@shape('strike')
def _(m, l, d, W):
    return [('t', 62, 96, 'S', 82, m), ('l', [(20, 64), (108, 64)], 9, l)]


@shape('h1')
def _(m, l, d, W):
    return [('t', 40, 94, 'H1', 56, m), ('k', 92, 100, 12, 8)]


@shape('h2')
def _(m, l, d, W):
    return [('t', 40, 94, 'H2', 56, m), ('k', 92, 100, 12, 8)]


@shape('h3')
def _(m, l, d, W):
    return [('t', 40, 94, 'H3', 56, m), ('k', 92, 100, 12, 8)]


@shape('text')
def _(m, l, d, W):
    return [('t', 62, 94, 'A', 80, m), ('l', [(30, 112), (94, 112)], 7, l)]


@shape('text_badge')
def _(m, l, d, W):
    return [('t', 64, 94, 'A', 74, m), ('c', 98, 98, 18, l), ('t', 98, 110, 'a', 18, W)]


# ---------------------------------------------------------------------------
# Template + rendering
# ---------------------------------------------------------------------------

TEMPLATE = (
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128">\n'
    '  <defs>\n'
    '    <linearGradient id="g1" x1="0" y1="0" x2="0" y2="1">\n'
    '      <stop offset="0" stop-color="{light}"/>\n'
    '      <stop offset="1" stop-color="{base}"/>\n'
    '    </linearGradient>\n'
    '    <filter id="sh" x="-20%" y="-20%" width="140%" height="150%">\n'
    '      <feDropShadow dx="0" dy="2.6" stdDeviation="2.4" flood-color="{dark}" flood-opacity="0.42"/>\n'
    '    </filter>\n'
    '  </defs>\n'
    '  <style>\n'
    '    @keyframes ikon-terbang { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-4px); } }\n'
    '    @keyframes ikon-kilau { 0%, 78%, 100% { opacity: 0.15; } 90% { opacity: 0.62; } }\n'
    '    .ikon { animation: ikon-terbang 3.6s ease-in-out infinite; }\n'
    '    .kilau { animation: ikon-kilau 4.2s ease-in-out infinite; }\n'
    '    @media (prefers-reduced-motion: reduce) { .ikon, .kilau { animation: none; } }\n'
    '  </style>\n'
    '  <g class="ikon" filter="url(#sh)">{content}</g>\n'
    '</svg>\n'
)


def color_fn(m, l, d):
    mapping = {'m': 'url(#g1)', 'l': l, 'd': d, 'W': '#FFFFFF'}

    def get(key):
        if isinstance(key, str) and key.startswith('#'):
            return key
        return mapping[key]

    return get


def fmt(value):
    return ('%.1f' % value).rstrip('0').rstrip('.') if isinstance(value, float) else str(value)


def render(prims, get_color):
    parts = []
    for entry in prims:
        tag = entry[0]
        a = entry[1:]
        if tag == 'c':
            x, y, r, key = a
            parts.append(f'<circle cx="{fmt(x)}" cy="{fmt(y)}" r="{fmt(r)}" fill="{get_color(key)}"/>')
        elif tag == 'e':
            x, y, rx, ry, key = a
            parts.append((f'<ellipse cx="{fmt(x)}" cy="{fmt(y)}" rx="{fmt(rx)}" ry="{fmt(ry)}" '
                          f'fill="{get_color(key)}"/>'))
        elif tag == 'r':
            x, y, w, h, rr, key = a
            parts.append((f'<rect x="{fmt(x)}" y="{fmt(y)}" width="{fmt(w)}" height="{fmt(h)}" rx="{fmt(rr)}" '
                          f'fill="{get_color(key)}"/>'))
        elif tag == 'p':
            pts, key = a
            points = ' '.join(f'{fmt(x)},{fmt(y)}' for x, y in pts)
            parts.append((f'<polygon points="{points}" fill="{get_color(key)}" '
                          f'stroke="{get_color(key)}" stroke-width="2.5" stroke-linejoin="round"/>'))
        elif tag == 'l':
            pts, w, key = a
            d_attr = 'M ' + ' L '.join(f'{fmt(x)} {fmt(y)}' for x, y in pts)
            parts.append((f'<path d="{d_attr}" fill="none" stroke="{get_color(key)}" stroke-width="{fmt(w)}" '
                          f'stroke-linecap="round" stroke-linejoin="round"/>'))
        elif tag == 'a':
            x, y, r, a0, a1, w, key = a
            steps = 26
            pts = []
            for i in range(steps + 1):
                ang = math.radians(a0 + (a1 - a0) * i / steps)
                pts.append((x + r * math.cos(ang), y + r * math.sin(ang)))
            d_attr = 'M ' + ' L '.join(f'{fmt(px)} {fmt(py)}' for px, py in pts)
            parts.append((f'<path d="{d_attr}" fill="none" stroke="{get_color(key)}" stroke-width="{fmt(w)}" '
                          f'stroke-linecap="round"/>'))
        elif tag == 't':
            x, y, text, size, key = a
            parts.append((f'<text x="{fmt(x)}" y="{fmt(y)}" fill="{get_color(key)}" '
                          f'font-family="Segoe UI, Arial, sans-serif" font-weight="bold" '
                          f'font-size="{fmt(size)}" text-anchor="middle" '
                          f'lengthAdjust="spacingAndGlyphs">{text}</text>'))
        elif tag == 'k':
            x, y, rx, ry = a
            parts.append((f'<ellipse class="kilau" cx="{fmt(x)}" cy="{fmt(y)}" rx="{fmt(rx)}" ry="{fmt(ry)}" '
                          f'fill="#FFFFFF" opacity="0.22"/>'))
    return ''.join(parts)


# ---------------------------------------------------------------------------
# Peta nama ikon -> file
# ---------------------------------------------------------------------------

GROUPS = {
    'flower': 'pink', 'gift': 'rose', 'bag': 'amber', 'cart': 'emerald',
    'clock': 'sky', 'star': 'gold', 'ticket': 'violet', 'heart': 'rose',
    'cog': 'slate', 'user_circle': 'indigo', 'user': 'indigo', 'users': 'indigo',
    'shield_check': 'teal', 'shield_alert': 'orange', 'question': 'sky',
    'external': 'violet', 'sign_out': 'rose', 'sign_in': 'emerald',
    'home': 'amber', 'chat': 'sky', 'chat_dots': 'indigo', 'chat_bubble': 'cyan',
    'review': 'rose', 'download': 'emerald', 'upload': 'emerald',
    'sparkles': 'gold', 'key': 'amber', 'database': 'indigo', 'code': 'emerald',
    'dollar': 'emerald', 'wrench': 'amber', 'server': 'emerald', 'card': 'violet',
    'list': 'slate', 'numbered_list': 'slate', 'flag': 'amber', 'document': 'sky',
    'banknotes': 'emerald', 'brush': 'rose', 'swatch': 'pink', 'stack': 'sky',
    'id_card': 'cyan', 'archive': 'amber', 'archive_box': 'slate',
    'refresh': 'cyan', 'calendar': 'rose', 'calendar_days': 'orange',
    'wallet': 'violet', 'at': 'sky', 'library': 'amber', 'globe': 'cyan',
    'lifebuoy': 'orange', 'qr': 'slate', 'signal': 'lime', 'map': 'emerald',
    'map_pin': 'rose', 'puzzle': 'violet', 'table': 'sky', 'variable': 'indigo',
    'beaker': 'lime', 'bulb': 'gold', 'play': 'emerald', 'squares': 'sky',
    'briefcase': 'amber', 'cube': 'cyan', 'phone': 'emerald', 'microphone': 'violet',
    'clipboard': 'amber', 'clipboard_check': 'emerald', 'smile': 'gold',
    'hand': 'orange', 'bookmark': 'rose', 'link': 'cyan', 'share': 'sky',
    'send': 'indigo', 'pencil': 'amber', 'photo': 'violet', 'camera': 'slate',
    'video': 'rose', 'speaker': 'indigo', 'mobile': 'violet',
    'computer_desktop': 'slate', 'search': 'sky', 'zoom_in': 'sky',
    'zoom_out': 'sky', 'filter': 'violet', 'trash': 'rose', 'plus': 'emerald',
    'plus_circle': 'emerald', 'minus_circle': 'rose', 'minus': 'slate',
    'check': 'emerald', 'check_circle': 'emerald', 'check_badge': 'emerald',
    'x': 'rose', 'x_circle': 'rose', 'warning': 'amber', 'alert': 'orange',
    'info': 'sky', 'bell': 'amber', 'bell_alert': 'rose', 'bell_slash': 'slate',
    'folder': 'amber', 'language': 'indigo', 'lock': 'emerald', 'unlock': 'emerald',
    'paperclip': 'cyan', 'cpu': 'slate', 'bolt': 'gold', 'menu': 'slate',
    'menu_alt': 'slate', 'more': 'slate', 'dots': 'slate', 'moon': 'indigo',
    'sun': 'gold', 'eye': 'cyan', 'eye_slash': 'slate', 'tag': 'rose',
    'tag_percent': 'rose', 'arrow_right': 'sky', 'arrow_left': 'sky',
    'arrow_up': 'sky', 'arrow_down': 'sky', 'arrow_circle': 'sky',
    'chevron_right': 'slate', 'chevron_left': 'slate', 'chevron_up': 'slate',
    'chevron_down': 'slate', 'chevron_right_left': 'slate',
    'chevron_double_right': 'slate', 'chevron_double_left': 'slate',
    'chevron_up_down': 'slate', 'uturn_left': 'cyan', 'uturn_right': 'cyan',
    'arrows_lr': 'indigo', 'arrows_pointing_out': 'cyan', 'arrows_up_down': 'sky',
    'arrow_right_start': 'indigo', 'backspace': 'rose', 'trending_up': 'emerald',
    'chart': 'indigo', 'hashtag': 'sky', 'envelope': 'sky', 'bug': 'lime',
    'megaphone': 'orange', 'thumb_up': 'amber', 'cloud': 'sky', 'book': 'teal',
    'percent': 'violet', 'qr_frame': 'slate', 'cart_add': 'emerald',
    'flower_alt': 'pink', 'bold': 'slate', 'italic': 'slate', 'underline': 'slate',
    'strike': 'slate', 'h1': 'slate', 'h2': 'slate', 'h3': 'slate', 'text': 'slate',
    'text_badge': 'sky',
}

# nama ikon di seluruh kode (vendor Filament + app) -> nama file
ICON_MAP = {
    'ri-flower-line': 'flower',
    'ri-gift-line': 'gift',
    'gmdi-shopping-cart-checkout-o': 'cart',
    'eos-account-circle': 'user_circle',
}

_SIMPLE = {
    'gift': 'gift',
    'shopping-bag': 'bag', 'shopping-cart': 'cart',
    'clock': 'clock', 'star': 'star', 'ticket': 'ticket', 'heart': 'heart',
    'cog-6-tooth': 'cog', 'user-circle': 'user_circle', 'user': 'user',
    'users': 'users', 'user-group': 'users',
    'shield-check': 'shield_check', 'question-mark-circle': 'question',
    'arrow-top-right-on-square': 'external',
    'arrow-right-on-rectangle': 'sign_out', 'arrow-left-on-rectangle': 'sign_out',
    'home': 'home', 'chat-bubble-left-right': 'chat',
    'chat-bubble-left-ellipsis': 'chat_dots',
    'chat-bubble-oval-left-ellipsis': 'chat_dots',
    'chat-bubble-bottom-center-text': 'chat_dots', 'chat-bubble-left': 'chat_bubble',
    'arrow-down-tray': 'download', 'arrow-up-tray': 'upload',
    'sparkles': 'sparkles', 'key': 'key', 'circle-stack': 'database',
    'command-line': 'code', 'code-bracket': 'code',
    'currency-dollar': 'dollar', 'wrench-screwdriver': 'wrench',
    'server-stack': 'server', 'credit-card': 'card', 'list-bullet': 'list',
    'numbered-list': 'numbered_list', 'flag': 'flag',
    'document-text': 'document', 'document': 'document',
    'banknotes': 'banknotes', 'paint-brush': 'brush', 'swatch': 'swatch',
    'rectangle-stack': 'stack', 'square-2-stack': 'stack',
    'identification': 'id_card', 'archive-box-arrow-down': 'archive',
    'archive-box': 'archive_box', 'arrow-path': 'refresh',
    'calendar': 'calendar', 'calendar-days': 'calendar_days', 'wallet': 'wallet',
    'at-symbol': 'at', 'building-library': 'library', 'globe-alt': 'globe',
    'language': 'language', 'lifebuoy': 'lifebuoy', 'qr-code': 'qr',
    'signal': 'signal', 'map': 'map', 'map-pin': 'map_pin',
    'puzzle-piece': 'puzzle', 'table-cells': 'table', 'view-columns': 'table',
    'variable': 'variable', 'beaker': 'beaker', 'light-bulb': 'bulb',
    'play': 'play', 'squares-2x2': 'squares', 'briefcase': 'briefcase',
    'cube': 'cube', 'phone': 'phone', 'microphone': 'microphone',
    'clipboard-document': 'clipboard', 'clipboard-document-list': 'clipboard',
    'clipboard-document-check': 'clipboard_check', 'clipboard': 'clipboard',
    'face-smile': 'smile', 'hand-raised': 'hand', 'hand-thumb-up': 'thumb_up',
    'bookmark': 'bookmark', 'link': 'link', 'share': 'share',
    'paper-airplane': 'send', 'pencil-square': 'pencil', 'photo': 'photo',
    'camera': 'camera', 'video-camera': 'video', 'speaker-wave': 'speaker',
    'device-phone-mobile': 'mobile', 'computer-desktop': 'computer_desktop',
    'magnifying-glass': 'search',
    'magnifying-glass-plus': 'zoom_in', 'magnifying-glass-minus': 'zoom_out',
    'funnel': 'filter', 'trash': 'trash', 'plus': 'plus',
    'plus-circle': 'plus_circle', 'minus': 'minus',
    'check': 'check', 'check-circle': 'check_circle', 'check-badge': 'check_badge',
    'x-mark': 'x', 'x-circle': 'x_circle',
    'exclamation-triangle': 'warning', 'shield-exclamation': 'shield_alert',
    'exclamation-circle': 'alert', 'information-circle': 'info',
    'bell': 'bell', 'bell-alert': 'bell_alert', 'bell-slash': 'bell_slash',
    'folder': 'folder', 'lock-closed': 'lock', 'lock-open': 'unlock',
    'paper-clip': 'paperclip', 'cpu-chip': 'cpu', 'bolt': 'bolt',
    'bars-2': 'menu_alt', 'bars-3': 'menu', 'bars-4': 'menu',
    'ellipsis-vertical': 'more', 'ellipsis-horizontal': 'dots',
    'moon': 'moon', 'sun': 'sun', 'eye': 'eye', 'eye-slash': 'eye_slash',
    'tag': 'tag', 'receipt-percent': 'tag_percent', 'percent-badge': 'tag_percent',
    'arrow-left': 'arrow_left', 'arrow-right': 'arrow_right',
    'arrow-up': 'arrow_up', 'arrow-down': 'arrow_down',
    'arrow-down-circle': 'arrow_circle', 'arrow-left-circle': 'arrow_circle',
    'arrow-right-circle': 'arrow_circle', 'arrow-up-circle': 'arrow_circle',
    'arrow-uturn-left': 'uturn_left', 'arrow-uturn-right': 'uturn_right',
    'arrows-right-left': 'arrows_lr', 'arrows-pointing-out': 'arrows_pointing_out',
    'arrows-up-down': 'arrows_up_down', 'chevron-up-down': 'arrows_up_down',
    'chevron-down': 'chevron_down', 'chevron-left': 'chevron_left',
    'chevron-right': 'chevron_right', 'chevron-up': 'chevron_up',
    'chevron-double-right': 'chevron_double_right',
    'chevron-double-left': 'chevron_double_left',
    'arrow-right-start-on-rectangle': 'arrow_right_start',
    'bold': 'bold', 'italic': 'italic', 'underline': 'underline',
    'strikethrough': 'strike', 'h1': 'h1', 'h2': 'h2', 'h3': 'h3',
    'text': 'text',
    'hashtag': 'hashtag', 'book-open': 'book', 'cloud': 'cloud',
    'bug-ant': 'bug', 'megaphone': 'megaphone',
    'chart-bar': 'chart', 'chart-bar-square': 'chart',
    'envelope': 'envelope', 'text-cursor-click': 'text_badge',
    'play': 'play', 'pencil-square-o': 'pencil',
    'arrow-right-end-on-rectangle': 'sign_in',
    'video-camera-slash': 'video',
    'backspace': 'backspace',
}

for key in list(_SIMPLE):
    ICON_MAP['heroicon-m-' + key] = _SIMPLE[key]
    ICON_MAP['heroicon-o-' + key] = _SIMPLE[key]
    ICON_MAP['heroicon-s-' + key] = _SIMPLE[key]

ICON_MAP['heroicon-o-globe-alt'] = 'globe'
ICON_MAP['heroicon-m-globe-alt'] = 'globe'
ICON_MAP['heroicon-o-signal'] = 'signal'
ICON_MAP['heroicon-s-exclamation-triangle'] = 'warning'
ICON_MAP['heroicon-s-exclamation-circle'] = 'alert'
ICON_MAP['heroicon-m-exclamation-circle'] = 'alert'
ICON_MAP['heroicon-o-exclamation-circle'] = 'alert'
ICON_MAP['heroicon-o-exclamation-triangle'] = 'warning'
ICON_MAP['heroicon-o-information-circle'] = 'info'
ICON_MAP['heroicon-m-information-circle'] = 'info'
ICON_MAP['heroicon-m-arrow-trending-up'] = 'trending_up'
ICON_MAP['heroicon-o-megaphone'] = 'megaphone'
ICON_MAP['heroicon-o-conversation-bubbles'] = 'chat_dots'
ICON_MAP['heroicon-m-numbered-list'] = 'numbered_list'
ICON_MAP['heroicon-m-bars-2'] = 'menu'
ICON_MAP['heroicon-o-bars-3-bottom-left'] = 'menu'
ICON_MAP['heroicon-o-ticket'] = 'ticket'
ICON_MAP['heroicon-o-envelope-open'] = 'envelope'
ICON_MAP['heroicon-o-bug'] = 'bug'
ICON_MAP['heroicon-o-academic-cap'] = 'book'
ICON_MAP['heroicon-o-pencil'] = 'pencil'
ICON_MAP['heroicon-o-check'] = 'check'
ICON_MAP['heroicon-s-check'] = 'check'
ICON_MAP['heroicon-m-check'] = 'check'
ICON_MAP['heroicon-s-plus'] = 'plus'
ICON_MAP['heroicon-m-plus'] = 'plus'
ICON_MAP['heroicon-o-plus'] = 'plus'
ICON_MAP['heroicon-o-ellipsis-horizontal'] = 'dots'
ICON_MAP['heroicon-o-heart'] = 'heart'
ICON_MAP['heroicon-m-heart'] = 'heart'
ICON_MAP['heroicon-o-star'] = 'star'
ICON_MAP['heroicon-m-star'] = 'star'
ICON_MAP['heroicon-s-star'] = 'star'
ICON_MAP['heroicon-o-receipt-percent'] = 'tag_percent'
ICON_MAP['heroicon-m-chevron-double-left'] = 'chevron_double_left'
ICON_MAP['heroicon-m-chevron-double-right'] = 'chevron_double_right'
ICON_MAP['heroicon-m-play'] = 'play'
ICON_MAP['heroicon-o-play'] = 'play'
ICON_MAP['heroicon-s-play'] = 'play'
ICON_MAP['heroicon-s-microphone'] = 'microphone'
ICON_MAP['heroicon-s-phone'] = 'phone'
ICON_MAP['heroicon-s-cube'] = 'cube'
ICON_MAP['heroicon-s-briefcase'] = 'briefcase'
ICON_MAP['heroicon-s-hashtag'] = 'hashtag'
ICON_MAP['heroicon-s-bookmark'] = 'bookmark'
ICON_MAP['heroicon-s-archive-box'] = 'archive_box'
ICON_MAP['heroicon-s-share'] = 'share'
ICON_MAP['heroicon-s-gift'] = 'gift'
ICON_MAP['heroicon-s-camera'] = 'camera'
ICON_MAP['heroicon-s-plus-circle'] = 'plus_circle'
ICON_MAP['heroicon-o-plus-circle'] = 'plus_circle'
ICON_MAP['heroicon-s-user-group'] = 'users'
ICON_MAP['heroicon-s-box'] = 'cube'
ICON_MAP['heroicon-o-bars-3-bottom-right'] = 'menu'
ICON_MAP['heroicon-m-arrow-small-left'] = 'arrow_left'
ICON_MAP['heroicon-m-arrow-small-right'] = 'arrow_right'
ICON_MAP['heroicon-s-arrow-small-left'] = 'arrow_left'
ICON_MAP['heroicon-m-chevron-small-left'] = 'chevron_left'
ICON_MAP['heroicon-m-chevron-small-right'] = 'chevron_right'
ICON_MAP['heroicon-o-chevron-small-left'] = 'chevron_left'
ICON_MAP['heroicon-o-chevron-small-right'] = 'chevron_right'

# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------


def build_svg(group, palette_name):
    base, light, dark = PAL[palette_name]
    prims = SHAPES[group]('m', 'l', 'd', '#FFFFFF')
    content = render(prims, color_fn(None, light, dark))

    # Penjaga di sisi generator: ikon HARUS berwarna sungguhan.
    # Kalau bagian isi cuma punya SATU warna (mis. satu gradasi saja), ikon
    # itu gampang kembali terlihat monokrom -- persis keluhan pengguna.
    colors = set()
    for match in re.finditer(r'(?:fill|stroke)="([^"]+)"', content):
        color = match.group(1)
        if color != 'none':
            colors.add(color)
    if len(colors) < 2:
        raise SystemExit(
            f'ikon `{group}` cuma punya satu warna ({", ".join(colors) or "tidak ada"}) -- '
            'tambahkan aksen (warna kedua) di bentuknya.'
        )

    return (TEMPLATE.replace('{light}', light)
                    .replace('{base}', base)
                    .replace('{dark}', dark)
                    .replace('{content}', content))


# ---------------------------------------------------------------------------
# Nama file SEMANTIK yang dipakai konstanta AppIcon -> bentuk sumbernya.
# Konstanta AppIcon (nav item, menu item, ikon halaman) menunjuk nama-nama
# berbahasa-bisnis ini; bentuk gambarnya sama dengan bentuk kanonis.
# ---------------------------------------------------------------------------

SEMANTIC_FILES = {
    'order': 'bag',
    'history': 'clock',
    'voucher': 'ticket',
    'wishlist': 'heart',
    'settings': 'cog',
    'profile': 'user_circle',
    'privacy': 'shield_check',
    'help': 'question',
    'external-link': 'external',
    'messages': 'chat',
    'export': 'download',
    'arrow-right': 'arrow_right',
    'sign-out': 'sign_out',
    'admin-shield': 'shield_check',
    'api-key': 'key',
    'discount': 'tag',
    'maintenance': 'wrench',
    'payment-gateway': 'globe',
    'payment-method': 'card',
    'reference': 'list',
    'report': 'chart',
    'terms': 'document',
    'transaction': 'banknotes',
    'customizer': 'swatch',
    'wedding-policy': 'stack',
    'general-document': 'document',
    # file bernama sama dengan bentuk kanonisnya
    'flower': 'flower',
    'review': 'review',
    'cart': 'cart',
    'database': 'database',
    'code': 'code',
    'gift': 'gift',
    'home': 'home',
    'sparkles': 'sparkles',
    'users': 'users',
}


def main():
    OUT.mkdir(parents=True, exist_ok=True)

    # File SVG TIDAK lagi digambar di sini.
    #
    # Keputusan pengguna (2026-10-21): "SEMUA ... HARUS PAKAI
    # https://fonts.google.com/icons". Bentuk gambarnya diambil dari
    # Material Symbols oleh `scripts/fetch-material-symbols.py` (rounded +
    # fill1), lalu diwarnai template gradien + drop shadow + animasi.
    #
    # Generator ini TIDAK MENULIS file SVG apa pun (kalau ditulis ulang,
    # bentuk gambar tangan akan menimpa vektor Google). Yang tetap
    # dikeluarkan hanya PETA nama ikon di bawah -- dipakai script pengganti
    # literal dan AppIcon::named()/VendorIconAliases.
    if any(OUT.glob('*.svg')) is False:
        raise SystemExit(
            'folder ikon kosong -- jalankan dulu: python scripts/fetch-material-symbols.py',
        )

    # Peta nama ikon (Heroicon/Remix/GMDI/EOS, vendor + app) -> nama file.
    # Dipakai script pengganti literal dan AppServiceProvider/FilamentIcon.
    # TIDAK ADA kunci yang dikecualikan: semua nama ikon -- termasuk
    # chevron, search (magnifying-glass), dan zoom -- menunjuk vektor
    # Material Symbols dari fonts.google.com/icons.
    php = ['<?php', '', '/**',
           ' * Peta nama ikon (Heroicon, Remix, GMDI, EOS, ...) -> file SVG berwarna.',
           ' *',
           ' * Vektornya dari fonts.google.com/icons (Material Symbols), diambil',
           ' * oleh scripts/fetch-material-symbols.py.',
           ' *',
           ' * DIHASILKAN OLEH scripts/generate-icons.py -- jangan diedit manual.',
           ' */', '', 'return [']
    for icon in sorted(ICON_MAP):
        php.append(f'    \'{icon}\' => \'{ICON_MAP[icon]}\',')
    php.append('];')
    php.append('')
    MAP_OUT.write_text('\n'.join(php), encoding='utf-8')
    print(f'Peta nama ikon: {len(ICON_MAP)} -> {MAP_OUT.name}')


if __name__ == '__main__':
    main()
