#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
ИИ-генератор технических статей «Точка Плавления» (ТЧП).
Использует Google Gemini API и создаёт черновики WordPress через REST API.

Важно: этот модуль не публикует статьи автоматически. Он создаёт только draft.
"""

import os
import sys
import json
import re
import hashlib
import urllib.request
import urllib.error
import base64
from typing import Dict, Any, Optional

CANONICAL_ALLOYS = {
    "pos61",
    "sn63pb37",
    "pos40",
    "sac305",
    "sac405",
    "sn42bi58",
    "snbiago",
    "roze",
    "vuda",
    "universal",
}

ALLOY_ALIASES = {
    "wood": "vuda",
    "rose": "roze",
}

ALLOWED_DIFFICULTY = {"Базовый", "Инженер", "Эксперт"}

SYSTEM_INSTRUCTION = """Ты — практический автор инженерных материалов для сайта «Точка Плавления».

Пиши по утверждённому голосу Practico:
- Читатель — новичок по знаниям, но коллега по достоинству.
- Формула абзаца: термин → механизм одной фразой → наблюдение или последствие.
- Используй наблюдаемые признаки: цвет, блеск, текучесть, дым, звук, время.
- Разбирай ошибки по схеме: симптом → причина → решение.
- Не выдумывай личный опыт, фотографии, измерения или историю работы в мастерской.
- Не используй обещания результата, хайп, FOMO и пустые похвалы без механизма.
- Не используй бытовые и кулинарные метафоры.

Технические правила:
- Используй только подтверждённые данные из предоставленного брифа и источников.
- Не придумывай температуру, марку, модель, цену или категоричный совет.
- Если критичный факт не подтверждён, используй безопасную нейтральную формулировку.
- Для каждого материала указывай канонический идентификатор сплава из разрешённого списка.

GEO-правила:
- Начинай раздел с прямого содержательного ответа.
- Делай разделы самодостаточными и цитируемыми.
- Используй понятные термины и объясняй аббревиатуры при первом упоминании.
- Добавляй FAQ только для реальных вопросов читателя.
- Не создавай FAQ ради разметки.

