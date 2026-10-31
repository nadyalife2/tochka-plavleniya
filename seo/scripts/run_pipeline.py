"""Консольный оркестратор SEO-конвейера «Точка Плавления».

Управляет сквозным циклом статьи между 4 субагентами и Шеф-редактором (Вам).

Примеры использования:
    # Посмотреть статус и следующий шаг по теме:
    python seo/scripts/run_pipeline.py --id 16 --step status

    # 1. Запустить подготовку исследователя (LSI, ссылки, бриф):
    python seo/scripts/run_pipeline.py --id 16 --step research

    # 2. Получить готовый промпт для Автора (Claude Sonnet / Gemini Pro):
    python seo/scripts/run_pipeline.py --id 16 --step prompt-writer

    # 3. Получить готовый промпт для Рецензента (Reviewer):
    python seo/scripts/run_pipeline.py --id 16 --step prompt-reviewer

    # 4. Прогнать автоматический скоринг 0–100:
    python seo/scripts/run_pipeline.py --id 16 --step score

    # 5. Вывести карточку утверждения для Шеф-редактора (Вы):
    python seo/scripts/run_pipeline.py --id 16 --step approve
"""
from __future__ import annotations

import argparse
import json
import subprocess
import sys
from pathlib import Path

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

from db import build_db_plan as build_plan, get_connection
from excel_io import OUT, STATUSES
from linkgraph import outbound_candidates


def get_topic_data(plan: dict, article_id: str) -> dict:
    tid_int = int(article_id) if str(article_id).isdigit() else article_id
    try:
        with get_connection() as conn:
            row = conn.execute("SELECT * FROM topics WHERE id = ?", (tid_int,)).fetchone()
            if row:
                t = dict(row)
                t["id"] = str(t["id"])
                t["has_text"] = bool(t.get("content_markdown")) and len(t.get("content_markdown") or "") > 400
                has_brief = conn.execute("SELECT COUNT(*) FROM briefs WHERE topic_id = ?", (tid_int,)).fetchone()[0] > 0
                t["has_brief"] = has_brief
                t["status_raw"] = t["status"]
                return t
    except Exception:
        pass
    topic = plan["topics"].get(str(article_id))
    if not topic:
        raise SystemExit(f"Ошибка: Тема #{article_id} не найдена в базе данных.")
    return topic


def find_article_file(article_id: str, slug: str) -> Path | None:
    root = Path(__file__).resolve().parents[2]
    dirs_to_check = [root / "сами статьи", root / "seo" / "data", root]
    for d in dirs_to_check:
        if not d.exists():
            continue
        # Ищем по ID или slug
        for p in d.glob("*.md"):
            name = p.name.lower()
            if name.startswith(f"{article_id}-") or f"-{article_id}-" in name or (slug and slug in name):
                return p
    return None


def step_status(topic: dict) -> None:
    tid = topic["id"]
    status_label = STATUSES.get(topic["status"], topic["status"])
    print("=" * 60)
    print(f"ТЕМА #{tid}: {topic['title']}")
    print("=" * 60)
    print(f"- Slug:      {topic['slug']}")
    print(f"- Кластер:   {topic['cluster']} (приоритет: {topic['priority']})")
    print(f"- Статус:    {status_label}")
    print(f"- Ключевики: {topic.get('keywords', '—')}")
    print(f"- Есть ТЗ:   {'Да' if topic['has_brief'] else 'Нет'}")
    print(f"- Есть текст:{'Да' if topic['has_text'] else 'Нет'}")

    score_file = OUT / "scores" / f"{tid}.json"
    if score_file.exists():
        score_data = json.loads(score_file.read_text(encoding="utf-8"))
        print(f"- Скоринг:   {score_data.get('total')}/100 ({score_data.get('verdict')})")

    # Рекомендованный шаг
    print("\n--- РЕКОМЕНДОВАННЫЙ ШАГ ---")
    if not topic["has_brief"]:
        print(f"1. Запустите исследователя: python seo/scripts/run_pipeline.py --id {tid} --step research")
    elif not topic["has_text"]:
        print(f"2. Отдайте бриф Автору (Claude/Gemini): python seo/scripts/run_pipeline.py --id {tid} --step prompt-writer")
    else:
        print(f"3. Прогоните скоринг: python seo/scripts/run_pipeline.py --id {tid} --step score")
        print(f"4. Посмотрите карточку приёмки: python seo/scripts/run_pipeline.py --id {tid} --step approve")


