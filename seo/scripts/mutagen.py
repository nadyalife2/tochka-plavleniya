"""Клиент официального JSON API Мутагена (конкуренция + частотность Вордстат).

Документация: http://mutagen.ru/index.php?p=api

Что берём у Мутагена (то, чего нет бесплатно):
    strong      — измеренная конкуренция по фразе
    wordstat    — частотность в кавычках (спрос)
    tails       — показы по хвостам (объём длинного хвоста)
    direct      — ставки Директа: спецразмещение / 1 место / гарантия
    vital       — есть ли сайт-монополист в выдаче

Безопасность:
    * ключ читается ТОЛЬКО из ~/.dsh/.credentials.yaml (вне репозитория);
    * ключ НИКОГДА не печатается и не попадает в логи/ошибки;
    * API Мутагена работает по http, поэтому запросы идут только с локальной машины.

Расход денег:
    * каждый вызов check_key списывает средства с баланса;
    * скрипт показывает баланс до/после и фактическую стоимость проверки;
    * --max-cost останавливает пакетный прогон при превышении лимита.

Использование:
    python seo/scripts/mutagen.py --balance
    python seo/scripts/mutagen.py --check "пос-61 или пос-40 разница"
    python seo/scripts/mutagen.py --batch keys.txt --max-cost 5
    python seo/scripts/mutagen.py --table
"""
from __future__ import annotations

import argparse
import json
import os
import re
import sys
import time
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

API_BASE = "http://api.mutagen.ru/json"
CACHE_DAYS = 30
CREDENTIALS = Path(os.path.expanduser("~")) / ".dsh" / ".credentials.yaml"


def _read_key() -> str:
    """Читает ключ через единый загрузчик (config/api-keys.local.yaml)."""
    from keys import get_key
    value = get_key("MUTAGEN_API_KEY")
    if not value:
        raise SystemExit(
            "❌ Не найден MUTAGEN_API_KEY. Откройте config/api-keys.local.yaml "
            "и впишите ключ Мутагена справа от MUTAGEN_API_KEY:"
        )
    return value


def _call(method: str, params: dict | None = None, timeout: int = 90) -> dict:
    """HTTP-вызов метода API. Ключ подставляется в URL, но никогда не выводится."""
    key = _read_key()
    url = f"{API_BASE}/{key}/{method}/"
    safe_url = f"{API_BASE}/***/{method}/"

    if params:
        body = json.dumps(params, ensure_ascii=False).encode("utf-8")
        req = urllib.request.Request(url, data=body, headers={"Content-Type": "application/json"})
    else:
        req = urllib.request.Request(url)

    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            raw = resp.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        raise RuntimeError(f"{safe_url} → HTTP {e.code}") from None
    except Exception as e:  # noqa: BLE001
        raise RuntimeError(f"{safe_url} → {type(e).__name__}: {str(e)[:120]}") from None

    try:
        return json.loads(raw)
    except Exception:
        raise RuntimeError(f"{safe_url} → ответ не JSON: {raw[:200]}") from None


# ------------------------------------------------------------------ API ---


def get_balance() -> float:
    data = _call("mutagen.balance")
    return float(data.get("balance", 0))


def check_key_new(keyword: str) -> dict:
    return _call("mutagen.check_key.new", {"key": keyword})


def check_key_get(task_id) -> dict:
    return _call("mutagen.check_key.get", {"task_id": task_id})


def check_keyword(keyword: str, poll_timeout: int = 90) -> dict:
    """Ставит фразу на проверку конкуренции и ждёт готовый результат."""
    task = check_key_new(keyword)
    if task.get("status") == "completed":
        return task
    task_id = task.get("task_id")
    if not task_id:
        raise RuntimeError(f"не удалось создать задание: {task}")

    started = time.monotonic()
    while time.monotonic() - started < poll_timeout:
        time.sleep(3)
        res = check_key_get(task_id)
        if res.get("status") == "completed":
            return res
        if res.get("status") == "rejected":
            raise RuntimeError(f"задание отклонено сервисом: {res}")
    raise RuntimeError(f"задание не завершилось за {poll_timeout} секунд (task_id={task_id})")


# --------------------------------------------------------------- SQLite ---


def init_cache():
    with get_connection() as conn:
        conn.execute("""
            CREATE TABLE IF NOT EXISTS keyword_metrics (
                keyword      TEXT PRIMARY KEY,
                strong       INTEGER,
                wordstat     INTEGER,
                tails        INTEGER,
                bid_spec     REAL,
                bid_first    REAL,
                bid_garant   REAL,
                vital        INTEGER,
                vital_site   TEXT,
                raw_json     TEXT,
                checked_at   TEXT NOT NULL
            )
        """)
        # История: каждое измерение сохраняется отдельной строкой и НЕ перезаписывается.
        conn.execute("""
            CREATE TABLE IF NOT EXISTS keyword_metrics_history (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                keyword    TEXT NOT NULL,
                strong     INTEGER,
                wordstat   INTEGER,
                tails      INTEGER,
                bid_spec   REAL,
                bid_first  REAL,
                bid_garant REAL,
                vital      INTEGER,
                vital_site TEXT,
                checked_at TEXT NOT NULL
            )
        """)
        conn.execute("CREATE INDEX IF NOT EXISTS idx_kmh_keyword ON keyword_metrics_history(keyword, checked_at)")
        conn.commit()


