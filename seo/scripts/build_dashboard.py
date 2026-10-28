"""Генератор интерактивного визуального дашборда «Точка Плавления».

Эстетика: Bento-Sketch Hybrid (DESIGN.md):
- Бумажный холст #F6F6F6, карточки #FFFFFF, карандашная рамка #111111, янтарный канифольный акцент #FFC700.
- Визуальные роли (Исследователь, Автор Gemini/Claude, Рецензент, Шеф-редактор Вы).
- Интерактивный инспектор статьи (разбор баллов 0–100, замечания, чек-лист приёмки).
- Фильтр по кластерам и поиск.

Запуск:
    python seo/scripts/build_dashboard.py
Выход:
    seo/dashboard/index.html
"""
from __future__ import annotations

import json
import sys
from datetime import datetime
from pathlib import Path

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

from excel_io import DATA, OUT, STATUSES, build_plan
from linkgraph import hubs

DASH = Path(__file__).resolve().parents[1] / "dashboard"

COLUMNS = [
    ("backlog", "1. Бэклог"),
    ("work", "2. В работе"),
    ("written", "3. Написано"),
    ("review", "4. На ревью"),
    ("ready", "5. Готово к ОТК"),
    ("published", "6. Опубликовано"),
]

STATUS_COLUMN = {
    "backlog": "backlog",
    "brief": "work",
    "brief_ready": "work",
    "writing": "work",
    "written": "written",
    "review": "review",
    "scored": "review",
    "ready": "ready",
    "published": "published",
    "blocked": "work",
}

ROLES = [
    ("free", "Бесплатные ИИ (Zen / freetap)", ["backlog", "brief"], "Сбор семантики, LSI, парсинг конкурентов, драфты FAQ", "#E5E5E5"),
    ("writer", "Автор (Claude Sonnet / Gemini Pro)", ["brief_ready", "writing"], "Написание текста уникальным голосом Practico", "#FFC700"),
    ("reviewer", "Рецензент (Reviewer)", ["written", "review"], "Фактчекинг по ГОСТ/IPC, безопасность, отлов ИИ-штампов", "#FFE082"),
    ("editor", "Шеф-редактор (Вы)", ["ready", "published"], "Финальное утверждение, проверка замечаний, подтверждение ОТК", "#FFC700"),
]


def esc(text: str) -> str:
    return (text or "").replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;").replace('"', "&quot;")


def short(text: str, limit: int = 56) -> str:
    text = " ".join((text or "").split())
    return text if len(text) <= limit else text[: limit - 1] + "…"


def avatar_svg(role: str, color: str = "#FFC700") -> str:
    icons = {
        "free": '<path d="M42 28h16v8H42zM36 44h28v6H36zM40 56h20v4H40z" fill="#111"/><circle cx="50" cy="20" r="4" fill="#111"/>',
        "writer": '<path d="M52 24l8 8-22 22-8-8zM36 54l-6 12 12-6z" fill="#111"/>',
        "reviewer": '<circle cx="48" cy="38" r="14" fill="none" stroke="#111" stroke-width="4"/><path d="M58 48l14 14" stroke="#111" stroke-width="5" stroke-linecap="round"/>',
        "editor": '<path d="M34 32l16-12 16 12v28H34z" fill="none" stroke="#111" stroke-width="3"/><path d="M42 46l6 6 12-12" stroke="#111" stroke-width="3" fill="none" stroke-linecap="round"/>',
    }
    return f'''<svg viewBox="0 0 100 100" width="52" height="52" class="avatar-svg" aria-hidden="true">
  <circle cx="50" cy="50" r="44" fill="{color}" stroke="#111" stroke-width="2.5"/>
  {icons.get(role, '')}
</svg>'''


def load_all_scores() -> dict[str, dict]:
    scores = {}
    score_dir = OUT / "scores"
    if score_dir.exists():
        for path in score_dir.glob("*.json"):
            try:
                data = json.loads(path.read_text(encoding="utf-8"))
                scores[str(data["article_id"])] = data
            except Exception:
                continue
    return scores


