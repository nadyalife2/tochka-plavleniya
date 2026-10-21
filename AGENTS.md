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
1. [MASTER_EDITORIAL_BOOK.md](gem-knowledge/MASTER_EDITORIAL_BOOK.md) — стратегия, голос и содержание.
2. [EDITORIAL_WORKFLOW.md](gem-knowledge/EDITORIAL_WORKFLOW.md) — роли, таблицы, контракты и WordPress.
Эти документы имеют приоритет над редакционными дублями в legacy-файлах. Они не меняют схемы, PHP и правила Git. Не копируй голос и таблицы температур в каждый skill. Не подтверждай тесты, ОТК и публикацию без фактической проверки.
