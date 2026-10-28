"""Библиотека чтения/записи мастер-таблицы «Точка Плавления».

Мастер: semantic_core_FULL_v11.xlsx (единственный источник истины).
Все скрипты seo/ используют только этот модуль для доступа к Excel.

Запуск напрямую:  python seo/scripts/excel_io.py  (покажет сводку по мастеру)
"""
from __future__ import annotations

import json
import re
import shutil
from datetime import datetime
from pathlib import Path

import openpyxl

ROOT = Path(__file__).resolve().parents[2]
MASTER = ROOT / "semantic_core_FULL_v11.xlsx"
SEO = ROOT / "seo"
DATA = SEO / "data"
OUT = SEO / "out"
BACKUP = DATA / "_backup"

# ---------------------------------------------------------------- статусы ----

STATUSES = {
    "backlog": "Не начато",
    "brief": "ТЗ готовится",
    "brief_ready": "ТЗ готово",
    "writing": "Пишется",
    "written": "Написано",
    "review": "На ревью",
    "scored": "Оценено",
    "ready": "Готово к публикации",
    "published": "Опубликовано",
    "blocked": "Блокер",
}

# старые значения Excel -> канонический код
STATUS_MAP = {
    "": "backlog",
    "не начато": "backlog",
    "запустить": "brief",
    "тз готовится": "brief",
    "готов к написанию": "brief_ready",
    "тз готово": "brief_ready",
    "написание": "writing",
    "пишется": "writing",
    "черновик": "writing",
    "написано": "written",
    "ревью: текст": "review",
    "на ревью": "review",
    "поправлено прошло аудит": "review",
    "wordpress draft готов": "ready",
    "черновик доставлен": "ready",
    "готово к публикации": "ready",
    "упаковано": "ready",
    "опубликовано": "published",
    "блокировано": "blocked",
}

PRIORITY_RANK = {"высокий": 1, "средний": 2, "низкий": 3}

# ---------------------------------------------------------------- утилиты ----


def clean(value) -> str:
    """Строка без мусора: пробелы, None, 'nan'."""
    if value is None:
        return ""
    text = str(value).strip()
    return "" if text.lower() in {"none", "nan", "nat"} else text


def norm_id(value) -> str:
    """'1.0' / 1.0 / 1 -> '1'."""
    text = clean(value)
    if re.fullmatch(r"\d+\.0+", text):
        return text.split(".")[0]
    return text


def map_status(raw) -> str:
    key = clean(raw).lower()
    if key in STATUS_MAP:
        return STATUS_MAP[key]
    for needle, code in STATUS_MAP.items():
        if needle and needle in key:
            return code
    return "backlog"


def priority_rank(raw) -> int:
    key = clean(raw).lower()
    for name, rank in PRIORITY_RANK.items():
        if name in key:
            return rank
    return 2


def parse_planned_links(raw) -> list[dict]:
    """'-> #2 (/s-chego-nachat-pajku) [что купить]' -> [{id, slug, anchor}]."""
    text = clean(raw)
    if not text:
        return []
    links: list[dict] = []
    for chunk in re.split(r"(?=->)", text):
        id_match = re.search(r"#\s*(\d+)", chunk)
        slug_match = re.search(r"/\s*\(?([a-z0-9\-]+)\)?", chunk.lower())
        anchor_match = re.search(r"\[([^\]]+)\]", chunk)
        if not id_match and not slug_match:
            continue
        links.append(
            {
                "id": id_match.group(1) if id_match else "",
                "slug": slug_match.group(1) if slug_match else "",
                "anchor": anchor_match.group(1) if anchor_match else "",
            }
        )
    return links


# ---------------------------------------------------------------- чтение -----


def load_master(path: Path | None = None) -> openpyxl.Workbook:
    path = path or MASTER
    if not path.exists():
        raise SystemExit(f"Мастер не найден: {path}")
    return openpyxl.load_workbook(path, read_only=True, data_only=True)


def read_sheet(wb: openpyxl.Workbook, name: str) -> list[dict]:
    if name not in wb.sheetnames:
        return []
    ws = wb[name]
    rows = ws.iter_rows(values_only=True)
    try:
        header = next(rows)
    except StopIteration:
        return []
    index = {clean(h): i for i, h in enumerate(header) if clean(h)}
    records: list[dict] = []
    for row in rows:
        if row is None or all(v in (None, "") for v in row):
            continue
        record = {key: (row[i] if i < len(row) else None) for key, i in index.items()}
        if all(clean(v) == "" for v in record.values()):
            continue
        records.append(record)
    return records


