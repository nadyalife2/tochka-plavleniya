"""Очередь работ: что писать, что написано, куда ссылаться.

Запуск из корня репозитория:
    python seo/scripts/build_worklist.py
Выход:
    seo/data/plan.json      полный нормализованный план (кэш для остальных скриптов)
    seo/out/WORKLIST.md     читаемый файл «с чем работать»
    seo/out/WORKLIST.csv    та же таблица для Excel/Google Sheets
"""
from __future__ import annotations

import csv
import json
import sys

from db import build_db_plan as build_plan
from excel_io import DATA, OUT, STATUSES
from linkgraph import hubs, inbound_candidates, outbound_candidates

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass


def short(text: str, limit: int = 70) -> str:
    text = " ".join((text or "").split())
    return text if len(text) <= limit else text[: limit - 1] + "…"


def keywords_of(topic: dict, limit: int = 3) -> str:
    raw = (topic.get("keywords") or "").replace(";", ",")
    parts = [p.strip() for p in raw.split(",") if p.strip()]
    return ", ".join(parts[:limit])


def links_text(items: list[dict]) -> str:
    if not items:
        return "—"
    return "; ".join(f"#{i['id']} {short(i['anchor'] or i['title'], 32)}" for i in items[:3])


def build() -> dict:
    plan = build_plan()
    DATA.mkdir(parents=True, exist_ok=True)
    OUT.mkdir(parents=True, exist_ok=True)
    (DATA / "plan.json").write_text(json.dumps(plan, ensure_ascii=False, indent=2), encoding="utf-8")
    return plan


def next_topics(plan: dict, limit: int = 10) -> list[dict]:
    """Приоритетная очередь: сначала матрица приоритетности, затем эвристики.

    Если `priority_matrix.py` уже отработал, главный вес у балла 0–100.
    Темы без балла уходят в конец, чтобы не вытеснять посчитанные.
    """
    candidates = [t for t in plan["topics"].values() if t["status"] in {"backlog", "brief", "brief_ready"}]
    candidates.sort(
        key=lambda t: (
            0 if t.get("priority_score") is not None else 1,
            -(t.get("priority_score") or 0),
            t["priority_rank"],
            t["cluster_priority"],
            0 if t["has_brief"] else 1,
            -len(t["inbound_planned"]),
            int(t["id"]) if t["id"].isdigit() else 999,
        )
    )
    return candidates[:limit]


def written_topics(plan: dict) -> list[dict]:
    items = [t for t in plan["topics"].values() if t["has_text"]]
    items.sort(key=lambda t: int(t["id"]) if t["id"].isdigit() else 999)
    return items


def cluster_table(plan: dict) -> list[dict]:
    rows = []
    for name, cluster in plan["clusters"].items():
        topics = [t for t in plan["topics"].values() if t["cluster"] == name]
        rows.append(
            {
                "name": name,
                "priority": cluster["priority"],
                "total": len(topics),
                "written": sum(1 for t in topics if t["has_text"]),
                "backlog": sum(1 for t in topics if t["status"] in {"backlog", "brief", "brief_ready"}),
                "keywords": short(cluster["keywords"], 45),
                "gap": short(cluster["gap"], 40),
            }
        )
    rows.sort(key=lambda r: (-r["written"], -r["total"]))
    return rows


def blockers(plan: dict) -> list[str]:
    issues: list[str] = []
    for dup in plan["duplicates"]:
        issues.append(f"Дубль slug `{dup['slug']}` у тем #{dup['ids'][0]} и #{dup['ids'][1]}")
    for topic in plan["topics"].values():
        if topic["has_text"] and topic["status"] in {"backlog", "brief"}:
            issues.append(f"# {topic['id']}: текст есть, но статус «{topic['status_raw'] or 'пусто'}»")
        if topic["has_brief"] and topic["status"] == "backlog":
            issues.append(f"# {topic['id']}: бриф есть, статус «Не начато»")
    for topic in plan["topics"].values():
        for link in topic.get("outbound_planned", []):
            if link["id"] not in plan["topics"]:
                issues.append(f"# {topic['id']}: ссылка на несуществующую тему #{link['id']}")
    if not issues:
        issues.append("Критичных расхождений не найдено.")
    return issues[:20]


