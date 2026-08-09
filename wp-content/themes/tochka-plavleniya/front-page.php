<?php
/**
 * Template Name: Front Page Masterpiece
 */
get_header();
?>

    <!-- HERO SECTION -->
    <section class="hero-section">
      <div>
        <div class="badge-pill">
          ◉ [ 2026 // ENGINEERING & MAKER HUB ]
        </div>
        <h1 class="hero-title">
          Паяй. <br>
          Твори. <br>
          <span class="wavy-accent">Понимай.</span>
        </h1>
        <p class="hero-subtitle">
          Современный инди-портал по микроэлектронике: от выбора жала и температурного профиля 340°C до восстановления BGA плат под микроскопом.
        </p>

        <div class="hero-actions">
          <a href="#articles" class="btn-pill btn-dark">Все гайды →</a>
          <a href="#bench" class="btn-pill btn-outline">Симулятор t°</a>
        </div>

        <div style="display:flex; align-items:center; gap:12px; font-family:var(--font-mono); font-size:13px; margin-top:20px;">
          <span style="color:var(--accent); font-weight:700;">+2 500</span> инженеров и мейкеров в сообществе
        </div>
      </div>

      <!-- Stripe Dev / nan.fyi Interactive Workbench Widget -->
      <div class="dev-bench-widget" id="bench">
        <div class="widget-header">
          <span>⚙️ ИНТЕРАКТИВНЫЙ ТЕРМО-ВЕРСТАК</span>
          <span style="color:var(--accent);">● LIVE SIMULATOR</span>
        </div>

        <div class="tip-tabs">
          <button class="tip-tab active" onclick="setTip('T12-K', '80W', 'SMD/Разъёмы')">T12-K</button>
          <button class="tip-tab" onclick="setTip('C245-I', '130W', 'Микроскопия')">C245-I</button>
          <button class="tip-tab" onclick="setTip('T12-BC2', '80W', 'THT выводы')">T12-BC2</button>
          <button class="tip-tab" onclick="setTip('C210-0201', '40W', '0201 SMD')">C210</button>
        </div>

        <div class="range-wrap">
          <div class="range-label-row">
            <span>Установка t° паяльника:</span>
            <span style="color:var(--accent);" id="bench-temp-num">340°C</span>
          </div>
          <input type="range" min="200" max="450" value="340" class="range-input" id="bench-slider" oninput="updateBench(this.value)">
        </div>

        <!-- Terminal Console Output -->
        <div class="bench-console" id="console-output">
          <div>> STATUS: <span class="console-accent">OPTIMAL (320°C - 350°C)</span></div>
          <div>> ALLOY: Sn63/Pb37 (Свинцовый)</div>
          <div>> RECOMMENDED FLUX: RMA-223 / NC-559</div>
          <div>> THERMAL RISK: <span class="console-accent">SAFE ZONE</span></div>
        </div>
      </div>
    </section>

    <!-- ARTICLES CATALOG (Piccalilli Style) -->
    <section class="articles-section" id="articles">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <h2 style="font-size:32px; font-weight:900;">Каталог материалов и гайдов</h2>
        <span style="font-family:var(--font-mono); font-size:12px; color:var(--text-muted);">UPDATED WEEKLY</span>
      </div>

      <div class="articles-grid">
        <?php
        $hot_posts = new WP_Query(array(
            'posts_per_page' => 6,
            'ignore_sticky_posts' => true
        ));

        if ($hot_posts->have_posts()) :
            while ($hot_posts->have_posts()) : $hot_posts->the_post();
                $categories = get_the_category();
                $cat_name = !empty($categories) ? esc_html($categories[0]->name) : 'Гайд';
                ?>
                <a href="<?php the_permalink(); ?>" class="piccalilli-card">
                  <div>
                    <div class="card-sketch-box">
                      <svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="#1c1b1a" stroke-width="1.6"><path d="M12 2v20M2 12h20M7 7l10 10M17 7L7 17"/></svg>
                    </div>
                    <div class="card-meta-row">
                      <span class="card-code-tag">[ <?php echo esc_html($cat_name); ?> ]</span>
                      <span class="card-date"><?php echo get_the_date('j M Y'); ?></span>
                    </div>
                    <h3 class="card-h3"><?php the_title(); ?></h3>
                    <p class="card-excerpt"><?php echo wp_trim_words(get_the_excerpt(), 14); ?></p>
                  </div>
                  <div class="card-foot">
                    <span>⏱ <?php echo tochka_reading_time(); ?> чтения</span>
                    <div class="plus-circle">+</div>
                  </div>
                </a>
            <?php
            endwhile;
            wp_reset_postdata();
        endif;
        ?>
      </div>
    </section>

    <!-- KNOWLEDGE BASE & TUTORIAL READER (nan.fyi style) -->
    <section class="knowledge-section" id="knowledge">
      <div class="knowledge-grid">
        <div class="reader-card">
          <div class="breadcrumbs">Главная > Гайды > Температурные режимы</div>
          <h2 class="reader-h2">Как правильно выбрать температуру паяльника и не перегреть плату</h2>
          <p style="font-size:15px; color:var(--text-muted); margin-bottom:20px; line-height:1.6;">
            Главная ошибка начинающих мейкеров — завышать температуру до 400°C при любых сложностях. Это приводит к выгоранию флюса за долю секунды и отслоению медных дорожек.
          </p>
          <div class="callout-yellow">
            <div class="callout-label">💡 СОВЕТ ИНЖЕНЕРА</div>
            <strong>Простое правило пайки:</strong> процесс плавления должен начинаться за 2–3 секунды. Если припой не растекается — возьмите более широкое жало (K или BC2) вместо поднятия температуры!
          </div>
          <div class="alert-red">
            <div class="alert-label">⚠️ КРАСНЫЕ ФЛАГИ / ОПАСНОСТЬ</div>
            • Обугливание флюса с образованием нагара.<br>
            • Отслаивание медных пятачков (pads) от текстолита.<br>
            • Повреждение внутренних слоев многослойных плат.
          </div>
        </div>

        <aside class="sidebar">
          <div class="side-card">
            <div class="side-title">Теги по темам</div>
            <div class="tag-cloud">
              <a href="#" class="tag-btn active">BGA</a>
              <a href="#" class="tag-btn">SMD</a>
              <a href="#" class="tag-btn">THT</a>
              <a href="#" class="tag-btn">Флюс</a>
            </div>
          </div>
        </aside>
      </div>
    </section>

<?php
get_footer();
?>
