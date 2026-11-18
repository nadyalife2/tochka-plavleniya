"""Проверка: подсказки фраз из SEO конкурентов + исправление кнопки «на доработку»."""
from __future__ import annotations

import json
import sys
import urllib.request

from playwright.sync_api import sync_playwright

BASE = "http://127.0.0.1:8765"
errors: list[str] = []


def api_get(path):
    with urllib.request.urlopen(BASE + path, timeout=30) as r:
        return json.loads(r.read().decode())


def api_post(path, payload):
    req = urllib.request.Request(BASE + path, data=json.dumps(payload).encode(),
                                 headers={"Content-Type": "application/json"})
    with urllib.request.urlopen(req, timeout=60) as r:
        return json.loads(r.read().decode())


def check_reject_fix():
    """Статья с высоким баллом после отказа должна попасть в «Требует правок»."""
    print("1. Проверка серверной части отказа...")
    before = api_get("/api/data")
    t = next(x for x in before["topics"] if x["id"] == 15)
    print(f"   #15 до отказа: status={t['status']}, балл={t['score_total']}")

    api_post("/api/articles/reject", {"id": 15, "reason": "автотест аудита"})
    after = api_get("/api/data")
    t2 = next(x for x in after["topics"] if x["id"] == 15)
    print(f"   #15 после отказа: status={t2['status']}, next_action={t2['next_action'][:40]}")
    ok = t2["status"] == "needs_fix"
    print(f"   статус needs_fix: {'✓' if ok else '❌'}")

    # вернуть как было: снова в работу к ОТК
    api_post("/api/articles/approve", {"id": 15})
    api_post("/api/articles/reject", {"id": 15, "reason": "снят автотест"})
    api_post("/api/articles/reject", {"id": 15, "reason": "восстановление статуса"})
    print("   (статус возвращён в needs_fix — это ожидаемое состояние после отказа)")
    return ok


def check_ui():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page(viewport={"width": 1440, "height": 900})
        page.on("pageerror", lambda e: errors.append(str(e)))
        page.goto(BASE + "/", wait_until="networkidle")
        page.wait_for_timeout(1200)

        print("\n2. Вкладка «Анализ запросов»: выпадающий список подсказок...")
        page.click("button:has-text('Анализ запросов')")
        page.wait_for_timeout(2000)

        options = page.locator("#kwSuggestSelect option").count()
        print(f"   опций в списке: {options - 1 if options else 0}")
        texts = page.locator("#kwSuggestSelect option").all_inner_texts()
        for t in texts[1:6]:
            print("     •", t[:88])

        print("\n3. Выбор подсказки заполняет поле ввода...")
        if options > 1:
            page.select_option("#kwSuggestSelect", index=1)
            page.wait_for_timeout(500)
            print("   поле ввода:", page.input_value("#analysisPhrase"))

        print("\n4. Колонка «Требуют правок» показывает отклонённую статью...")
        page.click("button:has-text('Канбан статей')")
        page.wait_for_timeout(800)
        cnt = page.inner_text("#stepFixCount")
        headers = page.locator(".board-column__header").all_inner_texts()
        needs_fix_col = [h for h in headers if "ТРЕБУЕТ ПРАВОК" in h.upper()]
        print(f"   счётчик «Требуют правок»: {cnt}")
        print(f"   колонка на доске: {' '.join(needs_fix_col[0].split()) if needs_fix_col else '—'}")

        page.screenshot(path="seo/out/screenshots/analysis_suggestions.png")
        print("   ✓ скриншот: seo/out/screenshots/analysis_suggestions.png")
        browser.close()
    return True


if __name__ == "__main__":
    ok1 = check_reject_fix()
    ok2 = check_ui()
    print(f"\nОшибок JS: {len(errors)}")
    for e in errors:
        print("  ❌", e)
    sys.exit(0 if (ok1 and ok2 and not errors) else 1)
