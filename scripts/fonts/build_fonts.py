"""Builds the self-hosted web fonts in public/fonts/portal/ (PLAN.md §9, §12).

- Inter (variable weight): the Latin and Latin Extended files from
  @fontsource-variable/inter, copied as they are.
- Noto Sans Ethiopic (variable weight): subset to the main Ethiopic block
  (U+1200-U+137F: every syllable, punctuation mark and numeral used by
  Amharic, Harari and Tigrinya) plus spaces, keeping all OpenType features.

File names carry the font version, so they can be cached for a year. Run
after updating the font packages (bump VERSION) and commit the output:

    python scripts/fonts/build_fonts.py
"""
import shutil
from pathlib import Path

from fontTools import subset

VERSION = "v5"
ROOT = Path(__file__).resolve().parents[2]
PACKAGES = ROOT / "node_modules/@fontsource-variable"
TARGET = ROOT / "public/fonts/portal"
TARGET.mkdir(parents=True, exist_ok=True)

for part in ("latin", "latin-ext"):
    source = PACKAGES / f"inter/files/inter-{part}-wght-normal.woff2"
    target = TARGET / f"inter-{part}-wght-{VERSION}.woff2"
    shutil.copyfile(source, target)
    print(f"{target.relative_to(ROOT)}: {target.stat().st_size} bytes")

options = subset.Options()
options.flavor = "woff2"
options.layout_features = ["*"]
options.name_IDs = ["*"]
options.notdef_outline = True

source = PACKAGES / "noto-sans-ethiopic/files/noto-sans-ethiopic-ethiopic-wght-normal.woff2"
target = TARGET / f"noto-sans-ethiopic-wght-{VERSION}.woff2"
font = subset.load_font(str(source), options)
subsetter = subset.Subsetter(options)
subsetter.populate(unicodes=[0x0020, 0x00A0, *range(0x1200, 0x1380)])
subsetter.subset(font)
subset.save_font(font, str(target), options)
print(f"{target.relative_to(ROOT)}: {source.stat().st_size} -> {target.stat().st_size} bytes")
