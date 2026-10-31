"""Единый канонический источник сплавов для всего SEO-контура.

Первоисточник — `theme4/data/materials-registry.php` (реестр материалов сайта).
Модуль парсит реестр и отдаёт канонические ключи, диапазоны плавления и рабочие
температуры. Никаких «+20 °C» и угадывания: если данных нет — их нет.

Используется в:
    - seo/scripts/package_contract.py   (паспорт статьи)
    - seo/scripts/score_article.py      (проверка сплавов)
    - tools/delivery_cli.py             (валидация контракта)
    - tests/test_seo_pipeline.py        (проверка паритета)

CLI:
    python seo/scripts/canonical_alloys.py            # показать таблицу
    python seo/scripts/canonical_alloys.py --json     # JSON для сверки
    python seo/scripts/canonical_alloys.py --check    # проверить паритет с schema/delivery
"""
from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

ROOT = Path(__file__).resolve().parents[2]
REGISTRY = ROOT / "theme4" / "data" / "materials-registry.php"
SCHEMA = ROOT / "schemas" / "article-contract-v1.1.schema.json"

# Псевдо-ключ для материалов, где конкретный сплав не применим
UNIVERSAL = "universal"

# Явные текстовые маркеры сплавов (регистр не важен).
# Ключи должны совпадать с id в реестре.
ALLOY_MARKERS: dict[str, list[str]] = {
    "pos61": ["пос-61", "пос61", "пос 61", "pos61", "sn60pb40", "60/40"],
    "sn63pb37": ["sn63pb37", "sn63/pb37", "63/37"],
    "pos40": ["пос-40", "пос40", "пос 40", "pos40", "sn40pb60", "40/60"],
    "sac305": ["sac305", "sac 305", "sn96.5ag3cu0.5"],
    "sac405": ["sac405", "sac 405", "sn95.5ag4cu0.5"],
    "sn42bi58": ["sn42bi58", "sn42/bi58", "58bi", "висмутовый припой"],
    "snbiago": ["snbiago", "sn42bi57", "bi57"],
    "roze": ["сплав розе", "розе", "roze"],
    "vuda": ["сплав вуда", "вуда", "vuda", "wood"],
}


def _parse_registry(path: Path = REGISTRY) -> dict[str, dict]:
    """Парсит блоки сплавов из PHP-реестра. Только реальные данные файла."""
    if not path.exists():
        return {}
    php = path.read_text(encoding="utf-8", errors="replace")

    # Блок начинается с "'<key>' => [" и первым ребёнком имеет "'id' => '<key>'"
    starts = []
    for m in re.finditer(r"'([a-z0-9_]+)'\s*=>\s*\[\s*\n\s*'id'\s*=>\s*'([a-z0-9_]+)'", php):
        starts.append((m.start(), m.group(2)))

    solders: dict[str, dict] = {}
    for idx, (pos, key) in enumerate(starts):
        end = starts[idx + 1][0] if idx + 1 < len(starts) else len(php)
        block = php[pos:end]

        def num(field: str) -> int | None:
            mm = re.search(rf"'{field}'\s*=>\s*(-?\d+)", block)
            return int(mm.group(1)) if mm else None

        def text(field: str) -> str:
            mm = re.search(rf"'{field}'\s*=>\s*'([^']*)'", block)
            return mm.group(1) if mm else ""

        melt_min, melt_max = num("t_melt_min"), num("t_melt_max")
        work_min, work_max = num("t_work_min"), num("t_work_max")

        solders[key] = {
            "id": key,
            "name": text("name") or key.upper(),
            "type": text("type"),
            "standard": text("std"),
            "t_melt_min": melt_min,
            "t_melt_max": melt_max,
            "t_work_min": work_min,
            "t_work_max": work_max,
        }
    return solders


REGISTRY_SOLDERS: dict[str, dict] = _parse_registry()

# Канонические ключи, разрешённые в контракте: реестр + universal
CANONICAL_KEYS: list[str] = list(REGISTRY_SOLDERS.keys()) + [UNIVERSAL]


def canonical_keys() -> list[str]:
    return list(CANONICAL_KEYS)


def normalize_key(value: str) -> str:
    """Нормализует устаревшие алиасы к каноническим ключам."""
    raw = (value or "").strip().lower()
    aliases = {"wood": "vuda", "rose": "roze", "pos-61": "pos61", "пос-61": "pos61"}
    raw = aliases.get(raw, raw)
    return raw if raw in CANONICAL_KEYS else ""


