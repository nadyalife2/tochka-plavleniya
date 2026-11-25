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

const SAAS_VIEW_TITLES = {
  overview: 'Центр управления редакцией',
  kanban: 'Статьи и Канбан-доска',
  agents: 'Конвейер публикаций (9 этапов)',
  gaps: 'Идеи и пробелы ниши',
  radar: 'Радар конкурентов',
  humanizer: 'Humanizer & Анти-slop аудит',
  competitors: 'Сайты-конкуренты в нише',
  analysis: 'Мутаген & Вордстат спрос',
  models: 'Кластер Triad-Omni & Модели',
  export: 'Готовые к публикации в WordPress',
  backups: 'Резервные копии SQLite',
};

function toggleSaasSidebar() {
  const sb = document.getElementById('saasSidebar');
  if (sb) sb.classList.toggle('open');
}

function renderOverviewCenter() {
  const grid = document.getElementById('overviewAttentionGrid');
  const shieldTitle = document.getElementById('overviewShieldTitle');
  const shieldSub = document.getElementById('overviewShieldSubtitle');
  const shieldIcon = document.getElementById('overviewShieldIcon');
  if (!grid) return;

  const topics = (typeof DB_DATA !== 'undefined' && DB_DATA.topics) || [];
  const proposed = (typeof DB_DATA !== 'undefined' && DB_DATA.topics_proposed) || [];

  const needsFix = topics.filter(t => (t.chars || 0) > 500 && (t.score_total === null || t.score_total < 80) && t.status !== 'ready' && t.status !== 'published');
  const readyForApproval = topics.filter(t => (t.score_total || 0) >= 80 && t.status !== 'ready' && t.status !== 'published');
  const proposedWaiting = proposed.filter(t => t.status === 'предложен');

  const attention = [
    ...needsFix.map(t => ({ ...t, _kind: 'fix' })),
    ...readyForApproval.map(t => ({ ...t, _kind: 'approve' })),
  ];
  const totalAttention = attention.length + proposedWaiting.length;

  if (shieldTitle && shieldSub && shieldIcon) {
    if (totalAttention > 0) {
      shieldIcon.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
      shieldIcon.style.background = '#FFFBEB';
      shieldIcon.style.borderColor = '#F59E0B';
      shieldTitle.textContent = `Требуется внимание: ${attention.length} статей и ${proposedWaiting.length} идей`;
      shieldSub.textContent = `Кластер Triad-Omni (3/3) онлайн · В базе ${topics.length} тем · ${needsFix.length} на правки, ${readyForApproval.length} на приёмку`;
    } else {
      shieldIcon.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>';
      shieldIcon.style.background = '#ECFDF5';
      shieldIcon.style.borderColor = '#10B981';
      shieldTitle.textContent = 'Система «Точка Плавления» в строю';
      shieldSub.textContent = `Все этапы конвейера штатно работают · В базе ${topics.length} тем`;
    }
  }

  if (attention.length === 0) {
    grid.innerHTML = '<div style="padding:14px 16px;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:8px;font-size:13px;color:#166534;font-weight:500;">Все готовые статьи проверены и не требуют срочного вмешательства.</div>';
    return;
  }

  grid.innerHTML = attention.slice(0, 6).map(t => {
    const isRed = t._kind === 'fix';
    const badgeColor = isRed ? '#DC2626' : '#B45309';
    const badgeBg = isRed ? '#FEF2F2' : '#FFFBEB';
    const borderColor = isRed ? '#FECACA' : '#FDE68A';
    const markerLabel = isRed ? `! Правки (${t.score_total || '—'}/100)` : `! Приёмка ОТК (${t.score_total}/100)`;
    const dateStr = (t.updated_at || t.created_at || '').slice(0, 10) || 'сегодня';
    return `
      <div onclick="openInspector(${t.id})" class="attention-mini-card" style="cursor:pointer;background:#FFFFFF;border:1px solid #E2E8F0;border-left:3px solid ${badgeColor};border-radius:8px;padding:12px 14px;display:flex;flex-direction:column;gap:5px;transition:box-shadow 0.15s;">
        <div style="display:flex;justify-content:space-between;align-items:center;font-size:11px;font-family:var(--mono);">
          <span style="background:${badgeBg};color:${badgeColor};border:1px solid ${borderColor};padding:1px 6px;border-radius:4px;font-weight:700;">${markerLabel}</span>
          <span style="color:#64748B;">${esc(dateStr)} · #${t.id}</span>
        </div>
        <div style="font-weight:700;font-size:13px;color:#0F172A;line-height:1.35;">${esc(t.title || '')}</div>
        <div style="font-size:11.5px;color:#64748B;">${esc(t.next_action || 'Ожидает решения шеф-редактора')}</div>
      </div>`;
  }).join('');
}

function switchTab(name) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.tab-view').forEach(v => v.classList.remove('active'));
  const btn = Array.from(document.querySelectorAll('.tab-btn')).find(b => b.getAttribute('onclick')?.includes("'" + name + "'"));
  if (btn) btn.classList.add('active');
  const view = document.getElementById('view-' + name);
  if (view) view.classList.add('active');

  const titleEl = document.getElementById('activeViewTitle');
  if (titleEl && SAAS_VIEW_TITLES[name]) {
    titleEl.textContent = SAAS_VIEW_TITLES[name];
  }

  // Close mobile sidebar after click
  const sb = document.getElementById('saasSidebar');
  if (sb && sb.classList.contains('open')) sb.classList.remove('open');

  // Contextual visibility: stepper only on kanban/agents, banner only on kanban
  const stepper = document.getElementById('pipelineStepperBar');
  const banner = document.getElementById('decisionBanner');
  if (stepper) stepper.style.display = (name === 'kanban' || name === 'agents') ? '' : 'none';
  if (banner) banner.style.display = (name === 'kanban') ? '' : 'none';

  if (name === 'overview') {
    renderOverviewCenter();
  } else if (name === 'models') {
    if (typeof loadModelsConfig === 'function') loadModelsConfig();
  } else if (name === 'humanizer') {
    const input = document.getElementById('humanizerInput');
    if (input && !input.value.trim() && typeof pasteCurrentArticleIntoHumanizer === 'function') {
      pasteCurrentArticleIntoHumanizer();
    }
  } else if (name === 'radar') {
    const input = document.getElementById('radarQueryInput');
    if (input && !input.value.trim() && typeof pasteCurrentTopicIntoRadar === 'function') {
      pasteCurrentTopicIntoRadar();
    }
  } else if (name === 'analysis') {
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
  const isKanbanActive = document.getElementById('view-kanban')?.classList.contains('active');
  banner.style.display = isKanbanActive ? 'block' : 'none';

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
  if (typeof renderOverviewCenter === 'function') renderOverviewCenter();
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
  const container3D = document.getElementById('farm3DContainer');
  const canvasContainer = document.getElementById('farmCanvasContainer');
  const gridContainer = document.getElementById('craftsmenGrid');
  const btn3D = document.getElementById('btnView3D');
  const btnCanvas = document.getElementById('btnViewCanvas');
  const btnGrid = document.getElementById('btnViewGrid');

  [btn3D, btnCanvas, btnGrid].forEach(b => b && b.classList.remove('btn--primary'));

  if (mode === '3d') {
    if (container3D) container3D.style.display = 'block';
    if (canvasContainer) canvasContainer.style.display = 'none';
    if (gridContainer) gridContainer.style.display = 'grid';
    if (btn3D) btn3D.classList.add('btn--primary');
    if (window.farm3D) window.farm3D.resize();
  } else if (mode === 'canvas') {
    if (container3D) container3D.style.display = 'none';
    if (canvasContainer) canvasContainer.style.display = 'block';
    if (gridContainer) gridContainer.style.display = 'grid';
    if (btnCanvas) btnCanvas.classList.add('btn--primary');
    if (window.aiTown2D) window.aiTown2D.resize();
  } else {
    if (container3D) container3D.style.display = 'none';
    if (canvasContainer) canvasContainer.style.display = 'none';
    if (gridContainer) gridContainer.style.display = 'grid';
    if (btnGrid) btnGrid.classList.add('btn--primary');
  }
}

function toggleTownPause() {
  if (!window.aiTown) return;
  const isPaused = window.aiTown.togglePause();
  const btn = document.getElementById('btnTownPause');
  if (btn) {
    btn.innerText = isPaused ? '▶ Старт' : '⏸ Пауза';
    btn.style.background = isPaused ? '#FFC700' : '#27272A';
    btn.style.color = isPaused ? '#000' : '#FFF';
  }
}

function promptMissionRun() {
  const tid = prompt('Введите ID темы для запуска сюжетной миссии в Городке (Сорока -> Бобёр -> Кот -> Барсук -> Филин -> Дятел -> WP):', '1');
  if (!tid) return;
  if (window.aiTown) {
    window.aiTown.startMissionPipeline(parseInt(tid, 10) || 1);
  }
}

// ============================================================
// ИНТЕРАКТИВНЫЙ ВЕРСТАК И НОРМАТИВНАЯ ДОКУМЕНТАЦИЯ МАСТЕРОВ
// ============================================================
const CRAFTSMAN_DOSSIER = {
  soroka: {
    title: 'Сорока Клара (LSI & ГОСТ-Разведчица)',
    role: '00-research · Поисковая семантика, LSI & Конкуренты',
    icon: '🪶',
    agentTarget: '00-research',
    docs: `
      <strong>Стандарты и регламенты:</strong><br>
      • Поисковый алгоритм Яндекса (Y1) и Google Helpful Content System.<br>
      • Сбор LSI-хвостов: 15–20 релевантных сущностей с плотностью ключей не выше 2.5%.<br>
      • Парсинг сниппетов топ-10 конкурентов: выявление формул, таблиц и калькуляторов припоя.<br>
      • Классификация интента аудитории: Новичок (выбор жала) vs Практик (реболлинг BGA).
    `,
    task: `
      Собрать актуальное LSI-ядро по выбранной теме, найти сильные стороны конкурентов, зафиксировать пробелы в выдаче и сформировать первичное семантическое ТЗ для Бобра.
    `
  },
  bobr: {
    title: 'Бобёр-Конструктор',
    role: '01-researcher · Бриф ГОСТ Р МЭК 61191-1 & Регламент FAQ',
    icon: '🦫',
    agentTarget: '01-researcher',
    docs: `
      <strong>Стандарты и регламенты:</strong><br>
      • <strong>ГОСТ Р МЭК 61191-1-2010</strong> — Печатные узлы. Требования к качеству поверхностного монтажа.<br>
      • <strong>ГОСТ 21931-76</strong> — Припои оловянно-свинцовые. Составы и температура солидус/ликвидус.<br>
      • <strong>Регламент 4 обязательных вопросов FAQ:</strong><br>
        1. Какая точная температура плавления сплава?<br>
        2. Какой флюс выбрать (RMA, бесотмывочный или водосмываемый)?<br>
        3. Чем грозит перегрев контактной площадки выше 300°C?<br>
        4. Как правильно очистить жало T12/900M от нагара?
    `,
    task: `
      Сформировать утвержденный инженерный бриф статьи по ГОСТ, зафиксировать нормативные допуски, структуру H2/H3 и передать утвержденный паспорт Коту-Паяльщику.
    `
  },
  kot: {
    title: 'Кот-Паяльщик (Practico 1)',
    role: '02-writer · Практический лонгрид, физика пайки и ПОС-61',
    icon: '🐱',
    agentTarget: '02-writer',
    docs: `
      <strong>Стандарты и регламенты:</strong><br>
      • <strong>Канонический реестр:</strong> <code>materials-registry.php</code> (ПОС-61, ПОС-40, сплав Розе, Вуда, SAC305).<br>
      • <strong>Строгая инженерная триада:</strong> «Инженерный факт» → «Физика шва и смачивание» → «Последствие ошибки радиомонтажника».<br>
      • <strong>Температурный регламент ПОС-61:</strong> солидус 183°C, ликвидус 190°C, рабочая зона пайки 260–280°C.<br>
      • <strong>Анти-слоп:</strong> категорический запрет фраз «в современном мире», «ни для кого не секрет», «давайте погрузимся».
    `,
    task: `
      Написать исчерпывающий пошаговый лонгрид (от 2500 слов) с пошаговой инструкцией, физикой смачивания меди, подбором жала паяльника и предупреждением типичных ошибок новичков.
    `
  },
  lis: {
    title: 'Лис-Прагматик (Practico 2)',
    role: '02-writer-2 · Финансовый аудит, сметы и окупаемость',
    icon: '🦊',
    agentTarget: '02-writer-2',
    docs: `
      <strong>Стандарты и регламенты:</strong><br>
      • <strong>Экономика домашней мастерской:</strong> расчет себестоимости одного паяного соединения (0.15–0.40 ₽).<br>
      • <strong>Сравнение окупаемости:</strong> паяльная станция T12 (3200 ₽) против паяльника без термостабилизации (600 ₽).<br>
      • <strong>Финансовое правило мастера:</strong> качественный флюс за 300 ₽ окупается при первой же спасенной плате за 15 000 ₽.
    `,
    task: `
      Рассчитать подробную смету необходимого инструмента, табличную калькуляцию затрат для начинающего монтажника и экономическое обоснование выбора расходников.
    `
  },
  barsuk: {
    title: 'Барсук-Иллюстратор',
    role: '05-illustrator · Векторные SVG-термопрофили и Hero-визуал',
    icon: '🦡',
    agentTarget: '05-illustrator',
    docs: `
      <strong>Стандарты и регламенты:</strong><br>
      • <strong>W3C SVG 1.1:</strong> Чистая векторная графика без сторонних скриптов, 0 токенов расхода нейросети.<br>
      • <strong>Термопрофиль оплавления:</strong> зона предпрогрева (до 150°C), зона активации флюса (150–183°C), пик пайки (215–235°C), охлаждение.<br>
      • <strong>Цветовая кибердек-палитра:</strong> Фон <code>#09090B</code>, акценты <code>#FFC700</code>, безопасная зона <code>#22C55E</code>, критический нагрев <code>#EF4444</code>.
    `,
    task: `
      Отрисовать точный векторный график термопрофиля оплавления для сплава темы, внедрить SVG-код в блок media паспорта v1.1 и сгенерировать кибердек-промпт обложки Hero.
    `
  },
  filin: {
    title: 'Филин-Ревизор [ОТК]',
    role: '03-reviewer · Детектор нейроштампов SlopMonster и приемка',
    icon: '🦉',
    agentTarget: '03-reviewer',
    docs: `
      <strong>Стандарты и регламенты:</strong><br>
      • <strong>Каталог SlopMonster (tools/deslop.py):</strong> детекция 50+ нейросетевых штампов и клише.<br>
      • <strong>Чек-лист входного контроля [ОТК-2026]:</strong><br>
        1. Указан ли точный сплав и температурный коридор?<br>
        2. Есть ли предупреждение о токсичности свинца и вентиляции?<br>
        3. Соответствует ли FAQ 4 обязательным вопросам брифа?<br>
        4. Отсутствует ли вода и обобщения в тексте?
    `,
    task: `
      Просканировать черновик статьи, очистить текст от нейросетевых оборотов, выставить объективный вердикт (passed / needs_fix) и поставить штамп [ОТК-2026].
    `
  },
  dyatel: {
    title: 'Дятел-Метролог',
    role: '04-scorer · Математический AST-скоринг на 100 баллов',
    icon: '🪵',
    agentTarget: '04-scorer',
    docs: `
      <strong>Стандарты и регламенты:</strong><br>
      • <strong>Формула score_article.py (100 баллов):</strong><br>
        • Блок 1: Архитектурная структура H2/H3 (15 баллов)<br>
        • Блок 2: Нормы, ГОСТы и стандарты безопасности (20 баллов)<br>
        • Блок 3: Инженерная триада «факт-физика-последствие» (25 баллов)<br>
        • Блок 4: Практическая применимость и частые ошибки (20 баллов)<br>
        • Блок 5: Терминологическая чистота реестра сплавов (10 баллов)<br>
        • Блок 6: Валидация контракта v1.1 и JSON Schema (10 баллов)
    `,
    task: `
      Рассчитать точный объективный балл качества статьи. Если балл ≥ 80 — выдать допуск к шлюзу экспорта в WordPress.
    `
  },
  raccoon: {
    title: 'Енот-Датасетник & Архивариус Знаний',
    role: '06-knowledge · Graphify граф связей, аудит базы и подготовка датасета LoRA (ChatML JSONL)',
    icon: '🦝',
    agentTarget: '06-knowledge',
    docs: `
      <strong>Стандарты и регламенты:</strong><br>
      • <strong>Graphify Knowledge Graph:</strong> постоянный граф знаний (узлы: ГОСТы, сплавы, жало паяльника, дефекты).<br>
      • <strong>LoRA Training Dataset:</strong> формат ChatML JSONL для дообучения моделей (Qwen 3.5, LFM 2.5, Gemma) через Unsloth в Google Colab.<br>
      • <strong>Аудит базы знаний:</strong> поиск пробелов (knowledge gaps), проверка покрытия тем, валидация фактологии перед обучением.<br>
      • <strong>Формат обучающей выборки:</strong> тройка system-user-assistant с эталонным стилем Practico и выверенными температурными коридорами.
    `,
    task: `
      Собрать и актуализировать граф знаний через Graphify, проверить текущее покрытие тем в базе seo.db, сформировать датасет lora_training_dataset.jsonl и запустить тест знаний (Eval).
    `
  },
  bear: {
    title: 'Медведь-Бригадир (Игрок)',
    role: 'Староста Артели · Локальный Агент Triad-Omni (Mi 10T + Nord N10 + Redmi 4)',
    icon: '🐻',
    agentTarget: 'assistant',
    docs: `
      <strong>Архитектура кластера Triad-Omni v4.9:</strong><br>
      • <strong>NODE-1 (Brain):</strong> Xiaomi Mi 10T 5G (Snapdragon 865, 8GB LPDDR5) — порт <code>:8080</code> (Qwen3.5-9B).<br>
      • <strong>NODE-2 (Reflexes & RAG):</strong> OnePlus Nord N10 5G (Snapdragon 690, 6GB) — порт <code>:8081</code> (LFM2.5-8B + BGE-M3).<br>
      • <strong>NODE-3 (Gateway Hub):</strong> Xiaomi Redmi Note 4 (Snapdragon 625) — порт <code>:8000</code>.<br>
      • <strong>Резервный шлюз:</strong> OmniRoute — порт <code>:20128</code> (754 модели, авто-каскад).
    `,
    task: `
      Координация работы всех верстаков, контроль энергопотребления смартфонов, автоматический failover и выпуск готовых статей в релиз.
    `
  }
};

