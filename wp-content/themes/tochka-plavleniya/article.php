<?php
require_once __DIR__ . '/includes/articles-data.php';
require_once __DIR__ . '/includes/functions.php';

$slug = $_GET['slug'] ?? 'temperaturnye-profili';
$article = get_article_by_slug($slug);

if (!$article) { 
    $article = $articles[0]; 
}

$page_title = $article['title'];
$extra_css = ['article.css'];
include __DIR__ . '/includes/header.php';
?>

<!-- BREADCRUMB -->
<div class="breadcrumb">
    <a href="/">Главная</a>
    <span>→</span>
    <a href="/tag/<?= e($article['tag_key']) ?>/"><?= e($article['tag']) ?></a>
    <span>→</span>
    <?= e($article['title']) ?>
</div>

<!-- ARTICLE HERO -->
<section class="article-hero">
    <span class="tag"><?= e($article['tag']) ?></span>
    <h1><?= e($article['title']) ?></h1>
    <div class="meta">
        <div class="meta-item">⏱️ <?= e($article['read_min']) ?> мин чтения</div>
        <div class="meta-item">📅 <?= e($article['date']) ?></div>
        <div class="meta-item">🔄 Обновлено: 10 авг 2026</div>
        <div class="meta-item">👤 <?= e($article['author']) ?></div>
        <div class="meta-item" style="background:var(--accent-orange); color:#1A1A1A; font-weight:800;">⚡ Совместимость: ESP32 / SAC305 / FR-4</div>
        <div class="meta-item" style="background:var(--bg-card); border:1.5px solid var(--border);">🤖 AI-Assisted / Verified by Human</div>
    </div>
</section>

<!-- AUTHOR & VERIFICATION BADGE -->
<div class="author-badge-row">
    <div class="author-info">
        <div class="author-avatar">ТЧП</div>
        <div class="author-text">
            <span class="author-name"><?= e($article['author']) ?></span>
            <span class="author-role">Инженер-электроник & DIY-исследователь</span>
        </div>
    </div>
    <div class="verified-tag">
        ⚡ Проверено в лаборатории на реальном железе
    </div>
</div>

<!-- ARTICLE LAYOUT -->
<div class="article-layout">
    <!-- CONTENT -->
    <article class="article-content" id="article-body">
        <!-- TL;DR Executive Summary Block -->
        <div class="callout tldr">
            <div class="callout-title">В двух словах (TL;DR)</div>
            <p><strong>Ключевая мысль:</strong> Соблюдение правильного температурного профиля (преднагрев 150°C ➔ пик 245°C) предотвращает отслоение дорожек платы и термошок компонентов. Используйте термопары для реального мониторинга.</p>
        </div>

        <h2 id="intro">Введение</h2>
        <p><?= e($article['excerpt']) ?></p>
        <p>Температурный профиль — это зависимость температуры платы от времени во время пайки. Правильный профиль критически важен для качества пайки и долговечности компонентов.</p>

        <div class="callout important">
            <div class="callout-title">Важно</div>
            <p>Перегрев платы может привести к отслоению дорожек, повреждению компонентов и снижению надёжности устройства.</p>
        </div>

        <h2 id="theory">Теория теплопередачи</h2>
        <p>Текстолит имеет определённую теплоёмкость и теплопроводность. При нагреве температура распределяется неравномерно: центр платы нагревается медленнее, чем края.</p>

        <h3 id="materials">Влияние материалов</h3>
        <p>Разные типы текстолита (FR-4, CEM-1, алюминиевые подложки) имеют разные тепловые характеристики.</p>

        <!-- Dynamic Thermal Curve SVG -->
        <div class="inline-svg">
            <svg viewBox="0 0 540 220" width="100%" height="100%" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                <rect width="540" height="220" rx="8" fill="var(--bg-card)" stroke="var(--border)" stroke-width="3"/>
                <path d="M40 180 Q120 160 180 110 T320 50 T440 80 T500 180" stroke="var(--border)" stroke-width="4" fill="none"/>
                <circle cx="180" cy="110" r="6" fill="var(--accent-yellow)" stroke="var(--border)" stroke-width="2.5"/>
                <circle cx="320" cy="50" r="6" fill="var(--accent-red)" stroke="var(--border)" stroke-width="2.5"/>
                <circle cx="440" cy="80" r="6" fill="var(--accent-green)" stroke="var(--border)" stroke-width="2.5"/>
                <line x1="40" y1="180" x2="500" y2="180" stroke="var(--border)" stroke-dasharray="4 4"/>
                <text x="50" y="35" font-family="JetBrains Mono" font-size="13" font-weight="700" fill="var(--text-main)">График профиля: T°C / Время (сек)</text>
                <text x="140" y="100" font-family="JetBrains Mono" font-size="11" font-weight="700" fill="var(--text-main)">Preheat (150°C)</text>
                <text x="300" y="38" font-family="JetBrains Mono" font-size="11" font-weight="700" fill="var(--text-main)">Peak (245°C)</text>
            </svg>
        </div>

        <div class="callout tip">
            <div class="callout-title">Совет</div>
            <p>Используйте термопары для мониторинга реальной температуры платы во время пайки.</p>
        </div>

        <h2 id="practice">Практика настройки</h2>
        <p>Для настройки профиля необходимо учитывать:</p>
        <ul>
            <li>Тип компонентов (BGA, QFP, 0402)</li>
            <li>Толщину платы и количество слоёв</li>
            <li>Тип припоя (свинцовый / бессвинцовый)</li>
        </ul>

        <!-- Enhanced Code Block with Header, Copy Button, and Collapsible Overlay -->
        <div class="code-wrapper collapsible">
            <div class="code-header">
                <span class="code-filename">thermal_calc.py</span>
                <button type="button" class="copy-code-btn">📋 Скопировать</button>
            </div>
            <div class="code-block">
                <code># Пример расчёта времени преднагрева
