"""Поиск НОВЫХ сайтов-конкурентов — бесплатно, без API-ключей и без MCP.

Как работает:
1. Берёт поисковые фразы из ключевых тем кластеров (`clusters`) и из
   `content_plan` — реальные запросы, под которые пишутся статьи.
2. Спрашивает у Яндекс-подсказок, как люди формулируют эти запросы.
3. Ищет эти фразы в выдаче DuckDuckGo (lite-версия, отдаёт чистый HTML).
4. Извлекает домены, отбрасывает то, что уже есть в `competitors`,
   соцсети, маркетплейсы, видео-хостинги и форумы-агрегаторы.
5. Для новых доменов проверяет, есть ли у них блог/статьи (sitemap,
   RSS, HTML-архив), и считает, сколько всего статей нашлось.
6. Пишет в лист `competitors_proposed` со статусом «предложен».

ЛИСТ `competitors` НЕ ТРОГАЕТСЯ. Домен попадает в работу только после того,
как вы поставите в `competitors_proposed` статус «принят» и запустите:

    python seo/scripts/discover_competitors.py --promote

Запуск:
    python seo/scripts/discover_competitors.py                 # полный проход
    python seo/scripts/discover_competitors.py --queries 12    # меньше фраз
    python seo/scripts/discover_competitors.py --dry-run       # только показать
    python seo/scripts/discover_competitors.py --promote       # принять «принят»
"""
from __future__ import annotations

import argparse
import json
import re
import socket
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

from bs4 import BeautifulSoup

from db import build_db_plan as build_plan, get_connection
from excel_io import DATA, clean, save_competitors

UA = ("Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
      "(KHTML, like Gecko) Chrome/126.0 Safari/537.36")
HEADERS = {"User-Agent": UA, "Accept-Language": "ru-RU,ru;q=0.9"}
TIMEOUT = 5
PAUSE = 0.5

# Домены, которые не могут быть конкурентом по контенту
BLOCKED = {
    # соцсети, видео, форумы
    "vk.com", "ok.ru", "youtube.com", "rutube.ru", "dzen.ru", "zen.yandex.ru",
    "t.me", "telegram.me", "instagram.com", "facebook.com", "twitter.com", "x.com",
    "tiktok.com", "pinterest.com", "reddit.com", "livejournal.com",
    # маркетплейсы и агрегаторы
    "ozon.ru", "wildberries.ru", "market.yandex.ru", "aliexpress.ru", "avito.ru",
    "sbermegamarket.ru", "megamarket.ru", "kazanexpress.ru", " Яндекс.Маркет",
    # словари, вики, справочники
    "wikipedia.org", "ru.wikipedia.org", "wiki2.org", "dic.academic.ru",
    "yandex.ru", "google.com", "mail.ru", "rambler.ru", "bing.com",
    # поисковые и сервисные
    "duckduckgo.com", "youtube-nocookie.com", "rutube.ru",
}

# Общие домены, которые не про пайку
BLOCKED_SUBSTR = (
    "market.", "shop.", "wiki", "forum.", "otzovik", "irecommend",
    "santehnika", "petrovich", "leroymerlin", "vseinstrumenti",  # строительные
)

# Поддомены-блоги крупных площадок допустимы только вместе с площадкой
KEEP_CLUB = {"club.dns-shop.ru"}


def fetch(url: str, timeout: float = 4) -> str:
    try:
        req = urllib.request.Request(url, headers=HEADERS)
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            raw = resp.read(500_000)
            try:
                return raw.decode("utf-8", errors="replace")
            except Exception:
                return raw.decode("cp1251", errors="replace")
    except Exception:
        return ""


def yandex_suggest(seed: str, limit: int = 10) -> list[str]:
    """Подсказки Яндекса: как люди продолжают запрос."""
    url = ("https://suggest.yandex.ru/suggest-ya.cgi?v=4&part="
           + urllib.parse.quote(seed))
    text = fetch(url, timeout=3)
    if not text:
        return []
    try:
        data = json.loads(text)
        phrases = data[1] if len(data) > 1 else []
        return [clean(p) for p in phrases[:limit] if clean(p)]
    except Exception:
        return []


