# -*- coding: utf-8 -*-
"""
Whole-site integrity regression net for the Alumo website.

Checks, for the 27 tracked page files (12 live + 2 private + 4 policy stubs +
4 past-winners stubs + 5 French-slug redirect stubs):
  1. Strict UTF-8 decodability + no mojibake markers.
  2. Structural tag balance (html.parser based).
  3. Internal href/src/action targets exist (with an expected-missing allowlist,
     and a separate AWAITING_CLIENT list for files the client still owes).
  4. Forbidden strings on the 12 live pages and the private pages.
  5. Identical css/style.css?v= and js/main.js?v= across all live pages and the
     private pages.
  6. One submission window on the live pages: every tag with data-opens-at or
     data-closes-at carries both, each an ISO date WITH an explicit offset,
     opens before closes, and all live pages share one value of each; the
     server's close date (DEFAULT_CLOSES_AT in api/apply.php, and the
     top-level closes_at example in api/config.example.php) equals that
     close. The private pages are outside this window.
  P. Private (unlisted) pages: carry noindex,nofollow, are absent from
     sitemap.xml and robots.txt, and are linked from no live page. They are deliberately NOT
     required to be in the nav or the sitemap — the opposite. Every shared
     css/js file they load carries the same ?v= as the live pages that load it
     (they reuse /apply-now/'s stylesheets), and they never load js/analytics.js.

Usage: python _tools/verify_integrity.py [--release]
  --release  files still AWAITING_CLIENT count as failures. Run with it
             before merging main -> release: a release must never ship a
             link to a file the client hasn't sent.

Exit code 0 = clean, 1 = findings.
"""
import io
import os
import re
import sys
from datetime import datetime
from html.parser import HTMLParser

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))

LIVE_PAGES = [
    "index.html",
    "about-the-fund/index.html",
    # how-to-apply and fr/comment-soumettre merged into the two submit pages on
    # 2026-09-01 and are redirect stubs now — see MERGED_STUBS below.
    "apply-now/index.html",
    "partner-schools/index.html",
    "terms-conditions/index.html",
    "fr/index.html",
    "fr/a-propos/index.html",
    "fr/soumettre/index.html",
    "fr/ecoles-partenaires/index.html",
    "fr/conditions-utilisation/index.html",
    # Alumo's privacy policy is now reproduced on this site (Aug 2026) instead
    # of linking out to alumo.ca, so these are full pages, not stubs.
    "privacy-policy/index.html",
    "fr/privacy-policy/index.html",
]
POLICY_STUBS = [
    "cookies-policy/index.html",
    "fr/cookies-policy/index.html",
]
# The French pages moved onto French slugs on 2026-08-28; each old URL keeps a
# redirect stub so existing links never break. Like the other stubs these carry
# no CSS/JS, so they are exempt from the live-page checks.
FR_SLUG_STUBS = [
    "fr/about-the-fund/index.html",
    "fr/how-to-apply/index.html",
    "fr/apply-now/index.html",
    "fr/partner-schools/index.html",
    "fr/terms-conditions/index.html",
]
PW_STUBS = [
    "past-winners/index.html",
    "past-winners/2/index.html",
    "fr/past-winners/index.html",
    "fr/past-winners/2/index.html",
]
# How to Submit merged into Submit Now on 2026-09-01 (and the French pair with
# it), so these two paths are redirect stubs. Like the other stubs they carry no
# CSS/JS, so they are exempt from the live-page checks — but they are still
# tracked, because a broken stub is a broken inbound link.
MERGED_STUBS = [
    "how-to-apply/index.html",
    "fr/comment-soumettre/index.html",
]
# Unlisted pages (Oct 2026): real pages with the live site's CSS/JS, reached only
# through a link Alumo emails. They get the live-page checks (forbidden strings,
# asset versions) but the opposite of the listing rules — see check P.
PRIVATE_PAGES = [
    "winners-fall-2026/index.html",
    "fr/gagnants-automne-2026/index.html",
]
ALL_PAGES = (LIVE_PAGES + PRIVATE_PAGES + POLICY_STUBS + PW_STUBS + FR_SLUG_STUBS
             + MERGED_STUBS)

MOJIBAKE_MARKERS = ["Ã©", "â€™", "�"]  # "Ã©", "â€™", "�"

