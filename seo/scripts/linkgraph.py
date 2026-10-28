"""Рекомендации по перелинковке на основе плана и карты связей."""
from __future__ import annotations

from excel_io import STATUSES


def _topic(plan: dict, article_id: str) -> dict | None:
    return plan["topics"].get(str(article_id))


def _is_written(plan: dict, article_id: str) -> bool:
    topic = _topic(plan, article_id)
    return bool(topic and topic.get("has_text"))


def status_label(plan: dict, article_id: str) -> str:
    topic = _topic(plan, article_id)
    if not topic:
        return "нет в плане"
    code = topic.get("status", "backlog")
    return STATUSES.get(code, code)


def outbound_candidates(plan: dict, article_id: str, limit: int = 5) -> list[dict]:
    """Куда ссылаться из статьи: плановые + подсказки по кластеру."""
    topic = _topic(plan, article_id)
    if not topic:
        return []
    result: list[dict] = []
    used: set[str] = set()

    for link in topic.get("outbound_planned", []):
        target_id = link["id"]
        if target_id in used:
            continue
        used.add(target_id)
        target = _topic(plan, target_id)
        result.append(
            {
                "id": target_id,
                "title": target["title"] if target else link.get("slug", ""),
                "slug": target["slug"] if target else link.get("slug", ""),
                "anchor": link.get("anchor", ""),
                "status": status_label(plan, target_id),
                "written": _is_written(plan, target_id),
                "source": "план",
            }
        )

    # подсказки: тот же кластер, уже написанные, ещё не связанные
    if len(result) < 3:
        for other_id, other in plan["topics"].items():
            if other_id == article_id or other_id in used:
                continue
            if other["cluster"] != topic["cluster"]:
                continue
            if not other.get("has_text"):
                continue
            used.add(other_id)
            result.append(
                {
                    "id": other_id,
                    "title": other["title"],
                    "slug": other["slug"],
                    "anchor": "",
                    "status": status_label(plan, other_id),
                    "written": True,
                    "source": "подсказка: тот же кластер",
                }
            )
            if len(result) >= limit:
                break
    return result[:limit]


def inbound_candidates(plan: dict, article_id: str) -> list[dict]:
    """Кто должен ссылаться на статью (входящие плановые связи)."""
    topic = _topic(plan, article_id)
    if not topic:
        return []
    result: list[dict] = []
    for link in topic.get("inbound_planned", []):
        source_id = link["id"]
        source = _topic(plan, source_id)
        result.append(
            {
                "id": source_id,
                "title": source["title"] if source else link.get("slug", ""),
                "slug": source["slug"] if source else link.get("slug", ""),
                "anchor": link.get("anchor", ""),
                "status": status_label(plan, source_id),
                "written": _is_written(plan, source_id),
            }
        )
    return result


def hubs(plan: dict, cluster: str | None = None, limit: int = 5) -> list[dict]:
    """Опорные статьи: больше всего входящих плановых связей."""
    items = []
    for topic in plan["topics"].values():
        if cluster and topic["cluster"] != cluster:
            continue
        items.append(
            {
                "id": topic["id"],
                "title": topic["title"],
                "cluster": topic["cluster"],
                "inbound": len(topic.get("inbound_planned", [])),
                "written": topic.get("has_text", False),
            }
        )
    items.sort(key=lambda x: (-x["inbound"], x["id"]))
    return items[:limit]


def orphan_links(plan: dict) -> list[dict]:
    """Плановые связи, ведущие на темы, которых нет в плане."""
    broken = []
    for link in plan["links"]:
        if link["target"] not in plan["topics"] or link["source"] not in plan["topics"]:
            broken.append(link)
    return broken
