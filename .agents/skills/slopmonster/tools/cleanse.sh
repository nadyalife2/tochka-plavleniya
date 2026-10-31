#!/usr/bin/env bash
# The model cleanse — a second model strips the tells the first one wrote.
#
# The rule: the cleanse runs on a DIFFERENT model family than the one that wrote
# the draft. A model is bad at hearing its own accent. A rival hears it instantly.
#
#   ./cleanse.sh draft.md                # cleanse a file
#   cat draft.md | ./cleanse.sh          # cleanse stdin
#   TIMEOUT=240 ./cleanse.sh draft.md    # longer bound for a long document
#
# DESLOP_WRITER names the family that WROTE the draft (claude|gpt|gemini,
# default claude). The script picks its rival — never the same family:
#   claude  -> codex  (codex exec --skip-git-repo-check --sandbox read-only)
#   gemini  -> codex
#   gpt     -> claude (claude --tools "" -p)
# If the rival CLI is not installed the prompt + the draft are printed to STDOUT
# and the script exits 127, so you can paste them into the other family's chat.
#
# If you are ALREADY inside Codex/ChatGPT and the draft is GPT's: you do not need
# this script. Paste prompts/cleanse.txt plus your draft into the other family.
#
# Exit codes — fixed contract, the docs quote them:
#   0     success: STDOUT is the cleansed copy, STDERR the change notes
#   1..   the rival CLI failed; its output is replayed on STDERR, result invalid
#   64    unknown DESLOP_WRITER, or TIMEOUT is not a positive integer
#   65    the rival CLI returned an empty/whitespace-only copy
#   66    bad input: unreadable file, empty draft, no draft and a TTY stdin,
#         or prompt+draft too large to pass as a single argv entry
#   124   timed out
#   127   no rival-family CLI (prompt + draft printed on STDOUT)
#
# STDOUT carries prose ONLY, so the documented `cleanse.sh draft.md > cleansed.md`
# writes the copy and nothing else. ALWAYS re-lint the output:
#   python tools/deslop.py --text "$(cat cleansed.md)"
set -uo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
PROMPT_FILE="$HERE/../prompts/cleanse.txt"
TIMEOUT="${TIMEOUT:-180}"

# ── config, validated before any model call or any blocking read ────────────
# DESLOP_WRITER is a family name. Anything else used to fall through to the
# "neither CLI" branch and look like a missing binary; 64 says what is wrong.
WRITER="${DESLOP_WRITER:-claude}"
case "$WRITER" in
  claude|gpt|gemini) ;;
  *)
    echo "cleanse: unknown DESLOP_WRITER='$WRITER' (expected claude|gpt|gemini)" >&2
    exit 64 ;;
esac

# `[ "$WAITED" -ge "$TIMEOUT" ]` on a non-number printed "integer expected" and
# the run carried on with a broken bound. Validate before the loop ever sees it.
case "$TIMEOUT" in
  ''|*[!0-9]*)
    echo "cleanse: TIMEOUT='$TIMEOUT' is not a positive integer number of seconds" >&2
    exit 64 ;;
esac
if [ "$TIMEOUT" -lt 1 ]; then
  echo "cleanse: TIMEOUT must be at least 1 second, got '$TIMEOUT'" >&2
  exit 64
fi