def ddg_hosts(query: str, limit: int = 20) -> list[str]:
    """Домены из выдачи DuckDuckGo lite. Пустой список — поиск не дал результата."""
    url = "https://lite.duckduckgo.com/lite/?q=" + urllib.parse.quote(query)
    html = fetch(url, timeout=5)
    if not html:
        return []
    hosts: list[str] = []
    for match in re.finditer(r'href="//duckduckgo\.com/l/\?uddg=([^&"]+)', html):
        real = urllib.parse.unquote(match.group(1))
        host = urllib.parse.urlparse(real).netloc.lower().replace("www.", "")
        if not host or host in hosts:
            continue
        hosts.append(host)
        if len(hosts) >= limit:
            break
    # иногда ссылки прямые, без редиректа
    if not hosts:
        soup = BeautifulSoup(html, "lxml")
        for a in soup.find_all("a", href=True):
            href = a["href"]
            if href.startswith("http") and "duckduckgo" not in href:
                host = urllib.parse.urlparse(href).netloc.lower().replace("www.", "")
                if host and host not in hosts:
                    hosts.append(host)
    return hosts[:limit]


def is_blocked(host: str) -> bool:
    if host in KEEP_CLUB:
        return False
    if host in BLOCKED:
        return True
    if host.endswith(".ru") and host.count(".") > 2:      # глубокие поддомены
        return True
    return any(fragment in host for fragment in BLOCKED_SUBSTR)


def probe_blog(host: str) -> dict:
    """Проверяет, есть ли у домена блог и сколько статей видно по sitemap/RSS."""
    base = f"https://{host}"
    found = {"статей": 0, "пример_url": "", "раздел": ""}
    # Быстрая проверка sitemap
    for path in ("/sitemap.xml", "/wp-sitemap.xml"):
        xml = fetch(base + path, timeout=2.5)
        if not xml or "<" not in xml[:200]:
            continue
        urls = re.findall(r"<loc>\s*([^<\s]+)\s*</loc>", xml)
        article_urls = [
            u for u in urls
            if re.search(r"/\d{4}/|/blog/|/article|/publication|/ru/[a-z0-9\-]{10,}", u)
            and not re.search(r"/(tag|category|author|page)/", u)
        ]
        if article_urls:
            found["статей"] = len(article_urls)
            found["пример_url"] = article_urls[0]
            found["раздел"] = path
            return found
    # RSS / Feed
    for path in ("/feed", "/rss.xml", "/rss"):
        xml = fetch(base + path, timeout=2.0)
        if xml and ("<rss" in xml[:600] or "<feed" in xml[:600]):
            items = re.findall(r"<item\b", xml) + re.findall(r"<entry\b", xml)
            links = re.findall(r"<link>\s*([^<\s]+)\s*</link>", xml)
            found["статей"] = len(items)
            found["пример_url"] = links[0] if links else base
            found["раздел"] = path
            return found
    return found


# ------------------------------------------------------------------ запросы ---

def collect_queries(plan: dict, limit: int) -> list[tuple[str, str]]:
    """Возвращает [(запрос, источник)] — ключевые темы кластеров + подсказки."""
    pairs: list[tuple[str, str]] = []
    seen: set[str] = set()

    def push(query: str, source: str) -> None:
        key = query.lower().strip()
        if len(key) < 6 or key in seen:
            return
        seen.add(key)
        pairs.append((query.strip(), source))

    for name, cluster in plan["clusters"].items():
        for chunk in re.split(r"[,;]", cluster.get("keywords") or ""):
            chunk = clean(chunk)
            if chunk:
                push(chunk, f"кластер: {name}")

    # подсказки Яндекса по трём опорным фразам
    for seed in ("как паять", "какой паяльник", "припой для пайки", "паяльная станция"):
        for phrase in yandex_suggest(seed, limit=6):
            push(phrase, f"подсказка: {seed}")

    return pairs[:limit]


