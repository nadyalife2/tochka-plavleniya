<?php
/**
 * @var array $article - The article array
 * @var bool $is_featured - Whether this is the featured (large) card
 */
$is_featured = $is_featured ?? false;
$level = $article['level'] ?? 'Новичок';
$tools = $article['tools'] ?? 'Паяльник, припой, флюс';
?>
<article class="border border-paper-border rounded-lg bg-card <?= $is_featured ? 'p-6 sm:p-7 shadow-sm' : 'p-4 sm:p-5' ?> transition-all hover:border-paper-border-dark flex flex-col h-full group relative">
  <!-- Image Frame 16:9 -->
  <div class="overflow-hidden rounded border border-paper-border bg-paper relative block w-full <?= $is_featured ? 'h-52 sm:h-60' : 'aspect-video' ?> mb-4">
    <img src="<?= e($article['image']) ?>" alt="<?= e($article['title']) ?>" class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-500" loading="lazy">
    <div class="absolute bottom-2 right-2 px-2 py-0.5 bg-paper/90 border border-paper-border text-[11px] font-mono text-ink-muted rounded backdrop-blur-sm">
      <?= e($article['tag']) ?>
    </div>
  </div>

  <!-- Meta Top -->
  <div class="flex items-center justify-between font-mono text-xs text-ink-muted pt-1 mb-2">
    <div class="flex items-center gap-2">
      <span class="text-ink font-medium px-2 py-0.5 bg-paper-border/30 rounded"><?= e($level) ?></span>
      <span class="text-ink-faint">·</span>
      <span><?= e($article['read_min']) ?> мин</span>
    </div>
  </div>

  <!-- Title & Excerpt -->
  <div class="space-y-2 flex-grow">
    <h2 class="<?= $is_featured ? 'text-2xl sm:text-[28px]' : 'text-lg sm:text-xl' ?> font-bold text-ink tracking-tight leading-[1.2]">
      <a class="hover:text-[#2563eb] hover:underline decoration-[#2563eb] decoration-1 underline-offset-4 transition-colors before:absolute before:inset-0" href="article.php?slug=<?= e($article['slug']) ?>">
        <?= e($article['title']) ?>
      </a>
    </h2>
    <p class="text-ink/85 font-serif <?= $is_featured ? 'text-[16px]' : 'text-sm' ?> leading-relaxed line-clamp-3">
      <?= e($article['excerpt']) ?>
    </p>
  </div>
  
  <!-- CTA / Tools needed -->
  <div class="mt-4 pt-4 border-t border-paper-border-light flex items-center justify-between font-mono text-xs text-ink-muted">
    <span class="truncate pr-4" title="Нужны: <?= e($tools) ?>">Нужны: <span class="text-ink font-medium"><?= e($tools) ?></span></span>
    <span class="text-[#2563eb] font-medium shrink-0 group-hover:underline">Урок →</span>
  </div>
</article>
