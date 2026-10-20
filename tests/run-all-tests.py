#!/usr/bin/env python3
"""
Master Test Runner for Package A.1 (ТОЧКА ПЛАВЛЕНИЯ)
Executes:
1. JSON Schema validation (article contract & example) + negative tests
2. PHP Lint on all theme, plugin and test PHP files
3. Mock-WP business logic & contract tests (10 scenarios)
4. Python delivery CLI & generator unit tests
5. Relative Markdown link check
6. Distribution packaging
7. Byte-parity verification for theme4.zip and tchp-content.zip
"""

import os
import sys
import json
import shutil
import zipfile
import subprocess
from pathlib import Path
import argparse

ROOT_DIR = Path(__file__).resolve().parent.parent

def get_php_executable():
    # 1. Environment variable override (PHP_BIN or PHP_EXE)
    env_bin = os.environ.get("PHP_BIN") or os.environ.get("PHP_EXE")
    if env_bin and Path(env_bin).exists():
        return env_bin

    # 2. Check system PATH (standard on Linux CI / macOS / configured Windows)
    which_php = shutil.which("php")
    if which_php:
        return which_php

    # 3. Local fallback on Windows portable PHP
    portable_win = Path(r"C:\Users\nadya\php_portable\php.exe")
    if portable_win.exists():
        return str(portable_win)

    # 4. Default fallback
    return "php"

PHP_EXE = get_php_executable()

def run_json_schema_tests():
    import jsonschema
    print("\n[STEP 1/7] Validating JSON Schemas & Article Contracts...")
    schema_file = ROOT_DIR / "schemas" / "article-contract-v1.schema.json"
    example_file = ROOT_DIR / "schemas" / "article-example-v1.json"

    with open(schema_file, "r", encoding="utf-8") as f:
        schema = json.load(f)
    with open(example_file, "r", encoding="utf-8") as f:
        example = json.load(f)

    # 1. Check Draft-07 meta-schema
    jsonschema.Draft7Validator.check_schema(schema)
    print("  [PASS] Meta-schema Draft-07 check passed.")

    # 2. Validate example against contract
    validator = jsonschema.Draft7Validator(schema)
    validator.validate(example)
    print("  [PASS] article-example-v1.json strictly conforms to article-contract-v1.schema.json.")

    # 3. Canonical alloy enum check
    expected_alloys = ["pos61", "sn63pb37", "pos40", "sac305", "sac405", "sn42bi58", "snbiago", "roze", "vuda", "universal"]
    actual_alloys = schema["properties"]["solder_alloy"]["enum"]
    assert actual_alloys == expected_alloys, f"Alloy mismatch: {actual_alloys}"
    print("  [PASS] Canonical alloys enum strictly matches registry.")

    # 4. Negative test: extra unknown field
    bad_extra = dict(example)
    bad_extra["malicious_extra_field"] = "bad"
    try:
        validator.validate(bad_extra)
        raise AssertionError("Failed: extra field was not rejected by schema!")
    except jsonschema.ValidationError:
        print("  [PASS] Negative test: additionalProperties: false successfully rejected extra field.")

    # 5. Negative test: non-canonical alloy 'wood'
    bad_alloy = dict(example)
    bad_alloy["solder_alloy"] = "wood"
    try:
        validator.validate(bad_alloy)
        raise AssertionError("Failed: legacy alloy 'wood' was not rejected by strict schema!")
    except jsonschema.ValidationError:
        print("  [PASS] Negative test: legacy alias 'wood' correctly rejected in strict schema.")

    # 6. Check v1.1 schema and example if present
    schema_v1_1_file = ROOT_DIR / "schemas" / "article-contract-v1.1.schema.json"
    example_v1_1_file = ROOT_DIR / "schemas" / "article-example-v1.1.json"
    if schema_v1_1_file.exists() and example_v1_1_file.exists():
        with open(schema_v1_1_file, "r", encoding="utf-8") as f:
            schema_v1_1 = json.load(f)
        with open(example_v1_1_file, "r", encoding="utf-8") as f:
            example_v1_1 = json.load(f)
        jsonschema.Draft7Validator.check_schema(schema_v1_1)
        validator_v1_1 = jsonschema.Draft7Validator(schema_v1_1)
        validator_v1_1.validate(example_v1_1)
        print("  [PASS] article-example-v1.1.json strictly conforms to article-contract-v1.1.schema.json.")

        # Negative test v1.1: FAQ less than 4 items
        bad_faq = dict(example_v1_1)
        bad_faq["faq"] = example_v1_1["faq"][:2]
        try:
            validator_v1_1.validate(bad_faq)
            raise AssertionError("Failed: FAQ with fewer than 4 items was not rejected by v1.1 schema!")
        except jsonschema.ValidationError:
            print("  [PASS] Negative test v1.1: fewer than 4 FAQ items successfully rejected.")

        # Negative test v1.1: sources missing
        bad_sources = dict(example_v1_1)
        del bad_sources["sources"]
        try:
            validator_v1_1.validate(bad_sources)
            raise AssertionError("Failed: missing sources was not rejected by v1.1 schema!")
        except jsonschema.ValidationError:
            print("  [PASS] Negative test v1.1: missing sources successfully rejected.")

