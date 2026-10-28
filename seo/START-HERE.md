# 🧭 SEO-конвейер «Точки Плавления» (START HERE)

Добро пожаловать в автономный SEO- и контентный конвейер проекта.
Все инструменты работают **локально, бесплатно, без лишнего расхода токенов и через интерактивный UI**.

Мастер-база данных: **SQLite `seo/data/seo.db`** (полный перенос из Excel `semantic_core_FULL_v11.xlsx`).
Вся работа теперь ведётся через визуальный веб-интерфейс дашборда:
👉 **Запуск интерактивного UI:** `python seo/scripts/server.py` → откройте **`http://127.0.0.1:8765`** (или откройте `seo/dashboard/index.html` прямо в браузере).

---

## 🗺️ Маршрутизатор: что делать прямо сейчас

| Ситуация | Куда смотреть | Команда / Действие в UI |
|---|---|---|
| **Хочу работать через интерактивный UI без Excel** | [Дашборд](dashboard/index.html) | `python seo/scripts/server.py` → браузер |
| **Хочу сделать резервную копию базы** | Кнопка в UI шапке | Кнопка **«💾 Создать бэкап БД»** |
| **Хочу запустить агента отдельно** | Вкладка «Центр агентов» | Кнопка запуска нужного агента с живым логом |
| **Хочу одобрить тему или оценить статью** | Вкладка «Темы из пробелов» / Модалка | Кнопка **«✓ Одобрить»** / **«✓ Утвердить (ОТК)»** |
| **Бот сбора конкурентов, фишек и SEO-слов** | Вкладка «Конкуренты и фишки» | Кнопка **«🤖 Запустить глубокий анализ»** |
| **Хочу посмотреть текстовый список очереди** | [`out/WORKLIST.md`](out/WORKLIST.md) (раздел 1) | `python seo/scripts/build_worklist.py` |
| **Хочу собрать статьи конкурентов (0 ₽, без ключей)** | [Агент 00-research](agents/00-research/AGENT.md) | `python seo/scripts/research_competitors.py --limit 60` |
| **Хочу оценить приоритетность тем 0–100** | [Агент 00-research](agents/00-research/AGENT.md) | `python seo/scripts/priority_matrix.py --top 20` |
| **Какие MCP подключить (Firecrawl, Tavily, Playwright)** | [00-research/MCP-SETUP.md](agents/00-research/MCP-SETUP.md) | YAML для `cordis.patch.yml` |
| **Нужно подготовить бриф и исследовать тему** | [Модуль 10 (Research)](modules/10-RESEARCH.md) | бриф по 13 пунктам |
| **Пишу статью, нужны правила моего голоса (Practico)** | [Модуль 50 (Voice)](modules/50-VOICE.md) | триада, ритм, стоп-фразы |
| **Оформляю структуру, H1/H2, сниппеты, FAQ** | [Модуль 20 (On-Page SEO)](modules/20-ONPAGE-SEO.md) | Answer First ≤40 слов |
| **Нужно расставить ссылки на другие статьи** | [Модуль 30 (Interlink)](modules/30-INTERLINK.md) | `python seo/scripts/link_candidates.py --id <ID>` |
| **Хочу проверить готовность статьи (0–100 баллов)** | [Модуль 40 (Scoring)](modules/40-SCORING.md) | `python seo/scripts/score_article.py --file <путь> --id <ID>` |
| **Статья готова, хочу внести её в общую таблицу** | Скрипт `add_article.py` | `python seo/scripts/add_article.py --file <путь> --id <ID>` |
| **Хочу, чтобы черновик заполнил ИИ по моим паттернам** | [Модуль 60 (AI Patterns)](modules/60-AI-PATTERNS.md) | контракт v1.1, боты |
| **Хочу прогнать статью через Google Gems** | [Модуль 70 (Gems)](modules/70-GEMS.md) | 3 готовых Gem'а |
| **Хочу увидеть общую картину (человечки, канбан)** | [Дашборд](dashboard/index.html) | `python seo/scripts/build_dashboard.py` |

---

## 🚀 Пошаговый цикл работы со статьёй

