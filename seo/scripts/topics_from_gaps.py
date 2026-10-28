"""Генератор тем из контентных пробелов — бесплатно, 0 токенов.

Отвечает на вопрос «на какие темы писать», когда план уже не покрывает всё,
что есть у конкурентов.

Логика:
1. Берёт `raw_data` — статьи конкурентов, собранные `research_competitors.py`.
2. Считает, сколько статей конкурентов в каждом кластере.
3. Сравнивает с `content_plan`: сколько наших тем в том же кластере.
4. Кластеры, где у конкурентов есть контент, а у нас нет тем (или мало),
   становятся источником предложений.
5. Дополнительно берёт заголовки конкурентов в «пустых» кластерах и
   переформулирует их в рабочие заголовки по правилам Practico.
6. Считает предварительную оценку приоритета по той же логике, что и
   `priority_matrix.py`, чтобы предложения сразу были отсортированы.

Пишет в лист `topics_proposed` со статусом «предложен».
ЛИСТ `content_plan` НЕ ТРОГАЕТСЯ. Тема попадает в план только после того,
как вы поставите статус «принят» и запустите:

    python seo/scripts/topics_from_gaps.py --promote

Запуск:
    python seo/scripts/topics_from_gaps.py                # предложить темы
    python seo/scripts/topics_from_gaps.py --dry-run      # только показать
    python seo/scripts/topics_from_gaps.py --promote      # внести «принят» в план
"""
from __future__ import annotations

import argparse
import json
import re
import sys
from collections import Counter, defaultdict
from datetime import datetime

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

from db import build_db_plan as build_plan, get_connection
from excel_io import DATA, clean, save_topic_proposals

# Минимум статей конкурентов в кластере, чтобы считать его пробелом
MIN_COMPETITOR_ARTICLES = 1
# Максимум наших тем в кластере, чтобы кластер считался незакрытым
MAX_OWN_TOPICS = 1

# Заголовки-шаблоны под тип контента: из темы конкурента делаем рабочую тему
TITLE_RULES = [
    ("Что такое {x}: виды и где применяется", r"что такое|виды|типы|классификац"),
    ("{x}: пошаговая инструкция", r"как |инструкц|руководств|пошагов"),
    ("{x}: как выбрать и на что смотреть", r"как выбрать|выбор|какой лучше"),
    ("{x}: типичные ошибки и как их избежать", r"ошибк|проблем|не работает|брак"),
    ("{x}: сравнение вариантов", r"сравнени|разниц|или |vs"),
    ("{x}: обзор и рейтинг", r"рейтинг|топ|лучш|обзор"),
]

# Кластеры, которые нам не нужны: чужой B2B-профиль контрактного производства
SKIP_CLUSTERS = {
    "Производство / Документация",
    "Производство / Поставщики",
    "Производство / Прототипы",
    "Производство / Сроки",
    "Производство / Стоимость",
    "Прочее",
}

# Источники, из которых темы не берём: соцсети, видео, маркетплейсы
SKIP_SOURCES = re.compile(r"vk\.com|youtube|rutube|dzen|t\.me|reddit|ozon|wildberries", re.I)

# Заголовки-услуги контрактного производства: это не темы для статей
SERVICE_TITLE = re.compile(
    r"под заказ|на заказ|в Москве|контрактн\w+ производств|сборка и монтаж|"
    r"монтаж печатных плат|производство печатных плат|изготовление печатных плат|"
    r"наш[аи]? (?:услуг|клиент|партн)|отзыв|прайс|стоимость услуг",
    re.I,
)

# Слишком общие заголовки и разделы форумов: из них не сделать тему
TOO_GENERIC = re.compile(
    r"^(?:форум.*|главная|блог|статьи|новости|все статьи|пайка и монтаж|отзывы клиентов|о компании)$", re.I
)


def normalize_title(title: str) -> str:
    """Убирает бренды, кавычки, лишние хвосты — оставляет суть темы."""
    text = clean(title)
    text = re.sub(r"[«»\"']", "", text)
    text = re.sub(r"\s*[|—–-]\s*(SolderPoint|solderpoint\.ru|Москва|под заказ)\s*$", "", text, flags=re.I)
    text = re.sub(r"\s+в Москве\s*$", "", text, flags=re.I)
    text = re.sub(r"\s+", " ", text).strip(" .:-—–")
    return text


def topic_from_competitor_title(title: str) -> str:
    """Строит рабочий заголовок из заголовка конкурента."""
    base = normalize_title(title)
    if not base:
        return ""
    # Превращаем вопросы форумов вида "Посоветуйте X" в "Как выбрать X"
    base = re.sub(r"^(?:посоветуйте|подскажите|помогите выбрать)\s+", "Как выбрать ", base, flags=re.I)
    low = base.lower()
    for template, pattern in TITLE_RULES:
        if re.search(pattern, low):
            # если в заголовке уже есть нужная конструкция — оставляем как есть
            if template.split(":")[0].lower() in low or len(base) > 25:
                return base[:120]
            return template.format(x=base[:80])
    return base[:120]


