<!-- Editorial Minimal Footer (Unified across all pages) -->
<footer class="w-full border-t border-paper-border bg-paper py-10 mt-12 text-ink-muted text-xs font-mono">
  <div class="max-w-[1140px] mx-auto px-5 sm:px-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
    <div class="space-y-1.5">
      <div class="flex items-center gap-2 text-ink font-semibold">
        <a class="logo text-sm hover:opacity-85 transition-opacity" href="index.php">
          ТОЧКА<span>.</span>ПЛАВЛЕНИЯ
        </a>
        <span class="text-ink-faint">·</span>
        <span class="text-[11px] font-normal text-ink-faint">Инженерный регламент v2.4</span>
      </div>
      <p class="text-[12px] text-ink-muted max-w-md">
        Инженерный справочник, регламенты поверхностного монтажа и открытая документация по пайке и теплофизике компонентов.
      </p>
      <div class="text-ink-faint text-[11px] pt-1">
        © <?php echo date('Y'); ?> ТОЧКА ПЛАВЛЕНИЯ. Все права защищены.
      </div>
    </div>
    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-[12px]">
      <a class="hover:text-ink transition-colors" href="index.php#articles">Статьи</a>
      <a class="hover:text-ink transition-colors" href="interactive.php#calculator">Калькулятор флюсов</a>
      <a class="hover:text-ink transition-colors" href="interactive.php#table">Таблица припоев</a>
      <a class="hover:text-ink transition-colors" href="interactive.php">Верстак</a>
      <a class="hover:text-ink transition-colors" href="privacy.php">Конфиденциальность</a>
      <a class="hover:text-ink transition-colors" href="terms.php">Соглашение</a>
    </div>
  </div>
</footer>

<!-- Cookie Consent Banner -->
<?php require_once __DIR__ . '/cookie-banner.php'; ?>
