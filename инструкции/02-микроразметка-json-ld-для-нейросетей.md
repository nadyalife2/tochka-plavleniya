# Микроразметка Schema.org (JSON-LD) для нейросетей и поисковых роботов

> **Что это такое**: JSON-LD — это структурированный программный код в формате JSON, внедряемый в тег `<head>` страницы. Он невидим обычному посетителю, но читается краулерами Google, Яндекса, а также поисковыми ИИ-агентами (ChatGPT, Perplexity, Claude, Bing Copilot) для построения базы знаний и мгновенных ответов.

---

## 1. Как микроразметка работает в теме «ТОЧКА ПЛАВЛЕНИЯ»

Вам **не нужно писать JSON-код вручную**.  
Тема «ТОЧКА ПЛАВЛЕНИЯ» генерирует микроразметку **автоматически на лету** при открытии любой опубликованной статьи в `theme/single.php`.

### Что тема извлекает из статьи сама:
1. **`headline`** ← Название записи (`get_the_title()`);
2. **`description`** ← Отрывок записи (`get_the_excerpt()`);
3. **`datePublished`** ← Дата публикации в формате ISO 8601 (`get_the_date('c')`);
4. **`dateModified`** ← Дата последнего обновления (`get_the_modified_date('c')`);
5. **`author`** ← Имя автора в WordPress (`get_the_author()`);
6. **`publisher`** ← Организация «ТОЧКА ПЛАВЛЕНИЯ»;
7. **`proficiencyLevel`** ← Уровень сложности (по умолчанию: `Expert / PRO`);
8. **`standard`** ← Отраслевой норматив (по умолчанию: `ГОСТ / IPC-A-610`).

---

## 2. Управление метаданными через админку WordPress

Чтобы придать статье максимальную ценность для ИИ, при редактировании статьи заполняйте следующие поля:

### 1. Отрывок (Excerpt) — правая панель записи
- **Почему это критично**: Именно отрывок нейросеть забирает в качестве краткого резюме ответа.
- **Длина**: 150–220 символов (1–2 емких предложения).
- **Пример**:  
  *«Пошаговое построение термопрофиля SAC305: время над ликвидусом 45–60 с, пиковый рефлоу 238–242°C, критерии годности галтелей по IPC-A-610 Class 3.»*

### 2. Произвольные поля (Custom Fields)
Если в вашей админке включены «Произвольные поля» (включаются через «Параметры экрана» вверху админки или через плагин ACF):

| Имя поля (Meta Key) | Тип значения | Пример значения | Где отображается |
|---------------------|--------------|-----------------|------------------|
| **`tchp_standard`** | Текст | `IPC-A-610G Class 3` или `ГОСТ 21931-76` | Бейдж в шапке статьи и JSON-LD |
| **`tchp_reading_time`** | Число | `6` | Время чтения (минут) |
| **`proficiency_level`** | Выбор / Текст | `Beginner`, `Intermediate`, `Expert` | Поле `proficiencyLevel` в JSON-LD |
| **`required_tools`** | Текст | `Термофен, Флюс NC-559, SAC305, Термопара` | Поле `dependencies` в JSON-LD |

*Примечание: Если эти поля не заполнены, тема подставит корректные экспертные значения по умолчанию.*

---

## 3. Эталонная структура JSON-LD блока (TechArticle + BreadcrumbList)

Вот как выглядит чистый и валидный JSON-LD блок, который генерирует тема:

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "TechArticle",
      "@id": "https://tochka-plavleniya.ru/temperaturnye-profili/#article",
      "isPartOf": {
        "@type": "WebSite",
        "@id": "https://tochka-plavleniya.ru/#website",
        "name": "ТОЧКА ПЛАВЛЕНИЯ // ТЧП",
        "url": "https://tochka-plavleniya.ru/"
      },
      "headline": "Температурные профили: как не перегреть плату за $300",
      "description": "Разбираем теплоёмкость текстолита и строим правильную кривую нагрева для BGA-монтажа и сложных многослойных плат по стандарту IPC/JEDEC J-STD-020D.",
      "inLanguage": "ru",
      "datePublished": "2026-10-07T12:00:00+03:00",
      "dateModified": "2026-10-08T15:30:00+03:00",
      "author": {
        "@type": "Person",
        "name": "Инженер Лаборатории ТЧП",
        "jobTitle": "Ведущий инженер-технолог РЭА"
      },
      "publisher": {
        "@type": "Organization",
        "name": "ТОЧКА ПЛАВЛЕНИЯ",
        "url": "https://tochka-plavleniya.ru/",
        "logo": {
          "@type": "ImageObject",
          "url": "https://tochka-plavleniya.ru/assets/img/og-cover.png"
        }
      },
      "proficiencyLevel": "Expert / PRO",
      "dependencies": "Термовоздушная станция, Нижний преднагреватель, Флюс NC-559, Термопара K-типа"
    },
    {
      "@type": "BreadcrumbList",
      "@id": "https://tochka-plavleniya.ru/temperaturnye-profili/#breadcrumb",
      "itemListElement": [
        {
          "@type": "ListItem",
          "position": 1,
          "name": "Главная",
          "item": "https://tochka-plavleniya.ru/"
        },
        {
          "@type": "ListItem",
          "position": 2,
          "name": "Материалы и сплавы",
          "item": "https://tochka-plavleniya.ru/category/materialy/"
        },
        {
          "@type": "ListItem",
          "position": 3,
          "name": "Температурные профили",
          "item": "https://tochka-plavleniya.ru/temperaturnye-profili/"
        }
      ]
    }
  ]
}
</script>
```

---

## 4. Как разметить FAQ для прямого попадания в сниппеты

Когда в статье используется паттерн **№14 (FAQ / Блок частых вопросов)**, поисковые системы и Perplexity извлекают его в быстрые ответы.

Если вы хотите добавить дополнительный блок `FAQPage` прямо в текст статьи, можно вставить блок Gutenberg **«Пользовательский HTML»** со следующим содержимым:

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "Какая максимальная температура допустима для бессвинцовой пайки BGA?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "По стандарту IPC/JEDEC J-STD-020D абсолютный пиковый предел составляет 250°C (не более 10 секунд). Оптимальный рабочий пик оплавления сплава SAC305 — ровно 238–242°C."
      }
    },
    {
      "@type": "Question",
      "name": "Сколько секунд припой должен находиться в расплавленном состоянии (TAL)?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Время над ликвидусом (TAL) для сплава SAC305 (217°C) должно составлять от 45 до 60 секунд. При TAL менее 40 с возникает риск непропая, при TAL более 90 с интерметаллический слой становится хрупким."
      }
    }
  ]
}
</script>
```

---

## 5. Как проверить корректность микроразметки

После публикации статьи проверьте её в официальных валидаторах:

1. **Google Rich Results Test (Проверка расширенных результатов)**:  
   👉 [https://search.google.com/test/rich-results](https://search.google.com/test/rich-results)  
   - Вставьте URL статьи.
   - Проверьте отсутствие ошибок и статус «Страница поддерживает расширенные результаты».
2. **Schema.org Validator**:  
   👉 [https://validator.schema.org/](https://validator.schema.org/)  
   - Проверяет синтаксис графа `TechArticle`, `BreadcrumbList`, `Organization`.