Формат вывода: только валидный JSON без Markdown-ограждений.
"""


class ContractValidationError(ValueError):
    """Статья не соответствует обязательному контракту TCHP."""


def normalize_alloy(value: Any) -> str:
    raw = str(value or "").strip().lower()
    raw = ALLOY_ALIASES.get(raw, raw)
    return raw if raw in CANONICAL_ALLOYS else ""


def normalize_difficulty(value: Any) -> str:
    raw = str(value or "").strip()
    if raw in ALLOWED_DIFFICULTY:
        return raw
    lowered = raw.lower()
    if "баз" in lowered or "нович" in lowered:
        return "Базовый"
    if "эксперт" in lowered or "профессиональн" in lowered:
        return "Эксперт"
    return "Инженер"


def clean_text(value: Any, max_length: int) -> str:
    text = re.sub(r"<[^>]+>", "", str(value or ""))
    text = re.sub(r"\s+", " ", text).strip()
    return text[:max_length]


def validate_article_contract(article: Dict[str, Any]) -> None:
    required = [
        "title",
        "slug",
        "excerpt",
        "solder_alloy",
        "temp_kind",
        "temp_range",
        "tools",
        "content_html",
    ]

    missing = [field for field in required if not article.get(field)]
    if missing:
        raise ContractValidationError(
            "Отсутствуют обязательные поля контракта: " + ", ".join(missing)
        )

    slug = str(article["slug"]).strip()
    if not re.fullmatch(r"[a-z0-9]+(?:-[a-z0-9]+)*", slug):
        raise ContractValidationError(
            "slug должен состоять из строчных латинских букв, цифр и дефисов."
        )

    alloy = normalize_alloy(article["solder_alloy"])
    if not alloy:
        raise ContractValidationError(
            "solder_alloy должен быть одним из канонических идентификаторов: "
            + ", ".join(sorted(CANONICAL_ALLOYS))
        )

    if article["temp_kind"] not in {
        "station_setpoint",
        "joint_temperature",
        "reflow_profile",
    }:
        raise ContractValidationError(
            "temp_kind должен быть station_setpoint, joint_temperature или reflow_profile."
        )

    if not isinstance(article["tools"], list):
        raise ContractValidationError("tools должен быть массивом.")

    for index, tool in enumerate(article["tools"]):
        if not isinstance(tool, dict) or not clean_text(tool.get("name"), 150):
            raise ContractValidationError(
                f"tools[{index}].name обязателен и не может быть пустым."
            )

    if not isinstance(article.get("faq", []), list):
        raise ContractValidationError("faq должен быть массивом.")

    for index, item in enumerate(article["faq"]):
        if not isinstance(item, dict):
            raise ContractValidationError(f"faq[{index}] должен быть объектом.")
        if not clean_text(item.get("q"), 250) or not clean_text(item.get("a"), 2000):
            raise ContractValidationError(
                f"faq[{index}] должен содержать непустые q и a."
            )

    if not isinstance(article.get("sources", []), list):
        raise ContractValidationError("sources должен быть массивом.")

    for index, source in enumerate(article["sources"]):
        if not isinstance(source, dict) or not clean_text(source.get("title"), 200):
            raise ContractValidationError(
                f"sources[{index}].title обязателен и не может быть пустым."
            )

    if len(clean_text(article["content_html"], 10 ** 9)) < 100:
        raise ContractValidationError("content_html слишком короткий.")


def normalize_article(article: Dict[str, Any]) -> Dict[str, Any]:
    """Приводит ответ модели к контракту статьи TCHP."""
    normalized = dict(article)
    normalized["slug"] = str(normalized.get("slug", "")).strip().lower()
    normalized["solder_alloy"] = normalize_alloy(normalized.get("solder_alloy"))
    normalized["difficulty"] = normalize_difficulty(normalized.get("difficulty"))

    temp_data = normalized.get("temp_data")
    if isinstance(temp_data, dict):
        normalized["temp_kind"] = normalized.get("temp_kind") or temp_data.get("kind") or "joint_temperature"
        normalized["temp_range"] = clean_text(normalized.get("temp_range") or temp_data.get("range"), 100)
        normalized["temp_peak"] = clean_text(normalized.get("temp_peak") or temp_data.get("peak"), 100)
    else:
        normalized["temp_kind"] = normalized.get("temp_kind") or "joint_temperature"
        normalized["temp_range"] = clean_text(normalized.get("temp_range"), 100)
        normalized["temp_peak"] = clean_text(normalized.get("temp_peak"), 100)

    std_data = normalized.get("standard_data")
    if isinstance(std_data, dict):
        normalized["standard"] = clean_text(normalized.get("standard") or std_data.get("standard"), 120)
        normalized["standard_class"] = clean_text(normalized.get("standard_class") or std_data.get("class"), 60)
        normalized["standard_description"] = clean_text(normalized.get("standard_description") or std_data.get("description"), 255)
    else:
        normalized["standard"] = clean_text(normalized.get("standard"), 120)
        normalized["standard_class"] = clean_text(normalized.get("standard_class"), 60)
        normalized["standard_description"] = clean_text(normalized.get("standard_description"), 255)

    normalized["temp_data"] = {
        "kind": normalized["temp_kind"],
        "range": normalized["temp_range"],
        "peak": normalized["temp_peak"],
    }

    tools = []
    for tool in normalized.get("tools") or normalized.get("tools_needed") or []:
        if isinstance(tool, dict):
            name = clean_text(tool.get("name"), 150)
            if not name:
                continue
            tools.append(
                {
                    "name": name,
                    "model": clean_text(tool.get("model"), 100),
                    "purpose": clean_text(tool.get("purpose"), 250),
                }
            )
    normalized["tools"] = tools

    faq = []
    for item in normalized.get("faq") or []:
        if isinstance(item, dict):
            question = clean_text(item.get("q"), 250)
            answer = clean_text(item.get("a"), 2000)
            if question and answer:
                faq.append({"q": question, "a": answer})
    normalized["faq"] = faq

    sources = []
    for source in normalized.get("sources") or []:
        if isinstance(source, dict):
            title = clean_text(source.get("title"), 200)
            if not title:
                continue
            sources.append(
                {
                    "title": title,
                    "standard": clean_text(source.get("standard"), 120),
                    "url": clean_text(source.get("url"), 500),
                }
            )
    normalized["sources"] = sources

    normalized["schema_version"] = "1.0"
    normalized["content_id"] = normalized.get("content_id") or (
        "tchp-" + hashlib.sha1(normalized["slug"].encode("utf-8")).hexdigest()[:12]
    )

    validate_article_contract(normalized)
    return normalized


class GeminiArticleGenerator:
    """Генератор статей на базе Google Gemini API."""

    def __init__(self, api_key: Optional[str] = None, model: str = "gemini-2.5-flash"):
        self.api_key = api_key or os.environ.get("GEMINI_API_KEY", "")
        self.model = model
        self.api_url = (
            "https://generativelanguage.googleapis.com/v1beta/models/"
            f"{self.model}:generateContent"
        )

    def generate_article(self, topic: str, target_audience: str = "Новички в пайке") -> Dict[str, Any]:
        if not self.api_key:
            raise ValueError("GEMINI_API_KEY не указан.")

        user_prompt = f"""Напиши техническую статью для портала «Точка Плавления».

ТЕМА: {topic}
АУДИТОРИЯ: {target_audience}