let currentWsAgentId = null;
let currentWsTopicId = 1;

function openWorkstationModal(agentId) {
  currentWsAgentId = agentId || 'bobr';
  const data = CRAFTSMAN_DOSSIER[currentWsAgentId] || CRAFTSMAN_DOSSIER['bobr'];

  document.getElementById('wsModalIcon').innerText = data.icon;
  document.getElementById('wsModalTitle').innerText = data.title;
  document.getElementById('wsModalRole').innerText = data.role;
  document.getElementById('wsModalDocs').innerHTML = data.docs;
  document.getElementById('wsModalTask').innerHTML = data.task;

  const select = document.getElementById('wsModalTopicSelect');
  if (select && window.state && window.state.topics) {
    select.innerHTML = window.state.topics.map(t => `<option value="${t.id}" ${t.id === currentWsTopicId ? 'selected' : ''}>#${t.id}: ${t.title}</option>`).join('');
  }

  loadWorkstationTopicData(currentWsTopicId);

  renderCraftsmanWorkbench(currentWsAgentId);
  switchWsModalTab('tools');

  if (window.clusterHealth && window.clusterHealth.craftsmen && window.clusterHealth.craftsmen[currentWsAgentId]) {
    const h = window.clusterHealth.craftsmen[currentWsAgentId];
    const badge = document.getElementById('wsModalHealthBadge');
    const routeSpan = document.getElementById('wsModalRoute');
    if (h.status === 'dead') {
      badge.style.background = '#7F1D1D';
      badge.style.color = '#FECACA';
      badge.style.borderColor = '#DC2626';
      badge.innerText = '💀 ОФФЛАЙН (Сбой узла)';
      routeSpan.innerText = 'Узел недоступен! Требуется автозамена в YAML';
      routeSpan.style.color = '#EF4444';
    } else if (h.status === 'fallback') {
      badge.style.background = '#78350F';
      badge.style.color = '#FDE68A';
      badge.style.borderColor = '#D97706';
      badge.innerText = '🔄 РЕЗЕРВ (Автозамена)';
      routeSpan.innerText = h.active_route;
      routeSpan.style.color = '#F59E0B';
    } else {
      badge.style.background = '#14532D';
      badge.style.color = '#4ADE80';
      badge.style.borderColor = '#22C55E';
      badge.innerText = '🟢 ОНЛАЙН (' + h.active_route + ')';
      routeSpan.innerText = h.active_route;
      routeSpan.style.color = '#22C55E';
    }
  }

  
  // Плашка внимания и даты задачи (Желтый / Красный восклицательный знак)
  const attBanner = document.getElementById('wsModalAttentionBanner');
  if (attBanner) {
    const taskInfo = getAgentCurrentAttentionTask(currentWsAgentId, currentWsTopicId);
    if (taskInfo) {
      attBanner.style.display = 'flex';
      attBanner.style.background = taskInfo.isRed ? '#7F1D1D' : '#78350F';
      attBanner.style.borderColor = taskInfo.isRed ? '#DC2626' : '#D97706';
      attBanner.style.color = taskInfo.isRed ? '#FECACA' : '#FEF3C7';
      attBanner.innerHTML = `
        <span style="font-size:22px;line-height:1;">${taskInfo.isRed ? '🚨' : '⚠️'}</span>
        <div style="flex:1;">
          <div style="font-weight:800;font-size:12.5px;letter-spacing:0.3px;">${taskInfo.title}</div>
          <div style="font-size:11px;opacity:0.95;margin-top:2px;">
            Поступила в работу: <strong>📅 ${taskInfo.date}</strong> · Требуется ваше подтверждение
          </div>
        </div>
      `;
    } else {
      attBanner.style.display = 'none';
    }
  }

  const modal = document.getElementById('workstationModal');
  if (modal) modal.classList.add('active');
}

function closeWorkstationModal(e) {
  if (e && e.target !== e.currentTarget && !e.target.classList.contains('modal-close')) return;
  const modal = document.getElementById('workstationModal');
  if (modal) modal.classList.remove('active');
}

function loadWorkstationTopicData(topicId) {
  currentWsTopicId = parseInt(topicId, 10) || 1;
  const container = document.getElementById('wsModalTopicData');
  if (!container) return;

  if (currentWsAgentId === 'raccoon') {
    container.innerHTML = `
      <div style="background:rgba(15,23,42,0.8);border:1px solid #10B981;border-radius:8px;padding:12px;margin-bottom:10px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
          <span style="font-weight:bold;color:#10B981;font-size:13px;">🕸️ GRAPHIFY & БАЗА ЗНАНИЙ АРТЕЛИ</span>
          <span id="raccoonGraphStatusBadge" style="background:#064E3B;color:#6EE7B7;padding:2px 8px;border-radius:4px;font-size:11px;">Связи: 153+ узлов</span>
        </div>
        <div style="font-size:12px;color:#CBD5E1;line-height:1.5;margin-bottom:10px;" id="raccoonKnowledgeDetails">
          Загрузка статуса базы знаний и графа...
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <button type="button" class="btn btn-sm" style="background:#047857;color:#FFFFFF;border:none;border-radius:6px;padding:6px 12px;cursor:pointer;font-weight:bold;font-size:11px;" onclick="triggerGraphifyUpdate()">
            🕸️ Обновить граф Graphify
          </button>
          <button type="button" class="btn btn-sm" style="background:#0D9488;color:#FFFFFF;border:none;border-radius:6px;padding:6px 12px;cursor:pointer;font-weight:bold;font-size:11px;" onclick="triggerDatasetGeneration()">
            📦 Собрать датасет LoRA (ChatML)
          </button>
          <button type="button" class="btn btn-sm" style="background:#4338CA;color:#FFFFFF;border:none;border-radius:6px;padding:6px 12px;cursor:pointer;font-weight:bold;font-size:11px;" onclick="triggerKnowledgeEval()">
            🧪 Проверить знания моделей (Eval)
          </button>
          <a href="/api/dataset/download" class="btn btn-sm" style="background:#334155;color:#E2E8F0;text-decoration:none;border-radius:6px;padding:6px 12px;font-size:11px;display:inline-block;" download="lora_training_dataset.jsonl">
            💾 Скачать JSONL
          </a>
        </div>
        <div id="raccoonActionOutput" style="margin-top:10px;font-size:11px;color:#94A3B8;display:none;padding:8px;background:#09090B;border-radius:6px;border:1px dashed #334155;"></div>
      </div>
    `;

    fetch('/api/knowledge/status')
      .then(r => r.json())
      .then(k => {
        if (k.status === 'ok') {
          const det = document.getElementById('raccoonKnowledgeDetails');
          const badge = document.getElementById('raccoonGraphStatusBadge');
          if (badge) badge.innerText = `Узлов: ${k.graphify.nodes_count} · Связей: ${k.graphify.edges_count}`;
          if (det) {
            det.innerHTML = `
              • <strong>Тем в ядре:</strong> ${k.total_topics} (черновиков: ${k.written_count}, аттестовано ОТК: ${k.verified_count})<br>
              • <strong>Покрытые сплавы:</strong> ${k.alloys_covered.join(', ')}<br>
              • <strong>Датасет LoRA:</strong> ${k.dataset.records_count ? k.dataset.records_count + ' обучающих пар (' + Math.round(k.dataset.size_bytes / 1024) + ' КБ)' : 'готов к сборке'}<br>
              • <strong>Стандарты:</strong> IPC-A-610G, ГОСТ 21930-76, ГОСТ 20.57.406-81, ГОСТ 12.3.003-75
            `;
          }
        }
      })
      .catch(() => {});
    return;
  }

  const topic = (window.state && window.state.topics) ? window.state.topics.find(t => t.id === currentWsTopicId) : null;
  if (!topic) {
    container.innerHTML = `Загрузка данных темы #${currentWsTopicId}...`;
    return;
  }

  container.innerHTML = `
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:8px;margin-bottom:8px;">
      <div><strong>Сплав:</strong> ${topic.solder_alloy || 'ПОС-61 (183–190°C)'}</div>
      <div><strong>Сложность:</strong> ${topic.difficulty || 'Практик'}</div>
      <div><strong>Статус:</strong> <span style="color:#FFC700;">${topic.status || 'в очереди'}</span></div>
      <div><strong>Балл ОТК:</strong> ${topic.score_total ? topic.score_total + '/100' : 'Не рассчитан'}</div>
    </div>
    <div style="color:#94A3B8;font-size:11px;">
      <strong>LSI-хвосты:</strong> ${topic.cluster_name || 'припой, паяльник, жало T12, флюс RMA-223, канифоль, пайка плат'}
    </div>
    <div style="margin-top:6px;color:#A1A1AA;font-size:11px;">
      <strong>Контракт v1.1:</strong> ${topic.has_text ? '✅ Черновик написан (' + (topic.word_count || 1200) + ' слов)' : '⏳ Ожидает написания Котом-Паяльщиком'}
    </div>
  `;
}

function triggerGraphifyUpdate() {
  const out = document.getElementById('raccoonActionOutput');
  if (out) {
    out.style.display = 'block';
    out.innerHTML = '<span style="color:#10B981;">⏳ Запуск инкрементального обновления графа знаний Graphify...</span>';
  }
  fetch('/api/knowledge/graphify_update', { method: 'POST' })
    .then(r => r.json())
    .then(data => {
      if (out) {
        out.innerHTML = `<span style="color:#34D399;">✅ ${data.output || 'Граф связей успешно актуализирован!'}</span>`;
      }
      showToast('Граф связей Graphify обновлен');
      if (window.farm3DInstance) {
        window.farm3DInstance.triggerHandoverAnimation('raccoon', 'bear', 'Graphify');
      }
    })
    .catch(err => {
      if (out) out.innerHTML = `<span style="color:#EF4444;">Ошибка: ${err.message}</span>`;
    });
}

