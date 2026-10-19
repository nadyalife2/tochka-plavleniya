# Верификация проекта: полный прогон

> Как прогнать проект целиком и что считается «зелёным». Документ обновляется по факту
> прогона; результаты ниже — на 19.10.2026, ветка `fix/full-pass-2026-10`.

## 1. Быстрый полный прогон

```bash
python -m pip install -r requirements.txt
python tests/run-all-tests.py
```

Ожидаемый результат:

* 7 шагов: схемы → PHP lint → mock-WP → Python unit → docs-links → сборка → byte-parity;
* `SUMMARY: 43 / 43 tests passed.` в mock-WP;
* `Python Delivery CLI & Generator Unit Tests passed`;
* `[PASS] all relative markdown links resolve`;
* `theme4.zip` и `tchp-content.zip` побайтово совпадают с исходниками;
* финальная строка `ALL TESTS & INTEGRITY CHECKS PASSED SUCCESSFULLY!`.

Только сверка без пересборки:

```bash
python tests/run-all-tests.py --verify-only
```

## 2. Что именно проверяется

| Слой | Проверка | Где |
|---|---|---|
| Контракт | Draft-07, эталон, enum сплавов, негативные кейсы | `tests/run-all-tests.py` шаг 1 |
| PHP | синтаксис всех PHP темы, плагина и тестов | шаг 2 |
| Бизнес-логика | 43 проверки / 10 сценариев mock-WP | `tests/test-package-a1.php` |
| Python | delivery CLI и генератор | `tests/test_*_unit.py` |
| Docs | относительные Markdown-ссылки | `tools/check_docs_links.py` |
| Дистрибутивы | сборка и побайтовая сверка | шаги 6–7 |

## 3. Что в прогон НЕ входит

* **Живой WordPress REST-тест** — `tools/test_live_wp_integration.py`. Нужен стенд,
  URL и application password; запускается вручную на стейджинге.
* **Браузерный UI/UX-аудит** — Playwright/скриншоты, проверка 375/768/1024/1440.
* **Продакшн-настройки** — noindex, Rank Math, кэш, SSL, права хостинга.
* **Патентная экспертиза** — предварительный поиск есть, юридическая оценка — у поверенного.

## 4. Локальные особенности Windows

Под песочницей DSH запись в `.git/refs` и временные каталоги Python может блокироваться.
Для полного прогона на Windows используйте запуск с полным доступом к рабочей папке
(или CI/Linux). Это ограничение среды, а не проекта.

## 5. Связанные документы

* [`tests/README.md`](../tests/README.md) — матрица тестов и живой REST-тест.
* [`docs/fixes/`](fixes/) — исправления, поставляемые отдельными архивами.
* [`docs/START_HERE.md`](START_HERE.md) — навигатор по проекту.
* [`docs/02-wordpress-setup/GUIDE.md`](02-wordpress-setup/GUIDE.md) — установка на WordPress.
