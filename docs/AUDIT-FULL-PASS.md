# Полный прогон и аудит проекта — 19–20.10.2026

> Ветка: `fix/full-pass-2026-10`. Основная ветка локально возвращена на `c4fa115`.
> Документ фиксирует, какие скиллы применялись, что проверено и что исправлено.

## 1. Скиллы по этапам

| Источник | Скилл | Этап | Как применён |
|---|---|---|---|
| Каталог сессии DSH | `tchp-venture-pipeline` | планирование прогона, гейты | режимы `gates`/`site`/`invent`, инварианты «один сайт», «патент до публикации» |
| Каталог сессии DSH | `tchp-editorial-standards` | контент и контракты | сверка сплавов, температур, SHA-256, DOM-якорей, JSON-LD |
| Каталог сессии DSH | `build-partner` | формат скиллов | `validate-skill` для `tchp-venture-pipeline` → `[PASS]` |
| Проект `.agents/skills` | `solder-researcher`, `solder-reviewer`, `solder-seo-planner` | аудит инструкций | прочитаны как чек-листы структуры, фактов, SEO-кластеров |
| Проект `.agents/skills` | `solder-wordpress-publisher` | деплой-гайды | сверка шагов установки темы/плагина |
| Пользовательские каталоги | `positioning-angles`, `seo-strategy`, `keyword-research` | аудит стратегии | проверка офферов, кластеров, монетизации в `ROADMAP-TWO-SITES` |
| FindSkills | `jtbd-knowledge-skill` | JTBD | установлен единственный verified/high (20 КБ SKILL.md) |
| FindSkills | community-кандидаты | бизнес-анализ | проверены метаданные; 10 из 11 — medium/community без SKILL.md, не устанавливались |
| Проект `.agents/skills` | `tchp-business-model`, `tchp-unit-economics`, `tchp-invention-patent` | бизнес-анализ | созданы через шаблон `build-partner`, `validate-skill` → PASS |

## 2. Что прогнано

```bash
python -m pip install -r requirements.txt
python tests/run-all-tests.py
```

7 шагов: JSON-схемы → PHP lint → mock-WP → Python unit → docs-links → сборка → byte-parity.

Результат на ветке:

* `SUMMARY: 43 / 43 tests passed.`
* Python unit — passed;
* `[PASS] all relative markdown links resolve` (48 файлов);
* `theme4.zip` — 162 файла, 1 388 366 байт, byte-parity;
* `tchp-content.zip` — 1 файл, 14 642 байта, byte-parity;
* финал: `ALL TESTS & INTEGRITY CHECKS PASSED SUCCESSFULLY!`.

Бизнес-анализ: [`business-model-two-sites.md`](business-model-two-sites.md),
[`unit-economics-two-sites.md`](unit-economics-two-sites.md),
[`customer-interviews-guide.md`](customer-interviews-guide.md),
[`invention-device.md`](invention-device.md).

## 3. Что нашёл аудит инструкций

1. `README.md` и `tests/README.md` обещали 4 этапа и 9 сценариев — фактически 7 шагов и 10 сценариев.
2. `PROJECT_OVERVIEW.md` ссылался на удалённый `site2/`, тему `wp-content/themes/tchp/` и медную палитру.
3. `start_server.ps1` / `.bat` запускали несуществующую `site2/`.
4. Гайды ссылались на `theme4-fixed.zip` / `tchp-content-fixed.zip`, хотя правки уже в канонических архивах.
5. PHP lint проверял только 5 файлов вместо всех PHP темы/плагина.
6. Не было проверки ссылок и единого `docs/VERIFICATION.md`.
7. `.gitignore` содержал мёртвые правила `site2`/`theme3`.
8. GitHub Actions реагировал только на `main`, а не на ветки/PR.
9. Не было зафиксированного правила «не коммитить в main» — из-за этого коммит с фиксами оказался в `main`.

## 4. Что исправлено и добавлено

* Интегрированы правки `theme4` v4.0.0 и плагина `tchp-content` 1.1.1; пересобраны архивы.
* `README.md`, `PROJECT_OVERVIEW.md`, `tests/README.md`, `docs/02`, `docs/03` синхронизированы с фактическим кодом.
* `DESIGN.md` / `PRODUCT.md` переведены на жёлтую палитру `#FFC700` и холст `#F6F6F6`.
* `start_server.*` переведены на `theme4/router.php`.
* `tests/run-all-tests.py`: 7 шагов, lint всех PHP, docs-links.
* `tools/check_docs_links.py`: проверка относительных Markdown-ссылок.
* `requirements.txt`: закреплённый `jsonschema>=4,<5`.
* `.github/workflows/ci.yml`: запуск на всех ветках/PR, установка из `requirements.txt`.
* `AGENTS.md` и `CLAUDE.md`: правило «main защищён, работа только в ветке».
* `docs/VERIFICATION.md` и этот отчёт.

## 5. Осталось за пределами гейта

* Живой REST-тест `tools/test_live_wp_integration.py` — нужен WordPress и application password.
* Playwright-скриншоты 375/768/1024/1440 и клавиатурная навигация.
* Продакшн: снятие `noindex`, Rank Math, кэш, SSL, 404 уроков, скорость TTFB.
* Патентная экспертиза — предварительный поиск есть, юридическая оценка у поверенного.

## 6. Git-статус

* локальная `main` — `c4fa115` (возвращена);
* `fix/full-pass-2026-10` — коммиты `53252aa` (интеграция правок) и `79580d8` (docs-чистка);
* `origin/main` уже содержит `53252aa`; для возврата удалённой `main` нужен revert-коммит или force-push по решению владельца.