STRUCTURAL_TAGS = {
    "html", "head", "body", "header", "footer", "main", "section", "div",
    "ul", "li", "form", "select", "nav", "a", "span", "p",
    "h1", "h2", "h3", "h4", "h5", "h6", "button", "label",
}

# Known-missing targets that are intentional (guide + template PDFs referenced
# by [hidden] buttons, to be unhidden when the PDFs land in /assets/docs/).
EXPECTED_MISSING_RE = re.compile(
    r"^/assets/docs/(application-guide-[a-z]{2}\.pdf"
    r"|template-(project-overview|team-members|action-plan)-[a-z]{2}\.pdf)$"
)

# AWAITING_CLIENT: files a page links to that the client has not supplied yet.
# Reported (so they are not forgotten) but not failures — except under
# --release, where each one fails. Remove each entry the moment its file lands
# in the repo — after that a missing file is a real break.
AWAITING_CLIENT = {
    # Winners page finance form, EN + FR — from Alumo (requested Oct 2026).
    "/assets/docs/winners-finance-form-en.pdf",
    "/assets/docs/winners-finance-form-fr.pdf",
}
awaiting_client = []  # (page, line, url)

FORBIDDEN_STRINGS = [
    "Consent preferences",
    "Gérer mon consentement",
    "CAD 1.2M",
    "engagment",
    "inititatives",
    "parraînage",
    "Recurrent projects",
    "recognised",   # special-cased: allowed inside name=/id= attribute values
    "postuler",
]

findings = []       # real problems
expected_missing = []  # intentional gaps, reported separately


def add(page, kind, detail):
    findings.append((page, kind, detail))


def line_of(text, index):
    return text.count("\n", 0, index) + 1


# ---------------------------------------------------------------- check 2
class TagBalanceChecker(HTMLParser):
    def __init__(self, page):
        super().__init__(convert_charrefs=True)
        self.page = page
        self.stack = []  # (tag, line)

    def handle_starttag(self, tag, attrs):
        if tag in STRUCTURAL_TAGS:
            self.stack.append((tag, self.getpos()[0]))

    def handle_startendtag(self, tag, attrs):
        pass  # self-closed: nothing pushed

    def handle_endtag(self, tag):
        if tag not in STRUCTURAL_TAGS:
            return
        line = self.getpos()[0]
        if self.stack and self.stack[-1][0] == tag:
            self.stack.pop()
            return
        open_tags = [t for t, _ in self.stack]
        if tag in open_tags:
            # close it, flagging everything above as unclosed/mismatched
            while self.stack:
                t, l = self.stack.pop()
                if t == tag:
                    break
                add(self.page, "tag-mismatch",
                    "<%s> opened at line %d closed implicitly by </%s> at line %d"
                    % (t, l, tag, line))
        else:
            add(self.page, "tag-mismatch",
                "stray </%s> at line %d with no matching open tag" % (tag, line))

    def finish(self):
        for t, l in self.stack:
            add(self.page, "tag-unclosed",
                "<%s> opened at line %d never closed" % (t, l))


# ---------------------------------------------------------------- check 3
ATTR_RE = re.compile(r"""\b(?:href|src|action)\s*=\s*(["'])(/[^"']*)\1""",
                     re.IGNORECASE)


def check_links(page, text):
    for m in ATTR_RE.finditer(text):
        url = m.group(2)
        if url.startswith("//"):  # protocol-relative external
            continue
        line = line_of(text, m.start())
        path = url.split("#", 1)[0].split("?", 1)[0]
        if path == "":
            continue  # pure fragment like "/#x" already handled: path "/" below
        if path.endswith("/"):
            rel = path.lstrip("/") + "index.html"
            if path == "/":
                rel = "index.html"
        else:
            rel = path.lstrip("/")
        target = os.path.join(ROOT, *rel.split("/"))
        if os.path.isfile(target):
            continue
        if EXPECTED_MISSING_RE.match(path):
            expected_missing.append((page, line, url))
            continue
        if path in AWAITING_CLIENT:
            awaiting_client.append((page, line, url))
            continue
        add(page, "broken-link",
            "line %d: %s -> missing target %s" % (line, url, rel))


# ---------------------------------------------------------------- check 4
NAME_ID_ATTR_RE = re.compile(r"""\b(?:name|id)\s*=\s*(["'])(.*?)\1""",
                             re.IGNORECASE)


