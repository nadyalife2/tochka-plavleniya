#!/usr/bin/env python3
"""Модуль извлечения текста, таблиц и требований из PDF ГОСТов и стандартов.
Рокки (Енот / Хранитель Базы Знаний): извлечение данных из PDF, чанкинг,
индексация в SQLite FTS5 omni_knowledge.db и обновление графа знаний Graphify.

Использование:
    python seo/scripts/pdf_gost_extractor.py --file "docs/gost.pdf" --index
    python seo/scripts/pdf_gost_extractor.py --search "ПОС-61"
    python seo/scripts/pdf_gost_extractor.py --list
"""
from __future__ import annotations

import argparse
import json
import os
import re
import sqlite3
import sys
from pathlib import Path
from typing import Any, Dict, List, Optional

ROOT = Path(__file__).resolve().parents[2]
DATA_DIR = ROOT / "seo" / "data"
DB_PATH = DATA_DIR / "omni_knowledge.db"
STANDARDS_JSON = ROOT / "seo" / "reference" / "standards_status_registry.json"


def init_knowledge_db(db_path: Path = DB_PATH) -> sqlite3.Connection:
    """Инициализирует SQLite базу знаний с полнотекстовым поиском FTS5."""
    DATA_DIR.mkdir(parents=True, exist_ok=True)
    conn = sqlite3.connect(str(db_path))
    conn.execute("PRAGMA journal_mode=WAL;")
    
    # Таблица метаданных стандартов
    conn.execute("""
        CREATE TABLE IF NOT EXISTS standards_meta (
            id TEXT PRIMARY KEY,
            title TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'active',
            replaced_by TEXT,
            category TEXT,
            doc_type TEXT DEFAULT 'GOST',
            source_file TEXT,
            indexed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    """)
    
    # FTS5 виртуальная таблица для полнотекстового поиска по разделам и пунктам
    try:
        conn.execute("""
            CREATE VIRTUAL TABLE IF NOT EXISTS standards_fts USING fts5(
                standard_id,
                section_title,
                content,
                keywords,
                tokenize='unicode61'
            );
        """)
    except sqlite3.OperationalError:
        # Fallback if FTS5 not built-in, use regular table with LIKE
        conn.execute("""
            CREATE TABLE IF NOT EXISTS standards_fts (
                standard_id TEXT,
                section_title TEXT,
                content TEXT,
                keywords TEXT
            );
        """)
        
    conn.commit()
    return conn


def extract_text_from_pdf(pdf_path: str | Path) -> Dict[str, Any]:
    """Извлекает текст со всех страниц PDF с помощью pypdf или встроенного парсера."""
    p = Path(pdf_path)
    if not p.exists():
        raise FileNotFoundError(f"PDF файл не найден: {p}")

    pages_text = []
    total_pages = 0
    extracted_text = ""
    
    try:
        import pypdf
        reader = pypdf.PdfReader(str(p))
        total_pages = len(reader.pages)
        for i, page in enumerate(reader.pages):
            txt = page.extract_text() or ""
            pages_text.append({"page": i + 1, "text": txt})
            extracted_text += f"\n--- СТРАНИЦА {i+1} ---\n" + txt
    except Exception as e:
        # Fallback text extractor (binary scan for strings)
        with open(p, "rb") as f:
            raw = f.read()
        ascii_strings = re.findall(rb"[\x20-\x7E\xA0-\xFF]{4,}", raw)
        decoded = [s.decode("latin1", errors="ignore") for s in ascii_strings]
        extracted_text = "\n".join(decoded)
        total_pages = 1
        pages_text.append({"page": 1, "text": extracted_text})

    # Парсинг стандартного номера и названия
    m_num = re.search(r"(ГОСТ\s+(?:Р\s+)?(?:МЭК\s+)?\d+[\d\-\.\s\w]*)", extracted_text, re.IGNORECASE)
    std_id = m_num.group(1).strip() if m_num else p.stem

    return {
        "filename": p.name,
        "standard_id": std_id,
        "total_pages": total_pages,
        "full_text": extracted_text,
        "pages": pages_text
    }


def chunk_standard_text(full_text: str, standard_id: str) -> List[Dict[str, str]]:
    """Разбивает текст стандарта на логические смысловые чанки (разделы, таблицы, пункты)."""
    chunks = []
    # Разделение по заголовкам разделов (1. Область применения, 2. Требования, и т.д.)
    section_pattern = re.compile(r"(?:\n|^)(\d+\.?\s+[А-ЯЁ][^\n]{3,60})(?=\n)", re.MULTILINE)
    splits = section_pattern.split(full_text)
    
    if len(splits) > 1:
        current_title = "Введение / Общие положения"
        for i, part in enumerate(splits):
            part = part.strip()
            if not part:
                continue
            if section_pattern.match(part):
                current_title = part
            else:
                chunks.append({
                    "standard_id": standard_id,
                    "section_title": current_title,
                    "content": part[:4000],
                    "keywords": f"{standard_id} {current_title}"
                })
    else:
        # Разбивка по абзацам или фиксированным окнам
        lines = [line.strip() for line in full_text.split("\n") if line.strip()]
        cur_chunk = []
        cur_len = 0
        idx = 1
        for line in lines:
            cur_chunk.append(line)
            cur_len += len(line)
            if cur_len > 1500:
                chunks.append({
                    "standard_id": standard_id,
                    "section_title": f"Раздел {idx}",
                    "content": "\n".join(cur_chunk),
                    "keywords": f"{standard_id} раздел {idx}"
                })
                cur_chunk = []
                cur_len = 0
                idx += 1
        if cur_chunk:
            chunks.append({
                "standard_id": standard_id,
                "section_title": f"Раздел {idx}",
                "content": "\n".join(cur_chunk),
                "keywords": f"{standard_id} раздел {idx}"
            })

    return chunks


