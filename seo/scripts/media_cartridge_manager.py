#!/usr/bin/env python3
"""Модуль управления картриджами весов (LoRA/GGUF) и медиа-контентом (SEO-Geo, Иллюстрации)
для сайта «Точка Плавления».

1. Управление картриджами (Енот Рокки):
   - Инспекция пула версий в cartridges/
   - Развертывание картриджей в мобильный кластер Triad-Omni (Node-1 Mi 10T / Node-2 Nord N10)
   - Контроль квот памяти (LRU rolling cache)

2. Медиа & Иллюстрации (Сорока Клара & Барсук Бруно):
   - 4 слота статьи: Hero-обложка, Макро-сравнение, Схема-инфографика, Каталог дефектов
   - Промпт №1 (Базовый FLUX / Midjourney / Recraft)
   - SEO-метаданные (Alt, Title, Caption) и GEO-теги мастерской
   - Оценка загруженного изображения (ОТК)
   - Промпт №2 (Уточняющий / Refinement) с исправлением артефактов
"""
from __future__ import annotations

import json
import os
import shutil
import subprocess
from pathlib import Path
from typing import Any, Dict, List, Optional

ROOT = Path(__file__).resolve().parents[2]
WORKSPACE_NORD = Path(r"d:\для one plus nord n10")
ADB_PATH = Path(r"C:\Users\MSI\AppData\Local\Android\Sdk\platform-tools\adb.exe")

CARTRIDGE_DIRS = [
    WORKSPACE_NORD / "cartridges",
    ROOT / "cartridges"
]

GEO_PRESETS = {
    "spb_main": {
        "city": "Санкт-Петербург",
        "location_name": "Центральная радиолаборатория пайки «Точка Плавления»",
        "lat": 59.934280,
        "lon": 30.335099,
        "iptc_copyright": "© Tochka-Plavleniya.ru (CC BY-SA 4.0)"
    },
    "msc_mitino": {
        "city": "Москва",
        "location_name": "Испытательный стенд монтажа плат, Митино",
        "lat": 55.845320,
        "lon": 37.362140,
        "iptc_copyright": "© Tochka-Plavleniya.ru (CC BY-SA 4.0)"
    }
}


def list_cartridges() -> List[Dict[str, Any]]:
    """Возвращает список всех доступных GGUF/LoRA картриджей."""
    cartridges = []
    seen = set()

    # Проверяем active_cartridge.json
    active_slug = ""
    active_cfg = WORKSPACE_NORD / "config" / "active_cartridge.json"
    if active_cfg.exists():
        try:
            with open(active_cfg, "r", encoding="utf-8") as f:
                data = json.load(f)
                active_slug = data.get("slug") or data.get("active_version", "")
        except Exception:
            pass

    for cdir in CARTRIDGE_DIRS:
        if not cdir.exists():
            continue
        for item in cdir.glob("*"):
            if item.name.startswith(".") or item.name in seen:
                continue
            seen.add(item.name)
            size_mb = round(item.stat().st_size / (1024 * 1024), 2) if item.is_file() else 0
            is_active = (item.stem == active_slug or item.name == active_slug)
            cartridges.append({
                "name": item.name,
                "path": str(item),
                "size_mb": size_mb,
                "is_active": is_active,
                "type": "zip_bundle" if item.suffix == ".zip" else "gguf_lora",
                "target_node": "Node-1 (Mi 10T 5G, :8080)"
            })

    # Если пусто, добавляем базовый картридж лаборатории
    if not cartridges:
        cartridges.append({
            "name": "cartridge_solder_v4.7.zip",
            "path": str(WORKSPACE_NORD / "cartridges" / "cartridge.omni.zip"),
            "size_mb": 45.2,
            "is_active": True,
            "type": "zip_bundle",
            "target_node": "Node-1 (Mi 10T 5G, :8080)"
        })

    return cartridges


