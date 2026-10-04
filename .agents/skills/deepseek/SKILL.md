---
name: deepseek
description: Consult DeepSeek Chat, DeepSeek Reasoner (R1), or DeepSeek Harness directly inside Antigravity for code review, complex algorithmic logic, architecture analysis, and second opinions.
---

# DeepSeek Integration for Antigravity

This skill enables Antigravity to consult DeepSeek AI models (`deepseek-chat`, `deepseek-reasoner` / R1) and DeepSeek Harness (`dsh`) directly during development.

## 1. When to Use DeepSeek

Activate or consult DeepSeek when:
- The user asks to "спросить у DeepSeek", "проверить через DeepSeek", "второе мнение от DeepSeek", or "запустить через deepseek harness".
- Complex algorithmic reasoning or mathematical formulas require verification (DeepSeek-R1 excels at step-by-step mathematical and logical reasoning).
- In-depth security or architectural code review where an independent model's perspective reduces blind spots.
- Autonomous harness delegation via `dsh` (DeepSeek Harness CLI).

## 2. Calling DeepSeek via MCP

The `deepseek` MCP server is registered in `.agents/mcp.json` and global `mcp_config.json`.
It provides tools for:
- Chat completions (`deepseek-chat` / V3)
- Deep reasoning (`deepseek-reasoner` / R1)

Ensure `DEEPSEEK_API_KEY` is set in the environment or `.env` before invoking MCP tools.

## 3. Delegating via DeepSeek Harness CLI (`dsh`)

If the user wants DeepSeek Harness to autonomously examine the workspace or run a task:
```powershell
dsh headless "Проанализируй калькулятор пайки и предложи оптимизацию алгоритмов"
```
Or to run the interactive Web dashboard:
```powershell
dsh web
```

## 4. Best Practices
- Always ground DeepSeek with the current project's constraints (`/DESIGN.md`, `AGENTS.md`).
- Review DeepSeek's suggestions against the local project rules before applying code edits.
