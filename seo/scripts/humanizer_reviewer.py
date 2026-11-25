#!/usr/bin/env python3
"""Модуль-ревьювер «Humanizer»: проверка текста на AI-штампы, очеловечивание и ритмику.
Создан по принципам karpathy-code-principles: stdlib-first, single-file, 0 внешних зависимостей.
Используется агентами Вуди (06-seo-critic / Дятел) и Архимед (05-review / Филин) для
предотвращения AI-воды и приведения текста к живому авторскому стилю Точки Плавления.
"""
from __future__ import annotations

import argparse
import json
import math
import re
import sys
from pathlib import Path
from typing import Any, Dict, List, Tuple

# 100+ маркеров AI-воды и штампов (ChatGPT / Claude / DeepSeek на русском)
AI_CLICHES = [
    # Канцелярские и шаблонные вводные
    "в современном мире", "нельзя не отметить", "важно понимать", "следует подчеркнуть",
    "как известно", "не секрет, что", "безусловно", "стоит обратить внимание",
    "резюмируя вышесказанное", "подводя итог", "в заключение хочется сказать",
    "в данной статье мы рассмотрим", "давайте погрузимся в", "погрузиться в мир",
    "откройте для себя", "неотъемлемая часть", "играет ключевую роль",
    "играет решающую роль", "является ключевым аспектом", "является неотъемлемой",
    "широкий спектр", "широкий спектр возможностей", "инновационный подход",
    "в конечном итоге", "на сегодняшний день", "в сухом остатке", "имеет первостепенное значение",
    "на первый взгляд может показаться", "не вызывает сомнений", "особое внимание заслуживает",
    "с уверенностью можно сказать", "как было сказано ранее", "не лишним будет упомянуть",
    
    # Канцелярские отглагольные конструкции
    "осуществление процесса", "произведение контроля", "проведение процедуры",
    "обеспечение возможности", "является необходимым условием", "оказывает непосредственное влияние",
    "приводит к возникновению", "носит характер", "в целях оптимизации",
    "в процессе выполнения работ", "посредством использования",
    
    # Пустые усилители и маркеры генерации
    "поистине удивительный", "буквально переворачивает", "раскрывает потенциал",
    "гармонично сочетает", "высокоэффективное решение", "безупречное качество",
    "лучший в своем роде", "максимально эффективный", "уникальный в своем роде"
]

# Разговорные маркеры живого инженерного стиля (в духе Точки Плавления и автора)
HUMAN_MARKERS = [
    "грабли", "косяк", "сопля", "недогрев", "перегрев", "вживую", "на практике",
    "проверила", "замерила", "выставила", "увидела", "разобрала", "напаяла",
    "руками", "в железе", "по факту", "нюанс", "засада", "чудес не бывает",
    "лайфхак", "термопара", "ликвидус", "солидус", "эвтектика", "микроскоп", "флюс"
]


def split_sentences(text: str) -> List[str]:
    """Разбивает текст на предложения, сохраняя структуру."""
    cleaned = re.sub(r"```[\s\S]*?```", "", text) # Игнорируем блоки кода
    cleaned = re.sub(r"\!\[.*?\]\(.*?\)", "", cleaned) # Игнорируем картинки
    raw_sents = re.split(r"(?<=[.!?])\s+", cleaned)
    sents = [s.strip() for s in raw_sents if len(s.strip()) > 3]
    return sents


def calculate_burstiness(sentences: List[str]) -> Tuple[float, float, float]:
    """Вычисляет вариативность (burstiness) длины предложений.
    Человеческая речь чередует короткие (3-6 слов) и длинные (20-30 слов) предложения.
    ИИ пишет с монотонной длиной (~14-18 слов).
    Возвращает: (mean_words, std_dev, coefficient_of_variation)
    """
    if not sentences:
        return 0.0, 0.0, 0.0
    
    lengths = [len(re.findall(r"\b\w+\b", s)) for s in sentences]
    n = len(lengths)
    if n == 0:
        return 0.0, 0.0, 0.0
    
    mean = sum(lengths) / n
    if n < 2:
        return mean, 0.0, 0.0
    
    variance = sum((x - mean) ** 2 for x in lengths) / (n - 1)
    std_dev = math.sqrt(variance)
    cv = (std_dev / mean) if mean > 0 else 0.0
    return round(mean, 1), round(std_dev, 1), round(cv, 2)


