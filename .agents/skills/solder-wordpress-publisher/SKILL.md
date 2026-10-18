---
name: solder-wordpress-publisher
description: Format and publish soldering articles to WordPress: populate passport meta fields, format tools and FAQ arrays, prepare REST payloads, and verify DOM anchors.
argument-hint: "[publish-draft|validate-payload|check-anchors] [post-data]"
metadata:
  author: claudekit
  version: "1.0.0"
---

# Solder WordPress Publisher — Инженер Публикации и Интеграции

Специализированный скилл для подготовки, валидации и отправки контента в WordPress через плагин `tchp-content` и тему `theme4`.

## When to Use

- При подготовке JSON-пейлоада для отправки статьи в WordPress REST API.
- При заполнении метаполей статьи (Паспорт, Инструменты, FAQ, Источники).
- При проверке DOM-якорей на уникальность перед публикацией.
- При запуске процедуры верификации ревизии (`POST /wp-json/tchp/v1/posts/{id}/verify`).

## Quick Start

**Сформировать REST-пейлоад для статьи:**
```bash
# Статус строго 'draft' или 'pending' (авто-publish запрещён!)
```

---

## 1. Спецификация Метаполей (`tchp-content`)

| Ключ поля | Тип | Описание |
| :--- | :--- | :--- |
| `tchp_solder_alloy` | `string` | Канонический slug (`pos61`, `sac305`, etc.) |
| `tchp_temp_min` | `integer` | Минимальная температура ликвидуса (°C) |
| `tchp_temp_max` | `integer` | Рабочая температура жала (°C) |
| `tchp_difficulty` | `string` | `beginner` / `intermediate` / `advanced` |
| `tchp_tools` | `array` | Список объектов `{ name, model, purpose }` |
| `tchp_faq_items` | `array` | Список объектов `{ q, a }` (не пустые!) |
| `tchp_sources` | `array` | Список объектов `{ title, url, standard }` |

---

## 2. Правила Доставки Контента

1. **Только черновики:** Никогда не отправлять статус `publish`. Публикацию осуществляет человек-редактор в админке.
2. **Защита от зачистки данных:** Запрещено отправлять пустые массивы или пробельные строки, чтобы не затереть существующие поля.
3. **Уникальность якорей:** В теме `theme4` работает двухпроходный фильтр DOM. Убедитесь, что заголовки не конфликтуют с ID секций (`sec-tools`, `sec-faq`, `sec-sources`).