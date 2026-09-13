<?php
/**
 * search-modal.php — Инженерный поиск по базе знаний ТЧП (Ctrl+K)
 * Мгновенная фильтрация по сплавам, флюсам, дефектам, инструментам и стандартам.
 */
?>
<!-- Global Search Modal Container -->
<div id="search-modal" role="dialog" aria-modal="true" aria-labelledby="global-search-input">
  <div id="search-modal-box">
    
    <!-- Search Header / Input Bar -->
    <div class="flex items-center px-4 py-3.5 border-b border-paper-border bg-paper/50 dark:bg-paper/10">
      <span class="material-symbols-outlined text-accent text-2xl mr-3 select-none">search</span>
      <input type="text" id="global-search-input" 
             class="w-full bg-transparent text-ink placeholder:text-ink-faint text-base sm:text-lg font-sans focus:outline-none" 
             placeholder="Поиск: SAC305, RMA-218, надгробия, T12, IPC..." 
             autocomplete="off" 
             spellcheck="false">
      <div class="flex items-center gap-1.5 ml-2">
        <kbd class="hidden sm:inline-block text-[11px] font-mono px-2 py-0.5 bg-paper-border/50 text-ink-muted rounded border border-paper-border select-none">ESC</kbd>
        <button type="button" id="search-modal-close" class="p-1 text-ink-muted hover:text-ink transition-colors min-w-[36px] min-h-[36px] flex items-center justify-center rounded" aria-label="Закрыть поиск">
          <span class="material-symbols-outlined text-xl">close</span>
        </button>
      </div>
    </div>

    <!-- Filter Category Badges -->
    <div class="flex items-center gap-1.5 px-4 py-2 border-b border-paper-border/70 overflow-x-auto text-xs font-mono scrollbar-none bg-paper/30">
      <span class="text-ink-faint text-[11px] uppercase mr-1 select-none">Раздел:</span>
      <button type="button" data-filter="all" class="search-category-pill active px-2.5 py-1 rounded bg-ink text-paper font-semibold transition-all">Все</button>
      <button type="button" data-filter="сплавы" class="search-category-pill px-2.5 py-1 rounded border border-paper-border text-ink-muted hover:text-ink transition-all">Сплавы</button>
      <button type="button" data-filter="флюсы" class="search-category-pill px-2.5 py-1 rounded border border-paper-border text-ink-muted hover:text-ink transition-all">Флюсы</button>
      <button type="button" data-filter="дефекты" class="search-category-pill px-2.5 py-1 rounded border border-paper-border text-ink-muted hover:text-ink transition-all">Дефекты</button>
      <button type="button" data-filter="инструменты" class="search-category-pill px-2.5 py-1 rounded border border-paper-border text-ink-muted hover:text-ink transition-all">Инструменты</button>
      <button type="button" data-filter="стандарты" class="search-category-pill px-2.5 py-1 rounded border border-paper-border text-ink-muted hover:text-ink transition-all">Стандарты</button>
    </div>

    <!-- Live Search Results List -->
    <div id="search-results-container" class="max-h-[60vh] sm:max-h-[420px] overflow-y-auto p-2 space-y-1 divide-y divide-paper-border/30">
      <!-- Injected by JavaScript -->
    </div>

    <!-- Search Footer Shortcut Tips -->
    <div class="px-4 py-2.5 bg-paper/80 dark:bg-card border-t border-paper-border flex items-center justify-between text-[11px] font-mono text-ink-muted select-none">
      <div class="flex items-center gap-3">
        <span><kbd class="px-1.5 py-0.5 bg-paper-border/60 rounded border border-paper-border text-ink">↑</kbd> <kbd class="px-1.5 py-0.5 bg-paper-border/60 rounded border border-paper-border text-ink">↓</kbd> Навигация</span>
        <span><kbd class="px-1.5 py-0.5 bg-paper-border/60 rounded border border-paper-border text-ink">Enter</kbd> Выбрать</span>
      </div>
      <span class="text-accent font-semibold flex items-center gap-1">
        <span class="w-1.5 h-1.5 rounded-full bg-accent animate-pulse"></span> ТЧП // Инженерная база
      </span>
    </div>

  </div>
</div>

