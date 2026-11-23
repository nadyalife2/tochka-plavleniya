# Triad-Omni Agent Bundle (Skills, Rules & MCP)

Данный архив содержит полный переносимый комплект навыков, системных инструкций и MCP-инструментов, экспортированный из среды Antigravity.

## Состав комплекта:
1. **`skills/`** — 12 специализированных навыков (`SKILL.md`):
   - `gadget-camera-tuning` (сенсоры, Camera2 API, PMIC/NTC)
   - `mobile-android-design` (Material Design 3, UI/UX)
   - `awesome-rea` (база знаний по реверсу)
   - `reverse-engineer-anything` (декомпиляция и анализ прошивок)
   - `budget-tablet-unmasking-tuning` (аппаратный анмаскинг и AOT)
   - `melvin-edge-ai` (on-device LLM, PRoot Alpine, Bionic)
   - `graphify` (GraphRAG, анализ архитектуры кодовой базы)
   - `impeccable` (профессиональный фронтенд и дизайн)
   - инструментарий REA (анализ, тулы, тесты, ченджлог)

2. **`rules/`** — системные правила и авторский голос:
   - `AGENTS.md` / `CLAUDE.md` / `.cursorrules` — правила для агента.
   - `USER_VOICE_STYLE_GUIDE.md` — профиль голоса автора (женский род прошедшего времени, живой темп, zero fluff).

3. **`mcp/`** — универсальный сервер `omni_mcp_server.py` (7 инструментов для вызова телефонов, Graphify и эскалации).

4. **`knowledge/`** — база знаний:
   - `cluster_docs/` — спецификации 01–08 по сборке и питанию кластера.
   - `wiki/` — офлайн-вики графа знаний на 954 статьи.

---

## Как подключить к стороннему харнессу на ПК:

### Вариант 1: DeepSeek Harness
Скопируйте папку `skills/` в директорию навыков вашего харнесса:
```bash
cp -r skills/* ~/deepseek-harness/skills/
cp rules/AGENTS.md ~/deepseek-harness/system_prompt.md
```

### Вариант 2: Claude Code CLI
```bash
cp rules/CLAUDE.md ~/CLAUDE.md
cp -r skills/* ~/.claude/skills/
```

### Вариант 3: Cursor / VS Code
Поместите `rules/.cursorrules` в корень вашего рабочего репозитория.

### Вариант 4: Подключение MCP
В конфигурацию MCP любого агента (`claude_desktop_config.json`):
```json
{
  "mcpServers": {
    "triad-omni": {
      "command": "python",
      "args": ["D:/для one plus nord n10/exported_harness_bundle/mcp/omni_mcp_server.py"],
      "env": {
        "HUB_URL": "http://192.168.1.203:8000",
        "OMNI_TOKEN": "ВАШ_OMNI_TOKEN"
      }
    }
  }
}
```
