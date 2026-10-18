#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Live WordPress REST API Integration Test Runner (Stage Б.0)
Validates real WordPress REST API & Database behavior using Application Passwords.

Acceptance Criteria:
1. Role & Permission Separation: Checks automation author vs editor reviewer.
2. Publishing Refusal: Ensures automation user cannot publish articles directly.
3. Review Capability: Automation user calling /verify endpoint gets strictly HTTP 403.
4. Editor Verification: Editor user calling /verify with valid expected_hash gets HTTP 200.
5. Strict HTTP 409: Refuses to treat HTTP 403 as a pass for expected_hash conflict.
6. Data preservation on rejected update: Asserts stored FAQ is 100% intact after rejected update.
7. Deep field equality: Compares full object/array content, not just array lengths.
8. Subprocess Delivery CLI Test: Tests delivery_cli.py execution and edit conflict detection (exit code 3).
9. Guaranteed cleanup & verification: Deletes test posts and asserts GET returns HTTP 404.
"""

import os
import sys
import time
import json
import base64
import tempfile
import argparse
import subprocess
from pathlib import Path
from typing import Dict, Any, Tuple, Optional, List
import urllib.request
import urllib.error

ROOT_DIR = Path(__file__).resolve().parent.parent

# Ensure UTF-8 output on Windows consoles
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")
if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding="utf-8")

from delivery_cli import compute_server_post_fingerprint


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
                print("ℹ️ Editor credentials not provided separately. Testing editor verification when available.")

            # 3. Direct publishing refusal for automation user
            publish_payload = {
                "title": "[CI Live Test] Rejection of direct publish by automation",
                "status": "publish",
                "content": "<p>Content should not be published automatically.</p>"
            }
            code_pub, res_pub = self.auto_client.request("POST", "/wp-json/wp/v2/posts", publish_payload)
            if code_pub in (200, 201):
                pid_pub = res_pub.get("id")
                if pid_pub:
                    self.created_post_ids.append(pid_pub)
                actual_pub_status = res_pub.get("status")
                self.assert_test(
                    "3. Direct publish disallowed (status clamped away from 'publish')",
                    actual_pub_status in ("draft", "pending"),
                    details=f"Post #{pid_pub} was published directly! Status: '{actual_pub_status}'"
                )
            else:
                self.assert_test(
                    "3. Direct publish disallowed (rejected with HTTP 403 Forbidden)",
                    code_pub == 403,
                    details=f"Received HTTP {code_pub}: {res_pub}"
                )

            # 4. HTML-stripped empty FAQ rejection (HTTP 400)
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
            self.assert_test("4. HTML tags stripped to empty rejected with HTTP 400", code_faq == 400)

            # 5. Create canonical test draft
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
                    "tchp_content_id": f"live-test-chip-{int(time.time())}",
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
            self.assert_test("5. Valid canonical draft post created (HTTP 201)", code_create == 201)
            if code_create != 201:
                print(f"❌ Aborting further tests: draft creation failed: {res_create}")
                return False

            post_id = res_create.get("id")
            self.created_post_ids.append(post_id)
            print(f"     Created test draft Post #{post_id}")

            # 6. Deep equality verification on initial read-back
            code_rb, res_rb = self.auto_client.request("GET", f"/wp-json/wp/v2/posts/{post_id}?context=edit")
            self.assert_test("6. Read-after-write post retrieval (HTTP 200)", code_rb == 200)
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

            # 7. Rejection of invalid update does NOT clear existing FAQ
            rejected_update = {
                "meta": {
                    "tchp_faq_items": [{"q": "", "a": ""}]
                }
            }
            code_rej, _ = self.auto_client.request("POST", f"/wp-json/wp/v2/posts/{post_id}", rejected_update)
            self.assert_test("7. Invalid update rejected with HTTP 400", code_rej == 400)

            code_check_faq, res_check_faq = self.auto_client.request("GET", f"/wp-json/wp/v2/posts/{post_id}?context=edit")
            intact_faq = res_check_faq.get("meta", {}).get("tchp_faq_items")
            self.assert_test(
                "   - Existing FAQ is 100% intact after rejected update",
                intact_faq == original_faq,
                details=f"FAQ mutated! Got: {intact_faq}"
            )

            # 8. Partial update: update difficulty without erasing tools/faq/sources
            code_partial, _ = self.auto_client.request(
                "POST",
                f"/wp-json/wp/v2/posts/{post_id}",
                {"meta": {"tchp_difficulty": "Эксперт"}}
            )
            self.assert_test("8. Partial update succeeded (HTTP 200)", code_partial == 200)

            code_rb2, res_rb2 = self.auto_client.request("GET", f"/wp-json/wp/v2/posts/{post_id}?context=edit")
            meta_rb2 = res_rb2.get("meta", {})
            self.assert_test("   - Updated field changed: tchp_difficulty == 'Эксперт'", meta_rb2.get("tchp_difficulty") == "Эксперт")
            self.assert_test("   - Omitted tchp_tools was NOT cleared", meta_rb2.get("tchp_tools") == original_tools)
            self.assert_test("   - Omitted tchp_sources was NOT cleared", meta_rb2.get("tchp_sources") == original_sources)

            # 9. Automation user lacks tchp_review_content cap -> /verify endpoint returns strictly HTTP 403
            code_v_auto, res_v_auto = self.auto_client.request(
                "POST",
                f"/wp-json/tchp/v1/posts/{post_id}/verify",
                {"expected_hash": "dummy_hash_for_permission_check"}
            )
            self.assert_test(
                "9. Automation user lacks 'tchp_review_content' cap -> verify endpoint returns HTTP 403 Forbidden",
                code_v_auto == 403,
                details=f"Expected HTTP 403 Forbidden, got HTTP {code_v_auto}: {res_v_auto}"
            )

            # 10. expected_hash conflict test (STRICT HTTP 409)
            verify_client = self.editor_client or self.auto_client
            stale_hash = "deadbeef00000000000000000000000000000000000000000000000000000000"
            code_v_stale, res_v_stale = verify_client.request(
                "POST",
                f"/wp-json/tchp/v1/posts/{post_id}/verify",
                {"expected_hash": stale_hash}
            )
            if self.editor_client:
                self.assert_test(
                    "10. Outdated expected_hash returns strictly HTTP 409 Conflict",
                    code_v_stale == 409,
                    details=f"Received HTTP {code_v_stale} instead of 409:\n{res_v_stale}"
                )
            else:
                print("ℹ️ Skipping Scenario 10 editor 409 assertion (requires editor client with tchp_review_content cap).")

            # 11. Valid expected_hash verification succeeds (HTTP 200 OK)
            if self.editor_client:
                valid_hash = compute_server_post_fingerprint(res_rb2)
                code_v_ok, res_v_ok = self.editor_client.request(
                    "POST",
                    f"/wp-json/tchp/v1/posts/{post_id}/verify",
                    {"expected_hash": valid_hash}
                )
                self.assert_test(
                    "11. Editor verification with valid expected_hash succeeds (HTTP 200 OK)",
                    code_v_ok == 200 and res_v_ok.get("review_status") == "verified",
                    details=f"Received HTTP {code_v_ok}: {res_v_ok}"
                )
            else:
                print("ℹ️ Skipping Scenario 11 editor verification (requires editor client).")

            # 12. Delivery CLI Subprocess Execution & Conflict Detection
            print("\n🔄 Running delivery_cli.py subprocess tests against live WordPress...")
            with tempfile.TemporaryDirectory() as tmp_dir:
                tmp_dir_path = Path(tmp_dir)
                test_cid = f"live-cli-test-{int(time.time())}"
                example_json_path = ROOT_DIR / "schemas" / "article-example-v1.json"
                with open(example_json_path, "r", encoding="utf-8") as f:
                    article_data = json.load(f)
                article_data["content_id"] = test_cid
                article_data["title"] = f"[CI Live Test CLI] {article_data['title']}"
                article_data["slug"] = f"live-cli-test-{int(time.time())}"

                tmp_article_path = tmp_dir_path / "article.json"
                tmp_journal_path = tmp_dir_path / "journal.json"
                with open(tmp_article_path, "w", encoding="utf-8") as f:
                    json.dump(article_data, f, ensure_ascii=False, indent=2)

                cli_proc = subprocess.run(
                    [
                        sys.executable,
                        str(ROOT_DIR / "tools" / "delivery_cli.py"),
                        "send-draft",
                        "--file", str(tmp_article_path),
                        "--wp-url", self.base_url,
                        "--wp-user", self.auto_client.username,
                        "--wp-pass", self.auto_client.app_password,
                        "--journal", str(tmp_journal_path)
                    ],
                    capture_output=True,
                    text=True,
                    encoding="utf-8"
                )
                self.assert_test(
                    "12.1 delivery_cli.py send-draft exits with code 0",
                    cli_proc.returncode == 0,
                    details=f"CLI failed with exit {cli_proc.returncode}:\n{cli_proc.stderr}\n{cli_proc.stdout}"
                )

                # Read created post ID from journal
                if tmp_journal_path.exists():
                    with open(tmp_journal_path, "r", encoding="utf-8") as f:
                        j_data = json.load(f)
                    sk = self.base_url.rstrip('/').lower()
                    entry = j_data.get("sites", {}).get(sk, {}).get(test_cid, {})
                    cli_pid = entry.get("wp_post_id")
                    if cli_pid:
                        self.created_post_ids.append(cli_pid)
                        print(f"     Subprocess created Post #{cli_pid}")

                        # 12.2 Manual edit conflict test: mutate post in WordPress
                        code_edit, _ = self.auto_client.request(
                            "POST",
                            f"/wp-json/wp/v2/posts/{cli_pid}",
                            {"content": "<p>Content edited by human editor in WP Admin.</p>"}
                        )
                        self.assert_test("12.2 Simulated manual edit in WordPress succeeded", code_edit == 200)

                        # Re-run delivery CLI: must detect conflict and return code 3
                        cli_proc_conflict = subprocess.run(
                            [
                                sys.executable,
                                str(ROOT_DIR / "tools" / "delivery_cli.py"),
                                "send-draft",
                                "--file", str(tmp_article_path),
                                "--wp-url", self.base_url,
                                "--wp-user", self.auto_client.username,
                                "--wp-pass", self.auto_client.app_password,
                                "--journal", str(tmp_journal_path)
                            ],
                            capture_output=True,
                            text=True,
                            encoding="utf-8"
                        )
                        self.assert_test(
                            "12.3 delivery_cli detects manual edit conflict and exits with code 3",
                            cli_proc_conflict.returncode == 3,
                            details=f"Expected exit code 3, got {cli_proc_conflict.returncode}:\n{cli_proc_conflict.stderr}"
                        )

        finally:
            # 13. Guaranteed cleanup and deletion verification of all created test posts
            print(f"\n🧹 Cleaning up test posts ({len(self.created_post_ids)} items)...")
            for pid in self.created_post_ids:
                code_del, _ = self.auto_client.request("DELETE", f"/wp-json/wp/v2/posts/{pid}?force=true")
                code_get, _ = self.auto_client.request("GET", f"/wp-json/wp/v2/posts/{pid}")
                self.assert_test(
                    f"Cleaned up test Post #{pid} (DELETE returns 200/202, GET returns 404)",
                    code_del in (200, 202) and code_get == 404,
                    details=f"DELETE HTTP {code_del}, GET HTTP {code_get}"
                )

        print(f"\n{'='*70}")
        print(f"SUMMARY: {self.passed} / {self.total} live WordPress integration tests passed.")
        print(f"{'='*70}\n")
        return self.passed == self.total


def main():
    parser = argparse.ArgumentParser(description="Live WordPress REST Integration Test Suite (Stage Б.0)")
    parser.add_argument("--wp-url", default=os.environ.get("WP_URL", "http://localhost"), help="WordPress base URL")
    parser.add_argument("--wp-user", default=os.environ.get("WP_USER", "automation"), help="Automation username")
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