def slugify(title: str) -> str:
    table = {
        "а": "a", "б": "b", "в": "v", "г": "g", "д": "d", "е": "e", "ё": "e",
        "ж": "zh", "з": "z", "и": "i", "й": "y", "к": "k", "л": "l", "м": "m",
        "н": "n", "о": "o", "п": "p", "р": "r", "с": "s", "т": "t", "у": "u",
        "ф": "f", "х": "h", "ц": "c", "ч": "ch", "ш": "sh", "щ": "sch", "ъ": "",
        "ы": "y", "ь": "", "э": "e", "ю": "yu", "я": "ya",
    }
    out = []
    for char in title.lower():
        if char in table:
            out.append(table[char])
        elif char.isalnum():
            out.append(char)
        else:
            out.append("-")
    slug = re.sub(r"-{2,}", "-", "".join(out)).strip("-")
    return slug[:80]


def keywords_count_of(cluster_info: dict) -> int:
    """Сколько ключевых сущностей заявлено у кластера."""
    raw = clean(cluster_info.get("keywords")) if cluster_info else ""
    return len([p for p in re.split(r"[,;]", raw) if p.strip()])


def is_service_title(title: str) -> bool:
    """Отсекает заголовки-услуги и слишком общие заголовки."""
    text = clean(title)
    if not text or len(text) < 12:
        return True
    if TOO_GENERIC.match(text):
        return True
    return bool(SERVICE_TITLE.search(text))


def estimate_priority(cluster: str, comp_articles: int, own_topics: int,
                      cluster_priority_rank: int, keywords_count: int) -> int:
    """Предварительный балл 0–100 по той же логике, что priority_matrix.py.

    Отличие от матрицы: тема новая, поэтому хабности ещё нет, а готовность
    всегда «с нуля». Зато сильнее вес пробела и широты ключей кластера —
    именно они решают, стоит ли вообще брать новую тему.
    """
    cluster_score = {1: 100, 2: 65, 3: 35}.get(cluster_priority_rank, 50)

    # Пробел: кластер без наших тем и с чужим контентом — максимальный
    if own_topics == 0 and comp_articles >= 1:
        gap_score = 100
    elif own_topics == 0:
        gap_score = 80
    elif own_topics == 1:
        gap_score = 70
    else:
        gap_score = 45

    # Покрытие: чем меньше конкурентов в кластере, тем легче войти
    if comp_articles >= 6:
        coverage_score = 25
    elif comp_articles >= 4:
        coverage_score = 45
    elif comp_articles >= 2:
        coverage_score = 65
    elif comp_articles == 1:
        coverage_score = 85
    else:
        coverage_score = 100

    links_score = 35          # у новой темы связей ещё нет
    keywords_score = min(100, 35 + keywords_count * 11)
    readiness_score = 55      # с нуля, но кластер разведан

    total = (cluster_score * 0.30 + gap_score * 0.25 + coverage_score * 0.15
             + links_score * 0.10 + keywords_score * 0.10 + readiness_score * 0.10)
    return round(total)


