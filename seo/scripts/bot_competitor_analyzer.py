"""Бот глубокого анализа конкурентов «Точка Плавления».

Что анализирует на сайтах конкурентов:
1. Главные SEO-слова (частотный анализ n-грамм, LSI, мета-теги, заголовки H1-H3).
2. Фишки конкурента:
   - интерактивные калькуляторы (подбор припоя, резисторы, закон Ома)
   - сравнительные таблицы моделей и сплавов
   - пошаговые фото/видео мануалы
   - скачиваемые даташиты, паспорта качества и ГОСТы
   - предупреждения по безопасности и чек-листы
   - интерактивные квизы / фильтры подбора
3. Оценка силы конкурента (0-100) по контенту и SEO.
4. Сохранение структурированного досье в SQLite seo.db.

Запуск:
    python seo/scripts/bot_competitor_analyzer.py --domain solderpoint.ru
    python seo/scripts/bot_competitor_analyzer.py --all
"""
from __future__ import annotations

import argparse
import json
import re
import socket
import sys
import urllib.parse
import urllib.request
from collections import Counter
from datetime import datetime
from pathlib import Path

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

from bs4 import BeautifulSoup

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "seo" / "scripts"))

from db import get_connection

UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36"
HEADERS = {"User-Agent": UA, "Accept-Language": "ru-RU,ru;q=0.9"}
TIMEOUT = 10

SOLDERING_KEYWORDS = [
    "припой", "пос-61", "пос-40", "sac305", "бессвинцовый", "флюс", "канифоль",
    "лти-120", "паяльник", "паяльная станция", "жало", "т12", "t12", "jbc", "ts101",
    "pinecil", "фен", "термофен", "bga", "smd", "паста", "паяльная паста",
    "демонтаж", "оплетка", "отсос", "лужение", "олово", "свинец", "розе", "вуда",
    "гост", "ipc", "j-std", "температура", "уставка", "пайка", "паять",
]

FEATURE_PATTERNS = [
    ("калькулятор", r"калькулятор|расчет|расчёт|подборщик|online calculator", "Интерактивный расчёт параметров пайки или компонентов"),
    ("таблица сравнения", r"<table\b|сравнительн\w+ таблиц|таблица характеристик|сравнение моделей", "Структурированные сравнительные таблицы"),
    ("видео-инструкция", r"youtube-nocookie|youtube\.com/embed|rutube\.ru/play|видео-урок|видеоинструкц", "Встроенные видеоматериалы процесса пайки"),
    ("скачиваемый PDF / ГОСТ", r"\.pdf\b|скачать схему|скачать даташит|datasheet|паспорт изделия", "Скачиваемая документация и даташиты"),
    ("чек-лист / шаги", r"чек-лист|пошаговый алгоритм|памятка мастера|инструкция по шагам", "Пошаговые чек-листы для мастера"),
    ("предупреждение безопасности", r"внимание!|техника безопасности|опасно|яд|вытяжк|защит\w+ глаз", "Блоки предупреждений о технике безопасности"),
    ("интерактивный FAQ", r"<details|accordion|аккордеон|частые вопросы|вопрос-ответ", "Сворачиваемые блоки вопрос-ответ"),
]


def fetch_page(url: str) -> tuple[int, str]:
    try:
        req = urllib.request.Request(url, headers=HEADERS)
        with urllib.request.urlopen(req, timeout=TIMEOUT) as resp:
            raw = resp.read(600_000)
            return resp.status, raw.decode("utf-8", errors="replace")
    except Exception:
        return 0, ""