def read_master_raw(path: Path | None = None) -> dict[str, list[dict]]:
    wb = load_master(path)
    try:
        return {name: read_sheet(wb, name) for name in wb.sheetnames}
    finally:
        wb.close()


# ------------------------------------------------------------ нормализация ---


def build_plan(path: Path | None = None) -> dict:
    """Собирает единый план: темы + брифы + тексты + связи + кластеры."""
    raw = read_master_raw(path)

    clusters: dict[str, dict] = {}
    for rec in raw.get("clusters", []):
        name = clean(rec.get("кластер"))
        if not name:
            continue
        clusters[name] = {
            "name": name,
            "description": clean(rec.get("описание")),
            "keywords": clean(rec.get("ключевые темы")),
            "planned": clean(rec.get("кол-во тем в плане")),
            "type": clean(rec.get("тип статьи")),
            "priority": clean(rec.get("приоритет")),
            "priority_rank": priority_rank(rec.get("приоритет")),
            "competitors": clean(rec.get("конкуренты")),
            "gap": clean(rec.get("пробел")),
            "status": clean(rec.get("статус")),
        }

    briefs: dict[str, dict] = {}
    for rec in raw.get("briefs_ТЗ", []):
        article_id = norm_id(rec.get("ID_темы"))
        if not article_id or article_id in briefs:
            continue
        briefs[article_id] = {
            "intent": clean(rec.get("Интент_и_Этап_Воронки")),
            "pain": clean(rec.get("Боль_и_Психология_Читателя")),
            "geo": clean(rec.get("GEO_Сниппет_Шаблон")),
            "eeat": clean(rec.get("E-E-A-T_Фактура")),
            "feature": clean(rec.get("Внедряемая_Фишечка")),
            "lsi": clean(rec.get("LSI_и_Сущности")),
            "links": clean(rec.get("Перелинковка_Исходящая")),
            "cta": clean(rec.get("Призыв_к_Диалогу")),
            "status": clean(rec.get("Статус_ТЗ")),
            "facts_ok": clean(rec.get("Факты_подтверждены")),
            "usefulness": clean(rec.get("Оценка_пользы_0_100")),
            "gap": clean(rec.get("Контентный_пробел")),
        }

    texts: dict[str, dict] = {}
    for rec in raw.get("statiy", []):
        article_id = norm_id(rec.get("ID_темы"))
        if not article_id:
            continue
        text = clean(rec.get("Текст_статьи"))
        texts[article_id] = {
            "title": clean(rec.get("Заголовок")),
            "slug": clean(rec.get("Slug")),
            "cluster": clean(rec.get("Кластер")),
            "chars": clean(rec.get("Кол-во_символов")) or str(len(text)),
            "status_raw": clean(rec.get("Статус")),
            "status": map_status(rec.get("Статус")),
            "has_text": bool(text),
        }

    links: list[dict] = []
    for rec in raw.get("interlinking_map", []):
        source = norm_id(rec.get("ID_источника"))
        target = norm_id(rec.get("ID_цели"))
        if not source or not target:
            continue
        links.append(
            {
                "source": source,
                "source_slug": clean(rec.get("slug_источника")),
                "target": target,
                "target_slug": clean(rec.get("slug_цели")),
                "anchor": clean(rec.get("анкорный_текст")),
                "type": clean(rec.get("тип_связи")),
            }
        )

    priority: dict[str, dict] = {}
    for rec in raw.get("priority_scores", []):
        article_id = norm_id(rec.get("article_id"))
        if not article_id:
            continue
        try:
            total = int(float(clean(rec.get("score"))))
        except (TypeError, ValueError):
            total = None
        priority[article_id] = {
            "score": total,
            "cluster_score": clean(rec.get("cluster_score")),
            "gap_score": clean(rec.get("gap_score")),
            "coverage_score": clean(rec.get("coverage_score")),
            "links_score": clean(rec.get("links_score")),
            "readiness_score": clean(rec.get("readiness_score")),
            "keywords_score": clean(rec.get("keywords_score")),
            "notes": clean(rec.get("notes")),
        }

    raw_data_rows = [
        {
            "url": clean(rec.get("url")),
            "title": clean(rec.get("title")),
            "source": clean(rec.get("source")),
            "content_type": clean(rec.get("content_type")),
            "cluster": clean(rec.get("cluster")),
            "priority": clean(rec.get("priority")),
        }
        for rec in raw.get("raw_data", [])
        if clean(rec.get("url"))
    ]

    competitors = [
        {
            "domain": clean(rec.get("домен")),
            "niche": clean(rec.get("ниша")),
            "type": clean(rec.get("тип")),
            "blog": clean(rec.get("раздел блога")),
            "articles": clean(rec.get("кол-во статей (собрано)")),
            "notes": clean(rec.get("заметки")),
        }
        for rec in raw.get("competitors", [])
        if clean(rec.get("домен"))
    ]

    topics: dict[str, dict] = {}
    seen_slugs: dict[str, str] = {}
    duplicates: list[dict] = []
    for rec in raw.get("content_plan", []):
        article_id = norm_id(rec.get("article_id"))
        if not article_id:
            continue
        slug = clean(rec.get("slug"))
        cluster_name = clean(rec.get("кластер"))
        cluster = clusters.get(cluster_name, {})
        topic = {
            "id": article_id,
            "title": clean(rec.get("рабочий заголовок")),
            "slug": slug,
            "cluster": cluster_name,
            "cluster_priority": cluster.get("priority_rank", 2),
            "keywords": cluster.get("keywords", ""),
            "type": clean(rec.get("тип статьи")),
            "priority": clean(rec.get("приоритет")),
            "priority_rank": priority_rank(rec.get("приоритет")),
            "status": map_status(rec.get("статус")),
            "status_raw": clean(rec.get("статус")),
            "planned_links": parse_planned_links(rec.get("перелинковка (исходящие ссылки и анкоры)")),
            "bot": clean(rec.get("Ответственный_бот")),
            "next_action": clean(rec.get("Следующее_действие")),
            "has_brief": article_id in briefs,
            "has_text": article_id in texts and texts[article_id]["has_text"],
            "brief": briefs.get(article_id),
            "text": texts.get(article_id),
            "outbound_planned": [],
            "inbound_planned": [],
            "score": None,
            "priority_score": (priority.get(article_id) or {}).get("score"),
            "priority_detail": priority.get(article_id),
        }
        if slug:
            if slug in seen_slugs:
                duplicates.append({"slug": slug, "ids": [seen_slugs[slug], article_id]})
            else:
                seen_slugs[slug] = article_id
        topics[article_id] = topic

    for link in links:
        src = topics.get(link["source"])
        dst = topics.get(link["target"])
        if src:
            src["outbound_planned"].append({"id": link["target"], "slug": link["target_slug"], "anchor": link["anchor"]})
        if dst:
            dst["inbound_planned"].append({"id": link["source"], "slug": link["source_slug"], "anchor": link["anchor"]})

    return {
        "generated_at": datetime.now().isoformat(timespec="seconds"),
        "master": str(path or MASTER),
        "clusters": clusters,
        "topics": topics,
        "links": links,
        "competitors": competitors,
        "priority": priority,
        "raw_data": raw_data_rows,
        "proposed_topics": [r for r in raw.get("topics_proposed", []) if clean(r.get("рабочий заголовок"))],
        "proposed_competitors": [r for r in raw.get("competitors_proposed", []) if clean(r.get("домен"))],
        "duplicates": duplicates,
        "counts": {
            "topics": len(topics),
            "clusters": len(clusters),
            "links": len(links),
            "written": sum(1 for t in topics.values() if t["has_text"]),
            "with_brief": sum(1 for t in topics.values() if t["has_brief"]),
            "raw_data": len(raw_data_rows),
            "scored_priority": sum(1 for p in priority.values() if p["score"] is not None),
            "proposed_topics": len(raw.get("topics_proposed", [])),
            "proposed_competitors": len(raw.get("competitors_proposed", [])),
        },
    }


