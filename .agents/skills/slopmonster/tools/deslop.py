#!/usr/bin/env python3
"""Hunt AI tells in the visible copy of a built page. Regex, no opinions, exits red.

    python deslop.py index.html
    python deslop.py index.html --view view-site     # score one element only
    python deslop.py --text "some copy to check"
    python deslop.py draft.md --markdown --allow-proof

This reads only what a visitor can SEE: it strips <script>, <style>, comments and
every HTML tag, so it scores the words on the page rather than the markup around
them. A bare `<` that belongs to the copy ("5 < 10") survives.

Scoring is out of 5. Below 5 exits 1, a usage error exits 2. That is deliberate —
"mostly clean" copy is how a page ends up sounding like every other AI page on the
internet.
"""
import html as _html   # aliased: `visible_text` takes a parameter named `html`
import re
import sys

# ── the catalogue ────────────────────────────────────────────────────────────
# Grouped by why they are a tell, because the fix differs per group.

# Matched by root, so every inflection fires: `elevate` also catches elevates,
# elevated, elevating, elevation. Landing-page copy is written in the third
# person ("Acme elevates your workflow"), so exact-string matching missed the
# single most common surface form of every word here.
VOCAB = [
    'delve', 'leverage', 'seamless', 'elevate', 'robust', 'unlock', 'unleash',
    'empower', 'streamline', 'cutting-edge', 'state-of-the-art', 'game-changer',
    'game-changing', 'revolutionize', 'revolutionise', 'transformative',
    'transformation', 'innovate', 'holistic', 'synergy', 'synergies', 'paradigm', 'bespoke',
    'meticulous', 'tapestry', 'testament', 'beacon', 'unparalleled', 'supercharge',
    'turbocharge', 'effortless', 'next-level',
    # second tier: fine once in a long page, damning in every section
    'pivotal', 'foster', 'showcase', 'compelling', 'intuitive', 'world-class',
    'best-in-class',
]

# Words with an ordinary literal sense — "we craft furniture", "harness the
# horse", "the landscape of the valley". Matched exactly, never by root, so the
# innocent use survives and only the marketing inflection is caught.
#
# Root-matched entries above can still fire on a literal or technical sense:
# `foster` catches "foster children", and `elevate` catches "elevation" in survey
# notes. Like every finding here, those get read in context before an edit.
VOCAB_EXACT = [
    'crafted', 'curated', 'harnessing', 'harness the power', 'journey', 'realm', 'landscape',
    'navigate the', 'in the world of', "in today's", 'ever-evolving', 'fast-paced',
    'look no further', 'dive in', "let's dive", 'deep dive', 'embark',
    'unlock the power', 'buckle up', 'the secret sauce', 'level up',
]


def _root_pattern(word):
    """A regex matching `word` and its inflections.

    Strip a trailing e/ed/ing/ly to get the root, then allow the suffixes back.
    The bare `e?` alternative is load-bearing: without it, stripping the `e` from
    `elevate` leaves `elevat`, which no longer matches the base form itself.
    """
    root = re.sub(r'(ed|ing|ly|e)$', '', word)
    if len(root) < 4:                     # too short to stem safely
        return rf"(?<!\w){re.escape(word)}(?!\w)"
    return rf"(?<!\w){re.escape(root)}(?:e|es|ed|ing|ion|ions|ional|ive|al|ally|s|ly|ness)?(?!\w)"

# Negated "just/only/merely/simply", in either register.
#
# `\bnot\b` cannot match inside `isn't` — there is no standalone `not` token there — so
# an uncontracted-only pattern misses every contracted form, which is the register a
# model reaches for when it is trying hardest to sound human. Matching the `n't` suffix
# covers isn't / aren't / wasn't / doesn't / don't / won't / can't without enumerating
# them. Straight and curly apostrophes both.
_NEG_JUST = r"(?:\bnot|n['’]t)\s+(?:just|only|merely|simply)\b"

# The Y clause of the swap, reached across a comma or a single full stop. It is not
# always a copula — "it doesn't just park you, it GETS you there" is the same move — so
# this takes a pronoun subject and lets any verb follow. The punctuation carries the
# precision: "It's not just about money." has no Y clause and stays clean, and so does
# "Do not just take my word for it."
_XY_TAIL = r"[^!?]{0,80}?[,.]\s*(?:it|this|that|they|we|you|he|she|i)\b"

