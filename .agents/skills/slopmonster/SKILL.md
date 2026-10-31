---
name: slopmonster
description: Review writing for its intended reader, check common AI writing patterns, revise, request a second pass from a different model family, and check again. Trigger on /slopmonster, "humanize this", "de-slop this", "does this sound like AI", "fix this copy".
---

# SlopMonster

Edit the supplied draft for its audience and purpose. The workflow supports
READMEs, landing pages, emails and scripts. Use the checker to locate known
patterns, then review the writing in context. A score of 5/5 means no scored
patterns matched. It does not establish that the copy is clear or accurate.

## Step 1: Establish the context and run the checker

Identify the reader, the format and what the reader needs from the text. Use the
user's brief and the draft itself. Ask a focused question only when missing context
would materially change the edit.

A README should explain what the project does, how to use it and its limitations.
A sales page may need an opening that establishes a problem. An email may need to
start with the request. Choose an approach that fits the document.

```bash
python tools/deslop.py draft.md
python tools/deslop.py page.html
python tools/deslop.py page.html --view view-site     # score one element only
python tools/deslop.py --text "paste a draft"
python tools/deslop.py draft.md --allow-proof
python tools/deslop.py -h
```

The examples call `python`. Substitute `python3` where that is the only name
available. On Windows a `python3` found in the path can be a Microsoft Store stub
that opens the Store instead of running a program.

The checker scores five categories: vocabulary, sentence patterns, punctuation,
three-item lists and possible unsupported proof. Each category with a match costs
one point. Exit status: `0` at 5/5, `1` below 5/5 or when the input cannot be read,
`2` for a usage error (unknown flag, `--view` without an id or with an id that is
not in the file, nothing to read). Options come before `--text`; everything after
it is copy. Empty input also fails.

For Markdown, pass the file path so fenced code, inline code, images and
struck-through specimens are treated as Markdown rather than copy. `--markdown`
forces that mode for other extensions and for `--text`. Indented (four-space) code
blocks are **not** excluded and count as prose — use fenced blocks in documents you
score. HTML mode strips tags, comments and script/style contents. `--view <id>`
scores only the element carrying that id.

The rules are English only and do not detect the input language. Two em dashes in
one 220-character window count as a punctuation match, which fires on languages
where the em dash is ordinary punctuation (Russian, for example). Read that finding
before editing anything.

## Step 2: Review and rewrite

Read [references/principles.md](references/principles.md) for the editorial checks
and [references/signs-of-ai-writing.md](references/signs-of-ai-writing.md) for the
pattern catalogue.

Work through the draft in this order:

1. **Purpose and structure.** Answer the reader's first question early. Remove
   duplicate hooks and curiosity gaps that delay an explanation. Keep each
   paragraph focused on a useful point, with enough context to understand it.
2. **Meaning and evidence.** Check that each sentence adds information and follows
   from what came before. Explain conditions on claims. Remove empty contrasts,
   repeated metaphors and comparisons that do not help the reader. Preserve
   facts, technical behavior and the author's position.
3. **Wording and rhythm.** Review the checker findings and replace vague language
   with direct wording. Read adjacent sentences together for repetitive shapes.
   Let sentence length follow the thought. Keep useful lists and punctuation.

Do not manufacture personality by adding an opinion, a mistake, a fragment or a
fixed quota of short sentences. Do not add a number to every claim. Use only facts
supplied by the author or verified from an appropriate source.

The checker can flag valid language. Review the actual match before editing it.
Never hide ordinary prose in code formatting to raise the score. If a necessary
phrase still triggers a rule, explain the remaining finding to the user.

## Step 3: Request a second editing pass

Use a different model family from the one that wrote the draft. This provides a
second review with the same audience and purpose. Inspect its suggestions before
accepting them.

| Draft written with | Command |
|---|---|
| Claude | `DESLOP_WRITER=claude tools/cleanse.sh draft.md > cleansed.md` |
| GPT / Codex / ChatGPT | `DESLOP_WRITER=gpt tools/cleanse.sh draft.md > cleansed.md` |
| Gemini | `DESLOP_WRITER=gemini tools/cleanse.sh draft.md > cleansed.md` |

`DESLOP_WRITER` names the family that **wrote** the draft. The script picks the
rival (`claude` → codex, `gpt` → claude, `gemini` → codex). An unknown value stops
the run. The script uses the selected CLI's configured model and
[prompts/cleanse.txt](prompts/cleanse.txt). The CLI must be installed **and
authenticated**: an installed but logged-out CLI fails at run time, so a working
rival CLI is not the same as a working second pass. The script sends the prompt and
the draft only, with no brief, so the audience has to be inferable from the draft.

If the CLI is unavailable, use the prompt and the draft in the other model family's
chat. If a second model cannot be accessed, report that the pass was not completed
and continue the editorial review and local checks.

Standard output contains the edited copy. Standard error contains change notes and
the script's own diagnostics. To keep both:

```bash
DESLOP_WRITER=gpt tools/cleanse.sh draft.md > cleansed.md 2> notes.txt
```

Check the exit status before using the file:

| Status | Meaning | Is stdout an edit? |
|---|---|---|
| 0 | The rival CLI answered: copy above the marker, notes below | yes |
| 1 or another CLI code | The CLI failed: what it printed is echoed to stderr | **no** |
| 64 | Unknown `DESLOP_WRITER`, or `TIMEOUT` is not a positive integer | no |
| 65 | The CLI returned an empty copy | no |
| 66 | Bad input: unreadable file, empty draft, no draft on a terminal, or prompt+draft above 30 000 bytes (the Windows `argv` limit) | no |
| 124 | Timeout (`TIMEOUT`, default 180 s) | no |
| 127 | No rival-family CLI found: the prompt and the draft are printed to stdout | **no** |

A reply without the notes marker keeps all output as copy and prints a warning, so
the result still needs reading. The model separates its notes with
`<<<SLOPMONSTER-NOTES>>>`. Inspect the result.

## Step 4: Compare the edit and check again

Compare the result with the source. Check facts and commands for changes, confirm
that links and placeholders survived, and read the opening in context. Review the
notes for deletions that may have removed necessary information.

```bash
python tools/deslop.py cleansed.md
```

Aim to resolve the checker findings while preserving the intended meaning. Report
any justified exceptions. The final editorial review is required even at 5/5.

If you change a regex, run `python tools/test_deslop.py`. If you change the cleanse
script, run `bash tools/test_cleanse.sh`.

## Factual claims

Never invent evidence. This includes customer counts, testimonials, ratings and
results. If the draft needs missing information, flag it for the author with a
placeholder such as `[needs number]`. Do not publish an unresolved placeholder as
a verified claim.

The proof rule only sees number-and-noun patterns. It cannot verify whether a claim
is true. Use `--allow-proof` only after checking the evidence. The option retains
the warning while removing its score penalty.

## Output

Return the rewritten copy or save the edited artifact as requested. Then give up
to five short notes explaining substantive changes and the check results. State
whether the second model pass completed. Keep any unresolved claims or remaining
checker findings visible. Do not claim the edit proves human authorship.