def render_markdown(plan: dict) -> str:
    counts = plan["counts"]
    lines: list[str] = []
    add = lines.append

    add("# WORKLIST — «Точка Плавления»")
    add("")
    add(f"Собрано из `semantic_core_FULL_v11.xlsx` · {plan['generated_at']}")
    add("")
    add(f"**Тем: {counts['topics']}** · кластеров: {counts['clusters']} · связей: {counts['links']} · "
        f"написано: {counts['written']} · брифов: {counts['with_brief']}")
    add("")

    add("## 1. Писать сейчас (топ-10)")
    add("")
    add("_Очередь отсортирована матрицей приоритетности `priority_matrix.py` (0–100)._")
    add("")
    add("| # | Балл | Тема | Кластер | Ключевики | Тип | Статус | Куда ссылаться | Входящих |")
    add("|---:|---:|---|---|---|---|---|---|---:|")
    for topic in next_topics(plan):
        links = outbound_candidates(plan, topic["id"])
        score = topic.get("priority_score")
        score_text = str(score) if score is not None else "—"
        add(
            f"| {topic['id']} | {score_text} | {short(topic['title'], 50)} | "
            f"{short(topic['cluster'], 26)} | "
            f"{short(keywords_of(topic), 30)} | {topic['type']} | {STATUSES[topic['status']]} | "
            f"{links_text(links)} | {len(topic['inbound_planned'])} |"
        )
    add("")

    add("## 2. Уже написано")
    add("")
    written = written_topics(plan)
    if not written:
        add("_Пока нет текстов._")
    else:
        add("| # | Тема | Символов | Статус | Ссылается на | Ссылаются сюда |")
        add("|---:|---|---:|---|---|---|")
        for topic in written:
            text = topic.get("text") or {}
            add(
                f"| {topic['id']} | {short(topic['title'], 50)} | {text.get('chars', '—')} | "
                f"{STATUSES[topic['status']]} | {links_text(topic['outbound_planned'])} | "
                f"{links_text(topic['inbound_planned'])} |"
            )
    add("")

    add("## 3. Перелинковка для ближайших статей")
    add("")
    for topic in next_topics(plan, limit=5):
        out_links = outbound_candidates(plan, topic["id"])
        in_links = inbound_candidates(plan, topic["id"])
        add(f"### #{topic['id']} {topic['title']}")
        add("")
        add(f"- slug: `{topic['slug']}` · кластер: {topic['cluster']}")
        if out_links:
            add("- исходящие:")
            for link in out_links:
                mark = "✅" if link["written"] else "⏳"
                anchor = f" · анкор «{link['anchor']}»" if link["anchor"] else ""
                add(f"  - {mark} #{link['id']} {link['title']}{anchor} — {link['status']}")
        else:
            add("- исходящие: пока нет кандидатов (нет написанных статей в кластере)")
        if in_links:
            add("- входящие (кто должен сослаться):")
            for link in in_links:
                mark = "✅" if link["written"] else "⏳"
                add(f"  - {mark} #{link['id']} {link['title']} — {link['status']}")
        else:
            add("- входящие: планом не заданы")
        add("")

    add("## 4. Опорные статьи (хабы)")
    add("")
    add("| # | Тема | Кластер | Входящих | Написана |")
    add("|---:|---|---|---:|---|")
    for hub in hubs(plan, limit=8):
        add(f"| {hub['id']} | {short(hub['title'], 55)} | {short(hub['cluster'], 30)} | {hub['inbound']} | {'да' if hub['written'] else 'нет'} |")
    add("")

    add("## 5. Кластеры")
    add("")
    add("| Кластер | Приоритет | Тем | Написано | В очереди | Ключевики |")
    add("|---|---|---:|---:|---:|---|")
    for row in cluster_table(plan):
        add(f"| {short(row['name'], 34)} | {row['priority']} | {row['total']} | {row['written']} | {row['backlog']} | {row['keywords']} |")
    add("")

    add("## 6. Что починить")
    add("")
    for issue in blockers(plan):
        add(f"- {issue}")
    add("")

    # --- 7. Предложения на утверждение (пробелы и новые сайты) ---
    prop_topics = plan.get("proposed_topics", [])
    prop_comps = plan.get("proposed_competitors", [])
    if prop_topics or prop_comps:
        add("## 7. Разведка: кандидаты на утверждение")
        add("")
        if prop_topics:
            add("### Новые темы из контентных пробелов (`topics_proposed`)")
            add("")
            add("| ID | Приоритет | Кластер | Тип | Заголовок | Статус |")
            add("|---|---:|---|---|---|---|")
            for pt in prop_topics[:10]:
                add(f"| {pt.get('предложение_id', '—')} | {pt.get('оценка_приоритета', '—')} | "
                    f"{short(pt.get('кластер', ''), 26)} | {pt.get('тип статьи', '')} | "
                    f"{short(pt.get('рабочий заголовок', ''), 50)} | {pt.get('статус', 'предложен')} |")
            add("")
            add("_Чтобы перенести одобренные темы в план: поставьте в листе `topics_proposed` статус `принят` и выполните `python seo/scripts/topics_from_gaps.py --promote`._")
            add("")
        if prop_comps:
            add("### Найденные сайты-конкуренты (`competitors_proposed`)")
            add("")
            add("| Домен | Ниша | Статей | В выдаче | Статус |")
            add("|---|---|---:|---:|---|")
            for pc in prop_comps[:8]:
                add(f"| {pc.get('домен', '')} | {short(pc.get('ниша', ''), 30)} | "
                    f"{pc.get('найдено_статей', 0)} | {pc.get('сколько_раз_в_выдаче', 1)} | "
                    f"{pc.get('статус', 'предложен')} |")
            add("")
            add("_Чтобы перенести одобренные сайты в работу: поставьте в листе `competitors_proposed` статус `принят` и выполните `python seo/scripts/discover_competitors.py --promote`._")
            add("")

    add("---")
    add("")
    add("Как пользоваться: `README.md` · правила: `modules/` · оценка: `scripts/score_article.py`")
    add("")
    return "\n".join(lines)


