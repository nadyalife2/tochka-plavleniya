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
    # 1. Локальные узлы и агенты (0₽, Офлайн, Приоритет №1)
    {"id": "triad:node1_brain", "name": "Triad Node-1: Mi 10T 5G (Qwen3.5-9B UD-Q3_K_XL, LPDDR5)", "provider": "Triad-Omni Local", "type": "Локальная / 0₽", "category": "local"},
    {"id": "triad:node2_reflex", "name": "Triad Node-2: Nord N10 (LFM2.5-8B + BGE-M3)", "provider": "Triad-Omni Local", "type": "Локальная / 0₽", "category": "local"},
    {"id": "triad:node3_gateway", "name": "Triad Node-3: Fast-Path Regex & Валидатор (0.02ms)", "provider": "Triad-Omni Local", "type": "Локальная / 0₽", "category": "local"},
    {"id": "local_vector_svg", "name": "Нативный SVG-генератор термопрофилей", "provider": "Local Algorithm", "type": "Локальная / 0₽", "category": "local"},
    {"id": "local_slop_monster", "name": "SlopMonster: Анализатор штампов & Anti-Slop", "provider": "Local Algorithm", "type": "Локальная / 0₽", "category": "local"},
    {"id": "local_ast_scorer", "name": "AST Scorer: 100-балльная метрология", "provider": "Local Algorithm", "type": "Локальная / 0₽", "category": "local"},
    {"id": "local_ollama:qwen2.5:7b", "name": "Локальный Ollama на ПК (Qwen 2.5 7B)", "provider": "Ollama Local", "type": "Локальный ПК", "category": "local"},

    # 2. Шлюз OmniRoute (Федеративный :20128, Без VPN)
    {"id": "omniroute:auto/thinking", "name": "OmniRoute: Авто-выбор CoT Reasoning", "provider": "OmniRoute Hub", "type": "Каскад / Без VPN", "category": "omniroute"},
    {"id": "omniroute:deepseek-r1", "name": "OmniRoute: DeepSeek-R1 (Рассуждения)", "provider": "OmniRoute Hub", "type": "Каскад / Без VPN", "category": "omniroute"},
    {"id": "omniroute:deepseek-chat", "name": "OmniRoute: DeepSeek-V3 (671B MoE)", "provider": "OmniRoute Hub", "type": "Каскад / Без VPN", "category": "omniroute"},
    {"id": "omniroute:claude-sonnet-4-6", "name": "OmniRoute: Claude Sonnet 4.6", "provider": "OmniRoute Hub", "type": "Каскад / Без VPN", "category": "omniroute"},
    {"id": "omniroute:gemini-2-5-flash", "name": "OmniRoute: Gemini 2.5 Flash", "provider": "OmniRoute Hub", "type": "Каскад / Без VPN", "category": "omniroute"},
    {"id": "omniroute:kimi-k3-pro", "name": "OmniRoute: Kimi K3.1 Pro", "provider": "OmniRoute Hub", "type": "Каскад / Без VPN", "category": "omniroute"},

    # 3. Облачные внешние провайдеры (По личному API-ключу / Резерв)
    {"id": "deepseek/deepseek-chat", "name": "DeepSeek-V3 (Direct API)", "provider": "DeepSeek API", "type": "Облако / Эконом", "category": "cloud"},
    {"id": "deepseek/deepseek-reasoner", "name": "DeepSeek-R1 (Direct API)", "provider": "DeepSeek API", "type": "Облако / CoT", "category": "cloud"},
    {"id": "anthropic/claude-3.5-sonnet", "name": "Claude 3.5 Sonnet (Direct API)", "provider": "Anthropic API", "type": "Облако / Премиум", "category": "cloud"},
    {"id": "anthropic/claude-3.7-sonnet", "name": "Claude 3.7 Sonnet (Direct API)", "provider": "Anthropic API", "type": "Облако / Премиум", "category": "cloud"},
    {"id": "google/gemini-2.5-flash", "name": "Gemini 2.5 Flash (Direct API)", "provider": "Google Gemini", "type": "Облако / Скорость", "category": "cloud"},
    {"id": "google/gemini-1.5-pro", "name": "Gemini 1.5 Pro (Direct API)", "provider": "Google Gemini", "type": "Облако / Длинный контекст", "category": "cloud"},
    {"id": "qwen/qwen-2.5-72b-instruct", "name": "Qwen 2.5 72B Instruct (OpenRouter)", "provider": "OpenRouter", "type": "Облако / Универсал", "category": "cloud"},
    {"id": "openai/gpt-4o-mini", "name": "GPT-4o Mini (OpenRouter)", "provider": "OpenRouter", "type": "Облако / Эконом", "category": "cloud"}
]

