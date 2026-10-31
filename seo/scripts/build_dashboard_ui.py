"""Синхронизатор данных дашборда «Точка Плавления».

Архитектура:
Дашборд переведён на модульную структуру без сборщиков (zero-dependency):
- seo/dashboard/index.html — компактный HTML-каркас и разметка верстака;
- seo/dashboard/dashboard.css — дизайн-токены и стили сплит-сетки;
- seo/dashboard/dashboard.js — клиентская логика, автосохранение и REST-клиент.

При работе с сервером данные подгружаются на лету через /api/data.
Этот скрипт сохраняет резервный оффлайн-снапшот в seo/dashboard/offline_data.json.
"""
from __future__ import annotations

import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SCRIPTS = ROOT / "seo" / "scripts"
sys.path.insert(0, str(SCRIPTS))

from server import fetch_all_data

DASH_DIR = ROOT / "seo" / "dashboard"
INDEX_FILE = DASH_DIR / "index.html"
CSS_FILE = DASH_DIR / "dashboard.css"
JS_FILE = DASH_DIR / "dashboard.js"
OFFLINE_DATA = DASH_DIR / "offline_data.json"


def main() -> None:
    try:
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    except Exception:
        pass

    if not INDEX_FILE.exists() or not CSS_FILE.exists() or not JS_FILE.exists():
        print("[ERROR] Модульные файлы дашборда не найдены!")
        sys.exit(1)

    data = fetch_all_data()
    OFFLINE_DATA.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")

    stat_topics = len(data.get("topics", []))
    stat_written = sum(1 for t in data.get("topics", []) if (t.get("chars") or 0) > 200)

    print(f"[OK] Оффлайн-снапшот сохранён в {OFFLINE_DATA.name} ({OFFLINE_DATA.stat().st_size} байт)")
    print(f"[OK] Модульный дашборд готов: {stat_topics} тем в SQLite, {stat_written} с текстом.")
    print("[OK] Защита активна: index.html, dashboard.css и dashboard.js защищены от затирания.")


if __name__ == "__main__":
    main()
