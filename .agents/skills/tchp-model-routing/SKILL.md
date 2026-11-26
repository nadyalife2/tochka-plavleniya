---
name: tchp-model-routing
description: Model routing, VPN requirements, failover and Council of Models (10 models) for the Тоchka Plavleniya project — roles, channels, and cross-model validation rules.
argument-hint: "[models|council|vpn|failover|critical-article]"
metadata:
  author: tchp
  version: "2.1.0"
---

# Совет моделей и маршрутизация — Точка Плавления

Единый источник правил Совета моделей и маршрутизации конвейера «Точка Плавления».

## 1. Совет моделей (10 членов коллегии)

| № | Модель | Роль в проекте | Канал / Провайдер | Назначение |
|---|---|---|---|---|
| **1** | **Gemini 3.8 / 4 (Google)** | **Координатор, UX-директор, архитектура** | Текущая среда + `antigravity-consult` | Общая координация, UX/UI верстака и лонгридов, оперативное ревью |
| **2** | **Claude Sonnet 4.6 / Opus 4.6 (Anthropic)** | **Главный редактор, голос Practico** | `antigravity-consult` (OAuth CLI) + DSH / Oxion | Редактура, статьи голосом Practico, аудит критичных тем (220В, безопасность, ГОСТ/IPC) |
| **3** | **ddgw/gpt-5.6-luna** | **Универсал, vision, упаковка данных** | OmniRoute / DSH | Комплексные проверки UI, структурирование схем и JSON, кросс-валидация |
| **4** | **ddgw/claude-haiku-4-5** | **Точный анализ кода (Claude 4.5 Haiku)** | OmniRoute / DSH (DDGW) | Быстрый и строгий аудит кода калькуляторов, CSS-токенов, скриптов |
| **5** | **gc/grok-4.7** | **Флагман xAI (Комплексный анализ)** | OmniRoute / DSH (gc) | Внесистемный критический анализ, поиск неочевидных пробелов и альтернативных гипотез |
| **6** | **pplx-web/pplx-auto** | **Верховный онлайн-фактчекер (Perplexity AI)** | OmniRoute (`pplx-web`) + Desktop App | Поиск в реальном времени: свежие ревизии ГОСТ, IPC-A-610H (2025–2026), даташиты производителей (Kester, Indium, AIM) |
| **7** | **moonshotai/kimi-k3.1-pro** | **Kimi K3.1 Pro (Vision & Reasoning)** | NVIDIA NIM · Аккаунт 2 | Глубокое логическое рассуждение, анализ графиков термопрофилей и диаграмм |
| **8** | **minimaxai/minimax-m3.1-flash** | **MiniMax M3.1 Flash** | NVIDIA NIM · Аккаунт 2 | Быстрый семантический скоринг, LSI-кластеризация, оперативная фильтрация |
| **9** | **mistralai/mistral-large-4-1t-a52b** | **Mistral Large 4 1T (Vision & Reasoning)** | NVIDIA NIM · Аккаунт 2 | Тяжёлый инженерный фактчекинг, сверка физики пайки и химических свойств сплавов/флюсов |
| **10** | **z-ai/glm-5.3-flash** | **GLM 5.3 Flash** | NVIDIA NIM · Аккаунт 2 | Вспомогательный быстрый анализ, синтез выжимок, валидация метаданных |

---

## 2. Кросс-модельные сценарии (Консилиум)

1. **Критическая статья (Безопасность / 220В / ГОСТ):**
   - Черновик: Claude Sonnet 4.6
   - Онлайн-проверка стандартов и даташитов: Perplexity AI (`pplx-web/pplx-auto`)
   - Инженерный фактчек: Mistral Large 4 1T (NVIDIA NIM) + Kimi K3.1 Pro
   - Финальное ОТК: Claude Opus 4.6
2. **UI/UX & Архитектура верстака (`DESIGN.md`):**
   - Архитектура и токен-система: Gemini 3.8 / 4 (`ux-director`)
   - Аудит кода компонентов: Claude 4.5 Haiku (`ddgw/claude-haiku-4-5`)
   - Vision & layout-контроль: `ddgw/gpt-5.6-luna` + Playwright
3. **Сложные инженерные споры / ТРИЗ / Патенты:**
   - Позиция A: Gemini 4 + Mistral Large 4 1T
   - Пруфы из живой сети: Perplexity AI
   - Критическая контр-позиция: Grok 4.7 (`gc/grok-4.7`) + Kimi K3.1 Pro
   - Синтез решения: Координатор

---

## 3. Требования по каналам и VPN

- **Oxion AI / Antigravity CLI (Google OAuth):** работают напрямую без сторонних прокси.
- **NVIDIA NIM (Аккаунт 2):** Kimi K3.1 Pro, MiniMax M3.1 Flash, Mistral Large 4 1T, GLM 5.3 Flash через MCP `nvidia` / direct API.
- **OmniRoute / DSH (порт 20128):** маршрутизация `ddgw/gpt-5.6-luna`, `ddgw/claude-haiku-4-5`, `gc/grok-4.7`, `pplx-web/pplx-auto`.
