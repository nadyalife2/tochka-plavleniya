"""Playwright-проверка перепроверки фраз и истории измерений."""
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

        page.click("button:has-text('Анализ запросов')")
        page.wait_for_timeout(1000)

        rows = page.locator("#analysisBody tr").count()
        btns_v = page.locator("#analysisBody button:has-text('Вордстат')").count()
        btns_m = page.locator("#analysisBody button:has-text('Мутаген')").count()
        btns_h = page.locator("#analysisBody button:has-text('История')").count()
        print(f"1. Строк истории: {rows}")
        print(f"   кнопок «↻ Вордстат»: {btns_v} | «↻ Мутаген»: {btns_m} | «📈 История»: {btns_h}")

        print("\n2. Перепроверяем первую строку через Вордстат (бесплатно, мимо кэша)...")
        page.locator("#analysisBody button:has-text('Вордстат')").first.click()
        page.wait_for_timeout(6000)
        text = page.inner_text("#analysisResult")
        print("   ", " | ".join(text.strip().splitlines()[:3]))

        print("\n3. Открываем историю измерений...")
        page.locator("#analysisBody button:has-text('История')").first.click()
        page.wait_for_timeout(3000)
        trend = page.inner_text("#analysisResult")
        lines = [l for l in trend.strip().splitlines() if l.strip()]
        print(f"   строк в истории: {len(lines)}")
        for l in lines[:6]:
            print("   ", l[:90])

        os.makedirs("seo/out/screenshots", exist_ok=True)
        page.screenshot(path="seo/out/screenshots/analysis_recheck.png")
        print("\n   ✓ скриншот: seo/out/screenshots/analysis_recheck.png")

        browser.close()

    print(f"\nОшибок JS: {len(errors)}")
    for e in errors:
        print("  ❌", e)
    return 0 if not errors else 1


if __name__ == "__main__":
    sys.exit(run())
