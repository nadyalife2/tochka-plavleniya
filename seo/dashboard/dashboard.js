let DB_DATA = { status: "ok", topics: [], topics_proposed: [], competitors: [], competitors_proposed: [], clusters: [], backups: [] };
let CURRENT_TOPIC_ID = null;
let ACTIVE_STEP_FILTER = null;
let CURRENT_AI_PROVIDER = 'auto';

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
    auto: '⚡ Авто (каскад Claude → DeepSeek → Yandex)',
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
  refreshMutagenBalance();
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
      const r = await fetch(`http://127.0.0.1:8765/api/jobs/${jobId}`);
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
      showToast('✓ Статус статьи #' + id + ': ' + (RU[newStatus] || newStatus));
      await refreshData();
    } else {
      showToast('⚠️ Не удалось изменить статус');
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

  document.getElementById('inspectorModal').classList.add('active');
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
    const res = await fetch('http://127.0.0.1:8765/api/standards');
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
    const res = await fetch(`http://127.0.0.1:8765/api/topics/links?id=${topicId}`);
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
