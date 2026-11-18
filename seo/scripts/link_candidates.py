"""Показать кандидатов на перелинковку для статьи.

    python seo/scripts/link_candidates.py --id 15
    python seo/scripts/link_candidates.py --id 15 --write   (сохранить в out/links-15.md)
"""
from __future__ import annotations

import argparse
import sys

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

from db import build_db_plan as build_plan
from excel_io import OUT, STATUSES
from linkgraph import hubs, inbound_candidates, outbound_candidates


def render(plan: dict, article_id: str) -> str:
    topic = plan["topics"].get(article_id)
    if not topic:
        raise SystemExit(f"Тема #{article_id} не найдена в плане")
    out_links = outbound_candidates(plan, article_id)
    in_links = inbound_candidates(plan, article_id)

    lines = [f"# Перелинковка: #{article_id} {topic['title']}", "",
             f"- slug: `{topic['slug']}`",
             f"- кластер: {topic['cluster']}",
             f"- статус: {STATUSES[topic['status']]}",
             "", "## Исходящие (ставьте в тексте)", ""]
    if out_links:
        lines.append("| # | Куда | Анкор | Статус | Можно ставить |")
        lines.append("|---:|---|---|---|---|")
        for link in out_links:
            ready = "да" if link["written"] else "нет — отложенная связь"
            lines.append(f"| {link['id']} | {link['title']} | {link['anchor'] or '—'} | {link['status']} | {ready} |")
    else:
        lines.append("_Кандидатов нет: в кластере пока нет написанных статей._")

    lines += ["", "## Входящие (кто должен сослаться на эту статью)", ""]
    if in_links:
        lines.append("| # | Откуда | Анкор | Статус |")
        lines.append("|---:|---|---|---|")
        for link in in_links:
            lines.append(f"| {link['id']} | {link['title']} | {link['anchor'] or '—'} | {link['status']} |")
    else:
        lines.append("_Планом не заданы._")

    lines += ["", "## Хабы кластера", ""]
    for hub in hubs(plan, cluster=topic["cluster"], limit=5):
        lines.append(f"- #{hub['id']} {hub['title']} — входящих {hub['inbound']}, "
                     f"{'написана' if hub['written'] else 'не написана'}")
    return "\n".join(lines) + "\n"


def main() -> int:
    parser = argparse.ArgumentParser(description="Кандидаты перелинковки")
    parser.add_argument("--id", required=True)
    parser.add_argument("--write", action="store_true", help="сохранить в seo/out/links-<id>.md")
    args = parser.parse_args()

    plan = build_plan()
    text = render(plan, str(args.id))
    print(text)
    if args.write:
        OUT.mkdir(parents=True, exist_ok=True)
        path = OUT / f"links-{args.id}.md"
        path.write_text(text, encoding="utf-8")
        print(f"Сохранено: {path}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
