"""Банк фраз из SEO-данных конкурентов для подсказок в дашборде.

Собирает кандидатов из четырёх источников и отдаёт 10 лучших:
    1. keyword_metrics            — фразы, у которых уже измерена конкуренция;
    2. wordstat_metrics           — фразы с измеренным спросом;
    3. словоформы из топ-запросов Вордстата (results/associations);
    4. seo_keywords конкурентов   — то, по чему реально ранжируются конкуренты (competitors.seo_keywords);
    5. заголовки статей конкурентов (raw_articles.title) — то, что они пишут.

Приоритет: сначала фразы с известными метриками (для них не нужно платить),
затем ключи конкурентов, затем заголовки их статей.

Использование:
    python seo/scripts/keyword_suggestions.py
    python seo/scripts/keyword_suggestions.py --limit 15 --contains паяль
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

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

# Служебные обрывки, которые не являются поисковыми фразами
JUNK = re.compile(
    r"(web:\d+|https?://|\bчитать далее\b|\bподробнее\b|^\d+$|^[^а-яёa-z]+$"
    r"|^общая электроника$|^электроника$|^прочее$|^без типа$)",
    re.I,
)


def _norm(phrase: str) -> str:
    return " ".join((phrase or "").lower().replace("ё", "е").split()).strip(" .,-—:;!?")


def _is_usable(phrase: str) -> bool:
    if not phrase:
        return False
    if len(phrase) < 4 or len(phrase) > 90:
        return False
    if JUNK.search(phrase):
        return False
    words = phrase.split()
    if len(words) > 8:
        return False
    return True


def collect(limit: int = 10, contains: str = "") -> list[dict]:
    """Возвращает до `limit` кандидатов, сбалансированно из разных источников.

    Формат: [{phrase, source, demand, competition, group}]
    Группы (группировка нужна для «выпадающего списка из 10»):
        measured   — фразы с уже измеренными метриками (Мутаген/Вордстат);
        tail       — топ-запросы Вордстата (что ищут рядом);
        competitor — SEO-ключи и заголовки статей конкурентов.
    """
    seen: dict[str, dict] = {}

    def add(phrase: str, source: str, group: str, demand=None, competition=None, weight: int = 0):
        key = _norm(phrase)
        if not _is_usable(phrase):
            return
        if contains and contains.lower() not in key:
            return
        existing = seen.get(key)
        if existing:
            existing["weight"] = max(existing["weight"], weight)
            if demand is not None and existing.get("demand") is None:
                existing["demand"] = demand
            if competition is not None and existing.get("competition") is None:
                existing["competition"] = competition
            return
        seen[key] = {
            "phrase": phrase.strip(),
            "source": source,
            "group": group,
            "demand": demand,
            "competition": competition,
            "weight": weight,
        }

    with get_connection() as conn:
        # 1. Фразы с измеренной конкуренцией
        try:
            for r in conn.execute(
                "SELECT keyword, strong, wordstat FROM keyword_metrics ORDER BY wordstat DESC NULLS LAST LIMIT 100"
            ).fetchall():
                add(r["keyword"], "мутаген", "measured", r["wordstat"], r["strong"], weight=100)
        except Exception:
            pass

        # 2. Фразы с измеренным спросом
        try:
            for r in conn.execute(
                "SELECT phrase, total_count FROM wordstat_metrics ORDER BY total_count DESC LIMIT 100"
            ).fetchall():
                add(r["phrase"], "вордстат", "measured", r["total_count"], None, weight=90)
        except Exception:
            pass

        # 3. Топ-запросы Вордстата (хвосты, которые реально ищут)
        try:
            rows = conn.execute("SELECT top_json FROM wordstat_metrics").fetchall()
            for r in rows:
                try:
                    items = json.loads(r["top_json"] or "[]")
                except Exception:
                    continue
                for it in items[:20]:
                    if it.get("phrase"):
                        add(it["phrase"], "хвост Вордстата", "tail", it.get("count"), None, weight=70)
        except Exception:
            pass

        # 4. SEO-ключи конкурентов — то, по чему они реально ранжируются
        try:
            for r in conn.execute(
                "SELECT domain, seo_keywords FROM competitors WHERE seo_keywords IS NOT NULL AND seo_keywords != '' "
                "ORDER BY score DESC LIMIT 30"
            ).fetchall():
                for kw in re.split(r"[,;/]", r["seo_keywords"]):
                    add(kw.strip(), f"SEO конкурента ({r['domain']})", "competitor", None, None, weight=60)
        except Exception:
            pass

        # 5. Заголовки статей конкурентов
        try:
            for r in conn.execute(
                "SELECT domain, title FROM raw_articles WHERE title IS NOT NULL ORDER BY id DESC LIMIT 200"
            ).fetchall():
                add(r["title"], f"статья ({r['domain']})", "competitor", None, None, weight=40)
        except Exception:
            pass

    # Сбалансированная выдача: конкурентные и хвостовые фразы не должны
    # вытесняться уже измеренными — иначе список теряет смысл.
    groups: dict[str, list[dict]] = {"measured": [], "competitor": [], "tail": []}
    for item in seen.values():
        groups.setdefault(item["group"], []).append(item)
    for g in groups.values():
        g.sort(key=lambda x: (-x["weight"], -(x.get("demand") or 0)))

    quota = {"measured": max(2, limit // 3), "competitor": max(3, limit // 3), "tail": max(2, limit // 3)}
    picked: list[dict] = []
    while len(picked) < limit:
        progressed = False
        for name in ("competitor", "measured", "tail"):
            if len(picked) >= limit:
                break
            taken = sum(1 for p in picked if p["group"] == name)
            if taken >= quota[name]:
                continue
            if groups[name]:
                picked.append(groups[name].pop(0))
                progressed = True
        if not progressed:
            # квоты исчерпаны — добираем из любых оставшихся
            rest = [it for g in groups.values() for it in g]
            rest.sort(key=lambda x: (-x["weight"], -(x.get("demand") or 0)))
            picked.extend(rest[: limit - len(picked)])
            break
    return picked


def main() -> int:
    ap = argparse.ArgumentParser(description="Подсказки фраз из SEO конкурентов")
    ap.add_argument("--limit", type=int, default=10)
    ap.add_argument("--contains", default="")
    ap.add_argument("--json", action="store_true")
    args = ap.parse_args()

    items = collect(limit=args.limit, contains=args.contains)
    if args.json:
        print(json.dumps(items, ensure_ascii=False, indent=2))
        return 0

    print(f"Подсказки фраз (всего {len(items)}):")
    for i, it in enumerate(items, 1):
        demand = f"спрос {it['demand']}" if it.get("demand") is not None else "спрос —"
        comp = f"конкур {it['competition']}/25" if it.get("competition") is not None else ""
        print(f"  {i:>2}. {it['phrase'][:60]:<62} [{it['source']}] {demand} {comp}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
