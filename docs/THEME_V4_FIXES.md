# theme4-fixed — что исправлено перед выгрузкой

Собран из `theme4/` (v4.0.0) **без изменения оригинальной папки**. Палитра, сетка,
типографика и визуальный язык не менялись: жёлтый `#FFC700`, холст `#f6f6f6`,
рамки `#111111`.

- Архив для загрузки: **`theme4-fixed.zip`** (162 файла, 1,32 МБ, внутри папка `theme4/`)
- Исходник для проверки: `theme4-fixed/`
- Оригинал `theme4/` и `theme4.zip` не тронуты (`git status` по `theme4` — 0 изменений)

## 1. Блокеры деплоя

| Файл | Правка |
|---|---|
| `includes/search-modal.php` | 31 ссылка `article.php?slug=…` заменена на `TCHP_BASE + "slug/"` (в WordPress такие URL давали 404). Добавлен focus trap: Tab больше не уходит за модал |
| `page.php` (новый) | Шаблон статических страниц. Без него WordPress отдавал любую страницу («О проекте», политика, контакты) через `index.php`, то есть главную |
| `search.php` (новый) | Результаты `/?s=`. Без него поиск WordPress показывал главную. Есть пустое состояние и пагинация |
| `functions.php` | Удалён дублирующий `wp_enqueue_style` для `tokens.css` и `headphones-style.css` — они уже подключены в `includes/header.php`. Двойной загрузки CSS больше нет |
| `index.php`, `article.php`, `category.php`, `includes/functions.php` | canonical/JSON-LD: домен через `home_url()` вместо хардкода; canonical статьи — на человеческий `/slug/`, а не `article.php?slug=`; при активном Rank Math тема не печатает второй canonical; убран фейковый canonical `/archive/` |
| `assets/js/workbench.js`, `assets/js/main.js` | 7 ссылок на статьи переведены на `window.TCHP_BASE` (добавлен в `includes/header.php`) |
| `includes/ui/article-card.php` | URL карточки через `tchp_get_article_url()` (компонент сейчас не подключён, но приведён к WP) |
| `includes/header.php` | Тёмная тема учитывает `prefers-color-scheme`, если пользователь ещё не выбирал вручную (было регрессией к текущему проду). Добавлены `og:url`, `og:locale`. Cache-busting `?v=filemtime` для fonts/build/base |
| `style.css` | `Requires at least: 6.4`, `Requires PHP: 8.0`, `Tested up to: 6.8` |

## 2. Полный проход по `text-accent` (175 вхождений)

Важное уточнение: в theme4 класс `.text-accent` переопределён в `assets/css/base.css`
на **blueprint navy `#0f4c81`** (на белом — 8.9:1, AAA), в тёмной теме — `#60a5fa`.
То есть 123 статических `text-accent` — это не жёлтый текст, они читаемы, менять их не нужно.

Проблемы были в другом:

1. **build.css собран без accent-утилит** (0 вхождений `accent`), поэтому варианты
   `hover:text-accent` (27), `group-hover:text-accent` (31), `group-open:text-accent` (5),
   `hover:border-accent` (51), `focus-visible:ring-accent` (39) были «мёртвыми» — правил нет.
   Восстановлены блоком в конце `base.css`: текст — navy/light-blue, рамки — жёлтые,
   focus-ring — navy. Палитра не менялась.
2. **Жёлтый как текст на светлом** — только 8 CSS-правил `color: var(--color-accent)`:
   hover ссылок в статье, активный пункт TOC, маркеры подписей и оснастки, badge-иконка,
   hover «в начало», keyframes temp-heat-pulse. Заменены на `var(--color-ink)`;
   жёлтые подчёркивания/рамки/заливки сохранены.
3. **Один случай текста на тёмном** — `#heatmap-tip-temp` на canvas-оверлее
   (`bg-[#14161a]`): navy был невидим. Получил класс `.text-accent-on-dark` (жёлтый).

Палитра, фоны, рамки, шрифты, отступы и композиция не изменены.

## 3. Что убрано из дистрибутива

