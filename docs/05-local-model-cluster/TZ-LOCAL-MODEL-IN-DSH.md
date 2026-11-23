# ТЗ: локальная модель кластера как child-LLM в DSH

**Дата диагноза:** 2026-11-22, 00:35–01:10 (Europe/Samara)
**Статус:** диагноз подтверждён логами, правки НЕ внесены — применяет владелец
**Область:** профиль DSH `web` + `llama-server` на Mi 10T (192.168.31.161:8080)
**Не входит:** правки сайта, темы, PHP, контента

---

## 0. Цель и текущий итог

**Цель:** маршрут `triad-qwen/qwen2.5-9b-instruct` должен работать как child-LLM DSH (сабагенты и селектор моделей).

**Что уже работает:**

- гейт маршрута снят новой сессией: `list_subagent_models` отдаёт `triad-qwen — Triad-Omni · Qwen2.5 9B (локальная)`;
- прямые HTTP-запросы к движку проходят: `/v1/models`, `/props`, `/v1/chat/completions` → 200;
- function calling поддерживается: запрос с `tools` вернул `finish_reason: tool_calls` и корректные `{"city":"Samara"}`;
- шаблон чата слушает `enable_thinking` (проверено напрямую).

**Что не работает:** сабагент DSH с этой моделью падает **за 831 мс**.

```
400: request (20905 tokens) exceeds the available context size (16384 tokens)
     type: exceed_context_size_error, n_prompt_tokens: 20905, n_ctx: 16384
turn/end → reason.kind = error, code = CONTEXT_WINDOW_EXCEEDED
```

Источник: `C:\Users\nadya\.dsh\sessions\--D-~0441...--\eb10d22a-c3aa-43e8-bd67-6b01b35e96ca\session.v4.jsonl.zstd`
Распаковщик: `_tmp/dsh-zstd-read.cjs` (написан в этой сессии, читает склеенные zstd-кадры).

---

## 1. Измеренные факты (база для всех решений)

| Что | Значение | Откуда |
|---|---|---|
| `n_ctx` движка | **16 384** | `/props` → `"n_ctx":16384`; строка запуска `-c 16384` в `01_Сеть...md` |
| Метрика модели | `n_ctx_train` 262144, 9.2 B параметров, Q3_K_M, файл `brain.gguf` | `/v1/models` → `meta` |
| Промпт ребёнка DSH | **20 905 токенов** | `session.v4.jsonl.zstd` → `assistant/attempt` |
| Из них system | 1 786 | `contextBreakdown` родительской сессии |
| Из них tools | **13 590** | там же |
| Остаток (~5 500) | каталог скилов + system-reminders | `user/message` seq 13 в логе ребёнка |
| Префилл | **269 мс/токен ≈ 3.7 ток/с**, линейно | ответ сервера, поле `timings` |
| Декод | 2.4–2.7 ток/с | `timings.predicted_per_second` |
| Кэш префикса | работает (`cache-reuse 256`), `cached_tokens` > 0 | `usage.prompt_tokens_details` |
| Thinking выключен | 2.2 с, 2 токена, `reasoning_content` отсутствует | запрос с `chat_template_kwargs` |
| Thinking включён | 47 с, 103 токена, 360 символов трейса | тот же промпт без kwargs |
| LoRA на сервере | **один** `adapter.gguf`, scale 1.0 | `/lora-adapters` |
| Спекулятивный декод | выключен: `"speculative.types":"none"` | `/props` |
| Зрение | выключено: `"vision":false` | `/props` |
| Слот | `total_slots: 1` — второй запрос встаёт в очередь | `/props` |

Скоростная арифметика, на которой всё держится:
`20 905 токенов × 0.269 с ≈ 5 620 с ≈ 94 минуты` префилла для одного запроса стандартного пресета.

---

## 2. Ошибки: что именно сломано

### E1 (P0). Промпт ребёнка не влезает в контекст движка

`20 905 > 16 384`. Это и убило сабагента. Движок отказал сразу, без генерации.

**Причина структуры промпта:** 13 590 токенов — описания инструментов стандартного пресета, ещё ~5 500 — каталог скилов и напоминания. Полезная нагрузка там — сотни токенов.

**Действие:** либо резать промпт (FIX-3), либо поднимать контекст (FIX-4). Одного фикса конфига для E1 **недостаточно**.

