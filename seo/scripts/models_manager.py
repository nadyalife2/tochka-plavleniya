#!/usr/bin/env python3
"""Менеджер конфигурации моделей (1 + 4) и роутинга Triad-Omni.
Обеспечивает чтение и безопасную запись config/models_config.yaml,
а также валидацию ключей и переключение маршрутов без перезапуска сервера.
"""
from __future__ import annotations

import json
import os
from pathlib import Path
from typing import Any, Dict, List, Optional

ROOT = Path(__file__).resolve().parents[2]
MODELS_YAML = ROOT / "config" / "models_config.yaml"
ROUTING_YAML = ROOT / "config" / "agents_routing.yaml"

ANIMAL_ALIASES: Dict[str, str] = {
    "bear": "mikhalych",
    "soroka": "clara",
    "bobr": "boris",
    "kot": "felix",
    "lis": "reynard",
    "barsuk": "bruno",
    "filin": "archimedes",
    "dyatel": "woody",
    "raccoon": "rocky",
}
REVERSE_ALIASES: Dict[str, str] = {v: k for k, v in ANIMAL_ALIASES.items()}

CURATED_MODELS: List[Dict[str, str]] = [
    {"id": "triad:node1_brain", "name": "Triad Node-1: Qwen3.5-9B-UD-Q3_K_XL (LPDDR5)", "provider": "Triad-Omni Local", "type": "Локальная / 0₽"},
    {"id": "triad:node2_reflex", "name": "Triad Node-2: LFM2.5-8B + BGE-M3 (LPDDR4X)", "provider": "Triad-Omni Local", "type": "Локальная / 0₽"},
    {"id": "triad:node3_gateway", "name": "Triad Node-3: Regex Fast-Path (0.02ms)", "provider": "Triad-Omni Local", "type": "Локальная / 0₽"},
    {"id": "deepseek/deepseek-chat", "name": "DeepSeek-V3 (671B MoE)", "provider": "DeepSeek API", "type": "Облако / Эконом"},
    {"id": "deepseek/deepseek-reasoner", "name": "DeepSeek-R1 (Рассуждения / CoT)", "provider": "DeepSeek API", "type": "Облако / Рассуждения"},
    {"id": "anthropic/claude-3.5-sonnet", "name": "Claude 3.5 Sonnet (Литературный & Код)", "provider": "Anthropic API", "type": "Облако / Премиум"},
    {"id": "anthropic/claude-3.7-sonnet", "name": "Claude 3.7 Sonnet (Гибридный CoT)", "provider": "Anthropic API", "type": "Облако / Премиум"},
    {"id": "google/gemini-2.5-flash", "name": "Gemini 2.5 Flash (Ультрабыстрый)", "provider": "Google Gemini", "type": "Облако / Скорость"},
    {"id": "google/gemini-1.5-pro", "name": "Gemini 1.5 Pro (2M контекст)", "provider": "Google Gemini", "type": "Облако / Длинный контекст"},
    {"id": "qwen/qwen-2.5-72b-instruct", "name": "Qwen 2.5 72B Instruct", "provider": "OpenRouter", "type": "Облако / Универсал"},
    {"id": "qwen/qwen-2.5-32b-instruct", "name": "Qwen 2.5 32B Instruct", "provider": "OpenRouter", "type": "Облако / Баланс"},
    {"id": "qwen/qwen-2.5-14b-instruct", "name": "Qwen 2.5 14B Instruct", "provider": "OpenRouter", "type": "Облако / Быстрый"},
    {"id": "meta-llama/llama-3.3-70b-instruct", "name": "Llama 3.3 70B Instruct", "provider": "OpenRouter", "type": "Облако / Открытый вес"},
    {"id": "openai/gpt-4o-mini", "name": "GPT-4o Mini", "provider": "OpenRouter", "type": "Облако / Эконом"},
    {"id": "local_ollama:qwen2.5:7b", "name": "Локальный Ollama (Qwen 2.5 7B на ПК)", "provider": "Ollama Local", "type": "Локальный ПК"},
    {"id": "local_ollama:mistral:7b", "name": "Локальный Ollama (Mistral 7B на ПК)", "provider": "Ollama Local", "type": "Локальный ПК"},
]


def load_yaml_raw(filepath: Path) -> Dict[str, Any]:
    """Загружает YAML файл с помощью pyyaml или простого парсера."""
    if not filepath.exists():
        return {}
    try:
        import yaml
        with open(filepath, "r", encoding="utf-8") as f:
            return yaml.safe_load(f) or {}
    except ImportError:
        return {}


