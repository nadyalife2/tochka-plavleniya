<!DOCTYPE html>
<html lang="ru" data-theme="craft">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? e($page_title) . ' — ' : '' ?>Точка Плавления // ТЧП</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@700;800&family=IBM+Plex+Mono:wght@500;700&family=Inter:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,300;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <?php if (isset($extra_css)) foreach((array)$extra_css as $css) echo "<link rel='stylesheet' href='/assets/css/{$css}'>\n"; ?>
</head>
<body>
    <div class="container">
        <header class="header">
            <a href="/" class="logo">Точка<span>.</span>Плавления</a>
            
            <div class="header-right">
                <!-- Custom PCB Command Search Console -->
                <div class="header-search-container" id="header-search-container">
                    <button type="button" class="search-trigger-btn" id="search-expand-btn" title="Поиск по сайту (Ctrl+K)" aria-label="Поиск по сайту">
                        <svg class="soldering-loupe-icon" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="10" cy="10" r="6" stroke-width="2" />
                            <line x1="14.2" y1="14.2" x2="20" y2="20" stroke-width="2.6" />
                            <!-- Перекрестие, выходящее за рамку линзы -->
                            <line x1="10" y1="1.5" x2="10" y2="5.5" stroke-width="1.3" stroke="var(--accent-orange)" />
                            <line x1="10" y1="14.5" x2="10" y2="18.5" stroke-width="1.3" stroke="var(--accent-orange)" />
                            <line x1="1.5" y1="10" x2="5.5" y2="10" stroke-width="1.3" stroke="var(--accent-orange)" />
                            <line x1="14.5" y1="10" x2="18.5" y2="10" stroke-width="1.3" stroke="var(--accent-orange)" />
                            <circle cx="10" cy="10" r="1.2" fill="var(--accent-orange)" stroke="none" />
                        </svg>
                    </button>
                    
                    <div class="search-console-popover" id="search-console-popover">
                        <div class="search-console-header">
                            <svg class="soldering-loupe-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="var(--accent-orange)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="10" cy="10" r="6.5" />
                                <path d="M14.5 14.5 L20.5 20.5" stroke-width="2.8" />
                            </svg>
                            <input type="text" id="header-search-input" class="header-search-input" placeholder="Поиск по статьям (ESP32, BGA, флюс...)" autocomplete="off" aria-label="Поиск по сайту">
                            <button type="button" class="search-close-x" id="search-close-x" title="Свернуть (ESC)" aria-label="Свернуть поиск">✕</button>
                        </div>
                        <div class="search-dropdown-results" id="header-search-results">
                            <!-- Instant search results will render here -->
                        </div>
                    </div>
                </div>

                <nav class="nav">
                    <a href="/">Статьи</a>
                    <a href="/interactive.php">Инструменты</a>
                    <a href="/cookies.php">Cookies</a>
                </nav>

                <!-- Theme Switcher Selector -->
                <select id="theme-select" class="theme-select" title="Выбор цветового режима" aria-label="Выбор цветового режима">
                    <option value="craft">📜 Крафт</option>
                    <option value="blueprint">📐 Чертёж (Белый / Минимал)</option>
                    <option value="dark">🌙 Тёмная</option>
                    <option value="pcb">🌲 Текстолит</option>
                    <option value="pastel">🎨 Пастель</option>
                </select>

                <!-- Mobile Burger Toggle Button -->
                <button type="button" class="burger-btn" id="burger-trigger" aria-label="Открыть мобильное меню">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </header>

        <!-- Mobile Drawer Navigation -->
        <div class="drawer-overlay" id="drawer-overlay" aria-hidden="true">
            <div class="drawer-content">
                <div class="drawer-header">
                    <a href="/" class="logo" style="font-size: 1.3rem;">Точка<span>.</span>Плавления</a>
                    <button type="button" class="drawer-close" id="drawer-close" aria-label="Закрыть меню">✕</button>
                </div>
                <nav class="drawer-nav">
                    <a href="/">⚡ Статьи</a>
                    <a href="/interactive.php">🧮 Инструменты</a>
                    <a href="/cookies.php">📜 Cookies</a>
                    <a href="/privacy.php">🔒 Privacy</a>
                </nav>
            </div>
        </div>

