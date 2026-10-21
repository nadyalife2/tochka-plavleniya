# Agent Guidelines & Rules — ТОЧКА ПЛАВЛЕНИЯ

## UI/UX Tool Policy
Use the UI/UX Pro Max skill as the primary design and implementation workflow.
Use MCP tools in this sequence:
1. `ui-ux-pro`: research suitable interface patterns, typography, layout and components.
2. `design-systems`: validate decisions against accessibility, W3C, WCAG and reusable design-system principles.
3. `ux-audit`: audit the completed page against Nielsen heuristics, readability, responsive layout, SEO and usability.
4. `Playwright`: inspect the actual local page at 375, 768, 1024 and 1440 px.

The visual authority is `/DESIGN.md`, not generic MCP preferences.

Never:
- Replace the project’s sketch/paper visual identity with generic SaaS UI.
- Add gradients, glassmorphism, neon, emojis as UI icons, or purple AI palettes.
- Modify source code, commit, push, create branches or PRs until the user explicitly approves the implementation plan.
- Treat an automated accessibility pass as a substitute for visual and keyboard-navigation checks.

## Git workflow

- `main` — защищённая ветка. Не коммитить и не пушить в `main` напрямую.
- Любая правка — отдельная ветка `fix/*`, `feat/*`, `docs/*` + PR.
- Перед коммитом: `python tests/run-all-tests.py` должен быть зелёным.
- Не переписывать опубликованную историю (`force-push`) без явного разрешения.

## Editorial routing
Для исследования, статей, редактуры и подготовки публикации сначала прочитай:
1. [MASTER_EDITORIAL_BOOK.md](gem-knowledge/MASTER_EDITORIAL_BOOK.md) — единая редакционная книга (стратегия, голос Practico, терминология, факты, типы статей, SEO, таблицы, приёмка).
2. Инструкции профильных агентов в `gem-knowledge/`:
   - [AGENT_01_RESEARCH_AND_SEO.md](gem-knowledge/AGENT_01_RESEARCH_AND_SEO.md) — исследование, доказательства и бриф.
   - [AGENT_02_AUTHOR.md](gem-knowledge/AGENT_02_AUTHOR.md) — написание и расширение статьи голосом Practico.
   - [AGENT_03_REVIEW_AND_PUBLISH.md](gem-knowledge/AGENT_03_REVIEW_AND_PUBLISH.md) — рецензирование, технический паспорт статьи, валидация по схеме v1.1 и контролируемая доставка.
Эти документы имеют приоритет над любыми устаревшими правилами. Они не меняют схемы, PHP и правила Git. Не подтверждай тесты, ОТК и публикацию без фактической проверки.
