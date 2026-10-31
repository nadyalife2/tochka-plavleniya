"""Русификация интерфейса плагина dsh-deepseek-web-login (только ключевые экраны).

Зачем: подписи плагина зашиты в `lib/client.js` на китайском. Скрипт заменяет
только полные строковые литералы из словаря `dict_ru.json`, не трогая логику.

Почему отдельным скриптом, а не правкой на месте:
    `node_modules` перезаписывается при `npm update` / `dsh plugin add`.
    После обновления плагина достаточно снова запустить этот скрипт.

Защита от поломок:
    * заменяются только литералы, встречающиеся в коде как `"текст"`;
    * литералы с подстановкой `${...}` пропускаются всегда;
    * литералы, которые используются в сравнениях/селекторах
      (`=== "x"`, `includes("x")`, `case "x"` и т. п.), пропускаются;
    * перед изменением создаётся резервная копия `client.js.zh-backup`;
    * `--revert` возвращает файл из резервной копии;
    * `--check` только показывает, что будет заменено.

Использование:
    python tools/dsh-ru-l10n/patch_plugin_ru.py --check
    python tools/dsh-ru-l10n/patch_plugin_ru.py
    python tools/dsh-ru-l10n/patch_plugin_ru.py --revert
"""
from __future__ import annotations

import argparse
import json
import os
import re
import shutil
import sys
from pathlib import Path

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

HERE = Path(__file__).resolve().parent
DICT_FILE = HERE / "dict_ru.json"

PLUGIN_REL = Path("profiles") / "web" / "node_modules" / "dsh-deepseek-web-login" / "lib" / "client.js"

# Контексты, в которых литерал является частью логики, а не подписью
LOGIC_CONTEXT = (
    r"(?:===|!==|==|!=)\s*",
    r"case\s+",
    r"(?:includes|indexOf|startsWith|endsWith|querySelector|matches)\s*\(\s*",
    r"switch\s*\(\s*",
)


def plugin_path() -> Path:
    home = os.environ.get("DSH_HOME") or str(Path.home() / ".dsh")
    return Path(home) / PLUGIN_REL


def load_dict() -> dict[str, str]:
    data = json.loads(DICT_FILE.read_text(encoding="utf-8"))
    return {k: v for k, v in data.items() if k and v}


def is_logic_literal(src: str, literal: str) -> bool:
    quoted = re.escape('"' + literal + '"')
    for ctx in LOGIC_CONTEXT:
        if re.search(ctx + quoted, src):
            return True
    return False


def patch(dry_run: bool) -> int:
    path = plugin_path()
    if not path.exists():
        print(f"❌ Не найден файл плагина: {path}")
        print("   Проверьте, что плагин установлен в профиль web.")
        return 2

    backup = path.with_suffix(".js.zh-backup")
    src = path.read_text(encoding="utf-8")
    original = src
    mapping = load_dict()

    replaced_total = 0
    skipped_logic: list[str] = []
    missing: list[str] = []
    rows: list[tuple[str, str, int]] = []

    for zh, ru in mapping.items():
        if "${" in zh or "${" in ru:
            continue
        quoted = '"' + zh + '"'
        count = src.count(quoted)
        if count == 0:
            missing.append(zh)
            continue
        if is_logic_literal(src, zh):
            skipped_logic.append(zh)
            continue
        src = src.replace(quoted, '"' + ru + '"')
        replaced_total += count
        rows.append((zh, ru, count))

    print(f"Файл: {path}")
    print(f"Словарь: {len(mapping)} записей | заменено вхождений: {replaced_total}")
    print(f"Строк изменено в этом прогоне: {len(rows)}")

    if skipped_logic:
        print(f"\n⚠️ Пропущено (литерал участвует в логике): {len(skipped_logic)}")
        for zh in skipped_logic[:10]:
            print(f"   • {zh}")

    if not rows and not dry_run:
        print("\nНечего менять: либо строки уже переведены, либо словарь не совпал с версией плагина.")
        return 0

    if dry_run:
        print("\n--- предпросмотр (--check) ---")
        for zh, ru, count in rows[:40]:
            print(f"   [{count}] {zh}\n        → {ru}")
        if len(rows) > 40:
            print(f"   … ещё {len(rows) - 40}")
        print("\nИзменения НЕ записаны (--check).")
        return 0

    if not backup.exists():
        shutil.copy2(path, backup)
        print(f"\nБэкап оригинала: {backup.name}")
    else:
        print(f"\nБэкап уже существует: {backup.name} (не перезаписываю)")

    path.write_text(src, encoding="utf-8")
    changed = sum(1 for a, b in zip(original.splitlines(), src.splitlines()) if a != b)
    print(f"Записано. Изменённых строк: {changed}")
    print("\nПерезапустите DSH, чтобы плагин прочитал новые подписи.")
    return 0


def revert() -> int:
    path = plugin_path()
    backup = path.with_suffix(".js.zh-backup")
    if not backup.exists():
        print(f"❌ Резервная копия не найдена: {backup}")
        return 2
    shutil.copy2(backup, path)
    print(f"↩️ Восстановлен оригинал из {backup.name}")
    print("Перезапустите DSH.")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description="Русификация UI плагина dsh-deepseek-web-login")
    parser.add_argument("--check", action="store_true", help="только показать, что будет заменено")
    parser.add_argument("--revert", action="store_true", help="вернуть оригинал из бэкапа")
    args = parser.parse_args()
    if args.revert:
        return revert()
    return patch(dry_run=args.check)


if __name__ == "__main__":
    raise SystemExit(main())