```
1. Подготовка и LSI     [Бесплатные ИИ: OpenCode Zen / freetap / DeepSeek Flash - 0 ₽]
       │                → python seo/scripts/build_worklist.py (очередь тем из WORKLIST.md)
       │                → сбор LSI-ключей, структуры H2/H3, драфтов FAQ (Модуль 10-RESEARCH.md)
       ▼
2. Написание текста     [Автор: Claude Sonnet или Gemini 3.8 Pro через Google Antigravity]
       │                → статья в авторском голосе Practico (Модуль 50-VOICE.md)
       │                → паттерны темы theme4: Answer First ≤40 слов, Warning, Steps
       │                → сохранение в папку `сами статьи/<ID>-<slug>.md`
       ▼
3. Перелинковка         [Скрипт link_candidates.py - 0 ₽]
       │                → python seo/scripts/link_candidates.py --id <ID> --write
       │                → Модуль 30-INTERLINK.md: 3–5 ссылок на опубликованные темы без 404
       ▼
4. Рецензирование       [Рецензент: Reviewer + slopmonster]
       │                → фактчекинг по ГОСТ/IPC, проверка реестра сплавов, безопасность
       │                → проверка на отсутствие шаблонных ИИ-зачинов («в современном мире»)
       ▼
5. Скоринг (0-100)      [Скрипт score_article.py - 0 ₽]
       │                → python seo/scripts/score_article.py --file "сами статьи/<файл>" --id <ID>
       │                → Модуль 40-SCORING.md: расчёт 6 категорий и отчёт в seo/out/scores/<ID>.md
       ▼
6. УТВЕРЖДЕНИЕ          [Шеф-редактор: ВЫ]
       │                → Вы смотрите отчёт скоринга: «Подходит» или «Что поправить»
       │                → при замечаниях — возврат автору на быструю правку
       ▼
7. Фиксация в таблице   [Скрипт add_article.py - 0 ₽]
       │                → python seo/scripts/add_article.py --file "сами статьи/<файл>" --id <ID>
       │                → автобэкап мастера, запись текста в лист `statiy`, обновление статуса
       ▼
8. Визуализация и ОТК   [Дашборд dashboard/index.html + WordPress]
                        → python seo/scripts/build_dashboard.py
                        → подтверждение в дашборде и кнопка «🛡️ Подтвердить проверку (ОТК)» в WP
```

---

## 📊 Структура папки `seo/`

```
seo/
├── START-HERE.md              ← этот навигатор
├── _SPEC.md                   ← внутренняя архитектурная спецификация
├── FREE-MODELS-AND-TOKENS.md  ← бесплатные нейросети и оптимизация токенов
├── modules/                   ← 7 модулей регламентов
│   ├── 10-RESEARCH.md         ← выбор темы, анализ выдачи, сбор фактов
│   ├── 20-ONPAGE-SEO.md       ← H1/H2, Answer First, сниппеты, FAQ, Schema
│   ├── 30-INTERLINK.md        ← правила перелинковки, анкоры, хабы
│   ├── 40-SCORING.md          ← 100-балльная приёмка статьи
│   ├── 50-VOICE.md            ← авторский голос Practico (единственный источник)
│   ├── 60-AI-PATTERNS.md      ← ИИ-паттерны, JSON-контракт, порядок проверки ботами
│   └── 70-GEMS.md             ← 3 Gem'а для Google Gemini
├── reference/                 ← быстрые справочники
│   └── glossary.md            ← термины, сплавы, температуры, J-STD-004
├── scripts/                   ← консольные утилиты автоматизации
│   ├── excel_io.py            ← чтение/запись master-Excel с автобэкапом
│   ├── build_worklist.py      ← сборка очереди «что писать»
│   ├── link_candidates.py     ← подбор связей для статьи
│   ├── score_article.py       ← расчёт оценки 0–100 по 6 категориям
│   ├── add_article.py         ← внесение текста в Excel
│   └── build_dashboard.py     ← сборка HTML-дашборда с ролями
├── out/                       ← файлы для человека
│   ├── WORKLIST.md            ← читаемый список задач
│   ├── WORKLIST.csv           ← таблица задач для импорта
│   └── scores/                ← протоколы проверок статей
├── dashboard/                 ← локальная визуализация
│   └── index.html             ← визуальный канбан с человечками
└── data/                      ← машинный кэш
    ├── plan.json              ← нормализованный слепок плана
    └── _backup/               ← автокопии Excel перед каждой записью
```

---

## ⚡ Быстрые команды (шпаргалка)

```bash
# Обновить рабочий список задач
python seo/scripts/build_worklist.py

# Подобрать ссылки для статьи #16
python seo/scripts/link_candidates.py --id 16 --write

# Проверить качество статьи #15
python seo/scripts/score_article.py --file "сами статьи/15-pripoj-dlya-pajki-vidy.md" --id 15

# Добавить готовую статью в общую таблицу
python seo/scripts/add_article.py --file "сами статьи/15-pripoj-dlya-pajki-vidy.md" --id 15

# Обновить визуальный дашборд
python seo/scripts/build_dashboard.py
```
