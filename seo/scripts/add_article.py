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

from excel_io import STATUSES, build_plan, save_article

try:
    from score_article import blocks as score_blocks
    from score_article import to_markdown
except Exception:  # pragma: no cover
    score_blocks = None


def main() -> int:
    parser = argparse.ArgumentParser(description="Добавить статью в мастер-таблицу")
    parser.add_argument("--file", required=True, help="путь к Markdown-файлу статьи")
    parser.add_argument("--id", required=True, help="ID темы (content_plan)")
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

    plan = build_plan()
    topic = plan["topics"].get(str(args.id))
    title = args.title or (topic["title"] if topic else "")
    slug = args.slug or (topic["slug"] if topic else "")
    cluster = args.cluster or (topic["cluster"] if topic else "")

    try:
        result = save_article(args.id, text, title, slug, cluster, args.status)
    except PermissionError:
        raise SystemExit("Excel открыт — закройте semantic_core_FULL_v11.xlsx и повторите.")

    print(f"Записано: ID {result['article_id']}, статус «{STATUSES.get(result['status'])}», "
          f"{result['chars']} знаков, строка {result['row']}")
    print(f"Бэкап: {result['backup']}")

    if not args.no_score and score_blocks:
        report = score_blocks(text, plan, args.id)
        out = Path(__file__).resolve().parents[1] / "out" / "scores"
        out.mkdir(parents=True, exist_ok=True)
        (out / f"{args.id}.md").write_text(to_markdown(report), encoding="utf-8")
        print(f"Оценка: {report['total']}/100 — {report['verdict']} (см. seo/out/scores/{args.id}.md)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