# ---------------------------------------------------------------- запись -----


def backup_master() -> Path:
    BACKUP.mkdir(parents=True, exist_ok=True)
    stamp = datetime.now().strftime("%Y-%m-%d_%H-%M-%S")
    target = BACKUP / f"semantic_core_{stamp}.xlsx"
    shutil.copy2(MASTER, target)
    return target


def _find_row(ws, column: int, value: str) -> int | None:
    for row in range(2, ws.max_row + 1):
        if norm_id(ws.cell(row=row, column=column).value) == value:
            return row
    return None


def save_article(
    article_id: str,
    text: str,
    title: str = "",
    slug: str = "",
    cluster: str = "",
    status: str = "written",
) -> dict:
    """Записывает текст в лист statiy и обновляет статус в content_plan.

    Делает резервную копию мастера перед записью.
    """
    backup = backup_master()
    wb = openpyxl.load_workbook(MASTER)
    try:
        ws_posts = wb["statiy"]
        row = _find_row(ws_posts, 1, article_id)
        if row is None:
            row = ws_posts.max_row + 1
            ws_posts.cell(row=row, column=1, value=int(article_id) if article_id.isdigit() else article_id)
        ws_posts.cell(row=row, column=2, value=title or ws_posts.cell(row=row, column=2).value)
        ws_posts.cell(row=row, column=3, value=slug or ws_posts.cell(row=row, column=3).value)
        ws_posts.cell(row=row, column=4, value=cluster or ws_posts.cell(row=row, column=4).value)
        ws_posts.cell(row=row, column=5, value=text)
        ws_posts.cell(row=row, column=6, value=len(text))
        ws_posts.cell(row=row, column=7, value=STATUSES.get(status, status))
        ws_posts.cell(row=row, column=8, value=datetime.now().strftime("%Y-%m-%d"))

        ws_plan = wb["content_plan"]
        plan_row = _find_row(ws_plan, 1, article_id)
        if plan_row:
            ws_plan.cell(row=plan_row, column=7, value=STATUSES.get(status, status))

        wb.save(MASTER)
        return {"article_id": article_id, "row": row, "status": status, "backup": str(backup), "chars": len(text)}
    finally:
        wb.close()


