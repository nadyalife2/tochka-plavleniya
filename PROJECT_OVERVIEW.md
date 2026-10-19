# 🔬 Проект «Точка Плавления» (ТЧП) — Концепция, цели и архитектура

> **Слоган:** журнал, лаборатория и интерактивный верстак инженера-электронщика.

---

## 🎯 1. Что это за проект

**«Точка Плавления» (ТЧП)** — экспертный медиа-портал, интерактивная база знаний и
инженерный верстак по пайке электроники:

* **Технологии монтажа:** SMD, BGA, THT, микропайка под микроскопом, гибкие шлейфы.
* **Материалы и химия:** флюсы (RMA, No-Clean, водосмывные по J-STD-004), припои
  (ПОС-61, SAC305, Розе/Вуда/Sn42Bi58), пасты, очистители.
* **Оборудование:** станции T12/JBC, термофены, нижний подогрев, микроскопы, дымоуловители.
* **Практика:** термопрофили, пайка без деламинации и перегрева, дефекты (мостики,
  непропай, трещины BGA-шаров).
* **Инструменты:** подбор флюса, реестр сплавов, диагностические квизы, калькуляторы.

---

## 🚀 2. Цели

### 2.1. Для аудитории
* **Инженеры и мастера сервисных центров:** таблицы, термопрофили, подбор расходников.
* **Начинающие и радиолюбители:** пошаговые руководства, шпаргалки, ESD и безопасность.

### 2.2. Для поиска и ИИ-ассистентов (SEO + GEO)
* JSON-LD Schema.org: `TechArticle`, `HowTo`, `Dataset`, `FAQPage`, `BreadcrumbList`.
* `llms.txt` / `llms-full.txt` с фактологической структурой для ИИ-краулеров.
* Точные физико-химические данные в машинночитаемом виде, `citation` и `reviewedBy` из
  паспорта статьи.

### 2.3. Для бизнеса
* Авторитетное нишевое медиа с органическим трафиком.
* Партнёрские ссылки, спонсорские обзоры, PDF-техкарты и B2B-обучение.
* Стратегия роста: [`docs/ROADMAP-TWO-SITES.md`](docs/ROADMAP-TWO-SITES.md).

---

## ⚙️ 3. Архитектура и стек

### 3.1. Визуальный стиль (Editorial Lab)
* Холст `#F6F6F6` (светлая) / `#121212` (тёмная), карточки `#FFFFFF` / `#1C1C1E`,
  акцент `#FFC700`, контуры `#111111`.
* Типографика: Space Grotesk / IBM Plex Sans, Newsreader, JetBrains Mono, Caveat.
* Единый источник токенов: [`theme4/assets/css/tokens.css`](theme4/assets/css/tokens.css).
* Цель — WCAG AA/AAA по тексту; контраст проверяется на реальных парах, а не декларативно.
* Без градиентов, glassmorphism, неона и эмодзи в UI-иконках.

### 3.2. Платформа
* **Тема WordPress `theme4`** ([`theme4/`](theme4/)): независимая, без визуальных
  конструкторов; PHP-шаблоны, Tailwind `build.css`, `base.css`, `article.css`, `tokens.css`.
* **Плагин `tchp-content`** ([`wp-content/plugins/tchp-content/`](wp-content/plugins/tchp-content/)):
  метаполя, REST-валидация, отпечаток SHA-256, ОТК, GEO-поля.
* **Автономный стенд:** [`theme4/router.php`](theme4/router.php) + `start_server.ps1` —
  PHP built-in server без MySQL для предпросмотра темы.

### 3.3. Интерактивный верстак (8 секций)
1. Терморежим (`temp`).
2. Подбор жал и теплоёмкость (`iron`).
3. Чек-лист предполётного контроля (`checklist`).
4. Дерево диагностики брака (`defect`).
5. Калькулятор флюса/пасты (`calculator`).
6. Селектор флюса по активности (`flux-selector`).
7. Расход припоя/пасты (`solder-consumption`).
8. Реестр сплавов (`table`).

### 3.4. Контентный пайплайн
* Семантика: `semantic_core.xlsx`.
* Контракт статьи: [`schemas/article-contract-v1.schema.json`](schemas/article-contract-v1.schema.json).
* Генерация: [`tools/ai_article_generator.py`](tools/ai_article_generator.py),
  [`tools/generate_article_cli.py`](tools/generate_article_cli.py).
* Доставка: [`tools/delivery_cli.py`](tools/delivery_cli.py) — только `draft`/`pending`.
* Проверка: `tests/run-all-tests.py`, живой REST-тест — отдельно.

---

## 📁 4. Карта файлов

| Путь | Назначение |
| :--- | :--- |
| [`theme4/`](theme4/) | действующая тема WordPress v4.0.0 |
| [`wp-content/plugins/tchp-content/`](wp-content/plugins/tchp-content/) | контентное ядро и REST |
| [`schemas/`](schemas/) | контракты статьи и пример |
| [`tests/`](tests/) | мастер-раннер и тесты |
| [`tools/`](tools/) | сборка, генерация, доставка |
| [`docs/START_HERE.md`](docs/START_HERE.md) | навигатор по документации |
| [`docs/02-wordpress-setup/GUIDE.md`](docs/02-wordpress-setup/GUIDE.md) | установка и настройка WP |
| [`docs/03-editorial-publishing/GUIDE.md`](docs/03-editorial-publishing/GUIDE.md) | подготовка и публикация статьи |
| [`docs/04-research-voice-visuals/GUIDE.md`](docs/04-research-voice-visuals/GUIDE.md) | голос Practico и визуалы |
| [`docs/STRATEGY-90-DAY.md`](docs/STRATEGY-90-DAY.md) | быстрый трек до дохода |
| [`docs/PATENT-SEARCH-TEG-FAN.md`](docs/PATENT-SEARCH-TEG-FAN.md) | патентный поиск устройства |
| [`start_server.ps1`](start_server.ps1) / [`start_server.bat`](start_server.bat) | локальный предпросмотр темы |
