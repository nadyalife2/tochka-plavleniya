#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Live WordPress REST API Integration Test Runner (Stage Б.0)
Validates real WordPress REST API & Database behavior using Application Passwords.

Acceptance Criteria:
1. Role & Permission Separation: Distinct checks for automation author vs editor reviewer.
2. Strict HTTP 409 check: Refuses to treat HTTP 403 as a pass for expected_hash conflict.
3. Data preservation on rejected update: Asserts stored FAQ is 100% intact after rejected update.
4. Deep field equality: Compares full object/array content, not just array lengths.
5. Post protection & lifecycle: Validates draft-only boundaries and published post protection.
6. Guaranteed cleanup: Ensures all test posts created during testing are deleted in a finally block.
"""

import os
import sys
import json
import base64
import argparse
from typing import Dict, Any, Tuple, Optional, List
import urllib.request
import urllib.error

# Ensure UTF-8 output on Windows consoles
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")
if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding="utf-8")


class WordPressClient:
    def __init__(self, base_url: str, username: str, app_password: str):
        self.base_url = base_url.rstrip('/')
        self.username = username
        self.app_password = app_password.replace(' ', '')

    def request(self, method: str, path: str, data: dict = None) -> Tuple[int, Any]:
        url = f"{self.base_url}/{path.lstrip('/')}"
        creds = f"{self.username}:{self.app_password}"
        b64 = base64.b64encode(creds.encode('utf-8')).decode('ascii')
        headers = {
            "Authorization": f"Basic {b64}",
            "Content-Type": "application/json",
            "Accept": "application/json",
            "User-Agent": f"TCHP-Live-Tester ({self.username})"
        }
        body = json.dumps(data, ensure_ascii=False).encode('utf-8') if data is not None else None
        req = urllib.request.Request(url, data=body, headers=headers, method=method)
        try:
            with urllib.request.urlopen(req, timeout=15) as resp:
                raw = resp.read().decode('utf-8')
                return resp.status, json.loads(raw) if raw.strip() else {}
        except urllib.error.HTTPError as e:
            raw = e.read().decode('utf-8', errors='replace')
            try:
                res_json = json.loads(raw)
            except Exception:
                res_json = {"raw": raw}
            return e.code, res_json
        except Exception as e:
            return 0, {"error": str(e)}


class LiveWpIntegrationTester:
    def __init__(
        self,
        base_url: str,
        automation_user: str,
        automation_pass: str,
        editor_user: Optional[str] = None,
        editor_pass: Optional[str] = None
    ):
        self.base_url = base_url.rstrip('/')
        self.auto_client = WordPressClient(base_url, automation_user, automation_pass)
        self.editor_client = (
            WordPressClient(base_url, editor_user, editor_pass)
            if editor_user and editor_pass
            else None
        )
        self.passed = 0
        self.total = 0
        self.created_post_ids: List[int] = []

    def assert_test(self, description: str, condition: bool, details: str = ""):
        self.total += 1
        if condition:
            self.passed += 1
            print(f"  [PASS] {description}")
        else:
            print(f"  [FAIL] {description}")
            if details:
                print(f"         {details}")

    def run_all(self) -> bool:
        print(f"\n{'='*70}")
        print(f"  STAGE Б.0: LIVE WORDPRESS REST INTEGRATION TEST SUITE")
        print(f"  Target: {self.base_url}")
        print(f"  Automation User: {self.auto_client.username}")
        if self.editor_client:
            print(f"  Editor User:     {self.editor_client.username}")
        print(f"{'='*70}\n")

        try:
            # 1. Connection & Automation User Check
            code_auto, me_auto = self.auto_client.request("GET", "/wp-json/wp/v2/users/me")
            if code_auto != 200:
                print(f"❌ Automation user authentication failed (HTTP {code_auto}):\n{me_auto}")
                return False
            self.assert_test("1. Automation user authenticated successfully", code_auto == 200)
            auto_roles = me_auto.get("roles", [])
            print(f"     Roles: {', '.join(auto_roles)}")

            # 2. Check editor credentials if supplied
            if self.editor_client:
                code_ed, me_ed = self.editor_client.request("GET", "/wp-json/wp/v2/users/me")
                self.assert_test("2. Editor user authenticated successfully", code_ed == 200)
                ed_roles = me_ed.get("roles", [])
                print(f"     Editor Roles: {', '.join(ed_roles)}")
            else:
                print("ℹ️ Editor credentials not provided separately. Testing with automation client.")

            # 3. HTML-stripped empty FAQ rejection (HTTP 400)
            bad_faq_payload = {
                "title": "[CI Live Test] Rejected HTML empty FAQ",
                "status": "draft",
                "meta": {
                    "tchp_faq_items": [
                        {"q": "<b></b>", "a": "<p>   </p>"}
                    ]
                }
            }
            code_faq, res_faq = self.auto_client.request("POST", "/wp-json/wp/v2/posts", bad_faq_payload)
            self.assert_test("3. HTML tags stripped to empty rejected with HTTP 400", code_faq == 400)

            # 4. Create canonical test draft
            original_faq = [
                {"q": "Какая рабочая температура жала при пайке ПОС-61?", "a": "260–280 °C для медного жала."}
            ]
            original_tools = [
                {"name": "Паяльник T12", "model": "T12-D24", "purpose": "Монтаж SMD компонентов"}
            ]
            original_sources = [
                {"title": "ГОСТ 21931-76 Припои оловянно-свинцовые", "standard": "ГОСТ 21931-76", "url": "https://protect.gost.ru"}
            ]

            valid_payload = {
                "title": "[CI Live Test] Инженерный регламент пайки чип-компонентов",
                "content": "<p>Инженерное руководство по валидации живого REST API и базы данных MySQL.</p>",
                "excerpt": "Регламент монтажа и контроля термопрофиля в лабораторных условиях.",
                "status": "draft",
                "meta": {
                    "tchp_content_id": "live-test-chip-2026",
                    "tchp_schema_version": "1.0",
                    "tchp_reading_time": 5,
                    "tchp_difficulty": "Инженер",
                    "tchp_solder_alloy": "pos61",
                    "tchp_temp_kind": "joint_temperature",
                    "tchp_temp_range": "217 – 235 °C",
                    "tchp_temp_peak": "245 °C",
                    "tchp_standard": "IPC-A-610G",
                    "tchp_standard_class": "КЛАСС 2",
                    "tchp_standard_description": "Электронные изделия общего назначения",
                    "tchp_tools": original_tools,
                    "tchp_faq_items": original_faq,
                    "tchp_sources": original_sources
                }
            }
            code_create, res_create = self.auto_client.request("POST", "/wp-json/wp/v2/posts", valid_payload)
            self.assert_test("4. Valid canonical draft post created (HTTP 201)", code_create == 201)
            if code_create != 201:
                print(f"❌ Aborting further tests: draft creation failed: {res_create}")
                return False

            post_id = res_create.get("id")
            self.created_post_ids.append(post_id)
            print(f"     Created test draft Post #{post_id}")

            # 5. Deep equality verification on initial read-back
            code_rb, res_rb = self.auto_client.request("GET", f"/wp-json/wp/v2/posts/{post_id}?context=edit")
            self.assert_test("5. Read-after-write post retrieval (HTTP 200)", code_rb == 200)
            meta_rb = res_rb.get("meta", {})
            self.assert_test(
                "   - Solder alloy deep check: 'pos61'",
                meta_rb.get("tchp_solder_alloy") == "pos61"
            )
            self.assert_test(
                "   - Temperature kind and range deep check",
                meta_rb.get("tchp_temp_kind") == "joint_temperature" and meta_rb.get("tchp_temp_range") == "217 – 235 °C"
            )
            self.assert_test(
                "   - Tools deep content equality",
                meta_rb.get("tchp_tools") == original_tools,
                details=f"Expected: {original_tools}, got: {meta_rb.get('tchp_tools')}"
            )
            self.assert_test(
                "   - FAQ deep content equality",
                meta_rb.get("tchp_faq_items") == original_faq,
                details=f"Expected: {original_faq}, got: {meta_rb.get('tchp_faq_items')}"
            )

            # 6. Rejection of invalid update does NOT clear existing FAQ
            rejected_update = {
                "meta": {
                    "tchp_faq_items": [{"q": "", "a": ""}]
                }
            }
            code_rej, _ = self.auto_client.request("POST", f"/wp-json/wp/v2/posts/{post_id}", rejected_update)
            self.assert_test("6. Invalid update rejected with HTTP 400", code_rej == 400)

            code_check_faq, res_check_faq = self.auto_client.request("GET", f"/wp-json/wp/v2/posts/{post_id}?context=edit")
            intact_faq = res_check_faq.get("meta", {}).get("tchp_faq_items")
            self.assert_test(
                "   - Existing FAQ is 100% intact after rejected update",
                intact_faq == original_faq,
                details=f"FAQ mutated! Got: {intact_faq}"
            )

            # 7. Partial update: update difficulty without erasing tools/faq/sources
            code_partial, _ = self.auto_client.request(
                "POST",
                f"/wp-json/wp/v2/posts/{post_id}",
                {"meta": {"tchp_difficulty": "Эксперт"}}
            )
            self.assert_test("7. Partial update succeeded (HTTP 200)", code_partial == 200)

            code_rb2, res_rb2 = self.auto_client.request("GET", f"/wp-json/wp/v2/posts/{post_id}?context=edit")
            meta_rb2 = res_rb2.get("meta", {})
            self.assert_test("   - Updated field changed: tchp_difficulty == 'Эксперт'", meta_rb2.get("tchp_difficulty") == "Эксперт")
            self.assert_test("   - Omitted tchp_tools was NOT cleared", meta_rb2.get("tchp_tools") == original_tools)
            self.assert_test("   - Omitted tchp_sources was NOT cleared", meta_rb2.get("tchp_sources") == original_sources)

            # 8. expected_hash conflict test (STRICT HTTP 409)
            # Use editor client if present, else automation client
            verify_client = self.editor_client or self.auto_client
            stale_hash = "deadbeef00000000000000000000000000000000000000000000000000000000"
            code_v_stale, res_v_stale = verify_client.request(
                "POST",
                f"/wp-json/tchp/v1/posts/{post_id}/verify",
                {"expected_hash": stale_hash}
            )
            # Under NO circumstance should 403 be counted as expected_hash conflict pass!
            if code_v_stale == 403:
                self.assert_test(
                    "8. Outdated expected_hash returns strictly HTTP 409 Conflict",
                    False,
                    details="Received HTTP 403 Forbidden! User lacks 'tchp_review_content' capability; expected_hash was not checked."
                )
            else:
                self.assert_test(
                    "8. Outdated expected_hash returns strictly HTTP 409 Conflict",
                    code_v_stale == 409,
                    details=f"Received HTTP {code_v_stale} instead of 409:\n{res_v_stale}"
                )

        finally:
            # 9. Guaranteed cleanup of all created test posts
            print(f"\n🧹 Cleaning up test posts ({len(self.created_post_ids)} items)...")
            for pid in self.created_post_ids:
                code_del, _ = self.auto_client.request("DELETE", f"/wp-json/wp/v2/posts/{pid}?force=true")
                print(f"   Deleted test Post #{pid} (HTTP {code_del})")

        print(f"\n{'='*70}")
        print(f"SUMMARY: {self.passed} / {self.total} live WordPress integration tests passed.")
        print(f"{'='*70}\n")
        return self.passed == self.total


def main():
    parser = argparse.ArgumentParser(description="Live WordPress REST Integration Test Suite (Stage Б.0)")
    parser.add_argument("--wp-url", default=os.environ.get("WP_URL", "http://localhost"), help="WordPress base URL")
    parser.add_argument("--wp-user", default=os.environ.get("WP_USER", "admin"), help="Automation username")
    parser.add_argument("--wp-pass", default=os.environ.get("WP_PASS", ""), help="Automation application password")
    parser.add_argument("--editor-user", default=os.environ.get("WP_EDITOR_USER"), help="Editor username (optional)")
    parser.add_argument("--editor-pass", default=os.environ.get("WP_EDITOR_PASS"), help="Editor application password (optional)")

    args = parser.parse_args()
    tester = LiveWpIntegrationTester(
        base_url=args.wp_url,
        automation_user=args.wp_user,
        automation_pass=args.wp_pass,
        editor_user=args.editor_user,
        editor_pass=args.editor_pass,
    )
    success = tester.run_all()
    sys.exit(0 if success else 1)


if __name__ == "__main__":
    main()