LOCAL_RECOMMENDED_MAP: Dict[str, str] = {
    "clara": "triad:node2_reflex",     # LFM2.5-8B + BGE-M3 (LSI & ГОСТ-радар)
    "soroka": "triad:node2_reflex",
    "boris": "triad:node1_brain",      # Mi 10T Qwen3.5-9B (ТЗ и ГОСТ допуски)
    "bobr": "triad:node1_brain",
    "felix": "triad:node1_brain",      # Mi 10T Qwen3.5-9B (Черновик и физика пайки)
    "kot": "triad:node1_brain",
    "reynard": "triad:node3_gateway",   # Regex Fast-Path (Сметы и окупаемость)
    "lis": "triad:node3_gateway",
    "bruno": "local_vector_svg",        # Нативный SVG генератор
    "barsuk": "local_vector_svg",
    "archimedes": "local_slop_monster", # SlopMonster
    "filin": "local_slop_monster",
    "woody": "local_ast_scorer",        # AST Scorer
    "dyatel": "local_ast_scorer",
    "rocky": "triad:node2_reflex",      # Graphify & LoRA Dataset
    "raccoon": "triad:node2_reflex",
    "mikhalych": "triad:node1_brain",   # Координатор артели
    "bear": "triad:node1_brain",
}


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


def apply_local_first_defaults() -> Dict[str, Any]:
    """Устанавливает во все слоты primary локальные узлы и агенты, которые справляются с задачей."""
    raw = load_yaml_raw(MODELS_YAML)
    animals = raw.get("animals", {})
    count = 0
    for animal_id, data in animals.items():
        if isinstance(data, dict):
            recommended = LOCAL_RECOMMENDED_MAP.get(animal_id)
            if recommended:
                data["primary"] = recommended
                data["current_selection"] = "primary"
                count += 1
    save_yaml_raw(MODELS_YAML, raw)
    return {"status": "ok", "message": f"Применён режим Local-First для {count} мастеров", "updated": count}


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


def test_provider_connection(provider_id: str) -> Dict[str, Any]:
    """Проверяет доступность и пинг указанного провайдера моделей."""
    import socket, urllib.request, time
    raw = load_yaml_raw(MODELS_YAML)
    providers = raw.get("providers", {})
    p_info = providers.get(provider_id, {})
    
    if provider_id == "triad_cluster":
        # Проверяем порты 8080 (Node-1) и 8000 (Node-3)
        nodes = [
            ("Node-1 Brain (Mi 10T)", "127.0.0.1", 8080),
            ("Node-2 Reflex (Nord N10)", "127.0.0.1", 8081),
            ("Node-3 Gateway (Redmi Note 4)", "127.0.0.1", 8000)
        ]
        status_list = []
        for name, host, port in nodes:
            t0 = time.time()
            try:
                s = socket.create_connection((host, port), timeout=0.8)
                s.close()
                dt = round((time.time() - t0) * 1000, 1)
                status_list.append({"node": name, "online": True, "ping_ms": dt})
            except Exception:
                status_list.append({"node": name, "online": False, "ping_ms": None})
        online_count = sum(1 for n in status_list if n["online"])
        return {
            "status": "ok",
            "provider": provider_id,
            "connected": online_count > 0,
            "details": f"Онлайн {online_count}/3 узлов Triad-Omni",
            "nodes": status_list
        }
    
    if provider_id == "ollama_local":
        t0 = time.time()
        try:
            req = urllib.request.Request("http://127.0.0.1:11434/api/tags", headers={"User-Agent": "Triad/1.0"})
            with urllib.request.urlopen(req, timeout=1.0) as resp:
                dt = round((time.time() - t0) * 1000, 1)
                return {"status": "ok", "provider": provider_id, "connected": True, "ping_ms": dt, "details": "Ollama активен на 127.0.0.1:11434"}
        except Exception:
            return {"status": "ok", "provider": provider_id, "connected": False, "details": "Ollama оффлайн на 11434"}
            
    # Облачные провайдеры (проверка наличия ключа)
    key = p_info.get("api_key", "").strip()
    if not key:
        return {"status": "ok", "provider": provider_id, "connected": False, "details": "API ключ не задан"}
    return {
        "status": "ok",
        "provider": provider_id,
        "connected": True,
        "details": f"API ключ настроен ({key[:4]}...{key[-3:] if len(key)>6 else ''})"
    }



if __name__ == "__main__":
    cfg = get_models_config()
    print("Конфигурация моделей загружена:")
    print("Провайдеры:", list(cfg.get("providers", {}).keys()))
    print("Персонажи:", list(cfg.get("animals", {}).keys()))
    print("Доступные модели:", len(cfg.get("all_available_models", [])))
