#!/usr/bin/env python3
"""Барсук-Иллюстратор (05-illustrator): Генератор визуальных инженерных материалов.

Создает:
1. Векторные SVG-схемы термопрофилей пайки и диаграммы сплавов (0 токенов, Karpathy-style).
2. Спецификации и промпты для Hero-обложек (OLED dark, #FFC700, кибердек-стиль).
3. Интегрирует media в контракт статьи по схеме v1.2 / v1.1.
"""
from __future__ import annotations

import json
import re
from pathlib import Path
from datetime import datetime
import canonical_alloys as canonical
from db import get_connection

ROOT = Path(__file__).resolve().parents[2]
SCHEMATICS_DIR = ROOT / "theme4" / "assets" / "schematics"
IMAGES_DIR = ROOT / "media" / "articles"


def generate_alloy_reflow_svg(alloy_key: str, title: str, melt_temp: int, peak_temp: int) -> str:
    """Генерирует чистый, высокоточный SVG график термопрофиля пайки."""
    label = canonical.alloy_label(alloy_key)
    return f"""<svg viewBox="0 0 720 340" class="w-full h-auto" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Термопрофиль пайки для {label}">
  <title>Технологический термопрофиль пайки: {label}</title>
  <defs>
    <linearGradient id="curveGrad_{alloy_key}" x1="0%" y1="100%" x2="0%" y2="0%">
      <stop offset="0%" stop-color="#FFC700" stop-opacity="0.05"/>
      <stop offset="70%" stop-color="#FFC700" stop-opacity="0.3"/>
      <stop offset="100%" stop-color="#FF4500" stop-opacity="0.8"/>
    </linearGradient>
    <filter id="glow" x="-20%" y="-20%" width="140%" height="140%">
      <feGaussianBlur stdDeviation="3" result="blur" />
      <feComposite in="SourceGraphic" in2="blur" operator="over" />
    </filter>
  </defs>

  <!-- Фон и сетка -->
  <rect width="720" height="340" fill="#121214" rx="8"/>
  <rect x="60" y="30" width="620" height="250" fill="#18181B" stroke="#27272A" stroke-width="1" rx="4"/>
  
  <!-- Горизонтальные линии температур -->
  <line x1="60" y1="230" x2="680" y2="230" stroke="#3F3F46" stroke-dasharray="3 3"/>
  <text x="50" y="234" fill="#71717A" font-size="11" font-family="monospace" text-anchor="end">100°C</text>
  
  <line x1="60" y1="160" x2="680" y2="160" stroke="#3F3F46" stroke-dasharray="3 3"/>
  <text x="50" y="164" fill="#71717A" font-size="11" font-family="monospace" text-anchor="end">150°C</text>
  
  <!-- Линия ликвидуса сплава -->
  <line x1="60" y1="105" x2="680" y2="105" stroke="#E11D48" stroke-dasharray="4 2" stroke-width="1.2"/>
  <text x="50" y="109" fill="#FB7185" font-size="11" font-family="monospace" text-anchor="end">{melt_temp}°C</text>
  <text x="670" y="100" fill="#FB7185" font-size="10" font-family="sans-serif" text-anchor="end">Ликвидус {label} ({melt_temp}°C)</text>

  <!-- Зоны профиля -->
  <line x1="180" y1="30" x2="180" y2="280" stroke="#27272A" stroke-width="1"/>
  <line x1="360" y1="30" x2="360" y2="280" stroke="#27272A" stroke-width="1"/>
  <line x1="520" y1="30" x2="520" y2="280" stroke="#27272A" stroke-width="1"/>
  
  <text x="120" y="50" fill="#A1A1AA" font-size="11" font-family="sans-serif" text-anchor="middle">1. Нагрев (1–3°C/c)</text>
  <text x="270" y="50" fill="#A1A1AA" font-size="11" font-family="sans-serif" text-anchor="middle">2. Активация флюса</text>
  <text x="440" y="50" fill="#FFC700" font-size="11" font-family="sans-serif" text-anchor="middle" font-weight="bold">3. Оплавление (TAL)</text>
  <text x="600" y="50" fill="#A1A1AA" font-size="11" font-family="sans-serif" text-anchor="middle">4. Охлаждение</text>

  <!-- Кривая профиля -->
  <path d="M 60 270 Q 120 200, 180 160 T 360 140 Q 420 120, 440 60 Q 460 60, 520 130 T 660 270"
        fill="url(#curveGrad_{alloy_key})" opacity="0.6"/>
  <path d="M 60 270 Q 120 200, 180 160 T 360 140 Q 420 120, 440 60 Q 460 60, 520 130 T 660 270"
        stroke="#FFC700" stroke-width="3" stroke-linecap="round" filter="url(#glow)"/>

  <!-- Пиковая точка -->
  <circle cx="450" cy="60" r="5" fill="#FF4500" stroke="#FFF" stroke-width="1.5"/>
  <rect x="400" y="15" width="100" height="22" rx="4" fill="#27272A" stroke="#FF4500" stroke-width="1"/>
  <text x="450" y="30" fill="#FFC700" font-size="11" font-family="monospace" font-weight="bold" text-anchor="middle">ПИК: {peak_temp}°C</text>

  <!-- Подписи осей -->
  <text x="370" y="315" fill="#71717A" font-size="11" font-family="sans-serif" text-anchor="middle">Время технологического цикла (секунды по стандарту IPC/JEDEC J-STD-020)</text>
  <text x="60" y="330" fill="#A1A1AA" font-size="10" font-family="monospace">Точка Плавления · Барсук-Иллюстратор v1.2</text>
</svg>"""