def render_csv(plan: dict) -> list[list[str]]:
    rows = [[
        "ID", "Тема", "Slug", "Кластер", "Тип", "Приоритет", "Статус", "Ключевики",
        "Исходящие (план)", "Входящие (план)", "Есть бриф", "Есть текст", "Следующее действие",
    ]]
    for topic in plan["topics"].values():
        rows.append([
            topic["id"],
            topic["title"],
            topic["slug"],
            topic["cluster"],
            topic["type"],
            topic["priority"],
            STATUSES[topic["status"]],
            keywords_of(topic, 5),
            "; ".join(f"#{i['id']}" for i in topic["outbound_planned"]) or "",
            "; ".join(f"#{i['id']}" for i in topic["inbound_planned"]) or "",
            "да" if topic["has_brief"] else "нет",
            "да" if topic["has_text"] else "нет",
            topic["next_action"],
        ])
    return rows


def main() -> None:
    plan = build()
    markdown = render_markdown(plan)
    (OUT / "WORKLIST.md").write_text(markdown, encoding="utf-8")
    with (OUT / "WORKLIST.csv").open("w", encoding="utf-8-sig", newline="") as handle:
        csv.writer(handle, delimiter=";").writerows(render_csv(plan))
    counts = plan["counts"]
    print(f"OK: тем {counts['topics']}, написано {counts['written']}, брифов {counts['with_brief']}")
    print(f"    {OUT / 'WORKLIST.md'}")
    print(f"    {OUT / 'WORKLIST.csv'}")
    print(f"    {DATA / 'plan.json'}")


if __name__ == "__main__":
    main()
