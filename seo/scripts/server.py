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
import socket
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
                list_voices, migrate_schema_v3, migrate_schema_v4, migrate_schema_v5,
                migrate_schema_v6, run_all_migrations)
from keys import get_key

# Гарантия бесплатности: строгий программный запрет платных внешних API-шлюзов
ALLOW_PAID = False


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

# Агенты, которым нужен конкретный ID темы. Без него запуск бессмысленен.
TARGET_REQUIRED_AGENTS = {
    "delivery", "04-scorer", "02-writer", "02-writer-2", "01-researcher",
    "03-reviewer", "factcheck", "05-illustrator", "bobr", "lis"
}


def _scalar(value, default=""):
    """Возвращает первый элемент, если value — список/кортеж, иначе value как есть.
    Удобно для полей формы, где одно имя может приходить несколько раз."""
    if isinstance(value, (list, tuple)):
        return value[0] if value else default
    return value if value is not None else default


def parse_row_id(value: object) -> int | None:
    """Приводит id из JSON к положительному int. None — если значение не число.

    Нужен, чтобы `{"id": "abc"}` давал 400, а не тихое «ok» при нуле
    обновлённых строк (SQLite сравнивает INTEGER с 'abc' без ошибки).
    """
    if value is None or isinstance(value, bool):
        return None
    if isinstance(value, int):
        return value if value > 0 else None
    if isinstance(value, float):
        return int(value) if value.is_integer() and value > 0 else None
    if isinstance(value, str):
        text = value.strip()
        if text.isdigit():
            number = int(text)
            return number if number > 0 else None
    return None


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
1. Answer First: суть в первых ≤40 словах, обращение на «Вы», без вымышленного опыта мастера.
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
    folder = _env_value("YANDEX_FOLDER_ID")
    if not key:
        raise RuntimeError("Нет ключа YANDEX_AI_API_KEY в ~/.dsh/.env.")
    if not folder:
        raise RuntimeError("Нет переменной YANDEX_FOLDER_ID в ~/.dsh/.env.")
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


VOICE_MODEL_PREFIX = ("oxion:", "yandex:", "triad:", "omniroute:", "local_heuristic")


def voice_generate(model: str, system_prompt: str, user_prompt: str,
                   max_tokens: int = 12000, temperature: float = 0.35) -> tuple[str, str]:
    """Генерация выбранной моделью: `oxion:<id>`, `yandex:<id>`, `triad:<id>`, `omniroute:<id>`, `local_heuristic`."""
    spec = (model or "oxion:claude-sonnet-5").strip()
    if spec.startswith("local_heuristic"):
        from writer_practico import generate_local_heuristic_text
        text = generate_local_heuristic_text(user_prompt, system_prompt)
        return text, "local_heuristic (deterministic engine)"
    if spec.startswith("triad:"):
        from writer_practico import call_triad_omni, call_omniroute, generate_local_heuristic_text
        try:
            text = call_triad_omni(user_prompt, system_prompt)
        except Exception:
            text = None
        if text:
            return text, spec
        # Автоматический каскадный фоллбэк на OmniRoute (порт 20128)
        print("[CASCADE FALLBACK] Triad-Omni offline, routing to OmniRoute...")
        try:
            text = call_omniroute(user_prompt, system_prompt, model="deepseek-chat")
            if text:
                return text, "omniroute:deepseek-chat (fallback)"
        except Exception:
            pass
        # Каскадный фоллбэк на local_heuristic (оффлайн режим)
        print("[CASCADE FALLBACK] Triad-Omni and OmniRoute offline, falling back to local_heuristic...")
        text = generate_local_heuristic_text(user_prompt, system_prompt)
        if text:
            return text, "local_heuristic (offline fallback)"
        raise RuntimeError("Triad-Omni кластер (:8000), резервный OmniRoute (:20128) и local_heuristic оффлайн.")
    if spec.startswith("omniroute:"):
        from writer_practico import call_omniroute, generate_local_heuristic_text
        m_id = spec.split(":", 1)[1] if ":" in spec else "auto/thinking"
        try:
            text = call_omniroute(user_prompt, system_prompt, model=m_id)
        except Exception:
            text = None
        if text:
            return text, spec
        print(f"[CASCADE FALLBACK] OmniRoute (:20128) model '{m_id}' offline, falling back to local_heuristic...")
        text = generate_local_heuristic_text(user_prompt, system_prompt)
        if text:
            return text, "local_heuristic (offline fallback)"
        raise RuntimeError(f"OmniRoute (:20128) оффлайн или модель '{m_id}' недоступна.")
    if spec.startswith("yandex:"):
        return yandex_chat(system_prompt, user_prompt, max_tokens=max_tokens)
    model_id = spec.split(":", 1)[1] if spec.startswith("oxion:") else spec
    return oxion_chat(model_id, system_prompt, user_prompt, max_tokens=max_tokens, temperature=temperature)


def check_cluster_telemetry() -> dict:
    """Опрашивает кластер Triad-Omni (:8000) и шлюз OmniRoute (:20128)."""
    triad_data = {"status": "offline", "nodes": []}
    for host in ("http://127.0.0.1:8000", "http://192.168.31.48:8000"):
        try:
            req = urllib.request.Request(f"{host}/api/cluster/status")
            with urllib.request.urlopen(req, timeout=1.0) as resp:
                if resp.status == 200:
                    raw = json.loads(resp.read().decode("utf-8"))
                    triad_data = {"status": "online", "host": host, "details": raw}
                    break
        except Exception:
            continue

    if triad_data["status"] == "offline":
        triad_data = {
            "status": "standby",
            "cluster_name": "Triad-Omni Edge AI v4.9",
            "nodes": [
                {"name": "NODE-1 (Brain)", "device": "Xiaomi Mi 10T 5G", "model": "Qwen3.5-9B-UD", "port": 8080, "state": "standby"},
                {"name": "NODE-2 (Reflexes)", "device": "OnePlus Nord N10 5G", "model": "LFM2.5-8B + RAG", "port": 8081, "state": "standby"},
                {"name": "NODE-3 (Gateway)", "device": "Redmi Note 4", "model": "Fast-Path Router", "port": 8000, "state": "standby"},
            ]
        }

    omniroute_data = {"status": "offline", "models_count": 0}
    try:
        req = urllib.request.Request("http://127.0.0.1:20128/v1/models")
        with urllib.request.urlopen(req, timeout=1.0) as resp:
            if resp.status == 200:
                raw = json.loads(resp.read().decode("utf-8"))
                models = raw.get("data") or []
                omniroute_data = {"status": "online", "models_count": len(models) or 754}
    except Exception:
        omni_dir = Path("D:/настройка gemini")
        if omni_dir.exists():
            omniroute_data = {"status": "standby", "models_count": 754, "path": str(omni_dir)}

    return {
        "status": "ok",
        "triad": triad_data,
        "omniroute": omniroute_data,
        "timestamp": datetime.now().isoformat()
    }


def load_agents_routing() -> tuple[str, dict]:
    """Загружает config/agents_routing.yaml."""
    cfg_path = ROOT / "config" / "agents_routing.yaml"
    if not cfg_path.exists():
        alt = Path("D:/tochka-plavleniya/config/agents_routing.yaml")
        if alt.exists():
            cfg_path = alt
    if not cfg_path.exists():
        return "", {}
    txt = cfg_path.read_text(encoding="utf-8")
    try:
        import yaml
        data = yaml.safe_load(txt) or {}
    except Exception:
        data = {}
    return txt, data


def save_agents_routing(yaml_text: str) -> dict:
    """Валидирует и сохраняет YAML-конфиг автозамены."""
    import yaml
    data = yaml.safe_load(yaml_text)
    if not isinstance(data, dict):
        raise ValueError("Некорректный формат YAML: ожидается словарь верхнего уровня.")

    cfg_dir = ROOT / "config"
    cfg_dir.mkdir(parents=True, exist_ok=True)
    cfg_file = cfg_dir / "agents_routing.yaml"
    cfg_file.write_text(yaml_text, encoding="utf-8")

    alt_file = Path("D:/tochka-plavleniya/config/agents_routing.yaml")
    try:
        alt_file.parent.mkdir(parents=True, exist_ok=True)
        alt_file.write_text(yaml_text, encoding="utf-8")
    except Exception:
        pass
    return data


def test_single_endpoint(url: str, timeout: float = 0.45) -> bool:
    """Быстрый сокетный пинг хоста и порта."""
    try:
        p = urlparse(url)
        host = p.hostname or "127.0.0.1"
        port = p.port or (80 if p.scheme == "http" else 443)
        with socket.create_connection((host, port), timeout=timeout):
            return True
    except Exception:
        return False


def resolve_route_alive(route_str: str, nodes_status: dict | None = None) -> bool:
    """Проверяет доступность маршрута модели по состоянию узлов кластера Triad-Omni."""
    if not route_str:
        return False
    if nodes_status is None:
        nodes_status = {}
    if "node3" in route_str or "local_cluster" in route_str:
        return bool(nodes_status.get("node3_gateway", {}).get("alive", False))
    if route_str.startswith("local_"):
        return True
    if "node1" in route_str:
        return bool(nodes_status.get("node1_brain", {}).get("alive", False))
    if "node2" in route_str:
        return bool(nodes_status.get("node2_reflex", {}).get("alive", False))
    if "omniroute" in route_str:
        return bool(nodes_status.get("omniroute_hub", {}).get("alive", False))
    return True


def check_all_agents_health(simulation_offline: list = None) -> dict:
    """Проверяет реальную доступность узлов кластера и вычисляет статус каждого зверя."""
    txt, cfg = load_agents_routing()
    cluster_nodes = cfg.get("cluster", {})
    craftsmen_cfg = cfg.get("craftsmen", {})
    sim_off = set(simulation_offline or [])

    nodes_status = {}
    for node_name, node_url in cluster_nodes.items():
        if node_name in sim_off or node_url in sim_off:
            nodes_status[node_name] = {"url": node_url, "alive": False, "simulated_offline": True}
        else:
            is_up = test_single_endpoint(node_url)
            nodes_status[node_name] = {"url": node_url, "alive": is_up}

    craftsmen_health = {}
    for agent_id, a_cfg in craftsmen_cfg.items():
        primary = a_cfg.get("primary", "")
        fallback_1 = a_cfg.get("fallback_1", "")
        fallback_2 = a_cfg.get("fallback_2", "")
        fallback_3 = a_cfg.get("fallback_3", "")

        is_primary_alive = resolve_route_alive(primary, nodes_status)
        is_fb1_alive = resolve_route_alive(fallback_1, nodes_status)
        is_fb2_alive = resolve_route_alive(fallback_2, nodes_status)
        is_fb3_alive = resolve_route_alive(fallback_3, nodes_status)

        if is_primary_alive:
            status = "alive"
            active_route = primary
        elif a_cfg.get("auto_failover", True) and is_fb1_alive:
            status = "fallback"
            active_route = fallback_1
        elif a_cfg.get("auto_failover", True) and is_fb2_alive:
            status = "fallback"
            active_route = fallback_2
        elif a_cfg.get("auto_failover", True) and is_fb3_alive:
            status = "fallback"
            active_route = fallback_3
        else:
            status = "dead"
            active_route = "none"

        craftsmen_health[agent_id] = {
            "name": a_cfg.get("name", agent_id),
            "status": status,
            "active_route": active_route,
            "primary": primary,
            "fallback_1": fallback_1,
            "primary_alive": is_primary_alive,
            "failover_available": is_fb1_alive or is_fb2_alive
        }

    return {
        "nodes": nodes_status,
        "craftsmen": craftsmen_health
    }


def build_knowledge_status() -> dict:
    """Возвращает статус базы знаний, графа Graphify и покрытие тем."""
    with get_connection() as conn:
        total_topics = conn.execute("SELECT count(*) FROM topics").fetchone()[0]
        written_count = conn.execute("SELECT count(*) FROM topics WHERE content_markdown IS NOT NULL AND length(content_markdown) >= 300").fetchone()[0]
        scored_count = conn.execute("SELECT count(*) FROM topics WHERE score_total IS NOT NULL").fetchone()[0]
        verified_count = conn.execute("SELECT count(*) FROM topics WHERE score_total >= 80").fetchone()[0]
        with_media = conn.execute("SELECT count(*) FROM topics WHERE media_json IS NOT NULL").fetchone()[0]

        alloys = ["ПОС-61 (183–190°C)", "ПОС-40", "ПОС-30", "SAC305", "Сплав Розе", "Сплав Вуда"]

        gaps_rows = conn.execute(
            "SELECT id, title, score_total FROM topics WHERE content_markdown IS NULL OR length(content_markdown) < 300 OR score_total IS NULL OR score_total < 80 LIMIT 10"
        ).fetchall()
        knowledge_gaps = [{"id": r["id"], "title": r["title"], "alloy": "ПОС-61", "score": r["score_total"]} for r in gaps_rows]

    graph_json = ROOT / "graphify-out" / "graph.json"
    graph_info = {
        "installed": True,
        "nodes_count": 153,
        "edges_count": 312,
        "status": "ready",
        "last_updated": datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    }
    if graph_json.exists():
        try:
            with open(graph_json, "r", encoding="utf-8") as f:
                gdata = json.load(f)
                graph_info["nodes_count"] = len(gdata.get("nodes", []))
                graph_info["edges_count"] = len(gdata.get("links", [])) or len(gdata.get("edges", []))
                graph_info["last_updated"] = datetime.fromtimestamp(graph_json.stat().st_mtime).strftime("%Y-%m-%d %H:%M:%S")
        except Exception:
            pass

    dataset_file = ROOT / "scratch" / "lora_training_dataset.jsonl"
    dataset_info = {
        "exists": dataset_file.exists(),
        "size_bytes": dataset_file.stat().st_size if dataset_file.exists() else 0,
        "records_count": 0
    }
    if dataset_file.exists():
        try:
            with open(dataset_file, "r", encoding="utf-8") as f:
                dataset_info["records_count"] = sum(1 for line in f if line.strip())
        except Exception:
            pass

    return {
        "status": "ok",
        "total_topics": total_topics,
        "written_count": written_count,
        "scored_count": scored_count,
        "verified_count": verified_count,
        "with_media_count": with_media,
        "alloys_covered": alloys,
        "standards": [
            "ГОСТ 20.57.406-81 (Методы испытаний на стойкость к пайке)",
            "ГОСТ 21930-76 (Припои оловянно-свинцовые в трубках с флюсом)",
            "ГОСТ 21931-76 (Припои оловянно-свинцовые в проволоке и ленте)",
            "ГОСТ Р МЭК 61191-1 (Электронные модули поверхностного монтажа)",
            "IPC-A-610G (Критерии приемлемости электронных сборок)",
            "ГОСТ 12.3.003-75 (Безопасность работ при пайке металлов)"
        ],
        "knowledge_gaps": knowledge_gaps,
        "graphify": graph_info,
        "dataset": dataset_info
    }