### E2 (P0). `contextWindow: 4096` в конфиге устарел

Строки 219 и 231 в `C:\Users\nadya\.dsh\profiles\web\cordis.patch.yml`. Реально движок даёт 16 384.

**Почему это не безобидная неточность:** DSH считает бюджет по своему объявленному значению. Увидев переполнение, он запустил автокомпакцию — а запрос компакции собрался на **21 213 токенов** и упал с тем же 400. То есть устаревшее число привело к лишней операции, которая не могла сработать.

### E3 (P0). `chatTemplateKwargs.enable_thinking: false` не доезжает до сервера

В конфиге (строки 221–224) поле есть, но оно **инертно**. В pi-ai ветка отправки kwargs имеет два независимых условия
(`@earendil-works/pi-ai/dist/api/openai-completions.js:630–641`):

```js
else if (compat.thinkingFormat === "qwen-chat-template" && model.reasoning) {
    params.chat_template_kwargs = { enable_thinking: !!options?.reasoningEffort, preserve_thinking: true };
}
else if (compat.thinkingFormat === "chat-template" && model.reasoning) { ... }
```

1. `thinkingFormat` не задан → автоопределение по приватному URL даёт `openai` → ветка не та.
2. `reasoningEfforts: false` (строка 234) объявляет модель нерассуждающей → `model.reasoning === false` → гейт не проходит даже с верным `thinkingFormat`.

Это задокументировано в самом DSH: «Nothing checks that pairing: the format in force may come from the installed catalog entry or from pi-ai's own baseURL detection, neither of which resolution can read, so kwargs set beside another format are sent nowhere»
(`docs/config-catalog.md:2264–2270`), а семантика `reasoningEfforts` — там же, `config-catalog.md:2194–2204`. Реализация: `dsh-llm-pi-ai/src/catalog.ts:725–773` (`reasoningEfforts: false → { reasoning: false }`; объявленный словарь → `reasoning: true` + `thinkingLevelMap`).

**Цена ошибки:** по умолчанию модель думает и тратит бюджет на трейс (47 с / 103 токена против 2.2 с / 2 токена). На больших промптах это еще и съедает `max_tokens` до пустого `content`.

### E4 (P0). `timeoutMs: 120000` недостижим физически

При 269 мс/токен бюджет 120 секунд — это **~446 токенов промпта**. Любой агентный промпт больше. Даже если E1 закрыть, запрос стандартного пресета умрёт по таймауту (~94 минуты префилла).

### E5 (P2). Дрейф конфигурации на телефоне

`start_brain.sh` (по `01_Сеть...md`, строки 173–177) запускает `llama-server` с тремя LoRA-адаптерами и `--spec-type draft-mtp`.
Фактически поднято: один `adapter.gguf`, спецдекод выключен. Значит запущенный экземпляр собран не этим скриптом, и документация кластера расходится с реальностью. Это не причина падения DSH, но это надо знать, прежде чем тюнить скорость (иначе тюнинг уйдёт не туда).

### E6 (P3). Термогвард умеет замораживать сервер

`05_Питание_и_Физика_24x7.md:155–156`: при ≥55 °C — `pkill -STOP -f llama-server`, возобновление при ≤45 °C.
Замерзание процесса выглядит как зависший запрос без ответа, а не как ошибка. В этом расследовании не наблюдалось (`/props` отвечал за 0.11 с во время долгого запроса), но при отладке скорости это первый подозреваемый, если запрос «висит».

---

## 3. Правки

### FIX-1. Блок `triad-qwen` в `cordis.patch.yml` (строки 212–234)

Заменить целиком:

```yaml
      triad-qwen:
        displayName: Triad-Omni · Qwen2.5 9B (локальная)
        api: openai-completions
        baseURL: "http://192.168.31.161:8080/v1"
        headers:
          Authorization: "Bearer sk-triad-omni-cluster"
        timeoutMs: 900000            # было 120000 — иначе физика не пускает
        defaultContextWindow: 16384  # было 4096 — приводим к факту движка
        defaultMaxTokens: 1024       # было 2048 — оставляем запас на ответ
        compat:
          maxTokensField: max_tokens
          thinkingFormat: qwen-chat-template   # вместо инертного chatTemplateKwargs
        retryPolicy:
          mode: normal
          maxRetries: 0
        models:
          - id: qwen2.5-9b-instruct
            name: Qwen2.5 9B Instruct · локальная · LAN
            contextWindow: 16384
            maxTokens: 1024
            input: [ text ]
            reasoningEfforts:      # ← обязателен, иначе thinkingFormat не сработает
              off: null
              low: low
```

