#!/usr/bin/env python3
"""Проверка относительных Markdown-ссылок в документации проекта.

Сканирует README.md, PROJECT_OVERVIEW.md, docs/**/*.md и SKILL.md,
игнорирует внешние URL, якоря и mailto. Возвращает exit code 1 при битых ссылках.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent

FILES = [ROOT / "README.md", ROOT / "PROJECT_OVERVIEW.md"]
FILES += sorted((ROOT / "docs").rglob("*.md"))
FILES += sorted((ROOT / ".agents" / "skills").rglob("SKILL.md"))

LINK_RE = re.compile(r"\[[^\]]*\]\(([^)]+)\)")
FENCE_RE = re.compile(r"```.*?```", re.S)
SKIP_PREFIXES = ("http://", "https://", "mailto:", "#", "file:", "data:")


def strip_code(text: str) -> str:
    return FENCE_RE.sub("", text)


def check_file(path: Path) -> list[str]:
    broken: list[str] = []
    text = strip_code(path.read_text(encoding="utf-8"))
    for raw in LINK_RE.findall(text):
        target = raw.strip().strip("<>").split("#", 1)[0].strip()
        if not target or target.startswith(SKIP_PREFIXES):
            continue
        if target.startswith("/") or re.match(r"^[a-z]+:", target):
            continue
        resolved = (path.parent / target).resolve()
        if not resolved.exists():
            rel = path.relative_to(ROOT).as_posix()
            broken.append(f"{rel} -> {target}")
    return broken


def main() -> int:
    all_broken: list[str] = []
    checked = 0
    for path in FILES:
        if not path.exists():
            continue
        checked += 1
        all_broken.extend(check_file(path))

    print(f"[DOCS] checked {checked} markdown files")
    if all_broken:
        print(f"[FAIL] broken relative links: {len(all_broken)}")
        for item in sorted(set(all_broken)):
            print("  -", item)
        return 1
    print("[PASS] all relative markdown links resolve")
    return 0


if __name__ == "__main__":
    sys.exit(main())