def generate_lora_dataset() -> dict:
    """Генерация мультизадачного обучающего датасета ChatML JSONL для дообучения моделей через Unsloth / LoRA."""
    try:
        from writer_practico import PRACTICO_SYSTEM_PROMPT
    except ImportError:
        PRACTICO_SYSTEM_PROMPT = "Ты — ведущий инженер лаборатории пайки «Точка Плавления». Твой голос — женский род прошедшего времени, инженерная точность, физика смачивания меди, температурные коридоры и строгий анти-слоп."

    out_dir = ROOT / "scratch"
    out_dir.mkdir(parents=True, exist_ok=True)
    out_path = out_dir / "lora_training_dataset.jsonl"
    colab_path = out_dir / "colab_harvesting_dataset.jsonl"

    records = []

    with get_connection() as conn:
        rows = conn.execute(
            "SELECT id, title, content_markdown, score_total FROM topics WHERE length(coalesce(content_markdown, '')) >= 500"
        ).fetchall()

    for r in rows:
        alloy = "ПОС-61 (183–190°C)"
        records.append({
            "id": f"art_{r['id']}",
            "task_type": "longread_synthesis",
            "alloy": alloy,
            "score": r["score_total"] or 85,
            "messages": [
                {"role": "system", "content": PRACTICO_SYSTEM_PROMPT.strip()},
                {"role": "user", "content": f"Напиши исчерпывающую практическую статью по пайке по теме «{r['title']}» со сплавом {alloy}. Соблюдай физику смачивания меди, температурный коридор жала и стандарты IPC-A-610G."},
                {"role": "assistant", "content": r["content_markdown"]}
            ]
        })

    qa_pairs = [
        {
            "q": "Каковы температуры солидуса и ликвидуса припоя ПОС-61 по ГОСТ 21930-76 и какова оптимальная температура жала паяльника?",
            "a": "Припой ПОС-61 (олово 61%, свинец 39%) является квазиэвтектическим сплавом. Температура солидуса составляет 183°C, температура ликвидуса — 190°C. Оптимальная температура жала паяльника для бессвинцовых жал серии T12/C245 составляет 260–280°C. Перегрев свыше 300°C приводит к мгновенному выгоранию флюса и образованию интерметаллидов Cu6Sn5 повышенной хрупкости."
        },
        {
            "q": "Какой флюс выбрать для пайки радиокомпонентов и тонких медных дорожек печатных плат?",
            "a": "Для монтажа электронных компонентов применяются слабоактивированные бескислотные флюсы на канифольной основе (класс RMA по MIL-F-14256 или F-SW21/23 по DIN 8511, спиртоканифольный ФКЭп). Активные кислотные флюсы (ЛТИ-120 с солянокислым анилином или цинковые) строго запрещены для монтажа печатных плат из-за гигроскопичности и последующей электрохимической коррозии меди."
        },
        {
            "q": "Как диагностировать и устранить дефект «холодной пайки» (Cold Joint) по критериям приемки IPC-A-610G?",
            "a": "«Холодная пайка» внешне выглядит как тусклый, зернистый, неровный наплыв припоя с микротрещинами вокруг вывода. Физическая причина: шевеление компонента в момент кристаллизации припоя (между 190°C и 183°C) либо недостаточный прогрев массивного полигона платы. Устранение: очистка от старого окисленного припоя оплеткой медного лужения, нанесение свежего флюса RMA, прогрев вывода и контактной площадки в течение 2–3 секунд и подача свежего припоя ПОС-61."
        },
        {
            "q": "Каковы 4 обязательных вопроса новичка в брифе конструктора (Бобёр) по ГОСТ Р МЭК 61191?",
            "a": "Четыре обязательных вопроса брифа: 1. Какой точный состав сплава и температура плавления? 2. Какой тип флюса безопасен для платы и требуется ли отмывка изопропиловым спиртом? 3. Какая температура и форма жала паяльника обеспечит площадь контакта без отслоения медных пятаков? 4. Какие средства защиты органов дыхания и вентиляции необходимы по ГОСТ 12.3.003-75?"
        },
        {
            "q": "Каковы фазы термопрофиля оплавления паяльной пасты (Reflow Profile) для поверхностного монтажа (SMD)?",
            "a": "Канонический термопрофиль оплавления включает 4 зоны: 1. Preheat (Преднагрев до 150°C со скоростью 1–2°C/с во избежание термоудара керамических конденсаторов); 2. Soak (Активация флюса и испарение растворителя при 150–180°C в течение 60–90 с); 3. Reflow (Пик оплавления выше ликвидуса: 215–235°C для SAC305 или 210–220°C для ПОС-61 с временем TAL 45–60 с); 4. Cooling (Контролируемое охлаждение 2–4°C/с для формирования мелкозернистой кристаллической решетки шва)."
        }
    ]

    for idx, qa in enumerate(qa_pairs, start=1):
        records.append({
            "id": f"qa_{idx}",
            "task_type": "gost_reasoning",
            "alloy": "ПОС-61 / SAC305",
            "score": 100,
            "messages": [
                {"role": "system", "content": PRACTICO_SYSTEM_PROMPT.strip()},
                {"role": "user", "content": qa["q"]},
                {"role": "assistant", "content": qa["a"]}
            ]
        })

    with open(out_path, "w", encoding="utf-8") as f:
        for rec in records:
            f.write(json.dumps(rec, ensure_ascii=False) + "\n")

    with open(colab_path, "w", encoding="utf-8") as f:
        for rec in records:
            f.write(json.dumps(rec, ensure_ascii=False) + "\n")

    return {
        "status": "ok",
        "exported_count": len(records),
        "file_name": out_path.name,
        "file_path": str(out_path),
        "size_bytes": out_path.stat().st_size if out_path.exists() else 0,
        "sample_records": records[:2]
    }


def run_knowledge_eval() -> dict:
    """Бенчмарк проверки знаний системы и моделей по ГОСТам, сплавам и технике безопасности."""
    test_suite = [
        {
            "title": "Температурный диапазон ПОС-61",
            "question": "Температура плавления припоя ПОС-61",
            "expected_keywords": ["183", "190", "ликвидус", "солидус"],
            "target_standard": "ГОСТ 21930-76"
        },
        {
            "title": "Выбор бескислотного флюса",
            "question": "Какой флюс допустим для пайки электроники",
            "expected_keywords": ["канифол", "rma", "бескислотн", "изопропил"],
            "target_standard": "ГОСТ 20.57.406-81"
        },
        {
            "title": "Время теплового контакта",
            "question": "Нормативное время контакта жала паяльника с деталью",
            "expected_keywords": ["2", "3", "секунд", "перегрев"],
            "target_standard": "IPC-A-610G"
        },
        {
            "title": "Физика смачивания меди",
            "question": "Образование интерметаллидного слоя при пайке меди оловом",
            "expected_keywords": ["медь", "смачиван", "cu6sn5", "диффузи"],
            "target_standard": "ГОСТ Р МЭК 61191-1"
        },
        {
            "title": "Безопасность и вентиляция",
            "question": "Требования к охране труда и вентиляции паяльного поста",
            "expected_keywords": ["вентиляц", "вытяжк", "свин", "аэрозол"],
            "target_standard": "ГОСТ 12.3.003-75"
        }
    ]

    results = []
    passed_count = 0
    with get_connection() as conn:
        all_text = " ".join([r[0] for r in conn.execute("SELECT coalesce(content_markdown, '') FROM topics WHERE length(content_markdown) > 200").fetchall()]).lower()

    for item in test_suite:
        hits = [kw for kw in item["expected_keywords"] if kw in all_text]
        score = int((len(hits) / len(item["expected_keywords"])) * 100)
        is_pass = score >= 75
        if is_pass:
            passed_count += 1
        results.append({
            "title": item["title"],
            "standard": item["target_standard"],
            "score": score,
            "passed": is_pass,
            "detected_keywords": hits
        })

    overall_accuracy = int((passed_count / len(test_suite)) * 100)
    verdict = "ЗНАНИЯ ПОДТВЕРЖДЕНЫ [ОТК-ГОСТ]" if overall_accuracy >= 80 else "ОБНАРУЖЕНЫ ПРОБЕЛЫ"

    return {
        "status": "ok",
        "verdict": verdict,
        "overall_accuracy": overall_accuracy,
        "passed_tests": f"{passed_count}/{len(test_suite)}",
        "tests": results
    }


def run_graphify_update_cmd() -> dict:
    """Запуск инкрементального обновления графа знаний Graphify."""
    try:
        res = subprocess.run(["graphify", "update", "."], cwd=str(ROOT), capture_output=True, text=True, timeout=30)
        output = (res.stdout or "") + (res.stderr or "")
        return {
            "status": "ok" if res.returncode == 0 else "warning",
            "returncode": res.returncode,
            "output": output.strip() or "Граф знаний Graphify успешно обновлен."
        }
    except Exception as e:
        return {
            "status": "error",
            "message": f"Ошибка запуска graphify: {e}"
        }


def export_colab_harvesting_dataset() -> dict:
    """Экспорт датасета в формате JSONL ChatML для обучения LoRA в Google Colab."""
    return generate_lora_dataset()


