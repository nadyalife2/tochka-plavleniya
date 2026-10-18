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


from pathlib import Path

ROOT_DIR = Path(__file__).resolve().parent.parent


def validate_article_contract(article: Dict[str, Any]) -> None:
    """
    Строго валидирует статью исключительно по канонической схеме schemas/article-contract-v1.schema.json.
    Никаких ручных послаблений или обходов схемы не допускается.
    """
    try:
        import jsonschema
    except ImportError as e:
        raise RuntimeError(
            "Для строгой валидации контракта требуется библиотека jsonschema. "
            "Установите зависимость: pip install jsonschema"
        ) from e

    schema_path = ROOT_DIR / "schemas" / "article-contract-v1.schema.json"
    if not schema_path.exists():
        raise FileNotFoundError(
            f"Критическая ошибка конфигурации: файл канонической схемы не найден по пути '{schema_path}'."
        )

    with open(schema_path, "r", encoding="utf-8") as f:
        schema = json.load(f)

    try:
        jsonschema.validate(instance=article, schema=schema)
    except jsonschema.ValidationError as err:
        path = " -> ".join(str(p) for p in err.absolute_path) or "root"
        raise ContractValidationError(
            f"Статья нарушает строгий контракт схемы [{path}]: {err.message}"
        ) from err


