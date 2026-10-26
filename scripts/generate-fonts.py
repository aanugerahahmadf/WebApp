#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
generate-fonts.py -- Pasang SEMUA font dari assets/fonts/ ke aplikasi.

Sumber kebenaran: Mobile-And-Backend/assets/fonts/<Keluarga>/
(paket resmi Google Fonts: TTF variable + static/ instansi + OFL + README).

Script ini (rerun-safe; jalankan ulang SETELAH menambah folder font baru):

  1. MENYALIN semua berkas (.ttf, OFL.txt, README.txt, preservasi struktur
     folder static/) dari assets/fonts/<Keluarga>/ ke public/fonts/<Keluarga>/
     -- inilah pemasangannya: server melayani semua font dari aset lokal,
     tanpa CDN. File .ufm (metrik Dompdf, dihasilkan lokal) dilewatkan.
  2. MENULIS public/css/app-font.css: satu @font-face per FACE:
     - TTF variable -> keluarga dasar ('Asap Sharp', 'Google Sans', ...) --
       satu file melayani semua sumurnya (wght/wdth/opsz/slnt), normal +
       wajah italic/oblique dari file italic-nya.
     - static/<instansi> -> keluarga ter-nama dari nama filenya:
       'Asap Sharp Condensed Black', 'Google Sans Flex 24pt Bold',
       'Google Sans 17pt SemiBold Italic', 'Roboto SemiCondensed Thin', ...
  3. MENULIS app/Support/AppFont/font-catalog.php: daftar nama keluarga
     hasil pembacaan nama file -- dibaca AppFont::families() untuk
     whitelist + opsi select (FontSettingsComponent, WebsiteMobileCustomizer).
     Select-nya searchable, jadi ratusan pilihan tetap nyaman.

Jebakan yang dijaga:
  - Nama keluarga harus UNIK (dua @font-face dengan nama sama = wajah
    kedua menimpa yang pertama).
  - Base32... eh, bebas: nama keluarga memuat spasi -- jadi di CSS var
    (`AppFont::styles()` menulis `--app-font-family: '<nama>'`) tanda kutip
    WAJIB. Nama dihasilkan tanpa tanda kutip; pembungkusnya di styles().
  - 'Google Sans Flex' tetap nama keluarga DASAR (pakai TTF variable) --
    compatibility legacy: setting lama tetap valid, dan PDF (PdfLayout)
    tetap memakai instansi statis 24pt yang tidak disentuh script ini.
