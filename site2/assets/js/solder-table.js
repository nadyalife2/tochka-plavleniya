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

  // LIVE SEARCH
  if (searchInput && tbody) {
    searchInput.addEventListener('input', (e) => {
      const term = e.target.value.toLowerCase().trim();
      const rows = tbody.querySelectorAll('tr');

      rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(term) ? '' : 'none';
      });
    });
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