def check_forbidden(page, text):
    name_id_spans = [(m.start(2), m.end(2)) for m in NAME_ID_ATTR_RE.finditer(text)]
    low = text.lower()
    for needle in FORBIDDEN_STRINGS:
        nlow = needle.lower()
        start = 0
        while True:
            i = low.find(nlow, start)
            if i == -1:
                break
            start = i + 1
            if needle == "recognised" and any(a <= i < b for a, b in name_id_spans):
                continue  # allowed inside name=/id= attribute values
            snippet = text[max(0, i - 40):i + len(needle) + 40].replace("\n", " ")
            add(page, "forbidden-string",
                "line %d: %r found: ...%s..." % (line_of(text, i), needle, snippet))


# ---------------------------------------------------------------- check 5
CSS_V_RE = re.compile(r"""/?css/style\.css\?v=([^"'&\s]+)""")
JS_V_RE = re.compile(r"""/?js/main\.js\?v=([^"'&\s]+)""")


def check_asset_versions(texts):
    css_v, js_v = {}, {}
    # Private pages reuse the live CSS/JS, so a version bump that misses them
    # is the same stale-cache bug as one that misses a live page.
    for page in LIVE_PAGES + PRIVATE_PAGES:
        text = texts.get(page)
        if text is None:
            continue
        cm = CSS_V_RE.findall(text)
        jm = JS_V_RE.findall(text)
        if not cm:
            add(page, "asset-version", "no reference to css/style.css?v=")
        else:
            css_v[page] = sorted(set(cm))
        if not jm:
            add(page, "asset-version", "no reference to js/main.js?v=")
        else:
            js_v[page] = sorted(set(jm))
    for label, seen in (("css/style.css", css_v), ("js/main.js", js_v)):
        for page, vals in seen.items():
            if len(vals) > 1:
                add(page, "asset-version",
                    "%s referenced with multiple versions on one page: %s"
                    % (label, vals))
        distinct = sorted({v for vals in seen.values() for v in vals})
        if len(distinct) > 1:
            detail = ", ".join("%s=%s" % (p, "/".join(v)) for p, v in sorted(seen.items()))
            add("(site-wide)", "asset-version",
                "%s ?v= differs across live/private pages: %s" % (label, detail))


# ---------------------------------------------------------------- check P
ROBOTS_META_RE = re.compile(r"""<meta\s+name=["']robots["']\s+content=["']([^"']*)["']""",
                            re.IGNORECASE)
HREF_RE = re.compile(r"""\bhref\s*=\s*["']([^"'#?]*)""", re.IGNORECASE)
# Any versioned site stylesheet or script: (path, version).
ASSET_V_RE = re.compile(r"""\b(?:href|src)\s*=\s*["']/?((?:css|js)/[^"'?#]+)\?v=([^"'&#\s]+)""",
                        re.IGNORECASE)
ANALYTICS_RE = re.compile(r"""\bsrc\s*=\s*["']/?js/analytics\.js""", re.IGNORECASE)


def check_private_assets(texts, page, text):
    """A shared file loaded at a different ?v= than on the live pages (check 5
    covers only style.css and main.js; the private pages also reuse
    /apply-now/'s stylesheets, which carry their field styles)."""
    live_v = {}
    for live in LIVE_PAGES:
        for path, v in ASSET_V_RE.findall(texts.get(live) or ""):
            live_v.setdefault(path, set()).add(v)
    for path, v in sorted(set(ASSET_V_RE.findall(text))):
        if path in live_v and live_v[path] != {v}:
            add(page, "private-page", "%s?v=%s, but the live pages load it at ?v=%s"
                % (path, v, "/".join(sorted(live_v[path]))))
    # No consent banner over a private form, and nothing recorded about it.
    if ANALYTICS_RE.search(text):
        add(page, "private-page", "loads js/analytics.js — private pages must not")


def private_url_path(page):
    """'winners-fall-2026/index.html' -> '/winners-fall-2026/'."""
    return "/" + page[:-len("index.html")]