"""

from __future__ import annotations

import argparse
import shutil
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / "assets" / "fonts"
DST = ROOT / "public" / "fonts"
CSS_PATH = ROOT / "public" / "css" / "app-font.css"
CATALOG_PATH = ROOT / "app" / "Support" / "AppFont" / "font-catalog.php"

COPY_SUFFIXES = {".ttf", ".txt"}
SKIP_SUFFIXES = {".ufm"}  # metrik Dompdf, dihasilkan lokal oleh PdfLayout

# Nama file statis -> berat CSS. Kombinasi "ThinItalic" dibaca token
# ber-token, bukan satu kata.
WEIGHTS = {
    "Thin": 100, "ExtraLight": 200, "Light": 300, "Regular": 400,
    "Medium": 500, "SemiBold": 600, "Bold": 700, "ExtraBold": 800, "Black": 900,
}

# Awalan nama berkas -> nama keluarga dasar. Urutkan panjang dulu,
# karena GoogleSansFlex harus menang atas GoogleSans.
FAMILY_PREFIXES = sorted(
    {
        "AsapSharp": "Asap Sharp",
        "GoogleSans": "Google Sans",
        "GoogleSansFlex": "Google Sans Flex",
        "InstrumentSans": "Instrument Sans",
        "OpenSans": "Open Sans",
        "Roboto": "Roboto",
    }.items(),
    key=lambda kv: len(kv[0]),
    reverse=True,
)


def display_family(folder: str) -> str:
    """Asap_Sharp -> 'Asap Sharp'; Google_Sans_Flex -> 'Google Sans Flex'."""
    return " ".join(part for part in folder.split("_") if part)


def split_tokens(style: str) -> list[tuple[str, str]]:
    """Pecah token gaya ('ThinItalic' -> [('Thin','Thin'), ('Italic','Italic')])."""
    out: list[tuple[str, str]] = []
    i = 0
    tokens = sorted(WEIGHTS, key=len, reverse=True) + ["Italic"]
    while i < len(style):
        for token in tokens:
            if style.startswith(token, i):
                out.append((token, token))
                i += len(token)
                break
        else:
            # Token tak dikenal (mis. 'VariableFont'): kelompokkan sisanya
            # dalam satu token mentah.
            out.append((style[i:], style[i:]))
            i = len(style)
    return out


def parse_file(folder_key: str, path: Path) -> dict | None:
    """Satu berkas TTF -> deskripsi face + nama keluarga display."""
    if path.suffix.lower() != ".ttf":
        return None

    stem = path.stem
    if stem.count("-") > 0:
        base, _, style = stem.partition("-")
    else:
        base, style = stem, ""

    # Keluarga dasar dari awalan berkas.
    family = None
    rest = None
    for prefix, display in FAMILY_PREFIXES:
        if base.startswith(prefix):
            family = display
            rest = base[len(prefix):]
            break
    if family is None:
        # Folder tanpa awalan yang dikenal: keluarga = nama folder,
        # berkas-nya 1:1. (Folder baru di masa depan otomatis masuk.)
        family = display_family(folder_key)
        rest = base

    # Suffix base ('_Condensed', '_24pt', '_SemiExpanded') -> pembeda nama
    # keluarga statis: 'Asap Sharp Condensed', 'Google Sans Flex 24pt'.
    suffix_words = " ".join(w for w in rest.split("_") if w)

    # Berapa face dari berkas ini, apa gayanya, beratnya berapa.
    faces: list[dict] = []
    if "VariableFont" in style:
        # TTF variable = keluarga DASAR (folder display), melayani semua
        # sumurnya. Italic file -> wajah italic (asli), bukan keluarga lain.
        italic = "Italic" in style
        face = {
            "url": f"/fonts/{folder_key}/{path.name}",
            "style": "italic" if italic else "normal",
            "weight": "100 1000",
            "stretch": "25% 151%" if family == "Google Sans Flex" else None,
        }
        faces.append(face)
        # Google Sans Flex tidak punya file italic: condong diambil dari
        # sumur slnt di file yang SAMA -- wajah kedua oblique (CSS Fonts 4).
        if not italic and family == "Google Sans Flex":
            oblique = dict(face)
            oblique["style"] = "oblique 0deg 10deg"
            faces.append(oblique)
        return {"kind": "variable", "name": family, "faces": faces}
    else:
        tokens = [t for t, _ in split_tokens(style) if t]
        weight_tokens = [t for t in tokens if t in WEIGHTS]
        weight = WEIGHTS[weight_tokens[0]] if weight_tokens else 400
        italic = "Italic" in tokens
        words = [w for w in suffix_words.split() if w]
        words += weight_tokens
        words += ["Italic"] if italic else []
        name = " ".join(w for w in ([family] + words) if w).strip()
        faces.append({
            "url": f"/fonts/{folder_key}/static/{path.name}",
            "style": "italic" if italic else "normal",
            "weight": str(weight),
            "stretch": None,
        })
        return {"kind": "static", "name": name, "faces": faces}


def collect() -> tuple[dict[str, dict], list[Path]]:
    """Keluarga per folder -> dict name -> {'faces': [...], 'files': [...]}"""
    if not SRC.is_dir():
        sys.exit(f"Tidak ada folder sumber: {SRC}")
    copied: list[Path] = []
    families: dict[str, dict] = {}

    for folder in sorted(p for p in SRC.iterdir() if p.is_dir()):
        folder_key = folder.name
        for path in sorted(folder.rglob("*")):
            if path.is_dir() or path.suffix.lower() not in COPY_SUFFIXES:
                continue
            if path.suffix.lower() in SKIP_SUFFIXES:
                continue
            rel = path.relative_to(folder)
            dst = DST / folder_key / rel
            dst.parent.mkdir(parents=True, exist_ok=True)
            changed = (not dst.exists()) or dst.stat().st_size != path.stat().st_size
            if changed:
                shutil.copy2(path, dst)
            if changed:
                copied.append(dst)

            face = parse_file(folder_key, path)
            if face is None:
                continue
            entry = families.setdefault(face["name"], {"faces": []})
            for f in face["faces"]:
                if f not in entry["faces"]:
                    entry["faces"].append(f)

    return families, copied


def css_for(families: dict[str, dict]) -> str:
    names = sorted(families)
    out = [
        "/*",
        " * PUBLIC/css/app-font.css -- DIHASILKAN scripts/generate-fonts.py.",
        " * JANGAN edit tangan kecuali pengaturan umum (aturan `*`, optical-",
        " * sizing) -- ulangin script untuk menyegarkan daftar @font-face.",
        " *",
        " * Sumber: assets/fonts/<Keluarga>/ (paket resmi Google Fonts),",
        " * dipasang ke public/fonts/<Keluarga>/ oleh script yang sama.",
        " * Semua keluarga TTF VARIABLE + SEMUA instansi statis dalam",
        " * folder itu jadi pilihan di select font (searchable).",
        " */",
        "",
    ]
    for name in names:
        entry = families[name]
        out.append(f"/* {name} */")
        for face in entry["faces"]:
            props = [
                f"font-family: '{name}';",
                f'src: url("{face["url"]}") format("truetype-variations");',
                f"font-weight: {face['weight']};",
                f"font-style: {face['style']};",
                "font-display: swap;",
            ]
            if face.get("stretch"):
                props.insert(2, f"font-stretch: {face['stretch']};")
            out.append("@font-face {")
            for p in props:
                out.append(f"    {p}")
            out.append("}")
            out.append("")
    # Aturan pakai variabel + optical sizing -- bagian "pengaturan umum"
    # dijaga tetap di sini supaya sekali terbaca.
    out += [
        "/* Keluarga aktif -- `--app-font-family` ditulis `AppFont::styles()`",
        " * per panel (storefront / admin). Halaman yang memuat CSS ini TANPA",
        " * styles() (halaman error glass: tidak boleh query Setting) jatuh",
        " * ke fallback default aplikasi. */",
        "*,",
        "::before,",
        "::after {",
        "    font-family: var(--app-font-family, 'Google Sans Flex'), sans-serif !important;",
        "}",
        "",
        ":root {",
        "    font-optical-sizing: auto;",
        "}",
        "",
    ]
    return "\n".join(out)


def catalog_for(families: dict[str, dict]) -> str:
    names = sorted(families)
    assert "Google Sans Flex" in names, "Keluarga bawaan hilang dari katalog!"
    lines = [
        "<?php",
        "",
        "/*",
        " * DIHASILKAN scripts/generate-fonts.py -- DAFTAR NAMA KELUARGA FONT.",
        " *",
        " * Satu entri = satu keluarga @font-face di public/css/app-font.css.",
        " * Sumber nama: file TTF di assets/fonts/<Keluarga>/ -- TTF variable",
        " * jadi keluarga dasar folder-nya; instansi static/ jadi keluarga",
        " * ter-nama ('Asap Sharp Condensed Black', 'Google Sans Flex 24pt",
        " * Bold', ...). Dibaca AppFont::families(): whitelist pilihan font",
        " * (select searchable) + validasi nilai yang disimpan admin.",
        " *",
        " * Ulang script setelah menambah folder/berkas font.",
        " */",
        "",
        "return [",
    ]
    for name in names:
        entry = families[name]
        src = ", ".join(Path(f["url"]).name for f in entry["faces"])
        lines.append(f"    '{name}', // {src}")
    lines += ["];", ""]
    return "\n".join(lines)


def main() -> int:
    ap = argparse.ArgumentParser(description="Pasang semua font aset lokal.")
    ap.add_argument("--check", action="store_true",
                    help="Tidak menulis apa pun; keluar 1 kalau ada bedanya.")
    args = ap.parse_args()

    families, copied = collect()

    css = css_for(families)
    catalog = catalog_for(families)
    css_old = CSS_PATH.read_text(encoding="utf-8") if CSS_PATH.exists() else ""
    cat_old = CATALOG_PATH.read_text(encoding="utf-8") if CATALOG_PATH.exists() else ""

    if args.check:
        diff = [f for f in copied]
        if css != css_old:
            diff.append("public/css/app-font.css (beda)")
        if catalog != cat_old:
            diff.append("font-catalog.php (beda)")
        if diff:
            for d in diff:
                print(d)
            return 1
        print("Semua font sudah terpasang; CSS & katalog sudah sinkron.")
        return 0

    CSS_PATH.write_text(css, encoding="utf-8")
    CATALOG_PATH.write_text(catalog, encoding="utf-8")

    names = sorted(families)
    print(f"Keluarga font: {len(names)}")
    for name in names:
        faces = ", ".join(f"{f['style']} {f['weight']}" for f in families[name]["faces"])
        print(f"  {name}  ({faces})")
    print(f"Keluaran: {CSS_PATH.relative_to(ROOT)} + {CATALOG_PATH.relative_to(ROOT)}")
    print(f"File disalin: {len(copied)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