PHRASES = [
    # constructions, not words — these are the loudest tells
    # The contracted and uncontracted forms both matter: formal register is not an
    # adversarial rewrite, it is the default thing a model emits.
    (_NEG_JUST + r"[^.!?]{0,80}\bbut\b", "the 'not just X, but Y' construction"),
    (_NEG_JUST + _XY_TAIL, "the 'not just X, it's Y' construction"),
    (r"\bwhether you(?:'?re| are)\b[^.!?]{0,40}\bor\b", "the 'whether you're X or Y' opener"),
    (r"\bmore than just\b",                        "'more than just'"),
    (r"\b(that|this)(?:'?s| is) where\b[^.!?]{0,30}\bcomes? in\b", "'that's where X comes in'"),
    (r"\bsay goodbye to\b",                        "'say goodbye to'"),
    (r"\bimagine (a|an|the)\b",                    "the 'imagine a…' opener"),
    (r"\bin conclusion\b|\bto sum up\b",           "essay-summary phrasing"),
    (r"\bwhen it comes to\b",                      "'when it comes to' filler"),
    (r"\bat the end of the day\b",                 "'at the end of the day'"),
    (r"\bthe key is\b|\bthe truth is\b",           "throat-clearing opener"),
    (r"\bhelps? you to\b|\bcan help you\b",        "hedged benefit ('helps you to…')"),
    (r"\bmay potentially\b|\bcould potentially\b|\bmight possibly\b", "stacked hedging"),
    (r"\bvery unique\b|\bquite literally\b",       "intensifier padding"),
    # Openers and self-answering questions. Cheap literals, near-zero false
    # positives, and they cover the register a pure vocabulary list cannot see.
    (r"\bhere'?s the thing\b|\blet'?s break (it|this) down\b|\bthe best part\b",
                                                   "throat-clearing opener"),
    (r"\bready to get started\b|\blet'?s get started\b", "boilerplate CTA"),
    (r"\bthe (result|answer|catch|kicker|upshot)\?\s", "self-answering question"),
]

# Invented social proof. Deliberately broad: this is the one mistake with no
# route back, so recall matters more than precision. If your number is real and
# you can evidence it, pass --allow-proof and the rule drops to advisory.
# Hyphenated compound modifiers. One is ordinary English: "a 25-year warranty".
# Four in a sentence is a model reaching for authority it has not earned, and the
# giveaway is that they stack in front of one noun: "our industry-leading,
# context-aware, best-in-class, AI-powered platform". The floor is high on purpose.
# Real trade copy runs one to three per hundred words, and stacked slop runs sixty.
COMPOUND = re.compile(r'\b[a-z]{2,}-[a-z]{2,}(?:-[a-z]{2,})*\b', re.I)
COMPOUND_FLOOR = 4

PROOF = re.compile(
    # Digits, thousands separators and a decimal point, but never a trailing
    # full stop. Absorbing it let "Don't Make Me Think, 2000. The reader…" read
    # as a proof claim, because the year swallowed the sentence break and then
    # reached across it for a noun. A number and its noun live in one sentence.
    r"([\d][\d,]*(?:\.\d+)?)\s*\+?\s*"
    r"((?:happy|early|active|satisfied|verified|trusted|delighted)\s+)?"
    r"(?:\w+\s+){0,1}"
    r"(users?|customers?|learners?|students?|teams?|members?|companies|businesses"
    r"|homeowners?|subscribers?|clients?|patients?|readers?|sites?|projects?)"
    # Closed with a word boundary, like `_root_pattern`. Without it `sites?`
    # matched inside "sitemaps", `teams?` inside "teamsters" and `projects?`
    # inside "projectors", and "12 sitemaps" scored as invented social proof.
    r"(?!\w)",
    re.I)


def normalise(t):
    """Fold the typographic variants back to the plain ones the rules match.

    A non-breaking hyphen is not `-`, \xa0 is not a space, and a curly
    apostrophe is not `'`. Miss any of them and `cutting-edge`,
    `it's not just X` and `whether you're` all stop matching on real copy,
    because real copy is exactly where the pretty characters come from.

    Every input path runs through here. Markdown skipped it once, and the
    construction rules went blind on every .md file with a smart quote in it.
    """
    t = t.replace('‑', '-').replace('\xa0', ' ').replace('’', "'")
    return re.sub(r'\s+', ' ', t).strip()


