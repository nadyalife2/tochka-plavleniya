"""Локальный API сервер дашборда «Точка Плавления».

Стандартная библиотека Python (http.server) — без сторонних зависимостей.
Работает с SQLite базой seo/data/seo.db.

Запуск:
    python seo/scripts/server.py --port 8765
Открывает UI:
    http://127.0.0.1:8765
"""
from __future__ import annotations

import argparse
import json
import mimetypes
import os
import re
import subprocess
import sys
import threading
import time
import urllib.error
import urllib.request
from datetime import datetime, timedelta
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs, urlparse

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

ROOT = Path(__file__).resolve().parents[2]
SEO = ROOT / "seo"
DATA = SEO / "data"
SCRIPTS = SEO / "scripts"
DASHBOARD_DIR = SEO / "dashboard"
sys.path.insert(0, str(SCRIPTS))

from db import (DB_PATH, backup_database, build_export_manifest, export_manifest_html,
                export_manifest_markdown, get_connection, init_db, list_backups,
                list_voices, migrate_schema_v3, migrate_schema_v4, migrate_schema_v5)


def slugify(title: str) -> str:
    table = {
        "а": "a", "б": "b", "в": "v", "г": "g", "д": "d", "е": "e", "ё": "e",
        "ж": "zh", "з": "z", "и": "i", "й": "y", "к": "k", "л": "l", "м": "m",
        "н": "n", "о": "o", "п": "p", "р": "r", "с": "s", "т": "t", "у": "u",
        "ф": "f", "х": "h", "ц": "c", "ч": "ch", "ш": "sh", "щ": "sch", "ъ": "",
        "ы": "y", "ь": "", "э": "e", "ю": "yu", "я": "ya",
    }
    out = []
    for char in (title or "").lower():
        if char in table:
            out.append(table[char])
        elif char.isalnum():
            out.append(char)
        else:
            out.append("-")
    slug = re.sub(r"-{2,}", "-", "".join(out)).strip("-")
    return slug[:70] or "article"


# Долгие агенты выполняются в фоновом потоке: обход сайтов не влезает в HTTP-таймаут.
LONG_AGENTS = {"00-research", "bot-analyzer"}
JOB_TIMEOUT_SECONDS = 600


# --- Oxion AI: роли моделей и клиент с ротацией ключей ---------------------
OXION_BASE_URL = "https://oxionai.com/v1"
OXION_ROLE_MODELS = {
    "writer": "claude-sonnet-5",
    "reviewer_critical": "claude-opus-4.6",
    "reviewer_default": "gemini-3.7-flash",
    "factcheck": "deepseek-v4-pro-0813",
}
OXION_REF_NAMES = ("OXION_KEY_ACTIVE", "OXION_KEY_1", "OXION_KEY_2")

REVIEW_SYSTEM_PROMPT = """Ты — рецензент (ОТК) проекта «Точка Плавления» о пайке.
Проверь статью строго по редполитике:
1. Answer First: суть в первых 30-50 словах, обращение на «Вы», без вымышленного опыта мастера.
2. Факты и числа: каждая температура/режим/норма должна иметь обоснование; ищи подмены понятий (припой/флюс, уставка/температура шва, плавление/уставка).
3. Структура: 4-8 H2, 1-2 сопоставительные таблицы, FAQ 4-6 пар в служебном блоке, источники 2-4.
4. Объём: 5 500-7 000 знаков с пробелами.
5. «ИИ-слоп»: «в современном мире», «важно отметить», «не просто X, а Y», самовопросы, тройки прилагательных, натянутые шутки.
Верни: вердикт (ПРИНЯТО / ДОРАБОТКА), 3-8 конкретных замечаний с цитатами, список блокеров (если есть), и что править в первую очередь. Без переписывания статьи."""

FACTCHECK_SYSTEM_PROMPT = """Ты — независимый фактчекер проекта «Точка Плавления» (пайка, электроника).
Задача: проверить статью по фактам и числам, не переписывая её.
1. Выпиши каждое техническое число (температура, мощность, время, состав, ГОСТ/IPC) и укажи, обосновано ли оно в тексте.
2. Найди физические противоречия и опасные утверждения (сеть 220 В, токсичность флюсов, перегрев).
3. Отметь утверждения, которые нельзя проверить по тексту (нет источника/расчёта).
4. Дай таблицу: утверждение | статус (подтверждено / требует источника / ошибка) | комментарий.
Вердикт: СТАТЬЯ ФАКТИЧЕСКИ НАДЁЖНА / ТРЕБУЕТ ПРАВОК."""


def _read_oxion_keys() -> list[str]:
    """Ключи Oxion: активный слот, затем два аккаунта (env -> ~/.dsh/.env)."""
    keys: list[str] = []
    seen: set[str] = set()

    def add(value) -> None:
        text = (value or "").strip().strip('"').strip("'")
        if text.startswith("cc_") and text not in seen:
            seen.add(text)
            keys.append(text)

    for ref in OXION_REF_NAMES:
        add(os.environ.get(ref))
    dotenv = Path.home() / ".dsh" / ".env"
    if dotenv.exists():
        try:
            for line in dotenv.read_text(encoding="utf-8").splitlines():
                line = line.strip()
                if not line or line.startswith("#") or "=" not in line:
                    continue
                name, _, value = line.partition("=")
                if name.strip() in OXION_REF_NAMES:
                    add(value)
        except Exception:  # noqa: BLE001
            pass
    return keys


def oxion_chat(model: str, system_prompt: str, user_prompt: str,
               max_tokens: int = 8000, temperature: float = 0.4) -> tuple[str, str]:
    """Запрос к Oxion с авто-переключением ключей при 401/402/403/429."""
    keys = _read_oxion_keys()
    if not keys:
        raise RuntimeError(
            "Нет ключей Oxion. Впишите OXION_KEY_ACTIVE, OXION_KEY_1, OXION_KEY_2 "
            "в ~/.dsh/.env и перезапустите сервер дашборда."
        )
    body = json.dumps({
        "model": model,
        "messages": [
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": user_prompt},
        ],
        "max_tokens": max_tokens,
        "temperature": temperature,
    }, ensure_ascii=False).encode("utf-8")
    last_error = ""
    for index, key in enumerate(keys):
        request = urllib.request.Request(
            OXION_BASE_URL + "/chat/completions",
            data=body,
            headers={"Authorization": "Bearer " + key, "Content-Type": "application/json"},
            method="POST",
        )
        try:
            with urllib.request.urlopen(request, timeout=300) as response:
                data = json.loads(response.read().decode("utf-8"))
            content = ((data.get("choices") or [{}])[0].get("message") or {}).get("content") or ""
            if content.strip():
                label = model if index == 0 else f"{model} (ключ #{index + 1})"
                return content, label
            last_error = "пустой ответ модели"
        except urllib.error.HTTPError as ex:
            last_error = f"HTTP {ex.code}: {ex.read().decode('utf-8', 'replace')[:300]}"
            if ex.code not in (401, 402, 403, 429):
                break
        except Exception as ex:  # noqa: BLE001
            last_error = f"{type(ex).__name__}: {ex}"
    raise RuntimeError(f"Oxion недоступен: {last_error}")


def _env_value(name: str) -> str:
    """Значение из env, иначе из ~/.dsh/.env."""
    value = (os.environ.get(name) or "").strip()
    if value:
        return value.strip('"').strip("'")
    dotenv = Path.home() / ".dsh" / ".env"
    if dotenv.exists():
        try:
            for line in dotenv.read_text(encoding="utf-8").splitlines():
                line = line.strip()
                if not line or line.startswith("#") or "=" not in line:
                    continue
                key, _, raw = line.partition("=")
                if key.strip() == name:
                    return raw.strip().strip('"').strip("'")
        except Exception:  # noqa: BLE001
            pass
    return ""


