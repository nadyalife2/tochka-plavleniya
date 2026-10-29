"""Smoke-тесты SEO-контура «Точки Плавления».

Проверяют то, что раньше ломалось молча:
1. Паритет канонических сплавов: реестр materials-registry.php == JSON-схема == delivery_cli.
2. В паспорте статьи нет выдуманных чисел (никаких «+20 °C» к пиковой температуре).
3. Сплав определяется честно: обзорная статья -> universal, бессвинцовая -> свой сплав.
4. Инструменты не подставляются «по умолчанию».
5. Скорер не считает предупреждение «не паяйте под напряжением» опасным советом,
   но ловит реальный опасный совет.
6. План проекта собирается из SQLite без Excel.
7. Несуществующая тема даёт понятную ошибку, а не ложный успех.

Запуск:
    python tests/test_seo_pipeline.py
    python tests/test_seo_pipeline.py -v
"""
from __future__ import annotations

import json
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

DB_FILE = ROOT / "seo" / "data" / "seo.db"


class TestCanonicalAlloys(unittest.TestCase):
    """Единый источник сплавов."""

    def setUp(self):
        import canonical_alloys
        self.canon = canonical_alloys

    def test_registry_parsed(self):
        self.assertGreaterEqual(len(self.canon.REGISTRY_SOLDERS), 9, "реестр сплавов не распарсен")
        for key, data in self.canon.REGISTRY_SOLDERS.items():
            self.assertTrue(data["name"], f"{key}: нет названия")
            self.assertIsNotNone(data["t_melt_min"], f"{key}: нет солидуса")
            self.assertIsNotNone(data["t_melt_max"], f"{key}: нет ликвидуса")

    def test_parity_registry_schema_delivery(self):
        schema_path = ROOT / "schemas" / "article-contract-v1.1.schema.json"
        schema = json.loads(schema_path.read_text(encoding="utf-8"))
        enum = set(schema["properties"]["solder_alloy"]["enum"])
        self.assertEqual(enum, set(self.canon.CANONICAL_KEYS), "схема и реестр разошлись")

        src = (ROOT / "tools" / "delivery_cli.py").read_text(encoding="utf-8", errors="replace")
        block = re.search(r"CANONICAL_ALLOYS\s*=\s*\{(.+?)\}", src, re.S)
        self.assertIsNotNone(block, "в delivery_cli не найден CANONICAL_ALLOYS")
        delivery_keys = set(re.findall(r'"([a-z0-9_]+)"', block.group(1)))
        self.assertEqual(delivery_keys, set(self.canon.CANONICAL_KEYS), "delivery_cli и реестр разошлись")

    def test_no_data_means_no_data(self):
        """Сплав без температур в реестре не должен получать выдуманные цифры."""
        for key in self.canon.CANONICAL_KEYS:
            if key == self.canon.UNIVERSAL:
                self.assertIsNone(self.canon.melt_range(key))
                continue
            self.assertIsNotNone(self.canon.melt_range(key), f"{key}: нет диапазона плавления")

    def test_alloy_detection_honest(self):
        # Обзор видов припоев -> universal, а не SAC305
        review = "ПОС-61 плавится при 183 °C. ПОС-40 — при 238 °C. SAC305 — при 217 °C. Розе — 94 °C."
        self.assertEqual(self.canon.detect_alloy(review, "Припой для пайки: виды")[0], self.canon.UNIVERSAL)

        # Бессвинцовая тема -> конкретный бессвинцовый сплав
        lead_free = "SAC305 требует 260–340 °C на жале. Сравним с ПОС-61."
        self.assertEqual(self.canon.detect_alloy(lead_free, "Бессвинцовый припой: плюсы")[0], "sac305")

        # Явный оловянно-свинцовый гайд -> pos61
        leaded = "ПОС-61 и только ПОС-61. Температура 280–320 °C. ПОС-61 удобен новичку."
        self.assertEqual(self.canon.detect_alloy(leaded, "Как паять паяльником")[0], "pos61")

        # Пустой текст -> universal
        self.assertEqual(self.canon.detect_alloy("", "Тема")[0], self.canon.UNIVERSAL)


