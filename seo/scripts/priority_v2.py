"""Единая бальная система приоритета темы (0–100) — понятная и проверяемая.

Зачем нужна отдельная шкала:
    `score_article.py` отвечает на вопрос «насколько хорошо написана статья» (качество текста).
    `priority_v2.py` отвечает на другой вопрос: «стоит ли вообще писать эту статью
    и в каком порядке». Это два разных решения, и смешивать их в одну цифру нельзя.

ФОРМУЛА (итог 0–100):

    1. СПРОС ................................................ до 40 баллов
       Берётся широкая частотность Вордстата (сколько людей ищут тему).
       Шкала логарифмическая, иначе «паяльник» (7 400) полностью перебивает всё:
           10 000+ → 40
            3 000+ → 34
            1 000+ → 28
              300+ → 20
              100+ → 12
               30+ → 6
                <30 → 2
          нет замера → 10 (нейтрально, пока не измерено)

    2. КОНКУРЕНЦИЯ ........................................... до 30 баллов
       Измеренная в Мутагене (strong 0–25). Чем ниже — тем больше баллов:
               ≤ 5 → 30
               ≤ 10 → 26
               ≤ 15 → 18
               ≤ 20 → 10
               > 20 → 4
          нет замера → 15 (нейтрально)

    3. ИНТЕНТ запроса ....................................... до 15 баллов
          informational (статья ищет ответ) → 15
          mixed (смешанный)                  → 8
          commercial (только магазины)        → 2
          не определён                        → 10

    4. ГОТОВНОСТЬ материала ................................. до 15 баллов
          текст есть и качество ≥ 80 → 15
          текст есть, качество < 80  → 9
          текст есть, не оценён      → 6
          текста нет                 → 4

Итог округляется до целого. Формула линейная и объяснимая: любую цифру можно
пересчитать на бумаге, а в базе сохраняется расшифровка по блокам.

Классы приоритета:
    A (80–100) — писать первым: спрос есть, конкуренция низкая
    B (60–79)  — хорошая тема, делать во вторую волну
    C (40–59)  — средняя: только если есть повод
    D (0–39)   — не тратить силы: либо нет спроса, либо стена конкуренции

Использование:
    python seo/scripts/priority_v2.py              # пересчитать и показать таблицу
    python seo/scripts/priority_v2.py --top 15     # только топ-15
    python seo/scripts/priority_v2.py --json       # машинный вывод
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


def ensure_columns():
    """Добавляет колонки, если их ещё нет (безопасно для существующей базы)."""
    with get_connection() as conn:
        cols = {r["name"] for r in conn.execute("PRAGMA table_info(topics)").fetchall()}
        if "seo_priority" not in cols:
            conn.execute("ALTER TABLE topics ADD COLUMN seo_priority INTEGER")
        if "seo_priority_json" not in cols:
            conn.execute("ALTER TABLE topics ADD COLUMN seo_priority_json TEXT")
        conn.commit()


def demand_points(demand: int | None) -> int:
    if demand is None:
        return 10
    if demand >= 10000:
        return 40
    if demand >= 3000:
        return 34
    if demand >= 1000:
        return 28
    if demand >= 300:
        return 20
    if demand >= 100:
        return 12
    if demand >= 30:
        return 6
    return 2


def competition_points(strong: int | None) -> int:
    if strong is None:
        return 15
    if strong <= 5:
        return 30
    if strong <= 10:
        return 26
    if strong <= 15:
        return 18
    if strong <= 20:
        return 10
    return 4


def intent_points(intent_type: str | None) -> int:
    mapping = {"informational": 15, "mixed": 8, "commercial": 2}
    return mapping.get((intent_type or "").lower(), 10)


def readiness_points(has_text: bool, quality: int | None) -> int:
    if has_text and quality is not None and quality >= 80:
        return 15
    if has_text and quality is not None:
        return 9
    if has_text:
        return 6
    return 4


STOP = {
    "как", "что", "чем", "для", "или", "при", "без", "над", "под", "про", "это",
    "когда", "нужно", "нужен", "нужна", "лучше", "какой", "какая", "какие", "почему",
    "можно", "ли", "в", "на", "и", "с", "по", "из", "от", "до", "не", "а", "же",
    "своими", "руками", "правильно", "знать", "выбрать", "делать", "нужны", "зачем",
}


def _tokens(text: str) -> set[str]:
    """Значимые слова в усечённой форме (первые 5 букв) — грубый стемминг для русского."""
    words = re.findall(r"[а-яёa-z0-9]{3,}", (text or "").lower().replace("ё", "е"))
    return {w[:5] for w in words if w not in STOP}


def _overlap(a: str, b: str) -> float:
    ta, tb = _tokens(a), _tokens(b)
    if not ta or not tb:
        return 0.0
    return len(ta & tb) / len(ta | tb)


def find_metrics(conn, title: str) -> tuple[dict | None, dict | None, str]:
    """Ищет метрики по заголовку: сначала точное совпадение, затем ближайшее по словам.

    Важно: нельзя брать «любую фразу, где встречается слово пайка» — тогда
    «Пайка алюминия» получит спрос от «пайка проводов» и рейтинг соврёт.
    Поэтому кандидат принимается только при пересечении значимых слов ≥ 0.5.
    """
    ws = None
    mg = None
    matched = ""

    try:
        ws = conn.execute(
            "SELECT phrase, total_count FROM wordstat_metrics WHERE phrase = ?", (title,)
        ).fetchone()
        if ws:
            matched = ws["phrase"]
    except Exception:
        ws = None
    try:
        mg = conn.execute(
            "SELECT keyword, strong, wordstat FROM keyword_metrics WHERE keyword = ?", (title,)
        ).fetchone()
        if mg and not matched:
            matched = mg["keyword"]
    except Exception:
        mg = None

    if ws or mg:
        return (dict(ws) if ws else None, dict(mg) if mg else None, matched)

    # 1) Надёжная эвристика: измеренная фраза входит в заголовок целиком.
    #    «как паять» ⊂ «Как паять паяльником: пошаговый гайд» — это та же тема.
    tnorm = re.sub(r"[^а-яёa-z0-9 ]", " ", (title or "").lower().replace("ё", "е"))
    tnorm = re.sub(r"\s+", " ", tnorm).strip()

    def in_title(phrase: str) -> bool:
        p = re.sub(r"[^а-яёa-z0-9 ]", " ", (phrase or "").lower().replace("ё", "е"))
        p = re.sub(r"\s+", " ", p).strip()
        return bool(p) and len(p) >= 4 and p in tnorm

    try:
        for c in conn.execute(
            "SELECT phrase, total_count FROM wordstat_metrics WHERE total_count IS NOT NULL "
            "ORDER BY length(phrase) DESC"
        ).fetchall():
            if in_title(c["phrase"]):
                ws = c
                matched = c["phrase"]
                break
    except Exception:
        pass
    try:
        for c in conn.execute(
            "SELECT keyword, strong, wordstat FROM keyword_metrics ORDER BY length(keyword) DESC"
        ).fetchall():
            if in_title(c["keyword"]):
                mg = c
                if not matched:
                    matched = c["keyword"]
                break
    except Exception:
        pass

    if ws or mg:
        return (dict(ws) if ws else None, dict(mg) if mg else None, matched)

    # Поиск по словам с проверкой пересечения
    words = [w for w in re.findall(r"[а-яёa-z0-9]{5,}", title.lower().replace("ё", "е")) if w not in STOP]
    if not words:
        return None, None, ""

    best_ws, best_ws_score = None, 0.0
    try:
        for cand in conn.execute(
            "SELECT phrase, total_count FROM wordstat_metrics WHERE total_count IS NOT NULL "
            "ORDER BY total_count DESC LIMIT 200"
        ).fetchall():
            score = _overlap(title, cand["phrase"])
            if score > best_ws_score:
                best_ws, best_ws_score = cand, score
    except Exception:
        pass

    best_mg, best_mg_score = None, 0.0
    try:
        for cand in conn.execute(
            "SELECT keyword, strong, wordstat FROM keyword_metrics ORDER BY wordstat DESC LIMIT 200"
        ).fetchall():
            score = _overlap(title, cand["keyword"])
            if score > best_mg_score:
                best_mg, best_mg_score = cand, score
    except Exception:
        pass

    THRESHOLD = 0.5
    if best_ws_score >= THRESHOLD and best_ws:
        ws = best_ws
        matched = best_ws["phrase"]
    if best_mg_score >= THRESHOLD and best_mg:
        mg = best_mg
        if not matched:
            matched = best_mg["keyword"]

    return (dict(ws) if ws else None, dict(mg) if mg else None, matched)


def compute_for_topic(conn, topic: dict) -> dict:
    ws, mg, matched = find_metrics(conn, topic["title"])

    demand = None
    if ws and ws.get("total_count") is not None:
        demand = int(ws["total_count"])
    elif mg and mg.get("wordstat") is not None:
        demand = int(mg["wordstat"])

    strong = int(mg["strong"]) if (mg and mg.get("strong") is not None) else None

    # интент: из сохранённой оценки квоты, если есть
    intent_type = None
    try:
        with get_connection() as c2:
            row = c2.execute(
                "SELECT intent_type FROM keyword_competition WHERE keyword = ?", (matched or topic["title"],)
            ).fetchone()
        if row:
            intent_type = row["intent_type"]
    except Exception:
        intent_type = None

    has_text = (topic.get("chars") or 0) > 500
    quality = topic.get("score_total")

    d_pts = demand_points(demand)
    c_pts = competition_points(strong)
    i_pts = intent_points(intent_type)
    r_pts = readiness_points(has_text, quality)
    total = d_pts + c_pts + i_pts + r_pts

    if total >= 80:
        grade = "A"
    elif total >= 60:
        grade = "B"
    elif total >= 40:
        grade = "C"
    else:
        grade = "D"

    breakdown = {
        "demand": {"points": d_pts, "max": 40, "value": demand,
                   "note": "широкая частотность Вордстата в месяц" if demand else "замеров нет"},
        "competition": {"points": c_pts, "max": 30, "value": strong,
                        "note": "конкуренция Мутагена 0–25" if strong is not None else "замера нет"},
        "intent": {"points": i_pts, "max": 15, "value": intent_type or "не определён",
                   "note": "informational выгоднее commercial"},
        "readiness": {"points": r_pts, "max": 15,
                      "value": f"текст {topic.get('chars') or 0} зн., качество {quality}",
                      "note": "готовность материала к публикации"},
        "total": total,
        "grade": grade,
        "matched_phrase": matched,
    }
    return breakdown


def recalculate() -> list[dict]:
    ensure_columns()
    results: list[dict] = []
    with get_connection() as conn:
        topics = [dict(r) for r in conn.execute("SELECT * FROM topics ORDER BY id").fetchall()]
        for t in topics:
            b = compute_for_topic(conn, t)
            results.append({"id": t["id"], "title": t["title"], **b})
            conn.execute(
                "UPDATE topics SET seo_priority = ?, seo_priority_json = ? WHERE id = ?",
                (b["total"], json.dumps(b, ensure_ascii=False), t["id"]),
            )
        conn.commit()
    results.sort(key=lambda x: -x["total"])
    return results


def main() -> int:
    ap = argparse.ArgumentParser(description="Приоритет тем по спросу и конкуренции")
    ap.add_argument("--top", type=int, default=20, help="сколько строк показать")
    ap.add_argument("--json", action="store_true")
    args = ap.parse_args()

    res = recalculate()

    if args.json:
        print(json.dumps(res[: args.top], ensure_ascii=False, indent=2))
        return 0

    print("=" * 96)
    print("ПРИОРИТЕТ ТЕМ (0–100): Спрос 40 + Конкуренция 30 + Интент 15 + Готовность 15")
    print("=" * 96)
    print(f"{'#':<4} {'КЛАСС':<6} {'ИТОГ':<6} {'СПРОС':<7} {'КОНК':<6} {'ИНТ':<5} {'ГОТ':<5} {'ТЕМА'}")
    print("-" * 96)
    for r in res[: args.top]:
        print(
            f"#{r['id']:<3} {r['grade']:<6} {r['total']:<6} "
            f"{r['demand']['points']:<7} {r['competition']['points']:<6} "
            f"{r['intent']['points']:<5} {r['readiness']['points']:<5} {r['title'][:44]}"
        )
    print("-" * 96)

    counts = {}
    for r in res:
        counts[r["grade"]] = counts.get(r["grade"], 0) + 1
    print("Распределение:", ", ".join(f"{g}: {counts.get(g, 0)}" for g in "ABCD"))
    print("\nКлассы: A (80–100) писать первым · B (60–79) вторая волна · "
          "C (40–59) по поводу · D (0–39) не тратить силы")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