def yandex_chat(system_prompt: str, user_prompt: str, max_tokens: int = 8000) -> tuple[str, str]:
    """Yandex AI Studio (OpenAI-compatible), модель YandexGPT."""
    key = _env_value("YANDEX_AI_API_KEY")
    folder = _env_value("YANDEX_FOLDER_ID") or "b1gtjgvrfp1vcc24p18n"
    if not key:
        raise RuntimeError("Нет ключа YANDEX_AI_API_KEY в ~/.dsh/.env.")
    model = f"gpt://{folder}/yandexgpt/latest"
    body = json.dumps({
        "model": model,
        "messages": [
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": user_prompt},
        ],
        "max_tokens": max_tokens,
        "temperature": 0.4,
    }, ensure_ascii=False).encode("utf-8")
    request = urllib.request.Request(
        "https://ai.api.cloud.yandex.net/v1/chat/completions",
        data=body,
        headers={"Authorization": "Bearer " + key, "Content-Type": "application/json"},
        method="POST",
    )
    try:
        with urllib.request.urlopen(request, timeout=300) as response:
            data = json.loads(response.read().decode("utf-8"))
    except urllib.error.HTTPError as ex:
        raise RuntimeError(f"Yandex AI Studio HTTP {ex.code}: {ex.read().decode('utf-8', 'replace')[:300]}")
    content = ((data.get("choices") or [{}])[0].get("message") or {}).get("content") or ""
    if not content.strip():
        raise RuntimeError("Yandex AI Studio вернул пустой ответ.")
    return content, model


VOICE_MODEL_PREFIX = ("oxion:", "yandex:")


def voice_generate(model: str, system_prompt: str, user_prompt: str,
                   max_tokens: int = 12000, temperature: float = 0.35) -> tuple[str, str]:
    """Генерация выбранной моделью: `oxion:<id>` или `yandex:<id>`."""
    spec = (model or "oxion:claude-sonnet-5").strip()
    if spec.startswith("yandex:"):
        return yandex_chat(system_prompt, user_prompt, max_tokens=max_tokens)
    model_id = spec.split(":", 1)[1] if spec.startswith("oxion:") else spec
    return oxion_chat(model_id, system_prompt, user_prompt, max_tokens=max_tokens, temperature=temperature)


def _topic_title(topic_id) -> str:
    with get_connection() as conn:
        row = conn.execute("SELECT title FROM topics WHERE id = ?", (topic_id,)).fetchone()
    return (row["title"] if row else "") or f"тема #{topic_id}"


def _save_written_article(topic_id, text: str) -> None:
    """Сохраняет готовый текст в topics и в файл «сами статьи/»."""
    chars = len(text.strip())
    slug = f"article-{topic_id}"
    with get_connection() as conn:
        row = conn.execute("SELECT slug FROM topics WHERE id = ?", (topic_id,)).fetchone()
        slug = row["slug"] if row else slug
        conn.execute(
            "UPDATE topics SET content_markdown = ?, chars = ?, status = 'written', "
            "writer_mode = 'draft', updated_at = ? WHERE id = ?",
            (text, chars, datetime.now().isoformat(timespec="seconds"), topic_id),
        )
        conn.commit()
    try:
        (ROOT / "сами статьи" / f"{topic_id}-{slug}.md").write_text(text, encoding="utf-8")
    except Exception:  # noqa: BLE001
        pass


def start_background_job(agent: str, cmd: list[str], target_id=None) -> int:
    """Ставит задачу в таблицу jobs и запускает её в отдельном потоке."""
    now_str = datetime.now().isoformat(timespec="seconds")
    with get_connection() as conn:
        cur = conn.execute(
            "INSERT INTO jobs (job_type, target_id, params_json, status, created_at, updated_at) "
            "VALUES (?, ?, ?, 'running', ?, ?)",
            (agent, str(target_id or ""), json.dumps(cmd, ensure_ascii=False), now_str, now_str),
        )
        job_id = cur.lastrowid
        conn.commit()

    def worker() -> None:
        status, return_code, output = "failed", None, ""
        try:
            proc = subprocess.run(
                cmd, capture_output=True, text=True, encoding="utf-8",
                timeout=JOB_TIMEOUT_SECONDS, cwd=str(ROOT),
            )
            output = (proc.stdout or "") + (("\n" + proc.stderr) if proc.stderr else "")
            return_code = proc.returncode
            status = "succeeded" if return_code == 0 else "failed"
        except subprocess.TimeoutExpired:
            output = f"Таймаут: задача выполнялась дольше {JOB_TIMEOUT_SECONDS} секунд."
        except Exception as ex:  # noqa: BLE001
            output = f"{type(ex).__name__}: {ex}"

        ts = datetime.now().isoformat(timespec="seconds")
        # Обновление статуса с повторами: SQLite может быть занят дашбордом.
        for attempt in range(5):
            try:
                with get_connection() as conn:
                    conn.execute(
                        "UPDATE jobs SET status = ?, return_code = ?, log = ?, updated_at = ? WHERE id = ?",
                        (status, return_code, output[:20000], ts, job_id),
                    )
                    conn.execute(
                        "INSERT INTO agent_runs (agent_name, action, target_id, status, log, created_at) "
                        "VALUES (?, 'background', ?, ?, ?, ?)",
                        (agent, str(target_id or ""), status, output[:2000], ts),
                    )
                    conn.commit()
                break
            except Exception:
                time.sleep(1 + attempt)

    threading.Thread(target=worker, daemon=True).start()
    return job_id


def mark_stale_jobs() -> int:
    """Помечает задачи, зависшие дольше таймаута: статус не должен висеть «running» вечно."""
    cutoff = (datetime.now() - timedelta(seconds=JOB_TIMEOUT_SECONDS + 120)).isoformat(timespec="seconds")
    try:
        with get_connection() as conn:
            cur = conn.execute(
                "UPDATE jobs SET status = 'failed', log = COALESCE(log, '') || ?, updated_at = ? "
                "WHERE status = 'running' AND updated_at < ?",
                ("\nТаймаут: задача не завершилась за отведённое время (процесс мог зависнуть).",
                 datetime.now().isoformat(timespec="seconds"), cutoff),
            )
            conn.commit()
            return cur.rowcount or 0
    except Exception:
        return 0


def fetch_all_data() -> dict:
    with get_connection() as conn:
        topics = [dict(r) for r in conn.execute("SELECT * FROM topics ORDER BY priority_score DESC, id ASC").fetchall()]
        clusters = [dict(r) for r in conn.execute("SELECT * FROM clusters ORDER BY priority_rank ASC, name ASC").fetchall()]
        links = [dict(r) for r in conn.execute("SELECT * FROM links").fetchall()]
        competitors = [dict(r) for r in conn.execute("SELECT * FROM competitors ORDER BY score DESC, articles_count DESC").fetchall()]
        comp_prop = [dict(r) for r in conn.execute("SELECT * FROM competitors_proposed ORDER BY hits DESC").fetchall()]
        top_prop = [dict(r) for r in conn.execute("SELECT * FROM topics_proposed ORDER BY priority_score DESC").fetchall()]
        backups = [dict(r) for r in conn.execute("SELECT * FROM backups ORDER BY id DESC LIMIT 15").fetchall()]
        runs = [dict(r) for r in conn.execute("SELECT * FROM agent_runs ORDER BY id DESC LIMIT 20").fetchall()]
        features = [dict(r) for r in conn.execute("SELECT * FROM competitor_features ORDER BY id DESC LIMIT 50").fetchall()]

    # Добавляем ссылки к темам
    links_by_src = {}
    links_by_dst = {}
    for l in links:
        links_by_src.setdefault(l["source_id"], []).append(l)
        links_by_dst.setdefault(l["target_id"], []).append(l)

    for t in topics:
        t["out_links"] = links_by_src.get(t["id"], [])
        t["in_links"] = links_by_dst.get(t["id"], [])
        if t["score_json"]:
            try:
                t["score_data"] = json.loads(t["score_json"])
            except Exception:
                t["score_data"] = None
        else:
            t["score_data"] = None

    return {
        "status": "ok",
        "timestamp": datetime.now().isoformat(timespec="seconds"),
        "topics": topics,
        "clusters": clusters,
        "competitors": competitors,
        "competitors_proposed": comp_prop,
        "topics_proposed": top_prop,
        "backups": backups,
        "agent_runs": runs,
        "competitor_features": features,
        "counts": {
            "topics": len(topics),
            "written": sum(1 for t in topics if (t.get("chars") or 0) > 500),
            "competitors": len(competitors),
            "proposed_topics": len(top_prop),
            "proposed_competitors": len(comp_prop),
            "backups": len(backups),
        },
    }