def detect_alloy(text: str, title: str = "") -> tuple[str, list[str]]:
    """Определяет канонический сплав по тексту статьи (и заголовку как усилителю).

    Возвращает (key, найденные_упоминания).
    Логика честная:
      - явный сплав в заголовке (вес ×5) задаёт тему статьи;
      - 0 упоминаний        -> universal (сплав не конкретизирован)
      - 1 доминирующий сплав -> его ключ
      - 2+ разных сплава без явного лидера -> universal (статья обзорная)
    Слово «бессвинцовый» само по себе НЕ означает SAC305: оно лишь разрешает
    взять конкретный бессвинцовый сплав, если он явно назван в тексте.
    """
    low = (text or "").lower()
    low_title = (title or "").lower()

    hits: dict[str, int] = {}
    for key, markers in ALLOY_MARKERS.items():
        if key not in CANONICAL_KEYS:
            continue
        count = 0
        for marker in markers:
            count += low.count(marker) + 5 * low_title.count(marker)
        if count:
            hits[key] = count

    if "бессвинцов" in low_title or "без свинца" in low_title:
        # Тема статьи — бессвинцовые сплавы: берём конкретный сплав, названный в тексте
        for key in ("sac305", "sac405", "sn42bi58", "snbiago"):
            if key not in CANONICAL_KEYS:
                continue
            if any(m in low for m in ALLOY_MARKERS.get(key, [])):
                return key, [key]
        return UNIVERSAL, []

    if not hits:
        return UNIVERSAL, []

    if len(hits) == 1:
        only = next(iter(hits))
        return only, [only]

    ordered = sorted(hits.items(), key=lambda kv: -kv[1])
    top_key, top_n = ordered[0]
    second_key, second_n = ordered[1]
    # Сильный лидер (в 2+ раза больше второго) задаёт тему статьи
    if top_n >= 2 * second_n and top_n >= 2:
        return top_key, [k for k, _ in ordered]
    return UNIVERSAL, [k for k, _ in ordered]


def melt_range(key: str) -> tuple[int, int] | None:
    data = REGISTRY_SOLDERS.get(key)
    if not data:
        return None
    lo, hi = data.get("t_melt_min"), data.get("t_melt_max")
    if lo is None or hi is None:
        return None
    return lo, hi


def work_range(key: str) -> tuple[int, int] | None:
    data = REGISTRY_SOLDERS.get(key)
    if not data:
        return None
    lo, hi = data.get("t_work_min"), data.get("t_work_max")
    if lo is None or hi is None:
        return None
    return lo, hi


def alloy_label(key: str) -> str:
    if key == UNIVERSAL:
        return "универсальный (сплав не конкретизирован)"
    return REGISTRY_SOLDERS.get(key, {}).get("name", key)


def main() -> int:
    parser = argparse.ArgumentParser(description="Канонический реестр сплавов для SEO-контура")
    parser.add_argument("--json", action="store_true", help="вывести JSON")
    parser.add_argument("--check", action="store_true", help="сверить паритет со схемой и delivery_cli")
    args = parser.parse_args()

    if args.json:
        print(json.dumps({"keys": CANONICAL_KEYS, "solders": REGISTRY_SOLDERS}, ensure_ascii=False, indent=2))
        return 0

    if args.check:
        errors = []
        if SCHEMA.exists():
            schema = json.loads(SCHEMA.read_text(encoding="utf-8"))
            enum = schema["properties"]["solder_alloy"]["enum"]
            if set(enum) != set(CANONICAL_KEYS):
                errors.append(f"schema enum != canonical keys:\n  schema={sorted(enum)}\n  canon={sorted(CANONICAL_KEYS)}")

        delivery = ROOT / "tools" / "delivery_cli.py"
        if delivery.exists():
            src = delivery.read_text(encoding="utf-8", errors="replace")
            m = re.search(r"CANONICAL_ALLOYS\s*=\s*\{(.+?)\}", src, re.S)
            if m:
                dk = set(re.findall(r'"([a-z0-9_]+)"', m.group(1)))
                if dk != set(CANONICAL_KEYS):
                    errors.append(f"delivery_cli.CANONICAL_ALLOYS != canonical keys: {sorted(dk)}")

        for key in CANONICAL_KEYS:
            if key == UNIVERSAL:
                continue
            if melt_range(key) is None:
                errors.append(f"{key}: нет t_melt_min/t_melt_max в реестре")

        if errors:
            print("❌ ПАРИТЕТ НАРУШЕН:")
            for e in errors:
                print("  •", e)
            return 1
        print(f"✓ Паритет в порядке: {len(CANONICAL_KEYS)} ключей совпадают в реестре, схеме и delivery_cli.")
        return 0

    print(f"{'КЛЮЧ':<12}{'НАЗВАНИЕ':<26}{'ПЛАВЛЕНИЕ':<14}{'РАБОЧАЯ':<14}")
    print("-" * 68)
    for key in CANONICAL_KEYS:
        melt, work = melt_range(key), work_range(key)
        melt_s = f"{melt[0]}–{melt[1]} °C" if melt else "—"
        work_s = f"{work[0]}–{work[1]} °C" if work else "—"
        print(f"{key:<12}{alloy_label(key)[:24]:<26}{melt_s:<14}{work_s:<14}")
    print(f"\nИсточник: {REGISTRY.relative_to(ROOT)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
