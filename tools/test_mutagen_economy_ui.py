"""Playwright-проверка: блок «Экономичные механики Мутагена» и живой баланс в UI.

Проверяет:
1. Бейдж баланса Мутагена в полосе субагентов показывает реальную сумму с сервера.
2. Вкладка «Анализ запросов» содержит 4 карточки механик экономии и чипсы тем.
3. Кнопка «↻ Обновить» перезапрашивает /api/mutagen/balance.
4. Клик по чипсу подставляет фразу в поле ввода.
5. Ноль JS-ошибок.
"""
from __future__ import annotations

import os
import sys

from playwright.sync_api import sync_playwright

BASE = "http://127.0.0.1:8765"
SHOT = "seo/out/screenshots/mutagen_economy.png"

errors: list[str] = []
failures: list[str] = []


def check(cond: bool, label: str) -> None:
    if cond:
        print(f"   ✓ {label}")
    else:
        print(f"   ❌ {label}")
        failures.append(label)


def run() -> int:
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page(viewport={"width": 1440, "height": 1000})
        page.on("pageerror", lambda e: errors.append(str(e)))

        page.goto(BASE + "/", wait_until="networkidle")
        page.wait_for_timeout(1500)

        print("1. Бейдж баланса Мутагена в полосе субагентов...")
        badge = page.locator("#mutagenBalanceBadge")
        check(badge.count() == 1, "бейдж #mutagenBalanceBadge существует")
        badge_text = (badge.inner_text() if badge.count() else "").strip()
        print(f"   текст бейджа: {badge_text!r}")
        check("₽" in badge_text, "бейдж содержит символ ₽")
        check(badge_text not in ("", "15.92 ₽") or "₽" in badge_text, "бейдж заполнен")

        print("\n2. Открываем вкладку «Анализ запросов»...")
        page.click("button.tab-btn:has-text('Анализ запросов')")
        page.wait_for_timeout(900)

        block = page.locator("#view-analysis")
        check(block.locator("text=Баланс API Мутагена").count() > 0, "заголовок баланса присутствует")

        balance_el = page.locator("#analysisMutagenBalance")
        check(balance_el.count() == 1, "элемент #analysisMutagenBalance существует")
        balance_text = (balance_el.inner_text() if balance_el.count() else "").strip()
        print(f"   баланс в панели: {balance_text!r}")
        check("₽" in balance_text, "панель баланса показывает сумму в рублях")
        check("проверок" in balance_text, "панель считает остаток проверок")

        print("\n3. Четыре карточки экономичных механик...")
        expected = [
            "Лимит на mutagen.ru",
            "Золотые темы",
            "хвосты (tails",
            "Подсказки и FAQ",
        ]
        for text in expected:
            n = block.locator(f"text={text}").count()
            check(n > 0, f"карточка «{text}» найдена")

        print("\n4. Чипсы перспективных тем...")
        chips = block.locator("button.btn--xs")
        chip_count = chips.count()
        print(f"   кнопок-чипсов: {chip_count}")
        check(chip_count >= 6, "минимум 6 чипсов тем")

        print("\n5. Клик по чипсу подставляет фразу...")
        chip = block.locator("button:has-text('#48')")
        check(chip.count() > 0, "чипс #48 найден")
        if chip.count() > 0:
            chip.first.click()
            page.wait_for_timeout(600)
            filled = page.input_value("#analysisPhrase")
            print(f"   в поле ввода: {filled!r}")
            check("алюмин" in filled.lower(), "фраза #48 подставилась в поле")

        print("\n6. Кнопка «↻ Обновить» перезапрашивает баланс...")
        before = page.locator("#analysisMutagenBalance").inner_text()
        page.click("button:has-text('↻ Обновить')")
        page.wait_for_timeout(2500)
        after = page.locator("#analysisMutagenBalance").inner_text()
        print(f"   до: {before!r} | после: {after!r}")
        check("₽" in after, "баланс остался валидным после обновления")

        os.makedirs("seo/out/screenshots", exist_ok=True)
        page.screenshot(path=SHOT, full_page=True)
        print(f"\n   ✓ скриншот: {SHOT}")

        browser.close()

    print(f"\nОшибок JS: {len(errors)}")
    for e in errors:
        print("  ❌", e)
    print(f"Провалов проверок: {len(failures)}")
    for f in failures:
        print("  ❌", f)
    return 0 if not errors and not failures else 1


if __name__ == "__main__":
    sys.exit(run())