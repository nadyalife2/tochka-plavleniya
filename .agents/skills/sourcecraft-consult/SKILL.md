---
name: sourcecraft-consult
description: Второе мнение и код-ревью через SourceCraft CLI (аккаунт Яндекса, бесплатные нейрокредиты Free-тарифа) — headless-запуск агента без API-ключа и без VPN.
argument-hint: "[consult|review] [prompt or file path]"
metadata:
  author: tchp
  version: "1.0.0"
---

# SourceCraft Consult

Второе мнение от моделей Яндекса через `src` CLI (SourceCraft). Авторизация — Яндекс-аккаунтом
(OAuth, один раз `src auth login`), расход — нейрокредиты Free-тарифа (500/мес). API-ключ не нужен,
VPN не нужен, работает из РФ.

## Когда использовать

- кросс-проверка инженерного утверждения или статьи (другое семейство моделей);
- ревью фрагмента кода/шаблона WordPress, PHP, Python;
- быстрый вопрос, который не жалко потратить на бесплатную квоту;
- когда Oxion/Token Harbor недоступны (нет VPN / кончились токены).

Не использовать для ежедневного кодинга: 500 нейрокредитов в месяц — это консультации, а не поток.
На Free-тарифе модель не выбирается — работает «SourceCraft Default» (ансамбль).

## Проверка доступа

```powershell
& "$env:LOCALAPPDATA\Programs\src\src.exe" auth status
& "$env:LOCALAPPDATA\Programs\src\src.exe" quota --section AI --json
```

Если `Authentication required` — один раз выполнить в своём терминале:

```powershell
& "$env:LOCALAPPDATA\Programs\src\src.exe" auth login
```

Откроется браузер с Яндекс-аккаунтом; после входа CLI готов к работе.
Проверено на аккаунте `nadyalife1` (IAM, keyring): `auth status` → `Successfully authenticated`.

Перед первым `src do` нужен OpenCode:

```powershell
& "$env:LOCALAPPDATA\Programs\src\src.exe" code install
```

Один вызов `src do --no-tools` стоит ~0.8 нейрокредита (пример: 21 845 in / 207 out = 0.77 CU),
то есть Free-квоты 500 CU хватает примерно на 600 консультаций в месяц.

## Запуск headless-консультации

Готовый скрипт: [ask.ps1](scripts/ask.ps1)

```powershell
powershell -ExecutionPolicy Bypass -File .agents/skills/sourcecraft-consult/scripts/ask.ps1 `
  -Prompt "Проверь с точки зрения физики: температура жала 320 °C для ПОС-61 — норма?" `
  -NoTools
```

Или напрямую:

```powershell
& "$env:LOCALAPPDATA\Programs\src\src.exe" do "твой промпт" --json
```

Флаги, которые реально нужны:
- `--no-tools` — чистая консультация без доступа к файлам/репозиторию (безопасно);
- `--json` — машинный вывод для агента;
- `--model default` — только если включена платная подписка с выбором модели.

## Ограничения и безопасность

- `src do` без `--no-tools` может читать и менять файлы репозитория — для консультаций всегда `--no-tools`.
- Ответы — мнение другой модели, не источник истины; цифры проверять по `MASTER_EDITORIAL_BOOK.md` и ГОСТ/IPC.
- Секреты проекта в промпт не вставлять (ключи, пароли, токены).
- Стоимость: списывается из нейрокредитов аккаунта; проверять остаток через `src quota`.