<script>
(function() {
  const KNOWLEDGE_BASE = [
    // Сплавы и припои
    {
      title: "ПОС-61 (Sn63Pb37)",
      category: "сплавы",
      badge: "183 °C // Эвтектика",
      desc: "Классический оловянно-свинцовый эвтектический припой. Мгновенный переход из жидкого в твердое состояние без пластической фазы.",
      url: "/interactive.php#calculator",
      badgeColor: "bg-amber-100 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border-amber-300"
    },
    {
      title: "SAC305 (Sn96.5Ag3.0Cu0.5)",
      category: "сплавы",
      badge: "217–221 °C // Lead-free",
      desc: "Основной бессвинцовый промышленный стандарт. Требует точного соблюдения пика reflow и защиты от деградации текстолита.",
      url: "/article.php#simulator",
      badgeColor: "bg-sky-100 dark:bg-sky-950/40 text-sky-800 dark:text-sky-300 border-sky-300"
    },
    {
      title: "Сплав Розе (Bi50Pb32Sn18)",
      category: "сплавы",
      badge: "94 °C // Демонтажный",
      desc: "Низкотемпературный сплав для безопасного демонтажа разъемов и экранов без перегрева текстолита. Хрупок, не предназначен для силовой пайки.",
      url: "/category.php?slug=materialy",
      badgeColor: "bg-purple-100 dark:bg-purple-950/40 text-purple-800 dark:text-purple-300 border-purple-300"
    },
    {
      title: "Сплав Вуда (Bi50Pb25Sn12.5Cd12.5)",
      category: "сплавы",
      badge: "68 °C // Токсичен (кадмий)",
      desc: "Сверхнизкотемпературный сплав. Содержит кадмий — требует вытяжки и не применяется для пищевой или бытовой электроники.",
      url: "/category.php?slug=materialy",
      badgeColor: "bg-rose-100 dark:bg-rose-950/40 text-rose-800 dark:text-rose-300 border-rose-300"
    },
    {
      title: "ПОС-40 (Sn40Pb60)",
      category: "сплавы",
      badge: "183–238 °C // Интервал 55°C",
      desc: "Неэвтектический припой с широким пастообразным состоянием. Подходит для пайки проводов и радиаторов, но противопоказан для прецизионного SMD.",
      url: "/category.php?slug=materialy",
      badgeColor: "bg-amber-100 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border-amber-300"
    },
    // Флюсы
    {
      title: "RMA-218 (Cyberflux / Amtech)",
      category: "флюсы",
      badge: "ROL0 / Безотмывочный",
      desc: "Слабоактивированный канифольный гелевый флюс. Идеален для BGA, реболлинга и SMD микромонтажа. Не кипит и не разбрасывает шары при 240°C.",
      url: "/article.php#materials-toolkit",
      badgeColor: "bg-emerald-100 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border-emerald-300"
    },
    {
      title: "NC-559-ASM (No-Clean)",
      category: "флюсы",
      badge: "ROL0 // Чистый остаток",
      desc: "Универсальный безотмывочный флюс средней вязкости с высоким поверхностным натяжением для центровки BGA чипов.",
      url: "/category.php?slug=materialy",
      badgeColor: "bg-emerald-100 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border-emerald-300"
    },
    {
      title: "Канифоль сосновая (ГОСТ 19113-84)",
      category: "флюсы",
      badge: "R0 // Нейтральный",
      desc: "Базовый флюс класса R. Диэлектрик при комнатной температуре. Требует очистки изопропанолом из-за хрупкости остатков и сбора пыли.",
      url: "/category.php?slug=materialy",
      badgeColor: "bg-amber-100 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border-amber-300"
    },
    {
      title: "ЛТИ-120 (Спиртоканифольный активированный)",
      category: "флюсы",
      badge: "ROM1 // Смывка обязательна",
      desc: "Содержит активаторы (триэтаноламин). Отлично лудит медь и сталь, но остатки гидрофильны — обязательна тщательная промывка плат.",
      url: "/category.php?slug=materialy",
      badgeColor: "bg-red-100 dark:bg-red-950/40 text-red-800 dark:text-red-300 border-red-300"
    },
    // Дефекты пайки
    {
      title: "Мостики припоя (Solder Bridging)",
      category: "дефекты",
      badge: "Короткое замыкание",
      desc: "Замыкание между соседними выводами QFP/SOIC. Причины: избыток пасты, низкая активность флюса или перекос трафарета. Устраняется медной оплеткой.",
      url: "/category.php?slug=oshibki",
      badgeColor: "bg-rose-100 dark:bg-rose-950/40 text-rose-800 dark:text-rose-300 border-rose-300"
    },
    {
      title: "Эффект надгробия / Манхэттена (Tombstoning)",
      category: "дефекты",
      badge: "Обрыв SMD 0402/0603",
      desc: "Подъем пассивного компонента вертикально на одной контактной площадке из-за разницы скоростей расплавления припоя на противоположных концах.",
      url: "/category.php?slug=oshibki",
      badgeColor: "bg-orange-100 dark:bg-orange-950/40 text-orange-800 dark:text-orange-300 border-orange-300"
    },
    {
      title: "Холодная пайка (Cold Solder Joint)",
      category: "дефекты",
      badge: "IPC-A-610 Дефект",
      desc: "Зернистая тусклая поверхность галтели, отсутствие интерметаллического слоя. Возникает из-за недостаточного прогрева или сдвига детали при остывании.",
      url: "/category.php?slug=oshibki",
      badgeColor: "bg-rose-100 dark:bg-rose-950/40 text-rose-800 dark:text-rose-300 border-rose-300"
    },
    {
      title: "Пористость BGA (Voiding)",
      category: "дефекты",
      badge: "IPC-7095 Class 3",
      desc: "Газовые пустоты в шариковых выводах свыше 25% площади сечения. Вызваны недостаточным временем фазы Soak (выпаривания растворителей флюса).",
      url: "/article.php#simulator",
      badgeColor: "bg-amber-100 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border-amber-300"
    },
    {
      title: "Деламинация и коробление текстолита (Warpage)",
      category: "дефекты",
      badge: "Критический перегрев",
      desc: "Расслоение слоев FR-4 при превышении температуры стеклования Tg (130–170°C) без предварительного нижнего подогрева.",
      url: "/article.php",
      badgeColor: "bg-rose-100 dark:bg-rose-950/40 text-rose-800 dark:text-rose-300 border-rose-300"
    },
    // Оборудование и инструменты
    {
      title: "Quick 861DW (ESD Lead-Free 1000W)",
      category: "инструменты",
      badge: "Турбинный термофен",
      desc: "Эталонная термовоздушная станция с прямым потоком воздуха до 120 л/мин и керамическим нагревателем. Быстрый выход на режим за 3 секунды.",
      url: "/article.php#materials-toolkit",
      badgeColor: "bg-sky-100 dark:bg-sky-950/40 text-sky-800 dark:text-sky-300 border-sky-300"
    },
    {
      title: "Картриджная система T12 vs JBC C245",
      category: "инструменты",
      badge: "Сравнение нагревателей",
      desc: "Картриджи со встроенной термопарой и нагревателем. JBC передает до 130 Вт в пятно контакта за счет моментального ПИД-отклика.",
      url: "/category.php?slug=start",
      badgeColor: "bg-emerald-100 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border-emerald-300"
    },
    {
      title: "Инфракрасный нижний подогрев плат",
      category: "инструменты",
      badge: "Обязательно для BGA",
      desc: "Снижает температурный градиент между верхней и нижней стороной платы, предотвращая коробление и тепловой удар по микросхемам.",
      url: "/category.php?slug=praktika",
      badgeColor: "bg-purple-100 dark:bg-purple-950/40 text-purple-800 dark:text-purple-300 border-purple-300"
    },
    // Стандарты и регламенты
    {
      title: "IPC-A-610H (Критерии качества пайки)",
      category: "стандарты",
      badge: "Международный стандарт",
      desc: "Определяет требования к галтелям припоя, смачиваемости выводов и дефектам для изделий Класса 1, 2 и 3 (High Reliability).",
      url: "/article.php",
      badgeColor: "bg-blue-100 dark:bg-blue-950/40 text-blue-800 dark:text-blue-300 border-blue-300"
    },
    {
      title: "J-STD-020E (Классификация влагочувствительности MSL)",
      category: "стандарты",
      badge: "Reflow термопрофили",
      desc: "Регламентирует максимальные температуры пика (260°C) и времена выдержки для предотвращения эффекта попкорна в корпусах микросхем.",
      url: "/article.php#thermal-zones",
      badgeColor: "bg-blue-100 dark:bg-blue-950/40 text-blue-800 dark:text-blue-300 border-blue-300"
    },
    {
      title: "ГОСТ 21931-76 (Припои оловянно-свинцовые)",
      category: "стандарты",
      badge: "ГОСТ РФ // Сплавы",
      desc: "Государственный стандарт на химический состав, марки и температуры солидус/ликвидус отечественных припоев серии ПОС.",
      url: "/category.php?slug=materialy",
      badgeColor: "bg-blue-100 dark:bg-blue-950/40 text-blue-800 dark:text-blue-300 border-blue-300"
    }
  ];

  let currentCategory = 'all';
  let activeIndex = -1;

  const modal = document.getElementById('search-modal');
  const modalBox = document.getElementById('search-modal-box');
  const searchInput = document.getElementById('global-search-input');
  const resultsContainer = document.getElementById('search-results-container');
  const closeBtn = document.getElementById('search-modal-close');
  const categoryPills = document.querySelectorAll('.search-category-pill');

  function openSearch() {
    if (!modal) return;
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    setTimeout(() => {
      searchInput.focus();
      renderResults(searchInput.value.trim());
    }, 50);
  }

  function closeSearch() {
    if (!modal) return;
    modal.classList.remove('active');
    document.body.style.overflow = '';
    searchInput.value = '';
    activeIndex = -1;
  }

  // Open triggers
  document.addEventListener('click', function(e) {
    const trigger = e.target.closest('#search-modal-trigger, .open-search-trigger');
    if (trigger) {
      e.preventDefault();
      openSearch();
    }
    if (e.target === modal) {
      closeSearch();
    }
  });

  if (closeBtn) {
    closeBtn.addEventListener('click', closeSearch);
  }

  // Global Ctrl+K / Cmd+K / Slash keybindings
  document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      if (!modal) return;
      if (modal.classList.contains('active')) {
        closeSearch();
      } else {
        openSearch();
      }
    } else if (e.key === 'Escape' && modal && modal.classList.contains('active')) {
      e.preventDefault();
      closeSearch();
    }
  });

  // Filter pills
  categoryPills.forEach(pill => {
    pill.addEventListener('click', () => {
      categoryPills.forEach(p => {
        p.classList.remove('bg-ink', 'text-paper', 'font-semibold', 'active');
        p.classList.add('border', 'border-paper-border', 'text-ink-muted');
      });
      pill.classList.remove('border', 'border-paper-border', 'text-ink-muted');
      pill.classList.add('bg-ink', 'text-paper', 'font-semibold', 'active');
      currentCategory = pill.dataset.filter;
      renderResults(searchInput.value.trim());
    });
  });

  function renderResults(query) {
    if (!resultsContainer) return;
    const q = query.toLowerCase();
    const filtered = KNOWLEDGE_BASE.filter(item => {
      const matchCategory = currentCategory === 'all' || item.category === currentCategory;
      if (!matchCategory) return false;
      if (!q) return true;
      return item.title.toLowerCase().includes(q) ||
             item.desc.toLowerCase().includes(q) ||
             item.badge.toLowerCase().includes(q) ||
             item.category.toLowerCase().includes(q);
    });

    if (filtered.length === 0) {
      resultsContainer.innerHTML = `
        <div class="py-12 text-center text-ink-muted">
          <span class="material-symbols-outlined text-4xl text-ink-faint mb-2">search_off</span>
          <p class="font-mono text-sm">Ничего не найдено по запросу «${escapeHtml(query)}»</p>
          <p class="text-xs text-ink-faint mt-1">Попробуйте ввести марку припоя (ПОС-61), флюса (RMA-218) или тип дефекта.</p>
        </div>
      `;
      activeIndex = -1;
      return;
    }

    resultsContainer.innerHTML = filtered.map((item, idx) => `
      <a href="${item.url}" class="search-result-item block p-3 rounded-lg hover:bg-paper-border/40 dark:hover:bg-paper/5 transition-colors border border-transparent hover:border-paper-border" data-index="${idx}">
        <div class="flex items-center justify-between gap-2 mb-1">
          <div class="flex items-center gap-2">
            <span class="font-sans font-bold text-ink text-sm sm:text-base">${highlightQuery(item.title, q)}</span>
            <span class="text-[10px] uppercase font-mono px-1.5 py-0.5 rounded border ${item.badgeColor}">${item.badge}</span>
          </div>
          <span class="text-[11px] font-mono text-ink-faint uppercase hidden sm:inline">${item.category}</span>
        </div>
        <p class="text-xs text-ink-muted line-clamp-2 leading-relaxed font-sans">${highlightQuery(item.desc, q)}</p>
      </a>
    `).join('');

    activeIndex = 0;
    updateActiveResult();
  }

  function highlightQuery(text, q) {
    if (!q) return escapeHtml(text);
    const regex = new RegExp(`(${escapeRegex(q)})`, 'gi');
    return escapeHtml(text).replace(regex, '<mark class="bg-amber-200 dark:bg-amber-900/60 text-ink font-semibold rounded px-0.5">$1</mark>');
  }

  function escapeHtml(str) {
    return str.replace(/[&<>"']/g, function(m) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
    });
  }

  function escapeRegex(str) {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  function updateActiveResult() {
    const items = resultsContainer.querySelectorAll('.search-result-item');
    items.forEach((it, idx) => {
      if (idx === activeIndex) {
        it.classList.add('bg-paper-border/50', 'dark:bg-paper/10', 'border-accent/40');
        it.scrollIntoView({ block: 'nearest' });
      } else {
        it.classList.remove('bg-paper-border/50', 'dark:bg-paper/10', 'border-accent/40');
      }
    });
  }

  // Keyboard navigation inside search results
  if (searchInput) {
    searchInput.addEventListener('input', () => {
      renderResults(searchInput.value.trim());
    });

    searchInput.addEventListener('keydown', (e) => {
      const items = resultsContainer.querySelectorAll('.search-result-item');
      if (items.length === 0) return;

      if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIndex = (activeIndex + 1) % items.length;
        updateActiveResult();
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIndex = (activeIndex - 1 + items.length) % items.length;
        updateActiveResult();
      } else if (e.key === 'Enter') {
        e.preventDefault();
        if (activeIndex >= 0 && activeIndex < items.length) {
          items[activeIndex].click();
        }
      }
    });
  }

})();
</script>
