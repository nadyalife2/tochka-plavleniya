"""Оценка статьи 0–100 по шести блокам (см. seo/_SPEC.md §3).

Запуск:
    python seo/scripts/score_article.py --file "сами статьи/15-pripoj-dlya-pajki-vidy.md" --id 15
    python seo/scripts/score_article.py --text "готовый текст" --id 15
Выход:
    seo/out/scores/<id>.json   машинный результат
    seo/out/scores/<id>.md     читаемый отчёт
    код возврата: 0 — ≥80, 2 — 60–79, 3 — <60, 4 — блокер
"""
from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

from db import build_db_plan as build_plan

ROOT = Path(__file__).resolve().parents[2]
DATA = ROOT / "seo" / "data"
OUT = ROOT / "seo" / "out"

SCORES = OUT / "scores"

STOP_PHRASES = [
    "идеальный результат", "абсолютно безопасно", "без каких-либо рисков",
    "справится любой", "проще простого", "гарантированный результат",
    "это можно делать как угодно", "ошибиться невозможно", "подойдёт всем",
    "секретный секрет",
]
BUREAUCRATIC = ["необходимо", "следует", "осуществляется", "выполните", "важно отметить",
                "является", "данный", "в целях", "посредством"]
AI_PATTERNS = [
    "в современном мире", "не секрет, что", "играет важную роль", "является ключевым",
    "в заключение", "подводя итоги", "в этой статье мы", "давайте рассмотрим",
    "стоит отметить", "как мы видим", "нельзя не отметить",
]
DANGEROUS = ["под напряжением", "не отключая", "без отключения от сети", "паять включ"]
SOURCE_MARKERS = ["гост", "ipc", "j-std", "jstd", "iso", "даташит", "datasheet", "tds",
                  "ansi", "iec", "ту ", "технические условия"]
ALLOYS = ["pos61", "пос-61", "sn63pb37", "pos40", "пос-40", "sac305", "sac405",
          "sn42bi58", "snbiago", "roze", "розе", "vuda", "вуда", "universal"]


def load_text(args) -> str:
    if args.file:
        return Path(args.file).read_text(encoding="utf-8", errors="replace")
    if args.text:
        return args.text
    if args.id:
        try:
            from db import get_connection
            with get_connection() as conn:
                row = conn.execute("SELECT content_markdown FROM topics WHERE id = ?", (args.id,)).fetchone()
                if row and row["content_markdown"]:
                    return row["content_markdown"]
        except Exception:
            pass
    raise SystemExit("Нужен --file, --text или валидный --id с текстом в базе")


def words(text: str) -> list[str]:
    return re.findall(r"[A-Za-zА-Яа-яЁё0-9\-]+", text)


def sentences(text: str) -> list[str]:
    parts = re.split(r"(?<=[.!?])\s+", re.sub(r"\s+", " ", text))
    return [p.strip() for p in parts if len(words(p)) > 2]


def paragraphs(text: str) -> list[str]:
    parts = [p.strip() for p in re.split(r"\n\s*\n", text) if p.strip()]
    return [p for p in parts if not p.startswith("#")]


def headings(text: str) -> list[tuple[int, str]]:
    return [(len(m.group(1)), m.group(2).strip()) for m in re.finditer(r"(?m)^(#{1,6})\s+(.+)$", text)]


def strip_markdown(text: str) -> str:
    text = re.sub(r"```.*?```", " ", text, flags=re.S)
    text = re.sub(r"`[^`]+`", " ", text)
    text = re.sub(r"!\[[^\]]*\]\([^)]*\)", " ", text)
    text = re.sub(r"\[([^\]]+)\]\([^)]*\)", r"\1", text)
    return text


def internal_links(text: str) -> list[str]:
    # Внутренние относительные ссылки сайта: /slug/, /slug или ../slug/
    return re.findall(r"\]\(((?:/|\.\./)(?:[a-z0-9\-]+)/?)\)", text)


