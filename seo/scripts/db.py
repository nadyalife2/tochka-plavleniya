"""База данных SEO-конвейера «Точка Плавления» (SQLite).

Заменяет Excel semantic_core_FULL_v11.xlsx для работы через UI и скрипты.
Файл БД: seo/data/seo.db
Полностью локально, автономно, с транзакциями и авто-бэкапом.
"""
from __future__ import annotations

import json
import re
import shutil
import sqlite3
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SEO = ROOT / "seo"
DATA = SEO / "data"
DB_PATH = DATA / "seo.db"
BACKUP_DIR = DATA / "_backup"


def get_connection() -> sqlite3.Connection:
    DATA.mkdir(parents=True, exist_ok=True)
    conn = sqlite3.connect(str(DB_PATH), timeout=20.0)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA journal_mode=WAL;")
    conn.execute("PRAGMA foreign_keys=ON;")
    return conn


def init_db() -> None:
    """Создаёт таблицы базы данных, если их нет."""
    with get_connection() as conn:
        conn.executescript("""
        CREATE TABLE IF NOT EXISTS topics (
            id INTEGER PRIMARY KEY,
            title TEXT NOT NULL,
            slug TEXT UNIQUE NOT NULL,
            cluster TEXT NOT NULL,
            type TEXT DEFAULT 'гайд',
            priority TEXT DEFAULT 'средний',
            priority_score INTEGER DEFAULT 50,
            status TEXT DEFAULT 'backlog',
            next_action TEXT,
            bot TEXT,
            content_markdown TEXT,
            chars INTEGER DEFAULT 0,
            score_total INTEGER,
            score_json TEXT,
            is_critical INTEGER DEFAULT 0,
            review_status TEXT,
            review_model TEXT,
            factcheck_status TEXT,
            factcheck_model TEXT,
            writer_mode TEXT,
            created_at TEXT,
            updated_at TEXT
        );

        CREATE TABLE IF NOT EXISTS clusters (
            name TEXT PRIMARY KEY,
            description TEXT,
            keywords TEXT,
            type TEXT,
            priority TEXT,
            priority_rank INTEGER DEFAULT 2,
            competitors TEXT,
            gap TEXT,
            status TEXT
        );

        CREATE TABLE IF NOT EXISTS links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            source_id INTEGER NOT NULL,
            target_id INTEGER NOT NULL,
            anchor TEXT,
            status TEXT DEFAULT 'planned',
            UNIQUE(source_id, target_id)
        );

        CREATE TABLE IF NOT EXISTS jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            job_type TEXT NOT NULL,
            target_id TEXT,
            params_json TEXT,
            status TEXT DEFAULT 'queued',
            return_code INTEGER,
            log TEXT,
            created_at TEXT,
            updated_at TEXT
        );

        CREATE TABLE IF NOT EXISTS competitors (
            domain TEXT PRIMARY KEY,
            niche TEXT,
            type TEXT,
            blog_url TEXT,
            articles_count INTEGER DEFAULT 0,
            features TEXT,
            seo_keywords TEXT,
            score INTEGER DEFAULT 50,
            notes TEXT,
            updated_at TEXT
        );

        CREATE TABLE IF NOT EXISTS competitors_proposed (
            domain TEXT PRIMARY KEY,
            niche TEXT,
            type TEXT,
            source TEXT,
            queries TEXT,
            hits INTEGER DEFAULT 1,
            articles_count INTEGER DEFAULT 0,
            example_url TEXT,
            status TEXT DEFAULT 'предложен',
            updated_at TEXT
        );

        CREATE TABLE IF NOT EXISTS topics_proposed (
            id TEXT PRIMARY KEY,
            title TEXT NOT NULL,
            cluster TEXT NOT NULL,
            type TEXT,
            source TEXT,
            evidence TEXT,
            hits INTEGER DEFAULT 1,
            priority_score INTEGER DEFAULT 50,
            status TEXT DEFAULT 'предложен',
            created_at TEXT
        );

        CREATE TABLE IF NOT EXISTS raw_articles (
            url TEXT PRIMARY KEY,
            domain TEXT NOT NULL,
            title TEXT NOT NULL,
            h1 TEXT,
            date TEXT,
            content_type TEXT,
            cluster TEXT,
            status TEXT DEFAULT 'собрано',
            crawled_at TEXT
        );

        CREATE TABLE IF NOT EXISTS competitor_features (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            domain TEXT NOT NULL,
            feature_type TEXT NOT NULL,
            title TEXT NOT NULL,
            description TEXT,
            example_url TEXT,
            seo_value TEXT,
            found_at TEXT
        );

        CREATE TABLE IF NOT EXISTS briefs (
            topic_id INTEGER PRIMARY KEY,
            intent TEXT,
            reader_pain TEXT,
            geo_snippet TEXT,
            eeat_facts TEXT,
            feature TEXT,
            lsi TEXT,
            faq_json TEXT,
            status TEXT DEFAULT 'черновик',
            updated_at TEXT
        );

        CREATE TABLE IF NOT EXISTS agent_runs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            agent_name TEXT NOT NULL,
            action TEXT NOT NULL,
            target_id TEXT,
            status TEXT NOT NULL,
            log TEXT,
            created_at TEXT
        );

        CREATE TABLE IF NOT EXISTS backups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename TEXT NOT NULL,
            filepath TEXT NOT NULL,
            size_bytes INTEGER NOT NULL,
            records_count INTEGER NOT NULL,
            created_at TEXT NOT NULL
        );
        """)


