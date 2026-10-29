"""Генератор статей в авторском голосе Practico через Claude Sonnet.

Использует:
1. Канонический стандарт: gem-knowledge/MASTER_EDITORIAL_BOOK.md (Глава 2)
   - Триада (§2.2): Факт -> Физический механизм -> Практическое последствие
   - Answer First (§2.5): прямой ответ в первом абзаце строго <= 40 слов
   - Живая ирония (§2.4): 2-5 сухих замечаний на статью (миф/ситуация, не читатель, без борща)
   - Реестр сплавов: theme4/data/materials-registry.php
   - Стоп-фразы (§2.10): запрет "идеальный результат", "абсолютно безопасно"
2. Строгие ограничения объёма:
   - Объём: строго 5 500 – 7 000 знаков с пробелами
   - H2 разделы: 4–8 шт.
   - FAQ: 4–6 шт. в служебном блоке `### FAQ:`
   - Источники: 2–4 документа в блоке `## Источники:`
3. Исполнитель:
   - Приоритет: claude-sonnet-4-6 через Antigravity CLI (скилл antigravity-consult)
   - Резерв: anthropic/claude-3.7-sonnet через OPENROUTER_API_KEY
"""
from __future__ import annotations

import argparse
import json
import re
import subprocess
import sys
from datetime import datetime
from pathlib import Path

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

ROOT = Path(__file__).resolve().parents[2]
SCRIPTS = ROOT / "seo" / "scripts"
sys.path.insert(0, str(SCRIPTS))

from db import backup_database, get_connection
from perplexity import get_credentials

# Резервный шлюз: актуальный Claude Sonnet на OpenRouter (июль 2026)
CLAUDE_OPENROUTER_MODEL = "anthropic/claude-sonnet-4.6"

PRACTICO_SYSTEM_PROMPT = """Ты — ведущий инженерный автор проекта «Точка Плавления».
Твоя задача — написать полноценную техническую статью по утверждённому ТЗ в авторском голосе Practico.

СТРОГИЕ ПРАВИЛА ГОЛОСА PRACTICO (MASTER_EDITORIAL_BOOK.md, ГЛАВА 2):
1. ОТНОШЕНИЕ К ЧИТАТЕЛЮ:
   - Читатель — новичок по знаниям, но взрослый коллега по достоинству. Обращение строго на «Вы».
   - Запрещён вымышленный опыт («в моей мастерской», «я за 20 лет перепаял тысячи плат»). Только объективная физика и технология.

2. ХОД ОБЪЯСНЕНИЯ (ТРИАДА PRACTICO):
   - Термин или факт -> Физический механизм (почему так) -> Практическое последствие для мастера.
   - Пример: «Припой ПОС-61 плавится при 183–190 °C. Интервал кристаллизации равен 7 °C: пока металл застывает, деталь нельзя шевелить ни секунды, иначе шов покроется микротрещинами холодной пайки».

3. ANSWER FIRST (ПЕРВЫЙ АБЗАЦ):
   - Первое же предложение даёт прямой исчерпывающий ответ на вопрос читателя.
   - Длина первого абзаца — СТРОГО до 40 слов! Без пустых разгонов («в современном мире», «на первый взгляд пайка кажется простой»).

4. СУХАЯ ИРОНИЯ И ЮМОР (§2.4):
   - Ровно 2–5 точных сухих ироничных замечаний на всю статью.
   - Форма: сухое замечание, спокойное преуменьшение («припой-инкогнито»), контраст ожидания и результата.
   - Объект иронии: нелепая ситуация, маркетинговый миф, привычка (НЕ читатель!).
   - ТАБУ: в разделах о безопасности, токсичности свинца, сетевом напряжении юмора НЕТ.
   - Никаких пищевых аналогий (каша, борщ, сметана).

5. РИТМ И СИНТАКСИС:
   - Предложения в среднем до 18–20 слов.
   - Абзацы компактные: 1–3 предложения (максимум 4).
   - Активные глаголы вместо канцелярита: «зачистите площадку», а не «осуществляется процесс зачистки».

6. СТРОГИЕ ОГРАНИЧЕНИЯ ПО ОБЪЁМУ:
   - Общий объём статьи: СТРОГО 5 500 – 7 000 знаков с пробелами!
   - 4–8 разделов H2 (сравнение, алгоритм, диагностика ошибок).

7. ОБЯЗАТЕЛЬНЫЕ БЛОКИ:
   - Раздел «## Безопасность и средства защиты» (вытяжка, защита глаз, недопустимость пайки под напряжением).
   - Раздел «## Источники и нормативные документы» (2–4 реальных ГОСТ / IPC / TDS).
   - Служебный блок в самом конце «### FAQ:» (4–6 реальных вопросов и исчерпывающих ответов). В теле статьи FAQ не писать!

8. СТОП-ФРАЗЫ (СТРОГО ЗАПРЕЩЕНЫ):
   - «идеальный результат», «абсолютно безопасно», «проще простого», «справится любой», «в заключение», «подводя итоги».
   - В конце статьи НЕ задавать вопросов читателю («А что думаете вы? Пишите в комментариях»).
"""