def parse_firecrawl_markdown(md: str) -> dict:
    """Извлекает конкурентов, кластеры, ключевые слова и страницы из отчёта Firecrawl Markdown."""
    pkg = {"competitors": [], "raw_data": [], "clusters": [], "keywords": []}
    if not md:
        return pkg

    # 1. Таблица конкурентов
    comp_match = re.search(r"## Конкуренты\s*\n(.*?)(?=\n##|\Z)", md, re.S)
    if comp_match:
        for line in comp_match.group(1).splitlines():
            line = line.strip()
            if not line.startswith("|") or "Домен" in line or "|---" in line:
                continue
            parts = [p.strip() for p in line.split("|")[1:-1]]
            if len(parts) >= 2 and "." in parts[0]:
                pkg["competitors"].append({
                    "domain": parts[0].lower(),
                    "type": parts[1] if len(parts) > 1 else "competitor",
                    "notes": f"Прочитано: {parts[2]}" if len(parts) > 2 else "",
                })

    # 2. Кластеры и ключевые слова (### C01. Название — P1)
    cluster_blocks = re.findall(r"### (C\d+)\.\s*([^—\n]+)(?:—\s*([^\n]+))?\s*\n(.*?)(?=\n###|\n##|\Z)", md, re.S)
    for cid, cname, cpriority, body in cluster_blocks:
        cname = cname.strip()
        h1_m = re.search(r"Предлагаемый H1:\s*(.+)", body)
        slug_m = re.search(r"Slug:\s*`([^`]+)`", body)
        key_m = re.search(r"Основной ключ:\s*(.+)", body)
        sec_keys = [k.strip() for k in re.findall(r"^-\s+(.+)$", body, re.M) if k.strip()]

        primary_k = key_m.group(1).strip() if key_m else cname.lower()
        slug = slug_m.group(1).strip() if slug_m else ""
        h1 = h1_m.group(1).strip() if h1_m else cname

        pkg["clusters"].append({
            "id": cid,
            "name": cname,
            "proposed_slug": slug,
            "proposed_h1": h1,
            "primary_keyword": primary_k,
            "secondary_keywords": sec_keys,
            "editorial_priority": (cpriority or "P2").strip(),
        })

        if primary_k:
            pkg["keywords"].append({"keyword": primary_k, "cluster_id": cid})
        for sk in sec_keys:
            if sk:
                pkg["keywords"].append({"keyword": sk, "cluster_id": cid})

    # 3. Карточки прочитанных страниц (### P01. Title)
    page_blocks = re.findall(r"### (P\d+)\.\s*([^\n]+)\s*\n(.*?)(?=\n###|\n##|\Z)", md, re.S)
    for pid, ptitle, body in page_blocks:
        url_m = re.search(r"URL:\s*(https?://[^\s\n]+)", body)
        h1_m = re.search(r"H1(?: из Markdown)?:\s*([^\n]+)", body)
        type_m = re.search(r"формат:\s*([^;\n]+)", body)
        topic_m = re.search(r"тема:\s*([^\n]+)", body)
        if url_m:
            u = url_m.group(1).strip()
            d = urlparse(u).netloc.replace("www.", "")
            pkg["raw_data"].append({
                "id": pid,
                "url": u,
                "domain": d,
                "title": ptitle.strip(),
                "h1": h1_m.group(1).strip() if h1_m else ptitle.strip(),
                "content_type": type_m.group(1).strip() if type_m else "article",
                "topic": topic_m.group(1).strip() if topic_m else "",
            })

    return pkg


