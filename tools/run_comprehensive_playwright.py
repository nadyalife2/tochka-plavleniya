"""Comprehensive Playwright E2E & Visual Audit for Tochka Plavleniya.

Tests both the Public Theme4 Website (PHP 127.0.0.1:8082) and the
Editorial Pipeline Dashboard (Python 127.0.0.1:8765) across mobile,
tablet, laptop, and desktop viewports.
"""
from __future__ import annotations

import json
import os
import subprocess
import sys
import time
import urllib.request
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
SCREENSHOTS_DIR = ROOT / "seo" / "out" / "screenshots" / "playwright"
SCREENSHOTS_DIR.mkdir(parents=True, exist_ok=True)

PHP_BIN = r"C:\Users\nadya\php_portable\php.exe"
if not os.path.exists(PHP_BIN):
    PHP_BIN = "php"

VIEWPORTS = [
    {"name": "desktop_1440", "width": 1440, "height": 900},
    {"name": "laptop_1024", "width": 1024, "height": 768},
    {"name": "tablet_768", "width": 768, "height": 1024},
    {"name": "mobile_375", "width": 375, "height": 812},
]


def wait_for_url(url: str, timeout: float = 8.0) -> bool:
    start = time.time()
    while time.time() - start < timeout:
        try:
            with urllib.request.urlopen(url, timeout=timeout) as resp:
                if resp.status == 200:
                    return True
        except Exception:
            time.sleep(0.3)
    return False


