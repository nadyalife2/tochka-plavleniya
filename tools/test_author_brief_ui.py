"""Тест вкладки брифа для автора-человека в дашборде."""
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

        print("1. Открываем тему #20 в канбане...")
        page.evaluate("openInspector(20)")
        page.wait_for_timeout(600)

        print("2. Переключаемся на вкладку «Бриф для автора (ТЗ)»...")
        page.click("#modalTabBtnBrief")
        page.wait_for_timeout(1500)

        brief_text = page.inner_text("#mBriefContent")
        print(f"   длина текста брифа: {len(brief_text)} зн.")
        lines = [l for l in brief_text.strip().splitlines() if l.strip()]
        for l in lines[:7]:
            print("     ", l[:85])

        has_title = "#20" in brief_text and "Флюс для пайки" in brief_text
        has_std = "ГОСТ" in brief_text or "IPC" in brief_text
        print(f"   проверка паспорта темы: {'✓ есть' if has_title else '❌ нет'}")
        print(f"   проверка стандартов: {'✓ есть' if has_std else '❌ нет'}")

        os.makedirs("seo/out/screenshots", exist_ok=True)
        page.screenshot(path="seo/out/screenshots/author_brief.png")
        print("   ✓ скриншот: seo/out/screenshots/author_brief.png")

        browser.close()

    print(f"\nОшибок JS: {len(errors)}")
    for e in errors:
        print("  ❌", e)
    return 0 if (not errors and has_title) else 1


if __name__ == "__main__":
    sys.exit(run())