def main() -> int:
    ap = argparse.ArgumentParser(description="Темы из контентных пробелов")
    ap.add_argument("--dry-run", action="store_true", help="не писать в Excel")
    ap.add_argument("--top", type=int, default=25, help="сколько предложений показать")
    ap.add_argument("--max-per-cluster", type=int, default=3,
                    help="максимум предложений из одного кластера")
    ap.add_argument("--promote", action="store_true",
                    help="внести в content_plan всё со статусом «принят»")
    args = ap.parse_args()

    if args.promote:
        return promote()

    plan = build_plan()
    own = Counter(t["cluster"] for t in plan["topics"].values())
    comp_by_cluster: dict[str, list[dict]] = defaultdict(list)
    for row in plan["raw_data"]:
        comp_by_cluster[row["cluster"]].append(row)

    print("=" * 78)
    print(" ТЕМЫ ИЗ КОНТЕНТНЫХ ПРОБЕЛОВ — «Точка Плавления»")
    print("=" * 78)
    print(f" Наших тем: {len(plan['topics'])} | статей конкурентов: {len(plan['raw_data'])}")
    print("-" * 78)

    gaps: list[dict] = []
    for cluster, rows in comp_by_cluster.items():
        if cluster in SKIP_CLUSTERS:
            continue
        n_comp = len(rows)
        n_own = own.get(cluster, 0)
        if n_comp < MIN_COMPETITOR_ARTICLES or n_own > MAX_OWN_TOPICS:
            continue
        cluster_info = plan["clusters"].get(cluster, {})
        rank = cluster_info.get("priority_rank", 2)
        gaps.append({
            "cluster": cluster,
            "comp_articles": n_comp,
            "own_topics": n_own,
            "rank": rank,
            "rows": rows,
        })
    gaps.sort(key=lambda g: (-g["comp_articles"], g["rank"]))

    print(f"{'Кластер':<40} {'наших':>6} {'конкур':>7}")
    print("-" * 78)
    for gap in gaps:
        print(f"{gap['cluster'][:40]:<40} {gap['own_topics']:>6} {gap['comp_articles']:>7}")

    # ---- формируем предложения -------------------------------------------
    proposals: list[dict] = []
    used_titles: set[str] = set(t["title"].lower() for t in plan["topics"].values())
    counter = 0

    for gap in gaps:
        taken = 0
        cluster_info = plan["clusters"].get(gap["cluster"], {})
        kw_count = keywords_count_of(cluster_info)
        for row in gap["rows"]:
            if taken >= args.max_per_cluster:
                break
            raw_title = row.get("title", "")
            source = row.get("source", "")
            if SKIP_SOURCES.search(source) or is_service_title(raw_title):
                continue
            title = topic_from_competitor_title(raw_title)
            if not title or title.lower() in used_titles or is_service_title(title):
                continue
            used_titles.add(title.lower())
            counter += 1
            priority = estimate_priority(
                gap["cluster"], gap["comp_articles"], gap["own_topics"], gap["rank"], kw_count
            )
            proposals.append({
                "предложение_id": f"P{counter:02d}",
                "рабочий заголовок": title,
                "кластер": gap["cluster"],
                "тип статьи": row["content_type"] or "гайд",
                "источник": row["source"],
                "доказательство": f"{row['source']}: {row['title'][:110]}",
                "частота_в_выдаче": gap["comp_articles"],
                "оценка_приоритета": priority,
                "статус": "предложен",
            })
            taken += 1

    proposals.sort(key=lambda p: -p["оценка_приоритета"])

    print("\n" + "=" * 78)
    print(f" ПРЕДЛОЖЕНИЯ ТЕМ: {len(proposals)}")
    print("=" * 78)
    print(f" {'ID':<5}{'P':<5}{'кластер':<34}{'тип':<16} Заголовок")
    print("-" * 78)
    for p in proposals[: args.top]:
        print(f" {p['предложение_id']:<5}{p['оценка_приоритета']:<5}"
              f"{p['кластер'][:33]:<34}{(p['тип статьи'] or '')[:15]:<16} "
              f"{p['рабочий заголовок'][:44]}")

    DATA.mkdir(parents=True, exist_ok=True)
    (DATA / "topics_from_gaps.json").write_text(json.dumps({
        "generated_at": datetime.now().isoformat(timespec="seconds"),
        "gaps": [{"cluster": g["cluster"], "comp": g["comp_articles"],
                  "own": g["own_topics"]} for g in gaps],
        "proposals": proposals,
    }, ensure_ascii=False, indent=2), encoding="utf-8")

    if args.dry_run:
        print("\n--dry-run: в Excel ничего не записано.")
        return 0
    if not proposals:
        print("\nПробелов не найдено: план уже покрывает то, что есть у конкурентов.")
        return 0

    # Сохраняем предложения в SQLite БД (Единый первоисточник)
    with get_connection() as conn:
        for p in proposals:
            conn.execute("""
                INSERT OR REPLACE INTO topics_proposed
                (id, title, cluster, type, source, evidence, hits, priority_score, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'предложен', ?)
            """, (p["предложение_id"], p["рабочий заголовок"], p["кластер"], p["тип статьи"],
                  p["источник"], p["доказательство"], p["частота_в_выдаче"], p["оценка_приоритета"],
                  datetime.now().strftime("%Y-%m-%d %H:%M")))
        conn.commit()

    print(f"\n✓ Записано в SQLite (topics_proposed): {len(proposals)} предложений.")
    print("Дальше: одобрите подходящие темы в дашборде (кнопка «✓ Одобрить») либо запустите:")
    print("python seo/scripts/topics_from_gaps.py --promote")
    return 0


def promote() -> int:
    """Вносит принятые темы в SQLite topics, рассчитывая новые ID."""
    with get_connection() as conn:
        cur = conn.cursor()
        accepted = cur.execute("SELECT * FROM topics_proposed WHERE status IN ('принят', 'принято')").fetchall()
        if not accepted:
            print("В таблице `topics_proposed` нет записей со статусом «принят».")
            print("Одобрите темы в дашборде (кнопка «✓ Одобрить») или установите статус в БД.")
            return 1

        max_id = cur.execute("SELECT MAX(id) FROM topics").fetchone()[0] or 0
        next_id = max_id + 1
        added = 0

        for rec in accepted:
            title = rec["title"]
            slug = slugify(title) + f"-{next_id}"
            p_score = rec["priority_score"] or 60
            priority = "высокий" if p_score >= 80 else "средний"
            now_str = datetime.now().isoformat(timespec="seconds")

            cur.execute("""
                INSERT INTO topics
                (id, title, slug, cluster, type, priority, priority_score, status, next_action, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'backlog', ?, ?, ?)
            """, (next_id, title, slug, rec["cluster"], rec["type"] or "гайд", priority, p_score,
                  f"одобрено из topics_proposed ({rec['id']})", now_str, now_str))
            
            cur.execute("UPDATE topics_proposed SET status = 'в работе' WHERE id = ?", (rec["id"],))
            next_id += 1
            added += 1

        conn.commit()

    print(f"✓ В SQLite (topics) перенесено тем: {added}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())