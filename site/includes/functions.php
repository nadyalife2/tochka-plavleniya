<?php
/**
 * functions.php — вспомогательные функции
 */

/**
 * Рендер карточки статьи
 */
function render_card(array $a, string $class = ''): void {
  $pills_html = '';
  foreach ($a['pills'] ?? [] as $pill) {
    $pills_html .= "<button class='pill' data-filter='{$a['tag_key']}'>{$pill}</button>";
  }
  echo <<<HTML
  <article class="card anim-in {$class}" data-tag="{$a['tag_key']}">
    <div class="card__thumb">
      <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
        <rect x="4" y="16" width="32" height="18" rx="2" stroke="#fc6c2b" stroke-width="1.5"/>
        <rect x="10" y="20" width="6" height="6" rx="1" stroke="#fc6c2b" stroke-width="1.2"/>
        <rect x="22" y="20" width="6" height="6" rx="1" stroke="#fc6c2b" stroke-width="1.2"/>
        <line x1="4" y1="25" x2="10" y2="25" stroke="#fc6c2b" stroke-width="1"/>
        <line x1="28" y1="25" x2="36" y2="25" stroke="#fc6c2b" stroke-width="1"/>
        <circle cx="20" cy="12" r="4" stroke="#fc6c2b" stroke-width="1.5"/>
      </svg>
    </div>
    <div class="card__body">
      <div class="card__dash"></div>
      <span class="card__tag">{$a['tag']}</span>
      <h3 class="card__title"><a href="/article/{$a['slug']}/">{$a['title']}</a></h3>
      <p class="card__excerpt">{$a['excerpt']}</p>
      <div class="card__pills">{$pills_html}</div>
      <div class="card__footer">
        <span class="card__meta mono">{$a['read_min']} мин чтения</span>
        <button class="card__btn" aria-label="Читать статью">+</button>
      </div>
    </div>
  </article>
  HTML;
}

/**
 * Рендер TL;DR блока
 */
function render_tldr(array $points): void {
  $items = implode('', array_map(fn($p) => "<li>{$p}</li>", $points));
  echo <<<HTML
  <div class="tldr">
    <p class="tldr__label">TL;DR — суть за 30 секунд</p>
    <ul>{$items}</ul>
  </div>
  HTML;
}

/**
 * Безопасный вывод переменной
 */
function e(string $str): string {
  return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
