<?php
/**
 * articles-data.php — Живая база знаний (12 статей ТЧП)
 */

$articles = [
    [
        'id'       => 1,
        'title'    => 'Температурные профили: как не перегреть плату за $300',
        'slug'     => 'temperaturnye-profili',
        'tag'      => 'Основы',
        'tag_key'  => 'basics',
        'excerpt'  => 'Разбираем теплоёмкость текстолита и строим правильную кривую нагрева для BGA-монтажа и сложных многослойных плат.',
        'read_min' => 8,
        'featured' => true,
        'date'     => '10 авг 2026',
        'author'   => 'Иван Пайкин',
        'icon'     => 'thermometer'
    ],
    [
        'id'       => 2,
        'title'    => 'SMD 0402 vs 0603: что выбрать для прототипа',
        'slug'     => 'smd-0402-vs-0603',
        'tag'      => 'SMD',
        'tag_key'  => 'smd',
        'excerpt'  => 'Плотность монтажа против ремонтопригодности — вечный спор инженера при разводке печатных плат.',
        'read_min' => 6,
        'featured' => true,
        'date'     => '05 авг 2026',
        'author'   => 'Мария Канифоль',
        'icon'     => 'chip'
    ],
    [
        'id'       => 3,
        'title'    => 'Гид по флюсам: RMA, NC и no-clean в шприце',
        'slug'     => 'gid-po-flyusam',
        'tag'      => 'Материалы',
        'tag_key'  => 'materials',
        'excerpt'  => 'Какой флюс оставить, а какой обязательно смыть — и чем это грозит схеме через 6 месяцев эксплуатации.',
        'read_min' => 12,
        'featured' => true,
        'date'     => '01 авг 2026',
        'author'   => 'Иван Пайкин',
        'icon'     => 'drop'
    ],
    [
        'id'       => 4,
        'title'    => 'Жала паяльника: T12 против JBC C245 на верстаке',
        'slug'     => 'zhala-payalnika',
        'tag'      => 'Инструменты',
        'tag_key'  => 'tools',
        'excerpt'  => 'Сравниваем скорость нагрева, реальный ресурс монолитных картриджей и стоит ли переплачивать за оригинал.',
        'read_min' => 7,
        'featured' => false,
        'date'     => '28 июл 2026',
        'author'   => 'Мария Канифоль',
        'icon'     => 'tools'
    ],
    [
        'id'       => 5,
        'title'    => 'BGA-реболлинг в домашних условиях на фене',
        'slug'     => 'bga-rebolling',
        'tag'      => 'SMD',
        'tag_key'  => 'smd',
        'excerpt'  => 'Пошаговая инструкция по замене и накатке шаров на чипах под микроскопом без дорогой инфракрасной станции.',
        'read_min' => 15,
        'featured' => false,
        'date'     => '20 июл 2026',
        'author'   => 'Иван Пайкин',
        'icon'     => 'chip'
    ],
    [
        'id'       => 6,
        'title'    => 'Сплав Розе и Вуда: когда спасение, а когда костыль',
        'slug'     => 'splav-roze',
        'tag'      => 'Материалы',
        'tag_key'  => 'materials',
        'excerpt'  => 'Сплавы с температурой плавления 94°C и 68°C. Как не оставить хрупкий сплав в рабочем контакте.',
        'read_min' => 5,
        'featured' => false,
        'date'     => '15 июл 2026',
        'author'   => 'Мария Канифоль',
        'icon'     => 'drop'
    ],
    [
        'id'       => 7,
        'title'    => 'Смывать или нет? Почему «нетребующий отмывки» флюс убивает цепи',
        'slug'     => 'zachem-smyvat-flyus',
        'tag'      => 'Материалы',
        'tag_key'  => 'materials',
        'excerpt'  => 'Разбираем паразитное сопротивление остатков флюса в высокочастотных и гигаомных цепях. Изопропил vs ультразвук.',
        'read_min' => 6,
        'featured' => false,
        'date'     => '10 июл 2026',
        'author'   => 'Иван Пайкин',
        'icon'     => 'drop'
    ],
    [
        'id'       => 8,
        'title'    => 'Замена конденсаторов на силовых платах: Low-ESR и разогрев полигонов',
        'slug'     => 'zamena-kondensatorov',
        'tag'      => 'Основы',
        'tag_key'  => 'basics',
        'excerpt'  => 'Практический ремонт БП и материнок: подбор конденсаторов по Low-ESR и как прогреть земляной полигон.',
        'read_min' => 10,
        'featured' => false,
        'date'     => '05 июл 2026',
        'author'   => 'Мария Канифоль',
        'icon'     => 'board'
    ],
    [
        'id'       => 9,
        'title'    => 'Посадка QFN и DFN без соплей: геометрия трафарета',
        'slug'     => 'montazh-qfn',
        'tag'      => 'SMD',
        'tag_key'  => 'smd',
        'excerpt'  => 'Работа с безвыводными микросхемами: как нанести пасту так, чтобы под брюхом не замыкали контакты.',
        'read_min' => 11,
        'featured' => false,
        'date'     => '01 июл 2026',
        'author'   => 'Иван Пайкин',
        'icon'     => 'chip'
    ],
    [
        'id'       => 10,
        'title'    => 'Инфракрасный низовой подогрев: собираем или покупаем',
        'slug'     => 'ik-stanciya',
        'tag'      => 'Инструменты',
        'tag_key'  => 'tools',
        'excerpt'  => 'Зачем нужен нижний подогрев платы до 120-150°C и почему без него тяжело работать с многослойным текстолитом.',
        'read_min' => 9,
        'featured' => false,
        'date'     => '25 июн 2026',
        'author'   => 'Иван Пайкин',
        'icon'     => 'thermometer'
    ],
    [
        'id'       => 11,
        'title'    => 'SAC305 против ПОС-61: почему бессвинцовка паяется иначе',
        'slug'     => 'sac305-vs-sn63pb37',
        'tag'      => 'Основы',
        'tag_key'  => 'basics',
        'excerpt'  => 'Наглядное сравнение смачиваемости, тугоплавкости и утомляемости припоя. Таблица свойств.',
        'read_min' => 6,
        'featured' => false,
        'date'     => '20 июн 2026',
        'author'   => 'Мария Канифоль',
        'icon'     => 'thermometer'
    ],
    [
        'id'       => 12,
        'title'    => 'Паяльная паста высохла: 5 способов восстановить свойства',
        'slug'     => 'payalnaya-pasta-oshibki',
        'tag'      => 'Материалы',
        'tag_key'  => 'materials',
        'excerpt'  => 'Как правильно хранить пасту в холодильнике, разбавлять флюсом и определять выгорание связки.',
        'read_min' => 8,
        'featured' => false,
        'date'     => '15 июн 2026',
        'author'   => 'Иван Пайкин',
        'icon'     => 'drop'
    ]
];

function get_article_by_slug($slug) {
    global $articles;
    foreach ($articles as $article) {
        if ($article['slug'] === $slug) return $article;
    }
    return $articles[0] ?? null;
}

function get_featured_articles($limit = 3) {
    global $articles;
    return array_slice(array_filter($articles, fn($a) => !empty($a['featured'])), 0, $limit);
}

function get_articles_by_tag($tag_key) {
    global $articles;
    if ($tag_key === 'all' || empty($tag_key)) return $articles;
    return array_values(array_filter($articles, fn($a) => $a['tag_key'] === $tag_key));
}
