"""Компилятор интерактивного UI дашборда «Точка Плавления» v3.2.

Включает:
1. Раздел «Готовые к выгрузке» (Ready to Export в WordPress)
2. Индикацию запущенных агентов (Claude Sonnet, Claude Opus, Perplexity Sonar)
3. Баннер «Ожидает Вашего решения»:
   - статьи, требующие правок (с конкретными замечаниями)
   - статьи, готовые к утверждению шеф-редактором
   - предложенные темы на одобрение
4. Работу с SQLite БД и моментальные бэкапы.
"""
from __future__ import annotations

import json
import sys
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SCRIPTS = ROOT / "seo" / "scripts"
sys.path.insert(0, str(SCRIPTS))

from server import fetch_all_data

DASH_DIR = ROOT / "seo" / "dashboard"


def esc(text: str) -> str:
    return (text or "").replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;").replace('"', "&quot;")


def generate_html() -> str:
    data = fetch_all_data()
    json_blob = json.dumps(data, ensure_ascii=False)

    html = f"""<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Точка Плавления — SEO Конвейер & Публикации v3.2</title>
<style>
  :root {{
    --bg: #F6F6F6;
    --card: #FFFFFF;
    --ink: #111111;
    --muted: #6B6B6B;
    --accent: #FFC700;
    --accent-light: #FFF4C2;
    --border: #111111;
    --border-light: #E0E0E0;
    --success: #059669;
    --success-bg: #D1FAE5;
    --warning: #D97706;
    --warning-bg: #FEF3C7;
    --danger: #DC2626;
    --danger-bg: #FEE2E2;
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
    max-width: 1540px;
    margin: 0 auto;
    padding: 18px 24px 80px;
  }}

  /* Top Bar */
  .brand-bar {{
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid var(--border);
    padding-bottom: 12px;
    margin-bottom: 14px;
    flex-wrap: wrap;
    gap: 12px;
  }}
  .brand-bar__left h1 {{
    margin: 0;
    font-size: 20px;
    font-weight: 800;
    letter-spacing: -0.3px;
    display: flex;
    align-items: center;
    gap: 8px;
  }}
  .brand-bar__tag {{
    background: var(--accent);
    color: var(--ink);
    padding: 2px 7px;
    font-size: 11px;
    font-family: var(--mono);
    font-weight: 700;
    border: 1px solid var(--border);
  }}
  .brand-bar__actions {{
    display: flex;
    gap: 8px;
    align-items: center;
  }}

  /* Agent Status Strip */
  .agents-status-strip {{
    display: flex;
    gap: 10px;
    background: #EAEAEA;
    border: 1.5px solid var(--border);
    padding: 8px 12px;
    margin-bottom: 14px;
    flex-wrap: wrap;
    align-items: center;
    font-size: 12px;
  }}
  .agent-badge {{
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--card);
    padding: 3px 8px;
    border: 1px solid var(--border);
    font-family: var(--mono);
    font-size: 11px;
    font-weight: 600;
  }}
  .status-dot {{
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #9CA3AF;
  }}
  .status-dot.active {{ background: var(--success); box-shadow: 0 0 6px var(--success); }}

  /* Decision Alert Banner */
  .decision-banner {{
    background: var(--card);
    border: 2px solid var(--border);
    padding: 12px 16px;
    margin-bottom: 16px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 14px;
    box-shadow: 3px 3px 0px rgba(0,0,0,0.06);
  }}
  .decision-col {{
    border-right: 1px dashed var(--border-light);
    padding-right: 12px;
  }}
  .decision-col:last-child {{ border-right: none; }}
  .decision-col__title {{
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
  }}
  .decision-col__list {{
    margin: 0;
    padding-left: 16px;
    font-size: 12px;
  }}
  .decision-col__list li {{ margin-bottom: 3px; }}

  /* Buttons */
  .btn {{
    padding: 6px 12px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    border: 1.5px solid var(--border);
    background: var(--card);
    color: var(--ink);
    border-radius: var(--radius);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.1s;
    box-shadow: 2px 2px 0px rgba(0,0,0,0.06);
  }}
  .btn:hover {{ transform: translateY(-1px); box-shadow: 3px 3px 0px rgba(0,0,0,0.12); }}
  .btn:active {{ transform: translateY(1px); box-shadow: 1px 1px 0px rgba(0,0,0,0.06); }}
  .btn--primary {{ background: var(--accent); }}
  .btn--success {{ background: var(--success-bg); color: #065F46; border-color: #065F46; }}
  .btn--warning {{ background: var(--warning-bg); color: #92400E; border-color: #92400E; }}
  .btn--danger {{ background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }}
  .btn--sm {{ padding: 3px 8px; font-size: 11px; }}

  /* Tabs Nav */
  .tabs-nav {{
    display: flex;
    gap: 4px;
    border-bottom: 1.5px solid var(--border);
    margin-bottom: 16px;
  }}
  .tab-btn {{
    padding: 8px 15px;
    background: #EAEAEA;
    border: 1.5px solid var(--border);
    border-bottom: none;
    font-weight: 700;
    font-size: 13px;
    cursor: pointer;
    border-radius: var(--radius) var(--radius) 0 0;
    margin-bottom: -1.5px;
    display: flex;
    align-items: center;
    gap: 7px;
  }}
  .tab-btn.active {{
    background: var(--card);
    border-bottom: 2px solid var(--card);
  }}
  .tab-badge {{
    background: var(--accent);
    color: var(--ink);
    padding: 1px 6px;
    font-size: 10px;
    font-family: var(--mono);
    border-radius: 10px;
    border: 1px solid var(--border);
  }}

  /* Tab View containers */
  .tab-view {{ display: none; }}
  .tab-view.active {{ display: block; }}

  /* Kanban */
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
    min-height: 520px;
  }}
  .board-column__header {{
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    padding-bottom: 6px;
    border-bottom: 1px dashed var(--border);
  }}
  .board-column__title {{ font-size: 12px; font-weight: 800; text-transform: uppercase; }}
  .board-column__badge {{
    background: var(--card);
    border: 1px solid var(--border);
    padding: 1px 6px;
    font-size: 11px;
    font-family: var(--mono);
    font-weight: 700;
  }}
  .board-column__cards {{ display: flex; flex-direction: column; gap: 8px; }}

  /* Cards */
  .task-card {{
    background: var(--card);
    border: 1.5px solid var(--border);
    padding: 10px;
    cursor: pointer;
    box-shadow: 2px 2px 0px rgba(0,0,0,0.05);
    transition: transform 0.1s;
  }}
  .task-card:hover {{ transform: translateY(-2px); border-color: #000; box-shadow: 3px 3px 0px rgba(0,0,0,0.12); }}
  .task-card__top {{ display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }}
  .task-card__id {{ font-family: var(--mono); font-size: 11px; font-weight: 700; color: var(--muted); }}
  .task-card__badges {{ display: flex; gap: 4px; }}
  .task-card__title {{ margin: 0 0 6px; font-size: 13px; font-weight: 600; line-height: 1.35; }}
  .task-card__footer {{ display: flex; justify-content: space-between; align-items: center; font-size: 11px; color: var(--muted); }}
  .task-card__cluster {{ background: var(--bg); padding: 1px 5px; border: 1px solid var(--border-light); }}
  .task-card__warn {{
    background: #FEF2F2;
    border: 1px solid #FCA5A5;
    padding: 4px 6px;
    font-size: 11px;
    color: #991B1B;
    margin-top: 6px;
    border-radius: var(--radius);
  }}

  .prio-pill {{ font-family: var(--mono); font-size: 10px; font-weight: 700; padding: 1px 5px; border: 1px solid var(--border); }}
  .prio-pill--hot {{ background: #FFC700; color: #111; }}
  .prio-pill--warm {{ background: #FFF3C4; color: #713F12; }}
  .prio-pill--cold {{ background: #F0F0F0; color: #6B6B6B; }}

  .score-pill {{ font-family: var(--mono); font-size: 10px; font-weight: 700; padding: 1px 5px; border: 1px solid var(--border); }}
  .score-pill--high {{ background: #A7F3D0; color: #064E3B; }}
  .score-pill--mid {{ background: #FEF08A; color: #713F12; }}
  .score-pill--low {{ background: #FECACA; color: #7F1D1D; }}

  /* Tables */
  .data-table-container {{
    background: var(--card);
    border: 1.5px solid var(--border);
    overflow-x: auto;
    box-shadow: 2px 2px 0px rgba(0,0,0,0.06);
  }}
  .data-table {{
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    text-align: left;
  }}
  .data-table th {{
    background: #EAEAEA;
    border-bottom: 1.5px solid var(--border);
    padding: 10px 12px;
    font-weight: 800;
    text-transform: uppercase;
    font-size: 11px;
    letter-spacing: 0.5px;
  }}
  .data-table td {{
    padding: 10px 12px;
    border-bottom: 1px solid var(--border-light);
    vertical-align: middle;
  }}
  .data-table tr:hover td {{ background: #FAFAFA; }}

  /* Agent Grid */
  .agents-grid {{
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
  }}
  .agent-card {{
    background: var(--card);
    border: 1.5px solid var(--border);
    padding: 16px;
    box-shadow: 3px 3px 0px rgba(0,0,0,0.06);
  }}
  .agent-card__header {{ display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }}
  .agent-card__title {{ font-size: 15px; font-weight: 800; margin: 0; }}
  .agent-card__desc {{ font-size: 12px; color: var(--muted); margin-bottom: 12px; min-height: 38px; }}
  .agent-card__meta {{ font-size: 11px; font-family: var(--mono); color: var(--muted); margin-bottom: 14px; }}

  .log-console {{
    background: #111;
    color: #A7F3D0;
    font-family: var(--mono);
    font-size: 12px;
    padding: 14px;
    border: 1.5px solid var(--border);
    height: 190px;
    overflow-y: auto;
    white-space: pre-wrap;
    margin-top: 14px;
  }}

  /* Modal */
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
    max-width: 820px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    padding: 24px;
    position: relative;
    box-shadow: 8px 8px 0px rgba(0,0,0,0.2);
  }}
  .modal-close {{
    position: absolute;
    top: 16px; right: 16px;
    border: 1px solid var(--border);
    background: var(--bg);
    width: 28px; height: 28px;
    cursor: pointer;
    font-weight: 800;
  }}

  .toast {{
    position: fixed;
    bottom: 24px; right: 24px;
    background: #111;
    color: #FFF;
    padding: 12px 18px;
    border: 1.5px solid var(--accent);
    font-size: 13px;
    font-weight: 600;
    box-shadow: 4px 4px 0px rgba(0,0,0,0.3);
    z-index: 9999;
    display: none;
  }}
</style>
</head>
<body>
<div class="app-container">

  <!-- Brand & Header -->
  <header class="brand-bar">
    <div class="brand-bar__left">
      <h1>ТОЧКА ПЛАВЛЕНИЯ <span class="brand-bar__tag">SEO КОНВЕЙЕР v3.2</span></h1>
      <div style="font-family:var(--mono);font-size:12px;background:var(--card);padding:3px 8px;border:1px solid var(--border);">
        ● База SQLite: <strong id="statTopicsCount">{data['counts']['topics']}</strong> тем · <strong id="statWrittenCount">{data['counts']['written']}</strong> написано
      </div>
    </div>
    <div class="brand-bar__actions">
      <button class="btn btn--primary" onclick="createBackup()">💾 Создать бэкап БД</button>
      <button class="btn" onclick="refreshData()">🔄 Обновить</button>
    </div>
  </header>

  <!-- Live Agents Status Bar -->
  <section class="agents-status-strip">
    <span style="font-weight:700;font-size:11px;text-transform:uppercase;">Субагенты конвейера:</span>
    <span class="agent-badge"><span class="status-dot active"></span> 00-Разведчик (Perplexity Sonar)</span>
    <span class="agent-badge"><span class="status-dot active"></span> 01-Исследователь (OpenCode Zen)</span>
    <span class="agent-badge"><span class="status-dot active" style="background:#FFC700;"></span> 02-Автор Practico (Claude Sonnet Antigravity)</span>
    <span class="agent-badge"><span class="status-dot active" style="background:#818CF8;"></span> 03-Рецензент (Claude Opus Antigravity)</span>
    <span class="agent-badge"><span class="status-dot active"></span> 04-Скорер (score_article.py)</span>
  </section>

  <!-- Decision / Action Required Banner -->
  <section class="decision-banner" id="decisionBanner">
    <!-- Решения шеф-редактора генерируются JS -->
  </section>

  <!-- Navigation Tabs -->
  <nav class="tabs-nav">
    <button class="tab-btn active" onclick="switchTab('kanban')">📋 Канбан статей <span class="tab-badge" id="bCountTopics">{data['counts']['topics']}</span></button>
    <button class="tab-btn" onclick="switchTab('export')">🚀 Готовые к выгрузке <span class="tab-badge" id="bCountExport" style="background:#A7F3D0;color:#064E3B;">0</span></button>
    <button class="tab-btn" onclick="switchTab('gaps')">💡 Темы из пробелов <span class="tab-badge" id="bCountProposed">{data['counts']['proposed_topics']}</span></button>
    <button class="tab-btn" onclick="switchTab('competitors')">🔎 Конкуренты и фишки <span class="tab-badge" id="bCountComps">{data['counts']['competitors']}</span></button>
    <button class="tab-btn" onclick="switchTab('agents')">🤖 Центр агентов</button>
    <button class="tab-btn" onclick="switchTab('backups')">💾 Бэкапы и архивы <span class="tab-badge" id="bCountBackups">{data['counts']['backups']}</span></button>
  </nav>

  <!-- TAB 1: KANBAN -->
  <section class="tab-view active" id="view-kanban">
    <div style="background:#EAEAEA;border:1.5px solid var(--border);padding:10px 14px;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
      <div style="display:flex;gap:10px;align-items:center;">
        <label style="font-size:13px;font-weight:600;">Кластер:
          <select id="clusterSelect" onchange="filterKanban()" style="padding:4px 8px;border:1px solid var(--border);">
            <option value="">Все кластеры</option>
          </select>
        </label>
        <input type="text" id="kanbanSearch" placeholder="Поиск по названию или #ID..." oninput="filterKanban()" style="padding:4px 8px;border:1px solid var(--border);width:220px;">
      </div>
      <div style="display:flex;gap:10px;align-items:center;">
        <button class="btn btn--sm btn--primary" onclick="toggleNewTopicBox()">✍️ + Добавить тему из головы</button>
      </div>
    </div>

    <!-- Окно быстрого добавления темы из головы -->
    <div id="newTopicBox" style="display:none;background:var(--card);border:2px solid var(--border);padding:14px 16px;margin-bottom:14px;box-shadow:3px 3px 0px rgba(0,0,0,0.06);">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
        <h3 style="margin:0;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:0.5px;display:flex;align-items:center;gap:6px;">
          ✍️ Добавить тему в бэклог (Ваша идея / из головы)
        </h3>
        <span style="font-size:11px;font-family:var(--mono);color:var(--muted);">Тема сразу попадёт в колонку «Бэклог» для обработки агентами</span>
      </div>
      <div style="display:grid;grid-template-columns:1fr 220px 150px 150px 180px;gap:8px;margin-bottom:8px;">
        <input type="text" id="newTopicTitle" placeholder="Название темы (например: Как паять алюминиевые радиаторы)..." style="padding:6px 10px;border:1.5px solid var(--border);font-size:12px;">
        <select id="newTopicCluster" style="padding:6px 10px;border:1.5px solid var(--border);font-size:12px;"></select>
        <select id="newTopicType" style="padding:6px 10px;border:1.5px solid var(--border);font-size:12px;">
          <option value="гайд">гайд / мануал</option>
          <option value="выбор">выбор припоя</option>
          <option value="ошибки">дефекты пайки</option>
          <option value="обзор">обзор станции</option>
        </select>
        <select id="newTopicPriority" style="padding:6px 10px;border:1.5px solid var(--border);font-size:12px;">
          <option value="высокий">Высокий (P85)</option>
          <option value="средний" selected>Средний (P60)</option>
        </select>
        <button class="btn btn--primary" onclick="createNewTopicFromInput()">+ Добавить в бэклог</button>
      </div>
      <div>
        <input type="text" id="newTopicNotes" placeholder="Ваши мысли, ключевые тезисы или акценты статьи (необязательно)..." style="width:100%;padding:5px 10px;border:1px dashed var(--border);font-size:11px;background:var(--bg);">
      </div>
    </div>

    <div class="kanban-board" id="kanbanBoard"></div>
  </section>

  <!-- TAB 2: READY TO EXPORT (WORDPRESS DELIVERY) -->
  <section class="tab-view" id="view-export">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
      <div>
        <h2 style="margin:0;font-size:17px;font-weight:800;">Статьи, готовые к выгрузке в WordPress</h2>
        <div style="font-size:12px;color:var(--muted);">Материалы, набравшие ≥80 баллов, проверенные рецензентом (Claude Opus) и утверждённые Вами к ОТК.</div>
      </div>
      <button class="btn btn--primary" onclick="exportAllReady()">🚀 Выгрузить черновики в WordPress (delivery_cli.py)</button>
    </div>

    <div class="data-table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Балл</th>
            <th>Заголовок статьи</th>
            <th>Slug</th>
            <th>Кластер</th>
            <th>Знаков</th>
            <th>Контракт v1.1</th>
            <th>Статус ОТК</th>
            <th>Действие</th>
          </tr>
        </thead>
        <tbody id="exportTableBody">
          <!-- Рендерится JS -->
        </tbody>
      </table>
    </div>
  </section>

  <!-- TAB 3: PROPOSED TOPICS FROM GAPS -->
  <section class="tab-view" id="view-gaps">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
      <div>
        <h2 style="margin:0;font-size:17px;font-weight:800;">Предложенные темы из контентных пробелов</h2>
        <div style="font-size:12px;color:var(--muted);">Темы, найденные у конкурентов в незакрытых кластерах. Одобрите подходящие — они попадут в рабочий бэклог.</div>
      </div>
      <button class="btn btn--primary" onclick="runAgent('topics-gaps')">⚡ Пересчитать пробелы</button>
    </div>

    <div class="data-table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Приоритет</th>
            <th>Заголовок темы</th>
            <th>Кластер</th>
            <th>Тип</th>
            <th>Источник / Доказательство</th>
            <th>Статус</th>
            <th>Действие</th>
          </tr>
        </thead>
        <tbody id="proposedTopicsBody"></tbody>
      </table>
    </div>
  </section>

  <!-- TAB 4: COMPETITORS & FEATURES -->
  <section class="tab-view" id="view-competitors">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
      <div>
        <h2 style="margin:0;font-size:17px;font-weight:800;">Анализ конкурентов, SEO-слов и фишек</h2>
        <div style="font-size:12px;color:var(--muted);">Досье на ключевые сайты: сила 0-100, калькуляторы, таблицы сравнения, PDF-даташиты и ключевые слова.</div>
      </div>
      <div style="display:flex;gap:10px;">
        <button class="btn btn--primary" onclick="runAgent('bot-analyzer')">🤖 Запустить глубокий анализ всех сайтов</button>
        <button class="btn" onclick="runAgent('00-research')">🌐 Собрать новые статьи</button>
      </div>
    </div>

    <!-- Блок ручного импорта выгрузки Firecrawl -->
    <div style="background:var(--card);border:2px solid var(--border);padding:14px 16px;margin-bottom:20px;box-shadow:3px 3px 0px rgba(0,0,0,0.06);">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
        <h3 style="margin:0;font-size:14px;font-weight:800;text-transform:uppercase;letter-spacing:0.5px;display:flex;align-items:center;gap:6px;">
          📥 Импорт выгрузки Firecrawl (Markdown / JSON)
        </h3>
        <span style="font-size:11px;font-family:var(--mono);color:var(--muted);">Вставьте скопированный текст из Firecrawl</span>
      </div>
      <div style="display:grid;grid-template-columns:1fr 220px 240px;gap:10px;margin-bottom:10px;">
        <input type="text" id="fcDomain" placeholder="Домен сайта (например: masterpaiki.ru)..." style="padding:6px 10px;border:1px solid var(--border);font-size:12px;">
        <select id="fcCluster" style="padding:6px 10px;border:1px solid var(--border);font-size:12px;">
          <option value="Материалы / Припои">Материалы / Припои</option>
          <option value="Инструменты / Паяльники">Инструменты / Паяльники</option>
          <option value="Ошибки / Дефекты">Ошибки / Дефекты</option>
          <option value="Типы компонентов / SMD">Типы компонентов / SMD</option>
          <option value="Основы пайки / Начинающим">Основы пайки / Начинающим</option>
        </select>
        <button class="btn btn--primary" onclick="importFirecrawlData()">⚡ Разобрать и добавить сведения</button>
      </div>
      <textarea id="fcContent" placeholder="Вставьте сюда текст страницы из Firecrawl (Markdown страницы или JSON результат скрейпинга)..." rows="4" style="width:100%;padding:8px 10px;border:1px solid var(--border);font-family:var(--mono);font-size:12px;resize:vertical;"></textarea>
      <div id="fcResult" style="display:none;margin-top:8px;padding:8px 12px;background:#F0FDF4;border:1px solid #86EFAC;font-size:12px;color:#166534;"></div>
    </div>

    <div class="data-table-container" style="margin-bottom:24px;">
      <table class="data-table">
        <thead>
          <tr>
            <th>Домен</th>
            <th>Сила</th>
            <th>Главные SEO-слова</th>
            <th>Обнаруженные фишки</th>
            <th>Статей</th>
            <th>Тип / Ниша</th>
            <th>Действие</th>
          </tr>
        </thead>
        <tbody id="competitorsBody"></tbody>
      </table>
    </div>

    <h3 style="margin:20px 0 10px;font-size:15px;font-weight:800;">Новые сайты-кандидаты (competitors_proposed)</h3>
    <div class="data-table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>Домен</th>
            <th>Ниша</th>
            <th>Частота в поиске</th>
            <th>Статей</th>
            <th>Пример URL</th>
            <th>Статус</th>
            <th>Действие</th>
          </tr>
        </thead>
        <tbody id="proposedCompetitorsBody"></tbody>
      </table>
    </div>
  </section>

  <!-- TAB 5: AGENT CENTER -->
  <section class="tab-view" id="view-agents">
    <div style="margin-bottom:16px;">
      <h2 style="margin:0;font-size:17px;font-weight:800;">Центр запуска субагентов</h2>
      <div style="font-size:12px;color:var(--muted);">Каждый агент решает строгую инженерную задачу по стандартам MASTER_EDITORIAL_BOOK.md.</div>
    </div>

    <div class="agents-grid">
      <!-- Agent 00 -->
      <div class="agent-card">
        <div class="agent-card__header">
          <h3 class="agent-card__title">00-research</h3>
          <span class="prio-pill prio-pill--cold">Perplexity · 0 ₽</span>
        </div>
        <div class="agent-card__desc">Скан конкурентов, живой поиск доменов через Perplexity Sonar и расчёт матрицы приоритетов 0–100.</div>
        <div class="agent-card__meta">Модель: Perplexity Sonar / Python CLI</div>
        <button class="btn btn--primary" onclick="runAgent('00-research')">▶ Запустить разведку</button>
      </div>

      <!-- Bot Analyzer -->
      <div class="agent-card">
        <div class="agent-card__header">
          <h3 class="agent-card__title">bot-analyzer</h3>
          <span class="prio-pill prio-pill--hot">Фишки & SEO</span>
        </div>
        <div class="agent-card__desc">Парсит сайты конкурентов, находит калькуляторы, таблицы, схемы, извлекает SEO-слова и считает силу домена.</div>
        <div class="agent-card__meta">Модель: HTML/LSI Parser · Стоимость: 0 токенов</div>
        <button class="btn btn--primary" onclick="runAgent('bot-analyzer')">▶ Анализировать фишки</button>
      </div>

      <!-- Agent 01 -->
      <div class="agent-card">
        <div class="agent-card__header">
          <h3 class="agent-card__title">01-researcher</h3>
          <span class="prio-pill prio-pill--cold">0 ₽ · Zen</span>
        </div>
        <div class="agent-card__desc">Сбор LSI, формулировка интента, выбор типа статьи, драфты FAQ 4–6 шт. и паспорт фактов по ГОСТ/IPC.</div>
        <div class="agent-card__meta">Модель: OpenCode Zen / freetap</div>
        <button class="btn" onclick="promptAgentTarget('01-researcher')">▶ Подготовить бриф (#ID)</button>
      </div>

      <!-- Agent 02: Writer Claude Sonnet Antigravity -->
      <div class="agent-card" style="border:2px solid var(--border);">
        <div class="agent-card__header">
          <h3 class="agent-card__title">02-writer (Practico)</h3>
          <span class="prio-pill prio-pill--hot">Claude Sonnet</span>
        </div>
        <div class="agent-card__desc">Написание статьи Вашим голосом Practico (триада, ритм, сухая ирония §2.4, Answer First ≤40 слов, паттерны theme4).</div>
        <div class="agent-card__meta">Модель: claude-sonnet-4-6 (Antigravity CLI)</div>
        <button class="btn btn--primary" onclick="promptAgentTarget('02-writer')">▶ Написать статью (#ID)</button>
      </div>

      <!-- Agent 03: Reviewer Claude Opus Antigravity -->
      <div class="agent-card" style="border:2px solid var(--border);">
        <div class="agent-card__header">
          <h3 class="agent-card__title">03-reviewer (ОТК)</h3>
          <span class="prio-pill prio-pill--warm">Claude Opus</span>
        </div>
        <div class="agent-card__desc">Независимый аудит Claude Opus: сверка температур по реестру, ТБ, отлов блокеров и упаковка по схеме JSON v1.1.</div>
        <div class="agent-card__meta">Модель: claude-opus-4-6-thinking (Antigravity CLI)</div>
        <button class="btn btn--primary" onclick="promptAgentTarget('03-reviewer')">▶ Провести аудит (#ID)</button>
      </div>

      <!-- Agent 04 -->
      <div class="agent-card">
        <div class="agent-card__header">
          <h3 class="agent-card__title">04-scorer</h3>
          <span class="prio-pill prio-pill--cold">0 ₽ · Local</span>
        </div>
        <div class="agent-card__desc">Математический расчёт 100 баллов по 6 блокам (Факты, Интент, Голос, SEO, Ссылки, ИИ) без субъективности.</div>
        <div class="agent-card__meta">Модель: score_article.py · Стоимость: 0 токенов</div>
        <button class="btn btn--primary" onclick="promptAgentTarget('04-scorer')">▶ Запустить скоринг (#ID)</button>
      </div>
    </div>

    <h3 style="margin:16px 0 6px;font-size:14px;font-weight:800;">Терминальный лог агентов:</h3>
    <div class="log-console" id="agentConsole">> Готов к выполнению задач. Выберите агента выше.</div>
  </section>

  <!-- TAB 6: BACKUPS -->
  <section class="tab-view" id="view-backups">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
      <div>
        <h2 style="margin:0;font-size:17px;font-weight:800;">Резервные копии и архивы БД</h2>
        <div style="font-size:12px;color:var(--muted);">Каждая операция защищена авто-бэкапом SQLite. Вы можете создать моментальный снимок в любой момент.</div>
      </div>
      <button class="btn btn--primary" onclick="createBackup()">💾 Создать резервную копию сейчас</button>
    </div>

    <div class="data-table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Имя архива</th>
            <th>Размер</th>
            <th>Количество тем</th>
            <th>Дата создания</th>
            <th>Путь на диске</th>
          </tr>
        </thead>
        <tbody id="backupsBody"></tbody>
      </table>
    </div>
  </section>

</div>

<!-- Modal Inspector -->
<div class="modal-overlay" id="inspectorModal" onclick="closeInspector(event)">
  <div class="modal-content" onclick="event.stopPropagation()">
    <button class="modal-close" onclick="closeInspector()">✕</button>
    <div style="border-bottom:2px solid var(--border);padding-bottom:12px;margin-bottom:14px;">
      <div style="font-family:var(--mono);font-size:11px;color:var(--muted);">
        <span id="mId">#15</span> · <span id="mCluster">Материалы</span> · статус: <strong id="mStatus">Готово</strong> · <span id="mChars">0</span> знаков
      </div>
      <h2 id="mTitle" style="margin:4px 0 0;font-size:17px;font-weight:800;">Название статьи</h2>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
      <h3 style="margin:0;font-size:14px;">Инженерная оценка качества (0–100):</h3>
      <span id="mTotalScore" style="font-family:var(--mono);font-size:18px;font-weight:800;padding:2px 8px;border:1px solid #111;">—</span>
    </div>

    <div id="mScoreGrid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:14px;"></div>

    <div id="mBlockersBox" style="display:none;background:#FEE2E2;border:1.5px solid #B91C1C;padding:10px;margin-bottom:10px;">
      <strong style="color:#B91C1C;">❌ КРИТИЧЕСКИЕ БЛОКЕРЫ:</strong>
      <ul id="mBlockersList" style="margin:4px 0 0;padding-left:18px;font-size:12px;color:#7F1D1D;"></ul>
    </div>

    <div id="mIssuesBox" style="display:none;background:#FEF9C3;border:1.5px solid #A16207;padding:10px;margin-bottom:10px;">
      <strong style="color:#854D0E;">⚠️ Замечания к доработке (требуются правки):</strong>
      <ul id="mIssuesList" style="margin:4px 0 0;padding-left:18px;font-size:12px;color:#713F12;"></ul>
    </div>

    <div style="border-top:1px dashed var(--border);padding-top:10px;margin-top:10px;">
      <h4 style="margin:0 0 6px;font-size:13px;">Перелинковка темы:</h4>
      <div id="mLinksText" style="font-size:12px;color:var(--muted);">—</div>
    </div>

    <div style="display:flex;gap:10px;margin-top:18px;padding-top:14px;border-top:2px solid var(--border);flex-wrap:wrap;">
      <button class="btn btn--success" onclick="approveArticle()">✓ Утвердить к публикации (ОТК)</button>
      <button class="btn btn--danger" onclick="rejectArticle()">↺ Вернуть автору на доработку</button>
      <button class="btn btn--primary" onclick="scoreCurrentArticle()">⚡ Пересчитать оценку (0-100)</button>
      <button class="btn" onclick="closeInspector()">Закрыть</button>
    </div>
  </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
let DB_DATA = {json_blob};
let CURRENT_TOPIC_ID = null;

function switchTab(name) {{
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.tab-view').forEach(v => v.classList.remove('active'));
  event.currentTarget.classList.add('active');
  document.getElementById('view-' + name).classList.add('active');
}}

function showToast(msg) {{
  const t = document.getElementById('toast');
  t.innerText = msg;
  t.style.display = 'block';
  setTimeout(() => {{ t.style.display = 'none'; }}, 3500);
}}

async function apiPost(route, payload) {{
  try {{
    const res = await fetch('http://127.0.0.1:8765' + route, {{
      method: 'POST',
      headers: {{ 'Content-Type': 'application/json' }},
      body: JSON.stringify(payload || {{}})
    }});
    return await res.json();
  }} catch (e) {{
    return null;
  }}
}}

async function refreshData() {{
  try {{
    const res = await fetch('http://127.0.0.1:8765/api/data');
    if (res.ok) {{
      DB_DATA = await res.json();
      renderAll();
      showToast('✓ Данные обновлены из SQLite БД');
      return;
    }}
  }} catch (e) {{}}
  renderAll();
  showToast('✓ Интерфейс перерисован');
}}

async function createBackup() {{
  showToast('Создание бэкапа базы данных...');
  const res = await apiPost('/api/backup', {{}});
  if (res && res.status === 'ok') {{
    showToast(`✓ Бэкап создан: ${{res.backup.filename}} (${{Math.round(res.backup.size_bytes/1024)}} КБ)`);
    await refreshData();
  }} else {{
    const blob = new Blob([JSON.stringify(DB_DATA, null, 2)], {{ type: 'application/json' }});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `seo_backup_${{new Date().toISOString().slice(0,10)}}.json`;
    a.click();
    showToast('✓ Экспорт данных скачан (офлайн JSON бэкап)');
  }}
}}

async function approveTopic(id) {{
  if (!confirm(`Одобрить тему ${{id}} и перенести в рабочий бэклог?`)) return;
  const res = await apiPost('/api/topics/approve', {{ id }});
  if (res && res.status === 'ok') {{
    showToast(`✓ Тема одобрена! Присвоен новый ID #${{res.new_topic_id}}`);
    await refreshData();
  }}
}}

async function rejectTopic(id) {{
  await apiPost('/api/topics/reject', {{ id }});
  const item = DB_DATA.topics_proposed.find(t => t.id === id);
  if (item) item.status = 'отклонён';
  renderAll();
  showToast('Тема отклонена.');
}}

async function approveCompetitor(domain) {{
  await apiPost('/api/competitors/approve', {{ domain }});
  showToast(`✓ Конкурент ${{domain}} добавлен в список мониторинга!`);
  await refreshData();
}}

async function approveArticle() {{
  if (!CURRENT_TOPIC_ID) return;
  await apiPost('/api/articles/approve', {{ id: CURRENT_TOPIC_ID }});
  showToast(`✓ Статья #${{CURRENT_TOPIC_ID}} утверждена к публикации! Перенесена во вкладку «Готовые к выгрузке».`);
  closeInspector();
  await refreshData();
}}

async function rejectArticle() {{
  if (!CURRENT_TOPIC_ID) return;
  const reason = prompt('Укажите замечания для автора Claude Sonnet:') || 'Требуется доработка текста';
  await apiPost('/api/articles/reject', {{ id: CURRENT_TOPIC_ID, reason }});
  showToast(`Статья #${{CURRENT_TOPIC_ID}} возвращена на доработку автору.`);
  closeInspector();
  await refreshData();
}}

async function scoreCurrentArticle() {{
  if (!CURRENT_TOPIC_ID) return;
  showToast(`Запуск скоринга статьи #${{CURRENT_TOPIC_ID}}...`);
  const res = await apiPost('/api/agent/run', {{ agent: '04-scorer', target_id: CURRENT_TOPIC_ID }});
  if (res && res.log) {{
    document.getElementById('agentConsole').innerText = res.log;
    showToast('✓ Скоринг завершён!');
    await refreshData();
    openInspector(CURRENT_TOPIC_ID);
  }}
}}

async function importFirecrawlData() {{
  const content = document.getElementById('fcContent').value.trim();
  const domain = document.getElementById('fcDomain').value.trim();
  const cluster = document.getElementById('fcCluster').value;
  const resBox = document.getElementById('fcResult');

  if (!content) {{
    alert('Пожалуйста, вставьте текст выгрузки из Firecrawl!');
    return;
  }}

  showToast('Разбор выгрузки Firecrawl...');
  const res = await apiPost('/api/import/firecrawl', {{ content, domain, cluster }});
  if (res && res.status === 'ok') {{
    resBox.style.display = 'block';
    resBox.innerHTML = `
      <strong>✓ Успешно разобрано и добавлено в БД!</strong><br>
      • Статей сохранено: <strong>${{res.saved_articles}}</strong><br>
      • Обнаруженные фишки: <strong>${{res.features.length ? res.features.join(', ') : 'базовый контент'}}</strong><br>
      • Извлечённые SEO-слова: <strong>${{res.keywords.length ? res.keywords.join(', ') : '—'}}</strong>
    `;
    document.getElementById('fcContent').value = '';
    showToast(`✓ Сведения по сайту ${{res.domain}} добавлены в базу!`);
    await refreshData();
  }} else {{
    alert('Ошибка при импорте выгрузки Firecrawl.');
  }}
}}

async function exportSingleArticle(id) {{
  showToast(`Выгрузка черновика статьи #${{id}} в WordPress...`);
  const c = document.getElementById('agentConsole');
  c.innerText = `[${{new Date().toLocaleTimeString()}}] Запуск delivery_cli.py для статьи #${{id}}...`;
  switchTab('agents');
  const res = await apiPost('/api/agent/run', {{ agent: 'delivery', target_id: id }});
  if (res && res.log) {{
    c.innerText = res.log;
    showToast(`✓ Черновик #${{id}} передан в WordPress! Подтвердите ОТК в админке.`);
  }}
}}

async function exportAllReady() {{
  showToast('Выгрузка всех готовых черновиков в WordPress...');
  switchTab('agents');
  runAgent('delivery-all');
}}

async function runAgent(agentName) {{
  showToast(`Запуск агента ${{agentName}}...`);
  const c = document.getElementById('agentConsole');
  c.innerText = `[${{new Date().toLocaleTimeString()}}] Запуск ${{agentName}}...\nОжидание ответа...`;
  const res = await apiPost('/api/agent/run', {{ agent: agentName }});
  if (res && res.log) {{
    c.innerText = res.log;
    showToast(`✓ Агент ${{agentName}} завершил работу!`);
    await refreshData();
  }}
}}

function promptAgentTarget(agentName) {{
  const tid = prompt(`Введите ID статьи для ${{agentName}}:`);
  if (!tid) return;
  if (agentName === '04-scorer') {{
    CURRENT_TOPIC_ID = tid;
    scoreCurrentArticle();
  }} else {{
    runAgent(agentName);
  }}
}}

// Render Decision Alert Banner
function renderDecisionBanner() {{
  const banner = document.getElementById('decisionBanner');
  
  // 1. Статьи требующие правок (score < 80 and chars > 500)
  const needsFix = DB_DATA.topics.filter(t => (t.chars || 0) > 500 && (t.score_total === null || t.score_total < 80) && t.status !== 'ready' && t.status !== 'published');
  
  // 2. Статьи ожидающие утверждения шеф-редактором (score >= 80 and status != ready and status != published)
  const readyForApproval = DB_DATA.topics.filter(t => (t.score_total || 0) >= 80 && t.status !== 'ready' && t.status !== 'published');
  
  // 3. Предложенные темы ожидающие решения
  const proposedWaiting = DB_DATA.topics_proposed.filter(t => t.status === 'предложен');

  let fixHtml = needsFix.map(t => `
    <li>
      <strong>#${{t.id}}</strong> ${{t.title.slice(0, 36)}}… 
      <span style="color:#B91C1C;font-weight:700;">(${{t.score_total || '—'}}/100)</span>
      <a href="javascript:void(0)" onclick="openInspector(${{t.id}})" style="font-size:11px;margin-left:4px;">правки ↗</a>
    </li>
  `).join('');

  let approveHtml = readyForApproval.map(t => `
    <li>
      <strong>#${{t.id}}</strong> ${{t.title.slice(0, 36)}}… 
      <span style="color:#059669;font-weight:700;">(${{t.score_total}}/100)</span>
      <a href="javascript:void(0)" onclick="openInspector(${{t.id}})" style="font-size:11px;margin-left:4px;">утвердить ↗</a>
    </li>
  `).join('');

  let propHtml = proposedWaiting.slice(0, 3).map(t => `
    <li>
      <span class="prio-pill prio-pill--warm">P${{t.priority_score}}</span>
      ${{t.title.slice(0, 38)}}…
    </li>
  `).join('');

  banner.innerHTML = `
    <div class="decision-col">
      <div class="decision-col__title" style="color:#B91C1C;">
        ⚠️ Требуются правки автора (${{needsFix.length}})
      </div>
      <ul class="decision-col__list">
        ${{fixHtml || '<li style="color:var(--muted);">Нет замечаний, все черновики в норме</li>'}}
      </ul>
    </div>

    <div class="decision-col">
      <div class="decision-col__title" style="color:#059669;">
        🛡️ Готово к Вашему утверждению (${{readyForApproval.length}})
      </div>
      <ul class="decision-col__list">
        ${{approveHtml || '<li style="color:var(--muted);">Нет новых статей на приёмку</li>'}}
      </ul>
    </div>

    <div class="decision-col">
      <div class="decision-col__title" style="color:#D97706;">
        💡 Темы из пробелов на одобрение (${{proposedWaiting.length}})
      </div>
      <ul class="decision-col__list">
        ${{propHtml || '<li style="color:var(--muted);">Все предложенные темы обработаны</li>'}}
      </ul>
      <div style="margin-top:6px;font-size:11px;">
        <a href="javascript:void(0)" onclick="switchTab('gaps')">Перейти к списку тем (${{proposedWaiting.length}}) →</a>
      </div>
    </div>
  `;
}}

// Render Export Tab (Ready for WordPress)
function renderExportTab() {{
  const tb = document.getElementById('exportTableBody');
  tb.innerHTML = '';
  
  const readyTopics = DB_DATA.topics.filter(t => t.status === 'ready' || (t.score_total >= 80 && (t.chars || 0) > 1000));
  document.getElementById('bCountExport').innerText = readyTopics.length;

  if (readyTopics.length === 0) {{
    tb.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:24px;color:var(--muted);">Пока нет статей, утверждённых к выгрузке. Утвердите статьи с оценкой ≥80 в канбане.</td></tr>';
    return;
  }}

  readyTopics.forEach(t => {{
    tb.innerHTML += `
      <tr>
        <td style="font-family:var(--mono);font-weight:700;">#${{t.id}}</td>
        <td><span class="score-pill score-pill--high">${{t.score_total || 80}}/100</span></td>
        <td><strong>${{t.title}}</strong></td>
        <td><code style="font-size:11px;">${{t.slug}}</code></td>
        <td><span class="task-card__cluster">${{t.cluster}}</span></td>
        <td style="font-family:var(--mono);">${{t.chars || '—'}}</td>
        <td><span class="prio-pill prio-pill--warm">Valid v1.1</span></td>
        <td>
          <span style="color:#059669;font-weight:700;font-size:11px;">
            ${{t.status === 'ready' ? '🛡️ Утверждено к ОТК' : 'Готово к утверждению'}}
          </span>
        </td>
        <td>
          <button class="btn btn--sm btn--primary" onclick="exportSingleArticle(${{t.id}})">🚀 Выгрузить в WP</button>
        </td>
      </tr>
    `;
  }});
}}

// Kanban Renderer
const COLUMNS = [
  {{ code: 'backlog', title: '1. Бэклог' }},
  {{ code: 'work', title: '2. В работе' }},
  {{ code: 'written', title: '3. Написано' }},
  {{ code: 'review', title: '4. На ревью' }},
  {{ code: 'ready', title: '5. Готово к ОТК' }},
  {{ code: 'published', title: '6. Опубликовано' }}
];

const STATUS_COL = {{
  'backlog': 'backlog', 'brief': 'work', 'brief_ready': 'work', 'writing': 'work',
  'written': 'written', 'review': 'review', 'scored': 'review', 'ready': 'ready',
  'published': 'published', 'blocked': 'work'
}};

function renderKanban() {{
  const board = document.getElementById('kanbanBoard');
  board.innerHTML = '';

  const clusterFilter = document.getElementById('clusterSelect').value.toLowerCase();
  const searchFilter = document.getElementById('kanbanSearch').value.toLowerCase().trim();

  COLUMNS.forEach(col => {{
    const colDiv = document.createElement('div');
    colDiv.className = 'board-column';

    let colCardsHtml = '';
    let count = 0;

    DB_DATA.topics.forEach(t => {{
      const cCode = STATUS_COL[t.status] || 'backlog';
      if (cCode !== col.code) return;

      if (clusterFilter && (t.cluster || '').toLowerCase() !== clusterFilter) return;
      if (searchFilter && !t.title.toLowerCase().includes(searchFilter) && !('#' + t.id).includes(searchFilter)) return;

      count++;
      const pScore = t.priority_score || 50;
      const pClass = pScore >= 90 ? 'prio-pill--hot' : (pScore >= 75 ? 'prio-pill--warm' : 'prio-pill--cold');

      let scoreBadge = '';
      let warnBlock = '';

      if (t.score_total !== null && t.score_total !== undefined) {{
        const sClass = t.score_total >= 80 ? 'score-pill--high' : (t.score_total >= 60 ? 'score-pill--mid' : 'score-pill--low');
        scoreBadge = `<span class="score-pill ${{sClass}}">${{t.score_total}}/100</span>`;
        if (t.score_total < 80 && (t.chars || 0) > 500) {{
          warnBlock = `<div class="task-card__warn">⚠️ Требуются правки: ${{t.score_data && t.score_data.issues ? t.score_data.issues[0] : 'доработать текст'}}</div>`;
        }}
      }} else if ((t.chars || 0) > 500) {{
        scoreBadge = `<span class="score-pill score-pill--mid">текст есть</span>`;
      }}

      colCardsHtml += `
        <article class="task-card" onclick='openInspector(${{t.id}})'>
          <div class="task-card__top">
            <span class="task-card__id">#${{t.id}}</span>
            <div class="task-card__badges">
              <span class="prio-pill ${{pClass}}">P${{pScore}}</span>
              ${{scoreBadge}}
            </div>
          </div>
          <h3 class="task-card__title">${{t.title}}</h3>
          <div class="task-card__footer">
            <span class="task-card__cluster">${{(t.cluster || '').slice(0, 22)}}</span>
            <span>🔗 ${{ (t.out_links || []).length + (t.in_links || []).length }}</span>
          </div>
          ${{warnBlock}}
        </article>
      `;
    }});

    colDiv.innerHTML = `
      <div class="board-column__header">
        <span class="board-column__title">${{col.title}}</span>
        <span class="board-column__badge">${{count}}</span>
      </div>
      <div class="board-column__cards">
        ${{colCardsHtml || '<div style="color:var(--muted);font-size:12px;text-align:center;padding:20px 0;">—</div>'}}
      </div>
    `;
    board.appendChild(colDiv);
  }});
}}

function filterKanban() {{
  renderKanban();
}}

// Render Proposed Topics
function renderProposedTopics() {{
  const tb = document.getElementById('proposedTopicsBody');
  tb.innerHTML = '';
  DB_DATA.topics_proposed.forEach(t => {{
    const isAccepted = t.status === 'принят';
    const isRejected = t.status === 'отклонён';
    tb.innerHTML += `
      <tr>
        <td style="font-family:var(--mono);font-weight:700;">${{t.id}}</td>
        <td><span class="prio-pill prio-pill--warm">P${{t.priority_score}}</span></td>
        <td><strong>${{t.title}}</strong></td>
        <td><span class="task-card__cluster">${{t.cluster}}</span></td>
        <td>${{t.type || 'гайд'}}</td>
        <td style="font-size:12px;color:var(--muted);">${{t.evidence || t.source || '—'}}</td>
        <td>
          <span style="font-weight:700;color:${{isAccepted ? '#059669' : (isRejected ? '#DC2626' : '#D97706')}}">
            ${{t.status}}
          </span>
        </td>
        <td>
          ${{!isAccepted && !isRejected ? `
            <button class="btn btn--sm btn--success" onclick="approveTopic('${{t.id}}')">✓ Одобрить</button>
            <button class="btn btn--sm btn--danger" onclick="rejectTopic('${{t.id}}')">✕</button>
          ` : '—'}}
        </td>
      </tr>
    `;
  }});
}}

// Render Competitors
function renderCompetitors() {{
  const tb = document.getElementById('competitorsBody');
  tb.innerHTML = '';
  DB_DATA.competitors.forEach(c => {{
    tb.innerHTML += `
      <tr>
        <td><strong>${{c.domain}}</strong><br><a href="${{c.blog_url || 'https://' + c.domain}}" target="_blank" style="font-size:11px;color:var(--muted);">перейти ↗</a></td>
        <td><span class="prio-pill ${{c.score >= 70 ? 'prio-pill--hot' : 'prio-pill--cold'}}">${{c.score || 50}}/100</span></td>
        <td style="font-size:12px;color:#065F46;max-width:240px;">${{c.seo_keywords || 'Общая электроника'}}</td>
        <td style="font-size:12px;max-width:280px;">${{c.features || 'Базовый контент'}}</td>
        <td style="font-family:var(--mono);">${{c.articles_count || 0}}</td>
        <td style="font-size:12px;color:var(--muted);">${{c.type}}</td>
        <td>
          <button class="btn btn--sm btn--primary" onclick="runAgent('bot-analyzer')">Анализ фишек</button>
        </td>
      </tr>
    `;
  }});

  const ptb = document.getElementById('proposedCompetitorsBody');
  ptb.innerHTML = '';
  DB_DATA.competitors_proposed.forEach(c => {{
    const isAccepted = c.status === 'принят';
    ptb.innerHTML += `
      <tr>
        <td><strong>${{c.domain}}</strong></td>
        <td>${{c.niche || '—'}}</td>
        <td>${{c.hits || 1}} запросов</td>
        <td>${{c.articles_count || 0}}</td>
        <td><a href="${{c.example_url || '#'}}" target="_blank" style="font-size:11px;">${{(c.example_url || '').slice(0,35)}}</a></td>
        <td><span style="font-weight:700;color:${{isAccepted ? '#059669' : '#D97706'}}">${{c.status}}</span></td>
        <td>
          ${{!isAccepted ? `<button class="btn btn--sm btn--success" onclick="approveCompetitor('${{c.domain}}')">✓ Добавить</button>` : '—'}}
        </td>
      </tr>
    `;
  }});
}}

// Render Backups
function renderBackups() {{
  const tb = document.getElementById('backupsBody');
  tb.innerHTML = '';
  DB_DATA.backups.forEach((b, idx) => {{
    tb.innerHTML += `
      <tr>
        <td style="font-family:var(--mono);">${{idx + 1}}</td>
        <td><strong>${{b.filename}}</strong></td>
        <td style="font-family:var(--mono);">${{Math.round(b.size_bytes / 1024)}} КБ</td>
        <td>${{b.records_count}} тем</td>
        <td style="font-size:12px;color:var(--muted);">${{b.created_at}}</td>
        <td style="font-family:var(--mono);font-size:11px;color:var(--muted);">${{b.filepath}}</td>
      </tr>
    `;
  }});
}}

// Open Inspector Modal
function openInspector(topicId) {{
  CURRENT_TOPIC_ID = topicId;
  const t = DB_DATA.topics.find(x => x.id === topicId);
  if (!t) return;

  document.getElementById('mId').innerText = '#' + t.id;
  document.getElementById('mCluster').innerText = t.cluster;
  document.getElementById('mStatus').innerText = t.status;
  document.getElementById('mChars').innerText = (t.chars || 0) + ' знаков';
  document.getElementById('mTitle').innerText = t.title;

  const scoreEl = document.getElementById('mTotalScore');
  const grid = document.getElementById('mScoreGrid');
  const bBox = document.getElementById('mBlockersBox');
  const bList = document.getElementById('mBlockersList');
  const iBox = document.getElementById('mIssuesBox');
  const iList = document.getElementById('mIssuesList');

  if (t.score_total !== null && t.score_total !== undefined) {{
    scoreEl.innerText = t.score_total + ' / 100';
    scoreEl.style.background = t.score_total >= 80 ? '#A7F3D0' : (t.score_total >= 60 ? '#FEF08A' : '#FECACA');
  }} else {{
    scoreEl.innerText = 'Не оценено';
    scoreEl.style.background = '#E5E5E5';
  }}

  grid.innerHTML = '';
  const sData = t.score_data;
  if (sData && sData.blocks) {{
    for (const [k, v] of Object.entries(sData.blocks)) {{
      grid.innerHTML += `<div style="background:var(--bg);border:1px solid var(--border);padding:8px 10px;font-size:12px;"><strong>${{v.points}} / ${{v.max}}</strong><span>${{k}}</span></div>`;
    }}
  }} else {{
    grid.innerHTML = '<div style="grid-column:1/-1;padding:8px;background:var(--bg);border:1px solid var(--border);">Текст ещё не проходил скоринг. Нажмите «Пересчитать оценку» ниже.</div>';
  }}

  if (sData && sData.blockers && sData.blockers.length > 0) {{
    bBox.style.display = 'block';
    bList.innerHTML = sData.blockers.map(b => `<li>${{b}}</li>`).join('');
  }} else {{
    bBox.style.display = 'none';
  }}

  if (sData && sData.issues && sData.issues.length > 0) {{
    iBox.style.display = 'block';
    iList.innerHTML = sData.issues.map(i => `<li>${{i}}</li>`).join('');
  }} else {{
    iBox.style.display = 'none';
  }}

  const linksEl = document.getElementById('mLinksText');
  let linksStr = '';
  if (t.out_links && t.out_links.length > 0) {{
    linksStr += '<strong>Исходящие:</strong> ' + t.out_links.map(l => `#${{l.target_id}} (${{l.anchor || 'ссылка'}})`).join(', ') + '<br>';
  }}
  if (t.in_links && t.in_links.length > 0) {{
    linksStr += '<strong>Входящие:</strong> ' + t.in_links.map(l => `#${{l.source_id}}`).join(', ');
  }}
  linksEl.innerHTML = linksStr || 'Связи не заданы';

  document.getElementById('inspectorModal').classList.add('active');
}}

function closeInspector(e) {{
  if (e && e.target !== e.currentTarget && !e.target.classList.contains('modal-close')) return;
  document.getElementById('inspectorModal').classList.remove('active');
}}

function toggleNewTopicBox() {{
  const box = document.getElementById('newTopicBox');
  box.style.display = box.style.display === 'none' ? 'block' : 'none';
  if (box.style.display === 'block') {{
    document.getElementById('newTopicTitle').focus();
  }}
}}

async function createNewTopicFromInput() {{
  const title = document.getElementById('newTopicTitle').value.trim();
  const cluster = document.getElementById('newTopicCluster').value;
  const type = document.getElementById('newTopicType').value;
  const priority = document.getElementById('newTopicPriority').value;
  const notes = document.getElementById('newTopicNotes').value.trim();

  if (!title) {{
    alert('Пожалуйста, введите название темы!');
    return;
  }}

  showToast('Добавление темы в бэклог...');
  const res = await apiPost('/api/topics/create', {{ title, cluster, type, priority, notes }});
  if (res && res.status === 'ok') {{
    showToast(`✓ Тема #${{res.id}} добавлена в бэклог!`);
    document.getElementById('newTopicTitle').value = '';
    document.getElementById('newTopicNotes').value = '';
    document.getElementById('newTopicBox').style.display = 'none';
    await refreshData();
  }} else {{
    alert('Ошибка при добавлении темы.');
  }}
}}

function renderAll() {{
  const sel = document.getElementById('clusterSelect');
  sel.innerHTML = '<option value="">Все кластеры</option>';
  DB_DATA.clusters.forEach(c => {{
    sel.innerHTML += `<option value="${{c.name.toLowerCase()}}">${{c.name}}</option>`;
  }});

  const nSel = document.getElementById('newTopicCluster');
  if (nSel) {{
    nSel.innerHTML = '';
    DB_DATA.clusters.forEach(c => {{
      nSel.innerHTML += `<option value="${{c.name}}">${{c.name}}</option>`;
    }});
  }}

  document.getElementById('statTopicsCount').innerText = DB_DATA.counts.topics;
  document.getElementById('statWrittenCount').innerText = DB_DATA.counts.written;
  document.getElementById('bCountTopics').innerText = DB_DATA.counts.topics;
  document.getElementById('bCountProposed').innerText = DB_DATA.counts.proposed_topics;
  document.getElementById('bCountComps').innerText = DB_DATA.counts.competitors;
  document.getElementById('bCountBackups').innerText = DB_DATA.counts.backups;

  renderDecisionBanner();
  renderExportTab();
  renderKanban();
  renderProposedTopics();
  renderCompetitors();
  renderBackups();
}}

document.addEventListener('DOMContentLoaded', () => {{
  renderAll();
  fetch('http://127.0.0.1:8765/api/status')
    .then(r => r.json())
    .then(() => refreshData())
    .catch(() => {{}});
}});

document.addEventListener('keydown', e => {{
  if (e.key === 'Escape') closeInspector();
}});
</script>
</body>
</html>"""
    return html


def main() -> None:
    DASH_DIR.mkdir(parents=True, exist_ok=True)
    out_file = DASH_DIR / "index.html"
    out_file.write_text(generate_html(), encoding="utf-8")
    print(f"✓ UI успешно скомпилирован в {out_file} ({out_file.stat().st_size} байт)")


if __name__ == "__main__":
    main()
