let DB_DATA = { status: "ok", topics: [], topics_proposed: [], competitors: [], competitors_proposed: [], clusters: [], backups: [] };
let CURRENT_TOPIC_ID = null;
let ACTIVE_STEP_FILTER = null;
let CURRENT_AI_PROVIDER = 'auto';
let lastFocusedElementBeforeModal = null;

// Bulk selection state
let BULK_SELECTED = new Set();

function clearBulkSelection() {
  BULK_SELECTED.clear();
  document.querySelectorAll('.bulk-check input').forEach(cb => cb.checked = false);
  updateBulkToolbar();
}

function toggleBulkSelection(checkbox) {
  const id = parseInt(checkbox.value, 10);
  if (checkbox.checked) BULK_SELECTED.add(id);
  else BULK_SELECTED.delete(id);
  updateBulkToolbar();
}

function updateBulkToolbar() {
  const toolbar = document.getElementById('bulkToolbar');
  const countEl = document.getElementById('bulkCount');
  if (!toolbar || !countEl) return;
  const count = BULK_SELECTED.size;
  countEl.innerText = count;
  toolbar.style.display = count > 0 ? 'flex' : 'none';
}

async function bulkAction(action) {
  if (BULK_SELECTED.size === 0) return;
  const ids = Array.from(BULK_SELECTED);
  let success = 0, failed = 0;
  
  for (const id of ids) {
    try {
      let res;
      if (action === 'approve') res = await apiPost('/api/articles/approve', { id });
      else if (action === 'reject') res = await apiPost('/api/articles/reject', { id, reason: 'Массовая отправка на доработку' });
      else if (action === 'score') res = await apiPost('/api/agent/run', { agent: '04-scorer', target_id: id });
      else if (action === 'export_wp') res = await apiPost('/api/agent/run', { agent: 'delivery', target_id: id });
      else continue;
      
      if (res && res.status === 'succeeded') success++;
      else failed++;
    } catch (e) { failed++; }
  }
  
  showToast(`Массовое действие: ${success} успешно, ${failed} ошибок`);
  clearBulkSelection();
  await refreshData();
}

function setGlobalProvider(val) {
  CURRENT_AI_PROVIDER = val || 'auto';
  const mSel = document.getElementById('modalProviderSelect');
  if (mSel && mSel.value !== CURRENT_AI_PROVIDER) mSel.value = CURRENT_AI_PROVIDER;
  const aSel = document.getElementById('agentProviderSelect');
  if (aSel && aSel.value !== CURRENT_AI_PROVIDER) aSel.value = CURRENT_AI_PROVIDER;
  const pNames = {
    auto: '⚡ Авто-каскад (Triad 0₽ → OmniRoute → Claude → DeepSeek → Yandex)',
    triad: '🐺 Triad-Omni Edge Cluster (Qwen3.5-9B) — 0 ₽, без VPN',
    omniroute: '🌐 OmniRoute Gateway (:20128, 750+ моделей)',
    claude: '🟣 Claude Sonnet — VPN ON',
    deepseek: '🔵 DeepSeek Chat — VPN не нужен',
    yandex: '🟡 YandexGPT Pro 32k — VPN не нужен'
  };
  showToast(`Канал генерации: ${pNames[CURRENT_AI_PROVIDER] || CURRENT_AI_PROVIDER}`);
}

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
  // Ручные переносы (drag-and-drop) — приоритет над расчётом по данным
  if (t.status === 'review') return 'review';
  if (t.status === 'work') return 'work';
  if (t.status === 'backlog') return 'backlog';
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
  } else if (name === 'competitors') {
    updatePromptPreviewText();
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

function goHome() {
  resetStepFilter();
  const sInput = document.getElementById('kanbanSearch');
  if (sInput) sInput.value = '';
  const cSel = document.getElementById('clusterSelect');
  if (cSel) cSel.value = '';
  switchTab('kanban');
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

const API_BASE = (typeof window !== 'undefined' && window.location && window.location.origin && window.location.origin.startsWith('http'))
  ? window.location.origin
  : 'http://127.0.0.1:8765';

let toastTimeout = null;
function showToast(msg, type = 'info', actionText = '', onAction = null) {
  const t = document.getElementById('toast');
  if (!t) return;
  t.innerHTML = '';
  t.className = 'toast' + (type ? ` toast--${type}` : '');
  
  const span = document.createElement('span');
  span.innerText = msg;
  t.appendChild(span);

  if (actionText && typeof onAction === 'function') {
    const actBtn = document.createElement('button');
    actBtn.type = 'button';
    actBtn.className = 'toast__action-btn';
    actBtn.innerText = actionText;
    actBtn.onclick = (e) => {
      e.stopPropagation();
      onAction();
      t.style.display = 'none';
      if (toastTimeout) clearTimeout(toastTimeout);
    };
    t.appendChild(actBtn);
  }

  t.style.display = 'inline-flex';
  t.style.alignItems = 'center';
  if (toastTimeout) clearTimeout(toastTimeout);
  toastTimeout = setTimeout(() => { t.style.display = 'none'; }, actionText ? 6000 : 3500);
}

async function apiPost(route, payload) {
  try {
    const res = await fetch(API_BASE + route, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload || {})
    });
    return await res.json();
  } catch (e) {
    showToast('Ошибка сети: ' + (e.message || 'нет связи с сервером'), 'error');
    return null;
  }
}

async function apiGet(route) {
  try {
    const res = await fetch(API_BASE + route);
    return await res.json();
  } catch (e) {
    showToast('Ошибка сети: ' + (e.message || 'нет связи с сервером'), 'error');
    return null;
  }
}

async function refreshData() {
  try {
    const res = await fetch(API_BASE + '/api/data');
    if (res.ok) {
      DB_DATA = await res.json();
      renderAll();
      showToast('✓ Данные обновлены из SQLite БД', 'success');
      return;
    }
  } catch (e) {}
  renderAll();
  showToast('✓ Интерфейс перерисован', 'info');
}

// ---------------------------------------------------------------------------
// Анализ поисковых запросов: спрос (Вордстат, бесплатно) + конкуренция (Мутаген)
// ---------------------------------------------------------------------------
function setAnalysisPhrase(phrase) {
  const input = document.getElementById('analysisPhrase');
  if (input) {
    input.value = phrase;
    input.scrollIntoView({ behavior: 'smooth', block: 'center' });
    input.focus();
  }
}

async function refreshMutagenBalance() {
  const el = document.getElementById('analysisMutagenBalance');
  const badge = document.getElementById('mutagenBalanceBadge');
  try {
    const res = await apiGet('/api/mutagen/balance');
    if (res && res.status === 'ok') {
      const bal = Number(res.balance || 0).toFixed(2);
      const checks = Math.floor(res.balance / 0.30);
      const text = `${bal} ₽ (~${checks} проверок)`;
      if (el) el.textContent = text;
      if (badge) badge.textContent = `${bal} ₽`;
    }
  } catch (e) {
    console.error('Ошибка загрузки баланса Мутагена:', e);
  }
}

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
    showToast('Введите фразу для проверки');
    return;
  }
  if (source === 'mutagen' || source === 'both') {
    const balEl = document.getElementById('analysisMutagenBalance');
    const curBal = balEl ? balEl.textContent : '15.92 ₽';
    if (!confirm(`Проверить фразу «${phrase}» в платном API Мутагена?
Это спишет 0.30 ₽ с баланса (${curBal}).

Продолжить?`)) {
      return;
    }
  }
  return checkPhraseValue(phrase, source, false);
}

// Перепроверка фразы из таблицы истории (игнорирует кэш)
function recheckPhraseByIndex(index, source) {
  const row = ANALYSIS_ROWS[index];
  if (!row) return;
  const input = document.getElementById('analysisPhrase');
  if (input) input.value = row.phrase;
  if (source === 'mutagen' || source === 'both') {
    const balEl = document.getElementById('analysisMutagenBalance');
    const curBal = balEl ? balEl.textContent : '15.92 ₽';
    if (!confirm(`Повторно проверить «${row.phrase}» в Мутагене?
Это спишет 0.30 ₽ с баланса (${curBal}).`)) {
      return;
    }
  }
  checkPhraseValue(row.phrase, source, true);
}

