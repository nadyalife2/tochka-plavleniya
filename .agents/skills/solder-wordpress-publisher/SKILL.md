---
name: solder-wordpress-publisher
description: Format and publish soldering articles to WordPress: populate passport meta fields, format tools and FAQ arrays, prepare REST payloads, and verify DOM anchors.
argument-hint: "[publish-draft|validate-payload|check-anchors] [post-data]"
metadata:
  author: claudekit
  version: "1.1.0"
---

# Solder WordPress Publisher

Использовать для технической упаковки контракта (Режим 2) и контролируемой доставки черновика в WordPress (Режим 3).
Обязательно прочитать [редакционную книгу](../../../gem-knowledge/MASTER_EDITORIAL_BOOK.md) и [инструкцию Агента 3](../../../gem-knowledge/AGENT_03_REVIEW_AND_PUBLISH.md).

Спецификация 3 слоёв данных, двух веток упаковки и протокол скрипта доставки зафиксированы в [`AGENT_03_REVIEW_AND_PUBLISH.md`](../../../gem-knowledge/AGENT_03_REVIEW_AND_PUBLISH.md).

Ключевые правила:
1. Контракт JSON (`package.json`) строго валидируется по `schemas/article-contract-v1.1.schema.json`.
2. Блок FAQ передаётся массивом объектов строго с ключами `q` и `a` (`meta.tchp_faq_items`). Использование `question`/`answer` запрещено.
3. Доставка черновика выполняется скриптом `tools/delivery_cli.py` строго в статусе `draft` или `pending`.
4. Автоматическое присвоение статуса `publish` и подделка `is_verified` категорически запрещены. Публикация и ОТК осуществляются исключительно человеком.
5. Для тем без термопрофиля (инструменты, оптика, организация верстака) применяется ветка Б (ручная/специальная публикация без подгонки под паяльный контракт).