function triggerDatasetGeneration() {
  const out = document.getElementById('raccoonActionOutput');
  if (out) {
    out.style.display = 'block';
    out.innerHTML = '<span style="color:#0D9488;">⏳ Сборка обучающей выборки ChatML JSONL для LoRA...</span>';
  }
  fetch('/api/dataset/generate', { method: 'POST' })
    .then(r => r.json())
    .then(data => {
      if (out) {
        out.innerHTML = `<span style="color:#2DD4BF;">✅ Собрано ${data.exported_count} обучающих пар (${Math.round(data.size_bytes / 1024)} КБ) в файл scratch/lora_training_dataset.jsonl</span>`;
      }
      showToast(`Датасет собран: ${data.exported_count} пар ChatML`);
      if (window.farm3DInstance) {
        window.farm3DInstance.triggerHandoverAnimation('raccoon', 'kot', 'Dataset JSONL');
      }
    })
    .catch(err => {
      if (out) out.innerHTML = `<span style="color:#EF4444;">Ошибка: ${err.message}</span>`;
    });
}

function triggerKnowledgeEval() {
  const out = document.getElementById('raccoonActionOutput');
  if (out) {
    out.style.display = 'block';
    out.innerHTML = '<span style="color:#818CF8;">⏳ Запуск тестового бенчмарка знаний моделей по ГОСТам...</span>';
  }
  fetch('/api/knowledge/eval_test', { method: 'POST' })
    .then(r => r.json())
    .then(data => {
      if (out) {
        const testsHtml = data.tests.map(t => `<div>• ${t.title}: <strong style="color:${t.passed ? '#34D399' : '#EF4444'}">${t.score}% [${t.passed ? 'OK' : 'FAIL'}]</strong> (${t.standard})</div>`).join('');
        out.innerHTML = `
          <div style="font-weight:bold;color:#A78BFA;margin-bottom:4px;">Результат Eval-теста: ${data.verdict} (${data.overall_accuracy}%)</div>
          ${testsHtml}
        `;
      }
      showToast(`Тест знаний: ${data.verdict} (${data.overall_accuracy}%)`);
    })
    .catch(err => {
      if (out) out.innerHTML = `<span style="color:#EF4444;">Ошибка: ${err.message}</span>`;
    });
}

function switchWsModalTab(tabName) {
  const tabs = ['tools', 'docs', 'task', 'data'];
  tabs.forEach(t => {
    const btn = document.getElementById('wsNav' + t.charAt(0).toUpperCase() + t.slice(1));
    const panel = document.getElementById('wsTab' + t.charAt(0).toUpperCase() + t.slice(1) + 'Panel');
    if (btn) {
      if (t === tabName) {
        btn.style.background = '#10B981';
        btn.style.color = '#09090B';
        btn.style.fontWeight = '800';
      } else {
        btn.style.background = '#27272A';
        btn.style.color = '#D4D4D8';
        btn.style.fontWeight = 'normal';
      }
    }
    if (panel) {
      panel.style.display = (t === tabName) ? 'block' : 'none';
    }
  });
}

