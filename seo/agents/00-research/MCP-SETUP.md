# MCP-разведка: какие серверы подключить и как это сделать в DSH

Практическое руководство. Отвечает на вопрос: «Мы хотим искать конкурентов, ключевые
слова, фишки и оценивать приоритетность — чем это делать и что для этого подключить».

Главное правило проекта: **сначала бесплатные локальные скрипты, MCP — только там, где
скрипт реально не справляется.** Каждый подключённый MCP-сервер добавляет JSON-схемы
своих инструментов в *каждый* запрос к модели. Это прямой расход токенов ещё до того,
как вы написали первое слово.

---

## 1. Что уже работает без единого ключа

| Инструмент | Что делает | Стоимость | Проверено |
|---|---|---|---|
| `seo/scripts/research_competitors.py` | Обходит `sitemap.xml`, `sitemap_index.xml`, `wp-sitemap.xml`, RSS/Atom и HTML-архив блога конкурента. Извлекает URL, `<h1>`, `<title>`, дату. Классифицирует тип статьи и кластер эвристиками. Пишет в таблицу `raw_articles` базы `seo/data/seo.db`. | 0 ₽, 0 токенов | Да: `solderpoint.ru` → 25 статей за один прогон |
| `seo/scripts/priority_matrix.py` | Считает интегральный приоритет каждой темы 0–100 по шести факторам (кластер, контентный пробел, покрытие конкурентами, хабность ссылок, широта ключей, готовность). Пишет в `topics.priority_score` и `topics.seo_priority_json`. | 0 ₽, 0 токенов | Да: все темы посчитаны |
| `seo/scripts/db.py` | Подключение к SQLite (WAL, busy-таймаут), схема, автобэкап, `build_db_plan`. | 0 ₽, 0 токенов | Да |

Порядок работы по умолчанию:

```bash
python seo/scripts/research_competitors.py --limit 60
python seo/scripts/priority_matrix.py --top 20
python seo/scripts/build_worklist.py
python seo/scripts/build_dashboard_ui.py
```

Этого достаточно для 80 % задач SEO-разведки. Дальше — про то, чего скрипт не умеет.

---

## 2. Чего бесплатный скрипт не умеет

| Проблема | Почему скрипт не справляется | Чем закрывать |
|---|---|---|
| Сайт отдаёт контент только через JavaScript | `urllib` получает пустой каркас без статей | Playwright MCP (браузер) |
| Антибот (Cloudflare, Qrator) | 403 на любой запрос из скрипта | Firecrawl (свой прокси-пул) |
| Нужна очистка страницы в Markdown | Скрипт берёт только ссылки и заголовки | Firecrawl `/scrape` |
| Нужны реальные позиции в поиске | Скрипт не ходит в поисковики | SerpAPI, Tavily, Brave Search |
| Нужны частотности Wordstat | Нет публичного API без OAuth-приложения | Яндекс.Вебмастер / ручной экспорт |
| Нужны клики и показы сайта | — | Яндекс.Метрика, Google Search Console |

---

## 3. Таблица MCP-серверов для SEO

| Сервер | Пакет | Что даёт | Ключ | Бесплатный лимит | Вес в контексте |
|---|---|---|---|---|---|
| **Firecrawl** | `firecrawl-mcp` | `scrape` (страница → Markdown), `crawl` (обход сайта), `search`, `map` (карта URL), обход антибота | `FIRECRAWL_API_KEY` | 500 кредитов/мес (уточнить на сайте) | Средний: 6–8 инструментов |
| **Tavily** | `tavily-mcp` | Поиск с готовыми выжимками, `extract` по URL, research-режим | `TAVILY_API_KEY` | 1000 запросов/мес | Средний: 4 инструмента |
| **Brave Search** | `@modelcontextprotocol/server-brave-search` | Веб- и локальный поиск, без прокси | `BRAVE_API_KEY` | 2000 запросов/мес | Низкий: 2 инструмента |
| **Playwright** | `@playwright/mcp` | Реальный браузер: JS-сайты, скриншоты, клики, чтение DOM | не нужен | без ограничений | Высокий: 20+ инструментов |
| **Яндекс.Вебмастер** | MCP-обёртка над API | Позиции, запросы, индексация своего сайта | OAuth-токен | по квоте API | Низкий |
| **Google Search Console** | `mcp-server-gsc` | Показы, клики, запросы, ошибки индексации | Service Account JSON | по квоте API | Низкий |
| **SerpAPI** | `serpapi-mcp` | Точные позиции Google/Yandex по региону | `SERPAPI_KEY` | 100 запросов/мес | Средний |