def design_visual_package_for_topic(topic_id: int) -> dict:
    """Генерирует полный комплект визуальных материалов для темы #ID."""
    SCHEMATICS_DIR.mkdir(parents=True, exist_ok=True)
    IMAGES_DIR.mkdir(parents=True, exist_ok=True)

    with get_connection() as conn:
        row = conn.execute("SELECT id, title, slug, content_markdown FROM topics WHERE id = ?", (topic_id,)).fetchone()
        if not row:
            return {"status": "error", "message": f"Тема #{topic_id} не найдена"}

    title = row["title"]
    slug = row["slug"] or f"topic-{topic_id}"
    text = row["content_markdown"] or ""

    # 1. Детектируем сплав по реестру
    alloy_key, _ = canonical.detect_alloy(text, title)
    melt_r = canonical.melt_range(alloy_key)
    work_r = canonical.work_range(alloy_key)

    melt_temp = melt_r[1] if melt_r else 183
    peak_temp = (work_r[1] if work_r else 260)

    # 2. Генерируем векторную SVG-схему
    svg_filename = f"{slug}-reflow-profile.svg"
    svg_path = SCHEMATICS_DIR / svg_filename
    svg_code = generate_alloy_reflow_svg(alloy_key, title, melt_temp, peak_temp)
    svg_path.write_text(svg_code, encoding="utf-8")

    # 3. Формируем спецификацию для Hero-баннера
    hero_prompt = (
        f"Macro professional photography of a modern high-end soldering station workbench, "
        f"focused on soldering iron tip with molten {canonical.alloy_label(alloy_key)} solder joint, "
        f"clean dark PCB board with gold contacts, subtle amber smoke rising (#FFC700), "
        f"oscilloscope and multimeter out-of-focus in dark cyberpunk background (#121214), "
        f"clean studio lighting, 8k resolution, technical editorial style, no humans, cinematic look."
    )
    hero_url = f"/assets/images/hero-{slug}.webp"

    # 4. Собираем контрактный media-блок
    media_data = {
        "hero_image": {
            "url": hero_url,
            "alt": f"Инженерный монтаж и пайка сплавом {canonical.alloy_label(alloy_key)}: {title}",
            "width": 1200,
            "height": 630,
            "prompt": hero_prompt
        },
        "illustrations": [
            {
                "type": "inline_svg",
                "caption": f"Технологический температурный профиль оплавления для {canonical.alloy_label(alloy_key)} ({melt_temp}–{peak_temp} °C)",
                "content_or_url": f"/assets/schematics/{svg_filename}"
            }
        ]
    }

    # 5. Сохраняем в БД и привязываем
    with get_connection() as conn:
        conn.execute(
            "UPDATE topics SET media_json = ?, updated_at = ? WHERE id = ?",
            (json.dumps(media_data, ensure_ascii=False), datetime.now().isoformat(timespec="seconds"), topic_id)
        )
        conn.commit()

    return {
        "status": "success",
        "topic_id": topic_id,
        "alloy": alloy_key,
        "svg_file": str(svg_path.relative_to(ROOT)),
        "media": media_data,
        "summary": f"✓ Отрисована векторная схема {svg_filename} ({melt_temp}–{peak_temp} °C) и сформирован промпт для Hero-обложки."
    }


if __name__ == "__main__":
    import sys
    tid = int(sys.argv[1]) if len(sys.argv) > 1 else 1
    res = design_visual_package_for_topic(tid)
    print(json.dumps(res, ensure_ascii=False, indent=2))