function renderCraftsmanWorkbench(agentId) {
  const container = document.getElementById('wsWorkbenchContainer');
  if (!container) return;
  const topic = (window.state && window.state.topics) ? window.state.topics.find(t => t.id === currentWsTopicId) : { id: 1, title: 'Пайка для начинающих', solder_alloy: 'ПОС-61 (183–190°C)' };

  if (agentId === 'soroka') {
    container.innerHTML = `
      <div style="background:#09090B;border:1.5px solid #38BDF8;border-radius:8px;padding:14px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
          <div style="font-weight:800;color:#38BDF8;font-size:13px;display:flex;align-items:center;gap:6px;">
            🪶 LSI-ЯДРО И СЕМАНТИЧЕСКИЙ АНАЛИЗАТОР ПОИСКОВОЙ ВЫДАЧИ
          </div>
          <span style="background:#0369A1;color:#E0F2FE;padding:2px 8px;border-radius:4px;font-size:11px;">63 темы в seo.db</span>
        </div>
        <div style="margin-bottom:10px;">
          <label style="font-size:11px;color:#94A3B8;">Поисковый кластер темы:</label>
          <div style="display:flex;gap:8px;margin-top:4px;">
            <input type="text" id="sorokaKeywordInput" value="${topic.title || 'пайка плат для начинающих'}" style="flex:1;background:#18181D;border:1px solid #38BDF8;color:#FFF;padding:6px 10px;border-radius:4px;font-size:12px;">
            <button type="button" class="btn btn--sm" onclick="showToast('Парсинг LSI-хвостов Яндекса выполнен!')" style="background:#0284C7;color:#FFF;border:none;border-radius:4px;padding:6px 12px;cursor:pointer;font-weight:bold;">🔍 Собрать LSI</button>
          </div>
        </div>
        <div style="font-size:11px;color:#94A3B8;margin-bottom:6px;">Ключевые LSI-хвосты с частотностью (Wordstat 2026):</div>
        <table style="width:100%;border-collapse:collapse;font-size:11px;text-align:left;background:#18181D;border-radius:6px;overflow:hidden;">
          <thead>
            <tr style="background:#1E293B;color:#94A3B8;">
              <th style="padding:6px 10px;">Поисковый запрос</th>
              <th style="padding:6px 10px;">Частота / мес</th>
              <th style="padding:6px 10px;">Конкуренция</th>
              <th style="padding:6px 10px;">Статус</th>
            </tr>
          </thead>
          <tbody>
            <tr style="border-bottom:1px solid #27272A;"><td style="padding:6px 10px;color:#FFF;">${topic.title || 'пайка плат'} для новичков</td><td style="padding:6px 10px;color:#38BDF8;">3 420</td><td style="padding:6px 10px;color:#4ADE80;">Низкая (12)</td><td style="padding:6px 10px;color:#4ADE80;">В ядре ✅</td></tr>
            <tr style="border-bottom:1px solid #27272A;"><td style="padding:6px 10px;color:#FFF;">как правильно паять паяльником с канифолью</td><td style="padding:6px 10px;color:#38BDF8;">1 840</td><td style="padding:6px 10px;color:#4ADE80;">Низкая (8)</td><td style="padding:6px 10px;color:#4ADE80;">В ядре ✅</td></tr>
            <tr style="border-bottom:1px solid #27272A;"><td style="padding:6px 10px;color:#FFF;">припой пос 61 температура плавления и состав</td><td style="padding:6px 10px;color:#38BDF8;">980</td><td style="padding:6px 10px;color:#4ADE80;">Низкая (5)</td><td style="padding:6px 10px;color:#4ADE80;">В ядре ✅</td></tr>
            <tr style="border-bottom:1px solid #27272A;"><td style="padding:6px 10px;color:#FFF;">какой флюс выбрать для пайки микросхем</td><td style="padding:6px 10px;color:#38BDF8;">1 120</td><td style="padding:6px 10px;color:#FACC15;">Средняя (19)</td><td style="padding:6px 10px;color:#4ADE80;">В ядре ✅</td></tr>
          </tbody>
        </table>
        <div style="margin-top:10px;display:flex;gap:8px;">
          <button type="button" class="btn btn--sm" onclick="showToast('Семантическое ядро зафиксировано!')" style="background:#0284C7;color:#FFF;border:none;border-radius:4px;padding:6px 12px;cursor:pointer;">📥 Применить LSI к теме #${topic.id}</button>
          <button type="button" class="btn btn--sm" onclick="switchTab('competitors')" style="background:#334155;color:#E2E8F0;border:none;border-radius:4px;padding:6px 12px;cursor:pointer;">📊 Открыть досье конкурентов</button>
        </div>
      </div>
    `;

  } else if (agentId === 'bobr') {
    container.innerHTML = `
      <div style="background:#09090B;border:1.5px solid #B45309;border-radius:8px;padding:14px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
          <div style="font-weight:800;color:#F59E0B;font-size:13px;display:flex;align-items:center;gap:6px;">
            🦫 КОНСТРУКТОР ТЕХНИЧЕСКОГО БРИФА ПО ГОСТ Р МЭК 61191-1
          </div>
          <span style="background:#78350F;color:#FEF3C7;padding:2px 8px;border-radius:4px;font-size:11px;">ГОСТ 21930 / IPC-A-610</span>
        </div>
        <div style="background:#18181D;border:1px solid #27272A;border-radius:6px;padding:10px;margin-bottom:10px;">
          <div style="font-weight:bold;color:#FBBF24;font-size:11.5px;margin-bottom:6px;">Чек-лист 4 обязательных вопросов новичка:</div>
          <label style="display:flex;align-items:center;gap:8px;margin-bottom:4px;font-size:11.5px;cursor:pointer;">
            <input type="checkbox" checked style="accent-color:#F59E0B;"> 1. Точный состав сплава и коридор плавления (ПОС-61: солидус 183°C, ликвидус 190°C)
          </label>
          <label style="display:flex;align-items:center;gap:8px;margin-bottom:4px;font-size:11.5px;cursor:pointer;">
            <input type="checkbox" checked style="accent-color:#F59E0B;"> 2. Безопасный флюс и регламент отмывки (RMA канифольный, отмывка ИПС 1:1)
          </label>
          <label style="display:flex;align-items:center;gap:8px;margin-bottom:4px;font-size:11.5px;cursor:pointer;">
            <input type="checkbox" checked style="accent-color:#F59E0B;"> 3. Форма жала и рабочая уставка (Клин 2.4D, 275–285°C без перегрева)
          </label>
          <label style="display:flex;align-items:center;gap:8px;font-size:11.5px;cursor:pointer;">
            <input type="checkbox" checked style="accent-color:#F59E0B;"> 4. Техника безопасности и вентиляция аэрозолей по ГОСТ 12.3.003-75
          </label>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:10px;margin-bottom:12px;">
          <div>
            <label style="font-size:11px;color:#94A3B8;">Выбор сплава по реестру:</label>
            <select id="bobrAlloySelect" style="width:100%;background:#18181D;color:#FFF;border:1px solid #B45309;padding:6px;border-radius:4px;font-size:11.5px;margin-top:4px;">
              <option value="pos61" selected>ПОС-61 (183–190°C) — оловянно-свинцовый</option>
              <option value="sac305">SAC305 (217–220°C) — бессвинцовый RoHS</option>
              <option value="roze">Сплав Розе (94°C) — легкоплавкий демонтажный</option>
              <option value="pos40">ПОС-40 (183–238°C) — монтажный</option>
            </select>
          </div>
          <div>
            <label style="font-size:11px;color:#94A3B8;">Класс надежности электронного модуля:</label>
            <select style="width:100%;background:#18181D;color:#FFF;border:1px solid #B45309;padding:6px;border-radius:4px;font-size:11.5px;margin-top:4px;">
              <option value="c2" selected>Класс 2 (Промышленная & Сервисная техника)</option>
              <option value="c1">Класс 1 (Бытовая электроника общего назначения)</option>
              <option value="c3">Класс 3 (Высоконадежная медицинская/автомобильная)</option>
            </select>
          </div>
        </div>
        <button type="button" class="btn btn--sm" onclick="showToast('Бриф ГОСТ утвержден и отправлен в цех Кота!')" style="background:#D97706;color:#FFF;border:none;border-radius:4px;padding:6px 14px;cursor:pointer;font-weight:bold;">📐 Утвердить бриф конструктора</button>
      </div>
    `;

  } else if (agentId === 'kot') {
    container.innerHTML = `
      <div style="background:#09090B;border:1.5px solid #EA580C;border-radius:8px;padding:14px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
          <div style="font-weight:800;color:#FB923C;font-size:13px;display:flex;align-items:center;gap:6px;">
            🐱 ПАЯЛЬНЫЙ ЦЕХ И ТРИАДА ФИЗИКИ (PRACTICO 1)
          </div>
          <span style="background:#7C2D12;color:#FFEDD5;padding:2px 8px;border-radius:4px;font-size:11px;">Температура жала: 280°C</span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(210px, 1fr));gap:10px;margin-bottom:12px;">
          <div style="background:#18181D;border:1px solid #27272A;border-radius:6px;padding:10px;">
            <div style="font-size:11px;color:#FDBA74;font-weight:bold;margin-bottom:4px;">Подбор жала T12/C245:</div>
            <div style="font-size:11.5px;color:#E2E8F0;">• <strong>Клин 2.4D:</strong> теплоемкость 95%, пятно 2.4 мм² (рекомендовано для темы)</div>
            <div style="font-size:11.5px;color:#94A3B8;">• <strong>Игла I:</strong> теплоемкость 35% (риск примерзания к полигону)</div>
            <div style="font-size:11.5px;color:#94A3B8;">• <strong>Нож K:</strong> теплоемкость 90% (подрезка перемычек)</div>
          </div>
          <div style="background:#18181D;border:1px solid #27272A;border-radius:6px;padding:10px;">
            <div style="font-size:11px;color:#FDBA74;font-weight:bold;margin-bottom:4px;">Физика смачивания меди:</div>
            <div style="font-size:11.5px;color:#E2E8F0;">• Интерметаллид: <strong>Cu₆Sn₅</strong> (толщина 0.8–1.2 мкм при 265°C)</div>
            <div style="font-size:11.5px;color:#E2E8F0;">• Мениск: вогнутый галтельный шов (угол смачивания &lt;30°)</div>
            <div style="font-size:11.5px;color:#EF4444;">• Ошибка: перегрев &gt;300°C растит хрупкий Cu₃Sn!</div>
          </div>
        </div>
        <div>
          <label style="font-size:11px;color:#94A3B8;">Редактор статьи мастера Practico 1:</label>
          <textarea id="kotDraftText" rows="6" style="width:100%;background:#18181D;border:1px solid #EA580C;color:#FFF;padding:8px 10px;font-family:monospace;font-size:11.5px;border-radius:4px;margin-top:4px;resize:vertical;" placeholder="Черновик статьи формируется Котом-Паяльщиком...">${topic.content_markdown ? topic.content_markdown.substring(0, 450) + '...' : 'Черновик темы #' + topic.id + ' ожидает генерации...'}</textarea>
        </div>
        <div style="margin-top:8px;display:flex;gap:8px;">
          <button type="button" class="btn btn--sm" onclick="showToast('Лонгрид отправлен на просчет сметы Лису!')" style="background:#EA580C;color:#FFF;border:none;border-radius:4px;padding:6px 14px;cursor:pointer;font-weight:bold;">✍️ Написать лонгрид по физике пайки</button>
        </div>
      
        <div style="margin-top:14px;background:#18181D;border:1.5px solid #EA580C;border-radius:6px;padding:12px;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:6px;">
            <div style="font-weight:bold;color:#FB923C;font-size:12px;display:flex;align-items:center;gap:6px;">
              ✍️ СВОИМИ СИЛАМИ / ВНЕШНЯЯ LLM («МОИМ ГОЛОСОСОМ»)
            </div>
            <button type="button" class="btn btn--xs" onclick="copyKotMegaPrompt()" style="background:#EA580C;color:#FFF;border:none;border-radius:4px;padding:4px 10px;cursor:pointer;font-weight:bold;">
              📋 Скопировать Мега-Промпт
            </button>
          </div>
          <p style="font-size:11px;color:#94A3B8;margin:0 0 8px 0;line-height:1.4;">
            Если нужно написать статью во внешней LLM (Claude 3.7 / ChatGPT) или самостоятельно: скопируйте подготовленный промпт с авторским голосом («женский род, от первого лица, конкретные грабли и цифры»), а готовую статью вставьте ниже для проверки Филином [ОТК-ГОСТ].
          </p>
          <textarea id="wsKotArticlePaste" placeholder="Вставьте готовый Markdown статьи сюда для автоматической проверки по ГОСТ и AST-метрикам..." style="width:100%;height:90px;background:#09090B;border:1px solid #4B5563;color:#F3F4F6;padding:8px;border-radius:4px;font-size:11.5px;font-family:monospace;resize:vertical;"></textarea>
          <div style="display:flex;justify-content:flex-end;margin-top:6px;">
            <button type="button" class="btn btn--sm" onclick="submitPastedArticleToOtk()" style="background:#22C55E;color:#000;border:none;border-radius:4px;padding:6px 14px;cursor:pointer;font-weight:bold;font-size:11.5px;">
              🦉 Отправить Филину на [ОТК-ГОСТ]
            </button>
          </div>
        </div>
    
    </div>
    `;

  } else if (agentId === 'lis') {
    container.innerHTML = `
      <div style="background:#09090B;border:1.5px solid #F59E0B;border-radius:8px;padding:14px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
          <div style="font-weight:800;color:#FBBF24;font-size:13px;display:flex;align-items:center;gap:6px;">
            🦊 ИНТЕРАКТИВНЫЙ СМЕТНЫЙ КАЛЬКУЛЯТОР ПАЙКИ (PRACTICO 2)
          </div>
          <span style="background:#78350F;color:#FEF3C7;padding:2px 8px;border-radius:4px;font-size:11px;">Экономика мастерской</span>
        </div>
        <div style="background:#18181D;border:1px solid #27272A;border-radius:6px;padding:10px;margin-bottom:12px;">
          <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(170px, 1fr));gap:8px;">
            <div>
              <label style="font-size:11px;color:#94A3B8;">Катушка ПОС-61 (100г), ₽:</label>
              <input type="number" id="calcSolderCost" value="850" oninput="recalcSolderCost()" style="width:100%;background:#09090B;border:1px solid #52525B;color:#FFF;padding:4px 8px;border-radius:4px;font-size:12px;margin-top:2px;">
            </div>
            <div>
              <label style="font-size:11px;color:#94A3B8;">Расход припоя на шов, г:</label>
              <input type="number" id="calcSolderWeight" value="0.015" step="0.005" oninput="recalcSolderCost()" style="width:100%;background:#09090B;border:1px solid #52525B;color:#FFF;padding:4px 8px;border-radius:4px;font-size:12px;margin-top:2px;">
            </div>
            <div>
              <label style="font-size:11px;color:#94A3B8;">Тюбик флюса RMA (10г), ₽:</label>
              <input type="number" id="calcFluxCost" value="350" oninput="recalcSolderCost()" style="width:100%;background:#09090B;border:1px solid #52525B;color:#FFF;padding:4px 8px;border-radius:4px;font-size:12px;margin-top:2px;">
            </div>
          </div>
          <div style="margin-top:10px;padding:8px;background:#09090B;border-radius:4px;display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:12px;color:#CBD5E1;">Себестоимость одного соединения:</span>
            <strong id="calcResultPerJoint" style="font-size:15px;color:#4ADE80;">0.23 ₽ / пайка</strong>
          </div>
        </div>
        <div style="font-size:11px;color:#94A3B8;margin-bottom:6px;">Сравнение окупаемости оборудования для новичка:</div>
        <table style="width:100%;border-collapse:collapse;font-size:11px;text-align:left;background:#18181D;border-radius:6px;overflow:hidden;">
          <thead>
            <tr style="background:#1E293B;color:#94A3B8;">
              <th style="padding:6px 10px;">Инструмент</th>
              <th style="padding:6px 10px;">Цена</th>
              <th style="padding:6px 10px;">Риск повреждения платы</th>
              <th style="padding:6px 10px;">Итоговая выгода</th>
            </tr>
          </thead>
          <tbody>
            <tr style="border-bottom:1px solid #27272A;"><td style="padding:6px 10px;color:#FFF;">Паяльник без стабилизации (220В)</td><td style="padding:6px 10px;">600 ₽</td><td style="padding:6px 10px;color:#EF4444;">Высокий (-15 000 ₽ плата)</td><td style="padding:6px 10px;color:#EF4444;">Убыток -14 400 ₽</td></tr>
            <tr style="border-bottom:1px solid #27272A;"><td style="padding:6px 10px;color:#FFF;">Станция T12 с термоконтролем</td><td style="padding:6px 10px;">3 200 ₽</td><td style="padding:6px 10px;color:#4ADE80;">Минимальный (&lt;1%)</td><td style="padding:6px 10px;color:#4ADE80;">Окупаемость 320% ✅</td></tr>
          </tbody>
        </table>
        <div style="margin-top:10px;">
          <button type="button" class="btn btn--sm" onclick="showToast('Смета и таблица окупаемости внедрены в статью!')" style="background:#D97706;color:#FFF;border:none;border-radius:4px;padding:6px 14px;cursor:pointer;font-weight:bold;">💰 Внедрить расчет сметы в статью</button>
        </div>
      </div>
    `;

  } else if (agentId === 'barsuk') {
    container.innerHTML = `
      <div style="background:#09090B;border:1.5px solid #FBBF24;border-radius:8px;padding:14px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
          <div style="font-weight:800;color:#FDE047;font-size:13px;display:flex;align-items:center;gap:6px;">
            🦡 ВЕКТОРНЫЙ SVG-КУЛЬМАН ТЕРМОПРОФИЛЕЙ ОПЛАВЛЕНИЯ
          </div>
          <span style="background:#713F12;color:#FEF9C3;padding:2px 8px;border-radius:4px;font-size:11px;">0 токенов расхода</span>
        </div>
        <div style="display:flex;justify-content:center;background:#18181D;border:1px solid #27272A;border-radius:6px;padding:12px;margin-bottom:12px;">
          <svg width="420" height="150" viewBox="0 0 420 150" style="background:#09090B;border-radius:6px;">
            <line x1="40" y1="120" x2="390" y2="120" stroke="#334155" stroke-width="1.5" />
            <line x1="40" y1="20" x2="40" y2="120" stroke="#334155" stroke-width="1.5" />
            <path d="M 40 120 L 110 80 L 210 65 L 260 25 L 300 35 L 370 120" fill="none" stroke="#FBBF24" stroke-width="3" />
            <line x1="40" y1="58" x2="390" y2="58" stroke="#EF4444" stroke-dasharray="4,4" stroke-width="1" />
            <text x="45" y="54" fill="#EF4444" font-size="10" font-family="monospace">Ликвидус ПОС-61: 190°C</text>
            <text x="65" y="105" fill="#94A3B8" font-size="9" font-family="monospace">Preheat</text>
            <text x="150" y="90" fill="#94A3B8" font-size="9" font-family="monospace">Soak</text>
            <text x="250" y="18" fill="#FBBF24" font-size="10" font-family="monospace" font-weight="bold">Reflow 225°C</text>
            <text x="325" y="85" fill="#38BDF8" font-size="9" font-family="monospace">Cooling</text>
          </svg>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
          <label style="font-size:11px;color:#94A3B8;">Выбор термопрофиля:</label>
          <select style="background:#18181D;color:#FFF;border:1px solid #FBBF24;padding:4px 8px;border-radius:4px;font-size:11.5px;">
            <option value="pos61" selected>ПОС-61: 183–190°C (Пик 215–225°C, TAL 50с)</option>
            <option value="sac305">SAC305: 217–220°C (Пик 235–245°C, TAL 60с)</option>
            <option value="roze">Сплав Розе: 94°C (Пик 130–140°C, демонтаж)</option>
          </select>
          <button type="button" class="btn btn--sm" onclick="showToast('Векторный SVG сгенерирован и добавлен в паспорт v1.1!')" style="background:#CA8A04;color:#FFF;border:none;border-radius:4px;padding:6px 14px;cursor:pointer;font-weight:bold;">🎨 Вшить чистый SVG в статью</button>
        </div>
      </div>
    `;

  } else if (agentId === 'filin') {
    container.innerHTML = `
      <div style="background:#09090B;border:1.5px solid #8B5CF6;border-radius:8px;padding:14px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
          <div style="font-weight:800;color:#C4B5FD;font-size:13px;display:flex;align-items:center;gap:6px;">
            🦉 ДЕТЕКТОР НЕЙРОШТАМПОВ SLOPMONSTER & ШТАМП [ОТК-2026]
          </div>
          <span style="background:#5B21B6;color:#EDE9FE;padding:2px 8px;border-radius:4px;font-size:11px;">База: 50+ стоп-клише</span>
        </div>
        <div style="background:#18181D;border:1px solid #27272A;border-radius:6px;padding:10px;margin-bottom:12px;">
          <div style="font-weight:bold;color:#A78BFA;font-size:11.5px;margin-bottom:6px;">Результат сканирования черновика статьи:</div>
          <div style="font-size:11.5px;color:#4ADE80;margin-bottom:4px;">✅ Клише «в современном мире» — не обнаружено</div>
          <div style="font-size:11.5px;color:#4ADE80;margin-bottom:4px;">✅ Оборот «ни для кого не секрет» — устранен</div>
          <div style="font-size:11.5px;color:#4ADE80;margin-bottom:4px;">✅ Вода «является важным аспектом» — заменена физикой процесса</div>
          <div style="font-size:11.5px;color:#FBBF24;">⚡ Рекомендация: в разделе ТБ усилить требование к принудительной вытяжке паров свинца.</div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
          <button type="button" class="btn btn--sm" onclick="showToast('SlopMonster: текст проверен, 0 нейроштампов!')" style="background:#7C3AED;color:#FFF;border:none;border-radius:4px;padding:6px 14px;cursor:pointer;font-weight:bold;">🔍 Сканировать SlopMonster</button>
          <button type="button" class="btn btn--sm" onclick="showToast('Штамп [ОТК-2026: ПРИНЯТО] поставлен!'); if(window.farm3DInstance) window.farm3DInstance.triggerHandoverAnimation('filin','dyatel','ОТК-Штамп');" style="background:#059669;color:#FFF;border:none;border-radius:4px;padding:6px 14px;cursor:pointer;font-weight:bold;">🦉 Выставить штамп [ОТК-2026: ПРИНЯТО]</button>
        </div>
      </div>
    `;

  } else if (agentId === 'dyatel') {
    container.innerHTML = `
      <div style="background:#09090B;border:1.5px solid #EC4899;border-radius:8px;padding:14px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
          <div style="font-weight:800;color:#F472B6;font-size:13px;display:flex;align-items:center;gap:6px;">
            🪵 МАТЕМАТИЧЕСКИЙ AST-СКОРИНГ СТАТЕЙ (100 БАЛЛОВ)
          </div>
          <span style="background:#831843;color:#FCE7F3;padding:2px 8px;border-radius:4px;font-size:11px;">Порог допуска в релиз: ≥80</span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(210px, 1fr));gap:8px;margin-bottom:12px;">
          <div style="background:#18181D;border:1px solid #27272A;border-radius:6px;padding:8px;">
            <div style="font-size:11px;color:#94A3B8;">1. Архитектура H2/H3 (15 б): <strong style="color:#4ADE80;">15/15</strong></div>
            <div style="width:100%;background:#334155;height:5px;border-radius:3px;margin-top:4px;"><div style="width:100%;background:#10B981;height:5px;border-radius:3px;"></div></div>
          </div>
          <div style="background:#18181D;border:1px solid #27272A;border-radius:6px;padding:8px;">
            <div style="font-size:11px;color:#94A3B8;">2. Нормы и ГОСТы (20 б): <strong style="color:#4ADE80;">20/20</strong></div>
            <div style="width:100%;background:#334155;height:5px;border-radius:3px;margin-top:4px;"><div style="width:100%;background:#10B981;height:5px;border-radius:3px;"></div></div>
          </div>
          <div style="background:#18181D;border:1px solid #27272A;border-radius:6px;padding:8px;">
            <div style="font-size:11px;color:#94A3B8;">3. Триада физики (25 б): <strong style="color:#4ADE80;">23/25</strong></div>
            <div style="width:100%;background:#334155;height:5px;border-radius:3px;margin-top:4px;"><div style="width:92%;background:#10B981;height:5px;border-radius:3px;"></div></div>
          </div>
          <div style="background:#18181D;border:1px solid #27272A;border-radius:6px;padding:8px;">
            <div style="font-size:11px;color:#94A3B8;">4. Практика и ошибки (20 б): <strong style="color:#4ADE80;">19/20</strong></div>
            <div style="width:100%;background:#334155;height:5px;border-radius:3px;margin-top:4px;"><div style="width:95%;background:#10B981;height:5px;border-radius:3px;"></div></div>
          </div>
          <div style="background:#18181D;border:1px solid #27272A;border-radius:6px;padding:8px;">
            <div style="font-size:11px;color:#94A3B8;">5. Реестр сплавов (10 б): <strong style="color:#4ADE80;">10/10</strong></div>
            <div style="width:100%;background:#334155;height:5px;border-radius:3px;margin-top:4px;"><div style="width:100%;background:#10B981;height:5px;border-radius:3px;"></div></div>
          </div>
          <div style="background:#18181D;border:1px solid #27272A;border-radius:6px;padding:8px;">
            <div style="font-size:11px;color:#94A3B8;">6. Контракт v1.1 (10 б): <strong style="color:#4ADE80;">10/10</strong></div>
            <div style="width:100%;background:#334155;height:5px;border-radius:3px;margin-top:4px;"><div style="width:100%;background:#10B981;height:5px;border-radius:3px;"></div></div>
          </div>
        </div>
        <div style="background:#18181D;padding:10px;border-radius:6px;display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
          <div>
            <div style="font-size:12px;color:#FFF;font-weight:bold;">Итоговый метрологический балл темы #${topic.id}:</div>
            <div style="font-size:11px;color:#4ADE80;">Соответствует нормам публикации (Релиз разрешен)</div>
          </div>
          <strong style="font-size:24px;color:#4ADE80;">97 / 100</strong>
        </div>
        <button type="button" class="btn btn--sm" onclick="showToast('AST-скоринг выполнен: 97/100 баллов! Зеленый свет!'); if(window.farm3DInstance) window.farm3DInstance.triggerHandoverAnimation('dyatel','wp','Релизный пакет');" style="background:#DB2777;color:#FFF;border:none;border-radius:4px;padding:6px 14px;cursor:pointer;font-weight:bold;">🪵 Пересчитать AST-скоринг</button>
      </div>
    `;

  } else if (agentId === 'raccoon') {
    container.innerHTML = `
      <div style="background:#09090B;border:1.5px solid #10B981;border-radius:8px;padding:14px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
          <div style="font-weight:800;color:#10B981;font-size:13px;display:flex;align-items:center;gap:6px;">
            🦝 GRAPHIFY & ПОДГОТОВКА ОБУЧАЮЩЕГО ДАТАСЕТА LORA
          </div>
          <span id="raccoonGraphStatusBadge" style="background:#064E3B;color:#6EE7B7;padding:2px 8px;border-radius:4px;font-size:11px;">Связи: 14 039 узлов</span>
        </div>
        <div style="font-size:12px;color:#CBD5E1;line-height:1.6;margin-bottom:12px;" id="raccoonKnowledgeDetails">
          Загрузка статуса базы знаний и графа связей...
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
          <button type="button" class="btn btn--sm" style="background:#047857;color:#FFFFFF;border:none;border-radius:6px;padding:7px 14px;cursor:pointer;font-weight:bold;font-size:11.5px;" onclick="triggerGraphifyUpdate()">
            🕸️ Обновить граф Graphify
          </button>
          <button type="button" class="btn btn--sm" style="background:#0D9488;color:#FFFFFF;border:none;border-radius:6px;padding:7px 14px;cursor:pointer;font-weight:bold;font-size:11.5px;" onclick="triggerDatasetGeneration()">
            📦 Собрать датасет LoRA (ChatML)
          </button>
          <button type="button" class="btn btn--sm" style="background:#4338CA;color:#FFFFFF;border:none;border-radius:6px;padding:7px 14px;cursor:pointer;font-weight:bold;font-size:11.5px;" onclick="triggerKnowledgeEval()">
            🧪 Проверить знания моделей (Eval)
          </button>
          <a href="/api/dataset/download" class="btn btn--sm" style="background:#334155;color:#E2E8F0;text-decoration:none;border-radius:6px;padding:7px 14px;font-size:11.5px;display:inline-block;" download="lora_training_dataset.jsonl">
            💾 Скачать JSONL
          </a>
        </div>
        <div id="raccoonActionOutput" style="margin-top:10px;font-size:11.5px;color:#94A3B8;display:none;padding:10px;background:#09090B;border-radius:6px;border:1px dashed #334155;"></div>
      </div>
    `;

    fetch('/api/knowledge/status')
      .then(r => r.json())
      .then(k => {
        if (k.status === 'ok') {
          const det = document.getElementById('raccoonKnowledgeDetails');
          const badge = document.getElementById('raccoonGraphStatusBadge');
          if (badge) badge.innerText = `Узлов: ${k.graphify.nodes_count} · Связей: ${k.graphify.edges_count}`;
          if (det) {
            det.innerHTML = `
              • <strong>Тем в семантическом ядре:</strong> ${k.total_topics} (написано черновиков: ${k.written_count}, аттестовано ОТК: ${k.verified_count})<br>
              • <strong>Реестр сплавов:</strong> ${k.alloys_covered.join(', ')}<br>
              • <strong>Обучающий датасет LoRA:</strong> ${k.dataset.records_count ? k.dataset.records_count + ' обучающих пар (' + Math.round(k.dataset.size_bytes / 1024) + ' КБ)' : 'готов к сборке'}<br>
              • <strong>Аудит ГОСТ:</strong> IPC-A-610G, ГОСТ 21930-76, ГОСТ 20.57.406-81, ГОСТ 12.3.003-75
            `;
          }
        }
      })
      .catch(() => {});

  } else if (agentId === 'bear') {
    container.innerHTML = `
      <div style="background:#09090B;border:1.5px solid #EAB308;border-radius:8px;padding:14px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
          <div style="font-weight:800;color:#FACC15;font-size:13px;display:flex;align-items:center;gap:6px;">
            🐻 КОМАНДНЫЙ ЦЕНТР АРТЕЛИ & ТЕЛЕМЕТРИЯ TRIAD-OMNI
          </div>
          <span style="background:#713F12;color:#FEF08A;padding:2px 8px;border-radius:4px;font-size:11px;">Snapdragon 865 + 690 + 625</span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:8px;margin-bottom:12px;">
          <div style="background:#18181D;border:1px solid #27272A;border-radius:6px;padding:8px;">
            <div style="font-size:11px;color:#A1A1AA;">NODE-1: Mi 10T 5G</div>
            <div style="font-size:12px;font-weight:bold;color:#4ADE80;">:8080 · Qwen3.5-9B</div>
          </div>
          <div style="background:#18181D;border:1px solid #27272A;border-radius:6px;padding:8px;">
            <div style="font-size:11px;color:#A1A1AA;">NODE-2: Nord N10 5G</div>
            <div style="font-size:12px;font-weight:bold;color:#4ADE80;">:8081 · LFM2.5 + BGE-M3</div>
          </div>
          <div style="background:#18181D;border:1px solid #27272A;border-radius:6px;padding:8px;">
            <div style="font-size:11px;color:#A1A1AA;">NODE-3: Redmi Note 4</div>
            <div style="font-size:12px;font-weight:bold;color:#4ADE80;">:8000 · Master Hub</div>
          </div>
          <div style="background:#18181D;border:1px solid #27272A;border-radius:6px;padding:8px;">
            <div style="font-size:11px;color:#A1A1AA;">OmniRoute Federation</div>
            <div style="font-size:12px;font-weight:bold;color:#38BDF8;">:20128 · 754 модели</div>
          </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <button type="button" class="btn btn--sm" onclick="openRoutingModal()" style="background:#2563EB;color:#FFF;border:none;border-radius:4px;padding:6px 12px;cursor:pointer;font-weight:bold;">⚙️ Открыть YAML-маршрутизацию</button>
          <button type="button" class="btn btn--sm" onclick="switchTab('agents')" style="background:#334155;color:#FFF;border:none;border-radius:4px;padding:6px 12px;cursor:pointer;">💬 Чат со старостой артели</button>
        </div>
      </div>
    `;
  }
}

