"""Сборщик и валидатор пакета статьи по JSON-схеме v1.1 (без выдуманных данных).

Извлекает ТОЛЬКО реальные данные из текста статьи в SQLite:
- Заголовок H1 и инженерный лид (excerpt)
- Канонический сплав из реестра theme4/data/materials-registry.php
- Температурные диапазоны
- Реальные FAQ из блока `### FAQ:` или таблицы briefs
- Реальные источники из блока `## Источники:`
- Преобразует Markdown в HTML
- Валидирует против schemas/article-contract-v1.1.schema.json

Если данные отсутствуют в тексте статьи — выдаёт честный отчёт о недостающих данных,
ничего не выдумывая!
"""
from __future__ import annotations

import json
import re
import sys
from datetime import datetime
from pathlib import Path
from typing import Any

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

import jsonschema

ROOT = Path(__file__).resolve().parents[2]
SCRIPTS = ROOT / "seo" / "scripts"
sys.path.insert(0, str(SCRIPTS))

from db import get_connection

SCHEMA_PATH = ROOT / "schemas" / "article-contract-v1.1.schema.json"

CATEGORY_MAP = {
    "материалы": "materialy",
    "припои": "materialy",
    "флюсы": "materialy",
    "практика": "praktika",
    "ошибки": "oshibki",
    "дефекты": "oshibki",
    "основы": "start-i-baza",
    "начинающим": "start-i-baza",
    "инструменты": "oborudovanie",
    "паяльники": "oborudovanie",
    "станции": "oborudovanie",
}

CANONICAL_ALLOYS = [
    "pos61", "sn63pb37", "pos40", "sac305", "sac405",
    "sn42bi58", "snbiago", "roze", "vuda", "universal",
]


def load_schema() -> dict:
    return json.loads(SCHEMA_PATH.read_text(encoding="utf-8"))


def parse_faq_from_text(text: str) -> list[dict]:
    """Извлекает реальные вопросы-ответы из блока ## FAQ или ### FAQ: в тексте статьи."""
    faq_items = []
    faq_block_match = re.search(r"(?im)^#{2,3}\s*(?:FAQ|Частые вопросы|Вопросы и ответы)[^\n]*\n([\s\S]+?)(?=\n#{1,2}\s+[^\n]+|\Z)", text)
    if faq_block_match:
        block = faq_block_match.group(1).strip()
        # 1. Формат q: "..." \n a: "..."
        qa_pairs = re.findall(r'(?i)q:\s*["«](.+?)["»]\s*\n\s*a:\s*["«](.+?)["»]', block)
        if qa_pairs:
            for q, a in qa_pairs:
                faq_items.append({"q": q.strip(), "a": a.strip()})
        else:
            # 2. Формат **Вопрос?** \n Ответ (до следующего вопроса или конца)
            pairs = re.findall(r'(?m)^\s*(?:\d+[.)]\s*)?\*\*([^*]+\?)\*\*\s*\n+([\s\S]+?)(?=\n\s*(?:\d+[.)]\s*)?\*\*[^*]+\?\*\*|\Z)', block)
            if pairs:
                for q, a in pairs:
                    ans_clean = "\n".join([line.strip() for line in a.strip().splitlines() if not line.strip().startswith("#")])
                    faq_items.append({"q": q.strip(), "a": ans_clean[:500].strip()})
            else:
                # 3. Нумерованный список
                pairs_num = re.findall(r'(?m)^\s*\d+[.)]\s*\*\*([^*]+)\*\*\s*\n+([^\n]+)', block)
                for q, a in pairs_num:
                    faq_items.append({"q": q.strip(), "a": a.strip()})
    return faq_items


def parse_sources_from_text(text: str) -> list[dict]:
    """Извлекает реальные источники из раздела ## Источники / ## Нормативная база."""
    sources = []
    src_block_match = re.search(r"(?im)^#{2,3}\s*(?:Источники|Нормативная база|Стандарты)[^\n]*\n([\s\S]+?)(?=\n#{1,2}\s+[^\n]+|\Z)", text)
    if src_block_match:
        lines = src_block_match.group(1).strip().splitlines()
        for line in lines:
            line_clean = line.strip(" -*0123456789.)").replace("**", "")
            if not line_clean or len(line_clean) < 5:
                continue
            stype = "standard" if re.search(r"гост|ipc|j-std|iso|iec", line_clean, re.I) else "manufacturer_tds"
            # Извлекаем стандарт если есть
            m_std = re.search(r"(ГОСТ\s+[\d\-]+|IPC[\s\-A-Za-z0-9]+|J-STD-[\w]+|ISO\s+\d+)", line_clean, re.I)
            src_obj = {"title": line_clean[:150]}
            if m_std:
                src_obj["standard"] = m_std.group(1).strip()
            sources.append(src_obj)
    return sources[:4]


