#!/usr/bin/env python3
"""Line coverage per area from a Clover report (CLAUDE.md rule 11).

The whole suite must reach the Pest minimum; the domain code must also reach
its own minimum, so well-tested admin screens cannot hide untested domain
logic.

    python scripts/coverage-gate.py coverage.xml --min 80 app/Domain app/Services app/Support
"""
from __future__ import annotations

import argparse
import sys
import xml.etree.ElementTree as ET
from pathlib import PurePosixPath


def area_of(path: str, areas: list[str]) -> str | None:
    normalised = PurePosixPath(path.replace("\\", "/")).as_posix()
    for area in areas:
        if f"/{area.strip('/')}/" in f"/{normalised}":
            return area
    return None


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("clover")
    parser.add_argument("areas", nargs="+")
    parser.add_argument("--min", type=float, default=80.0)
    args = parser.parse_args()

    totals = {area: [0, 0] for area in args.areas}  # covered, statements
    for file in ET.parse(args.clover).getroot().iter("file"):
        area = area_of(file.get("name", ""), args.areas)
        if area is None:
            continue
        metrics = file.find("metrics")
        if metrics is None:
            continue
        totals[area][0] += int(metrics.get("coveredstatements", 0))
        totals[area][1] += int(metrics.get("statements", 0))

    failed = False
    for area, (covered, statements) in totals.items():
        if statements == 0:
            print(f"{area}: no code found in the report")
            failed = True
            continue
        percent = covered / statements * 100
        verdict = "ok" if percent >= args.min else f"below {args.min:.0f}%"
        print(f"{area}: {percent:.1f}% of {statements} statements ({verdict})")
        failed = failed or percent < args.min

    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
