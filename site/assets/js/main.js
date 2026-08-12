// ===== 1. THEME SWITCHER LOGIC =====
(function() {
    const savedTheme = localStorage.getItem('tchp_theme') || 'craft';
    document.documentElement.setAttribute('data-theme', savedTheme);
    document.body?.setAttribute('data-theme', savedTheme);
})();

document.addEventListener('DOMContentLoaded', () => {
    // Theme selector
    const themeSelect = document.getElementById('theme-select');
    const savedTheme = localStorage.getItem('tchp_theme') || 'craft';

    if (themeSelect) {
        themeSelect.value = savedTheme;
        document.documentElement.setAttribute('data-theme', savedTheme);
        document.body.setAttribute('data-theme', savedTheme);

        themeSelect.addEventListener('change', (e) => {
            const selected = e.target.value;
            document.documentElement.setAttribute('data-theme', selected);
            document.body.setAttribute('data-theme', selected);
            localStorage.setItem('tchp_theme', selected);
        });
    }

    // ===== 2. MOBILE DRAWER NAVIGATION =====
    const burgerTrigger = document.getElementById('burger-trigger');
    const drawerOverlay = document.getElementById('drawer-overlay');
    const drawerClose = document.getElementById('drawer-close');

    function openDrawer() {
        if (drawerOverlay) {
            drawerOverlay.classList.add('active');
            drawerOverlay.setAttribute('aria-hidden', 'false');
        }
    }
    function closeDrawer() {
        if (drawerOverlay) {
            drawerOverlay.classList.remove('active');
            drawerOverlay.setAttribute('aria-hidden', 'true');
        }
    }

    if (burgerTrigger) burgerTrigger.addEventListener('click', openDrawer);
    if (drawerClose) drawerClose.addEventListener('click', closeDrawer);
    if (drawerOverlay) {
        drawerOverlay.addEventListener('click', (e) => {
            if (e.target === drawerOverlay) closeDrawer();
        });
    }

    // ===== 3. COMMAND SEARCH CONSOLE LOGIC & DROPDOWN =====
    const searchContainer = document.getElementById('header-search-container');
    const searchExpandBtn = document.getElementById('search-expand-btn');
    const headerSearchInput = document.getElementById('header-search-input');
    const searchCloseX = document.getElementById('search-close-x');
    const headerSearchResults = document.getElementById('header-search-results');

    const searchModal = document.getElementById('search-modal');
    const searchCloseBtn = document.getElementById('search-close-btn');
    const searchInput = document.getElementById('search-input');
    const searchResults = document.getElementById('search-results');
    const articlesGrid = document.getElementById('articles-grid');

    const sampleArticles = [
        {
            title: "Температурные профили: как не перегреть плату за $300",
            excerpt: "Подробный разбор графиков нагрева, оплавления и охлаждения для бессвинцовых и свинцовых припоев.",
            slug: "temperaturnye-profili",
            tag: "Основы"
        },
        {
            title: "Выбор флюса для SMD: RMA, No-Clean или водосмываемый?",
            excerpt: "Практический тест 5 популярных марок флюса под микроскопом. Что остаётся под BGA-чипами.",
            slug: "vybor-flyusa-dlya-smd",
            tag: "SMD"
        },
        {
            title: "ESP32 Deep Sleep: оптимизация энергопотребления до 5µA",
            excerpt: "Как отключить ненужную периферию, выбрать LDO-регулятор и настроить пробуждение по таймеру.",
            slug: "esp32-deep-sleep",
            tag: "Инструменты"
        },
        {
            title: "Обзор паяльных станций: T12 vs JBC C245 в 2026 году",
            excerpt: "Сравнение скорости нагрева, точности поддержания температуры и цены расходников.",
            slug: "obzor-payalnyh-stantsij",
            tag: "Материалы"
        }
    ];

    function openSearchPopover(e) {
        if (e) e.stopPropagation();
        if (searchContainer) {
            searchContainer.classList.add('active');
            setTimeout(() => {
                headerSearchInput?.focus();
                headerSearchInput?.select();
            }, 50);
            renderHeaderDropdown(headerSearchInput?.value || '');
        }
    }

    function closeSearchPopover(e) {
        if (e) e.stopPropagation();
        if (searchContainer) {
            searchContainer.classList.remove('active');
            if (headerSearchInput) {
                headerSearchInput.value = '';
            }
            if (articlesGrid) {
                filterHomeGrid('');
            }
        }
    }

    if (searchExpandBtn) searchExpandBtn.addEventListener('click', openSearchPopover);
    if (searchCloseX) searchCloseX.addEventListener('click', closeSearchPopover);

    document.addEventListener('click', (e) => {
        if (searchContainer && !searchContainer.contains(e.target) && searchContainer.classList.contains('active')) {
            closeSearchPopover();
        }
    });

    function renderHeaderDropdown(val) {
        if (!headerSearchResults) return;
        const query = val.trim().toLowerCase();

        if (!query) {
            headerSearchResults.innerHTML = `
                <div style="padding: 0.75rem; text-align: center; color: var(--text-muted); font-family: var(--font-mono); font-size: 0.8rem;">
                    Введите запрос для мгновенного поиска (например: <strong>ESP32</strong>, <strong>флюс</strong>)...
                </div>
            `;
            return;
        }

        const matches = sampleArticles.filter(art =>
            art.title.toLowerCase().includes(query) ||
            art.excerpt.toLowerCase().includes(query) ||
            art.tag.toLowerCase().includes(query)
        );

        if (matches.length === 0) {
            headerSearchResults.innerHTML = `
                <div style="padding: 1rem; text-align: center; color: var(--text-muted); font-family: var(--font-mono); font-size: 0.85rem;">
                    Ничего не найдено по запросу «<strong>${query}</strong>» 🔍
                </div>
            `;
        } else {
            headerSearchResults.innerHTML = matches.map(art => `
                <a href="/article.php?slug=${encodeURIComponent(art.slug)}" class="search-dropdown-item">
                    <span class="tag" style="margin-bottom:0.2rem; font-size:0.65rem; width:fit-content;">${art.tag}</span>
                    <strong style="font-size:0.9rem;">${art.title}</strong>
                    <span style="font-size:0.8rem; color:var(--text-muted);">${art.excerpt}</span>
                </a>
            `).join('');
        }
    }

    if (headerSearchInput) {
        headerSearchInput.addEventListener('input', (e) => {
            const query = e.target.value;
            renderHeaderDropdown(query);
            if (articlesGrid) filterHomeGrid(query);
        });
    }

    // Live search inside home grid
    function filterHomeGrid(queryStr) {
        if (!articlesGrid) return;
        const query = queryStr.trim().toLowerCase();
        let noResultsMsg = document.getElementById('no-results-msg');
        if (!noResultsMsg) {
            noResultsMsg = document.createElement('div');
            noResultsMsg.id = 'no-results-msg';
            noResultsMsg.style.cssText = 'grid-column: span 12; padding: 3rem; text-align: center; font-family: var(--font-mono); color: var(--text-muted); display: none;';
            articlesGrid.appendChild(noResultsMsg);
        }

        const cards = articlesGrid.querySelectorAll('.card');
        let visibleCount = 0;

        cards.forEach(card => {
            const title = card.querySelector('h3')?.textContent.toLowerCase() || '';
            const excerpt = card.querySelector('p')?.textContent.toLowerCase() || '';
            const tag = card.querySelector('.tag')?.textContent.toLowerCase() || '';

            if (!query || title.includes(query) || excerpt.includes(query) || tag.includes(query)) {
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        if (visibleCount === 0 && query !== '') {
            noResultsMsg.innerHTML = `🔍 Ничего не найдено по запросу «<strong>${query}</strong>»`;
            noResultsMsg.style.display = 'block';
        } else {
            noResultsMsg.style.display = 'none';
        }
    }

    function openSearchModal(initialQuery = '') {
        if (searchModal) {
            searchModal.classList.add('active');
            searchModal.setAttribute('aria-hidden', 'false');
            if (searchInput) {
                if (initialQuery) searchInput.value = initialQuery;
                setTimeout(() => searchInput.focus(), 100);
                triggerSearch(searchInput.value);
            }
        }
    }

    function closeSearchModal() {
        if (searchModal) {
            searchModal.classList.remove('active');
            searchModal.setAttribute('aria-hidden', 'true');
        }
    }

    if (searchCloseBtn) searchCloseBtn.addEventListener('click', closeSearchModal);

    if (searchModal) {
        searchModal.addEventListener('click', (e) => {
            if (e.target === searchModal) closeSearchModal();
        });
    }

    // Keyboard shortcut Cmd+K / Ctrl+K & ESC
    document.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            if (searchContainer?.classList.contains('active')) {
                headerSearchInput?.focus();
            } else {
                openSearchPopover(e);
            }
        }
        if (e.key === 'Escape') {
            if (searchContainer?.classList.contains('active')) closeSearchPopover(e);
            if (searchModal?.classList.contains('active')) closeSearchModal();
        }
    });

    // ===== 4. PILL FILTER FOR HOME ARTICLES GRID =====
    const pills = document.querySelectorAll('.pill');
    const cards = document.querySelectorAll('.card');

    pills.forEach(pill => {
        pill.addEventListener('click', (e) => {
            const filter = pill.getAttribute('data-filter') || pill.getAttribute('href')?.split('/').pop().replace('/', '') || 'all';
            
            if (cards.length > 0 && (pill.getAttribute('data-filter') || pill.getAttribute('href') === '#' || pill.tagName === 'BUTTON')) {
                e.preventDefault();
                pills.forEach(p => p.classList.remove('active'));
                pill.classList.add('active');

                cards.forEach(card => {
                    const tag = card.getAttribute('data-tag');
                    if (filter === 'all' || filter === 'all.php' || tag === filter) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
            }
        });
    });

    // ===== 5. AUTOMATIC TOC GENERATOR FOR ARTICLES =====
    const tocList = document.getElementById('toc-list');
    const articleBody = document.getElementById('article-body') || document.querySelector('.article-content');
    if (tocList && articleBody) {
        const headings = articleBody.querySelectorAll('h2, h3');
        headings.forEach(heading => {
            if (!heading.id) heading.id = heading.textContent.toLowerCase().replace(/\s+/g, '-');
            const li = document.createElement('li');
            const a = document.createElement('a');
            a.href = `#${heading.id}`;
            a.textContent = heading.textContent;
            if (heading.tagName === 'H3') a.classList.add('toc-sub');
            li.appendChild(a);
            tocList.appendChild(li);
        });

        // Highlight TOC active item on scroll using IntersectionObserver
        const observerOptions = {
            root: null,
            rootMargin: '-20% 0px -70% 0px',
            threshold: 0
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const id = entry.target.getAttribute('id');
                    document.querySelectorAll('#toc-list a').forEach(a => {
                        if (a.getAttribute('href') === `#${id}`) {
                            a.classList.add('active');
                        } else {
                            a.classList.remove('active');
                        }
                    });
                }
            });
        }, observerOptions);

        headings.forEach(heading => observer.observe(heading));
    }

    // ===== 6. COPY CODE BUTTON HANDLER =====
    document.querySelectorAll('.copy-code-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const codeBlock = btn.closest('.code-wrapper')?.querySelector('code');
            if (codeBlock) {
                navigator.clipboard.writeText(codeBlock.textContent).then(() => {
                    const originalText = btn.textContent;
                    btn.textContent = '✓ Скопировано!';
                    btn.style.background = 'var(--accent-green)';
                    btn.style.color = 'var(--text-main)';
                    setTimeout(() => {
                        btn.textContent = originalText;
                        btn.style.background = '';
                        btn.style.color = '';
                    }, 2000);
                }).catch(err => {
                    console.error('Failed to copy text: ', err);
                });
            }
        });
    });

    // ===== 7. COLLAPSIBLE CODE BLOCK EXPANDER =====
    document.querySelectorAll('.code-expand-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const wrapper = btn.closest('.code-wrapper');
            if (wrapper) {
                wrapper.classList.remove('collapsible');
                const overlay = wrapper.querySelector('.code-expand-overlay');
                if (overlay) overlay.style.display = 'none';
            }
        });
    });
});

