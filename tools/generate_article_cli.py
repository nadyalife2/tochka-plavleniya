#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CLI-инструмент генерации статьи «Точка Плавления».

Создаёт только черновик WordPress. Автоматическая публикация запрещена.
"""

import argparse
import json
import os
import sys

from ai_article_generator import (
    GeminiArticleGenerator,
    WordPressPublisher,
    save_local_preview,
)


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Генерация статьи по каноническому контракту TCHP (JSON + HTML предпросмотр)."
    )
    parser.add_argument("--topic", required=True, help="Тема статьи")
    parser.add_argument("--audience", default="Новички в пайке", help="Целевая аудитория")
    parser.add_argument("--model", default="gemini-2.5-flash", help="Модель Gemini")
    parser.add_argument("--output-dir", default="generated_articles", help="Директория для сохранения JSON и HTML")
    parser.add_argument(
        "--publish-direct-experimental",
        action="store_true",
        help="[ЭКСПЕРИМЕНТАЛЬНО] Прямая отправка черновика через встроенный класс (в обход delivery_cli.py и журнала). По умолчанию отключено.",
    )
    parser.add_argument("--wp-url", default=os.environ.get("WP_URL", ""), help="URL WordPress (только для экспериментальной отправки)")
    parser.add_argument("--wp-user", default=os.environ.get("WP_USER", ""), help="Имя пользователя WordPress")
    parser.add_argument("--wp-pass", default=os.environ.get("WP_PASS", ""), help="Application Password WordPress")
    parser.add_argument(
        "--status",
        default="draft",
        choices=["draft", "pending"],
        help="Статус черновика для экспериментальной отправки (draft или pending).",
    )

    args = parser.parse_args()

    api_key = os.environ.get("GEMINI_API_KEY")
    if not api_key:
        print("GEMINI_API_KEY не найден в переменных окружения.", file=sys.stderr)
        return 1

    try:
        generator = GeminiArticleGenerator(api_key=api_key, model=args.model)
        article = generator.generate_article(
            args.topic, target_audience=args.audience
        )
    except Exception as error:
        print(f"Ошибка генерации: {error}", file=sys.stderr)
        return 1

    os.makedirs(args.output_dir, exist_ok=True)
    slug = article["slug"]

    json_path = os.path.join(args.output_dir, f"{slug}.json")
    html_path = os.path.join(args.output_dir, f"{slug}.html")

    with open(json_path, "w", encoding="utf-8") as file:
        json.dump(article, file, ensure_ascii=False, indent=2)

    save_local_preview(article, html_path)

    print("=======================================================")
    print("✅ СТАТЬЯ УСПЕШНО СГЕНЕРИРОВАНА И ВАЛИДИРОВАНА!")
    print(f"   Файл контракта (JSON): {json_path}")
    print(f"   Предпросмотр (HTML):   {html_path}")
    print("=======================================================")

    if not args.publish_direct_experimental:
        print("\nℹ️ ШТАТНЫЙ СПОСОБ ДОСТАВКИ В WORDPRESS:")
        print("   Используйте канонический публикатор delivery_cli.py (с журналом и защитой от конфликтов):")
        print(f"   python tools/delivery_cli.py send-draft --file {json_path} --wp-url <WP_URL> --wp-user <WP_USER> --wp-pass <WP_PASS>")
        return 0

    # Экспериментальный прямой путь (только по явному флагу)
    print("\n⚠️ ВНИМАНИЕ: Запущена экспериментальная прямая отправка в обход delivery_cli.py...")
    if not (args.wp_url and args.wp_user and args.wp_pass):
        print("Ошибка: для прямой отправки необходимо указать --wp-url, --wp-user и --wp-pass.", file=sys.stderr)
        return 1

    try:
        publisher = WordPressPublisher(args.wp_url, args.wp_user, args.wp_pass)
        response = publisher.publish_article(article, status=args.status)
    except Exception as error:
        print(f"Ошибка создания черновика WordPress: {error}", file=sys.stderr)
        return 1

    print(
        "Черновик создан напрямую. "
        f"ID: {response.get('id')} | Статус: {response.get('status')}"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