def check_private_pages(texts):
    sitemap_path = os.path.join(ROOT, "sitemap.xml")
    sitemap = (open(sitemap_path, encoding="utf-8").read()
               if os.path.isfile(sitemap_path) else "")
    robots_path = os.path.join(ROOT, "robots.txt")
    robots = (open(robots_path, encoding="utf-8").read()
              if os.path.isfile(robots_path) else "")
    for page in PRIVATE_PAGES:
        text = texts.get(page)
        if text is None:
            continue
        m = ROBOTS_META_RE.search(text)
        content = m.group(1).replace(" ", "").lower() if m else ""
        if "noindex" not in content.split(",") or "nofollow" not in content.split(","):
            add(page, "private-page", "missing <meta name=\"robots\" content=\"noindex, nofollow\">")
        url = private_url_path(page)
        if re.search(r"<loc>[^<]*" + re.escape(url) + r"</loc>", sitemap):
            add(page, "private-page", "listed in sitemap.xml (%s) — unlisted pages must not be" % url)
        # A Disallow line would publish the URL to anyone reading robots.txt
        # and stop crawlers from ever seeing the noindex.
        if url.rstrip("/") in robots:
            add(page, "private-page", "named in robots.txt (%s) — the noindex meta is the control" % url)
        # Only the other private pages (the language switcher) may link here.
        for other in LIVE_PAGES:
            other_text = texts.get(other) or ""
            if any(h.rstrip("/") + "/" == url for h in HREF_RE.findall(other_text) if h):
                add(other, "private-page", "links to the unlisted page %s" % url)
        check_private_assets(texts, page, text)


# ---------------------------------------------------------------- check 6
# The submission window (data-opens-at / data-closes-at, read by /js/main.js).
# Checked per TAG, not per page: the About card was missed the first time the
# dates were wired up, and a page-level count would not notice one element
# with only an opening date next to another with only a closing date.
# The offset is required because without one every browser reads the date in
# its own local time zone (and api/apply.php refuses such a closes_at).
# Scoped to LIVE_PAGES on purpose: a page with its own deadline (e.g. the
# winners pages in PRIVATE_PAGES) keeps it out of this one site-wide window.
COMMENT_RE = re.compile(r"<!--.*?-->", re.S)
SCHED_TAG_RE = re.compile(r"<[a-zA-Z][^>]*\bdata-(?:opens|closes)-at\s*=[^>]*>")
OPENS_RE = re.compile(r'\bdata-opens-at\s*=\s*"([^"]*)"')
CLOSES_RE = re.compile(r'\bdata-closes-at\s*=\s*"([^"]*)"')
# Same pattern as /js/main.js and api/_lib.php schedule_time().
ISO_OFFSET_RE = re.compile(r"^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?([+-]\d{2}:\d{2}|Z)$")
# Server-side copies of the close date: (file, pattern, must be present).
# The committed default the release gates on, and the documented example a
# new api/config.php is copied from (an explicit '' there is skipped). Only
# the TOP-LEVEL closes_at (4-space indent): the winners block has its own.
SERVER_CLOSES = [
    ("api/apply.php", re.compile(r"^const DEFAULT_CLOSES_AT = '([^']*)';", re.M), True),
    ("api/config.example.php", re.compile(r"^    'closes_at'\s*=>\s*'([^']+)'", re.M), False),
]


def parse_schedule_date(value):
    """datetime for an ISO date with an explicit offset, else None."""
    if not ISO_OFFSET_RE.match(value):
        return None
    try:
        return datetime.fromisoformat(value.replace("Z", "+00:00"))
    except ValueError:
        return None