def _strip_block(html, tag):
    """Drop `<tag ...>...</tag>` in one linear pass.

    `<(script|style).*?</\\1>` is quadratic on a document full of unclosed
    openers: every start scans to the end of the file before it fails. A 320 KB
    page of truncated `<script` tags took 59 s. find() does not. An opener with
    no closer drops the rest of the document, which is what a truncated page is.
    """
    low = html.lower()
    open_at, close_at = f'<{tag}', f'</{tag}'
    out, i = [], 0
    while True:
        start = low.find(open_at, i)
        if start < 0:
            out.append(html[i:])
            return ''.join(out)
        after_name = low[start + len(open_at):start + len(open_at) + 1]
        if after_name and after_name not in ' \t\r\n/>':
            out.append(html[i:start + len(open_at)])   # `<scripture>` is not a tag
            i = start + len(open_at)
            continue
        out.append(html[i:start])
        end = low.find(close_at, start)
        if end < 0:
            return ''.join(out)
        gt = low.find('>', end)
        i = len(html) if gt < 0 else gt + 1


def _strip_comments(html):
    """Drop `<!-- ... -->`, linearly, for the same reason as `_strip_block`."""
    out, i = [], 0
    while True:
        start = html.find('<!--', i)
        if start < 0:
            out.append(html[i:])
            return ''.join(out)
        out.append(html[i:start])
        end = html.find('-->', start)
        if end < 0:
            return ''.join(out)
        i = end + 3


def _strip_tags(html):
    """Remove real tags, keeping a bare `<` that belongs to the copy.

    `re.sub(r'<[^>]+>', ' ')` treated every `<` as a tag, so "5 < 10" lost the
    text between the comparison and the next `>`. Only `<` followed by an
    optional slash and a letter is a tag; `<!` and `<?` are declarations.
    """
    out, i, n = [], 0, len(html)
    while i < n:
        lt = html.find('<', i)
        if lt < 0:
            out.append(html[i:])
            break
        out.append(html[i:lt])
        j = lt + 1
        if j < n and html[j] in '!?':                 # doctype, comment, PI
            gt = html.find('>', j)
            if gt < 0:
                break
            out.append(' ')
            i = gt + 1
            continue
        if j < n and html[j] == '/':
            j += 1
        if j < n and html[j].isalpha():
            gt = html.find('>', j)
            if gt < 0:
                break                                 # truncated tag: drop it
            out.append(' ')
            i = gt + 1
            continue
        out.append('<')                               # a comparison, not a tag
        i = lt + 1
    return ''.join(out)


def visible_text(html):
    """What a visitor actually reads. Script/style, comments and tags removed."""
    for tag in ('script', 'style'):
        html = _strip_block(html, tag)
    t = _strip_tags(_strip_comments(html))
    # Decode every entity, not a hand-written six. Numeric entities used to leak
    # through as literal text: each `&#x27;` donated a phantom semicolon to the
    # punctuation rule, and every apostrophe-encoded page went blind to the
    # `it's not just X` and `whether you're` patterns.
    t = _html.unescape(t)
    return normalise(t)


def read_utf8(path):
    """Read a file as UTF-8, whatever the machine's locale says.

    open() with no encoding= uses the locale's, which is cp1252 on a stock
    Windows install. A UTF-8 page then decodes its em-dashes and curly
    apostrophes into mojibake, .replace('\u2019', "'") never fires, and
    window.count('\u2014') counts zero. The punctuation and construction rules
    go silently blind: the same copy scores 4/5 through --text and 5/5 CLEAN
    from a file path, and the file path is what CI wires in.

    A gate that passes because it cannot read is worse than no gate at all.
    Reported by @Azrael259 in #3, who hit it on Windows.

    Bytes are decoded strictly first. A file that is not UTF-8 (cp1252 markup, a
    truncated download) is announced on stderr and decoded with replacements,
    rather than scored as silent mojibake.
    """
    data = open(path, 'rb').read()
    try:
        return data.decode('utf-8')
    except UnicodeDecodeError as e:
        print(f'deslop: {path} is not valid UTF-8 at byte {e.start}; '
              'decoding with replacements', file=sys.stderr)
        return data.decode('utf-8', errors='replace')