def backup_database() -> dict:
    """Создаёт резервную копию базы данных в seo/data/_backup/."""
    BACKUP_DIR.mkdir(parents=True, exist_ok=True)
    stamp = datetime.now().strftime("%Y-%m-%d_%H-%M-%S")
    backup_file = BACKUP_DIR / f"seo_{stamp}.db"

    # SQLite vacuum into / copy
    with get_connection() as conn:
        conn.execute(f"VACUUM INTO '{str(backup_file).replace(chr(92), '/')}'")
        cur = conn.cursor()
        cur.execute("SELECT COUNT(*) FROM topics")
        topics_count = cur.fetchone()[0]

    size = backup_file.stat().st_size
    with get_connection() as conn:
        conn.execute(
            "INSERT INTO backups (filename, filepath, size_bytes, records_count, created_at) "
            "VALUES (?, ?, ?, ?, ?)",
            (backup_file.name, str(backup_file), size, topics_count, datetime.now().isoformat(timespec="seconds")),
        )

    return {
        "filename": backup_file.name,
        "path": str(backup_file),
        "size_bytes": size,
        "topics_count": topics_count,
        "timestamp": stamp,
    }


def list_backups() -> list[dict]:
    with get_connection() as conn:
        cur = conn.execute("SELECT * FROM backups ORDER BY id DESC LIMIT 20")
        return [dict(r) for r in cur.fetchall()]


def migrate_schema_v2():
    """Миграция существующей БД: удаление дублей связей, уникальный индекс, колонки текста."""
    with get_connection() as conn:
        # 1. Удаляем возможные дубликаты связей
        conn.execute("""
            DELETE FROM links
            WHERE id NOT IN (
                SELECT MIN(id) FROM links GROUP BY source_id, target_id
            )
        """)
        # 2. Создаем уникальный индекс
        conn.execute("CREATE UNIQUE INDEX IF NOT EXISTS idx_links_pair ON links (source_id, target_id);")
        # 3. Добавляем колонку content_markdown и content_hash в raw_articles, если их нет
        cur = conn.cursor()
        cols = [c[1] for c in cur.execute("PRAGMA table_info(raw_articles)").fetchall()]
        if "content_markdown" not in cols:
            conn.execute("ALTER TABLE raw_articles ADD COLUMN content_markdown TEXT;")
        if "content_hash" not in cols:
            conn.execute("ALTER TABLE raw_articles ADD COLUMN content_hash TEXT;")
        conn.commit()


def migrate_schema_v3():
    """Миграция v3: флаг критичности, статусы рецензии/фактчека и режим автора."""
    with get_connection() as conn:
        cur = conn.cursor()
        cols = [c[1] for c in cur.execute("PRAGMA table_info(topics)").fetchall()]
        additions = {
            "is_critical": "INTEGER DEFAULT 0",
            "review_status": "TEXT",
            "review_model": "TEXT",
            "factcheck_status": "TEXT",
            "factcheck_model": "TEXT",
            "writer_mode": "TEXT",
        }
        for column, decl in additions.items():
            if column not in cols:
                conn.execute(f"ALTER TABLE topics ADD COLUMN {column} {decl};")
        conn.commit()


