"""Разведчик конкурентов «Точки Плавления» — БЕСПЛАТНО, без API-ключей.

Что делает:
1. Читает список конкурентов из листа `competitors` мастера.
2. Обходит sitemap.xml / RSS / HTML-архив блога каждого конкурента.
3. Извлекает реальные URL статей, заголовки, даты.
4. Классифицирует тип статьи и кластер по заголовку (эвристики).
5. Пишет результат в `raw_data` и обновляет счётчики в `competitors`.

Ноль токенов: чистый HTTP + регулярные выражения + XML/HTML-парсер.

Запуск:
    python seo/scripts/research_competitors.py                 # все конкуренты
    python seo/scripts/research_competitors.py --domain habr.com
    python seo/scripts/research_competitors.py --dry-run       # только показать
    python seo/scripts/research_competitors.py --limit 60      # максимум URL с сайта
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
from pathlib import Path

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

from bs4 import BeautifulSoup

from excel_io import DATA, build_plan, clean, save_competitors, save_raw_data

UA = "Mozilla/5.0 (compatible; TchP-ResearchBot/1.0; +https://tochka-plavleniya.ru)"
TIMEOUT = 15
PAUSE = 0.8           # пауза между запросами к одному хосту

# ------------------------------------------------------------ классификация --

CONTENT_TYPES = [
    ("рейтинг / обзор бренда", r"топ[\s\-]?\d+|лучш\w+ паяльник|рейтинг|обзор бренд|vs\b|сравнени\w+ модел"),
    ("топ ошибок / проблема-решение", r"ошибк|холодн\w+ пайк|не паяется|брак|дефект|почему не|как исправить"),
    ("сравнение", r"или\b|разниц|чем отлича|что выбрать|vs\b|против"),
    ("инструкция", r"как\s|пошагов|инструкц|руководств|своими руками|гайд"),
    ("buyer's guide", r"как выбрать|выбор\b|какой лучше|что купить|подбор"),
    ("обучающий / что такое", r"что такое|виды|типы|классификац|основы|для начинающих"),
]

CLUSTER_RULES = [
    ("Материалы / Припои", r"припой|пос-?\d|sac\d|бессвинцов|олово|розе|вуд|паяльн\w+ паст"),
    ("Материалы / Флюсы", r"флюс|канифол|rma|ra\b|лти|отмыв|гель"),
    ("Ошибки / Проблемы новичка", r"ошибк|брак|дефект|холодн\w+ пайк|мост|перегрев"),
    ("Инструменты / Выбор паяльника", r"паяльник|паяльн\w+ станц|жало|фен|термопинцет"),
    ("Инструменты / Рейтинг паяльников", r"топ[\s\-]?\d+ паяльник|рейтинг паяльник|лучш\w+ паяльник"),
    ("Ремонт электроники", r"ремонт|выпай|замен\w+ разъ|телефон|наушник|плат[аы] телефон"),
    ("Основы пайки / Как паять", r"как паять|пайка для начина|техник\w+ пайк|научит\w+ паять"),
    ("Типы компонентов / SMD", r"smd|smt|qfn|резистор|конденсатор|микросхем"),
    ("Типы компонентов / BGA", r"bga|реболл|reball|шарик\w+ вывод"),
    ("Производство / Документация", r"документац|гост|стандарт\w+ сборк|конструктор\w+ документ"),
    ("Производство / Поставщики", r"поставщик|производител\w+ плат|заказ\w+ плат|контрактн\w+ производств"),
    ("Производство / Прототипы", r"прототип|мелк\w+ сери|пилотн\w+ парт"),
    ("Производство / Стоимость", r"стоимост|цена сборк|сколько стоит монтаж"),
    ("Производство / Сроки", r"сроки|быстр\w+ производств|ускор\w+ сборк"),
    ("Ошибки / Проектирование", r"ошибк\w+ проект|трассировк|разводк\w+ плат|drc"),
    ("Типы монтажа / SMD vs DIP", r"рефлоу|reflow|волн\w+ пайк|селективн\w+ пайк|выводн\w+ монтаж|dip|tht"),
    ("Типы плат / Алюминиевые", r"алюмини\w+ плат|металлическ\w+ плат"),
    ("Типы плат / Двусторонние", r"двусторонн\w+ плат|двухсторонн\w+ монтаж"),
]

# Сервисные страницы и мусор — не статьи, в raw_data не попадают
SERVICE_PAGE = re.compile(
    r"^(о компании|о нас|контакты|партн[её]ры|клиенты|отзывы|услуги|доставка|оплата|"
    r"политика|соглашение|вакансии|новости|каталог|корзина|требования к документации|"
    r"как мы|почему мы|наши преимущества|сертификат|лицензи|реквизит|карта сайта)",
    re.I,
)
SERVICE_URL = re.compile(
    r"/(about|contacts?|partners?|reviews?|delivery|payment|privacy|policy|"
    r"vacancy|vacancies|catalog|shop|cart|news|company)(/|$|\?)", re.I,
)

# URL, которые почти никогда не являются статьями
JUNK = re.compile(
    r"/(tag|tags|category|categories|author|page|feed|search|login|cart|"
    r"checkout|account|privacy|policy|contacts?|about|sitemap|wp-json|"
    r"wp-content|wp-admin|comments?)(/|$|\?)",
    re.I,
)
ARTICLE_HINT = re.compile(r"/\d{4}/|/blog/|/article/|/articles/|/publication/|/ru/|/post/|/p/", re.I)


def classify_type(title: str) -> str:
    low = title.lower()
    for name, pattern in CONTENT_TYPES:
        if re.search(pattern, low):
            return name
    return "статья / гайд"


def classify_cluster(title: str) -> str:
    low = title.lower()
    for name, pattern in CLUSTER_RULES:
        if re.search(pattern, low):
            return name
    return "Прочее"


# --------------------------------------------------------------- HTTP-слой ---


def fetch(url: str) -> tuple[int, str, str]:
    """Возвращает (status, content_type, text). Ошибки — (0, '', '')."""
    req = urllib.request.Request(url, headers={"User-Agent": UA, "Accept-Language": "ru,en;q=0.8"})
    try:
        with urllib.request.urlopen(req, timeout=TIMEOUT) as resp:
            raw = resp.read(4_000_000)
            ctype = resp.headers.get("Content-Type", "")
            charset = "utf-8"
            m = re.search(r"charset=([\w\-]+)", ctype, re.I)
            if m:
                charset = m.group(1)
            try:
                text = raw.decode(charset, errors="replace")
            except LookupError:
                text = raw.decode("utf-8", errors="replace")
            return resp.status, ctype, text
    except urllib.error.HTTPError as e:
        return e.code, "", ""
    except Exception:
        return 0, "", ""


def strip_ns(tag: str) -> str:
    return tag.split("}")[-1].lower()


# ------------------------------------------------------------- sitemap/RSS ---


def parse_sitemap(xml: str, limit: int) -> tuple[list[str], list[str]]:
    """Возвращает (urls статей, вложенные sitemap)."""
    from xml.etree import ElementTree as ET

    urls: list[str] = []
    nested: list[str] = []
    try:
        root = ET.fromstring(xml.strip())
    except ET.ParseError:
        return urls, nested
    for node in root.iter():
        tag = strip_ns(node.tag)
        if tag == "loc" and node.text:
            loc = node.text.strip()
            if loc.endswith((".xml", ".xml.gz")):
                nested.append(loc)
            elif not JUNK.search(loc):
                urls.append(loc)
                if len(urls) >= limit:
                    break
    return urls, nested


def parse_rss(xml: str, limit: int) -> list[dict]:
    from xml.etree import ElementTree as ET

    items: list[dict] = []
    try:
        root = ET.fromstring(xml.strip())
    except ET.ParseError:
        return items
    for node in root.iter():
        if strip_ns(node.tag) not in {"item", "entry"}:
            continue
        link = title = date = ""
        for child in node:
            tag = strip_ns(child.tag)
            if tag == "link" and child.text:
                link = child.text.strip()
            elif tag == "link" and child.get("href"):
                link = child.get("href", "").strip()
            elif tag == "title" and child.text:
                title = child.text.strip()
            elif tag in {"pubdate", "published", "updated"} and child.text:
                date = child.text.strip()
        if link:
            items.append({"url": link, "title": title, "date": date})
        if len(items) >= limit:
            break
    return items


def discover_feeds(base: str) -> list[str]:
    """Ищет ссылки на RSS/sitemap в <head> главной страницы."""
    status, _, html = fetch(base)
    if status != 200 or not html:
        return []
    soup = BeautifulSoup(html, "lxml")
    found: list[str] = []
    for tag in soup.find_all(["link", "a"]):
        href = (tag.get("href") or "").strip()
        typ = (tag.get("type") or "").lower()
        if not href:
            continue
        if "rss" in typ or "atom" in typ or href.endswith(("rss.xml", "feed", "feed.xml", "atom.xml")):
            found.append(href)
    return found


# --------------------------------------------------------------- HTML-скан ---


def scan_html_index(url: str, source_domain: str, limit: int) -> list[dict]:
    """Собирает ссылки-статьи с HTML-страницы (архив блога)."""
    status, _, html = fetch(url)
    if status != 200 or not html:
        return []
    soup = BeautifulSoup(html, "lxml")
    out: list[dict] = []
    seen: set[str] = set()
    for a in soup.find_all("a", href=True):
        href = a["href"].strip()
        if href.startswith("//"):
            href = "https:" + href
        elif href.startswith("/"):
            href = urllib.parse.urljoin(url, href)
        elif not href.startswith("http"):
            continue
        if source_domain not in href:
            continue
        if JUNK.search(href) or not ARTICLE_HINT.search(href):
            continue
        clean_url = href.split("?")[0].split("#")[0]
        if clean_url in seen:
            continue
        seen.add(clean_url)
        title = " ".join(a.get_text(" ", strip=True).split())
        if len(title) < 15:
            continue
        out.append({"url": clean_url, "title": title, "date": ""})
        if len(out) >= limit:
            break
    return out


# ------------------------------------------------------------------ сканер ---


def scan_competitor(domain: str, blog_url: str, limit: int) -> list[dict]:
    """Пытается собрать максимум статей конкурента."""
    base = blog_url or f"https://{domain}"
    candidates: list[dict] = []
    seen: set[str] = set()

    def add(items: list[dict]) -> None:
        for it in items:
            u = it["url"].split("?")[0].rstrip("/")
            if u and u not in seen:
                seen.add(u)
                candidates.append(it)

    # 1) sitemap: корневой + типовые пути
    sitemap_paths = ["/sitemap.xml", "/sitemap_index.xml", "/wp-sitemap.xml",
                     "/sitemap-1.xml", "/blog/sitemap.xml"]
    for path in sitemap_paths:
        status, _, xml = fetch(urllib.parse.urljoin(base, path))
        if status != 200 or not xml.lstrip().startswith("<?xml"):
            continue
        urls, nested = parse_sitemap(xml, limit)
        add([{"url": u, "title": "", "date": ""} for u in urls])
        for n in nested[:4]:
            st, _, sub = fetch(n)
            if st == 200:
                sub_urls, _ = parse_sitemap(sub, limit)
                add([{"url": u, "title": "", "date": ""} for u in sub_urls])
        if len(candidates) >= limit:
            break
        time.sleep(PAUSE)

    # 2) RSS (главная + автопоиск)
    rss_paths = ["/rss", "/rss.xml", "/feed", "/feed.xml", "/atom.xml", "/blog/rss.xml", "/ru/rss/"]
    for path in rss_paths:
        status, _, xml = fetch(urllib.parse.urljoin(base, path))
        if status == 200 and "<rss" in xml[:500] or (status == 200 and "<feed" in xml[:800]):
            add(parse_rss(xml, limit))
            break
    for feed in discover_feeds(base)[:2]:
        st, _, xml = fetch(urllib.parse.urljoin(base, feed))
        if st == 200:
            add(parse_rss(xml, limit))
        time.sleep(PAUSE)

    # 3) HTML-архив блога
    if len(candidates) < limit:
        add(scan_html_index(base, domain, limit))

    # 4) обогащение: если нет заголовка — берём <title> страницы
    enriched: list[dict] = []
    for i, item in enumerate(candidates[:limit]):
        title = item.get("title", "")
        if not title:
            st, _, html = fetch(item["url"])
            if st == 200 and html:
                soup = BeautifulSoup(html, "lxml")
                h1 = soup.find("h1")
                t = soup.find("title")
                title = (h1.get_text(" ", strip=True) if h1 else "") or \
                        (t.get_text(" ", strip=True) if t else "")
            time.sleep(PAUSE * 0.5)
        if not title:
            continue
        # Отсекаем сервисные страницы: «О компании», «Отзывы», «Доставка» и т.п.
        if SERVICE_PAGE.match(title.strip()) or SERVICE_URL.search(item["url"]):
            continue
        enriched.append({
            "url": item["url"],
            "title": " ".join(title.split())[:300],
            "h1": " ".join(title.split())[:300],
            "date": item.get("date", ""),
            "source": domain,
            "content_type": classify_type(title),
            "cluster": classify_cluster(title),
            "priority": "",
            "status": "собрано",
        })
    return enriched


# -------------------------------------------------------------------- main ---


def main() -> int:
    ap = argparse.ArgumentParser(description="Разведка конкурентов (бесплатно, без API)")
    ap.add_argument("--domain", help="только один домен")
    ap.add_argument("--limit", type=int, default=60, help="максимум URL с одного сайта")
    ap.add_argument("--dry-run", action="store_true", help="не писать в Excel")
    args = ap.parse_args()

    socket.setdefaulttimeout(TIMEOUT)
    plan = build_plan()
    competitors = plan["competitors"]
    if not competitors:
        print("В листе `competitors` нет записей.")
        return 1

    todo = [c for c in competitors if not args.domain or args.domain.lower() in c["domain"].lower()]
    print(f"Разведка: {len(todo)} конкурентов, лимит {args.limit} URL на сайт")
    print("=" * 70)

    all_records: list[dict] = []
    summary: list[dict] = []

    for comp in todo:
        domain = comp["domain"]
        print(f"\n>>> {domain}  ({comp['type'] or 'без типа'})")
        records = scan_competitor(domain, comp.get("blog", ""), args.limit)
        print(f"    найдено статей: {len(records)}")
        by_type: dict[str, int] = {}
        by_cluster: dict[str, int] = {}
        for r in records:
            by_type[r["content_type"]] = by_type.get(r["content_type"], 0) + 1
            by_cluster[r["cluster"]] = by_cluster.get(r["cluster"], 0) + 1
        for t, n in sorted(by_type.items(), key=lambda x: -x[1]):
            print(f"      тип: {t} — {n}")
        for c, n in sorted(by_cluster.items(), key=lambda x: -x[1])[:5]:
            print(f"      кластер: {c} — {n}")
        all_records.extend(records)
        summary.append({"domain": domain, "found": len(records),
                        "by_type": by_type, "by_cluster": by_cluster})

    print("\n" + "=" * 70)
    print(f"Всего собрано URL: {len(all_records)}")

    # кэш
    DATA.mkdir(parents=True, exist_ok=True)
    cache = DATA / "research.json"
    cache.write_text(json.dumps({
        "generated_at": datetime.now().isoformat(timespec="seconds"),
        "summary": summary,
        "total": len(all_records),
    }, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"Кэш разведки: {cache}")

    if args.dry_run:
        print("\n--dry-run: в Excel ничего не записано.")
        for r in all_records[:10]:
            print(f"  • [{r['cluster']}] {r['title'][:80]}")
        return 0

    if not all_records:
        print("Нечего записывать в Excel.")
        return 1

    result = save_raw_data(all_records, update_competitor_counts=True)
    print(f"\nExcel raw_data: добавлено {result['added']}, пропущено дублей {result['skipped']}")
    print(f"Бэкап: {result['backup']}")
    return 0


if __name__ == "__main__":
    import urllib.parse  # noqa: E402  (нужен внутри scan_html_index)
    raise SystemExit(main())