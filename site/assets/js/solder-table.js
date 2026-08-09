/**
 * solder-table.js — Интерактивная таблица припоев (сортировка + поиск)
 * Точка Плавления
 */
(function () {
  const SOLDERS = [
    { alloy: 'Sn63/Pb37',         melt: 183,  type: 'Свинцовый',     use: 'Универсальная пайка THT/SMD', rohs: false },
    { alloy: 'SAC305 (Sn-Ag-Cu)', melt: 217,  type: 'Бессвинцовый',  use: 'Промышленный рефлоу, SMD',    rohs: true  },
    { alloy: 'ПОС-61',            melt: 190,  type: 'Свинцовый',     use: 'Общая пайка, ремонт',         rohs: false },
    { alloy: 'Сплав Розе',        melt: 94,   type: 'Легкоплавкий',  use: 'Демонтаж BGA, удаление чипов', rohs: false },
    { alloy: 'Sn42/Bi58',         melt: 138,  type: 'Бессвинцовый',  use: 'Пайка теплочувствительных компонентов', rohs: true },
    { alloy: 'ПОС-40',            melt: 235,  type: 'Свинцовый',     use: 'Монтаж мощных компонентов',   rohs: false },
    { alloy: 'In52/Sn48',         melt: 118,  type: 'Индиевый',      use: 'Криогенная пайка, оптика',    rohs: true  },
    { alloy: 'SAC405 (Sn-Ag-Cu)', melt: 219,  type: 'Бессвинцовый',  use: 'BGA-шарики, высокая надёжность', rohs: true },
    { alloy: 'Sn99.3/Cu0.7',      melt: 227,  type: 'Бессвинцовый',  use: 'Волновая пайка, дешёвая замена SAC', rohs: true },
    { alloy: 'ПОС-90',            melt: 220,  type: 'Свинцовый',     use: 'Пайка пищевых трубопроводов', rohs: false },
  ];

  const tableBody = document.getElementById('solder-tbody');
  const searchEl  = document.getElementById('solder-search');
  const headers   = document.querySelectorAll('.solder-table th[data-sort]');

  if (!tableBody) return;

  let currentSort = { col: null, dir: 'asc' };
  let currentSearch = '';

  function renderTable(data) {
    tableBody.innerHTML = '';
    if (!data.length) {
      tableBody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:24px;font-family:var(--font-mono);">Ничего не найдено</td></tr>';
      return;
    }
    data.forEach(row => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td class="alloy">${row.alloy}</td>
        <td class="temp">${row.melt}°C</td>
        <td>${row.type}</td>
        <td>${row.use}</td>
        <td style="text-align:center">${row.rohs ? '<span style="color:#22c55e;font-weight:700;font-family:var(--font-mono);">✓</span>' : '<span style="color:#aaa;font-family:var(--font-mono);">—</span>'}</td>
      `;
      tableBody.appendChild(tr);
    });
  }

  function getFiltered() {
    let data = [...SOLDERS];
    if (currentSearch) {
      const s = currentSearch.toLowerCase();
      data = data.filter(r =>
        r.alloy.toLowerCase().includes(s) ||
        r.type.toLowerCase().includes(s) ||
        r.use.toLowerCase().includes(s)
      );
    }
    if (currentSort.col !== null) {
      data.sort((a, b) => {
        const col = currentSort.col;
        const va = a[col];
        const vb = b[col];
        let cmp = 0;
        if (typeof va === 'number') cmp = va - vb;
        else cmp = String(va).localeCompare(String(vb), 'ru');
        return currentSort.dir === 'asc' ? cmp : -cmp;
      });
    }
    return data;
  }

  function update() { renderTable(getFiltered()); }

  // Search
  if (searchEl) {
    searchEl.addEventListener('input', () => {
      currentSearch = searchEl.value;
      update();
    });
  }

  // Sort
  const COL_KEYS = { 'alloy': 'alloy', 'melt': 'melt', 'type': 'type', 'use': 'use', 'rohs': 'rohs' };
  headers.forEach(th => {
    th.addEventListener('click', () => {
      const col = th.getAttribute('data-sort');
      if (currentSort.col === col) {
        currentSort.dir = currentSort.dir === 'asc' ? 'desc' : 'asc';
      } else {
        currentSort.col = col;
        currentSort.dir = 'asc';
      }
      headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
      th.classList.add(currentSort.dir === 'asc' ? 'sort-asc' : 'sort-desc');
      update();
    });
  });

  update();
})();