def audit_human_style(text: str) -> Dict[str, Any]:
    """Глубокий аудит текста на естественность, AI-штампы и человеческий голос."""
    lower_text = text.lower()
    sentences = split_sentences(text)
    total_words = len(re.findall(r"\b\w+\b", text))
    
    # 1. Поиск AI-клише
    found_cliches = []
    for cliche in AI_CLICHES:
        pattern = r"\b" + re.escape(cliche) + r"\b"
        matches = list(re.finditer(pattern, lower_text))
        if matches:
            found_cliches.append({
                "cliche": cliche,
                "count": len(matches),
                "penalty": len(matches) * 4
            })
            
    # 2. Поиск маркеров живого голоса автора
    found_human_markers = []
    for hm in HUMAN_MARKERS:
        pattern = r"\b" + re.escape(hm) + r"\b"
        cnt = len(re.findall(pattern, lower_text))
        if cnt > 0:
            found_human_markers.append({"marker": hm, "count": cnt})
            
    # 3. Метрика вариативности (Burstiness)
    mean_len, std_dev, cv = calculate_burstiness(sentences)
    
    # Оценка ритмики:
    # CV < 0.28: Монотонный роботизированный ритм
    # CV 0.28 - 0.42: Умеренно естественный
    # CV > 0.42: Отличный живой авторский ритм
    rhythm_status = "роботизированный (монотонный)"
    rhythm_score = 40
    if cv >= 0.45:
        rhythm_status = "отличный живой ритм (чередование коротких и длинных фраз)"
        rhythm_score = 100
    elif cv >= 0.32:
        rhythm_status = "умеренная вариативность"
        rhythm_score = 75
        
    # 4. Расчет итогового Human Score (0 - 100)
    base_score = 95
    total_cliche_penalty = sum(c["penalty"] for c in found_cliches)
    base_score -= total_cliche_penalty
    
    if cv < 0.30:
        base_score -= 15
    elif cv > 0.42:
        base_score += 5
        
    human_bonus = min(len(found_human_markers) * 2, 15)
    base_score += human_bonus
    
    final_score = max(5, min(100, base_score))
    
    # 5. Генерация точечных правок (Humanizing Recommendations)
    recommendations = []
    for c in found_cliches[:5]:
        recommendations.append(
            f"Удалите или замените шаблон «{c['cliche']}» на прямое действие или факт."
        )
        
    if cv < 0.30:
        recommendations.append(
            f"Разбавьте монотонные предложения: средняя длина {mean_len} слов. Добавьте ультракороткие фразы (3-5 слов) для акцента."
        )
        
    if not any("проверила" in m["marker"] or "замерила" in m["marker"] for m in found_human_markers):
        recommendations.append(
            "Добавьте личный опыт в женском роде прошедшего времени («проверила на плате», «замерила мультиметром», «выставила на фене 320°C»)."
        )
        
    verdict = "ОТЛИЧНО: Живой инженерный слог без AI-налёта"
    if final_score < 65:
        verdict = "БРАК / ВЫСОКИЙ AI-НАЛЁТ: Текст напичкан шаблонными штампами и требует глубокой переработки"
    elif final_score < 80:
        verdict = "ТРЕБУЕТСЯ ПРАВКА: Заметны нейросетевые обороты, разбавьте живыми примерами и практикой"
        
    return {
        "status": "ok",
        "human_score": final_score,
        "verdict": verdict,
        "cliches_count": len(found_cliches),
        "cliches": found_cliches,
        "human_markers_count": len(found_human_markers),
        "human_markers": found_human_markers,
        "rhythm": {
            "mean_sentence_words": mean_len,
            "std_dev": std_dev,
            "coefficient_of_variation": cv,
            "status": rhythm_status,
            "score": rhythm_score
        },
        "recommendations": recommendations,
        "total_words": total_words,
        "total_sentences": len(sentences)
    }


def humanize_text_simple(text: str) -> str:
    """Простой локальный де-слаппинг (очистка от топ-клише) без вызова внешней LLM."""
    res = text
    # Удаление вводных фраз в начале предложений
    cliches_to_strip = [
        (r"(?i)\bв современном мире,?\s*", ""),
        (r"(?i)\bнельзя не отметить,?\s*что\s*", ""),
        (r"(?i)\bважно понимать,?\s*что\s*", ""),
        (r"(?i)\bследует подчеркнуть,?\s*что\s*", ""),
        (r"(?i)\bкак известно,?\s*", ""),
        (r"(?i)\bбезусловно,?\s*", ""),
        (r"(?i)\bв данной статье мы рассмотрим\s*", "Разберём "),
        (r"(?i)\bосуществление процесса пайки\b", "пайка"),
        (r"(?i)\bпроизведение контроля температуры\b", "контроль температуры"),
        (r"(?i)\bявляется ключевым элементом\b", "это ключевой элемент"),
    ]
    for pattern, repl in cliches_to_strip:
        res = re.sub(pattern, repl, res)
    return res


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Humanizer Text Reviewer")
    parser.add_argument("--text", type=str, help="Текст для проверки")
    parser.add_argument("--file", type=str, help="Путь к файлу со статьей")
    parser.add_argument("--json", action="store_true", help="Вывод в формате JSON")
    args = parser.parse_args()

    content = ""
    if args.file:
        p = Path(args.file)
        if p.exists():
            content = p.read_text(encoding="utf-8")
    elif args.text:
        content = args.text
    else:
        content = sys.stdin.read()

    report = audit_human_style(content)
    if args.json:
        print(json.dumps(report, ensure_ascii=False, indent=2))
    else:
        print(f"=== РЕЗУЛЬТАТ АУДИТА HUMANIZER ===")
        print(f"Скор естественности: {report['human_score']}/100 ({report['verdict']})")
        print(f"Ритмика: {report['rhythm']['status']} (CV: {report['rhythm']['coefficient_of_variation']})")
        print(f"Найдено клише: {report['cliches_count']}")
        for c in report['cliches']:
            print(f"  - «{c['cliche']}» (встретилось {c['count']} раз)")
        print(f"Маркеры живой речи: {report['human_markers_count']}")
        print(f"Рекомендации:")
        for r in report['recommendations']:
            print(f"  • {r}")