Правило: **не более 3–4 серверов одновременно.** Больше — и схемы инструментов съедят
бюджет контекста раньше, чем начнётся работа.

---

## 4. Как подключить MCP в DSH

### 4.1. Формат конфигурации

MCP-клиент DSH — это плагин `@deepseek-ai/dsh-mcp-client`. Один сервер = одна запись
в вашем patch-слое профиля:

```
C:\Users\nadya\.dsh\profiles\web\cordis.patch.yml
```

Инструменты сервера появятся у модели под именами вида
`mcp__<serverName>__<tool>` — например `mcp__firecrawl__scrape`.

### 4.2. Firecrawl (stdio)

```yaml
- id: mcp-firecrawl
  name: '@deepseek-ai/dsh-mcp-client'
  config:
    serverName: firecrawl
    transport: stdio
    command: npx
    args: ['-y', 'firecrawl-mcp']
    env:
      FIRECRAWL_API_KEY: !!js process.env.FIRECRAWL_API_KEY
    toolCallTimeoutMs: 120000
```

### 4.3. Tavily (stdio)

```yaml
- id: mcp-tavily
  name: '@deepseek-ai/dsh-mcp-client'
  config:
    serverName: tavily
    transport: stdio
    command: npx
    args: ['-y', 'tavily-mcp']
    env:
      TAVILY_API_KEY: !!js process.env.TAVILY_API_KEY
```

### 4.4. Playwright (stdio)

```yaml
- id: mcp-playwright
  name: '@deepseek-ai/dsh-mcp-client'
  config:
    serverName: playwright
    transport: stdio
    command: npx
    args: ['-y', '@playwright/mcp@latest', '--headless']
```

### 4.5. Если сервер умеет Streamable HTTP

Предпочтительный вариант: меньше движущихся частей и нет проблем с пробросом stdio.

```yaml
- id: mcp-web
  name: '@deepseek-ai/dsh-mcp-client'
  config:
    serverName: web
    transport: streamable-http
    url: http://localhost:3000/mcp
    headers:
      Authorization: !!js '`Bearer ${process.env.MCP_TOKEN}`'
    reconnect:
      enabled: true
      maxAttempts: 10
```

Полезные поля: `toolCallTimeoutMs` (по умолчанию 60 000), `failOnStartupError`
(по умолчанию `false` — харнесс стартует даже при упавшем сервере),
`reconnect.maxAttempts` (по умолчанию 10).

---

## 5. КРИТИЧЕСКОЕ ПРЕДУПРЕЖДЕНИЕ: stdio-MCP и песочница Windows

Факт, зафиксированный на этом проекте:

> В песочнице DSH с режимом `workspace-write` программы не могут открывать именованные
> каналы. Любая попытка пробросить piped stdio в дочерний процесс падает с
> `PermissionError [WinError 5]` / `EPERM`.

Что именно упало:

- **Playwright (Python)** — `asyncio.create_subprocess_exec` →
  `_winapi.CreateFile` → `PermissionError: [WinError 5] Отказано в доступе`.
- **`tests/run-all-tests.py`, шаг 4** — `tempfile.TemporaryDirectory().cleanup()` →
  `PermissionError: [WinError 5]` при `chmod` во временной папке.
- `node child_process.spawn` с `stdio: 'pipe'` — документированная граница песочницы,
  даёт `EPERM`.

Практические следствия — три пути на выбор:

1. **Streamable HTTP вместо stdio.** Если сервер умеет `transport: streamable-http`,
   используйте его: сетевой транспорт не открывает именованных каналов.
2. **Осознанно расширить права** для конкретного запуска (`danger-full-access`) — только
   если сервер критичен и вы понимаете риск. Каждый такой случай фиксируйте в задаче.
3. **Не ставить MCP вообще.** Ходить по HTTP из Python-скриптов. Именно этот путь уже
   работает: `research_competitors.py` обошёл `solderpoint.ru` без единого ключа и
   без MCP. Стоимость — 0 токенов, 0 ₽.

Рабочая рекомендация проекта: **вариант 3 по умолчанию, вариант 1 — когда сайт на JS,
вариант 2 — крайний случай.**

---

## 6. Где хранить ключи

Ключи не пишутся в `cordis.patch.yml`. Два допустимых места:

### 6.1. Файл credentials DSH