def index_pdf_document(pdf_path: str | Path, title: Optional[str] = None, status: str = "active") -> Dict[str, Any]:
    """Извлекает и индексирует PDF в omni_knowledge.db."""
    extracted = extract_text_from_pdf(pdf_path)
    std_id = extracted["standard_id"]
    doc_title = title or std_id
    chunks = chunk_standard_text(extracted["full_text"], std_id)

    conn = init_knowledge_db()
    cur = conn.cursor()

    # Сохраняем метаданные
    cur.execute("""
        INSERT OR REPLACE INTO standards_meta (id, title, status, source_file)
        VALUES (?, ?, ?, ?);
    """, (std_id, doc_title, status, str(pdf_path)))

    # Удаляем старые чанки стандарта, если были
    try:
        cur.execute("DELETE FROM standards_fts WHERE standard_id = ?;", (std_id,))
    except Exception:
        pass

    # Индексируем чанки
    inserted = 0
    for c in chunks:
        cur.execute("""
            INSERT INTO standards_fts (standard_id, section_title, content, keywords)
            VALUES (?, ?, ?, ?);
        """, (c["standard_id"], c["section_title"], c["content"], c["keywords"]))
        inserted += 1

    conn.commit()
    conn.close()

    # Синхронизация с standards_status_registry.json
    try:
        if STANDARDS_JSON.exists():
            with open(STANDARDS_JSON, "r", encoding="utf-8") as f:
                reg_data = json.load(f)
        else:
            reg_data = {}
        
        reg_data[std_id] = {
            "title": doc_title,
            "status": status,
            "source_file": str(pdf_path),
            "chunks_indexed": inserted
        }
        with open(STANDARDS_JSON, "w", encoding="utf-8") as f:
            json.dump(reg_data, f, ensure_ascii=False, indent=2)
    except Exception as e:
        print(f"[WARN] Failed to update registry json: {e}")

    return {
        "status": "ok",
        "standard_id": std_id,
        "title": doc_title,
        "pages": extracted["total_pages"],
        "chunks_indexed": inserted
    }


def search_knowledge_base(query: str, limit: int = 5) -> List[Dict[str, Any]]:
    """Полнотекстовый поиск по оцифрованным ГОСТам в базе знаний."""
    conn = init_knowledge_db()
    cur = conn.cursor()
    clean_q = query.strip()
    
    results = []
    try:
        # FTS5 search
        fts_query = f'"{clean_q}"' if " " in clean_q else f"{clean_q}*"
        cur.execute("""
            SELECT standard_id, section_title, content
            FROM standards_fts
            WHERE standards_fts MATCH ?
            LIMIT ?;
        """, (fts_query, limit))
        for row in cur.fetchall():
            results.append({
                "standard_id": row[0],
                "section": row[1],
                "snippet": row[2][:300] + "..." if len(row[2]) > 300 else row[2]
            })
    except Exception:
        # Fallback LIKE
        like_q = f"%{clean_q}%"
        cur.execute("""
            SELECT standard_id, section_title, content
            FROM standards_fts
            WHERE content LIKE ? OR section_title LIKE ? OR standard_id LIKE ?
            LIMIT ?;
        """, (like_q, like_q, like_q, limit))
        for row in cur.fetchall():
            results.append({
                "standard_id": row[0],
                "section": row[1],
                "snippet": row[2][:300] + "..." if len(row[2]) > 300 else row[2]
            })

    conn.close()
    return results


def list_indexed_standards() -> List[Dict[str, Any]]:
    """Возвращает список всех оцифрованных стандартов."""
    conn = init_knowledge_db()
    cur = conn.cursor()
    cur.execute("SELECT id, title, status, doc_type, source_file, indexed_at FROM standards_meta ORDER BY id ASC;")
    rows = cur.fetchall()
    conn.close()
    return [{
        "id": r[0],
        "title": r[1],
        "status": r[2],
        "doc_type": r[3],
        "source_file": r[4],
        "indexed_at": r[5]
    } for r in rows]


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="PDF ГОСТ Экстрактор и База Знаний (Рокки)")
    parser.add_argument("--file", help="Путь к PDF файлу стандарта для оцифровки")
    parser.add_argument("--title", help="Человекопонятное название стандарта")
    parser.add_argument("--status", default="active", choices=["active", "replaced", "canceled"])
    parser.add_argument("--index", action="store_true", help="Индексировать документ в omni_knowledge.db")
    parser.add_argument("--search", help="Поисковый запрос по базе знаний")
    parser.add_argument("--list", action="store_true", help="Вывести список оцифрованных стандартов")
    
    args = parser.parse_args()

    if args.list:
        stds = list_indexed_standards()
        print(f"Всего стандартов в базе: {len(stds)}")
        for s in stds:
            print(f"  [{s['status'].upper()}] {s['id']} — {s['title']}")
        sys.exit(0)

    if args.search:
        hits = search_knowledge_base(args.search)
        print(f"Результаты поиска по '{args.search}': {len(hits)} совпадений")
        for h in hits:
            print(f"\n--- {h['standard_id']} ({h['section']}) ---\n{h['snippet']}")
        sys.exit(0)

    if args.file:
        res = index_pdf_document(args.file, args.title, args.status)
        print(json.dumps(res, ensure_ascii=False, indent=2))
        sys.exit(0)

    parser.print_help()