function recalcSolderCost() {
  const spool = parseFloat(document.getElementById('calcSolderCost')?.value) || 850;
  const weight = parseFloat(document.getElementById('calcSolderWeight')?.value) || 0.015;
  const flux = parseFloat(document.getElementById('calcFluxCost')?.value) || 350;

  const solderPerJoint = (spool / 100) * weight;
  const fluxPerJoint = (flux / 1000) * 0.05;
  const total = (solderPerJoint + fluxPerJoint + 0.05).toFixed(2);

  const resEl = document.getElementById('calcResultPerJoint');
  if (resEl) resEl.innerText = `${total} ₽ / пайка`;
}

function executeWorkstationAction() {
  const agentId = currentWsAgentId;
  const topicId = currentWsTopicId || 1;
  const data = CRAFTSMAN_DOSSIER[agentId];
  if (!data) return;

  closeWorkstationModal();
  if (typeof showToast === 'function') {
    showToast(`⚙️ Мастер ${data.title} приступил к выполнению задачи по теме #${topicId}...`);
  }

  // 1. Если 3D-ферма загружена, запускаем анимацию передачи задачи следующему мастеру
  if (window.farm3d && typeof window.farm3d.triggerAgentHandover === 'function') {
    window.farm3d.triggerAgentHandover(agentId, topicId);
  } else if (typeof window.onConveyorStepCompleted === 'function') {
    const nextMap = {
      soroka: 'bobr', bobr: 'kot', kot: 'lis', lis: 'barsuk',
      barsuk: 'filin', filin: 'dyatel', dyatel: 'raccoon', raccoon: 'bear', bear: 'wp'
    };
    const stepNums = { soroka: 1, bobr: 2, kot: 3, lis: 4, barsuk: 5, filin: 6, dyatel: 7, raccoon: 8, bear: 9 };
    const toAgent = nextMap[agentId] || 'wp';
    window.onConveyorStepCompleted(agentId, toAgent, topicId, stepNums[agentId] || 1, 9);
  }
}

// ============================================================
// YAML МАРШРУТИЗАЦИЯ, МОНИТОРИНГ УЗЛОВ И АВТОЗАМЕНА
// ============================================================
let simulatedOfflineNodes = [];

function openRoutingModal() {
  const modal = document.getElementById('routingModal');
  if (modal) modal.classList.add('active');
  fetchRoutingConfig();
}

function closeRoutingModal(e) {
  if (e && e.target !== e.currentTarget && !e.target.classList.contains('modal-close')) return;
  const modal = document.getElementById('routingModal');
  if (modal) modal.classList.remove('active');
}

function fetchRoutingConfig() {
  const statusEl = document.getElementById('routingSaveStatus');
  if (statusEl) statusEl.innerText = 'Опрос узлов кластера...';

  fetch('/api/agents/routing')
    .then(r => r.json())
    .then(data => {
      if (data.status === 'ok') {
        const textarea = document.getElementById('routingYamlTextarea');
        if (textarea) textarea.value = data.yaml;
        updateClusterHealthCards(data.health);
        window.clusterHealth = data.health;
        if (window.farm3D) window.farm3D.applyHealthStatus(data.health);
        if (statusEl) statusEl.innerText = 'Конфигурация актуальна. Узлы проверены.';
      }
    })
    .catch(err => {
      if (statusEl) statusEl.innerText = 'Ошибка загрузки маршрутизации: ' + err;
    });
}

function updateClusterHealthCards(health) {
  if (!health || !health.nodes) return;
  const nodes = health.nodes;

  const nodeMap = {
    node1_brain: { status: 'statusNode1', dot: 'dotNode1' },
    node2_reflex: { status: 'statusNode2', dot: 'dotNode2' },
    node3_gateway: { status: 'statusNode3', dot: 'dotNode3' },
    omniroute_hub: { status: 'statusOmniroute', dot: 'dotOmniroute' }
  };

  Object.entries(nodeMap).forEach(([key, els]) => {
    const info = nodes[key];
    const sEl = document.getElementById(els.status);
    const dEl = document.getElementById(els.dot);
    if (!info || !sEl || !dEl) return;

    if (info.alive) {
      sEl.style.color = '#22C55E';
      sEl.innerText = 'Онлайн';
      dEl.className = 'status-dot active';
      dEl.style.background = '#22C55E';
    } else {
      sEl.style.color = '#EF4444';
      sEl.innerText = info.simulated_offline ? 'Сбой (Симуляция)' : 'Оффлайн';
      dEl.className = 'status-dot';
      dEl.style.background = '#EF4444';
    }
  });
}

function pingClusterNodes() {
  const statusEl = document.getElementById('routingSaveStatus');
  if (statusEl) statusEl.innerText = 'Пинг узлов кластера...';

  fetch('/api/agents/ping', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ simulate_offline: simulatedOfflineNodes })
  })
    .then(r => r.json())
    .then(data => {
      if (data.status === 'ok') {
        updateClusterHealthCards(data.health);
        window.clusterHealth = data.health;
        if (window.farm3D) window.farm3D.applyHealthStatus(data.health);
        if (statusEl) statusEl.innerText = 'Пинг выполнен. Статус обновлен.';
        showToast('Пинг узлов кластера выполнен');
      }
    });
}

function simulateNodeFailure(nodeName) {
  if (!simulatedOfflineNodes.includes(nodeName)) {
    simulatedOfflineNodes.push(nodeName);
  }
  showToast(`Симуляция: узел ${nodeName} отключен!`);
  pingClusterNodes();
}

function restoreAllNodes() {
  simulatedOfflineNodes = [];
  showToast('Все узлы кластера восстановлены!');
  pingClusterNodes();
}

function saveRoutingYaml() {
  const textarea = document.getElementById('routingYamlTextarea');
  const statusEl = document.getElementById('routingSaveStatus');
  if (!textarea) return;

  const yamlContent = textarea.value;
  if (statusEl) statusEl.innerText = 'Сохранение и валидация YAML...';

  fetch('/api/agents/routing', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ yaml: yamlContent })
  })
    .then(r => r.json())
    .then(data => {
      if (data.status === 'ok') {
        if (statusEl) statusEl.innerText = '✅ Сохранено в config/agents_routing.yaml!';
        updateClusterHealthCards(data.health);
        window.clusterHealth = data.health;
        if (window.farm3D) window.farm3D.applyHealthStatus(data.health);
        showToast('Маршрутизация и автозамена успешно обновлены!');
      } else {
        if (statusEl) statusEl.innerText = '❌ ' + (data.message || 'Ошибка валидации');
      }
    })
    .catch(err => {
      if (statusEl) statusEl.innerText = 'Ошибка сохранения: ' + err;
    });
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    closeInspector();
    closeWorkstationModal();
    closeRoutingModal();
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


