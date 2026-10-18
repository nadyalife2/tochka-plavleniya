#!/usr/bin/env python3
"""
Master Test Runner for Package A.1 (ТОЧКА ПЛАВЛЕНИЯ)
Executes:
1. JSON Schema validation (article contract & example) + negative tests
2. PHP Lint on plugin, theme, and test suite
3. PHP Integration Test Suite (7 scenarios requested in audit)
4. Distribution packaging & integrity check for theme4.zip and tchp-content.zip
"""

import os
import sys
import json
import shutil
import zipfile
import subprocess
from pathlib import Path

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
    print("\n[STEP 1/4] Validating JSON Schemas & Article Contracts...")
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

def run_php_lint():
    print(f"\n[STEP 2/4] Running PHP Syntax Check using '{PHP_EXE}'...")
    php_files = [
        ROOT_DIR / "wp-content" / "plugins" / "tchp-content" / "tchp-content.php",
        ROOT_DIR / "theme4" / "single.php",
        ROOT_DIR / "theme4" / "includes" / "functions.php",
        ROOT_DIR / "tests" / "test-package-a1.php",
    ]
    for p in php_files:
        res = subprocess.run([PHP_EXE, "-l", str(p)], capture_output=True, text=True)
        assert res.returncode == 0, f"PHP Lint failed on {p.name}:\n{res.stderr}"
        print(f"  [PASS] {p.name} syntax OK.")

def run_php_unit_tests():
    print("\n[STEP 3/4] Running Business Logic & Schema Contract Unit Tests (Mock WP)...")
    test_script = ROOT_DIR / "tests" / "test-package-a1.php"
    res = subprocess.run([PHP_EXE, str(test_script)], capture_output=True, text=True, encoding="utf-8", errors="replace")
    print(res.stdout)
    if res.returncode != 0:
        print(res.stderr, file=sys.stderr)
        sys.exit(res.returncode)
    print("  [PASS] Business Logic & Contract Unit Tests completed with 100% success rate.")

def build_and_verify_archives():
    print("\n[STEP 4/4] Building and Verifying Distribution Packages...")
    build_script = ROOT_DIR / "tools" / "build_distribution_archives.py"
    res = subprocess.run([sys.executable, str(build_script)], capture_output=True, text=True)
    assert res.returncode == 0, f"Build archives failed:\n{res.stderr}"
    print(res.stdout.strip())

    # Verify theme4.zip structure and byte-exact parity with tracked sources
    theme_zip = ROOT_DIR / "theme4.zip"
    assert theme_zip.exists(), "theme4.zip was not created"
    with zipfile.ZipFile(theme_zip, "r") as zf:
        namelist = zf.namelist()
        assert "theme4/style.css" in namelist, "Missing theme4/style.css in theme4.zip"
        assert "theme4/single.php" in namelist, "Missing theme4/single.php in theme4.zip"
        assert "theme4/index.php" in namelist, "Missing theme4/index.php in theme4.zip"

        with open(ROOT_DIR / "theme4" / "single.php", "rb") as f_src:
            assert zf.read("theme4/single.php") == f_src.read(), "Byte mismatch between theme4.zip and theme4/single.php!"
        with open(ROOT_DIR / "theme4" / "style.css", "rb") as f_src:
            assert zf.read("theme4/style.css") == f_src.read(), "Byte mismatch between theme4.zip and theme4/style.css!"
    print(f"  [PASS] theme4.zip verified (byte-exact match with working directory, {theme_zip.stat().st_size} bytes).")

    # Verify tchp-content.zip structure and byte-exact parity with plugin source
    plugin_zip = ROOT_DIR / "tchp-content.zip"
    assert plugin_zip.exists(), "tchp-content.zip was not created"
    with zipfile.ZipFile(plugin_zip, "r") as zf:
        namelist = zf.namelist()
        assert "tchp-content/tchp-content.php" in namelist, "Missing tchp-content/tchp-content.php in tchp-content.zip"

        with open(ROOT_DIR / "wp-content" / "plugins" / "tchp-content" / "tchp-content.php", "rb") as f_src:
            assert zf.read("tchp-content/tchp-content.php") == f_src.read(), "Byte mismatch between tchp-content.zip and plugin source!"
    print(f"  [PASS] tchp-content.zip verified (byte-exact match with plugin source, {plugin_zip.stat().st_size} bytes).")

if __name__ == "__main__":
    run_json_schema_tests()
    run_php_lint()
    run_php_unit_tests()
    build_and_verify_archives()
    print("\n" + "=" * 55)
    print("ALL TESTS & INTEGRITY CHECKS PASSED SUCCESSFULLY!")
    print("=" * 55 + "\n")

