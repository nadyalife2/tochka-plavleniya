# Бэкап настроек DeepSeek Harness (DSH)

Скрипты сохраняют **все настройки DSH**: подключённые плагины, профили, DeepSeek OAuth,
ключи провайдеров, а также Google/Antigravity OAuth («Google Pro») и MCP-серверы.
Один архив — одно восстановление на любой машине.

```
tools/dsh-backup/
├── backup-dsh-settings.ps1    # создаёт архив
├── restore-dsh-settings.ps1   # возвращает настройки на место
└── README.md                  # этот файл
```

## Что где лежит (карта настроек)

| Что | Путь | В архиве |
|---|---|---|
| **DeepSeek OAuth** (device + account token, browser-session) | `%USERPROFILE%\.dsh\.credentials.yaml` | ✅ |
| **Ключи провайдеров** `OPENROUTER_API_KEY`, `DASHSCOPE_API_KEY` | там же, секция `refs` | ✅ |
| **Плагины и профили** (`dsh-freetap`, `dsh-subscriptions`, `dsh-deepseek-web-login`, `dsh-tool-antigravity`, `dsh-opencode-free-models`, patches, `cordis.patch.yml`) | `%USERPROFILE%\.dsh\profiles\web\` | ✅ (без `node_modules`, они переустанавливаются) |
| **Выбранные провайдер/модель, reasoningEffort** | `profiles\web\cordis.patch.yml` | ✅ |
| **Рабочие пространства и привязка сессий** | `.dsh\storages\workspace.json` | ✅ |
| **История чатов** | `.dsh\sessions\` | ✅ (можно `-NoSessions`) |
| **Аккаунт DeepSeek web-login** | `.dsh\web-login\` (без браузерного профиля) | ✅ |
| **Google OAuth** («Google Pro», Antigravity) — access/refresh/id token | `%USERPROFILE%\.gemini\oauth_creds.json` | ✅ |
| **Google-аккаунты** (активный + старые) | `.gemini\google_accounts.json` | ✅ |
| **OAuth MCP-серверов** (Figma и др.) | `.gemini\mcp-oauth-tokens.json` | ✅ |
| **MCP-серверы Antigravity IDE** (ui-expert, penpot, design-systems, playwright, stitch, figma…) | `.gemini\config\mcp_config.json` | ✅ |
| **Настройки Antigravity CLI** (settings, keybindings, mcp, plugins, knowledge) | `.gemini\antigravity-cli\` | ✅ (без bin/brain/чатов/кешей) |
| **Skills** | `.gemini\skills\`, `.gemini\config\skills\`, `<проект>\.agents\skills\` | ✅ |
| **Claude** (settings, credentials, plugins) | `%USERPROFILE%\.claude\` | ✅ |
| **Прочие агенты** (cagent, free-claude-code, data-agent-kit) | `%USERPROFILE%\.config\` | ✅ |
| **Переменные окружения с ключами** (`GEMINI_API_KEY` и т.п.) | реестр пользователя | ✅ `env-user.json` |
| node_modules, браузерные профили Chrome/Edge, кеши, логи, IDE-расширения | — | ❌ (пересоздаются) |

> ⚠️ В архиве лежат токены и API-ключи. Храните его как пароль: в защищённой папке,
> лучше зашифрованным (`-Encrypt`), не в публичном git-репозитории и не в облаке без шифрования.

## Создать бэкап

Из папки со скриптами:

```powershell
# простой архив в %USERPROFILE%\dsh-backups
powershell -ExecutionPolicy Bypass -File .\backup-dsh-settings.ps1

# зашифрованный архив в свою папку
powershell -ExecutionPolicy Bypass -File .\backup-dsh-settings.ps1 -OutDir D:\backups -Encrypt

# без истории чатов (архив заметно меньше)
powershell -ExecutionPolicy Bypass -File .\backup-dsh-settings.ps1 -NoSessions

# посмотреть, что попадёт в архив, ничего не создавая
powershell -ExecutionPolicy Bypass -File .\backup-dsh-settings.ps1 -DryRun
```

Результат: `dsh-settings-ГГГГ-ММ-ДД_ЧЧ-ММ-СС.zip` (или `.zip.enc`).

Внутри архива: `dsh\`, `gemini\`, `claude\`, `config\`, `agents\`, `env-user.json`,
`manifest.json` (версии dsh/node/pnpm и исходные пути) и `RESTORE.md`.

## Восстановить

1. Закройте DSH (`dsh web`) и Antigravity IDE.
2. Запустите восстановление:

```powershell
powershell -ExecutionPolicy Bypass -File .\restore-dsh-settings.ps1 `
  -Archive "C:\Users\ВЫ\dsh-backups\dsh-settings-2026-10-28_14-00-00.zip.enc"

# проверить без изменений (пароль всё равно спросит)
.\restore-dsh-settings.ps1 -Archive .\dsh-settings-....zip -DryRun
```

3. Переустановите плагины профиля (в архиве нет `node_modules`):

```powershell
dsh plugin --profile web install --frozen-lockfile
```

4. Запустите `dsh web`.

Существующие файлы по умолчанию не затираются, а переименовываются в `*.bak-<дата>`.
Дополнительно: `-RestoreEnv` — вернуть переменные окружения из `env-user.json`.

## Перенос на другой компьютер / после переустановки DSH

1. Установите DSH и Node.js 22+, `pnpm`.
2. Восстановите архив (шаги выше). Плагины поставятся из `package.json` + `pnpm-lock.yaml`
   профиля командой `dsh plugin --profile web install --frozen-lockfile`.
3. Если версия DSH в старом архиве отличалась — проверьте `manifest.json` → `versions.dsh`,
   при необходимости поставьте ту же версию (`npx @deepseek-ai/dsh@<версия>`).
4. Google OAuth переносится refresh-токеном; если Google отзовёт сессию (смена пароля,
   политика аккаунта) — достаточно один раз войти в Antigravity.

## Что важно знать

- **DeepSeek OAuth** (`deepseek-account`) восстанавливается целиком из `.credentials.yaml`.
- **DeepSeek web-login** — восстанавливается аккаунт (`web-login\accounts\`), но не
  браузерный профиль. Если вход не подхватился — один раз войдите заново в интерфейсе DSH.
- **Google Pro / Antigravity** — `oauth_creds.json` содержит `refresh_token`, поэтому вход
  в Google переживает восстановление, пока аккаунт активен.
- **MCP-серверы** (`ui-ux-pro`, `ux-audit`, `design-systems`, `playwright`…) прописаны в
  `.mcp.json` проекта и в `~/.gemini/config/mcp_config.json`; оба файла в архиве.
- **Секреты в архиве.** Если архив попадёт не туда — считайте, что токены утекли: отзовите
  ключи в OpenRouter/DashScope, перевыпустите Google/DeepSeek-вход.
- Регулярность: раз в неделю или перед обновлением DSH/плагинов достаточно запускать
  `backup-dsh-settings.ps1`. Старые архивы можно удалять вручную.

## Требования

Windows PowerShell 5.1 или PowerShell 7+, `robocopy` (входит в Windows).
Шифрование — AES-256-CBC + PBKDF2-SHA256 (120 000 итераций) + HMAC-SHA256, без внешних утилит.