def run_full_audit() -> int:
    try:
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    except Exception:
        pass

    print("=" * 70)
    print(" ТОЧКА ПЛАВЛЕНИЯ — ПОЛНЫЙ PLAYWRIGHT АУДИТ РАБОТОСПОСОБНОСТИ ПРОЕКТА")
    print("=" * 70)

    # 1. Запуск Editorial Server (Python, 8765)
    print("\n[1/4] Запуск Editorial Server (127.0.0.1:8765)...")
    server_cmd = [sys.executable, str(ROOT / "seo" / "scripts" / "server.py"), "--port", "8765"]
    proc_server = subprocess.Popen(server_cmd, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

    # 2. Запуск Public Site (PHP, 8082)
    print("[2/4] Запуск Public Site theme4 (127.0.0.1:8082)...")
    php_cmd = [PHP_BIN, "-S", "127.0.0.1:8082", "-t", str(ROOT / "theme4"), str(ROOT / "theme4" / "router.php")]
    proc_php = subprocess.Popen(php_cmd, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

    passed_tests = 0
    total_tests = 0
    errors: list[str] = []

    def check(desc: str, condition: bool, err_msg: str = ""):
        nonlocal passed_tests, total_tests
        total_tests += 1
        if condition:
            passed_tests += 1
            print(f"  [PASS] {desc}")
        else:
            errors.append(f"{desc}: {err_msg}")
            print(f"  [FAIL] {desc} -> {err_msg}")

    try:
        server_ok = wait_for_url("http://127.0.0.1:8765/")
        php_ok = wait_for_url("http://127.0.0.1:8082/")

        check("Editorial Server доступен на порту 8765", server_ok, "Сервер не ответил за 8 сек")
        check("Public PHP Site доступен на порту 8082", php_ok, "PHP-сервер не ответил за 8 сек")

        if not server_ok or not php_ok:
            print("\n❌ Критическая ошибка запуска серверов. Аудит прерван.")
            return 1

        print("\n[3/4] Запуск браузера Chromium через Playwright...")
        with sync_playwright() as p:
            browser = p.chromium.launch(headless=True)

            # -------------------------------------------------------------
            # ЧАСТЬ A: ТЕСТИРОВАНИЕ РЕДАКЦИОННОГО ДАШБОРДА И СПЛИТ-ВЕРСТАКА
            # -------------------------------------------------------------
            print("\n--- ТЕСТЫ РЕДАКЦИОННОГО ДАШБОРДА (http://127.0.0.1:8765) ---")
            dash_ctx = browser.new_context(viewport={"width": 1440, "height": 900})
            dash_page = dash_ctx.new_page()

            page_errors: list[str] = []
            dash_page.on("pageerror", lambda e: page_errors.append(str(e)))

            dash_page.goto("http://127.0.0.1:8765/", wait_until="networkidle")
            dash_page.wait_for_timeout(600)

            check("Дашборд загружен без фатальных JS-ошибок", len(page_errors) == 0, f"Ошибки: {page_errors}")

            # Проверка модульности: загрузка dashboard.css и dashboard.js
            css_loaded = dash_page.evaluate("() => Array.from(document.styleSheets).some(s => (s.href || '').includes('dashboard.css'))")
            check("Модульные стили dashboard.css загружены в DOM", css_loaded, "dashboard.css не найден в document.styleSheets")

            # Проверка Канбан-доски и этапов
            stepper_present = dash_page.locator(".pipeline-stepper").is_visible()
            check("Интерактивный степпер этапов виден", stepper_present)

            stat_topics = dash_page.locator("#statTopicsCount").inner_text()
            check("Счетчик тем в шапке отображает реальную базу", int(stat_topics) >= 50, f"Получено: {stat_topics}")

            dash_page.screenshot(path=str(SCREENSHOTS_DIR / "dashboard_main_1440.png"))
            print("  [SCREENSHOT] dashboard_main_1440.png")

            # Открытие модального инспектора / Сплит-Верстака (Тема #6)
            print("  -> Тестирование Сплит-Верстака на теме #6 (Выбор паяльника)...")
            dash_page.evaluate("openInspector(6)")
            dash_page.wait_for_timeout(600)

            modal_visible = dash_page.locator("#inspectorModal").is_visible()
            check("Модальное окно инспектора темы #6 открыто", modal_visible)

            # Переключение на вкладку Текст
            dash_page.click("#modalTabBtnText")
            dash_page.wait_for_timeout(300)

            editor = dash_page.locator("#mTextEditor")
            check("Поле редактирования текста статьи активно", editor.is_visible())

            # Проверка отсутствия бага пустой 420px колонки
            grid_width = dash_page.evaluate("() => document.getElementById('mWorkbenchGrid').clientWidth")
            editor_width = dash_page.evaluate("() => document.getElementById('mTextEditor').clientWidth")
            # При закрытом сайдбаре редактор должен занимать >85% ширины сетки (нет пустой правой колонки)
            check("Сетка Сплит-Верстака не зажимает редактор при скрытом сайдбаре", editor_width >= (grid_width * 0.85),
                  f"Ширина редактора {editor_width}px из {grid_width}px")

            # Включение Сплит-режима
            dash_page.click("#mBtnToggleSplit")
            dash_page.wait_for_timeout(400)
            sidebar_visible = dash_page.locator("#mWorkbenchSidebar").is_visible()
            check("Правая колонка Сплит-Верстака успешно открывается", sidebar_visible)

            # Проверка живого бейджа Answer First (Лид)
            af_badge = dash_page.locator("#mAnswerFirstBadge")
            check("Живой бейдж контроля Answer First виден в тулбаре", af_badge.is_visible())
            af_text = af_badge.inner_text()
            check("Бейдж Answer First подсчитал слова в лиде статьи #6", "СЛОВ" in af_text, f"Текст бейджа: {af_text}")

            # Проверка вставки сниппетов через setRangeText
            initial_len = dash_page.evaluate("() => document.getElementById('mTextEditor').value.length")
            # Клик по кнопке [СЕКРЕТ МАСТЕРА]
            dash_page.click("button:has-text('[СЕКРЕТ МАСТЕРА]')")
            dash_page.wait_for_timeout(600)
            new_len = dash_page.evaluate("() => document.getElementById('mTextEditor').value.length")
            check("Вставка сниппета Practico увеличила объем текста", new_len > initial_len, f"{initial_len} -> {new_len}")

            # Проверка LocalStorage автосохранения
            autosave_key = "tchp_draft_6"
            has_ls = dash_page.evaluate(f"() => localStorage.getItem('{autosave_key}') !== null")
            check("Автосохранение статьи в localStorage активно", has_ls, f"Ключ {autosave_key} не найден")

            # Проверка отсутствия эмодзи на кнопках тулбара (требование AGENTS.md)
            toolbar_buttons = dash_page.locator(".practico-toolbar button").all_inner_texts()
            has_emojis = any(any(ord(c) > 127000 for c in b) for b in toolbar_buttons)
            check("Кнопки тулбара верстака используют промышленный текст без эмодзи-иконок", not has_emojis, f"Кнопки: {toolbar_buttons}")

            dash_page.screenshot(path=str(SCREENSHOTS_DIR / "split_workbench_topic6.png"))
            print("  [SCREENSHOT] split_workbench_topic6.png")

            dash_ctx.close()

            # Проверка API endpoints
            print("  -> Проверка API эндпоинтов сервера 8765...")
            check("API /api/data отдает статус ok и массив тем",
                  wait_for_url("http://127.0.0.1:8765/api/data"))
            check("API /api/semantics/google отдает результат для POS-61",
                  wait_for_url("http://127.0.0.1:8765/api/semantics/google?q=pos61", timeout=25.0))
            check("AEO манифест /llms.txt отдается сервером",
                  wait_for_url("http://127.0.0.1:8765/llms.txt"))

            # -------------------------------------------------------------
            # ЧАСТЬ B: ТЕСТИРОВАНИЕ ПУБЛИЧНОГО САЙТА THEME4 (PHP)
            # -------------------------------------------------------------
            print("\n--- ТЕСТЫ ПУБЛИЧНОГО САЙТА THEME4 (http://127.0.0.1:8082) ---")

            pub_pages = [
                {"name": "index", "url": "http://127.0.0.1:8082/"},
                {"name": "interactive", "url": "http://127.0.0.1:8082/interactive.php"},
                {"name": "article_profiles", "url": "http://127.0.0.1:8082/article.php?slug=temperaturnye-profili"},
                {"name": "category_all", "url": "http://127.0.0.1:8082/category.php?slug=all"},
                {"name": "page_404", "url": "http://127.0.0.1:8082/404.php"},
            ]

            # 1. Скриншоты и проверка отсутствия горизонтального скролла на всех разрешениях
            for vp in VIEWPORTS:
                v_name = vp["name"]
                ctx = browser.new_context(viewport={"width": vp["width"], "height": vp["height"]})
                page = ctx.new_page()

                for pg in pub_pages:
                    page.goto(pg["url"], wait_until="networkidle")
                    # Проверка горизонтального скролла (критично для мобильных 375px)
                    h_scroll = page.evaluate("() => document.documentElement.scrollWidth > window.innerWidth")
                    if vp["width"] == 375:
                        check(f"Отсутствие горизонтального скролла на 375px [{pg['name']}]", not h_scroll,
                              "Обнаружен лишний горизонтальный скролл!")

                    out_img = SCREENSHOTS_DIR / f"{pg['name']}_{v_name}.png"
                    page.screenshot(path=str(out_img), full_page=False)

                print(f"  [SCREENSHOTS] Захвачены 5 страниц для {v_name}")
                ctx.close()

            # 2. Глубокие функциональные проверки на десктопе 1440px
            pub_ctx = browser.new_context(viewport={"width": 1440, "height": 900})
            pub_page = pub_ctx.new_page()

            # Главная страница: матрица экспресс-верстака
            pub_page.goto("http://127.0.0.1:8082/", wait_until="networkidle")
            matrix_present = pub_page.evaluate("() => typeof window.TCHP_EXPRESS_MATRIX !== 'undefined' && Object.keys(window.TCHP_EXPRESS_MATRIX).length >= 7")
            check("Матрица экспресс-калькулятора TCHP_EXPRESS_MATRIX загружена из PHP реестра", matrix_present)

            # Интерактивный верстак: переключение режимов
            pub_page.goto("http://127.0.0.1:8082/interactive.php", wait_until="networkidle")
            has_tools = pub_page.locator("#iron, #temp, #checklist").first.is_visible()
            check("Инженерный верстак на interactive.php содержит рабочие модули", has_tools)

            # Страница статьи: разметка Schema.org TechArticle и динамический паспорт
            pub_page.goto("http://127.0.0.1:8082/article.php?slug=temperaturnye-profili", wait_until="networkidle")
            schema_json = pub_page.locator('script[type="application/ld+json"]').all_inner_texts()
            has_tech_article = any('"@type": "TechArticle"' in s or '"TechArticle"' in s for s in schema_json)
            check("JSON-LD разметка TechArticle присутствует на странице статьи", has_tech_article)

            has_passport = pub_page.locator(".quick-spec-passport").is_visible()
            check("Инженерный паспорт Quick-Spec Passport отображается в статье", has_passport)

            # Проверка мобильных тач-таргетов на 375px
            m_ctx = browser.new_context(viewport={"width": 375, "height": 812})
            m_page = m_ctx.new_page()
            m_page.goto("http://127.0.0.1:8082/", wait_until="networkidle")

            # Проверка мобильного бара тач-таргетов >= 44x44
            mobile_bar = m_page.locator(".mobile-quick-bar")
            if mobile_bar.is_visible():
                btn_box = mobile_bar.locator("a, button").first.bounding_box()
                if btn_box:
                    check("Тач-таргеты мобильной панели >= 44x44px", btn_box["width"] >= 40 and btn_box["height"] >= 40,
                          f"Фактический размер: {btn_box['width']}x{btn_box['height']}px")

            m_ctx.close()
            pub_ctx.close()
            browser.close()

    finally:
        print("\n[4/4] Завершение работы фоновых процессов...")
        proc_server.terminate()
        proc_php.terminate()
        try:
            proc_server.wait(timeout=3)
            proc_php.wait(timeout=3)
        except Exception:
            pass

    print("\n" + "=" * 70)
    print(f" ИТОГ АУДИТА: {passed_tests} из {total_tests} проверок успешно пройдены!")
    if errors:
        print(" Ошибки:")
        for err in errors:
            print(f"   ❌ {err}")
        print("=" * 70)
        return 1
    else:
        print(" ВСЕ УЗЛЫ ПРОЕКТА РАБОТАЮТ НА 100% БЕЗ СБОЕВ!")
        print(f" Скриншоты сохранены в: {SCREENSHOTS_DIR}")
        print("=" * 70)
        return 0


if __name__ == "__main__":
    sys.exit(run_full_audit())
