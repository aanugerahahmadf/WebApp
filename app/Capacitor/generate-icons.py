#!/usr/bin/env python3
"""
Generate every app icon of the Capacitor + Electron shells from ONE source:
the brand logo of the Laravel application.

Source of truth
---------------
``Mobile-And-Backend/public/images/logo.png``

That file is already the canonical brand asset of the application:

* ``AppFont::brandLogo()`` falls back to ``/images/logo.png``
* ``public/manifest.webmanifest`` points its icon at ``/images/logo.png``
* ``resources/views/*/pwa-head`` uses it as the apple-touch-icon
* the PDF invoice renders ``public_path('images/logo.png')``

So the desktop shells have to show the SAME artwork -- not the default
Capacitor "X" logo that ships with the template.

Why a script instead of hand-copied PNGs
----------------------------------------
The shells need that one artwork at 20+ sizes spread over three platforms:

* Android launcher icon, adaptive foreground and 11 splash drawables
* iOS app icon (1024x1024, no alpha channel) and 3 splash images
* Electron ``icon.png`` + multi-resolution ``icon.ico``
* the web favicon used by the launcher page

Copying files by hand means one forgotten density silently keeps shipping the
old blue "X" logo, and there is no way to tell that apart from "it works".
Running this script regenerates the whole set in one go, so the shells can
never drift apart again.

Usage
-----
    python generate-icons.py
    python generate-icons.py --preview    # additionally writes preview/contact-sheet.png

Paths are resolved relative to this file, so the script can be run from any
working directory.
"""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

try:
    from PIL import Image, ImageChops, ImageDraw, ImageFilter
except ImportError:  # pragma: no cover - guidance for a missing dependency
    sys.exit(
        "Pillow is required.\n"
        "  python -m pip install pillow"
    )


HERE = Path(__file__).resolve().parent
BACKEND = HERE.parent.parent
SOURCE = (BACKEND / "public" / "images" / "logo.png").resolve()

# The shells that get the icon set. Both wrap the same Laravel server, so
# both carry the same brand mark.
APPS = ("DekstopApp", "DekstopAdminApp")

WHITE = (255, 255, 255, 255)

# Android launcher icon = 48dp, adaptive icon foreground = 108dp.
ANDROID_DENSITIES: dict[str, tuple[int, int]] = {
    "mdpi": (48, 108),
    "hdpi": (72, 162),
    "xhdpi": (96, 216),
    "xxhdpi": (144, 324),
    "xxxhdpi": (192, 432),
}

# Windows .ico: the same set the template shipped with, plus 24px.
ICO_SIZES = (16, 24, 32, 48, 64, 128, 256)

# How much of the canvas the artwork fills, per target.
SCALE_TRANSPARENT = 0.96   # logo.png / icon.png - artwork only
SCALE_ICO = 0.94           # favicon + electron icon.ico
SCALE_LEGACY = 0.82        # Android legacy launcher icon
SCALE_ADAPTIVE = 0.58      # Android adaptive foreground (66dp safe zone of 108dp)
SCALE_APPLE = 0.72         # iOS app icon
SCALE_SPLASH = 0.30        # splash screens, relative to the SHORT side
SCALE_SPLASH_IOS = 0.20    # iOS splash: the artwork is square, so it needs less


# --------------------------------------------------------------------------- #
# Drawing helpers
# --------------------------------------------------------------------------- #


