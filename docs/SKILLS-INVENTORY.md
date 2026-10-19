# Инвентарь скиллов проекта

> Дата: 20.10.2026. Ветка: `fix/full-pass-2026-10`.
> Установлено **29 скиллов** в `.agents/skills/` (универсальный путь для DSH/Antigravity).
> Глобальный профиль и `main` не менялись.

## 1. Установленные скиллы (29)

### Стратегия и бизнес проекта
| Скилл | Источник | Назначение |
|---|---|---|
| `tchp-venture-pipeline` | проект | сквозной контур «идея → сайт → доход → изобретение» |
| `tchp-business-model` | проект | Lean Canvas, сегменты, 3 потока дохода, цены |
| `tchp-unit-economics` | проект | RPM, CAC/LTV, payback, безубыточность, сценарии |
| `tchp-invention-patent` | проект | ТРИЗ, prior art, матрица признаков, формула, гранты |
| `tchp-editorial-standards` | проект | контракты, сплавы, температуры, SHA-256, DOM-якоря |
| `build-partner` | проект | создание и валидация скиллов |

### Marketingskills (этап 1)
| Скилл | Назначение |
|---|---|
| `product-marketing` | паспорт проекта: аудитория, проблема, отличие |
| `customer-research` | интервью, VOC, JTBD, анализ транскриптов |
| `competitor-profiling` | профили конкурентов по URL |
| `offers` | оффер, ценностная упаковка, гарантии |
| `pricing` | цены, пакеты, монетизация |
| `seo-audit` | технический и on-page SEO-аудит |

### Контент, дизайн, UX
`brand`, `banner-design`, `blog-color-palette-designer`, `design`, `design-system`,
`impeccable`, `slides`, `ui-styling`, `ui-ux-auditor`, `ui-ux-pro-max`.

### Пайка и публикация
`solder-researcher`, `solder-writer`, `solder-reviewer`, `solder-seo-planner`,
`solder-wordpress-publisher`.

### Исследование
`jtbd-knowledge-skill` — verified/high из FindSkills, JTBD v3.0.
`deepseek` — консультация второй моделью.

## 2. Marketingskills: все 51

Библиотека `coreyhaines31/marketingskills` (MIT, 55k+ звёзд). Установлено 6.

| Скилл | Статус | Когда |
|---|---|---|
| `product-marketing` | ✅ установлен | этап 1 |
| `customer-research` | ✅ установлен | этап 1 |
| `competitor-profiling` | ✅ установлен | этап 1 |
| `offers` | ✅ установлен | этап 1 |
| `pricing` | ✅ установлен | этап 1 |
| `seo-audit` | ✅ установлен | этап 1 |
| `content-strategy` | ⏳ этап 2 | после паспорта проекта |
| `site-architecture` | ⏳ этап 2 | после карты конкурентов |
| `cro` | ⏳ этап 2 | после первых заявок |
| `free-tools` | ⏳ этап 2 | калькуляторы |
| `analytics` | ⏳ этап 2 | Метрика/события (адаптировать) |
| `attribution` | ⏳ этап 2 | связка заявок и выручки |
| `ai-seo` | ⏳ этап 3 | после трафика |
| `schema` | ⏳ этап 3 | аудит JSON-LD |
| `community-marketing` | ⏳ этап 3 | Telegram/VK |
| `social` | ⏳ этап 3 | адаптировать под VK/Telegram |
| `copywriting` | ⏳ этап 3 | только после настройки голоса Practico |
| `copy-editing` | ⏳ этап 3 | редактура |
| `lead-magnets` | ⏳ этап 3 | PDF-лидмагниты |
| `marketing-plan` | ⏳ этап 3 | после проверки офферов |
| `marketing-psychology` | ⏳ этап 3 | усиление офферов |
| `marketing-ideas` | ⏳ этап 3 | идеи роста |
| `marketing-council` | ⏳ этап 3 | разбор гипотез |
| `marketing-loops` | ⏳ этап 3 | регулярные процессы |
| `link-building` | ⏳ этап 3 | ссылки |
| `competitors` | ⏳ этап 3 | comparison-страницы |
| `programmatic-seo` | ⏳ этап 3 | только при данных |
| `referrals` | ⏳ этап 3 | партнёрская программа |
| `launch` | ⏳ этап 3 | запуск продукта |
| `public-relations` | ⏳ этап 3 | PR |
| `co-marketing` | ⏳ этап 3 | партнёрства |
| `events` | ⏳ этап 3 | вебинары |
| `influencer-marketing` | ⏳ этап 3 | блогеры |
| `ads` | — | Google/Meta, не РФ |
| `ad-creative` | — | к `ads` |
| `ab-testing` | — | нужен трафик |
| `analytics` (GA4) | ⏳ | адаптировать под Метрику |
| `aso` | — | нет приложения |
| `churn-prevention` | — | нет подписок |
| `cold-email` | — | не основа РФ-канала |
| `directory-submissions` | — | западные каталоги |
| `emails` | — | заменяем Telegram |
| `image` | — | есть `design` |
| `onboarding` | — | нет SaaS |
| `paywalls` | — | нет SaaS |
| `popups` | — | позже, с CRO |
| `prospecting` | — | B2B SaaS |
| `revops` | — | B2B SaaS |
| `sales-enablement` | — | B2B SaaS |
| `signup` | — | нет регистрации |
| `sms` | — | не РФ-канал |
| `video` | — | есть `FACELESS-VIDEO-KIT` |

## 3. Где лежат и как ставить ещё

- Установка: `D:\сайт для пайки\.agents\skills\<name>\SKILL.md`.
- `skills-lock.json` фиксирует источник, путь и SHA-256 каждого установленного скилла.
- Универсальный путь читают DSH, Antigravity, Gemini CLI, Copilot; Claude Code — через symlink.
- Повторная установка этапа 2:
  ```bash
  npx -y -p skills@1.7.4 skills add coreyhaines31/marketingskills \
    --skill content-strategy site-architecture cro free-tools analytics attribution --yes
  ```
- Проверка: `python tests/run-all-tests.py` (7 шагов, включая docs-links).

## 4. Безопасность

`skills.sh` показал: Gen — Safe у всех 6; Socket — 0 alerts; Snyk — Low/Medium.
Это markdown-инструкции, не исполняемый код, но перед использованием читать
`SKILL.md` и не давать им доступ к секретам/платежам.

## 5. Следующий шаг

Запустить `product-marketing` → получить `.agents/product-marketing.md` под Practico,
проверить паспорт, затем `customer-research` и `competitor-profiling`.
