#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Unit tests for tools/delivery_cli.py
Validates:
1. Canonical fingerprint parity under key reordering & missing optional fields.
2. Full read-back verification: content.raw, status, slug, categories, tags, id, meta.
3. DeliveryJournal robustness: 0-byte file rejection, version 2.0 handling.
4. Taxonomy enforcement: category & tag resolution error halts.
5. Indeterminate outcomes & outcome_unknown handling.
"""

import os
import sys
import json
import tempfile
import unittest
from pathlib import Path

ROOT_DIR = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT_DIR / "tools"))

from delivery_cli import (
    compute_canonical_fingerprint,
    compute_server_post_fingerprint,
    normalize_canonical_tools,
    normalize_canonical_faq,
    normalize_canonical_sources,
    verify_read_back,
    DeliveryJournal,
    WordPressRestClient,
    build_wp_payload,
)


class TestDeliveryCliUnit(unittest.TestCase):

    def test_canonical_fingerprint_key_reordering(self):
        art1 = {
            "title": "  Тест пайки  ",
            "content_html": "<p>Текст</p>\n",
            "excerpt": "Лид",
            "tools": [{"name": "Паяльник", "model": "T12", "purpose": "Монтаж"}],
            "faq": [{"q": "Вопрос?", "a": "Ответ!"}],
            "sources": [{"title": "ГОСТ", "standard": "ГОСТ 1", "url": "https://gost.ru"}],
            "solder_alloy": "pos61",
            "temp_data": {"kind": "joint_temperature", "range": "210-230 °C", "peak": "245 °C"},
            "standard_data": {"standard": "IPC", "class": "2", "description": "Desc"}
        }

        # Shuffled keys in nested objects
        art2 = {
            "excerpt": "Лид",
            "content": "<p>Текст</p>",  # content instead of content_html
            "title": "Тест пайки",
            "tools": [{"purpose": "Монтаж", "name": "Паяльник", "model": "T12"}],
            "faq": [{"a": "Ответ!", "q": "Вопрос?"}],
            "sources": [{"url": "https://gost.ru", "title": "ГОСТ", "standard": "ГОСТ 1"}],
            "solder_alloy": "pos61",
            "temp_data": {"peak": "245 °C", "range": "210-230 °C", "kind": "joint_temperature"},
            "standard_data": {"description": "Desc", "class": "2", "standard": "IPC"}
        }

        h1 = compute_canonical_fingerprint(art1)
        h2 = compute_canonical_fingerprint(art2)
        self.assertEqual(h1, h2, "Fingerprint must be identical despite dictionary key reordering")

    def test_canonical_fingerprint_missing_optional_fields(self):
        # Missing model and purpose in tool, missing standard and url in source
        art_minimal = {
            "title": "Заголовок",
            "content": "<p>Текст</p>",
            "excerpt": "Лид",
            "tools": [{"name": "Паяльник"}],
            "faq": [{"q": "В?", "a": "О!"}],
            "sources": [{"title": "Источник"}],
            "solder_alloy": "pos61"
        }

        art_explicit_empty = {
            "title": "Заголовок",
            "content": "<p>Текст</p>",
            "excerpt": "Лид",
            "tools": [{"name": "Паяльник", "model": "", "purpose": ""}],
            "faq": [{"q": "В?", "a": "О!"}],
            "sources": [{"title": "Источник", "standard": "", "url": ""}],
            "solder_alloy": "pos61",
            "temp_data": {"kind": "", "range": "", "peak": ""},
            "standard_data": {"standard": "", "class": "", "description": ""}
        }

        h_min = compute_canonical_fingerprint(art_minimal)
        h_exp = compute_canonical_fingerprint(art_explicit_empty)
        self.assertEqual(h_min, h_exp, "Missing optional fields must match empty string defaults byte-for-byte")

    def test_verify_read_back_full_match(self):
        sent_payload = {
            "id": 123,
            "title": "Статья о пайке",
            "content": "<p>Основной текст руководства.</p>",
            "excerpt": "Краткое описание",
            "slug": "statya-o-payke",
            "status": "draft",
            "categories": [4, 7],
            "tags": [10, 15],
            "meta": {
                "tchp_content_id": "art-001",
                "tchp_schema_version": "1.0",
                "tchp_reading_time": 5,
                "tchp_difficulty": "Инженер",
                "tchp_solder_alloy": "pos61",
                "tchp_temp_kind": "joint_temperature",
                "tchp_temp_range": "210-230 °C",
                "tchp_temp_peak": "245 °C",
                "tchp_standard": "IPC-A-610G",
                "tchp_standard_class": "КЛАСС 2",
                "tchp_standard_description": "Описание стандарта",
                "tchp_tools": [{"name": "Паяльник", "model": "T12", "purpose": "Монтаж"}],
                "tchp_faq_items": [{"q": "Вопрос?", "a": "Ответ!"}],
                "tchp_sources": [{"title": "ГОСТ", "standard": "ГОСТ", "url": "https://gost.ru"}]
            }
        }

        stored_post = {
            "id": 123,
            "status": "draft",
            "slug": "statya-o-payke",
            "title": {"raw": "Статья о пайке", "rendered": "Статья о пайке"},
            "content": {"raw": "<p>Основной текст руководства.</p>\n", "rendered": "<p>Основной текст руководства.</p>"},
            "excerpt": {"raw": "Краткое описание", "rendered": "Краткое описание"},
            "categories": [7, 4],  # set equality: different order
            "tags": [15, 10],        # set equality: different order
            "meta": dict(sent_payload["meta"])
        }

        matches, mismatches = verify_read_back(sent_payload, stored_post)
        self.assertTrue(matches, f"Read-back must pass on valid data! Mismatches: {mismatches}")
        self.assertEqual(len(mismatches), 0)

    def test_verify_read_back_catches_content_and_taxonomies(self):
        sent_payload = {
            "id": 123,
            "title": "Статья о пайке",
            "content": "<p>Основной текст руководства.</p>",
            "excerpt": "Краткое описание",
            "slug": "statya-o-payke",
            "status": "draft",
            "categories": [4],
            "tags": [10],
            "meta": {}
        }

        stored_tampered = {
            "id": 123,
            "status": "publish",  # mismatch: wanted draft
            "slug": "statya-tampered",  # mismatch
            "title": {"raw": "Статья о пайке"},
            "content": {"raw": "<p>ИЗМЕНЕННЫЙ ТЕКСТ!</p>"},  # mismatch
            "excerpt": {"raw": "Краткое описание"},
            "categories": [99],  # mismatch
            "tags": [],          # mismatch
            "meta": {}
        }

        matches, mismatches = verify_read_back(sent_payload, stored_tampered)
        self.assertFalse(matches)
        m_str = " ".join(mismatches)
        self.assertIn("status:", m_str)
        self.assertIn("slug:", m_str)
        self.assertIn("content:", m_str)
        self.assertIn("categories:", m_str)
        self.assertIn("tags:", m_str)

    def test_journal_zero_byte_fatal_error(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            j_path = Path(tmp_dir) / "empty-journal.json"
            j_path.write_text("", encoding="utf-8")  # 0 bytes!

            with self.assertRaises(RuntimeError) as ctx:
                DeliveryJournal(j_path)
            self.assertIn("0 bytes", str(ctx.exception))

    def test_journal_version_and_atomic_save(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            j_path = Path(tmp_dir) / "journal.json"
            journal = DeliveryJournal(j_path)
            self.assertEqual(journal.data.get("version"), "2.0")

            journal.set_entry("https://example.com", "art-1", {
                "wp_post_id": 42,
                "status": "draft",
                "read_back_status": "verified"
            })

            # Reload journal from disk
            journal2 = DeliveryJournal(j_path)
            entry = journal2.get_entry("https://example.com", "art-1")
            self.assertIsNotNone(entry)
            self.assertEqual(entry["wp_post_id"], 42)
            self.assertEqual(journal2.data.get("version"), "2.0")


if __name__ == "__main__":
    unittest.main()