def migrate_schema_v5() -> None:
    """Миграция v5: таблица keyword-гипотез (для дедупликации выгрузок)."""
    with get_connection() as conn:
        conn.execute(
            """
            CREATE TABLE IF NOT EXISTS keywords (
                keyword TEXT PRIMARY KEY,
                cluster_id TEXT,
                origin TEXT,
                status TEXT DEFAULT 'not_checked',
                source_page_ids TEXT,
                created_at TEXT
            )
            """
        )
        conn.execute("CREATE INDEX IF NOT EXISTS idx_keywords_cluster ON keywords (cluster_id);")
        conn.commit()


def _rows(conn, sql: str) -> list[dict]:
    return [dict(r) for r in conn.execute(sql).fetchall()]


def build_export_manifest() -> dict:
    """Компактный манифест «что уже есть» — чтобы нейронка не делала дублей.

    Ключи сортированы и стабильны: slugs, titles, clusters, keywords,
    competitor_domains, collected_urls, proposed_titles.
    """
    with get_connection() as conn:
        topics = _rows(conn, "SELECT id, title, slug, cluster, status, chars, score_total "
                             "FROM topics ORDER BY id ASC")
        clusters = _rows(conn, "SELECT name, keywords, type, priority, status "
                               "FROM clusters ORDER BY priority_rank ASC, name ASC")
        competitors = _rows(conn, "SELECT domain, type, niche FROM competitors ORDER BY domain ASC")
        raw = _rows(conn, "SELECT url, domain, title FROM raw_articles ORDER BY crawled_at DESC")
        try:
            keywords = _rows(conn, "SELECT keyword, cluster_id FROM keywords ORDER BY keyword ASC")
        except Exception:  # noqa: BLE001
            keywords = []
        proposed = _rows(conn, "SELECT title FROM topics_proposed "
                               "WHERE status != 'отклонён' ORDER BY id ASC")

    return {
        "schema": "tchp-export-index/1.0",
        "generated_at": datetime.now().isoformat(timespec="seconds"),
        "counts": {
            "topics": len(topics),
            "written": sum(1 for t in topics if (t.get("chars") or 0) > 500),
            "clusters": len(clusters),
            "competitors": len(competitors),
            "collected_urls": len(raw),
            "keywords": len(keywords),
            "proposed_titles": len(proposed),
        },
        "slugs": [t["slug"] for t in topics],
        "titles": [t["title"] for t in topics],
        "clusters": [
            {"name": c["name"], "keywords": c["keywords"], "type": c["type"], "status": c["status"]}
            for c in clusters
        ],
        "keywords": [{"keyword": k["keyword"], "cluster_id": k["cluster_id"]} for k in keywords],
        "competitor_domains": [c["domain"] for c in competitors],
        "collected_urls": [
            {"url": r["url"], "domain": r["domain"], "title": r["title"]} for r in raw
        ],
        "proposed_titles": [p["title"] for p in proposed],
        "topics": [
            {"id": t["id"], "title": t["title"], "slug": t["slug"], "cluster": t["cluster"],
             "status": t["status"], "chars": t["chars"], "score": t["score_total"]}
            for t in topics
        ],
    }


