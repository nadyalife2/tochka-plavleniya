/**
 * solder-table.js — Живой поиск и сортировка по таблице припоев
 * Единый скрипт для автономного стенда (site2) и темы WordPress (tchp)
 */
document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('solder-search');
  const table = document.getElementById('solder-table');

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

  // COLUMN SORTING
  if (headers && headers.length > 0 && tbody) {
    let currentSortCol = -1;
    let sortAsc = true;

    headers.forEach((th, index) => {
      th.classList.add('cursor-pointer', 'select-none', 'hover:text-accent');
      th.title = 'Нажмите для сортировки';

      th.addEventListener('click', () => {
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

        headers.forEach(h => {
          h.textContent = h.textContent.replace(' ▲', '').replace(' ▼', '');
        });
        th.textContent += sortAsc ? ' ▲' : ' ▼';
      });
    });
  }
});
