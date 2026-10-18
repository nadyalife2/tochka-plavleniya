#!/usr/bin/env python3
"""
Build Partner (Self-Improve System) CLI Helper
Analyzes conversation logs, transcripts, and project knowledge base
to extract patterns, corrections, and synthesize ready-to-use Antigravity/ClaudeKit skills.
"""

import sys
import os
import json
import re
import argparse
from pathlib import Path

# Ensure UTF-8 output on Windows consoles
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")
if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding="utf-8")

def parse_transcript(transcript_path: Path):
    """Parses transcript.jsonl and extracts user inputs, verdicts, corrections, and tool calls."""
    if not transcript_path.exists():
        print(f"Error: Transcript file not found at {transcript_path}", file=sys.stderr)
        return []

    entries = []
    with open(transcript_path, "r", encoding="utf-8", errors="replace") as f:
        for line in f:
            line = line.strip()
            if not line:
                continue
            try:
                data = json.loads(line)
                entries.append(data)
            except Exception:
                continue
    return entries

def extract_insights_from_entries(entries):
    """Finds user corrections, verdicts, recurring friction points, and constraints."""
    user_inputs = []
    verdicts = []
    corrections = []
    recurring_topics = {}

    correction_keywords = [
        "вердикт", "ошибка", "исправить", "пробел", "p1", "p2",
        "не принимаю", "замечание", "не делай", "обрати внимание",
        "несогласованность", "неправильно", "отказ", "не очищай"
    ]

    for item in entries:
        content = item.get("content", "")
        item_type = item.get("type", "")

        if item_type == "USER_INPUT" or item.get("source") == "USER_EXPLICIT":
            user_inputs.append(content)
            lower_content = content.lower()
            if any(k in lower_content for k in correction_keywords):
                corrections.append(content)
                if "вердикт" in lower_content:
                    verdicts.append(content)

            # Track keywords
            for word in re.findall(r'[а-яa-z]{5,}', lower_content):
                recurring_topics[word] = recurring_topics.get(word, 0) + 1

    return {
        "total_user_messages": len(user_inputs),
        "corrections_found": len(corrections),
        "verdicts_found": len(verdicts),
        "recent_corrections": corrections[-5:] if corrections else [],
        "top_topics": sorted(recurring_topics.items(), key=lambda x: x[1], reverse=True)[:15]
    }

def validate_skill_file(skill_file: Path):
    """Validates that a SKILL.md file has valid YAML frontmatter and required sections."""
    if not skill_file.exists():
        return False, f"File does not exist: {skill_file}"

    content = skill_file.read_text(encoding="utf-8", errors="replace")
    if not content.startswith("---"):
        return False, "Missing opening YAML frontmatter '---'"

    parts = content.split("---", 2)
    if len(parts) < 3:
        return False, "Malformed YAML frontmatter (missing closing '---')"

    frontmatter = parts[1]
    body = parts[2]

    # Check required frontmatter fields
    if "name:" not in frontmatter:
        return False, "Missing 'name:' in frontmatter"
    if "description:" not in frontmatter:
        return False, "Missing 'description:' in frontmatter"

    # Check body structure
    if "# " not in body:
        return False, "Missing primary H1 header in markdown body"
    if "## When to Use" not in body and "## Quick Start" not in body and "## Workflow" not in body:
        return False, "Missing core structural section (e.g. '## When to Use' or '## Workflow')"

    return True, "Skill format is valid and compliant!"

def main():
    parser = argparse.ArgumentParser(description="Build Partner CLI tool for automated skill creation & analysis.")
    subparsers = parser.add_subparsers(dest="command")

    # Command: scan-transcript
    scan_parser = subparsers.add_parser("scan-transcript", help="Scan a transcript.jsonl file for corrections and friction points")
    scan_parser.add_argument("--path", required=True, help="Path to transcript.jsonl")

    # Command: validate-skill
    val_parser = subparsers.add_parser("validate-skill", help="Validate structure and frontmatter of a SKILL.md file")
    val_parser.add_argument("--path", required=True, help="Path to SKILL.md")

    args = parser.parse_args()

    if args.command == "scan-transcript":
        entries = parse_transcript(Path(args.path))
        insights = extract_insights_from_entries(entries)
        print(json.dumps(insights, ensure_ascii=False, indent=2))

    elif args.command == "validate-skill":
        valid, msg = validate_skill_file(Path(args.path))
        if valid:
            print(f"[PASS] {msg}")
            sys.exit(0)
        else:
            print(f"[FAIL] {msg}", file=sys.stderr)
            sys.exit(1)
    else:
        parser.print_help()

if __name__ == "__main__":
    main()
