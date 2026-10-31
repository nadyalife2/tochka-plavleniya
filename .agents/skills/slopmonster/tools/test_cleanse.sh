#!/usr/bin/env bash
# Regression tests for cleanse.sh: the copy/notes split AND the exit-code contract.
#
# The split is what keeps the model's change notes out of cleansed.md. Without it
# step 4 re-lints the notes as if they were prose and reports CLEAN, which is the
# gate lying at the one moment you are relying on it.
#
# Two blocks:
#   1. sources emit_copy out of cleanse.sh rather than reimplementing it, so the
#      split tests fail if the real function drifts;
#   2. runs the whole script against a stub rival CLI — no model calls, no
#      network — and pins the documented codes: 0 success, pass-through of a CLI
#      failure, 64 bad config, 65 empty copy, 66 bad input, 124 timeout, 127 no
#      rival CLI. The stubs live in a throwaway `mktemp -d` that is removed on
#      exit, so no junk is left behind.
set -uo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
BASH_BIN="$(command -v bash)"
eval "$(sed -n "/^NOTES_SENTINEL=/,/^}$/p" "$HERE/cleanse.sh")"

fail=0
check() {  # check <name> <condition-result>
  if [ "$2" = "0" ]; then printf '  ok    %s\n' "$1"
  else printf '  FAIL  %s\n' "$1"; fail=1; fi
}

# ── the ordinary reply ──────────────────────────────────────────────────────
out="$(printf 'Line one.\nLine two.\n\n%s\n- killed a tell\n' "$NOTES_SENTINEL" | emit_copy 2>/dev/null)"
[ "$out" = "$(printf 'Line one.\nLine two.')" ]; check 'copy is the text above the sentinel' $?
err="$(printf 'Copy.\n\n%s\n- killed a tell\n' "$NOTES_SENTINEL" | emit_copy 2>&1 >/dev/null)"
case "$err" in *'killed a tell'*) check 'notes go to stderr' 0;; *) check 'notes go to stderr' 1;; esac
case "$out" in *'killed a tell'*) check 'notes stay OUT of stdout' 1;; *) check 'notes stay OUT of stdout' 0;; esac

# ── a draft that legitimately contains --- ──────────────────────────────────
# The old separator was a bare `---`, which is both a markdown rule and a YAML
# frontmatter delimiter, so splitting on it truncated any draft containing one.
body="$(printf 'Intro.\n\n---\n\nAfter the rule.\nFinal line.\n\n%s\n- a note\n' "$NOTES_SENTINEL" | emit_copy 2>/dev/null)"
case "$body" in *'Final line.'*) check 'copy past a --- survives' 0;; *) check 'copy past a --- survives' 1;; esac
case "$body" in *'---'*) check 'the --- itself is preserved' 0;; *) check 'the --- itself is preserved' 1;; esac
case "$body" in *'a note'*) check 'notes not leaked past a ---' 1;; *) check 'notes not leaked past a ---' 0;; esac

# ── no sentinel: fail OPEN, never eat the copy ──────────────────────────────
out="$(printf 'Only copy.\nSecond line.\n' | emit_copy 2>/dev/null)"
case "$out" in *'Second line.'*) check 'missing sentinel keeps all copy' 0;; *) check 'missing sentinel keeps all copy' 1;; esac
err="$(printf 'Only copy.\n' | emit_copy 2>&1 >/dev/null)"
case "$err" in *WARNING*) check 'missing sentinel warns loudly' 0;; *) check 'missing sentinel warns loudly' 1;; esac

# ── sentinel with nothing after it ──────────────────────────────────────────
err="$(printf 'Copy only.\n\n%s\n' "$NOTES_SENTINEL" | emit_copy 2>&1 >/dev/null)"
[ -z "$err" ]; check 'empty notes print nothing to stderr' $?

# ── the blank lines before the sentinel are not published ───────────────────
# The model separates the sentinel from the copy with a blank line. Without the
# trim, every cleansed file ends in trailing whitespace.
# Asserted through a FILE, not "$(...)": command substitution strips trailing
# newlines, so a $()-based check cannot see this bug at all and passes either way.
tmp="$(mktemp)"
printf 'Copy ends here.\n\n\n%s\n- a note\n' "$NOTES_SENTINEL" | emit_copy 2>/dev/null >"$tmp"
[ "$(wc -c <"$tmp" | tr -d ' ')" = "16" ]; check 'blank lines before the sentinel are trimmed' $?
rm -f "$tmp"

# ── the sentinel must not match when quoted inside prose ────────────────────
out="$(printf 'We use the `%s` marker.\nStill copy.\n' "$NOTES_SENTINEL" | emit_copy 2>/dev/null)"
case "$out" in *'Still copy.'*) check 'sentinel inside a line is not a split point' 0;; *) check 'sentinel inside a line is not a split point' 1;; esac

