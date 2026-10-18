---
name: build-partner
description: Auto-create, refine, and synthesize agent skills and workflow rules from conversation history, user corrections, recurring friction points, and project documentation (Improve System / Self-Improve System).
argument-hint: "[improve-system|synthesize-knowledge|validate] [args]"
metadata:
  author: claudekit
  version: "1.0.0"
---

# Build Partner (Self-Improve System)

Build Partner automates continuous self-improvement for AI coding assistants. It scans local conversation transcripts, finds recurring user feedback, corrections, and unhandled edge cases, and synthesizes ready-to-use Antigravity and ClaudeKit skills or rules. It also compiles domain-specific skills directly from project documentation.

## When to Use

- **Self-Improve System (`improve-system`)**:
  - After a multi-round review or when the user provides detailed verdicts, rejections, or corrections.
  - To extract repetitive instructions into a permanent skill or rule so the agent never repeats past mistakes.
  - To discover recurring friction points in your workflow and automate them into checklists and helper scripts.
- **Synthesize Knowledge (`synthesize-knowledge`)**:
  - When you have rich project documentation (`DESIGN.md`, `PRODUCT.md`, `AGENTS.md`, guidelines) and want dedicated, focused agent skills for each subsystem.
  - When onboarding new domains (e.g. solder metallurgy calculations, strict REST API contracts, custom WordPress themes).
- **Validate Skill (`validate`)**:
  - To verify that a new `SKILL.md` conforms to standard YAML frontmatter, naming conventions, and required structural sections.

## Quick Start

**Scan dialogue history for corrections and recurring topics:**
```bash
python .agents/skills/build-partner/scripts/build_partner.py scan-transcript --path "<appDataDir>/brain/<conversation-id>/.system_generated/logs/transcript.jsonl"
```

**Validate a newly created skill:**
```bash
python .agents/skills/build-partner/scripts/build_partner.py validate-skill --path .agents/skills/<skill-name>/SKILL.md
```

---

## Core Workflows

### 1. Improve System (Self-Improvement from Dialogue)

When executing `improve-system`:

1. **Locate and Read the Transcript**:
   - Access `<appDataDir>/brain/<conversation-id>/.system_generated/logs/transcript.jsonl`.
   - Filter for `USER_INPUT` entries containing corrective keywords (`вердикт`, `ошибка`, `исправить`, `пробел`, `P1`, `не принимаю`, `замечание`, `не делай`, `отказ`).
2. **Extract Rule Patterns**:
   - **Negative Constraints**: What the user explicitly forbade (e.g., *"Never commit without plan approval"*, *"Never clear existing metadata on partial updates"*, *"Do not replace sketch aesthetic with generic SaaS UI"*).
   - **Positive Standards**: Exact contracts or formulas requested (e.g., *"REST schema must enforce minLength: 1 and pattern: \S"*, *"Always return 409 Conflict if expected_hash differs"*, *"Two-pass DOM anchor uniqueness"*).
   - **Recurring Checklist**: Steps that the reviewer checked every time (e.g., hash matching, error codes, DOM anchors, schema ownership).
3. **Generate the Target Customization**:
   - If the lesson applies to all agent tasks in the repository: synthesize into `AGENTS.md` or a workspace rule under `.agents/rules/`.
   - If the lesson represents a distinct capability or workflow: create `.agents/skills/<new-skill>/SKILL.md` complete with documentation and any needed helper scripts.
4. **Validate**:
   - Run `build_partner.py validate-skill --path <new-skill-path>`.

### 2. Synthesize Skills from Project Knowledge Base

When executing `synthesize-knowledge`:

1. **Identify Source Documents**:
   - Scan workspace documentation (e.g. `DESIGN.md`, `PRODUCT.md`, `README.md`, `AGENTS.md`, `регламент.md`).
2. **Map Knowledge Domains**:
   - Identify coherent functional areas that benefit from specialized skills (e.g. `tchp-audit-gatekeeper`, `tchp-article-formatter`, `tchp-rest-client`).
3. **Formulate Skill Specifications**:
   - Define exact triggering conditions (`When to Use`).
   - Define canonical data structures, constants, and enums (e.g. alloy slugs: `pos61`, `sn63pb37`, `pos40`, `sac305`, `sac405`, `sn42bi58`, `snbiago`, `roze`, `vuda`, `universal`).
   - Define operational commands and verification procedures.
4. **Deploy and Test**:
   - Write `.agents/skills/<skill-name>/SKILL.md`.
   - Add reference templates or verification scripts under `.agents/skills/<skill-name>/scripts/` or `resources/`.

---

## Subcommands & Actions

| Command / Action | Description | Output |
| :--- | :--- | :--- |
| `improve-system` | Scans conversation logs, extracts user corrections, and writes a new skill or rule. | `.agents/skills/<name>/SKILL.md` or `.agents/rules/` |
| `synthesize-knowledge` | Compiles documentation into domain-specific agent skills. | New domain skill directory with complete references. |
| `validate-skill` | Checks YAML frontmatter, headers, and section structure of a skill. | `[PASS]` or `[FAIL]` message with actionable errors. |

---

## Standard Skill Template

When Build Partner creates a new skill, it strictly adheres to this structure:

```markdown
---
name: <kebab-case-skill-name>
description: <concise summary of what the skill does and when to activate it>
argument-hint: "[command] [arguments]"
metadata:
  author: claudekit
  version: "1.0.0"
---

# <Title>

<High-level summary of the skill's purpose and authority>

## When to Use

- <Trigger 1>
- <Trigger 2>

## Quick Start

```bash
<Primary command or script execution>
```

## Workflow

1. <Step 1>
2. <Step 2>
3. <Step 3>

## Rules & Invariants

- <Strict rule 1>
- <Strict rule 2>
```

---

## Best Practices

- **Never Lose Critical Feedback**: Every explicit user rejection or P1 remark in a verdict is gold — ensure the synthesized rule directly addresses the root cause.
- **Preserve Project Identity**: Always respect existing project invariants (`DESIGN.md`, sketch/paper aesthetic, no premature commits, 5-point verification formula).
- **Keep Skills Modular**: Prefer focused, specialized skills over monolithic manuals.