# ── input ───────────────────────────────────────────────────────────────────
if [ "${1:-}" = "-" ] || [ $# -eq 0 ]; then
  # `cat` on a terminal waits for Ctrl-D forever, and from a tool call that looks
  # like a hang. With no file argument, say so instead. An explicit `-` is the
  # documented way to ask for stdin, so it keeps reading.
  if [ $# -eq 0 ] && [ -t 0 ]; then
    echo "cleanse: no draft file given and stdin is a terminal" >&2
    echo "cleanse: usage: cleanse.sh draft.md   (or: cat draft.md | cleanse.sh -)" >&2
    exit 66
  fi
  DRAFT="$(cat)"
else
  # Without this, a typo'd filename sent an EMPTY draft to a paid model call and
  # wrote the reply into your output file at exit 0.
  [ -f "$1" ] && [ -r "$1" ] || { echo "cleanse: cannot read $1" >&2; exit 66; }
  DRAFT="$(cat "$1")"
fi
[ -n "$(printf '%s' "$DRAFT" | tr -d '[:space:]')" ] || {
  echo "cleanse: draft is empty, nothing to cleanse" >&2; exit 66; }

FULL="$(cat "$PROMPT_FILE")

$DRAFT"

# The rival gets prompt+draft as ONE argv entry. Windows caps a command line at
# ~32767 characters, so a long draft used to die as a bare rc=126 with no output
# — indistinguishable from the model failing. Refuse up front and explain.
FULL_BYTES="$(printf '%s' "$FULL" | wc -c | tr -d '[:space:]')"
case "$FULL_BYTES" in ''|*[!0-9]*) FULL_BYTES=0 ;; esac
if [ "$FULL_BYTES" -gt 30000 ]; then
  echo "cleanse: prompt+draft is $FULL_BYTES bytes, over the 30000-byte limit" >&2
  echo "cleanse: it cannot be passed as one argv entry (Windows caps a command" >&2
  echo "cleanse: line near 32767 characters); rc 126 from that would be silent." >&2
  echo "cleanse: split the draft into smaller sections and cleanse each one." >&2
  exit 66
fi

find_bin() { command -v "$1" 2>/dev/null || { [ -x "${HOME:-}/.local/bin/$1" ] && echo "${HOME:-}/.local/bin/$1"; }; }

# The reply is the copy, then a sentinel line, then the change notes. Copy goes to
# STDOUT so the documented `cleanse.sh draft.md > cleansed.md` writes prose ONLY;
# the notes go to STDERR so you still read them.
#
# Splitting on the old bare `---` was not possible: `---` is a legal markdown
# horizontal rule and a YAML frontmatter delimiter, so any draft that contained one
# got cut at the wrong place. Without a split the notes landed inside cleansed.md,
# and step 4 then scored the model's own commentary as prose and stamped it CLEAN —
# the gate reporting a clean page at exactly the moment it was measuring the wrong
# bytes. Redirecting to a file and reading only that file is the documented flow, so
# the leak was invisible until you opened the output.
#
# Fails OPEN on content: no sentinel means print the whole reply as copy and warn.
# A stray note you can see and delete; a silently swallowed last paragraph you cannot.
NOTES_SENTINEL='<<<SLOPMONSTER-NOTES>>>'

emit_copy() {
  # Split on the LAST sentinel line, not the first. A draft may legitimately
  # contain the marker line itself; a model that copies it verbatim then puts the
  # real marker below its copy. Splitting on the first occurrence published only
  # part of the copy and misfiled the rest as "notes".
  #
  # The comparison trims surrounding blanks/tabs/CR: a model that indents the
  # marker or a CRLF pipe used to miss the sentinel entirely and fall open.
  awk -v s="$NOTES_SENTINEL" '
    { line[NR] = $0; n = NR }
    END {
      cut = 0
      for (i = 1; i <= n; i++) {
        t = line[i]
        sub(/^[ \t\r]+/, "", t)
        sub(/[ \t\r]+$/, "", t)
        if (t == s) cut = i
      }
      if (cut == 0) {
        for (i = 1; i <= n; i++) printf "%s\n", line[i]
        print "cleanse: WARNING no " s " line in the reply." > "/dev/stderr"
        print "cleanse: emitting everything as copy. Check the tail before you ship," > "/dev/stderr"
        print "cleanse: because change notes may be scored as prose by the re-lint." > "/dev/stderr"
        exit 0
      }
      body = cut - 1
      while (body >= 1 && line[body] ~ /^[ \t\r]*$/) body--   # drop the blank lines before the marker
      for (i = 1; i <= body; i++) printf "%s\n", line[i]
      if (cut < n) {
        print ""                        > "/dev/stderr"
        print "cleanse: what changed -" > "/dev/stderr"
        for (i = cut + 1; i <= n; i++) printf "%s\n", line[i] > "/dev/stderr"
      }
    }
  '
}

CODEX="$(find_bin codex || true)"
CLAUDE="$(find_bin claude || true)"

# Which family must cleanse this draft. DESLOP_WRITER names the writer, never the
# cleanser — a model marking its own homework is the one thing this script exists
# to prevent.
case "$WRITER" in
  gpt)           RIVAL_FAMILY=claude ;;
  claude|gemini) RIVAL_FAMILY=codex  ;;
  *)             RIVAL_FAMILY=claude ;;  # unreachable: validated above
esac

case "$RIVAL_FAMILY" in
  codex)  RIVAL_BIN="$CODEX"  ;;
  claude) RIVAL_BIN="$CLAUDE" ;;
esac

