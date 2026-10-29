"""Компилятор интерактивного UI дашборда «Точка Плавления» v3.3.

Архитектура:
1. Интерактивная линейка этапов (Pipeline Stepper) с динамическими счетчиками из БД.
2. Канбан-доска: распределение карточек строго по данным (текст, балл, статус):
   - 1. Бэклог (идеи без черновика)
   - 2. В работе (бриф/сбор фактов)
   - 3. Требует правок (есть текст, балл < 80)
   - 4. На ревью (ОТК) (есть текст, балл >= 80, ждёт приёмки)
   - 5. Готово к выгрузке (утверждено шеф-редактором)
   - 6. В WordPress (опубликовано)
3. Фиксированная высота колонок с плавным независимым скроллом (без растягивания страницы).
4. Защита от затирания готовых текстов статей при повторном запуске автора.
5. Русские статусы, исправление опечатки «знаков знаков».
6. Контекстные кнопки действий на карточках и в модальном инспекторе.
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

    template = """<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Точка Плавления — SEO Конвейер & Публикации v3.3</title>
<style>
  :root {
    /* Teenage Engineering Industrial Palette */
    --color-canvas-mist: #F6F8F7;
    --color-ink-black: #000000;
    --color-carbon: #0F0E12;
    --color-steel-gray: #E5E5E5;
    --color-graphite: #737373;
    --color-amber: #FFC700;
    --color-amber-light: #FFF4C2;
    --color-pure-white: #FFFFFF;

    /* Semantic tokens */
    --bg: var(--color-canvas-mist);
    --card: var(--color-pure-white);
    --ink: var(--color-ink-black);
    --muted: var(--color-graphite);
    --accent: var(--color-amber);
    --accent-light: var(--color-amber-light);
    --border: var(--color-ink-black);
    --border-hairline: var(--color-steel-gray);
    --dark-surface: var(--color-carbon);

    /* Functional alerts (kept rationed) */
    --success: #059669;
    --success-bg: #ECFDF5;
    --warning: #D97706;
    --warning-bg: #FFFBEB;
    --danger: #DC2626;
    --danger-bg: #FEF2F2;

    --font: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    --mono: "JetBrains Mono", Consolas, monospace;
    --radius: 0px;
  }
  * {
    box-sizing: border-box;
    border-radius: 0 !important;
  }
  body {
    margin: 0;
    background: var(--bg);
    color: var(--ink);
    font: 13.5px/1.45 var(--font);
    -webkit-font-smoothing: antialiased;
  }
  .app-container {
    max-width: 1560px;
    margin: 0 auto;
    padding: 14px 20px 80px;
  }

  /* Top Bar */
  .brand-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--card);
    border: 1.5px solid var(--border);
    padding: 10px 14px;
    margin-bottom: 12px;
    flex-wrap: wrap;
    gap: 12px;
  }
  .brand-bar__left h1 {
    margin: 0;
    font-size: 17px;
    font-weight: 800;
    letter-spacing: -0.02em;
    display: flex;
    align-items: center;
    gap: 8px;
    text-transform: uppercase;
  }
  .brand-bar__tag {
    background: var(--dark-surface);
    color: var(--color-canvas-mist);
    padding: 2px 7px;
    font-size: 10.5px;
    font-family: var(--mono);
    font-weight: 700;
    border: none;
    letter-spacing: 0.5px;
  }
  .brand-bar__actions {
    display: flex;
    gap: 8px;
    align-items: center;
  }

  /* Interactive Pipeline Stepper */
  .pipeline-stepper {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 8px;
    margin-bottom: 14px;
  }
  @media(max-width: 1024px) {
    .pipeline-stepper { grid-template-columns: repeat(3, 1fr); }
  }
  @media(max-width: 640px) {
    .pipeline-stepper { grid-template-columns: repeat(2, 1fr); }
  }
  .step-node {
    background: var(--card);
    border: 1px solid var(--border-hairline);
    padding: 8px 10px;
    cursor: pointer;
    transition: all 0.1s ease;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .step-node:hover {
    border-color: var(--border);
    background: #FAFAFA;
  }
  .step-node.active-filter {
    background: var(--accent-light);
    border: 1.5px solid var(--border);
  }
  .step-node__num {
    font-family: var(--mono);
    font-size: 10px;
    font-weight: 800;
    background: var(--dark-surface);
    color: var(--color-canvas-mist);
    padding: 2px 5px;
    line-height: 1;
  }
  .step-node__info {
    display: flex;
    flex-direction: column;
    min-width: 0;
  }
  .step-node__title {
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .step-node__meta {
    font-size: 11px;
    font-family: var(--mono);
    color: var(--muted);
  }

  /* Agent Status Strip */
  .agents-status-strip {
    display: flex;
    gap: 8px;
    background: #EAECEB;
    border: 1px solid var(--border-hairline);
    padding: 6px 12px;
    margin-bottom: 12px;
    flex-wrap: wrap;
    align-items: center;
    font-size: 12px;
  }
  .agent-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--card);
    padding: 3px 8px;
    border: 1px solid var(--border-hairline);
    font-family: var(--mono);
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.1s;
  }
  .agent-badge:hover {
    border-color: var(--border);
    background: #FAFAFA;
  }
  .status-dot {
    width: 6px;
    height: 6px;
    background: #9CA3AF;
  }
  .status-dot.active { background: var(--success); }

  /* Decision Alert Banner */
  .decision-banner {
    background: var(--card);
    border: 1.5px solid var(--border);
    padding: 10px 14px;
    margin-bottom: 14px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 12px;
  }
  .decision-col {
    border-right: 1px solid var(--border-hairline);
    padding-right: 12px;
  }
  .decision-col:last-child { border-right: none; }
  .decision-col__title {
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .decision-col__list {
    margin: 0;
    padding-left: 16px;
    font-size: 12px;
  }
  .decision-col__list li { margin-bottom: 3px; }

  /* Buttons */
  .btn {
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    cursor: pointer;
    border: 1.5px solid var(--border);
    background: var(--card);
    color: var(--ink);
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.1s;
    user-select: none;
  }
  .btn:hover {
    background: var(--dark-surface);
    color: #FFFFFF;
    border-color: var(--dark-surface);
  }
  .btn:active {
    transform: translateY(1px);
  }
  .btn--primary {
    background: var(--accent);
    color: #000000;
    border-color: var(--border);
  }
  .btn--primary:hover {
    background: #E5B200;
    color: #000000;
    border-color: var(--border);
  }
  .btn--success { background: var(--success-bg); color: #065F46; border-color: #065F46; }
  .btn--success:hover { background: #065F46; color: #FFFFFF; }
  .btn--warning { background: var(--warning-bg); color: #92400E; border-color: #92400E; }
  .btn--warning:hover { background: #92400E; color: #FFFFFF; }
  .btn--danger { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
  .btn--danger:hover { background: var(--danger); color: #FFFFFF; }
  .btn--sm { padding: 3px 8px; font-size: 11px; }
  .btn--xs { padding: 2px 6px; font-size: 10px; font-family: var(--mono); }

  /* Tabs Nav */
  .tabs-nav {
    display: flex;
    gap: 3px;
    border-bottom: 1.5px solid var(--border);
    margin-bottom: 14px;
    overflow-x: auto;
  }
  .tab-btn {
    padding: 7px 14px;
    background: #EAECEB;
    border: 1px solid var(--border-hairline);
    border-bottom: none;
    font-weight: 700;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    cursor: pointer;
    margin-bottom: -1.5px;
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
  }
  .tab-btn.active {
    background: var(--card);
    border: 1.5px solid var(--border);
    border-bottom: 2px solid var(--card);
  }
  .tab-badge {
    background: var(--dark-surface);
    color: var(--color-canvas-mist);
    padding: 1px 5px;
    font-size: 10px;
    font-family: var(--mono);
    border: none;
  }

  /* Tab View containers */
  .tab-view { display: none; }
  .tab-view.active { display: block; }

  /* Kanban Ergonomic Board */
  .kanban-board {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 8px;
    align-items: stretch;
  }
  @media(max-width: 1360px) {
    .kanban-board { grid-template-columns: repeat(3, 1fr); gap: 10px; }
  }
  @media(max-width: 768px) {
    .kanban-board { grid-template-columns: repeat(1, 1fr); }
  }

  .board-column {
    background: #F0F2F1;
    border: 1px solid var(--border-hairline);
    padding: 8px;
    display: flex;
    flex-direction: column;
    min-height: 560px;
    max-height: calc(100vh - 290px);
  }
  @media(max-width: 768px) {
    .board-column { max-height: none; min-height: 380px; }
  }
  .board-column__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    padding-bottom: 5px;
    border-bottom: 1.5px solid var(--border);
  }
  .board-column__title {
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  .board-column__badge {
    background: var(--dark-surface);
    color: var(--color-canvas-mist);
    border: none;
    padding: 1px 6px;
    font-size: 11px;
    font-family: var(--mono);
    font-weight: 700;
  }
  .board-column__cards {
    display: flex;
    flex-direction: column;
    gap: 6px;
    overflow-y: auto;
    overflow-x: hidden;
    flex: 1;
    padding-right: 3px;
    scrollbar-width: thin;
  }

  /* Cards */
  .task-card {
    background: var(--card);
    border: 1.5px solid var(--border);
    padding: 9px;
    cursor: pointer;
    transition: all 0.1s;
    display: flex;
    flex-direction: column;
    gap: 5px;
  }
  .task-card:hover {
    background: #FAFAFA;
    border-color: #000;
  }
  .task-card__top {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .task-card__id {
    font-family: var(--mono);
    font-size: 10.5px;
    font-weight: 700;
    color: var(--muted);
  }
  .task-card__badges {
    display: flex;
    gap: 4px;
    align-items: center;
  }
  .task-card__title {
    margin: 0;
    font-size: 12.5px;
    font-weight: 600;
    line-height: 1.35;
    word-break: break-word;
  }
  .task-card__footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11px;
    color: var(--muted);
    margin-top: 2px;
  }
  .task-card__cluster {
    background: #EAECEB;
    padding: 1px 5px;
    border: 1px solid var(--border-hairline);
    font-size: 10px;
    font-family: var(--mono);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 140px;
  }
  .task-card__action-row {
    margin-top: 4px;
    padding-top: 4px;
    border-top: 1px dashed var(--border-hairline);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 4px;
  }
  .task-card__warn {
    background: #FFF7ED;
    border: 1px solid #FDBA74;
    padding: 3px 5px;
    font-size: 10.5px;
    color: #9A3412;
    line-height: 1.25;
  }

  .prio-pill {
    font-family: var(--mono);
    font-size: 10px;
    font-weight: 700;
    padding: 1px 4px;
    border: 1px solid var(--border);
    line-height: 1.2;
  }
  .prio-pill--hot { background: var(--accent); color: #000; }
  .prio-pill--warm { background: #FFF3C4; color: #713F12; }
  .prio-pill--cold { background: #F0F2F1; color: #737373; border-color: var(--border-hairline); }

  .score-pill {
    font-family: var(--mono);
    font-size: 10px;
    font-weight: 700;
    padding: 1px 5px;
    border: 1px solid var(--border);
    line-height: 1.2;
  }
  .score-pill--high { background: #A7F3D0; color: #064E3B; }
  .score-pill--mid { background: #FEF08A; color: #713F12; }
  .score-pill--low { background: #FECACA; color: #7F1D1D; }

  /* Tables */
  .data-table-container {
    background: var(--card);
    border: 1.5px solid var(--border);
    overflow-x: auto;
  }
  .data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    text-align: left;
  }
  .data-table th {
    background: #EAECEB;
    border-bottom: 1.5px solid var(--border);
    padding: 8px 10px;
    font-weight: 800;
    text-transform: uppercase;
    font-size: 10.5px;
    letter-spacing: 0.5px;
    font-family: var(--mono);
  }
  .data-table td {
    padding: 8px 10px;
    border-bottom: 1px solid var(--border-hairline);
    vertical-align: middle;
  }
  .data-table tr:hover td { background: #FAFAFA; }

  /* Agent Grid */
  .agents-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
  }
  .agent-card {
    background: var(--card);
    border: 1.5px solid var(--border);
    padding: 14px;
  }
  .agent-card__header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
  .agent-card__title { font-size: 14px; font-weight: 800; margin: 0; text-transform: uppercase; letter-spacing: 0.3px; }
  .agent-card__desc { font-size: 12px; color: var(--muted); margin-bottom: 10px; min-height: 34px; line-height: 1.35; }
  .agent-card__meta { font-size: 11px; font-family: var(--mono); color: var(--muted); margin-bottom: 12px; }

  .log-console {
    background: var(--dark-surface);
    color: #34D399;
    font-family: var(--mono);
    font-size: 12px;
    padding: 12px;
    border: 1.5px solid var(--border);
    height: 180px;
    overflow-y: auto;
    white-space: pre-wrap;
    margin-top: 12px;
  }

  /* Modal */
  .modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 14, 18, 0.65);
    z-index: 999;
    align-items: center;
    justify-content: center;
    padding: 16px;
  }
  .modal-overlay.active { display: flex; }
  .modal-content {
    background: var(--card);
    border: 2px solid var(--border);
    max-width: 860px;
    width: 100%;
    max-height: 92vh;
    overflow-y: auto;
    padding: 20px 24px;
    position: relative;
  }
  .modal-close {
    position: absolute;
    top: 14px; right: 14px;
    border: 1.5px solid var(--border);
    background: var(--dark-surface);
    color: #FFFFFF;
    width: 28px; height: 28px;
    cursor: pointer;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    transition: background 0.1s;
  }
  .modal-close:hover {
    background: var(--accent);
    color: #000000;
  }

  .toast {
    position: fixed;
    bottom: 24px; right: 24px;
    background: var(--dark-surface);
    color: #FFFFFF;
    padding: 10px 16px;
    border: 1.5px solid var(--accent);
    font-size: 12px;
    font-family: var(--mono);
    font-weight: 600;
    z-index: 9999;
    display: none;
  }
</style>
</head>
<body>
<div class="app-container">

  <!-- Brand & Header -->
  <header class="brand-bar">
    <div class="brand-bar__left">
      <h1>ТОЧКА ПЛАВЛЕНИЯ <span class="brand-bar__tag">SEO КОНВЕЙЕР v3.3</span></h1>
      <div style="font-family:var(--mono);font-size:12px;background:var(--card);padding:3px 8px;border:1px solid var(--border);">
        ● База SQLite: <strong id="statTopicsCount">__STAT_TOPICS__</strong> тем · <strong id="statWrittenCount">__STAT_WRITTEN__</strong> с текстом
      </div>
    </div>
    <div class="brand-bar__actions">
      <button class="btn btn--primary" onclick="createBackup()">💾 Создать бэкап БД</button>
      <button class="btn" onclick="refreshData()">🔄 Обновить данные</button>
    </div>
  </header>

  <!-- Interactive Pipeline Stepper -->
  <nav class="pipeline-stepper" aria-label="Этапы конвейера публикаций">
    <div class="step-node" onclick="switchTab('gaps')" title="Пробелы ключевых слов у конкурентов">
      <span class="step-node__num">01</span>
      <div class="step-node__info">
        <span class="step-node__title">Разведка пробелов</span>
        <span class="step-node__meta"><strong id="stepGapsCount">0</strong> предложений</span>
      </div>
    </div>
    <div class="step-node" onclick="filterByStep('backlog')" title="Темы в очереди (без черновика)">
      <span class="step-node__num">02</span>
      <div class="step-node__info">
        <span class="step-node__title">Бэклог тем</span>
        <span class="step-node__meta"><strong id="stepBacklogCount">0</strong> в очереди</span>
      </div>
    </div>
    <div class="step-node" onclick="filterByStep('work')" title="Темы в процессе сбора брифа">
      <span class="step-node__num">03</span>
      <div class="step-node__info">
        <span class="step-node__title">В работе (Бриф)</span>
        <span class="step-node__meta"><strong id="stepWorkCount">0</strong> готовятся</span>
      </div>
    </div>
    <div class="step-node" onclick="filterByStep('needs_fix')" title="Черновики, набравшие менее 80 баллов">
      <span class="step-node__num">04</span>
      <div class="step-node__info">
        <span class="step-node__title">Требуют правок</span>
        <span class="step-node__meta"><strong id="stepFixCount" style="color:#B91C1C;">0</strong> черновиков</span>
      </div>
    </div>
    <div class="step-node" onclick="filterByStep('review')" title="Статьи с баллом ≥80, ожидающие приёмки">
      <span class="step-node__num">05</span>
      <div class="step-node__info">
        <span class="step-node__title">На ревью (ОТК)</span>
        <span class="step-node__meta"><strong id="stepReviewCount" style="color:#059669;">0</strong> готовы к ОТК</span>
      </div>
    </div>
    <div class="step-node" onclick="switchTab('export')" title="Утверждённые статьи для экспорта в WordPress">
      <span class="step-node__num">06</span>
      <div class="step-node__info">
        <span class="step-node__title">Готово к WP</span>
        <span class="step-node__meta"><strong id="stepReadyCount">0</strong> к доставке</span>
      </div>
    </div>
  </nav>

  <!-- Live Agents Status Bar -->
  <section class="agents-status-strip">
    <span style="font-weight:700;font-size:11px;text-transform:uppercase;">Субагенты конвейера:</span>
    <span class="agent-badge" onclick="switchTab('agents')"><span class="status-dot active"></span> 00-Разведчик (Perplexity Sonar)</span>
    <span class="agent-badge" onclick="switchTab('agents')"><span class="status-dot active"></span> 01-Исследователь (OpenCode Zen)</span>
    <span class="agent-badge" onclick="switchTab('agents')"><span class="status-dot active" style="background:#FFC700;"></span> 02-Автор Practico (Claude Sonnet Antigravity)</span>
    <span class="agent-badge" onclick="switchTab('agents')"><span class="status-dot active" style="background:#818CF8;"></span> 03-Рецензент (Claude Opus Antigravity)</span>
    <span class="agent-badge" onclick="switchTab('agents')"><span class="status-dot active"></span> 04-Скорер (score_article.py)</span>
  </section>

  <!-- Decision / Action Required Banner -->
  <section class="decision-banner" id="decisionBanner">
    <!-- Решения шеф-редактора генерируются JS -->
  </section>

  <!-- Navigation Tabs -->
  <nav class="tabs-nav">
    <button class="tab-btn active" onclick="switchTab('kanban')">📋 Канбан статей <span class="tab-badge" id="bCountTopics">0</span></button>
    <button class="tab-btn" onclick="switchTab('export')">🚀 Готовые к выгрузке <span class="tab-badge" id="bCountExport" style="background:#A7F3D0;color:#064E3B;">0</span></button>
    <button class="tab-btn" onclick="switchTab('gaps')">💡 Темы из пробелов <span class="tab-badge" id="bCountProposed">0</span></button>
    <button class="tab-btn" onclick="switchTab('competitors')">🔎 Конкуренты и фишки <span class="tab-badge" id="bCountComps">0</span></button>
    <button class="tab-btn" onclick="switchTab('analysis')">🔬 Анализ запросов <span class="tab-badge" id="bCountAnalysis">0</span></button>
    <button class="tab-btn" onclick="switchTab('agents')">🤖 Центр агентов</button>
    <button class="tab-btn" onclick="switchTab('backups')">💾 Бэкапы и архивы <span class="tab-badge" id="bCountBackups">0</span></button>
  </nav>

  <!-- TAB 1: KANBAN -->
  <section class="tab-view active" id="view-kanban">
    <div style="background:#EAEAEA;border:1.5px solid var(--border);padding:8px 12px;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <label style="font-size:12px;font-weight:700;">Кластер:
          <select id="clusterSelect" onchange="filterKanban()" style="padding:4px 8px;border:1px solid var(--border);font-size:12px;">
            <option value="">Все кластеры</option>
          </select>
        </label>
        <input type="text" id="kanbanSearch" placeholder="Поиск по названию или #ID..." oninput="filterKanban()" style="padding:4px 8px;border:1px solid var(--border);width:220px;font-size:12px;">
        <button class="btn btn--sm" id="btnResetFilter" onclick="resetStepFilter()" style="display:none;">Сбросить фильтр этапа ✕</button>
      </div>
      <div style="display:flex;gap:8px;align-items:center;">
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
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
      <div>
        <h2 style="margin:0;font-size:17px;font-weight:800;">Статьи, готовые к выгрузке в WordPress</h2>
        <div style="font-size:12px;color:var(--muted);">Материалы, набравшие ≥80 баллов, проверенные рецензентом (Claude Opus) и утверждённые к публикации.</div>
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
            <th>Объём</th>
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
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
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
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
      <div>
        <h2 style="margin:0;font-size:17px;font-weight:800;">Анализ конкурентов, SEO-слов и фишек</h2>
        <div style="font-size:12px;color:var(--muted);">Досье на ключевые сайты: сила 0-100, калькуляторы, таблицы сравнения, PDF-даташиты и ключевые слова.</div>
      </div>
      <div style="display:flex;gap:10px;">
        <button class="btn btn--primary" onclick="runAgent('bot-analyzer')">🤖 Глубокий анализ всех сайтов</button>
        <button class="btn" onclick="runAgent('00-research')">🌐 Собрать новые статьи</button>
      </div>
    </div>

    <!-- Блок ручного импорта выгрузки Firecrawl -->
    <div style="background:var(--card);border:2px solid var(--border);padding:14px 16px;margin-bottom:20px;box-shadow:2px 2px 0px rgba(0,0,0,0.05);">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
        <h3 style="margin:0;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:0.5px;display:flex;align-items:center;gap:6px;">
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
      <textarea id="fcContent" placeholder="Вставьте сюда текст страницы из Firecrawl (Markdown страницы или JSON результат скрейпинга)..." rows="3" style="width:100%;padding:8px 10px;border:1px solid var(--border);font-family:var(--mono);font-size:12px;resize:vertical;"></textarea>
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

    <h3 style="margin:20px 0 10px;font-size:14px;font-weight:800;">Новые сайты-кандидаты (competitors_proposed)</h3>
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

  <!-- TAB 4.5: KEYWORD ANALYSIS (Вордстат + Мутаген) -->
  <section class="tab-view" id="view-analysis">
    <div style="margin-bottom:12px;">
      <h2 style="margin:0;font-size:17px;font-weight:800;">🔬 Анализ поисковых запросов</h2>
      <div style="font-size:12px;color:var(--muted);">
        Спрос — бесплатно через Яндекс AI Studio (Wordstat API). Конкуренция — Мутаген (0,30 ₽ за фразу).
        Результаты кэшируются: повторная проверка денег не тратит.
      </div>
    </div>

    <div style="background:var(--card);border:1.5px solid var(--border);padding:12px;margin-bottom:14px;">
      <div style="font-size:11px;color:var(--muted);margin-bottom:6px;">
        💡 Подсказки берутся из SEO-ключей конкурентов, хвостов Вордстата и уже измеренных фраз — выберите из списка или впишите свою.
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <input type="text" id="analysisPhrase" list="kwSuggestList" placeholder="Введите или выберите фразу" style="flex:1;min-width:260px;padding:7px 10px;border:1.5px solid var(--border);font-size:12px;">
        <datalist id="kwSuggestList"></datalist>
        <select id="kwSuggestSelect" onchange="pickSuggestion(this)" style="padding:7px 10px;border:1.5px solid var(--border);font-size:12px;max-width:280px;">
          <option value="">— подсказки от конкурентов —</option>
        </select>
        <button class="btn btn--primary" onclick="checkPhrase('auto')">📊 Узнать спрос (бесплатно)</button>
        <button class="btn btn--warning" onclick="checkPhrase('mutagen')">🔬 Проверить Мутагеном (0,30 ₽)</button>
        <button class="btn" onclick="recalcPriorities()">⚡ Пересчитать приоритет тем</button>
      </div>
      <div id="priorityBox" style="margin-top:12px;display:none;"></div>
      <div id="analysisResult" style="margin-top:12px;display:none;"></div>
    </div>

    <div class="data-table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>Фраза</th>
            <th>Источник</th>
            <th>Спрос (широкая)</th>
            <th>Конкуренция</th>
            <th>Витальный сайт</th>
            <th>Проверено</th>
            <th>Действия</th>
          </tr>
        </thead>
        <tbody id="analysisBody"></tbody>
      </table>
    </div>
  </section>

  <!-- TAB 5: AGENT CENTER -->
  <section class="tab-view" id="view-agents">
    <div style="margin-bottom:14px;">
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
          <h3 class="agent-card__title">02-writer (Practico 1)</h3>
          <span class="prio-pill prio-pill--hot">Claude Sonnet</span>
        </div>
        <div class="agent-card__desc">Инженерный голос: триада «факт → физика → последствие», ГОСТ и IPC, сухая ирония, Answer First ≤ 40 слов.</div>
        <div class="agent-card__meta">Модель: claude-sonnet-4-6 · голос: practico</div>
        <button class="btn btn--primary" onclick="promptAgentTarget('02-writer')">▶ Написать (Practico 1)</button>
      </div>

      <!-- Agent 02b: Writer Practico 2 (голос заказчика) -->
      <div class="agent-card" style="border:2px solid var(--border);background:#1a1a1a;">
        <div class="agent-card__header">
          <h3 class="agent-card__title" style="color:#FFC700;">02-writer (Practico 2)</h3>
          <span class="prio-pill prio-pill--warm">Claude Sonnet</span>
        </div>
        <div class="agent-card__desc" style="color:#ddd;">Голос заказчика: короткие рубленые фразы, деньги и время в каждом разделе, «что купить / где обманут», без лекций и эрудиции.</div>
        <div class="agent-card__meta" style="color:#aaa;">Модель: claude-sonnet-4-6 · голос: practico2</div>
        <button class="btn btn--primary" onclick="promptAgentTarget('02-writer-2')">▶ Написать (Practico 2)</button>
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

    <h3 style="margin:16px 0 6px;font-size:13px;font-weight:800;">Терминальный лог агентов:</h3>
    <div class="log-console" id="agentConsole">> Готов к выполнению задач. Выберите агента выше.</div>
  </section>

  <!-- TAB 6: BACKUPS -->
  <section class="tab-view" id="view-backups">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
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
    
    <div style="border-bottom:2px solid var(--border);padding-bottom:10px;margin-bottom:12px;">
      <div style="font-family:var(--mono);font-size:11px;color:var(--muted);display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <span id="mId" style="font-weight:700;color:var(--ink);">#15</span> · 
        <span id="mCluster">Материалы</span> · 
        <span>статус: <strong id="mStatus" style="color:var(--ink);">В работе</strong></span> · 
        <span id="mChars" style="font-weight:600;">0 зн.</span>
      </div>
      <div id="mStageBadge" style="margin-top:6px;display:inline-block;padding:3px 9px;font-size:11px;font-family:var(--mono);font-weight:700;border:1px solid var(--border);background:var(--accent-light);">Этап конвейера</div>
      <h2 id="mTitle" style="margin:6px 0 0;font-size:17px;font-weight:800;line-height:1.3;">Название статьи</h2>
    </div>

    <!-- Вкладки модального окна: Оценка vs Текст статьи -->
    <div style="display:flex;gap:4px;border-bottom:1.5px solid var(--border);margin-bottom:12px;">
      <button class="tab-btn active" id="modalTabBtnScore" onclick="switchModalTab('score')">📊 Оценка и замечания (0-100)</button>
      <button class="tab-btn" id="modalTabBtnText" onclick="switchModalTab('text')">📄 Текст статьи (Markdown) <span class="tab-badge" id="mTextCharsBadge">0 зн.</span></button>
    </div>

    <!-- Вкладка 1: Оценка и замечания -->
    <div id="modalPanelScore">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
        <h3 style="margin:0;font-size:13px;font-weight:700;">Инженерная оценка качества (0–100):</h3>
        <span id="mTotalScore" style="font-family:var(--mono);font-size:16px;font-weight:800;padding:2px 8px;border:1.5px solid #111;">—</span>
      </div>

      <div id="mScoreGrid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px;"></div>

      <div id="mBlockersBox" style="display:none;background:#FEE2E2;border:1.5px solid #B91C1C;padding:10px;margin-bottom:10px;">
        <strong style="color:#B91C1C;font-size:12px;">❌ КРИТИЧЕСКИЕ БЛОКЕРЫ:</strong>
        <ul id="mBlockersList" style="margin:4px 0 0;padding-left:18px;font-size:12px;color:#7F1D1D;"></ul>
      </div>

      <div id="mIssuesBox" style="display:none;background:#FEF9C3;border:1.5px solid #A16207;padding:10px;margin-bottom:10px;">
        <strong style="color:#854D0E;font-size:12px;">⚠️ Замечания к доработке (требуются правки):</strong>
        <ul id="mIssuesList" style="margin:4px 0 0;padding-left:18px;font-size:12px;color:#713F12;"></ul>
      </div>

      <div style="border-top:1px dashed var(--border);padding-top:10px;margin-top:10px;">
        <h4 style="margin:0 0 4px;font-size:12px;font-weight:700;">Перелинковка темы:</h4>
        <div id="mLinksText" style="font-size:11.5px;color:var(--muted);line-height:1.4;">—</div>
      </div>
    </div>

    <!-- Вкладка 2: Полный текст статьи (Markdown) -->
    <div id="modalPanelText" style="display:none;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:8px;">
        <span style="font-size:11px;color:var(--muted);font-family:var(--mono);">
          Файл на диске: <strong id="mDiskFilePath" style="color:var(--ink);">сами статьи/...</strong>
        </span>
        <button class="btn btn--sm btn--primary" onclick="saveEditedArticleText()">💾 Сохранить изменения текста</button>
      </div>
      <textarea id="mTextEditor" rows="18" style="width:100%;padding:10px;border:1.5px solid var(--border);font-family:var(--mono);font-size:12px;line-height:1.5;background:#FAFAFA;resize:vertical;" placeholder="Текст статьи ещё не написан..."></textarea>
      <div style="margin-top:6px;font-size:11px;color:var(--muted);">
        💡 Вы можете читать, проверять или редактировать текст прямо здесь. Кнопка «Сохранить» синхронизирует и базу данных SQLite, и файл на диске.
      </div>
    </div>

    <!-- Contextual Action Buttons in Modal -->
    <div id="modalActionButtons" style="display:flex;gap:8px;margin-top:16px;padding-top:12px;border-top:2px solid var(--border);flex-wrap:wrap;">
      <!-- Генерируются динамически в openInspector() -->
    </div>
  </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
let DB_DATA = __JSON_DATA__;
let CURRENT_TOPIC_ID = null;
let ACTIVE_STEP_FILTER = null;

function esc(str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

const STATUS_RU = {
  'backlog': 'В бэклоге',
  'brief': 'Сбор фактов / Бриф',
  'brief_ready': 'Бриф готов',
  'writing': 'В написании',
  'written': 'Черновик написан',
  'needs_fix': 'Требуются правки',
  'review': 'На ревью (ОТК)',
  'ready': 'Готово к выгрузке',
  'published': 'Опубликовано в WordPress',
  'blocked': 'Заблокировано'
};

const COLUMNS = [
  { code: 'backlog', title: '1. Бэклог', desc: 'Идеи без текста' },
  { code: 'work', title: '2. В работе', desc: 'Сбор фактов / бриф' },
  { code: 'needs_fix', title: '3. Требует правок', desc: 'Есть текст < 80 баллов' },
  { code: 'review', title: '4. На ревью (ОТК)', desc: 'Балл ≥ 80, ждёт приёмки' },
  { code: 'ready', title: '5. Готово к выгрузке', desc: 'Утверждено шеф-редактором' },
  { code: 'published', title: '6. В WordPress', desc: 'Опубликовано' }
];

// Поправка #1: Колонку определять строго по данным (текст, балл, статус)
function getTopicColumn(t) {
  if (t.status === 'published') return 'published';
  if (t.status === 'ready') return 'ready';
  // Явный возврат на доработку редактором имеет приоритет над баллом
  if (t.status === 'needs_fix') return 'needs_fix';
  if (t.status === 'blocked') return 'needs_fix';
  const chars = t.chars || (t.content_markdown ? t.content_markdown.trim().length : 0);
  const hasContent = chars >= 200;
  if (hasContent) {
    if (t.score_total !== null && t.score_total !== undefined && t.score_total >= 80) {
      return 'review';
    }
    return 'needs_fix';
  }
  if (t.status === 'brief' || t.status === 'brief_ready' || t.status === 'writing') {
    return 'work';
  }
  return 'backlog';
}

function switchTab(name) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.tab-view').forEach(v => v.classList.remove('active'));
  const btn = Array.from(document.querySelectorAll('.tab-btn')).find(b => b.getAttribute('onclick')?.includes(name));
  if (btn) btn.classList.add('active');
  const view = document.getElementById('view-' + name);
  if (view) view.classList.add('active');
  if (name === 'analysis') {
    renderAnalysisHistory();
    loadKeywordSuggestions();
  }
}

function filterByStep(stepCode) {
  switchTab('kanban');
  ACTIVE_STEP_FILTER = stepCode;
  document.querySelectorAll('.step-node').forEach(node => {
    node.classList.toggle('active-filter', node.getAttribute('onclick')?.includes(stepCode));
  });
  const btnReset = document.getElementById('btnResetFilter');
  if (btnReset) btnReset.style.display = 'inline-flex';
  renderKanban();
}

function resetStepFilter() {
  ACTIVE_STEP_FILTER = null;
  document.querySelectorAll('.step-node').forEach(node => node.classList.remove('active-filter'));
  const btnReset = document.getElementById('btnResetFilter');
  if (btnReset) btnReset.style.display = 'none';
  renderKanban();
}

function showToast(msg) {
  const t = document.getElementById('toast');
  t.innerText = msg;
  t.style.display = 'block';
  setTimeout(() => { t.style.display = 'none'; }, 3500);
}

async function apiPost(route, payload) {
  try {
    const res = await fetch('http://127.0.0.1:8765' + route, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload || {})
    });
    return await res.json();
  } catch (e) {
    return null;
  }
}

async function apiGet(route) {
  try {
    const res = await fetch('http://127.0.0.1:8765' + route);
    return await res.json();
  } catch (e) {
    return null;
  }
}

async function refreshData() {
  try {
    const res = await fetch('http://127.0.0.1:8765/api/data');
    if (res.ok) {
      DB_DATA = await res.json();
      renderAll();
      showToast('✓ Данные обновлены из SQLite БД');
      return;
    }
  } catch (e) {}
  renderAll();
  showToast('✓ Интерфейс перерисован');
}

// ---------------------------------------------------------------------------
// Анализ поисковых запросов: спрос (Вордстат, бесплатно) + конкуренция (Мутаген)
// ---------------------------------------------------------------------------
function checkTopicPhrase(topicId) {
  const t = DB_DATA.topics.find(x => x.id === topicId);
  if (!t) return;
  closeInspector();
  switchTab('analysis');
  const input = document.getElementById('analysisPhrase');
  if (input) input.value = t.title;
  checkPhrase('auto');
}

// ---------------------------------------------------------------------------
// Подсказки фраз из SEO-данных конкурентов (выпадающий список)
// ---------------------------------------------------------------------------
let KW_SUGGESTIONS = [];

async function loadKeywordSuggestions(contains) {
  const sel = document.getElementById('kwSuggestSelect');
  const dl = document.getElementById('kwSuggestList');
  if (!sel || !dl) return;
  try {
    const route = '/api/keywords/suggest?limit=10' + (contains ? '&q=' + encodeURIComponent(contains) : '');
    const data = await apiGet(route);
    KW_SUGGESTIONS = (data && data.suggestions) || [];

    sel.innerHTML = '<option value="">— подсказки от конкурентов —</option>';
    dl.innerHTML = '';
    KW_SUGGESTIONS.forEach((s, i) => {
      const demand = s.demand != null ? Number(s.demand).toLocaleString('ru-RU') : '—';
      const comp = s.competition != null ? s.competition + '/25' : '—';
      const label = `${s.phrase} · ${s.source} · спрос ${demand} · конкур ${comp}`;
      sel.innerHTML += `<option value="${i}">${esc(label)}</option>`;
      dl.innerHTML += `<option value="${esc(s.phrase)}">${esc(s.source)}</option>`;
    });
  } catch (e) {
    sel.innerHTML = '<option value="">— подсказки недоступны —</option>';
  }
}

function pickSuggestion(selectEl) {
  const idx = selectEl.value;
  if (idx === '') return;
  const item = KW_SUGGESTIONS[parseInt(idx, 10)];
  if (!item) return;
  const input = document.getElementById('analysisPhrase');
  if (input) input.value = item.phrase;
  selectEl.value = '';
}

// Пересчёт приоритета тем и показ топа по бальной системе
async function recalcPriorities() {
  const box = document.getElementById('priorityBox');
  box.style.display = 'block';
  box.innerHTML = '<div style="padding:10px;font-size:12px;">⏳ Пересчитываю приоритет тем...</div>';
  const res = await apiPost('/api/priority/recalc', {});
  if (!res || res.status !== 'ok') {
    box.innerHTML = '<div style="padding:10px;color:#B91C1C;font-size:12px;">Не удалось пересчитать приоритет.</div>';
    return;
  }
  await refreshData();

  const top = res.top || [];
  let html = `<div style="border:1.5px solid var(--border);background:var(--card);padding:12px;">
    <div style="font-size:13px;font-weight:800;margin-bottom:6px;">⚡ Приоритет пересчитан: ${res.total} тем</div>
    <div style="font-size:11px;color:var(--muted);margin-bottom:8px;">
      Формула: Спрос (до 40) + Конкуренция (до 30) + Интент (до 15) + Готовность (до 15).
      Класс A — писать первым, D — не тратить силы.
    </div>
    <ul style="margin:0 0 0 16px;font-size:12px;">`;
  top.forEach(t => {
    const g = t.grade;
    const color = g === 'A' ? '#059669' : (g === 'B' ? '#B45309' : (g === 'C' ? '#6B7280' : '#B91C1C'));
    html += `<li><strong style="color:${color};">[${g}] ${t.score}</strong> — ${esc(t.title)}</li>`;
  });
  html += '</ul></div>';
  box.innerHTML = html;
  showToast('✓ Приоритет тем пересчитан');
}

async function checkPhrase(source) {
  const input = document.getElementById('analysisPhrase');
  const phrase = (input && input.value || '').trim();
  if (!phrase) {
    alert('Введите фразу для проверки');
    return;
  }
  return checkPhraseValue(phrase, source, false);
}

// Перепроверка фразы из таблицы истории (игнорирует кэш)
function recheckPhraseByIndex(index, source) {
  const row = ANALYSIS_ROWS[index];
  if (!row) return;
  const input = document.getElementById('analysisPhrase');
  if (input) input.value = row.phrase;
  checkPhraseValue(row.phrase, source, true);
}

async function checkPhraseValue(phrase, source, refresh) {
  const box = document.getElementById('analysisResult');
  box.style.display = 'block';
  box.innerHTML = `<div style="padding:10px;background:var(--bg);border:1px solid var(--border);font-size:12px;">
    ⏳ ${refresh ? 'Перепроверяю' : 'Проверяю'} «${esc(phrase)}»${source === 'mutagen' ? ' (Мутаген, 0,30 ₽)' : ' (Вордстат, бесплатно)'}...</div>`;

  const res = await apiPost('/api/keyword/check', { phrase: phrase, source: source, refresh: refresh });
  if (!res || res.status !== 'ok') {
    box.innerHTML = `<div style="padding:10px;background:#FEE2E2;border:1px solid #B91C1C;font-size:12px;">
      ❌ Не удалось проверить фразу.</div>`;
    return;
  }

  const ws = res.wordstat || null;
  const mg = res.mutagen || null;
  let html = `<div style="border:1.5px solid var(--border);background:var(--card);padding:12px;">
    <div style="font-size:13px;font-weight:800;margin-bottom:8px;">«${esc(res.phrase)}»</div>`;

  if (ws) {
    html += `<div style="margin-bottom:8px;">
      <strong>Широкая частотность (Вордстат):</strong> ${(ws.totalCount || 0).toLocaleString('ru-RU')} показов/мес
      ${ws.cached ? '<span style="color:var(--muted);font-size:11px;"> · из кэша, бесплатно</span>' : ''}
    </div>`;
    if (ws.results && ws.results.length) {
      html += '<div style="font-size:11px;color:var(--muted);margin-bottom:4px;">Топ похожих запросов:</div><ul style="margin:0 0 8px 16px;font-size:12px;">';
      ws.results.slice(0, 5).forEach(r => {
        html += `<li>${esc(r.phrase)} — <code>${(r.count || 0).toLocaleString('ru-RU')}</code></li>`;
      });
      html += '</ul>';
    }
  }

  if (mg) {
    const comp = mg.strong;
    const color = comp <= 10 ? '#059669' : (comp <= 18 ? '#B45309' : '#B91C1C');
    html += `<div style="margin-top:8px;padding-top:8px;border-top:1px dashed var(--border);">
      <strong>Конкуренция (Мутаген):</strong> <span style="color:${color};font-weight:800;">${comp}/25</span>
      · точный спрос в кавычках: <code>${(mg.wordstat || 0).toLocaleString('ru-RU')}</code>
      · хвосты: <code>${(mg.tails || 0).toLocaleString('ru-RU')}</code>
      ${mg.vital_site ? `<br><strong>Витальный сайт в топе:</strong> ${esc(mg.vital_site)}` : '<br>Витального сайта в топе нет — путь открыт'}
      ${mg.cached ? '<span style="color:var(--muted);font-size:11px;"> · из кэша, деньги не потрачены</span>' : ''}
    </div>`;
    // Трактовка
    let verdict = '';
    if (comp <= 10 && (ws && ws.totalCount > 300)) verdict = '✅ Отличная цель: спрос есть, конкуренция низкая';
    else if (comp <= 18) verdict = '⚠️ Рабочая цель: нужен сильный материал';
    else verdict = '❌ Высокая конкуренция: только с серьёзным преимуществом';
    html += `<div style="margin-top:8px;font-weight:700;">${verdict}</div>`;
  }

  if (res.cost) {
    html += `<div style="margin-top:8px;font-size:11px;color:var(--muted);">Стоимость проверки: ${res.cost} ₽ · баланс: ${res.balance || '—'} ₽</div>`;
  }
  if (res.notes && res.notes.length) {
    html += `<div style="margin-top:6px;font-size:11px;color:#B45309;">${res.notes.map(n => esc(n)).join('<br>')}</div>`;
  }
  html += '</div>';
  box.innerHTML = html;

  await renderAnalysisHistory();
}

let ANALYSIS_ROWS = [];

async function renderAnalysisHistory() {
  const tb = document.getElementById('analysisBody');
  if (!tb) return;
  try {
    const res = await fetch('http://127.0.0.1:8765/api/keyword/history');
    const data = await res.json();
    const rows = data.history || [];
    ANALYSIS_ROWS = rows;
    const badge = document.getElementById('bCountAnalysis');
    if (badge) badge.innerText = rows.length;

    if (!rows.length) {
      tb.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:20px;color:var(--muted);">Проверок пока нет. Введите фразу выше.</td></tr>';
      return;
    }
    tb.innerHTML = rows.map((r, i) => `
      <tr>
        <td><strong>${esc(r.phrase)}</strong></td>
        <td><span class="prio-pill ${r.source === 'mutagen' ? 'prio-pill--warm' : 'prio-pill--cold'}">${r.source === 'mutagen' ? 'Мутаген' : 'Вордстат'}</span></td>
        <td style="font-family:var(--mono);">${r.demand != null ? Number(r.demand).toLocaleString('ru-RU') : '—'}</td>
        <td style="font-family:var(--mono);">${r.competition != null ? r.competition + '/25' : '—'}</td>
        <td style="font-size:11px;">${esc(r.vital_site || '—')}</td>
        <td style="font-family:var(--mono);font-size:11px;color:var(--muted);">${(r.checked_at || '').replace('T', ' ').slice(0, 16)}</td>
        <td style="white-space:nowrap;">
          <button class="btn btn--xs" title="Перепроверить в Вордстате (бесплатно)" onclick="recheckPhraseByIndex(${i}, 'auto')">↻ Вордстат</button>
          <button class="btn btn--xs btn--warning" title="Перепроверить в Мутагене (0,30 ₽)" onclick="recheckPhraseByIndex(${i}, 'mutagen')">↻ Мутаген</button>
          <button class="btn btn--xs" title="Показать историю измерений" onclick="showPhraseTrend(${i})">📈 История</button>
        </td>
      </tr>`).join('');
  } catch (e) {
    tb.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--muted);">История недоступна</td></tr>';
  }
}

// Показывает все прошлые измерения фразы: как менялись спрос и конкуренция
async function showPhraseTrend(index) {
  const row = ANALYSIS_ROWS[index];
  if (!row) return;
  const box = document.getElementById('analysisResult');
  box.style.display = 'block';
  box.innerHTML = '<div style="padding:10px;font-size:12px;">⏳ Загружаю историю измерений...</div>';

  const data = await apiGet('/api/keyword/trend?phrase=' + encodeURIComponent(row.phrase));
  if (!data || data.status !== 'ok') {
    box.innerHTML = '<div style="padding:10px;color:#B91C1C;font-size:12px;">История недоступна</div>';
    return;
  }

  let html = `<div style="border:1.5px solid var(--border);background:var(--card);padding:12px;">
    <div style="font-size:13px;font-weight:800;margin-bottom:8px;">📈 История: «${esc(data.phrase)}»</div>`;

  if (data.wordstat_history && data.wordstat_history.length) {
    html += '<div style="font-weight:700;font-size:12px;margin-bottom:4px;">Вордстат (широкая частотность):</div><ul style="margin:0 0 10px 16px;font-size:12px;">';
    data.wordstat_history.forEach(h => {
      html += `<li><code>${(h.checked_at || '').replace('T', ' ').slice(0, 16)}</code> — ${h.total_count != null ? Number(h.total_count).toLocaleString('ru-RU') : '—'} показов/мес</li>`;
    });
    html += '</ul>';
  }

  if (data.mutagen_history && data.mutagen_history.length) {
    html += '<div style="font-weight:700;font-size:12px;margin-bottom:4px;">Мутаген (конкуренция и точный спрос):</div><ul style="margin:0 0 6px 16px;font-size:12px;">';
    data.mutagen_history.forEach(h => {
      html += `<li><code>${(h.checked_at || '').replace('T', ' ').slice(0, 16)}</code> — конкуренция <strong>${h.strong}/25</strong>, точный спрос <code>${h.wordstat != null ? Number(h.wordstat).toLocaleString('ru-RU') : '—'}</code>${h.vital_site ? ', витальный: ' + esc(h.vital_site) : ''}</li>`;
    });
    html += '</ul>';
  }

  const total = (data.wordstat_history || []).length + (data.mutagen_history || []).length;
  if (!total) html += '<div style="font-size:12px;color:var(--muted);">Измерений ещё не было.</div>';
  html += '</div>';
  box.innerHTML = html;
}

async function createBackup() {
  showToast('Создание бэкапа базы данных...');
  const res = await apiPost('/api/backup', {});
  if (res && res.status === 'ok') {
    showToast(`✓ Бэкап создан: ${res.backup.filename} (${Math.round(res.backup.size_bytes/1024)} КБ)`);
    await refreshData();
  } else {
    const blob = new Blob([JSON.stringify(DB_DATA, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `seo_backup_${new Date().toISOString().slice(0,10)}.json`;
    a.click();
    showToast('✓ Экспорт данных скачан (офлайн JSON бэкап)');
  }
}

async function approveTopic(id) {
  if (!confirm(`Одобрить тему ${id} и перенести в рабочий бэклог?`)) return;
  const res = await apiPost('/api/topics/approve', { id });
  if (res && res.status === 'ok') {
    showToast(`✓ Тема одобрена! Присвоен новый ID #${res.new_topic_id}`);
    await refreshData();
  }
}

async function rejectTopic(id) {
  await apiPost('/api/topics/reject', { id });
  const item = DB_DATA.topics_proposed.find(t => t.id === id);
  if (item) item.status = 'отклонён';
  renderAll();
  showToast('Тема отклонена.');
}

async function approveCompetitor(domain) {
  await apiPost('/api/competitors/approve', { domain });
  showToast(`✓ Конкурент ${domain} добавлен в список мониторинга!`);
  await refreshData();
}

async function approveArticle(topicId) {
  const tid = topicId || CURRENT_TOPIC_ID;
  if (!tid) return;
  await apiPost('/api/articles/approve', { id: tid });
  showToast(`✓ Статья #${tid} утверждена к публикации! Перенесена во вкладку «Готовые к выгрузке».`);
  closeInspector();
  await refreshData();
}

async function rejectArticle(topicId) {
  const tid = topicId || CURRENT_TOPIC_ID;
  if (!tid) return;
  const reason = prompt('Укажите замечания для автора Claude Sonnet:') || 'Требуется доработка текста';
  await apiPost('/api/articles/reject', { id: tid, reason });
  showToast(`Статья #${tid} возвращена на доработку автору.`);
  closeInspector();
  await refreshData();
}

async function scoreCurrentArticle() {
  if (!CURRENT_TOPIC_ID) return;
  showToast(`Запуск скоринга статьи #${CURRENT_TOPIC_ID}...`);
  const res = await apiPost('/api/agent/run', { agent: '04-scorer', target_id: CURRENT_TOPIC_ID });
  if (res && res.log) {
    document.getElementById('agentConsole').innerText = res.log;
    showToast('✓ Скоринг завершён!');
    await refreshData();
    openInspector(CURRENT_TOPIC_ID);
  }
}

// Поправка #2: Защита от затирания существующего текста
function confirmRewriteCurrentTopic(voice) {
  if (!CURRENT_TOPIC_ID) return;
  const v = voice || 'practico';
  const vName = v === 'practico2' ? 'Practico 2' : 'Practico 1';
  const t = DB_DATA.topics.find(x => x.id === CURRENT_TOPIC_ID);
  const chars = (t && t.chars) || 0;
  if (!confirm(`⚠️ ВНИМАНИЕ: У статьи #${CURRENT_TOPIC_ID} уже написан текст (${chars.toLocaleString('ru-RU')} зн.)!\n\nПереписать заново голосом ${vName}? Текущий текст будет заменён.`)) {
    return;
  }
  writeArticleForCurrentTopic(v);
}

async function writeArticleForCurrentTopic(voice) {
  if (!CURRENT_TOPIC_ID) return;
  const v = voice || 'practico';
  const voiceLabel = v === 'practico2' ? 'Practico 2 — голос заказчика' : 'Practico 1 — инженер';
  closeInspector();
  showToast(`Запуск написания статьи #${CURRENT_TOPIC_ID} (${voiceLabel})...`);
  const c = document.getElementById('agentConsole');
  c.innerText = `[${new Date().toLocaleTimeString()}] Запуск Агента 02 (Claude Sonnet)\nГолос: ${voiceLabel}\nОжидание генерации текста...`;
  switchTab('agents');
  const res = await apiPost('/api/agent/run', { agent: '02-writer', target_id: CURRENT_TOPIC_ID, voice: v });
  if (res && res.log) {
    c.innerText = res.log;
    showToast(res.status === 'succeeded' ? `✓ Статья #${CURRENT_TOPIC_ID} написана!` : `⚠️ Ошибка генерации`);
    await refreshData();
    openInspector(CURRENT_TOPIC_ID);
  }
}

function promptAgentTarget(agentName) {
  const tid = prompt(`Введите ID статьи для ${agentName}:`);
  if (!tid) return;
  CURRENT_TOPIC_ID = parseInt(tid, 10) || tid;
  if (agentName === '04-scorer') {
    scoreCurrentArticle();
  } else if (agentName === '02-writer') {
    const t = DB_DATA.topics.find(x => x.id === CURRENT_TOPIC_ID);
    if (t && (t.chars || 0) >= 200) {
      confirmRewriteCurrentTopic();
    } else {
      writeArticleForCurrentTopic();
    }
  } else {
    runAgent(agentName);
  }
}

async function runAgent(agentName) {
  showToast(`Запуск агента ${agentName}...`);
  const c = document.getElementById('agentConsole');
  c.innerText = `[${new Date().toLocaleTimeString()}] Запуск ${agentName}...\nОжидание ответа...`;
  const res = await apiPost('/api/agent/run', { agent: agentName });

  // Долгие агенты работают в фоне: сервер вернёт job_id
  if (res && res.job_id) {
    c.innerText = `[${new Date().toLocaleTimeString()}] ${res.log}\n\nИдёт работа... (это может занять несколько минут)`;
    showToast(`⏳ Задача #${res.job_id} выполняется в фоне...`);
    await pollJob(res.job_id, agentName);
    return;
  }

  if (res && res.log) {
    c.innerText = res.log;
    showToast(res.status === 'succeeded' ? `✓ Агент ${agentName} завершил работу!` : `⚠️ Агент ${agentName}: ${res.status}`);
    await refreshData();
  }
}

async function pollJob(jobId, agentName) {
  const c = document.getElementById('agentConsole');
  for (let attempt = 0; attempt < 150; attempt++) {
    await new Promise(r => setTimeout(r, 2000));
    let job = null;
    try {
      const r = await fetch(`http://127.0.0.1:8765/api/jobs/${jobId}`);
      if (r.ok) job = await r.json();
    } catch (e) { /* сервер занят — пробуем снова */ }
    if (!job || job.status === 'running') {
      c.innerText = `[${new Date().toLocaleTimeString()}] Задача #${jobId}: выполняется...`;
      continue;
    }
    c.innerText = `[${new Date().toLocaleTimeString()}] Задача #${jobId}: ${job.status}\n\n${job.log || ''}`;
    showToast(job.status === 'succeeded' ? `✓ Задача #${jobId} завершена!` : `⚠️ Задача #${jobId}: ${job.status}`);
    await refreshData();
    return;
  }
  c.innerText = `⚠️ Задача #${jobId} всё ещё выполняется. Проверьте позже: /api/jobs/${jobId}`;
}

async function exportSingleArticle(id) {
  showToast(`Проверка пакета статьи #${id} по схеме v1.1...`);
  const c = document.getElementById('agentConsole');
  c.innerText = `[${new Date().toLocaleTimeString()}] Проверка схемы v1.1 и упаковка пакета для статьи #${id}...`;
  switchTab('agents');
  const res = await apiPost('/api/agent/run', { agent: 'delivery', target_id: id });
  if (res && res.log) {
    c.innerText = res.log;
    if (res.status === 'succeeded') {
      showToast(`✓ Пакет статьи #${id} 100% валиден!`);
    } else {
      showToast(`⚠️ Статья #${id}: не хватает данных по схеме v1.1`);
    }
  }
}

async function exportAllReady() {
  showToast('Выгрузка всех готовых черновиков в WordPress...');
  switchTab('agents');
  runAgent('delivery-all');
}

async function importFirecrawlData() {
  const content = document.getElementById('fcContent').value.trim();
  const domain = document.getElementById('fcDomain').value.trim();
  const cluster = document.getElementById('fcCluster').value;
  const resBox = document.getElementById('fcResult');

  if (!content) {
    alert('Пожалуйста, вставьте текст выгрузки из Firecrawl!');
    return;
  }

  showToast('Разбор выгрузки Firecrawl...');
  const res = await apiPost('/api/import/firecrawl', { content, domain, cluster });
  if (res && res.status === 'ok') {
    resBox.style.display = 'block';
    resBox.innerHTML = `
      <strong>✓ Успешно разобрано и добавлено в БД!</strong><br>
      • Статей сохранено: <strong>${res.saved_articles}</strong><br>
      • Обнаруженные фишки: <strong>${res.features.length ? res.features.join(', ') : 'базовый контент'}</strong><br>
      • Извлечённые SEO-слова: <strong>${res.keywords.length ? res.keywords.join(', ') : '—'}</strong>
    `;
    document.getElementById('fcContent').value = '';
    showToast(`✓ Сведения по сайту ${res.domain} добавлены в базу!`);
    await refreshData();
  } else {
    alert('Ошибка при импорте выгрузки Firecrawl.');
  }
}

// Render Decision Alert Banner
function renderDecisionBanner() {
  const banner = document.getElementById('decisionBanner');
  
  // 1. Статьи требующие правок (score < 80 and chars > 500)
  const needsFix = DB_DATA.topics.filter(t => (t.chars || 0) > 500 && (t.score_total === null || t.score_total < 80) && t.status !== 'ready' && t.status !== 'published');
  
  // 2. Статьи ожидающие утверждения шеф-редактором (score >= 80 and status != ready and status != published)
  const readyForApproval = DB_DATA.topics.filter(t => (t.score_total || 0) >= 80 && t.status !== 'ready' && t.status !== 'published');
  
  // 3. Предложенные темы ожидающие решения
  const proposedWaiting = (DB_DATA.topics_proposed || []).filter(t => t.status === 'предложен');

  let fixHtml = needsFix.map(t => `
    <li>
      <strong>#${t.id}</strong> ${esc(t.title.slice(0, 34))}… 
      <span style="color:#B91C1C;font-weight:700;">(${t.score_total || '—'}/100)</span>
      <a href="javascript:void(0)" onclick="openInspector(${t.id})" style="font-size:11px;margin-left:4px;">правки ↗</a>
    </li>
  `).join('');

  let approveHtml = readyForApproval.map(t => `
    <li>
      <strong>#${t.id}</strong> ${esc(t.title.slice(0, 34))}… 
      <span style="color:#059669;font-weight:700;">(${t.score_total}/100)</span>
      <a href="javascript:void(0)" onclick="openInspector(${t.id})" style="font-size:11px;margin-left:4px;">утвердить ↗</a>
    </li>
  `).join('');

  let propHtml = proposedWaiting.slice(0, 3).map(t => `
    <li>
      <span class="prio-pill prio-pill--warm">P${t.priority_score}</span>
      ${esc(t.title.slice(0, 36))}…
    </li>
  `).join('');

  banner.innerHTML = `
    <div class="decision-col">
      <div class="decision-col__title" style="color:#B91C1C;">
        ⚠️ Требуются правки автора (${needsFix.length})
      </div>
      <ul class="decision-col__list">
        ${fixHtml || '<li style="color:var(--muted);">Нет замечаний, все черновики в норме</li>'}
      </ul>
    </div>

    <div class="decision-col">
      <div class="decision-col__title" style="color:#059669;">
        🛡️ Готово к Вашему утверждению (${readyForApproval.length})
      </div>
      <ul class="decision-col__list">
        ${approveHtml || '<li style="color:var(--muted);">Нет новых статей на приёмку</li>'}
      </ul>
    </div>

    <div class="decision-col">
      <div class="decision-col__title" style="color:#D97706;">
        💡 Темы из пробелов на одобрение (${proposedWaiting.length})
      </div>
      <ul class="decision-col__list">
        ${propHtml || '<li style="color:var(--muted);">Все предложенные темы обработаны</li>'}
      </ul>
      <div style="margin-top:6px;font-size:11px;">
        <a href="javascript:void(0)" onclick="switchTab('gaps')">Перейти к списку тем (${proposedWaiting.length}) →</a>
      </div>
    </div>
  `;
}

// Render Export Tab (Ready for WordPress)
function renderExportTab() {
  const tb = document.getElementById('exportTableBody');
  tb.innerHTML = '';
  
  const readyTopics = DB_DATA.topics.filter(t => t.status === 'ready' || (t.score_total >= 80 && (t.chars || 0) > 1000));
  document.getElementById('bCountExport').innerText = readyTopics.length;

  if (readyTopics.length === 0) {
    tb.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:24px;color:var(--muted);">Пока нет статей, утверждённых к выгрузке. Утвердите статьи с оценкой ≥80 в канбане.</td></tr>';
    return;
  }

  readyTopics.forEach(t => {
    tb.innerHTML += `
      <tr>
        <td style="font-family:var(--mono);font-weight:700;">#${t.id}</td>
        <td><span class="score-pill score-pill--high">${t.score_total || 80}/100</span></td>
        <td><strong>${esc(t.title)}</strong></td>
        <td><code style="font-size:11px;">${t.slug}</code></td>
        <td><span class="task-card__cluster">${esc(t.cluster)}</span></td>
        <td style="font-family:var(--mono);">${t.chars ? t.chars.toLocaleString('ru-RU') + ' зн.' : '—'}</td>
        <td><span class="prio-pill prio-pill--warm">Valid v1.1</span></td>
        <td>
          <span style="color:#059669;font-weight:700;font-size:11px;">
            ${t.status === 'ready' ? '🛡️ Утверждено к ОТК' : 'Готово к утверждению'}
          </span>
        </td>
        <td>
          <button class="btn btn--sm btn--primary" onclick="exportSingleArticle(${t.id})">📦 Проверить пакет v1.1</button>
        </td>
      </tr>
    `;
  });
}

// Kanban Renderer с независимым скроллом и контекстными кнопками
function renderKanban() {
  const board = document.getElementById('kanbanBoard');
  board.innerHTML = '';

  if (ACTIVE_STEP_FILTER) {
    board.style.gridTemplateColumns = 'minmax(320px, 480px)';
  } else {
    board.style.gridTemplateColumns = '';
  }

  const clusterFilter = document.getElementById('clusterSelect').value.toLowerCase();
  const searchFilter = document.getElementById('kanbanSearch').value.toLowerCase().trim();

  COLUMNS.forEach(col => {
    // Если активен фильтр этапа, скрываем неактивные колонки
    if (ACTIVE_STEP_FILTER && ACTIVE_STEP_FILTER !== col.code) {
      return;
    }

    const colDiv = document.createElement('div');
    colDiv.className = 'board-column';

    let colCardsHtml = '';
    let count = 0;

    DB_DATA.topics.forEach(t => {
      const cCode = getTopicColumn(t);
      if (cCode !== col.code) return;

      if (clusterFilter && (t.cluster || '').toLowerCase() !== clusterFilter) return;
      if (searchFilter && !t.title.toLowerCase().includes(searchFilter) && !('#' + t.id).includes(searchFilter)) return;

      count++;
      const pScore = t.priority_score || 50;
      const pClass = pScore >= 90 ? 'prio-pill--hot' : (pScore >= 75 ? 'prio-pill--warm' : 'prio-pill--cold');

      let scoreBadge = '';
      let warnBlock = '';
      let seoBadge = '';
      const chars = t.chars || (t.content_markdown ? t.content_markdown.trim().length : 0);

      // Приоритет темы по спросу и конкуренции (0–100, класс A/B/C/D)
      if (t.seo_priority != null) {
        let detail = {};
        try { detail = JSON.parse(t.seo_priority_json || '{}'); } catch (e) { detail = {}; }
        const grade = detail.grade || '?';
        const gColor = grade === 'A' ? '#059669' : (grade === 'B' ? '#B45309' : (grade === 'C' ? '#6B7280' : '#B91C1C'));
        const gBg = grade === 'A' ? '#A7F3D0' : (grade === 'B' ? '#FEF08A' : (grade === 'C' ? '#E5E7EB' : '#FECACA'));
        seoBadge = `<span class="prio-pill" style="background:${gBg};color:${gColor};font-weight:800;" title="Спрос+конкуренция+интент+готовность">SEO ${t.seo_priority} · ${grade}</span>`;
      }

      if (t.score_total !== null && t.score_total !== undefined) {
        const sClass = t.score_total >= 80 ? 'score-pill--high' : (t.score_total >= 60 ? 'score-pill--mid' : 'score-pill--low');
        scoreBadge = `<span class="score-pill ${sClass}">${t.score_total}/100</span>`;
        if (t.score_total < 80 && chars > 500) {
          const firstIssue = (t.score_data && t.score_data.issues && t.score_data.issues[0]) || 'требуется доработка';
          warnBlock = `<div class="task-card__warn">⚠️ ${esc(firstIssue)}</div>`;
        }
      } else if (chars > 500) {
        scoreBadge = `<span class="score-pill score-pill--mid">${Math.round(chars/1000)}k зн.</span>`;
      }

      // Контекстная кнопка прямо на карточке
      let actionBtn = '';
      if (col.code === 'backlog') {
        actionBtn = `<button class="btn btn--xs btn--primary" onclick="event.stopPropagation(); promptAgentTarget('01-researcher'); CURRENT_TOPIC_ID=${t.id};">▶ Бриф</button>`;
      } else if (col.code === 'work') {
        actionBtn = `<button class="btn btn--xs btn--primary" onclick="event.stopPropagation(); CURRENT_TOPIC_ID=${t.id}; writeArticleForCurrentTopic();">✍️ Написать</button>`;
      } else if (col.code === 'needs_fix') {
        actionBtn = `<button class="btn btn--xs btn--warning" onclick="event.stopPropagation(); openInspector(${t.id});">⚠️ Замечания (${t.score_total || '—'})</button>`;
      } else if (col.code === 'review') {
        actionBtn = `<button class="btn btn--xs btn--success" onclick="event.stopPropagation(); approveArticle(${t.id});">✓ В приёмку</button>`;
      } else if (col.code === 'ready') {
        actionBtn = `<button class="btn btn--xs btn--primary" onclick="event.stopPropagation(); exportSingleArticle(${t.id});">📦 Проверить v1.1</button>`;
      } else if (col.code === 'published') {
        actionBtn = `<span style="font-size:10px;font-weight:700;color:#059669;">✓ Опубликовано</span>`;
      }

      colCardsHtml += `
        <article class="task-card" onclick="openInspector(${t.id})">
          <div class="task-card__top">
            <span class="task-card__id">#${t.id}</span>
            <div class="task-card__badges">
              <span class="prio-pill ${pClass}">P${pScore}</span>
              ${seoBadge}
              ${scoreBadge}
            </div>
          </div>
          <h3 class="task-card__title">${esc(t.title)}</h3>
          <div class="task-card__footer">
            <span class="task-card__cluster" title="${esc(t.cluster)}">${esc((t.cluster || '').slice(0, 20))}</span>
            <span style="font-family:var(--mono);">${chars > 0 ? (chars > 999 ? (chars/1000).toFixed(1) + 'k' : chars) + ' зн.' : '—'}</span>
          </div>
          ${warnBlock}
          <div class="task-card__action-row">
            <span style="font-size:10px;color:var(--muted);">${STATUS_RU[t.status] || t.status}</span>
            ${actionBtn}
          </div>
        </article>
      `;
    });

    colDiv.innerHTML = `
      <div class="board-column__header">
        <div>
          <span class="board-column__title">${col.title}</span>
          <div style="font-size:10px;color:var(--muted);">${col.desc}</div>
        </div>
        <span class="board-column__badge">${count}</span>
      </div>
      <div class="board-column__cards">
        ${colCardsHtml || '<div style="color:var(--muted);font-size:12px;text-align:center;padding:24px 0;">—</div>'}
      </div>
    `;
    board.appendChild(colDiv);
  });
}

function filterKanban() {
  renderKanban();
}

// Render Proposed Topics
function renderProposedTopics() {
  const tb = document.getElementById('proposedTopicsBody');
  tb.innerHTML = '';
  (DB_DATA.topics_proposed || []).forEach(t => {
    const isAccepted = t.status === 'принят';
    const isRejected = t.status === 'отклонён';
    tb.innerHTML += `
      <tr>
        <td style="font-family:var(--mono);font-weight:700;">${t.id}</td>
        <td><span class="prio-pill prio-pill--warm">P${t.priority_score}</span></td>
        <td><strong>${esc(t.title)}</strong></td>
        <td><span class="task-card__cluster">${esc(t.cluster)}</span></td>
        <td>${t.type || 'гайд'}</td>
        <td style="font-size:12px;color:var(--muted);">${esc(t.evidence || t.source || '—')}</td>
        <td>
          <span style="font-weight:700;color:${isAccepted ? '#059669' : (isRejected ? '#DC2626' : '#D97706')}">
            ${t.status}
          </span>
        </td>
        <td>
          ${!isAccepted && !isRejected ? `
            <button class="btn btn--sm btn--success" onclick="approveTopic('${t.id}')">✓ Одобрить</button>
            <button class="btn btn--sm btn--danger" onclick="rejectTopic('${t.id}')">✕</button>
          ` : '—'}
        </td>
      </tr>
    `;
  });
}

// Render Competitors
function renderCompetitors() {
  const tb = document.getElementById('competitorsBody');
  tb.innerHTML = '';
  (DB_DATA.competitors || []).forEach(c => {
    tb.innerHTML += `
      <tr>
        <td><strong>${esc(c.domain)}</strong><br><a href="${c.blog_url || 'https://' + c.domain}" target="_blank" style="font-size:11px;color:var(--muted);">перейти ↗</a></td>
        <td><span class="prio-pill ${c.score >= 70 ? 'prio-pill--hot' : 'prio-pill--cold'}">${c.score || 50}/100</span></td>
        <td style="font-size:12px;color:#065F46;max-width:240px;">${esc(c.seo_keywords || 'Общая электроника')}</td>
        <td style="font-size:12px;max-width:280px;">${esc(c.features || 'Базовый контент')}</td>
        <td style="font-family:var(--mono);">${c.articles_count || 0}</td>
        <td style="font-size:12px;color:var(--muted);">${esc(c.type || '—')}</td>
        <td>
          <button class="btn btn--sm btn--primary" onclick="runAgent('bot-analyzer')">Анализ фишек</button>
        </td>
      </tr>
    `;
  });

  const ptb = document.getElementById('proposedCompetitorsBody');
  ptb.innerHTML = '';
  (DB_DATA.competitors_proposed || []).forEach(c => {
    const isAccepted = c.status === 'принят';
    ptb.innerHTML += `
      <tr>
        <td><strong>${esc(c.domain)}</strong></td>
        <td>${esc(c.niche || '—')}</td>
        <td>${c.hits || 1} запросов</td>
        <td>${c.articles_count || 0}</td>
        <td><a href="${c.example_url || '#'}" target="_blank" style="font-size:11px;">${esc((c.example_url || '').slice(0,35))}</a></td>
        <td><span style="font-weight:700;color:${isAccepted ? '#059669' : '#D97706'}">${c.status}</span></td>
        <td>
          ${!isAccepted ? `<button class="btn btn--sm btn--success" onclick="approveCompetitor('${c.domain}')">✓ Добавить</button>` : '—'}
        </td>
      </tr>
    `;
  });
}

// Render Backups
function renderBackups() {
  const tb = document.getElementById('backupsBody');
  tb.innerHTML = '';
  (DB_DATA.backups || []).forEach((b, idx) => {
    tb.innerHTML += `
      <tr>
        <td style="font-family:var(--mono);">${idx + 1}</td>
        <td><strong>${esc(b.filename)}</strong></td>
        <td style="font-family:var(--mono);">${Math.round(b.size_bytes / 1024)} КБ</td>
        <td>${b.records_count} тем</td>
        <td style="font-size:12px;color:var(--muted);">${b.created_at}</td>
        <td style="font-family:var(--mono);font-size:11px;color:var(--muted);">${esc(b.filepath)}</td>
      </tr>
    `;
  });
}

// Open Inspector Modal
function openInspector(topicId) {
  CURRENT_TOPIC_ID = topicId;
  const t = DB_DATA.topics.find(x => x.id === topicId);
  if (!t) return;

  const chars = t.chars || (t.content_markdown ? t.content_markdown.trim().length : 0);
  const hasText = chars >= 200;
  const colCode = getTopicColumn(t);

  document.getElementById('mId').innerText = '#' + t.id;
  document.getElementById('mCluster').innerText = t.cluster;
  
  // Поправка #4: Корректный статус на русском и единичное обозначение знаков (без дублирования слова знаков)
  document.getElementById('mStatus').innerText = STATUS_RU[t.status] || t.status;
  document.getElementById('mChars').innerText = chars > 0 ? chars.toLocaleString('ru-RU') + ' зн.' : '0 зн.';
  document.getElementById('mTitle').innerText = t.title;

  // Индикатор этапа жизненного цикла статьи
  const stageLabels = {
    'backlog': 'Этап 1/5: В бэклоге (ожидает исследования и брифа)',
    'work': 'Этап 2/5: В работе (сбор фактов / подготовка брифа)',
    'needs_fix': `Этап 3/5: Черновик написан (${chars.toLocaleString('ru-RU')} зн.), требует доработки (${t.score_total !== null && t.score_total !== undefined ? t.score_total : '—'}/100)`,
    'review': `Этап 4/5: Готово к ОТК (${chars.toLocaleString('ru-RU')} зн., ${t.score_total}/100 баллов)`,
    'ready': 'Этап 5/5: Утверждено шеф-редактором (готово к выгрузке в WordPress)',
    'published': 'Этап 5/5: Опубликовано на сайте tochka-plavleniya.ru'
  };
  const stageEl = document.getElementById('mStageBadge');
  if (stageEl) {
    stageEl.innerText = stageLabels[colCode] || colCode;
    stageEl.style.background = (colCode === 'review' || colCode === 'ready' || colCode === 'published') ? '#A7F3D0' : (colCode === 'needs_fix' ? '#FEF08A' : 'var(--accent-light)');
  }

  const scoreEl = document.getElementById('mTotalScore');
  const grid = document.getElementById('mScoreGrid');
  const bBox = document.getElementById('mBlockersBox');
  const bList = document.getElementById('mBlockersList');
  const iBox = document.getElementById('mIssuesBox');
  const iList = document.getElementById('mIssuesList');

  if (t.score_total !== null && t.score_total !== undefined) {
    scoreEl.innerText = t.score_total + ' / 100';
    scoreEl.style.background = t.score_total >= 80 ? '#A7F3D0' : (t.score_total >= 60 ? '#FEF08A' : '#FECACA');
  } else {
    scoreEl.innerText = 'Не оценено';
    scoreEl.style.background = '#E5E5E5';
  }

  grid.innerHTML = '';
  const sData = t.score_data;
  if (sData && sData.blocks) {
    for (const [k, v] of Object.entries(sData.blocks)) {
      grid.innerHTML += `<div style="background:var(--bg);border:1px solid var(--border);padding:6px 8px;font-size:11px;"><strong>${v.points} / ${v.max}</strong> <span>${k}</span></div>`;
    }
  } else {
    grid.innerHTML = '<div style="grid-column:1/-1;padding:8px;background:var(--bg);border:1px solid var(--border);font-size:12px;">Текст ещё не проходил скоринг. Нажмите «Пересчитать оценку» ниже.</div>';
  }

  if (sData && sData.blockers && sData.blockers.length > 0) {
    bBox.style.display = 'block';
    bList.innerHTML = sData.blockers.map(b => `<li>${esc(b)}</li>`).join('');
  } else {
    bBox.style.display = 'none';
  }

  if (sData && sData.issues && sData.issues.length > 0) {
    iBox.style.display = 'block';
    iList.innerHTML = sData.issues.map(i => `<li>${esc(i)}</li>`).join('');
  } else {
    iBox.style.display = 'none';
  }

  const linksEl = document.getElementById('mLinksText');
  let linksStr = '';
  if (t.out_links && t.out_links.length > 0) {
    linksStr += '<strong>Исходящие:</strong> ' + t.out_links.map(l => `#${l.target_id} (${esc(l.anchor || 'ссылка')})`).join(', ') + '<br>';
  }
  if (t.in_links && t.in_links.length > 0) {
    linksStr += '<strong>Входящие:</strong> ' + t.in_links.map(l => `#${l.source_id}`).join(', ');
  }
  linksEl.innerHTML = linksStr || 'Связи не заданы';

  // Редактор текста статьи
  document.getElementById('mTextEditor').value = t.content_markdown || '';
  document.getElementById('mDiskFilePath').innerText = `сами статьи/${t.id}-${t.slug}.md`;
  document.getElementById('mTextCharsBadge').innerText = (chars || 0) + ' зн.';
  switchModalTab('score');

  // Поправка #2: Контекстные кнопки действий с защитой от затирания
  const btnRow = document.getElementById('modalActionButtons');
  btnRow.innerHTML = '';

  if (hasText) {
    if (t.score_total !== null && t.score_total >= 80 && t.status !== 'ready') {
      btnRow.innerHTML += `<button class="btn btn--success" onclick="approveArticle(${t.id})">✓ Утвердить к выгрузке (ОТК)</button>`;
    } else if (t.status === 'ready') {
      btnRow.innerHTML += `<button class="btn btn--primary" onclick="exportSingleArticle(${t.id})">📦 Проверить пакет v1.1</button>`;
    } else {
      btnRow.innerHTML += `<button class="btn btn--primary" onclick="switchModalTab('text')">✏️ Править текст статьи</button>`;
      btnRow.innerHTML += `<button class="btn" onclick="scoreCurrentArticle()">⚡ Пересчитать оценку (0-100)</button>`;
    }
    // Кнопка переписать с нуля — второстепенная с подтверждением
    btnRow.innerHTML += `<button class="btn btn--warning" onclick="confirmRewriteCurrentTopic('practico')">↻ Переписать (Practico 1)</button>`;
    btnRow.innerHTML += `<button class="btn btn--warning" onclick="confirmRewriteCurrentTopic('practico2')">↻ Переписать (Practico 2)</button>`;
  } else {
    btnRow.innerHTML += `<button class="btn btn--primary" onclick="writeArticleForCurrentTopic('practico')">✍️ Написать (Practico 1)</button>`;
    btnRow.innerHTML += `<button class="btn btn--primary" style="background:#111;color:#FFC700;" onclick="writeArticleForCurrentTopic('practico2')">✍️ Написать (Practico 2)</button>`;
  }

  if (hasText) {
    btnRow.innerHTML += `<button class="btn btn--danger" onclick="rejectArticle(${t.id})">↺ Вернуть на доработку</button>`;
  }
  // Проверка спроса и конкуренции по заголовку статьи
  btnRow.innerHTML += `<button class="btn" onclick="checkTopicPhrase(${t.id})">🔬 Проверить запрос</button>`;
  btnRow.innerHTML += `<button class="btn" onclick="closeInspector()">Закрыть</button>`;

  document.getElementById('inspectorModal').classList.add('active');
}

function switchModalTab(tab) {
  document.getElementById('modalTabBtnScore').classList.toggle('active', tab === 'score');
  document.getElementById('modalTabBtnText').classList.toggle('active', tab === 'text');
  document.getElementById('modalPanelScore').style.display = tab === 'score' ? 'block' : 'none';
  document.getElementById('modalPanelText').style.display = tab === 'text' ? 'block' : 'none';
}

async function saveEditedArticleText() {
  if (!CURRENT_TOPIC_ID) return;
  const newText = document.getElementById('mTextEditor').value;
  showToast('Сохранение текста статьи...');
  const res = await apiPost('/api/topics/update-text', { id: CURRENT_TOPIC_ID, content_markdown: newText });
  if (res && res.status === 'ok') {
    showToast(`✓ Текст сохранён в SQLite и на диск! (${res.chars} зн.)`);
    document.getElementById('mChars').innerText = res.chars.toLocaleString('ru-RU') + ' зн.';
    document.getElementById('mTextCharsBadge').innerText = res.chars + ' зн.';
    await refreshData();
  } else {
    alert('Ошибка при сохранении текста.');
  }
}

function closeInspector(e) {
  if (e && e.target !== e.currentTarget && !e.target.classList.contains('modal-close')) return;
  document.getElementById('inspectorModal').classList.remove('active');
}

function toggleNewTopicBox() {
  const box = document.getElementById('newTopicBox');
  box.style.display = box.style.display === 'none' ? 'block' : 'none';
  if (box.style.display === 'block') {
    document.getElementById('newTopicTitle').focus();
  }
}

async function createNewTopicFromInput() {
  const title = document.getElementById('newTopicTitle').value.trim();
  const cluster = document.getElementById('newTopicCluster').value;
  const type = document.getElementById('newTopicType').value;
  const priority = document.getElementById('newTopicPriority').value;
  const notes = document.getElementById('newTopicNotes').value.trim();

  if (!title) {
    alert('Пожалуйста, введите название темы!');
    return;
  }

  showToast('Добавление темы в бэклог...');
  const res = await apiPost('/api/topics/create', { title, cluster, type, priority, notes });
  if (res && res.status === 'ok') {
    showToast(`✓ Тема #${res.id} добавлена в бэклог!`);
    document.getElementById('newTopicTitle').value = '';
    document.getElementById('newTopicNotes').value = '';
    document.getElementById('newTopicBox').style.display = 'none';
    await refreshData();
  } else {
    alert('Ошибка при добавлении темы.');
  }
}

// Поправка #3: Динамический пересчёт счётчиков степпера и табов
function renderAll() {
  const sel = document.getElementById('clusterSelect');
  sel.innerHTML = '<option value="">Все кластеры</option>';
  (DB_DATA.clusters || []).forEach(c => {
    sel.innerHTML += `<option value="${esc(c.name.toLowerCase())}">${esc(c.name)}</option>`;
  });

  const nSel = document.getElementById('newTopicCluster');
  if (nSel) {
    nSel.innerHTML = '';
    (DB_DATA.clusters || []).forEach(c => {
      nSel.innerHTML += `<option value="${esc(c.name)}">${esc(c.name)}</option>`;
    });
  }

  // Расчет динамических счетчиков по колонкам
  let cntBacklog = 0, cntWork = 0, cntNeedsFix = 0, cntReview = 0, cntReady = 0, cntPublished = 0;
  (DB_DATA.topics || []).forEach(t => {
    const col = getTopicColumn(t);
    if (col === 'backlog') cntBacklog++;
    else if (col === 'work') cntWork++;
    else if (col === 'needs_fix') cntNeedsFix++;
    else if (col === 'review') cntReview++;
    else if (col === 'ready') cntReady++;
    else if (col === 'published') cntPublished++;
  });

  const proposedCnt = (DB_DATA.topics_proposed || []).filter(t => t.status === 'предложен').length;
  const writtenCnt = (DB_DATA.topics || []).filter(t => (t.chars || 0) >= 200).length;

  document.getElementById('statTopicsCount').innerText = (DB_DATA.topics || []).length;
  document.getElementById('statWrittenCount').innerText = writtenCnt;
  document.getElementById('bCountTopics').innerText = (DB_DATA.topics || []).length;
  document.getElementById('bCountProposed').innerText = (DB_DATA.topics_proposed || []).length;
  document.getElementById('bCountComps').innerText = (DB_DATA.competitors || []).length;
  document.getElementById('bCountBackups').innerText = (DB_DATA.backups || []).length;

  // Динамические счётчики степпера
  document.getElementById('stepGapsCount').innerText = proposedCnt;
  document.getElementById('stepBacklogCount').innerText = cntBacklog;
  document.getElementById('stepWorkCount').innerText = cntWork;
  document.getElementById('stepFixCount').innerText = cntNeedsFix;
  document.getElementById('stepReviewCount').innerText = cntReview;
  document.getElementById('stepReadyCount').innerText = cntReady;

  renderDecisionBanner();
  renderExportTab();
  renderKanban();
  renderProposedTopics();
  renderCompetitors();
  renderBackups();
  renderAnalysisHistory();
}

document.addEventListener('DOMContentLoaded', () => {
  renderAll();
  fetch('http://127.0.0.1:8765/api/status')
    .then(r => r.json())
    .then(() => refreshData())
    .catch(() => {});
});

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeInspector();
});
</script>
</body>
</html>"""

    stat_topics = str(len(data.get("topics", [])))
    stat_written = str(sum(1 for t in data.get("topics", []) if (t.get("chars") or 0) > 200))

    html = template.replace("__JSON_DATA__", json_blob)
    html = html.replace("__STAT_TOPICS__", stat_topics)
    html = html.replace("__STAT_WRITTEN__", stat_written)
    return html


def main() -> None:
    DASH_DIR.mkdir(parents=True, exist_ok=True)
    out_file = DASH_DIR / "index.html"
    out_file.write_text(generate_html(), encoding="utf-8")
    print(f"✓ UI успешно скомпилирован в {out_file} ({out_file.stat().st_size} байт)")


if __name__ == "__main__":
    main()