def deploy_cartridge_to_node1(cartridge_name: str) -> Dict[str, Any]:
    """Развертывает картридж на Node-1 (Mi 10T 5G) через ADB."""
    if not ADB_PATH.exists():
        return {"status": "error", "message": f"ADB не найден по пути {ADB_PATH}"}

    target_file = None
    for cdir in CARTRIDGE_DIRS:
        cand = cdir / cartridge_name
        if cand.exists():
            target_file = cand
            break

    if not target_file:
        return {"status": "error", "message": f"Файл картриджа {cartridge_name} не найден в каталогах"}

    device_id = "192.168.31.161:5555"
    remote_dest = f"/data/data/com.termux/files/home/cartridges/{cartridge_name}"

    try:
        cmd = [str(ADB_PATH), "-s", device_id, "push", str(target_file), remote_dest]
        res = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
        if res.returncode == 0:
            # Обновляем active_cartridge.json
            active_cfg = WORKSPACE_NORD / "config" / "active_cartridge.json"
            active_cfg.parent.mkdir(parents=True, exist_ok=True)
            with open(active_cfg, "w", encoding="utf-8") as f:
                json.dump({"active_cartridge": cartridge_name, "slug": target_file.stem, "deployed_at": str(Path(__file__))}, f, indent=2)
            return {"status": "ok", "message": f"Картридж {cartridge_name} успешно прошит на Node-1!"}
        else:
            return {"status": "error", "message": f"Ошибка ADB push: {res.stderr}"}
    except Exception as e:
        return {"status": "error", "message": f"Исключение при прошивке: {str(e)}"}


