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

from db import DB_PATH, backup_database, get_connection, init_db, list_backups


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

        if path == "/api/status":
            self.send_json({"status": "running", "time": datetime.now().isoformat()})
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
        body = self.rfile.read(length).decode("utf-8") if length > 0 else "{}"
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
                conn.execute("UPDATE topics SET status = 'writing', next_action = ? WHERE id = ?", (f"доработка: {reason}", tid))
                conn.commit()
            self.send_json({"status": "ok", "id": tid})
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
                    cmd = [sys.executable, str(SCRIPTS / "writer_practico.py"), "--id", str(target_id)]
                    p = subprocess.run(cmd, capture_output=True, text=True, encoding="utf-8", timeout=180)
                    log_output = p.stdout + ("\n" + p.stderr if p.stderr else "")
                    status = "succeeded" if p.returncode == 0 else "failed"
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

        # 8. Импорт выгрузки Firecrawl (Markdown / JSON)
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
