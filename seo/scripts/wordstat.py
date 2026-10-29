"""Бесплатный Wordstat API через Яндекс AI Studio (Search API v2).

Что даёт (бесплатно, по ключу AI Studio):
    topRequests   — общая частотность фразы + топ похожих запросов + ассоциации
    dynamics      — динамика частотности по месяцам/неделям/дням
    regions       — распределение по регионам с индексом интереса
    getRegionsTree— справочник регионов (кэшируется локально)

Чего НЕ даёт (и поэтому дополняется Мутагеном):
    * операторы Вордстата (`!слово`, `+слово`, `[слово]`, кавычки) не поддерживаются,
      поэтому точная фразовая частотность недоступна — только широкая;
    * нет измерения конкуренции и ставок Директа.

Ключи берутся из config/api-keys.local.yaml (см. keys.py):
    YANDEX_AI_API_KEY, YANDEX_FOLDER_ID

Использование:
    python seo/scripts/wordstat.py --top "пайка"
    python seo/scripts/wordstat.py --batch keys.txt
    python seo/scripts/wordstat.py --dynamics "пайка"
    python seo/scripts/wordstat.py --table
"""
from __future__ import annotations

import argparse
import json
import re
import sys
import urllib.error
import urllib.request
from datetime import datetime, timedelta
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SCRIPTS = ROOT / "seo" / "scripts"
sys.path.insert(0, str(SCRIPTS))

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

from db import get_connection
from keys import require_key

API_BASE = "https://searchapi.api.cloud.yandex.net/v2/wordstat"
CACHE_DAYS = 14