def load_art() -> Image.Image:
    """Return the brand artwork cropped to its visible bounds, padded square.

    The source PNG has a transparent margin. Cropping to the alpha bounding
    box first means the artwork is scaled to the icon instead of being scaled
    around a mostly empty canvas, so a 48px launcher icon is actually legible.
    """
    src = Image.open(SOURCE).convert("RGBA")

    # Faint anti-aliased pixels (alpha 1..7) would otherwise stretch the
    # bounding box to the full canvas, so the alpha is thresholded first.
    alpha = src.getchannel("A").point(lambda value: 255 if value > 8 else 0)
    bbox = alpha.getbbox()

    if bbox is None:
        raise SystemExit(f"Logo ini kosong (tidak ada piksel yang terlihat): {SOURCE}")

    art = src.crop(bbox)

    width, height = art.size
    side = max(width, height)
    square = Image.new("RGBA", (side, side), (0, 0, 0, 0))
    square.paste(art, ((side - width) // 2, (side - height) // 2))

    return square


def shape_mask(size: int, shape: str) -> Image.Image:
    """Return an 'L' mask (255 = inside the shape) for the requested shape."""
    mask = Image.new("L", (size, size), 0)
    draw = ImageDraw.Draw(mask)

    if shape == "circle":
        draw.ellipse((0, 0, size - 1, size - 1), fill=255)
    elif shape == "rounded":
        draw.rounded_rectangle((0, 0, size - 1, size - 1), radius=int(size * 0.22), fill=255)
    elif shape != "square":
        raise ValueError(f"Bentuk tidak dikenal: {shape}")

    return mask


def render(
    art: Image.Image,
    size: int,
    scale: float,
    background: tuple[int, int, int, int] | None = None,
    shape: str = "square",
) -> Image.Image:
    """Draw the artwork centred on a `size` x `size` canvas.

    `background` None means a transparent canvas. `shape` masks the WHOLE
    canvas (background included), so a round icon gets transparent corners
    instead of a white square with a white circle painted in it.
    """
    canvas = Image.new("RGBA", (size, size), background if background else (0, 0, 0, 0))

    side = max(1, int(round(size * scale)))
    scaled = art.resize((side, side), Image.LANCZOS)

    # The source artwork is only ~282px wide, so the big targets (512px logo,
    # 1024px iOS icon) are UPSCALED and come out soft. A mild unsharp mask
    # puts the line art back in shape. It is only applied when upscaling --
    # sharpening a downscale would just add halos around the thin strokes.
    if side > art.size[0]:
        scaled = scaled.filter(ImageFilter.UnsharpMask(radius=1.2, percent=70, threshold=3))

    canvas.alpha_composite(scaled, ((size - side) // 2, (size - side) // 2))

    if shape != "square":
        mask = shape_mask(size, shape)
        canvas.putalpha(ImageChops.multiply(canvas.getchannel("A"), mask))

    return canvas


def quantize(image: Image.Image) -> Image.Image:
    """Reduce an RGBA image to a 256 colour palette.

    The brand mark is a soft gold gradient on transparency -- the worst case
    for PNG, because every anti-aliased pixel becomes its own colour. The
    palette version is ~4x smaller and visually identical, which matters
    because the artwork is embedded in the APK, the .ipa and the installer.

    RGB images are left alone: the iOS app icon must stay truecolor, and the
    splash images are mostly flat white, so they compress well anyway.
    """
    if image.mode != "RGBA":
        return image

    try:
        return image.quantize(colors=256, method=Image.FASTOCTREE)
    except ValueError:  # pragma: no cover - only on exotic Pillow builds
        return image


def save_png(image: Image.Image, path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    quantize(image).save(path, "PNG", optimize=True)
    print(f"  png  {path.relative_to(HERE)}")


def save_ico(art: Image.Image, path: Path) -> None:
    """Write a multi-resolution .ico.

    The base image has to be at least as large as the biggest entry, and
    PIL resamples it for the smaller ones. The base is the PALETTE version of
    the artwork: the .ico then stores the palette too, which is ~4x smaller
    than the truecolor file for the exact same picture.
    """
    path.parent.mkdir(parents=True, exist_ok=True)

    base = quantize(render(art, max(ICO_SIZES), SCALE_ICO))
    base.save(path, "ICO", sizes=[(size, size) for size in ICO_SIZES])
    print(f"  ico  {path.relative_to(HERE)}")


def build_refresh_icon(size: int = 32) -> Image.Image:
    """Ikon "Refresh" -- lingkaran panah emas brand.

    Digambar pada skala 8x lalu dikecilkan, supaya tepinya halus di ukuran
    kecil (~16px).

    Catatan: ikon ini TIDAK dipakai di item menu konteks Electron -- yang
    ditampilkan di sana cuma teks "Refresh". File-nya tetap ada karena ini
    aset brand yang siap pakai (misalnya untuk tombol di panel).
    """
    import math

    scale = 8
    big = size * scale
    canvas = Image.new("RGBA", (big, big), (0, 0, 0, 0))
    draw = ImageDraw.Draw(canvas)

    centre = big / 2
    radius = big * 0.33
    stroke = max(1, int(big * 0.085))
    gold = (234, 179, 8, 255)

    # Busur: dari 120 derajat ke 380 derajat, jadi ada celah di kanan atas.
    start, end = 120, 380
    draw.arc(
        (centre - radius, centre - radius, centre + radius, centre + radius),
        start=start,
        end=end,
        fill=gold,
        width=stroke,
    )

    # Kepala panah di ujung busur, menghadap arah putaran (searah jarum jam).
    angle = math.radians(end)
    point = (centre + radius * math.cos(angle), centre + radius * math.sin(angle))

    # Arah tangensial = arah sudut bertambah; normal = arah radial.
    tangent = (-math.sin(angle), math.cos(angle))
    normal = (math.cos(angle), math.sin(angle))

    head_length = big * 0.17
    head_width = big * 0.20

    tip = (point[0] + tangent[0] * head_length, point[1] + tangent[1] * head_length)
    base_a = (point[0] + normal[0] * head_width / 2, point[1] + normal[1] * head_width / 2)
    base_b = (point[0] - normal[0] * head_width / 2, point[1] - normal[1] * head_width / 2)

    draw.polygon((tip, base_a, base_b), fill=gold)

    return canvas.resize((size, size), Image.LANCZOS)


# --------------------------------------------------------------------------- #
# Per-platform generators
# --------------------------------------------------------------------------- #


def generate_web(app: Path, art: Image.Image) -> None:
    """Launcher page assets: the big logo and the favicon."""
    print("[web]")

    save_png(render(art, 512, SCALE_TRANSPARENT), app / "src" / "assets" / "imgs" / "logo.png")
    save_ico(art, app / "src" / "assets" / "icon" / "favicon.ico")


def generate_electron(app: Path, art: Image.Image) -> None:
    """Electron assets: window/app icon for every platform electron-builder targets."""
    print("[electron]")

    save_png(render(art, 512, SCALE_TRANSPARENT), app / "electron" / "assets" / "icon.png")
    save_ico(art, app / "electron" / "assets" / "icon.ico")
    save_png(build_refresh_icon(), app / "electron" / "assets" / "refresh.png")


def generate_android(app: Path, art: Image.Image) -> None:
    """Android launcher icon, adaptive foreground and splash drawables."""
    print("[android]")

    res = app / "android" / "app" / "src" / "main" / "res"

    for density, (launcher_size, foreground_size) in ANDROID_DENSITIES.items():
        mipmap = res / f"mipmap-{density}"

        # Legacy icon: white rounded square, artwork on top.
        save_png(
            render(art, launcher_size, SCALE_LEGACY, background=WHITE, shape="rounded"),
            mipmap / "ic_launcher.png",
        )

        # Round variant: white circle, transparent corners.
        save_png(
            render(art, launcher_size, SCALE_LEGACY, background=WHITE, shape="circle"),
            mipmap / "ic_launcher_round.png",
        )

        # Adaptive foreground: artwork only, sized for the 66dp safe zone.
        save_png(
            render(art, foreground_size, SCALE_ADAPTIVE),
            mipmap / "ic_launcher_foreground.png",
        )

    # Splash drawables. The dimensions of the shipped files are kept as-is
    # (the plugin renders them with CENTER_CROP, so the aspect ratio matters).
    for splash in sorted(res.glob("drawable*/splash.png")):
        with Image.open(splash) as current:
            width, height = current.size

        side = int(round(min(width, height) * SCALE_SPLASH))
        logo = render(art, side, 1.0)

        canvas = Image.new("RGBA", (width, height), WHITE)
        canvas.alpha_composite(logo, ((width - side) // 2, (height - side) // 2))
        save_png(canvas.convert("RGB"), splash)


def generate_ios(app: Path, art: Image.Image) -> None:
    """iOS app icon and splash images."""
    print("[ios]")

    # The Xcode project nests the catalogue one level deeper than the project
    # folder: ios/App/App/Assets.xcassets.
    xcassets = app / "ios" / "App" / "App" / "Assets.xcassets"

    # App icon: 1024x1024 and NO alpha channel, or the App Store rejects it.
    icon = render(art, 1024, SCALE_APPLE, background=WHITE).convert("RGB")
    save_png(icon, xcassets / "AppIcon.appiconset" / "AppIcon-512@2x.png")

    for splash in sorted((xcassets / "Splash.imageset").glob("splash-*.png")):
        with Image.open(splash) as current:
            width, height = current.size

        side = int(round(min(width, height) * SCALE_SPLASH_IOS))
        logo = render(art, side, 1.0)

        canvas = Image.new("RGBA", (width, height), WHITE)
        canvas.alpha_composite(logo, ((width - side) // 2, (height - side) // 2))
        save_png(canvas.convert("RGB"), splash)


# --------------------------------------------------------------------------- #
# Preview
# --------------------------------------------------------------------------- #


def build_preview(art: Image.Image) -> None:
    """Write a contact sheet so the result can be eyeballed without a build."""
    tiles: list[tuple[str, Image.Image]] = []

    for name, image in (
        ("512 logo", render(art, 512, SCALE_TRANSPARENT)),
        ("192 launcher", render(art, 192, SCALE_LEGACY, background=WHITE, shape="rounded")),
        ("192 round", render(art, 192, SCALE_LEGACY, background=WHITE, shape="circle")),
        ("432 adaptive", render(art, 432, SCALE_ADAPTIVE)),
        ("1024 apple", render(art, 1024, SCALE_APPLE, background=WHITE)),
    ):
        tiles.append((name, image))

    cell = 256
    pad = 24
    label_h = 22
    sheet = Image.new("RGB", (cell * len(tiles) + pad * (len(tiles) + 1), cell + pad * 2 + label_h), (241, 245, 249))
    draw = ImageDraw.Draw(sheet)

    for index, (name, image) in enumerate(tiles):
        x = pad + index * (cell + pad)
        sheet.paste(image.convert("RGB"), (x, pad), image)
        draw.text((x + 4, pad + cell + 4), name, fill=(31, 41, 55))

    out = HERE / "preview" / "contact-sheet.png"
    out.parent.mkdir(parents=True, exist_ok=True)
    sheet.save(out, "PNG")
    print(f"\nPreview: {out}")

    build_ico_preview(art)


def build_ico_preview(art: Image.Image) -> None:
    """Show the .ico at taskbar size, on a light AND a dark surface.

    The brand mark is thin gold line art, so the small sizes are where it can
    fall apart. Seeing it on both surfaces is the only honest check.
    """
    import io

    buffer = io.BytesIO()
    quantize(render(art, max(ICO_SIZES), SCALE_ICO)).save(
        buffer, "ICO", sizes=[(size, size) for size in ICO_SIZES]
    )
    buffer.seek(0)

    with Image.open(buffer) as icon:
        sizes = (16, 24, 32, 48, 64)

        gap = 10
        strip = Image.new("RGB", (sum(sizes) + gap * (len(sizes) + 1), 150), (240, 240, 240))

        for row, surface in enumerate(((255, 255, 255), (40, 44, 52))):
            x = gap
            for size in sizes:
                frame = Image.new("RGBA", (size, size), surface + (255,))
                frame.alpha_composite(icon.ico.getimage((size, size)).convert("RGBA"))
                strip.paste(frame.convert("RGB"), (x, 12 + row * 66))
                x += size + gap

    out = HERE / "preview" / "ico-sizes.png"
    strip.resize((strip.width * 3, strip.height * 3), Image.NEAREST).save(out, "PNG")
    print(f"Preview: {out}")


# --------------------------------------------------------------------------- #


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--preview", action="store_true", help="tulis contact sheet untuk diperiksa")
    args = parser.parse_args()

    if not SOURCE.exists():
        raise SystemExit(f"Logo sumber tidak ditemukan: {SOURCE}")

    print(f"Sumber logo : {SOURCE}")
    print(f"Ukuran      : {Image.open(SOURCE).size}\n")

    art = load_art()

    for name in APPS:
        app = HERE / name
        print(f"=== {name} ===")
        generate_web(app, art)
        generate_electron(app, art)
        generate_android(app, art)
        generate_ios(app, art)
        print()

    if args.preview:
        build_preview(art)

    print("Selesai. Jalankan `npm run sync` di masing-masing app untuk menyalin dist/ ke native project.")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
