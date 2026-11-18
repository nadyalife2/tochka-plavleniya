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
   - Резерв: anthropic/claude-sonnet-4.6 через OPENROUTER_API_KEY
"""
from __future__ import annotations

import argparse
import json
import re
import subprocess
import sys
import urllib.error
import urllib.request
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


PRACTICO2_SYSTEM_PROMPT = """Ты — автор проекта «Точка Плавления» во втором голосе: PRACTICO 2 «Голос заказчика».
Это голос владельца мастерской, который платит за инструмент и материалы из своего кармана.
Он не читает лекций и не показывает эрудицию. Он объясняет так, чтобы человек завтра сделал работу и не потерял деньги.

ЧЕМ ЭТОТ ГОЛОС ОТЛИЧАЕТСЯ ОТ PRACTICO 1:
- Practico 1 — инженер: физика процесса, ГОСТ, сухая ирония.
- Practico 2 — заказчик: польза, цена ошибки, время, «делай так и не мучайся».
- Practico 2 короче и прямее. Меньше теории, больше «что купить», «сколько это стоит», «где вас обманут».

1. ОТНОШЕНИЕ К ЧИТАТЕЛЮ:
   - Обращение на «Вы», без заискивания и без снисходительности.
   - Читатель — взрослый человек, у которого есть задача и ограниченный бюджет. Его время дороже вашего текста.

2. ГЛАВНЫЙ ПРИНЦИП: СНАЧАЛА ВЫГОДА, ПОТОМ ТЕХНИКА.
   - Каждый раздел отвечает на вопрос «что мне это даёт» или «сколько мне это будет стоить».
   - Формула абзаца: что делать -> сколько стоит / сколько времени -> что будет, если сделать наоборот.

3. ANSWER FIRST (ПЕРВЫЙ АБЗАЦ):
   - Первое предложение — прямой ответ. Строго до 40 слов.
   - Сразу цифра или решение. Без «в современном мире», «многие задаются вопросом», «на первый взгляд кажется».

4. РИТМ — РУБЛЕНЫЙ:
   - Предложения короткие: в среднем до 15–18 слов. Максимум 22.
   - Абзацы по 1–2 предложения. Один абзац — одна мысль.
   - Разрешены короткие вставки-констатации: «Это дорого. Это не нужно. Это работает.»

5. ИНТОНАЦИЯ:
   - Спокойная деловая прямота. Допустима лёгкая ирония над рынком и маркетингом — но без сарказма в адрес читателя.
   - ТАБУ: шутить в разделах про безопасность, свинец, электричество и здоровье. Там только факты.
   - Запрещены канцелярит и «умные» слова без расшифровки.

6. ЧЕСТНОСТЬ ПРО ДЕНЬГИ И ОГРАНИЧЕНИЯ (обязательно):
   - Указывайте, где дешёвое решение работает, а где выброшенные деньги.
   - Если что-то нельзя сделать без оборудования — так и скажите, без обтекаемых формулировок.
   - Не обещать «вечный» результат и «100% гарантию».

7. СТРУКТУРА (обязательно):
   - H1 — задача читателя, без кликбейта.
   - 4–7 разделов H2. Хотя бы один раздел — с цифрами затрат, временем или расходом материалов.
   - Хотя бы один раздел «где ошибаются и сколько это стоит».
   - Раздел «## Безопасность и средства защиты» — коротко и по делу.
   - Раздел «## Источники и нормативные документы» — 2–4 реальных ГОСТ / IPC / TDS.
   - Служебный блок в самом конце «### FAQ:» — 4–6 практических вопросов, ответы в 1–3 предложения.

8. ОБЪЁМ: строго 5 500 – 7 000 знаков с пробелами.

9. СТОП-ФРАЗЫ (запрещены):
   - «идеальный результат», «абсолютно безопасно», «проще простого», «справится любой», «в заключение», «подводя итоги», «стоит отметить».
   - Вопросов читателю в конце не задавать.
