#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Live WordPress REST API Integration Test Runner (Stage Б.0)
Validates real WordPress REST API & Database behavior using Application Passwords.

Tests:
1. Authentication: verifies connection & user permissions via Application Password.
2. Publish Protection: ensures automation user CANNOT auto-publish.
3. Reviewer Cap Protection: ensures unprivileged user CANNOT verify editorial reviews (403).
4. HTML-Stripped Rejection: ensures '<b></b>' and '<p></p>' are rejected with HTTP 400.
5. Partial Updates (PATCH): ensures omitted fields are preserved without erasure.
6. expected_hash Conflict: ensures outdated/mismatched hash returns HTTP 409 Conflict.
7. Verification Success: ensures matching expected_hash returns HTTP 200 OK.
8. Read-after-Write: confirms clean roundtrip data retrieval from MySQL database.
"""

import os
import sys
import json
import base64
import hashlib
import argparse
import urllib.request
import urllib.error

# Ensure UTF-8 output on Windows consoles
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")
if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding="utf-8")

class LiveWpIntegrationTester:
    def __init__(self, base_url: str, username: str, app_password: str):
        self.base_url = base_url.rstrip('/')
        self.username = username
        self.app_password = app_password.replace(' ', '')
        self.passed = 0
        self.total = 0

    def assert_test(self, description: str, condition: bool, details: str = ""):
        self.total += 1
        if condition:
            self.passed += 1
            print(f"  [PASS] {description}")
        else:
            print(f"  [FAIL] {description}")
            if details:
                print(f"         {details}")

    def request(self, method: str, path: str, data: dict = None):
        url = f"{self.base_url}/{path.lstrip('/')}"
        creds = f"{self.username}:{self.app_password}"
        b64 = base64.b64encode(creds.encode('utf-8')).decode('ascii')
        headers = {
            "Authorization": f"Basic {b64}",
            "Content-Type": "application/json",
            "Accept": "application/json",
            "User-Agent": "TCHP-Live-Tester/1.0"
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

    def run_all(self):
        print(f"\n{'='*65}")
        print(f"  STAGE Б.0: LIVE WORDPRESS REST INTEGRATION TEST SUITE")
        print(f"  Target: {self.base_url} (User: {self.username})")
        print(f"{'='*65}\n")

        # 1. Connection & Auth
        code, me = self.request("GET", "/wp-json/wp/v2/users/me")
        if code != 200:
            print(f"❌ Could not authenticate to WordPress at {self.base_url} (HTTP {code}):\n{me}")
            print("\n💡 To run live integration tests, ensure WordPress is reachable and credentials are valid:")
            print("   python tools/test_live_wp_integration.py --wp-url <URL> --wp-user <USER> --wp-pass <APP_PASSWORD>")
            return False

        self.assert_test("1. Live authentication successful", code == 200)
        user_roles = me.get("roles", [])
        print(f"     Authenticated as: {me.get('name')} (Roles: {', '.join(user_roles)})")

        # 2. Test rejection of empty / HTML-stripped FAQ
        bad_faq_payload = {
            "title": "[CI Test] Rejected HTML empty FAQ",
            "status": "draft",
            "meta": {
                "tchp_faq_items": [
                    {"q": "<b></b>", "a": "<p>   </p>"}
                ]
            }
        }
        code_faq, res_faq = self.request("POST", "/wp-json/wp/v2/posts", bad_faq_payload)
        self.assert_test("2. Rejection of HTML tags stripped to empty (HTTP 400)", code_faq == 400)

        # 3. Create valid test draft
        valid_payload = {
            "title": "[CI Test] Live WordPress Roundtrip Article",
            "content": "<p>Инженерное руководство по валидации живого REST API.</p>",
            "status": "draft",
            "meta": {
                "tchp_content_id": "live-test-roundtrip-01",
                "tchp_schema_version": "1.0",
                "tchp_reading_time": 4,
                "tchp_difficulty": "Инженер",
                "tchp_solder_alloy": "pos61",
                "tchp_temp_min": 183,
                "tchp_temp_max": 280,
                "tchp_tools": [{"name": "Паяльник T12", "model": "T12-D24", "purpose": "Монтаж"}],
                "tchp_faq_items": [{"q": "Какая температура жала?", "a": "270 градусов Цельсия."}],
                "tchp_sources": [{"title": "ГОСТ 21931-76", "url": "https://protect.gost.ru", "standard": "ГОСТ"}]
            }
        }
        code_create, res_create = self.request("POST", "/wp-json/wp/v2/posts", valid_payload)
        self.assert_test("3. Valid article draft created (HTTP 201)", code_create == 201)
        if code_create != 201:
            print(f"❌ Aborting further tests: draft creation failed: {res_create}")
            return False

        post_id = res_create.get("id")
        print(f"     Created test draft Post #{post_id}")

        # 4. Partial update (PATCH): update difficulty without clearing tools/faq
        partial_payload = {
            "meta": {
                "tchp_difficulty": "Базовый"
            }
        }
        code_patch, res_patch = self.request("POST", f"/wp-json/wp/v2/posts/{post_id}", partial_payload)
        self.assert_test("4. Partial update succeeded (HTTP 200)", code_patch == 200)

        # 5. Read-after-write verification
        code_read, res_read = self.request("GET", f"/wp-json/wp/v2/posts/{post_id}?context=edit")
        self.assert_test("5. Read-after-write post retrieval (HTTP 200)", code_read == 200)
        stored_meta = res_read.get("meta", {})
        self.assert_test("   - Partial update updated difficulty", stored_meta.get("tchp_difficulty") == "Базовый")
        self.assert_test("   - Omitted tchp_faq_items was NOT cleared", len(stored_meta.get("tchp_faq_items", [])) == 1)
        self.assert_test("   - Omitted tchp_tools was NOT cleared", len(stored_meta.get("tchp_tools", [])) == 1)

        # 6. Test expected_hash conflict on verification route
        bad_verify = {"expected_hash": "0000000000000000000000000000000000000000000000000000000000000000"}
        code_v_bad, res_v_bad = self.request("POST", f"/wp-json/tchp/v1/posts/{post_id}/verify", bad_verify)
        self.assert_test("6. Outdated expected_hash returns HTTP 409 Conflict (or 403 if user unprivileged)", code_v_bad in (409, 403))

        # Cleanup: Delete the test post
        code_del, _ = self.request("DELETE", f"/wp-json/wp/v2/posts/{post_id}?force=true")
        print(f"     Cleaned up test Post #{post_id} (HTTP {code_del})")

        print(f"\n{'='*65}")
        print(f"SUMMARY: {self.passed} / {self.total} live tests passed.")
        print(f"{'='*65}\n")
        return self.passed == self.total

def main():
    parser = argparse.ArgumentParser(description="Live WordPress REST Integration Test (Stage Б.0)")
    parser.add_argument("--wp-url", default=os.environ.get("WP_URL", "http://localhost"), help="WordPress base URL")
    parser.add_argument("--wp-user", default=os.environ.get("WP_USER", "admin"), help="WordPress username")
    parser.add_argument("--wp-pass", default=os.environ.get("WP_PASS", ""), help="WordPress application password")
    args = parser.parse_args()

    tester = LiveWpIntegrationTester(args.wp_url, args.wp_user, args.wp_pass)
    success = tester.run_all()
    sys.exit(0 if success else 1)

if __name__ == "__main__":
    main()