Почему так:

- `qwen-chat-template` — формат, специально сделанный под llama.cpp/Qwen: он сам выставляет `chat_template_kwargs.enable_thinking` по выбранному уровню (нет уровня → `false`);
- `reasoningEfforts` словарём (а не `false`) переводит модель в `reasoning: true`, без чего ни одна chat-template ветка не исполняется;
- `maxTokens: 1024` вместо 2048 — при 16k контекста запас на ответ важнее, чем 2k.

### FIX-2. Проверка синтаксиса и применения

```powershell
cd "D:\сайт для пайки"          # любой cwd
dsh --dump-config | Select-String -Pattern 'triad-qwen' -Context 0,30
```

Затем **перезапустить DSH** (конфиг читается на старте) и **открыть новую сессию** — пресет и гейт маршрута тоже читаются на старте сессии.

### FIX-3. Обрезать промпт (обязательно, если не делать FIX-4)

В DSH есть готовый легкий пресет `minimal` (`@deepseek-ai/dsh-web-app/presets/minimal.patch.yml`): персона в одну строку, `includeRuntimeContext: false`, каталога скилов нет, инструментов почти нет. Промпт падает с ~21 000 ориентировочно до 1 000–1 500 токенов (оценка по составу, замером не подтверждена).

Два способа:

**Вариант A (минимальное вмешательство):** в новой сессии выбрать в селекторе пресет **minimal** и проверить, хватает ли его инструментов. Ничего не править.

**Вариант B (если нужны файловые операции):** переопределить `preset-minimal` в `cordis.patch.yml` полным списком `config.plugins` по образцу `minimal.patch.yml`, включив чтение/запись файлов:

```yaml
- id: preset-minimal
  name: "@deepseek-ai/dsh-agent-preset"
  config:
    id: minimal
    order: 3
    plugins:
      - id: persona
        name: '@deepseek-ai/dsh-persona'
        config:
          prefix: You are a helpful software engineer assistant.
          complete: true
          includeRuntimeContext: false
      - id: tool-fs
        name: '@deepseek-ai/dsh-tool-fs'
        config:
          read: true
          readImage: true
          write: true
          edit: true
      - id: tool-fs-search
        name: '@deepseek-ai/dsh-tool-fs-search'
      - id: compaction
        name: cordis:group
        group: true
        isolate:
          compaction: true
        config:
          - id: compaction-basic
            name: '@deepseek-ai/dsh-compaction-basic'
          - id: command-compact
            name: '@deepseek-ai/dsh-command-compact'
      - id: persistent-shell
        name: cordis:group
        group: true
        isolate:
          terminals: true
        config:
          - id: pty
            name: '@deepseek-ai/dsh-terminal'
          - id: terminal-bash
            name: '@deepseek-ai/dsh-terminal-bash'
            disabled: !!js process.platform === 'win32'
          - id: persistent-bash
            name: '@deepseek-ai/dsh-tool-bash-persistent'
            disabled: !!js process.platform === 'win32'
          - id: terminal-pwsh
            name: '@deepseek-ai/dsh-terminal-bash'
            disabled: !!js process.platform !== 'win32'
            config:
              shellDialect: pwsh
          - id: persistent-pwsh
            name: '@deepseek-ai/dsh-tool-pwsh-persistent'
            disabled: !!js process.platform !== 'win32'
```

Если строка не активируется, реестр пресетов покажет причину в списке (`broken`) — это безопасный отказ, чужие пресеты не ломаются. Набор инструментов проверяется в селекторе и по факту работы.

### FIX-4. Альтернатива FIX-3: поднять контекст движка до 32768

Тогда влезет даже стандартный пресет (20 905 + ответ). Правка на телефоне: `ctx-size = 16384 → 32768` в `~/brain.ini` или `-c 32768` в строке запуска.

Ограничения, которые надо проверить до правки:

- RAM: весов 5.2 ГБ; KV при `-ctk q8_0 -ctv q8_0` даёт ориентировочно ~0.6 ГБ на 16k, то есть ~1.2 ГБ на 32k (оценка; точное число — из `llama_perf_context_print` / memory breakdown после запуска);
- префилл 20 905 токенов всё равно займёт ~94 минуты — FIX-1 обязателен, иначе таймаут;

### FIX-5 (опционально, вне DSH). Скорость движка

Проверенные первоисточники (llama.cpp docs, master):

- **KleidiAI:** `-DGGML_CPU_KLEIDIAI=ON` для Android `arm64-v8a`, признак включения в логе — `load_tensors: CPU_KLEIDIAI model buffer size = ...`. Диспетчер использует dotprod / i8mm / SVE / SME. У Cortex-A77 (Snapdragon 865) есть dotprod, но нет i8mm — выигрыш ожидать умеренный, замерять. Источник: `docs/build.md`, раздел «Arm® KleidiAI».
- **BLAS:** сборка с `-DGGML_BLAS=ON -DGGML_BLAS_VENDOR=OpenBLAS` даёт ускорение **именно prompt processing** при batch > 32 — то есть бьёт ровно в нашу стену префилла. Источник: `docs/build.md`, «BLAS Build».
- **OpenCL на Adreno 650 — тупик.** В `docs/backend/OPENCL.md` поддержаны Adreno 750/810/830/840 и X-серии, и прямо сказано: «A6x GPUs in phones are likely not supported due to the outdated driver and compiler». Adreno 650 — это A6xx. Не тратить время.
- **Hexagon NPU (экспериментальный):** `docs/backend/snapdragon/README.md`, сборка даёт `libggml-htp-v73/v75/v79/v81.so`. Архитектуру Hexagon конкретного Mi 10T надо посмотреть на устройстве (строка `ggml-hex: Hexagon Arch version vNN` в логе). Если она ниже v73 — путь закрыт; если совпадёт — это единственный кандидат на радикальное ускорение.

### FIX-6 (P2). Привести документацию кластера в соответствие

Либо перезапустить сервер по `start_brain.sh` (три LoRA + `draft-mtp`), либо поправить доки под фактический запуск: один `adapter.gguf`, без спецдекода. Сейчас `01` и `05` описывают не то, что работает.

---

## 4. Критерии приемки

1. `dsh --dump-config` показывает для `triad-qwen`: `timeoutMs: 900000`, `contextWindow: 16384`, `thinkingFormat: qwen-chat-template`, `reasoningEfforts` словарём.
2. В новой сессии селектор моделей содержит «Qwen2.5 9B Instruct · локальная · LAN».
3. Сабагент на этой модели **не** завершает ход с `CONTEXT_WINDOW_EXCEEDED`, а в его логе есть успешный `assistant/attempt` и `turn/end` без `reason.kind = error`.
4. Один запрос с пресетом `minimal` укладывается в таймаут и возвращает непустой `content` (не только `reasoning_content`).

Проверка лога ребёнка:

```powershell
node "D:\сайт для пайки\_tmp\dsh-zstd-read.cjs" "<путь>\session.v4.jsonl.zstd" "error|CONTEXT|turn/end"
```

---

## 5. Чего не делать (проверенные тупики)

- Не пытаться включить OpenCL на Adreno 650 (см. FIX-5).
- Не отправлять 20k-промпт в `timeoutMs: 120000` — не пройдёт никогда, независимо от контекста.
- Не полагаться на `chatTemplateKwargs` без `thinkingFormat` и без `reasoningEfforts` — молча ничего не отправится.
- Не верить самоотчёту модели о себе: она называет себя Qwen3.5 (в файле весов действительно Qwen3.5-UD-Q3_K_XL, а `qwen2.5-9b-instruct` — алиас роутера), но заявляет поддержку изображений при `vision: false`.
- Не считать `reasoning_content` ответом модели: при исчерпанном `max_tokens` `content` пустой, а весь бюджет уходит в трейс.

---

## 6. Что осталось непроверенным

- Токенный состав пресета `minimal` — оценка, не замер.
- Сквозной round-trip «DSH → llama-server → tool_call → результат» не проверен: ребёнок до него не дожил (упал на 400).
- Поведение модели с урезанным набором инструментов (качество вызовов) не проверено.
- Архитектура Hexagon на Mi 10T не проверена (доступ к устройству из этой машины есть только по HTTP-порту 8080).
