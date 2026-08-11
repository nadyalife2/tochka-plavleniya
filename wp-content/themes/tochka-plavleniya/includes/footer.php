        <footer class="footer">
            <div class="footer-grid">
                <div class="footer-col">
                    <div class="footer-brand">Точка<span>.</span>Плавления</div>
                    <p class="footer-desc">Независимый журнал и лаборатория о пайке, микроконтроллерах, ESP32 и AI в DIY-электронике. Проверено на реальном железе.</p>
                </div>
                <div class="footer-col">
                    <div class="footer-title">Рубрики</div>
                    <ul class="footer-list">
                        <li><a href="/?tag=basics">Основы пайки</a></li>
                        <li><a href="/?tag=smd">SMD и BGA монтаж</a></li>
                        <li><a href="/?tag=tools">Инструменты & Жала</a></li>
                        <li><a href="/?tag=materials">Флюсы & Припои</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <div class="footer-title">Инструменты</div>
                    <ul class="footer-list">
                        <li><a href="/interactive.php#calc">Калькулятор флюса</a></li>
                        <li><a href="/interactive.php#divider">Делитель напряжения</a></li>
                        <li><a href="/interactive.php#battery">Автономия ESP32</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <div class="footer-title">Лицензия & Проект</div>
                    <p class="footer-meta">Материалы доступны по лицензии <a href="https://creativecommons.org/licenses/by-nc/4.0/" target="_blank" rel="noopener">CC BY-NC 4.0</a>.</p>
                    <div class="footer-links">
                        <a href="/privacy.php">Конфиденциальность</a>
                        <a href="/cookies.php">Cookies</a>
                    </div>
                    <button type="button" class="back-to-top-btn" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">Наверх ↑</button>
                </div>
            </div>
            <div class="footer-bottom">
                <p>© <?= date('Y') ?> ТЧП // Точка Плавления · Лаборатория практической электроники</p>
            </div>
        </footer>
    </div> <!-- /.container -->

    <!-- Global Search Modal -->
    <div class="search-modal" id="search-modal" aria-hidden="true" role="dialog" aria-label="Поиск по статьям и калькуляторам">
        <div class="search-modal-box">
            <div class="search-modal-header">
                <span style="font-size: 1.2rem;">🔍</span>
                <input type="text" id="search-input" class="search-input" placeholder="Поиск статей, компонентов, кода..." autocomplete="off" autofocus>
                <button type="button" class="search-close-btn" id="search-close-btn" aria-label="Закрыть поиск">ESC</button>
            </div>
            <div class="search-results" id="search-results">
                <div style="padding: 1rem; text-align: center; color: var(--text-muted); font-family: var(--font-mono); font-size: 0.9rem;">
                    Введите слово для поиска (например: <strong>пайка</strong>, <strong>ESP32</strong>, <strong>флюс</strong>)...
                </div>
            </div>
        </div>
    </div>

    <script src="/assets/js/main.js"></script>
    <?php if (isset($extra_js)) foreach((array)$extra_js as $js) echo "<script src='/assets/js/{$js}'></script>\n"; ?>
    <?php include __DIR__ . '/cookie-banner.php'; ?>
</body>
</html>

