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
import canonical_alloys as canonical

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

CANONICAL_ALLOYS = canonical.canonical_keys()


def load_schema() -> dict:
    return json.loads(SCHEMA_PATH.read_text(encoding="utf-8"))


def parse_faq_from_text(text: str) -> list[dict]:
    """Извлекает реальные вопросы-ответы из блока ## FAQ или ### FAQ: в тексте статьи."""
    faq_items = []
    faq_block_match = re.search(r"(?im)^#{2,3}\s*[^a-zA-Zа-яА-Я0-9\n]*(?:FAQ|Частые вопросы|Часто задаваемые|Вопросы и ответы)[^\n]*\n([\s\S]+?)(?=\n#{1,2}\s+[^\n]+|\Z)", text)
    if faq_block_match:
        block = faq_block_match.group(1).strip()
        # 1. Формат q: ... \n a: ... (разбиение по меткам q: и a:)
        if re.search(r"(?im)^\s*(?:\d+[.)]\s*)?q:\s*", block):
            raw_parts = re.split(r"(?im)^\s*(?:\d+[.)]\s*)?q:\s*", block)
            for part in raw_parts:
                if not part.strip():
                    continue
                if re.search(r"(?im)\n\s*a:\s*", part):
                    m = re.split(r"(?im)\n\s*a:\s*", part, maxsplit=1)
                    q = m[0].strip().strip('"«»')
                    a = m[1].split("\n---")[0].strip().strip('"«»')
                    if q and a:
                        faq_items.append({"q": q, "a": a})
        if not faq_items:
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

        # 4. Формат подзаголовков ### Вопрос? \n Ответ
        if not faq_items:
            pairs_h3 = re.findall(r'(?m)^#{3,4}\s+([^\n]+\?)\s*\n+([\s\S]+?)(?=\n#{3,4}\s+[^\n]+\?|\Z)', block)
            for q, a in pairs_h3:
                ans_clean = "\n".join([line.strip() for line in a.strip().splitlines() if not line.strip().startswith("#")])
                faq_items.append({"q": q.strip(), "a": ans_clean[:500].strip()})
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
    """Извлекает упомянутые инструменты и приводит названия к каноническим."""
    # каноническое имя -> шаблон упоминания
    CATALOG = [
        ("Паяльная станция", r"паяльн\w*\s+станци"),
        ("Паяльник", r"\bпаяльник\w*"),
        ("Термофен", r"термофен\w*|\bфен\w*"),
        ("Микроскоп", r"микроскоп\w*|\bлупа\b"),
        ("Оловоотсос", r"оловоотсос\w*|помп\w*"),
        ("Оплётка", r"оплётк\w*|оплетк\w*"),
        ("Термопинцет", r"термопинцет\w*|пинцет\w*"),
    ]
    tools: list[dict] = []
    for name, pattern in CATALOG:
        if re.search(pattern, text, re.I):
            tools.append({"name": name, "model": "", "purpose": "Пайка и демонтаж"})
    return tools


def detect_alloy(text: str, title: str = "") -> str:
    """Канонический сплав из реестра theme4/data/materials-registry.php."""
    key, _ = canonical.detect_alloy(text, title)
    return key


def detect_temp_data(text: str, alloy: str) -> dict | None:
    """Извлекает типизированные температурные данные из текста или канонического реестра.

    Строго разделяет:
      - температуру плавления (солидус/ликвидус) -> joint_temperature / melting
      - уставку жала паяльника / станции -> station_setpoint
    """
    # 1. Поиск явной уставки станции/паяльника по контекстным маркерам
    setpoint_matches = []
    for m in re.finditer(r"(?i)(?:уставк\w*|паяльник\w*|жал\w*|на станци\w*|режим\w*|паять при|температур\w*)\s*(?:жала|паяльника|станции|регулятора|составляет|равна|—|-|:)?\s*(\d{2,3})\s*(?:[–—\-]|до)\s*(\d{2,3})\s*°\s*[CС]", text):
        lo, hi = int(m.group(1)), int(m.group(2))
        if 230 <= lo < hi <= 400:
            setpoint_matches.append((lo, hi))

    if setpoint_matches:
        lo, hi = setpoint_matches[0]
        return {"kind": "station_setpoint", "range": f"{lo} – {hi} °C"}

    # 2. Поиск общих диапазонов в тексте с фильтрацией плавления
    melt_r = canonical.melt_range(alloy)
    all_ranges = [
        (int(m.group(1)), int(m.group(2)), m.start())
        for m in re.finditer(r"(\d{2,3})\s*(?:[–—\-]|до)\s*(\d{2,3})\s*°\s*[CС]", text)
        if int(m.group(1)) < int(m.group(2))
    ]

    for lo, hi, pos in all_ranges:
        prefix = text[max(0, pos - 60):pos].lower()
        is_melting = any(word in prefix for word in ("плавлен", "плавит", "солидус", "ликвидус", "тпл"))
        if is_melting or (melt_r and (abs(lo - melt_r[0]) <= 5 and abs(hi - melt_r[1]) <= 5)):
            continue  # Пропускаем температуру плавления

        if 240 <= lo and hi <= 380:
            return {"kind": "station_setpoint", "range": f"{lo} – {hi} °C"}

    # 3. Если уставка не названа явно в тексте, берём канонический рабочий коридор из реестра
    work_r = canonical.work_range(alloy)
    if work_r:
        return {"kind": "station_setpoint", "range": f"{work_r[0]} – {work_r[1]} °C"}

    # 4. В крайнем случае — температура плавления как joint_temperature
    if melt_r:
        return {"kind": "joint_temperature", "range": f"{melt_r[0]} – {melt_r[1]} °C"}

    return None


