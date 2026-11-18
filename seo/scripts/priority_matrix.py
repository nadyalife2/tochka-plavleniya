"""Матрица приоритетности тем «Точки Плавления» — бесплатно, 0 токенов.

Считает для каждой темы из `content_plan` интегральную оценку 0–100 по пяти
факторам, которые в Excel раньше оценивались интуитивно:

    1. cluster_score   — приоритет кластера (🔴 высокий / 🟡 средний / 🟢 низкий)
    2. gap_score       — контентный пробел: слабо ли тема закрыта конкурентами
    3. coverage_score  — покрытие конкурентами (чем меньше их статей — тем лучше)
    4. links_score     — сколько входящих ссылок собирает тема (хабность)
    5. readiness_score — готовность (есть бриф, есть текст)
    6. keywords_score  — объём ключевых тем кластера (широта запроса)

Итог = взвешенная сумма. Пишется в лист `priority_scores` и в новую колонку
`Оценка_приоритета_0_100` листа `content_plan`.

Запуск:
    python seo/scripts/priority_matrix.py            # посчитать и записать
    python seo/scripts/priority_matrix.py --dry-run  # только показать топ
    python seo/scripts/priority_matrix.py --top 15
"""
from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

from db import build_db_plan as build_plan, get_connection
from excel_io import clean, save_priority_scores

# ---------------------------------------------------------------- веса ------

WEIGHTS = {
    "cluster_score": 0.25,
    "gap_score": 0.20,
    "coverage_score": 0.15,
    "links_score": 0.15,
    "keywords_score": 0.10,
    "readiness_score": 0.15,
}

# Слова, по которым распознаём «пробел» в описании кластера
GAP_STRONG = ("нет ", "почти не", "не закрыт", "слабый контент", "нет сильного",
              "нет актуального", "нет отдельного", "не даёт", "ни один")
GAP_MEDIUM = ("не хватает", "частично", "устарел", "мало")


def score_cluster(topic: dict) -> int:
    """Приоритет кластера: высокий=100, средний=65, низкий=35."""
    rank = topic.get("cluster_priority", 2)
    return {1: 100, 2: 65, 3: 35}.get(rank, 50)


def score_gap(topic: dict, clusters: dict) -> int:
    """Контентный пробел из листа `clusters` (колонка «пробел»)."""
    cluster = clusters.get(topic["cluster"], {})
    text = (clean(cluster.get("gap")) or "").lower()
    if not text:
        return 55
    if any(k in text for k in GAP_STRONG):
        return 95
    if any(k in text for k in GAP_MEDIUM):
        return 70
    return 50


def score_coverage(topic: dict, competitor_hits: dict[str, int], plan: dict) -> int:
    """Чем меньше статей конкурентов в этом кластере — тем выше балл.

    competitor_hits: кластер → сколько статей конкурентов найдено в raw_data.
    """
    hits = competitor_hits.get(topic["cluster"], 0)
    if hits == 0:
        return 100
    if hits <= 2:
        return 85
    if hits <= 5:
        return 70
    if hits <= 10:
        return 50
    if hits <= 20:
        return 30
    return 15


def score_links(topic: dict) -> int:
    """Хабность: сколько входящих плановых ссылок собирает тема."""
    inbound = len(topic.get("inbound_planned", []))
    outbound = len(topic.get("outbound_planned", []))
    total = inbound * 2 + outbound          # входящие ценнее
    if total >= 14:
        return 100
    if total >= 10:
        return 88
    if total >= 7:
        return 75
    if total >= 4:
        return 60
    if total >= 2:
        return 42
    return 25


def score_keywords(topic: dict) -> int:
    """Широта запроса: сколько ключевых сущностей заявлено у кластера."""
    kw = clean(topic.get("keywords"))
    if not kw:
        return 45
    parts = [p for p in re.split(r"[,;]", kw) if p.strip()]
    n = len(parts)
    if n >= 6:
        return 100
    if n >= 5:
        return 88
    if n >= 4:
        return 75
    if n >= 3:
        return 62
    if n >= 2:
        return 50
    return 35


def score_readiness(topic: dict) -> int:
    """Готовность к работе: бриф и текст приближают тему к выдаче результата.

    Логика: тему, у которой уже есть бриф, дешевле начать (быстрый выигрыш),
    но полностью готовая тема уже не требует внимания — её балл средний.
    """
    has_brief = topic.get("has_brief")
    has_text = topic.get("has_text")
    if has_text:
        return 40          # текст есть — тема уже отработана, приоритет ниже
    if has_brief:
        return 100         # бриф готов — можно писать немедленно
    return 70              # с нуля, но кластер высокий