Обязательные поля JSON:
- title, slug, excerpt
- category_slug: start | materialy | praktika | oshibki
- tags: массив непустых строк
- reading_time: целое число от 1 до 60
- difficulty: Базовый | Инженер | Эксперт
- solder_alloy: один канонический ID
- temp_kind: station_setpoint | joint_temperature | reflow_profile
- temp_range, temp_peak
- tools: массив объектов name, model, purpose
- sources: массив объектов title, standard, url
- content_html: HTML статьи
- faq: массив объектов q, a

Если материал не требует выбора одного сплава, используй "universal".
Не выдумывай источники. Если точного источника нет, не указывай URL.
"""

        payload = {
            "contents": [
                {
                    "parts": [
                        {"text": SYSTEM_INSTRUCTION},
                        {"text": user_prompt},
                    ]
                }
            ],
            "generationConfig": {
                "temperature": 0.3,
                "topP": 0.95,
                "responseMimeType": "application/json",
            },
        }

        request = urllib.request.Request(
            f"{self.api_url}?key={self.api_key}",
            data=json.dumps(payload).encode("utf-8"),
            headers={"Content-Type": "application/json"},
        )

        try:
            with urllib.request.urlopen(request, timeout=60) as response:
                response_body = json.loads(response.read().decode("utf-8"))
        except urllib.error.HTTPError as error:
            details = error.read().decode("utf-8", errors="ignore")
            raise RuntimeError(
                f"Ошибка Gemini API (HTTP {error.code}): {details}"
            ) from error

        candidates = response_body.get("candidates", [])
        if not candidates:
            raise RuntimeError("Gemini API вернул пустой список кандидатов.")

        raw_text = candidates[0]["content"]["parts"][0]["text"]
        article = json.loads(raw_text)
        return normalize_article(article)


class WordPressPublisher:
    """Создаёт черновик WordPress из статьи, соответствующей контракту TCHP."""

    ALLOWED_STATUSES = {"draft", "pending"}

    def __init__(self, site_url: str, username: str, app_password: str):
        self.site_url = site_url.rstrip("/")
        self.username = username
        self.app_password = app_password
        self.auth_header = "Basic " + base64.b64encode(
            f"{username}:{app_password}".encode()
        ).decode()

    def build_payload(self, article: Dict[str, Any], status: str = "draft") -> Dict[str, Any]:
        if status not in self.ALLOWED_STATUSES:
            raise ValueError(
                "Автоматическая доставка разрешает только draft или pending."
            )

        article = normalize_article(article)

        meta = {
            "tchp_content_id": article["content_id"],
            "tchp_schema_version": article["schema_version"],
            "tchp_reading_time": int(article.get("reading_time", 5)),
            "tchp_difficulty": article["difficulty"],
            "tchp_solder_alloy": article["solder_alloy"],
            "tchp_temp_kind": article["temp_kind"],
            "tchp_temp_range": article["temp_range"],
            "tchp_temp_peak": article.get("temp_peak", ""),
            "tchp_standard": article.get("standard", ""),
            "tchp_standard_class": article.get("standard_class", ""),
            "tchp_standard_description": article.get("standard_description", ""),
            "tchp_tools": article["tools"],
            "tchp_faq_items": article["faq"],
            "tchp_sources": article["sources"],
        }

        return {
            "title": article["title"],
            "slug": article["slug"],
            "excerpt": article["excerpt"],
            "content": article["content_html"],
            "status": status,
            "meta": meta,
        }

    def publish_article(self, article: Dict[str, Any], status: str = "draft") -> Dict[str, Any]:
        payload = self.build_payload(article, status=status)
        endpoint = f"{self.site_url}/wp-json/wp/v2/posts"
        request = urllib.request.Request(
            endpoint,
            data=json.dumps(payload).encode("utf-8"),
            headers={
                "Content-Type": "application/json",
                "Authorization": self.auth_header,
            },
        )

        try:
            with urllib.request.urlopen(request, timeout=30) as response:
                return json.loads(response.read().decode("utf-8"))
        except urllib.error.HTTPError as error:
            details = error.read().decode("utf-8", errors="ignore")
            raise RuntimeError(
                f"Ошибка WordPress REST API (HTTP {error.code}): {details}"
            ) from error


def save_local_preview(article_data: Dict[str, Any], output_path: str) -> None:
    html_content = f"""<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>{article_data.get('title', 'Предпросмотр статьи')}</title>
</head>
<body>
<h1>{article_data.get('title', '')}</h1>
<div>{article_data.get('content_html', '')}</div>
</body>
</html>"""

    with open(output_path, "w", encoding="utf-8") as file:
        file.write(html_content)


if __name__ == "__main__":
    print("Модуль генератора статей TCHP загружен.")
