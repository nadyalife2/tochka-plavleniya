<?php
/**
 * Unified Header Navigation Bar
 * Точка Плавления
 */
$current_page = $current_page ?? '';
?>
<nav class="hidden md:flex items-center gap-5 text-[13.5px] text-ink-muted">
  <a class="<?php echo ($current_page === 'index') ? 'text-ink font-medium' : 'hover:text-ink transition-colors'; ?>" href="index.php#articles">Статьи</a>
  <a class="hover:text-ink transition-colors" href="interactive.php#calculator">Калькулятор флюсов</a>
  <a class="hover:text-ink transition-colors" href="interactive.php#table">Таблица припоев</a>
  <a class="<?php echo ($current_page === 'interactive') ? 'text-ink font-medium' : 'hover:text-ink transition-colors'; ?>" href="interactive.php">Инструменты</a>
</nav>
