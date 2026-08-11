/**
 * solder-table.js — Живой поиск и сортировка по таблице припоев
 */
document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('solder-search');
  const table = document.getElementById('solder-table');

  if (!table) return;

  const tbody = table.querySelector('tbody');
  const headers = table.querySelectorAll('th');

  // LIVE SEARCH
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      const term = e.target.value.toLowerCase().trim();
      const rows = tbody.querySelectorAll('tr');

      rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(term)) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    });
  }

  // COLUMN SORTING
  let currentSortCol = -1;
  let sortAsc = true;

  headers.forEach((th, index) => {
    th.addEventListener('click', () => {
      const rows = Array.from(tbody.querySelectorAll('tr'));

      if (currentSortCol === index) {
        sortAsc = !sortAsc;
      } else {
        currentSortCol = index;
        sortAsc = true;
      }

      rows.sort((a, b) => {
        const valA = a.children[index].textContent.trim();
        const valB = b.children[index].textContent.trim();

        // Check if numeric
        const numA = parseFloat(valA);
        const numB = parseFloat(valB);

        if (!isNaN(numA) && !isNaN(numB)) {
          return sortAsc ? numA - numB : numB - numA;
        }

        return sortAsc ? valA.localeCompare(valB, 'ru') : valB.localeCompare(valA, 'ru');
      });

      rows.forEach(row => tbody.appendChild(row));

      headers.forEach(h => h.textContent = h.textContent.replace(' ▲', '').replace(' ▼', ''));
      th.textContent += sortAsc ? ' ▲' : ' ▼';
    });
  });
});