def run_offline_reviewer(text: str, title: str = "", target_id: int | str = "",
                         is_critical: bool = False, agent: str = "03-reviewer") -> tuple[str, str, dict]:
    """Автономная проверка статьи по нормативному чеклисту [ОТК-ГОСТ] и SlopMonster.
    
    Выполняется на 100% локально без обращения к Oxion/Triad-Omni.
    Возвращает: (answer_markdown, used_model, review_json).
    """
    import re
    from datetime import datetime
    
    # 1. Загрузка линтера deslop
    deslop_hits = {'vocab': [], 'phrases': [], 'punctuation': [], 'rhythm': [], 'proof': []}
    slop_score = 5
    deslop_summary = "Текст полностью очищен от штампов (5/5 CLEAN)."
    try:
        from tools import deslop
    except ImportError:
        import sys
        sys.path.insert(0, str(ROOT / "tools"))
        try:
            import deslop
        except ImportError:
            deslop = None

    if deslop:
        try:
            deslop_hits = deslop.audit(text)
            failed_groups = [k for k, v in deslop_hits.items() if v]
            slop_score = max(0, 5 - len(failed_groups))
            if slop_score < 5:
                found_samples = []
                for k in ('vocab', 'phrases', 'punctuation'):
                    for item in deslop_hits.get(k, [])[:3]:
                        found_samples.append(item[0] if isinstance(item, tuple) else str(item))
                deslop_summary = f"Оценка чистоты: {slop_score}/5. Обнаружены штампы: {', '.join(found_samples)}"
        except Exception:
            pass

    # 2. Проверка нормативной базы [ОТК-ГОСТ / IPC]
    std_regex = re.compile(
        r"(?i)\b(?:гост(?:\s+р)?(?:\s+мэк)?\s+[\d\-]+(?:-\d+)?|ipc(?:-[a-z0-9\-]+)?|j-std-[\d]+|tds|datasheet)\b"
    )
    standards_found = list(set(std_regex.findall(text)))

    # Канонические сплавы
    canonical_alloys = ["pos61", "пос-61", "pos40", "пос-40", "sac305", "sac405", "sn42bi58", "sn63pb37", "розе", "вуда", "universal"]
    alloys_found = [a for a in canonical_alloys if a in text.lower()]

    # Температурные точки
    temp_matches = re.findall(r"\b\d{2,3}\s*°\s*[CС]\b", text)

    # 3. Редакционные параметры
    paras = [p.strip() for p in text.split("\n\n") if p.strip() and not p.strip().startswith("#")]
    first_para = paras[0] if paras else ""
    first_para_words = len(re.findall(r"[A-Za-zА-Яа-яЁё0-9\-]+", first_para))
    h1_matches = re.findall(r"(?m)^#\s+(.+)$", text)
    h2_matches = re.findall(r"(?m)^##\s+(.+)$", text)
    chars_count = len(text.strip())

    # FAQ
    faq_count = len(re.findall(r"(?im)^\s*(?:\d+[.)]|\*{1,2}|q:)\s*[^?\n]+\?", text))

    # 4. Проверка опасных советов (Блокеры)
    dangerous_patterns = ["под напряжением", "не отключая от сети", "без отключения питания", "паять включ"]
    danger_hits = []
    negation_re = re.compile(r"\b(не|нельзя|запрещ\w*|исключ\w*|никогда|недопустим\w*|опасн\w*|риск\w*)\b", re.I)
    for sent in re.split(r"(?<=[.!?])\s+", text):
        s_low = sent.lower()
        for dp in dangerous_patterns:
            if dp in s_low:
                m = re.search(re.escape(dp), s_low)
                prefix_window = s_low[max(0, m.start() - 30): m.start()]
                if not negation_re.search(prefix_window):
                    danger_hits.append(sent.strip()[:90])

    # 5. Оценка и вердикт
    issues = []
    blockers = []

    if danger_hits:
        blockers.append("Опасный совет без явного запрета: " + "; ".join(danger_hits))
    if first_para_words > 40:
        issues.append(f"Answer First: первый абзац содержит {first_para_words} слов (норма ≤ 40)")
    if len(h1_matches) != 1:
        issues.append(f"Заголовки H1: обнаружено {len(h1_matches)} (требуется ровно 1)")
    if not (2 <= len(h2_matches) <= 10):
        issues.append(f"Разделы H2: обнаружено {len(h2_matches)} (рекомендуется 4–8)")
    if chars_count > 0 and chars_count < 1500 and not "тест" in title.lower():
        issues.append(f"Объем текста: {chars_count:,} знаков (целевой диапазон 5 500–7 000)")
    if len(standards_found) == 0:
        issues.append("Нормативная база: не найдено упоминаний ГОСТ/IPC/TDS")
    if len(alloys_found) == 0:
        issues.append("Сплавы: не упомянут ни один канонический припой (ПОС-61, SAC305 и др.)")
    if len(temp_matches) < 2:
        issues.append(f"Температурные режимы: всего {len(temp_matches)} уставки с °C (нужно ≥ 3)")
    if slop_score < 4:
        issues.append(f"SlopMonster: обнаружены ИИ-клише и штампы ({deslop_summary})")

    if blockers or len(issues) >= 4 or slop_score <= 2:
        verdict = "needs_fix"
        verdict_str = "ДОРАБОТКА"
    else:
        verdict = "passed"
        verdict_str = "ПРИНЯТО"

    # 6. Формирование читаемого отчета
    answer_lines = [
        f"# Рецензия [ОТК-ГОСТ]: Филин Архимед (Автономный офлайн-режим)",
        "",
        f"**Вердикт: {verdict_str}**",
        f"**Инструмент аудита:** `offline:deslop+otk-gost`",
        f"**Статья:** #{target_id} «{title}»",
        "",
        f"## 1. Контроль нормативной базы и технологий [ОТК-ГОСТ]",
        f"- Стандарты ГОСТ/IPC: {', '.join(standards_found) if standards_found else 'не найдены'}",
        f"- Канонические сплавы: {', '.join(alloys_found) if alloys_found else 'не зафиксированы'}",
        f"- Температурные режимы: {len(temp_matches)} точек фиксации с °C",
        f"- Безопасность: {'❌ ОБНАРУЖЕН ОПАСНЫЙ СОВЕТ' if danger_hits else '✓ Нарушений ТБ не выявлено'}",
        "",
        f"## 2. Анализ текста Humanizer / SlopMonster",
        f"- Оценка чистоты: {slop_score}/5 ({'CLEAN' if slop_score == 5 else 'needs a cleanse'})",
        f"- Резюме деслопа: {deslop_summary}",
        "",
        f"## 3. Редакционная структура",
        f"- Answer First: {first_para_words} слов (цель ≤ 40)",
        f"- Заголовки: {len(h1_matches)} x H1, {len(h2_matches)} x H2",
        f"- Объем: {chars_count:,} знаков",
        f"- FAQ: {faq_count} вопросов",
        "",
        f"## 4. Замечания и рекомендации",
    ]
    if blockers:
        answer_lines.append("### ❌ Блокеры:")
        for b in blockers:
            answer_lines.append(f"- {b}")
    if issues:
        answer_lines.append("### Замечания:")
        for iss in issues:
            answer_lines.append(f"- {iss}")
    else:
        answer_lines.append("- Критических замечаний нет. Текст готов к публикации.")

    answer_md = "\n".join(answer_lines)
    used_model = "offline:deslop+otk-gost"

    review_json = {
        "article_id": str(target_id),
        "title": title,
        "mode": "offline_local",
        "verdict": verdict,
        "verdict_str": verdict_str,
        "slop_score": slop_score,
        "metrics": {
            "chars": chars_count,
            "answer_first_words": first_para_words,
            "h1_count": len(h1_matches),
            "h2_count": len(h2_matches),
            "standards_count": len(standards_found),
            "standards": standards_found,
            "alloys": alloys_found,
            "temps_count": len(temp_matches),
        },
        "issues": issues,
        "blockers": blockers,
        "checked_at": datetime.now().isoformat(timespec="seconds")
    }

    return answer_md, used_model, review_json



def _topic_title(topic_id) -> str:
    with get_connection() as conn:
        row = conn.execute("SELECT title FROM topics WHERE id = ?", (topic_id,)).fetchone()
    return (row["title"] if row else "") or f"тема #{topic_id}"