# --------------------------------------------------- запись разведки ---------

COMPETITOR_COLS = ["домен", "ниша", "тип", "раздел блога",
                   "кол-во статей (собрано)", "заметки", "дата_обновления"]

RAW_COLS = ["url", "title", "h1", "date", "source", "content_type",
            "cluster", "priority", "status"]

PRIORITY_COLS = ["article_id", "score", "cluster_score", "gap_score",
                 "coverage_score", "links_score", "readiness_score",
                 "keywords_score", "updated_at", "notes"]

PRIORITY_SHEET = "priority_scores"
PRIORITY_COLUMN = 12          # content_plan: сразу после «test» (11)
PRIORITY_HEADER = "Оценка_приоритета_0_100"

# ------------------------------------ листы-предложения (на утверждение) ----

PROPOSED_COMPETITORS_SHEET = "competitors_proposed"
PROPOSED_COMPETITORS_COLS = [
    "домен", "ниша", "тип", "источник_находки", "запросы", "сколько_раз_в_выдаче",
    "найдено_статей", "пример_url", "статус", "дата_обновления",
]

PROPOSED_TOPICS_SHEET = "topics_proposed"
PROPOSED_TOPICS_COLS = [
    "предложение_id", "рабочий заголовок", "кластер", "тип статьи", "источник",
    "доказательство", "частота_в_выдаче", "оценка_приоритета", "статус",
    "дата_обновления",
]

PROPOSAL_STATUSES = {"предложен", "принят", "отклонён", "в работе"}


def _sheet_headers(ws) -> dict[str, int]:
    return {clean(c.value): i for i, c in enumerate(ws[1], 1) if clean(c.value)}


def _ensure_columns(ws, wanted: list[str]) -> dict[str, int]:
    """Дописывает недостающие колонки справа и возвращает карту имя→номер."""
    headers = _sheet_headers(ws)
    for name in wanted:
        if name not in headers:
            col = (max(headers.values()) if headers else 0) + 1
            ws.cell(row=1, column=col, value=name)
            headers[name] = col
    return headers


def _ensure_sheet(wb, name: str, cols: list[str]):
    if name in wb.sheetnames:
        ws = wb[name]
        _ensure_columns(ws, cols)
        return ws
    ws = wb.create_sheet(name)
    for i, col in enumerate(cols, 1):
        ws.cell(row=1, column=i, value=col)
    return ws