def count_faq(text: str) -> int:
    try:
        from package_contract import parse_faq_from_text
        items = parse_faq_from_text(text)
        if items:
            return len(items)
    except Exception:
        pass

    if re.search(r"(?im)^#{2,3}\s*(faq|частые вопросы|вопросы и ответы)", text):
        questions = re.findall(r'(?m)^\s*(?:\d+[.)]\s*)?(?:\*\*[^*]+\?\*\*|q:\s*["«]?[^"\n\r]+\?|[^.\n\r]+\?\s*["»]?\s*$)', text)
        return len([q for q in questions if q])
    return len(re.findall(r"(?m)^\s*(?:[-*]|\d+[.)])\s+[^.\n]+\?", text))


def blocks(text: str, plan: dict | None, article_id: str) -> dict:
    plain = strip_markdown(text)
    h = headings(text)
    h1 = [t for lvl, t in h if lvl == 1]
    h2 = [t for lvl, t in h if lvl == 2]
    # Объём текста: с пробелами (редакционный стандарт)
    chars = len(plain.strip())
    paras = paragraphs(plain)
    sents = [s for p in paras for s in sentences(p)]
    avg_len = sum(len(words(s)) for s in sents) / max(1, len(sents))
    avg_par = sum(len(sentences(p)) for p in paras) / max(1, len(paras))
    links = internal_links(text)
    faq = count_faq(text)
    sources = sum(1 for marker in SOURCE_MARKERS for _ in re.finditer(marker, plain.lower()))
    alloys_ok = any(a in plain.lower() for a in ALLOYS)
    temps = re.findall(r"\d{2,3}\s*°\s*[CС]", plain)
    stop_hits = [p for p in STOP_PHRASES if p in plain.lower()]
    bur_hits = [w for w in BUREAUCRATIC if re.search(rf"\b{w}", plain.lower())]
    ai_hits = [p for p in AI_PATTERNS if p in plain.lower()]

    # Проверка опасных советов: отрицание ищем ВБЛИЗИ фразы, а не в любом месте предложения.
    # Иначе «Паяйте под напряжением, чтобы не тратить время» ошибочно считается предупреждением.
    danger_hits = []
    NEGATION_RE = re.compile(
        r"\b(не|нельзя|запрещ\w*|исключ\w*|никогда|недопустим\w*|опасн\w*|внимани\w*|предостереж\w*|риск\w*)\b",
        re.I,
    )
    for s in sents:
        s_low = s.lower()
        for p in DANGEROUS:
            caught = False
            for m in re.finditer(re.escape(p), s_low):
                pre_words = re.findall(r"[\w\-]+", s_low[: m.start()])[-5:]
                post_words = re.findall(r"[\w\-]+", s_low[m.end():])[:1]
                window = " ".join(pre_words + post_words)
                if not NEGATION_RE.search(window):
                    danger_hits.append(f"«{s.strip()[:80]}»")
                    caught = True
                    break
            if caught:
                break
    first_para = paras[0] if paras else ""
    answer_words = len(words(first_para))

    result = {
        "chars": chars,
        "h1": h1, "h2_count": len(h2),
        "avg_sentence_words": round(avg_len, 1),
        "avg_paragraph_sentences": round(avg_par, 1),
        "faq": faq, "internal_links": len(links), "sources": sources,
        "temps": len(temps), "alloys": alloys_ok,
        "answer_first_words": answer_words,
        "stop_phrases": stop_hits, "bureaucratic": bur_hits,
        "ai_patterns": ai_hits, "dangerous": danger_hits,
    }

    score: dict[str, dict] = {}
    issues: list[str] = []
    blockers: list[str] = []

    # --- intent 20
    p = 0
    p += 5 if 0 < answer_words <= 40 else (2 if answer_words <= 60 else 0)
    if answer_words > 40:
        issues.append(f"Answer First: {answer_words} слов в первом абзаце (нужно ≤ 40)")
    p += 5 if len(h1) == 1 else (0 if len(h1) > 1 else 2)
    if len(h1) != 1:
        issues.append(f"H1: найдено {len(h1)} (нужен ровно один)")
    p += 5 if 3 <= len(h2) <= 8 else (3 if len(h2) <= 12 else 1)
    if not 3 <= len(h2) <= 8:
        issues.append(f"H2: {len(h2)} (цель 4–8; для справочников допустимо до 10)")
    p += 5 if 5500 <= chars <= 7000 else (3 if 4500 <= chars <= 8000 else 0)
    if not 5500 <= chars <= 7000:
        issues.append(f"Объём: {chars:,} знаков (цель 5 500–7 000)")
    score["intent"] = {"points": p, "max": 20}

    # --- facts 25
    p = 0
    p += 8 if 2 <= sources <= 8 else (4 if sources >= 1 else 0)
    if sources < 2:
        issues.append(f"Источники: найдено {sources} упоминаний ГОСТ/IPC/TDS (нужно 2–4)")
    p += 5 if alloys_ok else 2
    if not alloys_ok:
        issues.append("Сплавы: в тексте нет ни одного канонического сплава")
    p += 5 if len(temps) >= 3 else (2 if temps else 0)
    if len(temps) < 3:
        issues.append(f"Температуры: {len(temps)} значений с °C (нужно ≥ 3 в паяльной теме)")
    if danger_hits:
        blockers.append("Опасный совет: " + ", ".join(danger_hits))
        p += 0
    else:
        p += 7
    score["facts"] = {"points": p, "max": 25}

    # --- voice 15
    p = 0
    p += 5 if not stop_hits else 0
    if stop_hits:
        issues.append("Стоп-фразы: " + ", ".join(stop_hits))
    p += 4 if len(bur_hits) <= 3 else 0
    if len(bur_hits) > 3:
        issues.append("Канцелярит: " + ", ".join(bur_hits[:5]))
    p += 3 if avg_len <= 20 else (1 if avg_len <= 24 else 0)
    if avg_len > 20:
        issues.append(f"Средняя длина предложения {avg_len} слов (цель ≤ 20)")
    p += 3 if avg_par <= 3 else 1
    if avg_par > 3:
        issues.append(f"Абзацы по {avg_par} предложений (цель 1–3)")
    score["voice"] = {"points": p, "max": 15}

    # --- seo 20
    p = 0
    p += 6 if 4 <= faq <= 6 else (3 if faq else 0)
    if not 4 <= faq <= 6:
        issues.append(f"FAQ: {faq} вопросов (нужно 4–6)")
    p += 5 if len(links) >= 3 else (3 if links else 0)
    if len(links) < 3:
        issues.append(f"Внутренние ссылки: {len(links)} (нужно 3–5)")
    p += 5 if len(temps) or alloys_ok else 2
    p += 4 if re.search(r"(?im)^#{2,3}.*(источник|стандарт)", text) else 2
    if not re.search(r"(?im)^#{2,3}.*(источник|стандарт)", text):
        issues.append("Нет раздела «Источники/Стандарты»")
    score["seo"] = {"points": p, "max": 20}

    # --- links 10
    p = 0
    p += 5 if 3 <= len(links) <= 5 else (3 if links else 0)
    if not 3 <= len(links) <= 5:
        issues.append(f"Ссылки: {len(links)} (цель 3–5)")
    bad_anchor = re.search(r"\]\((?:/|https?)[^)]*\)", text) and re.search(r"\((?:тут|здесь|по ссылке)\)", text.lower())
    p += 2 if not bad_anchor else 0
    if bad_anchor:
        issues.append("Анкоры «тут/здесь» — заменить")
    p += 3
    score["links"] = {"points": p, "max": 10}

    # --- ai 10
    p = 0
    p += 4 if len(ai_hits) <= 1 else 0
    if len(ai_hits) > 1:
        issues.append("ИИ-паттерны: " + ", ".join(ai_hits))
    p += 3 if not re.search(r"(?i)в этой статье|подводя итоги|в заключение", plain) else 0
    p += 3 if chars >= 4500 else 1
    score["ai"] = {"points": p, "max": 10}

    total = sum(v["points"] for v in score.values())
    verdict = "готово к ОТК" if total >= 80 else ("черновик" if total >= 60 else "в доработку")
    if blockers:
        verdict = "блокер"

    aeo_geo = compute_aeo_geo_score(text, result)

    return {
        "article_id": article_id,
        "generated_at": __import__("datetime").datetime.now().isoformat(timespec="seconds"),
        "file": None,
        "metrics": result,
        "blocks": score,
        "total": total,
        "verdict": verdict,
        "aeo_geo": aeo_geo,
        "issues": issues,
        "blockers": blockers,
        "manual": ["проверить факты по первоисточникам", "проверить уникальность (text.ru/Главред)",
                   "прочитать голосом вслух (голос Practico)"],
    }


