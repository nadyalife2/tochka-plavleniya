# SEO-контур портала «Точка Плавления»

Автономная система планирования тем, перелинковки, скоринга (0–100) и приёмки статей.
Работает локально поверх мастер-таблицы `semantic_core_FULL_v11.xlsx`.

👉 **Главная точка входа:** читайте [START-HERE.md](START-HERE.md).

## Быстрый старт:

```bash
# 1. Посмотреть очередь «что писать»
python seo/scripts/build_worklist.py
# Результат в seo/out/WORKLIST.md

# 2. Подобрать перелинковку для статьи
python seo/scripts/link_candidates.py --id 16 --write

# 3. Оценить качество текста (0–100)
python seo/scripts/score_article.py --file "сами статьи/15-pripoj-dlya-pajki-vidy.md" --id 15

# 4. Открыть визуальный дашборд с человечками
python seo/scripts/build_dashboard.py
# Откройте seo/dashboard/index.html в браузере
```

## Документация:

- [START-HERE.md](START-HERE.md) — единый навигатор по шагам
- [FREE-MODELS-AND-TOKENS.md](FREE-MODELS-AND-TOKENS.md) — бесплатные модели (Zen/freetap) и экономия токенов
- [modules/](modules/) — 7 рабочих модулей (Research, OnPage, Interlink, Scoring, Voice, AI-Patterns, Gems)
- [reference/glossary.md](reference/glossary.md) — инженерный словарь сплавов и понятий