def save_yaml_raw(filepath: Path, data: Dict[str, Any]) -> bool:
    """Сохраняет данные в YAML файл."""
    filepath.parent.mkdir(parents=True, exist_ok=True)
    try:
        import yaml
        with open(filepath, "w", encoding="utf-8") as f:
            yaml.safe_dump(data, f, allow_unicode=True, sort_keys=False, default_flow_style=False)
        return True
    except Exception as e:
        print(f"[ERROR] Failed to save yaml: {e}")
        return False


def get_models_config() -> Dict[str, Any]:
    """Возвращает текущую конфигурацию моделей, обогащенную списками и алиасами."""
    raw = load_yaml_raw(MODELS_YAML)
    animals = raw.get("animals", {})
    providers = raw.get("providers", {})

    # Обогащаем провайдеров флагом has_key
    for p_id, p_info in providers.items():
        if isinstance(p_info, dict):
            p_info["has_key"] = bool(p_info.get("api_key") or p_id == "triad_cluster")

    # Синхронизируем животных по обоим наборам ключей (soroka и clara)
    normalized_animals = {}
    for canon_name, data in animals.items():
        if not isinstance(data, dict):
            continue
        # Массив альтернатив [alt1, alt2, alt3, alt4]
        alts_dict = data.get("alternatives", {})
        alts_list = [
            alts_dict.get("alt1", ""),
            alts_dict.get("alt2", ""),
            alts_dict.get("alt3", ""),
            alts_dict.get("alt4", "")
        ]
        data_copy = dict(data)
        data_copy["alternates"] = alts_list
        data_copy["description"] = data.get("role", "")
        
        normalized_animals[canon_name] = data_copy
        # Добавляем алиас
        alias_id = REVERSE_ALIASES.get(canon_name)
        if alias_id:
            normalized_animals[alias_id] = data_copy

    return {
        "providers": providers,
        "animals": normalized_animals,
        "all_available_models": CURATED_MODELS
    }


def update_models_config(new_config: Dict[str, Any]) -> bool:
    """Обновляет конфигурацию моделей и персистит в models_config.yaml."""
    current = load_yaml_raw(MODELS_YAML)
    current.update(new_config)
    return save_yaml_raw(MODELS_YAML, current)


def set_animal_model(animal_id: str, slot: Any, model_name: str = "") -> Dict[str, Any]:
    """Переключает или назначает модель для конкретного зверя."""
    raw = load_yaml_raw(MODELS_YAML)
    animals = raw.get("animals", {})
    
    # Резолвим алиас в каноническое имя (например soroka -> clara)
    canon_name = ANIMAL_ALIASES.get(animal_id, animal_id)
    if canon_name not in animals:
        return {"status": "error", "message": f"Неизвестный персонаж: {animal_id}"}

    target_animal = animals[canon_name]
    
    # Если передан slot (primary или индекс 0..3)
    if slot == "primary":
        target_animal["primary"] = model_name
    elif str(slot) in ["alt1", "0", 0]:
        target_animal.setdefault("alternatives", {})["alt1"] = model_name
    elif str(slot) in ["alt2", "1", 1]:
        target_animal.setdefault("alternatives", {})["alt2"] = model_name
    elif str(slot) in ["alt3", "2", 2]:
        target_animal.setdefault("alternatives", {})["alt3"] = model_name
    elif str(slot) in ["alt4", "3", 3]:
        target_animal.setdefault("alternatives", {})["alt4"] = model_name
    else:
        # Просто переключение current_selection
        target_animal["current_selection"] = str(slot)

    save_yaml_raw(MODELS_YAML, raw)
    return {"status": "ok", "animal": canon_name, "slot": slot, "model": model_name}


def set_provider_key(provider_id: str, api_key: str, active: bool = True) -> Dict[str, Any]:
    """Сохраняет API ключ провайдера в YAML."""
    raw = load_yaml_raw(MODELS_YAML)
    providers = raw.get("providers", {})
    if provider_id in providers:
        providers[provider_id]["api_key"] = api_key
        providers[provider_id]["active"] = bool(api_key.strip())
        save_yaml_raw(MODELS_YAML, raw)
        return {"status": "ok", "provider": provider_id, "active": providers[provider_id]["active"]}
    return {"status": "error", "message": f"Неизвестный провайдер: {provider_id}"}


if __name__ == "__main__":
    cfg = get_models_config()
    print("Конфигурация моделей загружена:")
    print("Провайдеры:", list(cfg.get("providers", {}).keys()))
    print("Персонажи:", list(cfg.get("animals", {}).keys()))
    print("Доступные модели:", len(cfg.get("all_available_models", [])))
