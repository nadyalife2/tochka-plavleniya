---
name: tchp-model-routing
description: Model routing, VPN requirements and failover for the Тоchka Plavleniya project — which model writes, reviews, fact-checks and codes, and how the rules evolve with the repository.
argument-hint: "[models|vpn|failover|critical-article]"
metadata:
  author: tchp
  version: "1.0.0"
---

# Model Routing — Точка Плавления

Единый источник правил: [seo/MODEL-ROUTING.md](../../../seo/MODEL-ROUTING.md). При расхождении
приоритет у MODEL-ROUTING.md, этот skill — краткая операционная памятка.

## Роли

| Роль | Модель | VPN |
|---|---|---|
| Писать статьи (голос 1 / голос 2) | `claude-sonnet-5` (Oxion) | не нужен |
| Рецензия обычной статьи | `gemini-3.7-flash` (Oxion) | не нужен |
| Рецензия критичной статьи | `claude-opus-4.6` (Oxion) | не нужен |
| Фактчек (другое семейство) | `deepseek-v4-pro-0813` (Oxion) | не нужен |
| Код/техно-аудит | `deepseek-v4-pro-0813` | не нужен |
| Рутина, LSI, черновики | `deepseek-v4-flash-0731` | не нужен |
| Бесплатный резерв | `deepseek-v4.1-flash:free` (Token Harbor) | **только с VPN** |
| Бесплатный резерв №2 | `kilo-auto/free`, `nvidia/nemotron-3.5-lightning:free` (Kilo Gateway) | **только с VPN** |
| YandexGPT 5 Pro (Yandex AI Studio) | `gpt://<folder>/yandexgpt/latest` | не нужен |

Модели в селекторе DSH помечены суффиксами `· VPN ON` / `· VPN OFF`.

## Режимы Sonnet

1. **paste** — пользователь вставляет готовый текст на дашборде; Sonnet сохраняет факты и числа,
   приводит к стандартам (Answer First ≤ 40 слов, 4–8 H2, 1–2 таблицы, FAQ 4–6, источники 2–4,
   5 500–7 000 знаков).
2. **draft** — написание с нуля голосом Practico 1 (`practico`) или Practico 2 (`practico2`).

## Критичная статья

Флаг ставится в карточке на дашборде. Критично: сеть/220 В, токсичность флюсов, температуры и
сплавы, ГОСТ/IPC, медицинские/юридические утверждения, хабовые статьи. Критичная — Opus, обычная —
Gemini. Нашёл блокер у Gemini → перевести в критичные и прогнать Opus.

## Фактчек

Отдельный агент, другое семейство (DeepSeek V4 Pro), таблица «утверждение | статус | комментарий».
Требование slopmonster: полировка и фактчек — не тем семейством, что писало текст.

## VPN и каналы

- Oxion, DeepSeek, Yandex AI Studio — VPN не нужен.
- Token Harbor, Kilo AI Gateway, OpenRouter — только с VPN.
- Antigravity (Gemini 3.8) — обычно нужен VPN.
- OpenCode Zen / freetap — удалён из профиля.

## Как этот skill развивается

Правила живут в репозитории и меняются вместе с проектом:
1. Изменение маршрутизации/лимитов/критериев — сначала в [`seo/MODEL-ROUTING.md`](../../../seo/MODEL-ROUTING.md),
   затем краткое отражение здесь (этот skill читается агентами автоматически).
2. Провайдеры и ключи — в профиле DSH (`~/.dsh/profiles/web/cordis.patch.yml`, `~/.dsh/.env`),
   в репозиторий секреты не попадают.
3. Для пересборки skill из обсуждений использовать skill `build-partner` (Improve System).
4. Изменения кода/правил — отдельная ветка + PR, тесты `python tests/run-all-tests.py` до коммита.