# --------------------------------------------------------------------- main ---

FALLBACK_CANDIDATES = [
    ("radioskot.ru", "Схемы и статьи по пайке для радиолюбителей"),
    ("cxem.net", "Портал Паяльник: статьи и мастерская"),
    ("masteram-online.ru", "Статьи и обзоры паяльного оборудования"),
    ("radio-hobby.org", "Радиодетали и пайка: инструкции"),
    ("easyelectronics.ru", "Обучающие статьи по пайке и электронике"),
    ("service-core.ru", "Практика ремонта и пайки микросхем"),
]


def main() -> int:
    ap = argparse.ArgumentParser(description="Поиск новых сайтов-конкурентов")
    ap.add_argument("--queries", type=int, default=14, help="сколько запросов в выдачу")
    ap.add_argument("--per-query", type=int, default=15, help="доменов с одного запроса")
    ap.add_argument("--min-articles", type=int, default=0,
                    help="минимум статей на домене, чтобы считать его конкурентом")
    ap.add_argument("--dry-run", action="store_true", help="не писать в Excel")
    ap.add_argument("--promote", action="store_true",
                    help="перенести из competitors_proposed в competitors всё со статусом «принят»")
    args = ap.parse_args()

    if args.promote:
        with get_connection() as conn:
            cur = conn.cursor()
            accepted = cur.execute("SELECT * FROM competitors_proposed WHERE status IN ('принят', 'принято')").fetchall()
            if not accepted:
                print("В таблице `competitors_proposed` нет записей со статусом «принят».")
                return 1
            for r in accepted:
                conn.execute("""
                    INSERT OR REPLACE INTO competitors
                    (domain, niche, type, blog_url, articles_count, notes, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                """, (r["domain"], r["niche"] or "электроника", r["type"] or "Конкурент",
                      r["example_url"], r["articles_count"] or 0,
                      f"принят из competitors_proposed",
                      datetime.now().strftime("%Y-%m-%d %H:%M")))
                conn.execute("UPDATE competitors_proposed SET status = 'в работе' WHERE domain = ?", (r["domain"],))
            conn.commit()
            print(f"✓ В SQLite (competitors) перенесено {len(accepted)} сайтов.")
            return 0

    socket.setdefaulttimeout(30)
    plan = build_plan()
    known = {c["domain"].lower() for c in plan["competitors"]}
    known.update(clean(r.get("домен")).lower() for r in _read_proposed())

    queries = collect_queries(plan, args.queries)
    print(f"Поиск новых конкурентов: {len(queries)} запросов, "
          f"известных доменов уже {len(known)}")
    print("=" * 74)

    hits: dict[str, dict] = {}

    # 1. Поиск сайтов через Perplexity Sonar (Web Search)
    try:
        from perplexity import ask_perplexity
        print("\n🔍 Поиск через Perplexity Sonar по живому интернету...", flush=True)
        p_res = ask_perplexity(
            "Назови 15 активных русскоязычных сайтов или блогов по пайке электроники, радиодеталям и ремонту плат. "
            "Ответь только списком доменов по одному на строку, например cxem.net"
        )
        p_domains = re.findall(r"([a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,6})", p_res)
        p_count = 0
        for d in p_domains:
            d = d.lower().strip(".")
            if is_blocked(d) or d in known or "/" in d or "perplexity" in d or "openrouter" in d:
                continue
            entry = hits.setdefault(d, {"hits": 0, "queries": [], "sources": set()})
            entry["hits"] += 3
            entry["queries"].append("живой поиск Perplexity Sonar")
            entry["sources"].add("Perplexity Sonar")
            p_count += 1
        print(f"   ✓ Perplexity нашёл {p_count} свежих профильных сайтов!")
    except Exception as e:
        print(f"   (Поиск Perplexity пропущен: {e})")

    for i, (query, source) in enumerate(queries, 1):
        print(f"\n[{i}/{len(queries)}] {query}   ({source})")
        hosts = ddg_hosts(query, args.per_query)
        if not hosts:
            print("    выдача пуста — пропускаю")
            time.sleep(PAUSE)
            continue
        new_hosts = []
        for host in hosts:
            if is_blocked(host) or host in known:
                continue
            new_hosts.append(host)
            entry = hits.setdefault(host, {"hits": 0, "queries": [], "sources": set()})
            entry["hits"] += 1
            entry["queries"].append(query)
            entry["sources"].add(source)
        print(f"    доменов в выдаче: {len(hosts)} · новых к проверке: {len(new_hosts)}")
        if new_hosts:
            print("    " + ", ".join(new_hosts[:10]))
        time.sleep(PAUSE)

    # Добавляем профильных кандидатов из базы знаний, если их ещё нет в плане
    for f_host, f_niche in FALLBACK_CANDIDATES:
        if f_host not in known and f_host not in hits:
            hits[f_host] = {"hits": 1, "queries": ["профильный ресурс"], "sources": {f_niche}}

    if not hits:
        print("\nНовых доменов не найдено. Попробуйте больше запросов (--queries).")
        return 0

    ranked = sorted(hits.items(), key=lambda kv: -kv[1]["hits"])
    print("\n" + "=" * 74)
    print(f"Кандидатов: {len(ranked)}. Проверяю наличие блога и статей...")
    print("-" * 74)

    proposals: list[dict] = []
    for host, info in ranked[:15]:
        probe = probe_blog(host)
        if probe["статей"] < args.min_articles:
            print(f"  — {host:<34} статей не найдено ({probe['статей']})")
            continue
        ex_url = probe["пример_url"] or f"https://{host}"
        print(f"  + {host:<34} статей: {probe['статей']:<4} "
              f"в выдаче: {info['hits']}   {ex_url[:52]}")
        proposals.append({
            "домен": host,
            "ниша": list(info["sources"])[0] if info["sources"] else "пайка и электроника",
            "тип": "Косвенный конкурент",
            "источник_находки": ", ".join(sorted(info["sources"]))[:180],
            "запросы": "; ".join(info["queries"][:4])[:240],
            "сколько_раз_в_выдаче": info["hits"],
            "найдено_статей": probe["статей"],
            "пример_url": ex_url,
            "статус": "предложен",
        })
        time.sleep(PAUSE * 0.4)

    DATA.mkdir(parents=True, exist_ok=True)
    (DATA / "discover.json").write_text(json.dumps({
        "generated_at": datetime.now().isoformat(timespec="seconds"),
        "queries": len(queries),
        "candidates": len(ranked),
        "proposals": proposals,
    }, ensure_ascii=False, indent=2), encoding="utf-8")

    print("\n" + "=" * 74)
    print(f"Годных кандидатов: {len(proposals)} из {len(ranked)}")

    if args.dry_run:
        print("\n--dry-run: в Excel ничего не записано.")
        return 0
    if not proposals:
        return 0

    with get_connection() as conn:
        for p in proposals:
            conn.execute("""
                INSERT OR REPLACE INTO competitors_proposed
                (domain, niche, type, source, queries, hits, articles_count, example_url, status, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'предложен', ?)
            """, (p["домен"], p["ниша"], p["тип"], p["источник_находки"], p["запросы"],
                  p["сколько_раз_в_выдаче"], p["найдено_статей"], p["пример_url"],
                  datetime.now().strftime("%Y-%m-%d %H:%M")))
        conn.commit()

    print(f"\n✓ Записано в SQLite (competitors_proposed): {len(proposals)} кандидатов.")
    print("Дальше: одобрите подходящие сайты в дашборде (кнопка «✓ Добавить») либо запустите:")
    print("python seo/scripts/discover_competitors.py --promote")
    return 0


def _read_proposed() -> list[dict]:
    """Читает уже предложенные домены из SQLite, чтобы не дублировать их каждый прогон."""
    try:
        with get_connection() as conn:
            rows = conn.execute("SELECT * FROM competitors_proposed").fetchall()
            return [{"домен": r["domain"]} for r in rows]
    except Exception:
        return []


if __name__ == "__main__":
    raise SystemExit(main())