def _upsert(ws, key_col: str, key_value: str, values: dict) -> str:
    """Ищет строку по ключу; возвращает 'updated' или 'created'."""
    headers = _sheet_headers(ws)
    key_idx = headers[key_col]
    target = None
    for row in range(2, ws.max_row + 1):
        if clean(ws.cell(row=row, column=key_idx).value).lower() == key_value.lower():
            target = row
            break
    if target is None:
        target = ws.max_row + 1
    for name, value in values.items():
        col = headers.get(name)
        if col:
            ws.cell(row=target, column=col, value=value)
    return "updated" if target <= ws.max_row else "created"


def save_competitors(records: list[dict]) -> dict:
    """Обновляет лист `competitors`. Ключ — домен."""
    backup = backup_master()
    wb = openpyxl.load_workbook(MASTER)
    created = updated = 0
    try:
        ws = wb["competitors"]
        _ensure_columns(ws, COMPETITOR_COLS)
        stamp = datetime.now().strftime("%Y-%m-%d %H:%M")
        for rec in records:
            domain = clean(rec.get("домен"))
            if not domain:
                continue
            values = {k: rec[k] for k in COMPETITOR_COLS if k in rec and k != "домен"}
            values["домен"] = domain
            values.setdefault("дата_обновления", stamp)
            action = _upsert(ws, "домен", domain, values)
            if action == "created":
                created += 1
            else:
                updated += 1
        wb.save(MASTER)
        return {"created": created, "updated": updated, "backup": str(backup)}
    finally:
        wb.close()


def save_raw_data(records: list[dict], update_competitor_counts: bool = True) -> dict:
    """Добавляет найденные URL в лист `raw_data`. Ключ — url, дубли пропускаются."""
    backup = backup_master()
    wb = openpyxl.load_workbook(MASTER)
    added = skipped = 0
    per_source: dict[str, int] = {}
    try:
        ws = wb["raw_data"]
        _ensure_columns(ws, RAW_COLS)
        headers = _sheet_headers(ws)
        url_col = headers["url"]
        known = {
            clean(ws.cell(row=r, column=url_col).value).rstrip("/")
            for r in range(2, ws.max_row + 1)
        }
        next_row = ws.max_row + 1
        for rec in records:
            url = clean(rec.get("url"))
            if not url or url.rstrip("/") in known:
                skipped += 1
                continue
            for name in RAW_COLS:
                col = headers.get(name)
                if col:
                    ws.cell(row=next_row, column=col, value=rec.get(name, ""))
            known.add(url.rstrip("/"))
            next_row += 1
            added += 1
            per_source[clean(rec.get("source"))] = per_source.get(clean(rec.get("source")), 0) + 1

        if update_competitor_counts and per_source:
            wc = wb["competitors"]
            ch = _sheet_headers(wc)
            domain_col = ch.get("домен")
            count_col = ch.get("кол-во статей (собрано)")
            if domain_col and count_col:
                for row in range(2, wc.max_row + 1):
                    domain = clean(wc.cell(row=row, column=domain_col).value).lower()
                    if domain in per_source:
                        old = wc.cell(row=row, column=count_col).value
                        old_n = int(old) if isinstance(old, (int, float)) else 0
                        wc.cell(row=row, column=count_col, value=old_n + per_source[domain])

        wb.save(MASTER)
        return {"added": added, "skipped": skipped, "by_source": per_source, "backup": str(backup)}
    finally:
        wb.close()


def save_priority_scores(scores: list[dict], write_plan_column: bool = True) -> dict:
    """Пишет скоры приоритетности в лист `priority_scores` и колонку content_plan."""
    backup = backup_master()
    wb = openpyxl.load_workbook(MASTER)
    try:
        ws = _ensure_sheet(wb, PRIORITY_SHEET, PRIORITY_COLS)
        headers = _sheet_headers(ws)
        stamp = datetime.now().strftime("%Y-%m-%d %H:%M")
        for rec in scores:
            values = {k: rec.get(k, "") for k in PRIORITY_COLS}
            values["article_id"] = str(rec.get("article_id", ""))
            values.setdefault("updated_at", stamp)
            _upsert(ws, "article_id", values["article_id"], values)

        if write_plan_column:
            wp = wb["content_plan"]
            hdr = _sheet_headers(wp)
            id_col = hdr.get("article_id", 1)
            score_col = hdr.get(PRIORITY_HEADER) or PRIORITY_COLUMN
            wp.cell(row=1, column=score_col, value=PRIORITY_HEADER)
            by_id = {str(s["article_id"]): s["score"] for s in scores}
            for row in range(2, wp.max_row + 1):
                aid = norm_id(wp.cell(row=row, column=id_col).value)
                if aid in by_id:
                    wp.cell(row=row, column=score_col, value=by_id[aid])

        wb.save(MASTER)
        return {"written": len(scores), "sheet": PRIORITY_SHEET, "backup": str(backup)}
    finally:
        wb.close()