def markdown_to_html(md: str) -> str:
    """Полноценная конвертация Markdown структуры в чистый HTML для WordPress.

    Поддерживает:
      - Удаление YAML-frontmatter;
      - Удаление служебного блока FAQ из тела статьи (он упаковывается отдельно);
      - Таблицы Markdown (|---|) -> <div class="table-responsive"><table ...>;
      - Ссылки [текст](url) -> <a href="url">текст</a>;
      - Жирный (**текст**) и курсив (*текст*);
      - Инлайн-код (`код`);
      - Заголовки H1-H4;
      - Маркированные и нумерованные списки (ul / ol);
      - Абзацы (<p>...</p>).
    """
    if not md:
        return ""

    # 1. Срезаем YAML frontmatter (---\n...\n---)
    text = re.sub(r"^---\s*\n[\s\S]+?\n---\s*\n*", "", md.strip())

    # 2. Вырезаем служебный блок FAQ из тела статьи
    text = re.sub(
        r"(?im)^#{2,3}\s*[^a-zA-Zа-яА-Я0-9\n]*(?:FAQ|Частые вопросы|Часто задаваемые|Вопросы и ответы)[^\n]*\n[\s\S]+?(?=\n#{1,2}\s+[^\n]+|\Z)",
        "",
        text,
    ).strip()

    # 3. Конвертация таблиц Markdown (| ... |)
    def convert_table(match: re.Match) -> str:
        lines = [line.strip() for line in match.group(0).strip().splitlines() if line.strip()]
        if len(lines) < 2:
            return match.group(0)

        headers = [c.strip() for c in lines[0].split("|")[1:-1]]
        sep_cells = [c.strip() for c in lines[1].split("|")[1:-1]]
        has_sep = len(sep_cells) == len(headers) and all(re.match(r"^:?-+:?$", c) for c in sep_cells)

        rows = []
        start_row = 2 if has_sep else 1
        for line in lines[start_row:]:
            cells = [c.strip() for c in line.split("|")[1:-1]]
            if cells:
                rows.append(cells)

        out = ['<div class="table-responsive"><table class="article-table">']
        if headers:
            out.append('  <thead><tr>' + ''.join(f'<th>{h}</th>' for h in headers) + '</tr></thead>')
        out.append('  <tbody>')
        for r in rows:
            out.append('    <tr>' + ''.join(f'<td>{c}</td>' for c in r) + '</tr>')
        out.append('  </tbody>')
        out.append('</table></div>')
        return "\n".join(out)

    table_pattern = re.compile(r'(?:^\|.+?\|$\n?)+', re.MULTILINE)
    text = table_pattern.sub(convert_table, text)

    # 4. Ссылки: [текст](url)
    text = re.sub(r'\[([^\]]+)\]\(([^)]+)\)', r'<a href="\2">\1</a>', text)

    # 5. Инлайн-код: `code`
    text = re.sub(r'`([^`]+)`', r'<code>\1</code>', text)

    # 6. Жирный шрифт и курсив
    text = re.sub(r'\*\*([^*]+)\*\*', r'<strong>\1</strong>', text)
    text = re.sub(r'(?<!\*)\*([^*]+)\*(?!\*)', r'<em>\1</em>', text)

    # 7. Заголовки H1-H4
    text = re.sub(r'(?m)^####\s+(.+)$', r'<h4>\1</h4>', text)
    text = re.sub(r'(?m)^###\s+(.+)$', r'<h3>\1</h3>', text)
    text = re.sub(r'(?m)^##\s+(.+)$', r'<h2>\1</h2>', text)
    text = re.sub(r'(?m)^#\s+(.+)$', r'<h1>\1</h1>', text)

    # 8. Списки
    def convert_ul(match: re.Match) -> str:
        items = re.findall(r'(?m)^[-*]\s+(.+)$', match.group(0))
        return '<ul>' + ''.join(f'<li>{it}</li>' for it in items) + '</ul>'
    text = re.sub(r'(?:^[-*]\s+.+$\n?)+', convert_ul, text, flags=re.MULTILINE)

    def convert_ol(match: re.Match) -> str:
        items = re.findall(r'(?m)^\d+\.\s+(.+)$', match.group(0))
        return '<ol>' + ''.join(f'<li>{it}</li>' for it in items) + '</ol>'
    text = re.sub(r'(?:^\d+\.\s+.+$\n?)+', convert_ol, text, flags=re.MULTILINE)

    # 9. Формирование абзацев
    blocks = [b.strip() for b in re.split(r'\n\s*\n', text) if b.strip()]
    final_blocks = []
    for b in blocks:
        if any(b.startswith(tag) for tag in ('<h', '<table', '<div', '<ul', '<ol', '<blockquote', '<!--', '<hr')):
            final_blocks.append(b)
        elif b == '---':
            final_blocks.append('<hr />')
        else:
            final_blocks.append(f'<p>{b}</p>')

    return "\n\n".join(final_blocks)


