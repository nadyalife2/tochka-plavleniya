"""Живая проверка валидации REST-API дашборда (контрактные коды ответов).

Требует запущенного `python seo/scripts/server.py` на 127.0.0.1:8765.
Проверяет именно то, что раньше ломалось молча:

    {"id": "abc"}     -> 400 (было: 200 ok)
    {"id": 999999}    -> 404 (было: 200 ok, 0 строк)
    {"agent": "delivery"} без target_id -> 400
    {"agent": "delivery", "target_id": "abc"} -> 400
    валидный запрос   -> 200

Запуск:
    python tools/test_api_validation_live.py
"""
from __future__ import annotations

import json
import sys
import urllib.error
import urllib.request

BASE = "http://127.0.0.1:8765"
failures: list[str] = []


def post(path: str, payload: dict) -> tuple[int, dict]:
    body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
    req = urllib.request.Request(
        BASE + path, data=body, headers={"Content-Type": "application/json"}, method="POST")
    try:
        with urllib.request.urlopen(req, timeout=15) as resp:
            return resp.status, json.loads(resp.read().decode("utf-8"))
    except urllib.error.HTTPError as exc:
        raw = exc.read().decode("utf-8", "replace")
        try:
            return exc.code, json.loads(raw)
        except Exception:
            return exc.code, {"raw": raw[:200]}
    except Exception as exc:  # noqa: BLE001
        return 0, {"error": str(exc)[:150]}


def check(label: str, got: int, expected: int, detail: str = "") -> None:
    ok = got == expected
    mark = "✓" if ok else "❌"
    print(f"   {mark} {label}: HTTP {got} (ожидалось {expected}){(' — ' + detail) if detail else ''}")
    if not ok:
        failures.append(f"{label}: HTTP {got} != {expected}")


def run() -> int:
    print("1. Нечисловой id в /api/articles/approve...")
    code, data = post("/api/articles/approve", {"id": "abc"})
    check("id='abc'", code, 400, str(data.get("message", ""))[:90])

    print("\n2. Пустой id...")
    code, data = post("/api/articles/approve", {})
    check("id отсутствует", code, 400)

    print("\n3. Несуществующий id...")
    code, data = post("/api/articles/approve", {"id": 999999})
    check("id=999999", code, 404, str(data.get("message", ""))[:90])

    print("\n4. /api/articles/critical с мусором...")
    code, _ = post("/api/articles/critical", {"id": "drop table", "critical": True})
    check("id='drop table'", code, 400)

    print("\n5. /api/articles/reject с мусором...")
    code, _ = post("/api/articles/reject", {"id": None, "reason": "тест"})
    check("id=null", code, 400)

    print("\n6. /api/topics/reject с мусором...")
    code, _ = post("/api/topics/reject", {"id": []})
    check("id=[]", code, 400)

    print("\n7. /api/competitors/approve без домена...")
    code, _ = post("/api/competitors/approve", {"domain": ""})
    check("domain=''", code, 400)

    print("\n8. /api/agent/run без target_id для агента, которому он нужен...")
    code, data = post("/api/agent/run", {"agent": "delivery"})
    check("delivery без target_id", code, 400, str(data.get("message", ""))[:90])

    print("\n9. /api/agent/run с мусорным target_id...")
    code, _ = post("/api/agent/run", {"agent": "04-scorer", "target_id": "abc"})
    check("target_id='abc'", code, 400)

    print("\n10. /api/agent/run без агента...")
    code, _ = post("/api/agent/run", {})
    check("agent отсутствует", code, 400)

    print("\n11. /api/topics/update-text для несуществующей темы...")
    code, data = post("/api/topics/update-text", {"id": 999999, "content_markdown": "текст"})
    check("id=999999", code, 404, str(data.get("message", ""))[:90])

    print("\n12. /api/topics/update-text с нестроковым текстом...")
    code, _ = post("/api/topics/update-text", {"id": 1, "content_markdown": 12345})
    check("content_markdown=12345", code, 400)

    print("\n13. Валидный запрос проходит (несуществующий id голоса -> 404, а не 400)...")
    code, _ = post("/api/voices/delete", {"id": "no-such-voice-xyz"})
    check("voices/delete несуществующий", code, 404)

    print("\n14. Пустой id голоса -> 400...")
    code, _ = post("/api/voices/delete", {"id": ""})
    check("voices/delete пустой", code, 400)

    print(f"\nПровалов: {len(failures)}")
    for f in failures:
        print("  ❌", f)
    return 0 if not failures else 1


if __name__ == "__main__":
    sys.exit(run())