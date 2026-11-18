"""Visual & Functional Playwright Audit for Tochka Plavleniya SEO Dashboard."""
from __future__ import annotations

import os
import sys
from playwright.sync_api import sync_playwright

errors: list[str] = []
console_messages: list[str] = []


def run_audit() -> int:
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(viewport={"width": 1440, "height": 900})
        page = context.new_page()

        page.on("console", lambda msg: console_messages.append(f"[{msg.type}] {msg.text}"))
        page.on("pageerror", lambda err: errors.append(f"PageError: {err}"))

        print("1. Навигация на http://127.0.0.1:8765/...")
        page.goto("http://127.0.0.1:8765/", wait_until="networkidle")
        page.wait_for_timeout(1000)

        # Проверка счётчиков в степпере
        step_gaps = page.inner_text("#stepGapsCount")
        step_backlog = page.inner_text("#stepBacklogCount")
        step_work = page.inner_text("#stepWorkCount")
        step_fix = page.inner_text("#stepFixCount")
        step_review = page.inner_text("#stepReviewCount")
        step_ready = page.inner_text("#stepReadyCount")
        print(f"   Счётчики степпера: Пробелы={step_gaps}, Бэклог={step_backlog}, В работе={step_work}, Требуют правок={step_fix}, На ревью={step_review}, Готово к WP={step_ready}")

        # Проверка колонок канбана
        col_headers = page.locator(".board-column__header").all_inner_texts()
        print(f"   Колонки канбана ({len(col_headers)}):")
        for ch in col_headers:
            lines = [l.strip() for l in ch.split("\n") if l.strip()]
            print(f"      • {' '.join(lines)}")

        # Скриншот десктопа 1440px
        os.makedirs("seo/out/screenshots", exist_ok=True)
        page.screenshot(path="seo/out/screenshots/dashboard_1440.png", full_page=False)
        print("   ✓ Скриншот сохранён: seo/out/screenshots/dashboard_1440.png")

        # Проверка клика на карточку #15
        print("\n2. Клик на карточку #15 (готовая статья 87/100)...")
        card_15 = page.locator("article.task-card:has-text('#15')").first
        if card_15.count() > 0:
            card_15.click()
            page.wait_for_timeout(500)

            m_id = page.inner_text("#mId")
            m_status = page.inner_text("#mStatus")
            m_chars = page.inner_text("#mChars")
            m_stage = page.inner_text("#mStageBadge")
            m_score = page.inner_text("#mTotalScore")
            buttons = page.locator("#modalActionButtons button").all_inner_texts()

            print(f"   Модалка #15: {m_id} | Статус: {m_status} | Объём: {m_chars} | Оценка: {m_score}")
            print(f"   Индикатор этапа: «{m_stage}»")
            print(f"   Кнопки действий: {buttons}")

            # Проверка бага "знаков знаков"
            visible_text = page.locator("body").inner_text()
            assert "знаков знаков" not in visible_text, "ОШИБКА: обнаружена опечатка 'знаков знаков' в видимом тексте!"
            assert "зн. зн." not in visible_text, "ОШИБКА: обнаружена опечатка 'зн. зн.' в видимом тексте!"
            print("   ✓ Опечатка дублирования 'знаков знаков' отсутствует!")

            page.screenshot(path="seo/out/screenshots/modal_15.png")
            print("   ✓ Скриншот модалки сохранён: seo/out/screenshots/modal_15.png")
            page.click("button.modal-close")
            page.wait_for_timeout(300)
        else:
            print("   ⚠️ Карточка #15 не найдена на доске!")

        # Проверка клика на карточку #6
        print("\n3. Клик на карточку #6 (черновик 79/100, требует правок)...")
        page.evaluate("openInspector(6)")
        page.wait_for_timeout(500)
        m_id_6 = page.inner_text("#mId")
        m_chars_6 = page.inner_text("#mChars")
        m_score_6 = page.inner_text("#mTotalScore")
        m_stage_6 = page.inner_text("#mStageBadge")
        buttons_6 = page.locator("#modalActionButtons button").all_inner_texts()
        print(f"   Модалка #6: {m_id_6} | Объём: {m_chars_6} | Оценка: {m_score_6}")
        print(f"   Индикатор: «{m_stage_6}»")
        print(f"   Кнопки действий: {buttons_6}")
        assert m_id_6 == "#6", f"ОШИБКА: открылся {m_id_6} вместо #6"
        assert "требует доработки" in m_stage_6.lower(), f"ОШИБКА: неверный этап {m_stage_6}"
        page.screenshot(path="seo/out/screenshots/modal_6.png")
        print("   ✓ Скриншот модалки #6 сохранён: seo/out/screenshots/modal_6.png")
        page.click("button.modal-close")
        page.wait_for_timeout(300)

        # Проверка табов
        print("\n4. Переключение табов...")
        page.click("button:has-text('Готовые к выгрузке')")
        page.wait_for_timeout(500)
        export_rows = page.locator("#exportTableBody tr").count()
        print(f"   Таб 'Готовые к выгрузке': строк в таблице = {export_rows}")

        # Проверка адаптивности на 768px (планшет)
        print("\n5. Проверка брейкпоинта 768px...")
        page.set_viewport_size({"width": 768, "height": 1024})
        page.click("button:has-text('Канбан статей')")
        page.wait_for_timeout(500)
        page.screenshot(path="seo/out/screenshots/dashboard_768.png", full_page=False)
        print("   ✓ Скриншот планшета сохранён: seo/out/screenshots/dashboard_768.png")

        browser.close()

    print("\n=== РЕЗУЛЬТАТ AUDIT PLAYWRIGHT ===")
    print(f"Ошибок JS: {len(errors)}")
    for e in errors:
        print("   ❌", e)
    warn_msgs = [m for m in console_messages if "[error]" in m or "[warning]" in m]
    print(f"Предупреждений консоли: {len(warn_msgs)}")
    for w in warn_msgs:
        print("   ⚠️", w)

    return 0 if len(errors) == 0 else 1


if __name__ == "__main__":
    sys.exit(run_audit())
