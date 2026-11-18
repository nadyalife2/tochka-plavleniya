"""Playwright-проверка: вкладка «Анализ запросов» и кнопка проверки фразы."""
from __future__ import annotations

import os
import sys
from playwright.sync_api import sync_playwright

errors: list[str] = []


def run() -> int:
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page(viewport={"width": 1440, "height": 900})
        page.on("pageerror", lambda e: errors.append(str(e)))

        page.goto("http://127.0.0.1:8765/", wait_until="networkidle")
        page.wait_for_timeout(1200)

        print("1. Открываем вкладку «Анализ запросов»...")
        page.click("button:has-text('Анализ запросов')")
        page.wait_for_timeout(800)
        rows = page.locator("#analysisBody tr").count()
        print(f"   строк в истории: {rows}")

        print("\n2. Проверяем фразу через Вордстат (бесплатно)...")
        page.fill("#analysisPhrase", "пайка проводов")
        page.click("button:has-text('Узнать спрос')")
        page.wait_for_timeout(6000)
        result_text = page.inner_text("#analysisResult")
        print("   результат:", " | ".join(result_text.strip().splitlines()[:4]))

        print("\n3. Проверяем Мутагеном (платно)...")
        page.click("button:has-text('Проверить Мутагеном')")
        page.wait_for_timeout(8000)
        result_text2 = page.inner_text("#analysisResult")
        for line in result_text2.strip().splitlines()[:6]:
            print("   ", line)

        os.makedirs("seo/out/screenshots", exist_ok=True)
        page.screenshot(path="seo/out/screenshots/analysis_tab.png")
        print("\n   ✓ скриншот: seo/out/screenshots/analysis_tab.png")

        print("\n4. Кнопка «Проверить запрос» из карточки статьи...")
        page.click("button:has-text('Канбан статей')")
        page.wait_for_timeout(500)
        page.evaluate("openInspector(1)")
        page.wait_for_timeout(500)
        has_btn = page.locator("#modalActionButtons button:has-text('Проверить запрос')").count()
        print(f"   кнопка в инспекторе: {'✓ есть' if has_btn else '❌ нет'}")
        if has_btn:
            page.click("#modalActionButtons button:has-text('Проверить запрос')")
            page.wait_for_timeout(6000)
            print("   заголовок запроса:", page.input_value("#analysisPhrase"))
            page.screenshot(path="seo/out/screenshots/analysis_from_topic.png")

        browser.close()

    print(f"\nОшибок JS: {len(errors)}")
    for e in errors:
        print("  ❌", e)
    return 0 if not errors else 1


if __name__ == "__main__":
    sys.exit(run())
