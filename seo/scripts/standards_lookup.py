"""Сверхкомпактный CLI-справочник стандартов пайки (ГОСТ / ОСТ / IPC).

Предназначен для мгновенного и экономичного фактчека агентами-авторами и рецензентами:
возвращает только выверенные нормативные данные без лишнего расхода токенов.

Использование:
    python seo/scripts/standards_lookup.py pos61
    python seo/scripts/standards_lookup.py sac305
    python seo/scripts/standards_lookup.py rol0
    python seo/scripts/standards_lookup.py "холодная пайка"
    python seo/scripts/standards_lookup.py safety
"""
from __future__ import annotations

import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
STANDARDS_DIR = ROOT / "seo" / "reference" / "standards"

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass


def load_alloys() -> dict:
    fpath = STANDARDS_DIR / "alloys.json"
    if fpath.exists():
        return json.loads(fpath.read_text(encoding="utf-8")).get("items", {})
    return {}


def load_fluxes() -> dict:
    fpath = STANDARDS_DIR / "fluxes.json"
    if fpath.exists():
        return json.loads(fpath.read_text(encoding="utf-8"))
    return {}


def lookup_alloy(query: str, alloys: dict) -> dict | None:
    q = query.strip().lower().replace("-", "").replace(" ", "")
    # Прямой поиск по id
    for aid, data in alloys.items():
        clean_aid = aid.replace("-", "").replace(" ", "")
        clean_name = data.get("name", "").lower().replace("-", "").replace(" ", "")
        if q == clean_aid or q in clean_name or clean_aid in q:
            return data
    return None


def lookup_flux(query: str, fluxes: dict) -> dict | None:
    q = query.strip().upper()
    ipc = fluxes.get("ipc_classes", {})
    if q in ipc:
        return ipc[q]
    q_low = query.strip().lower()
    for code, item in ipc.items():
        if q_low in code.lower() or q_low in item.get("notes", "").lower():
            return item
    return None


def format_alloy_card(item: dict) -> str:
    temps = item.get("temperatures", {})
    comp = item.get("composition", {})
    props = item.get("physical_properties", {})
    interval = temps.get("crystal_interval", 0)
    eutectic_str = "ДА (0 °C)" if temps.get("is_eutectic") else f"НЕТ (интервал {interval} °C)"
    lines = [
        "═══════════════════════════════════════════════════════════════",
        f"[СПЛАВ] {item.get('name')} | Стандарт: {item.get('standard')}",
        "═══════════════════════════════════════════════════════════════",
        f"• Тип: {item.get('type')} | Эвтектика: {eutectic_str}",
        f"• Температуры: солидус {temps.get('t_solidus')} °C, ликвидус {temps.get('t_liquidus')} °C",
        f"• Рабочая уставка жала: {temps.get('t_iron_min')} – {temps.get('t_iron_max')} °C",
    ]
    if temps.get("t_reflow_peak"):
        lines.append(f"• Пик термопрофиля фена/печи: {temps.get('t_reflow_peak')} °C")
    prop_parts = []
    if props.get("density_g_cm3"):
        prop_parts.append(f"плотность {props['density_g_cm3']} г/см³")
    if props.get("electrical_resistivity_ohm_mm2_m"):
        prop_parts.append(f"уд. сопр. {props['electrical_resistivity_ohm_mm2_m']} Ом·мм²/м")
    if props.get("tensile_strength_mpa"):
        prop_parts.append(f"прочность на разрыв {props['tensile_strength_mpa']} МПа")
    if props.get("relative_elongation_pct"):
        prop_parts.append(f"удлинение {props['relative_elongation_pct']}%")
    if prop_parts:
        lines.append(f"• Физ. свойства: {', '.join(prop_parts)}")
    warns = item.get("warnings", [])
    if warns:
        lines.append("• КРИТИЧЕСКИЕ ОГРАНИЧЕНИЯ:")
        for w in warns:
            lines.append(f"  ⚠️ {w}")
    lines.append(f"═══════════════════════════════════════════════════════════════")
    return "\n".join(lines)


def format_flux_card(item: dict) -> str:
    lines = [
        f"═══════════════════════════════════════════════════════════════",
        f"[ФЛЮС IPC] Класс: {item.get('code')} | Основа: {item.get('base')}",
        f"═══════════════════════════════════════════════════════════════",
        f"• Активность: {item.get('activity')} | Галогены: {item.get('halides')}",
        f"• Отмывка обязательна: {'ДА ⚠️' if item.get('wash_required') else 'НЕТ (безотмывочный No-Clean)'}",
        f"• Описание: {item.get('notes', '')}",
    ]
    for w in item.get("warnings", []):
        lines.append(f"  ⚠️ {w}")
    lines.append(f"═══════════════════════════════════════════════════════════════")
    return "\n".join(lines)


def main():
    as_json = "--json" in sys.argv
    clean_argv = [a for a in sys.argv[1:] if a != "--json"]

    if not clean_argv:
        if as_json:
            print(json.dumps({"error": "missing_query", "usage": "python seo/scripts/standards_lookup.py <query> [--json]"}, ensure_ascii=False, indent=2))
        else:
            print("Использование: python seo/scripts/standards_lookup.py <марка_сплава | код_флюса | тема> [--json]")
            print("Примеры: pos61, sac305, sn42bi58, roze, ROL0, ROL1, safety, quality")
        sys.exit(1)

    query = clean_argv[0].strip()

    if query.lower() in ("safety", "тб", "безопасность", "пдк"):
        fpath = STANDARDS_DIR / "safety.md"
        if fpath.exists():
            text = fpath.read_text(encoding="utf-8")
            if as_json:
                print(json.dumps({"status": "ok", "type": "safety", "standard": "ГОСТ 12.3.040-86", "content": text}, ensure_ascii=False, indent=2))
            else:
                print(text.strip())
        return

    if query.lower() in ("quality", "дефекты", "отк", "брак", "ipc-610", "холодная пайка"):
        fpath = STANDARDS_DIR / "quality_defects.md"
        if fpath.exists():
            text = fpath.read_text(encoding="utf-8")
            if as_json:
                print(json.dumps({"status": "ok", "type": "quality", "standard": "IPC-A-610G / ГОСТ Р 56427-2015", "content": text}, ensure_ascii=False, indent=2))
            else:
                print(text.strip())
        return

    alloys = load_alloys()
    alloy_match = lookup_alloy(query, alloys)
    if alloy_match:
        if as_json:
            print(json.dumps({"status": "ok", "type": "alloy", "item": alloy_match}, ensure_ascii=False, indent=2))
        else:
            print(format_alloy_card(alloy_match))
        return

    fluxes = load_fluxes()
    flux_match = lookup_flux(query, fluxes)
    if flux_match:
        if as_json:
            print(json.dumps({"status": "ok", "type": "flux", "item": flux_match}, ensure_ascii=False, indent=2))
        else:
            print(format_flux_card(flux_match))
        return

    if as_json:
        print(json.dumps({
            "status": "not_found",
            "query": query,
            "available": {
                "alloys": list(alloys.keys()),
                "fluxes": ["ROL0", "ROL1", "ROM1", "REL0", "ORH1"],
                "topics": ["safety", "quality"]
            }
        }, ensure_ascii=False, indent=2))
        sys.exit(1)

    print(f"По запросу «{query}» точных стандартов не найдено. Доступные ключи:")
    print("  Сплавы: " + ", ".join(alloys.keys()))
    print("  Флюсы: ROL0, ROL1, ROM1, REL0, ORH1")
    print("  Справочники: safety, quality")
    sys.exit(1)


if __name__ == "__main__":
    main()