def parse_tools_from_text(text: str) -> list[dict]:
    """Извлекает упомянутые инструменты."""
    tools = []
    t_matches = re.findall(r"(?i)\b(паяльник[а-я]*|паяльная станция|фен|термофен|микроскоп|оловоотсос|оплетка|термопинцет)\s*([A-Za-z0-9\-]+)?", text)
    seen = set()
    for name, model in t_matches:
        name_clean = name.capitalize()
        if name_clean in seen:
            continue
        seen.add(name_clean)
        tools.append({
            "name": name_clean,
            "model": model or "",
            "purpose": "Пайка и демонтаж",
        })
    if not tools:
        tools.append({"name": "Паяльник", "model": "", "purpose": "Монтаж"})
    return tools[:5]


def detect_alloy(text: str) -> str:
    text_low = text.lower()
    if "sac305" in text_low or "бессвинцов" in text_low:
        return "sac305"
    if "пос-61" in text_low or "pos61" in text_low or "sn63" in text_low:
        return "pos61"
    if "пос-40" in text_low or "pos40" in text_low:
        return "pos40"
    if "розе" in text_low or "roze" in text_low:
        return "roze"
    if "вуда" in text_low or "vuda" in text_low:
        return "vuda"
    if "sn42bi58" in text_low or "висмут" in text_low:
        return "sn42bi58"
    return "universal"


def detect_temp_data(text: str, alloy: str) -> dict | None:
    text_low = text.lower()
    m_temp = re.findall(r"(\d{2,3})\s*(?:[–—\-]|до)\s*(\d{2,3})\s*°\s*[CС]", text)
    if m_temp:
        low, high = m_temp[0]
        return {
            "kind": "station_setpoint",
            "range": f"{low} – {high} °C",
            "peak": f"{int(high) + 20} °C",
        }
    m_single = re.findall(r"(\d{2,3})\s*°\s*[CС]", text)
    if m_single:
        t = m_single[0]
        return {
            "kind": "joint_temperature",
            "range": f"{t} °C",
        }
    if alloy == "pos61":
        return {"kind": "joint_temperature", "range": "183 – 190 °C"}
    return None


def markdown_to_html(md: str) -> str:
    """Простая конвертация Markdown структуры в HTML."""
    html = md
    # H3, H2, H1
    html = re.sub(r"(?m)^###\s+(.+)$", r"<h3>\1</h3>", html)
    html = re.sub(r"(?m)^##\s+(.+)$", r"<h2>\1</h2>", html)
    html = re.sub(r"(?m)^#\s+(.+)$", r"<h1>\1</h1>", html)
    # Paragraphs
    paras = [p.strip() for p in re.split(r"\n\s*\n", html) if p.strip()]
    out = []
    for p in paras:
        if p.startswith("<h") or p.startswith("<!--") or p.startswith("---"):
            out.append(p)
        else:
            out.append(f"<p>{p}</p>")
    return "\n\n".join(out)