```
C:\Users\nadya\.dsh\.credentials.yaml
```

Секция `refs:` — там уже лежат `DASHSCOPE_API_KEY` и `OPENROUTER_API_KEY`:

```yaml
refs:
  OPENROUTER_API_KEY: sk-or-v1-...
  FIRECRAWL_API_KEY: fc-...
  TAVILY_API_KEY: tvly-...
```

Затем в patch-слое ссылайтесь на переменную через `!!js process.env.FIRECRAWL_API_KEY`.

### 6.2. Переменные окружения Windows

```powershell
[Environment]::SetEnvironmentVariable('FIRECRAWL_API_KEY', 'fc-...', 'User')
```

После этого нужен перезапуск DSH, чтобы процесс увидел новую переменную.

### 6.3. Запреты

- Не коммитить ключи в git. Проверьте, что `.credentials.yaml` и `*.env` покрыты
  `.gitignore`; в проекте `main` защищён и правки идут через ветки `feat/*` / `fix/*`.
- Не вставлять ключ в статью, бриф, лог или отчёт скоринга.
- Не дублировать ключ в двух местах: выберите одно — `refs` или переменную окружения.

---

## 7. Порядок внедрения по приоритету

| Шаг | Что | Когда переходить |
|---|---|---|
| 1 | `research_competitors.py` + `priority_matrix.py` | Сейчас. Уже работает, покрывает sitemap/RSS/HTML |
| 2 | Playwright MCP (streamable-http, если доступен) | Когда появится конкурент с JS-рендерингом |
| 3 | Firecrawl MCP | Когда начнётся массовый обход с антиботом и нужна очистка в Markdown |
| 4 | Tavily или Brave Search | Когда понадобится поисковая выдача, а не обход известных доменов |
| 5 | Яндекс.Вебмастер / GSC | Когда сайт уже проиндексирован и нужны позиции и клики |

Не подключайте шаг 3 раньше шага 1. Firecrawl даёт то же, что скрипт, но за деньги и
с расходом контекста.

---

## 8. Экономия токенов

1. **Каждый MCP — это постоянный налог.** Схемы инструментов уходят в запрос при каждом
   ходе. Playwright MCP — 20+ инструментов, это тысячи токенов на ровном месте.
2. **Держите 3–4 сервера максимум.** Отключайте неиспользуемые: закомментируйте запись
   в `cordis.patch.yml` и перезапустите DSH.
3. **Не отдавайте модели сырой HTML.** Скрипт складывает результат в `raw_data`, модель
   читает 20 строк отчёта, а не 200 КБ разметки.
4. **Считайте локально, читайте модели только итог.** `priority_matrix.py` обрабатывает
   60 тем и 18 кластеров за доли секунды; тот же расчёт «в голове» модели — это
   десятки тысяч токенов.
5. **Стабильный префикс.** Не правьте системные правила посреди написания статьи —
   кэш промпта слетает, цена запроса растёт на 60–80 %.

---

## 9. Чек-лист: подключил MCP → проверил, что он виден

- [ ] Запись добавлена в `C:\Users\nadya\.dsh\profiles\web\cordis.patch.yml`.
- [ ] `serverName` уникален и соответствует `[A-Za-z0-9_-]{1,32}`.
- [ ] Ключ лежит в `.credentials.yaml` (`refs:`) или в переменной окружения — **не** в yaml.
- [ ] DSH перезапущен после правки конфига и переменных.
- [ ] В списке инструментов модели появился `mcp__<serverName>__<tool>`.
- [ ] Пробный вызов выполнен и вернул реальный результат (не «fake success»).
- [ ] В логах DSH нет ошибки подключения и цикла реконнекта.
- [ ] Если сервер stdio — проверено, что он не падает с `WinError 5` / `EPERM`.
- [ ] Если MCP не нужен — запись закомментирована, контекст не раздут.

---

## Связанные файлы

- [seo/agents/00-research/AGENT.md](AGENT.md) — регламент разведчика
- [seo/agents/01-researcher/AGENT.md](../01-researcher/AGENT.md) — сбор брифа по теме
- [seo/scripts/research_competitors.py](../../scripts/research_competitors.py) — бесплатный сканер
- [seo/scripts/priority_matrix.py](../../scripts/priority_matrix.py) — матрица приоритетов
- [seo/FREE-MODELS-AND-TOKENS.md](../../FREE-MODELS-AND-TOKENS.md) — бесплатные модели и экономия