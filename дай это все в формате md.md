<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# дай это все в формате md

Да. Ниже даю всё в формате **Markdown**, чтобы ты мог просто скопировать в `.md` файл и дальше по нему работать. Основа взята из структуры твоего проекта и приложенных workflow, но я её **упростил под дешёвый локальный старт в Docker** с учётом Gemini API и того, что исходный проект содержит более тяжёлую архитектуру с множеством job endpoints, WordPress draft-пайплайном и SEO-мониторингом через Yandex Webmaster.[^1][^2][^3][^4][^5][^6][^7]

***

# Гайд: локальный запуск проекта сайта о пайке

## Что мы делаем

Мы поднимаем локально у себя на компьютере весь минимальный стек в Docker:

- WordPress
- MariaDB
- PostgreSQL
- n8n
- Python worker с Gemini API

Это соответствует логике проекта: тема попадает в `content_queue`, затем проходит research, генерацию статьи, позже quality check и WordPress draft. Именно такая цепочка видна в приложенных workflow `01`–`05`.[^3][^4][^5][^6][^8]

***

## Что будет работать на первом этапе

На старте делаем только это:

1. Добавление темы через webhook.
2. Research через worker.
3. Генерация статьи через Gemini.
4. Сохранение в PostgreSQL.
5. Позже — отправка в WordPress как draft.

SEO-мониторинг и бюджет-контроль пока не включаем, потому что в текущих файлах они либо завязаны на внешние данные Яндекс Вебмастера, либо логически неполны: например, budget workflow пишет флаг паузы, но остальные workflow этот флаг ещё не читают.[^7][^9]

***

## Почему не берём всё из исходного проекта сразу

В проекте есть:

- Gemini CLI
- Antigravity
- Perplexity MCP/API
- Chrome DevTools MCP
- WordPress MCP adapter
- LanguageTool
- Vale
- Git-репозиторий
- много отдельных AI-ролей

Это полезно для “большой редакции”, но **не для дешёвого MVP**. В текущих дампах это прямо видно по количеству компонентов и endpoints worker-а.[^2][^1]

Для старта дешевле и разумнее использовать:

- один `worker`
- прямой вызов Gemini API
- n8n
- WordPress REST API
- PostgreSQL

***

# Полезные ссылки

## Основные