async function checkPhraseValue(phrase, source, refresh) {
  const box = document.getElementById('analysisResult');
  box.style.display = 'block';

  // Блокируем кнопки на время запроса
  const allBtns = document.querySelectorAll('#view-analysis button');
  allBtns.forEach(b => { b.disabled = true; b.style.opacity = '0.6'; });

  const startTime = Date.now();
  function renderLoading() {
    const elapsed = Math.floor((Date.now() - startTime) / 1000);
    let stepTitle = 'Связываюсь с сервером...';
    if (source === 'mutagen') {
      if (elapsed < 3) stepTitle = 'Постановка задачи в API Мутагена...';
      else if (elapsed < 8) stepTitle = 'Мутаген анализирует ТОП-10 Яндекса и хвосты...';
      else stepTitle = 'Расчёт силы конкуренции и ставок Директа...';
    } else {
      stepTitle = 'Сбор частотности из Яндекс Вордстат...';
    }

    box.innerHTML = `
      <div class="loading-card">
        <div class="loading-card__spinner"></div>
        <div style="flex:1;">
          <div class="loading-card__title">
            <span>${source === 'mutagen' ? 'Мутаген (проверка конкуренции)' : 'Вордстат (сбор спроса)'}: «${esc(phrase)}»</span>
            <span class="loading-card__timer">${elapsed} сек</span>
          </div>
          <div class="loading-card__step">
            <span class="loading-card__step-dot"></span>
            <span>${stepTitle}</span>
          </div>
        </div>
      </div>
    `;
  }

  renderLoading();
  const loadingInterval = setInterval(renderLoading, 500);

  let res = null;
  try {
    res = await apiPost('/api/keyword/check', { phrase: phrase, source: source, refresh: refresh });
  } catch (err) {
    res = null;
  } finally {
    clearInterval(loadingInterval);
    allBtns.forEach(b => { b.disabled = false; b.style.opacity = ''; });
  }

  if (!res || res.status !== 'ok') {
    box.innerHTML = `<div style="padding:12px;background:#FEE2E2;border:1.5px solid #B91C1C;border-radius:var(--radius-sm);font-size:12px;color:#7F1D1D;">
      <strong>❌ Не удалось проверить фразу.</strong><br>
      ${(res && res.notes && res.notes.join('<br>')) || 'Сервис не ответил или вернул ошибку сети. Проверьте баланс или повторите через минуту.'}
    </div>`;
    return;
  }

  const ws = res.wordstat || null;
  const mg = res.mutagen || null;
  let html = `<div style="border:1.5px solid var(--border);border-radius:var(--radius-md);background:var(--card);padding:14px;box-shadow:0 2px 6px rgba(0,0,0,0.03);">
    <div style="font-size:14px;font-weight:800;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;">
      <span>«${esc(res.phrase)}»</span>
      <span style="font-size:11px;font-family:var(--mono);color:var(--muted);">${source === 'mutagen' ? 'Проверка: Мутаген (0,30 ₽)' : 'Проверка: Вордстат (бесплатно)'}</span>
    </div>`;

  if (ws) {
    html += `<div style="margin-bottom:8px;">
      <strong>Широкая частотность (Вордстат):</strong> <strong style="font-family:var(--mono);">${(ws.totalCount || 0).toLocaleString('ru-RU')}</strong> показов/мес
      ${ws.cached ? '<span style="color:var(--muted);font-size:11px;"> · из кэша, бесплатно</span>' : ''}
    </div>`;

    // 1. Похожие запросы (левая колонка Вордстата)
    if (ws.results && ws.results.length) {
      html += '<div style="font-size:11px;color:var(--muted);margin-bottom:4px;">Топ похожих запросов (хвосты людей):</div><ul style="margin:0 0 8px 16px;font-size:12px;">';
      ws.results.slice(0, 5).forEach(r => {
        html += `<li>${esc(r.phrase)} — <code>${(r.count || 0).toLocaleString('ru-RU')}</code></li>`;
      });
      html += '</ul>';
    }

    // 2. Ассоциации (правая колонка Вордстата — что искали рядом)
    if (ws.associations && ws.associations.length) {
      html += `
        <div style="margin:8px 0;padding:8px 10px;background:#F0FDF4;border:1px solid #86EFAC;border-radius:var(--radius-sm);">
          <div style="font-size:11px;font-weight:700;color:#166534;margin-bottom:6px;display:flex;align-items:center;gap:6px;">
            <span>🔗 Искали рядом в той же сессии (Ассоциации для перелинковки):</span>
          </div>
          <div style="display:flex;gap:6px;flex-wrap:wrap;">
            ${ws.associations.map(a => `
              <button class="btn btn--xs" type="button" onclick="setAnalysisPhrase('${esc(a.phrase)}')" title="Кликните, чтобы проверить этот смежный запрос" style="background:#FFFFFF;border-color:#86EFAC;color:#166534;">
                ${esc(a.phrase)} <span style="color:#059669;font-weight:700;margin-left:4px;">${Number(a.count).toLocaleString('ru-RU')}</span>
              </button>
            `).join('')}
          </div>
        </div>
      `;
    }

    // 3. Сезонность и помесячный тренд спроса
    if (ws.trend && ws.trend.points && ws.trend.points.length) {
      const t = ws.trend;
      const dirColor = t.direction === 'up' ? '#059669' : (t.direction === 'down' ? '#B91C1C' : '#6B7280');
      const dirIcon = t.direction === 'up' ? '📈' : (t.direction === 'down' ? '📉' : '➡️');
      const dirLabel = t.direction === 'up' 
        ? `Растущий спрос: +${t.change_pct}% за 6 мес (пик в ${t.peak_month}: ${Number(t.peak_count).toLocaleString('ru-RU')})`
        : (t.direction === 'down' ? `Спад спроса: ${t.change_pct}% за 6 мес` : `Стабильный спрос (${t.change_pct}%)`);

      const maxVal = Math.max(...t.points.map(p => p.count), 1);
      const barsHtml = t.points.map(p => {
        const h = Math.max(6, Math.round((p.count / maxVal) * 36));
        const isPeak = p.month === t.peak_month;
        return `
          <div style="display:flex;flex-direction:column;align-items:center;gap:3px;font-size:10px;font-family:var(--mono);" title="${p.month}: ${Number(p.count).toLocaleString('ru-RU')} показов">
            <span style="color:${isPeak ? '#B45309' : 'var(--muted)'};font-weight:${isPeak ? '700' : '500'};">${p.count > 999 ? (p.count/1000).toFixed(0) + 'k' : p.count}</span>
            <div style="width:26px;height:${h}px;background:${isPeak ? 'var(--accent)' : '#CBD5E1'};border:1px solid ${isPeak ? '#000' : '#94A3B8'};border-radius:2px 2px 0 0;"></div>
            <span style="color:var(--muted);">${p.month.slice(5)}</span>
          </div>
        `;
      }).join('');

      html += `
        <div style="margin:8px 0;padding:8px 12px;background:#F8FAFC;border:1px solid var(--border-hairline);border-radius:var(--radius-sm);">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
            <span style="font-weight:700;font-size:11.5px;color:${dirColor};">${dirIcon} ${dirLabel}</span>
            <span style="font-size:10px;color:var(--muted);">Яндекс Search API v2</span>
          </div>
          <div style="display:flex;align-items:flex-end;gap:12px;height:56px;padding-top:6px;border-top:1px dashed #E2E8F0;">
            ${barsHtml}
          </div>
        </div>
      `;
    }
  }

  if (mg) {
    const comp = mg.strong;
    const hasComp = (comp !== null && comp !== undefined && comp !== '');
    const color = hasComp ? (comp <= 10 ? '#059669' : (comp <= 18 ? '#B45309' : '#B91C1C')) : '#6B7280';
    html += `<div style="margin-top:10px;padding-top:10px;border-top:1px dashed var(--border-hairline);">
      <strong>Конкуренция (Мутаген):</strong> <span style="color:${color};font-weight:800;font-size:13px;">${hasComp ? comp + '/25' : '— / 25'}</span>
      · точный спрос в кавычках: <code style="font-size:12px;">${mg.wordstat != null ? (mg.wordstat).toLocaleString('ru-RU') : '—'}</code>
      · хвосты: <code style="font-size:12px;">${mg.tails != null ? (mg.tails).toLocaleString('ru-RU') : '—'}</code>
      ${mg.vital_site ? `<br><strong>Витальный сайт в топе:</strong> ${esc(mg.vital_site)}` : '<br>Витального сайта в топе нет — путь открыт'}
      ${mg.cached ? '<span style="color:var(--muted);font-size:11px;"> · из кэша, бесплатно</span>' : ''}
    </div>`;
    // Трактовка
    let verdict = '';
    if (!hasComp) {
      verdict = '⏳ Конкуренция ещё вычисляется сервисом';
    } else if (comp <= 10 && (ws && ws.totalCount > 300)) {
      verdict = '✅ Отличная цель: спрос есть, конкуренция низкая (≤10/25)';
    } else if (comp <= 18) {
      verdict = '⚠️ Рабочая цель: средняя конкуренция (11–18/25), нужен глубокий материал';
    } else {
      verdict = '❌ Высокая конкуренция (>18/25): пробиться в топ сложно';
    }
    html += `<div style="margin-top:10px;font-weight:700;padding:6px 10px;background:#F8FAFC;border:1px solid var(--border-hairline);border-radius:var(--radius-sm);">${verdict}</div>`;
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
  refreshMutagenBalance();
}

let ANALYSIS_ROWS = [];

async function renderAnalysisHistory() {
  const tb = document.getElementById('analysisBody');
  if (!tb) return;
  try {
    const res = await fetch(API_BASE + '/api/keyword/history');
    const data = await res.json();
    const rows = data.history || [];
    ANALYSIS_ROWS = rows;
    const badge = document.getElementById('bCountAnalysis');
    if (badge) badge.innerText = rows.length;

    if (!rows.length) {
      tb.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:20px;color:var(--muted);">Проверок пока нет. Введите фразу выше.</td></tr>';
      return;
    }
    tb.innerHTML = rows.map((r, i) => {
      const comp = r.competition;
      const hasComp = comp != null;
      const compClass = hasComp ? (comp <= 10 ? 'score-pill--high' : (comp <= 18 ? 'score-pill--mid' : 'score-pill--low')) : '';
      const compHtml = hasComp 
        ? `<span class="score-pill ${compClass}">${comp}/25</span>` 
        : '<span style="color:var(--muted);">—</span>';

      const rawBroad = r.demand_broad != null ? r.demand_broad : (r.source === 'wordstat' ? r.demand : null);
      const broadDemand = rawBroad != null 
        ? `<strong style="font-family:var(--mono);">${Number(rawBroad).toLocaleString('ru-RU')}</strong>` 
        : '<span style="color:var(--muted);">—</span>';

      const rawExact = r.demand_exact != null ? r.demand_exact : (r.source === 'mutagen' ? r.demand : null);
      const exactDemand = rawExact != null 
        ? `<code style="font-size:11px;">${Number(rawExact).toLocaleString('ru-RU')}</code>` 
        : '<span style="color:var(--muted);">—</span>';

      const tails = r.tails != null 
        ? `<span style="font-family:var(--mono);font-size:11px;">${Number(r.tails).toLocaleString('ru-RU')}</span>` 
        : '<span style="color:var(--muted);">—</span>';

      const bids = (r.bid_spec != null || r.bid_first != null)
        ? `<span style="font-family:var(--mono);font-size:10.5px;color:#047857;" title="Ставки клика: спецразмещение / 1-е место">${r.bid_spec || 0} ₽ / ${r.bid_first || 0} ₽</span>`
        : '<span style="color:var(--muted);font-size:11px;">—</span>';

      return `
        <tr>
          <td>
            <strong>${esc(r.phrase)}</strong>
            <div style="font-size:10px;color:var(--muted);margin-top:2px;">
              ${r.has_wordstat ? '<span style="color:#059669;">● Вордстат</span>' : ''}
              ${r.has_mutagen ? '<span style="color:#D97706;margin-left:4px;">● Мутаген</span>' : ''}
            </div>
          </td>
          <td>${broadDemand}</td>
          <td>${exactDemand}</td>
          <td>${tails}</td>
          <td>${compHtml}</td>
          <td>${bids}</td>
          <td style="font-size:11px;">${esc(r.vital_site || '—')}</td>
          <td style="font-family:var(--mono);font-size:11px;color:var(--muted);">${(r.checked_at || '').replace('T', ' ').slice(0, 16)}</td>
          <td style="white-space:nowrap;">
            <button class="btn btn--xs" title="Перепроверить в Вордстате (бесплатно)" onclick="recheckPhraseByIndex(${i}, 'auto')">↻ Вордстат</button>
            <button class="btn btn--xs btn--warning" title="Перепроверить в Мутагене (0,30 ₽)" onclick="recheckPhraseByIndex(${i}, 'mutagen')">↻ Мутаген</button>
            <button class="btn btn--xs" title="Показать историю измерений" onclick="showPhraseTrend(${i})">📈 История</button>
          </td>
        </tr>
      `;
    }).join('');
  } catch (e) {
    tb.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--muted);">История недоступна</td></tr>';
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
  showModalLoading(`⚡ Скоринг статьи #${CURRENT_TOPIC_ID}`, 'Проверка 100-балльной рубрики: факты, температуры, ГОСТ/IPC, разметка и ссылки...');
  try {
    const res = await apiPost('/api/agent/run', { agent: '04-scorer', target_id: CURRENT_TOPIC_ID });
    if (res && res.log) {
      document.getElementById('agentConsole').innerText = res.log;
      showToast('✓ Скоринг завершён!');
      await refreshData();
      openInspector(CURRENT_TOPIC_ID);
    }
  } catch (e) {
    showToast('⚠️ Ошибка скоринга');
  } finally {
    hideModalLoading();
  }
}

// Поправка #2: Защита от затирания существующего текста
function confirmRewriteCurrentTopic(voice) {
  if (!CURRENT_TOPIC_ID) return;
  const v = voice || 'practico';
  const vName = v === 'practico2' ? 'Practico 2' : 'Practico 1';
  const t = DB_DATA.topics.find(x => x.id === CURRENT_TOPIC_ID);
  const chars = (t && t.chars) || 0;
  if (!confirm(`⚠️ ВНИМАНИЕ: У статьи #${CURRENT_TOPIC_ID} уже написан текст (${chars.toLocaleString('ru-RU')} зн.)!

Переписать заново голосом ${vName}? Текущий текст будет заменён.`)) {
    return;
  }
  writeArticleForCurrentTopic(v);
}

async function writeArticleForCurrentTopic(voice) {
  if (!CURRENT_TOPIC_ID) return;
  const v = voice || 'practico';
  const provider = CURRENT_AI_PROVIDER || 'auto';
  const providerNames = {
    auto: 'Авто (Claude → DeepSeek → Yandex)',
    claude: 'Claude Sonnet — VPN ON',
    deepseek: 'DeepSeek Chat — VPN не нужен',
    yandex: 'YandexGPT Pro 32k — VPN не нужен'
  };
  const voiceLabel = v === 'practico2' ? 'Practico 2 — голос заказчика' : 'Practico 1 — инженер';
  closeInspector();
  showToast(`Запуск #${CURRENT_TOPIC_ID}: ${voiceLabel}, канал ${providerNames[provider] || provider}...`);
  const c = document.getElementById('agentConsole');
  c.innerText = `[${new Date().toLocaleTimeString()}] Запуск Агента 02
Голос: ${voiceLabel}
Канал ИИ: ${providerNames[provider] || provider}
Ожидание генерации текста...`;
  switchTab('agents');
  const res = await apiPost('/api/agent/run', { agent: '02-writer', target_id: CURRENT_TOPIC_ID, voice: v, provider: provider });
  if (res && res.log) {
    c.innerText = res.log;
    showToast(res.status === 'succeeded' ? `✓ Статья #${CURRENT_TOPIC_ID} написана!` : `⚠️ Ошибка генерации`);
    await refreshData();
    openInspector(CURRENT_TOPIC_ID);
  }
}

// --- Критичность, рецензия и фактчек (Oxion AI) ---------------------------
function togglePasteBox() {
  const box = document.getElementById('mPasteBox');
  if (box) box.style.display = box.style.display === 'none' ? 'block' : 'none';
}

function updateChecksStatus(t) {
  const el = document.getElementById('mChecksStatus');
  if (!el) return;
  const parts = [];
  if (t && t.is_critical) parts.push('⚠️ критичная');
  if (t && t.review_model) parts.push(`рецензия: ${t.review_model}`);
  if (t && t.factcheck_model) parts.push(`фактчек: ${t.factcheck_model}`);
  el.innerText = parts.join(' · ');
}

async function toggleCritical() {
  if (!CURRENT_TOPIC_ID) return;
  const el = document.getElementById('mCriticalCheck');
  const critical = !!(el && el.checked);
  await apiPost('/api/articles/critical', { id: CURRENT_TOPIC_ID, critical });
  showToast(critical ? '⚠️ Критичная статья: рецензия пойдёт на Opus' : 'Обычная статья: рецензия на Gemini');
  await refreshData();
  openInspector(CURRENT_TOPIC_ID);
}

async function runReviewerForCurrentTopic() {
  if (!CURRENT_TOPIC_ID) return;
  const t = DB_DATA.topics.find(x => x.id === CURRENT_TOPIC_ID) || {};
  const model = t.is_critical ? 'Claude Opus 4.6' : 'Gemini 3.7 Flash';
  showModalLoading(`🧐 Рецензия статьи #${CURRENT_TOPIC_ID}`, `Модель ${model} проверяет факты, безопасность и стандарты...`);
  try {
    const res = await apiPost('/api/agent/run', { agent: '03-reviewer', target_id: CURRENT_TOPIC_ID });
    showToast(res && res.status === 'succeeded' ? '✓ Рецензия готова' : '⚠️ Ошибка рецензии');
    await refreshData();
    if (CURRENT_TOPIC_ID) openInspector(CURRENT_TOPIC_ID);
  } catch (e) {
    showToast('⚠️ Ошибка соединения при рецензии');
  } finally {
    hideModalLoading();
  }
}

async function runFactcheckForCurrentTopic() {
  if (!CURRENT_TOPIC_ID) return;
  showModalLoading(`🔬 Фактчек статьи #${CURRENT_TOPIC_ID}`, 'Модель DeepSeek V4 Pro сверяет числа, сплавы и ГОСТ...');
  try {
    const res = await apiPost('/api/agent/run', { agent: 'factcheck', target_id: CURRENT_TOPIC_ID });
    showToast(res && res.status === 'succeeded' ? '✓ Фактчек готов' : '⚠️ Ошибка фактчека');
    await refreshData();
    if (CURRENT_TOPIC_ID) openInspector(CURRENT_TOPIC_ID);
  } catch (e) {
    showToast('⚠️ Ошибка соединения при фактчеке');
  } finally {
    hideModalLoading();
  }
}

// --- Голоса автора: загрузка, редактирование, сохранение ------------------
let VOICES = [];

async function loadVoices(selectId) {
  const data = await apiGet('/api/voices');
  VOICES = (data && data.voices) || [];
  const sel = document.getElementById('mVoiceSelect');
  if (!sel) return;
  sel.innerHTML = VOICES.map(v => `<option value="${esc(v.id)}">${esc(v.name)}</option>`).join('');
  const id = selectId || (VOICES[0] && VOICES[0].id);
  if (id) { sel.value = id; loadVoiceIntoEditor(id); }
}

function loadVoiceIntoEditor(id) {
  const v = VOICES.find(x => x.id === id);
  const st = document.getElementById('mVoiceStatus');
  if (!v) { if (st) st.innerText = 'Голос не найден'; return; }
  document.getElementById('mVoiceName').value = v.name || '';
  document.getElementById('mVoicePrompt').value = v.system_prompt || '';
  const modelSel = document.getElementById('mVoiceModel');
  if (modelSel && v.model) {
    const has = Array.from(modelSel.options).some(o => o.value === v.model);
    if (!has) {
      const opt = document.createElement('option');
      opt.value = v.model; opt.textContent = v.model;
      modelSel.appendChild(opt);
    }
    modelSel.value = v.model;
  }
  if (st) st.innerText = `Голос «${v.name}» · ${(v.system_prompt || '').length} знаков · ${v.model || ''}`;
}

function startNewVoice() {
  document.getElementById('mVoiceName').value = '';
  document.getElementById('mVoicePrompt').value = '';
  document.getElementById('mVoiceStatus').innerText = 'Новый голос: задайте id (латиницей), название, инструкцию и модель';
  document.getElementById('mVoiceName').focus();
}

async function saveVoice() {
  if (!CURRENT_TOPIC_ID) return;
  const name = (document.getElementById('mVoiceName').value || '').trim();
  const prompt = (document.getElementById('mVoicePrompt').value || '').trim();
  const model = document.getElementById('mVoiceModel').value || 'oxion:claude-sonnet-5';
  if (!name) { showToast('Укажите название голоса'); return; }
  if (prompt.length < 50) { showToast('Инструкция голоса слишком короткая (≥ 50 знаков)'); return; }
  const existing = document.getElementById('mVoiceSelect').value;
  const isNew = !VOICES.some(v => v.id === existing) || document.getElementById('mVoicePrompt').value.trim().length === 0;
  const voiceId = (isNew ? name : (VOICES.find(v => v.id === existing) || {}).id)
    .toLowerCase().replace(/[^a-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '') || ('voice-' + Date.now());
  const res = await apiPost('/api/voices/save', { id: voiceId, name, system_prompt: prompt, model });
  if (res && res.status === 'ok') {
    showToast(`✓ Голос «${name}» сохранён`);
    await loadVoices(voiceId);
  } else {
    showToast('⚠️ ' + ((res && res.message) || 'Не удалось сохранить голос'));
  }
}

async function deleteVoice() {
  const id = document.getElementById('mVoiceSelect').value;
  const v = VOICES.find(x => x.id === id);
  if (!v) return;
  if (!confirm(`Удалить голос «${v.name}»?`)) return;
  await apiPost('/api/voices/delete', { id });
  showToast('Голос удалён');
  await loadVoices();
}

function selectedVoicePayload() {
  const voice = document.getElementById('mVoiceSelect').value || 'practico';
  const model = document.getElementById('mVoiceModel').value || 'oxion:claude-sonnet-5';
  const name = (VOICES.find(v => v.id === voice) || {}).name || voice;
  return { voice, model, name };
}

async function writeWithSelectedVoice() {
  if (!CURRENT_TOPIC_ID) return;
  const { voice, model, name } = selectedVoicePayload();
  const t = DB_DATA.topics.find(x => x.id === CURRENT_TOPIC_ID);
  if (t && (t.chars || 0) >= 200 && !confirm(`У статьи #${CURRENT_TOPIC_ID} уже есть текст. Переписать голосом «${name}»?`)) return;
  showToast(`Пишем голосом «${name}» (${model})...`);
  const c = document.getElementById('agentConsole');
  if (c) c.innerText = `[${new Date().toLocaleTimeString()}] Написание статьи
Голос: ${name}
Модель: ${model}
Ожидание...`;
  switchTab('agents');
  const res = await apiPost('/api/agent/run', {
    agent: '02-writer', target_id: CURRENT_TOPIC_ID, voice, model, provider: CURRENT_AI_PROVIDER || 'auto'
  });
  if (c && res) c.innerText = res.log || JSON.stringify(res);
  showToast(res && res.status === 'succeeded' ? `✓ Статья написана голосом «${name}»` : '⚠️ Ошибка написания');
  await refreshData();
  if (CURRENT_TOPIC_ID) openInspector(CURRENT_TOPIC_ID);
}

let CURRENT_ORIGINAL_TEXT = '';

function showModalLoading(title, desc) {
  const o = document.getElementById('mModalLoadingOverlay');
  if (!o) return;
  document.getElementById('mModalLoadingTitle').innerText = title || 'Обработка...';
  if (desc) document.getElementById('mModalLoadingDesc').innerText = desc;
  o.style.display = 'flex';
}

function hideModalLoading() {
  const o = document.getElementById('mModalLoadingOverlay');
  if (o) o.style.display = 'none';
}

let AUTOSAVE_TIMER = null;

function handleEditorInput() {
  updateLiveCharCounter();
  updateWorkbenchLiveAssistant();

  if (!CURRENT_TOPIC_ID) return;
  const statusEl = document.getElementById('mAutosaveStatus');
  if (statusEl) statusEl.innerText = 'Изменения...';

  clearTimeout(AUTOSAVE_TIMER);
  AUTOSAVE_TIMER = setTimeout(() => {
    try {
      const editor = document.getElementById('mTextEditor');
      if (!editor) return;
      const key = `tchp_draft_${CURRENT_TOPIC_ID}`;
      const timeStr = new Date().toLocaleTimeString('ru-RU');
      localStorage.setItem(key, JSON.stringify({
        topic_id: CURRENT_TOPIC_ID,
        text: editor.value,
        time: timeStr,
        timestamp: Date.now()
      }));
      if (statusEl) statusEl.innerText = `Локально: ${timeStr}`;
    } catch (e) {}
  }, 350);
}

function discardDraft(topicId) {
  try {
    localStorage.removeItem(`tchp_draft_${topicId}`);
    const t = (DB_DATA.topics || []).find(x => x.id === topicId) || {};
    const editor = document.getElementById('mTextEditor');
    if (editor) editor.value = t.content_markdown || '';
    const statusEl = document.getElementById('mAutosaveStatus');
    if (statusEl) statusEl.innerText = '[Сброшено к БД]';
    updateLiveCharCounter();
    updateWorkbenchLiveAssistant();
  } catch (e) {}
}

function updateLiveCharCounter() {
  const ta = document.getElementById('mTextEditor');
  const counter = document.getElementById('mTextLiveCounter');
  const badge = document.getElementById('mTextCharsBadge');
  if (!ta) return;
  const len = (ta.value || '').length;
  if (badge) badge.innerText = len.toLocaleString('ru-RU') + ' зн.';
  if (counter) {
    let color = '#D97706';
    let label = 'коротко';
    if (len >= 5500 && len <= 7000) {
      color = '#059669';
      label = 'норма (5 500-7 000)';
    } else if (len > 7000) {
      color = '#DC2626';
      label = 'длинно (>7 000)';
    } else if (len === 0) {
      label = 'пусто';
      color = 'var(--muted)';
    }
    counter.innerHTML = `<span style="color:${color};font-weight:700;">${len.toLocaleString('ru-RU')} зн.</span> <span style="font-size:10.5px;color:var(--muted);">(${label})</span>`;
  }

  // Подсчёт слов в первом абзаце (Answer First <= 40 слов)
  const afBadge = document.getElementById('mAnswerFirstBadge');
  if (afBadge) {
    let clean = (ta.value || '').replace(/^---\s*\n[\s\S]+?\n---\s*\n*/, '');
    clean = clean.replace(/^#\s+[^\n]+\n*/, '').trim();
    const paras = clean.split(/\n\s*\n/).map(p => p.trim()).filter(p => p && !p.startsWith('#'));
    const firstPara = paras[0] || '';
    const words = firstPara ? firstPara.trim().split(/\s+/).filter(Boolean) : [];
    const wCount = words.length;
    let afColor = '#059669';
    let afBg = '#ECFDF5';
    let afLabel = 'OK';
    if (wCount === 0) {
      afColor = 'var(--muted)';
      afBg = '#FFF';
      afLabel = 'нет лида';
    } else if (wCount > 40 && wCount <= 50) {
      afColor = '#D97706';
      afBg = '#FFFBEB';
      afLabel = 'лимит ≤40';
    } else if (wCount > 50) {
      afColor = '#DC2626';
      afBg = '#FEF2F2';
      afLabel = 'перебор';
    }
    afBadge.style.color = afColor;
    afBadge.style.borderColor = afColor;
    afBadge.style.backgroundColor = afBg;
    afBadge.innerHTML = `ЛИД: <strong>${wCount}/40</strong> СЛОВ <span style="font-size:9.5px;font-weight:normal;">(${afLabel})</span>`;
  }
}

function renderSimpleMarkdown(md) {
  if (!md) return '<p style="color:var(--muted);font-style:italic;">Текст статьи пока отсутствует.</p>';
  let text = md.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  text = text.replace(/^### (.*$)/gim, '<h3 style="font-size:14px;font-weight:700;margin:14px 0 6px;color:var(--ink);">$1</h3>');
  text = text.replace(/^## (.*$)/gim, '<h2 style="font-size:16px;font-weight:800;border-bottom:1.5px solid #E5E7EB;padding-bottom:4px;margin:18px 0 8px;color:var(--ink);">$1</h2>');
  text = text.replace(/^# (.*$)/gim, '<h1 style="font-size:20px;font-weight:800;margin:8px 0 12px;color:var(--ink);">$1</h1>');
  text = text.replace(/^> (.*$)/gim, '<blockquote style="border-left:3.5px solid var(--accent);background:#FFFBEB;padding:8px 12px;margin:10px 0;font-size:12.5px;font-style:italic;line-height:1.5;">$1</blockquote>');
  text = text.replace(/\*\*(.*?)\*\*/gim, '<strong>$1</strong>');
  text = text.replace(/\*(.*?)\*/gim, '<em>$1</em>');
  text = text.replace(/`([^`]+)`/gim, '<code style="background:#F3F4F6;padding:2px 5px;font-family:var(--mono);font-size:11.5px;border-radius:3px;">$1</code>');
  
  const lines = text.split(/\r?\n/);
  let inTable = false;
  let out = [];
  for (let i = 0; i < lines.length; i++) {
    const l = lines[i].trim();
    if (l.startsWith('|') && l.endsWith('|')) {
      if (!inTable) {
        inTable = true;
        out.push('<div style="overflow-x:auto;margin:12px 0;"><table style="width:100%;border-collapse:collapse;font-size:11.5px;border:1px solid #D1D5DB;">');
      }
      if (l.includes('---')) continue;
      const cells = l.split('|').slice(1, -1).map(c => c.trim());
      out.push('<tr>' + cells.map(c => `<td style="border:1px solid #D1D5DB;padding:5px 8px;">${c}</td>`).join('') + '</tr>');
    } else {
      if (inTable) {
        inTable = false;
        out.push('</table></div>');
      }
      if (l.startsWith('- ') || l.startsWith('* ')) {
        out.push(`<li style="margin-left:18px;font-size:12.5px;line-height:1.5;">${l.substring(2)}</li>`);
      } else if (l === '') {
        out.push('<br>');
      } else if (!l.startsWith('<h') && !l.startsWith('<block')) {
        out.push(`<p style="margin:5px 0;font-size:12.5px;line-height:1.6;">${l}</p>`);
      } else {
        out.push(l);
      }
    }
  }
  if (inTable) out.push('</table></div>');
  return out.join('');
}

function toggleMarkdownPreview() {
  const ta = document.getElementById('mTextEditor');
  const prev = document.getElementById('mTextPreview');
  const btn = document.getElementById('mTextPreviewBtn');
  if (!ta || !prev || !btn) return;
  if (prev.style.display === 'none') {
    prev.innerHTML = renderSimpleMarkdown(ta.value);
    prev.style.display = 'block';
    ta.style.display = 'none';
    btn.innerText = '✏️ Редактор';
    btn.classList.add('btn--primary');
  } else {
    prev.style.display = 'none';
    ta.style.display = 'block';
    btn.innerText = '👁️ Превью';
    btn.classList.remove('btn--primary');
  }
}

async function writePastedText() {
  if (!CURRENT_TOPIC_ID) return;
  const ta = document.getElementById('mPasteText');
  const text = ((ta && ta.value) || '').trim();
  if (text.length < 200) { showToast('Вставьте текст статьи (≥ 200 знаков)'); return; }
  const { voice, model, name } = selectedVoicePayload();
  showModalLoading(`Адаптация текста: ${name}`, `Модель ${model} сохраняет факты и адаптирует структуру под стандарты...`);
  try {
    const res = await apiPost('/api/agent/run', {
      agent: '02-writer', target_id: CURRENT_TOPIC_ID, voice, model, mode: 'paste', pasted_text: text, preview_only: true
    });
    if (res && res.status === 'succeeded' && res.adapted_markdown) {
      CURRENT_ORIGINAL_TEXT = document.getElementById('mTextEditor').value;
      document.getElementById('mTextEditor').value = res.adapted_markdown;
      updateLiveCharCounter();
      switchModalTab('text');
      const notice = document.getElementById('mAdaptationNotice');
      if (notice) {
        document.getElementById('mAdaptationChars').innerText = (res.chars || res.adapted_markdown.length).toLocaleString('ru-RU');
        notice.style.display = 'block';
      }
      showToast('✓ Текст адаптирован! Проверьте результат и нажмите «Принять в БД»');
      togglePasteBox();
    } else {
      showToast('⚠️ Ошибка адаптации: ' + ((res && res.log) || 'неизвестная ошибка'));
    }
  } catch (e) {
    showToast('⚠️ Сбой соединения при адаптации текста');
  } finally {
    hideModalLoading();
  }
}

function cancelAdaptation() {
  if (CURRENT_ORIGINAL_TEXT !== undefined) {
    document.getElementById('mTextEditor').value = CURRENT_ORIGINAL_TEXT;
    updateLiveCharCounter();
  }
  const notice = document.getElementById('mAdaptationNotice');
  if (notice) notice.style.display = 'none';
  showToast('Адаптация отменена, исходный текст восстановлен');
}

async function acceptAdaptation() {
  await saveEditedArticleText();
  const notice = document.getElementById('mAdaptationNotice');
  if (notice) notice.style.display = 'none';
  showToast('✓ Адаптированная версия принята и сохранена в SQLite!');
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
      confirmRewriteCurrentTopic('practico');
    } else {
      writeArticleForCurrentTopic('practico');
    }
  } else if (agentName === '02-writer-2') {
    const t = DB_DATA.topics.find(x => x.id === CURRENT_TOPIC_ID);
    if (t && (t.chars || 0) >= 200) {
      confirmRewriteCurrentTopic('practico2');
    } else {
      writeArticleForCurrentTopic('practico2');
    }
  } else {
    runAgent(agentName);
  }
}

async function runAgent(agentName) {
  showToast(`Запуск агента ${agentName}...`);
  const c = document.getElementById('agentConsole');
  c.innerText = `[${new Date().toLocaleTimeString()}] Запуск ${agentName}...
Ожидание ответа...`;
  const res = await apiPost('/api/agent/run', { agent: agentName });

  // Долгие агенты работают в фоне: сервер вернёт job_id
  if (res && res.job_id) {
    c.innerText = `[${new Date().toLocaleTimeString()}] ${res.log}

Идёт работа... (это может занять несколько минут)`;
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
      const r = await fetch(`${API_BASE}/api/jobs/${jobId}`);
      if (r.ok) job = await r.json();
    } catch (e) { /* сервер занят — пробуем снова */ }
    if (!job || job.status === 'running') {
      c.innerText = `[${new Date().toLocaleTimeString()}] Задача #${jobId}: выполняется...`;
      continue;
    }
    c.innerText = `[${new Date().toLocaleTimeString()}] Задача #${jobId}: ${job.status}

${job.log || ''}`;
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

function clearFirecrawlInput() {
  document.getElementById('fcContent').value = '';
  const resBox = document.getElementById('fcResult');
  if (resBox) resBox.style.display = 'none';
}

let CURRENT_FC_PROMPT_MODE = 'new_competitors';

function setFcPromptMode(mode) {
  CURRENT_FC_PROMPT_MODE = mode;
  const btnNew = document.getElementById('btnFcModeNew');
  const btnGaps = document.getElementById('btnFcModeGaps');
  if (btnNew) btnNew.classList.toggle('active', mode === 'new_competitors');
  if (btnGaps) btnGaps.classList.toggle('active', mode === 'existing_gaps');
  updatePromptPreviewText();
}

function generateFirecrawlPromptText() {
  const focusInput = document.getElementById('fcPromptFocus');
  const focus = (focusInput && focusInput.value || 'Пайка BGA/SMD, выбор оборудования, флюсы').trim();
  const depth = (document.getElementById('fcPromptDepth') || {}).value || 'deep';
  const format = (document.getElementById('fcPromptFormat') || {}).value || 'markdown';
  const optGaps = document.getElementById('fcOptGaps') ? document.getElementById('fcOptGaps').checked : true;
  const optStruct = document.getElementById('fcOptStructure') ? document.getElementById('fcOptStructure').checked : true;
  const optFaq = document.getElementById('fcOptFaq') ? document.getElementById('fcOptFaq').checked : true;
  const optModels = document.getElementById('fcOptModels') ? document.getElementById('fcOptModels').checked : true;

  const existingDomains = (DB_DATA.competitors || []).map(c => c.domain).filter(Boolean);
  const existingTopics = (DB_DATA.topics || []).map(t => t.title).filter(Boolean);

  let prompt = '';

  if (CURRENT_FC_PROMPT_MODE === 'new_competitors') {
    prompt += `# ЗАДАНИЕ ДЛЯ FIRECRAWL / AI-ИССЛЕДОВАНИЯ: ПОИСК НОВЫХ КОНКУРЕНТОВ\n\n`;
    prompt += `Ты — старший инженерный аналитик и SEO-разведчик проекта «Точка Плавления» (tochka-plavleniya.ru).\n`;
    prompt += `Наша цель: Найти НОВЫХ профильных конкурентов в русскоязычном интернете по теме: «${focus}».\n\n`;
    prompt += `## СТРОГИЙ ЧЁРНЫЙ СПИСОК (ЭТИ САЙТЫ УЖЕ ЕСТЬ В БАЗЕ — ИСКЛЮЧИТЬ!):\n`;
    prompt += `Не включай в отчёт следующие ${existingDomains.length} доменов:\n`;
    prompt += existingDomains.join(', ') + '\n\n';
    prompt += `## ИСКЛЮЧИТЬ СУЩЕСТВУЮЩИЕ ${existingTopics.length} ТЕМ НАШЕГО БЛОГА (БЕЗ ДУБЛИКАТОВ):\n`;
    prompt += existingTopics.slice(0, 40).map(t => `• ${t}`).join('\n') + '\n\n';
    prompt += `## ВОЗМОЖНОСТИ И ГЛУБИНА СБОРА FIRECRAWL (${depth.toUpperCase()}):\n`;
    prompt += `1. Используй Search/Map для выявления активных блогов и порталов по пайке и ремонту электроники.\n`;
    if (depth === 'deep' || depth === 'crawl') {
      prompt += `2. Проведи Scrape ключевых статей конкурента, извлекая полные метаданные:\n`;
      if (optStruct) prompt += `   - Точный H1, логику разделов H2–H3, наличие таблиц режимов и шагов.\n`;
      if (optFaq) prompt += `   - Вопросы читателей (FAQ), реальные боли новичков.\n`;
      if (optModels) prompt += `   - Упомянутые модели станций, припои (ПОС/SAC), флюсы и инструмент.\n`;
      if (optGaps) prompt += `   - Чего в статье НЕ ХВАТАЕТ (Content Gaps): где не назван ГОСТ, где завышена температура, где пропущена техника безопасности.\n`;
    }
  } else {
    prompt += `# ЗАДАНИЕ ДЛЯ FIRECRAWL / AI-ИССЛЕДОВАНИЯ: АНАЛИЗ ТЕКУЩИХ КОНКУРЕНТОВ НА НОВЫЕ ТЕМЫ (CONTENT GAPS)\n\n`;
    prompt += `Ты — ведущий контент-стратег проекта «Точка Плавления» (tochka-plavleniya.ru).\n`;
    prompt += `Наша цель: Проанализировать блоги наших существующих конкурентов по теме «${focus}» и найти НОВЫЕ ТЕМЫ и ПРОБЕЛЫ (Content Gaps), которых ЕЩЁ НЕТ в нашей базе из ${existingTopics.length} статей.\n\n`;
    prompt += `## СПИСОК КОНКУРЕНТОВ ДЛЯ ОБХОДА (FIRECRAWL MAP / CRAWL):\n`;
    prompt += existingDomains.slice(0, 25).map(d => `• https://${d}`).join('\n') + '\n\n';
    prompt += `## НАШИ УЖЕ НАПИСАННЫЕ И ЗАПЛАНИРОВАННЫЕ ТЕМЫ (НЕ ПРЕДЛАГАТЬ ДУБЛИ!):\n`;
    prompt += existingTopics.slice(0, 45).map(t => `• ${t}`).join('\n') + '\n\n';
    prompt += `## ЧТО ИЗВЛЕЧЬ И ПРЕДЛОЖИТЬ ДЛЯ «ТОЧКИ ПЛАВЛЕНИЯ»:\n`;
    prompt += `1. Найти у конкурентов темы со стабильным интересом, которые мы упустили.\n`;
    if (optGaps) prompt += `2. Выявить Content Gaps: где статьи конкурентов устарели, грешат мифами (пайка плат кислотой, конические жала-иглы) или не дают четких технологических шагов.\n`;
    if (optFaq) prompt += `3. Собрать популярные вопросы из комментариев и FAQ конкурентов.\n`;
    prompt += `4. Сформулировать новые сильные темы с рабочими H1 и обоснованием приоритета.\n\n`;
  }

  prompt += `## ТРЕБУЕМЫЙ ФОРМАТ ВЫДАЧИ ДЛЯ ИМПОРТА В ДАШБОРД ТЧП:\n`;
  if (format === 'markdown') {
    prompt += `Отдай результат строго в Markdown с таблицами следующего вида:\n\n`;
    prompt += `## Приоритетные конкуренты\n\n`;
    prompt += `| Сайт | Тип | Ниша | Почему конкурент | Уровень |\n`;
    prompt += `|---|---|---|---|---|\n`;
    prompt += `| [domain.ru](https://domain.ru/statya) | Тип сайта | Ниша | Почему важен и какие темы освещает | **Высокий** |\n\n`;
    prompt += `## Предлагаемые новые темы и пробелы\n\n`;
    prompt += `### C01. Название темы — P85\n`;
    prompt += `Предлагаемый H1: Как правильно...\n`;
    prompt += `Slug: \`novaya-tema-slug\`\n`;
    prompt += `Основной ключ: поисковый запрос\n`;
    prompt += `- lsi ключ 1\n`;
    prompt += `- lsi ключ 2\n\n`;
    prompt += `Пробел у конкурентов: в статьях нет физики смачивания и температурного режима.\n`;
  } else {
    prompt += `Отдай валидный JSON со структурой:\n`;
    prompt += `{\n  "competitors": [{ "domain": "site.ru", "type": "...", "niche": "...", "blog_url": "https://site.ru/page", "score": 85, "notes": "..." }],\n`;
    prompt += `  "raw_data": [{ "url": "https://site.ru/page", "domain": "site.ru", "title": "...", "h1": "...", "topic": "..." }],\n`;
    prompt += `  "clusters": [{ "cid": "C01", "name": "...", "h1": "...", "slug": "...", "primary_key": "...", "secondary_keys": ["..."] }]\n}\n`;
  }

  return prompt;
}

function updatePromptPreviewText() {
  const preview = document.getElementById('fcPromptPreviewText');
  if (preview) {
    preview.value = generateFirecrawlPromptText();
  }
}

function togglePromptPreview() {
  const box = document.getElementById('fcPromptPreview');
  if (!box) return;
  const isHidden = box.style.display === 'none';
  box.style.display = isHidden ? 'block' : 'none';
  if (isHidden) {
    updatePromptPreviewText();
  }
}

async function copyGeneratedFirecrawlPrompt() {
  const text = generateFirecrawlPromptText();
  try {
    await navigator.clipboard.writeText(text);
    showToast('✓ Готовый промпт для Firecrawl скопирован в буфер обмена!', 'success');
    const btn = document.getElementById('btnCopyPrompt');
    if (btn) {
      const oldText = btn.innerText;
      btn.innerText = '✓ СКОПИРОВАНО В БУФЕР!';
      btn.style.background = '#059669';
      btn.style.color = '#FFFFFF';
      setTimeout(() => {
        btn.innerText = oldText;
        btn.style.background = '';
        btn.style.color = '';
      }, 1800);
    }
  } catch (err) {
    showToast('⚠️ Не удалось скопировать промпт в буфер', 'error');
  }
}

async function copyExistingBaseJson() {
  try {
    const res = await apiGet('/api/export.json');
    if (res) {
      const text = JSON.stringify(res, null, 1);
      await navigator.clipboard.writeText(text);
      showToast('✓ JSON манифеста базы скопирован в буфер для нейросети!');
    }
  } catch (e) {
    showToast('⚠️ Не удалось скопировать JSON');
  }
}

async function importFirecrawlData() {
  const content = (document.getElementById('fcContent').value || '').trim();
  const resBox = document.getElementById('fcResult');

  if (!content) {
    showToast('Вставьте текст выгрузки из Firecrawl (JSON или Markdown)');
    return;
  }

  showToast('Сверка с базой и импорт с дедупликацией...');
  resBox.style.display = 'block';
  resBox.style.background = '#FFFBEB';
  resBox.style.borderColor = '#FCD34D';
  resBox.style.color = '#92400E';
  resBox.innerHTML = '⏳ Проверяем дубликаты по текущей базе (URL, домены, кластеры, слаги)...';

  // 1. Попытка v1 пакетного импорта с дедупликацией
  let res = await apiPost('/api/import/firecrawl/v1', { content });

  // 2. Если это одиночная статья старого формата, пробуем legacy эндпоинт
  if (!res || res.status !== 'ok') {
    res = await apiPost('/api/import/firecrawl', { content });
  }

  if (res && res.status === 'ok') {
    resBox.style.background = '#F0FDF4';
    resBox.style.borderColor = '#86EFAC';
    resBox.style.color = '#166534';
    const a = res.added || {};
    const d = res.duplicates || {};
    const col = res.slug_collisions || [];
    let collisionHtml = '';
    if (col && col.length > 0) {
      collisionHtml = `<div style="margin-top:8px;padding:6px 10px;background:#FEF2F2;border:1px solid #FCA5A5;color:#991B1B;font-size:11px;">
        ⚠️ <strong>Обнаружены совпадения со слагами существующих статей:</strong><br>${col.map(s => '<code>' + esc(s) + '</code>').join(', ')}
      </div>`;
    }
    const addedText = (res.added)
      ? `• Добавлено уникальных: <strong>конкурентов ${a.competitors || 0}</strong>, <strong>страниц ${a.raw_articles || 0}</strong>, <strong>кластеров ${a.clusters || 0}</strong>, <strong>ключей ${a.keywords || 0}</strong><br>` +
        `• Пропущено дубликатов: <strong>конкурентов ${d.competitors || 0}</strong>, <strong>страниц ${d.raw_articles || 0}</strong>, <strong>кластеров ${d.clusters || 0}</strong>, <strong>ключей ${d.keywords || 0}</strong>`
      : `• Статей сохранено: <strong>${res.saved_articles || 0}</strong> (домен: ${esc(res.domain || '—')})`;

    resBox.innerHTML = `
      <div style="font-weight:800;font-size:13px;margin-bottom:6px;">✓ ${esc(res.message || 'Успешно импортировано и сведено с базой!')}</div>
      <div style="font-size:12px;line-height:1.5;">${addedText}</div>
      ${collisionHtml}
    `;
    showToast('✓ Выгрузка Firecrawl успешно сведена с базой без дублей!');
    await refreshData();
  } else {
    resBox.style.background = '#FEF2F2';
    resBox.style.borderColor = '#FCA5A5';
    resBox.style.color = '#991B1B';
    resBox.innerHTML = `<strong>⚠️ Ошибка при импорте:</strong> ${esc((res && res.message) || 'Не удалось разобрать данные Firecrawl')}`;
    showToast('⚠️ Ошибка при импорте выгрузки Firecrawl');
  }
}

let DECISION_DETAILS_EXPANDED = false;

function toggleDecisionDetails() {
  DECISION_DETAILS_EXPANDED = !DECISION_DETAILS_EXPANDED;
  renderDecisionBanner();
}

function toggleToolsMenu(e) {
  if (e) e.stopPropagation();
  const m = document.getElementById('toolsDropdownMenu');
  if (m) m.classList.toggle('active');
}
document.addEventListener('click', () => {
  const m = document.getElementById('toolsDropdownMenu');
  if (m) m.classList.remove('active');
});

// Render Decision Alert Banner
function renderDecisionBanner() {
  const banner = document.getElementById('decisionBanner');
  if (!banner) return;
  
  // 1. Статьи требующие правок (score < 80 and chars > 500)
  const needsFix = (DB_DATA.topics || []).filter(t => (t.chars || 0) > 500 && (t.score_total === null || t.score_total < 80) && t.status !== 'ready' && t.status !== 'published');
  
  // 2. Статьи ожидающие утверждения шеф-редактором (score >= 80 and status != ready and status != published)
  const readyForApproval = (DB_DATA.topics || []).filter(t => (t.score_total || 0) >= 80 && t.status !== 'ready' && t.status !== 'published');
  
  // 3. Предложенные темы ожидающие решения
  const proposedWaiting = (DB_DATA.topics_proposed || []).filter(t => t.status === 'предложен');

  const totalActions = needsFix.length + readyForApproval.length + proposedWaiting.length;
  if (totalActions === 0) {
    banner.style.display = 'none';
    return;
  }
  banner.style.display = 'block';

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
    <div class="decision-summary-bar" onclick="toggleDecisionDetails()">
      <div style="display:flex;align-items:center;gap:8px;">
        <span style="font-size:13px;">⚡</span>
        <span>Внимание шеф-редактора: 
          ${needsFix.length > 0 ? `<strong style="color:#B91C1C;">${needsFix.length} на правки</strong> · ` : ''}
          ${readyForApproval.length > 0 ? `<strong style="color:#059669;">${readyForApproval.length} на приёмку</strong> · ` : ''}
          <strong>${proposedWaiting.length} тем на одобрение</strong>
        </span>
      </div>
      <button class="btn btn--xs" type="button" style="background:#FFF;" onclick="event.stopPropagation(); toggleDecisionDetails();">
        ${DECISION_DETAILS_EXPANDED ? '▲ Скрыть детали' : '▼ Показать задачи (' + totalActions + ')'}
      </button>
    </div>
    ${DECISION_DETAILS_EXPANDED ? `
    <div class="decision-grid">
      <div class="decision-col">
        <div class="decision-col__title" style="color:#B91C1C;">
          [ПРАВКИ АВТОРА] (${needsFix.length})
        </div>
        <ul class="decision-col__list">
          ${fixHtml || '<li style="color:var(--muted);">Нет замечаний, черновики в норме</li>'}
        </ul>
      </div>

      <div class="decision-col">
        <div class="decision-col__title" style="color:#059669;">
          [ОТК И УТВЕРЖДЕНИЕ] (${readyForApproval.length})
        </div>
        <ul class="decision-col__list">
          ${approveHtml || '<li style="color:var(--muted);">Нет статей на приёмку</li>'}
        </ul>
      </div>

      <div class="decision-col">
        <div class="decision-col__title" style="color:#D97706;">
          [ИДЕИ ТЕМ] (${proposedWaiting.length})
        </div>
        <ul class="decision-col__list">
          ${propHtml || '<li style="color:var(--muted);">Все темы обработаны</li>'}
        </ul>
        <div style="margin-top:6px;font-size:11px;">
          <a href="javascript:void(0)" onclick="switchTab('gaps')">Открыть темы (${proposedWaiting.length}) →</a>
        </div>
      </div>
    </div>
    ` : ''}
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

    let colCardsHtml = '';
    let count = 0;

    const colDiv = document.createElement('div');
    colDiv.className = 'board-column';
    colDiv.dataset.status = col.code;
    colDiv.setAttribute('tabindex', '0');
    colDiv.setAttribute('role', 'region');

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
        <article class="task-card" draggable="true" data-topic-id="${t.id}" tabindex="0" role="button" aria-label="Статья #${t.id}: ${esc(t.title)}. ${STATUS_RU[t.status] || t.status}. ${chars > 0 ? chars + ' знаков' : 'без текста'}" onclick="openInspector(${t.id})" onkeydown="handleTaskCardKeydown(event, ${t.id})">
          <div class="task-card__top">
            <label class="bulk-check" style="display:flex;align-items:center;gap:4px;cursor:pointer;">
              <input type="checkbox" value="${t.id}" onchange="toggleBulkSelection(this)" style="width:18px;height:18px;flex-shrink:0;">
            </label>
            <span class="task-card__id" style="cursor:grab;" title="Перетащите в другую колонку">⠿ #${t.id}</span>
            <div class="task-card__badges">
              ${t.is_critical ? '<span class="prio-pill" style="background:#FEE2E2;color:#B91C1C;">⚠ критично</span>' : ''}
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
    colDiv.setAttribute('aria-label', `Колонка: ${col.title}, ${count} задач`);
    board.appendChild(colDiv);
  });

  initKanbanDnD();
}

// ===== Drag & Drop карточек между колонками (нативный HTML5, без CDN) =====
let KANBAN_DRAG_ID = null;

function initKanbanDnD() {
  const board = document.getElementById('kanbanBoard');
  if (!board || board.dataset.dndReady === '1') return;
  board.dataset.dndReady = '1';

  board.addEventListener('dragstart', (e) => {
    const card = e.target && e.target.closest ? e.target.closest('.task-card') : null;
    if (!card || card.getAttribute('draggable') !== 'true') return;
    KANBAN_DRAG_ID = Number(card.dataset.topicId);
    e.dataTransfer.setData('text/plain', String(KANBAN_DRAG_ID));
    e.dataTransfer.effectAllowed = 'move';
    card.classList.add('dragging');
  });

  board.addEventListener('dragend', () => {
    board.querySelectorAll('.task-card.dragging').forEach(c => c.classList.remove('dragging'));
    board.querySelectorAll('.board-column.drag-over').forEach(c => c.classList.remove('drag-over'));
  });

  board.addEventListener('dragover', (e) => {
    const col = e.target && e.target.closest ? e.target.closest('.board-column') : null;
    if (!col) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    col.classList.add('drag-over');
  });

  board.addEventListener('dragleave', (e) => {
    const col = e.target && e.target.closest ? e.target.closest('.board-column') : null;
    if (col && e.relatedTarget && !col.contains(e.relatedTarget)) col.classList.remove('drag-over');
  });

  board.addEventListener('drop', async (e) => {
    const col = e.target && e.target.closest ? e.target.closest('.board-column') : null;
    if (!col) return;
    e.preventDefault();
    col.classList.remove('drag-over');
    const id = Number(e.dataTransfer.getData('text/plain') || KANBAN_DRAG_ID || 0);
    const newStatus = col.dataset.status;
    if (!id || !newStatus) return;
    const topic = (DB_DATA.topics || []).find(x => x.id === id);
    if (!topic) return;
    const fromCol = getTopicColumn(topic);
    if (fromCol === newStatus) return;
    const RU = { backlog: 'Бэклог', work: 'В работе', needs_fix: 'Требует правок', review: 'На ревью', ready: 'Готово к выгрузке', published: 'В WordPress' };
    const risky = ['ready', 'published'].includes(newStatus) || ['ready', 'published'].includes(fromCol);
    if (risky && !confirm('Перенести статью #' + id + ' в колонку «' + (RU[newStatus] || newStatus) + '»?')) {
      renderKanban();
      return;
    }
    const res = await apiPost('/api/articles/status', { id: id, status: newStatus });
    if (res && res.status === 'ok') {
      showToast('✓ Статус статьи #' + id + ': ' + (RU[newStatus] || newStatus), 'success', '↩ Отменить', async () => {
        const revertRes = await apiPost('/api/articles/status', { id: id, status: fromCol });
        if (revertRes && revertRes.status === 'ok') {
          showToast('✓ Возвращён статус: ' + (RU[fromCol] || fromCol), 'info');
          await refreshData();
        }
      });
      await refreshData();
    } else {
      showToast('⚠️ Не удалось изменить статус', 'error');
      renderKanban();
    }
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

// ===== KEYBOARD NAVIGATION HANDLERS =====
function handleTaskCardKeydown(event, topicId) {
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault();
    openInspector(topicId);
  } else if (event.key === 'ArrowRight') {
    event.preventDefault();
    // Move to next card in column
    const cards = document.querySelectorAll('.task-card[tabindex="0"]');
    const idx = Array.from(cards).findIndex(c => c.getAttribute('onclick')?.includes(topicId));
    if (idx >= 0 && idx < cards.length - 1) cards[idx + 1].focus();
  } else if (event.key === 'ArrowLeft') {
    event.preventDefault();
    // Move to previous card in column
    const cards = document.querySelectorAll('.task-card[tabindex="0"]');
    const idx = Array.from(cards).findIndex(c => c.getAttribute('onclick')?.includes(topicId));
    if (idx > 0) cards[idx - 1].focus();
  } else if (event.key === 'ArrowDown') {
    event.preventDefault();
    // Move to next column (handled by column focus)
    const col = event.target.closest('.board-column');
    if (col) {
      const nextCol = col.nextElementSibling;
      if (nextCol && nextCol.classList.contains('board-column')) {
        const firstCard = nextCol.querySelector('.task-card[tabindex="0"]');
        if (firstCard) firstCard.focus();
      }
    }
  } else if (event.key === 'ArrowUp') {
    event.preventDefault();
    const col = event.target.closest('.board-column');
    if (col) {
      const prevCol = col.previousElementSibling;
      if (prevCol && prevCol.classList.contains('board-column')) {
        const firstCard = prevCol.querySelector('.task-card[tabindex="0"]');
        if (firstCard) firstCard.focus();
      }
    }
  } else if (event.key === 'Escape') {
    // Close modal if open, or blur card
    if (document.getElementById('inspectorModal').classList.contains('active')) {
      closeInspector();
    } else {
      event.target.blur();
    }
  }
}

// Global keyboard shortcuts
function initGlobalKeyboardShortcuts() {
  document.addEventListener('keydown', e => {
    // Cmd/Ctrl + K = Command Palette (future)
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
      e.preventDefault();
      showToast('⌘K Command Palette — в разработке');
    }
    // Cmd/Ctrl + Enter = Save in text editor
    if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
      const activeTab = document.querySelector('.tab-view[style*="block"]')?.id;
      if (activeTab === 'view-text' || activeTab === 'modalPanelText') {
        e.preventDefault();
        saveEditedArticleText();
      }
    }
    // Escape = Close modal
    if (e.key === 'Escape') {
      if (document.getElementById('inspectorModal').classList.contains('active')) {
        closeInspector();
      } else if (document.getElementById('mPasteBox')?.style.display !== 'none') {
        togglePasteBox();
      }
    }
    // Alt + 1..7 = Switch tabs
    if (e.altKey && e.key >= '1' && e.key <= '7') {
      e.preventDefault();
      const tabs = ['kanban', 'export', 'gaps', 'competitors', 'analysis', 'agents', 'backups'];
      const idx = parseInt(e.key, 10) - 1;
      if (tabs[idx]) switchTab(tabs[idx]);
    }
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

  // Редактор текста статьи (с проверкой локального черновика)
  const statusEl = document.getElementById('mAutosaveStatus');
  const draftKey = `tchp_draft_${t.id}`;
  let restored = false;
  try {
    const rawDraft = localStorage.getItem(draftKey);
    if (rawDraft) {
      const draft = JSON.parse(rawDraft);
      if (draft && draft.text && draft.text !== (t.content_markdown || '')) {
        document.getElementById('mTextEditor').value = draft.text;
        if (statusEl) statusEl.innerHTML = `<span style="color:var(--warning);">[Восстановлен черновик от ${draft.time || ''}]</span> <a href="javascript:void(0)" onclick="discardDraft(${t.id})" style="color:var(--muted);text-decoration:underline;">Сбросить</a>`;
        restored = true;
      }
    }
  } catch (e) {}

  if (!restored) {
    document.getElementById('mTextEditor').value = t.content_markdown || '';
    if (statusEl) statusEl.innerText = '';
  }

  const prevBox = document.getElementById('mTextPreview');
  if (prevBox) prevBox.style.display = 'none';
  document.getElementById('mTextEditor').style.display = 'block';
  const prevBtn = document.getElementById('mTextPreviewBtn');
  if (prevBtn) { prevBtn.innerText = '[ПРЕВЬЮ]'; prevBtn.classList.remove('btn--primary'); }
  const notice = document.getElementById('mAdaptationNotice');
  if (notice) notice.style.display = 'none';
  updateLiveCharCounter();

  const critEl = document.getElementById('mCriticalCheck');
  if (critEl) critEl.checked = !!t.is_critical;
  updateChecksStatus(t);
  loadVoices();
  document.getElementById('mDiskFilePath').innerText = `articles/${t.id}-${t.slug}.md`;
  switchModalTab('score');

  // Главные действия (Primary Actions)
  const mProv = document.getElementById('modalProviderSelect');
  if (mProv) mProv.value = CURRENT_AI_PROVIDER || 'auto';
  const btnRow = document.getElementById('modalActionButtons');
  const toolRow = document.getElementById('modalSecondaryToolbar');
  btnRow.innerHTML = '';
  if (toolRow) toolRow.innerHTML = '';

  let leftBtns = '';
  if (hasText) {
    if (t.score_total !== null && t.score_total >= 80 && t.status !== 'ready') {
      leftBtns += `<button class="btn btn--success" onclick="approveArticle(${t.id})">✓ Утвердить к выгрузке (ОТК)</button>`;
      leftBtns += `<button class="btn btn--danger" onclick="rejectArticle(${t.id})">↺ На доработку</button>`;
    } else if (t.status === 'ready') {
      leftBtns += `<button class="btn btn--primary" onclick="exportSingleArticle(${t.id})">🚀 Отправить черновик в WordPress</button>`;
      leftBtns += `<button class="btn btn--danger" onclick="rejectArticle(${t.id})">↺ Вернуть в черновики</button>`;
    } else {
      leftBtns += `<button class="btn btn--primary" onclick="switchModalTab('text')">✏️ Править текст статьи</button>`;
      leftBtns += `<button class="btn" onclick="scoreCurrentArticle()">⚡ Оценить (0-100)</button>`;
      leftBtns += `<button class="btn btn--danger" onclick="rejectArticle(${t.id})">↺ На доработку</button>`;
    }
  } else {
    leftBtns += `<button class="btn btn--primary" onclick="writeArticleForCurrentTopic('practico')">✍️ Написать (Practico 1)</button>`;
    leftBtns += `<button class="btn btn--primary" style="background:#111;color:#FFC700;" onclick="writeArticleForCurrentTopic('practico2')">✍️ Написать (Practico 2)</button>`;
    leftBtns += `<button class="btn" onclick="switchModalTab('brief')">📋 Напишу сама (ТЗ)</button>`;
  }

  btnRow.innerHTML = `<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">${leftBtns}</div>` +
                     `<button class="btn" onclick="closeInspector()">✕ Закрыть</button>`;

  // Вспомогательная панель (Инструменты качества и редактора)
  if (toolRow) {
    toolRow.style.display = 'flex';
    let toolsHtml = '<strong style="color:var(--ink);font-size:11px;">Экспертиза:</strong>';
    if (hasText) {
      toolsHtml += `<button class="btn btn--xs" onclick="runReviewerForCurrentTopic()">🧐 Рецензия (ОТК)</button>`;
      toolsHtml += `<button class="btn btn--xs" onclick="runFactcheckForCurrentTopic()">🔬 Фактчек</button>`;
    }
    toolsHtml += `<button class="btn btn--xs" onclick="checkTopicPhrase(${t.id})">🔬 Запрос темы</button>`;
    toolsHtml += '<span style="color:#CBD5E1;margin:0 4px;">|</span>';
    toolsHtml += '<strong style="color:var(--ink);font-size:11px;">Контент:</strong>';
    toolsHtml += `<button class="btn btn--xs" onclick="switchModalTab('brief')">📋 ТЗ темы</button>`;
    toolsHtml += `<button class="btn btn--xs" onclick="togglePasteBox()">📋 Вставить готовый текст</button>`;
    if (hasText) {
      toolsHtml += `<button class="btn btn--xs btn--warning" onclick="confirmRewriteCurrentTopic('practico')">↻ Переписать ИИ</button>`;
    }
    toolRow.innerHTML = toolsHtml;
  }

  lastFocusedElementBeforeModal = document.activeElement;
  const modalEl = document.getElementById('inspectorModal');
  modalEl.classList.add('active');
  setTimeout(() => {
    const closeBtn = modalEl.querySelector('.modal-close');
    if (closeBtn) closeBtn.focus();
  }, 40);
}

function switchModalTab(tab) {
  document.getElementById('modalTabBtnScore').classList.toggle('active', tab === 'score');
  document.getElementById('modalTabBtnText').classList.toggle('active', tab === 'text');
  document.getElementById('modalTabBtnBrief').classList.toggle('active', tab === 'brief');
  const btnPass = document.getElementById('modalTabBtnPassport');
  if (btnPass) btnPass.classList.toggle('active', tab === 'passport');

  document.getElementById('modalPanelScore').style.display = tab === 'score' ? 'block' : 'none';
  document.getElementById('modalPanelText').style.display = tab === 'text' ? 'block' : 'none';
  document.getElementById('modalPanelBrief').style.display = tab === 'brief' ? 'block' : 'none';
  const panPass = document.getElementById('modalPanelPassport');
  if (panPass) panPass.style.display = tab === 'passport' ? 'block' : 'none';

  if (tab === 'brief') {
    loadModalBrief();
  } else if (tab === 'passport') {
    loadModalPassport();
  }
}

let CURRENT_PASSPORT_CATALOG = [];

function setStandardQuick(std) {
  const el = document.getElementById('mPassportStandard');
  if (el) el.value = std;
}

function onAlloySelectChange(alloyId) {
  applyAlloyPreset();
}

function applyAlloyPreset() {
  const alloyId = document.getElementById('mPassportAlloy').value;
  const item = CURRENT_PASSPORT_CATALOG.find(x => x.id === alloyId);
  if (!item) return;

  if (item.work_range) {
    document.getElementById('mPassportTempRange').value = item.work_range;
  }
  if (item.standard && !document.getElementById('mPassportStandard').value.trim()) {
    document.getElementById('mPassportStandard').value = item.standard;
  }
  showToast(`Нормы применены: ${item.name}`);
}

async function loadModalPassport() {
  if (!CURRENT_TOPIC_ID) return;
  const resBox = document.getElementById('mPassportValidationResult');
  if (resBox) resBox.style.display = 'none';

  try {
    const res = await apiGet('/api/topics/passport?id=' + CURRENT_TOPIC_ID);
    if (!res || res.status !== 'ok') {
      showToast('⚠️ Не удалось загрузить паспорт статьи');
      return;
    }
    const p = res.passport || {};
    const val = res.validation || {};
    CURRENT_PASSPORT_CATALOG = res.alloys_catalog || [];

    // 1. Сплав
    const alloySel = document.getElementById('mPassportAlloy');
    if (alloySel && p.solder_alloy) {
      alloySel.value = p.solder_alloy;
    }

    // 2. Температуры
    document.getElementById('mPassportTempKind').value = p.temp_kind || 'station_setpoint';
    document.getElementById('mPassportTempRange').value = p.temp_range || '';
    document.getElementById('mPassportTempPeak').value = p.temp_peak || '';

    // 3. Сложность
    document.getElementById('mPassportDifficulty').value = p.difficulty || 'Практик';

    // 4. Стандарт
    document.getElementById('mPassportStandard').value = p.standard || '';

    // 5. Инструменты
    const toolNames = new Set((p.tools || []).map(t => (t.name || t).toLowerCase()));
    document.querySelectorAll('.passport-tool-cb').forEach(cb => {
      cb.checked = toolNames.has(cb.value.toLowerCase());
    });

    // 6. Сводка
    document.getElementById('mPassportFaqStatus').innerHTML = `FAQ в тексте: <strong>${p.faq_count}</strong> (мин. 4)`;
    document.getElementById('mPassportSourcesStatus').innerHTML = `Источники в тексте: <strong>${p.sources_count}</strong> (мин. 2)`;
    document.getElementById('mPassportCustomStatus').innerHTML = p.is_customized ? `<span style="color:#059669;font-weight:700;">сохранён вручную</span>` : `<span>расчётный из текста</span>`;

    // 7. Валидация
    renderPassportValidation(val);
  } catch (e) {
    showToast('Ошибка загрузки паспорта статьи');
  }
}

function renderPassportValidation(val) {
  const resBox = document.getElementById('mPassportValidationResult');
  if (!resBox) return;
  resBox.style.display = 'block';

  if (val.valid) {
    resBox.style.background = '#F0FDF4';
    resBox.style.borderColor = '#86EFAC';
    resBox.style.color = '#166534';
    resBox.innerHTML = `
      <strong>✓ ПАКЕТ СТАТЬИ 100% ВАЛИДЕН ПО СХЕМЕ v1.1</strong><br>
      <div style="font-size:11.5px;margin-top:4px;">Все обязательные поля, канонический сплав, температурный коридор, FAQ (≥4) и источники (≥2) подтверждены. Статья готова к выгрузке в WordPress.</div>
    `;
  } else {
    resBox.style.background = '#FFFBEB';
    resBox.style.borderColor = '#FCD34D';
    resBox.style.color = '#92400E';
    const errs = (val.errors || []).concat(val.missing_warnings || []);
    resBox.innerHTML = `
      <strong>⚠️ Требуется внимание для схемы v1.1:</strong>
      <ul style="margin:6px 0 0;padding-left:18px;font-size:11.5px;line-height:1.4;">
        ${errs.map(e => `<li>${esc(e)}</li>`).join('')}
      </ul>
    `;
  }
}

async function savePassport() {
  if (!CURRENT_TOPIC_ID) return;

  const solder_alloy = document.getElementById('mPassportAlloy').value;
  const temp_kind = document.getElementById('mPassportTempKind').value;
  const temp_range = document.getElementById('mPassportTempRange').value.trim();
  const temp_peak = document.getElementById('mPassportTempPeak').value.trim();
  const difficulty = document.getElementById('mPassportDifficulty').value;
  const standard = document.getElementById('mPassportStandard').value.trim();

  const selectedTools = [];
  document.querySelectorAll('.passport-tool-cb:checked').forEach(cb => {
    selectedTools.push({ name: cb.value, model: '', purpose: 'Пайка' });
  });

  showToast('Сохранение паспорта и валидация v1.1...');
  const res = await apiPost('/api/topics/passport', {
    id: CURRENT_TOPIC_ID,
    solder_alloy,
    temp_kind,
    temp_range,
    temp_peak,
    difficulty,
    standard,
    tools: selectedTools,
  });

  if (res && res.status === 'ok') {
    showToast('✓ Паспорт статьи сохранён!');
    document.getElementById('mPassportCustomStatus').innerHTML = `<span style="color:#059669;font-weight:700;">сохранён вручную</span>`;
    renderPassportValidation({
      valid: res.valid,
      errors: res.errors || [],
      missing_warnings: res.missing_warnings || []
    });
    await refreshData();
  } else {
    showToast('⚠️ Ошибка при сохранении паспорта');
  }
}

async function loadModalBrief() {
  if (!CURRENT_TOPIC_ID) return;
  const el = document.getElementById('mBriefContent');
  if (!el) return;
  el.innerText = '⏳ Загрузка брифа и SEO-метрик...';
  try {
    const res = await apiGet('/api/brief?id=' + CURRENT_TOPIC_ID);
    if (res && res.status === 'ok' && res.brief) {
      el.innerText = res.brief.markdown;
    } else {
      el.innerText = 'Не удалось загрузить бриф для этой темы.';
    }
  } catch (e) {
    el.innerText = 'Ошибка соединения при загрузке брифа.';
  }
}

function copyBriefToClipboard() {
  const el = document.getElementById('mBriefContent');
  if (!el) return;
  navigator.clipboard.writeText(el.innerText)
    .then(() => showToast('✓ Бриф скопирован в буфер обмена!'))
    .catch(() => showToast('Выделите и скопируйте текст вручную'));
}

async function applySelectedTemplate() {
  const sel = document.getElementById('mTemplateSelect');
  const tmplId = sel ? sel.value : '';
  if (!tmplId) {
    showToast('Выберите тип инженерного шаблона');
    return;
  }
  const editor = document.getElementById('mTextEditor');
  if (!editor) return;

  if (editor.value.trim().length > 200) {
    if (!confirm('В редакторе уже есть текст статьи. Заменить его выбранным инженерным шаблоном?')) {
      return;
    }
  }

  showToast('Загрузка шаблона...');
  try {
    const res = await apiGet('/api/templates?id=' + tmplId);
    if (res && res.status === 'ok' && res.content) {
      editor.value = res.content;
      updateLiveCharCounter();
      showToast('✓ Шаблон успешно применён в редактор!');
    } else {
      showToast('⚠️ Не удалось загрузить шаблон');
    }
  } catch (e) {
    showToast('Ошибка при загрузке шаблона');
  }
}

async function saveEditedArticleText() {
  if (!CURRENT_TOPIC_ID) return;
  const newText = document.getElementById('mTextEditor').value;
  showToast('Сохранение текста статьи...');
  const res = await apiPost('/api/topics/update-text', { id: CURRENT_TOPIC_ID, content_markdown: newText });
  if (res && res.status === 'ok') {
    try { localStorage.removeItem(`tchp_draft_${CURRENT_TOPIC_ID}`); } catch (e) {}
    const statusEl = document.getElementById('mAutosaveStatus');
    if (statusEl) statusEl.innerText = '[Сохранено в БД]';
    showToast(`✓ Текст сохранён в SQLite и articles/ (${res.chars} зн.)`);
    document.getElementById('mChars').innerText = res.chars.toLocaleString('ru-RU') + ' зн.';
    document.getElementById('mTextCharsBadge').innerText = res.chars + ' зн.';
    await refreshData();
  } else {
    showToast('Ошибка при сохранении текста.', 'error');
  }
}

function closeInspector(e) {
  if (e && e.target !== e.currentTarget && !e.target.classList.contains('modal-close')) return;
  const modalEl = document.getElementById('inspectorModal');
  if (modalEl) modalEl.classList.remove('active');
  if (lastFocusedElementBeforeModal && typeof lastFocusedElementBeforeModal.focus === 'function') {
    try { lastFocusedElementBeforeModal.focus(); } catch (err) {}
  }
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
    showToast('Пожалуйста, введите название темы!', 'warning');
    return;
  }

  showToast('Добавление темы в бэклог...');
  const res = await apiPost('/api/topics/create', { title, cluster, type, priority, notes });
  if (res && res.status === 'ok') {
    showToast(`✓ Тема #${res.id} добавлена в бэклог!`, 'success');
    document.getElementById('newTopicTitle').value = '';
    document.getElementById('newTopicNotes').value = '';
    document.getElementById('newTopicBox').style.display = 'none';
    await refreshData();
  } else {
    showToast('Ошибка при добавлении темы.', 'error');
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

  // Счётчики карточки экспорта базы
  const elTopics = document.getElementById('expStatTopics');
  if (elTopics) elTopics.innerText = (DB_DATA.topics || []).length;
  const elWritten = document.getElementById('expStatWritten');
  if (elWritten) elWritten.innerText = writtenCnt;
  const elClusters = document.getElementById('expStatClusters');
  if (elClusters) elClusters.innerText = (DB_DATA.clusters || []).length;
  const elComps = document.getElementById('expStatCompetitors');
  if (elComps) elComps.innerText = (DB_DATA.competitors || []).length;
  const elUrls = document.getElementById('expStatUrls');
  if (elUrls) elUrls.innerText = (DB_DATA.raw_articles || []).length;

  renderDecisionBanner();
  renderExportTab();
  renderKanban();
  renderProposedTopics();
  renderCompetitors();
  renderBackups();
  renderAnalysisHistory();
  refreshMutagenBalance();
  initGlobalKeyboardShortcuts();
}

// ==========================================
// СПЛИТ-ВЕРСТАК & ВСТАВКИ PRACTICO
// ==========================================
let STANDARDS_CACHE = null;

async function loadStandardsCatalog() {
  if (STANDARDS_CACHE) return STANDARDS_CACHE;
  try {
    const res = await fetch(API_BASE + '/api/standards');
    if (res.ok) {
      STANDARDS_CACHE = await res.json();
    }
  } catch (e) {}
  return STANDARDS_CACHE;
}

function toggleSplitWorkbench() {
  const modal = document.querySelector('#inspectorModal .modal-content');
  const sidebar = document.getElementById('mWorkbenchSidebar');
  const btn = document.getElementById('mBtnToggleSplit');
  if (!modal || !sidebar) return;

  const isExpanded = modal.classList.toggle('workbench-expanded');
  sidebar.style.display = isExpanded ? 'block' : 'none';
  if (btn) btn.innerText = isExpanded ? '🗗 Обычный вид' : '🗖 Сплит-Верстак';

  if (isExpanded) {
    updateWorkbenchLiveAssistant();
    if (CURRENT_TOPIC_ID) {
      loadWorkbenchLinks(CURRENT_TOPIC_ID);
    }
  }
}

function switchWbTab(tab) {
  const btnGost = document.getElementById('wbTabGost');
  const btnSerp = document.getElementById('wbTabSerp');
  const panelGost = document.getElementById('wbPanelGost');
  const panelSerp = document.getElementById('wbPanelSerp');

  if (tab === 'gost') {
    btnGost.classList.add('active');
    btnSerp.classList.remove('active');
    panelGost.style.display = 'block';
    panelSerp.style.display = 'none';
  } else {
    btnGost.classList.remove('active');
    btnSerp.classList.add('active');
    panelGost.style.display = 'none';
    panelSerp.style.display = 'block';
  }
}

function insertPracticoBlock(type) {
  const editor = document.getElementById('mTextEditor');
  if (!editor) return;
  let snippet = '';
  switch (type) {
    case 'master_secret':
      snippet = '\n\n> 💡 **Секрет мастера (Practico):** Сухое жало передает тепло медленно. Крошечная капля припоя на кончике жала образует «тепловой мостик», увеличивая площадь контакта.\n\n';
      break;
    case 'temp_table':
      snippet = '\n\n## Температурные режимы пайки\n\n| Сплав / Операция | Ликвидус (°C) | Уставка жала (°C) | Макс. время контакта |\n|---|---|---|---|\n| ПОС-61 (Sn61Pb39) | 190 | 240 – 320 | 2–3 сек |\n| Sn63Pb37 | 183 | 250 – 285 | 2–3 сек |\n| SAC305 (Sn96.5Ag3Cu0.5) | 220 | 260 – 340 | 2–3 сек |\n| Сплав Розе (демонтаж) | 96 | 120 – 150 | 3–5 сек |\n\n';
      break;
    case 'faq_block':
      snippet = '\n\n## FAQ: Часто задаваемые вопросы\n\n1. **Вопрос?**\nОтвет.\n\n2. **Вопрос?**\nОтвет.\n\n3. **Вопрос?**\nОтвет.\n\n4. **Вопрос?**\nОтвет.\n\n';
      break;
    case 'passport_card':
      snippet = '\n\n## Паспорт технологического процесса\n\n* **Припой:** [Укажите марку припоя, напр. ПОС-61 (ГОСТ 21931-76) или SAC305]\n* **Флюс:** [Укажите класс флюса по IPC J-STD-004B, напр. ROL0 нейтральный]\n* **Температура жала:** [Укажите рабочий диапазон уставки жала, напр. 270–300 °C]\n* **Инструмент:** [Укажите паяльную станцию/паяльник и картридж/жало]\n\n';
      break;
    case 'alloy_compare':
      snippet = '\n\n## Сравнительная таблица ключевых сплавов пайки\n\n| Сплав | Стандарт | Солидус | Ликвидус | Интервал | Тип кристаллизации | Уставка жала | Типовое применение |\n|---|---|---|---|---|---|---|---|\n| **ПОС-61** | ГОСТ 21930/31-76 | 183 °C | 190 °C | 7 °C | Не эвтектика (кашеобразная фаза) | 240 – 320 °C | Монтаж и ремонт РЭА, THT/SMD |\n| **Sn63Pb37** | IPC J-STD-006C | 183 °C | 183 °C | 0 °C | Истинная эвтектика | 250 – 285 °C | Прецизионный монтаж, QFP, 0402 |\n| **SAC305** | IPC J-STD-006C | 217 °C | 220 °C | 3 °C | Бессвинцовый RoHS | 260 – 340 °C | Промышленная бессвинцовая пайка |\n| **Sn42Bi58** | СТП / ASTM | 138 °C | 138 °C | 0 °C | Низкотемпературная эвтектика | 160 – 200 °C | Термочувствительные LED, разъёмы |\n| **Сплав Розе** | ТУ 6-09-4064-87 | 94 °C | 96 °C | 2 °C | Демонтажный сплав | 120 – 150 °C | Безопасный демонтаж микросхем |\n\n';
      break;
    case 'safety_lead':
      snippet = '\n\n## ⚠️ Безопасность и охрана труда (ГОСТ 12.3.040-86)\n\n* **Вентиляция:** пары флюса содержат летучие органические соединения и абиетиновую кислоту. Работайте исключительно под местным дымоуловителем или приточно-вытяжной вентиляцией с фильтром EPA/Carbon.\n* **Токсичность свинца:** свинец образует аэрозоль и микрочастицы на коже. Категорически запрещено принимать пищу и пить в зоне пайки. Обязательно мойте руки теплой водой с мылом.\n* **Защита от ожогов и брызг:** капли вскипающего флюса разлетаются на расстояние до 40–50 см при резком погружении жала. Защитные очки — базовое требование.\n\n';
      break;
    case 'fact_source':
      snippet = '\n\n> 🔍 **Источник факта и норматив:** Подтверждено ГОСТ 21930-76 (п. 2.1) и IPC J-STD-006C / IPC-A-610G (раздел 5.1). Точный температурный коридор солидус/ликвидус сверен с каноническим реестром материалов.\n\n';
      break;
    case 'sources_block':
      snippet = '\n\n## Нормативная база и стандарты\n\n* **ГОСТ 21930-76** — Припои оловянно-свинцовые в чушках. Технические условия.\n* **ГОСТ 21931-76** — Припои оловянно-свинцовые в виде проволоки и трубок с флюсом.\n* **IPC-A-610G** — Критерии качества электронных сборок (Acceptability of Electronic Assemblies).\n* **IPC J-STD-004B** — Требования к паяльным флюсам (Requirements for Soldering Fluxes).\n\n';
      break;
  }

  const start = editor.selectionStart !== undefined ? editor.selectionStart : editor.value.length;
  const end = editor.selectionEnd !== undefined ? editor.selectionEnd : start;

  if (typeof editor.setRangeText === 'function') {
    editor.setRangeText(snippet, start, end, 'end');
  } else {
    const before = editor.value.substring(0, start);
    const after = editor.value.substring(end);
    editor.value = before + snippet + after;
    editor.selectionStart = editor.selectionEnd = start + snippet.length;
  }
  editor.focus();
  handleEditorInput();
  showToast('✓ Блок вставлен в редактор');
}

async function updateWorkbenchLiveAssistant() {
  const text = (document.getElementById('mTextEditor') || {}).value || '';
  const textLow = text.toLowerCase();

  // 1. SERP превью
  const t = (DB_DATA.topics || []).find(x => x.id === CURRENT_TOPIC_ID) || {};
  const serpUrl = document.getElementById('wbSerpUrl');
  const serpTitle = document.getElementById('wbSerpTitle');
  const serpSnippet = document.getElementById('wbSerpSnippet');

  if (serpUrl) serpUrl.innerText = `https://tochka-plavleniya.ru/${t.slug || 'article'}/`;
  if (serpTitle) serpTitle.innerText = `${t.title || 'Статья'} — Точка Плавления`;
  if (serpSnippet) {
    const lines = text.split('\n').map(l => l.trim()).filter(l => l && !l.startsWith('#') && !l.startsWith('---'));
    serpSnippet.innerText = lines[0] ? lines[0].substring(0, 160) + '...' : 'Инженерный ответ на интент мастера появится здесь...';
  }

  // 2. Справка по упомянутым материалам (LSI)
  const std = await loadStandardsCatalog();
  const badgesBox = document.getElementById('wbGostBadges');
  const quickBox = document.getElementById('wbQuickAlloys');

  if (std && std.alloys) {
    if (badgesBox) {
      let matched = [];
      for (const [key, al] of Object.entries(std.alloys)) {
        if (textLow.includes(key) || textLow.includes(al.name.toLowerCase()) || (key === 'pos61' && textLow.includes('пос-61'))) {
          matched.push(al);
        }
      }
      if (matched.length > 0) {
        badgesBox.innerHTML = matched.map(m => `
          <div style="padding:6px 8px;background:#F8FAFC;border:1px solid #CBD5E1;margin-bottom:6px;">
            <div style="font-weight:700;color:#1E293B;">📋 ${m.name} · Норматив: ${m.std}</div>
            <div style="font-size:11px;color:#475569;margin-top:2px;">
              Ликвидус: <strong>${m.t_liquidus} °C</strong> | Канонический коридор уставки: <strong>${m.t_iron_min ? (m.t_iron_min + '–' + m.t_iron_max + ' °C') : 'по регламенту'}</strong>
            </div>
            <div style="font-size:10.5px;color:#64748B;margin-top:2px;font-style:italic;">
              ℹ️ Найдено по ключевым словам (соответствие текста проверяется перед упаковкой).
            </div>
            ${(m.warnings || []).map(w => `<div style="font-size:10.5px;color:#B91C1C;margin-top:2px;">⚠️ ${w}</div>`).join('')}
          </div>
        `).join('');
      } else {
        badgesBox.innerHTML = `
          <div style="padding:6px 8px;background:#F9FAFB;border:1px solid var(--border-hairline);color:var(--muted);font-size:11px;">
            Сплав пока не упомянут. Напишите «ПОС-61», «SAC305», «Sn63Pb37» или кликните ниже.
          </div>`;
      }
    }

    if (quickBox && quickBox.children.length === 0) {
      quickBox.innerHTML = Object.entries(std.alloys).slice(0, 5).map(([k, al]) => `
        <button class="btn btn--xs" type="button" style="text-align:left;justify-content:flex-start;" onclick="insertAlloyMention('${al.name}', '${al.t_iron_min ? (al.t_iron_min + '–' + al.t_iron_max + ' °C') : 'по регламенту'}')">
          ➕ ${al.name} (ликвидус ${al.t_liquidus} °C)
        </button>
      `).join('');
    }
  }
}

function insertAlloyMention(name, tempRange) {
  const editor = document.getElementById('mTextEditor');
  if (!editor) return;
  const str = ` припоем ${name} при рабочей уставке жала ${tempRange} `;
  const start = editor.selectionStart !== undefined ? editor.selectionStart : editor.value.length;
  const end = editor.selectionEnd !== undefined ? editor.selectionEnd : start;

  if (typeof editor.setRangeText === 'function') {
    editor.setRangeText(str, start, end, 'end');
  } else {
    editor.value = editor.value.substring(0, start) + str + editor.value.substring(end);
    editor.selectionStart = editor.selectionEnd = start + str.length;
  }
  editor.focus();
  handleEditorInput();
}

async function loadWorkbenchLinks(topicId) {
  const box = document.getElementById('wbTopicLinksList');
  if (!box) return;
  box.innerHTML = 'Загрузка связей...';
  try {
    const res = await fetch(`${API_BASE}/api/topics/links?id=${topicId}`);
    if (res.ok) {
      const data = await res.json();
      const outb = data.outbound || [];
      if (outb.length === 0) {
        box.innerHTML = '<span style="color:var(--muted);">Нет плановых связей для этого кластера</span>';
        return;
      }
      box.innerHTML = outb.map(l => `
        <button class="btn btn--xs" type="button" style="text-align:left;justify-content:flex-start;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" onclick="insertLinkToEditor('${l.title}', '/${l.slug}/')" title="Вставить ссылку в текст: [${l.anchor || l.title}](/${l.slug}/)">
          🔗 [${l.anchor || l.title}] → /${l.slug}/
        </button>
      `).join('');
    } else {
      box.innerHTML = '<span style="color:var(--muted);">Связи формируются автоматически</span>';
    }
  } catch (e) {
    box.innerHTML = '<span style="color:var(--muted);">Связи формируются автоматически</span>';
  }
}

function insertLinkToEditor(title, url) {
  const editor = document.getElementById('mTextEditor');
  if (!editor) return;
  const mdLink = ` [${title}](${url}) `;
  const pos = editor.selectionStart || editor.value.length;
  editor.value = editor.value.substring(0, pos) + mdLink + editor.value.substring(pos);
  editor.focus();
  updateLiveCharCounter();
  showToast('✓ Ссылка вставлена в текст');
}

async function refreshClusterTelemetry() {
  const node1 = document.getElementById('node1Badge');
  const node2 = document.getElementById('node2Badge');
  const node3 = document.getElementById('node3Badge');
  const omni = document.getElementById('omniRouteBadge');

  try {
    const res = await fetch(API_BASE + '/api/cluster/status').then(r => r.json());
    if (res && res.status === 'ok') {
      const t = res.triad || {};
      const o = res.omniroute || {};

      if (t.status === 'online') {
        if (node1) { node1.className = 'telemetry-node-badge online'; node1.innerHTML = '<span class="pulse-dot"></span> NODE-1 (Mi 10T) :8080 · ONLINE'; }
        if (node2) { node2.className = 'telemetry-node-badge online'; node2.innerHTML = '<span class="pulse-dot"></span> NODE-2 (Nord N10) :8081 · ONLINE'; }
        if (node3) { node3.className = 'telemetry-node-badge online'; node3.innerHTML = '<span class="pulse-dot"></span> NODE-3 (Redmi 4) :8000 · ONLINE'; }
      } else {
        if (node1) { node1.className = 'telemetry-node-badge standby'; node1.innerHTML = '<span class="pulse-dot"></span> NODE-1 (Mi 10T) · Ожидание'; }
        if (node2) { node2.className = 'telemetry-node-badge standby'; node2.innerHTML = '<span class="pulse-dot"></span> NODE-2 (Nord N10) · Ожидание'; }
        if (node3) { node3.className = 'telemetry-node-badge standby'; node3.innerHTML = '<span class="pulse-dot"></span> NODE-3 (Redmi 4) · Ожидание'; }
      }

      if (o.status === 'online') {
        if (omni) { omni.className = 'telemetry-node-badge online'; omni.innerHTML = `<span class="pulse-dot"></span> OmniRoute :20128 · ${o.models_count || 754} Моделей`; }
      } else {
        if (omni) { omni.className = 'telemetry-node-badge standby'; omni.innerHTML = `<span class="pulse-dot"></span> OmniRoute · Резерв (${o.models_count || 754})`; }
      }
      showToast('📡 Телеметрия кластера обновлена');
    }
  } catch (e) {
    console.warn('Ошибка опроса телеметрии:', e);
  }
}

async function exportColabDataset() {
  showToast('⚡ Формирование датасета для Colab LoRA...');
  const c = document.getElementById('agentConsole');
  if (c) c.innerText = `[${new Date().toLocaleTimeString()}] Экспорт обучающей выборки в JSONL ChatML...
Фильтрация статей по качеству и объему...`;
  try {
    const res = await fetch(API_BASE + '/api/dataset/export').then(r => r.json());
    if (res && res.status === 'ok') {
      const msg = `✓ Сформирован датасет: ${res.exported_count} статей (${Math.round((res.size_bytes || 0)/1024)} КБ)`;
      showToast(msg);
      if (c) c.innerText = `[${new Date().toLocaleTimeString()}] ${msg}
Файл: ${res.file_path}
Готов к передаче в Google Colab для дообучения картриджа LoRA Unsloth (r=16, alpha=32).`;
    } else {
      showToast('⚠️ Ошибка экспорта датасета');
    }
  } catch (e) {
    showToast('⚠️ Ошибка соединения с сервером');
  }
}

function updateCraftsmanModel(craftsmanId, modelValue) {
  if (craftsmanId === 'writer1') {
    const b = document.getElementById('badgeWriter1Model');
    if (b) {
      const labels = {
        triad: 'Triad 0 ₽',
        omniroute: 'OmniRoute R1',
        claude: 'Claude 4.6',
        deepseek: 'DeepSeek Chat',
        yandex: 'YandexGPT 32k'
      };
      b.innerText = labels[modelValue] || modelValue;
    }
    setGlobalProvider(modelValue);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  renderAll();
  fetch(API_BASE + '/api/status')
    .then(r => r.json())
    .then(() => {
      refreshData();
      refreshClusterTelemetry();
    })
    .catch(() => {});
});

/* ── ИНТЕРАКТИВНЫЙ ГОЛОСОВОЙ БРИГАДИР АРТЕЛИ ────────────────────────────────── */
let speechSynthesisEnabled = false;

function toggleSpeech() {
  speechSynthesisEnabled = !speechSynthesisEnabled;
  const btn = document.getElementById('btnToggleVoice');
  if (btn) {
    btn.innerHTML = speechSynthesisEnabled ? '🔊 Голос Артели: ВКЛ' : '🔇 Голос Артели: ВЫКЛ';
    btn.style.background = speechSynthesisEnabled ? '#FFC700' : '#27272A';
    btn.style.color = speechSynthesisEnabled ? '#000' : '#FFC700';
  }
  if (speechSynthesisEnabled) {
    speakText('Голос Артели активирован. Я готова отвечать!');
  } else if (window.speechSynthesis) {
    window.speechSynthesis.cancel();
  }
}

function speakText(text) {
  if (!speechSynthesisEnabled || !window.speechSynthesis) return;
  window.speechSynthesis.cancel();
  const utter = new SpeechSynthesisUtterance(text);
  utter.lang = 'ru-RU';
  utter.rate = 1.05;
  utter.pitch = 1.0;
  window.speechSynthesis.speak(utter);
}

async function askAssistant(customQuestion) {
  const input = document.getElementById('assistantInput');
  const q = customQuestion || (input ? input.value : '');
  if (!q || !q.trim()) return;

  const bubble = document.getElementById('assistantReplyBubble');
  if (bubble) bubble.innerHTML = '<span style="color:#FFC700;">🐾 Бригадир опрашивает верстаки зверей-мастеров...</span>';

  try {
    const res = await fetch(API_BASE + '/api/assistant/chat', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: q })
    });
    const data = await res.json();
    if (bubble) {
      bubble.innerText = data.reply;
    }
    if (input && !customQuestion) input.value = '';

    // Голосовое воспроизведение
    if (data.voice_text) {
      speakText(data.voice_text);
    }

    // Подсветка мастера на ферме и плавный скролл
    if (data.highlight_craftsman) {
      document.querySelectorAll('.craftsman-card').forEach(el => el.classList.remove('craftsman-highlighted'));
      const targetCard = document.getElementById(data.highlight_craftsman);
      if (targetCard) {
        targetCard.classList.add('craftsman-highlighted');
        targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => targetCard.classList.remove('craftsman-highlighted'), 7000);
      }
    }

    // Кнопка быстрого действия
    const actBox = document.getElementById('assistantActionBox');
    if (actBox) {
      if (data.action && data.action.agent) {
        actBox.innerHTML = `<button class="btn btn--sm btn--primary" type="button" style="margin-left:8px;" onclick="runAgent('${data.action.agent}', ${data.action.target_id || ''})">⚡ ${data.action.label}</button>`;
      } else {
        actBox.innerHTML = '';
      }
    }
  } catch (err) {
    if (bubble) bubble.innerText = 'Ошибка связи со Старостой Артели: ' + err.message;
  }
}

async function promptFullPipelineRun() {
  const tidStr = prompt('Введите ID темы для запуска сквозного цикла Артели (Бобёр → Кот → Барсук → Филин → Дятел):', '1');
  if (!tidStr) return;
  const tid = parseInt(tidStr, 10);
  if (!tid || tid < 1) {
    alert('Некорректный ID темы');
    return;
  }
  await runFullPipelineForTopic(tid);
}

async function runFullPipelineForTopic(tid) {
  const consoleEl = document.getElementById('agentConsole');
  if (consoleEl) {
    consoleEl.innerText = `[СКВОЗНОЙ ЦИКЛ] Запуск полного конвейера Артели для темы #${tid}...\n`;
  }

  const steps = [
    { name: '🦫 Бобёр-Конструктор (01-researcher)', agent: '01-researcher' },
    { name: '🐱 Кот-Паяльщик (02-writer)', agent: '02-writer' },
    { name: '🦡 Барсук-Иллюстратор (05-illustrator)', agent: '05-illustrator' },
    { name: '🦉 Филин-Ревизор + Humanizer (03-reviewer)', agent: '03-reviewer' },
    { name: '🪵 Дятел-Метролог (04-scorer)', agent: '04-scorer' }
  ];

  for (const step of steps) {
    if (consoleEl) {
      consoleEl.innerText += `\n>> Запуск: ${step.name}...\n`;
    }
    try {
      const res = await fetch(API_BASE + '/api/agent/run', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ agent: step.agent, target_id: tid })
      });
      const data = await res.json();
      if (consoleEl) {
        consoleEl.innerText += (data.log || data.message || 'Шаг завершен') + '\n';
      }
    } catch (e) {
      if (consoleEl) {
        consoleEl.innerText += `❌ Ошибка на шаге ${step.name}: ${e.message}\n`;
      }
      break;
    }
  }

  if (consoleEl) {
    consoleEl.innerText += `\n[ГОТОВО] Сквозной цикл Артели для темы #${tid} завершен!\n`;
  }
  refreshData();
  askAssistant(`Что с темой #${tid}?`);
}

function switchFarmView(mode) {
  const canvasContainer = document.getElementById('farmCanvasContainer');
  const gridContainer = document.getElementById('craftsmenGrid');
  const btnCanvas = document.getElementById('btnViewCanvas');
  const btnGrid = document.getElementById('btnViewGrid');

  if (mode === 'canvas') {
    if (canvasContainer) canvasContainer.style.display = 'block';
    if (btnCanvas) btnCanvas.classList.add('btn--primary');
    if (btnGrid) btnGrid.classList.remove('btn--primary');
    if (window.farmCanvas) window.farmCanvas.resize();
  } else {
    if (canvasContainer) canvasContainer.style.display = 'none';
    if (btnGrid) btnGrid.classList.add('btn--primary');
    if (btnCanvas) btnCanvas.classList.remove('btn--primary');
  }
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    closeInspector();
    return;
  }
  if (e.key === 'Tab') {
    const modalEl = document.getElementById('inspectorModal');
    if (modalEl && modalEl.classList.contains('active')) {
      const focusable = modalEl.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
      const visible = Array.from(focusable).filter(el => el.offsetParent !== null);
      if (visible.length > 0) {
        const first = visible[0];
        const last = visible[visible.length - 1];
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    }
  }
});
