#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""seo/scripts/google_semantics.py
Автономный сборщик семантики Google Suggest и вопросительных AEO-интентов (PAA-профиль)
для проекта «Точка Плавления». Не требует внешних pip-зависимостей и платных API.
"""
from __future__ import annotations

import argparse
import json
import random
import sqlite3
import sys
import time
import urllib.parse
import urllib.request
from pathlib import Path
from typing import Dict, List, Set

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

ROOT = Path(__file__).resolve().parents[2]
DEFAULT_DB = ROOT / "seo" / "data" / "seo.db"

# Вопросительные модификаторы специально под интенты Google AI Overviews и People Also Ask
AEO_INTENT_MODIFIERS = [
    "",
    "как ",
    "чем ",
    "почему ",
    "какая температура ",
    "можно ли ",
    "в чем разница ",
    "гост ",
]

ENGINEERING_SUFFIXES = [
    "",
    " температура плавления",
    " температура пайки",
    " чем смыть",
    " состав",
    " для чего",
]

GOOGLE_SUGGEST_URL = (
    "https://suggestqueries.google.com/complete/search"
    "?client=firefox&hl=ru&gl=ru&ds=&q={query}"
)

USER_AGENTS = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36",
    "Mozilla/5.0 (X11; Linux x86_64; rv:125.0) Gecko/20100101 Firefox/125.0",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15",
]


def fetch_google_suggest(query: str, timeout: float = 6.0) -> List[str]:
    """Запрашивает публичный эндпоинт Google Autocomplete (JSON) без сторонних библиотек."""
    encoded_q = urllib.parse.quote(query.strip())
    url = GOOGLE_SUGGEST_URL.format(query=encoded_q)
    headers = {
        "User-Agent": random.choice(USER_AGENTS),
        "Accept-Language": "ru-RU,ru;q=0.9",
        "Accept": "application/json, text/javascript, */*; q=0.01",
    }
    req = urllib.request.Request(url, headers=headers)
    try:
        with urllib.request.urlopen(req, timeout=timeout) as response:
            raw_data = response.read().decode("utf-8", errors="replace")
            parsed = json.loads(raw_data)
            # Формат ответа client=firefox: ["запрос", ["подсказка 1", "подсказка 2", ...]]
            if isinstance(parsed, list) and len(parsed) > 1 and isinstance(parsed[1], list):
                res: list[str] = []
                for item in parsed[1]:
                    if isinstance(item, str) and item.strip():
                        res.append(item.strip())
                    elif isinstance(item, list) and item and isinstance(item[0], str) and item[0].strip():
                        res.append(item[0].strip())
                return res
    except Exception as exc:
        sys.stderr.write(f"[WARN] Ошибка запроса для '{query}': {exc}\n")
    return []


def collect_aeo_cluster(seed_query: str, delay: float = 0.4) -> Dict[str, object]:
    """
    Формирует двухконтурное ядро по базовому маркеру (например, 'припой ПОС-61' или 'флюс ЛТИ-120'):
    1. base_suggests — прямые и суффиксные подсказки Google.
    2. aeo_questions — вопросительные формулировки (кандидаты в FAQPage и Answer First).
    """
    base_set: Set[str] = set()
    questions_set: Set[str] = set()

    # 1. Базовые и технические суффиксы
    for suffix in ENGINEERING_SUFFIXES:
        q = f"{seed_query}{suffix}".strip()
        for suggestion in fetch_google_suggest(q):
            base_set.add(suggestion)
        time.sleep(delay + random.uniform(0.05, 0.2))

    # 2. Вопросительные AEO/PAA-префиксы для захвата колдунщиков и AI Overviews
    for prefix in AEO_INTENT_MODIFIERS:
        if not prefix:
            continue
        q = f"{prefix}{seed_query}".strip()
        for suggestion in fetch_google_suggest(q):
            questions_set.add(suggestion)
        time.sleep(delay + random.uniform(0.05, 0.2))

    return {
        "seed": seed_query,
        "google_suggests": sorted(base_set),
        "aeo_questions_paa": sorted(questions_set),
        "total_collected": len(base_set) + len(questions_set),
    }


def save_to_sqlite(db_path: Path, cluster: Dict[str, object]) -> int:
    """Сохраняет собранные подсказки Google в локальную БД seo/data/seo.db."""
    db_path.parent.mkdir(parents=True, exist_ok=True)
    conn = sqlite3.connect(str(db_path))
    inserted = 0
    try:
        cur = conn.cursor()
        cur.execute(
            """
            CREATE TABLE IF NOT EXISTS google_semantics (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                seed_query TEXT NOT NULL,
                suggestion TEXT NOT NULL,
                intent_type TEXT NOT NULL, -- 'suggest' | 'aeo_question'
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(seed_query, suggestion)
            )
            """
        )
        for item in cluster.get("google_suggests", []):
            cur.execute(
                "INSERT OR IGNORE INTO google_semantics (seed_query, suggestion, intent_type) VALUES (?, ?, 'suggest')",
                (cluster["seed"], item),
            )
            if cur.rowcount > 0:
                inserted += 1
        for item in cluster.get("aeo_questions_paa", []):
            cur.execute(
                "INSERT OR IGNORE INTO google_semantics (seed_query, suggestion, intent_type) VALUES (?, ?, 'aeo_question')",
                (cluster["seed"], item),
            )
            if cur.rowcount > 0:
                inserted += 1
        conn.commit()
    finally:
        conn.close()
    return inserted


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Сборщик подсказок Google Suggest и вопросов AEO для «Точки Плавления»"
    )
    parser.add_argument("seed", help="Базовый поисковый маркер (например: 'припой ПОС-61')")
    parser.add_argument("--json", action="store_true", help="Вывести результат в чистом JSON")
    parser.add_argument(
        "--save-db",
        nargs="?",
        const=str(DEFAULT_DB),
        default=None,
        help="Путь к SQLite БД для сохранения кластера (по умолчанию seo/data/seo.db)",
    )
    args = parser.parse_args()

    result = collect_aeo_cluster(args.seed)

    if args.save_db:
        db_file = Path(args.save_db)
        saved_cnt = save_to_sqlite(db_file, result)
        if not args.json:
            print(f"✓ Сохранено в БД {db_file.name}: {saved_cnt} новых записей")

    if args.json:
        print(json.dumps(result, ensure_ascii=False, indent=2))
    else:
        print(f"=== Ядро Google для: «{result['seed']}» (Всего: {result['total_collected']}) ===")
        print("\n[Прямые подсказки Google Suggest]:")
        for s in result["google_suggests"]:
            print(f"  • {s}")
        print("\n[Вопросительные интенты для Answer First и FAQPage (AEO / PAA)]:")
        for q in result["aeo_questions_paa"]:
            print(f"  ? {q}")


if __name__ == "__main__":
    main()