class DashboardHandler(BaseHTTPRequestHandler):
    def log_message(self, format, *args):
        # Тихий логгер, чтобы не спамить в консоль
        sys.stderr.write(f"[{datetime.now().strftime('%H:%M:%S')}] {args[0]} {args[1]}\n")

    def send_json(self, data: dict, status: int = 200):
        body = json.dumps(data, ensure_ascii=False).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type")
        self.end_headers()
        self.wfile.write(body)

    def do_OPTIONS(self):
        self.send_response(200)
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type")
        self.end_headers()

    def do_GET(self):
        url = urlparse(self.path)
        path = url.path

        if path == "/api/data":
            self.send_json(fetch_all_data())
            return

        # Экспорт «что уже есть»: JSON / Markdown / HTML для нейронки
        if path in ("/api/export.json", "/api/export.md", "/api/export.html"):
            try:
                manifest = build_export_manifest()
                if path == "/api/export.json":
                    body = json.dumps(manifest, ensure_ascii=False, indent=1).encode("utf-8")
                    ctype, filename = "application/json; charset=utf-8", "tchp-existing-base.json"
                elif path == "/api/export.md":
                    body = export_manifest_markdown(manifest).encode("utf-8")
                    ctype, filename = "text/markdown; charset=utf-8", "tchp-existing-base.md"
                else:
                    body = export_manifest_html(manifest).encode("utf-8")
                    ctype, filename = "text/html; charset=utf-8", "tchp-existing-base.html"
            except Exception as ex:  # noqa: BLE001
                self.send_json({"status": "error", "message": str(ex)}, 500)
                return
            self.send_response(200)
            self.send_header("Content-Type", ctype)
            self.send_header("Content-Length", str(len(body)))
            if path != "/api/export.html":
                self.send_header("Content-Disposition", f'attachment; filename="{filename}"')
            self.send_header("Access-Control-Allow-Origin", "*")
            self.end_headers()
            self.wfile.write(body)
            return

        if path == "/api/voices":
            try:
                self.send_json({"status": "ok", "voices": list_voices()})
            except Exception as ex:  # noqa: BLE001
                self.send_json({"status": "error", "message": str(ex)}, 500)
            return

        if path == "/api/status":
            try:
                with get_connection() as conn:
                    cnt = conn.execute("SELECT COUNT(*) FROM topics").fetchone()[0]
            except Exception:
                cnt = 0
            self.send_json({"status": "running", "topics_count": cnt, "time": datetime.now().isoformat()})
            return

        if path == "/api/mutagen/balance":
            try:
                import mutagen as mg
                bal = mg.get_balance()
                self.send_json({"status": "ok", "balance": bal, "currency": "₽"})
            except Exception as ex:  # noqa: BLE001
                self.send_json({"status": "error", "message": f"{type(ex).__name__}: {str(ex)[:150]}"}, 500)
            return

        if path == "/api/brief":
            qs = parse_qs(url.query)
            tid = (qs.get("id") or [""])[0].strip()
            if not tid:
                self.send_json({"status": "error", "message": "Укажите ?id="}, 400)
                return
            try:
                import generate_brief
                brief_data = generate_brief.build_author_brief(tid)
                self.send_json({"status": "ok", "brief": brief_data})
            except Exception as ex:  # noqa: BLE001
                self.send_json({"status": "error", "message": str(ex)}, 500)
            return

        if path == "/api/priority/top":
            try:
                with get_connection() as conn:
                    rows = conn.execute(
                        "SELECT id, title, seo_priority, seo_priority_json FROM topics "
                        "WHERE seo_priority IS NOT NULL ORDER BY seo_priority DESC LIMIT 30"
                    ).fetchall()
                items = []
                for r in rows:
                    detail = {}
                    try:
                        detail = json.loads(r["seo_priority_json"] or "{}")
                    except Exception:
                        detail = {}
                    items.append({"id": r["id"], "title": r["title"], "score": r["seo_priority"], "detail": detail})
                self.send_json({"status": "ok", "items": items})
            except Exception as ex:  # noqa: BLE001
                self.send_json({"status": "error", "message": f"{type(ex).__name__}: {str(ex)[:150]}"}, 500)
            return

        if path == "/api/keywords/suggest":
            # Подсказки фраз из SEO-данных конкурентов и хвостов Вордстата
            qs = parse_qs(url.query)
            try:
                limit = int((qs.get("limit") or ["10"])[0])
            except ValueError:
                limit = 10
            limit = max(1, min(50, limit))
            contains = (qs.get("q") or [""])[0].strip()
            try:
                import keyword_suggestions
                items = keyword_suggestions.collect(limit=limit, contains=contains)
                self.send_json({"status": "ok", "count": len(items), "suggestions": items})
            except Exception as ex:  # noqa: BLE001
                self.send_json({"status": "error", "message": f"{type(ex).__name__}: {str(ex)[:120]}"}, 500)
            return

        if path == "/api/keyword/trend":
            # История измерений по конкретной фразе (что менялось и когда)
            phrase = (parse_qs(url.query).get("phrase") or [""])[0].strip()
            if not phrase:
                self.send_json({"status": "error", "message": "Укажите ?phrase="}, 400)
                return
            ws_hist, mg_hist = [], []
            try:
                with get_connection() as conn:
                    for r in conn.execute(
                        "SELECT total_count, checked_at FROM wordstat_history WHERE phrase = ? ORDER BY checked_at ASC",
                        (phrase,),
                    ).fetchall():
                        ws_hist.append({"total_count": r["total_count"], "checked_at": r["checked_at"]})
            except Exception:
                pass
            try:
                with get_connection() as conn:
                    for r in conn.execute(
                        "SELECT strong, wordstat, tails, vital_site, checked_at FROM keyword_metrics_history "
                        "WHERE keyword = ? ORDER BY checked_at ASC",
                        (phrase,),
                    ).fetchall():
                        mg_hist.append({
                            "strong": r["strong"],
                            "wordstat": r["wordstat"],
                            "tails": r["tails"],
                            "vital_site": r["vital_site"] or "",
                            "checked_at": r["checked_at"],
                        })
            except Exception:
                pass
            self.send_json({"status": "ok", "phrase": phrase, "wordstat_history": ws_hist, "mutagen_history": mg_hist})
            return

        if path == "/api/keyword/history":
            # Единая история проверок: Вордстат (бесплатно) + Мутаген (платно)
            rows = []
            try:
                with get_connection() as conn:
                    ws_rows = conn.execute(
                        "SELECT phrase, total_count, checked_at FROM wordstat_metrics ORDER BY checked_at DESC LIMIT 100"
                    ).fetchall()
                for r in ws_rows:
                    rows.append({
                        "phrase": r["phrase"],
                        "source": "wordstat",
                        "demand": r["total_count"],
                        "competition": None,
                        "checked_at": r["checked_at"],
                    })
            except Exception:
                pass
            try:
                with get_connection() as conn:
                    mg_rows = conn.execute(
                        "SELECT keyword, strong, wordstat, vital_site, checked_at FROM keyword_metrics ORDER BY checked_at DESC LIMIT 100"
                    ).fetchall()
                for r in mg_rows:
                    rows.append({
                        "phrase": r["keyword"],
                        "source": "mutagen",
                        "demand": r["wordstat"],
                        "competition": r["strong"],
                        "vital_site": r["vital_site"] or "",
                        "checked_at": r["checked_at"],
                    })
            except Exception:
                pass
            rows.sort(key=lambda x: x.get("checked_at") or "", reverse=True)
            self.send_json({"phrases_checked": len(rows), "history": rows[:100]})
            return

        # --- фоновые задания (долгие агенты) ---
        if path == "/api/jobs":
            mark_stale_jobs()
            with get_connection() as conn:
                rows = conn.execute(
                    "SELECT id, job_type, target_id, status, return_code, created_at, updated_at "
                    "FROM jobs ORDER BY id DESC LIMIT 20"
                ).fetchall()
            self.send_json({"jobs": [dict(r) for r in rows]})
            return

        if path.startswith("/api/jobs/"):
            mark_stale_jobs()
            try:
                job_id = int(path.rsplit("/", 1)[-1])
            except ValueError:
                self.send_json({"status": "error", "message": "bad job id"}, 400)
                return
            with get_connection() as conn:
                row = conn.execute("SELECT * FROM jobs WHERE id = ?", (job_id,)).fetchone()
            if not row:
                self.send_json({"status": "error", "message": "job not found"}, 404)
                return
            self.send_json(dict(row))
            return

        # Статика: index.html
        if path in ("/", "/index.html"):
            index_path = DASHBOARD_DIR / "index.html"
            if index_path.exists():
                content = index_path.read_bytes()
                self.send_response(200)
                self.send_header("Content-Type", "text/html; charset=utf-8")
                self.send_header("Content-Length", str(len(content)))
                self.end_headers()
                self.wfile.write(content)
                return

        self.send_error(404, "File Not Found")

    def do_POST(self):
        url = urlparse(self.path)
        path = url.path
        length = int(self.headers.get("Content-Length", 0))
        body = self.rfile.read(length).decode("utf-8", "replace") if length > 0 else "{}"
        try:
            payload = json.loads(body)
        except Exception:
            payload = {}

        # 1. Бэкап БД
        if path == "/api/backup":
            try:
                res = backup_database()
                self.send_json({"status": "ok", "backup": res})
            except Exception as e:
                self.send_json({"status": "error", "message": str(e)}, 500)
            return

        # 1.5. Создание темы вручную (из головы)
        if path == "/api/topics/create":
            title = payload.get("title", "").strip()
            cluster = payload.get("cluster", "").strip() or "Материалы / Припои"
            ctype = payload.get("type", "гайд").strip()
            priority = payload.get("priority", "средний").strip()
            notes = payload.get("notes", "").strip()

            if not title:
                self.send_json({"status": "error", "message": "Введите название темы"}, 400)
                return

            with get_connection() as conn:
                cur = conn.cursor()
                max_id = cur.execute("SELECT MAX(id) FROM topics").fetchone()[0] or 0
                next_id = max_id + 1
                slug = slugify(title) + f"-{next_id}"
                priority_score = 85 if priority.lower() in ("высокий", "hot") else 60
                now_str = datetime.now().isoformat(timespec="seconds")

                cur.execute("""
                    INSERT INTO topics
                    (id, title, slug, cluster, type, priority, priority_score, status, next_action, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'backlog', ?, ?, ?)
                """, (next_id, title, slug, cluster, ctype, priority, priority_score, f"добавлено вручную: {notes}" if notes else "добавлено вручную", now_str, now_str))
                conn.commit()

            self.send_json({
                "status": "ok",
                "id": next_id,
                "title": title,
                "slug": slug,
                "cluster": cluster,
                "priority_score": priority_score,
            })
            return

        # 2. Одобрить предложенную тему -> в topics
        if path == "/api/topics/approve":
            pid = payload.get("id")
            with get_connection() as conn:
                cur = conn.cursor()
                row = cur.execute("SELECT * FROM topics_proposed WHERE id = ?", (pid,)).fetchone()
                if not row:
                    self.send_json({"status": "error", "message": "Тема не найдена"}, 404)
                    return
                # Находим следующий ID
                max_id = cur.execute("SELECT MAX(id) FROM topics").fetchone()[0] or 0
                next_id = max_id + 1
                slug = __import__("re").sub(r"[^a-z0-9\-]+", "-", row["title"].lower()).strip("-")
                cur.execute("""
                    INSERT INTO topics (id, title, slug, cluster, type, priority_score, status, next_action)
                    VALUES (?, ?, ?, ?, ?, ?, 'backlog', 'одобрена из topics_proposed')
                """, (next_id, row["title"], f"{slug}-{next_id}", row["cluster"], row["type"], row["priority_score"]))
                cur.execute("UPDATE topics_proposed SET status = 'принят' WHERE id = ?", (pid,))
                conn.commit()
            self.send_json({"status": "ok", "new_topic_id": next_id})
            return

        # 3. Отклонить предложенную тему
        if path == "/api/topics/reject":
            pid = payload.get("id")
            with get_connection() as conn:
                conn.execute("UPDATE topics_proposed SET status = 'отклонён' WHERE id = ?", (pid,))
                conn.commit()
            self.send_json({"status": "ok"})
            return

        # 4. Одобрить конкурента -> в competitors
        if path == "/api/competitors/approve":
            domain = payload.get("domain")
            with get_connection() as conn:
                cur = conn.cursor()
                row = cur.execute("SELECT * FROM competitors_proposed WHERE domain = ?", (domain,)).fetchone()
                if not row:
                    self.send_json({"status": "error", "message": "Домен не найден"}, 404)
                    return
                cur.execute("""
                    INSERT OR REPLACE INTO competitors (domain, niche, type, blog_url, articles_count, notes, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                """, (domain, row["niche"], row["type"], row["example_url"], row["articles_count"],
                      f"принят из competitors_proposed", datetime.now().strftime("%Y-%m-%d %H:%M")))
                cur.execute("UPDATE competitors_proposed SET status = 'принят' WHERE domain = ?", (domain,))
                conn.commit()
            self.send_json({"status": "ok", "domain": domain})
            return

        # 4.9. Пересчёт приоритета тем (спрос + конкуренция + интент + готовность)
        if path == "/api/priority/recalc":
            try:
                import priority_v2
                results = priority_v2.recalculate()
                top = results[:10]
                self.send_json({
                    "status": "ok",
                    "total": len(results),
                    "top": [{"id": r["id"], "title": r["title"], "score": r["total"], "grade": r["grade"]} for r in top],
                })
            except Exception as ex:  # noqa: BLE001
                self.send_json({"status": "error", "message": f"{type(ex).__name__}: {str(ex)[:150]}"}, 500)
            return

        # 5. Утвердить статью к публикации
        if path == "/api/articles/approve":
            tid = payload.get("id")
            with get_connection() as conn:
                conn.execute("UPDATE topics SET status = 'ready', next_action = 'утверждено шеф-редактором к ОТК' WHERE id = ?", (tid,))
                conn.commit()
            self.send_json({"status": "ok", "id": tid})
            return

        # 6. Вернуть статью на доработку
        if path == "/api/articles/reject":
            tid = payload.get("id")
            reason = payload.get("reason", "Замечания редактора")
            with get_connection() as conn:
                # needs_fix — отдельный статус, чтобы статья ГАРАНТИРОВАННО
                # попала в колонку «Требует правок», даже если балл был ≥ 80
                conn.execute(
                    "UPDATE topics SET status = 'needs_fix', next_action = ? WHERE id = ?",
                    (f"доработка: {reason}", tid),
                )
                conn.commit()
            self.send_json({"status": "ok", "id": tid, "new_status": "needs_fix"})
            return

        # 5.5. Флаг критичной статьи: рецензия уходит на Opus, обычная — на Gemini
        if path == "/api/articles/critical":
            tid = payload.get("id")
            flag = 1 if payload.get("critical") else 0
            with get_connection() as conn:
                conn.execute(
                    "UPDATE topics SET is_critical = ?, updated_at = ? WHERE id = ?",
                    (flag, datetime.now().isoformat(timespec="seconds"), tid),
                )
                conn.commit()
            self.send_json({"status": "ok", "id": tid, "is_critical": flag})
            return

        # 5.6. Голоса автора: список, сохранение, удаление
        if path == "/api/voices":
            try:
                self.send_json({"status": "ok", "voices": list_voices()})
            except Exception as ex:  # noqa: BLE001
                self.send_json({"status": "error", "message": str(ex)}, 500)
            return

        if path == "/api/voices/save":
            voice_id = re.sub(r"[^a-z0-9_-]+", "-", (payload.get("id") or "").strip().lower()).strip("-")
            name = (payload.get("name") or "").strip()
            prompt = (payload.get("system_prompt") or "").strip()
            model = (payload.get("model") or "oxion:claude-sonnet-5").strip()
            if not voice_id or not name or len(prompt) < 50:
                self.send_json({"status": "error",
                                "message": "Нужны id, название и инструкция голоса (≥ 50 знаков)."}, 400)
                return
            now = datetime.now().isoformat(timespec="seconds")
            with get_connection() as conn:
                conn.execute(
                    "INSERT INTO voices (id, name, system_prompt, model, is_active, updated_at) "
                    "VALUES (?, ?, ?, ?, 1, ?) "
                    "ON CONFLICT(id) DO UPDATE SET name = excluded.name, "
                    "system_prompt = excluded.system_prompt, model = excluded.model, "
                    "is_active = 1, updated_at = excluded.updated_at",
                    (voice_id, name, prompt, model, now),
                )
                conn.commit()
            self.send_json({"status": "ok", "id": voice_id, "updated_at": now})
            return

        if path == "/api/voices/delete":
            voice_id = (payload.get("id") or "").strip()
            with get_connection() as conn:
                conn.execute("UPDATE voices SET is_active = 0 WHERE id = ?", (voice_id,))
                conn.commit()
            self.send_json({"status": "ok", "id": voice_id})
            return

        # 6.5. Сохранить отредактированный текст статьи
        if path == "/api/topics/update-text":
            tid = payload.get("id")
            new_text = payload.get("content_markdown", "")
            now_str = datetime.now().isoformat(timespec="seconds")
            chars = len(new_text.strip())

            with get_connection() as conn:
                row = conn.execute("SELECT slug FROM topics WHERE id = ?", (tid,)).fetchone()
                slug = row["slug"] if row else f"article-{tid}"
                conn.execute("""
                    UPDATE topics
                    SET content_markdown = ?, chars = ?, updated_at = ?
                    WHERE id = ?
                """, (new_text, chars, now_str, tid))
                conn.commit()

            # Синхронизируем файл на диске в `сами статьи/`
            art_file = ROOT / "сами статьи" / f"{tid}-{slug}.md"
            try:
                art_file.write_text(new_text, encoding="utf-8")
            except Exception:
                pass

            self.send_json({"status": "ok", "id": tid, "chars": chars})
            return

        # 7. Запуск субагента
        if path == "/api/agent/run":
            agent = payload.get("agent")
            target_id = payload.get("target_id")

            # Долгие агенты (обход сайтов) выполняются в фоне: HTTP-запрос
            # не должен ждать десятки секунд и обрываться по таймауту.
            if agent in LONG_AGENTS:
                if agent == "00-research":
                    cmd = [sys.executable, str(SCRIPTS / "research_competitors.py"),
                           "--limit", "10", "--max-domains", "5", "--budget", "60"]
                else:
                    domain = payload.get("domain", "")
                    cmd = [sys.executable, str(SCRIPTS / "bot_competitor_analyzer.py")]
                    cmd.extend(["--domain", domain] if domain else ["--all"])
                job_id = start_background_job(agent, cmd, target_id)
                self.send_json({
                    "status": "queued",
                    "agent": agent,
                    "job_id": job_id,
                    "log": f"Задача поставлена в очередь (job #{job_id}). Прогресс: /api/jobs/{job_id}",
                })
                return

            log_output = f"Запуск {agent}..."
            status = "completed"

            try:
                if agent == "delivery" and target_id:
                    from package_contract import package_article
                    res = package_article(target_id)
                    if not res["valid"]:
                        errors_str = "\n".join([f"  • {e}" for e in (res.get("missing_warnings", []) + res.get("errors", []))])
                        log_output = f"❌ Статья #{target_id} не готова к доставке в WordPress (нарушение схемы v1.1):\n{errors_str}\n\nУстраните замечания перед отправкой черновика."
                        status = "failed"
                    else:
                        contract_path = DATA / f"contract_{target_id}.json"
                        contract_path.write_text(json.dumps(res["contract"], ensure_ascii=False, indent=2), encoding="utf-8")
                        delivery_tool = ROOT / "tools" / "delivery_cli.py"
                        cmd = [sys.executable, str(delivery_tool), "send-draft", "--file", str(contract_path), "--dry-run"]
                        p = subprocess.run(cmd, capture_output=True, text=True, encoding="utf-8", timeout=30)
                        log_output = f"✓ Пакет статьи #{target_id} 100% валиден по схеме v1.1!\n" + p.stdout + "\n" + p.stderr
                        status = "succeeded" if p.returncode == 0 else "failed"
                elif agent == "04-scorer" and target_id:
                    with get_connection() as conn:
                        row = conn.execute("SELECT content_markdown FROM topics WHERE id = ?", (target_id,)).fetchone()
                        content = row["content_markdown"] if row else ""
                    if content and len(content) > 200:
                        tmp_file = DATA / f"tmp_score_{target_id}.md"
                        tmp_file.write_text(content, encoding="utf-8")
                        cmd = [sys.executable, str(SCRIPTS / "score_article.py"), "--file", str(tmp_file), "--id", str(target_id)]
                        p = subprocess.run(cmd, capture_output=True, text=True, encoding="utf-8", timeout=30)
                        log_output = p.stdout + ("\n" + p.stderr if p.stderr else "")
                        # Коды скорера: 0 (passed >=80), 2 (needs_revision 60-79), 3 (low <60), 4 (blocker).
                        # Все они являются успешным завершением аналитической проверки, а не падением сервера!
                        status = "succeeded" if p.returncode in (0, 2, 3, 4) else "failed"
                        if tmp_file.exists():
                            try:
                                tmp_file.unlink()
                            except Exception:
                                pass
                        # Обновляем скор в БД
                        score_json_file = SEO / "out" / "scores" / f"{target_id}.json"
                        if score_json_file.exists():
                            s_data = json.loads(score_json_file.read_text(encoding="utf-8"))
                            with get_connection() as conn:
                                conn.execute("UPDATE topics SET score_total = ?, score_json = ? WHERE id = ?",
                                             (s_data.get("total"), json.dumps(s_data, ensure_ascii=False), target_id))
                                conn.commit()
                    else:
                        log_output = f"У темы #{target_id} нет текста для оценки (требуется черновик)."
                        status = "failed"
                elif agent == "02-writer" and target_id:
                    voice = (payload.get("voice") or "practico").strip()
                    mode = (payload.get("mode") or "draft").strip().lower()
                    voices = {v["id"]: v for v in list_voices()}
                    voice_row = voices.get(voice)
                    if voice_row is None:
                        voice = "practico"
                        voice_row = voices.get("practico")
                    model_spec = (payload.get("model") or (voice_row or {}).get("model")
                                  or "oxion:claude-sonnet-5").strip()
                    system_prompt = ((voice_row or {}).get("system_prompt") or "").strip()
                    if not system_prompt:
                        try:
                            from writer_practico import PRACTICO2_SYSTEM_PROMPT, PRACTICO_SYSTEM_PROMPT
                            system_prompt = (
                                PRACTICO2_SYSTEM_PROMPT if voice == "practico2"
                                else PRACTICO_SYSTEM_PROMPT
                            )
                        except Exception:  # noqa: BLE001
                            system_prompt = (
                                "Ты — автор проекта «Точка Плавления» о пайке. Обращение на «Вы», "
                                "без вымышленного опыта мастера, Answer First, живая ирония без натянутых шуток."
                            )
                    voice_name = (voice_row or {}).get("name") or voice

                    if mode == "paste":
                        pasted = (payload.get("pasted_text") or "").strip()
                        if len(pasted) < 200:
                            log_output = "Вставленный текст слишком короткий (нужно ≥ 200 знаков)."
                            status = "failed"
                        else:
                            instruction = (
                                "Ниже готовый текст автора. Приведи его к стандартам проекта и голосу: "
                                "Answer First ≤ 40 слов, 4-8 разделов H2, 1-2 сопоставительные таблицы, "
                                "FAQ 4-6 пар в блоке '### FAQ:', источники 2-4 в '## Источники:', "
                                "объём 5 500-7 000 знаков с пробелами. Сохрани все факты и числа автора, "
                                "не выдумывай новых. Верни только готовый markdown.\n\n"
                            )
                            answer, used = voice_generate(
                                model_spec, system_prompt, instruction + pasted,
                                max_tokens=12000, temperature=0.3,
                            )
                            chars = len(answer.strip())
                            slug = f"article-{target_id}"
                            with get_connection() as conn:
                                row = conn.execute("SELECT slug FROM topics WHERE id = ?", (target_id,)).fetchone()
                                slug = row["slug"] if row else slug
                                conn.execute(
                                    "UPDATE topics SET content_markdown = ?, chars = ?, status = 'written', "
                                    "writer_mode = 'paste', next_action = ?, updated_at = ? WHERE id = ?",
                                    (answer, chars, f"адаптировано: {voice_name} · {used}",
                                     datetime.now().isoformat(timespec="seconds"), target_id),
                                )
                                conn.commit()
                            try:
                                (ROOT / "сами статьи" / f"{target_id}-{slug}.md").write_text(answer, encoding="utf-8")
                            except Exception:  # noqa: BLE001
                                pass
                            log_output = f"✅ Готовый текст адаптирован: {voice_name} · {used} ({chars} зн.)\n\n{answer[:1500]}"
                            status = "succeeded"
                    else:
                        provider = (payload.get("provider") or "auto").strip().lower()
                        if provider not in ("auto", "claude", "deepseek", "yandex"):
                            provider = "auto"
                        custom_voice = voice not in ("practico", "practico2")
                        if custom_voice:
                            instruction = (
                                "Напиши статью по теме «" + _topic_title(target_id) + "» в этом голосе. "
                                "Стандарты: Answer First ≤ 40 слов, 4-8 H2, 1-2 таблицы, FAQ 4-6 пар "
                                "в блоке '### FAQ:', источники 2-4 в '## Источники:', объём 5 500-7 000 знаков. "
                                "Верни только markdown."
                            )
                            answer, used = voice_generate(
                                model_spec, system_prompt, instruction,
                                max_tokens=12000, temperature=0.4,
                            )
                            _save_written_article(target_id, answer)
                            log_output = f"✅ Статья написана: {voice_name} · {used} ({len(answer)} зн.)\n\n{answer[:1500]}"
                            status = "succeeded"
                        else:
                            cmd = [sys.executable, str(SCRIPTS / "writer_practico.py"),
                                   "--id", str(target_id), "--voice", voice, "--provider", provider]
                            p = subprocess.run(cmd, capture_output=True, text=True, encoding="utf-8", timeout=600)
                            log_output = p.stdout + ("\n" + p.stderr if p.stderr else "")
                            status = "succeeded" if p.returncode == 0 else "failed"
                elif agent in ("03-reviewer", "factcheck") and target_id:
                    with get_connection() as conn:
                        row = conn.execute(
                            "SELECT title, content_markdown, is_critical FROM topics WHERE id = ?",
                            (target_id,),
                        ).fetchone()
                    text = (row["content_markdown"] if row else "") or ""
                    if len(text.strip()) < 200:
                        log_output = f"У статьи #{target_id} нет текста для проверки."
                        status = "failed"
                    else:
                        critical = bool(row["is_critical"]) if row else False
                        if agent == "03-reviewer":
                            model_key = "reviewer_critical" if critical else "reviewer_default"
                            system_prompt = REVIEW_SYSTEM_PROMPT
                            status_column, model_column = "review_status", "review_model"
                            label = "рецензия"
                        else:
                            model_key = "factcheck"
                            system_prompt = FACTCHECK_SYSTEM_PROMPT
                            status_column, model_column = "factcheck_status", "factcheck_model"
                            label = "фактчек"
                        answer, used = oxion_chat(
                            OXION_ROLE_MODELS[model_key], system_prompt,
                            f"Статья #{target_id} «{row['title']}»:\n\n{text}",
                            max_tokens=6000,
                        )
                        with get_connection() as conn:
                            conn.execute(
                                f"UPDATE topics SET {status_column} = 'done', {model_column} = ?, "
                                "next_action = ?, updated_at = ? WHERE id = ?",
                                (used, f"{label}: {used}",
                                 datetime.now().isoformat(timespec="seconds"), target_id),
                            )
                            conn.commit()
                        log_output = f"✅ {label.capitalize()} завершён моделью {used}\n\n{answer}"
                        status = "succeeded"
                else:
                    log_output = f"Агент {agent} требует вызова модели Claude/Gemini. Промпт сформирован по стандартам seo/agents/{agent}/AGENT.md."
                    status = "not_implemented"
            except Exception as ex:
                status = "failed"
                log_output = str(ex)

            # Сохраняем запуск в agent_runs
            with get_connection() as conn:
                conn.execute("""
                    INSERT INTO agent_runs (agent_name, action, target_id, status, log, created_at)
                    VALUES (?, ?, ?, ?, ?, ?)
                """, (agent, "run", str(target_id or ""), status, log_output[:2000], datetime.now().isoformat(timespec="seconds")))
                conn.commit()

            self.send_json({"status": status, "agent": agent, "log": log_output})
            return

        # 7.5. Проверка фразы: спрос (Вордстат, бесплатно) и конкуренция (Мутаген, платно)
        if path == "/api/keyword/check":
            phrase = (payload.get("phrase") or "").strip()
            source = (payload.get("source") or "auto").strip().lower()
            if not phrase:
                self.send_json({"status": "error", "message": "Пустая фраза"}, 400)
                return

            result = {"status": "ok", "phrase": phrase, "wordstat": None, "mutagen": None, "cost": 0.0, "notes": []}

            # Вордстат — всегда и бесплатно
            try:
                import wordstat as ws_mod
                cached = None if payload.get("refresh") else ws_mod.get_cached(phrase)
                if cached:
                    result["wordstat"] = {
                        "totalCount": cached["totalCount"],
                        "results": cached["results"][:5],
                        "associations": cached["associations"][:5],
                        "cached": True,
                        "checked_at": cached.get("checked_at"),
                    }
                else:
                    data = ws_mod.top_requests(phrase, num_phrases=15)
                    ws_mod.save_cache(phrase, data)
                    result["wordstat"] = {
                        "totalCount": int(data.get("totalCount") or 0),
                        "results": [{"phrase": r.get("phrase"), "count": int(r.get("count") or 0)}
                                    for r in (data.get("results") or [])[:5]],
                        "associations": [{"phrase": r.get("phrase"), "count": int(r.get("count") or 0)}
                                         for r in (data.get("associations") or [])[:5]],
                        "cached": False,
                    }
            except Exception as ex:  # noqa: BLE001
                result["notes"].append(f"Вордстат недоступен: {type(ex).__name__}: {str(ex)[:120]}")

            # Мутаген — только по явному запросу (стоит денег)
            if source in ("mutagen", "both"):
                try:
                    import mutagen as mg
                    cached_m = None if payload.get("refresh") else mg.cached_metrics(phrase)
                    if cached_m:
                        result["mutagen"] = {
                            "strong": cached_m["strong"],
                            "wordstat": cached_m["wordstat"],
                            "tails": cached_m["tails"],
                            "vital_site": cached_m["vital_site"],
                            "cached": True,
                            "checked_at": cached_m["checked_at"],
                        }
                    else:
                        balance_before = mg.get_balance()
                        res = mg.check_keyword(phrase)
                        balance_after = mg.get_balance()
                        cost = max(0.0, balance_before - balance_after)
                        mg.save_metrics(phrase, res)
                        direct = res.get("direct") or {}
                        result["mutagen"] = {
                            "strong": res.get("strong"),
                            "wordstat": res.get("wordstat"),
                            "tails": res.get("tails"),
                            "vital_site": res.get("vital_site") or "",
                            "bids": {"spec": direct.get("spec"), "first": direct.get("first"), "garant": direct.get("garant")},
                            "cached": False,
                        }
                        result["cost"] = round(cost, 4)
                        result["balance"] = round(balance_after, 2)
                except Exception as ex:  # noqa: BLE001
                    result["notes"].append(f"Мутаген недоступен: {type(ex).__name__}: {str(ex)[:120]}")

            self.send_json(result)
            return

        # 8. Импорт пакета Firecrawl schema 1.0 (JSON или Markdown) с дедупликацией по базе
        if path == "/api/import/firecrawl/v1":
            raw = payload.get("package") if payload.get("package") is not None else payload.get("content", payload)
            pkg = None
            if isinstance(raw, str):
                raw_str = raw.strip()
                if raw_str.startswith("{") or raw_str.startswith("["):
                    try:
                        pkg = json.loads(raw_str)
                    except Exception:
                        pass
                if not isinstance(pkg, dict):
                    pkg = parse_firecrawl_markdown(raw_str)
            elif isinstance(raw, dict):
                inner = raw.get("content") or raw.get("package")
                if isinstance(inner, str) and not ("clusters" in raw or "competitors" in raw):
                    raw_str = inner.strip()
                    if raw_str.startswith("{") or raw_str.startswith("["):
                        try:
                            pkg = json.loads(raw_str)
                        except Exception:
                            pass
                    if not isinstance(pkg, dict):
                        pkg = parse_firecrawl_markdown(raw_str)
                else:
                    pkg = raw
            else:
                pkg = {}

            if not isinstance(pkg, dict) or not any(k in pkg for k in ("competitors", "raw_data", "clusters", "keywords")):
                self.send_json({
                    "status": "error",
                    "message": "Не удалось распознать структуру пакета Firecrawl (ожидался JSON с competitors/raw_data/clusters или Markdown-отчёт с таблицами).",
                }, 400)
                return

            now = datetime.now().isoformat(timespec="seconds")
            added = {"competitors": 0, "raw_articles": 0, "clusters": 0, "keywords": 0}
            skipped = {"competitors": 0, "raw_articles": 0, "clusters": 0, "keywords": 0}
            collisions = {"slugs": [], "titles": []}

            with get_connection() as conn:
                have_domains = {r[0] for r in conn.execute("SELECT domain FROM competitors").fetchall()}
                have_urls = {r[0] for r in conn.execute("SELECT url FROM raw_articles").fetchall()}
                have_clusters = {r[0] for r in conn.execute("SELECT name FROM clusters").fetchall()}
                have_kw = {r[0] for r in conn.execute("SELECT keyword FROM keywords").fetchall()}
                have_slugs = {r[0] for r in conn.execute("SELECT slug FROM topics").fetchall()}
                have_titles = {r[0].strip().lower() for r in conn.execute("SELECT title FROM topics").fetchall()}

                for comp in pkg.get("competitors", []) or []:
                    domain = (comp.get("domain") or "").strip().lower()
                    if not domain:
                        continue
                    if domain in have_domains:
                        skipped["competitors"] += 1
                        continue
                    conn.execute(
                        "INSERT OR IGNORE INTO competitors (domain, type, notes, updated_at) VALUES (?, ?, ?, ?)",
                        (domain, comp.get("type") or "", (comp.get("notes") or "")[:500], now),
                    )
                    have_domains.add(domain)
                    added["competitors"] += 1

                for page in pkg.get("raw_data", []) or []:
                    url = (page.get("url") or "").strip()
                    if not url:
                        continue
                    if url in have_urls:
                        skipped["raw_articles"] += 1
                        continue
                    conn.execute(
                        "INSERT OR IGNORE INTO raw_articles (url, domain, title, h1, content_type, status, crawled_at) "
                        "VALUES (?, ?, ?, ?, ?, 'собрано', ?)",
                        (url, page.get("domain") or "", page.get("title") or "",
                         page.get("h1") or "", page.get("content_type") or "", page.get("collected_at") or now),
                    )
                    have_urls.add(url)
                    added["raw_articles"] += 1

                for cl in pkg.get("clusters", []) or []:
                    name = (cl.get("name") or "").strip()
                    if not name:
                        continue
                    slug = (cl.get("proposed_slug") or "").strip()
                    if slug and slug in have_slugs:
                        collisions["slugs"].append(slug)
                    if name in have_clusters:
                        skipped["clusters"] += 1
                    else:
                        keywords = ", ".join(
                            [cl.get("primary_keyword") or ""] + list(cl.get("secondary_keywords") or [])
                        ).strip(", ")
                        conn.execute(
                            "INSERT OR IGNORE INTO clusters (name, description, keywords, type, priority, status) "
                            "VALUES (?, ?, ?, ?, ?, ?)",
                            (name, cl.get("suggested_differentiation") or "", keywords,
                             cl.get("intent") or "", cl.get("editorial_priority") or "", cl.get("status") or ""),
                        )
                        have_clusters.add(name)
                        added["clusters"] += 1

                for kw in pkg.get("keywords", []) or []:
                    keyword = (kw.get("keyword") or "").strip()
                    if not keyword:
                        continue
                    if keyword in have_kw:
                        skipped["keywords"] += 1
                        continue
                    conn.execute(
                        "INSERT OR IGNORE INTO keywords (keyword, cluster_id, origin, status, source_page_ids, created_at) "
                        "VALUES (?, ?, ?, ?, ?, ?)",
                        (keyword, kw.get("cluster_id") or "", kw.get("origin") or "",
                         kw.get("validation_status") or "not_checked",
                         json.dumps(kw.get("source_page_ids") or [], ensure_ascii=False), now),
                    )
                    have_kw.add(keyword)
                    added["keywords"] += 1

                conn.commit()

            self.send_json({
                "status": "ok",
                "added": added,
                "duplicates": skipped,
                "slug_collisions": collisions["slugs"][:20],
                "message": (
                    f"Добавлено: конкурентов {added['competitors']}, страниц {added['raw_articles']}, "
                    f"кластеров {added['clusters']}, ключей {added['keywords']}. "
                    f"Дублей пропущено: {sum(skipped.values())}."
                ),
            })
            return

        if path == "/api/import/firecrawl":
            raw_text = payload.get("content", "").strip()
            source_domain = payload.get("domain", "").strip().lower()
            default_cluster = payload.get("cluster", "").strip() or "Материалы / Припои"

            if not raw_text:
                self.send_json({"status": "error", "message": "Вставьте текст выгрузки Firecrawl"}, 400)
                return

            articles_to_save = []
            parsed_json = None
            try:
                parsed_json = json.loads(raw_text)
            except Exception:
                pass

            if isinstance(parsed_json, dict):
                data_obj = parsed_json.get("data", parsed_json)
                items = data_obj if isinstance(data_obj, list) else [data_obj]
                for item in items:
                    meta = item.get("metadata", {})
                    url = meta.get("sourceURL") or meta.get("url") or item.get("url") or ""
                    title = meta.get("title") or item.get("title") or ""
                    md = item.get("markdown") or item.get("content") or ""
                    if not title and md:
                        m_h1 = re.search(r"^#\s+(.+)$", md, re.M)
                        title = m_h1.group(1).strip() if m_h1 else "Статья Firecrawl"
                    d = source_domain or (urlparse(url).netloc.replace("www.", "") if url else "firecrawl-import")
                    articles_to_save.append({
                        "url": url or f"https://{d}/{slugify(title)}",
                        "domain": d,
                        "title": title,
                        "content": md,
                    })
            else:
                m_h1 = re.search(r"^#\s+(.+)$", raw_text, re.M)
                title = m_h1.group(1).strip() if m_h1 else "Импортированная статья Firecrawl"
                d = source_domain or "firecrawl-import"
                articles_to_save.append({
                    "url": f"https://{d}/{slugify(title)}",
                    "domain": d,
                    "title": title,
                    "content": raw_text,
                })

            from bot_competitor_analyzer import FEATURE_PATTERNS, SOLDERING_KEYWORDS

            features_found = []
            keywords_found = []
            saved_count = 0

            with get_connection() as conn:
                for art in articles_to_save:
                    text_blob = f"{art['title']} {art['content']}".lower()

                    for kw in SOLDERING_KEYWORDS:
                        if re.search(rf"\b{re.escape(kw)}", text_blob) and kw not in keywords_found:
                            keywords_found.append(kw)

                    for f_name, pattern, desc in FEATURE_PATTERNS:
                        if re.search(pattern, art["content"], flags=re.I) and f_name not in features_found:
                            features_found.append(f_name)
                            conn.execute("""
                                INSERT INTO competitor_features
                                (domain, feature_type, title, description, example_url, seo_value, found_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                            """, (art["domain"], f_name, f_name.capitalize(), desc, art["url"], "Из выгрузки Firecrawl", datetime.now().strftime("%Y-%m-%d %H:%M")))

                    conn.execute("""
                        INSERT OR REPLACE INTO raw_articles
                        (url, domain, title, h1, date, content_type, cluster, status, crawled_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'импортировано из Firecrawl', ?)
                    """, (
                        art["url"], art["domain"], art["title"], art["title"],
                        datetime.now().strftime("%Y-%m-%d"),
                        "импорт / гайд", default_cluster, datetime.now().strftime("%Y-%m-%d %H:%M"),
                    ))

                    if art["domain"] and art["domain"] != "firecrawl-import":
                        conn.execute("""
                            INSERT INTO competitors (domain, niche, type, blog_url, articles_count, features, seo_keywords, score, notes, updated_at)
                            VALUES (?, 'электроника и пайка', 'Конкурент (Firecrawl)', ?, 1, ?, ?, 70, 'Импортировано из выгрузки Firecrawl', ?)
                            ON CONFLICT(domain) DO UPDATE SET
                                articles_count = articles_count + 1,
                                features = CASE WHEN length(features) > 0 THEN features || ', ' || ? ELSE ? END,
                                seo_keywords = CASE WHEN length(seo_keywords) > 0 THEN seo_keywords || ', ' || ? ELSE ? END,
                                updated_at = ?
                        """, (
                            art["domain"], art["url"],
                            ", ".join(features_found), ", ".join(keywords_found[:8]), datetime.now().strftime("%Y-%m-%d %H:%M"),
                            ", ".join(features_found), ", ".join(features_found),
                            ", ".join(keywords_found[:6]), ", ".join(keywords_found[:6]),
                            datetime.now().strftime("%Y-%m-%d %H:%M"),
                        ))
                    saved_count += 1
                conn.commit()

            self.send_json({
                "status": "ok",
                "saved_articles": saved_count,
                "domain": source_domain or "разные",
                "features": features_found,
                "keywords": keywords_found[:12],
            })
            return

        self.send_error(404, "Unknown API route")


def main():
    parser = argparse.ArgumentParser(description="Сервер дашборда Точки Плавления")
    parser.add_argument("--port", type=int, default=8765, help="Порт сервера (по умолчанию 8765)")
    args = parser.parse_args()

    init_db()
    migrate_schema_v3()
    migrate_schema_v4()
    migrate_schema_v5()
    server = ThreadingHTTPServer(("127.0.0.1", args.port), DashboardHandler)
    print("=" * 60)
    print(f"СЕРВЕР SEO-КОНВЕЙЕРА ЗАПУЩЕН:")
    print(f"   URL: http://127.0.0.1:{args.port}")
    print(f"   База данных: {DB_PATH}")
    print(f"   Для остановки нажмите Ctrl+C")
    print("=" * 60)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nСервер остановлен.")


if __name__ == "__main__":
    main()