def save_competitor_proposals(records: list[dict]) -> dict:
    """Пишет найденные домены в лист `competitors_proposed` (на утверждение).

    Лист `competitors` не трогается: домен попадает туда только после того,
    как вы поставите в колонке «статус» значение «принят» и запустите
    `python seo/scripts/discover_competitors.py --promote`.
    """
    backup = backup_master()
    wb = openpyxl.load_workbook(MASTER)
    created = updated = 0
    try:
        ws = _ensure_sheet(wb, PROPOSED_COMPETITORS_SHEET, PROPOSED_COMPETITORS_COLS)
        stamp = datetime.now().strftime("%Y-%m-%d %H:%M")
        for rec in records:
            domain = clean(rec.get("домен"))
            if not domain:
                continue
            values = {k: rec.get(k, "") for k in PROPOSED_COMPETITORS_COLS}
            values["домен"] = domain
            values.setdefault("статус", "предложен")
            values.setdefault("дата_обновления", stamp)
            action = _upsert(ws, "домен", domain, values)
            if action == "created":
                created += 1
            else:
                updated += 1
        wb.save(MASTER)
        return {"created": created, "updated": updated, "backup": str(backup)}
    finally:
        wb.close()


def save_topic_proposals(records: list[dict]) -> dict:
    """Пишет предложенные темы в лист `topics_proposed` (на утверждение).

    В `content_plan` тема попадает только после того, как вы поставите
    статус «принят» и запустите `topics_from_gaps.py --promote`.
    """
    backup = backup_master()
    wb = openpyxl.load_workbook(MASTER)
    created = updated = 0
    try:
        ws = _ensure_sheet(wb, PROPOSED_TOPICS_SHEET, PROPOSED_TOPICS_COLS)
        stamp = datetime.now().strftime("%Y-%m-%d %H:%M")
        for rec in records:
            title = clean(rec.get("рабочий заголовок"))
            if not title:
                continue
            key = clean(rec.get("предложение_id")) or title
            values = {k: rec.get(k, "") for k in PROPOSED_TOPICS_COLS}
            values["предложение_id"] = key
            values.setdefault("статус", "предложен")
            values.setdefault("дата_обновления", stamp)
            action = _upsert(ws, "предложение_id", key, values)
            if action == "created":
                created += 1
            else:
                updated += 1
        wb.save(MASTER)
        return {"created": created, "updated": updated, "backup": str(backup)}
    finally:
        wb.close()


def approved_proposals(kind: str) -> list[dict]:
    """Читает предложения со статусом «принят» из нужного листа."""
    sheet = PROPOSED_COMPETITORS_SHEET if kind == "competitors" else PROPOSED_TOPICS_SHEET
    wb = load_master()
    try:
        rows = read_sheet(wb, sheet)
    finally:
        wb.close()
    accepted = {"принят", "принято", "в работе"}
    return [r for r in rows if clean(r.get("статус")).lower() in accepted]


def read_research_cache() -> dict:
    """Кэш разведки без чтения Excel: конкурент → сколько URL собрано."""
    cache = DATA / "research.json"
    if not cache.exists():
        return {}
    try:
        return json.loads(cache.read_text(encoding="utf-8"))
    except Exception:
        return {}


if __name__ == "__main__":
    plan = build_plan()
    print("Мастер:", plan["master"])
    print("Тем:", plan["counts"]["topics"], "| кластеров:", plan["counts"]["clusters"],
          "| связей:", plan["counts"]["links"], "| текстов:", plan["counts"]["written"],
          "| брифов:", plan["counts"]["with_brief"])
    if plan["duplicates"]:
        print("Дубли slug:", plan["duplicates"])