# ── the sentinel as a line INSIDE the copy: split at the LAST one (P-03) ────
# The draft may legitimately contain the marker line (this skill's own docs do).
# A model that preserves it puts the real marker below the copy; splitting on the
# first occurrence published a truncated copy and misfiled the tail as "notes".
out="$(printf 'Copy start.\n%s\nstill copy.\n%s\n- a real note\n' "$NOTES_SENTINEL" "$NOTES_SENTINEL" | emit_copy 2>/dev/null)"
case "$out" in *'still copy.'*) check 'marker line inside copy: tail of copy survives' 0;; *) check 'marker line inside copy: tail of copy survives' 1;; esac
case "$out" in *'a real note'*) check 'marker line inside copy: notes stay out' 1;; *) check 'marker line inside copy: notes stay out' 0;; esac

# ── the marker is compared with surrounding whitespace trimmed (P-10) ───────
out="$(printf 'Copy.\n\t%s  \n- a note\n' "$NOTES_SENTINEL" | emit_copy 2>/dev/null)"
[ "$out" = "Copy." ]; check 'indented/padded marker still splits' $?
err="$(printf 'Copy.\n%s   \n- a note\n' "$NOTES_SENTINEL" | emit_copy 2>&1 >/dev/null)"
case "$err" in *'a note'*) check 'notes follow a padded marker to stderr' 0;; *) check 'notes follow a padded marker to stderr' 1;; esac

# ── end-to-end against a stub rival CLI ─────────────────────────────────────
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
stub_bin="$work/bin"; mkdir -p "$stub_bin"

# One stub serves both CLIs. Controlled entirely by env vars, records argv so a
# test can prove which flags the real script actually passes.
write_stub() {  # write_stub <name>
  cat >"$stub_bin/$1" <<'STUB'
#!/usr/bin/env bash
set -uo pipefail
if [ -n "${STUB_LOG:-}" ]; then for a in "$@"; do printf 'ARG[%s]\n' "$a" >>"$STUB_LOG"; done; fi
if [ -n "${STUB_ERR:-}" ]; then printf '%s\n' "$STUB_ERR" >&2; fi
if [ -n "${STUB_REPLY:-}" ]; then cat "$STUB_REPLY"; fi
if [ -n "${STUB_SLEEP:-}" ]; then exec sleep "$STUB_SLEEP"; fi
exit "${STUB_RC:-0}"
STUB
  chmod +x "$stub_bin/$1"
}
write_stub codex
write_stub claude

draft="$work/draft.md"
printf 'Line one.\nLine two.\n' >"$draft"
draft_sum="$(cksum <"$draft")"
reply="$work/reply.txt"
printf 'Line one.\nLine two.\n\n%s\n- killed a tell\n' "$NOTES_SENTINEL" >"$reply"

# ── happy path: stdout=copy, stderr=notes, rc 0, source untouched ───────────
out="$(PATH="$stub_bin:$PATH" STUB_REPLY="$reply" STUB_RC=0 bash "$HERE/cleanse.sh" "$draft" 2>"$work/err")"
rc=$?
[ "$rc" = "0" ]; check 'e2e success: exit 0' $?
[ "$out" = "$(printf 'Line one.\nLine two.')" ]; check 'e2e success: stdout is the copy only' $?
case "$(cat "$work/err")" in *'killed a tell'*) check 'e2e success: notes on stderr' 0;; *) check 'e2e success: notes on stderr' 1;; esac
case "$out" in *'killed a tell'*) check 'e2e success: no notes in stdout' 1;; *) check 'e2e success: no notes in stdout' 0;; esac
[ "$(cksum <"$draft")" = "$draft_sum" ]; check 'e2e success: source draft not rewritten' $?

# ── P-01: a failing CLI is no longer silent ────────────────────────────────
printf 'boom\n' >"$work/boom.txt"
out="$(PATH="$stub_bin:$PATH" STUB_REPLY="$work/boom.txt" STUB_RC=3 bash "$HERE/cleanse.sh" "$draft" 2>"$work/err")"
rc=$?
[ "$rc" = "3" ]; check 'CLI failure: rc 3 is propagated' $?
case "$(cat "$work/err")" in *boom*) check 'CLI failure: CLI output replays on stderr' 0;; *) check 'CLI failure: CLI output replays on stderr' 1;; esac
[ -z "$out" ]; check 'CLI failure: stdout stays empty' $?

# ── P-04: an empty reply is a failure, not a 1-byte success ────────────────
out="$(PATH="$stub_bin:$PATH" STUB_RC=0 bash "$HERE/cleanse.sh" "$draft" 2>"$work/err")"
rc=$?
[ "$rc" = "65" ]; check 'empty CLI reply: exit 65' $?
case "$(cat "$work/err")" in *'empty copy'*) check 'empty CLI reply: explains itself on stderr' 0;; *) check 'empty CLI reply: explains itself on stderr' 1;; esac
[ -z "$out" ]; check 'empty CLI reply: nothing on stdout' $?

