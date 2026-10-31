# SlopMonster

![The five SlopMonster mascots, one for each scoring category](docs/img/hero.png)

SlopMonster checks English writing for common AI writing patterns and provides
instructions for editing the draft. Use it on a README, a landing page, an email or
a script.

The Python checker reports matches in five categories and gives the text a score
out of 5. The editing workflow asks an AI agent to review the draft for its intended
reader, revise it, and request another pass from a different model family.

A score of 5/5 means the checker found no scored matches. You still need to review
whether the text makes sense, supports its claims and gives the reader what they
came for. The previous version of this README passed while using repetitive
metaphors and vague contrasts. That exposed a gap in the editing guidance.

## Quick start

The checker uses Python 3 and its standard library. It needs no API key or packages
to install.

```bash
git clone https://github.com/ItsssssJack/SlopMonster
cd SlopMonster

# Check a short draft.
python tools/deslop.py --text "It's not just a tool, it's a game-changing journey."
# Scores 3/5 and exits with status 1.

# Check a Markdown file.
python tools/deslop.py README.md

# Check text extracted from an HTML file.
python tools/deslop.py index.html

# Usage summary and options.
python tools/deslop.py -h
```

The examples call `python`. Use `python3` where that is the only name available. On
Windows, a `python3` in the path can be a Microsoft Store stub that opens the Store
instead of running a program; `python` is the reliable name there.

Each category with a match costs one point, regardless of how many matches it
contains. Status `0` means 5/5, status `1` means below 5/5 or an input the checker
could not read, and status `2` means a usage error (unknown flag, `--view` without
an id or with an id that is missing from the file, no input at all). Empty input
also fails. Checking a file leaves its contents unchanged.

For Markdown, the checker skips fenced code blocks, inline code, images and
struck-through examples. Indented (four-space) code blocks are **not** skipped and
are scored as prose — fence your examples instead. For HTML, it removes tags,
comments and script/style contents. It doesn't render the page, so text hidden by
CSS can still be included.

### Options

| Option | Effect |
|---|---|
| `--allow-proof` | Keep proof warnings but remove their score penalty |
| `--view <id>` | Score only the element with that id in an HTML file |
| `--markdown` | Force Markdown handling for `--text` or a non-`.md` file |
| `-h`, `--help` | Print the usage summary and exit 0 |

With `--text`, put options first: everything after `--text` is copy. A flag placed after it is scored as text, and the run says so on stderr.

## Edit a draft

Give your agent [SKILL.md](SKILL.md) and the draft. Include the intended audience
and where the writing will appear if those are unclear from the text.

1. Run the checker to identify the patterns it recognizes.
2. Review the draft's purpose and order. A README should explain the tool and show
   how to use it. An email should make its reason for writing clear.
3. Rewrite unclear or repetitive passages. Check that each sentence adds useful
   information and that claims match the supplied facts.
4. Request a second editing pass from a different model family, then inspect the
   changes and run the checker again.

The second pass uses [prompts/cleanse.txt](prompts/cleanse.txt). With a supported AI
command-line tool (CLI) installed and authenticated, run:

```bash
# If Claude wrote the draft, use Codex for the second pass.
DESLOP_WRITER=claude tools/cleanse.sh draft.md > cleansed.md

# If GPT wrote the draft, use Claude for the second pass.
DESLOP_WRITER=gpt tools/cleanse.sh draft.md > cleansed.md

# Read the edited file, then check it as Markdown.
python tools/deslop.py cleansed.md
```

Run the command for the family that wrote your draft. The script writes edited
copy to standard output and change notes to standard error. It uses the installed
CLI's configured model. The prompt and the draft are the only input, so the audience
must be inferable from the draft.

An installed CLI is not necessarily a working one: both branches need the CLI to be
authenticated, and a logged-out CLI fails at run time. Check the status before
treating the output as an edit:

| Status | Meaning | Is stdout an edit? |
|---|---|---|
| 0 | The rival CLI answered | yes |
| 1 or another CLI code | The CLI failed; its output is echoed to standard error | **no** |
| 64 | Unknown `DESLOP_WRITER`, or `TIMEOUT` is not a positive integer | no |
| 65 | The CLI returned an empty copy | no |
| 66 | Bad input: unreadable file, empty draft, no draft on a terminal, or prompt+draft above 30 000 bytes (the Windows `argv` limit) | no |
| 124 | Timeout (`TIMEOUT`, default 180 s) | no |
| 127 | No rival-family CLI found; the prompt and draft are printed to standard output | **no** |