- `docs/screenshots/*` (14 PNG от предыдущих аудитов) и `docs/headphones-visual-audit.md`
- два неиспользуемых PNG `assets/img/b69d….png` (1,48 МБ) и `b7beb….png` (1,39 МБ)
- `data/comments.json` (нигде не подключается)
- В `style.css`/шаблонах версия приведена к 4.0.0 (была смесь 2.2.0 и 4.0.0)

Итого: 5,70 МБ → 1,32 МБ, 177 → 162 файла. Добавлен `screenshot.png` (1200×900)
для карточки темы в админке.

## 4. Проверка

- `php -l`: 37 PHP-файлов, 0 ошибок
- GEO JSON-LD: unit-тест с WP-заглушками — JSON валиден, TechArticle с 7 PropertyValue,
  keywords, 3 хлебные крошки; FAQPage добавляется при заполненном FAQ
- `llms.txt` / `llms-full.txt`: 0 ссылок `/interactive#…`, все 7 инструментов ведут
  на `/interactive.php#…`
- автономный прогон через `php -S` + `router.php`: `/`, `/interactive.php`,
  `/luzhenie-provoda/`, `/category/praktika/`, `/privacy.php`, `/terms.php`,
  `/cookies.php`, `/robots.txt`, `/llms.txt` — все 200, без Fatal/Warning/Notice
- в HTML главной: по одному `<link>` на tokens/fonts/build/base/headphones (дублей нет),
  один canonical, `TCHP_BASE` присутствует, `prefers-color-scheme` присутствует,
  модал поиска и мобильный бар на месте
- `tchp-content.zip` не менялся, ставить отдельно (версия 1.1.0)

## 4.1. Паттерны для новых статей

Паттерны не переписывались — они уже были настроены в `theme4/functions.php`. Проверил и довёл:

- **15 паттернов** `tochka-plavleniya/*` (tldr-center, tldr-aside, safety-warning, pro-tip,
  ipc-note, media-text-left/right, compare-defect-norm, thermal-profile-svg, step-procedure,
  tools-needed, checklist-preflight, bom-table, faq-accordion, marginalia-note);
- категория `tochka-plavleniya` зарегистрирована до паттернов, регистрация — на `init`;
- разметка всех 15 сбалансирована (открывающих/закрывающих block-комментариев 0 расхождений);
- классы из паттернов реально есть в CSS: `callout-safety`, `callout-tldr`, `callout-aside`,
  `callout-pro`, `callout-ipc`, `compare-grid/card`, `step-card`, `tools-box`, `checklist-box`,
  `bom-table`, `faq-item`, `marginalia-box`, `thermal-profile`;
- **исправлено:** `add_editor_style()` подключал только `article.css`, а он использует
  301 `var(--color-…)` и Tailwind-классы из `build.css`. В редакторе паттерны и типографика
  выглядели «бесцветными». Теперь в редактор идут `tokens.css`, `fonts.css`, `build.css`,
  `base.css`, `article.css` — превью соответствует фронтенду.

Как создать статью: Записи → Добавить → вставка паттерна из категории «ТЧП — Инженерные блоки»;
метаполя (сплав, оснастка, стандарт, температура, FAQ, источники) — в блоке плагина
`tchp-content`; «проверено» и отпечаток — через плагин. Паттерны дают только контент,
метаполя они не заполняют — для этого нужен установленный `tchp-content.zip`.

## 4.2. GEO-поля и JSON-LD (машиночитаемые факты)

Поля, отвечающие за GEO, живут в плагине `tchp-content` — 19 метаполей, все с
`show_in_rest` (ИИ-краулеры и REST-клиенты их видят), плюс два метабокса в редакторе
(«Паспорт статьи» и «ОТК»):

- паспорт: `tchp_solder_alloy`, `tchp_temp_kind`, `tchp_temp_range`, `tchp_temp_peak`,
  `tchp_standard`, `tchp_standard_class`, `tchp_standard_description`, `tchp_tools`,
  `tchp_faq_items`, `tchp_sources`, `tchp_difficulty`, `tchp_reading_time`,
  `tchp_content_id`, `tchp_schema_version`;
