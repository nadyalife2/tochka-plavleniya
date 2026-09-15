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
        'difficulty' => 'Новичок',
        'required_tools' => ['Паяльник', 'Пинцет'],
        'featured' => true,
        'date'     => '10 авг 2026',
        'author'   => 'Иван Пайкин',
        'icon'     => 'thermometer',
        'image'    => 'https://lh3.googleusercontent.com/aida/AEtjO1XPXO_7vFBi0-sZRVk7MH6OetXskpHt5Xcf3Ip6nfHDDYM7qdO0nERNQaH_49PYrri281JQZUD0JhgzliwsR7F5Ks8GvSMY32dTxDUIHzZ_P5Drz6niq2ZSyIRirmXvsdrZExlqKeZ_11m0Vf64Fa9fYCMG9SMrAAg0F5hGzsceoEP1ajdrLph6LgFKfgF6aLS30BF8hJJkW20S0l03CIQZ4uc7pmSJ_MFPJyqCHL4KNonVuGSJXGX1rAg'
    ],
    [
        'id'       => 2,
        'title'    => 'SMD 0402 vs 0603: что выбрать для прототипа',
        'slug'     => 'smd-0402-vs-0603',
        'tag'      => 'SMD',
        'tag_key'  => 'smd',
        'excerpt'  => 'Плотность монтажа против ремонтопригодности — вечный спор инженера при разводке печатных плат.',
        'read_min' => 6,
        'difficulty' => 'Новичок',
        'required_tools' => ['Паяльник', 'Пинцет'],
        'featured' => true,
        'date'     => '05 авг 2026',
        'author'   => 'Мария Канифоль',
        'icon'     => 'chip',
        'image'    => 'https://lh3.googleusercontent.com/aida-public/AB6AXuAYi7MdtqY4d65d01YGA76Nb9UaAhDQ7QkjjWLOubwy90v1ERBKSlSZppwjkUC5jpi9kY1HdnQ5FHJR21MXRmgii-iF6CYNt9W4mNydzzoSQsgYTW_6wcWir8FOtb6ioNVbDgKW05RwHoKlGk90RvpJXFOV0hLsAaFjRijsi-njQlJ2_aoNGm_Q0M2v_1jGJM6d0HEoWjg1G2ov5xWDRXeKNl2a49NrjH0NmXjEyipzYiA6d1bDpnFu'
    ],
    [
        'id'       => 3,
        'title'    => 'Гид по флюсам: RMA, NC и no-clean в шприце',
        'slug'     => 'gid-po-flyusam',
        'tag'      => 'Материалы',
        'tag_key'  => 'materials',
        'excerpt'  => 'Какой флюс оставить, а какой обязательно смыть — и чем это грозит схеме через 6 месяцев эксплуатации.',
        'read_min' => 12,
        'difficulty' => 'Новичок',
        'required_tools' => ['Паяльник', 'Пинцет'],
        'featured' => true,
        'date'     => '01 авг 2026',
        'author'   => 'Иван Пайкин',
        'icon'     => 'drop',
        'image'    => 'https://lh3.googleusercontent.com/aida-public/AB6AXuDq0hH5xM39QDK1ZqCle8_Wk1nH4vsPEFi-qLOCIaH0mt5-NgDzrCsJkTQtjTFrNG8X9c4iXDGbdoYyxa9w9vsD5lhmodhFeYkAWkHXAFy0RloCfAkIN97lpnpFIzEqofnLwCZ_hIQ31MPvwYjr0YKml4OBALGI73SBp5g7T0cpRCK35Hp4utgd9ONG1OB3QN5BnZnqPkHXUtJPjiJgakBYuQhMka55il18DlWwDTGLTl5ZZrP1LM9R'
    ],
    [
        'id'       => 4,
        'title'    => 'Жала паяльника: T12 против JBC C245 на верстаке',
        'slug'     => 'zhala-payalnika',
        'tag'      => 'Инструменты',
        'tag_key'  => 'tools',
        'excerpt'  => 'Сравниваем скорость нагрева, реальный ресурс монолитных картриджей и стоит ли переплачивать за оригинал.',
        'read_min' => 7,
        'difficulty' => 'Новичок',
        'required_tools' => ['Паяльник', 'Пинцет'],
        'featured' => false,
        'date'     => '28 июл 2026',
        'author'   => 'Мария Канифоль',
        'icon'     => 'tools',
        'image'    => 'https://lh3.googleusercontent.com/aida-public/AB6AXuChE9QvE8LozVtk7VrKxyYI-SrHgrPhImpEKRwWLy8fCBhxDhgQJbz22eBoGpZ97AEmkDILNPB6nXPLwk4Pq62v8qVL76AJmtm7Y3M86EKjJYmx-yPJkCc-vq1O-rNVWNo_vypZINr_lZHpl4QKTYJnCzMarAZm24hHEabOepCGf90eCac2R0yEBmu4eXa8cQtRGKfECNtCPUpfGFTV4N28XpeOWVBdpVriBQO9ZdB_yuU45CFoJi1n'
    ],
    [
        'id'       => 5,
        'title'    => 'BGA-реболлинг в домашних условиях на фене',
        'slug'     => 'bga-rebolling',
        'tag'      => 'SMD',
        'tag_key'  => 'smd',
        'excerpt'  => 'Пошаговая инструкция по замене и накатке шаров на чипах под микроскопом без дорогой инфракрасной станции.',
        'read_min' => 15,
        'difficulty' => 'Новичок',
        'required_tools' => ['Паяльник', 'Пинцет'],
        'featured' => false,
        'date'     => '20 июл 2026',
        'author'   => 'Иван Пайкин',
        'icon'     => 'chip',
        'image'    => 'https://lh3.googleusercontent.com/aida/AEtjO1VPsrSrCUy54hlNvHq3-Bi3BKtsJ-HwVhUxUONjwD8ZW0JSYUhhCuE48K8uFAbVhm1l6z5bcfKlnWxsiJr8i-lI5dRkG6Iq2l-n00la6L6BC75KwJkw-kUiJ47GXICVho8XMATZw8RLlRI3tpSAkvmZT52hn5vSTCl2e71Md3Q3zGQ48s2w26JMOB_rnaw7fgtN-WHJUqkwlP4tX_D5R9x006Zys-oVO1_EwhYmZFvSU24W0G04DQtmqfA'
    ],
    [
        'id'       => 6,
        'title'    => 'Сплав Розе и Вуда: когда спасение, а когда костыль',
        'slug'     => 'splav-roze',
        'tag'      => 'Материалы',
        'tag_key'  => 'materials',
        'excerpt'  => 'Сплавы с температурой плавления 94°C и 68°C. Как не оставить хрупкий сплав в рабочем контакте.',
        'read_min' => 5,
        'difficulty' => 'Новичок',
        'required_tools' => ['Паяльник', 'Пинцет'],
        'featured' => false,
        'date'     => '15 июл 2026',
        'author'   => 'Мария Канифоль',
        'icon'     => 'drop',
        'image'    => 'https://image.qwenlm.ai/public_source/439a7a74-597a-4187-a71c-1abbf07ab726/1cb217151-e6ef-4d7e-bafa-ad806b9ccce8.png'
    ],
    [
        'id'       => 7,
        'title'    => 'Смывать или нет? Почему «нетребующий отмывки» флюс убивает цепи',
        'slug'     => 'zachem-smyvat-flyus',
        'tag'      => 'Материалы',
        'tag_key'  => 'materials',
        'excerpt'  => 'Разбираем паразитное сопротивление остатков флюса в высокочастотных и гигаомных цепях. Изопропил vs ультразвук.',
        'read_min' => 6,
        'difficulty' => 'Новичок',
        'required_tools' => ['Паяльник', 'Пинцет'],
        'featured' => false,
        'date'     => '10 июл 2026',
        'author'   => 'Иван Пайкин',
        'icon'     => 'drop',
        'image'    => 'https://image.qwenlm.ai/public_source/439a7a74-597a-4187-a71c-1abbf07ab726/15dd6dbde-db15-4e17-8fda-e3bb1a6afa8f.png'
    ],
    [
        'id'       => 8,
        'title'    => 'Замена конденсаторов на силовых платах: Low-ESR и разогрев полигонов',
        'slug'     => 'zamena-kondensatorov',
        'tag'      => 'Основы',
        'tag_key'  => 'basics',
        'excerpt'  => 'Практический ремонт БП и материнок: подбор конденсаторов по Low-ESR и как прогреть земляной полигон.',
        'read_min' => 10,
        'difficulty' => 'Новичок',
        'required_tools' => ['Паяльник', 'Пинцет'],
        'featured' => false,
        'date'     => '05 июл 2026',
        'author'   => 'Мария Канифоль',
        'icon'     => 'board',
        'image'    => 'https://image.qwenlm.ai/public_source/439a7a74-597a-4187-a71c-1abbf07ab726/13b081235-33ee-4b22-bc56-d705ec7d3cab.png'
    ],
    [
        'id'       => 9,
        'title'    => 'Посадка QFN и DFN без соплей: геометрия трафарета',
        'slug'     => 'montazh-qfn',
        'tag'      => 'SMD',
        'tag_key'  => 'smd',
        'excerpt'  => 'Работа с безвыводными микросхемами: как нанести пасту так, чтобы под брюхом не замыкали контакты.',
        'read_min' => 11,
        'difficulty' => 'Новичок',
        'required_tools' => ['Паяльник', 'Пинцет'],
        'featured' => false,
        'date'     => '01 июл 2026',
        'author'   => 'Иван Пайкин',
        'icon'     => 'chip',
        'image'    => 'https://image.qwenlm.ai/public_source/439a7a74-597a-4187-a71c-1abbf07ab726/1c1f6b86a-91ec-41fa-99fd-e3e2ca4548a9.png'
    ],
    [
        'id'       => 10,
        'title'    => 'Инфракрасный низовой подогрев: собираем или покупаем',
        'slug'     => 'ik-stanciya',
        'tag'      => 'Инструменты',
        'tag_key'  => 'tools',
        'excerpt'  => 'Зачем нужен нижний подогрев платы до 120-150°C и почему без него тяжело работать с многослойным текстолитом.',
        'read_min' => 9,
        'difficulty' => 'Новичок',
        'required_tools' => ['Паяльник', 'Пинцет'],
        'featured' => false,
        'date'     => '25 июн 2026',
        'author'   => 'Иван Пайкин',
        'icon'     => 'thermometer',
        'image'    => 'https://lh3.googleusercontent.com/aida-public/AB6AXuCz3EwM-yiiGuYAXSgycnw3IamGAkG45KS9762AyA9_0RrrlkIL1iHEpmka6kq84n028UCS8F5Fng_yDk-0oMCYSywnUmcfYDGuiLzBNmGEgPgYJ8ARSDGgl4mTd4p03iPJtH9_gFS7toreuV8Ud6t3Xmsz0WiLpKTjwhgbdra-vGgts3_XRkNXVfXR3rTGpqCpuw9nDyhp6gptDUKonPp2XCLgE7N0Aidcy7Pd8JuRU4TaqQqNEHPP'
    ],
    [
        'id'       => 11,
        'title'    => 'SAC305 против ПОС-61: почему бессвинцовка паяется иначе',
        'slug'     => 'sac305-vs-sn63pb37',
        'tag'      => 'Основы',
        'tag_key'  => 'basics',
        'excerpt'  => 'Наглядное сравнение смачиваемости, тугоплавкости и утомляемости припоя. Таблица свойств.',
        'read_min' => 6,
        'difficulty' => 'Новичок',
        'required_tools' => ['Паяльник', 'Пинцет'],
        'featured' => false,
        'date'     => '20 июн 2026',
        'author'   => 'Мария Канифоль',
        'icon'     => 'thermometer',
        'image'    => 'https://image.qwenlm.ai/public_source/439a7a74-597a-4187-a71c-1abbf07ab726/1dac4df59-7c91-4cd1-90bd-4eec5b1f3d16.png'
    ],
    [
        'id'       => 12,
        'title'    => 'Паяльная паста высохла: 5 способов восстановить свойства',
        'slug'     => 'payalnaya-pasta-oshibki',
        'tag'      => 'Материалы',
        'tag_key'  => 'materials',
        'excerpt'  => 'Как правильно хранить пасту в холодильнике, разбавлять флюсом и определять выгорание связки.',
        'read_min' => 8,
        'difficulty' => 'Новичок',
        'required_tools' => ['Паяльник', 'Пинцет'],
        'featured' => false,
        'date'     => '15 июн 2026',
        'author'   => 'Иван Пайкин',
        'icon'     => 'drop',
        'image'    => 'https://image.qwenlm.ai/public_source/439a7a74-597a-4187-a71c-1abbf07ab726/19085d6ab-8b91-4bb8-aab8-88de0d60a98e.png'
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