if [ -z "$RIVAL_BIN" ]; then
  echo "cleanse: no rival-family CLI found: a $WRITER draft needs $RIVAL_FAMILY" >&2
  echo "cleanse: (not installed, or not on PATH). Paste the block below into the" >&2
  echo "cleanse: other family's chat:" >&2
  echo >&2
  printf '%s\n' "$FULL"
  exit 127
fi

run_bounded() {  # run_bounded <cmd...>  — macOS has no `timeout`, so we roll one
  local out pid waited rc
  out="$(mktemp)" || { echo "cleanse: cannot create a temp file" >&2; return 66; }
  "$@" >"$out" 2>&1 </dev/null &
  pid=$!; waited=0
  while kill -0 "$pid" 2>/dev/null; do
    if [ "$waited" -ge "$TIMEOUT" ]; then
      kill -9 "$pid" 2>/dev/null; wait "$pid" 2>/dev/null
      echo "cleanse: timed out after ${TIMEOUT}s" >&2
      rm -f "$out"
      return 124
    fi
    sleep 2; waited=$((waited + 2))
  done
  wait "$pid"; rc=$?
  cat "$out"; rm -f "$out"          # no temp file survives any path out of here
  return "$rc"
}

# P-01: a failing CLI used to exit with 0 bytes in BOTH streams, so the 124/1
# distinction and every diagnostic vanished. Keep the merged output and replay it
# on STDERR before propagating the CLI's own code.
cli_failed() {  # cli_failed <rc> <output>
  echo "cleanse: $RIVAL_FAMILY CLI failed (exit $1); result is not valid." >&2
  [ -n "$2" ] && printf '%s\n' "$2" >&2
  exit "$1"
}

if [ "$RIVAL_FAMILY" = "codex" ]; then
  # Claude (or Gemini) wrote the draft -> GPT-5.x cleanses it.
  # --skip-git-repo-check: copy lives in plain folders. Sandbox read-only: this
  # is a text transform, it has no business writing files.
  RAW="$(run_bounded "$RIVAL_BIN" exec --skip-git-repo-check --sandbox read-only "$FULL")"
  RC=$?
  [ "$RC" -eq 0 ] || cli_failed "$RC" "$RAW"
  # codex wraps the answer in banners; the answer sits between the last bare
  # `codex` line and `tokens used`.
  #
  # Test the EXTRACTION, not the first line. Branching on `read -r first` meant an
  # answer that opened with a blank line fell through to the raw transcript —
  # banner, session id, the whole prompt file — written straight into your output.
  # `read` also strips leading whitespace, which breaks the "preserve markdown
  # structure exactly" instruction the prompt file gives.
  BODY="$(printf '%s\n' "$RAW" | awk '$0=="codex"{buf="";on=1;next} $0=="tokens used"{on=0;next} on{buf=buf $0 "\n"} END{printf "%s", buf}')"
  [ -n "$(printf '%s' "$BODY" | tr -d '[:space:]')" ] || BODY="$RAW"
else
  # GPT wrote the draft -> Claude cleanses it.
  # --tools "": every built-in tool is disabled, so the pass cannot write files.
  # That is the closest flag claude documents to codex's --sandbox read-only and
  # it is deliberate: check `claude --help`, do not invent one. (--permission-mode
  # plan also restricts rights, but in -p it answers with a plan instead of the
  # copy, which breaks the output contract.) If upstream ever removes --tools,
  # drop the flag and keep this note: the asymmetry is a known, documented gap,
  # not an oversight.
  RAW="$(run_bounded "$RIVAL_BIN" --tools "" -p "$FULL")"
  RC=$?
  [ "$RC" -eq 0 ] || cli_failed "$RC" "$RAW"
  BODY="$RAW"
fi

# P-04: an empty reply is not a success. It used to exit 0 and write a 1-byte
# cleansed.md, which then sailed through the re-lint as "clean".
if [ -z "$(printf '%s' "$BODY" | tr -d '[:space:]')" ]; then
  echo "cleanse: $RIVAL_FAMILY returned an empty copy; nothing to write." >&2
  [ -n "$RAW" ] && printf '%s\n' "$RAW" >&2
  exit 65
fi

# Captured, not piped: the check below needs the copy, and a pipeline would report
# emit_copy's status instead of the split's outcome. Notes still go to STDERR from
# inside emit_copy.
CLEAN="$(printf '%s\n' "$BODY" | emit_copy)"
if [ -z "$(printf '%s' "$CLEAN" | tr -d '[:space:]')" ]; then
  echo "cleanse: no copy before the $NOTES_SENTINEL marker; nothing to write." >&2
  exit 65
fi
printf '%s\n' "$CLEAN"