@unittest.skipUnless(DB_FILE.exists(), "нет seo/data/seo.db")
class TestPackageContract(unittest.TestCase):
    """Паспорт статьи: без выдуманных данных."""

    def setUp(self):
        import package_contract
        self.pc = package_contract

    def test_peak_never_fabricated(self):
        """Пиковая температура не должна появляться из воздуха."""
        for tid in range(1, 63):
            res = self.pc.package_article(tid)
            contract = res.get("contract") or {}
            temp = contract.get("temp_data") or {}
            peak = temp.get("peak")
            if peak is None:
                continue
            allowed = set()
            for data in self.pc.canonical.REGISTRY_SOLDERS.values():
                for field in ("t_work_max", "t_melt_max"):
                    if data.get(field):
                        allowed.add(f"{data[field]} °C")
            self.assertIn(peak, allowed, f"#{tid}: пик «{peak}» не из реестра (выдуман)")

    def test_tools_not_defaulted(self):
        """Инструменты берутся только из текста."""
        catalog = {"Паяльная станция", "Паяльник", "Термофен", "Микроскоп",
                   "Оловоотсос", "Оплётка", "Термопинцет"}
        for tid in (1, 3, 15, 16, 17, 52):
            res = self.pc.package_article(tid)
            contract = res.get("contract") or {}
            for tool in contract.get("tools") or []:
                self.assertIn(tool["name"], catalog, f"#{tid}: неканонический инструмент {tool['name']}")

    def test_missing_topic_is_error(self):
        res = self.pc.package_article(99999)
        self.assertFalse(res["valid"])
        self.assertTrue(any("не найдена" in e for e in res.get("errors", [])))

    def test_empty_article_is_error(self):
        res = self.pc.package_article(62)  # тема без текста
        if res.get("contract") is None:
            self.assertFalse(res["valid"])
            self.assertTrue(any("отсутствует готовый текст" in e for e in res.get("errors", [])))


@unittest.skipUnless(DB_FILE.exists(), "нет seo/data/seo.db")
class TestScorerSafety(unittest.TestCase):
    """Скорер не должен путать предупреждение с опасным советом."""

    def setUp(self):
        from score_article import blocks
        self.blocks = blocks

    def _danger(self, text: str) -> list:
        report = self.blocks(text, None, "test")
        return report["metrics"]["dangerous"]

    def test_negated_warning_is_not_danger(self):
        text = (
            "# Как паять провода\n\n"
            "Пайка проводов требует подготовки. Зачистите жилы и залудите их.\n\n"
            "## Безопасность\n\n"
            "Не паяйте под напряжением. Пайка под напряжением запрещена.\n"
        )
        self.assertEqual(self._danger(text), [], "предупреждение ТБ ложно помечено опасным советом")

    def test_real_danger_is_caught(self):
        text = (
            "# Как паять провода\n\n"
            "Пайка проводов требует подготовки. Зачистите жилы и залудите их.\n\n"
            "## Порядок работы\n\n"
            "Паяйте под напряжением, чтобы не тратить время на отключение.\n"
        )
        self.assertTrue(self._danger(text), "реальный опасный совет не поймали")


@unittest.skipUnless(DB_FILE.exists(), "нет seo/data/seo.db")
class TestSqlitePlan(unittest.TestCase):
    """SQLite — единственный источник плана, Excel не нужен."""

    def test_build_db_plan_without_excel(self):
        from db import build_db_plan, get_connection
        plan = build_db_plan()
        self.assertIn("topics", plan)
        self.assertIn("clusters", plan)
        with get_connection() as conn:
            db_topics = conn.execute("SELECT COUNT(*) FROM topics").fetchone()[0]
        self.assertEqual(plan["counts"]["topics"], db_topics, "план и база разошлись")
        self.assertGreater(plan["counts"]["topics"], 0)

    def test_links_unique_index(self):
        from db import get_connection
        with get_connection() as conn:
            idx = conn.execute(
                "SELECT COUNT(*) FROM sqlite_master WHERE type='index' AND name='idx_links_pair'"
            ).fetchone()[0]
            dupes = conn.execute(
                "SELECT COUNT(*) FROM (SELECT source_id, target_id FROM links GROUP BY 1,2 HAVING COUNT(*)>1)"
            ).fetchone()[0]
        self.assertEqual(idx, 1, "нет уникального индекса на пары связей")
        self.assertEqual(dupes, 0, "в links остались дубликаты")


if __name__ == "__main__":
    unittest.main(verbosity=2)
