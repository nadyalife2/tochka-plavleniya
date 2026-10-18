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
        description="Генерация статьи и создание черновика WordPress."
    )
    parser.add_argument("--topic", required=True)
    parser.add_argument("--audience", default="Новички в пайке")
    parser.add_argument("--model", default="gemini-2.5-flash")
    parser.add_argument("--dry-run", action="store_true")
    parser.add_argument("--output-dir", default="generated_articles")
    parser.add_argument("--wp-url", default=os.environ.get("WP_URL", ""))
    parser.add_argument("--wp-user", default=os.environ.get("WP_USER", ""))
    parser.add_argument("--wp-pass", default=os.environ.get("WP_PASS", ""))
    parser.add_argument(
        "--status",
        default="draft",
        choices=["draft", "pending"],
        help="Автоматическая публикация запрещена.",
    )

    args = parser.parse_args()

    api_key = os.environ.get("GEMINI_API_KEY")
    if not api_key:
        print("GEMINI_API_KEY не найден.", file=sys.stderr)
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

    if args.dry_run:
        print(f"Dry-run завершён. JSON: {json_path}")
        return 0

    if not (args.wp_url and args.wp_user and args.wp_pass):
        print("Для создания черновика укажите WP_URL, WP_USER и WP_PASS.", file=sys.stderr)
        return 1

    try:
        publisher = WordPressPublisher(args.wp_url, args.wp_user, args.wp_pass)
        response = publisher.publish_article(article, status=args.status)
    except Exception as error:
        print(f"Ошибка создания черновика WordPress: {error}", file=sys.stderr)
        return 1

    print(
        "Черновик создан. "
        f"ID: {response.get('id')} | Статус: {response.get('status')}"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
