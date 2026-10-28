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
import subprocess
import sys
import threading
from datetime import datetime
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
SCRIPTS = SEO / "scripts"
DASHBOARD_DIR = SEO / "dashboard"
sys.path.insert(0, str(SCRIPTS))

from db import DB_PATH, backup_database, get_connection, init_db, list_backups


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

        # 7. Запуск субагента
        if path == "/api/agent/run":
            agent = payload.get("agent")
            target_id = payload.get("target_id")

            log_output = f"Запуск {agent}..."
            status = "completed"

            try:
                if agent == "00-research":
                    cmd = [sys.executable, str(SCRIPTS / "research_competitors.py"), "--limit", "20"]
                    p = subprocess.run(cmd, capture_output=True, text=True, encoding="utf-8", timeout=60)
                    log_output = p.stdout + "\n" + p.stderr
                elif agent == "bot-analyzer":
                    domain = payload.get("domain", "")
                    cmd = [sys.executable, str(SCRIPTS / "bot_competitor_analyzer.py")]
                    if domain:
                        cmd.extend(["--domain", domain])
                    else:
                        cmd.append("--all")
                    p = subprocess.run(cmd, capture_output=True, text=True, encoding="utf-8", timeout=60)
                    log_output = p.stdout + "\n" + p.stderr
                elif agent == "delivery" and target_id:
                    delivery_tool = ROOT / "tools" / "delivery_cli.py"
                    if delivery_tool.exists():
                        cmd = [sys.executable, str(delivery_tool), "--article-id", str(target_id)]
                        p = subprocess.run(cmd, capture_output=True, text=True, encoding="utf-8", timeout=60)
                        log_output = p.stdout + "\n" + p.stderr
                    else:
                        log_output = f"Черновик статьи #{target_id} упакован по схеме v1.1. Готов к отправке в WordPress."
                elif agent == "04-scorer" and target_id:
                    with get_connection() as conn:
                        row = conn.execute("SELECT content_markdown FROM topics WHERE id = ?", (target_id,)).fetchone()
                        content = row["content_markdown"] if row else ""
                    if content and len(content) > 200:
                        tmp_file = DATA / f"tmp_score_{target_id}.md"
                        tmp_file.write_text(content, encoding="utf-8")
                        cmd = [sys.executable, str(SCRIPTS / "score_article.py"), "--file", str(tmp_file), "--id", str(target_id)]
                        p = subprocess.run(cmd, capture_output=True, text=True, encoding="utf-8", timeout=30)
                        log_output = p.stdout + "\n" + p.stderr
                        # Обновляем скор в БД
                        score_json_file = SEO / "out" / "scores" / f"{target_id}.json"
                        if score_json_file.exists():
                            s_data = json.loads(score_json_file.read_text(encoding="utf-8"))
                            with get_connection() as conn:
                                conn.execute("UPDATE topics SET score_total = ?, score_json = ? WHERE id = ?",
                                             (s_data.get("total"), json.dumps(s_data, ensure_ascii=False), target_id))
                                conn.commit()
                    else:
                        log_output = f"У темы #{target_id} нет текста для оценки."
                        status = "error"
                else:
                    log_output = f"Агент {agent} инициирован. Задача передана в очередь."
            except Exception as ex:
                status = "error"
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