def competitor_hits_from_raw(plan: dict) -> dict[str, int]:
    """Считает, сколько собранных статей конкурентов попало в каждый кластер (из SQLite)."""
    hits: dict[str, int] = {}
    try:
        with get_connection() as conn:
            rows = conn.execute("SELECT cluster, COUNT(*) FROM raw_articles WHERE cluster IS NOT NULL AND length(cluster) > 0 GROUP BY cluster").fetchall()
            for c, cnt in rows:
                hits[c] = cnt
    except Exception:
        pass
    return hits


def compute(plan: dict, competitor_hits: dict[str, int]) -> list[dict]:
    clusters = plan["clusters"]
    results: list[dict] = []
    for topic in plan["topics"].values():
        parts = {
            "cluster_score": score_cluster(topic),
            "gap_score": score_gap(topic, clusters),
            "coverage_score": score_coverage(topic, competitor_hits, plan),
            "links_score": score_links(topic),
            "keywords_score": score_keywords(topic),
            "readiness_score": score_readiness(topic),
        }
        total = sum(parts[k] * w for k, w in WEIGHTS.items())
        results.append({
            "article_id": topic["id"],
            "title": topic["title"],
            "cluster": topic["cluster"],
            "status": topic["status"],
            "score": round(total),
            **{k: v for k, v in parts.items()},
        })
    results.sort(key=lambda r: -r["score"])
    return results


def main() -> int:
    ap = argparse.ArgumentParser(description="Матрица приоритетности тем")
    ap.add_argument("--dry-run", action="store_true", help="не писать в Excel")
    ap.add_argument("--top", type=int, default=20, help="сколько строк показать")
    args = ap.parse_args()

    plan = build_plan()
    hits = competitor_hits_from_raw(plan)
    results = compute(plan, hits)

    print("=" * 78)
    print(" МАТРИЦА ПРИОРИТЕТНОСТИ ТЕМ — «Точка Плавления»")
    print("=" * 78)
    print(f" Тем в плане: {len(results)} | кластеров: {len(plan['clusters'])}")
    print(f" Статей конкурентов в raw_data: {sum(hits.values())}")
    print("-" * 78)
    print(f" {'#':>3}  {'балл':>4}  {'класт':>5} {'пробел':>6} {'покр':>5} "
          f"{'связи':>5} {'ключи':>5} {'гот':>4}  Тема")
    print("-" * 78)
    for r in results[: args.top]:
        print(f" {r['article_id']:>3}  {r['score']:>4}  {r['cluster_score']:>5} "
              f"{r['gap_score']:>6} {r['coverage_score']:>5} {r['links_score']:>5} "
              f"{r['keywords_score']:>5} {r['readiness_score']:>4}  "
              f"{r['title'][:44]}")

    print("-" * 78)
    buckets = {"90-100 (взять сейчас)": 0, "75-89 (следующая волна)": 0,
               "60-74 (позже)": 0, "0-59 (в конец очереди)": 0}
    for r in results:
        s = r["score"]
        if s >= 90:
            buckets["90-100 (взять сейчас)"] += 1
        elif s >= 75:
            buckets["75-89 (следующая волна)"] += 1
        elif s >= 60:
            buckets["60-74 (позже)"] += 1
        else:
            buckets["0-59 (в конец очереди)"] += 1
    for name, n in buckets.items():
        print(f"  {name}: {n}")

    if args.dry_run:
        print("\n--dry-run: в Excel ничего не записано.")
        return 0

    # 1. Сохраняем в SQLite БД (Единый первоисточник)
    from db import get_connection
    with get_connection() as conn:
        for r in results:
            conn.execute("UPDATE topics SET priority_score = ? WHERE id = ?", (r["score"], r["article_id"]))
        conn.commit()
    print(f"✓ Записано в SQLite seo.db: обновлено {len(results)} тем.")

    # 2. Дополнительно обновляем Excel, если файл доступен
    try:
        payload = [
            {
                "article_id": r["article_id"],
                "score": r["score"],
                "cluster_score": r["cluster_score"],
                "gap_score": r["gap_score"],
                "coverage_score": r["coverage_score"],
                "links_score": r["links_score"],
                "readiness_score": r["readiness_score"],
                "keywords_score": r["keywords_score"],
                "notes": f"кластер: {r['cluster']}",
            }
            for r in results
        ]
        res = save_priority_scores(payload, write_plan_column=True)
        print(f"Excel: записано {res['written']} оценок в лист `{res['sheet']}`")
    except Exception as e:
        print(f"(Синхронизация с Excel пропущена: {e})")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())