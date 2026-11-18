"""Синхронизация всех существующих текстов статей в SQLite seo/data/seo.db.

Собирает тексты из всех источников проекта:
1. Папка `сами статьи/*.md` (статьи #15, #16, #17, #52)
2. Папка `seo/data/article_*.md` (статьи #1, #2, #3, #4, #6)
3. Лист `statiy` мастер-книги Excel (если есть тексты)

Сохраняет всё в таблицу `topics` (колонки `content_markdown`, `chars`, `has_text`).
"""
from __future__ import annotations

import re
import sys
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SCRIPTS = ROOT / "seo" / "scripts"
sys.path.insert(0, str(SCRIPTS))

from db import backup_database, get_connection


def sync_texts():
    backup_database()
    loaded = {}

    # 1. Из папки 'сами статьи'
    articles_dir = ROOT / "сами статьи"
    if articles_dir.exists():
        for mf in articles_dir.glob("*.md"):
            m = re.match(r"^(\d+)-", mf.name)
            if m:
                tid = int(m.group(1))
                text = mf.read_text(encoding="utf-8", errors="replace")
                if len(text) > 100:
                    loaded[tid] = (text, f"сами статьи/{mf.name}")

    # 2. Из папки 'seo/data/article_*.md'
    seo_data = ROOT / "seo" / "data"
    if seo_data.exists():
        for mf in seo_data.glob("article_*.md"):
            m = re.match(r"^article_(\d+)\.md$", mf.name)
            if m:
                tid = int(m.group(1))
                text = mf.read_text(encoding="utf-8", errors="replace")
                if len(text) > 100 and tid not in loaded:
                    loaded[tid] = (text, f"seo/data/{mf.name}")

    # Копируем тексты из seo/data в 'сами статьи' для единообразия
    articles_dir.mkdir(parents=True, exist_ok=True)
    slug_map = {
        1: "kak-payat-payalnikom-poshagovyj-gajd-dlya-nachinayushchih",
        2: "s-chego-nachat-payku-startovyj-nabor",
        3: "payka-provodov-kak-pravilno-soedinit-i-izolirovat",
        4: "kak-nauchitsya-payat-bystro-i-bez-braka",
        6: "kakoj-payalnik-vybrat-dlya-doma",
    }
    for tid in [1, 2, 3, 4, 6]:
        src = seo_data / f"article_{tid}.md"
        slug = slug_map.get(tid, f"article-{tid}")
        dst = articles_dir / f"{tid}-{slug}.md"
        if src.exists() and not dst.exists():
            dst.write_text(src.read_text(encoding="utf-8"), encoding="utf-8")
            print(f"  • Создан файл: сами статьи/{dst.name}")
            loaded[tid] = (dst.read_text(encoding="utf-8"), f"сами статьи/{dst.name}")

    now_str = datetime.now().isoformat(timespec="seconds")
    updated_count = 0

    with get_connection() as conn:
        for tid, (text, src_path) in sorted(loaded.items()):
            row = conn.execute("SELECT id, title, chars FROM topics WHERE id = ?", (tid,)).fetchone()
            if row:
                chars = len(text.strip())
                status = "written" if chars > 3000 else "writing"
                conn.execute("""
                    UPDATE topics
                    SET content_markdown = ?, chars = ?, status = ?, updated_at = ?
                    WHERE id = ?
                """, (text, chars, status, now_str, tid))
                print(f"✓ Статья #{tid:>2} синхронизирована в SQLite: {chars:>5} знаков (из {src_path})")
                updated_count += 1
        conn.commit()

    print(f"\nВсего обновлено статей в SQLite: {updated_count}")


if __name__ == "__main__":
    sync_texts()
