# 🧭 ТОЧКА ПЛАВЛЕНИЯ — Навигатор по проекту (START HERE)

Добро пожаловать в единую систему документации инженерного портала «Точка Плавления».

Этот документ — единая точка входа для разработчиков, редакторов и ИИ-ассистентов. Здесь собраны маршруты для решения любых задач проекта без путаницы между кодом, настройкой WordPress, редакционным процессом и генерацией контента.

---

## 🗺️ Куда идти: Маршрутизатор задач

| Что вы хотите сделать? | Раздел | Ссылка на руководство |
|---|:---:|---|
| **Изменить сайт, тему `theme4`, плагин `tchp-content`, схемы контрактов или CLI-инструменты** | **Раздел 1** | [01-development/GUIDE.md](01-development/GUIDE.md) |
| **Развернуть, настроить или восстановить WordPress, сервер, плагины, права и REST API** | **Раздел 2** | [02-wordpress-setup/GUIDE.md](02-wordpress-setup/GUIDE.md) |
| **Оформить готовую статью, заполнить метаполя, доставить черновик в WordPress и вычитать** | **Раздел 3** | [03-editorial-publishing/GUIDE.md](03-editorial-publishing/GUIDE.md) |
| **Найти новую тему, собрать доказательства (ГОСТ/IPC), написать статью голосом Practico или сгенерировать иллюстрации** | **Раздел 4** | [04-research-voice-visuals/GUIDE.md](04-research-voice-visuals/GUIDE.md) |

---

## 🏛️ Единые Источники Истины (Single Source of Truth)

Чтобы правила и код не противоречили друг другу, в проекте закреплены канонические источники:

1. **Контракт статьи и типы данных:**
   * Источник: [`schemas/article-contract-v1.schema.json`](../schemas/article-contract-v1.schema.json)
   * Пример: [`schemas/article-example-v1.json`](../schemas/article-example-v1.json)
   * *Никакие скиллы или скрипты не имеют права придумывать собственные форматы полей.*

2. **Реестр сплавов и физические свойства:**
   * **Физические характеристики и температурные диапазоны:** [`theme4/data/materials-registry.php`](../theme4/data/materials-registry.php) (справочник сплавов `$MATERIALS_REGISTRY`, температуры солидус/ликвидус, ГОСТы).
   * **Канонические идентификаторы (slugs) и нормализация:** `pos61`, `sn63pb37`, `pos40`, `sac305`, `sac405`, `sn42bi58`, `snbiago`, `roze`, `vuda`, `universal`. Источник валидации: функции `tchp_get_canonical_alloys()` и `tchp_normalize_solder_alloy()` в [`wp-content/plugins/tchp-content/tchp-content.php`](../wp-content/plugins/tchp-content/tchp-content.php).
   * **Формат данных статьи:** объект `solder_alloy` и `temp_data` в [`schemas/article-contract-v1.schema.json`](../schemas/article-contract-v1.schema.json).

3. **Редакционная верификация и отпечаток (Fingerprint):**
   * Регистрация метаполей и расчёт SHA-256: [`wp-content/plugins/tchp-content/tchp-content.php`](../wp-content/plugins/tchp-content/tchp-content.php).
   * Поля: `tchp_review_status` (`unreviewed`, `in_review`, `verified`), `tchp_reviewed_content_hash`, `tchp_reviewer_id`, `tchp_reviewed_at`, `tchp_review_outdated`.

4. **Визуальный стиль и компоненты (Bento-Sketch Hybrid):**
   * Единый источник дизайн-токенов: [`theme4/assets/css/tokens.css`](../theme4/assets/css/tokens.css) и правила [`DESIGN.md`](../DESIGN.md).
   * Фактическая палитра темы: светло-серый фон `--color-bg: #f6f6f6` (светлая тема) / `#121212` (тёмная тема), фирменный янтарный акцент канифоли `--color-accent: #FFC700`, карандашная рамка 1px, векторный монохромный SVG. Без градиентов, неонового свечения и стеклянного морфизма.

5. **Голос автора (Practico):**
   * Спецификация и образцы: раздел [`docs/04-research-voice-visuals/GUIDE.md`](04-research-voice-visuals/GUIDE.md) и база знаний [`gem-knowledge/`](../gem-knowledge/).
   * Принцип: *термин → физический механизм → наблюдаемое последствие*. Никакого выдуманного личного опыта и кулинарных метафор.

---

## 🤖 Правило взаимодействия для ИИ-ассистентов

1. **Не путать статусы:** Инструкция в Git описывает целевое состояние системы. Настройки WordPress хранятся в базе данных хостинга. Если действие описано в руководстве — это не значит, что оно уже применено на рабочем сайте.
2. **Соблюдать границы роли:**
   * В Разделе 1 результат работы — протестированный код и архивы, а не опубликованный пост.
   * В Разделе 3 и 4 результат работы — подготовленный JSON черновика или проверенный бриф.
3. **Автоматическая публикация запрещена:** Все скрипты доставки (`delivery_cli.py`, `ai_article_generator.py`) работают строго со статусом `draft` или `pending`. Финальное решение о публикации принимает только шеф-редактор.
