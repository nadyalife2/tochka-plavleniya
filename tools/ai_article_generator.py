#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
🔬 ИИ-Генератор технических статей «Точка Плавления» (ТЧП)
Использует бесплатный Google Gemini API (2.5 Flash / 1.5 Flash)
и автоматически публикует структурированные статьи в WordPress через REST API.
"""

import os
import sys
import json
import urllib.request
import urllib.parse
import base64
from typing import Dict, Any, Optional

# Системный промпт с инженерным контекстом и правилами верстки
SYSTEM_INSTRUCTION = """Ты — ведущий инженер-технолог по монтажу и пайке радиоэлектронной аппаратуры (РЭА) лаборатории «ТОЧКА ПЛАВЛЕНИЯ».
Твоя задача: писать исчерпывающие, глубокие, научно-практические и полезные статьи для инженеров, мастеров сервисных центров и радиолюбителей.

ПРАВИЛА И СТАНДАРТЫ:
1. Никакой «воды», общих фраз или банальностей. Сразу к сути, цифрам, температурам и физико-химическим процессам.
2. Все параметры должны соответствовать международным стандартам IPC (IPC-A-610, IPC-7095, J-STD-004, J-STD-020) и ГОСТ (ГОСТ 21931).
3. Используй точные марки сплавов (ПОС-61, SAC305, Sn42Bi58, сплав Розе), флюсов (RMA, No-Clean, WS) и оборудования (Quick, JBC, Hakko, Aixun).

ТРЕБОВАНИЯ К HTML ВЕРСТКЕ (Используй классы дизайн-системы ТЧП):
- Выделения маркером: <mark class="marker-yellow">важный текст</mark>
- Инженерные плашки параметров: <span class="sketch-pill-yellow">T = 217°C</span> или <span class="sketch-pill-gray">SAC305</span>
- Блоки предупреждений:
  <div class="border-l-4 border-ink bg-paper-subtle/70 p-4 my-4 rounded-r">
    <div class="font-mono text-xs font-bold uppercase text-ink flex items-center gap-1.5">
      <span class="material-symbols-outlined text-[15px] text-brand-orange">warning</span>
      <span>ВАЖНО / РЕГЛАМЕНТ IPC</span>
    </div>
    <p class="text-sm text-ink-muted mt-1">Текст предупреждения...</p>
  </div>
- Таблицы характеристик:
  <div class="overflow-x-auto my-6">
    <table class="w-full text-left font-mono text-xs border-collapse">
      <thead><tr class="border-b border-paper-border bg-paper-subtle text-ink"><th class="p-2.5">Параметр</th><th class="p-2.5">Значение</th><th class="p-2.5">Допуск</th></tr></thead>
      <tbody class="divide-y divide-paper-border text-ink-muted">...</tbody>
    </table>
  </div>
- Пошаговые фазы: разделы с <h2 id="step-1" class="text-xl font-bold text-ink mt-8 mb-3">1. Название раздела</h2>

ФОРМАТ ВЫВОДА:
Ты ДОЛЖЕН вернуть ТОЛЬКО валидный JSON-объект следующей структуры (без лишнего текста вокруг markdown):
{
  "title": "Точный технический заголовок статьи",
  "slug": "url-slug-latinicey",
  "excerpt": "Краткое инженерное резюме статьи (2-3 предложения для сниппета и лида)",
  "category": "Категория (BGA Монтаж / SMD Компоненты / Флюсы и Химия / Оборудование)",
  "tags": ["BGA", "SAC305", "Термопрофиль"],
  "reading_time": 7,
  "difficulty": "Инженерный уровень (или Базовый / Профессиональный)",
  "solder_alloy": "SAC305 / ПОС-61",
  "temp_range": "217°C – 245°C",
  "tools_needed": [
    {"name": "Термовоздушная станция", "model": "Quick 861DW", "purpose": "Бесконтактный локальный прогрев"},
    {"name": "Флюс-гель безотмывочный", "model": "Cyberflux RMA-218", "purpose": "Удаление оксидов без отмывки"}
  ],
  "specs_summary": [
    {"label": "Температура ликвидуса", "value": "217°C"},
    {"label": "Скорость нагрева (Ramp-up)", "value": "1-3°C / сек"}
  ],
  "content_html": "<p class=\"lead text-lg font-serif italic text-ink\">...</p><section id=\"step-1\">...</section>",
  "faq": [
    {"q": "Вопрос 1?", "a": "Развернутый технический ответ 1."},
    {"q": "Вопрос 2?", "a": "Развернутый технический ответ 2."}
  ]
}
"""


class GeminiArticleGenerator:
    """Генератор статей на базе Google Gemini API (бесплатный тариф)"""

    def __init__(self, api_key: Optional[str] = None, model: str = "gemini-2.5-flash"):
        self.api_key = api_key or os.environ.get("GEMINI_API_KEY", "")
        self.model = model
        self.api_url = f"https://generativelanguage.googleapis.com/v1beta/models/{self.model}:generateContent"

    def generate_article(self, topic: str, target_audience: str = "Инженеры СЦ и радиолюбители") -> Dict[str, Any]:
        """Генерирует полную структурированную статью по заданной теме"""
        if not self.api_key:
            raise ValueError(
                "GEMINI_API_KEY не указан! Получите бесплатный ключ в https://aistudio.google.com/ "
                "и установите переменную окружения GEMINI_API_KEY или передайте в конструктор."
            )

        user_prompt = f"""Напиши исчерпывающую техническую статью для портала «ТОЧКА ПЛАВЛЕНИЯ».