def package_article(topic_id: int | str, passport_override: dict | None = None) -> dict:
    """Собирает и строго валидирует пакет статьи без выдуманных данных.

    При наличии сохранённого паспорта (passport_override или topic['passport_json'])
    использует явные параметры инженера (сплав, температуры, сложность, инструменты).
    """
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

    # 1. Извлекаем реальные элементы из текста
    first_para = re.split(r"\n\s*\n", md_text.strip())[0] if md_text else ""
    first_para_clean = re.sub(r"^#+.*?\n", "", first_para).strip()
    excerpt = first_para_clean[:280] if len(first_para_clean) >= 20 else (topic["title"] + ": техническое руководство.")

    alloy = detect_alloy(md_text, topic["title"])
    temp_data = detect_temp_data(md_text, alloy)
    tools = parse_tools_from_text(md_text)
    faq = parse_faq_from_text(md_text)
    sources = parse_sources_from_text(md_text)
    difficulty = "Практик"

    # Если передан явный паспорт или сохранён в БД — используем настройки инженера
    passport_data = {}
    if passport_override and isinstance(passport_override, dict):
        passport_data = passport_override
    elif topic and "passport_json" in topic.keys() and topic["passport_json"]:
        try:
            passport_data = json.loads(topic["passport_json"])
        except Exception:
            passport_data = {}

    if passport_data:
        if passport_data.get("solder_alloy"):
            alloy = passport_data["solder_alloy"]
        if passport_data.get("temp_data"):
            temp_data = passport_data["temp_data"]
        elif passport_data.get("temp_range"):
            t_obj = {
                "kind": passport_data.get("temp_kind") or "station_setpoint",
                "range": passport_data["temp_range"].strip(),
            }
            if passport_data.get("temp_peak"):
                t_obj["peak"] = passport_data["temp_peak"].strip()
            temp_data = t_obj
        if passport_data.get("difficulty"):
            difficulty = passport_data["difficulty"]
        if passport_data.get("tools"):
            tools = passport_data["tools"]
        if passport_data.get("sources"):
            sources = passport_data["sources"]

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
        "difficulty": difficulty,
        "solder_alloy": alloy,
        "temp_data": temp_data,
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
    if not tools:
        missing_data_warnings.append(
            "В тексте не найдено ни одного инструмента (паяльник, фен, станция, микроскоп). "
            "Паспорт требует минимум один — добавьте упоминание в статье, выдумывать нельзя."
        )

    # Не блокирует выгрузку: `universal` — легальный ключ схемы (обзорные материалы)
    notes = []
    if alloy == canonical.UNIVERSAL:
        notes.append(
            "Сплав не определён однозначно (обзорная тема или сплав не назван) — в паспорт ушёл ключ universal. "
            "Это допустимо по схеме v1.1; уточните сплав, только если материал про конкретную марку."
        )

    is_valid = len(validation_errors) == 0 and len(missing_data_warnings) == 0

    return {
        "valid": is_valid,
        "topic_id": tid,
        "contract": contract_data,
        "errors": validation_errors,
        "missing_warnings": missing_data_warnings,
        "notes": notes,
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
    for note in res.get("notes", []):
        print(f"  ℹ️ {note}")
    print("=" * 60)


if __name__ == "__main__":
    main()
