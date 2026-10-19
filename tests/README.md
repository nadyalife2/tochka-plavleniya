# Автоматизированное тестирование и CI/CD // ТОЧКА ПЛАВЛЕНИЯ

В репозитории развернута система непрерывной интеграции (CI/CD) на базе **GitHub Actions**
и локального мастер-раннера.

---

## 1. Структура тестовой инфраструктуры

| Файл | Назначение |
| :--- | :--- |
| [`.github/workflows/ci.yml`](../.github/workflows/ci.yml) | GitHub Actions: прогон мастер-раннера при push/PR в `main` и вручную. |
| [`tests/run-all-tests.py`](run-all-tests.py) | Мастер-раннер: 6 шагов — схемы, PHP lint, mock-WP, Python unit, сборка, byte-parity. |
| [`tests/test-package-a1.php`](test-package-a1.php) | Mock-WP тесты: REST, верификация, отпечатки, DOM-якоря, владелец Schema.org. |
| [`tests/test_delivery_cli_unit.py`](test_delivery_cli_unit.py) | Unit-тесты delivery CLI. |
| [`tests/test_article_generator.py`](test_article_generator.py) | Unit-тесты генератора и нормализации контракта. |
| [`tools/test_live_wp_integration.py`](../tools/test_live_wp_integration.py) | Живой REST-тест против WordPress (не входит в CI, требует стенд и пароль). |
| [`tools/build_distribution_archives.py`](../tools/build_distribution_archives.py) | Сборка `theme4.zip` и `tchp-content.zip` с POSIX-путями. |
| [`schemas/article-contract-v1.schema.json`](../schemas/article-contract-v1.schema.json) | Draft-07 JSON-схема контракта статьи. |
| [`schemas/article-example-v1.json`](../schemas/article-example-v1.json) | Эталонный валидируемый пример. |

---

## 2. Что проверяет мастер-раннер (6 шагов)

### Шаг 1. JSON-схемы и контракт статьи
1. Draft-07 meta-schema: синтаксис `article-contract-v1.schema.json`.
2. Эталонный пример: `article-example-v1.json` соответствует схеме, `additionalProperties: false`.
3. Канонические сплавы: строгий enum `pos61`, `sn63pb37`, `pos40`, `sac305`, `sac405`,
   `sn42bi58`, `snbiago`, `roze`, `vuda`, `universal`.
4. Негативные тесты: лишнее поле и устаревший алиас `wood` отклоняются.

### Шаг 2. PHP Lint
* Рекурсивная проверка **всех** PHP-файлов `theme4/`, `wp-content/plugins/tchp-content/`
  и `tests/test-package-a1.php`.

### Шаг 3. Mock-WP бизнес-логика (10 сценариев, 43 проверки)
> Запускается в изолированном mock-окружении без MySQL.

1. **REST-валидация FAQ**: пустые строки и HTML, очищаемый до пустоты, отклоняются с `400`;
   ранее сохранённый FAQ не повреждается.
2. **Неизвестный припой**: `400 Bad Request`.
3. **Частичные PATCH-обновления**: отсутствие `tchp_faq_items` не затирает существующий FAQ.
4. **Защита `expected_hash`**: устаревший/несовпадающий хеш → `409 Conflict`, метаполя не меняются;
   отсутствие `expected_hash` → `400`.
5. **Успешная верификация**: валидный хеш → `200 OK`, фиксируются reviewer, дата, `reviewed_content_hash`.
6. **Инвалидация при правках**: изменение контента → `is_outdated = true`, `is_verified = false`,
   статус `in_review`.
7. **Уникальность DOM-якорей**: коллизии H2/секций/прочих `[id]` устраняются, все ID уникальны.
8. **Нормализация сплавов**: `wood → vuda`, `rose → roze`, `universal` сохраняется.
9. **Владелец Schema.org**: тема уступает разметку Rank Math при активном `rich-snippet`,
   иначе выводит резервную схему.
10. **Паритет отпечатков PHP↔Python**: `tchp_calculate_article_hash()` и
    `compute_canonical_fingerprint()` совпадают байт-в-байт при перестановке ключей,
    отсутствующих полях и разных языках значений.

### Шаг 4. Python unit-тесты
* `tests/test_delivery_cli_unit.py` — журнал доставки, ошибки, идемпотентность.
* `tests/test_article_generator.py` — нормализация, отказ от выдуманных температур,
  невалидные сплавы, контракт.

### Шаг 5. Сборка пакетов
* `theme4.zip` с корнем `theme4/`.
* `tchp-content.zip` с корнем `tchp-content/`.

### Шаг 6. Byte-parity
* Каждый файл архива сверяется с исходником побайтово; проверяется отсутствие лишних
  и пропавших файлов.

---

## 3. Локальный запуск

Требования: Python 3.10+, PHP 8.0+, `jsonschema`.

```bash
python -m pip install jsonschema
python tests/run-all-tests.py
```

Порядок поиска PHP:
1. `PHP_BIN` / `PHP_EXE`;
2. системный `php` в `PATH`;
3. Windows-портативный `C:\Users\nadya\php_portable\php.exe`;
4. `php` по умолчанию.

Только mock-WP тесты:

```bash
php tests/test-package-a1.php
```

Только сборка и сверка без пересборки:

```bash
python tests/run-all-tests.py --verify-only
```

---

## 4. Живой REST-тест (отдельно от CI)

`tools/test_live_wp_integration.py` проверяет реальный WordPress: REST, права, `expected_hash`,
создание и удаление тестовых записей. Требует:

* работающий WordPress (локальный Docker или хостинг);
* URL сайта;
* application password пользователя автоматизации (роль без `publish_posts`);
* переменные окружения/аргументы, см. `--help`.

В CI не включается: нет стенда и секретов. Запускать вручную перед релизом на стейджинге.

---

## 5. CI на GitHub

1. `git push origin main` или pull request.
2. Workflow `.github/workflows/ci.yml` поднимает Ubuntu, PHP 8.2, Python 3.11.
3. Устанавливает `jsonschema`, запускает `python tests/run-all-tests.py`.
4. При успехе публикует `theme4.zip` и `tchp-content.zip` в Artifacts.
5. При ошибке сборка красная, коммит не проходит quality gate.