def save_metrics(keyword: str, res: dict):
    init_cache()
    direct = res.get("direct") or {}
    now_str = datetime.now().isoformat(timespec="seconds")
    with get_connection() as conn:
        conn.execute("""
            INSERT INTO keyword_metrics
                (keyword, strong, wordstat, tails, bid_spec, bid_first, bid_garant,
                 vital, vital_site, raw_json, checked_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT(keyword) DO UPDATE SET
                strong = excluded.strong,
                wordstat = excluded.wordstat,
                tails = excluded.tails,
                bid_spec = excluded.bid_spec,
                bid_first = excluded.bid_first,
                bid_garant = excluded.bid_garant,
                vital = excluded.vital,
                vital_site = excluded.vital_site,
                raw_json = excluded.raw_json,
                checked_at = excluded.checked_at
        """, (
            keyword,
            res.get("strong"),
            res.get("wordstat"),
            res.get("tails"),
            direct.get("spec"),
            direct.get("first"),
            direct.get("garant"),
            1 if res.get("vital") else 0,
            res.get("vital_site") or "",
            json.dumps(res, ensure_ascii=False),
            now_str,
        ))
        # Дублируем в историю (append-only): прошлые замеры не теряются
        conn.execute("""
            INSERT INTO keyword_metrics_history
                (keyword, strong, wordstat, tails, bid_spec, bid_first, bid_garant,
                 vital, vital_site, checked_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        """, (
            keyword,
            res.get("strong"),
            res.get("wordstat"),
            res.get("tails"),
            direct.get("spec"),
            direct.get("first"),
            direct.get("garant"),
            1 if res.get("vital") else 0,
            res.get("vital_site") or "",
            now_str,
        ))
        conn.commit()


def cached_metrics(keyword: str) -> dict | None:
    """Кэш на 30 дней: повторная проверка той же фразы не тратит деньги."""
    init_cache()
    with get_connection() as conn:
        row = conn.execute(
            "SELECT * FROM keyword_metrics WHERE keyword = ?", (keyword,)
        ).fetchone()
    if not row:
        return None
    try:
        checked = datetime.fromisoformat(row["checked_at"])
    except Exception:
        return None
    if datetime.now() - checked > timedelta(days=CACHE_DAYS):
        return None
    return dict(row)


# ---------------------------------------------------------------- вывод ---


def print_metrics(keyword: str, res: dict, cost: float | None = None):
    direct = res.get("direct") or {}
    wordstat = res.get("wordstat") or 0
    strong = res.get("strong")
    tails = res.get("tails") or 0
    vital = res.get("vital")
    vital_site = res.get("vital_site") or ""

    print("=" * 66)
    print(f"ФРАЗА: «{keyword}»")
    print("=" * 66)
    print(f"  Спрос (wordstat, в кавычках): {wordstat:,} показов/мес".replace(",", " "))
    print(f"  Хвосты (tails):              {tails:,} показов/мес".replace(",", " "))
    print(f"  Конкуренция (strong):         {strong if strong is not None else '—'}")
    print(f"  Витальный сайт:               {vital_site if vital else 'нет (путь открыт)'}")
    if direct:
        print("  Ставки Директа, ₽:")
        print(f"      спецразмещение: {direct.get('spec')}")
        print(f"      1-е место:      {direct.get('first')}")
        print(f"      гарантия:       {direct.get('garant')}")

    # трактовка
    verdict = []
    if strong is not None:
        if strong <= 10:
            verdict.append("конкуренция низкая — можно брать в работу")
        elif strong <= 18:
            verdict.append("конкуренция средняя — нужен сильный материал")
        else:
            verdict.append("конкуренция высокая — только с серьёзным преимуществом")
    if wordstat and wordstat < 100:
        verdict.append("спрос очень низкий (меньше 100/мес)")
    elif wordstat >= 1000:
        verdict.append("спрос хороший (1000+)")
    if vital:
        verdict.append(f"в топе монополист {vital_site}")

    if verdict:
        print("  ВЕРДИКТ: " + "; ".join(verdict))
    if cost is not None:
        print(f"  Стоимость проверки: {cost:.4f} ₽")
    print("=" * 66)