def step_research(plan: dict, topic: dict) -> None:
    tid = topic["id"]
    out_briefs = OUT / "briefs"
    out_briefs.mkdir(parents=True, exist_ok=True)
    brief_path = out_briefs / f"{tid}-brief.md"

    out_links = outbound_candidates(plan, tid)
    links_str = "\n".join(
        f"- {'[АКТИВНАЯ]' if l['written'] else '[ОТЛОЖЕННАЯ]'} #{l['id']} {l['title']} (анкор: «{l['anchor'] or l['slug']}»)"
        for l in out_links
    )

    brief_content = f"""# Бриф на статью: #{tid} {topic['title']}

## 1. Паспорт темы
- ID темы: {tid}
- Slug: `{topic['slug']}`
- Кластер: {topic['cluster']}
- Тип статьи: {topic['type'] or 'Руководство / Инструкция'}
- Приоритет: {topic['priority']}

## 2. Главный интент читателя
- Ключевые запросы: {topic.get('keywords', '—')}
- Задача читателя: получить чёткий пошаговый ответ на практический вопрос без пустых введений.

## 3. Рекомендованная структура H2
- Вводный абзац (Answer First): прямой ответ до 40 слов.
- H2 (1): Суть технологии и параметры.
- H2 (2): Пошаговый регламент работы (паттерн Steps).
- H2 (3): Сравнительная таблица или диагностика дефектов.
- H2 (4): Безопасность и средства защиты.
- H2 (5): Источники и нормативные документы (ГОСТ / IPC).

## 4. Карта перелинковки (3–5 связей)
{links_str or 'Плановые связи отсутствуют.'}

## 5. Пул вопросов FAQ (4–6 шт.)
1. q: "..."
   a: "..."
2. q: "..."
   a: "..."
3. q: "..."
   a: "..."
4. q: "..."
   a: "..."
"""
    brief_path.write_text(brief_content, encoding="utf-8")
    print(f"✓ Бриф успешно сгенерирован: {brief_path}")
    print(f"Следующий шаг: python seo/scripts/run_pipeline.py --id {tid} --step prompt-writer")


def step_prompt_writer(plan: dict, topic: dict) -> None:
    tid = topic["id"]
    brief_file = OUT / "briefs" / f"{tid}-brief.md"
    brief_text = brief_file.read_text(encoding="utf-8") if brief_file.exists() else f"Тема: #{tid} {topic['title']}"

    prompt = f"""Ты — инженерный автор проекта «Точка Плавления» (голос Practico).
Твоя задача — написать полноценную статью по брифу ниже.

ТРЕБОВАНИЯ К ТЕКСТУ:
1. Голос Practico: обращение строго на «Вы», тон спокойного опытного коллеги-инженера.
2. Формула мысли: Факт/уставка -> Физический процесс -> Наблюдаемое последствие.
3. Answer First: первый абзац дает прямой ответ на вопрос СТРОГО до 40 слов. Никаких «В современном мире...».
4. Объём: от 5 500 до 7 000 знаков с пробелами.
5. Расширение: включи блоки «Где ошибаются», «Признаки результата», «Что делать, если не получилось».
6. Безопасность: напомни про вентиляцию, защиту глаз, запрет пайки под напряжением.
7. Не пиши ручной FAQ в теле текста! Сформируй в конце список FAQ (4-6 пар q/a) для метаполя.
8. Первоисточники: сошлись на 2-4 реальных стандарта (ГОСТ 21930/21931, IPC J-STD, ISO).

БРИФ:
{brief_text}

Сохрани готовый текст в: сами статьи/{tid}-{topic['slug']}.md
"""
    print("=" * 60)
    print("ГОТОВЫЙ ПРОМПТ ДЛЯ АВТОРА (Claude Sonnet / Gemini Pro):")
    print("=" * 60)
    print(prompt)


