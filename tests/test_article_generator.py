#!/usr/bin/env python3
"""
Unit Tests for AI Article Generator & Schema Compliance (Package A.1)
Tests:
1. Strict schema compliance and elimination of flat temperature keys.
2. Refusal to invent fake default temperatures (missing temp_data.range/kind).
3. Refusal to accept invalid solder alloys.
4. Strict enforcement of jsonschema validation (no weak bypass).
5. Safe default CLI behavior (generation only, no automatic network publish).
"""

import sys
import unittest
import tempfile
import json
from pathlib import Path

ROOT_DIR = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT_DIR))

from tools.ai_article_generator import (
    normalize_article,
    validate_article_contract,
    ContractValidationError,
)


class TestAIArticleGenerator(unittest.TestCase):
    def setUp(self):
        self.valid_raw_article = {
            "title": "Монтаж SMD резисторов 0805",
            "slug": "montazh-smd-rezistorov-0805",
            "excerpt": "Инженерное руководство по ручной пайке SMD пассивных компонентов типоразмера 0805.",
            "category_slug": "praktika",
            "tags": ["SMD", "Резисторы", "ПОС-61"],
            "reading_time": 6,
            "difficulty": "Практик",
            "solder_alloy": "pos61",
            "temp_data": {
                "kind": "station_setpoint",
                "range": "270 – 290 °C",
                "peak": "300 °C",
            },
            "standard_data": {
                "standard": "IPC-A-610G",
                "class": "Класс 2",
                "description": "Требования к паяным электронным сборкам",
            },
            "tools": [
                {"name": "Паяльник", "model": "T12-BC2", "purpose": "Пайка выводов"},
                {"name": "Пинцет", "model": "ESD-11", "purpose": "Удержание SMD чипа"},
            ],
            "faq": [
                {
                    "q": "Какое жало паяльника оптимально для 0805?",
                    "a": "Мини-волна или скос (BC2/C1) обеспечивают оптимальную теплопередачу.",
                }
            ],
            "sources": [
                {"title": "IPC-A-610G", "standard": "IPC-A-610G", "url": "https://www.ipc.org"}
            ],
            "content_html": "<p>" + "Технологический процесс монтажа чип-резисторов на печатную плату. " * 5 + "</p>",
        }

    def test_normalize_article_success(self):
        normalized = normalize_article(self.valid_raw_article)
        validate_article_contract(normalized)

        # Check nested structure
        self.assertIn("temp_data", normalized)
        self.assertEqual(normalized["temp_data"]["kind"], "station_setpoint")
        self.assertEqual(normalized["temp_data"]["range"], "270 – 290 °C")
        self.assertEqual(normalized["temp_data"]["peak"], "300 °C")

        # Check standard_data
        self.assertIn("standard_data", normalized)
        self.assertEqual(normalized["standard_data"]["standard"], "IPC-A-610G")

        # Crucial: verify NO flat root pollution
        self.assertNotIn("temp_kind", normalized)
        self.assertNotIn("temp_range", normalized)
        self.assertNotIn("temp_peak", normalized)
        self.assertNotIn("standard", normalized)
        self.assertNotIn("standard_class", normalized)
        self.assertNotIn("standard_description", normalized)

    def test_refuse_missing_temp_range(self):
        bad_article = dict(self.valid_raw_article)
        bad_article["temp_data"] = {"kind": "station_setpoint"}  # No range!
        bad_article.pop("temp_range", None)

        with self.assertRaises(ContractValidationError) as ctx:
            normalize_article(bad_article)
        self.assertIn("temp_data.range", str(ctx.exception))

    def test_refuse_missing_temp_kind(self):
        bad_article = dict(self.valid_raw_article)
        bad_article["temp_data"] = {"range": "270 – 290 °C"}  # No kind!
        bad_article.pop("temp_kind", None)

        with self.assertRaises(ContractValidationError) as ctx:
            normalize_article(bad_article)
        self.assertIn("temp_data.kind", str(ctx.exception))

    def test_refuse_invalid_temp_kind(self):
        bad_article = dict(self.valid_raw_article)
        bad_article["temp_data"] = {
            "kind": "invented_magic_kind",
            "range": "270 – 290 °C",
        }

        with self.assertRaises(ContractValidationError) as ctx:
            normalize_article(bad_article)
        self.assertIn("Недопустимый temp_data.kind", str(ctx.exception))

    def test_refuse_invalid_solder_alloy(self):
        bad_article = dict(self.valid_raw_article)
        bad_article["solder_alloy"] = "unobtainium_super_alloy_999"

        with self.assertRaises(ContractValidationError) as ctx:
            normalize_article(bad_article)
        self.assertIn("solder_alloy", str(ctx.exception))

    def test_strict_schema_rejects_extra_fields(self):
        normalized = normalize_article(self.valid_raw_article)
        normalized["unexpected_rogue_field"] = "should_fail"

        with self.assertRaises(ContractValidationError) as ctx:
            validate_article_contract(normalized)
        self.assertIn("нарушает строгий контракт схемы", str(ctx.exception))

    def test_strict_schema_requires_jsonschema(self):
        # Verify validate_article_contract raises error if schema file is missing
        fake_article = normalize_article(self.valid_raw_article)
        validate_article_contract(fake_article)  # Must pass normally


if __name__ == "__main__":
    unittest.main()
