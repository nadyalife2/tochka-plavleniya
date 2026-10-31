#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Автономный сборщик поисковых подсказок Google Suggest для «Точки Плавления».

Работает без платных API и без сторонних зависимостей (на стандартной библиотеке urllib).
Использует публичный endpoint suggestqueries.google.com с безопасным троттлингом (jitter),
ротацией заголовков и защитой от блокировок.

Использование:
    python seo/scripts/google_suggest_collect.py --seed "пайка припоем"
    python seo/scripts/google_suggest_collect.py --seed "ПОС-61" --mode full --out seo/data/pos61_suggest.json
    python seo/scripts/google_suggest_collect.py --seed "флюс для меди" --delay 1.2
"""
from __future__ import annotations

import argparse
import json
import random
import sys
import time
import urllib.parse
import urllib.request
from datetime import datetime
from pathlib import Path

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

ROOT = Path(__file__).resolve().parents[2]

USER_AGENTS = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36",
    "Mozilla/5.0 (X11; Linux x86_64; rv:125.0) Gecko/20100101 Firefox/125.0",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15",
]


def fetch_suggestions(query: str, lang: str = "ru", geo: str = "RU", client: str = "firefox") -> list[str]:
    """Получает подсказки от Google Suggest для заданной строки."""
    base_url = "https://suggestqueries.google.com/complete/search"
    params = {
        "client": client,
        "hl": lang,
        "gl": geo,
        "q": query,
    }
    url = f"{base_url}?{urllib.parse.urlencode(params)}"
    req = urllib.request.Request(
        url,
        headers={
            "User-Agent": random.choice(USER_AGENTS),
            "Accept-Language": f"{lang}-{geo},{lang};q=0.9",
            "Accept": "application/json, text/javascript, */*; q=0.01",
        },
    )

    try:
        with urllib.request.urlopen(req, timeout=12) as response:
            raw = response.read().decode("utf-8", errors="replace")
            data = json.loads(raw)
            if isinstance(data, list) and len(data) >= 2 and isinstance(data[1], list):
                # data[1] содержит список подсказок (строки или списки)
                suggestions = []
                for item in data[1]:
                    if isinstance(item, str):
                        suggestions.append(item.strip())
                    elif isinstance(item, list) and item and isinstance(item[0], str):
                        suggestions.append(item[0].strip())
                return [s for s in suggestions if s]
    except Exception as ex:
        print(f"  ⚠️ Ошибка при запросе '{query}': {ex}", file=sys.stderr)

    return []


def expand_seed(seed: str, mode: str = "basic", lang: str = "ru", geo: str = "RU", base_delay: float = 1.0) -> list[str]:
    """Расширяет семя через перебор суффиксов."""
    results = set()
    cleaned_seed = seed.strip()
    if not cleaned_seed:
        return []

    print(f"🔍 [1/2] Запрос базового семени: '{cleaned_seed}'")
    for s in fetch_suggestions(cleaned_seed, lang=lang, geo=geo):
        results.add(s)

    # Пробельный разворот
    for s in fetch_suggestions(f"{cleaned_seed} ", lang=lang, geo=geo):
        results.add(s)

    if mode == "shallow":
        return sorted(results)

    # Выборка суффиксов в зависимости от режима
    if mode == "full":
        # Полный русский алфавит, латиница и цифры
        suffixes = (
            list("абвгдежзийклмнопрстуфхцчшщэюя")
            + list("abcdefghijklmnopqrstuvwxyz")
            + list("0123456789")
        )
    else:  # 'basic'
        # Наиболее частотные буквы и цифры
        suffixes = list("абвгдеиклмнопрстф") + list("0123456789")

    total = len(suffixes)
    print(f"🔍 [2/2] Перебор {total} расширений (задержка {base_delay}s + джиттер)...")

    for idx, suffix in enumerate(suffixes, 1):
        combo = f"{cleaned_seed} {suffix}"
        res = fetch_suggestions(combo, lang=lang, geo=geo)
        for s in res:
            results.add(s)

        # Безопасная пауза с джиттером от 0.15 до 0.45 сек
        jitter = random.uniform(0.15, 0.45)
        time.sleep(base_delay + jitter)

        if idx % 10 == 0 or idx == total:
            print(f"  [{idx}/{total}] Собрано уникальных подсказок: {len(results)}")

    return sorted(results)


def main():
    parser = argparse.ArgumentParser(description="Сборщик поисковых подсказок Google Suggest для Точки Плавления")
    parser.add_argument("--seed", required=True, help="Базовое слово/фраза (например: 'припой ПОС-61')")
    parser.add_argument("--mode", choices=["shallow", "basic", "full"], default="basic", help="Глубина перебора: shallow, basic (умеренная), full (полная)")
    parser.add_argument("--lang", default="ru", help="Язык поиска (default: ru)")
    parser.add_argument("--geo", default="RU", help="Гео-локация поиска (default: RU)")
    parser.add_argument("--delay", type=float, default=1.0, help="Базовая задержка между запросами в секундах (default: 1.0)")
    parser.add_argument("--out", default="", help="Путь к результирующему JSON (по умолчанию: seo/data/suggestions_<slug>.json)")
    args = parser.parse_args()

    results = expand_seed(args.seed, mode=args.mode, lang=args.lang, geo=args.geo, base_delay=args.delay)

    out_file = args.out
    if not out_file:
        safe_slug = "".join(c if c.isalnum() else "_" for c in args.seed.lower())[:30].strip("_")
        out_file = str(ROOT / "seo" / "data" / f"suggestions_{safe_slug}.json")

    out_path = Path(out_file)
    out_path.parent.mkdir(parents=True, exist_ok=True)

    payload = {
        "seed": args.seed,
        "collected_at": datetime.now().isoformat(),
        "mode": args.mode,
        "count": len(results),
        "suggestions": results,
    }

    out_path.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"✅ Успешно собрано {len(results)} подсказок. Сохранено в: {out_path}")


if __name__ == "__main__":
    main()
