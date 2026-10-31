---
name: solder-wordpress-publisher
description: Format and publish soldering articles to WordPress: populate passport meta fields, format tools and FAQ arrays, prepare REST payloads, and verify DOM anchors.
argument-hint: "[publish-draft|validate-payload|check-anchors] [post-data]"
metadata:
  author: claudekit
  version: "1.2.0"
---

# Solder WordPress Publisher (v1.2)

Использовать для технической упаковки контракта (Режим 2) и контролируемой доставки черновика в WordPress (Режим 3).
Обязательно прочитать [редакционную книгу](../../../gem-knowledge/MASTER_EDITORIAL_BOOK.md) и [инструкцию Агента 3](../../../gem-knowledge/AGENT_03_REVIEW_AND_PUBLISH.md).

Спецификация 3 слоёв данных, двух веток упаковки и протокол скрипта доставки зафиксированы в [`AGENT_03_REVIEW_AND_PUBLISH.md`](../../../gem-knowledge/AGENT_03_REVIEW_AND_PUBLISH.md).

## Ключевые команды публикации

**1. Проверка готовности и упаковка контракта:**
```bash
python seo/scripts/package_contract.py <TOPIC_ID>
```
*Упаковщик извлекает метаданные из текста, проверяет отсутствие вымышленных температур, инверсий и строит валидный JSON-контракт v1.1.*

**2. Безопасная пробная доставка (Dry-Run без записи в WP):**
```bash
python tools/delivery_cli.py deliver --topic <TOPIC_ID> --dry-run
```

**3. Реальная доставка черновика в WordPress:**
```bash
python tools/delivery_cli.py deliver --topic <TOPIC_ID>
```

---

## Ключевые правила и инварианты

1. **Контракт JSON строго валидируется по `schemas/article-contract-v1.1.schema.json`:**
   * `schema_version`: `'1.1'` (плагин `tchp-content.php` принимает `['1.0', '1.1']`).
   * `difficulty`: `['Базовый', 'Инженер', 'Эксперт', 'Практик']`.
2. **Блок FAQ:** передаётся массивом объектов строго с ключами `q` и `a` (`meta.tchp_faq_items`). Использование `question`/`answer` запрещено. Минимум 4 вопроса (`minItems: 4`).
3. **Источники (`meta.tchp_sources`):** минимум 2 подтверждённых стандарта (ГОСТ / IPC) с шифром или URL.
4. **Контроль физики:** отсутствие уставки жалы в тексте статьи блокирует выгрузку (тихие подстановки из реестра запрещены).
5. **Статус записи:** доставка черновика выполняется скриптом `tools/delivery_cli.py` строго в статусе `draft` или `pending`.
6. **Человеческие ворота (Human-in-the-loop):** автоматическое присвоение статуса `publish` и подделка `is_verified` категорически запрещены. Публикация и ОТК осуществляются исключительно человеком в административной панели WordPress.
7. **Для тем без пайки (инструменты, оптика, организация верстака):** в паспорте легитимно используется ключ сплава `universal`.
