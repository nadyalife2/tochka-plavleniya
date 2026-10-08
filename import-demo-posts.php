<?php
/**
 * Скрипт прямого импорта базы знаний и регламентов «Точка Плавления»
 * Запуск: откройте https://tochka-plavleniya.ru/import-demo-posts.php в браузере (будучи авторизованным в WP админке).
 */

require_once __DIR__ . '/wp-load.php';

// Проверка прав: только администратор может запускать импорт
if (!current_user_can('manage_options')) {
    auth_redirect();
}

$theme_dir = get_template_directory();
if (file_exists($theme_dir . '/includes/demo-importer.php')) {
    require_once $theme_dir . '/includes/demo-importer.php';
} else {
    require_once __DIR__ . '/wp-content/themes/theme/includes/demo-importer.php';
}

$res = tchp_run_demo_import();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Импорт базы знаний — ТОЧКА ПЛАВЛЕНИЯ</title>
<style>
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #faf8f5; color: #2c2523; padding: 40px 20px; line-height: 1.6; }
.card { max-width: 680px; margin: 0 auto; background: #fff; border: 2px solid #2c2523; box-shadow: 6px 6px 0 #2c2523; border-radius: 8px; padding: 32px; }
h1 { margin-top: 0; font-size: 24px; color: #1c1917; }
.badge { display: inline-block; background: #fef3c7; color: #92400e; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 13px; margin-bottom: 16px; }
.stat-box { background: #fdfbf7; border: 1px solid #e7e0d3; padding: 16px; border-radius: 6px; margin: 20px 0; }
.btn { display: inline-block; background: #f59e0b; color: #1c1917; text-decoration: none; font-weight: bold; padding: 12px 24px; border-radius: 6px; border: 2px solid #2c2523; box-shadow: 2px 2px 0 #2c2523; margin-top: 10px; }
.btn:hover { background: #d97706; }
ul { margin: 8px 0; padding-left: 20px; }
</style>
</head>
<body>
<div class="card">
  <span class="badge">🔥 БАЗА ЗНАНИЙ ТОЧКА ПЛАВЛЕНИЯ</span>
  <h1>✅ Импорт успешно выполнен!</h1>
  <p>Все рубрики и инженерные статьи с полным набором из 15 паттернов Gutenberg успешно добавлены в базу данных вашего сайта.</p>
  
  <div class="stat-box">
    <strong>Итоги операции:</strong>
    <ul>
      <li>Создано рубрик: <strong><?= (int)$res['created_cats'] ?></strong></li>
      <li>Создано новых статей и регламентов: <strong><?= count($res['created_posts']) ?></strong></li>
      <li>Сохранено существующих статей: <strong><?= count($res['skipped_posts']) ?></strong> (ваши личные записи не тронуты)</li>
    </ul>
  </div>

  <p>
    <a href="/wp-admin/edit.php" class="btn">Перейти в админку к списку статей →</a>
  </p>
</div>
</body>
</html>