"""

# Реестр голосов: ключ -> (системный промпт, читаемое имя)
VOICES: dict[str, tuple[str, str]] = {
    "practico": (PRACTICO_SYSTEM_PROMPT, "Practico 1 — инженер"),
    "practico2": (PRACTICO2_SYSTEM_PROMPT, "Practico 2 — голос заказчика"),
}


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


def call_deepseek(prompt: str, system_prompt: str) -> str | None:
    """DeepSeek официальный API (platform.deepseek.com). Работает из РФ без VPN."""
    try:
        from keys import get_key
        api_key = get_key("DEEPSEEK_API_KEY")
    except Exception:
        api_key = ""
    if not api_key:
        print("   (DeepSeek: ключ DEEPSEEK_API_KEY пустой в config/api-keys.local.yaml)...", flush=True)
        return None

    body = {
        "model": "deepseek-chat",
        "messages": [
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": prompt},
        ],
        "temperature": 0.35,
        "max_tokens": 4000,
    }
    req = urllib.request.Request(
        "https://api.deepseek.com/chat/completions",
        data=json.dumps(body, ensure_ascii=False).encode("utf-8"),
        headers={"Authorization": f"Bearer {api_key}", "Content-Type": "application/json"},
    )
    try:
        with urllib.request.urlopen(req, timeout=300) as resp:
            data = json.loads(resp.read().decode("utf-8"))
        return data["choices"][0]["message"]["content"]
    except urllib.error.HTTPError as e:
        err_body = e.read().decode("utf-8", "replace")[:300]
        print(f"   (DeepSeek HTTP {e.code}: {err_body})...", flush=True)
        return None
    except Exception as e:  # noqa: BLE001
        print(f"   (DeepSeek ошибка: {type(e).__name__}: {str(e)[:150]})...", flush=True)
        return None


def call_yandex_ai_studio(prompt: str, system_prompt: str) -> str | None:
    """Третий резерв: Yandex AI Studio (YandexGPT). Работает из РФ без VPN.

    Ключ и folderId берутся из config/api-keys.local.yaml (см. keys.py).
    Модель: YandexGPT Pro 32k — слабее Claude по голосу, но канал доступен всегда.
    """
    try:
        from keys import get_key
        api_key = get_key("YANDEX_AI_API_KEY")
        folder_id = get_key("YANDEX_FOLDER_ID")
    except Exception:
        return None
    if not api_key or not folder_id:
        return None

    body = {
        "modelUri": f"gpt://{folder_id}/yandexgpt-32k/latest",
        "completionOptions": {"stream": False, "temperature": 0.35, "maxTokens": 4000},
        "messages": [
            {"role": "system", "text": system_prompt},
            {"role": "user", "text": prompt},
        ],
    }
    req = urllib.request.Request(
        "https://llm.api.cloud.yandex.net/foundationModels/v1/completion",
        data=json.dumps(body, ensure_ascii=False).encode("utf-8"),
        headers={"Authorization": f"Api-Key {api_key}", "Content-Type": "application/json"},
    )
    try:
        with urllib.request.urlopen(req, timeout=180) as resp:
            data = json.loads(resp.read().decode("utf-8"))
        alternatives = data.get("result", {}).get("alternatives") or []
        if not alternatives:
            return None
        text = alternatives[0].get("message", {}).get("text", "")
        return text or None
    except Exception:
        return None


def call_openrouter_claude(prompt: str, system_prompt: str) -> str:
    """Вызов Claude Sonnet через OpenRouter API."""
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

    try:
        with urllib.request.urlopen(req, timeout=90) as resp:
            data = json.loads(resp.read().decode("utf-8"))
            return data["choices"][0]["message"]["content"]
    except urllib.error.HTTPError as e:
        body = ""
        try:
            body = e.read().decode("utf-8", "replace")[:300]
        except Exception:
            pass
        if e.code == 403:
            raise RuntimeError(
                "HTTP 403 «Access denied by security policy». "
                "OpenRouter блокирует запросы из РФ на уровне гео-политики.\n"
                "Что делать: включите VPN/прокси и повторите. Без VPN работает только локальный конвейер "
                "(скоринг, приоритеты, упаковка контракта) — он не требует интернета."
            ) from None
        if e.code == 401:
            raise RuntimeError("HTTP 401: ключ OPENROUTER_API_KEY недействителен. Проверьте ~/.dsh/.credentials.yaml.") from None
        if e.code == 402:
            raise RuntimeError("HTTP 402: на балансе OpenRouter недостаточно средств.") from None
        if e.code == 429:
            raise RuntimeError("HTTP 429: слишком много запросов к OpenRouter. Подождите минуту.") from None
        raise RuntimeError(f"HTTP {e.code} от OpenRouter: {body}") from None


def _keyword_context(title: str) -> str:
    """Собирает реальные SEO-данные из базы для включения в задание автору.

    Берём то, что уже измерено инструментами (Вордстат/Мутаген), чтобы автор
    строил разделы и FAQ вокруг настоящих запросов, а не выдумывал их.
    """
    parts: list[str] = []
    matched = ""
    try:
        with get_connection() as conn:
            mg = None
            try:
                mg = conn.execute(
                    "SELECT keyword, strong, wordstat, tails, vital_site FROM keyword_metrics WHERE keyword = ?",
                    (title,),
                ).fetchone()
            except Exception:
                mg = None
            ws = None
            try:
                ws = conn.execute(
                    "SELECT phrase, total_count, top_json FROM wordstat_metrics WHERE phrase = ?", (title,)
                ).fetchone()
            except Exception:
                ws = None
            # если точного совпадения нет — ищем ближайшую измеренную фразу по словам
            if not mg and not ws:
                for w in [x for x in title.lower().split() if len(x) > 4][:2]:
                    try:
                        row = conn.execute(
                            "SELECT keyword, strong, wordstat, tails, vital_site FROM keyword_metrics "
                            "WHERE keyword LIKE ? ORDER BY wordstat DESC LIMIT 1",
                            (f"%{w}%",),
                        ).fetchone()
                    except Exception:
                        row = None
                    if row:
                        mg = row
                        matched = row["keyword"]
                        try:
                            ws = conn.execute(
                                "SELECT phrase, total_count, top_json FROM wordstat_metrics WHERE phrase = ?",
                                (matched,),
                            ).fetchone()
                        except Exception:
                            ws = None
                        break
    except Exception:
        return ""

    if matched:
        parts.append(f"- Ближайшая измеренная фраза к теме: «{matched}» (точного замера по заголовку нет)")

    if mg:
        mg = dict(mg)
        strong = mg.get("strong")
        demand = mg.get("wordstat")
        tails = mg.get("tails")
        vital = mg.get("vital_site")
        parts.append(
            f"- Замеренная конкуренция фразы: {strong}/25 "
            f"({'низкая' if strong is not None and strong <= 10 else 'средняя' if strong is not None and strong <= 18 else 'высокая'})"
        )
        if demand is not None:
            parts.append(f"- Точная частотность фразы (в кавычках): {demand} показов/мес")
        if tails:
            parts.append(f"- Объём хвостов вокруг темы: {tails} показов/мес")
        if vital:
            parts.append(f"- В топе есть витальный сайт: {vital} (обойти можно только точностью фактов и глубиной)")
    if ws:
        ws = dict(ws)
        parts.append(f"- Широкая частотность темы (Вордстат): {ws.get('total_count')} показов/мес")
        try:
            tops = [it.get("phrase") for it in (json.loads(ws.get("top_json") or "[]"))[:8] if it.get("phrase")]
        except Exception:
            tops = []
        if tops:
            parts.append("- Реальные запросы людей (использовать в подзаголовках и FAQ): " + "; ".join(tops))
    return "\n".join(parts)


def write_article_for_topic(topic_id: int | str, voice: str = "practico", provider: str = "auto") -> dict:
    tid = int(topic_id) if str(topic_id).isdigit() else topic_id
    system_prompt, voice_name = VOICES.get(voice, VOICES["practico"])
    provider = (provider or "auto").lower()
    if provider not in ("auto", "claude", "deepseek", "yandex"):
        provider = "auto"

    with get_connection() as conn:
        topic = conn.execute("SELECT * FROM topics WHERE id = ?", (tid,)).fetchone()
        brief_row = conn.execute("SELECT * FROM briefs WHERE topic_id = ?", (tid,)).fetchone()

    if not topic:
        raise SystemExit(f"Тема #{tid} не найдена в базе данных.")

    print(f"✍️ Написание статьи #{tid}: «{topic['title']}» голосом Practico...")
    print(f"   Кластер: {topic['cluster']} | Голос: {voice_name} | Канал: {provider}")

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

    kw_ctx = _keyword_context(topic["title"])
    if kw_ctx:
        user_prompt += f"""
