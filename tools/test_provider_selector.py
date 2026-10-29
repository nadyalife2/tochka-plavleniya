"""Тест переключателя провайдеров ИИ (Claude / DeepSeek / Yandex / Auto) в UI."""
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
        page.wait_for_timeout(1200)

        print("1. Проверка селектора в Центре агентов...")
        page.click("button:has-text('Центр агентов')")
        page.wait_for_timeout(600)
        sel_agent = page.locator("#agentProviderSelect")
        print(f"   селектор в агентах: {'✓ есть' if sel_agent.count() else '❌ нет'}")
        val_agent = sel_agent.input_value()
        print(f"   значение по умолчанию: {val_agent}")

        # Переключаем на DeepSeek
        page.select_option("#agentProviderSelect", value="deepseek")
        page.wait_for_timeout(400)

        os.makedirs("seo/out/screenshots", exist_ok=True)
        page.screenshot(path="seo/out/screenshots/provider_agents.png")
        print("   ✓ скриншот: seo/out/screenshots/provider_agents.png")

        print("\n2. Проверка селектора в модальном окне статьи...")
        page.click("button:has-text('Канбан статей')")
        page.wait_for_timeout(600)
        page.evaluate("openInspector(25)")
        page.wait_for_timeout(600)

        sel_modal = page.locator("#modalProviderSelect")
        print(f"   селектор в инспекторе: {'✓ есть' if sel_modal.count() else '❌ нет'}")
        val_modal = sel_modal.input_value()
        print(f"   синхронизировано значение: {val_modal} ({'✓ DeepSeek' if val_modal == 'deepseek' else '❌'})")

        # Переключаем на Claude
        page.select_option("#modalProviderSelect", value="claude")
        page.wait_for_timeout(400)
        print("   переключено на:", page.locator("#modalProviderSelect").input_value())

        # Проверяем кнопки написания/перезаписи
        btns = page.locator("#modalActionButtons button").all_inner_texts()
        print("   кнопки в модалке:")
        for b in btns:
            print(f"     • {b.strip()}")

        page.screenshot(path="seo/out/screenshots/provider_modal.png")
        print("   ✓ скриншот: seo/out/screenshots/provider_modal.png")

        browser.close()

    print(f"\nОшибок JS: {len(errors)}")
    for e in errors:
        print("  ❌", e)
    return 0 if not errors else 1


if __name__ == "__main__":
    sys.exit(run())