def compute_aeo_geo_score(text: str, metrics: dict | None = None) -> dict:
    """AEO & GEO Скоринг цитируемости статьи нейросетями (Yandex Нейро / Perplexity / Gemini).
    
    Модели цитируют контент, содержащий четкие предикаты: [Сущность] + [Свойство] + [Физическое значение].
    5 инженерных критериев (по 20 баллов каждый):
    1. Answer First (прямой ответ в первом абзаце <= 40 слов).
    2. Таблица параметров с физическими величинами (°C, мм, Вт, сплавы).
    3. Ссылки на стандарты и первоисточники (ГОСТ, IPC, J-STD, TDS).
    4. Структурированный FAQ-блок (готов для Schema.org FAQPage).
    5. Anti-Slop (полная чистота от AI-воды и штампов).
    """
    if metrics is None:
        plain = strip_markdown(text)
        paras = paragraphs(plain)
        sents = [s for p in paras for s in sentences(p)]
        first_para = paras[0] if paras else ""
        answer_words = len(words(first_para))
        sources = sum(1 for marker in SOURCE_MARKERS for _ in re.finditer(marker, plain.lower()))
        faq = count_faq(text)
        ai_hits = [p for p in AI_PATTERNS if p in plain.lower()]
        stop_hits = [p for p in STOP_PHRASES if p in plain.lower()]
        temps = re.findall(r"\d{2,3}\s*°\s*[CС]", plain)
        alloys_ok = any(a in plain.lower() for a in ALLOYS)
    else:
        answer_words = metrics.get("answer_first_words", 0)
        sources = metrics.get("sources", 0)
        faq = metrics.get("faq", 0)
        ai_hits = metrics.get("ai_patterns", [])
        stop_hits = metrics.get("stop_phrases", [])
        temps = range(metrics.get("temps", 0))
        alloys_ok = metrics.get("alloys", False)

    predicates = []
    total_pts = 0

    # 1. Answer First
    af_pts = 20 if 0 < answer_words <= 40 else (10 if answer_words <= 60 else 0)
    total_pts += af_pts
    predicates.append({
        "name": "answer_first",
        "title": "Прямой ответ в первом абзаце (Answer First)",
        "points": af_pts,
        "max": 20,
        "status": "pass" if af_pts == 20 else ("warn" if af_pts > 0 else "fail"),
        "detail": f"{answer_words} слов в зачине (норма: ≤ 40 слов без вводных конструкций)"
    })

    # 2. Таблица параметров с единицами измерения
    has_md_table = bool(re.search(r"\|[-:\s|]+\|", text))
    has_units = bool(temps) or bool(re.search(r"\b(мм|вт|мпа|мком|г/см3|%|sn\d+pb\d+)\b", text.lower()))
    tbl_pts = 20 if (has_md_table and has_units) else (12 if (has_md_table or (has_units and alloys_ok)) else 0)
    total_pts += tbl_pts
    predicates.append({
        "name": "params_table",
        "title": "Таблица параметров с физическими величинами",
        "points": tbl_pts,
        "max": 20,
        "status": "pass" if tbl_pts >= 18 else ("warn" if tbl_pts > 0 else "fail"),
        "detail": "Таблица Markdown с величинами (°C, мм, сплавы) найдена" if has_md_table else "Таблица параметров не найдена (нейросетям сложнее парсить)"
    })

    # 3. Первоисточники и стандарты (ГОСТ/IPC/TDS)
    std_pts = 20 if sources >= 2 else (10 if sources == 1 else 0)
    total_pts += std_pts
    predicates.append({
        "name": "standards",
        "title": "Ссылки на первоисточники (ГОСТ, IPC, TDS)",
        "points": std_pts,
        "max": 20,
        "status": "pass" if std_pts == 20 else ("warn" if std_pts > 0 else "fail"),
        "detail": f"Найдено {sources} упоминаний стандартов (цель: ≥ 2 для авторитетности)"
    })

    # 4. FAQ блок
    faq_pts = 20 if faq >= 3 else (10 if faq in (1, 2) else 0)
    total_pts += faq_pts
    predicates.append({
        "name": "faq_block",
        "title": "FAQ-блок для микроразметки Schema.org FAQPage",
        "points": faq_pts,
        "max": 20,
        "status": "pass" if faq_pts == 20 else ("warn" if faq_pts > 0 else "fail"),
        "detail": f"Найдено {faq} вопросов FAQ (норма: 3–5 вопросов под сниппет)"
    })

    # 5. Чистота от AI-штампов
    slop_count = len(ai_hits) + len(stop_hits)
    slop_pts = 20 if slop_count == 0 else (10 if slop_count == 1 else 0)
    total_pts += slop_pts
    predicates.append({
        "name": "anti_slop",
        "title": "Чистота от AI-клише и штампов (Anti-Slop)",
        "points": slop_pts,
        "max": 20,
        "status": "pass" if slop_pts == 20 else ("warn" if slop_pts > 0 else "fail"),
        "detail": "Штампы отсутствуют" if slop_count == 0 else f"Обнаружено {slop_count} AI-маркеров/стоп-фраз"
    })

    level = "Высокая (AEO / GEO Ready)" if total_pts >= 80 else ("Умеренная" if total_pts >= 50 else "Низкая")
    return {
        "score": total_pts,
        "max": 100,
        "level": level,
        "badge_class": "status-badge--success" if total_pts >= 80 else ("status-badge--review" if total_pts >= 50 else "status-badge--fix"),
        "predicates": predicates
    }


