# SEO-контур «Точки Плавления» — внутренняя спецификация

> Служебный документ для агентов и скриптов. Человеку читать `README.md` и `START-HERE.md`.

## 1. Источник истины

Единственный мастер — **SQLite-база `seo/data/seo.db`**.

Excel `semantic_core_FULL_v11.xlsx` больше не мастер. Он остаётся в репозитории как архив
исходного семантического ядра и используется только для двух операций: разового импорта
(`migrate_excel_to_db.py`) и ручного экспорта-обмена по просьбе человека. Скрипты конвейера
читают и пишут только БД (`db.py` → `get_connection()`).

Производные (можно удалить и пересобрать): `seo/data/plan.json`, `seo/out/*`,
`сами статьи/*.md`, `seo/dashboard/index.html`.

Правила доступа:

- запись в БД — через `db.py` и UI (`server.py`); прямые `UPDATE` из скриптов допустимы только в своих таблицах;
- **секреты в БД не хранятся**: API-ключи и пароли живут в `config/api-keys.local.yaml` (файл в `.gitignore`)
  и читаются через `keys.py`. `seo/data/seo.db` тоже версионируется, поэтому секретам там места нет;
- `semantic_core_FULL_v11.xlsx` в git не редактируем: расхождения с БД считаются ошибкой, а не «второй правдой».

Таблицы БД и их роль:

| Таблица | Роль | Ключевые колонки |
|---|---|---|
| `topics` | очередь статей + тексты + оценки | `id`, `title`, `slug`, `cluster`, `status`, `content_markdown`, `chars`, `score_total`, `seo_priority`, `review_status`, `factcheck_status`, `writer_mode` |
| `clusters` | кластеры и ключевики | `name`, `priority_rank`, ключевые темы, конкуренты, пробел |
| `links` | плановые и фактические связи | `source_id`, `target_id`, `anchor`, `status` |
| `competitors`, `competitors_proposed` | конкуренты и предложенные | `domain`, `niche`, `score`, `articles_count` |
| `topics_proposed` | темы из контентных пробелов | `title`, `cluster`, `source`, `evidence`, `hits`, `priority_score`, `status` |
| `raw_articles` | выгрузка страниц конкурентов | `url`, `domain`, `title`, `h1`, `content_type`, `cluster` |
| `briefs` | брифы (ТЗ) | `topic_id`, `intent`, `reader_pain`, `geo_snippet`, `eeat_facts`, `lsi`, `faq_json` |
| `keyword_metrics`, `keyword_metrics_history` | конкуренция по Мутагену | `keyword`, `strong`, `wordstat`, `tails`, `vital` |
| `wordstat_metrics`, `wordstat_history` | спрос по Вордстату | `phrase`, `total_count`, `top_json` |
| `voices` | авторские голоса Practico 1 / 2 | `id`, `name`, `system_prompt`, `model`, `is_active` |
| `jobs`, `agent_runs` | очередь фоновых задач и журнал прогонов | `job_type`, `target_id`, `status`, `log` |
| `backups` | журнал резервных копий | `filename`, `filepath`, `size_bytes`, `records_count` |

Ещё не мигрированы в БД (единственные оставшиеся листы Excel): `wp_export` (поля публикации —
в `topics` пока нет `wp_post_id`/`wp_url`/`wp_status`) и `image_generation` (промпты иллюстраций).
Это известный долг, а не действующее правило: новые поля добавляем в БД, а не в xlsx.

## 2. Канонические статусы

Единый словарь. В БД и в UI допустимы только эти значения (маппинг старых — в `reference/statuses.md`).

| Код | Русский | Значение |
|---|---|---|
| `backlog` | Не начато | тема в плане, работ нет |
| `brief` | ТЗ готовится | исследование идёт |
| `brief_ready` | ТЗ готово | бриф есть, можно писать |
| `writing` | Пишется | черновик в работе |
| `written` | Написано | текст есть, не проверен |
| `needs_fix` | Требует правок | возвращено редактором, обязательные замечания в `next_action` |
| `review` | На ревью | фактчек/голос/SEO проверяются |
| `scored` | Оценено | есть `score` ≥ порога |
| `ready` | Готово к публикации | все гейты пройдены |
| `published` | Опубликовано | в WordPress |
| `blocked` | Блокер | причина в `next_action` |

## 3. Оценка контента (100 баллов)

Шесть блоков; блокеры — отдельно.

| Блок | Вес | Проверки |
|---|---:|---|
| `intent` Интент и структура | 20 | Answer First ≤40 слов; H1 = интент; 4–6 H2; объём 5 500–7 000 знаков; 3–4 блока расширения |
| `facts` Факты и безопасность | 25 | паспорт числа; 2–4 источника ГОСТ/IPC/TDS; сплав из реестра; нет запрещённых советов (**блокер**) |
| `voice` Голос и читаемость | 15 | нет стоп-фраз; нет канцелярита; средняя длина предложения ≤ 20; абзацы 1–3; нет выдуманного опыта |
| `seo` SEO/GEO | 20 | title ≤ 60; description ≤ 160; 4–6 FAQ; schema/additionalProperty; LSI |
| `links` Перелинковка | 10 | 3–5 исходящих на существующие URL; естественные анкоры; нет ссылок на черновики |
| `ai` ИИ-риски | 10 | AI-паттерны ≤ порога; нет шаблонных зачинов/финалов; уникальность ≥ 85% |

Пороги: **< 60** — в доработку; **60–79** — черновик; **≥ 80** — к ОТК.
Любой блокер (опасный совет, выдуманная температура, несуществующий сплав) — стоп
независимо от суммы.

Реализация: `seo/scripts/score_article.py` → `seo/out/scores/<id>.json` + `.md`.

## 4. Модули (документация)