def build_html(plan: dict) -> str:
    scores = load_all_scores()
    topics = list(plan["topics"].values())
    total = len(topics)
    written = sum(1 for t in topics if t["has_text"])
    pct = int(round(100 * written / max(1, total)))

    # Кластеры для фильтра
    all_clusters = sorted(list(plan["clusters"].keys()))

    # Карточки ролей
    role_counts = {
        "free": sum(1 for t in topics if t["status"] in ["backlog", "brief"]),
        "writer": sum(1 for t in topics if t["status"] in ["brief_ready", "writing"]),
        "reviewer": sum(1 for t in topics if t["status"] in ["written", "review"]),
        "editor": sum(1 for t in topics if t["status"] in ["ready", "published"]),
    }
    role_cards = "".join(
        f'''<div class="role-card">
  {avatar_svg(rid, col)}
  <div class="role-card__info">
    <div class="role-card__header">
      <span class="role-card__name">{esc(name)}</span>
      <span class="role-card__count">{role_counts[rid]}</span>
    </div>
    <div class="role-card__hint">{esc(hint)}</div>
  </div>
</div>'''
        for rid, name, _, hint, col in ROLES
    )

    # Колонки канбана
    columns_html = []
    for code, label in COLUMNS:
        cards = []
        for topic in topics:
            if STATUS_COLUMN.get(topic["status"], "backlog") != code:
                continue
            tid = topic["id"]
            score_data = scores.get(tid)
            score_val = score_data.get("total") if score_data else None

            if score_val is not None:
                badge_cls = "score-pill--high" if score_val >= 80 else ("score-pill--mid" if score_val >= 60 else "score-pill--low")
                score_pill = f'<span class="score-pill {badge_cls}">{score_val}/100</span>'
            elif topic["has_text"]:
                score_pill = '<span class="score-pill score-pill--pending">текст есть</span>'
            else:
                score_pill = ''

            prio_val = topic.get("priority_score")
            prio_pill = ""
            if prio_val is not None:
                p_cls = "prio-pill--hot" if prio_val >= 90 else ("prio-pill--warm" if prio_val >= 75 else "prio-pill--cold")
                prio_pill = f'<span class="prio-pill {p_cls}" title="Приоритет 0–100">P{prio_val}</span>'

            cluster_esc = esc(topic["cluster"])
            links_cnt = len(topic["inbound_planned"]) + len(topic["outbound_planned"])

            # JSON для модалки инспекции
            modal_data = {
                "id": tid,
                "title": topic["title"],
                "slug": topic["slug"],
                "cluster": topic["cluster"],
                "status": STATUSES.get(topic["status"], topic["status"]),
                "chars": topic.get("text", {}).get("chars", "—") if topic.get("text") else "—",
                "score": score_val,
                "verdict": score_data.get("verdict", "не оценивалось") if score_data else "не оценивалось",
                "blocks": score_data.get("blocks", {}) if score_data else {},
                "issues": score_data.get("issues", []) if score_data else [],
                "blockers": score_data.get("blockers", []) if score_data else [],
                "metrics": score_data.get("metrics", {}) if score_data else {},
                "out_links": topic.get("outbound_planned", []),
                "in_links": topic.get("inbound_planned", []),
                "priority": prio_val,
                "priority_detail": topic.get("priority_detail") or {},
            }
            json_blob = esc(json.dumps(modal_data, ensure_ascii=False))

            cards.append(
                f'''<article class="task-card" data-id="{tid}" data-cluster="{cluster_esc}" onclick='openInspector({json_blob})'>
  <div class="task-card__top">
    <span class="task-card__id">#{tid}</span>
    <span class="task-card__badges">{prio_pill}{score_pill}</span>
  </div>
  <h3 class="task-card__title">{esc(short(topic["title"], 60))}</h3>
  <div class="task-card__footer">
    <span class="task-card__cluster">{esc(short(topic["cluster"], 24))}</span>
    <span class="task-card__links" title="Плановых связей">🔗 {links_cnt}</span>
  </div>
</article>'''
            )
        col_count = sum(1 for t in topics if STATUS_COLUMN.get(t["status"], "backlog") == code)
        if not cards:
            cards.append('<div class="empty-col">Нет задач</div>')
        columns_html.append(
            f'''<div class="board-column">
  <div class="board-column__header">
    <span class="board-column__title">{label}</span>
    <span class="board-column__badge">{col_count}</span>
  </div>
  <div class="board-column__cards">
    {"".join(cards)}
  </div>
</div>'''
        )

    cluster_options = "".join(f'<option value="{esc(c)}">{esc(c)}</option>' for c in all_clusters)

    prop_topics = plan.get("proposed_topics", [])
    prop_comps = plan.get("proposed_competitors", [])

    prop_topics_html = "".join(
        f'''<li style="margin-bottom:8px;font-size:13px;">
  <span class="prio-pill prio-pill--warm">P{esc(str(t.get("оценка_приоритета", "—")))}</span>
  <strong>{esc(short(t.get("рабочий заголовок", ""), 60))}</strong><br>
  <span style="color:var(--muted);font-size:11px;">{esc(t.get("кластер", ""))} · источник: {esc(t.get("источник", ""))}</span>
</li>'''
        for t in prop_topics[:8]
    ) or '<li style="color:var(--muted);">Нет новых предложений</li>'

    prop_comps_html = "".join(
        f'''<li style="margin-bottom:8px;font-size:13px;">
  <strong>{esc(c.get("домен", ""))}</strong>
  <span style="color:var(--muted);font-size:11px;">— {esc(short(c.get("ниша", ""), 40))} (статей: {c.get("найдено_статей", 0)})</span>
</li>'''
        for c in prop_comps[:8]
    ) or '<li style="color:var(--muted);">Нет новых кандидатов</li>'

    generated = datetime.now().strftime("%d.%m.%Y %H:%M")

    return f'''<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Точка Плавления — Визуальный конвейер SEO и публикаций</title>
<style>
  :root {{
    --bg: #F6F6F6;
    --card: #FFFFFF;
    --ink: #111111;
    --muted: #6B6B6B;
    --accent: #FFC700;
    --border: #111111;
    --border-light: #E0E0E0;
    --font: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    --mono: "JetBrains Mono", Consolas, monospace;
    --radius: 2px;
  }}
  * {{ box-sizing: border-box; }}
  body {{
    margin: 0;
    background: var(--bg);
    color: var(--ink);
    font: 14px/1.45 var(--font);
    -webkit-font-smoothing: antialiased;
  }}
  .app-container {{
    max-width: 1480px;
    margin: 0 auto;
    padding: 24px 20px 80px;
  }}

  /* Top Bar */
  .brand-bar {{
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid var(--border);
    padding-bottom: 16px;
    margin-bottom: 20px;
  }}
  .brand-bar__left h1 {{
    margin: 0;
    font-size: 22px;
    font-weight: 800;
    letter-spacing: -0.3px;
    display: flex;
    align-items: center;
    gap: 10px;
  }}
  .brand-bar__tag {{
    background: var(--accent);
    color: var(--ink);
    padding: 2px 8px;
    font-size: 11px;
    font-family: var(--mono);
    font-weight: 700;
    border: 1px solid var(--border);
    border-radius: var(--radius);
  }}
  .brand-bar__sub {{
    color: var(--muted);
    font-size: 13px;
    margin-top: 4px;
  }}
  .brand-bar__right {{
    text-align: right;
    font-size: 12px;
    color: var(--muted);
    font-family: var(--mono);
  }}

  /* Workflow banner */
  .workflow-banner {{
    background: var(--card);
    border: 1.5px solid var(--border);
    padding: 14px 18px;
    margin-bottom: 22px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    box-shadow: 2px 2px 0px rgba(0,0,0,0.06);
  }}
  .workflow-step {{
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
  }}
  .workflow-step__num {{
    width: 24px;
    height: 24px;
    background: var(--ink);
    color: #FFF;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 11px;
  }}
  .workflow-step--accent .workflow-step__num {{
    background: var(--accent);
    color: var(--ink);
    border: 1px solid var(--border);
  }}
  .workflow-arrow {{ color: var(--muted); font-size: 16px; }}

  /* Role cards */
  .roles-grid {{
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
  }}
  .role-card {{
    background: var(--card);
    border: 1.5px solid var(--border);
    padding: 12px 14px;
    display: flex;
    gap: 14px;
    align-items: center;
    box-shadow: 2px 2px 0px rgba(0,0,0,0.04);
  }}
  .role-card__info {{ flex: 1; min-width: 0; }}
  .role-card__header {{
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 3px;
  }}
  .role-card__name {{ font-weight: 700; font-size: 13px; }}
  .role-card__count {{
    font-family: var(--mono);
    font-size: 20px;
    font-weight: 800;
  }}
  .role-card__hint {{
    color: var(--muted);
    font-size: 11px;
    line-height: 1.3;
  }}

  /* Filter toolbar */
  .toolbar {{
    background: #EAEAEA;
    border: 1.5px solid var(--border);
    padding: 10px 14px;
    margin-bottom: 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
  }}
  .toolbar__group {{
    display: flex;
    align-items: center;
    gap: 10px;
  }}
  .toolbar input, .toolbar select {{
    border: 1px solid var(--border);
    background: var(--card);
    padding: 6px 10px;
    font-size: 13px;
    border-radius: var(--radius);
    outline: none;
  }}
  .toolbar input:focus, .toolbar select:focus {{
    box-shadow: 0 0 0 2px var(--accent);
  }}
  .progress-stat {{
    font-size: 12px;
    font-family: var(--mono);
    color: var(--ink);
  }}

  /* Kanban Board */
  .kanban-board {{
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 12px;
    align-items: start;
  }}
  @media(max-width: 1200px) {{ .kanban-board {{ grid-template-columns: repeat(3, 1fr); }} }}
  @media(max-width: 768px) {{ .kanban-board {{ grid-template-columns: repeat(1, 1fr); }} }}

  .board-column {{
    background: #EAEAEA;
    border: 1.5px solid var(--border);
    padding: 10px;
    min-height: 500px;
  }}
  .board-column__header {{
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    padding-bottom: 6px;
    border-bottom: 1px dashed var(--border);
  }}
  .board-column__title {{
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }}
  .board-column__badge {{
    background: var(--card);
    border: 1px solid var(--border);
    padding: 1px 6px;
    font-size: 11px;
    font-family: var(--mono);
    font-weight: 700;
  }}
  .board-column__cards {{
    display: flex;
    flex-direction: column;
    gap: 8px;
  }}

  /* Task Card */
  .task-card {{
    background: var(--card);
    border: 1.5px solid var(--border);
    padding: 10px 11px;
    cursor: pointer;
    transition: transform 0.1s, box-shadow 0.1s;
    position: relative;
    box-shadow: 2px 2px 0px rgba(0,0,0,0.05);
  }}
  .task-card:hover {{
    transform: translateY(-2px);
    box-shadow: 3px 3px 0px rgba(0,0,0,0.12);
    border-color: #000;
  }}
  .task-card__top {{
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
  }}
  .task-card__id {{
    font-family: var(--mono);
    font-size: 11px;
    font-weight: 700;
    color: var(--muted);
  }}
  .task-card__title {{
    margin: 0 0 8px;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.35;
  }}
  .task-card__footer {{
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11px;
    color: var(--muted);
  }}
  .task-card__cluster {{
    background: var(--bg);
    padding: 2px 5px;
    border: 1px solid var(--border-light);
    border-radius: var(--radius);
  }}

  /* Score Pills */
  .score-pill {{
    font-family: var(--mono);
    font-size: 11px;
    font-weight: 700;
    padding: 1px 6px;
    border: 1px solid var(--border);
    border-radius: var(--radius);
  }}
  .score-pill--high {{ background: #A7F3D0; color: #064E3B; }}
  .score-pill--mid {{ background: #FEF08A; color: #713F12; }}
  .score-pill--low {{ background: #FECACA; color: #7F1D1D; }}
  .score-pill--pending {{ background: #E0E7FF; color: #3730A3; font-size: 10px; }}

  /* Priority Pills (матрица приоритетности 0–100) */
  .task-card__badges {{
    display: flex;
    gap: 4px;
    align-items: center;
  }}
  .prio-pill {{
    font-family: var(--mono);
    font-size: 10px;
    font-weight: 700;
    padding: 1px 5px;
    border: 1px solid var(--border);
    border-radius: var(--radius);
  }}
  .prio-pill--hot {{ background: #FFC700; color: #111; }}
  .prio-pill--warm {{ background: #FFF3C4; color: #713F12; }}
  .prio-pill--cold {{ background: #F0F0F0; color: #6B6B6B; }}

  .empty-col {{
    color: var(--muted);
    font-size: 12px;
    text-align: center;
    padding: 20px 0;
    font-style: italic;
  }}

  /* Modal Inspector */
  .modal-overlay {{
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.55);
    z-index: 999;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }}
  .modal-overlay.active {{ display: flex; }}
  .modal-content {{
    background: var(--card);
    border: 2px solid var(--border);
    max-width: 760px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    padding: 24px;
    position: relative;
    box-shadow: 8px 8px 0px rgba(0,0,0,0.2);
  }}
  .modal-close {{
    position: absolute;
    top: 16px;
    right: 16px;
    border: 1px solid var(--border);
    background: var(--bg);
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-weight: 800;
  }}
  .modal-header {{
    border-bottom: 2px solid var(--border);
    padding-bottom: 12px;
    margin-bottom: 16px;
  }}
  .modal-title {{
    margin: 6px 0 0;
    font-size: 18px;
    font-weight: 800;
  }}
  .score-grid {{
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin: 14px 0;
  }}
  .score-box {{
    background: var(--bg);
    border: 1px solid var(--border);
    padding: 8px 10px;
    font-size: 12px;
  }}
  .score-box strong {{ display: block; font-size: 16px; font-family: var(--mono); }}

  .issues-list, .blockers-list {{
    margin: 10px 0;
    padding-left: 20px;
    font-size: 13px;
  }}
  .issues-list li {{ margin-bottom: 4px; }}
  .blockers-list li {{ color: #B91C1C; font-weight: 700; margin-bottom: 4px; }}

  .modal-actions {{
    display: flex;
    gap: 10px;
    margin-top: 20px;
    padding-top: 14px;
    border-top: 1px solid var(--border);
  }}
  .btn {{
    padding: 8px 16px;
    border: 1.5px solid var(--border);
    font-weight: 700;
    font-size: 13px;
    cursor: pointer;
    border-radius: var(--radius);
  }}
  .btn--primary {{ background: var(--accent); color: var(--ink); }}
  .btn--secondary {{ background: var(--bg); color: var(--ink); }}
</style>
</head>
<body>
<div class="app-container">

  <!-- Header -->
  <header class="brand-bar">
    <div class="brand-bar__left">
      <h1>ТОЧКА ПЛАВЛЕНИЯ <span class="brand-bar__tag">SEO КОНВЕЙЕР v2.0</span></h1>
      <div class="brand-bar__sub">Автономное управление 60 темами, скоринг 0–100 и строгая приёмка шеф-редактором</div>
    </div>
    <div class="brand-bar__right">
      Обновлено: {generated}<br>
      Источник: semantic_core_FULL_v11.xlsx
    </div>
  </header>

  <!-- 4-Шаговый цикл согласования -->
  <section class="workflow-banner">
    <div class="workflow-step">
      <div class="workflow-step__num">1</div>
      <div><strong>Бесплатный ИИ</strong><br><span style="color:var(--muted);font-size:11px">LSI, FAQ, H2/H3</span></div>
    </div>
    <div class="workflow-arrow">→</div>
    <div class="workflow-step">
      <div class="workflow-step__num">2</div>
      <div><strong>Gemini / Claude Sonnet</strong><br><span style="color:var(--muted);font-size:11px">Текст голосом Practico</span></div>
    </div>
    <div class="workflow-arrow">→</div>
    <div class="workflow-step">
      <div class="workflow-step__num">3</div>
      <div><strong>Рецензент (Reviewer)</strong><br><span style="color:var(--muted);font-size:11px">ГОСТ/IPC, безопасность</span></div>
    </div>
    <div class="workflow-arrow">→</div>
    <div class="workflow-step workflow-step--accent">
      <div class="workflow-step__num">4</div>
      <div><strong>Шеф-редактор (ВЫ)</strong><br><span style="color:var(--muted);font-size:11px">Финальное утверждение и ОТК</span></div>
    </div>
  </section>

  <!-- Роли конвейера -->
  <section class="roles-grid">
    {role_cards}
  </section>

  <!-- Toolbar -->
  <div class="toolbar">
    <div class="toolbar__group">
      <label>Кластер:
        <select id="clusterFilter" onchange="filterCards()">
          <option value="">Все кластеры ({len(all_clusters)})</option>
          {cluster_options}
        </select>
      </label>
      <input type="text" id="searchInput" placeholder="Поиск по теме или #ID..." oninput="filterCards()">
    </div>
    <div class="toolbar__group">
      <span class="progress-stat">Готово текстов: <strong>{written} / {total}</strong> ({pct}%)</span>
    </div>
  </div>

  <!-- Kanban Board -->
  <div class="kanban-board">
    {''.join(columns_html)}
  </div>

  <!-- Разведка: предложения на утверждение -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:24px;">
    <div style="background:var(--card);border:1.5px solid var(--border);padding:14px 16px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
        <h3 style="margin:0;font-size:14px;font-weight:800;text-transform:uppercase;letter-spacing:0.5px;">
          💡 Темы из пробелов ({len(prop_topics)})
        </h3>
        <span style="font-size:11px;color:var(--muted);font-family:var(--mono);">topics_proposed</span>
      </div>
      <ul style="margin:0;padding-left:18px;">
        {prop_topics_html}
      </ul>
      <div style="margin-top:10px;font-size:11px;color:var(--muted);border-top:1px dashed var(--border-light);padding-top:6px;">
        Утвердить: статус «принят» в Excel → <code>python seo/scripts/topics_from_gaps.py --promote</code>
      </div>
    </div>

    <div style="background:var(--card);border:1.5px solid var(--border);padding:14px 16px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
        <h3 style="margin:0;font-size:14px;font-weight:800;text-transform:uppercase;letter-spacing:0.5px;">
          🔎 Сайты-конкуренты ({len(prop_comps)})
        </h3>
        <span style="font-size:11px;color:var(--muted);font-family:var(--mono);">competitors_proposed</span>
      </div>
      <ul style="margin:0;padding-left:18px;">
        {prop_comps_html}
      </ul>
      <div style="margin-top:10px;font-size:11px;color:var(--muted);border-top:1px dashed var(--border-light);padding-top:6px;">
        Утвердить: статус «принят» в Excel → <code>python seo/scripts/discover_competitors.py --promote</code>
      </div>
    </div>
  </div>

</div>

<!-- Modal Article Inspector -->
<div class="modal-overlay" id="inspectorModal" onclick="closeInspector(event)">
  <div class="modal-content" onclick="event.stopPropagation()">
    <button class="modal-close" onclick="closeInspector()">✕</button>
    <div class="modal-header">
      <div style="font-family:var(--mono);font-size:12px;color:var(--muted);">
        <span id="mId">#15</span> · <span id="mCluster">Материалы</span> · статус: <strong id="mStatus">Готово</strong>
      </div>
      <h2 class="modal-title" id="mTitle">Название статьи</h2>
    </div>

    <div>
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <h3 style="margin:0;">Инженерный скоринг качества:</h3>
        <span id="mTotalScore" style="font-family:var(--mono);font-size:18px;font-weight:800;padding:2px 8px;border:1px solid #111;">—</span>
      </div>

      <div class="score-grid" id="mScoreGrid">
        <!-- Заполняется JS -->
      </div>

      <div style="margin-top:16px;padding-top:12px;border-top:1px dashed #111;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <h4 style="margin:0;">Приоритет темы (матрица 0–100):</h4>
          <span id="mPriorityTotal" style="font-family:var(--mono);font-size:16px;font-weight:800;padding:2px 8px;border:1px solid #111;">—</span>
        </div>
        <div class="score-grid" id="mPriorityGrid" style="grid-template-columns:repeat(3,1fr);">
          <!-- Заполняется JS -->
        </div>
        <div id="mPriorityHint" style="font-size:12px;color:#6B6B6B;margin-top:4px;"></div>
      </div>

      <div id="mBlockersBox" style="display:none;background:#FEE2E2;border:1px solid #B91C1C;padding:10px;margin:10px 0;">
        <strong style="color:#B91C1C;">❌ КРИТИЧЕСКИЕ БЛОКЕРЫ:</strong>
        <ul class="blockers-list" id="mBlockersList"></ul>
      </div>

      <div id="mIssuesBox" style="display:none;background:#FEF9C3;border:1px solid #A16207;padding:10px;margin:10px 0;">
        <strong style="color:#854D0E;">⚠️ Замечания к доработке:</strong>
        <ul class="issues-list" id="mIssuesList"></ul>
      </div>

      <div style="margin-top:14px;">
        <h4 style="margin:0 0 6px;">Рекомендованная перелинковка:</h4>
        <div id="mLinksText" style="font-size:12px;color:var(--muted);">—</div>
      </div>
    </div>

    <div class="modal-actions">
      <button class="btn btn--primary" onclick="alert('Статья утверждена! Следующий шаг: публикация черновика в WordPress и подтверждение ОТК в админке.')">✓ Утвердить статью</button>
      <button class="btn btn--secondary" onclick="alert('Отправлено автору на устранение замечаний.')">↺ Вернуть на доработку</button>
      <button class="btn btn--secondary" onclick="closeInspector()">Закрыть</button>
    </div>
  </div>
</div>

<script>
function filterCards() {{
  const cluster = document.getElementById('clusterFilter').value.toLowerCase();
  const search = document.getElementById('searchInput').value.toLowerCase().trim();
  const cards = document.querySelectorAll('.task-card');

  cards.forEach(card => {{
    const cCluster = (card.getAttribute('data-cluster') || '').toLowerCase();
    const cId = '#' + (card.getAttribute('data-id') || '');
    const cText = card.innerText.toLowerCase();

    const matchCluster = !cluster || cCluster === cluster;
    const matchSearch = !search || cText.includes(search) || cId.includes(search);

    if (matchCluster && matchSearch) {{
      card.style.display = 'block';
    }} else {{
      card.style.display = 'none';
    }}
  }});
}}

function openInspector(data) {{
  document.getElementById('mId').innerText = '#' + data.id;
  document.getElementById('mCluster').innerText = data.cluster;
  document.getElementById('mStatus').innerText = data.status;
  document.getElementById('mTitle').innerText = data.title;

  const totalEl = document.getElementById('mTotalScore');
  if (data.score !== null) {{
    totalEl.innerText = data.score + ' / 100 (' + data.verdict + ')';
    totalEl.style.background = data.score >= 80 ? '#A7F3D0' : (data.score >= 60 ? '#FEF08A' : '#FECACA');
  }} else {{
    totalEl.innerText = 'Ещё не оценено';
    totalEl.style.background = '#E5E5E5';
  }}

  // Сетка блоков
  const grid = document.getElementById('mScoreGrid');
  grid.innerHTML = '';
  if (data.blocks && Object.keys(data.blocks).length > 0) {{
    for (const [k, v] of Object.entries(data.blocks)) {{
      grid.innerHTML += `<div class="score-box"><strong>${{v.points}} / ${{v.max}}</strong><span>${{k}}</span></div>`;
    }}
  }} else {{
    grid.innerHTML = '<div class="score-box" style="grid-column:1/-1;">Статья ещё не проходила автоматический скоринг `score_article.py`.</div>';
  }}

  // Блокеры
  const bBox = document.getElementById('mBlockersBox');
  const bList = document.getElementById('mBlockersList');
  if (data.blockers && data.blockers.length > 0) {{
    bBox.style.display = 'block';
    bList.innerHTML = data.blockers.map(b => `<li>${{b}}</li>`).join('');
  }} else {{
    bBox.style.display = 'none';
  }}

  // Замечания
  const iBox = document.getElementById('mIssuesBox');
  const iList = document.getElementById('mIssuesList');
  if (data.issues && data.issues.length > 0) {{
    iBox.style.display = 'block';
    iList.innerHTML = data.issues.map(i => `<li>${{i}}</li>`).join('');
  }} else {{
    iBox.style.display = 'none';
  }}

  // Связи
  const linksEl = document.getElementById('mLinksText');
  let linksStr = '';
  if (data.out_links && data.out_links.length > 0) {{
    linksStr += '<strong>Исходящие:</strong> ' + data.out_links.map(l => `#${{l.id}} (${{l.anchor || l.slug}})`).join(', ') + '<br>';
  }}
  if (data.in_links && data.in_links.length > 0) {{
    linksStr += '<strong>Входящие:</strong> ' + data.in_links.map(l => `#${{l.id}}`).join(', ');
  }}
  linksEl.innerHTML = linksStr || 'Плановые связи не заданы.';

  // Матрица приоритетности
  const pTotalEl = document.getElementById('mPriorityTotal');
  const pGrid = document.getElementById('mPriorityGrid');
  const pHint = document.getElementById('mPriorityHint');
  const pd = data.priority_detail || {{}};
  if (data.priority !== null && data.priority !== undefined) {{
    pTotalEl.innerText = data.priority + ' / 100';
    pTotalEl.style.background = data.priority >= 90 ? '#FFC700' : (data.priority >= 75 ? '#FFF3C4' : '#F0F0F0');
    const labels = {{
      cluster_score: 'Кластер',
      gap_score: 'Пробел',
      coverage_score: 'Покрытие конкурентами',
      links_score: 'Хабность ссылок',
      keywords_score: 'Широта ключей',
      readiness_score: 'Готовность',
    }};
    pGrid.innerHTML = Object.entries(labels).map(([k, label]) =>
      `<div class="score-box"><strong>${{pd[k] !== undefined ? pd[k] : '—'}}</strong><span>${{label}}</span></div>`
    ).join('');
    pHint.innerText = data.priority >= 90 ? 'Брать сейчас: первый эшелон очереди.'
      : (data.priority >= 75 ? 'Следующая волна: писать после тем 90+.'
      : (data.priority >= 60 ? 'Позже: держать в резерве.' : 'В конец очереди: низкий потенциал.'));
  }} else {{
    pTotalEl.innerText = 'Не рассчитан';
    pTotalEl.style.background = '#E5E5E5';
    pGrid.innerHTML = '<div class="score-box" style="grid-column:1/-1;">Запустите `python seo/scripts/priority_matrix.py`.</div>';
    pHint.innerText = '';
  }}

  document.getElementById('inspectorModal').classList.add('active');
}}

function closeInspector(e) {{
  if (e && e.target !== e.currentTarget && !e.target.classList.contains('modal-close')) return;
  document.getElementById('inspectorModal').classList.remove('active');
}}

document.addEventListener('keydown', e => {{
  if (e.key === 'Escape') closeInspector();
}});
</script>
</body>
</html>'''


def main() -> None:
    plan = build_plan()
    DATA.mkdir(parents=True, exist_ok=True)
    (DATA / "plan.json").write_text(json.dumps(plan, ensure_ascii=False, indent=2), encoding="utf-8")
    DASH.mkdir(parents=True, exist_ok=True)
    target = DASH / "index.html"
    target.write_text(build_html(plan), encoding="utf-8")
    print(f"OK: {target}")


if __name__ == "__main__":
    main()