def markdown_prose(md):
    """The prose of a Markdown file, with the specimens removed.

    A literal is not copy. A README that documents `delve` has not shipped the
    word, it has quoted it, and a linter that cannot tell the difference makes
    every catalogue score zero. So four things come out before scoring:

      fenced blocks   ```…```      commands and code, never prose
      inline code     `delve`      the specimen being named
      struck text     ~~before~~   the line being shown as wrong, on purpose
      images          ![alt](src)  alt text is metadata, not body copy

    Link text stays, because that is read as part of the sentence. Everything
    else is scored exactly as before: this strips markup, it does not soften a
    single rule.
    """
    # Fences first: paired, then unterminated. A truncated ``` used to expose the
    # very specimen the fence was hiding, and the coder did not get told.
    md = re.sub(r'```.*?```', ' ', md, flags=re.S)
    md = re.sub(r'~~~.*?~~~', ' ', md, flags=re.S)
    md = re.sub(r'```.*$', ' ', md, flags=re.S)
    md = re.sub(r'~~~.*$', ' ', md, flags=re.S)
    md = re.sub(r'!\[[^\]]*\]\([^)]*\)', ' . ', md)
    md = re.sub(r'\[([^\]]*)\]\([^)]*\)', r'\1', md)
    # Stand-ins, not deletions. Removing `[needs number]` outright welds
    # "you do not have, write ... and move on" into a false rule-of-three, and
    # a linter that invents a hit is worse than one that misses. Two letters
    # keep the clause intact without ever matching a rule itself.
    md = re.sub(r'`[^`]*`', ' it ', md)
    md = re.sub(r'~~.*?~~', ' it ', md, flags=re.S)
    # Headings, table cells and rows are separate copy, not one long sentence.
    # Joined, two em-dashes from two table rows read as one machine cadence.
    #
    # Every anchored pattern below says `[ \t]`, never `\s`. `\s` matches the
    # newline, so on a file of blank lines each `^` scanned the whole remainder
    # and failed: 50 KB of blank lines took 9.6 s and 100 KB about 38 s, growing
    # quadratically. Runs of whitespace are collapsed afterwards anyway.
    md = re.sub(r'^[ \t]{0,3}#{1,6}[ \t]*(.*)$', r' . \1 . ', md, flags=re.M)
    md = re.sub(r'\|', ' . ', md)
    # A bullet is its own line of copy. Left joined, two list items donate one
    # em-dash each and read as a single machine cadence that nobody wrote.
    md = re.sub(r'^[ \t]*(?:[-*+]|\d+\.)[ \t]+', ' . ', md, flags=re.M)
    md = re.sub(r'^[ \t]*>[ \t]?', ' . ', md, flags=re.M)
    md = re.sub(r'[*_>]', ' ', md)
    return normalise(md)