def call_antigravity_cli(prompt: str) -> str | None:
    """Попытка вызова Claude Sonnet через Antigravity CLI."""
    ask_ps1 = ROOT / ".agents" / "skills" / "antigravity-consult" / "scripts" / "ask.ps1"
    if not ask_ps1.exists():
        return None
    try:
        tmp_prompt = ROOT / "seo" / "data" / "tmp_writer_prompt.md"
        tmp_prompt.write_text(prompt, encoding="utf-8")
        ps_cmd = f"$sb = [scriptblock]::Create((Get-Content -Raw -Encoding UTF8 '{ask_ps1}')); & $sb -Prompt (Get-Content -Raw -Encoding UTF8 '{tmp_prompt}') -Model 'claude-sonnet-4-6'"
        p = subprocess.run(["powershell", "-NoProfile", "-Command", ps_cmd], capture_output=True, text=True, encoding="utf-8", timeout=120)
        if tmp_prompt.exists():
            tmp_prompt.unlink()
        if p.returncode == 0 and len(p.stdout) > 1000 and "Eligibility check failed" not in p.stdout:
            return p.stdout.strip()
    except Exception:
        pass
    return None


def call_openrouter_claude(prompt: str, system_prompt: str) -> str:
    """Вызов Claude Sonnet через OpenRouter API."""
    import urllib.request
    provider, api_key = get_credentials()
    if not api_key:
        raise RuntimeError("OPENROUTER_API_KEY не найден в ~/.dsh/.credentials.yaml.")

    payload = {
        "model": CLAUDE_OPENROUTER_MODEL,
        "messages": [
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": prompt},
        ],
        "temperature": 0.4,
        "max_tokens": 4000,
    }

    req = urllib.request.Request(
        "https://openrouter.ai/api/v1/chat/completions",
        data=json.dumps(payload).encode("utf-8"),
        headers={
            "Authorization": f"Bearer {api_key}",
            "Content-Type": "application/json",
            "HTTP-Referer": "https://tochka-plavleniya.ru",
            "X-Title": "Tochka Plavleniya SEO",
        },
    )

    with urllib.request.urlopen(req, timeout=90) as resp:
        data = json.loads(resp.read().decode("utf-8"))
        return data["choices"][0]["message"]["content"]


