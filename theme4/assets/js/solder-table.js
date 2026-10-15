/**
 * solder-table.js — Живой поиск и доступная сортировка по таблице припоев
 * Стандарты доступности: WCAG 2.1 AA / WAI-ARIA 1.2
 *   - Поддержка навигации с клавиатуры (tabindex="0", Enter, Space)
 *   - Динамические атрибуты aria-sort ("ascending" / "descending" / "none")
 *   - Поиск в реальном времени с фильтрацией строк
 */

document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('solder-search');
  const table = document.getElementById('solder-table-grid') || document.getElementById('solder-table');

  if (!table) return;

  const tbody = table.querySelector('tbody') || document.getElementById('solder-tbody');
  const headers = table.querySelectorAll('th');

  // LIVE SEARCH & RESET WITH HARDENED EMPTY STATE & ARIA-LIVE
  const resetBtn = document.getElementById('solder-reset-btn');
  let liveRegion = document.getElementById('solder-table-live');
  if (!liveRegion && table) {
    liveRegion = document.createElement('div');
    liveRegion.id = 'solder-table-live';
    liveRegion.setAttribute('aria-live', 'polite');
    liveRegion.setAttribute('aria-atomic', 'true');
    liveRegion.className = 'sr-only';
    table.parentNode.insertBefore(liveRegion, table);
  }

  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, (m) => {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
    });
  }

  if (searchInput && tbody) {
    let filterRaf = null;

    searchInput.addEventListener('input', (e) => {
      const term = e.target.value.toLowerCase().trim();
      if (resetBtn) {
        resetBtn.classList.toggle('hidden', term.length === 0);
      }

      if (filterRaf) cancelAnimationFrame(filterRaf);
      filterRaf = requestAnimationFrame(() => {
        const rows = tbody.querySelectorAll('tr:not(.solder-empty-row)');
        let visibleCount = 0;

        rows.forEach(row => {
          const text = row.textContent.toLowerCase();
          const matches = term === '' || text.includes(term);
          row.style.display = matches ? '' : 'none';
          if (matches) visibleCount++;
        });

        // Empty state handling
        let emptyRow = tbody.querySelector('.solder-empty-row');
        if (visibleCount === 0 && term.length > 0) {
          if (!emptyRow) {
            emptyRow = document.createElement('tr');
            emptyRow.className = 'solder-empty-row';
            const colCount = headers.length || 6;
            emptyRow.innerHTML = `
              <td colspan="${colCount}" class="text-center py-8 text-ink-muted font-mono text-xs">
                <div class="flex flex-col items-center justify-center gap-1.5 max-w-md mx-auto text-center px-4">
                  <span class="font-bold text-ink empty-msg">В реестре ГОСТ и IPC не найден сплав «${escapeHtml(term)}»</span>
                  <span class="text-ink-faint text-[11px] leading-relaxed">Попробуйте ввести марку (ПОС-61, ПОС-40), бессвинцовый аналог (SAC305, Sn99Cu1) или температуру плавления (183 °C, 217 °C).</span>
                  <button type="button" class="solder-empty-reset text-accent underline hover:text-accent-hover cursor-pointer font-bold mt-1 text-xs">
                    Сбросить фильтр поиска
                  </button>
                </div>
              </td>
            `;
            tbody.appendChild(emptyRow);
            const btn = emptyRow.querySelector('.solder-empty-reset');
            if (btn) {
              btn.addEventListener('click', () => {
                searchInput.value = '';
                searchInput.dispatchEvent(new Event('input'));
                searchInput.focus();
              });
            }
          } else {
            emptyRow.style.display = '';
            const msg = emptyRow.querySelector('.empty-msg');
            if (msg) msg.textContent = `В реестре ГОСТ и IPC не найден сплав «${term}»`;
          }
          if (liveRegion) liveRegion.textContent = `В реестре ГОСТ и IPC по запросу ${term} сплавов не найдено`;
        } else if (emptyRow) {
          emptyRow.style.display = 'none';
          if (liveRegion && term.length > 0) {
            liveRegion.textContent = `Найдено сплавов: ${visibleCount}`;
          }
        }
      });
    });

    if (resetBtn) {
      resetBtn.addEventListener('click', () => {
        searchInput.value = '';
        searchInput.dispatchEvent(new Event('input'));
        searchInput.focus();
      });
    }
  }


  // ACCESSIBLE COLUMN SORTING
  if (headers && headers.length > 0 && tbody) {
    let currentSortCol = -1;
    let sortAsc = true;

    headers.forEach((th, index) => {
      th.classList.add('cursor-pointer', 'select-none', 'hover:text-accent', 'transition-colors');
      th.setAttribute('tabindex', '0');
      th.setAttribute('role', 'columnheader');
      th.setAttribute('aria-sort', 'none');
      th.title = 'Нажмите Enter или пробел для сортировки';

      // Save initial clean label text
      const baseText = th.textContent.trim().replace(/[▲▼]/g, '').trim();

      function triggerSort() {
        const rows = Array.from(tbody.querySelectorAll('tr'));
        if (rows.length === 0) return;

        if (currentSortCol === index) {
          sortAsc = !sortAsc;
        } else {
          currentSortCol = index;
          sortAsc = true;
        }

        rows.sort((a, b) => {
          const cellA = a.children[index];
          const cellB = b.children[index];
          if (!cellA || !cellB) return 0;

          const valA = cellA.textContent.trim();
          const valB = cellB.textContent.trim();

          const numA = parseFloat(valA.replace(',', '.').replace(/[^0-9.-]/g, ''));
          const numB = parseFloat(valB.replace(',', '.').replace(/[^0-9.-]/g, ''));

          if (!isNaN(numA) && !isNaN(numB) && !valA.includes('%') && (valA.includes('°C') || !isNaN(parseFloat(valA)))) {
            return sortAsc ? numA - numB : numB - numA;
          }

          return sortAsc ? valA.localeCompare(valB, 'ru') : valB.localeCompare(valA, 'ru');
        });

        rows.forEach(row => tbody.appendChild(row));

        // Update aria-sort and visual indicator
        headers.forEach((h, hIdx) => {
          h.setAttribute('aria-sort', 'none');
          const hClean = h.getAttribute('data-base-label') || h.textContent.trim().replace(/[▲▼]/g, '').trim();
          h.setAttribute('data-base-label', hClean);
          h.textContent = hClean;
        });

        th.setAttribute('aria-sort', sortAsc ? 'ascending' : 'descending');
        th.textContent = `${baseText} ${sortAsc ? '▲' : '▼'}`;
      }

      th.addEventListener('click', triggerSort);

      th.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          triggerSort();
        }
      });
    });
  }
});
