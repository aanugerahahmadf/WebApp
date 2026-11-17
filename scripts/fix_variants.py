# GESER: semua varian heroicon-(m|o|s)-* dipetakan (kini hanya sebagian s).
# Ganti blok loop _VARIANTS dengan versi penuh.

PATH = 'scripts/generate-icons.py'
src = open(PATH, encoding='utf-8').read()

start = src.index("_VARIANTS = {")
end = src.index("ICON_MAP['heroicon-o-globe-alt']")
block = """for key in list(_SIMPLE):
    ICON_MAP['heroicon-m-' + key] = _SIMPLE[key]
    ICON_MAP['heroicon-o-' + key] = _SIMPLE[key]
    ICON_MAP['heroicon-s-' + key] = _SIMPLE[key]

"""
src = src[:start] + block + src[end:]
open(PATH, 'w', encoding='utf-8').write(src)
print('loop varian diganti')