def write_article_for_topic(topic_id: int | str) -> dict:
    tid = int(topic_id) if str(topic_id).isdigit() else topic_id

    with get_connection() as conn:
        topic = conn.execute("SELECT * FROM topics WHERE id = ?", (tid,)).fetchone()
        brief_row = conn.execute("SELECT * FROM briefs WHERE topic_id = ?", (tid,)).fetchone()

    if not topic:
        raise SystemExit(f"Тема #{tid} не найдена в базе данных.")

    print(f"✍️ Написание статьи #{tid}: «{topic['title']}» голосом Practico...")
    print(f"   Кластер: {topic['cluster']} | Модель: Claude Sonnet")

    # Формируем задание для модели
    user_prompt = f"""НАПИШИ ПОЛНУЮ СТАТЬЮ ГОЛОСОМ PRACTICO ПО СЛЕДУЮЩЕМУ ТЗ:
- ID темы: #{tid}
- Заголовок H1: {topic['title']}
- Slug: {topic['slug']}
- Кластер: {topic['cluster']}
- Тип материала: {topic['type'] or 'гайд'}
"""
    if brief_row:
        user_prompt += f"""
- Целевой интент: {brief_row['intent']}
- Боль читателя: {brief_row['reader_pain']}
- LSI-ключи: {brief_row['lsi']}
"""

    user_prompt += """
ТРЕБОВАНИЯ К ФОРМАТУ СТАТЬИ:
1. Первый абзац Answer First строго до 40 слов: прямой ответ на суть темы.
2. 4–8 разделов H2 (сравнение характеристик, температурный режим, порядок действий, ошибки).
3. 2–5 точных сухих ироничных замечаний над ситуацией или мифом.
4. Раздел «## Безопасность и средства защиты».
5. Раздел «## Источники и нормативные документы» (минимум 2 ГОСТ/IPC).
6. В самом конце статьи — служебный блок:
### FAQ:
1. q: "Вопрос?"
   a: "Ответ."
(минимум 4 вопроса!).

НАПИШИ ПОЛНЫЙ ТЕКСТ СТАТЬИ В MARKDOWN (ОБЪЁМ 5 500 – 7 000 ЗНАКОВ С ПРОБЕЛАМИ).
"""

    print("   [1/3] Запрос к модели Claude Sonnet...", flush=True)
    generated_text = call_antigravity_cli(f"{PRACTICO_SYSTEM_PROMPT}\n\n{user_prompt}")
    model_used = "Claude Sonnet (Antigravity CLI)"

    if not generated_text:
        print("   (Antigravity CLI недоступен без VPN — переключение на резервный шлюз OpenRouter Claude 3.7 Sonnet)...", flush=True)
        generated_text = call_openrouter_claude(user_prompt, PRACTICO_SYSTEM_PROMPT)
        model_used = "Claude 3.7 Sonnet (OpenRouter)"

    if not generated_text or len(generated_text) < 1500:
        raise RuntimeError("Модель вернула слишком короткий текст.")

    chars_count = len(generated_text.strip())
    print(f"   [2/3] Текст получен ({chars_count} знаков с пробелами, модель: {model_used})", flush=True)

    # 1. Сохраняем в папку `сами статьи/<ID>-<slug>.md`
    articles_dir = ROOT / "сами статьи"
    articles_dir.mkdir(parents=True, exist_ok=True)
    out_file = articles_dir / f"{tid}-{topic['slug']}.md"
    out_file.write_text(generated_text, encoding="utf-8")
    print(f"   [3/3] Сохранено на диск: {out_file}", flush=True)

    # 2. Сохраняем в SQLite БД
    backup_database()
    now_str = datetime.now().isoformat(timespec="seconds")
    with get_connection() as conn:
        conn.execute("""
            UPDATE topics
            SET content_markdown = ?, chars = ?, status = 'written',
                next_action = 'написано Claude Sonnet, требуется ревью Claude Opus',
                updated_at = ?
            WHERE id = ?
        """, (generated_text, chars_count, now_str, tid))
        conn.commit()

    # 3. Автоматический скоринг текста
    from score_article import blocks as score_blocks
    report = score_blocks(generated_text, None, str(tid))
    scores_dir = ROOT / "seo" / "out" / "scores"
    scores_dir.mkdir(parents=True, exist_ok=True)
    (scores_dir / f"{tid}.json").write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")

    with get_connection() as conn:
        conn.execute("UPDATE topics SET score_total = ?, score_json = ? WHERE id = ?",
                     (report["total"], json.dumps(report, ensure_ascii=False), tid))
        conn.commit()

    print(f"\n✓ СТАТЬЯ #{tid} УСПЕШНО НАПИСАНА ГОЛОСОМ PRACTICO!")
    print(f"  • Объём: {chars_count} знаков")
    print(f"  • Оценка качества: {report['total']}/100 — {report['verdict']}")
    print(f"  • Файл: {out_file}")
    return {
        "topic_id": tid,
        "chars": chars_count,
        "score": report["total"],
        "verdict": report["verdict"],
        "file": str(out_file),
        "model": model_used,
    }


def main():
    parser = argparse.ArgumentParser(description="Написание статьи голосом Practico через Claude Sonnet")
    parser.add_argument("--id", required=True, help="ID темы из базы")
    args = parser.parse_args()
    res = write_article_for_topic(args.id)


if __name__ == "__main__":
    main()