def export_manifest_markdown(manifest: dict) -> str:
    """Тот же манифест, но markdown-таблицами — удобно вставлять в промпт."""
    lines = [
        "# Что уже есть в базе «Точка Плавления»",
        "",
        f"Сгенерировано: {manifest['generated_at']}",
        "",
        "## Счётчики",
        "",
        "| Сущность | Кол-во |",
        "|---|---:|",
    ]
    for key, value in manifest["counts"].items():
        lines.append(f"| {key} | {value} |")

    lines += ["", "## Существующие статьи (slug — не повторять)", "", "| id | title | slug | cluster | status |", "|---:|---|---|---|---|"]
    for t in manifest["topics"]:
        lines.append(f"| {t['id']} | {t['title']} | `{t['slug']}` | {t['cluster']} | {t['status']} |")

    lines += ["", "## Кластеры", "", "| Кластер | Ключевые слова |", "|---|---|"]
    for c in manifest["clusters"]:
        lines.append(f"| {c['name']} | {c['keywords'] or ''} |")

    lines += ["", "## Домены конкурентов", "", ", ".join(manifest["competitor_domains"]) or "—"]

    if manifest["keywords"]:
        lines += ["", "## Keyword-гипотезы", "", ", ".join(k["keyword"] for k in manifest["keywords"])]

    lines += ["", "## Уже собранные URL", "", "| URL | Домен |", "|---|---|"]
    for u in manifest["collected_urls"]:
        lines.append(f"| {u['url']} | {u['domain']} |")

    if manifest["proposed_titles"]:
        lines += ["", "## Предложенные темы (не отклонённые)", ""]
        lines += [f"- {title}" for title in manifest["proposed_titles"]]

    return "\n".join(lines) + "\n"


def export_manifest_html(manifest: dict) -> str:
    """SAFE: все значения экранируются — страница только читает данные."""
    from html import escape

    json_text = json.dumps(manifest, ensure_ascii=False, indent=1)

    def table(headers: list[str], rows: list[list], numeric: set[int] | None = None) -> str:
        numeric = numeric or set()
        head = "".join(f"<th>{escape(h)}</th>" for h in headers)
        body = []
        for row in rows:
            cells = []
            for i, cell in enumerate(row):
                style = ' style="text-align:right"' if i in numeric else ""
                cells.append(f"<td{style}>{escape('' if cell is None else str(cell))}</td>")
            body.append("<tr>" + "".join(cells) + "</tr>")
        return f"<table><thead><tr>{head}</tr></thead><tbody>{''.join(body)}</tbody></table>"

    cards = "".join(
        f'<div class="card"><div class="num">{v}</div><div class="lbl">{escape(k)}</div></div>'
        for k, v in manifest["counts"].items()
    )
    topics_table = table(
        ["id", "title", "slug", "cluster", "status"],
        [[t["id"], t["title"], t["slug"], t["cluster"], t["status"]] for t in manifest["topics"]],
        numeric={0},
    )
    clusters_table = table(
        ["Кластер", "Ключевые слова", "Тип"],
        [[c["name"], c["keywords"], c["type"]] for c in manifest["clusters"]],
    )
    urls_table = table(
        ["URL", "Домен", "Заголовок"],
        [[u["url"], u["domain"], u["title"]] for u in manifest["collected_urls"]],
    )
    return f"""<!DOCTYPE html>
<html lang="ru"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>База «Точка Плавления» — что уже есть</title>
<style>
  body{{font-family:-apple-system,Segoe UI,Roboto,sans-serif;margin:0;padding:24px;background:#F6F8F7;color:#0F0E12}}
  h1{{font-size:20px;margin:0 0 4px}} .sub{{color:#737373;font-size:12px;margin-bottom:18px}}
  .cards{{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:20px}}
  .card{{background:#fff;border:1.5px solid #000;padding:10px 14px;min-width:110px}}
  .num{{font-size:22px;font-weight:800}} .lbl{{font-size:11px;color:#737373}}
  h2{{font-size:14px;margin:22px 0 8px}}
  table{{border-collapse:collapse;width:100%;background:#fff;font-size:12px}}
  th,td{{border:1px solid #E5E5E5;padding:5px 8px;text-align:left;vertical-align:top}}
  th{{background:#FFC700}}
  code{{font-family:Consolas,monospace}}
  .btns{{margin:14px 0}}
  button{{padding:7px 12px;border:1.5px solid #000;background:#FFC700;font-weight:700;cursor:pointer;margin-right:8px}}
</style></head><body>
<h1>Что уже есть в базе</h1>
<div class="sub">Сгенерировано: {escape(manifest['generated_at'])} · схема {escape(manifest['schema'])}</div>
<div class="btns">
  <button onclick="navigator.clipboard.writeText(document.getElementById('json').textContent)">📋 Скопировать JSON</button>
  <a href="/api/export.json"><button>⬇️ Скачать JSON</button></a>
  <a href="/api/export.md"><button>⬇️ Скачать Markdown</button></a>
</div>
<div class="cards">{cards}</div>
<h2>Статьи ({len(manifest['topics'])})</h2>{topics_table}
<h2>Кластеры ({len(manifest['clusters'])})</h2>{clusters_table}
<h2>Домены конкурентов ({len(manifest['competitor_domains'])})</h2>
<p>{escape(', '.join(manifest['competitor_domains']) or '—')}</p>
<h2>Собранные URL ({len(manifest['collected_urls'])})</h2>{urls_table}
<h2>JSON для нейронки</h2>
<pre id="json" style="background:#0F0E12;color:#E5E5E5;padding:14px;overflow:auto;font-size:11px;max-height:420px">{escape(json_text)}</pre>
</body></html>"""