def normalize_article(article: Dict[str, Any]) -> Dict[str, Any]:
    """
    Приводит ответ модели к канонической форме контракта TCHP (schemas/article-contract-v1.schema.json).
    Температурные данные и стандарты сохраняются строго во вложенных объектах temp_data и standard_data;
    плоские вспомогательные ключи удаляются из корня для соблюдения additionalProperties: false.

    Внимание: выдуманные значения по умолчанию для физических параметров (температура, сплав)
    категорически запрещены. При отсутствии обязательных параметров возбуждается ContractValidationError.
    """
    normalized: Dict[str, Any] = {}

    slug = clean_text(article.get("slug", ""), 100).strip().lower()
    if not slug:
        title_for_slug = clean_text(article.get("title", ""), 100)
        if not title_for_slug:
            raise ContractValidationError("Не указан slug или title статьи для генерации идентификаторов.")
        slug = "statya-" + hashlib.sha1(title_for_slug.encode("utf-8")).hexdigest()[:8]
    normalized["slug"] = slug
    normalized["schema_version"] = "1.0"
    normalized["content_id"] = article.get("content_id") or (
        "tchp-" + hashlib.sha1(slug.encode("utf-8")).hexdigest()[:12]
    )
    if "content_hash" in article:
        normalized["content_hash"] = str(article["content_hash"])

    title = clean_text(article.get("title", ""), 200)
    if not title:
        raise ContractValidationError("Не указан обязательный заголовок title.")
    normalized["title"] = title

    excerpt = clean_text(article.get("excerpt", ""), 400)
    if not excerpt:
        raise ContractValidationError("Не указан обязательный лид (аннотация) excerpt.")
    normalized["excerpt"] = excerpt

    content_html = str(article.get("content_html", "")).strip()
    if not content_html:
        raise ContractValidationError("Не указано тело статьи content_html.")
    normalized["content_html"] = content_html

    cat_slug = str(article.get("category_slug", "praktika")).strip().lower()
    if cat_slug not in {"start", "materialy", "praktika", "oshibki"}:
        cat_slug = "praktika"
    normalized["category_slug"] = cat_slug

    raw_tags = article.get("tags") or []
    if isinstance(raw_tags, str):
        raw_tags = [t.strip() for t in raw_tags.split(",") if t.strip()]
    clean_tags = []
    for t in raw_tags:
        cleaned_t = clean_text(t, 50)
        if len(cleaned_t) >= 2 and cleaned_t not in clean_tags:
            clean_tags.append(cleaned_t)
    normalized["tags"] = clean_tags or ["Пайка"]

    try:
        r_time = int(article.get("reading_time", 5))
        normalized["reading_time"] = max(1, min(60, r_time))
    except (ValueError, TypeError):
        normalized["reading_time"] = 5

    normalized["difficulty"] = normalize_difficulty(article.get("difficulty"))

    # Сплав: строгая проверка без подмены
    alloy = normalize_alloy(article.get("solder_alloy"))
    if not alloy:
        raise ContractValidationError(
            f"Не указан или не опознан канонический идентификатор solder_alloy: '{article.get('solder_alloy')}'. "
            f"Разрешены: {', '.join(sorted(CANONICAL_ALLOYS))}"
        )
    normalized["solder_alloy"] = alloy

    # Вложенный объект temp_data (без выдуманных дефолтов)
    raw_temp = article.get("temp_data") if isinstance(article.get("temp_data"), dict) else {}
    t_kind = raw_temp.get("kind") or article.get("temp_kind")
    if not t_kind:
        raise ContractValidationError(
            "Не указан обязательный параметр temp_data.kind. "
            "Разрешены: station_setpoint, joint_temperature, reflow_profile."
        )
    if t_kind not in {"station_setpoint", "joint_temperature", "reflow_profile"}:
        raise ContractValidationError(
            f"Недопустимый temp_data.kind: '{t_kind}'. "
            "Разрешены: station_setpoint, joint_temperature, reflow_profile."
        )

    t_range = clean_text(raw_temp.get("range") or article.get("temp_range", ""), 100)
    if not t_range:
        raise ContractValidationError(
            "Не указан обязательный параметр temp_data.range. Уточните режим по исследовательскому брифу."
        )
    t_peak = clean_text(raw_temp.get("peak") or article.get("temp_peak", ""), 100)

    temp_obj: Dict[str, str] = {
        "kind": t_kind,
        "range": t_range,
    }
    if t_peak:
        temp_obj["peak"] = t_peak
    normalized["temp_data"] = temp_obj

    # Вложенный объект standard_data (без плоских ключей в корне)
    raw_std = article.get("standard_data") if isinstance(article.get("standard_data"), dict) else {}
    std_name = clean_text(raw_std.get("standard") or article.get("standard", ""), 120)
    std_class = clean_text(raw_std.get("class") or article.get("standard_class", ""), 60)
    std_desc = clean_text(raw_std.get("description") or article.get("standard_description", ""), 255)

    if std_name:
        std_obj: Dict[str, str] = {"standard": std_name}
        if std_class:
            std_obj["class"] = std_class
        if std_desc:
            std_obj["description"] = std_desc
        normalized["standard_data"] = std_obj

    tools = []
    raw_tools = article.get("tools") or article.get("tools_needed") or []
    for tool in raw_tools if isinstance(raw_tools, list) else []:
        if isinstance(tool, dict):
            name = clean_text(tool.get("name"), 150)
            if not name:
                continue
            item = {"name": name}
            if tool.get("model"):
                item["model"] = clean_text(tool.get("model"), 100)
            if tool.get("purpose"):
                item["purpose"] = clean_text(tool.get("purpose"), 250)
            tools.append(item)
    normalized["tools"] = tools

    faq = []
    for item in article.get("faq") or []:
        if isinstance(item, dict):
            q = clean_text(item.get("q"), 250)
            a = clean_text(item.get("a"), 2000)
            if q and a:
                faq.append({"q": q, "a": a})
    if faq:
        normalized["faq"] = faq

    sources = []
    for source in article.get("sources") or []:
        if isinstance(source, dict):
            title = clean_text(source.get("title"), 200)
            if not title:
                continue
            s_item = {"title": title}
            if source.get("standard"):
                s_item["standard"] = clean_text(source.get("standard"), 120)
            if source.get("url"):
                s_item["url"] = clean_text(source.get("url"), 500)
            sources.append(s_item)
    if sources:
        normalized["sources"] = sources

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
    """
    [ЭКСПЕРИМЕНТАЛЬНЫЙ / РЕЗЕРВНЫЙ] Создаёт черновик WordPress в обход delivery_cli.py.
    Внимание: этот класс не ведёт локальный журнал, не разрешает таксономии и не выполняет read-back.
    Штатным способом доставки в архитектуре проекта является tools/delivery_cli.py.
    """

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
        temp_data = article.get("temp_data") or {}
        std_data = article.get("standard_data") or {}

        meta = {
            "tchp_content_id": article["content_id"],
            "tchp_schema_version": article.get("schema_version", "1.0"),
            "tchp_reading_time": int(article.get("reading_time", 5)),
            "tchp_difficulty": article["difficulty"],
            "tchp_solder_alloy": article["solder_alloy"],
            "tchp_temp_kind": temp_data.get("kind", "joint_temperature"),
            "tchp_temp_range": temp_data.get("range", ""),
            "tchp_temp_peak": temp_data.get("peak", ""),
            "tchp_standard": std_data.get("standard", ""),
            "tchp_standard_class": std_data.get("class", ""),
            "tchp_standard_description": std_data.get("description", ""),
            "tchp_tools": article.get("tools", []),
            "tchp_faq_items": article.get("faq", []),
            "tchp_sources": article.get("sources", []),
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
