"""Final verification: no image in the Capacitor shells may still carry the
default Capacitor template artwork (the blue "X").

The brand mark is thin gold line art, so a colour RATIO is useless here: a
splash screen is 99% white and a 48px launcher icon is mostly anti-aliased
strokes. What matters is that the image CONTAINS gold and contains NO
template blue. The thresholds below are calibrated against the real output
(the smallest generated image, the 48px mdpi launcher icon, still measures
50 gold pixels).
"""

import os

from PIL import Image

BASE = os.path.dirname(os.path.abspath(__file__))
SKIP_DIRS = {"node_modules", ".gradle", "build", ".idea", ".git", "preview"}

# Gold of the brand mark: bronze (159,125,68) up to pale gold (249,227,173).
MIN_GOLD_PIXELS = 20


def is_gold(pixel: tuple[int, int, int]) -> bool:
    r, g, b = pixel
    return r - b >= 20 and g - b >= 8 and r >= 90


def is_template_blue(pixel: tuple[int, int, int]) -> bool:
    r, g, b = pixel
    return b > 130 and b - r >= 45 and b - g >= 25


failures = []
checked = 0

for root, dirs, files in os.walk(BASE):
    dirs[:] = [d for d in dirs if d not in SKIP_DIRS]
    for name in files:
        if not name.lower().endswith((".png", ".ico")):
            continue
        path = os.path.join(root, name)

        with Image.open(path) as image:
            rgb = image.convert("RGB")
            small = rgb.resize(
                (max(1, rgb.width // 4), max(1, rgb.height // 4)), Image.LANCZOS
            )
            pixels = list(small.getdata())

        gold = sum(1 for p in pixels if is_gold(p))
        blue = sum(1 for p in pixels if is_template_blue(p))
        checked += 1

        if blue > 0:
            failures.append(f"{os.path.relpath(path, BASE)}: masih ada biru template ({blue} px)")
        elif gold < MIN_GOLD_PIXELS:
            failures.append(f"{os.path.relpath(path, BASE)}: logo brand tidak terbawa ({gold} px)")

print(f"diperiksa: {checked} berkas gambar")

if failures:
    print(f"\nGAGAL ({len(failures)}):")
    for failure in failures:
        print(f"  - {failure}")
    raise SystemExit(1)

print("SEMUA ikon sudah memakai logo brand; tidak ada sisa logo template Capacitor.")
