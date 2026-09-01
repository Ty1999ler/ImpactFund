# -*- coding: utf-8 -*-
"""
Whole-site integrity regression net for the Alumo website.

Checks, for the 25 tracked page files (12 live + 4 policy stubs + 4 past-winners
stubs + 5 French-slug redirect stubs):
  1. Strict UTF-8 decodability + no mojibake markers.
  2. Structural tag balance (html.parser based).
  3. Internal href/src/action targets exist (with an expected-missing allowlist).
  4. Forbidden strings on the 12 live pages.
  5. Identical css/style.css?v= and js/main.js?v= across all live pages.

Exit code 0 = clean, 1 = findings.
"""
import io
import os
import re
import sys
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
ALL_PAGES = LIVE_PAGES + POLICY_STUBS + PW_STUBS + FR_SLUG_STUBS + MERGED_STUBS

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
    for page in LIVE_PAGES:
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
                "%s ?v= differs across live pages: %s" % (label, detail))


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

        # 4. forbidden strings (live pages only)
        if page in LIVE_PAGES:
            check_forbidden(page, text)

    # 5. asset version consistency (live pages only)
    check_asset_versions(texts)

    out = io.StringIO()
    if expected_missing:
        out.write("EXPECTED-known-missing (allowed, not failures):\n")
        for page, line, url in expected_missing:
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
