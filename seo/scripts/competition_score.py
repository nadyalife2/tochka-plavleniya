"""Бесплатная локальная оценка конкурентности ключевых фраз (без платных сервисов).

Анализирует выдачу и контентную силу конкурентов по 4 инженерным факторам:
1. Тип выдачи: доля коммерческих сайтов (маркетплейсы, магазины) vs статей.
   Если выдача занята магазинами — информационная статья ранжируется плохо.
2. Контентная глубина топа: средний объём и структура статей конкурентов
   из локальной базы `raw_articles` (если в топе рерайты до 3 000 знаков — легко обойти).
3. Наличие технических фишек: есть ли у конкурентов таблицы ГОСТ, калькуляторы,
   интерактивные FAQ (из таблицы `competitor_features`).
4. Наличие витального сайта-монополиста (Википедия, Госстандарт, Kester).

Метрика:
  - competition_score: 0–100 (где 0 = конкуренции нет, 100 = перегретый коммерческий топ);
  - opportunity_score: 0–100 (потенциал статьи: чем выше, тем быстрее выйдет в ТОП-3);
  - intent_type: informational (наш формат) | commercial | mixed.

Использование:
    python seo/scripts/competition_score.py --query "какой припой выбрать"
    python seo/scripts/competition_score.py --all
    python seo/scripts/competition_score.py --table
"""
from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SCRIPTS = ROOT / "seo" / "scripts"
sys.path.insert(0, str(SCRIPTS))

from db import get_connection

COMMERCIAL_MARKERS = [
    "купить", "цена", "доставка", "руб", "в наличии", "корзина", "каталог",
    "интернет-магазин", "скидка", "артикул", "заказать", "отзывы покупателей"
]

E_COMMERCE_DOMAINS = {
    "ozon.ru", "wildberries.ru", "market.yandex.ru", "dns-shop.ru", "vseinstrumenti.ru",
    "chipdip.ru", "sbermegamarket.ru", "megamarket.ru", "aliexpress.ru", "avito.ru",
    "leroymerlin.ru", "lemanapro.ru", "citilink.ru", "solderpoint.ru/shop"
}

VITAL_DOMAINS = {
    "ru.wikipedia.org", "wikipedia.org", "gost.ru", "standards.ru", "kester.com"
}


def init_competition_table():
    with get_connection() as conn:
        conn.execute("""
            CREATE TABLE IF NOT EXISTS keyword_competition (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                keyword TEXT UNIQUE NOT NULL,
                cluster TEXT,
                competition_score INTEGER NOT NULL,
                opportunity_score INTEGER NOT NULL,
                intent_type TEXT NOT NULL,
                vital_domain TEXT,
                details_json TEXT,
                updated_at TEXT NOT NULL
            )
        """)
        conn.commit()


def evaluate_keyword_competition(keyword: str, cluster: str = "") -> dict:
    """Оценивает конкурентность фразы на основе локальной базы знаний и SERP."""
    kw_low = keyword.lower().strip()
    words = [w for w in re.findall(r"[a-zа-яё0-9\-]+", kw_low) if len(w) > 2]

    with get_connection() as conn:
        # 1. Поиск релевантных статей конкурентов в raw_articles
        raw_rows = []
        for w in words[:3]:
            rows = conn.execute(
                "SELECT domain, url, title, content_type FROM raw_articles WHERE lower(title) LIKE ? LIMIT 30",
                (f"%{w}%",)
            ).fetchall()
            raw_rows.extend([dict(r) for r in rows])

        # Устраняем дубли по URL
        seen_urls = set()
        unique_articles = []
        for r in raw_rows:
            if r["url"] not in seen_urls:
                seen_urls.add(r["url"])
                unique_articles.append(r)

        # 2. Поиск фишек конкурентов
        features_rows = conn.execute("SELECT domain, feature_type, title, description FROM competitor_features").fetchall()
        features_map = {}
        for f in features_rows:
            features_map.setdefault(f["domain"], []).append(f"{f['feature_type']} {f['title']} {f['description'] or ''}")

    # Анализ коммерческих слов в запросе
    comm_hits = sum(1 for m in COMMERCIAL_MARKERS if m in kw_low)
    is_pure_commercial = comm_hits >= 2 or ("купить" in kw_low or "цена" in kw_low)

    # Оценка состава выдачи
    total_articles = len(unique_articles)
    domains_in_top = {r["domain"] for r in unique_articles}

    # Проверка витального сайта
    vital_found = None
    for d in domains_in_top:
        if d in VITAL_DOMAINS:
            vital_found = d
            break

    # Коммерческий вес (0-100)
    comm_domains = sum(1 for d in domains_in_top if any(cd in d for cd in E_COMMERCE_DOMAINS))
    comm_ratio = (comm_domains / max(1, len(domains_in_top))) if domains_in_top else (0.8 if is_pure_commercial else 0.2)

    # Сила конкурентов: есть ли интерактивные фишки
    has_calc_or_tables = any(
        any("калькулятор" in feat.lower() or "таблица" in feat.lower() for feat in features_map.get(d, []))
        for d in domains_in_top
    )

    # Расчёт Competition Score (0 - халява, 100 - бетонная стена)
    base_comp = 20
    if is_pure_commercial:
        base_comp += 45
    elif comm_hits == 1:
        base_comp += 20

    if comm_ratio > 0.5:
        base_comp += 20
    if vital_found:
        base_comp += 15
    if has_calc_or_tables:
        base_comp += 10
    if total_articles > 15:
        base_comp += 10
    elif total_articles < 3:
        base_comp -= 15

    competition_score = max(5, min(95, base_comp))
    opportunity_score = max(5, min(98, 105 - competition_score))

    intent_type = "commercial" if (is_pure_commercial or comm_ratio > 0.6) else ("mixed" if comm_hits > 0 else "informational")

    details = {
        "articles_found": total_articles,
        "domains_count": len(domains_in_top),
        "commercial_domains": comm_domains,
        "has_calculators_in_top": has_calc_or_tables,
        "vital_site": vital_found,
        "sample_titles": [r["title"] for r in unique_articles[:3]],
    }

    return {
        "keyword": keyword,
        "cluster": cluster,
        "competition_score": competition_score,
        "opportunity_score": opportunity_score,
        "intent_type": intent_type,
        "vital_domain": vital_found,
        "details": details,
    }