def to_markdown(report: dict) -> str:
    m = report["metrics"]
    lines = [f"# Оценка статьи #{report['article_id']}", "",
             f"**Итог: {report['total']} / 100 — {report['verdict']}**", "",
             f"- знаков: {m['chars']:,} · H1: {len(m['h1'])} · H2: {m['h2_count']}",
             f"- источники: {m['sources']} · температуры: {m['temps']} · FAQ: {m['faq']} · ссылки: {m['internal_links']}",
             f"- предложение: {m['avg_sentence_words']} слов · абзац: {m['avg_paragraph_sentences']} предл.",
             "", "| Блок | Баллы | Макс |", "|---|---:|---:|"]
    for name, data in report["blocks"].items():
        lines.append(f"| {name} | {data['points']} | {data['max']} |")
    if report["blockers"]:
        lines += ["", "## Блокеры"] + [f"- ❌ {b}" for b in report["blockers"]]
    if report["issues"]:
        lines += ["", "## Замечания"] + [f"- {i}" for i in report["issues"]]
    lines += ["", "## Проверить вручную"] + [f"- {i}" for i in report["manual"]]
    return "\n".join(lines) + "\n"


def main() -> int:
    parser = argparse.ArgumentParser(description="Оценка статьи 0–100")
    parser.add_argument("--file", help="путь к markdown-файлу статьи")
    parser.add_argument("--text", help="текст статьи строкой")
    parser.add_argument("--id", default="", help="ID темы из мастер-таблицы")
    args = parser.parse_args()

    text = load_text(args)
    plan = build_plan()
    report = blocks(text, plan, args.id or "—")
    report["file"] = args.file

    SCORES.mkdir(parents=True, exist_ok=True)
    slug = args.id or "article"
    (SCORES / f"{slug}.json").write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")
    (SCORES / f"{slug}.md").write_text(to_markdown(report), encoding="utf-8")

    if args.id:
        try:
            from db import get_connection
            with get_connection() as conn:
                conn.execute(
                    "UPDATE topics SET score_total = ?, score_json = ? WHERE id = ?",
                    (report["total"], json.dumps(report, ensure_ascii=False), args.id)
                )
                conn.commit()
        except Exception:
            pass

    print(f"#{args.id or '—'}: {report['total']}/100 — {report['verdict']}")
    print(f"    {SCORES / (slug + '.md')}")
    if report["blockers"]:
        return 4
    return 0 if report["total"] >= 80 else (2 if report["total"] >= 60 else 3)


if __name__ == "__main__":
    raise SystemExit(main())
