# SEO-контур портала «Точка Плавления»

Автономная система планирования тем, перелинковки, скоринга (0–100) и приёмки статей.
Работает локально поверх мастер-базы **SQLite `seo/data/seo.db`**. Excel
`semantic_core_FULL_v11.xlsx` — только архив исходного ядра и ручной обмен, мастером не является.

👉 **Главная точка входа:** читайте [START-HERE.md](START-HERE.md).

## Быстрый старт:

```bash
# 1. Открыть дашборд (канбан, инспектор статьи, ТЗ, анализ запросов)
python seo/scripts/server.py
# → http://127.0.0.1:8765

# 2. Посмотреть очередь «что писать» в файлах
python seo/scripts/build_worklist.py
# Результат в seo/out/WORKLIST.md

# 3. Подобрать перелинковку для статьи
python seo/scripts/link_candidates.py --id 16 --write

# 4. Оценить качество текста (0–100)
python seo/scripts/score_article.py --file "сами статьи/15-pripoj-dlya-pajki-vidy.md" --id 15

# 5. Получить ТЗ для автора по теме
python seo/scripts/generate_brief.py --id 20
```

## Документация:

- [START-HERE.md](START-HERE.md) — единый навигатор по шагам
- [_SPEC.md](_SPEC.md) — внутренняя спецификация: источник истины, статусы, скрипты
- [MODEL-ROUTING.md](MODEL-ROUTING.md) — какие модели за что отвечают и где нужен VPN
- [FREE-MODELS-AND-TOKENS.md](FREE-MODELS-AND-TOKENS.md) — бесплатные модели и экономия токенов
- [modules/](modules/) — 7 рабочих модулей (Research, OnPage, Interlink, Scoring, Voice, AI-Patterns, Gems)
- [reference/glossary.md](reference/glossary.md) — инженерный словарь сплавов и понятий