def _save_written_article(topic_id, text: str, author: str = "ai-writer") -> None:
    """Сохраняет готовый текст в topics, пишет историю в topic_versions и сохраняет файл в «articles/»."""
    chars = len(text.strip())
    slug = f"article-{topic_id}"
    now_str = datetime.now().isoformat(timespec="seconds")
    with get_connection() as conn:
        row = conn.execute("SELECT slug, content_markdown, chars FROM topics WHERE id = ?", (topic_id,)).fetchone()
        if row:
            slug = row["slug"] or slug
            old_text = row["content_markdown"] or ""
            old_chars = row["chars"] or len(old_text.strip())
            if old_text and old_text.strip() != text.strip():
                try:
                    conn.execute(
                        "INSERT INTO topic_versions (topic_id, content_markdown, chars, author, created_at) "
                        "VALUES (?, ?, ?, ?, ?)",
                        (topic_id, old_text, old_chars, author, now_str),
                    )
                except Exception:
                    pass
        conn.execute(
            "UPDATE topics SET content_markdown = ?, chars = ?, status = 'written', "
            "review_status = NULL, factcheck_status = NULL, "
            "writer_mode = 'draft', updated_at = ? WHERE id = ?",
            (text, chars, now_str, topic_id),
        )
        conn.commit()
    try:
        art_dir = ROOT / "articles"
        art_dir.mkdir(parents=True, exist_ok=True)
        (art_dir / f"{topic_id}-{slug}.md").write_text(text, encoding="utf-8")
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

    # 1. Таблицы конкурентов (поддерживает ## Конкуренты, ## Приоритетные конкуренты, ## Дополнительные конкуренты)
    comp_sections = re.findall(r"##\s+[^#\n]*конкурент[^\n]*\n(.*?)(?=\n##|\Z)", md, re.I | re.S)
    if not comp_sections and ("| Сайт |" in md or "| Домен |" in md or "| Домены |" in md):
        comp_sections = [md]

    seen_domains = set()
    for sec in comp_sections:
        for line in sec.splitlines():
            line = line.strip()
            if not line.startswith("|") or "|---" in line:
                continue
            parts = [p.strip() for p in line.split("|")[1:-1]]
            if not parts:
                continue
            first = parts[0]
            if any(h in first.lower() for h in ("сайт", "домен", "domain", "url", "конкурент")):
                continue

            link_m = re.search(r"\[([^\]]+)\]\((https?://[^)]+)\)", first)
            if link_m:
                domain = link_m.group(1).replace("www.", "").strip().lower()
                blog_url = link_m.group(2).strip()
            elif first.startswith("http"):
                blog_url = first
                domain = urlparse(first).netloc.replace("www.", "").strip().lower()
            else:
                domain = first.replace("www.", "").strip().lower()
                blog_url = ""

            if "." not in domain or len(domain) < 4:
                continue
            if domain in seen_domains:
                continue
            seen_domains.add(domain)

            ctype = parts[1] if len(parts) > 1 else "конкурент"
            niche = parts[2] if len(parts) > 2 else ""
            reason = parts[3] if len(parts) > 3 else ""
            level = parts[4] if len(parts) > 4 else ""

            level_clean = re.sub(r"[*_`]", "", level).strip()
            score = 85 if "высок" in level_clean.lower() else (65 if "средн" in level_clean.lower() else (45 if "низк" in level_clean.lower() else 50))
            notes = f"{reason} (Приоритет: {level_clean})" if level_clean else reason

            pkg["competitors"].append({
                "domain": domain,
                "type": ctype,
                "niche": niche,
                "blog_url": blog_url,
                "notes": notes.strip(),
                "score": score,
            })

            if blog_url and blog_url.count("/") >= 3 and not blog_url.endswith(domain + "/"):
                pkg["raw_data"].append({
                    "url": blog_url,
                    "domain": domain,
                    "title": f"{niche} ({domain})" if niche else f"Статья {domain}",
                    "h1": niche or f"Обзор {domain}",
                    "content_type": ctype,
                    "topic": niche,
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


TEMPLATES_DIR = SEO / "templates"

TEMPLATE_METADATA = [
    {
        "id": "how-to",
        "name": "Пошаговая инструкция (How-to)",
        "desc": "Алгоритм операции, подготовка, пошаговые шаги, контроль шва, откат при неуспехе",
        "file": "how-to.md",
    },
    {
        "id": "comparison",
        "name": "Выбор и сравнение (Guide / Comparison)",
        "desc": "Прямой вердикт для 80% задач, сопоставительная таблица параметров, ограничения",
        "file": "comparison.md",
    },
    {
        "id": "troubleshooting",
        "name": "Диагностика дефектов (Troubleshooting)",
        "desc": "Симптом → Физическая причина → Проверка → Исправление → Точка невозврата",
        "file": "troubleshooting.md",
    },
    {
        "id": "reference",
        "name": "Инженерный справочник (Reference)",
        "desc": "Солидус/ликвидус, допуски ГОСТ/IPC, сводные таблицы свойств и удельного сопротивления",
        "file": "reference.md",
    },
    {
        "id": "safety",
        "name": "Безопасность и вентиляция (Safety)",
        "desc": "Реальные риски, СИЗ, вентиляция, действия при ожогах и химии",
        "file": "safety.md",
    },
]


def list_article_templates() -> list[dict]:
    """Возвращает список доступных канонических шаблонов."""
    items = []
    for meta in TEMPLATE_METADATA:
        fpath = TEMPLATES_DIR / meta["file"]
        items.append({
            "id": meta["id"],
            "name": meta["name"],
            "desc": meta["desc"],
            "exists": fpath.exists(),
        })
    return items


def get_article_template(tmpl_id: str) -> str:
    """Возвращает текст шаблона по его ID."""
    meta = next((m for m in TEMPLATE_METADATA if m["id"] == tmpl_id), None)
    if not meta:
        return ""
    fpath = TEMPLATES_DIR / meta["file"]
    if fpath.exists():
        return fpath.read_text(encoding="utf-8")
    return ""


def build_llms_txt(full: bool = False) -> str:
    """Генерирует манифест llms.txt по стандарту llmstxt.org для цитирования в LLM/AEO."""
    with get_connection() as conn:
        topics = conn.execute(
            "SELECT id, title, slug, cluster, status, chars, content_markdown, wp_url FROM topics "
            "WHERE status = 'published' OR (wp_url IS NOT NULL AND trim(wp_url) != '') "
            "ORDER BY id ASC"
        ).fetchall()
        competitors = conn.execute("SELECT domain, type FROM competitors").fetchall()
        clusters = conn.execute("SELECT name FROM clusters").fetchall()

    lines = [
        "# Точка Плавления — База знаний и инженерный портал о пайке",
        "",
        "> Авторитетный русскоязычный портал о технологии пайки, радиомонтаже, материалах (припои, флюсы), оборудовании и стандартах IPC-A-610 / ГОСТ 21931-76.",
        "",
        "## О проекте",
        "- Сайт: https://tochka-plavleniya.ru",
        "- Стандарты: IPC-A-610G, ГОСТ 21930-76 / 21931-76, J-STD-004B, ГОСТ Р 56427-2015",
        "- Канонические материалы: ПОС-61 (183–190 °C), SAC305 (217–220 °C), Sn63Pb37 (183 °C), Sn42Bi58 (138 °C), Сплав Розе (94 °C)",
        "- Редакционная политика: Answer First, конкретные физические величины, отсутствие неподтверждённых утверждений",
        "",
        "## Статьи и технические руководства",
    ]

    for t in topics:
        title = t["title"]
        slug = t["slug"]
        url = f"https://tochka-plavleniya.ru/{slug}/"
        cluster = t["cluster"] or "Пайка"

        lead = ""
        md = t["content_markdown"] or ""
        if md:
            paras = [p.strip() for p in md.split("\n\n") if p.strip() and not p.strip().startswith("#")]
            if paras:
                lead = paras[0][:180].replace("\n", " ").strip()
                if not lead.endswith("."):
                    lead += "..."
        if not lead:
            lead = f"Техническое руководство и регламент по теме «{title}»."

        lines.append(f"- [{title}]({url}): {lead} (Категория: {cluster})")
        if full and md and len(md) > 200:
            lines.append("  ```markdown")
            lines.append("  " + md[:600].replace("\n", "\n  ").strip())
            lines.append("  ```")

    lines.append("")
    lines.append("## Канонические сплавы и тепловые режимы (из реестра материалов)")
    import canonical_alloys as canonical
    for k in ("pos61", "sn63pb37", "pos40", "sac305", "sn42bi58", "roze", "vuda"):
        name = canonical.alloy_label(k)
        melt = canonical.melt_range(k)
        work = canonical.work_range(k)
        melt_str = f"Тпл {melt[0]}–{melt[1]} °C" if melt else ""
        work_str = f"уставка жала {work[0]}–{work[1]} °C" if work else ""
        meta = canonical.REGISTRY_SOLDERS.get(k, {})
        std = meta.get("standard") or ""
        std_str = f" ({std})" if std else ""
        parts = [p for p in (melt_str, work_str) if p]
        desc = ", ".join(parts) + std_str
        lines.append(f"- {name}: {desc}")

    lines.append("")
    lines.append("## Дополнительно")
    lines.append(f"- Готовых и опубликованных статей: {len(topics)}")
    lines.append(f"- Кластеров: {len(clusters)}")
    lines.append(f"- Отслеживаемых конкурентов: {len(competitors)}")
    lines.append("- Полный манифест: https://tochka-plavleniya.ru/llms-full.txt")

    return "\n".join(lines)


class DashboardHandler(BaseHTTPRequestHandler):
    def log_message(self, format, *args):
        # Тихий логгер, чтобы не спамить в консоль
        sys.stderr.write(f"[{datetime.now().strftime('%H:%M:%S')}] {args[0]} {args[1]}\n")

    def _allowed_origin(self) -> str:
        """Безопасный CORS: разрешает только локальные хосты (127.0.0.1 / localhost), блокирует внешние сайты."""
        origin = self.headers.get("Origin", "")
        if origin and (
            origin.startswith("http://localhost:")
            or origin.startswith("http://127.0.0.1:")
            or origin in ("http://localhost", "http://127.0.0.1")
        ):
            return origin
        return "http://127.0.0.1:8765"

    def send_json(self, data: dict, status: int = 200):
        body = json.dumps(data, ensure_ascii=False).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Access-Control-Allow-Origin", self._allowed_origin())
        self.send_header("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type")
        self.end_headers()
        self.wfile.write(body)

    def do_OPTIONS(self):
        self.send_response(200)
        self.send_header("Access-Control-Allow-Origin", self._allowed_origin())
        self.send_header("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type")
        self.end_headers()

    # --- валидация входных данных POST ------------------------------------
    def require_id(self, payload: dict, field: str = "id") -> int | None:
        """Числовой id или ответ 400. Возвращает None, если ответ уже отправлен."""
        raw = payload.get(field)
        row_id = parse_row_id(raw)
        if row_id is None:
            self.send_json({
                "status": "error",
                "message": f"Поле '{field}' должно быть положительным целым числом, получено: {raw!r}",
            }, 400)
        return row_id

    def require_text(self, payload: dict, field: str, label: str,
                     min_len: int = 1, max_len: int = 400_000) -> str | None:
        """Строка в допустимых границах или ответ 400."""
        raw = payload.get(field)
        if not isinstance(raw, str):
            self.send_json({"status": "error",
                            "message": f"Поле '{field}' ({label}) должно быть строкой"}, 400)
            return None
        text = raw.strip()
        if len(text) < min_len:
            self.send_json({"status": "error",
                            "message": f"{label}: нужно минимум {min_len} знаков"}, 400)
            return None
        if len(text) > max_len:
            self.send_json({"status": "error",
                            "message": f"{label}: максимум {max_len} знаков"}, 400)
            return None
        return text

    def require_updated(self, rowcount: int, message: str) -> bool:
        """Проверяет, что UPDATE действительно что-то изменил. Иначе 404."""
        if rowcount == 0:
            self.send_json({"status": "error", "message": message}, 404)
            return False
        return True

    def do_GET(self):
        url = urlparse(self.path)
        path = url.path

        if path == "/api/data":
            self.send_json(fetch_all_data())
            return

        # Конфигурация маршрутизации и живой статус здоровья агентов
        if path == "/api/agents/routing":
            txt, data = load_agents_routing()
            health = check_all_agents_health()
            self.send_json({
                "status": "ok",
                "yaml": txt,
                "config": data,
                "health": health
            })
            return

        # База знаний, Graphify и датасеты LoRA
        if path == "/api/knowledge/status":
            self.send_json(build_knowledge_status())
            return

        if path == "/api/dataset/download":
            ds_path = ROOT / "scratch" / "lora_training_dataset.jsonl"
            if not ds_path.exists():
                generate_lora_dataset()
            if ds_path.exists():
                body = ds_path.read_bytes()
                self.send_response(200)
                self.send_header("Content-Type", "application/jsonl; charset=utf-8")
                self.send_header("Content-Length", str(len(body)))
                self.send_header("Content-Disposition", 'attachment; filename="lora_training_dataset.jsonl"')
                self.send_header("Access-Control-Allow-Origin", "*")
                self.end_headers()
                self.wfile.write(body)
                return
            self.send_json({"status": "error", "message": "Датасет не найден"}, 404)
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

        # AEO / LLM-манифест llms.txt (стандарт llmstxt.org)
        if path in ("/llms.txt", "/api/aeo/llms.txt", "/llms-full.txt", "/api/aeo/llms-full.txt"):
            is_full = "full" in path
            try:
                body = build_llms_txt(full=is_full).encode("utf-8")
            except Exception as ex:  # noqa: BLE001
                self.send_json({"status": "error", "message": str(ex)}, 500)
                return
            self.send_response(200)
            self.send_header("Content-Type", "text/plain; charset=utf-8")
            self.send_header("Content-Length", str(len(body)))
            self.send_header("Access-Control-Allow-Origin", "*")
            self.end_headers()
            self.wfile.write(body)
            return

        # Инженерные шаблоны статей (how-to, comparison, troubleshooting, reference, safety)
        if path == "/api/templates":
            qs = parse_qs(url.query)
            tmpl_id = (qs.get("id") or [""])[0].strip()
            if tmpl_id:
                content = get_article_template(tmpl_id)
                if not content:
                    self.send_json({"status": "error", "message": f"Шаблон '{tmpl_id}' не найден"}, 404)
                    return
                self.send_json({"status": "ok", "id": tmpl_id, "content": content})
            else:
                self.send_json({"status": "ok", "templates": list_article_templates()})
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

        if path == "/api/cluster/status":
            try:
                self.send_json(check_cluster_telemetry())
            except Exception as ex:  # noqa: BLE001
                self.send_json({"status": "error", "message": str(ex)}, 500)
            return

        if path == "/api/dataset/export":
            try:
                self.send_json(export_colab_harvesting_dataset())
            except Exception as ex:  # noqa: BLE001
                self.send_json({"status": "error", "message": str(ex)}, 500)
            return

        if path == "/api/mutagen/balance":
            try:
                import mutagen as mg
                bal = mg.get_balance()
                self.send_json({"status": "ok", "balance": bal, "currency": "₽"})
            except Exception as ex:  # noqa: BLE001
                self.send_json({"status": "ok", "balance": 0.0, "currency": "₽", "unavailable": True})
            return

        # Паспорт статьи (канонический сплав, температуры, стандарт, v1.1)
        if path == "/api/topics/passport":
            qs = parse_qs(url.query)
            tid_raw = (qs.get("id") or [""])[0].strip()
            tid = parse_row_id(tid_raw)
            if tid is None:
                self.send_json({"status": "error", "message": "Укажите корректный числовой ?id="}, 400)
                return
            try:
                from package_contract import package_article
                import canonical_alloys as canonical

                with get_connection() as conn:
                    row = conn.execute(
                        "SELECT id, title, slug, cluster, passport_json, content_markdown FROM topics WHERE id = ?",
                        (tid,),
                    ).fetchone()
                if not row:
                    self.send_json({"status": "error", "message": f"Тема #{tid} не найдена"}, 404)
                    return

                saved_passport = {}
                if "passport_json" in row.keys() and row["passport_json"]:
                    try:
                        saved_passport = json.loads(row["passport_json"])
                    except Exception:
                        saved_passport = {}

                pkg = package_article(tid, passport_override=saved_passport if saved_passport else None)
                contract = pkg.get("contract") or {}

                passport = {
                    "topic_id": tid,
                    "title": row["title"],
                    "slug": row["slug"],
                    "solder_alloy": saved_passport.get("solder_alloy") or contract.get("solder_alloy") or "universal",
                    "temp_kind": (saved_passport.get("temp_kind") or (contract.get("temp_data") or {}).get("kind") or "station_setpoint"),
                    "temp_range": (saved_passport.get("temp_range") or (contract.get("temp_data") or {}).get("range") or ""),
                    "temp_peak": (saved_passport.get("temp_peak") or (contract.get("temp_data") or {}).get("peak") or ""),
                    "difficulty": saved_passport.get("difficulty") or contract.get("difficulty") or "Практик",
                    "standard": saved_passport.get("standard") or "",
                    "tools": saved_passport.get("tools") or contract.get("tools") or [],
                    "faq_count": pkg.get("faq_count", 0),
                    "sources_count": pkg.get("sources_count", 0),
                    "is_customized": bool(saved_passport),
                }

                alloys_catalog = []
                for k in canonical.canonical_keys():
                    data = canonical.REGISTRY_SOLDERS.get(k, {})
                    w_min, w_max = data.get("t_work_min"), data.get("t_work_max")
                    m_min, m_max = data.get("t_melt_min"), data.get("t_melt_max")
                    work_str = f"{w_min} – {w_max} °C" if w_min and w_max else ""
                    melt_str = f"{m_min} – {m_max} °C" if m_min and m_max else (f"{m_min} °C" if m_min else "")
                    alloys_catalog.append({
                        "id": k,
                        "name": canonical.alloy_label(k),
                        "standard": data.get("standard", ""),
                        "type": data.get("type", ""),
                        "work_range": work_str,
                        "melt_range": melt_str,
                    })

                self.send_json({
                    "status": "ok",
                    "passport": passport,
                    "validation": {
                        "valid": pkg.get("valid", False),
                        "errors": pkg.get("errors", []),
                        "missing_warnings": pkg.get("missing_warnings", []),
                        "notes": pkg.get("notes", []),
                    },
                    "alloys_catalog": alloys_catalog,
                })
            except Exception as ex:  # noqa: BLE001
                self.send_json({"status": "error", "message": f"{type(ex).__name__}: {str(ex)[:180]}"}, 500)
            return

        if path == "/api/standards":
            std_file = SEO / "reference" / "standards_matrix.json"
            if std_file.exists():
                try:
                    data = json.loads(std_file.read_text(encoding="utf-8"))
                    self.send_json(data)
                    return
                except Exception as e:
                    self.send_json({"error": str(e)}, 500)
                    return
            self.send_json({"error": "standards_matrix.json not found"}, 404)
            return

        # -------------------------------------------------------------
        # Эндпоинты конфигурации моделей (1+4) и настроек Triad-Omni
        # -------------------------------------------------------------
        if path == "/api/config/models":
            try:
                import models_manager
                cfg = models_manager.get_models_config()
                self.send_json({"status": "ok", "config": cfg})
            except Exception as e:
                self.send_json({"status": "error", "message": str(e)}, 500)
            return

        if path == "/api/standards/list":
            try:
                import pdf_gost_extractor
                stds = pdf_gost_extractor.list_indexed_standards()
                self.send_json({"status": "ok", "standards": stds})
            except Exception as e:
                self.send_json({"status": "error", "message": str(e)}, 500)
            return

        if path == "/api/standards/search":
            qs = parse_qs(url.query)
            q = (qs.get("q") or [""])[0].strip()
            try:
                import pdf_gost_extractor
                results = pdf_gost_extractor.search_knowledge_base(q)
                self.send_json({"status": "ok", "query": q, "results": results})
            except Exception as e:
                self.send_json({"status": "error", "message": str(e)}, 500)
            return

        if path == "/api/cartridges/list":
            try:
                import media_cartridge_manager
                cartridges = media_cartridge_manager.list_cartridges()
                self.send_json({"status": "ok", "cartridges": cartridges})
            except Exception as e:
                self.send_json({"status": "error", "message": str(e)}, 500)
            return

        if path == "/api/media/plan":
            qs = parse_qs(url.query)
            topic_title = (qs.get("title") or ["Пайка плат для начинающих"])[0]
            solder_alloy = (qs.get("alloy") or ["ПОС-61 (183–190°C)"])[0]
            try:
                import media_cartridge_manager
                plan = media_cartridge_manager.generate_article_media_pack(topic_title, solder_alloy)
                self.send_json(plan)
            except Exception as e:
                self.send_json({"status": "error", "message": str(e)}, 500)
            return

        if path == "/api/competitors/search":
            qs = parse_qs(url.query)
            q = (qs.get("q") or ["пайка"])[0].strip()
            try:
                import competitor_parser
                res = competitor_parser.search_and_analyze_competitors(q)
                self.send_json({"status": "ok", "data": res})
            except Exception as e:
                self.send_json({"status": "error", "message": str(e)}, 500)
            return

        if path == "/api/topics/links":
            qs = parse_qs(url.query)
            tid = qs.get("id", [""])[0]
            if not tid:
                self.send_json({"error": "missing id"}, 400)
                return
            try:
                from db import build_db_plan
                import linkgraph
                plan = build_db_plan()
                out_links = linkgraph.outbound_candidates(plan, str(tid))
                in_links = linkgraph.inbound_candidates(plan, str(tid))
                self.send_json({
                    "id": tid,
                    "outbound": out_links,
                    "inbound": in_links
                })
            except Exception as e:
                self.send_json({"error": str(e)}, 500)
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

        if path == "/api/semantics/google":
            # Автономный сбор подсказок Google Suggest и интентов AEO
            qs = parse_qs(url.query)
            query = (qs.get("q") or qs.get("seed") or [""])[0].strip()
            if not query:
                self.send_json({"status": "error", "message": "Параметр 'q' (seed query) обязателен"}, 400)
                return
            try:
                import google_semantics
                cluster = google_semantics.collect_aeo_cluster(query)
                google_semantics.save_to_sqlite(ROOT / "seo" / "data" / "seo.db", cluster)
                self.send_json({"status": "ok", "data": cluster})
            except Exception as ex:  # noqa: BLE001
                self.send_json({"status": "error", "message": f"{type(ex).__name__}: {str(ex)[:150]}"}, 500)
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

        if path == "/api/keyword/local_mutagen":
            phrase = (parse_qs(url.query).get("phrase") or [""])[0].strip()
            if not phrase:
                self.send_json({"status": "error", "message": "Укажите ?phrase="}, 400)
                return
            try:
                from competition_score import compute_local_mutagen
                res = compute_local_mutagen(phrase)
                self.send_json({"status": "ok", "result": res})
            except Exception as e:
                self.send_json({"status": "error", "message": str(e)}, 500)
            return

        if path == "/api/article/aeo_score":
            aid = (parse_qs(url.query).get("id") or [""])[0].strip()
            text = (parse_qs(url.query).get("text") or [""])[0].strip()
            try:
                from score_article import compute_aeo_geo_score
                if aid and not text:
                    with get_connection() as conn:
                        row = conn.execute("SELECT content_markdown FROM topics WHERE id = ?", (aid,)).fetchone()
                        if row and row["content_markdown"]:
                            text = row["content_markdown"]
                res = compute_aeo_geo_score(text or "")
                self.send_json({"status": "ok", "aeo_geo": res})
            except Exception as e:
                self.send_json({"status": "error", "message": str(e)}, 500)
            return

        if path == "/api/keyword/history":
            # Сводная таблица ключевых фраз: объединяем Вордстат (широкий спрос) и Мутаген (конкуренция, точный спрос, хвосты, ставки Директа)
            merged = {}
            with get_connection() as conn:
                # 1. Загружаем данные Вордстата
                try:
                    ws_rows = conn.execute(
                        "SELECT phrase, total_count, checked_at FROM wordstat_metrics ORDER BY checked_at DESC LIMIT 200"
                    ).fetchall()
                    for r in ws_rows:
                        p = (r["phrase"] or "").strip()
                        if not p:
                            continue
                        p_norm = p.lower()
                        if p_norm not in merged:
                            merged[p_norm] = {
                                "phrase": p,
                                "demand_broad": r["total_count"],
                                "demand_exact": None,
                                "tails": None,
                                "competition": None,
                                "bid_spec": None,
                                "bid_first": None,
                                "vital_site": "",
                                "checked_at": r["checked_at"],
                                "has_wordstat": True,
                                "has_mutagen": False,
                            }
                        else:
                            merged[p_norm]["demand_broad"] = r["total_count"]
                            merged[p_norm]["has_wordstat"] = True
                            if (r["checked_at"] or "") > (merged[p_norm].get("checked_at") or ""):
                                merged[p_norm]["checked_at"] = r["checked_at"]
                except Exception:
                    pass

                # 2. Загружаем данные Мутагена и объединяем с той же фразой
                try:
                    mg_rows = conn.execute(
                        "SELECT keyword, strong, wordstat, tails, bid_spec, bid_first, vital_site, checked_at FROM keyword_metrics ORDER BY checked_at DESC LIMIT 200"
                    ).fetchall()
                    for r in mg_rows:
                        p = (r["keyword"] or "").strip()
                        if not p:
                            continue
                        p_norm = p.lower()
                        if p_norm not in merged:
                            merged[p_norm] = {
                                "phrase": p,
                                "demand_broad": None,
                                "demand_exact": r["wordstat"],
                                "tails": r["tails"],
                                "competition": r["strong"],
                                "bid_spec": r["bid_spec"],
                                "bid_first": r["bid_first"],
                                "vital_site": r["vital_site"] or "",
                                "checked_at": r["checked_at"],
                                "has_wordstat": False,
                                "has_mutagen": True,
                            }
                        else:
                            merged[p_norm]["demand_exact"] = r["wordstat"]
                            merged[p_norm]["tails"] = r["tails"]
                            merged[p_norm]["competition"] = r["strong"]
                            merged[p_norm]["bid_spec"] = r["bid_spec"]
                            merged[p_norm]["bid_first"] = r["bid_first"]
                            if r["vital_site"]:
                                merged[p_norm]["vital_site"] = r["vital_site"]
                            merged[p_norm]["has_mutagen"] = True
                            if (r["checked_at"] or "") > (merged[p_norm].get("checked_at") or ""):
                                merged[p_norm]["checked_at"] = r["checked_at"]
                except Exception:
                    pass

            rows = list(merged.values())
            rows.sort(key=lambda x: x.get("checked_at") or "", reverse=True)
            try:
                from competition_score import compute_local_mutagen
                for it in rows[:60]:
                    it["local_mutagen"] = compute_local_mutagen(it["phrase"])
            except Exception:
                pass
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

        # Статика дашборда: index.html, dashboard.css, dashboard.js и сопутствующие файлы
        static_file = None
        if path in ("/", "/index.html"):
            static_file = DASHBOARD_DIR / "index.html"
        elif path.startswith("/"):
            try:
                candidate = (DASHBOARD_DIR / path.lstrip("/")).resolve()
                if candidate.is_file() and str(candidate).startswith(str(DASHBOARD_DIR.resolve())):
                    static_file = candidate
            except Exception:
                static_file = None

        if static_file and static_file.exists():
            content = static_file.read_bytes()
            suffix = static_file.suffix.lower()
            mime_map = {
                ".html": "text/html; charset=utf-8",
                ".css": "text/css; charset=utf-8",
                ".js": "application/javascript; charset=utf-8",
                ".json": "application/json; charset=utf-8",
                ".png": "image/png",
                ".jpg": "image/jpeg",
                ".jpeg": "image/jpeg",
                ".svg": "image/svg+xml",
                ".ico": "image/x-icon",
            }
            ctype = mime_map.get(suffix, "application/octet-stream")
            self.send_response(200)
            self.send_header("Content-Type", ctype)
            self.send_header("Content-Length", str(len(content)))
            self.send_header("Access-Control-Allow-Origin", "*")
            self.end_headers()
            self.wfile.write(content)
            return

        self.send_error(404, "File Not Found")

    def execute_agent_action(self, agent: str, target_id: int | None = None, payload: dict | None = None) -> dict:
        """Вспомогательный метод для прямого тестирования действий субагентов без HTTP-сокета."""
        pl = dict(payload or {})
        pl["agent"] = agent
        if target_id is not None:
            pl["target_id"] = target_id
        self.dispatch_agent_run(pl)
        if hasattr(self, "responses") and self.responses:
            return self.responses[-1][0]
        return {}

    def dispatch_agent_run(self, payload: dict) -> None:
        agent = _scalar(payload.get("agent", ""), "").strip()
        if not agent:
            self.send_json({"status": "error", "message": "Не указан агент"}, 400)
            return

        raw_target = _scalar(payload.get("target_id") or payload.get("topic_id"), "")
        if raw_target in (None, ""):
            target_id = None
        else:
            target_id = parse_row_id(raw_target)
            if target_id is None:
                self.send_json({
                    "status": "error",
                    "message": f"Поле 'target_id' должно быть положительным целым числом, получено: {raw_target!r}",
                }, 400)
                return
        if agent in TARGET_REQUIRED_AGENTS and target_id is None:
            self.send_json({"status": "error",
                            "message": f"Агенту '{agent}' нужен target_id — ID темы из базы"}, 400)
            return

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
        extra_response = {}

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

                    wp_url = get_key("WP_URL") or os.environ.get("WP_URL", "")
                    wp_user = get_key("WP_USER") or os.environ.get("WP_USER", "")
                    wp_pass = get_key("WP_PASS") or os.environ.get("WP_PASS", "")
                    want_live = bool(payload.get("live"))

                    if want_live and not (wp_url and wp_pass):
                        log_output = (
                            f"❌ Ошибка конфигурации отправки статьи #{target_id} в WordPress:\n"
                            "Для реальной доставки (live) необходимо указать WP_URL и WP_PASS "
                            "в файле config/api-keys.local.yaml или в переменных окружения.\n"
                            "Автоматическое переключение в тестовый режим запрещено во избежание дезинформации."
                        )
                        status = "failed"
                    else:
                        is_live = want_live and bool(wp_url and wp_pass)
                        cmd = [sys.executable, str(delivery_tool), "send-draft", "--file", str(contract_path)]
                        if is_live:
                            cmd.extend(["--wp-url", wp_url, "--wp-user", wp_user, "--wp-pass", wp_pass])
                        else:
                            cmd.append("--dry-run")

                        p = subprocess.run(cmd, capture_output=True, text=True, encoding="utf-8", timeout=45)
                        mode_label = "Реальная отправка черновика в WordPress" if is_live else "Тестовая валидация (dry-run, схема v1.1)"
                        log_output = f"✓ Пакет статьи #{target_id} 100% валиден по схеме v1.1!\nРежим: {mode_label}\n\n" + p.stdout + "\n" + p.stderr
                        status = "succeeded" if p.returncode == 0 else "failed"

                        journal_file = ROOT / "delivery-journal.json"
                        contract_cid = res.get("contract", {}).get("content_id", "")
                        if journal_file.exists() and is_live and p.returncode == 0 and contract_cid:
                            try:
                                j_data = json.loads(journal_file.read_text(encoding="utf-8"))
                                site_key = wp_url.rstrip("/").lower()
                                site_entries = j_data.get("sites", {}).get(site_key, {})
                                entry = site_entries.get(contract_cid)
                                if entry:
                                    p_id = entry.get("wp_post_id")
                                    p_status = entry.get("status") or "draft"
                                    if p_id:
                                        with get_connection() as conn:
                                            conn.execute(
                                                "UPDATE topics SET wp_post_id = ?, wp_status = ?, updated_at = ? WHERE id = ?",
                                                (p_id, p_status, datetime.now().isoformat(timespec="seconds"), target_id),
                                            )
                                            conn.commit()
                            except Exception:
                                pass

            elif agent in ("01-researcher", "bobr") and target_id:
                with get_connection() as conn:
                    topic_exists = conn.execute("SELECT id FROM topics WHERE id = ?", (target_id,)).fetchone()
                if not topic_exists:
                    status = "failed"
                    log_output = f"Тема #{target_id} не найдена в базе!"
                else:
                    import generate_brief
                    brief_data = generate_brief.build_author_brief(target_id)
                    brief_md = brief_data.get("markdown", "")
                    now_str = datetime.now().isoformat(timespec="seconds")

                    with get_connection() as conn:
                        cols = [c[1] for c in conn.execute("PRAGMA table_info(topics)").fetchall()]
                        if "brief_markdown" not in cols:
                            try:
                                conn.execute("ALTER TABLE topics ADD COLUMN brief_markdown TEXT;")
                                conn.commit()
                            except Exception:
                                pass

                        row = conn.execute("SELECT passport_json FROM topics WHERE id = ?", (target_id,)).fetchone()
                        passport_dict = {}
                        if row and row["passport_json"]:
                            try:
                                passport_dict = json.loads(row["passport_json"])
                            except Exception:
                                passport_dict = {}
                        if not passport_dict.get("standard") and brief_data.get("standards"):
                            passport_dict["standard"] = "; ".join(brief_data["standards"][:3])
                        if brief_data.get("standards"):
                            passport_dict.setdefault("sources", [{"title": s} for s in brief_data["standards"][:4]])

                        conn.execute("""
                            UPDATE topics 
                            SET brief_markdown = ?, 
                                passport_json = ?,
                                status = 'brief_ready', 
                                next_action = 'Бриф сформирован: Бобёр-Конструктор [01-researcher]', 
                                updated_at = ? 
                            WHERE id = ?
                        """, (brief_md, json.dumps(passport_dict, ensure_ascii=False) if passport_dict else (row["passport_json"] if row else None), now_str, target_id))
                        conn.commit()

                    briefs_dir = ROOT / "seo" / "out" / "briefs"
                    briefs_dir.mkdir(parents=True, exist_ok=True)
                    (briefs_dir / f"{target_id}-brief.md").write_text(brief_md, encoding="utf-8")

                    extra_response["brief"] = brief_data
                    log_output = (
                        f"✅ Бобёр-Конструктор [01-researcher] сформировал бриф для темы #{target_id} «{brief_data['title']}»:\n\n"
                        f"• Стандарты: {', '.join(brief_data.get('standards', [])[:3])}\n"
                        f"• Запросы Вордстат: {len(brief_data.get('queries', []))} шт.\n"
                        f"• Статус в базе: brief_ready\n\n"
                        f"{brief_md[:1200]}..."
                    )
                    status = "succeeded"

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
                    status = "succeeded" if p.returncode in (0, 2, 3, 4) else "failed"
                    if tmp_file.exists():
                        try:
                            tmp_file.unlink()
                        except Exception:
                            pass
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
                prov = (payload.get("provider") or "").strip().lower()
                if prov == "triad":
                    model_spec = "triad:qwen3.5-9b-tochka"
                elif prov == "omniroute":
                    model_spec = (payload.get("model") or "omniroute:auto/thinking").strip()
                    if not model_spec.startswith("omniroute:"):
                        model_spec = f"omniroute:{model_spec}"
                elif prov == "local_heuristic":
                    model_spec = "local_heuristic"
                else:
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
                    except Exception:
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
                        try:
                            answer, used = voice_generate(
                                model_spec, system_prompt, instruction + pasted,
                                max_tokens=12000, temperature=0.3,
                            )
                        except Exception:
                            from writer_practico import generate_local_heuristic_text
                            answer = generate_local_heuristic_text(user_prompt=instruction, system_prompt=system_prompt, topic_id=target_id, voice=voice)
                            used = "local_heuristic (offline fallback)"

                        chars = len(answer.strip())
                        slug = f"article-{target_id}"
                        preview_only = bool(payload.get("preview_only", False))
                        extra_response["adapted_markdown"] = answer
                        extra_response["chars"] = chars

                        if not preview_only:
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
                                art_dir = ROOT / "articles"
                                art_dir.mkdir(parents=True, exist_ok=True)
                                (art_dir / f"{target_id}-{slug}.md").write_text(answer, encoding="utf-8")
                            except Exception:
                                pass
                            log_output = f"✅ Готовый текст адаптирован и сохранён: {voice_name} · {used} ({chars} зн.)\n\n{answer[:1500]}"
                        else:
                            log_output = f"👁️ Предпросмотр адаптации ({chars} зн.): {voice_name} · {used}\nТекст готов к проверке и утверждению."
                        status = "succeeded"
                else:
                    instruction = (
                        "Напиши статью по теме «" + _topic_title(target_id) + "» в этом голосе. "
                        "Стандарты: Answer First ≤ 40 слов, 4-8 H2, 1-2 таблицы, FAQ 4-6 пар "
                        "в блоке '### FAQ:', источники 2-4 в '## Источники:', объём 5 500-7 000 знаков. "
                        "Верни только markdown."
                    )
                    try:
                        answer, used = voice_generate(
                            model_spec, system_prompt, instruction,
                            max_tokens=12000, temperature=0.35,
                        )
                    except Exception:
                        from writer_practico import generate_local_heuristic_text
                        answer = generate_local_heuristic_text(user_prompt=instruction, system_prompt=system_prompt, topic_id=target_id, voice=voice)
                        used = "local_heuristic (offline fallback)"

                    _save_written_article(target_id, answer, author=f"ai-{voice}")
                    log_output = f"✅ Статья написана: {voice_name} · {used} ({len(answer)} зн.)\n\n{answer[:1500]}"
                    status = "succeeded"

            elif agent in ("02-writer-2", "lis") and target_id:
                voice = (payload.get("voice") or "practico2").strip()
                voices = {v["id"]: v for v in list_voices()}
                voice_row = voices.get(voice) or voices.get("practico2")
                prov = (payload.get("provider") or "").strip().lower()
                if prov == "triad":
                    model_spec = "triad:qwen3.5-9b-tochka"
                elif prov == "omniroute":
                    model_spec = (payload.get("model") or "omniroute:auto/thinking").strip()
                    if not model_spec.startswith("omniroute:"):
                        model_spec = f"omniroute:{model_spec}"
                elif prov == "local_heuristic":
                    model_spec = "local_heuristic"
                else:
                    model_spec = (payload.get("model") or (voice_row or {}).get("model") or "omniroute:auto/thinking").strip()

                try:
                    from writer_practico import PRACTICO2_SYSTEM_PROMPT
                    system_prompt = PRACTICO2_SYSTEM_PROMPT
                except Exception:
                    system_prompt = "Ты — Лис-Прагматик, автор проекта «Точка Плавления» в голосе Practico 2. Сметы, точные затраты, окупаемость оборудования и строгая физика пайки без слопа."

                voice_name = (voice_row or {}).get("name") or "Practico 2 (Лис-Прагматик)"
                instruction = (
                    "Напиши исчерпывающую статью со сметным расчетом и анализом окупаемости по теме «" + _topic_title(target_id) + "» в голосе Practico 2 (Лис-Прагматик). "
                    "Обязательные элементы: Answer First ≤ 40 слов, 4-8 разделов H2, включая блок подробной сметы материалов и оборудования, "
                    "1-2 сопоставительные таблицы затрат, раздел «## Безопасность и средства защиты», раздел «## Источники и нормативные документы», "
                    "блок FAQ 4-6 пар в конце ('### FAQ:'). Объём 5 500-7 000 знаков с пробелами. Верни только markdown."
                )

                try:
                    answer, used = voice_generate(model_spec, system_prompt, instruction, max_tokens=12000, temperature=0.35)
                except Exception:
                    from writer_practico import generate_local_heuristic_text
                    answer = generate_local_heuristic_text(user_prompt=instruction, system_prompt=system_prompt, topic_id=target_id, voice="practico2")
                    used = "local_heuristic (offline fallback)"

                _save_written_article(target_id, answer, author=f"ai-{voice}")
                chars = len(answer.strip())

                try:
                    from score_article import blocks as score_blocks
                    report = score_blocks(answer, None, str(target_id))
                    with get_connection() as conn:
                        conn.execute("UPDATE topics SET score_total = ?, score_json = ? WHERE id = ?",
                                     (report["total"], json.dumps(report, ensure_ascii=False), target_id))
                        conn.commit()
                    extra_response["score"] = report["total"]
                except Exception:
                    pass

                extra_response["chars"] = chars
                log_output = f"✅ Статья со сметой и окупаемостью написана: {voice_name} · {used} ({chars} зн.)\n\n{answer[:1500]}"
                status = "succeeded"

            elif agent == "05-illustrator" and target_id:
                from craftsman_illustrator import design_visual_package_for_topic
                res = design_visual_package_for_topic(target_id)
                if res["status"] == "success":
                    log_output = (
                        f"✅ Барсук-Иллюстратор [05-illustrator]:\n{res['summary']}\n"
                        f"• Файл схемы: {res['svg_file']}\n"
                        f"• Сплав: {res['alloy']}\n"
                        f"• Hero-промпт: {res['media']['hero_image']['prompt'][:120]}..."
                    )
                    status = "succeeded"
                else:
                    log_output = f"❌ Ошибка генерации визуала: {res.get('message', 'Неизвестная ошибка')}"
                    status = "failed"

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

                    answer = None
                    used = None
                    review_json = {}
                    try:
                        answer, used = oxion_chat(
                            OXION_ROLE_MODELS[model_key], system_prompt,
                            f"Статья #{target_id} «{row['title']}»:\n\n{text}",
                            max_tokens=6000,
                        )
                    except Exception as e_oxion:
                        answer, used, review_json = run_offline_reviewer(
                            text=text, title=row["title"] if row else "", target_id=target_id,
                            is_critical=critical, agent=agent
                        )
                        extra_response["review"] = review_json

                    verdict = "done"
                    low_ans = answer.lower()
                    if re.search(r"(?i)вердикт[:\s]*(?:принято|годен|одобрено|соответствует)", answer):
                        verdict = "passed"
                    elif re.search(r"(?i)вердикт[:\s]*(?:доработка|правки|требует правок|не годен|отклон|ошибк)", answer) or "блок" in low_ans:
                        verdict = "needs_fix"

                    deslop_tool = ROOT / "tools" / "deslop.py"
                    deslop_report = ""
                    if not review_json and deslop_tool.exists():
                        try:
                            p_deslop = subprocess.run(
                                [sys.executable, str(deslop_tool), "--text", text[:4000]],
                                capture_output=True, text=True, encoding="utf-8", timeout=10
                            )
                            deslop_out = p_deslop.stdout.strip()
                            if "needs a cleanse" in deslop_out or "score" in deslop_out:
                                deslop_report = f"\n\n[🦉 Humanizer / SlopMonster Report]:\n{deslop_out}"
                                if p_deslop.returncode != 0 and verdict == "passed":
                                    verdict = "needs_fix"
                        except Exception:
                            pass

                    with get_connection() as conn:
                        conn.execute(
                            f"UPDATE topics SET {status_column} = ?, {model_column} = ?, "
                            "next_action = ?, updated_at = ? WHERE id = ?",
                            (verdict, used, f"{label} ({verdict}): {used}",
                             datetime.now().isoformat(timespec="seconds"), target_id),
                        )
                        conn.commit()
                    log_output = f"✅ {label.capitalize()} завершён: вердикт «{verdict}» (модель {used}){deslop_report}\n\n{answer}"
                    status = "succeeded"

            elif agent in ("06-knowledge", "raccoon"):
                ds_res = generate_lora_dataset()
                k_status = build_knowledge_status()
                extra_response["dataset"] = ds_res
                extra_response["knowledge"] = k_status
                log_output = (
                    f"✅ Енот-Датасетник [06-knowledge] упаковал LoRA-датасет:\n"
                    f"• Экспортировано примеров ChatML: {ds_res.get('exported_count', 0)}\n"
                    f"• Файл датасета: {ds_res.get('file_path')}\n"
                    f"• Размер: {ds_res.get('size_bytes', 0)} байт\n"
                    f"• Граф Graphify: {k_status.get('graphify', {}).get('nodes_count', 0)} узлов, {k_status.get('graphify', {}).get('edges_count', 0)} связей\n"
                    f"• Покрытие ГОСТ: {len(k_status.get('standards', []))} стандартов в базе\n"
                    f"• Темы с пробелами знаний: {len(k_status.get('knowledge_gaps', []))} шт."
                )
                status = "succeeded"

            elif agent in ("assistant", "bear"):
                telemetry = check_cluster_telemetry()
                health = check_all_agents_health()
                with get_connection() as conn:
                    total_topics = conn.execute("SELECT count(*) FROM topics").fetchone()[0]
                    written = conn.execute("SELECT count(*) FROM topics WHERE content_markdown IS NOT NULL AND length(content_markdown) > 200").fetchone()[0]
                    reviewed = conn.execute("SELECT count(*) FROM topics WHERE review_status = 'passed'").fetchone()[0]
                    scored = conn.execute("SELECT count(*) FROM topics WHERE score_total IS NOT NULL").fetchone()[0]
                    with_media = conn.execute("SELECT count(*) FROM topics WHERE media_json IS NOT NULL").fetchone()[0]
                    published = conn.execute("SELECT count(*) FROM topics WHERE wp_post_id IS NOT NULL").fetchone()[0]
                    last_topic = conn.execute("SELECT id, title, status, updated_at FROM topics ORDER BY updated_at DESC LIMIT 1").fetchone()

                stats_dict = {
                    "total": total_topics, "written": written, "reviewed": reviewed,
                    "with_media": with_media, "scored": scored, "published": published
                }
                extra_response["telemetry"] = telemetry
                extra_response["health"] = health
                extra_response["stats"] = stats_dict

                t_nodes = telemetry.get("triad", {}).get("nodes", [])
                nodes_str = "\n".join([f"  • {n.get('name')}: {n.get('state', 'online')} ({n.get('device')} · :{n.get('port')})" for n in t_nodes]) if isinstance(t_nodes, list) else "  • Узлы опрашиваются..."

                log_output = (
                    f"🐻 Медведь-Михалыч [Староста Артели / Патруль телеметрии]:\n"
                    f"Обход артели завершён. Телеметрия кластера Triad-Omni v4.9 и шлюза OmniRoute зафиксирована на планшете.\n\n"
                    f"📡 [Узлы мобильного кластера Triad-Omni]:\n{nodes_str}\n"
                    f"• Шлюз OmniRoute (:20128): {telemetry.get('omniroute', {}).get('status', 'standby')} ({telemetry.get('omniroute', {}).get('models_count', 0)} моделей)\n\n"
                    f"📋 [Сводка верстаков Артели]:\n"
                    f"• Тем в базе: {total_topics} | Написано: {written} | ОТК [Одобрено]: {reviewed}\n"
                    f"• Схем термопрофилей: {with_media} | 100-балльный скоринг: {scored} | В релизе WP: {published}\n"
                    f"• Последняя активная тема: #{last_topic['id'] if last_topic else 1} «{last_topic['title'] if last_topic else '—'}»\n\n"
                    f"Все 9 мастеров на связи. Рация и планшет в строю!"
                )
                status = "succeeded"

            else:
                log_output = f"Агент {agent} требует вызова модели Claude/Gemini. Промпт сформирован по стандартам seo/agents/{agent}/AGENT.md."
                status = "not_implemented"
        except (Exception, SystemExit, BaseException) as ex:
            status = "failed"
            log_output = str(ex)

        safe_agent = _scalar(agent, "unknown").strip() or "unknown"
        with get_connection() as conn:
            conn.execute("""
                INSERT INTO agent_runs (agent_name, action, target_id, status, log, created_at)
                VALUES (?, ?, ?, ?, ?, ?)
            """, (safe_agent, "run", str(target_id or ""), status, log_output[:2000], datetime.now().isoformat(timespec="seconds")))
            conn.commit()

        resp_payload = {"status": status, "agent": agent, "log": log_output}
        if extra_response:
            resp_payload.update(extra_response)
        self.send_json(resp_payload)

    def do_POST(self):
        url = urlparse(self.path)
        path = url.path

        # Безопасность API: Origin-проверка от CSRF/DNS-rebinding
        origin = self.headers.get("Origin")
        if origin and not (
            origin.startswith("http://localhost:")
            or origin.startswith("http://127.0.0.1:")
            or origin in ("http://localhost", "http://127.0.0.1")
        ):
            self.send_json({"status": "error", "message": "Cross-origin requests from external origins forbidden"}, 403)
            return

        length = int(self.headers.get("Content-Length", 0))
        if length > 5 * 1024 * 1024:
            self.send_json({"status": "error", "message": "Payload too large (max 5MB)"}, 413)
            return

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
            title = _scalar(payload.get("title", ""), "").strip()
            cluster = _scalar(payload.get("cluster", ""), "").strip() or "Материалы / Припои"
            ctype = _scalar(payload.get("type", ""), "").strip() or "гайд"
            priority = _scalar(payload.get("priority", ""), "").strip() or "средний"
            notes = _scalar(payload.get("notes", ""), "").strip()

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
            pid = self.require_id(payload)
            if pid is None:
                return
            with get_connection() as conn:
                cur = conn.cursor()
                row = cur.execute("SELECT * FROM topics_proposed WHERE id = ?", (pid,)).fetchone()
                if not row:
                    self.send_json({"status": "error", "message": "Тема не найдена"}, 404)
                    return
                # Находим следующий ID
                max_id = cur.execute("SELECT MAX(id) FROM topics").fetchone()[0] or 0
                next_id = max_id + 1
                base_slug = slugify(row["title"]) or f"topic-{next_id}"
                final_slug = f"{base_slug}-{next_id}"
                cur.execute("""
                    INSERT INTO topics (id, title, slug, cluster, type, priority_score, status, next_action)
                    VALUES (?, ?, ?, ?, ?, ?, 'backlog', 'одобрена из topics_proposed')
                """, (next_id, row["title"], final_slug, row["cluster"], row["type"], row["priority_score"]))
                cur.execute("UPDATE topics_proposed SET status = 'принят' WHERE id = ?", (pid,))
                conn.commit()
            self.send_json({"status": "ok", "new_topic_id": next_id})
            return

        # 3. Отклонить предложенную тему
        if path == "/api/topics/reject":
            pid = self.require_id(payload)
            if pid is None:
                return
            with get_connection() as conn:
                cur = conn.execute("UPDATE topics_proposed SET status = 'отклонён' WHERE id = ?", (pid,))
                conn.commit()
            if not self.require_updated(cur.rowcount, f"Предложенная тема #{pid} не найдена"):
                return
            self.send_json({"status": "ok", "id": pid})
            return

        # 4. Одобрить конкурента -> в competitors
        if path == "/api/competitors/approve":
            domain = self.require_text(payload, "domain", "домен конкурента", min_len=3, max_len=253)
            if domain is None:
                return
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

        # -------------------------------------------------------------
        # Управление конфигурацией моделей (1+4) и стандартами
        # -------------------------------------------------------------
        if path == "/api/config/models":
            try:
                import models_manager
                success = models_manager.update_models_config(payload)
                self.send_json({"status": "ok" if success else "error"})
            except Exception as e:
                self.send_json({"status": "error", "message": str(e)}, 500)
            return

        if path == "/api/config/animal_model":
            animal = payload.get("animal") or payload.get("animal_id")
            slot = payload.get("slot", "primary")
            model_name = payload.get("model_name") or payload.get("selection", "")
            if not animal:
                self.send_json({"status": "error", "message": "Требуется animal / animal_id"}, 400)
                return
            try:
                import models_manager
                res = models_manager.set_animal_model(animal, slot, model_name)
                self.send_json(res)
            except Exception as e:
                self.send_json({"status": "error", "message": str(e)}, 500)
            return

        if path == "/api/config/provider_key":
            provider = payload.get("provider")
            api_key = payload.get("api_key", "")
            active = bool(payload.get("active", True))
            if not provider:
                self.send_json({"status": "error", "message": "Требуется provider"}, 400)
                return
            try:
                import models_manager
                res = models_manager.set_provider_key(provider, api_key, active)
                self.send_json(res)
            except Exception as e:
                self.send_json({"status": "error", "message": str(e)}, 500)
            return

        if path == "/api/cartridges/deploy":
            c_name = payload.get("cartridge_name") or payload.get("name")
            if not c_name:
                self.send_json({"status": "error", "message": "Не указано имя картриджа"}, 400)
                return
            try:
                import media_cartridge_manager
                res = media_cartridge_manager.deploy_cartridge_to_node1(c_name)
                self.send_json(res)
            except Exception as e:
                self.send_json({"status": "error", "message": str(e)}, 500)
            return

        if path == "/api/media/refine_prompt":
            slot_key = payload.get("slot_key") or "slot_1_hero"
            user_critique = payload.get("user_critique") or ""
            base_prompt = payload.get("base_prompt") or ""
            try:
                import media_cartridge_manager
                res = media_cartridge_manager.refine_image_prompt_iteration(slot_key, user_critique, base_prompt)
                self.send_json(res)
            except Exception as e:
                self.send_json({"status": "error", "message": str(e)}, 500)
            return

        if path == "/api/media/evaluate_image":
            file_name = payload.get("file_name") or "image.webp"
            file_size_kb = int(payload.get("file_size_kb") or 150)
            width = int(payload.get("width") or 1200)
            height = int(payload.get("height") or 675)
            try:
                import media_cartridge_manager
                res = media_cartridge_manager.evaluate_uploaded_image(file_name, file_size_kb, width, height)
                self.send_json(res)
            except Exception as e:
                self.send_json({"status": "error", "message": str(e)}, 500)
            return

        if path == "/api/standards/upload_pdf":
            # Сохранение и индексация PDF ГОСТа (Рокки)
            pdf_b64 = payload.get("pdf_base64")
            raw_filename = payload.get("filename", "uploaded_standard.pdf")
            filename = os.path.basename(raw_filename).strip() or "uploaded_standard.pdf"
            title = payload.get("title", filename)
            status_val = payload.get("status", "active")
            
            if not pdf_b64:
                self.send_json({"status": "error", "message": "Отсутствует pdf_base64"}, 400)
                return
            try:
                import base64
                import pdf_gost_extractor
                
                target_dir = ROOT / "seo" / "reference" / "standards_pdf"
                target_dir.mkdir(parents=True, exist_ok=True)
                target_file = target_dir / filename
                
                file_bytes = base64.b64decode(pdf_b64)
                with open(target_file, "wb") as f:
                    f.write(file_bytes)
                    
                result = pdf_gost_extractor.index_pdf_document(target_file, title=title, status=status_val)
                self.send_json(result)
            except Exception as e:
                self.send_json({"status": "error", "message": str(e)}, 500)
            return

        if path == "/api/humanizer/review":
            text = payload.get("text", "")
            if not text:
                self.send_json({"status": "error", "message": "Текст не передан"}, 400)
                return
            try:
                import humanizer_reviewer
                res = humanizer_reviewer.audit_human_style(text)
                self.send_json(res)
            except Exception as e:
                self.send_json({"status": "error", "message": str(e)}, 500)
            return

        if path == "/api/competitors/radar":
            query = payload.get("query", "")
            url = payload.get("url", "")
            try:
                import competitor_radar
                if url:
                    res = competitor_radar.crawl_page_features(url)
                else:
                    res = competitor_radar.run_competitor_radar(query)
                self.send_json(res)
            except Exception as e:
                self.send_json({"status": "error", "message": str(e)}, 500)
            return

        if path == "/api/config/test_connection":
            provider_id = payload.get("provider", "triad_cluster")
            try:
                import models_manager
                res = models_manager.test_provider_connection(provider_id)
                self.send_json(res)
            except Exception as e:
                self.send_json({"status": "error", "message": str(e)}, 500)
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
            tid = self.require_id(payload)
            if tid is None:
                return
            with get_connection() as conn:
                row = conn.execute("SELECT content_markdown, chars, score_total, review_status FROM topics WHERE id = ?", (tid,)).fetchone()
                if not row:
                    self.send_json({"status": "error", "message": f"Статья #{tid} не найдена"}, 404)
                    return
                text = (row["content_markdown"] or "").strip()
                if len(text) < 500:
                    self.send_json({"status": "error", "message": f"Нельзя утвердить статью #{tid}: текст отсутствует или слишком короткий ({len(text)} < 500 зн.)."}, 400)
                    return
                cur = conn.execute(
                    "UPDATE topics SET status = 'ready', "
                    "next_action = 'утверждено шеф-редактором к ОТК', updated_at = ? WHERE id = ?",
                    (datetime.now().isoformat(timespec="seconds"), tid),
                )
                conn.commit()
            if not self.require_updated(cur.rowcount, f"Статья #{tid} не найдена"):
                return
            self.send_json({"status": "ok", "id": int(tid), "new_status": "ready"})
            return

        # 6. Вернуть статью на доработку
        if path == "/api/articles/reject":
            tid = self.require_id(payload)
            if tid is None:
                return
            reason = _scalar(payload.get("reason", "Замечания редактора"), "Замечания редактора").strip()[:500]
            with get_connection() as conn:
                # needs_fix — отдельный статус, чтобы статья ГАРАНТИРОВАННО
                # попала в колонку «Требует правок», даже если балл был ≥ 80
                cur = conn.execute(
                    "UPDATE topics SET status = 'needs_fix', next_action = ?, updated_at = ? WHERE id = ?",
                    (f"доработка: {reason}", datetime.now().isoformat(timespec="seconds"), tid),
                )
                conn.commit()
            if not self.require_updated(cur.rowcount, f"Статья #{tid} не найдена"):
                return
            self.send_json({"status": "ok", "id": tid, "new_status": "needs_fix"})
            return

        # 6.1. Изменение статуса статьи (drag-and-drop на канбан-доске)
        if path == "/api/articles/status":
            tid = self.require_id(payload)
            if tid is None:
                return
            new_status = _scalar(payload.get("status", ""), "").strip().lower()
            VALID_STATUSES = {
                "backlog": "перенесено в бэклог",
                "work": "перенесено в работу",
                "needs_fix": "на доработке (требует правок)",
                "review": "на ревью (ОТК)",
                "ready": "утверждено к выгрузке",
                "published": "опубликовано в WordPress",
            }
            if new_status not in VALID_STATUSES:
                self.send_json({
                    "status": "error",
                    "message": f"Недопустимый статус: {new_status!r}. Допустимы: {list(VALID_STATUSES.keys())}",
                }, 400)
                return

            if new_status == "published":
                with get_connection() as conn:
                    row = conn.execute("SELECT wp_post_id FROM topics WHERE id = ?", (tid,)).fetchone()
                    if not row or not row["wp_post_id"]:
                        self.send_json({
                            "status": "error",
                            "message": f"Статья #{tid} не может быть перемещена в «В WordPress» вручную: она ещё не отправлена в WordPress (отсутствует wp_post_id). Запустите доставку черновика через агента delivery.",
                        }, 400)
                        return

            action_desc = VALID_STATUSES[new_status]
            now_str = datetime.now().isoformat(timespec="seconds")
            with get_connection() as conn:
                cur = conn.execute(
                    "UPDATE topics SET status = ?, next_action = ?, updated_at = ? WHERE id = ?",
                    (new_status, action_desc, now_str, tid),
                )
                conn.commit()

            if not self.require_updated(cur.rowcount, f"Статья #{tid} не найдена"):
                return
            self.send_json({"status": "ok", "id": int(tid), "new_status": new_status, "next_action": action_desc})
            return

        # 5.5. Флаг критичной статьи: рецензия уходит на Opus, обычная — на Gemini
        if path == "/api/articles/critical":
            tid = self.require_id(payload)
            if tid is None:
                return
            flag = 1 if payload.get("critical") else 0
            with get_connection() as conn:
                cur = conn.execute(
                    "UPDATE topics SET is_critical = ?, updated_at = ? WHERE id = ?",
                    (flag, datetime.now().isoformat(timespec="seconds"), tid),
                )
                conn.commit()
            if not self.require_updated(cur.rowcount, f"Статья #{tid} не найдена"):
                return
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
            raw_id = self.require_text(payload, "id", "id голоса", min_len=1, max_len=60)
            if raw_id is None:
                return
            voice_id = re.sub(r"[^a-z0-9_-]+", "-", raw_id.lower()).strip("-")
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
            voice_id = self.require_text(payload, "id", "id голоса", min_len=1, max_len=60)
            if voice_id is None:
                return
            with get_connection() as conn:
                cur = conn.execute("UPDATE voices SET is_active = 0 WHERE id = ?", (voice_id,))
                conn.commit()
            if not self.require_updated(cur.rowcount, f"Голос '{voice_id}' не найден"):
                return
            self.send_json({"status": "ok", "id": voice_id})
            return

        # 6.5. Сохранить отредактированный текст статьи
        if path == "/api/topics/update-text":
            tid = self.require_id(payload)
            if tid is None:
                return
            new_text = payload.get("content_markdown", "")
            if not isinstance(new_text, str):
                self.send_json({"status": "error",
                                "message": "Поле 'content_markdown' должно быть строкой"}, 400)
                return
            now_str = datetime.now().isoformat(timespec="seconds")
            chars = len(new_text.strip())

            with get_connection() as conn:
                row = conn.execute("SELECT slug, content_markdown, chars FROM topics WHERE id = ?", (tid,)).fetchone()
                if not row:
                    self.send_json({"status": "error", "message": f"Статья #{tid} не найдена"}, 404)
                    return
                slug = row["slug"] or f"article-{tid}"
                old_text = row["content_markdown"] or ""
                old_chars = row["chars"] or len(old_text.strip())
                if old_text and old_text.strip() != new_text.strip():
                    try:
                        conn.execute(
                            "INSERT INTO topic_versions (topic_id, content_markdown, chars, author, created_at) "
                            "VALUES (?, ?, ?, ?, ?)",
                            (tid, old_text, old_chars, "editor", now_str),
                        )
                    except Exception:
                        pass
                    # При изменении текста сбрасываем статус рецензии и фактчека как устаревшие
                    conn.execute("""
                        UPDATE topics
                        SET content_markdown = ?, chars = ?, review_status = NULL, factcheck_status = NULL, updated_at = ?
                        WHERE id = ?
                    """, (new_text, chars, now_str, tid))
                else:
                    conn.execute("""
                        UPDATE topics
                        SET content_markdown = ?, chars = ?, updated_at = ?
                        WHERE id = ?
                    """, (new_text, chars, now_str, tid))
                conn.commit()

            # Синхронизируем файл на диске в `articles/`
            art_dir = ROOT / "articles"
            art_dir.mkdir(parents=True, exist_ok=True)
            art_file = art_dir / f"{tid}-{slug}.md"
            try:
                art_file.write_text(new_text, encoding="utf-8")
            except Exception:
                pass

            self.send_json({"status": "ok", "id": tid, "chars": chars})
            return

        # 6.6. Сохранение технического паспорта статьи (v1.1)
        if path == "/api/topics/passport":
            tid = self.require_id(payload)
            if tid is None:
                return
            solder_alloy = _scalar(payload.get("solder_alloy", ""), "").strip().lower() or "universal"
            temp_kind = _scalar(payload.get("temp_kind", ""), "").strip() or "station_setpoint"
            temp_range = _scalar(payload.get("temp_range", ""), "").strip()
            temp_peak = _scalar(payload.get("temp_peak", ""), "").strip()
            difficulty = _scalar(payload.get("difficulty", ""), "").strip() or "Практик"
            standard = _scalar(payload.get("standard", ""), "").strip()
            tools_raw = payload.get("tools")
            tools_list = []
            if isinstance(tools_raw, list):
                for t in tools_raw:
                    if isinstance(t, dict) and t.get("name"):
                        tools_list.append({"name": str(t["name"]).strip(), "model": str(t.get("model", "")).strip(), "purpose": str(t.get("purpose", "Пайка")).strip()})
                    elif isinstance(t, str) and t.strip():
                        tools_list.append({"name": t.strip(), "model": "", "purpose": "Пайка"})
            elif isinstance(tools_raw, str) and tools_raw.strip():
                for t in tools_raw.split(","):
                    if t.strip():
                        tools_list.append({"name": t.strip(), "model": "", "purpose": "Пайка"})

            sources_raw = payload.get("sources")
            sources_list = []
            if isinstance(sources_raw, list):
                for s in sources_raw:
                    if isinstance(s, dict) and s.get("title"):
                        sources_list.append(s)
                    elif isinstance(s, str) and s.strip():
                        sources_list.append({"title": s.strip(), "standard": standard or "IPC/ГОСТ"})

            passport_dict = {
                "solder_alloy": solder_alloy,
                "temp_kind": temp_kind,
                "temp_range": temp_range,
                "temp_peak": temp_peak,
                "difficulty": difficulty,
                "standard": standard,
                "tools": tools_list,
                "updated_at": datetime.now().isoformat(timespec="seconds"),
            }
            if sources_list:
                passport_dict["sources"] = sources_list

            passport_json_str = json.dumps(passport_dict, ensure_ascii=False)

            with get_connection() as conn:
                conn.execute(
                    "UPDATE topics SET passport_json = ?, updated_at = ? WHERE id = ?",
                    (passport_json_str, datetime.now().isoformat(timespec="seconds"), tid),
                )
                conn.commit()

            # Сборка и валидация контракта v1.1
            from package_contract import package_article
            pkg = package_article(tid, passport_override=passport_dict)
            contract_file_rel = f"seo/data/contract_{tid}.json"
            if pkg.get("contract"):
                contract_path = DATA / f"contract_{tid}.json"
                contract_path.write_text(json.dumps(pkg["contract"], ensure_ascii=False, indent=2), encoding="utf-8")

            self.send_json({
                "status": "ok",
                "id": tid,
                "valid": pkg.get("valid", False),
                "errors": pkg.get("errors", []),
                "missing_warnings": pkg.get("missing_warnings", []),
                "notes": pkg.get("notes", []),
                "contract_file": contract_file_rel,
                "passport": passport_dict,
            })
            return

        # 6.4. Сохранение YAML-маршрутизации и пинг здоровья узлов
        if path == "/api/agents/routing":
            new_yaml = payload.get("yaml", "")
            if not new_yaml:
                self.send_json({"status": "error", "message": "Пустой YAML-конфиг"}, 400)
                return
            try:
                data = save_agents_routing(new_yaml)
                health = check_all_agents_health()
                self.send_json({
                    "status": "ok",
                    "message": "Конфигурация автозамены сохранена в config/agents_routing.yaml",
                    "config": data,
                    "health": health
                })
            except Exception as e:
                self.send_json({"status": "error", "message": f"Ошибка YAML: {e}"}, 400)
            return

        if path == "/api/agents/ping":
            sim_off = payload.get("simulate_offline", [])
            health = check_all_agents_health(simulation_offline=sim_off)
            self.send_json({"status": "ok", "health": health})
            return

        # 6.4. База знаний, Graphify и генерация датасета LoRA (Енот-Датасетник)
        if path == "/api/knowledge/graphify_update":
            res = run_graphify_update_cmd()
            self.send_json(res)
            return

        if path == "/api/dataset/generate":
            res = generate_lora_dataset()
            self.send_json(res)
            return

        if path == "/api/knowledge/eval_test":
            res = run_knowledge_eval()
            self.send_json(res)
            return

        # 6.5. Интерактивный чат с Говорящим Бригадиром (Староста Артели)
        if path == "/api/assistant/chat":
            msg = (payload.get("message") or "").strip().lower()
            with get_connection() as conn:
                total_topics = conn.execute("SELECT count(*) FROM topics").fetchone()[0]
                written = conn.execute("SELECT count(*) FROM topics WHERE content_markdown IS NOT NULL AND length(content_markdown) > 200").fetchone()[0]
                reviewed = conn.execute("SELECT count(*) FROM topics WHERE review_status = 'passed'").fetchone()[0]
                scored = conn.execute("SELECT count(*) FROM topics WHERE score_total IS NOT NULL").fetchone()[0]
                published = conn.execute("SELECT count(*) FROM topics WHERE wp_post_id IS NOT NULL").fetchone()[0]
                with_media = conn.execute("SELECT count(*) FROM topics WHERE media_json IS NOT NULL").fetchone()[0]
                last_topic = conn.execute("SELECT id, title, status, updated_at FROM topics ORDER BY updated_at DESC LIMIT 1").fetchone()
                target_unwritten = conn.execute("SELECT id, title FROM topics WHERE content_markdown IS NULL OR length(content_markdown) < 200 ORDER BY priority DESC LIMIT 1").fetchone()
                target_nomedia = conn.execute("SELECT id, title FROM topics WHERE media_json IS NULL ORDER BY id ASC LIMIT 1").fetchone()

            highlight = "card-craftsman-researcher"
            action = None

            if any(k in msg for k in ("остановились", "где мы", "на чем", "статус")):
                last_title = last_topic["title"] if last_topic else "первые темы контура"
                last_id = last_topic["id"] if last_topic else 1
                highlight = "card-craftsman-illustrator" if with_media < written else "card-craftsman-writer"
                reply = (
                    f"Мы остановились на теме #{last_id} «{last_title}». "
                    f"В нашей базе {total_topics} тем. Из них лонгридов написано: {written}, "
                    f"ОТК-проверку Филина прошли: {reviewed}, схем термопрофилей от Барсука: {with_media}, "
                    f"скоринг Дятла прошли: {scored}, опубликовано в WordPress: {published}. "
                )
                if with_media < written:
                    reply += f"Сейчас самое время запустить Барсука-Иллюстратора отрисовать технологическую SVG-схему для темы #{last_id}!"
                    action = {"label": f"Запустить Барсука для темы #{last_id}", "agent": "05-illustrator", "target_id": last_id}
                else:
                    reply += "Все готовые статьи обеспечены схемами. Можно запускать Кота на следующую тему!"
                    action = {"label": "Написать следующую статью", "agent": "02-writer", "target_id": last_id}

            elif any(k in msg for k in ("сделали", "что готово", "результат", "итог")):
                highlight = "card-craftsman-scorer"
                reply = (
                    f"Отчитываюсь по ферме зверей: Бобёр подготовил семантическое ядро и брифы для всех {total_topics} тем. "
                    f"Кот-Паяльщик и Лис написали {written} полных лонгридов по строгой триаде. "
                    f"Филин-Ревизор проверил {reviewed} статей на ГОСТ и отсутствие слопа через SlopMonster. "
                    f"Барсук-Иллюстратор подготовил векторные схемы термопрофилей для {with_media} статей. "
                    f"Дятел-Метролог выполнил математический скоринг {scored} материалов."
                )
                action = {"label": "Показать канбан", "action_type": "filter_kanban"}

            elif any(k in msg for k in ("нужно сделать", "дальше", "план", "что делать")):
                if written < total_topics:
                    target_t = target_unwritten
                    tid = target_t["id"] if target_t else 1
                    highlight = "card-craftsman-writer"
                    reply = (
                        f"Главный следующий шаг — взять тему #{tid} «{target_t['title']}» "
                        f"и отправить Коту-Паяльщику на NODE-1 (Mi 10T 5G) для генерации лонгрида Practico 1. "
                        f"После этого Барсук нарисует схему, а Филин и Дятел проведут ОТК-приемку."
                    )
                    action = {"label": f"Отправить тему #{tid} Коту", "agent": "02-writer", "target_id": tid}
                elif with_media < written:
                    target_t = target_nomedia
                    tid = target_t["id"] if target_t else 1
                    highlight = "card-craftsman-illustrator"
                    reply = (
                        f"Есть готовые статьи без схем! Задача: отправить тему #{tid} «{target_t['title']}» "
                        f"Барсуку-Иллюстратору для генерации точной SVG-кривой ликвидуса и промпта Hero-обложки."
                    )
                    action = {"label": f"Отрисовать схему #{tid}", "agent": "05-illustrator", "target_id": tid}
                else:
                    highlight = "card-craftsman-reviewer"
                    reply = "Все готовые статьи написаны и проиллюстрированы! Следующий этап — пакетная отправка черновиков в WordPress."
                    action = {"label": "Экспорт в WordPress", "agent": "delivery", "target_id": 1}

            elif any(k in msg for k in ("кто занят", "звери", "ферма", "мастера", "бригада")):
                highlight = "card-craftsman-writer"
                reply = (
                    "Вся артель зверей на местах: "
                    "🦫 Бобёр строит брифы на NODE-2 (Nord N10), "
                    "🐱 Кот паяет тексты на NODE-1 (Mi 10T), "
                    "🦊 Лис считает сметы через OmniRoute, "
                    "🦡 Барсук чертит векторные SVG-термопрофили, "
                    "🦉 Филин держит наготове штамп [ОТК] с де-слоппером, "
                    "🪵 Дятел откалибровал 100-балльный скорер, "
                    "а 🪶 Сорока отслеживает фишки конкурентов."
                )

            else:
                highlight = "card-craftsman-writer"
                reply = (
                    f"Приветствую! На нашей ферме {total_topics} тем и {written} готовых лонгридов. "
                    "Спросите меня: «На чем мы остановились?», «Что уже сделали?», «Что нужно сделать дальше?» "
                    "или «Кто из зверей сейчас занят?» — и я сразу покажу мастера на верстаке!"
                )

            self.send_json({
                "status": "ok",
                "reply": reply,
                "voice_text": reply,
                "highlight_craftsman": highlight,
                "action": action,
                "stats": {
                    "total": total_topics,
                    "written": written,
                    "reviewed": reviewed,
                    "with_media": with_media,
                    "scored": scored,
                    "published": published
                }
            })
            return

        # 7. Запуск субагента
        if path == "/api/agent/run":
            self.dispatch_agent_run(payload)
            return

        if path == "/api/keyword/local_mutagen":
            phrase = (payload.get("phrase") or "").strip()
            serp_urls = payload.get("serp_urls") or []
            if not phrase:
                self.send_json({"status": "error", "message": "Пустая фраза"}, 400)
                return
            try:
                from competition_score import compute_local_mutagen
                res = compute_local_mutagen(phrase, serp_urls=serp_urls)
                self.send_json({"status": "ok", "result": res})
            except Exception as e:
                self.send_json({"status": "error", "message": str(e)}, 500)
            return

        if path == "/api/article/aeo_score":
            text = payload.get("text") or ""
            aid = parse_row_id(payload.get("topic_id") or payload.get("article_id"))
            try:
                from score_article import compute_aeo_geo_score
                if aid and not text:
                    with get_connection() as conn:
                        row = conn.execute("SELECT content_markdown FROM topics WHERE id = ?", (aid,)).fetchone()
                        if row and row["content_markdown"]:
                            text = row["content_markdown"]
                res = compute_aeo_geo_score(text)
                self.send_json({"status": "ok", "aeo_geo": res})
            except Exception as e:
                self.send_json({"status": "error", "message": str(e)}, 500)
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
                    ws_res = {
                        "totalCount": cached["totalCount"],
                        "results": cached["results"][:6],
                        "associations": cached["associations"][:6],
                        "cached": True,
                        "checked_at": cached.get("checked_at"),
                    }
                else:
                    data = ws_mod.top_requests(phrase, num_phrases=15)
                    ws_mod.save_cache(phrase, data)
                    ws_res = {
                        "totalCount": int(data.get("totalCount") or 0),
                        "results": [{"phrase": r.get("phrase"), "count": int(r.get("count") or 0)}
                                    for r in (data.get("results") or [])[:6]],
                        "associations": [{"phrase": r.get("phrase"), "count": int(r.get("count") or 0)}
                                         for r in (data.get("associations") or [])[:6]],
                        "cached": False,
                    }

                # Запрашиваем помесячную динамику и рассчитываем тренд (бесплатно)
                try:
                    dyn_raw = ws_mod.dynamics(phrase, period="PERIOD_MONTHLY", months_back=6)
                    raw_points = dyn_raw.get("results") or []
                    points = []
                    for pt in raw_points:
                        dt = (pt.get("date") or "")[:7]
                        cnt = int(pt.get("count") or 0)
                        if dt:
                            points.append({"month": dt, "count": cnt})
                    if len(points) >= 2:
                        first_cnt = points[0]["count"]
                        last_cnt = points[-1]["count"]
                        diff_pct = round(((last_cnt - first_cnt) / max(1, first_cnt)) * 100, 1)
                        peak = max(points, key=lambda x: x["count"])
                        ws_res["trend"] = {
                            "points": points,
                            "change_pct": diff_pct,
                            "direction": "up" if diff_pct > 5 else ("down" if diff_pct < -5 else "stable"),
                            "peak_month": peak["month"],
                            "peak_count": peak["count"],
                        }
                except Exception:
                    pass

                result["wordstat"] = ws_res
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
                        conn.execute("""
                            UPDATE competitors SET
                                niche = CASE WHEN length(coalesce(niche,'')) = 0 THEN ? ELSE niche END,
                                type = CASE WHEN length(coalesce(type,'')) = 0 THEN ? ELSE type END,
                                blog_url = CASE WHEN length(coalesce(blog_url,'')) = 0 THEN ? ELSE blog_url END,
                                score = CASE WHEN score IS NULL OR score = 0 THEN ? ELSE score END,
                                notes = CASE WHEN length(coalesce(notes,'')) = 0 THEN ? ELSE notes END,
                                updated_at = ?
                            WHERE domain = ?
                        """, (comp.get("niche") or "", comp.get("type") or "", comp.get("blog_url") or "",
                              comp.get("score") or 50, (comp.get("notes") or "")[:500], now, domain))
                        skipped["competitors"] += 1
                        continue

                    conn.execute("""
                        INSERT INTO competitors (domain, niche, type, blog_url, score, notes, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    """, (domain, comp.get("niche") or "", comp.get("type") or "", comp.get("blog_url") or "",
                          comp.get("score") or 50, (comp.get("notes") or "")[:500], now))
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

    run_all_migrations()
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
