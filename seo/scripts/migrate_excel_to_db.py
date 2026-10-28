"""Полная миграция данных из semantic_core_FULL_v11.xlsx в SQLite seo/data/seo.db.

Переносит:
- clusters (18 кластеров)
- content_plan + priority_scores (60 тем с приоритетами)
- interlinking_map (147 связей)
- competitors (17 доменов)
- competitors_proposed (6 найденных сайтов)
- topics_proposed (10 предложенных тем из пробелов)
- raw_data (64 собранные статьи конкурентов)
- statiy + файлы из 'сами статьи/' (полные тексты статей)
- briefs_ТЗ (паспорта ТЗ)
- scores (оценки 0-100 из out/scores/)
"""
from __future__ import annotations

import json
import sqlite3
import sys
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "seo" / "scripts"))

from db import DB_PATH, get_connection, init_db
from excel_io import (
    MASTER,
    OUT,
    PRIORITY_RANK,
    STATUS_MAP,
    build_plan,
    clean,
    load_master,
    norm_id,
    read_sheet,
)


def migrate_all() -> dict:
    init_db()
    plan = build_plan()
    wb = load_master()

    counts = {
        "clusters": 0,
        "topics": 0,
        "links": 0,
        "competitors": 0,
        "competitors_proposed": 0,
        "topics_proposed": 0,
        "raw_articles": 0,
        "briefs": 0,
        "articles_with_text": 0,
    }

    scores = {}
    score_dir = OUT / "scores"
    if score_dir.exists():
        for sf in score_dir.glob("*.json"):
            try:
                sdata = json.loads(sf.read_text(encoding="utf-8"))
                scores[str(sdata.get("article_id"))] = sdata
            except Exception:
                continue

    # Загружаем реальные тексты из папки 'сами статьи'
    disk_texts = {}
    articles_dir = ROOT / "сами статьи"
    if articles_dir.exists():
        for mf in articles_dir.glob("*.md"):
            name = mf.stem.lower()
            tid_match = __import__("re").match(r"^(\d+)-", name)
            if tid_match:
                disk_texts[tid_match.group(1)] = mf.read_text(encoding="utf-8", errors="replace")

    with get_connection() as conn:
        cur = conn.cursor()

        # 1. Clusters
        for name, c in plan["clusters"].items():
            cur.execute("""
                INSERT OR REPLACE INTO clusters
                (name, description, keywords, type, priority, priority_rank, competitors, gap, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            """, (
                name,
                c.get("description", ""),
                c.get("keywords", ""),
                c.get("type", ""),
                c.get("priority", ""),
                c.get("priority_rank", 2),
                c.get("competitors", ""),
                c.get("gap", ""),
                c.get("status", "в работе"),
            ))
            counts["clusters"] += 1

        # 2. Competitors
        for c in plan["competitors"]:
            domain = clean(c.get("domain"))
            if not domain:
                continue
            cur.execute("""
                INSERT OR REPLACE INTO competitors
                (domain, niche, type, blog_url, articles_count, notes, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            """, (
                domain,
                c.get("niche", ""),
                c.get("type", "Прямой конкурент"),
                c.get("blog", ""),
                int(c.get("articles")) if str(c.get("articles", "")).isdigit() else 0,
                c.get("notes", ""),
                datetime.now().strftime("%Y-%m-%d %H:%M"),
            ))
            counts["competitors"] += 1

        # 3. Competitors Proposed
        for cp in read_sheet(wb, "competitors_proposed"):
            domain = clean(cp.get("домен"))
            if not domain:
                continue
            cur.execute("""
                INSERT OR REPLACE INTO competitors_proposed
                (domain, niche, type, source, queries, hits, articles_count, example_url, status, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            """, (
                domain,
                clean(cp.get("ниша")),
                clean(cp.get("тип")),
                clean(cp.get("источник_находки")),
                clean(cp.get("запросы")),
                int(clean(cp.get("сколько_раз_в_выдаче")) or 1),
                int(clean(cp.get("найдено_статей")) or 0),
                clean(cp.get("пример_url")),
                clean(cp.get("статус")) or "предложен",
                clean(cp.get("дата_обновления")) or datetime.now().strftime("%Y-%m-%d %H:%M"),
            ))
            counts["competitors_proposed"] += 1

        # 4. Topics Proposed
        for tp in read_sheet(wb, "topics_proposed"):
            pid = clean(tp.get("предложение_id"))
            title = clean(tp.get("рабочий заголовок"))
            if not title:
                continue
            cur.execute("""
                INSERT OR REPLACE INTO topics_proposed
                (id, title, cluster, type, source, evidence, hits, priority_score, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            """, (
                pid or title,
                title,
                clean(tp.get("кластер")),
                clean(tp.get("тип статьи")),
                clean(tp.get("источник")),
                clean(tp.get("доказательство")),
                int(clean(tp.get("частота_в_выдаче")) or 1),
                int(clean(tp.get("оценка_приоритета")) or 50),
                clean(tp.get("статус")) or "предложен",
                clean(tp.get("дата_обновления")) or datetime.now().strftime("%Y-%m-%d %H:%M"),
            ))
            counts["topics_proposed"] += 1

        # 5. Raw Articles
        for ra in read_sheet(wb, "raw_data"):
            url = clean(ra.get("url"))
            if not url:
                continue
            cur.execute("""
                INSERT OR REPLACE INTO raw_articles
                (url, domain, title, h1, date, content_type, cluster, status, crawled_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            """, (
                url,
                clean(ra.get("source")),
                clean(ra.get("title")),
                clean(ra.get("h1")),
                clean(ra.get("date")),
                clean(ra.get("content_type")),
                clean(ra.get("cluster")),
                clean(ra.get("status")) or "собрано",
                datetime.now().strftime("%Y-%m-%d %H:%M"),
            ))
            counts["raw_articles"] += 1

        # 6. Topics & Articles
        for tid, t in plan["topics"].items():
            score_data = scores.get(tid)
            score_tot = score_data.get("total") if score_data else None
            score_blob = json.dumps(score_data, ensure_ascii=False) if score_data else None

            # Текст: сначала из папки 'сами статьи', иначе из Excel statiy
            content = disk_texts.get(tid) or (t.get("text", {}).get("text") if t.get("text") else None)
            char_len = len(content) if content else 0
            if content and char_len > 500:
                counts["articles_with_text"] += 1

            cur.execute("""
                INSERT OR REPLACE INTO topics
                (id, title, slug, cluster, type, priority, priority_score, status, next_action, bot,
                 content_markdown, chars, score_total, score_json, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            """, (
                int(tid) if tid.isdigit() else tid,
                t["title"],
                t["slug"],
                t["cluster"],
                t["type"],
                t["priority"],
                t.get("priority_score", 50),
                t["status"],
                t.get("next_action", ""),
                t.get("bot", ""),
                content,
                char_len,
                score_tot,
                score_blob,
                datetime.now().isoformat(timespec="seconds"),
                datetime.now().isoformat(timespec="seconds"),
            ))
            counts["topics"] += 1

        # 7. Links
        for l in plan["links"]:
            try:
                sid = int(l["source"])
                tid = int(l["target"])
                cur.execute("""
                    INSERT INTO links (source_id, target_id, anchor, status)
                    VALUES (?, ?, ?, ?)
                """, (sid, tid, l.get("anchor", ""), "planned"))
                counts["links"] += 1
            except Exception:
                continue

        # 8. Briefs
        for b in read_sheet(wb, "briefs_ТЗ"):
            bid = norm_id(b.get("ID_темы"))
            if not bid:
                continue
            cur.execute("""
                INSERT OR REPLACE INTO briefs
                (topic_id, intent, reader_pain, geo_snippet, eeat_facts, feature, lsi, status, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            """, (
                int(bid) if bid.isdigit() else bid,
                clean(b.get("Интент_и_Этап_Воронки")),
                clean(b.get("Боль_и_Психология_Читателя")),
                clean(b.get("GEO_Сниппет_Шаблон")),
                clean(b.get("E-E-A-T_Фактура")),
                clean(b.get("Внедряемая_Фишечка")),
                clean(b.get("LSI_и_Сущности")),
                clean(b.get("Статус_ТЗ")) or "готово",
                datetime.now().strftime("%Y-%m-%d %H:%M"),
            ))
            counts["briefs"] += 1

        conn.commit()

    wb.close()
    return counts


if __name__ == "__main__":
    res = migrate_all()
    print("=" * 60)
    print("МИГРАЦИЯ В SQLITE ЗАВЕРШЕНА:")
    for k, v in res.items():
        print(f"  • {k:<22}: {v}")
    print("Файл БД:", DB_PATH)
    print("=" * 60)
