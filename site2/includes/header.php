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
            <a href="/" class="logo">ТОЧКА<span>.</span>ПЛАВЛЕНИЯ</a>
            
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

                <?php include __DIR__ . '/header-nav.php'; ?>

                <!-- Theme Switcher Selector -->
                <select id="theme-select" class="theme-select" title="Выбор цветового режима" aria-label="Выбор цветового режима">
                    <option value="minimal">Минимал (Светлая)</option>
                    <option value="craft">Крафт (Инженерная)</option>
                    <option value="blueprint">Чертёж (Белый / Сетка)</option>
                    <option value="dark">Тёмная лаборатория</option>
                    <option value="pcb">Текстолит (PCB)</option>
                    <option value="pastel">Пастель</option>
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
                    <a href="/" class="logo">ТОЧКА<span>.</span>ПЛАВЛЕНИЯ</a>
                    <button type="button" class="drawer-close" id="drawer-close" aria-label="Закрыть меню">✕</button>
                </div>
                <nav class="drawer-nav" aria-label="Мобильная навигация">
                    <div class="drawer-quick-actions">
                        <a href="/interactive.php#temp" class="drawer-cta-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"/>
                            </svg>
                            Подобрать температуру
                        </a>
                        <a href="/interactive.php#defect" class="drawer-cta-btn drawer-cta-btn--alt">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="8" x2="12" y2="12"/>
                                <line x1="12" y1="16" x2="12.01" y2="16"/>
                            </svg>
                            Найти ошибку пайки
                        </a>
                    </div>
                    <?php 
                    $nav_items = [
                        ['href' => '/',                                  'label' => 'Главная',      'page' => 'index'],
                        ['href' => '/category.php?slug=start',           'label' => 'Начать паять', 'page' => 'start'],
                        ['href' => '/category.php?slug=materialy',       'label' => 'Материалы',    'page' => 'materialy'],
                        ['href' => '/category.php?slug=praktika',        'label' => 'Практика',     'page' => 'praktika'],
                        ['href' => '/category.php?slug=oshibki',         'label' => 'Проблемы',     'page' => 'oshibki'],
                        ['href' => '/interactive.php',                   'label' => 'Калькуляторы', 'page' => 'interactive'],
                    ];
                    foreach ($nav_items as $item):
                        $is_active = ($current_page === $item['page'])
                                  || ($current_page === 'category' && isset($_GET['slug']) && $_GET['slug'] === $item['page']);
                    ?>
                        <a href="<?= $item['href'] ?>" <?= $is_active ? 'class="active" aria-current="page"' : '' ?>>
                            <?= $item['label'] ?>
                        </a>
                    <?php endforeach; ?>
                    <div class="drawer-footer-links">
                        <a href="/privacy.php">Политика</a>
                        <a href="/terms.php">Условия</a>
                        <a href="/cookies.php">Cookies</a>
                    </div>
                </nav>
            </div>
        </div>