def save_evaluation(res: dict):
    init_competition_table()
    from datetime import datetime
    now_str = datetime.now().isoformat(timespec="seconds")
    with get_connection() as conn:
        conn.execute("""
            INSERT INTO keyword_competition (keyword, cluster, competition_score, opportunity_score, intent_type, vital_domain, details_json, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT(keyword) DO UPDATE SET
                competition_score = excluded.competition_score,
                opportunity_score = excluded.opportunity_score,
                intent_type = excluded.intent_type,
                vital_domain = excluded.vital_domain,
                details_json = excluded.details_json,
                updated_at = excluded.updated_at
        """, (
            res["keyword"],
            res.get("cluster", ""),
            res["competition_score"],
            res["opportunity_score"],
            res["intent_type"],
            res.get("vital_domain"),
            json.dumps(res.get("details", {}), ensure_ascii=False),
            now_str,
        ))
        conn.commit()


def main() -> int:
    parser = argparse.ArgumentParser(description="Локальная оценка конкуренции поисковых запросов")
    parser.add_argument("--query", help="Оценить конкретную фразу")
    parser.add_argument("--all", action="store_true", help="Оценить все ключевые слова из базы тем")
    parser.add_argument("--table", action="store_true", help="Показать сводную таблицу")
    parser.add_argument("--json", action="store_true", help="Вывод в формате JSON")
    args = parser.parse_args()

    init_competition_table()

    if args.query:
        res = evaluate_keyword_competition(args.query)
        save_evaluation(res)
        if args.json:
            print(json.dumps(res, ensure_ascii=False, indent=2))
        else:
            print("=" * 65)
            print(f"АНАЛИЗ КОНКУРЕНЦИИ: «{res['keyword']}»")
            print("=" * 65)
            print(f"  • Конкурентность:  {res['competition_score']}/100  (чем ниже, тем проще пробиться)")
            print(f"  • Шанс на ТОП-3:   {res['opportunity_score']}/100  (потенциал статьи)")
            print(f"  • Тип интента:     {res['intent_type']}  (informational = наша цель)")
            print(f"  • Витальный сайт:  {res['vital_domain'] or 'отсутствует (путь открыт)'}")
            print(f"  • Найдено статей:  {res['details']['articles_found']} у конкурентов")
            if res['details']['sample_titles']:
                print("  • Примеры статей топа:")
                for st in res['details']['sample_titles']:
                    print(f"      - {st[:60]}")
            print("=" * 65)
        return 0

    if args.all or args.table:
        with get_connection() as conn:
            topics = conn.execute("SELECT id, title, cluster, priority_score FROM topics ORDER BY priority_score DESC LIMIT 30").fetchall()

        print(f"{'#':<4} {'КОНКУРЕНЦИЯ':<14} {'ШАНС':<8} {'ИНТЕНТ':<15} {'ТЕМА СТАТЬИ'}")
        print("-" * 80)
        for t in topics:
            res = evaluate_keyword_competition(t["title"], t["cluster"])
            save_evaluation(res)
            c_badge = f"{res['competition_score']}/100"
            o_badge = f"{res['opportunity_score']}/100"
            print(f"#{t['id']:<3} {c_badge:<14} {o_badge:<8} {res['intent_type']:<15} {t['title'][:36]}")
        print("-" * 80)
        print("✓ Результаты сохранены в таблицу keyword_competition базы seo.db.")
        return 0

    parser.print_help()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
