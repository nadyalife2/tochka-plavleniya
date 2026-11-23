---
name: awesome-rea
description: "Search the curated Awesome-REA catalog of reverse-engineering resources: 5,006 reviewed tools and materials, 442 research papers and 451 case studies across Android apps, native binaries, firmware, communication protocols, games and websites. Use when researching RE tooling (JADX, Ghidra, Frida, Rizin, IDA), looking for decompilation, unpacking or protocol-reconstruction approaches, checking prior art before starting a task, or finding academic literature on binary analysis."
---

# Awesome REA

Каталог ресурсов по реверс-инжинирингу: инструменты, разобранные кейсы и научные статьи.
Это **не** upstream-проект REA CLI/MCP (`github.com/morluto/rea`) — это независимая подборка.

- Источник: <https://github.com/reacher-z/Awesome-REA> (MIT, `CITATION.cff` → v0.12.0)
- Локальная копия: `.agents/skills/awesome-rea/` относительно корня проекта,
  то есть `D:\сайт для пайки\.agents\skills\awesome-rea`

## Что внутри (проверено по файлам данных)

| Файл | Записей | Что это |
| :--- | ---: | :--- |
| `data/tool-resources.json` | 5 006 | инструменты и материалы |
| `data/papers.json` | 442 | рецензируемая библиография |
| `data/case-coverage.json` | 451 | разобранные кейсы |
| `data/rea-resources.json` | 185 | связи с REA |
| `data/source-index.json` | 3 000 | автоматический индекс отсканированных README |

## Как искать

Поиск идёт по имени, описанию, категории и требованиям — только стандартная библиотека Python.

```powershell
cd "D:\сайт для пайки\.agents\skills\awesome-rea"
python scripts/catalog.py "ghidra"
```

Фильтры (проверены через `--help`):

```powershell
python scripts/catalog.py "bluetooth" --target protocol   # website|game|app|binary|firmware|protocol
python scripts/catalog.py "mediatek"  --target firmware
python scripts/catalog.py "decompil"  --papers            # искать по библиографии
python scripts/catalog.py "jadx"      --source-index      # искать по индексу сканов
python scripts/catalog.py "frida"     --json              # полные записи с provenance
python scripts/catalog.py "android" --category <точное>   # точная категория
python scripts/catalog.py --list-categories               # какие категории есть
```

Для китайских описаний добавьте `--language zh`.

## Что здесь есть

Читать каталог стоит до того, как браться за задачу: он показывает, чем её уже решали и
на что ссылаться. Самые полезные срезы — инструменты под Android (JADX, Frida, Blutter,
objection), нативные бинарники (Ghidra, Rizin, angr), прошивки (LK-загрузчики, парсеры
дампов), протоколы (BLE/Wi-Fi IoT, перехват трафика) и веб (деобфускация JS, разбор API).

## Обновление

Копия — обычный git-клон, поэтому обновляется на месте:

```powershell
cd "D:\сайт для пайки\.agents\skills\awesome-rea"
git pull
```

Каталог читается напрямую из JSON, пересборка не нужна. Скрипты `scripts/build_readme.py`,
`scripts/build_repository_index.py` и `scripts/validate.py` нужны только при правках самих
данных — см. `CONTRIBUTING.md` и `AGENTS.md` в корне клона.

## Границы

- `source-reviewed` означает разбор документации, а **не** проверку в работе: статус ничего
  не говорит о безопасности, полноте восстановления или совместимости.
- Записи из автоматического скана (`--source-index`) — отдельное доказательство, его нельзя
  повышать в статусе вручную.
- Каталог ничего не запускает и не устанавливает; он только описывает и ссылается.