РЕАЛЬНЫЕ SEO-ДАННЫЕ ПО ТЕМЕ (измерено Вордстатом и Мутагеном, не выдумывать своё):
{kw_ctx}
Как их использовать:
- главную фразу поставь в H1 и в первый абзац (Answer First) без переспама;
- 2–3 подзаголовка H2 сформулируй из реальных запросов людей выше;
- 3–4 вопроса FAQ возьми из этих запросов дословно, ответь по существу;
- не повторяй одну фразу в каждом абзаце: поисковик считает это переспамом.
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

    generated_text = None
    model_used = ""

    def try_claude() -> str | None:
        nonlocal model_used
        text = call_antigravity_cli(f"{system_prompt}\n\n{user_prompt}")
        if text:
            model_used = f"Claude Sonnet (Antigravity CLI), {voice_name}"
            return text
        print(f"   (Antigravity CLI недоступен без VPN — пробую OpenRouter: {CLAUDE_OPENROUTER_MODEL})...", flush=True)
        try:
            text = call_openrouter_claude(user_prompt, system_prompt)
            model_used = f"{CLAUDE_OPENROUTER_MODEL} (OpenRouter), {voice_name}"
            return text
        except Exception as ex:  # noqa: BLE001
            print(f"   (OpenRouter недоступен: {str(ex)[:90]})...", flush=True)
            return None

    def try_deepseek() -> str | None:
        nonlocal model_used
        print("   (Пробую DeepSeek официальный API из РФ)...", flush=True)
        text = call_deepseek(user_prompt, system_prompt)
        if text:
            model_used = f"DeepSeek Chat (api.deepseek.com), {voice_name}"
        else:
            print("   (DeepSeek недоступен: нет ключа или сеть блокирует)...", flush=True)
        return text

    def try_yandex() -> str | None:
        nonlocal model_used
        print("   (Пробую Yandex AI Studio — YandexGPT 32k)...", flush=True)
        text = call_yandex_ai_studio(user_prompt, system_prompt)
        if text:
            model_used = f"YandexGPT Pro 32k (AI Studio), {voice_name}"
        else:
            print("   (Yandex AI Studio недоступен)...", flush=True)
        return text

    if provider == "claude":
        generated_text = try_claude()
    elif provider == "deepseek":
        generated_text = try_deepseek()
    elif provider == "yandex":
        generated_text = try_yandex()
    else:  # auto: максимальный шанс получить текст
        print(f"   [1/3] Запрос к модели (голос: {voice_name}, канал: авто)...", flush=True)
        generated_text = try_claude() or try_deepseek() or try_yandex()

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

    print(f"\n✓ СТАТЬЯ #{tid} УСПЕШНО НАПИСАНА ГОЛОСОМ: {voice_name.upper()}")
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
        "voice": voice,
    }


def main():
    parser = argparse.ArgumentParser(description="Написание статьи голосом Practico через Claude Sonnet")
    parser.add_argument("--id", required=True, help="ID темы из базы")
    parser.add_argument("--voice", default="practico", choices=sorted(VOICES.keys()),
                        help="голос автора: practico (инженер) или practico2 (голос заказчика)")
    parser.add_argument("--provider", default="auto", choices=["auto", "claude", "deepseek", "yandex"],
                        help="канал генерации: auto (каскад) / claude / deepseek / yandex")
    args = parser.parse_args()
    write_article_for_topic(args.id, voice=args.voice, provider=args.provider)


if __name__ == "__main__":
    main()