def generate_article_media_pack(topic_title: str, solder_alloy: str = "ПОС-61 (183–190°C)") -> Dict[str, Any]:
    """Генерирует полный медиа-план из 4 слотов с промптами, SEO и гео-разметкой."""
    geo = GEO_PRESETS["spb_main"]
    
    slots = {
        "slot_1_hero": {
            "title": "Слот 1: Hero-обложка статьи (16:9)",
            "purpose": "Главное изображение сразу под заголовком H1. Захватывает внимание, задает атмосферу радиолаборатории.",
            "aspect_ratio": "16:9 (1200x675 px)",
            "recommended_model": "FLUX.1-schnell / Midjourney v6.1",
            "prompt_1": (
                f"Cinematic ultra-detailed macro photography of professional soldering workbench in high-tech dark lab. "
                f"Close-up on soldering iron with glowing heated T12 chisel tip, delicate blue silicone ESD heat-resistant mat, "
                f"modern printed circuit board with real copper traces, green solder mask, spool of {solder_alloy} tin-lead solder wire, "
                f"translucent golden amber pine rosin block with subtle melted puddle. Soft atmospheric 2700K warm halogen side light "
                f"and crisp 5000K white LED spot on the joint, shallow depth of field, natural bokeh, no cartoon, 8k resolution, photorealistic."
            ),
            "negative_prompt": "blurry, out of focus, distorted PCB, fake runes, extra fingers, cartoon, plastic 3D render, oversaturated blue lasers, messy wires, AI artifacts",
            "seo_meta": {
                "alt": f"{topic_title} — правильное оборудование и подготовка рабочего места",
                "title": f"Руководство: {topic_title} в лаборатории Точка Плавления",
                "caption": f"Подготовка рабочего места и разогрев жала для безопасного монтажа по ГОСТ Р МЭК 61191-1.",
                "geo_tag": f"{geo['city']}, {geo['location_name']} [{geo['lat']}, {geo['lon']}]"
            }
        },
        "slot_2_macro": {
            "title": "Слот 2: Макро-структура паяного шва (10x зум)",
            "purpose": "Иллюстрация раздела выбора припоя и формы галтели. Демонстрирует разницу между идеальной пайкой и дефектом.",
            "aspect_ratio": "4:3 (1024x768 px)",
            "recommended_model": "FLUX.1-dev / SDXL Solder-LoRA",
            "prompt_1": (
                f"Extreme 10x macro photography of an electronic soldered joint on PCB through microscope lens. "
                f"Textbook concave silver solder meniscus covering 80% of component through-hole pin, smooth mirror-like surface of {solder_alloy}, "
                f"30 degree contact angle, ring of clean transparent golden rosin flux around the pad, zero cracks, zero oxidation. "
                f"Scientific industrial macro photo, ring LED illumination, razor sharp details, textbook IPC-A-610 Class 3 acceptance standard."
            ),
            "negative_prompt": "cold joint, dull grey solder, cracked pad, solder bridge, icicle spike, burned black flux, blurry microscope lens, distorted electronics",
            "seo_meta": {
                "alt": f"Макроструктура паяного соединения {solder_alloy} с правильным вогнутым мениском",
                "title": f"Идеальный вогнутый мениск пайки по стандарту IPC-A-610",
                "caption": f"Микроструктура эвтектического шва {solder_alloy}: контактный угол смачивания менее 30 градусов.",
                "geo_tag": f"{geo['city']}, {geo['location_name']}"
            }
        },
        "slot_3_diagram": {
            "title": "Слот 3: Инфографика шагов пайки (1.5-2 сек тайминг)",
            "purpose": "Наглядная пошаговая схема для новичка: одновременный прогрев и подача припоя.",
            "aspect_ratio": "16:9 (1200x675 px)",
            "recommended_model": "Recraft.ai v3 (Vector/Infographic) / FLUX.1-schnell",
            "prompt_1": (
                "Clean technical infographic diagram on dark slate charcoal background (#18181D). "
                "Three sequential horizontal panels: Step 1 (Heat pad 1.5s, soldering tip touching copper pad and component leg), "
                "Step 2 (Feed solder wire 0.5s from opposite side into thermal bridge), Step 3 (Remove solder then remove iron tip 1.0s). "
                "Crisp white and amber technical callouts, temperature corridor labels (270C-300C), clean minimalist schematic drawing, high contrast vector UI."
            ),
            "negative_prompt": "cluttered, illegible text, handwritten messy lines, 3D childish cartoon, bright purple neon, fuzzy low resolution",
            "seo_meta": {
                "alt": f"Пошаговая схема пайки радиодетали паяльником за 2 секунды",
                "title": f"Инфографика тайминга пайки: прогрев, подача припоя, остывание",
                "caption": "Физика трехсекундного контакта: исключение перегрева текстолита и растворения медного пятачка.",
                "geo_tag": f"{geo['city']}, {geo['location_name']}"
            }
        },
        "slot_4_defect": {
            "title": "Слот 4: Каталог ошибок новичка (Анти-примеры)",
            "purpose": "Разбор граблей: перегрев дорожки (отслоение) и перемычка (solder bridge).",
            "aspect_ratio": "4:3 (1024x768 px)",
            "recommended_model": "FLUX.1-schnell",
            "prompt_1": (
                "Technical diagnostic split macro photo showing common beginner soldering errors. "
                "Left side: severely overheated PCB trace with lifted copper delaminating from charred brown FR-4 fiberglass. "
                "Right side: accidental solder bridge short-circuiting two adjacent pins of surface mount integrated circuit. "
                "Harsh direct LED inspection light, forensic electronics quality analysis, clear fault indicators, realistic workshop macro."
            ),
            "negative_prompt": "fantasy explosion, cartoon smoke, perfect board, generic motherboard stock photo, CGI blur, glowing magic wires",
            "seo_meta": {
                "alt": "Типичные ошибки при пайке плат: перегрев дорожки и случайная перемычка припоем",
                "title": "Дефекты монтажа: почему отслаивается медь и как убрать перемычку оплеткой",
                "caption": "Отслоение печатного проводника при температуре жала свыше 330°C и времени контакта более 4 секунд.",
                "geo_tag": f"{geo['city']}, {geo['location_name']}"
            }
        }
    }
    
    return {
        "status": "ok",
        "topic": topic_title,
        "solder_alloy": solder_alloy,
        "slots": slots
    }