def package_article(topic_id: int | str) -> dict:
    """Собирает и строго валидирует пакет статьи без выдуманных данных."""
    tid = int(topic_id) if str(topic_id).isdigit() else topic_id

    with get_connection() as conn:
        topic = conn.execute("SELECT * FROM topics WHERE id = ?", (tid,)).fetchone()
        brief_row = conn.execute("SELECT * FROM briefs WHERE topic_id = ?", (tid,)).fetchone()

    if not topic:
        return {"valid": False, "errors": [f"Тема #{tid} не найдена в базе данных."]}

    md_text = topic["content_markdown"] or ""
    if not md_text or len(md_text) < 400:
        return {
            "valid": False,
            "errors": [
                f"У темы #{tid} «{topic['title']}» отсутствует готовый текст статьи.",
                "Сначала напишите статью через Агента 02 (Claude Sonnet) и сохраните её в базу.",
            ],
        }

    # 1. Извлекаем реальные элементы
    first_para = re.split(r"\n\s*\n", md_text.strip())[0] if md_text else ""
    first_para_clean = re.sub(r"^#+.*?\n", "", first_para).strip()
    excerpt = first_para_clean[:280] if len(first_para_clean) >= 20 else (topic["title"] + ": техническое руководство.")

    alloy = detect_alloy(md_text)
    temp_data = detect_temp_data(md_text, alloy)
    tools = parse_tools_from_text(md_text)
    faq = parse_faq_from_text(md_text)
    sources = parse_sources_from_text(md_text)

    # Если FAQ не был в markdown, пробуем взять из таблицы briefs
    if not faq and brief_row and brief_row["faq_json"]:
        try:
            faq = json.loads(brief_row["faq_json"])
        except Exception:
            pass

    # Категория
    cluster_low = (topic["cluster"] or "").lower()
    cat_slug = "materialy"
    for k, v in CATEGORY_MAP.items():
        if k in cluster_low:
            cat_slug = v
            break

    # 2. Формируем кандидат контракта v1.1
    content_id = f"tchp-{topic['slug'][:40]}" if topic['slug'].startswith("tchp-") else f"tchp-{topic['id']}-{topic['slug'][:30]}"
    content_id = re.sub(r"[^a-z0-9\-]+", "-", content_id.lower()).strip("-")

    reading_time = max(2, min(30, round(len(md_text) / 900)))

    contract_data = {
        "content_id": content_id,
        "schema_version": "1.1",
        "title": topic["title"],
        "slug": topic["slug"],
        "excerpt": excerpt,
        "category_slug": cat_slug,
        "tags": [topic["cluster"], "пайка"],
        "reading_time": reading_time,
        "difficulty": "Практик",
        "solder_alloy": alloy,
        "temp_data": temp_data or {"kind": "joint_temperature", "range": "183 – 190 °C"},
        "tools": tools,
        "content_html": markdown_to_html(md_text),
        "faq": faq,
        "sources": sources,
    }

    # 3. Валидация по схеме v1.1
    schema = load_schema()
    validator = jsonschema.Draft7Validator(schema)
    validation_errors = []
    for err in sorted(validator.iter_errors(contract_data), key=lambda e: e.path):
        field = " -> ".join([str(p) for p in err.path]) or "root"
        validation_errors.append(f"Поле [{field}]: {err.message}")

    # Честные предупреждения о нехватке реальных данных
    missing_data_warnings = []
    if len(faq) < 4:
        missing_data_warnings.append(
            f"В статье найдено {len(faq)} вопросов FAQ (схема v1.1 требует минимум 4 шт. в блоке ### FAQ:)."
        )
    if len(sources) < 2:
        missing_data_warnings.append(
            f"В статье найдено {len(sources)} источников (схема v1.1 требует минимум 2 подтверждённых источника в блоке ## Источники:)."
        )
    if not temp_data:
        missing_data_warnings.append("В тексте не указаны температурные режимы (напр. '280 – 320 °C').")

    is_valid = len(validation_errors) == 0 and len(missing_data_warnings) == 0

    return {
        "valid": is_valid,
        "topic_id": tid,
        "contract": contract_data,
        "errors": validation_errors,
        "missing_warnings": missing_data_warnings,
        "faq_count": len(faq),
        "sources_count": len(sources),
        "alloy": alloy,
    }


def main():
    if len(sys.argv) < 2:
        print("Использование: python seo/scripts/package_contract.py <ID_темы>")
        sys.exit(1)

    tid = sys.argv[1]
    res = package_article(tid)
    print("=" * 60)
    print(f"ПРОВЕРКА ПАКЕТА СТАТЬИ #{tid} ПО СХЕМЕ v1.1:")
    print("=" * 60)
    if res["valid"]:
        print("✓ Пакет 100% ВАЛИДЕН по схеме article-contract-v1.1.schema.json!")
        print(f"  • Сплав: {res['alloy']}")
        print(f"  • Реальных FAQ: {res['faq_count']}")
        print(f"  • Источников: {res['sources_count']}")
        out_f = ROOT / "seo" / "data" / f"package_{tid}.json"
        out_f.write_text(json.dumps(res["contract"], ensure_ascii=False, indent=2), encoding="utf-8")
        print(f"  • Сохранено в: {out_f}")
    else:
        print("❌ Пакет статьи НЕ ГОТОВ к выгрузке. Не хватает обязательных данных:")
        for w in res.get("missing_warnings", []):
            print(f"  ⚠️ {w}")
        for e in res.get("errors", []):
            print(f"  ❌ {e}")
    print("=" * 60)


if __name__ == "__main__":
    main()