def check_schedule(texts):
    opens, closes = set(), set()
    for page in LIVE_PAGES:
        text = texts.get(page)
        if text is None:
            continue
        # Blank out comments (keeping line numbers) so prose about the
        # attributes is never mistaken for a scheduled element.
        code = COMMENT_RE.sub(lambda m: re.sub(r"[^\n]", " ", m.group(0)), text)
        for m in SCHED_TAG_RE.finditer(code):
            tag = m.group(0)
            line = line_of(code, m.start())
            o, c = OPENS_RE.findall(tag), CLOSES_RE.findall(tag)
            if len(o) != 1 or len(c) != 1:
                add(page, "schedule", "line %d: a scheduled tag needs exactly one "
                    "data-opens-at and one data-closes-at (has %d and %d)"
                    % (line, len(o), len(c)))
                continue
            od, cd = parse_schedule_date(o[0]), parse_schedule_date(c[0])
            for label, raw, parsed in (("data-opens-at", o[0], od),
                                       ("data-closes-at", c[0], cd)):
                if parsed is None:
                    add(page, "schedule", "line %d: %s=%r is not an ISO date with "
                        "an explicit offset (e.g. 2026-10-15T23:59:59-04:00)"
                        % (line, label, raw))
            if od is not None and cd is not None and od >= cd:
                add(page, "schedule", "line %d: data-opens-at %s is not before "
                    "data-closes-at %s" % (line, o[0], c[0]))
            opens.add(o[0])
            closes.add(c[0])
    for label, vals in (("data-opens-at", opens), ("data-closes-at", closes)):
        if len(vals) > 1:
            add("(site-wide)", "schedule", "%s differs across live pages: %s"
                % (label, sorted(vals)))
    # The server's own close date has to move with the pages: if it drifts,
    # api/apply.php refuses applications the pages invite (or keeps taking
    # them after the advertised deadline). forms-smoke only reads it back.
    for path, regex, required in SERVER_CLOSES:
        try:
            src = io.open(os.path.join(ROOT, *path.split("/")), encoding="utf-8").read()
        except (IOError, OSError) as e:
            add(path, "schedule", "cannot read: %s" % e)
            continue
        found = regex.findall(src)
        if not found:
            if required:
                add(path, "schedule", "no line matching %s" % regex.pattern)
            continue
        for value in found:
            if parse_schedule_date(value) is None:
                add(path, "schedule", "closes_at %r is not an ISO date with an "
                    "explicit offset" % value)
            elif len(closes) == 1 and value not in closes:
                add(path, "schedule", "closes_at %s differs from data-closes-at %s "
                    "on the live pages" % (value, next(iter(closes))))


# ---------------------------------------------------------------- driver
def main():
    texts = {}
    for page in ALL_PAGES:
        path = os.path.join(ROOT, *page.split("/"))
        if not os.path.isfile(path):
            add(page, "missing-file", "tracked page file does not exist")
            continue
        raw = open(path, "rb").read()
        try:
            text = raw.decode("utf-8", errors="strict")
        except UnicodeDecodeError as e:
            add(page, "encoding", "not strict UTF-8: %s" % e)
            text = raw.decode("utf-8", errors="replace")
        texts[page] = text

        # 1. mojibake markers
        for marker in MOJIBAKE_MARKERS:
            i = text.find(marker)
            if i != -1:
                add(page, "mojibake",
                    "marker %r at line %d: ...%s..."
                    % (marker, line_of(text, i),
                       text[max(0, i - 30):i + 30].replace("\n", " ")))

        # 2. tag balance
        checker = TagBalanceChecker(page)
        checker.feed(text)
        checker.close()
        checker.finish()

        # 3. internal links
        check_links(page, text)

        # 4. forbidden strings (live + private pages)
        if page in LIVE_PAGES or page in PRIVATE_PAGES:
            check_forbidden(page, text)

    # 5. asset version consistency (live + private pages)
    check_asset_versions(texts)

    # 6. one submission window (live pages only)
    check_schedule(texts)

    # P. private pages stay unlisted
    check_private_pages(texts)

    # --release: nothing the client still owes may ship.
    release = "--release" in sys.argv[1:]
    if release:
        for page, line, url in awaiting_client:
            add(page, "awaiting-client",
                "line %d: %s is still owed by the client — it would 404 in this release"
                % (line, url))

    out = io.StringIO()
    if expected_missing:
        out.write("EXPECTED-known-missing (allowed, not failures):\n")
        for page, line, url in expected_missing:
            out.write("  %s line %d: %s\n" % (page, line, url))
        out.write("\n")
    if awaiting_client and not release:
        out.write("AWAITING_CLIENT (files the client still owes; not failures):\n")
        for page, line, url in awaiting_client:
            out.write("  %s line %d: %s\n" % (page, line, url))
        out.write("\n")
    if findings:
        out.write("FAILURES (%d):\n" % len(findings))
        for page, kind, detail in findings:
            out.write("  [%s] %s: %s\n" % (kind, page, detail))
    else:
        out.write("ALL CHECKS PASSED for %d pages.\n" % len(ALL_PAGES))
    sys.stdout.write(out.getvalue())
    return 1 if findings else 0


if __name__ == "__main__":
    sys.exit(main())