t_preheat = (T_target - T_initial) / rate
print(f"Время преднагрева: {t_preheat:.1f} сек")

# Оптимальная скорость нагрева: 2-3°C/сек
rate = 2.5  # °C/сек
max_temp = 245  # °C Peak
soak_time = 90  # seconds soak phase
reflow_time = 45  # seconds reflow phase

def calculate_profile(pcb_thickness, layer_count):
    thermal_mass = pcb_thickness * (1 + layer_count * 0.15)
    adjusted_preheat = t_preheat * thermal_mass
    return round(adjusted_preheat, 2)</code>
            </div>
            <div class="code-expand-overlay">
                <button type="button" class="code-expand-btn">Развернуть весь код ⚡</button>
            </div>
        </div>

        <h2 id="table">Таблица температур</h2>
        <table class="article-table">
            <thead>
                <tr>
                    <th>Тип припоя</th>
                    <th>Температура плавления</th>
                    <th>Пиковая температура</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Sn63/Pb37 (ПОС-61)</td>
                    <td>183°C</td>
                    <td>220-235°C</td>
                </tr>
                <tr>
                    <td>SAC305</td>
                    <td>217°C</td>
                    <td>245-260°C</td>
                </tr>
                <tr>
                    <td>Sn96.5/Ag3/Cu0.5</td>
                    <td>217°C</td>
                    <td>240-250°C</td>
                </tr>
            </tbody>
        </table>

        <div class="callout danger">
            <div class="callout-title">Опасно</div>
            <p>Превышение пиковой температуры более чем на 10°C может привести к необратимому повреждению компонентов.</p>
        </div>

        <!-- INTERACTIVE WIDGET -->
        <div class="interactive-widget">
            <h3 class="widget-title">🧮 Калькулятор флюса</h3>
            <p>Рассчитайте необходимое количество флюса для вашей платы:</p>
            <div class="widget-form">
                <input type="number" id="flux-area" class="widget-input" placeholder="Площадь платы (см²)">
                <select id="flux-type" class="widget-input">
                    <option value="rma">Тип флюса: RMA-223</option>
                    <option value="nc">Тип флюса: NC-559 (No-clean)</option>
                </select>
                <button id="calc-flux-btn" class="widget-button">Рассчитать ⚡</button>
            </div>
            <div id="flux-result" class="callout tip" style="display:none; margin-top:1rem;"></div>
        </div>

        <h2 id="conclusion">Заключение</h2>
        <p>Правильный температурный профиль — это баланс между качеством пайки и сохранностью компонентов. Экспериментируйте, используйте термопары и не бойтесь корректировать настройки.</p>
    </article>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <!-- Table of Contents -->
        <div class="sidebar-block">
            <div class="sidebar-title">Содержание</div>
            <ul class="toc-list" id="toc-list"></ul>
        </div>

        <!-- Tags -->
        <div class="sidebar-block">
            <div class="sidebar-title">Теги</div>
            <div class="sidebar-tags" style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <span class="tag" style="margin:0;"><?= e($article['tag']) ?></span>
                <span class="tag" style="margin:0; background:var(--accent-yellow); color:var(--text-main);">Пайка</span>
                <span class="tag" style="margin:0; background:var(--accent-blue); color:var(--text-main);">Температура</span>
            </div>
        </div>

        <!-- Related Articles -->
        <div class="sidebar-block">
            <div class="sidebar-title">Читать также</div>
            <?php 
            $related = array_filter($articles, fn($a) => $a['slug'] !== $article['slug']);
            $related = array_slice($related, 0, 3);
            foreach ($related as $rel): 
            ?>
                <div class="related-card" style="margin-bottom:1rem; padding:0.75rem; border:2px solid var(--border); border-radius:8px; background:var(--bg);">
                    <h4 style="font-family:var(--font-serif); font-size:1.05rem; margin-bottom:0.3rem;">
                        <a href="/article/<?= e($rel['slug']) ?>/" style="text-decoration:none; color:var(--text-main);"><?= e($rel['title']) ?></a>
                    </h4>
                    <p style="font-size:0.85rem; color:var(--text-muted); margin:0;"><?= e($rel['excerpt']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </aside>
</div>



<?php 
$extra_js = ['flux-calc.js'];
include __DIR__ . '/includes/footer.php'; 
?>
