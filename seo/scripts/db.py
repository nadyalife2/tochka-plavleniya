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
            status TEXT DEFAULT 'planned'
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


if __name__ == "__main__":
    init_db()
    print("OK: БД инициализирована в", DB_PATH)
    bk = backup_database()
    print("OK: Тестовый бэкап создан:", bk["filename"])