def main() -> int:
    ap = argparse.ArgumentParser(description="Мутаген: конкуренция и частотность фраз")
    ap.add_argument("--balance", action="store_true", help="показать баланс (бесплатно)")
    ap.add_argument("--check", help="проверить одну фразу")
    ap.add_argument("--batch", help="файл со списком фраз (по одной в строке)")
    ap.add_argument("--max-cost", type=float, default=0.0, help="лимит расходов, ₽ (0 = без лимита)")
    ap.add_argument("--no-cache", action="store_true", help="игнорировать кэш (всегда свежая проверка)")
    ap.add_argument("--table", action="store_true", help="показать сохранённые метрики")
    ap.add_argument("--json", action="store_true", help="вывод в JSON")
    ap.add_argument("--yes", action="store_true", help="подтвердить пакетный прогон")
    args = ap.parse_args()

    if args.balance:
        bal = get_balance()
        print(f"Баланс Мутагена: {bal:.2f} ₽")
        return 0

    if args.check:
        kw = args.check.strip()
        if not args.no_cache:
            cached = cached_metrics(kw)
            if cached:
                res = json.loads(cached["raw_json"])
                print(f"(кэш от {cached['checked_at']} — деньги не потрачены)")
                if args.json:
                    print(json.dumps(res, ensure_ascii=False, indent=2))
                else:
                    print_metrics(kw, res)
                return 0

        balance_before = get_balance()
        res = check_keyword(kw)
        balance_after = get_balance()
        cost = max(0.0, balance_before - balance_after)
        save_metrics(kw, res)
        if args.json:
            print(json.dumps(res, ensure_ascii=False, indent=2))
        else:
            print_metrics(kw, res, cost=cost)
            print(f"  Баланс: {balance_before:.2f} ₽ → {balance_after:.2f} ₽")
        return 0

    if args.batch:
        path = Path(args.batch)
        if not path.exists():
            raise SystemExit(f"❌ файл не найден: {path}")
        keys = [l.strip() for l in path.read_text(encoding="utf-8-sig").splitlines() if l.strip() and not l.startswith("#")]
        # Убираем BOM и невидимые символы, из-за которых Мутаген отклоняет ключ
        keys = [re.sub(r"[\ufeff\u200b\u200e\u200f]", "", k).strip() for k in keys]
        keys = [k for k in keys if k]
        fresh = [k for k in keys if args.no_cache or not cached_metrics(k)]
        print(f"Фраз в файле: {len(keys)} | требует проверки: {len(fresh)} | из кэша: {len(keys) - len(fresh)}")

        if len(fresh) > 5 and not args.yes:
            raise SystemExit("❌ Больше 5 платных проверок. Добавьте --yes для подтверждения.")

        start_balance = get_balance()
        print(f"Баланс до: {start_balance:.2f} ₽")
        spent = 0.0
        results = []

        for i, kw in enumerate(fresh, 1):
            if args.max_cost and spent >= args.max_cost:
                print(f"⏹ Остановка: достигнут лимит --max-cost {args.max_cost} ₽ (потрачено {spent:.4f} ₽)")
                break
            before = get_balance()
            try:
                res = check_keyword(kw)
            except Exception as e:  # noqa: BLE001
                print(f"  [{i}/{len(fresh)}] «{kw}» — ошибка: {e}")
                continue
            after = get_balance()
            cost = max(0.0, before - after)
            spent += cost
            save_metrics(kw, res)
            results.append(res)
            print(f"  [{i}/{len(fresh)}] «{kw}»: strong={res.get('strong')} wordstat={res.get('wordstat')} "
                  f"vital={bool(res.get('vital'))} · {cost:.4f} ₽")

        print(f"\nИтого потрачено: {spent:.4f} ₽ | баланс: {get_balance():.2f} ₽")

        init_cache()
        with get_connection() as conn:
            rows = conn.execute(
                "SELECT keyword, strong, wordstat, vital, vital_site FROM keyword_metrics "
                "ORDER BY wordstat DESC"
            ).fetchall()
        print(f"\n{'ФРАЗА':<38} {'STRONG':>7} {'WORDSTAT':>9} {'VITAL':>6}")
        print("-" * 66)
        for r in rows:
            marks = "да" if r["vital"] else "—"
            print(f"{r['keyword'][:36]:<38} {str(r['strong']):>7} {str(r['wordstat']):>9} {marks:>6}")
        return 0

    if args.table:
        init_cache()
        with get_connection() as conn:
            rows = conn.execute(
                "SELECT keyword, strong, wordstat, tails, vital_site, checked_at FROM keyword_metrics ORDER BY wordstat DESC"
            ).fetchall()
        if not rows:
            print("Метрик пока нет. Запустите --check или --batch.")
            return 0
        print(f"{'ФРАЗА':<38} {'STRONG':>7} {'WORDSTAT':>9} {'VITAL':<20} {'ПРОВЕРЕНО'}")
        print("-" * 92)
        for r in rows:
            print(f"{r['keyword'][:36]:<38} {str(r['strong']):>7} {str(r['wordstat']):>9} "
                  f"{(r['vital_site'] or '—')[:18]:<20} {r['checked_at']}")
        return 0

    ap.print_help()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
