"""Единая загрузка API-ключей проекта из одного файла.

Где лежат ключи:
    config/api-keys.local.yaml     ← ОСНОВНОЙ файл, который правит владелец
                                     (вне git, добавлен в .gitignore)

Порядок поиска значения:
    1. переменная окружения (удобно для CI и разовых запусков);
    2. config/api-keys.local.yaml;
    3. ~/.dsh/.credentials.yaml (секция refs) — исторический запасной путь.

Значения НИКОГДА не печатаются: функция describe() показывает только факт
наличия и длину, чтобы можно было диагностировать конфигурацию без утечки.

Использование:
    from keys import get_key, require_key, describe

    token = require_key("MUTAGEN_API_KEY")
    print(describe())
"""
from __future__ import annotations

import os
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
LOCAL_KEYS = ROOT / "config" / "api-keys.local.yaml"
CREDENTIALS = Path(os.path.expanduser("~")) / ".dsh" / ".credentials.yaml"

_CACHE: dict[str, str] | None = None


def _parse_flat_yaml(path: Path) -> dict[str, str]:
    """Читает простой YAML вида `KEY: value` (комментарии и пустые строки пропускаются)."""
    out: dict[str, str] = {}
    if not path.exists():
        return out
    for line in path.read_text(encoding="utf-8").splitlines():
        s = line.strip()
        if not s or s.startswith("#"):
            continue
        m = re.match(r"^([A-Za-z_][A-Za-z0-9_]*)\s*:\s*(.*)$", s)
        if not m:
            continue
        value = m.group(2).strip().strip("\"'")
        if value and not value.upper().startswith("ВПИШИТЕ"):
            out[m.group(1)] = value
    return out


def _parse_credentials_refs(path: Path) -> dict[str, str]:
    """Достаёт секцию refs из ~/.dsh/.credentials.yaml."""
    out: dict[str, str] = {}
    if not path.exists():
        return out
    in_refs = False
    for line in path.read_text(encoding="utf-8").splitlines():
        if line.startswith("refs:"):
            in_refs = True
            continue
        if in_refs:
            m = re.match(r"^\s{2}([A-Z_][A-Z0-9_]*)\s*:\s*(\S+)\s*$", line)
            if m:
                out[m.group(1)] = m.group(2)
    return out


def _load() -> dict[str, str]:
    global _CACHE
    if _CACHE is None:
        merged = _parse_credentials_refs(CREDENTIALS)
        merged.update(_parse_flat_yaml(LOCAL_KEYS))  # локальный файл важнее
        _CACHE = merged
    return _CACHE


def get_key(name: str, default: str = "") -> str:
    """Возвращает значение ключа или пустую строку."""
    env = os.environ.get(name)
    if env:
        return env.strip()
    return _load().get(name, default)


def require_key(name: str) -> str:
    """Возвращает ключ или завершает работу с понятным сообщением (без вывода значения)."""
    value = get_key(name)
    if not value:
        raise SystemExit(
            f"❌ Не найден ключ {name}.\n"
            f"   Откройте {LOCAL_KEYS} и впишите значение справа от {name}:"
        )
    return value


def describe() -> str:
    """Диагностика: какие ключи заполнены. Значения не раскрываются."""
    keys = _load()
    names = sorted(set(keys) | {
        "MUTAGEN_API_KEY", "YANDEX_AI_API_KEY", "YANDEX_FOLDER_ID",
        "OPENROUTER_API_KEY", "DASHSCOPE_API_KEY", "PERPLEXITY_API_KEY", "DEEPSEEK_API_KEY",
    })
    lines = [f"Файл ключей: {LOCAL_KEYS}", f"Существует: {LOCAL_KEYS.exists()}", ""]
    for n in names:
        v = keys.get(n, "")
        status = f"заполнен ({len(v)} симв.)" if v else "ПУСТО"
        lines.append(f"  {n:<22} {status}")
    return "\n".join(lines)


if __name__ == "__main__":
    print(describe())
