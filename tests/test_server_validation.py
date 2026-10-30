"""Тесты валидации входных данных REST-API дашборда.

Закрывают класс дефектов «тихое ok»: раньше `{"id": "abc"}` возвращал
`{"status": "ok"}`, хотя UPDATE не менял ни одной строки (SQLite спокойно
сравнивает INTEGER с 'abc' и просто не находит совпадений).

Проверяется:
1. `parse_row_id` — приведение id из JSON к положительному int.
2. `require_id` / `require_text` / `require_updated` — коды ответов 400 и 404.
3. Статический страж: в `do_POST` не осталось сырых `payload.get("id")`.

Запуск:
    python tests/test_server_validation.py
    python tests/test_server_validation.py -v
"""
from __future__ import annotations

import re
import sys
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SCRIPTS = ROOT / "seo" / "scripts"
sys.path.insert(0, str(SCRIPTS))

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

import server  # noqa: E402  (после настройки sys.path)


class FakeHandler(server.DashboardHandler):
    """Хендлер без сокета: перехватывает ответы вместо отправки в сеть."""

    def __init__(self):  # noqa: D107 — намеренно не зовём BaseHTTPRequestHandler.__init__
        self.responses: list[tuple[dict, int]] = []

    def send_json(self, data: dict, status: int = 200):  # noqa: D102
        self.responses.append((data, status))

    @property
    def last_status(self) -> int | None:
        return self.responses[-1][1] if self.responses else None

    @property
    def last_message(self) -> str:
        return self.responses[-1][0].get("message", "") if self.responses else ""


class TestParseRowId(unittest.TestCase):
    """Приведение id из JSON к числу."""

    def test_accepts_positive_integers(self):
        for value in (1, 20, 999, "20", " 7 ", 20.0):
            with self.subTest(value=value):
                self.assertGreater(server.parse_row_id(value), 0)

    def test_rejects_non_numeric(self):
        for value in ("abc", "", "  ", "12abc", "1e3", None, [], {}, "0x10"):
            with self.subTest(value=value):
                self.assertIsNone(server.parse_row_id(value))

    def test_rejects_zero_and_negative(self):
        for value in (0, -1, "0", "-5"):
            with self.subTest(value=value):
                self.assertIsNone(server.parse_row_id(value))

    def test_rejects_booleans(self):
        """True — это int(1) в Python, но id=True почти наверняка ошибка клиента."""
        self.assertIsNone(server.parse_row_id(True))
        self.assertIsNone(server.parse_row_id(False))

    def test_rejects_fractional(self):
        self.assertIsNone(server.parse_row_id(1.5))


class TestRequireId(unittest.TestCase):
    """`require_id` возвращает id или отвечает 400."""

    def test_valid_id_passes_silently(self):
        h = FakeHandler()
        self.assertEqual(h.require_id({"id": 20}), 20)
        self.assertEqual(h.responses, [])

    def test_string_id_is_coerced(self):
        h = FakeHandler()
        self.assertEqual(h.require_id({"id": "52"}), 52)
        self.assertEqual(h.responses, [])

    def test_garbage_id_gets_400(self):
        h = FakeHandler()
        self.assertIsNone(h.require_id({"id": "abc"}))
        self.assertEqual(h.last_status, 400)
        self.assertIn("целым числом", h.last_message)

    def test_missing_id_gets_400(self):
        h = FakeHandler()
        self.assertIsNone(h.require_id({}))
        self.assertEqual(h.last_status, 400)

    def test_custom_field_name(self):
        h = FakeHandler()
        self.assertEqual(h.require_id({"target_id": "7"}, field="target_id"), 7)
        self.assertEqual(h.responses, [])

        h2 = FakeHandler()
        self.assertIsNone(h2.require_id({"target_id": "x"}, field="target_id"))
        self.assertEqual(h2.last_status, 400)
        self.assertIn("target_id", h2.last_message)


class TestRequireText(unittest.TestCase):
    """`require_text` проверяет тип, длину и обрезает пробелы."""

    def test_ok(self):
        h = FakeHandler()
        self.assertEqual(h.require_text({"domain": "  chipdip.ru  "}, "domain", "домен"), "chipdip.ru")
        self.assertEqual(h.responses, [])

    def test_too_short(self):
        h = FakeHandler()
        self.assertIsNone(h.require_text({"domain": "a"}, "domain", "домен", min_len=3))
        self.assertEqual(h.last_status, 400)
        self.assertIn("минимум 3", h.last_message)

    def test_wrong_type(self):
        h = FakeHandler()
        self.assertIsNone(h.require_text({"domain": 42}, "domain", "домен"))
        self.assertEqual(h.last_status, 400)
        self.assertIn("строкой", h.last_message)

    def test_too_long(self):
        h = FakeHandler()
        self.assertIsNone(h.require_text({"x": "a" * 50}, "x", "поле", max_len=10))
        self.assertEqual(h.last_status, 400)


class TestRequireUpdated(unittest.TestCase):
    """`require_updated` превращает «0 строк обновлено» в 404."""

    def test_zero_rows_is_404(self):
        h = FakeHandler()
        self.assertFalse(h.require_updated(0, "Статья #999 не найдена"))
        self.assertEqual(h.last_status, 404)
        self.assertIn("не найдена", h.last_message)

    def test_updated_rows_pass(self):
        h = FakeHandler()
        self.assertTrue(h.require_updated(1, "не важно"))
        self.assertEqual(h.responses, [])


class TestNoRawIdInPostHandlers(unittest.TestCase):
    """Страж: в do_POST не должно остаться сырых `payload.get("id")`."""

    def setUp(self):
        self.source = (SCRIPTS / "server.py").read_text(encoding="utf-8")
        self.post_body = self._extract(self.source, "def do_POST(self):")

    @staticmethod
    def _extract(source: str, marker: str) -> str:
        start = source.index(marker)
        rest = source[start + len(marker):]
        match = re.search(r"\n    def ", rest)
        return rest[: match.start()] if match else rest

    def test_no_raw_payload_id(self):
        offenders = [
            line.strip()
            for line in self.post_body.splitlines()
            if 'payload.get("id")' in line
        ]
        self.assertEqual(offenders, [], f"Сырой payload.get(\"id\") в do_POST: {offenders}")

    def test_require_id_used_in_handlers(self):
        self.assertGreaterEqual(self.post_body.count("self.require_id("), 5)

    def test_require_updated_used_in_handlers(self):
        self.assertGreaterEqual(self.post_body.count("self.require_updated("), 4)

    def test_target_required_agents_defined(self):
        for agent in ("delivery", "04-scorer", "02-writer", "03-reviewer", "factcheck"):
            self.assertIn(agent, server.TARGET_REQUIRED_AGENTS)


def main() -> int:
    loader = unittest.TestLoader()
    suite = loader.loadTestsFromModule(sys.modules[__name__])
    result = unittest.TextTestRunner(verbosity=2).run(suite)
    return 0 if result.wasSuccessful() else 1


if __name__ == "__main__":
    sys.exit(main())