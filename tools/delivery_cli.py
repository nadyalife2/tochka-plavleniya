#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Delivery CLI & WordPress REST Integration Engine for «Точка Плавления» (Package Б)
Strict, idempotent, and safe delivery of pre-generated articles into WordPress drafts.

Invariants & Safeguards:
1. Schema conformance: Validates against schemas/article-contract-v1.schema.json before dispatch.
2. Draft only: NEVER publishes automatically (status is strictly 'draft' or 'pending').
3. Published post protection: Refuses to overwrite already published articles (NO --force bypass).
4. Taxonomy resolution: Maps category_slug and tags to real WordPress term IDs.
5. Manual edit detection: Detects reviewer approvals & editor edits, raising conflicts instead of overwriting.
6. Multi-site journal: Differentiates destination site & material, logs 'sending' prior to dispatch,
   records 'outcome_unknown' on communication loss, protected by file locks & atomic write.
7. Deep read-after-write verification: Deeply compares all stored fields with payload; returns exit code 4 on mismatch.
"""

import os
import sys
import json
import base64
import hashlib
import argparse
import contextlib
from pathlib import Path
from datetime import datetime, timezone
from typing import Dict, Any, Optional, Tuple, List
import urllib.request
import urllib.parse
import urllib.error

import jsonschema

ROOT_DIR = Path(__file__).resolve().parent.parent

# Cross-platform file locking
try:
    import msvcrt
except ImportError:
    msvcrt = None
try:
    import fcntl
except ImportError:
    fcntl = None

# Ensure UTF-8 output on Windows consoles
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")
if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding="utf-8")

CANONICAL_ALLOYS = {
    "pos61", "sn63pb37", "pos40", "sac305", "sac405",
    "sn42bi58", "snbiago", "roze", "vuda", "universal",
}

ALLOY_ALIASES = {
    "wood": "vuda",
    "rose": "roze",
}

ALLOWED_UPDATE_STATUSES = {"draft", "pending", "auto-draft"}


def normalize_alloy(value: Any) -> str:
    raw = str(value or "").strip().lower()
    raw = ALLOY_ALIASES.get(raw, raw)
    return raw if raw in CANONICAL_ALLOYS else ""


def normalize_canonical_tools(tools_data: Any) -> list:
    """Normalizes tools array into strict dictionary structure with ordered keys: name, model, purpose."""
    if not isinstance(tools_data, list):
        return []
    clean = []
    for item in tools_data:
        if isinstance(item, str) and item.strip():
            clean.append({
                "name": item.strip(),
                "model": "",
                "purpose": ""
            })
        elif isinstance(item, dict) and str(item.get("name", "")).strip():
            clean.append({
                "name": str(item.get("name", "")).strip(),
                "model": str(item.get("model", "")).strip(),
                "purpose": str(item.get("purpose", "")).strip()
            })
    return clean


def normalize_canonical_faq(faq_data: Any) -> list:
    """Normalizes FAQ array into strict dictionary structure with ordered keys: q, a."""
    if not isinstance(faq_data, list):
        return []
    clean = []
    for item in faq_data:
        if isinstance(item, dict):
            q = str(item.get("q", "")).strip()
            a = str(item.get("a", "")).strip()
            if q and a:
                clean.append({
                    "q": q,
                    "a": a
                })
    return clean


def normalize_canonical_sources(sources_data: Any) -> list:
    """Normalizes sources array into strict dictionary structure with ordered keys: title, standard, url."""
    if not isinstance(sources_data, list):
        return []
    clean = []
    for item in sources_data:
        if isinstance(item, dict) and str(item.get("title", "")).strip():
            clean.append({
                "title": str(item.get("title", "")).strip(),
                "standard": str(item.get("standard", "")).strip(),
                "url": str(item.get("url", "")).strip()
            })
    return clean


def compute_canonical_fingerprint(article_data: dict) -> str:
    """
    Calculates SHA-256 fingerprint from canonical normalized fields.
    Byte-for-byte identical to tchp_calculate_article_hash() in tchp-content.php,
    independent of dictionary key ordering or omitted optional fields in input.
    """
    title = str(article_data.get("title", "")).strip()
    content = str(article_data.get("content_html", article_data.get("content", ""))).strip()
    excerpt = str(article_data.get("excerpt", "")).strip()

    tools = normalize_canonical_tools(article_data.get("tools", []))
    faq = normalize_canonical_faq(article_data.get("faq", []))
    sources = normalize_canonical_sources(article_data.get("sources", []))

    alloy = normalize_alloy(article_data.get("solder_alloy", ""))

    temp_data = article_data.get("temp_data") or {}
    t_kind = str(temp_data.get("kind", ""))
    t_range = str(temp_data.get("range", ""))
    t_peak = str(temp_data.get("peak", ""))

    std_data = article_data.get("standard_data") or {}
    standard = str(std_data.get("standard", ""))
    std_class = str(std_data.get("class", ""))
    standard_desc = str(std_data.get("description", ""))

    fingerprint_data = {
        "title": title,
        "content": content,
        "excerpt": excerpt,
        "tools": tools,
        "faq": faq,
        "sources": sources,
        "alloy": alloy,
        "t_kind": t_kind,
        "t_range": t_range,
        "t_peak": t_peak,
        "standard": standard,
        "std_class": std_class,
        "standard_desc": standard_desc,
    }
    canonical_json = json.dumps(fingerprint_data, ensure_ascii=False, separators=(',', ':'))
    return hashlib.sha256(canonical_json.encode('utf-8')).hexdigest()


def compute_server_post_fingerprint(post: dict) -> str:
    """Calculates canonical fingerprint from WordPress post dictionary retrieved via REST."""
    meta = post.get("meta", {})
    title = str(post.get("title", {}).get("raw", post.get("title", {}).get("rendered", ""))).strip()
    content = str(post.get("content", {}).get("raw", post.get("content", {}).get("rendered", ""))).strip()
    excerpt = str(post.get("excerpt", {}).get("raw", post.get("excerpt", {}).get("rendered", ""))).strip()

    tools = normalize_canonical_tools(meta.get("tchp_tools", []))
    faq = normalize_canonical_faq(meta.get("tchp_faq_items", []))
    sources = normalize_canonical_sources(meta.get("tchp_sources", []))

    fingerprint_data = {
        "title": title,
        "content": content,
        "excerpt": excerpt,
        "tools": tools,
        "faq": faq,
        "sources": sources,
        "alloy": normalize_alloy(meta.get("tchp_solder_alloy", "")),
        "t_kind": str(meta.get("tchp_temp_kind", "")),
        "t_range": str(meta.get("tchp_temp_range", "")),
        "t_peak": str(meta.get("tchp_temp_peak", "")),
        "standard": str(meta.get("tchp_standard", "")),
        "std_class": str(meta.get("tchp_standard_class", "")),
        "standard_desc": str(meta.get("tchp_standard_description", "")),
    }
    canonical_json = json.dumps(fingerprint_data, ensure_ascii=False, separators=(',', ':'))
    return hashlib.sha256(canonical_json.encode('utf-8')).hexdigest()


class WordPressRestClient:
    def __init__(self, base_url: str, username: str, app_password: str):
        self.base_url = base_url.rstrip('/')
        self.username = username
        self.app_password = app_password.replace(' ', '')

    def _get_auth_header(self) -> str:
        creds = f"{self.username}:{self.app_password}"
        b64 = base64.b64encode(creds.encode('utf-8')).decode('ascii')
        return f"Basic {b64}"

    def request(self, method: str, path: str, data: dict = None, params: dict = None) -> Tuple[int, Any]:
        url = f"{self.base_url}/{path.lstrip('/')}"
        if params:
            query = urllib.parse.urlencode(params)
            url = f"{url}?{query}"

        headers = {
            "Authorization": self._get_auth_header(),
            "Content-Type": "application/json",
            "Accept": "application/json",
            "User-Agent": "TCHP-Delivery-Engine/1.0"
        }

        body = None
        if data is not None:
            body = json.dumps(data, ensure_ascii=False).encode('utf-8')

        req = urllib.request.Request(url, data=body, headers=headers, method=method)
        try:
            with urllib.request.urlopen(req, timeout=30) as resp:
                status_code = resp.status
                raw = resp.read().decode('utf-8')
                res_data = json.loads(raw) if raw.strip() else {}
                return status_code, res_data
        except urllib.error.HTTPError as e:
            err_raw = e.read().decode('utf-8', errors='replace')
            try:
                err_data = json.loads(err_raw)
            except Exception:
                err_data = {"raw": err_raw}
            return e.code, err_data
        except Exception as e:
            return 0, {"error": str(e)}

    def resolve_category(self, slug: str) -> Optional[int]:
        code, data = self.request("GET", f"/wp-json/wp/v2/categories", params={"slug": slug})
        if code == 200 and isinstance(data, list) and len(data) > 0:
            return data[0].get("id")
        return None

    def resolve_tag(self, tag_name: str) -> Tuple[Optional[int], Optional[str]]:
        """
        Resolves tag by name or creates it if not found.
        Returns (tag_id, error_message).
        Halts immediately on network/server errors to avoid blind creation.
        """
        code, data = self.request("GET", f"/wp-json/wp/v2/tags", params={"search": tag_name})
        if code != 200:
            return None, f"Tag search failed with HTTP {code}: {data}"
        if isinstance(data, list):
            for t in data:
                if t.get("name", "").lower() == tag_name.lower():
                    return t.get("id"), None

        # Definitively not found: create tag
        code_create, res_create = self.request("POST", "/wp-json/wp/v2/tags", data={"name": tag_name})
        if code_create in (200, 201) and isinstance(res_create, dict):
            return res_create.get("id"), None
        return None, f"Tag creation failed with HTTP {code_create}: {res_create}"


@contextlib.contextmanager
def file_lock(lock_path: Path):
    """File locking mechanism to guarantee single-writer safety."""
    lock_path.parent.mkdir(parents=True, exist_ok=True)
    f = open(lock_path, "w")
    try:
        if msvcrt:
            msvcrt.locking(f.fileno(), msvcrt.LK_NBLCK, 1)
        elif fcntl:
            fcntl.flock(f.fileno(), fcntl.LOCK_EX | fcntl.LOCK_NB)
        yield
    except (BlockingIOError, OSError) as e:
        raise RuntimeError(f"Concurrent delivery in progress: lock file busy at {lock_path}") from e
    finally:
        try:
            if msvcrt:
                f.seek(0)
                msvcrt.locking(f.fileno(), msvcrt.LK_UNLCK, 1)
            elif fcntl:
                fcntl.flock(f.fileno(), fcntl.LOCK_UN)
        except Exception:
            pass
        f.close()


class DeliveryJournal:
    JOURNAL_VERSION = "2.0"

    def __init__(self, log_path: Path):
        self.log_path = log_path
        self.lock_path = log_path.with_suffix(".lock")
        self.data = self._load()

    def _load(self) -> dict:
        if self.log_path.exists():
            try:
                with open(self.log_path, "r", encoding="utf-8") as f:
                    content = f.read().strip()
                    if not content:
                        raise RuntimeError(
                            f"CRITICAL: Delivery journal at '{self.log_path}' is 0 bytes (empty/truncated). "
                            "Refusing to reset to avoid duplicate deliveries or data loss."
                        )
                    data = json.loads(content)
                    if not isinstance(data, dict):
                        raise ValueError("Journal root must be a JSON object")
                    if "version" not in data:
                        data["version"] = self.JOURNAL_VERSION
                    if "sites" not in data:
                        data["sites"] = {}
                    return data
            except Exception as e:
                # Corrupted or truncated journal must NEVER be silently treated as empty!
                raise RuntimeError(
                    f"CRITICAL: Delivery journal at '{self.log_path}' is corrupted or unparseable: {e}. "
                    "Halting to avoid duplicate deliveries or data loss."
                ) from e
        return {"version": self.JOURNAL_VERSION, "sites": {}}

    def save(self):
        """Atomic write to prevent partially-written journal files."""
        self.log_path.parent.mkdir(parents=True, exist_ok=True)
        tmp_path = self.log_path.with_suffix(".tmp")
        self.data["version"] = self.JOURNAL_VERSION
        with open(tmp_path, "w", encoding="utf-8") as f:
            json.dump(self.data, f, ensure_ascii=False, indent=2)
        os.replace(tmp_path, self.log_path)

    def _site_key(self, site_url: str) -> str:
        return site_url.rstrip('/').lower()

    def get_entry(self, site_url: str, content_id: str) -> Optional[dict]:
        sk = self._site_key(site_url)
        return self.data.get("sites", {}).get(sk, {}).get(content_id)

    def set_entry(self, site_url: str, content_id: str, entry: dict):
        sk = self._site_key(site_url)
        if "sites" not in self.data:
            self.data["sites"] = {}
        if sk not in self.data["sites"]:
            self.data["sites"][sk] = {}
        self.data["sites"][sk][content_id] = entry
        self.save()


def validate_contract_schema(article_data: dict, schema_path: Path):
    if not schema_path.exists():
        raise FileNotFoundError(f"Schema not found at {schema_path}")
    with open(schema_path, "r", encoding="utf-8") as f:
        schema = json.load(f)
    jsonschema.validate(instance=article_data, schema=schema)


def build_wp_payload(article_data: dict, client: WordPressRestClient, status: str = "draft") -> dict:
    temp_data = article_data.get("temp_data") or {}
    std_data = article_data.get("standard_data") or {}

    meta = {
        "tchp_content_id": article_data["content_id"],
        "tchp_schema_version": article_data.get("schema_version", "1.0"),
        "tchp_reading_time": int(article_data.get("reading_time", 5)),
        "tchp_difficulty": article_data.get("difficulty", "Инженер"),
        "tchp_solder_alloy": normalize_alloy(article_data["solder_alloy"]),
        "tchp_temp_kind": temp_data.get("kind", "joint_temperature"),
        "tchp_temp_range": temp_data.get("range", ""),
        "tchp_temp_peak": temp_data.get("peak", ""),
        "tchp_standard": std_data.get("standard", ""),
        "tchp_standard_class": std_data.get("class", ""),
        "tchp_standard_description": std_data.get("description", ""),
        "tchp_tools": normalize_canonical_tools(article_data.get("tools", [])),
        "tchp_faq_items": normalize_canonical_faq(article_data.get("faq", [])),
        "tchp_sources": normalize_canonical_sources(article_data.get("sources", [])),
    }

    payload = {
        "title": article_data["title"],
        "content": article_data["content_html"],
        "excerpt": article_data["excerpt"],
        "slug": article_data["slug"],
        "status": status,
        "meta": meta
    }

    # Strict category resolution: must resolve or halt
    cat_slug = article_data.get("category_slug")
    if cat_slug:
        cat_id = client.resolve_category(cat_slug)
        if not cat_id:
            raise RuntimeError(
                f"Category slug '{cat_slug}' could not be resolved in WordPress (not found or API error). "
                "Halting delivery to prevent unassigned category."
            )
        payload["categories"] = [cat_id]

    # Strict tag resolution: must resolve/create without network/server failure
    tags = article_data.get("tags")
    if isinstance(tags, list) and tags:
        tag_ids = []
        for tag_name in tags:
            tid, err = client.resolve_tag(str(tag_name))
            if not tid:
                raise RuntimeError(
                    f"Tag '{tag_name}' could not be resolved or created in WordPress: {err}. "
                    "Halting delivery."
                )
            tag_ids.append(tid)
        if tag_ids:
            payload["tags"] = tag_ids

    return payload


def verify_read_back(sent_payload: dict, stored_post: dict) -> Tuple[bool, List[str]]:
    """
    Performs full deep comparison of stored WordPress data against payload.
    Checks: ID, status, slug, title, raw content, excerpt, categories, tags,
    scalar metadata, and array metadata (tools, FAQ, sources).
    """
    mismatches = []
    meta = stored_post.get("meta", {})
    sent_meta = sent_payload.get("meta", {})

    # 1. Post ID check (if sent)
    if "id" in sent_payload and stored_post.get("id") != sent_payload["id"]:
        mismatches.append(f"id: expected {sent_payload['id']}, got {stored_post.get('id')}")

    # 2. Status check (strictly draft or pending, matching requested status)
    stored_status = stored_post.get("status", "")
    expected_status = sent_payload.get("status", "draft")
    if stored_status != expected_status:
        mismatches.append(f"status: expected '{expected_status}', got '{stored_status}'")

    # 3. Slug check
    expected_slug = sent_payload.get("slug", "")
    if expected_slug:
        stored_slug = stored_post.get("slug", "")
        if stored_slug != expected_slug:
            mismatches.append(f"slug: expected '{expected_slug}', got '{stored_slug}'")

    # 4. Title check
    stored_title = stored_post.get("title", {}).get("raw", stored_post.get("title", {}).get("rendered", ""))
    if stored_title.strip() != sent_payload.get("title", "").strip():
        mismatches.append(f"title: expected '{sent_payload.get('title')}', got '{stored_title}'")

    # 5. Content check (raw content preserved, ignoring trailing whitespace differences)
    stored_content = stored_post.get("content", {}).get("raw", "")
    if not stored_content:
        stored_content = stored_post.get("content", {}).get("rendered", "")
    expected_content = sent_payload.get("content", "")
    if stored_content.strip() != expected_content.strip():
        mismatches.append(
            f"content: content mismatch (length expected {len(expected_content.strip())}, got {len(stored_content.strip())})"
        )

    # 6. Excerpt check
    stored_excerpt = stored_post.get("excerpt", {}).get("raw", stored_post.get("excerpt", {}).get("rendered", ""))
    if stored_excerpt.strip() != sent_payload.get("excerpt", "").strip():
        mismatches.append(f"excerpt: mismatch (expected '{sent_payload.get('excerpt')}', got '{stored_excerpt}')")

    # 7. Categories check (set equality of term IDs)
    if "categories" in sent_payload:
        expected_cats = set(sent_payload["categories"])
        stored_cats = set(stored_post.get("categories", []))
        if expected_cats != stored_cats:
            mismatches.append(f"categories: expected {expected_cats}, got {stored_cats}")

    # 8. Tags check (set equality of term IDs)
    if "tags" in sent_payload:
        expected_tags = set(sent_payload["tags"])
        stored_tags = set(stored_post.get("tags", []))
        if expected_tags != stored_tags:
            mismatches.append(f"tags: expected {expected_tags}, got {stored_tags}")

    # 9. Meta scalar comparisons
    meta_keys = [
        "tchp_content_id", "tchp_schema_version", "tchp_reading_time",
        "tchp_difficulty", "tchp_solder_alloy", "tchp_temp_kind",
        "tchp_temp_range", "tchp_temp_peak", "tchp_standard",
        "tchp_standard_class", "tchp_standard_description"
    ]
    for k in meta_keys:
        expected = str(sent_meta.get(k, ""))
        actual = str(meta.get(k, ""))
        if expected != actual:
            mismatches.append(f"meta.{k}: expected '{expected}', got '{actual}'")

    # 10. Meta array comparisons (canonical deep normalized equality)
    norm_expected_tools = normalize_canonical_tools(sent_meta.get("tchp_tools", []))
    norm_stored_tools = normalize_canonical_tools(meta.get("tchp_tools", []))
    if norm_expected_tools != norm_stored_tools:
        mismatches.append(f"meta.tchp_tools: content mismatch")

    norm_expected_faq = normalize_canonical_faq(sent_meta.get("tchp_faq_items", []))
    norm_stored_faq = normalize_canonical_faq(meta.get("tchp_faq_items", []))
    if norm_expected_faq != norm_stored_faq:
        mismatches.append(f"meta.tchp_faq_items: content mismatch")

    norm_expected_sources = normalize_canonical_sources(sent_meta.get("tchp_sources", []))
    norm_stored_sources = normalize_canonical_sources(meta.get("tchp_sources", []))
    if norm_expected_sources != norm_stored_sources:
        mismatches.append(f"meta.tchp_sources: content mismatch")

    return len(mismatches) == 0, mismatches


def send_draft_command(args) -> int:
    article_path = Path(args.file)
    if not article_path.exists():
        print(f"❌ Error: File not found: {article_path}", file=sys.stderr)
        return 1

    try:
        with open(article_path, "r", encoding="utf-8") as f:
            article_data = json.load(f)
    except Exception as e:
        print(f"❌ Error: Could not read JSON: {e}", file=sys.stderr)
        return 1

    # 1. Validate against strict schema
    schema_path = ROOT_DIR / "schemas" / "article-contract-v1.schema.json"
    print(f"🔍 Validating article against schema: {schema_path.name}...")
    try:
        validate_contract_schema(article_data, schema_path)
        print("  [PASS] Article strictly conforms to article-contract-v1.schema.json")
    except jsonschema.ValidationError as e:
        print(f"❌ Schema Validation Error: {e.message}", file=sys.stderr)
        print(f"   At path: {'/'.join(str(p) for p in e.path)}", file=sys.stderr)
        return 1
    except Exception as e:
        print(f"❌ Validation Setup Error: {e}", file=sys.stderr)
        return 1

    content_id = article_data["content_id"]
    content_hash = compute_canonical_fingerprint(article_data)

    client = WordPressRestClient(args.wp_url, args.wp_user, args.wp_pass)
    journal_path = Path(args.journal)

    # 2. Acquire lock & inspect journal
    try:
        with file_lock(journal_path.with_suffix(".lock")):
            journal = DeliveryJournal(journal_path)
            existing_entry = journal.get_entry(args.wp_url, content_id)

            target_post_id = None
            if existing_entry:
                last_status = existing_entry.get("status")
                target_post_id = existing_entry.get("wp_post_id")

                # Handle indeterminate prior states
                if last_status in ("sending", "outcome_unknown"):
                    print(
                        f"⛔ INDETERMINATE STATE DETECTED: Content '{content_id}' was left in '{last_status}' state.\n"
                        f"   Target post ID: #{target_post_id}. Refusing blind re-send to avoid duplicates.\n"
                        f"   Inspect WordPress posts manually and update {journal_path} before re-trying.",
                        file=sys.stderr
                    )
                    return 5

                print(f"ℹ️ Found previous delivery record for '{content_id}': WordPress Post #{target_post_id}")

                # Check existing post status in WordPress
                status_code, post_resp = client.request("GET", f"/wp-json/wp/v2/posts/{target_post_id}?context=edit")
                if status_code == 200:
                    current_status = post_resp.get("status")
                    if current_status not in ALLOWED_UPDATE_STATUSES:
                        print(
                            f"⛔ PROTECTION CONFLICT: Post #{target_post_id} has status '{current_status}' (not a draft)!\n"
                            "   Overwriting published or non-draft posts via automated delivery is strictly forbidden.\n"
                            "   Manual editorial decision required.",
                            file=sys.stderr
                        )
                        return 3

                    # Check external content_id match
                    server_cid = post_resp.get("meta", {}).get("tchp_content_id")
                    if server_cid and server_cid != content_id:
                        print(
                            f"⛔ CONTENT_ID CONFLICT: Post #{target_post_id} has tchp_content_id '{server_cid}', expected '{content_id}'!\n"
                            "   ID collision detected. Aborting delivery.",
                            file=sys.stderr
                        )
                        return 3

                    # Detect human editor review
                    server_review_status = post_resp.get("meta", {}).get("tchp_review_status")
                    server_reviewer_id = post_resp.get("meta", {}).get("tchp_reviewer_id")
                    if server_review_status == "verified" or server_reviewer_id:
                        print(
                            f"⛔ REVIEW CONFLICT: Post #{target_post_id} has been verified by editor (reviewer #{server_reviewer_id})!\n"
                            "   Overwriting verified content via automation is forbidden. Revoke review in WP-admin first.",
                            file=sys.stderr
                        )
                        return 3

                    # Detect manual content modifications
                    current_server_hash = compute_server_post_fingerprint(post_resp)
                    last_recorded_hash = existing_entry.get("content_hash")
                    if last_recorded_hash and current_server_hash != last_recorded_hash:
                        print(
                            f"⛔ MANUAL EDIT CONFLICT: Post #{target_post_id} was modified in WordPress after last delivery!\n"
                            f"   Server hash: {current_server_hash[:16]}... != Recorded hash: {last_recorded_hash[:16]}...\n"
                            "   Aborting delivery to protect manual edits.",
                            file=sys.stderr
                        )
                        return 3

                elif status_code == 404:
                    print(f"⚠️ Post #{target_post_id} no longer exists on WordPress (404). Will create a fresh draft.")
                    target_post_id = None
                else:
                    # Halt on network/server errors during pre-check
                    print(
                        f"❌ Error: Pre-delivery GET /posts/{target_post_id} failed with HTTP {status_code}:\n"
                        f"{json.dumps(post_resp, ensure_ascii=False, indent=2)}\n"
                        "   Halting to avoid duplicate drafts or desynchronization.",
                        file=sys.stderr
                    )
                    return 2

            # 3. Build payload with resolved taxonomies
            wp_payload = build_wp_payload(article_data, client, status=args.status)

            # 4. Record 'sending' in journal BEFORE network request
            journal.set_entry(
                site_url=args.wp_url,
                content_id=content_id,
                entry={
                    "wp_post_id": target_post_id,
                    "status": "sending",
                    "content_hash": content_hash,
                    "started_at": datetime.now(timezone.utc).isoformat(),
                    "title": article_data.get("title")
                }
            )

            # 5. Dispatch request
            if target_post_id:
                print(f"🔄 Updating existing draft Post #{target_post_id}...")
                status_code, resp = client.request("POST", f"/wp-json/wp/v2/posts/{target_post_id}", data=wp_payload)
            else:
                print(f"✨ Creating new draft post for '{content_id}'...")
                status_code, resp = client.request("POST", "/wp-json/wp/v2/posts", data=wp_payload)

            # Indeterminate outcome on connection loss or HTTP 5xx codes
            if status_code in (0, 500, 502, 503, 504):
                journal.set_entry(
                    site_url=args.wp_url,
                    content_id=content_id,
                    entry={
                        "wp_post_id": target_post_id,
                        "status": "outcome_unknown",
                        "content_hash": content_hash,
                        "http_status": status_code,
                        "error": str(resp),
                        "failed_at": datetime.now(timezone.utc).isoformat()
                    }
                )
                print(
                    f"⛔ INDETERMINATE NETWORK/SERVER OUTCOME (HTTP {status_code}):\n"
                    f"   The post may have been created or modified in MySQL before the connection dropped.\n"
                    f"   Recorded 'outcome_unknown' in journal to prevent duplicate re-sends.\n"
                    f"   Inspect WordPress and update journal before retrying.",
                    file=sys.stderr
                )
                return 2

            if status_code not in (200, 201):
                journal.set_entry(
                    site_url=args.wp_url,
                    content_id=content_id,
                    entry={
                        "wp_post_id": target_post_id,
                        "status": "failed",
                        "content_hash": content_hash,
                        "http_status": status_code,
                        "error": str(resp),
                        "failed_at": datetime.now(timezone.utc).isoformat()
                    }
                )
                print(f"❌ WordPress REST Error (HTTP {status_code}):\n{json.dumps(resp, ensure_ascii=False, indent=2)}", file=sys.stderr)
                return 2

            created_id = resp.get("id")
            wp_payload["id"] = created_id

            # IMMEDIATELY record created_id in journal prior to read-back verification!
            journal.set_entry(
                site_url=args.wp_url,
                content_id=content_id,
                entry={
                    "wp_post_id": created_id,
                    "status": "delivered_unconfirmed",
                    "content_hash": content_hash,
                    "title": article_data.get("title"),
                    "delivered_at": datetime.now(timezone.utc).isoformat()
                }
            )

            print(f"✅ Draft saved successfully! WordPress Post #{created_id}, Status: {resp.get('status')}")

            # 6. Read-after-write verification
            print("🔍 Performing strict Read-after-Write verification...")
            v_code, v_post = client.request("GET", f"/wp-json/wp/v2/posts/{created_id}?context=edit")
            if v_code != 200:
                print(f"❌ Critical Error: Could not re-read post #{created_id} (HTTP {v_code})", file=sys.stderr)
                journal.set_entry(
                    site_url=args.wp_url,
                    content_id=content_id,
                    entry={
                        "wp_post_id": created_id,
                        "status": args.status,
                        "read_back_status": "unconfirmed",
                        "content_hash": content_hash,
                        "error": f"Read-back failed with HTTP {v_code}",
                        "delivered_at": datetime.now(timezone.utc).isoformat()
                    }
                )
                return 4

            matches, mismatches = verify_read_back(wp_payload, v_post)
            if not matches:
                print(f"❌ Read-back verification FAILED with {len(mismatches)} mismatches:", file=sys.stderr)
                for m in mismatches:
                    print(f"   - {m}", file=sys.stderr)
                journal.set_entry(
                    site_url=args.wp_url,
                    content_id=content_id,
                    entry={
                        "wp_post_id": created_id,
                        "status": args.status,
                        "read_back_status": "unconfirmed",
                        "mismatches": mismatches,
                        "content_hash": content_hash,
                        "delivered_at": datetime.now(timezone.utc).isoformat()
                    }
                )
                return 4

            print("  [PASS] Stored post ID, status and slug match expected.")
            print("  [PASS] Stored title and raw content match expected.")
            print("  [PASS] Stored categories and tags match expected.")
            print("  [PASS] Stored alloy, temperatures and standards match expected.")
            print("  [PASS] Stored tools, FAQ and sources match expected.")
            print("  [PASS] Read-after-write deep equality 100% verified.")

            # 7. Record confirmed delivery in journal
            journal.set_entry(
                site_url=args.wp_url,
                content_id=content_id,
                entry={
                    "wp_post_id": created_id,
                    "status": args.status,
                    "read_back_status": "verified",
                    "content_hash": content_hash,
                    "title": article_data.get("title"),
                    "delivered_at": datetime.now(timezone.utc).isoformat()
                }
            )
            print(f"📝 Journal confirmed and locked: {journal_path}")
            return 0

    except RuntimeError as e:
        print(f"❌ Runtime Error: {e}", file=sys.stderr)
        return 1


def main():
    parser = argparse.ArgumentParser(description="TCHP WordPress Safe Delivery CLI (Package Б)")
    subparsers = parser.add_subparsers(dest="command")

    # Command: send-draft
    send_parser = subparsers.add_parser("send-draft", help="Deliver validated article JSON into WordPress as draft")
    send_parser.add_argument("--file", required=True, help="Path to article JSON file")
    send_parser.add_argument("--wp-url", default=os.environ.get("WP_URL", "http://localhost"), help="WordPress base URL")
    send_parser.add_argument("--wp-user", default=os.environ.get("WP_USER", "automation"), help="WordPress username")
    send_parser.add_argument("--wp-pass", default=os.environ.get("WP_PASS", ""), help="WordPress application password")
    send_parser.add_argument(
        "--status",
        default="draft",
        choices=["draft", "pending"],
        help="Target WordPress post status (draft or pending only)"
    )
    send_parser.add_argument("--journal", default=str(ROOT_DIR / "delivery-journal.json"), help="Path to delivery journal JSON")

    args = parser.parse_args()
    if args.command == "send-draft":
        code = send_draft_command(args)
        sys.exit(code)
    else:
        parser.print_help()
        sys.exit(1)


if __name__ == "__main__":
    main()