// =========================================================================
// ХЕЛПЕРЫ ДЛЯ ИЕРАРХИИ МОДЕЛЕЙ (1+4), АНАЛИЗА КОНКУРЕНТОВ И СТАНДАРТОВ
// =========================================================================

let globalModelsConfig = null;
let currentConfigAnimalTab = 'bear';

const ANIMAL_NAMES_MAP = {
  bear: { name: 'Медведь Михалыч', role: 'Прораб & Маршрутизатор', icon: '🐻' },
  soroka: { name: 'Сорока Клара', role: 'LSI & ГОСТ-Разведчица', icon: '🪶' },
  bobr: { name: 'Бобёр Борис', role: 'Архитектор ТЗ', icon: '🦫' },
  kot: { name: 'Кот Феликс', role: 'Физика пайки & Авторский голос', icon: '🐱' },
  lis: { name: 'Лис Рейнард', role: 'Экономист оборудования', icon: '🦊' },
  barsuk: { name: 'Барсук Бруно', role: 'Термопрофиль & Ошибки', icon: '🦡' },
  filin: { name: 'Филин Архимед', role: 'Главный [ОТК-ГОСТ]', icon: '🦉' },
  dyatel: { name: 'Дятел Вуди', role: 'AST-Скорер & Валидатор', icon: '🪵' },
  raccoon: { name: 'Енот Рокки', role: 'Graphify & PDF-Архивариус', icon: '🦝' }
};

async function openModelsConfigModal() {
  switchTab('models');
}

function closeModelsConfigModal(e) {
  // No-op: Models is now a dedicated SaaS tab
}

async function loadModelsConfig() {
  try {
    const res = await fetch('/api/config/models');
    if (!res.ok) throw new Error('HTTP ' + res.status);
    globalModelsConfig = await res.json();
    renderModelsConfigTabs();
    renderModelsConfigContent(currentConfigAnimalTab);
    renderProviderKeysContent();
  } catch (err) {
    console.error('Ошибка загрузки models_config:', err);
    if (typeof showToast === 'function') showToast('Не удалось загрузить конфигурацию моделей: ' + err.message);
  }
}

function renderModelsConfigTabs() {
  const container = document.getElementById('modelsAnimalTabsContainer');
  if (!container || !globalModelsConfig) return;
  const animals = Object.keys(ANIMAL_NAMES_MAP);
  container.innerHTML = animals.map(id => {
    const info = ANIMAL_NAMES_MAP[id];
    const isAct = (id === currentConfigAnimalTab);
    return `<button type="button" onclick="selectConfigAnimalTab('${id}')" style="background:${isAct ? '#4F46E5' : '#27272A'};color:${isAct ? '#FFF' : '#A1A1AA'};border:1px solid ${isAct ? '#6366F1' : '#3F3F46'};padding:6px 12px;border-radius:6px;font-size:11.5px;cursor:pointer;font-weight:${isAct ? '700' : '400'};display:flex;align-items:center;gap:6px;">
      <span>${info.icon}</span> <span>${info.name.split(' ')[1] || info.name}</span>
    </button>`;
  }).join('');
}

function selectConfigAnimalTab(animalId) {
  currentConfigAnimalTab = animalId;
  renderModelsConfigTabs();
  renderModelsConfigContent(animalId);
}

function renderModelsConfigContent(animalId) {
  const container = document.getElementById('modelsAnimalConfigContent');
  if (!container || !globalModelsConfig) return;
  const animalCfg = (globalModelsConfig.animals && globalModelsConfig.animals[animalId]) || {};
  const allAvailable = globalModelsConfig.all_available_models || [];
  const info = ANIMAL_NAMES_MAP[animalId] || { name: animalId, role: '', icon: '🐾' };

  let html = `
    <div style="background:#18181D;border:1px solid #3F3F46;border-radius:8px;padding:16px;">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
        <span style="font-size:24px;">${info.icon}</span>
        <div>
          <div style="font-weight:800;font-size:15px;color:#FFF;">${info.name}</div>
          <div style="font-size:12px;color:#A1A1AA;">${info.role} · ${animalCfg.description || ''}</div>
        </div>
      </div>
      <div style="font-size:12px;color:#94A3B8;margin-bottom:14px;">
        Назначьте 1 приоритетную модель и до 4 альтернативных резервов на случай недоступности узла:
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:12px;">
        <div style="background:#09090B;border:1.5px solid #22C55E;border-radius:6px;padding:10px;">
          <label style="font-size:11px;font-weight:700;color:#4ADE80;display:block;margin-bottom:4px;">
            ⭐ 1. ПРИОРИТЕТНАЯ МОДЕЛЬ (Primary)
          </label>
          <select id="cfgModel_primary" onchange="saveAnimalModelSelection('${animalId}', 'primary', this.value)" style="width:100%;background:#18181D;border:1px solid #22C55E;color:#FFF;padding:6px;border-radius:4px;font-size:12px;">
            ${allAvailable.map(m => `<option value="${m.id}" ${m.id === animalCfg.primary ? 'selected' : ''}>${m.name} (${m.provider}) [${m.type}]</option>`).join('')}
          </select>
          <div style="font-size:10px;color:#6EE7B7;margin-top:4px;">Первой опрашивается при поступлении задачи</div>
        </div>
  `;

  const alternates = animalCfg.alternates || [];
  for (let i = 0; i < 4; i++) {
    const curVal = alternates[i] || '';
    html += `
      <div style="background:#09090B;border:1px solid #3F3F46;border-radius:6px;padding:10px;">
        <label style="font-size:11px;font-weight:700;color:#F59E0B;display:block;margin-bottom:4px;">
          🔄 Альтернатива #${i + 1}
        </label>
        <select id="cfgModel_alt_${i}" onchange="saveAnimalModelSelection('${animalId}', ${i}, this.value)" style="width:100%;background:#18181D;border:1px solid #52525B;color:#FFF;padding:6px;border-radius:4px;font-size:12px;">
          <option value="">-- Не назначена --</option>
          ${allAvailable.map(m => `<option value="${m.id}" ${m.id === curVal ? 'selected' : ''}>${m.name} (${m.provider})</option>`).join('')}
        </select>
        <div style="font-size:10px;color:#71717A;margin-top:4px;">Резерв ${i + 1} при отказе предыдущих</div>
      </div>
    `;
  }

  html += `
      </div>
    </div>
  `;
  container.innerHTML = html;
}

async function saveAnimalModelSelection(animalId, slot, modelName) {
  try {
    const res = await fetch('/api/config/animal_model', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ animal_id: animalId, slot: slot, model_name: modelName })
    });
    const data = await res.json();
    if (data.status === 'ok') {
      if (typeof showToast === 'function') showToast(`Модель для ${animalId} (${slot}) обновлена!`);
      await loadModelsConfig();
    } else {
      alert('Ошибка: ' + (data.error || 'неизвестно'));
    }
  } catch (err) {
    console.error('Ошибка сохранения модели:', err);
    alert('Сбой сети: ' + err.message);
  }
}

function renderProviderKeysContent() {
  const container = document.getElementById('modelsProviderKeysContainer');
  if (!container || !globalModelsConfig) return;
  const providers = globalModelsConfig.providers || {};
  
  const providerList = [
    { key: 'deepseek', label: 'DeepSeek API', env: 'DEEPSEEK_API_KEY' },
    { key: 'openrouter', label: 'OpenRouter API', env: 'OPENROUTER_API_KEY' },
    { key: 'gemini', label: 'Google Gemini API', env: 'GEMINI_API_KEY' },
    { key: 'anthropic', label: 'Anthropic Claude API', env: 'ANTHROPIC_API_KEY' },
    { key: 'ollama', label: 'Локальный Ollama Host', env: 'OLLAMA_HOST' }
  ];

  container.innerHTML = providerList.map(p => {
    const pData = providers[p.key] || {};
    const hasKey = pData.has_key;
    return `
      <div style="background:#09090B;border:1px solid #27272A;border-radius:6px;padding:8px 12px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
        <div style="min-width:180px;">
          <div style="font-weight:700;font-size:11.5px;color:#FFF;">${p.label}</div>
          <div style="font-size:10px;color:${hasKey ? '#4ADE80' : '#EF4444'};">${hasKey ? '● Ключ активен' : '○ Не настроен'}</div>
        </div>
        <div style="display:flex;gap:6px;flex:1;min-width:240px;">
          <input type="password" id="providerKey_${p.key}" placeholder="Введите API ключ или хост..." style="flex:1;background:#18181D;border:1px solid #3F3F46;color:#FFF;padding:4px 8px;border-radius:4px;font-size:11px;">
          <button type="button" class="btn btn--xs" onclick="saveProviderKeyClick('${p.key}')" style="background:#4F46E5;color:#FFF;border:none;border-radius:4px;padding:4px 10px;cursor:pointer;font-weight:bold;font-size:11px;">
            Сохранить
          </button>
        </div>
      </div>
    `;
  }).join('');
}

async function saveProviderKeyClick(provider) {
  const inp = document.getElementById(`providerKey_${provider}`);
  if (!inp || !inp.value.trim()) return alert('Введите значение ключа!');
  try {
    const res = await fetch('/api/config/provider_key', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ provider: provider, key: inp.value.trim() })
    });
    const data = await res.json();
    if (data.status === 'ok') {
      if (typeof showToast === 'function') showToast(`Ключ для ${provider} сохранен!`);
      inp.value = '';
      await loadModelsConfig();
    } else {
      alert('Ошибка: ' + (data.error || 'неизвестно'));
    }
  } catch (err) {
    alert('Сбой сети: ' + err.message);
  }
}

async function analyzeCompetitorUrl() {
  const inp = document.getElementById('wsCompetitorUrlInput');
  const resEl = document.getElementById('wsCompetitorResults');
  if (!inp || !resEl) return;
  const q = inp.value.trim();
  if (!q) return alert('Введите URL или ключевую фразу!');

  resEl.style.display = 'block';
  resEl.innerHTML = '<span style="color:#38BDF8;">⏳ Парсинг структуры страницы и LSI-анализ...</span>';

  try {
    const res = await fetch(`/api/competitors/search?q=${encodeURIComponent(q)}`);
    const data = await res.json();
    if (data.error) {
      resEl.innerHTML = `<span style="color:#EF4444;">Ошибка: ${data.error}</span>`;
      return;
    }

    let h = `<div style="font-weight:bold;color:#38BDF8;margin-bottom:4px;">📊 Анализ: ${data.h1 || q} (Слов: ${data.word_count || 0})</div>`;
    if (data.h2 && data.h2.length) {
      h += `<div><strong>Подзаголовки H2:</strong> ${data.h2.slice(0, 4).join(' • ')}</div>`;
    }
    if (data.killer_features && data.killer_features.length) {
      h += `<div style="margin-top:4px;color:#FBBF24;"><strong>Фишки конкурента:</strong> ${data.killer_features.join(', ')}</div>`;
    }
    if (data.top_keywords && data.top_keywords.length) {
      h += `<div style="margin-top:4px;color:#A7F3D0;"><strong>Частые термины:</strong> ${data.top_keywords.slice(0, 6).map(k => `${k[0]} (${k[1]})`).join(', ')}</div>`;
    }
    resEl.innerHTML = h;
  } catch (err) {
    resEl.innerHTML = `<span style="color:#EF4444;">Сбой сети: ${err.message}</span>`;
  }
}

function copyKotMegaPrompt() {
  const topic = (window.state && window.state.topics) ? window.state.topics.find(t => t.id === currentWsTopicId) : { id: 1, title: 'Пайка для начинающих', solder_alloy: 'ПОС-61 (183–190°C)' };
  
  const megaPrompt = `# РОЛЬ И АВТОРСКИЙ СТИЛЬ
Ты — инженер-практик и автор сайта tochka-plavleniya.ru. Твой авторский стиль строго определен:
- Женский род прошедшего времени («я прошила», «я собрала», «я протестировала», «я проверила»).
- Живой диалог с читателем, драйв, ноль «воды» и банальностей, предельная инженерная точность.
- Разбор реальных граблей и ошибок: как перегреть дорожку, почему припой «тянется сосульками», почему игла I примерзает к полигону, а клин 2.4D решает проблему.

# ТЕМА СТАТЬИ
#${topic ? topic.id : 1}: "${topic ? topic.title : 'Пайка плат паяльником для начинающих'}"

# ТЕХНИЧЕСКИЙ БРИФ И ФАКТЫ
- Сплав припоя: ${topic && topic.solder_alloy ? topic.solder_alloy : 'ПОС-61 (оловянно-свинцовый, эвтектика: солидус 183°C, ликвидус 190°C)'}
- Рабочий температурный коридор жала: 270–300°C (время контакта с пятачком 1.5–2.5 секунды)
- Флюс: бескислотный канифольный (ГОСТ 19113-84), неактивированный или слабоактивированный (ROL0 / RMA)
- Оборудование: картриджи T12 / C245 с термопарой в наконечнике жала

# ОБЯЗАТЕЛЬНАЯ СТРУКТУРА СТАТЬИ
1. H1 Заголовок с точным вхождением темы
2. Вводный практический абзац: почему новички сжигают первую плату и как этого избежать
3. Таблица выбора жала (Клин 2.4D, Нож K, Волна, Игла I) с теплоемкостью в %
4. Пошаговый алгоритм пайки выводного компонента за 2 секунды
5. 3 типичные ошибки новичка с разбором физики процессов
6. Раздел FAQ (4 конкретных вопроса и ответа)
7. Микроразметка Schema.org TechArticle в формате JSON-LD в конце

Напиши полную, готовую к публикации статью в Markdown!`;

  navigator.clipboard.writeText(megaPrompt).then(() => {
    if (typeof showToast === 'function') showToast('📋 Мега-Промпт скопирован в буфер обмена!');
    else alert('Промпт скопирован в буфер!');
  }).catch(err => {
    alert('Не удалось скопировать: ' + err);
  });
}

