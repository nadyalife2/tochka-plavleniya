# 🔗 Карта Сквозного Процесса «Точка Плавления» (Master Pipeline Map)

Этот документ представляет собой обзорную карту сквозного конвейера проекта: от формирования темы до доставки черновика в WordPress, редакционной вычитки и тестирования.

Детальные пошаговые руководства вынесены в специализированные разделы. Полный навигатор доступен в [START_HERE.md](file:///d:/сайт%20для%20пайки/docs/START_HERE.md).

---

## 🗺️ Генеральная Архитектурная Схема Конвейера

```mermaid
graph TD
    subgraph S1["ЭТАП 1: Темы, Исследование, Голос Practico и Визуал"]
        A[semantic_core: ядро тем] --> B[solder-researcher: сбор фактов по ГОСТ/IPC]
        B --> C[solder-writer: текст в голосе Practico]
        C --> D[Иллюстрации в стиле Bento-Sketch Hybrid]
    end

    subgraph S2["ЭТАП 2: Подготовка статьи и JSON-контракт"]
        D --> E[solder-reviewer: технический фактчекинг]
        E --> F[solder-seo-planner: сниппеты Rank Math и FAQ]
        F --> G["Валидация по schemas/article-contract-v1.schema.json"]
    end

    subgraph S3["ЭТАП 3: Безопасная Доставка в WordPress (Пакет Б)"]
        G --> H["delivery_cli.py: отправка черновика (status=draft)"]
        H --> I["tchp-content: REST-валидация и отпечаток SHA-256"]
        I --> J["Read-after-Write сверка и журнал delivery-journal.json"]
    end

    subgraph S4["ЭТАП 4: Редакционная Приёмка и Экспертиза"]
        J --> K["Вычитка шеф-редактором в WP-admin"]
        K --> L["POST /wp-json/tchp/v1/posts/{id}/verify (expected_hash)"]
        L -->|409 Conflict: расхождение| K
        L -->|200 OK: совпадение| M["Статус: verified (ОТК пройден)"]
        M --> N["Ручное решение редактора о публикации (Publish)"]
    end

    subgraph S5["ЭТАП 5: Разработка, Тестирование и Сборка"]
        O["tests/run-all-tests.py: локальные unit-тесты (38 assertions)"]
        P["tools/test_live_wp_integration.py: тесты на живом WP"]
        Q["tools/build_distribution_archives.py: сборка theme4.zip и tchp-content.zip"]
        R["GitHub Actions CI: автоматический прогон в облаке"]
        S["build-partner: петля самообучения (Improve System)"]
    end

    style S1 fill:#fbf7ee,stroke:#d4a373,stroke-width:2px
    style S2 fill:#f5eedc,stroke:#8c6d46,stroke-width:2px
    style S3 fill:#e8f4f8,stroke:#2b6cb0,stroke-width:2px
    style S4 fill:#fbf7ee,stroke:#2d2b28,stroke-width:2px
    style S5 fill:#f3f0e6,stroke:#706e6b,stroke-width:2px
```

---

## 🧭 Навигация по 4 руководствам системы

### 1. [Раздел 1: Разработка сайта, плагинов и автоматизации](file:///d:/сайт%20для%20пайки/docs/01-development/GUIDE.md)
* **Для чего:** Разработка PHP-кода темы `theme4` и плагина `tchp-content`, доработка CLI-скриптов доставки, поддержка схем контрактов.
* **Ключевые инструменты:** `tests/run-all-tests.py`, `tools/delivery_cli.py`, `tools/test_live_wp_integration.py`, `tools/build_distribution_archives.py`.
* **Результат работы:** Протестированный код, 38/38 unit-тестов, валидные ZIP-дистрибутивы с побайтовой сверкой.

### 2. [Раздел 2: Установка и настройка WordPress](file:///d:/сайт%20для%20пайки/docs/02-wordpress-setup/GUIDE.md)
* **Для чего:** Пошаговая инструкция по настройке хостинга, постоянных ссылок, рубрик, пользователей и паролей приложений (Application Passwords).
* **Таблица плагинов:** `tchp-content`, Rank Math SEO, кэширование, бэкапы.
* **Результат работы:** Проверенный и защищённый стенд WordPress, готовый принимать черновики через REST API.

### 3. [Раздел 3: Подготовка и публикация статьи](file:///d:/сайт%20для%20пайки/docs/03-editorial-publishing/GUIDE.md)
* **Для чего:** Выбор типа статьи, заполнение полей JSON-контракта, каталог контентных паттернов (TL;DR, Warning, Steps, Troubleshooting, FAQ), безопасная отправка черновика и подтверждение верификации `expected_hash`.
* **Ключевой контракт:** [`schemas/article-contract-v1.schema.json`](file:///d:/сайт%20для%20пайки/schemas/article-contract-v1.schema.json).
* **Результат работы:** Валидированный черновик в WordPress со статусом `verified`.

### 4. [Раздел 4: Темы, исследование, ваш голос и изображения](file:///d:/сайт%20для%20пайки/docs/04-research-voice-visuals/GUIDE.md)
* **Для чего:** Отбор тем из семантического ядра, сбор доказательной базы по ГОСТ/IPC, написание текста в авторском голосе Practico и генерация иллюстраций в стиле Bento-Sketch Hybrid.
* **Главные инварианты:** Запрет выдуманного опыта, формула *«термин → механизм → последствие»*, карандашная графика на бумажном фоне `#FBF7EE`.
* **Результат работы:** Готовый текст статьи и проверенные схемы.

---

## 🏛️ Единые источники истины проекта

| Ресурс | Файл источника |
|---|---|
| **JSON-контракт статьи** | [`schemas/article-contract-v1.schema.json`](file:///d:/сайт%20для%20пайки/schemas/article-contract-v1.schema.json) |
| **Канонический пример статьи** | [`schemas/article-example-v1.json`](file:///d:/сайт%20для%20пайки/schemas/article-example-v1.json) |
| **Реестр припоев и сплавов** | Функция `tchp_get_canonical_alloys()` в [`tchp-content.php`](file:///d:/сайт%20для%20пайки/wp-content/plugins/tchp-content/tchp-content.php) |
| **Редакционная верификация** | Метаполя и SHA-256 в [`tchp-content.php`](file:///d:/сайт%20для%20пайки/wp-content/plugins/tchp-content/tchp-content.php) |
| **Визуальная система** | Токены темы [`theme4/`](file:///d:/сайт%20для%20пайки/theme4/) и правила [`DESIGN.md`](file:///d:/сайт%20для%20пайки/DESIGN.md) |