DEFAULT_VOICES = (
    (
        "practico",
        "Голос 1 — Practico (инженер)",
        "claude-sonnet-5",
    ),
    (
        "practico2",
        "Голос 2 — Practico 2 (заказчик)",
        "claude-sonnet-5",
    ),
)


def _default_voice_prompt(voice_id: str) -> str:
    """Текст инструкции по умолчанию — из writer_practico (единый источник)."""
    try:
        from writer_practico import PRACTICO2_SYSTEM_PROMPT, PRACTICO_SYSTEM_PROMPT

        return PRACTICO2_SYSTEM_PROMPT if voice_id == "practico2" else PRACTICO_SYSTEM_PROMPT
    except Exception:  # noqa: BLE001
        return ""


def migrate_schema_v4() -> None:
    """Миграция v4: таблица голосов автора (редактируемые инструкции + модель)."""
    with get_connection() as conn:
        conn.execute(
            """
            CREATE TABLE IF NOT EXISTS voices (
                id TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                system_prompt TEXT NOT NULL DEFAULT '',
                model TEXT,
                is_active INTEGER DEFAULT 1,
                updated_at TEXT
            )
            """
        )
        conn.commit()
        existing = {r[0] for r in conn.execute("SELECT id FROM voices").fetchall()}
        now = datetime.now().isoformat(timespec="seconds")
        for voice_id, name, model in DEFAULT_VOICES:
            if voice_id in existing:
                continue
            prompt = _default_voice_prompt(voice_id)
            if not prompt:
                continue
            conn.execute(
                "INSERT INTO voices (id, name, system_prompt, model, is_active, updated_at) "
                "VALUES (?, ?, ?, ?, 1, ?)",
                (voice_id, name, prompt, model, now),
            )
        conn.commit()


def list_voices() -> list[dict]:
    """Активные голоса автора: id, name, system_prompt, model."""
    with get_connection() as conn:
        rows = conn.execute(
            "SELECT id, name, system_prompt, model, is_active, updated_at "
            "FROM voices WHERE is_active = 1 ORDER BY rowid ASC"
        ).fetchall()
    return [dict(r) for r in rows]


