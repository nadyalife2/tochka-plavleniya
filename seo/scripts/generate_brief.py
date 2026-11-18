"""Генератор подробного брифа для автора-человека (шеф-редактора).

Формирует чёткое ТЗ для самостоятельного написания статьи:
1. Заголовок и интент.
2. Реальные данные спроса (Вордстат) и конкуренции (Мутаген).
3. Список поисковых запросов и хвостов людей.
4. Канонические сплавы, температуры и ГОСТ/IPC.
5. Обязательные внутренние ссылки для перелинковки.
6. Рекомендованный план разделов H2.

Использование:
    python seo/scripts/generate_brief.py --id 20
    python seo/scripts/generate_brief.py --top
"""
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SCRIPTS = ROOT / "seo" / "scripts"
sys.path.insert(0, str(SCRIPTS))

from db import get_connection


def build_author_brief(topic_id: int | str) -> dict:
    tid = int(topic_id) if str(topic_id).isdigit() else topic_id
    with get_connection() as conn:
        t = conn.execute("SELECT * FROM topics WHERE id = ?", (tid,)).fetchone()
        if not t:
            raise SystemExit(f"Тема #{tid} не найдена в базе!")
        topic = dict(t)

        # 1. Метрики Вордстата и Мутагена
        ws = conn.execute("SELECT * FROM wordstat_metrics WHERE phrase = ?", (topic["title"],)).fetchone()
        if not ws:
            # поиск по словам
            for w in [x for x in topic["title"].lower().split() if len(x) > 4][:2]:
                ws = conn.execute("SELECT * FROM wordstat_metrics WHERE lower(phrase) LIKE ? LIMIT 1", (f"%{w}%",)).fetchone()
                if ws:
                    break
        mg = conn.execute("SELECT * FROM keyword_metrics WHERE keyword = ?", (topic["title"],)).fetchone()
        if not mg:
            for w in [x for x in topic["title"].lower().split() if len(x) > 4][:2]:
                mg = conn.execute("SELECT * FROM keyword_metrics WHERE lower(keyword) LIKE ? LIMIT 1", (f"%{w}%",)).fetchone()
                if mg:
                    break

        # 2. Исходящие ссылки
        out_links = conn.execute("""
            SELECT l.target_id, l.anchor, t2.title, t2.slug, t2.chars
            FROM links l
            JOIN topics t2 ON l.target_id = t2.id
            WHERE l.source_id = ?
        """, (tid,)).fetchall()

        # 3. Фишки конкурентов в этом кластере
        feats = conn.execute("""
            SELECT domain, feature_type, title, description
            FROM competitor_features
            LIMIT 5
        """).fetchall()

    demand = (dict(ws).get("total_count") if ws else None) or (dict(mg).get("wordstat") if mg else None)
    comp = dict(mg).get("strong") if mg else None

    # Похожие запросы из Вордстата
    queries = []
    if ws and ws["top_json"]:
        try:
            items = json.loads(ws["top_json"])
            queries = [it["phrase"] for it in items[:8] if it.get("phrase")]
        except Exception:
            pass

    # Рекомендованные ГОСТ/IPC в зависимости от кластера и названия
    low_title = topic["title"].lower()
    standards = []
    if "флюс" in low_title or "канифол" in low_title:
        standards = [
            "ГОСТ 19250-73 (Флюсы паяльные. Классификация)",
            "IPC J-STD-004B (Requirements for Soldering Fluxes: ROL0, RMA, RA)",
        ]
        alloy = "universal / пос-61"
    elif "припой" in low_title or "пос" in low_title:
        standards = [
            "ГОСТ 21930-76 (Припои оловянно-свинцовые в чушках)",
            "ГОСТ 21931-76 (Припои в виде проволоки и трубок с канифолью)",
            "ISO 9453:2020 (Soft solder alloys)",
        ]
        alloy = "pos61 / pos40"
    elif "провод" in low_title:
        standards = [
            "ГОСТ 23587-79 (Монтаж электрический радиоэлектронной аппаратуры. Разделка проводов)",
            "IPC-A-610H (Критерии качества монтажа проводных соединений)",
        ]
        alloy = "pos61"
    else:
        standards = [
            "ГОСТ 21931-76 (Припои оловянно-свинцовые с канифолью)",
            "IPC-A-610H (Критерии приёмки электронных сборок)",
        ]
        alloy = "pos61 / universal"

    links_formatted = []
    for l in out_links:
        links_formatted.append({
            "id": l["target_id"],
            "title": l["title"],
            "url": f"/{l['slug']}",
            "anchor": l["anchor"] or l["title"],
            "ready": (l["chars"] or 0) > 300,
        })

    md_brief = f"""# 📋 БРИФ ДЛЯ АВТОРА: Статья #{tid}

## 1. Паспорт статьи
- **Заголовок:** {topic['title']}
- **URL (slug):** `/{topic['slug']}`
- **Кластер:** {topic['cluster']}
- **Формат:** {topic['type'] or 'гайд'}
- **Целевой объём:** 5 500 – 7 000 знаков с пробелами
- **Приоритет темы в плане:** SEO {topic.get('seo_priority', '—')} / 100

## 2. Спрос и конкуренция (что люди реально ищут)
- **Широкий спрос (Вордстат):** {demand or 'требует замера'} показов/мес
- **Конкуренция (Мутаген):** {f'{comp}/25' if comp is not None else 'требует замера'}
- **Реальные запросы людей для подзаголовков H2 и FAQ:**
{chr(10).join(f'  • «{q}»' for q in queries) if queries else '  • Используйте естественные вопросы новичков по теме'}

## 3. Обязательные технические факты и стандарты
- **Канонический сплав:** {alloy}
- **Реалистичные температуры:** 280–320 °C (для ПОС-61 жалом), не придумывать пиков выше нормы
- **Нормативная база (упомянуть в статье):**
{chr(10).join(f'  • {s}' for s in standards)}

## 4. Обязательные внутренние ссылки (вставить в естественный контекст)
{chr(10).join(f"  • [{l['anchor']}]({l['url']}) — переход на статью #{l['id']} «{l['title']}»" for l in links_formatted) if links_formatted else '  • Ссылки на соседние материалы кластера'}

## 5. Рекомендованный скелет структуры
1. **Первый абзац (Answer First, до 40 слов):** прямой исчерпывающий ответ на главный вопрос темы с первой строчки.
2. **Раздел H2 (1):** Суть и матчасть (без «воды» — сразу параметры, цифры, сравнение).
3. **Раздел H2 (2):** Пошаговый алгоритм или практическая инструкция с индикаторами успеха.
4. **Раздел H2 (3):** Где ошибаются и сколько это стоит (типичные грабли новичка).
5. **Раздел H2 (4):** ## Безопасность и средства защиты (вытяжка, защита глаз, гигиена).
6. **Раздел H2 (5):** ## Нормативная база (перечислить ГОСТ/IPC).
7. **Раздел H2 (6):** ## FAQ (4–5 реальных конкретных вопросов и кратких ответов по 1–3 предложения).
"""

    return {
        "topic_id": tid,
        "title": topic["title"],
        "slug": topic["slug"],
        "cluster": topic["cluster"],
        "demand": demand,
        "competition": comp,
        "queries": queries,
        "standards": standards,
        "links": links_formatted,
        "markdown": md_brief,
    }


def main():
    parser = argparse.ArgumentParser(description="Генератор ТЗ/брифа для автора")
    parser.add_argument("--id", help="ID темы")
    parser.add_argument("--top", action="store_true", help="Показать топ тем, готовых для написания")
    args = parser.parse_args()

    if args.top:
        with get_connection() as conn:
            rows = conn.execute("""
                SELECT id, title, cluster, seo_priority
                FROM topics
                WHERE (content_markdown IS NULL OR length(content_markdown) < 200)
                ORDER BY seo_priority DESC, id
                LIMIT 10
            """).fetchall()
        print("=" * 70)
        print("ТОП ТЕМ ДЛЯ НАПИСАНИЯ АВТОРОМ (высокий SEO-приоритет, текста ещё нет):")
        print("=" * 70)
        for r in rows:
            print(f"#{r['id']:<3} SEO {r['seo_priority'] or '—':>3}/100  |  [{r['cluster'][:22]:<22}]  {r['title']}")
        print("=" * 70)
        print("Для получения полного брифа: python seo/scripts/generate_brief.py --id <ID>")
        return 0

    if not args.id:
        parser.print_help()
        return 0

    res = build_author_brief(args.id)
    print(res["markdown"])
    return 0


if __name__ == "__main__":
    main()
