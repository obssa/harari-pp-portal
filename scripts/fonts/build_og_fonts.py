"""Static bold TrueType fonts for generated sharing images (GD needs TTF).

Writes resources/fonts/og/Inter-Bold.ttf (Latin) and
resources/fonts/og/NotoSansEthiopic-Bold.ttf (Ethiopic block) from the
variable fonts in node_modules. Run from the repository root.
"""
from pathlib import Path

from fontTools import subset
from fontTools.ttLib import TTFont
from fontTools.varLib import instancer

ROOT = Path.cwd()
PACKAGES = ROOT / "node_modules/@fontsource-variable"
TARGET = ROOT / "resources/fonts/og"
TARGET.mkdir(parents=True, exist_ok=True)

jobs = [
    (PACKAGES / "inter/files/inter-latin-wght-normal.woff2", TARGET / "Inter-Bold.ttf", None),
    (PACKAGES / "noto-sans-ethiopic/files/noto-sans-ethiopic-ethiopic-wght-normal.woff2", TARGET / "NotoSansEthiopic-Bold.ttf",
     [0x0020, 0x00A0, *range(0x1200, 0x1380)]),
]

for source, target, unicodes in jobs:
    font = TTFont(str(source))
    font.flavor = None
    font = instancer.instantiateVariableFont(font, {"wght": 700})
    if unicodes is not None:
        options = subset.Options()
        options.layout_features = ["*"]
        options.notdef_outline = True
        subsetter = subset.Subsetter(options)
        subsetter.populate(unicodes=unicodes)
        subsetter.subset(font)
    font.save(str(target))
    print(f"{target.relative_to(ROOT)}: {target.stat().st_size} bytes")