def audit(text):
    hits = {'vocab': [], 'phrases': [], 'punctuation': [], 'rhythm': [], 'proof': []}
    low = text.lower()

    for w in VOCAB:
        n = len(re.findall(_root_pattern(w), low))
        if n:
            hits['vocab'].append((w, n))

    for w in VOCAB_EXACT:
        n = len(re.findall(rf"(?<!\w){re.escape(w)}(?!\w)", low))
        if n:
            hits['vocab'].append((w, n))

    for pat, label in PHRASES:
        found = re.findall(pat, low)
        if found:
            hits['phrases'].append((label, len(found)))

    # Em-dash density: two or more in one sentence reads as machine cadence.
    # Bounded to a 220-char window on purpose — UI strings (nav items, quiz options,
    # labels) carry no terminal punctuation, so a naive sentence split merges the
    # whole page into one "sentence" and this rule fires on every page. Ask me how
    # I know. A linter that cries wolf gets switched off.
    for s in re.split(r'(?<=[.!?])\s+', text):
        for i in range(0, max(1, len(s)), 220):
            window = s[i:i + 220]
            if window.count('—') >= 2:
                hits['punctuation'].append(('two or more em-dashes in one sentence',
                                            window[:70].strip()))
                break
    for s in re.split(r'(?<=[.!?])\s+', text):
        for i in range(0, max(1, len(s)), 220):
            window = s[i:i + 220]
            found = COMPOUND.findall(window)
            if len(found) >= COMPOUND_FLOOR:
                hits['punctuation'].append((f'{len(found)} hyphenated compounds stacked '
                                            'in one sentence', ', '.join(found[:4])))
                break

    # Floor of 3: two semicolons in a long technical page is a style, not a tell.
    if text.count(';') > max(3, len(text) // 1200):
        hits['punctuation'].append(('semicolon-heavy for web copy', f"{text.count(';')} found"))

    # Tricolon: the rule-of-three reflex. Two shapes, deliberately narrow.
    #
    # With the Oxford comma, three single words: "faster, smarter, and better".
    # Without it, the third item must be a 2–3 word phrase that ends the clause:
    # "Trusted, reliable and built to last". That phrase requirement is what
    # separates a rhetorical flourish from a plain list of services —
    # "Inspection, repair and replacement for homes and commercial buildings"
    # is three real things a roofer does, its third item runs long, and
    # flagging it would be exactly the wolf-crying that gets a linter switched
    # off. Note the shape is what is judged, not the meaning: a three-word
    # closing item ("replacement for homes") does fire, by design.
    for pat in (r'\b(\w{4,}),\s+(\w{4,}),\s+and\s+(\w{4,})\b',
                r'\b(\w{4,}),\s+(\w{4,})\s+and\s+((?:\w+\s+){1,2}\w+)\s*[.!?,;:]'):
        for m in re.finditer(pat, text):
            hits['rhythm'].append(('rule-of-three list', m.group(0)[:60]))

    for m in PROOF.finditer(text):
        hits['proof'].append(m.group(0).strip())

    return hits


def report(hits, label='', allow_proof=False):
    weights = {'vocab': 1, 'phrases': 1, 'punctuation': 1, 'rhythm': 1, 'proof': 1}
    if allow_proof:
        # The one rule a regex cannot judge: it sees a number beside a noun, not
        # whether you can evidence it. --allow-proof still prints the hits, but
        # stops a true, defensible claim from blocking a green build forever.
        weights['proof'] = 0
    failed = [k for k, v in hits.items() if v]
    score = 5 - sum(weights[k] for k in failed)
    score = max(0, score)

    titles = {
        'vocab': 'AI vocabulary',
        'phrases': 'AI constructions',
        'punctuation': 'punctuation cadence',
        'rhythm': 'rule-of-three rhythm',
        'proof': 'possible invented proof',
    }

    if label:
        print(f'── {label}')
    for k in ('proof', 'phrases', 'vocab', 'punctuation', 'rhythm'):
        if not hits[k]:
            continue
        print(f'  {titles[k]}:')
        for item in hits[k][:8]:
            print(f'    · {item[0] if isinstance(item, tuple) else item}'
                  + (f'  ({item[1]})' if isinstance(item, tuple) and len(item) > 1 else ''))
        if len(hits[k]) > 8:
            print(f'    · …and {len(hits[k]) - 8} more')

    print(f'\n  score {score}/5', end='  ')
    print('CLEAN' if score == 5 else 'needs a cleanse')
    return score


# ── command line ────────────────────────────────────────────────────────────

USAGE = """usage: deslop.py <file.md|file.html> [--view <id>] [--allow-proof]
       deslop.py --text "<copy>" [--markdown] [--allow-proof]
       deslop.py -h

Scores visible copy out of 5. Exit 0 at 5/5, 1 below 5/5 or an unreadable input,
2 for a usage error."""


def usage(message):
    print(f'deslop: {message}', file=sys.stderr)
    print(USAGE, file=sys.stderr)
    sys.exit(2)


_TAG = re.compile(r'<(/?)([a-zA-Z][\w:-]*)((?:"[^"]*"|\'[^\']*\'|[^>])*)>', re.S)

# Elements that never have a closing tag, so they must not open a nesting level.
VOID = {'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link',
        'meta', 'param', 'source', 'track', 'wbr'}


def view_element(html, vid):
    """The element whose opening tag carries `id="vid"`, closing tag included.

    Slicing from `id="vid"` to the next `id="view-` left the rest of the opening
    tag in the copy and, when later blocks used another naming scheme, swept
    them in too: a clean tab scored 4/5 on a neighbour's slop. This walks the
    tags and counts nesting of the same element name instead.
    """
    id_re = re.compile(r'(?<![\w-])id\s*=\s*(["\'])' + re.escape(vid) + r'\1', re.I)
    for m in _TAG.finditer(html):
        if m.group(1) or not id_re.search(m.group(3)):
            continue
        name = m.group(2).lower()
        if name in VOID or m.group(3).rstrip().endswith('/'):
            return html[m.start():m.end()]
        depth = 1
        for m2 in _TAG.finditer(html, m.end()):
            if m2.group(2).lower() != name:
                continue
            if m2.group(1):
                depth -= 1
                if depth == 0:
                    return html[m.start():m2.end()]
            elif not m2.group(3).rstrip().endswith('/'):
                depth += 1
        return html[m.start():]        # unclosed element: to the end of the file
    return None


def parse_args(argv):
    """Flags and input, POSIX-style: options first, `--text` takes the rest."""
    allow_proof = as_md = False
    view = text = path = None
    i = 0
    while i < len(argv):
        a = argv[i]
        if a in ('-h', '--help'):
            print(__doc__.strip())
            sys.exit(0)
        elif a == '--allow-proof':
            allow_proof = True
        elif a == '--markdown':
            as_md = True
        elif a == '--view':
            if i + 1 >= len(argv):
                usage('--view needs an element id')
            view = argv[i + 1]
            i += 1
        elif a == '--text':
            payload = argv[i + 1:]
            # Options come before the input. A lone flag after --text is almost
            # always a misplaced option rather than copy, so say so instead of
            # scoring it and shifting the word count.
            stray = [p for p in payload
                     if p in ('--allow-proof', '--markdown', '--view')]
            if stray:
                print(f'deslop: note - {", ".join(stray)} after --text is scored '
                      'as copy; put options before --text', file=sys.stderr)
            text = ' '.join(payload)
            break
        elif a.startswith('-') and a != '-':
            usage(f'unknown option {a}')
        elif path is None:
            path = a
        else:
            usage(f'unexpected extra argument {a}')
        i += 1
    return allow_proof, as_md, view, text, path


def read_input(path, as_md, view):
    """The copy to score, read from a file."""
    try:
        if as_md or path.lower().endswith('.md'):
            if view:
                usage('--view applies to HTML files, not Markdown')
            return markdown_prose(read_utf8(path))
        html = read_utf8(path)
    except (FileNotFoundError, IsADirectoryError, PermissionError) as e:
        sys.exit(f'deslop: cannot read {path}: {e.strerror}')
    if view:
        element = view_element(html, view)
        if element is None:
            usage(f'no element with id "{view}"')
        html = element
    return visible_text(html)


if __name__ == '__main__':
    # Reading UTF-8 correctly means real non-ASCII now reaches print(), and a
    # Windows console is cp1252: one CJK character or emoji inside a flagged
    # snippet would end the run in a UnicodeEncodeError traceback. Replace
    # rather than raise. Naming the tell is the job; echoing it byte-for-byte
    # is not, and the suite already asserts this tool never shows a traceback.
    try:
        sys.stdout.reconfigure(errors='replace')
    except (AttributeError, ValueError):      # already-wrapped or exotic stream
        pass

    allow_proof, as_md, view, text, path = parse_args(sys.argv[1:])

    if text is None and path is None:
        usage('nothing to score - pass a file, or --text "copy"')
    if text is not None:
        if view:
            usage('--view applies to HTML files, not --text')
        # --text used to skip normalise(): a smart apostrophe or a non-breaking
        # hyphen scored 5/5 here and 4/5 from a file. Every input path runs
        # through it now, which is what the comment above promises.
        text = markdown_prose(text) if as_md else normalise(text)
    else:
        text = read_input(path, as_md, view)

    # Nothing to score is a failure, not a pass. A cleanse that times out leaves a
    # zero-byte file, and a gate that stamps an empty file CLEAN reports slop as
    # clean at exactly the moment the pipeline broke.
    if not text.split():
        sys.exit('deslop: no visible copy to score — empty input')

    print(f'{len(text.split())} words of visible copy\n')
    sys.exit(0 if report(audit(text), allow_proof=allow_proof) == 5 else 1)
