# ТОЧКА ПЛАВЛЕНИЯ — Инженерный портал и техрегламенты пайки РЭА

[![CI / Quality Gate](https://github.com/nadyalife2/tochka-plavleniya/actions/workflows/ci.yml/badge.svg)](https://github.com/nadyalife2/tochka-plavleniya/actions/workflows/ci.yml)

Лабораторный справочник, калькуляторы термопрофилей и библиотека технических регламентов монтажа электронных модулей по стандартам IPC-A-610 и ГОСТ.

---

## 🏗️ Архитектура проекта

* **`theme4/`** — действующая тема WordPress (v4.0.0). Эстетика Editorial Lab: бумажный холст, янтарный акцент `#FFC700`, паспорта техпроцессов, калькуляторы, строгая дедупликация DOM-якорей.
* **`wp-content/plugins/tchp-content/`** — контентное ядро: паспорт статьи, оснастка, FAQ, источники, канонические сплавы, редакционная проверка (ОТК) и защита от рассинхронизации по `expected_hash`.
* **`schemas/`** — контракты данных: [`article-contract-v1.schema.json`](schemas/article-contract-v1.schema.json) и эталонный [`article-example-v1.json`](schemas/article-example-v1.json).
* **`tests/`** — мастер-раннер, mock-WP тесты, Python unit-тесты и раннеры пакетов.
* **`tools/`** — сборка дистрибутивов, генерация статей и доставка черновиков (`delivery_cli.py`).
* **`docs/`** — навигатор [`docs/START_HERE.md`](docs/START_HERE.md), 4 тематических гида, стратегия роста, патентный трек и верификация.
* **`prompts/`, `gem-knowledge/`, `media/`, `semantic_core.xlsx`** — промпты, редполитика и голос, фирменная графика, семантическое ядро.

---

## 🧪 Тестирование и CI/CD

GitHub Actions ([`.github/workflows/ci.yml`](.github/workflows/ci.yml)) запускает мастер-раннер
[`tests/run-all-tests.py`](tests/run-all-tests.py). Локально — тот же прогон:

```bash
python -m pip install jsonschema
python tests/run-all-tests.py
```

Раннер выполняет **7 шагов**:

1. **JSON Schema**: Draft-07 мета-схема, эталонный контракт, реестр канонических сплавов, негативные тесты.
2. **PHP Lint**: все PHP-файлы `theme4/`, плагина `tchp-content` и тестового набора.
3. **Mock-WP бизнес-логика**: 43 проверки, 10 сценариев (REST-валидация, `expected_hash`/409, инвалидация ОТК, DOM-якоря, сплавы, владелец Schema.org, паритет отпечатков PHP↔Python).
4. **Python unit**: delivery CLI и генератор статей.
5. **Docs links**: проверка относительных Markdown-ссылок в README, `docs/` и `SKILL.md`.
6. **Package build**: сборка `theme4.zip` и `tchp-content.zip`.
7. **Byte-parity**: побайтовая сверка каждого файла архивов с исходниками.

Живой REST-тест `tools/test_live_wp_integration.py` требует WordPress, URL и application password
пользователя автоматизации — в CI он не запускается. Подробности: [`tests/README.md`](tests/README.md).

---

## 📚 Документация и стратегия

* [`docs/START_HERE.md`](docs/START_HERE.md) — навигатор по проекту.
* [`docs/ROADMAP-TWO-SITES.md`](docs/ROADMAP-TWO-SITES.md) — мастер-план двух сайтов до дохода.
* [`docs/STRATEGY-TWO-BRANDS.md`](docs/STRATEGY-TWO-BRANDS.md) — понятная стратегия двух брендов.
* [`docs/BLOG-PLAN.md`](docs/BLOG-PLAN.md) — простой план блога без бизнес-давления.
* [`docs/LAUNCH-20-ARTICLES.md`](docs/LAUNCH-20-ARTICLES.md) — 20 статей и 5 первых видео.
* [`docs/business-model-two-sites.md`](docs/business-model-two-sites.md) — Lean Canvas и потоки дохода.
* [`docs/unit-economics-two-sites.md`](docs/unit-economics-two-sites.md) — финмодель и сценарии.
* [`docs/invention-device.md`](docs/invention-device.md) — ТРИЗ, prior art и черновик формулы.
* [`docs/STRATEGY-90-DAY.md`](docs/STRATEGY-90-DAY.md) — быстрый трек до первого дохода.
* [`docs/STRATEGY-6-MONTH.md`](docs/STRATEGY-6-MONTH.md) — бюджет, второй сайт, гранты, устройство.
* [`docs/STRATEGY-2026.md`](docs/STRATEGY-2026.md) — портфель и долгий горизонт.
* [`docs/PATENT-SEARCH-TEG-FAN.md`](docs/PATENT-SEARCH-TEG-FAN.md) — предварительный патентный поиск.
* [`docs/DEVICE-MOUNT-BOM.md`](docs/DEVICE-MOUNT-BOM.md) — крепление, BOM и испытания прототипа.
* [`docs/FACELESS-VIDEO-KIT.md`](docs/FACELESS-VIDEO-KIT.md) — съёмка без лица, монтаж, Gemini.
* [`docs/VERIFICATION.md`](docs/VERIFICATION.md) — как прогнать проект целиком и что считается «зелёным».
* [`docs/SKILLS-INVENTORY.md`](docs/SKILLS-INVENTORY.md) — все установленные и доступные скиллы.
* [`docs/AUDIT-FULL-PASS.md`](docs/AUDIT-FULL-PASS.md) — полный аудит, скиллы по этапам и список правок.

---

## 📦 Дистрибутивные пакеты

Для установки на WordPress используются готовые архивы:

* **`theme4.zip`** — тема (Внешний вид → Темы).
* **`tchp-content.zip`** — плагин полей и регламента (Плагины → Добавить новый).

Порядок установки и проверки: [`docs/02-wordpress-setup/GUIDE.md`](docs/02-wordpress-setup/GUIDE.md).
Требования к теме: WordPress 6.4+, PHP 8.0+.
