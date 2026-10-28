"""Модуль работы с моделями Perplexity (через OpenRouter).

Использует существующий ключ OPENROUTER_API_KEY из ~/.dsh/.credentials.yaml.
Ищет в реальном времени по всему интернету, возвращает выжимку и ссылки.

Примеры запуска:
    python seo/scripts/perplexity.py --query "лучшие блоги по пайке электроники"
    python seo/scripts/perplexity.py --query "какие флюсы для пайки SMD рекомендуют мастера на форумах"
"""
from __future__ import annotations

import argparse
import json
import sys
import urllib.request
from pathlib import Path

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

ROOT = Path(__file__).resolve().parents[2]


def get_api_key() -> str:
    # 1. Сначала из переменной окружения
    import os
    if os.environ.get("OPENROUTER_API_KEY"):
        return os.environ["OPENROUTER_API_KEY"]
    if os.environ.get("PERPLEXITY_API_KEY"):
        return os.environ["PERPLEXITY_API_KEY"]

    # 2. Из credentials DSH
    cred_path = Path.home() / ".dsh" / ".credentials.yaml"
    if cred_path.exists():
        try:
            import yaml
            data = yaml.safe_load(cred_path.read_text(encoding="utf-8"))
            refs = data.get("refs", {})
            if refs.get("OPENROUTER_API_KEY"):
                return refs["OPENROUTER_API_KEY"]
            if refs.get("PERPLEXITY_API_KEY"):
                return refs["PERPLEXITY_API_KEY"]
        except Exception:
            pass
    return ""


def ask_perplexity(
    prompt: str,
    model: str = "perplexity/sonar",
    system_prompt: str = "Ты — инженерный ассистент по пайке и электронике. Отвечай кратко, чётко, со ссылками на источники.",
) -> str:
    api_key = get_api_key()
    if not api_key:
        raise RuntimeError("OPENROUTER_API_KEY не найден в ~/.dsh/.credentials.yaml или переменных окружения.")

    payload = {
        "model": model,
        "messages": [
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": prompt},
        ],
    }

    req = urllib.request.Request(
        "https://openrouter.ai/api/v1/chat/completions",
        data=json.dumps(payload).encode("utf-8"),
        headers={
            "Authorization": f"Bearer {api_key}",
            "Content-Type": "application/json",
            "HTTP-Referer": "https://tochka-plavleniya.ru",
            "X-Title": "Tochka Plavleniya SEO",
        },
    )

    with urllib.request.urlopen(req, timeout=40) as resp:
        data = json.loads(resp.read().decode("utf-8"))
        return data["choices"][0]["message"]["content"]


def main():
    parser = argparse.ArgumentParser(description="Поиск через Perplexity Sonar")
    parser.add_argument("--query", required=True, help="Поисковый запрос")
    parser.add_argument("--pro", action="store_true", help="Использовать Sonar Pro")
    parser.add_argument("--reasoning", action="store_true", help="Использовать Sonar Reasoning Pro")
    args = parser.parse_args()

    model = "perplexity/sonar"
    if args.reasoning:
        model = "perplexity/sonar-reasoning-pro"
    elif args.pro:
        model = "perplexity/sonar-pro"

    print(f"Поиск через {model}...")
    answer = ask_perplexity(args.query, model=model)
    print("\n" + "=" * 60)
    print(answer)
    print("=" * 60)


if __name__ == "__main__":
    main()