def _post(method: str, body: dict, timeout: int = 40) -> dict:
    key = require_key("YANDEX_AI_API_KEY")
    folder = require_key("YANDEX_FOLDER_ID")
    payload = dict(body)
    payload["folderId"] = folder

    req = urllib.request.Request(
        f"{API_BASE}/{method}",
        data=json.dumps(payload, ensure_ascii=False).encode("utf-8"),
        headers={"Authorization": f"Api-Key {key}", "Content-Type": "application/json"},
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            return json.loads(resp.read().decode("utf-8"))
    except urllib.error.HTTPError as e:
        detail = e.read().decode("utf-8", "replace")[:400]
        raise RuntimeError(f"Wordstat {method}: HTTP {e.code} — {detail}") from None
    except Exception as e:  # noqa: BLE001
        raise RuntimeError(f"Wordstat {method}: {type(e).__name__}: {str(e)[:150]}") from None


# ------------------------------------------------------------------ API ---


def sanitize_phrase(phrase: str) -> str:
    """Готовит фразу для Wordstat API.

    API отвечает HTTP 400 «Invalid query», если в фразе есть двоеточие,
    точка с запятой, кавычки-ёлочки и другие знаки препинания.
    Заголовок статьи как запрос не годится — оставляем только слова.
    """
    cleaned = re.sub(r"[«»\"'`()\[\]{}<>:;,!?—–|/\\]", " ", phrase or "")
    cleaned = re.sub(r"\s+", " ", cleaned).strip(" .-")
    return cleaned[:400]


STOPWORDS = {
    "как", "что", "чем", "для", "или", "при", "без", "над", "под", "про", "это",
    "когда", "нужно", "нужен", "нужна", "лучше", "какой", "какая", "какие", "почему",
    "можно", "ли", "в", "на", "и", "с", "по", "из", "от", "до", "не", "а", "же",
    "своими", "руками", "правильно", "знать", "выбрать", "делать",
}


def shorten_phrase(phrase: str, max_words: int = 3) -> str:
    """Сокращает длинный заголовок до короткого запроса (2–3 значимых слова)."""
    words = [w for w in sanitize_phrase(phrase).lower().split() if w not in STOPWORDS and len(w) > 2]
    return " ".join(words[:max_words])


def top_requests(phrase: str, num_phrases: int = 30, regions: list[str] | None = None) -> dict:
    query = sanitize_phrase(phrase) or phrase
    body: dict = {"phrase": query, "numPhrases": num_phrases}
    if regions:
        body["regions"] = regions
    data = _post("topRequests", body)

    # Длинный заголовок статьи часто не является поисковым запросом:
    # если спроса нет, пробуем всё более короткие версии (3 слова → 2 слова).
    if not data.get("totalCount"):
        for n in (3, 2):
            short = shorten_phrase(phrase, max_words=n)
            if not short or short == query.lower():
                continue
            try:
                data2 = _post("topRequests", {"phrase": short, "numPhrases": num_phrases})
                if data2.get("totalCount"):
                    data2["_query_used"] = short
                    data2["_shortened_from"] = phrase
                    return data2
            except Exception:
                continue

    data["_query_used"] = query
    return data


def dynamics(phrase: str, period: str = "PERIOD_MONTHLY", months_back: int = 6) -> dict:
    today = datetime.now()
    to_date = today.replace(day=1) - timedelta(days=1)  # последний день прошлого месяца
    from_date = (to_date - timedelta(days=months_back * 31)).replace(day=1)
    return _post("dynamics", {
        "phrase": phrase,
        "period": period,
        "fromDate": from_date.strftime("%Y-%m-%dT00:00:00Z"),
        "toDate": to_date.strftime("%Y-%m-%dT23:59:59Z"),
    })


# --------------------------------------------------------------- SQLite ---


def init_cache():
    with get_connection() as conn:
        conn.execute("""
            CREATE TABLE IF NOT EXISTS wordstat_metrics (
                phrase        TEXT PRIMARY KEY,
                total_count   INTEGER,
                top_json      TEXT,
                assoc_json    TEXT,
                checked_at    TEXT NOT NULL
            )
        """)
        # История: каждое измерение сохраняется отдельно, прошлые данные не затираются
        conn.execute("""
            CREATE TABLE IF NOT EXISTS wordstat_history (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                phrase      TEXT NOT NULL,
                total_count INTEGER,
                checked_at  TEXT NOT NULL
            )
        """)
        conn.execute("CREATE INDEX IF NOT EXISTS idx_wsh_phrase ON wordstat_history(phrase, checked_at)")
        conn.commit()


def save_cache(phrase: str, data: dict):
    init_cache()

    def to_int(v):
        try:
            return int(v)
        except Exception:
            return None

    results = data.get("results") or []
    assoc = data.get("associations") or []
    serializable = {
        "results": [{"phrase": r.get("phrase"), "count": to_int(r.get("count"))} for r in results],
        "associations": [{"phrase": r.get("phrase"), "count": to_int(r.get("count"))} for r in assoc],
    }
    now_str = datetime.now().isoformat(timespec="seconds")
    with get_connection() as conn:
        conn.execute("""
            INSERT INTO wordstat_metrics (phrase, total_count, top_json, assoc_json, checked_at)
            VALUES (?, ?, ?, ?, ?)
            ON CONFLICT(phrase) DO UPDATE SET
                total_count = excluded.total_count,
                top_json = excluded.top_json,
                assoc_json = excluded.assoc_json,
                checked_at = excluded.checked_at
        """, (
            phrase,
            to_int(data.get("totalCount")),
            json.dumps(serializable["results"], ensure_ascii=False),
            json.dumps(serializable["associations"], ensure_ascii=False),
            now_str,
        ))
        # История: прошлые значения сохраняются навсегда
        conn.execute(
            "INSERT INTO wordstat_history (phrase, total_count, checked_at) VALUES (?, ?, ?)",
            (phrase, to_int(data.get("totalCount")), now_str),
        )
        conn.commit()


def get_cached(phrase: str, max_age_days: int = CACHE_DAYS) -> dict | None:
    init_cache()
    with get_connection() as conn:
        row = conn.execute("SELECT * FROM wordstat_metrics WHERE phrase = ?", (phrase,)).fetchone()
    if not row:
        return None
    try:
        checked = datetime.fromisoformat(row["checked_at"])
    except Exception:
        return None
    if datetime.now() - checked > timedelta(days=max_age_days):
        return None
    return {
        "totalCount": row["total_count"],
        "results": json.loads(row["top_json"] or "[]"),
        "associations": json.loads(row["assoc_json"] or "[]"),
        "checked_at": row["checked_at"],
        "cached": True,
    }


# ---------------------------------------------------------------- вывод ---


def _relevant(phrase: str, candidate: str) -> bool:
    """Отсекает мусорные ассоциации, не связанные с фразой по словам."""
    def stems(text: str) -> set[str]:
        return {w[:4] for w in re.findall(r"[а-яёa-z0-9]{4,}", text.lower())}
    a, b = stems(phrase), stems(candidate)
    return bool(a & b)


def print_top(phrase: str, data: dict):
    total = data.get("totalCount")
    results = data.get("results") or []
    assoc = [a for a in (data.get("associations") or []) if _relevant(phrase, a.get("phrase", ""))]
    if data.get("cached"):
        print(f"(кэш от {data.get('checked_at')} — бесплатно, запрос не выполнялся)")

    print("=" * 66)
    print(f"ВОРДСТАТ (бесплатно): «{phrase}»")
    print("=" * 66)
    print(f"  Широкая частотность: {int(total):,} показов/мес".replace(",", " ") if total else "  Широкая частотность: —")
    if results:
        print("\n  ТОП похожих запросов:")
        for r in results[:10]:
            print(f"     {int(r['count']):>9,}  {r['phrase']}".replace(",", " "))
    if assoc:
        print("\n  АССОЦИАЦИИ (семантически близкое):")
        for r in assoc[:8]:
            print(f"     {int(r['count']):>9,}  {r['phrase']}".replace(",", " "))
    print("=" * 66)


def main() -> int:
    ap = argparse.ArgumentParser(description="Бесплатный Wordstat через Яндекс AI Studio")
    ap.add_argument("--top", help="топ запросов и ассоциации по фразе")
    ap.add_argument("--dynamics", help="динамика частотности по месяцам")
    ap.add_argument("--batch", help="файл со списком фраз")
    ap.add_argument("--table", action="store_true", help="показать сохранённые результаты")
    ap.add_argument("--refresh", action="store_true", help="игнорировать кэш")
    ap.add_argument("--json", action="store_true")
    args = ap.parse_args()

    if args.top:
        phrase = args.top.strip()
        if not args.refresh:
            cached = get_cached(phrase)
            if cached:
                if args.json:
                    print(json.dumps(cached, ensure_ascii=False, indent=2))
                else:
                    print_top(phrase, cached)
                return 0
        data = top_requests(phrase)
        save_cache(phrase, data)
        if args.json:
            print(json.dumps(data, ensure_ascii=False, indent=2))
        else:
            print_top(phrase, data)
        return 0

    if args.dynamics:
        data = dynamics(args.dynamics.strip())
        if args.json:
            print(json.dumps(data, ensure_ascii=False, indent=2))
            return 0
        print(f"Динамика: «{args.dynamics}»")
        for r in data.get("results") or []:
            count = int(r["count"]) if r.get("count") else 0
            print(f"  {str(r.get('date'))[:10]}  {count:,}".replace(",", " "))
        return 0

    if args.batch:
        path = Path(args.batch)
        if not path.exists():
            raise SystemExit(f"❌ файл не найден: {path}")
        phrases = [l.strip() for l in path.read_text(encoding="utf-8-sig").splitlines()
                   if l.strip() and not l.startswith("#")]
        print(f"Фраз в файле: {len(phrases)}")
        import time as _time
        for phrase in phrases:
            if not args.refresh:
                cached = get_cached(phrase)
                if cached:
                    print(f"  [кэш] {phrase[:38]:<40} {cached['totalCount']}")
                    continue
            try:
                data = top_requests(phrase, num_phrases=10)
            except Exception as e:  # noqa: BLE001
                print(f"  [ошибка] {phrase[:38]:<40} {e}")
                _time.sleep(1.0)
                continue
            save_cache(phrase, data)
            print(f"  {phrase[:38]:<40} {data.get('totalCount')}")
            _time.sleep(0.2)  # вежливая пауза, чтобы не ловить 429
        return 0

    if args.table:
        init_cache()
        with get_connection() as conn:
            rows = conn.execute(
                "SELECT phrase, total_count, checked_at FROM wordstat_metrics ORDER BY total_count DESC"
            ).fetchall()
        if not rows:
            print("Данных пока нет. Запустите --top или --batch.")
            return 0
        print(f"{'ФРАЗА':<40} {'ШИРОКАЯ ЧАСТОТНОСТЬ':>20}  ПРОВЕРЕНО")
        print("-" * 80)
        for r in rows:
            print(f"{r['phrase'][:38]:<40} {str(r['total_count']):>20}  {r['checked_at']}")
        return 0

    ap.print_help()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