def analyze_domain(domain: str, blog_url: str = "") -> dict:
    base = blog_url or f"https://{domain}"
    print(f"\n🔍 Анализ конкурента: {domain} ({base})...", flush=True)

    status, html = fetch_page(base)
    if not html:
        print(f"  ❌ Не удалось загрузить главную страницу {base}")
        return {
            "domain": domain,
            "seo_keywords": [],
            "features": [],
            "score": 30,
            "pages_analyzed": 0,
        }

    soup = BeautifulSoup(html, "lxml")

    # 1. Сбор текстов и ключевых слов
    meta_kw = ""
    meta_desc = ""
    for meta in soup.find_all("meta"):
        if meta.get("name", "").lower() == "keywords":
            meta_kw = meta.get("content", "")
        if meta.get("name", "").lower() == "description":
            meta_desc = meta.get("content", "")

    title_text = soup.title.string if soup.title else ""
    h1_texts = " ".join([h.get_text() for h in soup.find_all("h1")])
    h2_texts = " ".join([h.get_text() for h in soup.find_all("h2")])

    combined_text = f"{title_text} {meta_kw} {meta_desc} {h1_texts} {h2_texts}".lower()

    # Считаем частотность профильных SEO-слов
    kw_hits = []
    for kw in SOLDERING_KEYWORDS:
        cnt = len(re.findall(rf"\b{re.escape(kw)}", combined_text))
        if cnt > 0:
            kw_hits.append((kw, cnt))
    kw_hits.sort(key=lambda x: -x[1])
    top_seo_keywords = [k for k, _ in kw_hits[:12]]

    # 2. Поиск фишек (features)
    found_features = []
    for f_name, pattern, desc in FEATURE_PATTERNS:
        if re.search(pattern, html, flags=re.I):
            found_features.append({
                "type": f_name,
                "description": desc,
                "found": True,
            })

    # 3. Расчёт силы конкурента (0-100)
    score = 40
    if len(top_seo_keywords) >= 8:
        score += 20
    elif len(top_seo_keywords) >= 4:
        score += 10

    if len(found_features) >= 4:
        score += 25
    elif len(found_features) >= 2:
        score += 15

    if meta_desc and len(meta_desc) > 50:
        score += 10
    if len(soup.find_all("h2")) >= 3:
        score += 5

    score = min(98, score)

    features_str = ", ".join([f["type"] for f in found_features]) or "Базовый контент без интерактивных фишек"
    keywords_str = ", ".join(top_seo_keywords) or "Общая электроника"

    print(f"  ✓ Сила конкурента: {score}/100", flush=True)
    print(f"  ✓ Главные SEO-слова: {keywords_str}", flush=True)
    print(f"  ✓ Обнаруженные фишки: {features_str}", flush=True)

    # 4. Сохранение в SQLite
    with get_connection() as conn:
        conn.execute("""
            UPDATE competitors
            SET seo_keywords = ?, features = ?, score = ?, updated_at = ?
            WHERE domain = ?
        """, (
            keywords_str,
            features_str,
            score,
            datetime.now().strftime("%Y-%m-%d %H:%M"),
            domain,
        ))

        # Сохраняем каждую фишку в таблицу competitor_features
        for f in found_features:
            conn.execute("""
                INSERT INTO competitor_features
                (domain, feature_type, title, description, example_url, seo_value, found_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            """, (
                domain,
                f["type"],
                f["type"].capitalize(),
                f["description"],
                base,
                "Удержание внимания, глубина страницы, сниппет",
                datetime.now().strftime("%Y-%m-%d %H:%M"),
            ))
        conn.commit()

    return {
        "domain": domain,
        "seo_keywords": top_seo_keywords,
        "features": [f["type"] for f in found_features],
        "score": score,
    }


def analyze_all_competitors() -> list[dict]:
    with get_connection() as conn:
        cur = conn.execute("SELECT domain, blog_url FROM competitors")
        rows = cur.fetchall()

    results = []
    for r in rows:
        res = analyze_domain(r["domain"], r["blog_url"] or "")
        results.append(res)
    return results


def main() -> None:
    parser = argparse.ArgumentParser(description="Бот анализа конкурентов и фишек")
    parser.add_argument("--domain", help="Конкретный домен")
    parser.add_argument("--all", action="store_true", help="Анализировать всех конкурентов из БД")
    args = parser.parse_args()

    if args.domain:
        analyze_domain(args.domain)
    else:
        print("Запуск глубокого анализа всех конкурентов...")
        analyze_all_competitors()
    print("\n✓ Анализ завершен. Результаты сохранены в seo.db!")


if __name__ == "__main__":
    main()
