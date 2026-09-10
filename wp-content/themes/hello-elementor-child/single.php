<?php
/**
 * Professional Single Post Template for Tochka Plavleniya (ТЧП)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Caveat:wght@400;700&family=Hanken+Grotesk:wght@400;600;700;800;900&family=IBM+Plex+Mono:wght@400;600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
	<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'min-h-screen flex flex-col antialiased bg-[#FCFAF7]' ); ?>>

<!-- Полоса прогресса чтения -->
<div id="reading-progress"></div>

<!-- Шапка -->
<header class="sticky top-0 z-50 bg-[#FCFAF7] border-b-2 border-black/90 backdrop-blur-md bg-opacity-95">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="flex items-center gap-2 group">
            <div class="w-9 h-9 bg-[#FC6C2B] border-2 border-black rounded flex items-center justify-center font-mono font-black text-white text-lg shadow-[2px_2px_0px_#000]">
                ТЧП
            </div>
            <div class="flex flex-col">
                <span class="font-['Hanken_Grotesk'] font-black text-lg tracking-tight leading-none"><?php bloginfo( 'name' ); ?></span>
                <span class="font-mono text-[10px] text-gray-500 font-bold uppercase tracking-wider">Журнал & Верстак Инженера</span>
            </div>
        </a>

        <nav class="hidden md:flex items-center gap-6 font-bold text-sm">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="hover:text-[#FC6C2B] transition-colors">Статьи</a>
            <a href="<?php echo esc_url( home_url( '/interactive/' ) ); ?>" class="hover:text-[#FC6C2B] transition-colors">Инструменты</a>
        </nav>
    </div>
</header>

<main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-12 w-full">

<?php
while ( have_posts() ) :
	the_post();
	$categories = get_the_category();
	$cat_name = ! empty( $categories ) ? $categories[0]->name : 'Пайка и Монтаж';
?>

    <!-- Хлебные крошки -->
    <nav class="flex items-center gap-2 text-xs font-mono font-bold text-gray-500 mb-6 flex-wrap">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="hover:text-[#FC6C2B]">Главная</a>
        <span>→</span>
        <span><?php echo esc_html( $cat_name ); ?></span>
        <span>→</span>
        <span class="text-black font-semibold truncate max-w-[280px] sm:max-w-md"><?php the_title(); ?></span>
    </nav>

    <!-- Заголовок и метаданные -->
    <header class="mb-8">
        <div class="flex flex-wrap items-center gap-2.5 mb-3">
            <span class="callout-badge bg-[#FFDCCB] text-[#8a3808] px-3 py-1 font-bold text-xs uppercase border border-black rounded">
                <?php echo esc_html( $cat_name ); ?>
            </span>
            <span class="bg-[#BDEEE2] text-[#0a5040] px-3 py-1 font-bold text-xs border border-black rounded">
                ⏱ 7 мин чтения
            </span>
            <span class="bg-[#FFF1A8] text-[#5e4700] px-3 py-1 font-bold text-xs border border-black rounded">
                Сложность: PRO
            </span>
        </div>

        <h1 class="text-2xl sm:text-4xl md:text-5xl font-black font-['Hanken_Grotesk'] tracking-tight leading-[1.15] text-[#1A1A1A] mb-4">
            <?php the_title(); ?>
        </h1>

        <div class="flex items-center gap-3 py-3 border-y-2 border-black/15 my-4">
            <div class="w-10 h-10 bg-[#FC6C2B] border-2 border-black rounded-full flex items-center justify-center font-bold text-white shadow-[2px_2px_0px_#000]">
                <?php echo esc_html( mb_substr( get_the_author(), 0, 1 ) ); ?>
            </div>
            <div>
                <div class="font-bold text-sm text-black"><?php the_author(); ?></div>
                <div class="text-xs text-gray-500 font-mono">
                    Опубликовано: <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
                </div>
            </div>
        </div>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-start">
        <article class="lg:col-span-8 text-[#1A1A1A] text-base leading-relaxed space-y-6 post-content">
            <?php the_content(); ?>
        </article>

        <aside class="lg:col-span-4 space-y-6">
            <div class="sketch-box p-5 bg-white toc-sidebar">
                <div class="flex items-center justify-between pb-3 mb-3 border-b-2 border-black">
                    <span class="font-['Hanken_Grotesk'] font-black text-base flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm text-[#FC6C2B]">format_list_bulleted</span>
                        Содержание статьи
                    </span>
                </div>
                <nav class="space-y-1">
                    <a href="#section-basics" class="toc-link">1. Общие сведения</a>
                    <a href="#section-simulator" class="toc-link">2. Симулятор термопрофиля</a>
                    <a href="#section-callouts" class="toc-link">3. Советы и ошибки</a>
                    <a href="#section-comparison" class="toc-link">4. Сравнительная таблица</a>
                    <a href="#section-faq" class="toc-link">5. FAQ</a>
                </nav>
            </div>
        </aside>
    </div>

<?php endwhile; ?>

</main>

<div id="copy-toast">Скопировано в буфер!</div>

<footer class="bg-[#1A1A1A] text-white border-t-4 border-black mt-16 py-8">
    <div class="max-w-7xl mx-auto px-4 text-center text-xs text-gray-400 font-mono">
        © <?php echo date( 'Y' ); ?> <?php bloginfo( 'name' ); ?>. Все права защищены.
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