If the required CLI is missing, the script prints the editing prompt and draft,
then exits with status 127. Paste that output into the other model family's chat,
and do not save it as a cleansed draft. A warning about a missing notes marker
means the response may include change notes in the copy.

Using another model provides another editing pass. It doesn't guarantee better
writing. Review the result for changed facts and lost context before accepting it.

### Use as an agent skill

For Claude Code, copy this folder to `~/.claude/skills/slopmonster/` and invoke
`/slopmonster`. For Codex or another agent, ask it to follow [SKILL.md](SKILL.md).

## What the checker flags

The current score has five categories:

| Category | Output heading | What it checks |
|---|---|---|
| Vocabulary | `AI vocabulary` | Listed words such as `delve` and `seamless`, matched by root so inflections count. |
| Sentence patterns | `AI constructions` | Listed constructions such as `not just X, but Y`, stacked hedges and certain self-answering questions. |
| Punctuation | `punctuation cadence` | Two or more em dashes in a 220-character window, four or more stacked hyphenated compounds in a window, or semicolon density above `max(3, length/1200)`. |
| Three-item lists | `rule-of-three rhythm` | Two specific three-item list patterns. |
| Possible unsupported proof | `possible invented proof` | Numbers near nouns such as `users` or `customers`. The checker cannot verify the claim. |

The patterns are in [tools/deslop.py](tools/deslop.py). The broader editing
catalogue is in [references/signs-of-ai-writing.md](references/signs-of-ai-writing.md).
Some entries in that catalogue require editorial judgment and have no automated
check; the catalogue is deliberately wider than the implemented rules.

If a flagged customer count is accurate and you have evidence for it, use:

```bash
python tools/deslop.py index.html --allow-proof
```

This option still reports proof matches but removes their score penalty. It
doesn't check the evidence. In this mode, 5/5 can include proof warnings.

## Review beyond the score

Read the draft in the context where someone will encounter it. Check these points
before accepting an edit:

- **Audience and purpose:** Does it answer the reader's likely questions at the
  right level of detail? Explain technical terms where they first matter.
- **Opening:** Does the first paragraph explain the subject or establish a useful
  reason to keep reading? Avoid adding a second hook that delays the explanation.
- **Progression:** Does each sentence build on the previous one? Remove repeated
  metaphors and contrasts that add no information.
- **Claims:** Can you support each factual statement? Describe optional behavior
  with its conditions. For example, a command's failure status only stops a
  workflow if that workflow is configured to act on it.
- **Voice:** Does the wording suit the author and format? Contractions are fine
  where natural. Forced fragments and invented opinions can make the edit worse.

See [references/principles.md](references/principles.md) for more editing guidance
and examples. Never add a statistic or testimonial to make a sentence sound
specific. If information is missing, flag it for the author.

### Limits

The checker uses English patterns and has no language detection. Text in another
language can score 5/5 because those patterns don't match. The punctuation rules are
script-agnostic and cut the other way: two em dashes in one window is a tell in
English but ordinary typography in Russian, so a correct Russian sentence can score
4/5 for its dashes while Russian AI phrasing passes unnoticed. Treat a non-English
score as a reading prompt, not as a gate.

It can flag ordinary wording and miss poor writing. It cannot determine who wrote
the text, verify facts or judge whether a hook suits the audience. Use its findings
as prompts for review. A passing score alone doesn't establish writing quality.

## Optional CI check

You can run the checker in continuous integration (CI), the automated checks a
repository runs when code changes. Because a score below 5/5 returns a failure
status, a workflow that runs the command can fail on flagged copy.

The upstream repository runs its regression tests and checks this README plus a
sample file (`examples/gate-sample.txt`). This vendored copy does not ship the
workflow file, so wire the same commands into your own CI and set the paths you
want checked:

```bash
python tools/test_deslop.py
bash tools/test_cleanse.sh
python tools/deslop.py README.md
```

Blocking a merge or deployment requires your own repository rules or deployment
configuration. Gate only text in a language whose patterns the checker implements.

## Examples and sources

- [Jasper homepage excerpt](examples/jasper-live-run.md): a recorded editing run
  with the commands and output from that run. Scores can change as rules change.
- [Ridgeline Roofing](examples/ridgeline-roofing.md): a longer worked example with
  before-and-after copy. Check any business facts before reusing a line.
- [Source list](references/sources.md): Wikipedia's *Signs of AI writing* and the
  open-source humanizer projects used to assemble the catalogue. The marketing
  guidance also draws on Steve Krug, Daniel Priestley and Alex Hormozi.

[MIT license](LICENSE).
