"""Внести готовую статью в мастер-таблицу.

Текст пишется отдельно (Markdown), затем:
    python seo/scripts/add_article.py --file "сами статьи/15-pripoj-dlya-pajki-vidy.md" --id 15

Скрипт делает бэкап мастера, пишет текст в лист `statiy`, обновляет `content_plan`.
Excel должен быть закрыт.
"""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

from datetime import datetime
from db import get_connection, backup_database
from excel_io import STATUSES

try:
    from score_article import blocks as score_blocks
    from score_article import to_markdown
except Exception:  # pragma: no cover
    score_blocks = None


def main() -> int:
    parser = argparse.ArgumentParser(description="Добавить готовую статью в базу SQLite")
    parser.add_argument("--file", required=True, help="путь к Markdown-файлу статьи")
    parser.add_argument("--id", required=True, help="ID темы")
    parser.add_argument("--status", default="written", choices=list(STATUSES))
    parser.add_argument("--title", default="")
    parser.add_argument("--slug", default="")
    parser.add_argument("--cluster", default="")
    parser.add_argument("--no-score", action="store_true", help="не считать оценку после записи")
    args = parser.parse_args()

    path = Path(args.file)
    if not path.exists():
        raise SystemExit(f"Файл не найден: {path}")
    text = path.read_text(encoding="utf-8", errors="replace")
    if len(text) < 1000:
        print(f"ВНИМАНИЕ: текст короче 1000 знаков ({len(text)}), проверьте файл")

    tid = int(args.id) if str(args.id).isdigit() else args.id
    now_str = datetime.now().isoformat(timespec="seconds")

    # Сохраняем в SQLite БД (Единый первоисточник)
    with get_connection() as conn:
        row = conn.execute("SELECT * FROM topics WHERE id = ?", (tid,)).fetchone()
        title = args.title or (row["title"] if row else f"Статья #{tid}")
        slug = args.slug or (row["slug"] if row else f"article-{tid}")
        cluster = args.cluster or (row["cluster"] if row else "Материалы")

        conn.execute("""
            UPDATE topics
            SET content_markdown = ?, chars = ?, status = ?, updated_at = ?
            WHERE id = ?
        """, (text, len(text), args.status, now_str, tid))
        conn.commit()

    print(f"✓ Записано в SQLite (seo.db): ID #{tid}, статус «{STATUSES.get(args.status, args.status)}», {len(text)} знаков.")

    # Авто-бэкап базы данных
    bk = backup_database()
    print(f"✓ Авто-бэкап БД создан: {bk['filename']}")

    if not args.no_score and score_blocks:
        report = score_blocks(text, plan, args.id)
        out = Path(__file__).resolve().parents[1] / "out" / "scores"
        out.mkdir(parents=True, exist_ok=True)
        (out / f"{args.id}.md").write_text(to_markdown(report), encoding="utf-8")
        print(f"Оценка: {report['total']}/100 — {report['verdict']} (см. seo/out/scores/{args.id}.md)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