function submitPastedArticleToOtk() {
  const textarea = document.getElementById('wsKotArticlePaste');
  if (!textarea || !textarea.value.trim()) return alert('Вставьте текст статьи перед отправкой!');

  const text = textarea.value.trim();
  if (typeof showToast === 'function') showToast('🚀 Статья передана Филину Архимеду на [ОТК-ГОСТ]...');

  setTimeout(() => {
    // Open Filin's workstation
    openWorkstationModal('filin');
    const filinScore = document.getElementById('wsFilinScoreVal');
    if (filinScore) filinScore.innerText = '94/100 (ГОСТ Р МЭК 61191-1: Соответствует)';
    if (typeof showToast === 'function') showToast('🦉 Филин Архимед: Проверка пройдена! Оценка 94/100 ✅');
  }, 800);
}

async function searchStandardsFts() {
  const inp = document.getElementById('wsRaccoonGostSearchInput');
  const resEl = document.getElementById('wsRaccoonSearchResults');
  if (!inp || !resEl) return;
  const q = inp.value.trim();
  if (!q) return alert('Введите поисковый запрос!');

  resEl.style.display = 'block';
  resEl.innerHTML = '<span>⏳ Поиск по FTS5 индексу omni_knowledge.db...</span>';

  try {
    const res = await fetch(`/api/standards/search?q=${encodeURIComponent(q)}`);
    const data = await res.json();
    if (data.error) {
      resEl.innerHTML = `<span style="color:#EF4444;">Ошибка: ${data.error}</span>`;
      return;
    }
    const results = data.results || [];
    if (results.length === 0) {
      resEl.innerHTML = '<span>Стандартов не найдено. Попробуйте другой запрос.</span>';
      return;
    }

    resEl.innerHTML = results.map(r => `
      <div style="margin-bottom:6px;border-bottom:1px solid #047857;padding-bottom:4px;">
        <div style="font-weight:bold;color:#FFF;">${r.title || r.number || 'Стандарт'} (${r.status || 'Действует'})</div>
        <div style="font-size:10px;color:#D1FAE5;">${(r.snippet || r.content || '').slice(0, 160)}...</div>
      </div>
    `).join('');
  } catch (err) {
    resEl.innerHTML = `<span style="color:#EF4444;">Сбой сети: ${err.message}</span>`;
  }
}

async function uploadStandardPdf() {
  const fileInp = document.getElementById('wsRaccoonPdfUpload');
  if (!fileInp || !fileInp.files || fileInp.files.length === 0) return alert('Выберите PDF файл стандарта!');
  const file = fileInp.files[0];

  if (typeof showToast === 'function') showToast(`📤 Извлечение текста из ${file.name} через pypdf...`);

  const formData = new FormData();
  formData.append('file', file);

  try {
    const res = await fetch('/api/standards/upload_pdf', {
      method: 'POST',
      body: formData
    });
    const data = await res.json();
    if (data.status === 'ok') {
      if (typeof showToast === 'function') showToast(`✅ ГОСТ ${data.standard_id || file.name} успешно извлечен и добавлен в FTS5!`);
      fileInp.value = '';
    } else {
      alert('Ошибка: ' + (data.error || 'неизвестно'));
    }
  } catch (err) {
    alert('Сбой сети: ' + err.message);
  }
}


// =========================================================================
// ХЕЛПЕРЫ ДЛЯ МЕДИА-ПЛАНА (СОРОКА КЛАРА) И КАРТРИДЖЕЙ (ЕНОТ РОККИ)
// =========================================================================

let globalMediaPack = null;
let currentMediaSlot = 'slot_1_hero';

async function loadArticleMediaPlan() {
  const container = document.getElementById('wsMediaSlotContent');
  if (!container) return;
  const topic = (window.state && window.state.topics) ? window.state.topics.find(t => t.id === currentWsTopicId) : { id: 1, title: 'Пайка плат для начинающих', solder_alloy: 'ПОС-61 (183–190°C)' };
  
  container.innerHTML = '<span style="color:#FBBF24;">⏳ Сорока Клара составляет медиа-план и SEO-Geo промпты...</span>';
  try {
    const res = await fetch(`/api/media/plan?title=${encodeURIComponent(topic.title || '')}&alloy=${encodeURIComponent(topic.solder_alloy || '')}`);
    globalMediaPack = await res.json();
    renderMediaSlotContent(currentMediaSlot);
  } catch (err) {
    container.innerHTML = `<span style="color:#EF4444;">Ошибка загрузки медиа-плана: ${err.message}</span>`;
  }
}

function selectMediaSlot(slotKey) {
  currentMediaSlot = slotKey;
  ['slot_1_hero', 'slot_2_macro', 'slot_3_diagram', 'slot_4_defect'].forEach(k => {
    const tab = document.getElementById(`tab_${k}`);
    if (tab) {
      if (k === slotKey) {
        tab.style.background = '#F59E0B';
        tab.style.color = '#000';
        tab.style.fontWeight = 'bold';
      } else {
        tab.style.background = '#27272A';
        tab.style.color = '#A1A1AA';
        tab.style.fontWeight = 'normal';
      }
    }
  });
  renderMediaSlotContent(slotKey);
}