ТЕМА СТАТЬИ: {topic}
ЦЕЛЕВАЯ АУДИТОРИЯ: {target_audience}

Включи:
1. Физико-химические основы процесса.
2. Температурные режимы и допуски по стандартам IPC.
3. Пошаговый практический алгоритм работы.
4. Ошибки, дефекты пайки и способы их предотвращения.
5. Блок FAQ из 3-4 вопросов.
6. Рекомендуемый список проверенных инструментов и химии.

Верни ответ строго в формате JSON."""

        payload = {
            "contents": [
                {
                    "parts": [
                        {"text": SYSTEM_INSTRUCTION},
                        {"text": user_prompt}
                    ]
                }
            ],
            "generationConfig": {
                "temperature": 0.3,
                "topP": 0.95,
                "responseMimeType": "application/json"
            }
        }

        url = f"{self.api_url}?key={self.api_key}"
        req = urllib.request.Request(
            url,
            data=json.dumps(payload).encode("utf-8"),
            headers={"Content-Type": "application/json"}
        )

        try:
            with urllib.request.urlopen(req, timeout=60) as response:
                res_body = response.read().decode("utf-8")
                res_json = json.loads(res_body)
                
                # Извлекаем текст ответа
                candidates = res_json.get("candidates", [])
                if not candidates:
                    raise RuntimeError("Gemini API вернул пустой список кандидатов.")
                
                raw_text = candidates[0]["content"]["parts"][0]["text"]
                article_data = json.loads(raw_text)
                return article_data

        except urllib.error.HTTPError as e:
            err_msg = e.read().decode("utf-8", errors="ignore")
            raise RuntimeError(f"Ошибка Gemini API (HTTP {e.code}): {err_msg}")


class WordPressPublisher:
    """Публикатор статей в WordPress через WP REST API"""

    def __init__(self, site_url: str, username: str, app_password: str):
        self.site_url = site_url.rstrip("/")
        self.username = username
        self.app_password = app_password
        self.auth_header = "Basic " + base64.b64encode(f"{username}:{app_password}".encode()).decode()

    def publish_article(self, article: Dict[str, Any], status: str = "draft") -> Dict[str, Any]:
        """Публикует статью в WordPress со всеми кастомными мета-полями"""
        endpoint = f"{self.site_url}/wp-json/wp/v2/posts"

        # Формируем мета-поля
        meta = {
            "tchp_reading_time": int(article.get("reading_time", 5)),
            "tchp_difficulty": str(article.get("difficulty", "Инженерный уровень")),
            "tchp_solder_alloy": str(article.get("solder_alloy", "ПОС-61 / SAC305")),
            "tchp_temp_range": str(article.get("temp_range", "")),
            "tchp_tools_needed": json.dumps(article.get("tools_needed", []), ensure_ascii=False),
            "tchp_faq_items": json.dumps(article.get("faq", []), ensure_ascii=False)
        }

        # Тело запроса к WordPress
        wp_payload = {
            "title": article["title"],
            "slug": article.get("slug", ""),
            "excerpt": article.get("excerpt", ""),
            "content": article["content_html"],
            "status": status,
            "meta": meta
        }

        req = urllib.request.Request(
            endpoint,
            data=json.dumps(wp_payload).encode("utf-8"),
            headers={
                "Content-Type": "application/json",
                "Authorization": self.auth_header
            }
        )

        try:
            with urllib.request.urlopen(req, timeout=30) as response:
                res_body = response.read().decode("utf-8")
                return json.loads(res_body)
        except urllib.error.HTTPError as e:
            err_msg = e.read().decode("utf-8", errors="ignore")
            raise RuntimeError(f"Ошибка WordPress REST API (HTTP {e.code}): {err_msg}")


def save_local_preview(article_data: Dict[str, Any], output_path: str) -> None:
    """Сохраняет локальный HTML-файл для предварительного просмотра"""
    html_content = f"""<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>{article_data.get('title', 'Предпросмотр статьи')}</title>
  <link rel="stylesheet" href="../site2/assets/css/fonts.css">
  <link rel="stylesheet" href="../site2/assets/css/build.css">
</head>
<body class="p-8 max-w-4xl mx-auto bg-[#faf8f5]">
  <h1 class="text-3xl font-bold mb-4">{article_data.get('title')}</h1>
  <div class="mb-4 text-sm font-mono text-gray-600">
    Сложность: <b>{article_data.get('difficulty')}</b> | Сплав: <b>{article_data.get('solder_alloy')}</b> | Время: <b>{article_data.get('reading_time')} мин</b>
  </div>
  <div class="prose max-w-none">
    {article_data.get('content_html')}
  </div>
</body>
</html>"""
    with open(output_path, "w", encoding="utf-8") as f:
        f.write(html_content)
    print(f"✅ Локальный предпросмотр сохранен: {output_path}")


if __name__ == "__main__":
    print("🔬 Модуль генератора статей ТЧП загружен.")
    print("Используйте CLI: python tools/generate_article_cli.py --topic 'Тема статьи'")