| Файл | Вопрос | Пользователь |
|---|---|---|
| `modules/10-RESEARCH.md` | какую тему брать, бриф | исследователь |
| `modules/20-ONPAGE-SEO.md` | как статья выглядит для поиска/ИИ | автор, SEO |
| `modules/30-INTERLINK.md` | куда ссылаться и чем | SEO |
| `modules/40-SCORING.md` | готова ли статья | редактор |
| `modules/50-VOICE.md` | как писать голосом Practico | автор |
| `modules/60-AI-PATTERNS.md` | что заполняет ИИ, кто проверяет | автор, бот |
| `modules/70-GEMS.md` | прогон через Google Gems | автор |

Каждый модуль: 150–250 строк, блоки «Когда читать», «Шаги», «Чек-лист», «Плохо/хорошо»,
«Команды/скрипты», «Связанные файлы». Ссылки только относительные.

## 5. Скрипты

Все скрипты работают из корня репозитория и читают БД, а не Excel.

Ядро данных и UI:

| Скрипт | Что делает | Вход → Выход |
|---|---|---|
| `db.py` | подключение к БД (WAL, `busy_timeout`), схема, авто-бэкап, `build_db_plan` | `seo.db` ↔ dict |
| `server.py` | локальный HTTP-сервер дашборда (`127.0.0.1:8765`), REST-API, очередь фоновых задач | БД + `o*/` → JSON для UI |
| `build_dashboard_ui.py` | сборка `seo/dashboard/index.html` (канбан, инспектор, вкладки) | БД → один HTML-файл |
| `migrate_excel_to_db.py` | разовая миграция xlsx → SQLite | `semantic_core_FULL_v11.xlsx` → `seo.db` |
| `excel_io.py` | legacy-мост: константы статусов и путей, чтение/запись xlsx для обмена | xlsx ↔ dict |

Конвейер:

| Скрипт | Что делает | Вход → Выход |
|---|---|---|
| `build_worklist.py` | очередь «что писать», сводка, перелинковка | БД → `WORKLIST.md/.csv`, `data/plan.json` |
| `generate_brief.py` | ТЗ для автора: паспорт, спрос, реальные запросы, стандарты, ссылки, скелет | `--id` → бриф (JSON/текст) |
| `link_candidates.py` | кандидаты связей для статьи | `--id` → `out/links-<id>.md` |
| `priority_v2.py` | приоритет темы 0–100 (спрос, конкуренция, интент, готовность) | БД → `seo_priority` |
| `competition_score.py` | бесплатная локальная оценка конкуренции 0–100 | БД → балл |
| `wordstat.py` | частотность Вордстат (Yandex AI Studio, бесплатно) | `--check`/`--batch` → `wordstat_metrics` |
| `mutagen.py` | конкуренция, хвосты, ставки Директа (0,30 ₽/фраза) | `--check`/`--batch` → `keyword_metrics` |
| `score_article.py` | оценка 0–100 по 6 блокам | `--file`/`--id` → `out/scores/*` |
| `canonical_alloys.py` | канонические ID сплавов и сверка с реестром | `--json`/`--check` |
| `package_contract.py` | упаковка статьи по схеме v1.1 + паспорт | `--id` → контракт + валидация |
| `writer_practico.py` | генерация статьи голосами Practico 1/2 | `--id --voice --provider` → файл + БД |
| `sync_all_texts_to_db.py` | синхронизация markdown с БД | `сами статьи/*.md` → `topics` |
| `topics_from_gaps.py` | темы из пробелов конкурентов | БД → `topics_proposed` |
| `research_competitors.py`, `discover_competitors.py`, `bot_competitor_analyzer.py` | сбор и разбор конкурентов | сайты → `competitors*`, `raw_articles` |
| `run_pipeline.py` | сквозной прогон темы по этапам | `--id` → лог этапов |

Запуск: `python seo/scripts/build_worklist.py` (работает из корня репозитория).

## 6. Правила для агентов

1. Мастер — SQLite `seo/data/seo.db`; не создавать «вторую правду» в CSV/Markdown/Excel.
   Excel — только импорт-экспорт по просьбе человека, правки в нём не накапливаются.
2. Любая правка данных конвейера — через БД (UI `server.py` или скрипты на `db.get_connection()`)
   с резервной копией. Ручные `UPDATE` в обход `db.py` запрещены.
3. Секреты (API-ключи, пароли приложений WordPress, OAuth-токены) — только в
   `config/api-keys.local.yaml` (вне git) через `keys.py`. В `seo.db` и в git их не писать.
4. Статусы — только из словаря §2.
5. Ссылки — только на существующие/опубликованные страницы; иначе «отложенная связь».
6. Числа (температуры, сплавы) — только из `theme4/data/materials-registry.php` и стандартов.
7. Голос — `modules/50-VOICE.md`; споры решает человек.
8. Никаких коммитов в `main`; только ветка `feat/*` или `fix/*` + PR.

## 7. Экономия токенов

- Не читать БД и Excel целиком: `build_worklist.py` отдаёт готовые `WORKLIST.md`/`WORKLIST.csv`,
  `generate_brief.py --id` — готовое ТЗ по одной теме.
- Один модуль за раз, только по маршруту `START-HERE.md`.
- Модели: бесплатные (`dsh-freetap`, OpenCode Zen) — черновики и рутина; Google Antigravity (Pro),
  Oxion (Claude/Gemini) и DeepSeek — финальные проверки и тексты. Правила — `MODEL-ROUTING.md`.
- MCP: только `firecrawl`, `yandex-webmaster`, `yandex-metrika`, `playwright` (см. `mcp.recommended.json`).
- Wordstat — CLI (`scripts/wordstat.py`), не MCP: схемы инструментов не висят в контексте.