- n8n Docker docs: [https://docs.n8n.io/hosting/installation/docker/](https://docs.n8n.io/hosting/installation/docker/)[^9]
- Docker Compose multi-container docs: [https://docs.docker.com/get-started/docker-concepts/running-containers/multi-container-applications/](https://docs.docker.com/get-started/docker-concepts/running-containers/multi-container-applications/)[^3]
- Gemini pricing: [https://ai.google.dev/gemini-api/docs/pricing](https://ai.google.dev/gemini-api/docs/pricing)[^10]
- Gemini rate limits: [https://ai.google.dev/gemini-api/docs/rate-limits](https://ai.google.dev/gemini-api/docs/rate-limits)[^11]
- WordPress REST API auth: [https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/)[^12]
- WordPress Application Passwords: [https://developer.wordpress.org/rest-api/reference/application-passwords/](https://developer.wordpress.org/rest-api/reference/application-passwords/)[^13]
- Yandex Webmaster API getting started: [https://yandex.ru/dev/webmaster/doc/ru/concepts/getting-started](https://yandex.ru/dev/webmaster/doc/ru/concepts/getting-started)[^5]
- Yandex Webmaster query history: [https://yandex.ru/dev/webmaster/doc/ru/reference/host-search-queries-history-all](https://yandex.ru/dev/webmaster/doc/ru/reference/host-search-queries-history-all)[^14]


## MCP и сопутствующее

- Chrome DevTools MCP: [https://github.com/ChromeDevTools/chrome-devtools-mcp](https://github.com/ChromeDevTools/chrome-devtools-mcp)

Важно: MCP в первой версии **не нужен**. В твоём проекте он фигурирует как возможная расширенная инфраструктура, но для MVP прямые HTTP-запросы через n8n и worker проще, дешевле и стабильнее.[^1][^2]

***

# Архитектура MVP

```text
Тема -> n8n webhook -> PostgreSQL
     -> research workflow -> worker /jobs/research
     -> generate workflow -> worker /jobs/write
     -> review workflow -> worker /jobs/review
     -> WordPress draft workflow -> worker /jobs/render-wordpress
     -> WordPress REST API
```


***

# Структура папки

Создай папку проекта:

```text
solder-site/
├── compose.yml
├── .env
├── init.sql
└── worker/
    ├── Dockerfile
    ├── requirements.txt
    └── app.py
```


***

# Шаг 1. Создай `.env`

Файл `.env`:

```env
POSTGRES_DB=editorial
POSTGRES_USER=editorial_user
POSTGRES_PASSWORD=super_secret_postgres_password

N8N_BASIC_AUTH_USER=admin
N8N_BASIC_AUTH_PASSWORD=super_secret_n8n_password
N8N_ENCRYPTION_KEY=super_secret_n8n_encryption_key_64_chars_min
N8N_HOST=localhost
N8N_PROTOCOL=http
WEBHOOK_URL=http://localhost:5678/

WP_DB_NAME=wordpress
WP_DB_USER=wp_user
WP_DB_PASSWORD=super_secret_wp_password
WP_DB_ROOT_PASSWORD=super_secret_wp_root_password

WORKER_TOKEN=super_secret_worker_token
GEMINI_API_KEY=PASTE_YOUR_GEMINI_KEY_HERE
TZ=Europe/Moscow
```


***

# Шаг 2. Создай `compose.yml`

```yaml
services:
  postgres:
    image: postgres:16-alpine
    container_name: solder-postgres
    restart: unless-stopped
    environment:
      POSTGRES_DB: ${POSTGRES_DB}
      POSTGRES_USER: ${POSTGRES_USER}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
      TZ: ${TZ}
    ports:
      - "5432:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./init.sql:/docker-entrypoint-initdb.d/init.sql:ro
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${POSTGRES_USER} -d ${POSTGRES_DB}"]
      interval: 5s
      timeout: 5s
      retries: 20

  mariadb:
    image: mariadb:11
    container_name: solder-mariadb
    restart: unless-stopped
    environment:
      MARIADB_DATABASE: ${WP_DB_NAME}
      MARIADB_USER: ${WP_DB_USER}
      MARIADB_PASSWORD: ${WP_DB_PASSWORD}
      MARIADB_ROOT_PASSWORD: ${WP_DB_ROOT_PASSWORD}
      TZ: ${TZ}
    ports:
      - "3306:3306"
    volumes:
      - mariadb_data:/var/lib/mysql

  wordpress:
    image: wordpress:php8.3-apache
    container_name: solder-wordpress
    restart: unless-stopped
    depends_on:
      - mariadb
    environment:
      WORDPRESS_DB_HOST: mariadb
      WORDPRESS_DB_NAME: ${WP_DB_NAME}
      WORDPRESS_DB_USER: ${WP_DB_USER}
      WORDPRESS_DB_PASSWORD: ${WP_DB_PASSWORD}
    ports:
      - "8080:80"
    volumes:
      - wordpress_data:/var/www/html

  n8n:
    image: n8nio/n8n:latest
    container_name: solder-n8n
    restart: unless-stopped
    depends_on:
      postgres:
        condition: service_healthy
    environment:
      N8N_BASIC_AUTH_ACTIVE: "true"
      N8N_BASIC_AUTH_USER: ${N8N_BASIC_AUTH_USER}
      N8N_BASIC_AUTH_PASSWORD: ${N8N_BASIC_AUTH_PASSWORD}
      N8N_ENCRYPTION_KEY: ${N8N_ENCRYPTION_KEY}
      N8N_HOST: ${N8N_HOST}
      N8N_PROTOCOL: ${N8N_PROTOCOL}
      WEBHOOK_URL: ${WEBHOOK_URL}
      GENERIC_TIMEZONE: ${TZ}
      TZ: ${TZ}
    ports:
      - "5678:5678"
    volumes:
      - n8n_data:/home/node/.n8n

  worker:
    build: ./worker
    container_name: solder-worker
    restart: unless-stopped
    depends_on:
      postgres:
        condition: service_healthy
    environment:
      WORKER_TOKEN: ${WORKER_TOKEN}
      GEMINI_API_KEY: ${GEMINI_API_KEY}
      POSTGRES_DB: ${POSTGRES_DB}
      POSTGRES_USER: ${POSTGRES_USER}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
      POSTGRES_HOST: postgres
      TZ: ${TZ}
    ports:
      - "8090:8090"

volumes:
  postgres_data:
  mariadb_data:
  wordpress_data:
  n8n_data:
```

Docker Compose хорошо подходит для такого набора сервисов, потому что контейнеры могут обращаться друг к другу по именам сервисов, например `worker`, `postgres`, `mariadb`.[^3]

***

# Шаг 3. Создай `init.sql`

```sql
CREATE TABLE IF NOT EXISTS content_queue (
  id BIGSERIAL PRIMARY KEY,
  primary_keyword TEXT NOT NULL,
  normalized_keyword TEXT NOT NULL UNIQUE,
  slug TEXT NOT NULL,
  secondary_keywords JSONB NOT NULL DEFAULT '[]'::jsonb,
  content_type TEXT NOT NULL DEFAULT 'guide',
  subject TEXT NOT NULL,
  priority INTEGER NOT NULL DEFAULT 50,
  notes TEXT,
  status TEXT NOT NULL DEFAULT 'research_required',

  research_report JSONB,
  article_markdown TEXT,
  markdown_path TEXT,
  git_commit TEXT,
  generation_report JSONB,
  qa_report JSONB,

  wordpress_post_id BIGINT,
  published_url TEXT,
  visual_report JSONB,
  review_notes TEXT,

  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_content_queue_status
ON content_queue (status, priority DESC, created_at);

CREATE TABLE IF NOT EXISTS editorial_settings (
  key TEXT PRIMARY KEY,
  value TEXT NOT NULL,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

INSERT INTO editorial_settings (key, value)
VALUES
  ('pause_generation', 'false'),
  ('monthly_limit_usd', '0')
ON CONFLICT (key) DO NOTHING;

CREATE TABLE IF NOT EXISTS editorial_errors (
  id BIGSERIAL PRIMARY KEY,
  workflow_id TEXT,
  workflow_name TEXT,
  execution_id TEXT,
  message TEXT,
  stack TEXT,
  is_temporary BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS ai_usage (
  id BIGSERIAL PRIMARY KEY,
  model TEXT,
  operation TEXT,
  input_tokens INTEGER DEFAULT 0,
  output_tokens INTEGER DEFAULT 0,
  cost_usd NUMERIC(12,6) DEFAULT 0,
  used_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS sites (
  id BIGSERIAL PRIMARY KEY,
  domain TEXT NOT NULL,
  yandex_host_id TEXT,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  last_seo_check TIMESTAMPTZ
);

CREATE TABLE IF NOT EXISTS content_updates (
  id BIGSERIAL PRIMARY KEY,
  site_id BIGINT REFERENCES sites(id) ON DELETE CASCADE,
  url TEXT NOT NULL,
  reason TEXT NOT NULL,
  priority INTEGER NOT NULL DEFAULT 50,
  metrics JSONB NOT NULL DEFAULT '{}'::jsonb,
  status TEXT NOT NULL DEFAULT 'update_required',
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ
);
```

Эта схема согласована с тем, что ожидают workflow `01`, `02`, `03`, `05`, `06`, `07`, хотя для первого запуска реально нужны только основные таблицы, а `sites` и `content_updates` понадобятся позже для SEO-мониторинга.[^4][^6][^5][^7][^9][^3]

***

# Шаг 4. Создай worker

## `worker/requirements.txt`

```txt
fastapi
uvicorn[standard]
requests
psycopg[binary]
```


## `worker/Dockerfile`

```dockerfile
FROM python:3.12-slim

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY app.py .

EXPOSE 8090

CMD ["uvicorn", "app:app", "--host", "0.0.0.0", "--port", "8090"]
```


## `worker/app.py`

```python
import os
import json
import re
import requests
from fastapi import FastAPI, Header, HTTPException
import psycopg

app = FastAPI()

WORKER_TOKEN = os.environ["WORKER_TOKEN"]
GEMINI_API_KEY = os.environ["GEMINI_API_KEY"]

PG_CONN = (
    f"dbname={os.environ['POSTGRES_DB']} "
    f"user={os.environ['POSTGRES_USER']} "
    f"password={os.environ['POSTGRES_PASSWORD']} "
    f"host={os.environ['POSTGRES_HOST']}"
)

def check_token(token: str | None):
    if token != WORKER_TOKEN:
        raise HTTPException(status_code=401, detail="Invalid worker token")

def gemini_prompt(prompt: str, model: str = "gemini-2.5-flash-lite"):
    url = f"https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={GEMINI_API_KEY}"
    body = {
        "contents": [{"parts": [{"text": prompt}]}],
        "generationConfig": {
            "temperature": 0.4
        }
    }
    r = requests.post(url, json=body, timeout=180)
    r.raise_for_status()
    data = r.json()
    return data["candidates"][^0]["content"]["parts"][^0]["text"]

def translit_slug(text: str) -> str:
    mapping = {
        'а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ё':'e','ж':'zh','з':'z',
        'и':'i','й':'y','к':'k','л':'l','м':'m','н':'n','о':'o','п':'p','р':'r',
        'с':'s','т':'t','у':'u','ф':'f','х':'h','ц':'ts','ч':'ch','ш':'sh',
        'щ':'sch','ы':'y','э':'e','ю':'yu','я':'ya','ь':'','ъ':''
    }
    s = text.lower().strip()
    s = ''.join(mapping.get(ch, ch) for ch in s)
    s = re.sub(r'[^a-z0-9]+', '-', s)
    s = re.sub(r'-+', '-', s).strip('-')
    return s or "post"

@app.get("/health")
def health():
    return {"ok": True}

@app.post("/jobs/research")
def research(payload: dict, x_worker_token: str | None = Header(default=None)):
    check_token(x_worker_token)
    subject = payload.get("subject", "")
    topics = payload.get("topics", [])
    keywords = [t.get("primary_keyword", "") for t in topics if t.get("primary_keyword")]
    prompt = f"""
Ты технический редактор сайта о пайке.
Тема кластера: {subject}
Ключевые темы: {json.dumps(keywords, ensure_ascii=False)}

Сделай краткий research report в JSON:
{{
  "summary": "...",
  "facts": [
    {{
      "claim": "...",
      "confidence": "high|medium|low"
    }}
  ],
  "conflicts": []
}}

Не придумывай конкретные стандарты, цифры и маркировки, если не уверен.
"""
    text = gemini_prompt(prompt)
    try:
        cleaned = text.strip().removeprefix("```json").removesuffix("```").strip()
        data = json.loads(cleaned)
    except Exception:
        data = {"summary": text, "facts": [], "conflicts": []}
    return data

@app.post("/jobs/write")
def write(payload: dict, x_worker_token: str | None = Header(default=None)):
    check_token(x_worker_token)
    keyword = payload.get("primary_keyword", "")
    secondary = payload.get("secondary_keywords", [])
    subject = payload.get("subject", keyword)
    research_report = payload.get("research_report", {})

    prompt = f"""
Напиши русскоязычную полезную SEO-статью для новичков по теме пайки.

Главный запрос: {keyword}
Доп. запросы: {json.dumps(secondary, ensure_ascii=False)}
Тема: {subject}
Research report: {json.dumps(research_report, ensure_ascii=False)}

Требования:
- Формат Markdown.
- Без воды.
- Пиши просто.
- Структура:
  1. Краткий ответ
  2. Что понадобится
  3. Пошаговая инструкция
  4. Частые ошибки
  5. Безопасность
  6. FAQ
- Не выдумывай конкретные цифры и стандарты без уверенности.
- Не обещай идеальный результат.
- Не советуй опасные действия с сетевым напряжением без предупреждения.
"""
    article = gemini_prompt(prompt)

    with psycopg.connect(PG_CONN) as conn:
        with conn.cursor() as cur:
            cur.execute(
                "INSERT INTO ai_usage(model, operation, input_tokens, output_tokens, cost_usd) VALUES(%s,%s,%s,%s,%s)",
                ("gemini-2.5-flash-lite", "write", 0, 0, 0)
            )
            conn.commit()

    return {
        "markdown_path": "inline",
        "git_commit": "mvp-no-git",
        "article_markdown": article,
        "generation_report": {
            "model": "gemini-2.5-flash-lite",
            "status": "ok"
        }
    }

@app.post("/jobs/review")
def review(payload: dict, x_worker_token: str | None = Header(default=None)):
    check_token(x_worker_token)
    article = payload.get("article_markdown", "")
    research_report = payload.get("research_report", {})

    prompt = f"""
Проверь статью и верни ТОЛЬКО JSON:

{{
  "verdict": "approve|revise",
  "fact_score": 0,
  "style_score": 0,
  "usefulness_score": 0,
  "seo_score": 0,
  "safety_score": 0,
  "blocking_problems": [],
  "recommendations": []
}}

Проверяй:
- логичность
- понятность
- базовую SEO-пригодность
- безопасность
- соответствие research report

Статья:
{article}

Research:
{json.dumps(research_report, ensure_ascii=False)}
"""
    text = gemini_prompt(prompt)
    try:
        cleaned = text.strip().removeprefix("```json").removesuffix("```").strip()
        data = json.loads(cleaned)
    except Exception:
        data = {
            "verdict": "revise",
            "fact_score": 70,
            "style_score": 70,
            "usefulness_score": 70,
            "seo_score": 70,
            "safety_score": 70,
            "blocking_problems": ["Review JSON parse failed"],
            "recommendations": [text]
        }

    with psycopg.connect(PG_CONN) as conn:
        with conn.cursor() as cur:
            cur.execute(
                "INSERT INTO ai_usage(model, operation, input_tokens, output_tokens, cost_usd) VALUES(%s,%s,%s,%s,%s)",
                ("gemini-2.5-flash-lite", "review", 0, 0, 0)
            )
            conn.commit()

    return data

@app.post("/jobs/render-wordpress")
def render_wordpress(payload: dict, x_worker_token: str | None = Header(default=None)):
    check_token(x_worker_token)
    title = payload.get("primary_keyword", "Без названия")
    slug = payload.get("slug") or translit_slug(title)
    markdown = payload.get("article_markdown", "")
    html = markdown.replace("\n", "<br>\n")
    excerpt = f"Практическая статья по теме: {title}"

    return {
        "title": title,
        "slug": slug,
        "content": html,
        "excerpt": excerpt
    }

@app.post("/jobs/visual-check")
def visual_check(payload: dict, x_worker_token: str | None = Header(default=None)):
    check_token(x_worker_token)
    return {
        "passed": True,
        "notes": ["MVP visual check skipped"]
    }

@app.post("/jobs/seo-monitor")
def seo_monitor(payload: dict, x_worker_token: str | None = Header(default=None)):
    check_token(x_worker_token)
    return {
        "tasks": []
    }
```

Gemini Developer API имеет отдельную страницу pricing и отдельную страницу rate limits, так что использование бесплатного Gemini на старте — нормальная стратегия, если ты следишь за квотами своего проекта.[^10][^11]

***

# Шаг 5. Запуск локально

В терминале в папке проекта:

```bash
docker compose up -d --build
```

Проверка:

```bash
docker compose ps
curl http://localhost:8090/health
```

Если всё хорошо, увидишь:

```json
{"ok":true}
```

Открывай:

- `http://localhost:5678` — n8n
- `http://localhost:8080` — WordPress

***

# Шаг 6. WordPress

После открытия `http://localhost:8080`:

1. пройди установку WordPress
2. создай администратора
3. зайди в `Users -> Profile`
4. найди `Application Passwords`
5. создай пароль для `n8n-publisher`
6. скопируй пароль сразу

WordPress рекомендует Application Passwords для программного доступа к REST API, и это как раз подходит для связки n8n -> WordPress posts API.[^12][^13]

***

# Шаг 7. Настрой n8n credentials

## PostgreSQL

Создай credential:

- Host: `postgres`
- Port: `5432`
- Database: `editorial`
- User: `editorial_user`
- Password: из `.env`


## WordPress Basic Auth

Создай credential:

- Username: твой WordPress логин
- Password: Application Password

***

# Какие workflow использовать

## Сразу импортировать

- `01_Content_Intake`
- `02_Research_And_Facts`
- `03_Generate_Article`

Они уже есть у тебя файлами и соответствуют базовому потоку `ввод темы -> research -> генерация`.[^4][^5][^3]

## Переделать

- `04_Quality_Control` — в текущем проекте слишком тяжёлый, потому что зовёт `lint`, `edit`, `fact-check`, `chief-editor`.[^8]
- `05_WordPress_Draft_And_Approval` — использовать на втором этапе, когда статья уже уверенно пишется и проходит review.[^6]


## Пока не включать

- `06_SEO_Monitoring`
- `07_Error_And_Budget_Control`

Потому что SEO требует данных Яндекс Вебмастера, а бюджетный workflow пока не связан с остановкой генерации в остальных flows.[^7][^9]

***

# JSON workflow: 01_Content_Intake_MVP

```json
{
  "name": "01_Content_Intake_MVP",
  "nodes": [
    {
      "parameters": {
        "httpMethod": "POST",
        "path": "editorial/topics",
        "responseMode": "responseNode",
        "options": {}
      },
      "id": "1",
      "name": "Topic Webhook",
      "type": "n8n-nodes-base.webhook",
      "typeVersion": 2,
      "position": [-600, 0],
      "webhookId": "editorial-topics"
    },
    {
      "parameters": {
        "jsCode": "const b=$json.body||$json; const primary=String(b.primary_keyword||'').trim(); if(!primary) throw new Error('primary_keyword is required'); const normalized=primary.toLowerCase().replace(/ё/g,'е').replace(/[^\\p{L}\\p{N}]+/gu,' ').trim(); const map={а:'a',б:'b',в:'v',г:'g',д:'d',е:'e',ж:'zh',з:'z',и:'i',й:'y',к:'k',л:'l',м:'m',н:'n',о:'o',п:'p',р:'r',с:'s',т:'t',у:'u',ф:'f',х:'h',ц:'ts',ч:'ch',ш:'sh',щ:'sch',ы:'y',э:'e',ю:'yu',я:'ya','ь':'','ъ':''}; const slug=normalized.split('').map(ch=>map[ch]??ch).join('').replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,''); return [{json:{primary_keyword:primary,normalized_keyword:normalized,slug:slug||'post',secondary_keywords:Array.isArray(b.secondary_keywords)?b.secondary_keywords:[],content_type:b.content_type||'guide',subject:b.subject||primary,priority:Number(b.priority||50),notes:b.notes||''}}];"
      },
      "id": "2",
      "name": "Normalize Topic",
      "type": "n8n-nodes-base.code",
      "typeVersion": 2,
      "position": [-380, 0]
    },
    {
      "parameters": {
        "operation": "executeQuery",
        "query": "INSERT INTO content_queue (primary_keyword, normalized_keyword, slug, secondary_keywords, content_type, subject, priority, notes, status, created_at)\nVALUES ($1,$2,$3,$4::jsonb,$5,$6,$7,$8,'research_required',NOW())\nON CONFLICT (normalized_keyword) DO UPDATE SET updated_at=NOW()\nRETURNING id, primary_keyword, slug, status;",
        "options": {
          "queryReplacement": "={{ [$json.primary_keyword,$json.normalized_keyword,$json.slug,JSON.stringify($json.secondary_keywords),$json.content_type,$json.subject,$json.priority,$json.notes] }}"
        }
      },
      "id": "3",
      "name": "Upsert Topic",
      "type": "n8n-nodes-base.postgres",
      "typeVersion": 2.6,
      "position": [-140, 0]
    },
    {
      "parameters": {
        "respondWith": "json",
        "responseBody": "={{ { success:true, topic:$json } }}",
        "options": {}
      },
      "id": "4",
      "name": "Return Topic",
      "type": "n8n-nodes-base.respondToWebhook",
      "typeVersion": 1.4,
      "position": [100, 0]
    }
  ],
  "connections": {
    "Topic Webhook": {
      "main": [[{ "node": "Normalize Topic", "type": "main", "index": 0 }]]
    },
    "Normalize Topic": {
      "main": [[{ "node": "Upsert Topic", "type": "main", "index": 0 }]]
    },
    "Upsert Topic": {
      "main": [[{ "node": "Return Topic", "type": "main", "index": 0 }]]
    }
  },
  "settings": {
    "executionOrder": "v1",
    "timezone": "Europe/Moscow"
  },
  "pinData": {},
  "tags": []
}
```


***

# JSON workflow: 02_Research_And_Facts_MVP

```json
{
  "name": "02_Research_And_Facts_MVP",
  "nodes": [
    {
      "parameters": {
        "rule": {
          "interval": [{ "field": "hours", "hoursInterval": 1 }]
        }
      },
      "id": "1",
      "name": "Hourly",
      "type": "n8n-nodes-base.scheduleTrigger",
      "typeVersion": 1.2,
      "position": [-700, 0]
    },
    {
      "parameters": {
        "operation": "executeQuery",
        "query": "SELECT subject, jsonb_agg(jsonb_build_object('id',id,'primary_keyword',primary_keyword,'secondary_keywords',secondary_keywords,'content_type',content_type) ORDER BY priority DESC) AS topics\nFROM content_queue\nWHERE status='research_required'\nGROUP BY subject\nORDER BY max(priority) DESC\nLIMIT 1;",
        "options": {}
      },
      "id": "2",
      "name": "Get Research Cluster",
      "type": "n8n-nodes-base.postgres",
      "typeVersion": 2.6,
      "position": [-480, 0]
    },
    {
      "parameters": {
        "conditions": {
          "options": { "typeValidation": "strict", "version": 2 },
          "conditions": [
            {
              "id": "has-subject",
              "leftValue": "={{ $json.subject }}",
              "rightValue": "",
              "operator": { "type": "string", "operation": "notEmpty", "singleValue": true }
            }
          ],
          "combinator": "and"
        },
        "options": {}
      },
      "id": "3",
      "name": "Cluster Found?",
      "type": "n8n-nodes-base.if",
      "typeVersion": 2.2,
      "position": [-260, 0]
    },
    {
      "parameters": {
        "operation": "executeQuery",
        "query": "UPDATE content_queue SET status='researching', updated_at=NOW() WHERE id = ANY($1::bigint[]) RETURNING id;",
        "options": {
          "queryReplacement": "={{ [ $json.topics.map(t=>t.id) ] }}"
        }
      },
      "id": "4",
      "name": "Mark Researching",
      "type": "n8n-nodes-base.postgres",
      "typeVersion": 2.6,
      "position": [-40, -80]
    },
    {
      "parameters": {
        "method": "POST",
        "url": "http://worker:8090/jobs/research",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [{ "name": "X-Worker-Token", "value": "REPLACE_WORKER_TOKEN" }]
        },
        "sendBody": true,
        "contentType": "raw",
        "rawContentType": "application/json",
        "body": "={{ JSON.stringify({subject:$('Get Research Cluster').first().json.subject,topics:$('Get Research Cluster').first().json.topics}) }}",
        "options": { "timeout": 600000 }
      },
      "id": "5",
      "name": "Run Research Skill",
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.2,
      "position": [180, -80]
    },
    {
      "parameters": {
        "jsCode": "const r=$json; const ids=$('Get Research Cluster').first().json.topics.map(t=>t.id); const conflicts=Array.isArray(r.conflicts)?r.conflicts:[]; return [{json:{topic_ids:ids,result:r,next_status:conflicts.length?'facts_review':'ready_to_write'}}];"
      },
      "id": "6",
      "name": "Validate Research Result",
      "type": "n8n-nodes-base.code",
      "typeVersion": 2,
      "position": [400, -80]
    },
    {
      "parameters": {
        "operation": "executeQuery",
        "query": "UPDATE content_queue SET status=$2, research_report=$3::jsonb, updated_at=NOW() WHERE id = ANY($1::bigint[]);",
        "options": {
          "queryReplacement": "={{ [$json.topic_ids,$json.next_status,JSON.stringify($json.result)] }}"
        }
      },
      "id": "7",
      "name": "Save Research Result",
      "type": "n8n-nodes-base.postgres",
      "typeVersion": 2.6,
      "position": [620, -80]
    }
  ],
  "connections": {
    "Hourly": {
      "main": [[{ "node": "Get Research Cluster", "type": "main", "index": 0 }]]
    },
    "Get Research Cluster": {
      "main": [[{ "node": "Cluster Found?", "type": "main", "index": 0 }]]
    },
    "Cluster Found?": {
      "main": [[{ "node": "Mark Researching", "type": "main", "index": 0 }], []]
    },
    "Mark Researching": {
      "main": [[{ "node": "Run Research Skill", "type": "main", "index": 0 }]]
    },
    "Run Research Skill": {
      "main": [[{ "node": "Validate Research Result", "type": "main", "index": 0 }]]
    },
    "Validate Research Result": {
      "main": [[{ "node": "Save Research Result", "type": "main", "index": 0 }]]
    }
  },
  "settings": {
    "executionOrder": "v1",
    "timezone": "Europe/Moscow"
  },
  "pinData": {},
  "tags": []
}
```


***

# Что дальше

Следующим сообщением я могу дать тебе **продолжение в таком же `.md` формате**:

1. `03_Generate_Article_MVP.json`
2. `04_Quality_Control_MVP.json`
3. `05_WordPress_Draft_And_Approval_MVP.json`
4. пошаговый импорт в n8n
5. тестовые `curl` команды
6. чек-лист “если сломалось — где смотреть”

Потому что если всё это добавить в один ответ целиком, получится уже слишком громоздко и неудобно для копирования.

<div align="center">⁂</div>

[^1]: dump_dialoga_set_blogov_payka_n8n-8.md

[^2]: dump_dialoga_set_blogov_payka_n8n-9.md

[^3]: 01_Content_Intake-6.json

[^4]: 02_Research_And_Facts-7.json

[^5]: 03_Generate_Article.json

[^6]: 05_WordPress_Draft_And_Approval-3.json

[^7]: 06_SEO_Monitoring-4.json

[^8]: 04_Quality_Control-2.json

[^9]: 07_Error_And_Budget_Control-5.json

[^10]: https://ai.google.dev/gemini-api/docs/pricing

[^11]: https://ai.google.dev/gemini-api/docs/rate-limits

[^12]: https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/

[^13]: https://developer.wordpress.org/rest-api/reference/application-passwords/

[^14]: https://yandex.ru/dev/webmaster/doc/ru/reference/host-search-queries-history-all