- редакционка: `tchp_review_status`, `tchp_reviewer_id`, `tchp_reviewed_at`,
  `tchp_reviewed_content_hash`, `tchp_review_outdated`.

**Проблема была не в полях, а в их выводе.** В WordPress-режиме `single.php` печатал
минимальный TechArticle (заголовок/даты/автор), без припоя, температур, стандарта,
источников и ОТК. А если у Rank Math активен модуль rich-snippet (по умолчанию),
`tchp_should_render_theme_schema()` вообще отключал схему темы — и факты не попадали
ни в какой JSON-LD. `llms.txt`/`llms-full.txt` при этом содержали 15 битых ссылок
(`/interactive#…` вместо `/interactive.php#…` и несуществующую `/category/start/`).

Что сделано в `theme4-fixed`:

- `includes/functions.php`: `tchp_get_geo_facts()` собирает PropertyValue
  (припой, тип/диапазон/пик температуры, стандарт, класс, пояснение), `citation`
  из `tchp_sources`, `reviewedBy` из ОТК и `keywords`; `tchp_render_wp_article_schema()`
  собирает полный TechArticle + BreadcrumbList + FAQPage (если FAQ заполнен).
- `single.php`: вместо минимальной схемы выводится полный GEO JSON-LD.
- `functions.php`: фильтр `rank_math/json_ld` — если разметкой владеет Rank Math,
  те же `additionalProperty`, `citation`, `reviewedBy`, `keywords` добавляются
  прямо в его Article/TechArticle-узел.
- `llms.txt`, `llms-full.txt`: ссылки на инструменты (`/interactive.php#…`) и категорию
  «Старт и база» исправлены.

Заполнение остаётся ручным: припой/температуры/стандарт — в «Паспорте статьи»,
источники — для `citation`, статус «проверено» — для `reviewedBy`, FAQ — для FAQPage.
Плагин менять не нужно: он уже отдаёт все поля в REST.

Поля припоя и температур **не обязательны** — статьи бывают про оборудование, химию,
дефекты или организацию места. Если в селекте припоя выбрать «Универсальный / Обзор
регламента» (значение `universal`), припой в JSON-LD не выводится; температуры
добавляются только когда заполнены диапазон/пик. Остальные факты (стандарт, оснастка,
FAQ, источники, ОТК) выводятся независимо.

## 5. Порядок установки

1. Бэкап БД и файлов.
2. `tchp-content.zip` → Плагины → Добавить новый (если ещё не стоит).
3. `theme4-fixed.zip` → Внешний вид → Темы → Добавить новый → активировать.
   Если хостинг не принимает 1,32 МБ — FTP в `wp-content/themes/theme4`.
4. **Инструменты → «Импорт базы знаний „Точка Плавления“»** — создаст недостающие
   уроки/статьи (существующие по slug пропускает, ничего не перезаписывает).
   До импорта 8 из 10 уроков на главной будут 404.
5. Rank Math / Settings: снять «Попросить поисковики не индексировать», задать
   название сайта, шаблоны title/description, og:image; сохранить permalinks.
6. Смоук: главная, статья, `/interactive.php` (8 инструментов), Ctrl+K, рубрика,
   тег, новая страница («О проекте»), `/?s=флюс`, cookie-баннер, мобильный вид, печать.

## 6. Ограничения

- `page.php` и `search.php` — новые шаблоны; проверены линтом и структурой,
  но первый живой прогон лучше сделать на стейджинге/сразу после активации.
- Восстановленные accent-утилиты лежат отдельным блоком в конце `base.css`.
  Если `build.css` когда-нибудь пересоберут с Tailwind — блок оставить последним.
- Темы/подписи в `DESIGN.md` §2 и `PRODUCT.md` всё ещё описывают медный вариант
  (`#eb5211`), хотя бренд — жёлтый theme4. Документацию не трогал (по договорённости
  репозиторий не меняется), но синхронизировать стоит.