def run_php_lint():
    print(f"\n[STEP 2/7] Running PHP Syntax Check using '{PHP_EXE}'...")
    php_files = []
    for scan_root in (ROOT_DIR / "theme4", ROOT_DIR / "wp-content" / "plugins" / "tchp-content"):
        php_files.extend(sorted(scan_root.rglob("*.php")))
    php_files.append(ROOT_DIR / "tests" / "test-package-a1.php")
    php_files = [p for p in php_files if "__pycache__" not in p.parts]
    print(f"  Scanning {len(php_files)} PHP files...")
    for p in php_files:
        res = subprocess.run([PHP_EXE, "-l", str(p)], capture_output=True, text=True)
        assert res.returncode == 0, f"PHP Lint failed on {p.name}:\n{res.stderr}"
    print(f"  [PASS] PHP syntax OK for all {len(php_files)} files.")

def run_php_unit_tests():
    print("\n[STEP 3/7] Running Business Logic & Schema Contract Unit Tests (Mock WP)...")
    test_script = ROOT_DIR / "tests" / "test-package-a1.php"
    res = subprocess.run([PHP_EXE, str(test_script)], capture_output=True, text=True, encoding="utf-8", errors="replace")
    print(res.stdout)
    if res.returncode != 0:
        print(res.stderr, file=sys.stderr)
        sys.exit(res.returncode)
    print("  [PASS] Business Logic & Contract Unit Tests completed with 100% success rate.")

def run_python_unit_tests():
    print("\n[STEP 4/7] Running Python Delivery CLI & Contract Unit Tests...")
    test_scripts = [
        ROOT_DIR / "tests" / "test_delivery_cli_unit.py",
        ROOT_DIR / "tests" / "test_article_generator.py",
    ]
    for script in test_scripts:
        res = subprocess.run([sys.executable, "-m", "unittest", str(script)], capture_output=True, text=True, encoding="utf-8")
        print(res.stdout)
        if res.returncode != 0:
            print(res.stderr, file=sys.stderr)
            sys.exit(res.returncode)
    print("  [PASS] Python Delivery CLI & Generator Unit Tests passed.")

def run_docs_link_check():
    print("\n[STEP 5/7] Checking relative Markdown links...")
    checker = ROOT_DIR / "tools" / "check_docs_links.py"
    res = subprocess.run([sys.executable, str(checker)], capture_output=True, text=True, encoding="utf-8", errors="replace")
    print(res.stdout.strip())
    if res.returncode != 0:
        print(res.stderr, file=sys.stderr)
        sys.exit(res.returncode)
    print("  [PASS] Documentation links are consistent.")

