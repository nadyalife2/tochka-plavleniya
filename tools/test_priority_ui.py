"""Проверка бальной системы приоритета в интерфейсе."""
from __future__ import annotations

import os
import sys
from playwright.sync_api import sync_playwright

errors: list[str] = []


def run() -> int:
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page(viewport={"width": 1440, "height": 950})
        page.on("pageerror", lambda e: errors.append(str(e)))
        page.goto("http://127.0.0.1:8765/", wait_until="networkidle")
        page.wait_for_timeout(1500)

        print("1. Бейджи SEO-приоритета на карточках канбана...")
        badges = page.locator("article.task-card span:has-text('SEO ')").count()
        print(f"   найдено бейджей: {badges}")
        if badges:
            texts = page.locator("article.task-card span:has-text('SEO ')").all_inner_texts()
            for t in texts[:6]:
                print("     •", t.strip())

        print("\n2. Кнопка пересчёта приоритета на вкладке анализа...")
        page.click("button:has-text('Анализ запросов')")
        page.wait_for_timeout(1000)
        page.click("button:has-text('Пересчитать приоритет тем')")
        page.wait_for_timeout(5000)
        box = page.inner_text("#priorityBox")
        lines = [l for l in box.strip().splitlines() if l.strip()]
        print("   результат:")
        for l in lines[:9]:
            print("     ", l[:95])

        os.makedirs("seo/out/screenshots", exist_ok=True)
        page.screenshot(path="seo/out/screenshots/priority_system.png")
        print("\n   ✓ скриншот: seo/out/screenshots/priority_system.png")

        print("\n3. Возврат на канбан (бейджи после пересчёта)...")
        page.click("button:has-text('Канбан статей')")
        page.wait_for_timeout(1200)
        badges2 = page.locator("article.task-card span:has-text('SEO ')").count()
        print(f"   бейджей после пересчёта: {badges2}")
        page.screenshot(path="seo/out/screenshots/priority_kanban.png")

        browser.close()

    print(f"\nОшибок JS: {len(errors)}")
    for e in errors:
        print("  ❌", e)
    return 0 if not errors else 1


if __name__ == "__main__":
    sys.exit(run())