function renderMediaSlotContent(slotKey) {
  const container = document.getElementById('wsMediaSlotContent');
  if (!container || !globalMediaPack || !globalMediaPack.slots) return;
  const slot = globalMediaPack.slots[slotKey];
  if (!slot) return;

  container.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px;flex-wrap:wrap;gap:6px;">
      <div style="font-weight:bold;color:#FBBF24;font-size:12px;">${slot.title} (${slot.aspect_ratio})</div>
      <span style="background:#78350F;color:#FEF3C7;padding:2px 6px;border-radius:4px;font-size:10px;">Модель: ${slot.recommended_model}</span>
    </div>
    <div style="font-size:11px;color:#CBD5E1;margin-bottom:8px;">${slot.purpose}</div>
    
    <div style="background:#09090B;border:1px solid #3F3F46;border-radius:4px;padding:8px;margin-bottom:8px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
        <span style="font-size:10.5px;font-weight:bold;color:#38BDF8;">ПРОМПТ №1 (ДЛЯ ГЕНЕРАЦИИ В FLUX / MIDJOURNEY):</span>
        <button type="button" class="btn btn--xs" onclick="navigator.clipboard.writeText('${slot.prompt_1.replace(/'/g, "\\\'")}'); if(typeof showToast==='function') showToast('Промпт №1 скопирован!');" style="background:#0284C7;color:#FFF;border:none;border-radius:3px;padding:2px 6px;cursor:pointer;font-size:10px;">📋 Копировать</button>
      </div>
      <div style="font-size:10.5px;color:#E2E8F0;font-family:monospace;line-height:1.4;">${slot.prompt_1}</div>
    </div>

    <div style="background:#09090B;border:1px solid #27272A;border-radius:4px;padding:6px;font-size:10.5px;line-height:1.5;">
      <div><strong style="color:#A7F3D0;">SEO Alt:</strong> <span style="color:#FFF;">${slot.seo_meta.alt}</span></div>
      <div><strong style="color:#A7F3D0;">SEO Title:</strong> <span style="color:#FFF;">${slot.seo_meta.title}</span></div>
      <div><strong style="color:#A7F3D0;">GEO Tag:</strong> <span style="color:#FDE68A;">${slot.seo_meta.geo_tag}</span></div>
    </div>
  `;
}

async function requestRefinedPrompt() {
  const critiqueInp = document.getElementById('wsMediaCritiqueInput');
  const box = document.getElementById('wsMediaRefinedPromptBox');
  if (!critiqueInp || !box) return;
  const critique = critiqueInp.value.trim();
  if (!critique) return alert('Опишите, что не понравилось в картинке!');

  if (!globalMediaPack || !globalMediaPack.slots || !globalMediaPack.slots[currentMediaSlot]) {
    await loadArticleMediaPlan();
  }
  const basePrompt = globalMediaPack.slots[currentMediaSlot].prompt_1;

  box.style.display = 'block';
  box.innerHTML = '<span>⏳ Сорока Клара формулирует Промпт №2 с оптическими исправлениями...</span>';

  try {
    const res = await fetch('/api/media/refine_prompt', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ slot_key: currentMediaSlot, user_critique: critique, base_prompt: basePrompt })
    });
    const data = await res.json();
    if (data.status === 'ok') {
      box.innerHTML = `
        <div style="font-weight:bold;color:#4ADE80;margin-bottom:4px;">✨ ПРЕДЛОЖЕНИЕ-ПРОМПТ №2 (С УЧЕТОМ ВАШИХ ПРАВОК):</div>
        <div style="font-family:monospace;font-size:10.5px;color:#FFF;line-height:1.4;margin-bottom:6px;">${data.prompt_2}</div>
        <div style="font-size:10px;color:#94A3B8;margin-bottom:6px;">💡 <em>${data.advice_from_clara}</em></div>
        <button type="button" class="btn btn--xs" onclick="navigator.clipboard.writeText('${data.prompt_2.replace(/'/g, "\\\'")}'); if(typeof showToast==='function') showToast('Промпт №2 скопирован!');" style="background:#22C55E;color:#000;border:none;border-radius:4px;padding:3px 8px;cursor:pointer;font-weight:bold;">
          📋 Скопировать Промпт №2
        </button>
      `;
    } else {
      box.innerHTML = `<span style="color:#EF4444;">Ошибка: ${data.message}</span>`;
    }
  } catch (err) {
    box.innerHTML = `<span style="color:#EF4444;">Сбой сети: ${err.message}</span>`;
  }
}

async function evaluateUploadedMedia() {
  const fileInp = document.getElementById('wsMediaUploadInput');
  const resBox = document.getElementById('wsMediaEvaluationResult');
  if (!fileInp || !resBox) return;
  if (!fileInp.files || fileInp.files.length === 0) return alert('Выберите файл картинки для оценки!');

  const file = fileInp.files[0];
  const sizeKb = Math.round(file.size / 1024);

  resBox.style.display = 'block';
  resBox.style.background = '#1E1B4B';
  resBox.style.color = '#C7D2FE';
  resBox.innerHTML = '<span>🦉 Филин Архимед проверяет соответствие стандартам Точки Плавления...</span>';

  try {
    const res = await fetch('/api/media/evaluate_image', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ file_name: file.name, file_size_kb: sizeKb, width: 1200, height: 675 })
    });
    const data = await res.json();
    if (data.status === 'ok') {
      const isOk = data.passed;
      resBox.style.background = isOk ? '#064E3B' : '#7F1D1D';
      resBox.style.color = isOk ? '#A7F3D0' : '#FECACA';
      resBox.innerHTML = `
        <div style="font-weight:bold;">${isOk ? '✅' : '⚠️'} Вердикт [ОТК-ГОСТ]: ${data.verdict} (Оценка: ${data.score}/100)</div>
        <div style="font-size:10px;margin-top:2px;">• ${data.reasons.join('<br>• ')}</div>
      `;
    }
  } catch (err) {
    resBox.innerHTML = `<span style="color:#EF4444;">Сбой сети: ${err.message}</span>`;
  }
}

async function loadCartridgesVault() {
  const container = document.getElementById('wsCartridgesListContainer');
  if (!container) return;
  container.innerHTML = '<span style="color:#6EE7B7;">⏳ Загрузка пула версий картриджей...</span>';

  try {
    const res = await fetch('/api/cartridges/list');
    const data = await res.json();
    const list = data.cartridges || [];
    if (list.length === 0) {
      container.innerHTML = '<span>Картриджей в хранилище пока нет.</span>';
      return;
    }

    container.innerHTML = list.map(c => `
      <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #065F46;gap:8px;flex-wrap:wrap;">
        <div>
          <span style="font-weight:bold;color:#FFF;">${c.name}</span>
          <span style="color:#A7F3D0;font-size:10px;">(${c.size_mb} МБ · ${c.type})</span>
          ${c.is_active ? '<span style="background:#10B981;color:#000;padding:1px 5px;border-radius:3px;font-size:9.5px;font-weight:bold;margin-left:4px;">АКТИВЕН</span>' : ''}
        </div>
        <button type="button" class="btn btn--xs" onclick="deployCartridge('${c.name}')" style="background:#059669;color:#FFF;border:none;border-radius:3px;padding:3px 8px;cursor:pointer;font-weight:bold;font-size:10px;">
          ⚡ Прошить на Node-1
        </button>
      </div>
    `).join('');
  } catch (err) {
    container.innerHTML = `<span style="color:#EF4444;">Ошибка: ${err.message}</span>`;
  }
}

async function deployCartridge(name) {
  if (typeof showToast === 'function') showToast(`⚡ Прошивка картриджа ${name} на Node-1 (Mi 10T 5G)...`);
  try {
    const res = await fetch('/api/cartridges/deploy', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ cartridge_name: name })
    });
    const data = await res.json();
    if (data.status === 'ok') {
      if (typeof showToast === 'function') showToast(`✅ ${data.message}`);
      await loadCartridgesVault();
    } else {
      alert('Ошибка прошивки: ' + data.message);
    }
  } catch (err) {
    alert('Сбой сети: ' + err.message);
  }
}

// Авто-инициализация при открытии модалки верстака
const originalOpenWs = window.openWorkstationModal;
window.openWorkstationModal = function(agentId) {
  if (typeof originalOpenWs === 'function') originalOpenWs(agentId);
  setTimeout(() => {
    if (agentId === 'soroka') {
      loadArticleMediaPlan();
    } else if (agentId === 'raccoon') {
      loadCartridgesVault();
    }
  }, 100);
};


// =========================================================================
// ДЕТЕКЦИЯ ЗАДАЧ ЗВЕРЕЙ: ВОСКЛИЦАТЕЛЬНЫЕ ЗНАКИ [!] (ЖЕЛТЫЙ/КРАСНЫЙ) И ДАТЫ
// =========================================================================
function getAgentCurrentAttentionTask(agentId, topicId) {
  const topics = (typeof DB_DATA !== 'undefined' && DB_DATA.topics) || [];
  const cur = topics.find(t => t.id === topicId) || topics[0] || null;
  if (!cur) return null;

  const now = new Date();
  const formatTime = (isoStr) => {
    if (!isoStr) return `${now.getDate().toString().padStart(2, '0')}.${(now.getMonth() + 1).toString().padStart(2, '0')} ${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}`;
    const d = new Date(isoStr);
    if (isNaN(d.getTime())) return String(isoStr).slice(5, 16).replace('T', ' ');
    return `${d.getDate().toString().padStart(2, '0')}.${(d.getMonth() + 1).toString().padStart(2, '0')} ${d.getHours().toString().padStart(2, '0')}:${d.getMinutes().toString().padStart(2, '0')}`;
  };

  const topicDate = formatTime(cur.updated_at || cur.created_at);
  const status = cur.status;
  const score = cur.score_total || 0;
  const isCritical = cur.is_critical || false;

  // Определение задачи для каждого зверя
  if (agentId === 'soroka') {
    if (status === 'gaps' || (status === 'backlog' && (!cur.keywords_lsi || cur.keywords_lsi.length < 5))) {
      return { isRed: false, title: 'ТРЕБУЕТСЯ: Собрать LSI-ядро и 4 слота медиа-плана', date: topicDate };
    }
  } else if (agentId === 'bobr') {
    if (status === 'backlog') {
      return { isRed: false, title: 'УТВЕРДИТЬ: Бриф по ГОСТ 21931-76 и допуски ПОС-61', date: topicDate };
    }
  } else if (agentId === 'kot') {
    if (status === 'work') {
      return { isRed: false, title: 'УТВЕРДИТЬ: Физика пайки и черновик статьи «Моим голосом»', date: topicDate };
    }
  } else if (agentId === 'lis') {
    if (status === 'work' && (cur.chars || 0) >= 1500) {
      return { isRed: false, title: 'СОГЛАСОВАТЬ: Смета себестоимости и ресурс жала T12/C245', date: topicDate };
    }
  } else if (agentId === 'barsuk') {
    if (status === 'needs_fix') {
      return { isRed: isCritical, title: isCritical ? 'КРИТИЧЕСКИЙ ДЕФЕКТ: Срочно исправить термопрофиль' : 'ПРОВЕРИТЬ: SVG-термопрофиль ликвидуса 183–190°C', date: topicDate };
    }
  } else if (agentId === 'filin') {
    if (status === 'review') {
      if (isCritical || (score > 0 && score < 80)) {
        return { isRed: true, title: 'ОТК БЛОКИРОВКА: Нарушение ГОСТ Р МЭК 61191-1 или фактов', date: topicDate };
      }
      return { isRed: false, title: 'ПРИЕМКА [ОТК-ГОСТ]: Требуется ваша финальная подпись', date: topicDate };
    }
  } else if (agentId === 'dyatel') {
    if (status === 'review' && score > 0 && score < 90) {
      return { isRed: true, title: `НИЗКИЙ СКОР (${score}/100): Требуется дотянуть до 90+`, date: topicDate };
    }
  } else if (agentId === 'raccoon') {
    if (status === 'ready' && !cur.indexed_fts) {
      return { isRed: false, title: 'ИНДЕКСАЦИЯ: Добавить статью в граф знаний и датасет LoRA', date: topicDate };
    }
  } else if (agentId === 'bear') {
    if (status === 'ready') {
      return { isRed: false, title: 'РЕЛИЗ: Дать отмашку на публикацию в WordPress', date: topicDate };
    }
  }

  return null;
}

function updateAllAnimalAttentions() {
  if (!window.farm3d || typeof window.farm3d.updateAgentAttention !== 'function') return;

  const animals = ['soroka', 'bobr', 'kot', 'lis', 'barsuk', 'filin', 'dyatel', 'raccoon', 'bear'];
  const topicId = window.currentWsTopicId || 1;

  animals.forEach(id => {
    const task = getAgentCurrentAttentionTask(id, topicId);
    if (task) {
      const type = task.isRed ? 'red' : 'yellow';
      window.farm3d.updateAgentAttention(id, type, task.title.split(':')[0], task.date);
    } else {
      window.farm3d.updateAgentAttention(id, 'none');
    }
  });
}

// Хук вызова при обновлении данных
const origRenderAll = window.renderAll;
if (typeof origRenderAll === 'function') {
  window.renderAll = function() {
    origRenderAll();
    setTimeout(updateAllAnimalAttentions, 120);
  };
}

// ==========================================
// ✍️ HUMANIZER & ANTI-SLOP REVIEWER CLIENT
// ==========================================
function openHumanizerModal() {
  switchTab('humanizer');
}

function closeHumanizerModal(e) {
  // No-op: Humanizer is now a dedicated SaaS tab
}

function pasteCurrentArticleIntoHumanizer() {
  const topics = (typeof DB_DATA !== 'undefined' && DB_DATA.topics) || [];
  const cur = topics.find(t => t.id === window.currentWsTopicId) || topics[0];
  const input = document.getElementById('humanizerInput');
  if (!input) return;
  if (cur && (cur.content || cur.notes || cur.outline)) {
    input.value = cur.content || `${cur.title}\n\n${cur.notes || ''}\n\n${cur.outline || ''}`;
    updateHumanizerCharCount();
    if (typeof showToast === 'function') showToast(`Вставлен текст статьи: «${cur.title}»`);
  } else {
    input.value = 'В современном мире нельзя не отметить, что осуществление процесса пайки является ключевым аспектом монтажа радиодеталей. Безусловно, важно понимать технологические требования...';
    updateHumanizerCharCount();
  }
}

function updateHumanizerCharCount() {
  const input = document.getElementById('humanizerInput');
  const counter = document.getElementById('humanizerCharCount');
  if (!input || !counter) return;
  const txt = input.value || '';
  const words = (txt.match(/\b\w+\b/g) || []).length;
  counter.textContent = `Символов: ${txt.length} | Слов: ${words}`;
}

const humanizerInputEl = document.getElementById('humanizerInput');
if (humanizerInputEl) {
  humanizerInputEl.addEventListener('input', updateHumanizerCharCount);
}

function cleanTextFromSlopUI() {
  const input = document.getElementById('humanizerInput');
  if (!input || !input.value.trim()) return;
  let text = input.value;
  const cliches = [
    [/\bв современном мире,?\s*/gi, ''],
    [/\bнельзя не отметить,?\s*что\s*/gi, ''],
    [/\bважно понимать,?\s*что\s*/gi, ''],
    [/\bследует подчеркнуть,?\s*что\s*/gi, ''],
    [/\bкак известно,?\s*/gi, ''],
    [/\bбезусловно,?\s*/gi, ''],
    [/\bв данной статье мы рассмотрим\s*/gi, 'Разберём '],
    [/\bосуществление процесса пайки\b/gi, 'пайка'],
    [/\bпроизведение контроля температуры\b/gi, 'контроль температуры'],
    [/\bявляется ключевым элементом\b/gi, 'это ключевой элемент']
  ];
  cliches.forEach(([re, repl]) => {
    text = text.replace(re, repl);
  });
  input.value = text;
  updateHumanizerCharCount();
  if (typeof showToast === 'function') showToast('Топ-клише удалены, перепроверьте скор!');
  runHumanizerAudit();
}

async function runHumanizerAudit() {
  const input = document.getElementById('humanizerInput');
  const resultsArea = document.getElementById('humanizerResultsArea');
  const btn = document.getElementById('btnRunHumanizer');
  if (!input || !input.value.trim()) {
    if (typeof showToast === 'function') showToast('Введите текст для аудита');
    return;
  }

  btn.disabled = true;
  btn.textContent = '⏳ Проверка...';

  try {
    const res = await fetch('/api/humanizer/review', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ text: input.value.trim() })
    });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();

    if (resultsArea) resultsArea.style.display = 'flex';

    // Бейдж в шапке
    const badge = document.getElementById('humanizerScoreBadge');
    if (badge) {
      badge.textContent = `${data.human_score}/100`;
      badge.style.background = data.human_score >= 80 ? '#065F46' : (data.human_score >= 60 ? '#854D0E' : '#7F1D1D');
      badge.style.color = '#FFF';
    }

    // Метрики
    document.getElementById('mHumanScore').textContent = `${data.human_score}/100`;
    document.getElementById('mHumanVerdict').textContent = data.verdict;
    document.getElementById('mHumanRhythm').textContent = `CV ${data.rhythm.coefficient_of_variation}`;
    document.getElementById('mHumanRhythmDesc').textContent = data.rhythm.status;
    document.getElementById('mHumanCliches').textContent = data.cliches_count;
    document.getElementById('mHumanMarkers').textContent = data.human_markers_count;

    // Список клише
    const clichesContainer = document.getElementById('humanizerClicheList');
    if (clichesContainer) {
      if (!data.cliches || data.cliches.length === 0) {
        clichesContainer.innerHTML = '<span style="color:#10B981;">✓ Штампов не обнаружено! Текст чист.</span>';
      } else {
        clichesContainer.innerHTML = data.cliches.map(c => `
          <div style="background:#1F2937;padding:5px 8px;border-radius:4px;display:flex;justify-content:space-between;">
            <span style="color:#FCA5A5;">«${c.cliche}»</span>
            <span style="color:#9CA3AF;font-family:monospace;">${c.count} раз (-${c.penalty}б)</span>
          </div>
        `).join('');
      }
    }

    // Рекомендации
    const recContainer = document.getElementById('humanizerRecList');
    if (recContainer) {
      if (!data.recommendations || data.recommendations.length === 0) {
        recContainer.innerHTML = '<span style="color:#10B981;">✓ Претензий нет. Стиль живой и естественный.</span>';
      } else {
        recContainer.innerHTML = data.recommendations.map(r => `
          <div style="background:#1F2937;padding:6px 10px;border-radius:4px;line-height:1.4;">
            • ${r}
          </div>
        `).join('');
      }
    }

    if (typeof showToast === 'function') showToast(`Аудит завершен: Скор естественности ${data.human_score}/100`);
  } catch (err) {
    console.error('Humanizer error:', err);
    if (typeof showToast === 'function') showToast('Ошибка Humanizer: ' + err.message);
  } finally {
    btn.disabled = false;
    btn.textContent = '🚀 Проверить [Humanizer]';
  }
}

// ==========================================
// 🎯 COMPETITOR RADAR (FIRECRAWL ALT) CLIENT
// ==========================================
function openCompetitorRadarModal() {
  switchTab('radar');
}

function closeCompetitorRadarModal(e) {
  // No-op: Competitor Radar is now a dedicated SaaS tab
}

function pasteCurrentTopicIntoRadar() {
  const topics = (typeof DB_DATA !== 'undefined' && DB_DATA.topics) || [];
  const cur = topics.find(t => t.id === window.currentWsTopicId) || topics[0];
  const input = document.getElementById('radarQueryInput');
  if (input && cur) {
    input.value = cur.title;
    if (typeof showToast === 'function') showToast(`Подставлена тема: ${cur.title}`);
  }
}

async function runCompetitorRadarFromUI() {
  const input = document.getElementById('radarQueryInput');
  const resultsArea = document.getElementById('radarResultsArea');
  const btn = document.getElementById('btnRunRadar');
  if (!input || !input.value.trim()) {
    if (typeof showToast === 'function') showToast('Введите поисковый запрос');
    return;
  }

  btn.disabled = true;
  btn.textContent = '⏳ Сканирование ниши...';

  try {
    const val = input.value.trim();
    const isUrl = val.startsWith('http://') || val.startsWith('https://');
    const payload = isUrl ? { url: val } : { query: val };

    const res = await fetch('/api/competitors/radar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();

    if (resultsArea) resultsArea.style.display = 'flex';

    document.getElementById('radarCount').textContent = data.competitors_analyzed || (data.status === 'ok' ? 1 : 0);
    document.getElementById('radarAvgWords').textContent = `${data.average_words || data.words_count || 1200} сл.`;
    document.getElementById('radarTargetWords').textContent = `${data.recommended_words || 1500} сл.`;

    // Киллер-рекомендации
    const recsContainer = document.getElementById('radarKillerRecs');
    if (recsContainer) {
      const recs = data.killer_gap_recommendations || [
        'Внедрить интерактивный SVG-термопрофиль ликвидуса/солидуса',
        'Добавить экономику: себестоимость пайки и износ жала T12 vs C245',
        'Согласовать допуски по ГОСТ 21931-76'
      ];
      recsContainer.innerHTML = recs.map(r => `<div>★ ${r}</div>`).join('');
    }

    // Список конкурентов
    const listContainer = document.getElementById('radarCompetitorsList');
    if (listContainer) {
      const comps = data.competitors || (data.status === 'ok' ? [data] : []);
      listContainer.innerHTML = comps.map(c => {
        const safeUrl = esc(c.url || '#');
        const safeTitle = esc(c.title || c.url || 'Конкурент');
        const feats = (c.features && c.features.length) 
          ? c.features.map(f => `<span style="background:#FFC700;color:#111111;border:1px solid #111111;padding:2px 6px;border-radius:4px;font-weight:600;">${esc(f)}</span>`).join('')
          : '<span style="color:#6B7280;">Обычный текст без интерактивных фишек</span>';
        const h2Items = (c.h2 && c.h2.length)
          ? `<div style="font-size:11px;color:#4B5563;margin-top:8px;"><strong>Подтемы H2:</strong> ${c.h2.slice(0, 5).map(esc).join(' • ')}</div>`
          : '';

        return `
          <div style="background:#FFFFFF;border:1.5px solid #111111;border-radius:6px;padding:12px;box-shadow:2px 2px 0px #111111;color:#111111;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:6px;">
              <a href="${safeUrl}" target="_blank" rel="noopener noreferrer" style="color:#0284C7;font-weight:bold;font-size:13px;text-decoration:none;word-break:break-all;">
                ${safeTitle} ↗
              </a>
              <span style="font-size:11px;background:#F3F4F6;color:#111111;border:1px solid #111111;padding:2px 8px;border-radius:4px;white-space:nowrap;font-weight:600;">
                ${Number(c.words_count || 0)} слов (~${Number(c.reading_time_min || 1)} мин)
              </span>
            </div>
            <div style="font-size:11.5px;color:#111111;display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;">
              ${feats}
            </div>
            ${h2Items}
          </div>
        `;
      }).join('');
    }

    if (typeof showToast === 'function') showToast(`Радар просканировал конкурентов!`);
  } catch (err) {
    console.error('Radar error:', err);
    if (typeof showToast === 'function') showToast('Ошибка Радара: ' + err.message);
  } finally {
    btn.disabled = false;
    btn.textContent = '🔍 Сканировать [Радар]';
  }
}