def verify_distribution_archives():
    print("\n[STEP 7/7] Verifying Distribution Packages Composition & Byte Parity...")

    # 1. Verify theme4.zip full composition and byte-exact parity
    theme_zip = ROOT_DIR / "theme4.zip"
    theme_src = ROOT_DIR / "theme4"
    assert theme_zip.exists(), f"theme4.zip was not found at {theme_zip}"

    with zipfile.ZipFile(theme_zip, "r") as zf:
        zip_files = set(zf.namelist())
        src_files = set()
        for root, dirs, files in os.walk(theme_src):
            dirs[:] = [d for d in dirs if not d.startswith('.') and d != '__pycache__']
            for file in sorted(files):
                if file.startswith('.') and file != '.htaccess':
                    continue
                if file.endswith('.pyc'):
                    continue
                full_path = Path(root) / file
                rel_path = full_path.relative_to(theme_src).as_posix()
                arcname = f"theme4/{rel_path}"
                src_files.add(arcname)

                # Verify byte-exact match for EVERY single file
                with open(full_path, "rb") as f_src:
                    assert zf.read(arcname) == f_src.read(), f"Byte mismatch between theme4.zip and {full_path}!"

        # Ensure no extra or missing files
        assert zip_files == src_files, f"Archive composition mismatch! Extra: {zip_files - src_files}, Missing: {src_files - zip_files}"
        print(f"  [PASS] theme4.zip verified: 100% composition & byte-exact parity ({len(zip_files)} files, {theme_zip.stat().st_size} bytes).")

    # 2. Verify tchp-content.zip full composition and byte-exact parity
    plugin_zip = ROOT_DIR / "tchp-content.zip"
    plugin_src = ROOT_DIR / "wp-content" / "plugins" / "tchp-content"
    assert plugin_zip.exists(), f"tchp-content.zip was not found at {plugin_zip}"

    with zipfile.ZipFile(plugin_zip, "r") as zf:
        zip_files = set(zf.namelist())
        src_files = set()
        for root, dirs, files in os.walk(plugin_src):
            dirs[:] = [d for d in dirs if not d.startswith('.') and d != '__pycache__']
            for file in sorted(files):
                if file.startswith('.') and file != '.htaccess':
                    continue
                if file.endswith('.pyc'):
                    continue
                full_path = Path(root) / file
                rel_path = full_path.relative_to(plugin_src).as_posix()
                arcname = f"tchp-content/{rel_path}"
                src_files.add(arcname)

                # Verify byte-exact match
                with open(full_path, "rb") as f_src:
                    assert zf.read(arcname) == f_src.read(), f"Byte mismatch between tchp-content.zip and {full_path}!"

        assert zip_files == src_files, f"Plugin archive composition mismatch! Extra: {zip_files - src_files}, Missing: {src_files - zip_files}"
        print(f"  [PASS] tchp-content.zip verified: 100% composition & byte-exact parity ({len(zip_files)} files, {plugin_zip.stat().st_size} bytes).")

def build_distribution_archives():
    print("\n[STEP 6/7] Compiling fresh distribution packages...")
    build_script = ROOT_DIR / "tools" / "build_distribution_archives.py"
    res = subprocess.run([sys.executable, str(build_script)], capture_output=True, text=True)
    assert res.returncode == 0, f"Build archives failed:\n{res.stderr}"
    print(res.stdout.strip())

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="TCHP Local Quality Gate & Test Runner")
    parser.add_argument("--verify-only", action="store_true", help="Only verify existing archives without rebuilding them")
    parser.add_argument("--build", action="store_true", help="Explicitly rebuild archives before verification")
    args = parser.parse_args()

    run_json_schema_tests()
    run_php_lint()
    run_php_unit_tests()
    run_python_unit_tests()
    run_docs_link_check()

    if not args.verify_only:
        build_distribution_archives()

    verify_distribution_archives()

    print("\n" + "=" * 55)
    print("ALL TESTS & INTEGRITY CHECKS PASSED SUCCESSFULLY!")
    print("=" * 55 + "\n")