def step_prompt_reviewer(topic: dict) -> None:
    tid = topic["id"]
    prompt = f"""Ты — независимый технический рецензент проекта «Точка Плавления».
Твоя задача — провести строгий аудит черновика статьи #{tid} ({topic['title']}).

КРИТЕРИИ АУДИТА:
1. Физика и химия: соответствуют ли температуры ГОСТам и реестру theme4/data/materials-registry.php?
2. Безопасность: нет ли советов пайки под напряжением (КРИТИЧЕСКИЙ БЛОКЕР)?
3. Голос Practico: нет ли выдуманного опыта мастера («в моей мастерской») и стоп-фраз («идеальный результат», «проще простого»)?
4. Slop-контроль: нет ли водянистых фраз («в заключение», «играет важную роль»)?

Выдай результат в виде таблицы с колонками:
| Место в тексте | Обнаруженная проблема | Почему это важно | Что исправить | Критичность (Блокер/Важно/Замечание) |
"""
    print("=" * 60)
    print("ГОТОВЫЙ ПРОМПТ ДЛЯ РЕЦЕНЗЕНТА (Reviewer):")
    print("=" * 60)
    print(prompt)


def step_score(topic: dict) -> None:
    tid = topic["id"]
    scorer_script = Path(__file__).resolve().parent / "score_article.py"
    if topic.get("content_markdown"):
        cmd = [sys.executable, str(scorer_script), "--text", topic["content_markdown"], "--id", str(tid)]
    else:
        file_path = find_article_file(tid, topic["slug"])
        if file_path:
            cmd = [sys.executable, str(scorer_script), "--file", str(file_path), "--id", str(tid)]
        else:
            raise SystemExit(f"Ошибка: Текст статьи #{tid} не найден ни в SQLite, ни в `сами статьи/`.")
    subprocess.run(cmd)


def step_approve(topic: dict) -> None:
    tid = topic["id"]
    score_file = OUT / "scores" / f"{tid}.json"
    score_data = json.loads(score_file.read_text(encoding="utf-8")) if score_file.exists() else {}

    total = score_data.get("total", "—")
    verdict = score_data.get("verdict", "не оценено")
    blocks = score_data.get("blocks", {})
    issues = score_data.get("issues", [])
    blockers = score_data.get("blockers", [])

    print("\n" + "█" * 60)
    print(f" КАРТОЧКА ПРИЁМКИ ДЛЯ ШЕФ-РЕДАКТОРА: #{tid} {topic['title']}")
    print("█" * 60)
    print(f" Итоговый балл:  {total} / 100 — {verdict.upper()}")
    print("-" * 60)
    if blocks:
        scores_line = "  ".join(f"{k}: {v['points']}/{v['max']}" for k, v in blocks.items())
        print(f" Разбалловка:    {scores_line}")

    if blockers:
        print("\n ❌ КРИТИЧЕСКИЕ БЛОКЕРЫ:")
        for b in blockers:
            print(f"   • {b}")

    if issues:
        print("\n ⚠️ ЗАМЕЧАНИЯ К ДОРАБОТКЕ:")
        for i in issues[:5]:
            print(f"   • {i}")

    print("\n" + "=" * 60)
    print(" ВАШЕ РЕШЕНИЕ:")
    if blockers:
        print(" [!] Статья заблокирована. Требуется устранение блокеров.")
    elif isinstance(total, int) and total >= 80:
        print(" ✓ Статья готова к публикации!")
        print(" Чтобы утвердить и зафиксировать в мастер-таблице Excel, выполните:")
        file_path = find_article_file(tid, topic["slug"]) or f"сами статьи/{tid}-{topic['slug']}.md"
        print(f" python seo/scripts/add_article.py --file \"{file_path}\" --id {tid} --status ready")
    else:
        print(" ↺ Требуется точечная доработка по списку замечаний выше.")
    print("=" * 60 + "\n")


def main() -> None:
    parser = argparse.ArgumentParser(description="Оркестратор SEO-конвейера")
    parser.add_argument("--id", required=True, help="ID темы из плана")
    parser.add_argument("--step", required=True,
                        choices=["status", "research", "prompt-writer", "write", "prompt-reviewer", "review", "score", "approve"])
    args = parser.parse_args()

    plan = build_plan()
    topic = get_topic_data(plan, str(args.id))

    if args.step == "status":
        step_status(topic)
    elif args.step == "research":
        step_research(plan, topic)
    elif args.step in ("prompt-writer", "write"):
        step_prompt_writer(plan, topic)
    elif args.step in ("prompt-reviewer", "review"):
        step_prompt_reviewer(topic)
    elif args.step == "score":
        step_score(topic)
    elif args.step == "approve":
        step_approve(topic)


if __name__ == "__main__":
    main()