def build_db_plan() -> dict:
    """Собирает полный план проекта напрямую из SQLite seo.db.
    
    Полностью заменяет Excel build_plan(). Возвращает идентичную структуру данных:
    - clusters: dict[name -> dict]
    - topics: dict[id_str -> dict]
    - links: list[dict]
    - competitors: list[dict]
    - raw_data: list[dict]
    - proposed_topics: list[dict]
    - proposed_competitors: list[dict]
    - counts: dict
    """
    with get_connection() as conn:
        clusters_rows = conn.execute("SELECT * FROM clusters ORDER BY priority_rank ASC, name ASC").fetchall()
        topics_rows = conn.execute("SELECT * FROM topics ORDER BY priority_score DESC, id ASC").fetchall()
        links_rows = conn.execute("SELECT * FROM links").fetchall()
        competitors_rows = conn.execute("SELECT * FROM competitors ORDER BY score DESC").fetchall()
        raw_rows = conn.execute("SELECT * FROM raw_articles ORDER BY crawled_at DESC").fetchall()
        prop_topics_rows = conn.execute("SELECT * FROM topics_proposed ORDER BY priority_score DESC").fetchall()
        prop_comps_rows = conn.execute("SELECT * FROM competitors_proposed ORDER BY hits DESC").fetchall()
        briefs_rows = conn.execute("SELECT * FROM briefs").fetchall()

    briefs_map = {str(b["topic_id"]): dict(b) for b in briefs_rows}
    clusters = {c["name"]: dict(c) for c in clusters_rows}

    topics = {}
    for t in topics_rows:
        tid = str(t["id"])
        c_name = t["cluster"]
        cluster_info = clusters.get(c_name, {})
        has_text = bool(t["content_markdown"]) and (t["chars"] or 0) > 400
        has_brief = tid in briefs_map

        topics[tid] = {
            "id": tid,
            "title": t["title"],
            "slug": t["slug"],
            "cluster": c_name,
            "cluster_priority": cluster_info.get("priority_rank", 2),
            "keywords": cluster_info.get("keywords", ""),
            "type": t["type"] or "гайд",
            "priority": t["priority"] or "средний",
            "priority_rank": 1 if (t["priority"] or "").lower() in ("высокий", "hot") else 2,
            "status": t["status"] or "backlog",
            "status_raw": t["status"] or "backlog",
            "bot": t["bot"] or "",
            "next_action": t["next_action"] or "",
            "has_brief": has_brief,
            "has_text": has_text,
            "brief": briefs_map.get(tid),
            "text": {"chars": t["chars"] or 0, "text": t["content_markdown"] or ""},
            "outbound_planned": [],
            "inbound_planned": [],
            "score": t["score_total"],
            "priority_score": t["priority_score"],
        }

    links = []
    for l in links_rows:
        sid = str(l["source_id"])
        tid = str(l["target_id"])
        link_dict = {
            "source": sid,
            "target": tid,
            "anchor": l["anchor"] or "",
            "status": l["status"] or "planned",
        }
        links.append(link_dict)
        if sid in topics:
            dst_t = topics.get(tid)
            topics[sid]["outbound_planned"].append({
                "id": tid,
                "slug": dst_t["slug"] if dst_t else f"article-{tid}",
                "anchor": l["anchor"] or "",
                "written": dst_t["has_text"] if dst_t else False,
            })
        if tid in topics:
            src_t = topics.get(sid)
            topics[tid]["inbound_planned"].append({
                "id": sid,
                "slug": src_t["slug"] if src_t else f"article-{sid}",
                "anchor": l["anchor"] or "",
                "written": src_t["has_text"] if src_t else False,
            })

    competitors = [
        {
            "domain": c["domain"],
            "niche": c["niche"],
            "type": c["type"],
            "blog": c["blog_url"],
            "articles": c["articles_count"],
            "features": c["features"],
            "keywords": c["seo_keywords"],
            "score": c["score"],
            "notes": c["notes"],
        }
        for c in competitors_rows
    ]

    raw_data = [
        {
            "url": r["url"],
            "source": r["domain"],
            "title": r["title"],
            "h1": r["h1"],
            "date": r["date"],
            "content_type": r["content_type"],
            "cluster": r["cluster"],
            "status": r["status"],
        }
        for r in raw_rows
    ]

    return {
        "generated_at": datetime.now().isoformat(timespec="seconds"),
        "master": "SQLite seo/data/seo.db",
        "clusters": clusters,
        "topics": topics,
        "links": links,
        "competitors": competitors,
        "raw_data": raw_data,
        "proposed_topics": [dict(r) for r in prop_topics_rows],
        "proposed_competitors": [dict(r) for r in prop_comps_rows],
        "duplicates": [],
        "counts": {
            "topics": len(topics),
            "clusters": len(clusters),
            "links": len(links),
            "written": sum(1 for t in topics.values() if t["has_text"]),
            "with_brief": sum(1 for t in topics.values() if t["has_brief"]),
            "raw_data": len(raw_data),
            "scored_priority": sum(1 for t in topics.values() if t["priority_score"] is not None),
            "proposed_topics": len(prop_topics_rows),
            "proposed_competitors": len(prop_comps_rows),
        },
    }


if __name__ == "__main__":
    init_db()
    migrate_schema_v2()
    migrate_schema_v3()
    migrate_schema_v4()
    migrate_schema_v5()
    p = build_db_plan()
    print("✓ План собран из SQLite! Тем:", p["counts"]["topics"], "| Ссылок:", p["counts"]["links"])
