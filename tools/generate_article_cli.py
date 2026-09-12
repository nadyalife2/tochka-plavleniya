#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CLI-инструмент для запуска ИИ-генерации статей «Точка Плавления».

Примеры использования:
1. Тестовая генерация в локальный HTML (без отправки в WP):
   python tools/generate_article_cli.py --topic "Пайка QFN микросхем с термалпадом" --dry-run

2. Публикация готовой статьи в черновики WordPress:
   python tools/generate_article_cli.py --topic "Диагностика интерметаллических интерметаллидов BGA" --wp-url "https://ваш-сайт.рф" --wp-user "admin" --wp-pass "xxxx xxxx xxxx xxxx" --status draft
"""

import os
import sys
import argparse
import json
from ai_article_generator import GeminiArticleGenerator, WordPressPublisher, save_local_preview


def main():
    parser = argparse.ArgumentParser(description="Автоматическая генерация статей для блога «Точка Плавления» через Gemini API.")
    parser.add_argument("--topic", type=str, required=True, help="Тема статьи или ключевой запрос (напр. 'Пайка BGA видеочипов')")
    parser.add_argument("--audience", type=str, default="Инженеры СЦ и радиолюбители", help="Целевая аудитория")
    parser.add_argument("--model", type=str, default="gemini-2.5-flash", help="Модель Gemini (gemini-2.5-flash, gemini-1.5-flash)")
    parser.add_argument("--dry-run", action="store_true", help="Не публиковать в WordPress, только сохранить локально JSON и HTML")
    parser.add_argument("--output-dir", type=str, default="generated_articles", help="Папка для сохранения сгенерированных материалов")
    
    # WordPress параметры
    parser.add_argument("--wp-url", type=str, default=os.environ.get("WP_URL", ""), help="URL сайта WordPress (напр. http://localhost:8080)")
    parser.add_argument("--wp-user", type=str, default=os.environ.get("WP_USER", ""), help="Логин пользователя WordPress")
    parser.add_argument("--wp-pass", type=str, default=os.environ.get("WP_PASS", ""), help="Пароль приложения WordPress (Application Password)")
    parser.add_argument("--status", type=str, default="draft", choices=["draft", "publish", "pending"], help="Статус публикации в WordPress")

    args = parser.parse_args()

    api_key = os.environ.get("GEMINI_API_KEY")
    if not api_key:
        print("⚠️ ВНИМАНИЕ: Переменная окружения GEMINI_API_KEY не найдена!")
        print("💡 Получите бесплатный API ключ на https://aistudio.google.com/ и выполните:")
        print("   set GEMINI_API_KEY=ваш_ключ   (Windows)")
        print("   export GEMINI_API_KEY=ваш_ключ (Linux/Mac)")
        sys.exit(1)

    print(f"\n🚀 Запуск ИИ-генерации статьи на тему: «{args.topic}»...")
    generator = GeminiArticleGenerator(api_key=api_key, model=args.model)
    
    try:
        article = generator.generate_article(args.topic, target_audience=args.audience)
    except Exception as e:
        print(f"❌ Ошибка генерации статьи: {e}")
        sys.exit(1)

    print(f"\n✨ Статья успешно сгенерирована!")
    print(f"📌 Заголовок: {article.get('title')}")
    print(f"⏱️ Время чтения: {article.get('reading_time')} мин")
    print(f"🔬 Сплав: {article.get('solder_alloy')}")
    print(f"🌡️ Диапазон T: {article.get('temp_range')}")

    # Создаем папку вывода
    os.makedirs(args.output_dir, exist_ok=True)
    slug = article.get("slug", "article")
    json_path = os.path.join(args.output_dir, f"{slug}.json")
    html_path = os.path.join(args.output_dir, f"{slug}.html")

    # Сохраняем JSON
    with open(json_path, "w", encoding="utf-8") as f:
        json.dump(article, f, ensure_ascii=False, indent=2)
    print(f"💾 JSON сохранен: {json_path}")

    # Сохраняем HTML предпросмотр
    save_local_preview(article, html_path)

    # Публикация в WordPress
    if not args.dry_run and args.wp_url and args.wp_user and args.wp_pass:
        print(f"\n🌐 Публикация в WordPress ({args.wp_url}) со статусом [{args.status}]...")
        publisher = WordPressPublisher(args.wp_url, args.wp_user, args.wp_pass)
        try:
            res = publisher.publish_article(article, status=args.status)
            print(f"🎉 Статья опубликована! ID: {res.get('id')} | Ссылка: {res.get('link')}")
        except Exception as e:
            print(f"❌ Ошибка публикации в WordPress: {e}")
    else:
        if args.dry_run:
            print("\n🔍 Режим Dry-Run: публикация в WordPress пропущена.")
        else:
            print("\n💡 Для автоматической публикации в WordPress укажите --wp-url, --wp-user и --wp-pass.")


if __name__ == "__main__":
    main()