def refine_image_prompt_iteration(slot_key: str, user_critique: str, base_prompt: str) -> Dict[str, Any]:
    """Генерирует Промпт №2 (уточняющий) с исправлениями на основе замечаний пользователя."""
    corrections = []
    critique_lower = user_critique.lower()
    
    if "слишком темно" in critique_lower or "темно" in critique_lower:
        corrections.append("increase ambient workshop lighting, clear visibility on all circuit components, brighter LED worklight")
    if "не макро" in critique_lower or "ближе" in critique_lower or "зум" in critique_lower:
        corrections.append("magnified 15x macro zoom, extreme close-up on solder fillet, showing individual flux crystallization rings")
    if "жало" in critique_lower or "паяльник" in critique_lower:
        corrections.append("replace conical needle with exact Hakko T12-2.4D chisel tip, metallic oxidation patina, realistic heater collar")
    if "мультяшно" in critique_lower or "не реалистично" in critique_lower or "ai" in critique_lower:
        corrections.append("shot on Sony A7R IV with 90mm f/2.8 Macro G OSS lens, raw authentic documentary photo, 100% tangible physical textures")
    if "флюс" in critique_lower or "канифоль" in critique_lower:
        corrections.append("add natural honey-amber translucent rosin splatter droplets around the pad, realistic sticky resin sheen")

    if not corrections:
        corrections.append(f"incorporate specific user adjustment: {user_critique.strip()}, emphasize authentic electronics workbench precision")

    refined_prompt = base_prompt + " " + ", ".join(corrections)
    
    return {
        "status": "ok",
        "slot": slot_key,
        "critique": user_critique,
        "prompt_2": refined_prompt,
        "advice_from_clara": (
            "Сорока Клара скорректировала промпт! Я добавила оптические характеристики макро-линзы Sony 90mm Macro, "
            "усилила детализацию канифоли и четко прописала геометрию клиновидного жала T12. Скопируй Промпт №2 для новой генерации!"
        )
    }


def evaluate_uploaded_image(file_name: str, file_size_kb: int, width: int = 1200, height: int = 675) -> Dict[str, Any]:
    """Оценка загруженной картинки Филином [ОТК-ГОСТ]."""
    score = 100
    reasons = []

    # Проверка формата
    ext = file_name.split(".")[-1].lower()
    if ext not in ["webp", "png", "jpg", "jpeg"]:
        score -= 40
        reasons.append(f"Неподдерживаемый формат .{ext}. Требуется WebP или PNG.")

    # Проверка размера
    if file_size_kb > 500:
        score -= 20
        reasons.append(f"Размер {file_size_kb} КБ великоват для Core Web Vitals (рекомендовано до 250 КБ). Рекомендуется WebP сжатие.")
    elif file_size_kb < 30:
        score -= 30
        reasons.append(f"Размер {file_size_kb} КБ слишком мал — возможно, сильное сжатие или пикселированное превью.")

    # Проверка разрешения
    if width < 800:
        score -= 25
        reasons.append(f"Ширина {width}px недостаточна для Retina-экранов и мобильного зума (минимум 1200px для Hero).")

    is_passed = score >= 75
    return {
        "status": "ok",
        "file_name": file_name,
        "score": score,
        "passed": is_passed,
        "verdict": "Принято [ОТК-ГОСТ]" if is_passed else "Требуются правки по качеству",
        "reasons": reasons if reasons else ["Изображение соответствует стандартам качества Точка Плавления ✅"]
    }


if __name__ == "__main__":
    print("Проверка media_cartridge_manager:")
    print("Картриджи:", list_cartridges())
    pack = generate_article_media_pack("Пайка плат паяльником для начинающих", "ПОС-61 (183–190°C)")
    print("Сгенерировано медиа-слотов:", len(pack["slots"]))
    ref = refine_image_prompt_iteration("slot_1_hero", "слишком темно, сделай ближе макро и покажи жало клином", pack["slots"]["slot_1_hero"]["prompt_1"])
    print("Промпт №2 сформирован:", len(ref["prompt_2"]))
