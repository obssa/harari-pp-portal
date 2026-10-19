#!/usr/bin/env python3
"""Fails when a Blade template shows text that does not go through __() (CLAUDE.md rule 2).

Visible text means text between tags and the human-readable attributes
(alt, title, aria-label, placeholder, label). Blade echoes, directives, PHP
blocks, comments, scripts and styles are ignored, as are strings made only of
digits, punctuation and symbols.

    python scripts/check-hardcoded-strings.py            # check resources/views
    python scripts/check-hardcoded-strings.py --self-test

A template that is developer-facing and never shown in production can be
listed in EXEMPT with the reason.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VIEWS = ROOT / "resources" / "views"

EXEMPT = {
    # Developer styleguide, never served in production (ADR-0010).
    "styleguide.blade.php",
}

READABLE_ATTRIBUTES = ("alt", "title", "aria-label", "placeholder", "label")
LETTER = re.compile(r"[A-Za-zሀ-፿ⶀ-⷟]")


def blank(text: str, start: int, end: int) -> str:
    """Replaces text[start:end] with spaces, keeping newlines so line numbers stay right."""
    chunk = text[start:end]
    return text[:start] + re.sub(r"[^\n]", " ", chunk) + text[end:]


def skip_parens(text: str, start: int) -> int:
    """Index just after the parenthesised group opening at text[start]."""
    depth = 0
    quote = ""
    i = start
    while i < len(text):
        c = text[i]
        if quote:
            if c == "\\":
                i += 2
                continue
            if c == quote:
                quote = ""
        elif c in "'\"":
            quote = c
        elif c == "(":
            depth += 1
        elif c == ")":
            depth -= 1
            if depth == 0:
                return i + 1
        i += 1
    return len(text)


def strip_blade(text: str) -> str:
    for pattern in (
        r"\{\{--.*?--\}\}",
        r"<!--.*?-->",
        r"@php\b.*?@endphp",
        r"<script\b.*?</script>",
        r"<style\b.*?</style>",
        r"\{\{.*?\}\}",
        r"\{!!.*?!!\}",
    ):
        for match in reversed(list(re.finditer(pattern, text, re.S))):
            text = blank(text, match.start(), match.end())

    # Directives such as @if(...), @props([...]), @class([...]).
    i = 0
    while True:
        match = re.compile(r"@[A-Za-z_]+").search(text, i)
        if not match:
            break
        end = match.end()
        j = end
        while j < len(text) and text[j] in " \t":
            j += 1
        if j < len(text) and text[j] == "(":
            end = skip_parens(text, j)
        text = blank(text, match.start(), end)
        i = end
    return text


def findings(source: str) -> list[tuple[int, str]]:
    text = strip_blade(source)
    found: list[tuple[int, str]] = []

    # A tag ends at the first ">" outside quotes ("->" in a component attribute is not the end).
    for tag in re.finditer(r"<(?:[^<>\"']|\"[^\"]*\"|'[^']*')+>", text):
        for attribute in READABLE_ATTRIBUTES:
            for value in re.finditer(rf'(?<![\w:-]){attribute}\s*=\s*"([^"]*)"', tag.group(0)):
                if LETTER.search(value.group(1)):
                    line = text.count("\n", 0, tag.start()) + 1
                    found.append((line, f'{attribute}="{value.group(1).strip()}"'))
        text = blank(text, tag.start(), tag.end())

    for line_number, line in enumerate(text.split("\n"), start=1):
        words = line.strip()
        if words and LETTER.search(words):
            found.append((line_number, words))
    return found


def check(paths: list[Path]) -> int:
    problems = 0
    for path in paths:
        if path.name in EXEMPT:
            continue
        for line, snippet in findings(path.read_text(encoding="utf-8")):
            problems += 1
            print(f"{path.relative_to(ROOT).as_posix()}:{line}: hard-coded text: {snippet}")
    if problems:
        print(f"\n{problems} hard-coded string(s). Put the text in lang/en.json (and am, om, har) and use __('key').")
        return 1
    print(f"OK: {len(paths)} templates, no hard-coded visible text.")
    return 0


def self_test() -> int:
    cases = {
        "<p>{{ __('site.name') }}</p>": [],
        "<p>Hello world</p>": ["Hello world"],
        '<img src="x.png" alt="Party logo">': ['alt="Party logo"'],
        '<img alt="{{ $media->altFor() }}">': [],
        "{{-- a comment with words --}}<span>·</span>": [],
        "@props(['title' => 'Default title'])\n<div></div>": [],
        "<p>ሰላም</p>": ["ሰላም"],
        "@if ($x)\n  <b>{{ $x }}</b>\n@endif": [],
        "<span>© 2026 — 12:30</span>": [],
        '<a href="/en" class="text-sm">{{ $name }}</a>': [],
        '<button aria-label="Close menu"></button>': ['aria-label="Close menu"'],
        "@php\n $a = 'Some text';\n@endphp": [],
        "<script>const label = 'Hi';</script>": [],
        '<x-ui.badge :tone="$tone">{{ ucfirst($tone) }}</x-ui.badge>': [],
        """<x-ui.card :title="$post->title" :href="route('x')" />""": [],
        """<x-layouts.public :title="$a ? $a->label() : __('k')">\n<p>Words here</p>""": ["Words here"],
    }
    failed = 0
    for source, expected in cases.items():
        got = [snippet for _, snippet in findings(source)]
        if got != expected:
            failed += 1
            print(f"FAIL: {source!r}\n  expected {expected}\n  got      {got}")
    print("self-test " + ("failed" if failed else "passed") + f" ({len(cases)} cases)")
    return 1 if failed else 0


if __name__ == "__main__":
    if "--self-test" in sys.argv:
        sys.exit(self_test())
    sys.exit(check(sorted(VIEWS.rglob("*.blade.php"))))
