---
name: tchp-editorial-standards
description: Strict editorial, technical, and architectural standards for tochka-plavleniya.ru (solder alloys, temperature regimes, verification fingerprint, REST contracts, and sketch aesthetic).
argument-hint: "[verify|audit|format-article] [args]"
metadata:
  author: claudekit
  version: "1.0.0"
---

# Точка Плавления — Редакционные и Технические Стандарты

Этот скилл содержит канонические правила создания, валидации и публикации материалов на инженерном портале «Точка Плавления».

## When to Use

- При подготовке, генерации или рецензировании технических статей по пайке.
- При валидации метаполей статьи (сплавы, температуры, инструменты, FAQ, источники).
- При проверке отпечатка статьи (SHA-256 fingerprint) и статуса редакционной проверки.
- При рендеринге DOM-якорей и проверке отсутствия коллизий в теме `theme4`.
- При аудите UI/UX на соответствие стилю Bento-Sketch Hybrid (инженерный блокнот).

## Quick Start

**Валидация статьи и проверка отпечатка:**
```bash
php -r "require 'theme4/includes/articles-data.php'; echo tchp_get_alloy_label('pos61');"
```

**Запуск регрессионных тестов:**
```bash
python tests/run-all-tests.py
```

---

## 1. Канонические Сплавы и Температуры

Физические характеристики сплавов хранятся в [`theme4/data/materials-registry.php`](../../../theme4/data/materials-registry.php).
Разрешённые ID (slugs) и нормализация валидируются в [`wp-content/plugins/tchp-content/tchp-content.php`](../../../wp-content/plugins/tchp-content/tchp-content.php) (`tchp_get_canonical_alloys()`).
Формат данных статьи задаётся схемой [`schemas/article-contract-v1.schema.json`](../../../schemas/article-contract-v1.schema.json).

В базе данных и API разрешены только следующие slug'и:

| Slug | Название | Диапазон T (°C) | Тип |
| :--- | :--- | :--- | :--- |
| `pos61` | ПОС-61 (Sn61Pb39) | 183–190 | Эвтектический/свинцовый |
| `sn63pb37` | Sn63Pb37 | 183 | Эвтектический международный |
| `pos40` | ПОС-40 | 183–238 | Свинцовый монтажный |
| `sac305` | SAC305 | 217–220 | Бессвинцовый стандарт |
| `sac405` | SAC405 | 217 | Бессвинцовый |
| `sn42bi58` | Sn42Bi58 | 138 | Низкотемпературный |
| `snbiago` | Sn-Bi-Ag | 138–145 | Низкотемпературный |
| `roze` | Сплав Розе | 94–96 | Легкоплавкий |
| `vuda` | Сплав Вуда | 68–72 | Легкоплавкий токсичный (Cd) |
| `universal` | Универсальный | Варьируется | Составной режим |

*Алиасы:* `wood` автоматически нормализуется в `vuda`, а `rose` — в `roze`.

---

## 2. Формула Редакционной Верификации

Редакционная верификация в WordPress управляется плагином `tchp-content` ([`tchp-content.php`](../../../wp-content/plugins/tchp-content/tchp-content.php)).

Статья считается верифицированной (`is_verified = true`) только если выполнены условия:
1. `tchp_review_status === 'verified'`
2. `tchp_review_outdated !== '1'` (контент не менялся после проверки)
3. Хеш отпечатка статьи (`tchp_reviewed_content_hash`) строго совпадает с вычисленным SHA-256:
   * Вычисляется от канонического набора: `title`, `content`, `excerpt`, `tools`, `faq`, `sources`, `alloy`, `t_kind`, `t_range`, `t_peak`, `standard`, `std_class`, `standard_desc`.
4. Зафиксированы `tchp_reviewer_id` (ID пользователя WP с правом `tchp_review_content`) и `tchp_reviewed_at` (ISO 8601).

При сохранении через метабокс административной панели верификация подтверждается при сохранении записи (`save_post`).
Для внешних вызовов через эндпоинт `POST /wp-json/tchp/v1/posts/{id}/verify` обязательно передавать `expected_hash` — при несовпадении сервер возвращает **409 Conflict**.
Любое последующее изменение текста автоматически сбрасывает статус в `in_review` (`tchp_review_outdated = 1`).

---

## 3. Требования к Тексту (Голос Practico)

- **Голос Practico:** Читатель — новичок по знаниям, но коллега по достоинству.
- **Формула абзаца:** термин → физический механизм одной фразой → наблюдаемое последствие.
- **Наблюдаемые признаки:** цвет, блеск, текучесть, дым, звук, время.
- **Запрет на выдуманный опыт:** Никаких историй «ко мне в мастерскую занесли» или имитации измерений.
- **Запрет на бытовые аналогии:** Никаких сравнений с едой, тестом или бытом (суп, пирог, масло).
- **Стоп-фразы:** Не использовать штампы «как известно», «ни для кого не секрет», «в наше время» ([`04_forbidden_phrases.txt`](../../../gem-knowledge/04_forbidden_phrases.txt)).
- **Честные формулировки виджетов:** Не «QC PASSED», а «Подбор стартового режима ручной пайки» и «Этапы профиля (учебно)».
- Подробнее см. [`docs/04-research-voice-visuals/GUIDE.md`](../../../docs/04-research-voice-visuals/GUIDE.md).

---

## 4. Визуальные Правила (Bento-Sketch Hybrid)

- Токены веб-интерфейса ([`theme4/assets/css/tokens.css`](../../../theme4/assets/css/tokens.css)):
  - Фон: `#F6F6F6` (светлая тема) / `#121212` (тёмная тема).
  - Карточки: `#FFFFFF` / `#1C1C1E`.
  - Акцент: `#FFC700` (янтарный, предупреждающий).
  - Тонкие контуры: `1px solid var(--border-color)` (`#111111`).
- Иллюстрации и схемы:
  - Медь дорожек: `#D4A373` (только для проводников на платах в иллюстрациях, не для UI кнопок/фона).
  - Припой: `#A0AAB2`, флюс `#C99700`.
- Никакого неонового блеска, градиентов, стеклянного морфизма или SaaS-стиля.
- Иконки — только строгий монохромный векторный SVG, без эмодзи.
- Подробнее см. [`DESIGN.md`](../../../DESIGN.md) и [`docs/04-research-voice-visuals/GUIDE.md`](../../../docs/04-research-voice-visuals/GUIDE.md).