# ── P-05: DESLOP_WRITER is a closed list ───────────────────────────────────
out="$(PATH="$stub_bin:$PATH" DESLOP_WRITER=bogus bash "$HERE/cleanse.sh" "$draft" 2>"$work/err")"
rc=$?
[ "$rc" = "64" ]; check 'unknown DESLOP_WRITER: exit 64' $?
case "$(cat "$work/err")" in *unknown\ DESLOP_WRITER*) check 'unknown DESLOP_WRITER: names the variable' 0;; *) check 'unknown DESLOP_WRITER: names the variable' 1;; esac
[ -z "$out" ]; check 'unknown DESLOP_WRITER: nothing on stdout' $?

# ── P-09: TIMEOUT must be a positive integer ───────────────────────────────
PATH="$stub_bin:$PATH" TIMEOUT=abc bash "$HERE/cleanse.sh" "$draft" >"$work/out" 2>"$work/err"
rc=$?
[ "$rc" = "64" ]; check 'non-numeric TIMEOUT: exit 64' $?
case "$(cat "$work/err")" in *TIMEOUT*) check 'non-numeric TIMEOUT: names the variable' 0;; *) check 'non-numeric TIMEOUT: names the variable' 1;; esac
PATH="$stub_bin:$PATH" TIMEOUT=0 bash "$HERE/cleanse.sh" "$draft" >/dev/null 2>&1
[ "$?" = "64" ]; check 'zero TIMEOUT: exit 64' $?

# ── P-02: oversize prompt+draft is refused, not silently rc 126 ────────────
awk 'BEGIN { for (i = 0; i < 31000; i++) printf "a" }' >"$work/big.md"
out="$(PATH="$stub_bin:$PATH" STUB_REPLY="$reply" bash "$HERE/cleanse.sh" "$work/big.md" 2>"$work/err")"
rc=$?
[ "$rc" = "66" ]; check 'oversize draft: exit 66' $?
case "$(cat "$work/err")" in *30000*argv*) check 'oversize draft: explains the argv limit' 0;; *) check 'oversize draft: explains the argv limit' 1;; esac
[ -z "$out" ]; check 'oversize draft: no partial copy on stdout' $?

# ── P-07: the claude branch is started with tools disabled ─────────────────
log="$work/args-claude.log"
out="$(PATH="$stub_bin:$PATH" STUB_LOG="$log" STUB_REPLY="$reply" DESLOP_WRITER=gpt bash "$HERE/cleanse.sh" "$draft" 2>"$work/err")"
rc=$?
[ "$rc" = "0" ]; check 'gpt draft: claude rival runs' $?
grep -qxF 'ARG[--tools]' "$log" && grep -qxF 'ARG[]' "$log"; check 'gpt draft: claude gets --tools ""' $?
grep -qxF 'ARG[-p]' "$log"; check 'gpt draft: claude keeps -p' $?
! grep -qxF 'ARG[--sandbox]' "$log"; check 'gpt draft: no codex-only flags leak into claude' $?

# ── routing: claude/gemini drafts go to codex, read-only sandbox kept ──────
log="$work/args-codex.log"
out="$(PATH="$stub_bin:$PATH" STUB_LOG="$log" STUB_REPLY="$reply" DESLOP_WRITER=gemini bash "$HERE/cleanse.sh" "$draft" 2>"$work/err")"
rc=$?
[ "$rc" = "0" ]; check 'gemini draft: exit 0 via the codex rival' $?
grep -qxF 'ARG[--sandbox]' "$log" && grep -qxF 'ARG[read-only]' "$log"; check 'gemini draft: codex still runs --sandbox read-only' $?

# ── 124 on timeout, and no temp file left behind ───────────────────────────
out="$(PATH="$stub_bin:$PATH" STUB_SLEEP=30 TIMEOUT=1 bash "$HERE/cleanse.sh" "$draft" 2>"$work/err")"
rc=$?
[ "$rc" = "124" ]; check 'timeout: exit 124' $?
case "$(cat "$work/err")" in *'timed out'*) check 'timeout: message on stderr' 0;; *) check 'timeout: message on stderr' 1;; esac
[ -z "$out" ]; check 'timeout: nothing on stdout' $?

# ── 127 when the rival family is not installed ─────────────────────────────
# Point PATH at an empty dir first and drop /usr/local/bin, so a real codex on
# the test machine cannot leak in; /usr/bin stays because Git-for-Windows
# coreutils cannot load their DLLs from anywhere else. HOME points at a dir with
# no ~/.local/bin. bash itself is invoked by absolute path — the restricted PATH
# cannot find it either.
minbin="$work/minbin"; mkdir -p "$minbin"
out="$(PATH="$minbin:/usr/bin:/bin" HOME="$work/nohome" "$BASH_BIN" "$HERE/cleanse.sh" "$draft" 2>"$work/err")"
rc=$?
[ "$rc" = "127" ]; check 'no rival CLI: exit 127' $?
case "$out" in *'Line one.'*) check 'no rival CLI: prompt+draft on stdout' 0;; *) check 'no rival CLI: prompt+draft on stdout' 1;; esac
case "$(cat "$work/err")" in *rival*) check 'no rival CLI: says which family is missing' 0;; *) check 'no rival CLI: says which family is missing' 1;; esac

[ "$fail" = "0" ] && echo "all cleanse tests passed" || { echo "cleanse tests FAILED"; exit 1; }