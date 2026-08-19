# -*- coding: utf-8 -*-
"""Self-check for launch hygiene: canonicals, og:url, sitemap integrity."""
import io
import os
import sys
import xml.etree.ElementTree as ET
from html.parser import HTMLParser

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BASE = "https://alumoimpact.ca"
errors = []


class HeadScan(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.canonicals = []
        self.og = {}
        self.twitter_card = None

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == "link" and a.get("rel") == "canonical":
            self.canonicals.append(a.get("href"))
        if tag == "meta" and a.get("property", "").startswith("og:"):
            self.og[a["property"]] = a.get("content")
        if tag == "meta" and a.get("name") == "twitter:card":
            self.twitter_card = a.get("content")


pages = []
for dirpath, dirnames, filenames in os.walk(ROOT):
    dirnames[:] = [d for d in dirnames if d not in {".git", "node_modules", "_source", "_tools", "api", "ci"}]
    if "index.html" in filenames:
        pages.append(os.path.join(dirpath, "index.html"))
pages.sort()

if len(pages) != 20:
    errors.append("Expected 20 pages, found %d" % len(pages))

for fp in pages:
    rel = os.path.relpath(fp, ROOT).replace(os.sep, "/")
    with io.open(fp, "r", encoding="utf-8") as f:
        src = f.read()
    p = HeadScan()
    p.feed(src)
    if len(p.canonicals) != 1:
        errors.append("%s: %d canonical links" % (rel, len(p.canonicals)))
        continue
    canon = p.canonicals[0]
    expected = BASE + "/" + rel[: -len("index.html")].rstrip("/")
    expected = expected.rstrip("/") + "/" if rel != "index.html" else BASE + "/"
    if canon != expected:
        errors.append("%s: canonical %r != expected %r" % (rel, canon, expected))
    if p.og.get("og:url") != canon:
        errors.append("%s: og:url %r != canonical %r" % (rel, p.og.get("og:url"), canon))
    for req in ("og:type", "og:site_name", "og:title", "og:description",
                "og:locale", "og:image", "og:image:alt"):
        if not p.og.get(req):
            errors.append("%s: missing %s" % (rel, req))
    # landscape social card (twitter summary_large_image crops ~2:1)
    if p.og.get("og:image") != BASE + "/assets/img/og-card.png":
        errors.append("%s: og:image %r" % (rel, p.og.get("og:image")))
    if p.og.get("og:image:width") != "1200" or p.og.get("og:image:height") != "630":
        errors.append("%s: og:image dims %r x %r" % (
            rel, p.og.get("og:image:width"), p.og.get("og:image:height")))
    if p.twitter_card != "summary_large_image":
        errors.append("%s: twitter:card = %r" % (rel, p.twitter_card))
    want_locale = "fr_CA" if rel.startswith("fr/") else "en_CA"
    if p.og.get("og:locale") != want_locale:
        errors.append("%s: og:locale %r" % (rel, p.og.get("og:locale")))

# ---------- sitemap ----------
sm_path = os.path.join(ROOT, "sitemap.xml")
tree = ET.parse(sm_path)  # raises if not well-formed XML
ns = {"sm": "http://www.sitemaps.org/schemas/sitemap/0.9",
      "xhtml": "http://www.w3.org/1999/xhtml"}
urls = tree.getroot().findall("sm:url", ns)
if len(urls) != 20:
    errors.append("sitemap has %d <url> entries" % len(urls))
locs = []
for u in urls:
    loc = u.find("sm:loc", ns).text
    locs.append(loc)
    if not loc.startswith(BASE + "/") or not loc.endswith("/"):
        errors.append("sitemap loc malformed: %s" % loc)
    rel = loc[len(BASE):].strip("/")
    folder = os.path.join(ROOT, rel.replace("/", os.sep)) if rel else ROOT
    if not os.path.isfile(os.path.join(folder, "index.html")):
        errors.append("sitemap loc has no index.html on disk: %s" % loc)
    alts = {l.get("hreflang"): l.get("href") for l in u.findall("xhtml:link", ns)}
    if set(alts) != {"en", "fr", "x-default"}:
        errors.append("%s: hreflang set %s" % (loc, sorted(alts)))
    elif alts["x-default"] != alts["en"] or not alts["fr"].startswith(BASE + "/fr/"):
        errors.append("%s: alternate targets wrong: %s" % (loc, alts))
    else:
        for href in alts.values():
            r = href[len(BASE):].strip("/")
            fdr = os.path.join(ROOT, r.replace("/", os.sep)) if r else ROOT
            if not os.path.isfile(os.path.join(fdr, "index.html")):
                errors.append("%s: alternate target missing on disk: %s" % (loc, href))
if len(set(locs)) != len(locs):
    errors.append("duplicate locs in sitemap")

# every page on disk is in the sitemap
disk_urls = set()
for fp in pages:
    rel = os.path.relpath(os.path.dirname(fp), ROOT).replace(os.sep, "/")
    disk_urls.add(BASE + "/" if rel == "." else BASE + "/" + rel + "/")
if disk_urls != set(locs):
    errors.append("sitemap/disk mismatch: only-disk=%s only-sitemap=%s"
                  % (sorted(disk_urls - set(locs)), sorted(set(locs) - disk_urls)))

# ---------- og-card image on disk ----------
card = os.path.join(ROOT, "assets", "img", "og-card.png")
if not os.path.isfile(card):
    errors.append("assets/img/og-card.png missing on disk")
else:
    try:
        from PIL import Image
        size = Image.open(card).size
        if size != (1200, 630):
            errors.append("og-card.png is %sx%s, expected 1200x630" % size)
    except ImportError:
        pass  # Pillow not installed — skip the dimension check

# ---------- robots ----------
with io.open(os.path.join(ROOT, "robots.txt"), "r", encoding="utf-8") as f:
    r = f.read()
for needle in ("User-agent: *", "Allow: /", "Disallow: /api/",
               "Sitemap: https://alumoimpact.ca/sitemap.xml"):
    if needle not in r:
        errors.append("robots.txt missing: %s" % needle)

if errors:
    print("FAIL")
    for e in errors:
        print(" -", e)
    sys.exit(1)
print("ALL CHECKS PASSED (%d pages, %d sitemap URLs)" % (len(pages), len(locs)))
for loc in locs:
    print(loc)
