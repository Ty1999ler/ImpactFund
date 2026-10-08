# -*- coding: utf-8 -*-
"""
Browser test for the submission window and the ?preview-at= site preview
(/js/main.js). Run from anywhere:  python _tools/test_schedule_preview.py

What it does:
  - serves the site from this checkout on 127.0.0.1 (random free port), plus a
    small harness page under /__schedtest__/ that only this test serves;
  - opens the harness in headless Edge (or Chrome: set BROWSER=<path>). The
    harness loads each case in a same-origin iframe, reads the resulting DOM
    (which scheduled cards/notes are hidden, the preview banner, the stored
    preview) and POSTs it back here, where the assertions run.
  Why not --dump-dom: on Windows the Edge launcher detaches, so the dumped DOM
  never reaches stdout, and --dump-dom is one load per process, so it could
  not test that a preview sticks across pages. An iframe shares the tab's
  sessionStorage, so the stickiness steps below are real.

Cases, for EN + FR home / About / Submit / Partners (each with fresh storage):
  no parameter -> the state the real clock gives (open until the close)
  ?preview-at=2026-10-15T23:59:58-04:00 -> open, banner
  ?preview-at=2026-10-15T23:59:59-04:00 -> closed, notes shown, banner
  ?preview-at=2026-10-16T09:00:00-04:00 -> closed, notes shown, banner + date
  ?preview-at=2026-08-31T18:29:59-04:00 -> closed (before the window opens)
  ?preview-form=1 -> open, notes hidden, no banner
  ?preview-form=1&preview-at=<after close> -> open (forced), banner
  ?preview-at=garbage / %E0 / a date without offset -> like no parameter
Then a stickiness walk through several pages, the banner's exit link,
preview-at given twice (the last one wins, the exit link drops both), and
layout checks in a 375px-wide frame (no sideways overflow; the banner clears
the menu button). At 375/768/1024px the open burger menu must cover the
banner (the language switcher stays clickable), and scrolled to the very end
the footer's last lines must sit clear of it.
A second run loads the same harness as http://alumoimpact.ca:<port>/ (the
browser maps that name to 127.0.0.1), where ?preview-at= must do nothing and
a stored preview must be dropped, while ?preview-form=1 still works.
More runs, the same way, under the other host names main.js knows:
  before.alumoimpact.ca / open.alumoimpact.ca / closed.alumoimpact.ca (the
    pinned preview copies): every page closed (before the window) / open /
    closed with no parameter, the before-copy / open-copy / closed-copy
    banner (before: "on <the opening moment>", closed: "on <the close
    moment>"; undated on pages with nothing scheduled) and its "Go to
    staging" link (same path, other parameters and the hash kept, preview
    ones dropped, focus outline); with the clock before the window, at its
    edges and long after it; ?preview-at= (and a stored one) wins over the
    pin and its exit link comes back to it; ?preview-form=1 still forces
    open (with the open-copy banner on all three); 375px layout, menu over
    the banner, footer clear of it. The before copy also loads a page only
    this test serves (/__schedtest__/probe.html: the window's element plus
    one that opens on PROBE_OPENS, between CLOCK_BEFORE and the opening),
    which tells the clocks apart: before the window it must follow the real
    clock (probe still hidden), from the opening on the window's last second
    before it (probe shown, the window's element still closed);
  www.closed.alumoimpact.ca (cPanel's www. alias): pinned the same way;
  www.alumoimpact.ca: production, like the bare name;
  staging.alumoimpact.ca: the real clock, ?preview-at= honoured;
  september.alumoimpact.ca (retired copy): ?preview-at= no longer honoured.
The clock is faked per step by a cookie the harness sets: when it is there,
this server puts a small script at the top of the page's <head> that pins
Date to that moment (it also proves itself: the staging steps with the
clock after the close must come out closed).
With --screenshots it also saves 375px screenshots of the
cards and notes (and the pinned copies' banners) for a human look, in
<temp>/sif-schedule-screenshots/ (never inside the repo).

Exit code 0 = all assertions passed, 1 = failures, 2 = could not run.
"""
import datetime
import http.server
import io
import json
import os
import re
import shutil
import socketserver
import subprocess
import sys
import tempfile
import threading
import time
import urllib.parse

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
DEFAULT_BROWSERS = [
    r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe",
    r"C:\Program Files\Microsoft\Edge\Application\msedge.exe",
    r"C:\Program Files\Google\Chrome\Application\chrome.exe",
    "/usr/bin/microsoft-edge", "/usr/bin/google-chrome", "/usr/bin/chromium",
]

PAGES = {
    # path: (language, number of scheduled elements on the page)
    "/": ("en", 2),
    "/about-the-fund/": ("en", 1),
    "/apply-now/": ("en", 2),
    "/partner-schools/": ("en", 1),
    "/fr/": ("fr", 2),
    "/fr/a-propos/": ("fr", 1),
    "/fr/soumettre/": ("fr", 2),
    "/fr/ecoles-partenaires/": ("fr", 1),
}

BANNER_PREFIX = {
    "en": "Preview: the site as it will look on ",
    "fr": "Aperçu\u00a0: le site tel qu'il apparaîtra le ",
}
BANNER_EXIT = {"en": "Exit preview", "fr": "Quitter l'aperçu"}
AFTER = "2026-10-16T09:00:00-04:00"
AFTER_TEXT = {
    "en": "October 16, 2026, 9:00 a.m. ET",
    "fr": "16 octobre 2026 à 9\u00a0h\u00a000 (heure de l'Est)",
}
LAST_SECOND_TEXT = {
    "en": "October 15, 2026, 11:59 p.m. ET",
    "fr": "15 octobre 2026 à 23\u00a0h\u00a059 (heure de l'Est)",
}
# The opening moment (2026-08-31T18:30:00-04:00), as the before copy names it.
OPENS_TEXT = {
    "en": "August 31, 2026, 6:30 p.m. ET",
    "fr": "31 août 2026 à 18\u00a0h\u00a030 (heure de l'Est)",
}

ISO_RE = re.compile(r"^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?([+-]\d{2}:\d{2}|Z)$")

# The pinned preview copies (before. / open. / closed.alumoimpact.ca). {date}
# is " on <moment>" / " le <moment>" on pages with something scheduled (the
# opening for before., the close for closed.), else "".
PINNED_TEXT = {
    "before": {
        "en": "Preview copy: the site as it looks before submissions open{date}. ",
        "fr": "Copie d'aperçu\u00a0: le site tel qu'il apparaît avant l'ouverture des "
              "soumissions{date}. ",
    },
    "open": {
        "en": "Preview copy: the site as it looks while submissions are open. ",
        "fr": "Copie d'aperçu\u00a0: le site tel qu'il apparaît pendant la période de "
              "soumission. ",
    },
    "closed": {
        "en": "Preview copy: the site as it will look after submissions close{date}. ",
        "fr": "Copie d'aperçu\u00a0: le site tel qu'il apparaîtra après la fermeture des "
              "soumissions{date}. ",
    },
}
PINNED_DATE = {"before": OPENS_TEXT, "closed": LAST_SECOND_TEXT}
PINNED_DATE_ON = {"en": " on ", "fr": " le "}
PINNED_LINK = {"en": "Go to staging", "fr": "Aller au site d'essai"}
STAGING_URL = "https://staging.alumoimpact.ca"
PINNED_HOSTS = {"before": "before.alumoimpact.ca", "open": "open.alumoimpact.ca",
                "closed": "closed.alumoimpact.ca"}
# The state each pinned copy shows: before. is outside the window too.
PINNED_STATE = {"before": "closed", "open": "open", "closed": "closed"}
STAGING_HOST = "staging.alumoimpact.ca"
RETIRED_HOST = "september.alumoimpact.ca"
# Fake clocks (cookie -> the page's Date pinned there) around the window
# [2026-08-31T18:30:00-04:00, 2026-10-15T23:59:59-04:00).
CLOCK_BEFORE = "2026-08-15T12:00:00-04:00"
CLOCK_OPENS = "2026-08-31T18:30:00-04:00"
CLOCK_INSIDE = "2026-10-01T12:00:00-04:00"
CLOCK_LAST_OPEN = "2026-10-15T23:59:58-04:00"
CLOCK_CLOSES = "2026-10-15T23:59:59-04:00"
CLOCK_LONG_AFTER = "2026-11-20T12:00:00-05:00"
FAKE_CLOCK_COOKIE = "sifFakeNow"
# /__schedtest__/probe.html: an element opening here, after CLOCK_BEFORE and
# before the window, shows which clock the before copy used.
PROBE_OPENS = "2026-08-20T00:00:00-04:00"
PROBE_PATH = "/__schedtest__/probe.html"


def window_raw():
    """The (opens, closes) pair the pages carry, as written in the home page."""
    html = io.open(os.path.join(ROOT, "index.html"), encoding="utf-8").read()
    o = re.search(r'data-opens-at="([^"]+)"', html).group(1)
    c = re.search(r'data-closes-at="([^"]+)"', html).group(1)
    assert ISO_RE.match(o) and ISO_RE.match(c), (o, c)
    return o, c


def window_from_pages():
    """The (opens, closes) pair the pages carry — read from the home page."""
    o, c = window_raw()
    return datetime.datetime.fromisoformat(o), datetime.datetime.fromisoformat(c)


def real_state():
    opens, closes = window_from_pages()
    now = datetime.datetime.now(datetime.timezone.utc)
    return "open" if opens <= now < closes else "closed"


# ---------------------------------------------------------------- the plan
def plan_builder():
    """(steps, step): an empty plan and the function that appends to it."""
    steps = []

    def step(name, url, expect, banner=None, clear=True, width=1280, height=900,
             measure=None, lang=None, date_text=None, stored=Ellipsis,
             action=None, probe=None, preset=None, exit_href=None, clock=None):
        steps.append({
            "name": name, "url": url, "clear": clear, "width": width,
            "height": height, "measure": measure or [],
            # action: "menu" opens the burger, "bottom" scrolls to the end,
            # "focus" focuses the banner's link;
            # probe: selectors hit-tested afterwards; preset: stored preview
            # written before the load; clock: the page's Date pinned to this
            # moment (None = the real clock).
            "action": action, "probe": probe or [], "preset": preset,
            "clock": clock,
            # expectations (used here, not by the harness). banner: True =
            # the ?preview-at= banner, "open"/"closed" = a pinned copy's.
            "_expect": expect, "_banner": banner, "_lang": lang,
            "_date": date_text, "_stored": stored, "_exit": exit_href,
        })
    return steps, step


def build_plan():
    steps, step = plan_builder()
    real = real_state()

    for path, (lang, _count) in PAGES.items():
        step(path + " no parameter", path, real, banner=False, lang=lang)
        step(path + " last second open", path + "?preview-at=2026-10-15T23:59:58-04:00",
             "open", banner=True, lang=lang)
        step(path + " close moment", path + "?preview-at=2026-10-15T23:59:59-04:00",
             "closed", banner=True, lang=lang, date_text=LAST_SECOND_TEXT[lang])
        step(path + " morning after", path + "?preview-at=" + AFTER,
             "closed", banner=True, lang=lang, date_text=AFTER_TEXT[lang],
             stored=AFTER)
        step(path + " before opening", path + "?preview-at=2026-08-31T18:29:59-04:00",
             "closed", banner=True, lang=lang)
        step(path + " preview-form", path + "?preview-form=1", "open",
             banner=False, lang=lang)
        step(path + " preview-form beats preview-at",
             path + "?preview-form=1&preview-at=" + AFTER, "open",
             banner=True, lang=lang)
        step(path + " garbage", path + "?preview-at=garbage", real,
             banner=False, lang=lang, stored=None)
        step(path + " bad escape", path + "?preview-at=%E0", real,
             banner=False, lang=lang, stored=None)
        step(path + " no offset", path + "?preview-at=2026-10-16T09:00:00", real,
             banner=False, lang=lang, stored=None)

    # Stickiness: one tab, storage cleared only at the start.
    step("sticky 1: link with preview", "/?preview-at=" + AFTER, "closed",
         banner=True, lang="en", date_text=AFTER_TEXT["en"], stored=AFTER)
    step("sticky 2: About, no parameter", "/about-the-fund/", "closed",
         banner=True, clear=False, lang="en", stored=AFTER)
    step("sticky 3: FR Partners", "/fr/ecoles-partenaires/", "closed",
         banner=True, clear=False, lang="fr", date_text=AFTER_TEXT["fr"], stored=AFTER)
    step("sticky 4: terms page (nothing scheduled) still shows the banner",
         "/terms-conditions/", "none", banner=True, clear=False, lang="en", stored=AFTER)
    step("sticky 5: garbage keeps the stored preview", "/apply-now/?preview-at=garbage",
         "closed", banner=True, clear=False, lang="en", stored=AFTER)
    step("sticky 6: a new value replaces it",
         "/apply-now/?preview-at=2026-10-15T12:00:00-04:00", "open", banner=True,
         clear=False, lang="en", stored="2026-10-15T12:00:00-04:00")
    step("sticky 7: still sticks", "/partner-schools/", "open", banner=True,
         clear=False, lang="en", stored="2026-10-15T12:00:00-04:00")
    step("sticky 8: the banner's exit link", "@exit", real, banner=False,
         clear=False, lang="en", stored=None)
    step("sticky 9: preview gone on the next page", "/fr/", real, banner=False,
         clear=False, lang="fr", stored=None)
    step("exit link keeps other parameters and the hash",
         "/?preview-form=1&preview-at=" + AFTER + "#faq-section", "open",
         banner=True, lang="en", exit_href="/?preview-form=1&preview-at=off#faq-section")
    step("the exit link takes focus with an outline", "/fr/?preview-at=" + AFTER, "closed",
         banner=True, lang="fr", action="focus")

    # preview-at given twice: the last one wins and the exit link drops both.
    step("twice: the last preview-at wins",
         "/?preview-at=" + AFTER + "&preview-at=2026-10-15T12:00:00-04:00", "open",
         banner=True, lang="en", stored="2026-10-15T12:00:00-04:00",
         exit_href="/?preview-at=off")
    step("twice: its exit link ends the preview", "@exit", real, banner=False,
         clear=False, lang="en", stored=None)
    step("twice: an appended preview-at=off wins",
         "/apply-now/?preview-at=" + AFTER + "&preview-at=off", real, banner=False,
         lang="en", stored=None)
    step("twice: an appended date beats the earlier one",
         "/fr/?preview-form=1&preview-at=2026-10-15T12:00:00-04:00&preview-at=" + AFTER,
         "open", banner=True, lang="fr", date_text=AFTER_TEXT["fr"], stored=AFTER,
         exit_href="/fr/?preview-form=1&preview-at=off")

    # The open burger menu covers the banner, so the language switcher at the
    # bottom of the menu can still be clicked (every burger width).
    for path, (w, h) in [("/", (375, 812)), ("/fr/soumettre/", (375, 812)),
                         ("/apply-now/", (768, 1024)), ("/fr/", (1024, 768))]:
        step("open menu %dpx %s" % (w, path), path + "?preview-at=" + AFTER, "closed",
             banner=True, width=w, height=h, action="menu",
             probe=[".lang-switcher .lang-current"])
    # Scrolled to the very end, the footer's last lines are clear of the banner.
    for path, (w, h) in [("/", (375, 812)), ("/fr/soumettre/", (375, 812)),
                         ("/apply-now/", (768, 1024))]:
        step("page end %dpx %s" % (w, path), path + "?preview-at=" + AFTER, "closed",
             banner=True, width=w, height=h, action="bottom",
             probe=[".footer-bottom p", ".footer-legal a", "#alumo-consent-link"])

    # Layout at 375px (open state from the clock, closed state via preview).
    for path in ["/", "/about-the-fund/", "/fr/", "/fr/a-propos/"]:
        step("375px open card " + path, path + "?preview-at=2026-10-15T12:00:00-04:00",
             "open", banner=True, width=375, height=812,
             measure=[".period .apply-form-open .period-date",
                      ".period .apply-form-open", ".hamburger-menu-toggle-btn",
                      ".preview-banner"])
        step("375px closed card " + path, path + "?preview-at=" + AFTER,
             "closed", banner=True, width=375, height=812,
             measure=[".period .apply-form-closed .period-date",
                      ".period .apply-form-closed", ".hamburger-menu-toggle-btn",
                      ".preview-banner"])
    for path in ["/apply-now/", "/fr/soumettre/"]:
        step("375px Submit note " + path, path + "?preview-at=" + AFTER, "closed",
             banner=True, width=375, height=812,
             measure=[".apply-closed-notice", ".hamburger-menu-toggle-btn",
                      ".preview-banner"])
    for path in ["/partner-schools/", "/fr/ecoles-partenaires/"]:
        step("375px Partners note " + path, path + "?preview-at=" + AFTER, "closed",
             banner=True, width=375, height=812,
             measure=[".partners-update-note", ".partners-card",
                      ".hamburger-menu-toggle-btn", ".preview-banner"])
    return steps


PROD_HOST = "alumoimpact.ca"
WWW_PROD_HOST = "www.alumoimpact.ca"
WWW_CLOSED_HOST = "www.closed.alumoimpact.ca"


def build_prod_plan(label="production"):
    """Run with the harness served as http://alumoimpact.ca:<port>/ (or www.)."""
    steps, step = plan_builder()
    real = real_state()
    step(label + ": preview-at is ignored", "/apply-now/?preview-at=" + AFTER, real,
         banner=False, lang="en", stored=None)
    step(label + ": FR too", "/fr/?preview-at=" + AFTER, real,
         banner=False, lang="fr", stored=None)
    step(label + ": a stored preview is dropped", "/", real, banner=False,
         lang="en", preset=AFTER, stored=None)
    step(label + ": preview-form=1 still forces open",
         "/apply-now/?preview-form=1&preview-at=" + AFTER, "open", banner=False,
         lang="en", stored=None)
    return steps


def build_www_pinned_plan():
    """Run as http://www.closed.alumoimpact.ca:<port>/: the www. alias cPanel
    makes for a subdomain must pin like the bare name, not act as production.
    The clock is inside the window, so only the pin can make it closed."""
    steps, step = plan_builder()
    tag = "www.closed copy: "
    step(tag + "/apply-now/ closed with the clock inside the window", "/apply-now/",
         "closed", banner="closed", lang="en", stored=None, clock=CLOCK_INSIDE)
    step(tag + "/fr/ closed too", "/fr/", "closed", banner="closed", lang="fr",
         clock=CLOCK_INSIDE)
    at = "2026-10-15T12:00:00-04:00"
    step(tag + "preview-at honoured (a preview host)", "/?preview-at=" + at, "open",
         banner=True, lang="en", stored=at, clock=CLOCK_INSIDE)
    return steps


def build_pinned_plan(view):
    """Run as http://<view>.alumoimpact.ca:<port>/ (view = "before"/"open"/
    "closed"). state = what the pages show there: before. is outside the
    window too, so it shows the closed cards and notes like closed. does."""
    steps, step = plan_builder()
    state = PINNED_STATE[view]
    other = "closed" if state == "open" else "open"
    tag = view + " copy: "
    for path, (lang, _count) in PAGES.items():
        step(tag + path, path, state, banner=view, lang=lang, stored=None)
    # Nothing scheduled: the banner only, undated.
    step(tag + "terms page (nothing scheduled)", "/terms-conditions/", "none",
         banner=view, lang="en")
    step(tag + "FR privacy page (nothing scheduled)", "/fr/privacy-policy/", "none",
         banner=view, lang="fr")
    # Whatever the real clock says, the view holds.
    for label, clock in [("clock before the window", CLOCK_BEFORE),
                         ("clock at the opening", CLOCK_OPENS),
                         ("clock inside the window", CLOCK_INSIDE),
                         ("clock at the last open second", CLOCK_LAST_OPEN),
                         ("clock at the close", CLOCK_CLOSES),
                         ("clock long after the close", CLOCK_LONG_AFTER)]:
        step(tag + label + " /apply-now/", "/apply-now/", state, banner=view,
             lang="en", clock=clock)
        step(tag + label + " /fr/", "/fr/", state, banner=view, lang="fr", clock=clock)
        step(tag + label + " /fr/ecoles-partenaires/", "/fr/ecoles-partenaires/", state,
             banner=view, lang="fr", clock=clock)
    if view == "before":
        # Which clock: the real one before the window (the probe, which opens
        # after CLOCK_BEFORE, still hidden), the window's last second from its
        # opening on (probe shown, the window's element still closed).
        step(tag + "probe: real clock before the window", PROBE_PATH, "closed",
             banner=view, lang="en", clock=CLOCK_BEFORE)
        for label, clock in [("at the opening", CLOCK_OPENS),
                             ("inside the window", CLOCK_INSIDE),
                             ("long after the close", CLOCK_LONG_AFTER)]:
            step(tag + "probe: last second before opening, clock " + label, PROBE_PATH,
                 "mixed: closed, open", banner=view, lang="en", clock=clock)
    # The link to staging: same page, other parameters and the hash kept,
    # preview ones dropped.
    step(tag + "staging link keeps other parameters and the hash",
         "/fr/?a=1&preview-at=garbage&b=2#faq-section", state, banner=view, lang="fr",
         stored=None, exit_href=STAGING_URL + "/fr/?a=1&b=2#faq-section")
    step(tag + "preview-form=1 still forces open, open banner (left off the link)",
         "/apply-now/?preview-form=1", "open", banner="open", lang="en",
         exit_href=STAGING_URL + "/apply-now/")
    step(tag + "the banner link takes focus with an outline", "/", state, banner=view,
         lang="en", action="focus")
    # An explicit preview-at wins over the pin, sticks, and its exit link
    # comes back to the pinned view.
    at = AFTER if state == "open" else "2026-10-15T12:00:00-04:00"
    step(tag + "preview-at wins over the pin", "/?preview-at=" + at, other,
         banner=True, lang="en", stored=at)
    step(tag + "the preview sticks", "/fr/ecoles-partenaires/", other, banner=True,
         clear=False, lang="fr", stored=at)
    step(tag + "its exit link returns to the pinned view", "@exit", state,
         banner=view, clear=False, lang="fr", stored=None)
    step(tag + "still pinned on the next page", "/about-the-fund/", state, banner=view,
         clear=False, lang="en", stored=None)
    step(tag + "a stored preview wins too", "/partner-schools/", other, banner=True,
         lang="en", preset=at, stored=at)
    step(tag + "preview-at=off drops it", "/partner-schools/?preview-at=off", state,
         banner=view, lang="en", preset=at, stored=None,
         exit_href=STAGING_URL + "/partner-schools/")
    step(tag + "preview-form=1 beats a preview-at too",
         "/fr/soumettre/?preview-form=1&preview-at=" + AFTER, "open", banner=True,
         lang="fr", stored=AFTER)
    # Layout: the menu covers the banner, the footer's end clears it, nothing
    # scrolls sideways.
    for path, (w, h) in [("/", (375, 812)), ("/fr/soumettre/", (375, 812)),
                         ("/fr/", (1024, 768))]:
        step(tag + "open menu %dpx %s" % (w, path), path, state, banner=view,
             width=w, height=h, action="menu", probe=[".lang-switcher .lang-current"])
    for path, (w, h) in [("/", (375, 812)), ("/fr/soumettre/", (375, 812)),
                         ("/fr/ecoles-partenaires/", (768, 1024))]:
        step(tag + "page end %dpx %s" % (w, path), path, state, banner=view,
             width=w, height=h, action="bottom",
             probe=[".footer-bottom p", ".footer-legal a"])
    for path in ["/", "/fr/", "/fr/soumettre/", "/fr/privacy-policy/"]:
        step(tag + "375px " + path, path, "none" if "privacy" in path else state,
             banner=view, width=375, height=812,
             measure=[".hamburger-menu-toggle-btn", ".preview-banner"])
    return steps


def build_staging_plan():
    """Run as http://staging.alumoimpact.ca:<port>/ (the real clock)."""
    steps, step = plan_builder()
    real = real_state()
    step("staging: no parameter = the real clock", "/", real, banner=False, lang="en",
         stored=None)
    step("staging: FR Submit, real clock", "/fr/soumettre/", real, banner=False, lang="fr")
    step("staging: preview-at honoured", "/apply-now/?preview-at=" + AFTER, "closed",
         banner=True, lang="en", date_text=AFTER_TEXT["en"], stored=AFTER)
    # These also prove the fake clock works (the real one says open until the
    # close, so "closed" here can only come from it).
    step("staging: clock before the window", "/fr/", "closed", banner=False, lang="fr",
         clock=CLOCK_BEFORE)
    step("staging: clock inside the window", "/apply-now/", "open", banner=False,
         lang="en", clock=CLOCK_INSIDE)
    step("staging: clock at the last open second", "/", "open", banner=False,
         lang="en", clock=CLOCK_LAST_OPEN)
    step("staging: clock at the close", "/", "closed", banner=False, lang="en",
         clock=CLOCK_CLOSES)
    step("staging: clock long after the close", "/fr/ecoles-partenaires/", "closed",
         banner=False, lang="fr", clock=CLOCK_LONG_AFTER)
    # Control for the before copy's probe steps: unpinned, the same page is
    # simply open inside the window.
    step("staging: probe page, clock inside the window", PROBE_PATH, "open",
         banner=False, lang="en", clock=CLOCK_INSIDE)
    return steps


def build_retired_plan():
    """Run as http://september.alumoimpact.ca:<port>/ (no longer a preview host)."""
    steps, step = plan_builder()
    real = real_state()
    step("september: preview-at no longer honoured", "/apply-now/?preview-at=" + AFTER,
         real, banner=False, lang="en", stored=None)
    step("september: FR too", "/fr/?preview-at=" + AFTER, real, banner=False,
         lang="fr", stored=None)
    step("september: a stored preview is dropped", "/", real, banner=False, lang="en",
         preset=AFTER, stored=None)
    return steps


HARNESS = r"""<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>schedule test</title></head>
<body style="margin:0">
<iframe id="f" style="border:0;display:block"></iframe>
<script>
(function () {
  var frame = document.getElementById("f");
  var results = [];
  var lastExit = null;
  function rect(el) {
    if (!el) return null;
    var r = el.getBoundingClientRect();
    return { left: r.left, top: r.top, right: r.right, bottom: r.bottom,
             width: r.width, height: r.height,
             scrollWidth: el.scrollWidth, clientWidth: el.clientWidth,
             display: frame.contentWindow.getComputedStyle(el).display };
  }
  function inspect(step) {
    var doc = frame.contentDocument, win = frame.contentWindow;
    var sched = [];
    doc.querySelectorAll("[data-opens-at], [data-closes-at]").forEach(function (el) {
      var closed = el.querySelector(".apply-form-closed");
      var open = el.querySelector(".apply-form-open");
      sched.push({
        tag: el.tagName.toLowerCase(), cls: el.className,
        showWhen: el.getAttribute("data-show-when"),
        hidden: el.hidden,
        closedHidden: closed ? closed.hidden : null,
        openHidden: open ? open.hidden : null,
        displayed: win.getComputedStyle(el).display !== "none"
      });
    });
    var banner = doc.querySelector(".preview-banner");
    var exit = banner ? banner.querySelector("a") : null;
    if (exit) lastExit = exit.getAttribute("href");
    var measures = {};
    step.measure.forEach(function (sel) { measures[sel] = rect(doc.querySelector(sel)); });
    /* Hit-test the centre of every visible match: what is actually on top there? */
    var probes = [];
    step.probe.forEach(function (sel) {
      doc.querySelectorAll(sel).forEach(function (el) {
        var r = el.getBoundingClientRect();
        if (!r.width || !r.height) return;
        var hit = doc.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
        probes.push({ sel: sel, text: (el.textContent || "").trim().slice(0, 40),
                      rect: rect(el), hitSelf: !!hit && el.contains(hit),
                      hitBanner: !!hit && !!banner && banner.contains(hit),
                      hit: hit ? hit.tagName.toLowerCase() + "." + hit.className : null });
      });
    });
    var stored = null;
    try { stored = sessionStorage.getItem("sifPreviewAt"); } catch (e) { stored = "ERR"; }
    return {
      name: step.name, url: step.url, location: win.location.pathname + win.location.search + win.location.hash,
      host: win.location.hostname,
      lang: doc.documentElement.lang, sched: sched,
      banner: banner ? { text: banner.textContent, role: banner.getAttribute("role"),
                         exitHref: exit ? exit.getAttribute("href") : null,
                         exitText: exit ? exit.textContent : null,
                         position: win.getComputedStyle(banner).position,
                         rect: rect(banner) } : null,
      stored: stored, measures: measures, probes: probes,
      viewport: win.innerWidth, viewportH: win.innerHeight,
      docScrollWidth: doc.documentElement.scrollWidth,
      focus: exit ? { active: doc.activeElement === exit, tabIndex: exit.tabIndex,
                      outlineStyle: win.getComputedStyle(exit).outlineStyle,
                      outlineWidth: win.getComputedStyle(exit).outlineWidth } : null,
      pageNow: win.Date.now()
    };
  }
  /* What to do once the page has loaded, before inspecting it. */
  function act(step, done) {
    var doc = frame.contentDocument, win = frame.contentWindow;
    if (step.action === "menu") {
      var burger = doc.querySelector(".hamburger-menu-toggle-btn");
      if (burger) burger.click();
      setTimeout(done, 900);        /* menu + link transitions are 0.3–0.45s */
    } else if (step.action === "bottom") {
      win.scrollTo(0, doc.documentElement.scrollHeight);
      setTimeout(done, 400);
    } else if (step.action === "focus") {
      var link = doc.querySelector(".preview-banner a");
      try { win.focus(); } catch (e) {}
      if (link) link.focus();
      setTimeout(done, 100);
    } else {
      done();
    }
  }
  function run(steps, i) {
    if (i >= steps.length) {
      var x = new XMLHttpRequest();
      x.open("POST", "/__schedtest__/report");
      x.setRequestHeader("Content-Type", "application/json");
      x.send(JSON.stringify(results));
      return;
    }
    var step = steps[i];
    if (step.clear) { try { sessionStorage.clear(); } catch (e) {} }
    if (step.preset) { try { sessionStorage.setItem("sifPreviewAt", step.preset); } catch (e) {} }
    /* The server pins the page's Date to this moment while the cookie is set. */
    document.cookie = step.clock
      ? "sifFakeNow=" + encodeURIComponent(step.clock) + "; path=/"
      : "sifFakeNow=; path=/; max-age=0";
    frame.style.width = step.width + "px";
    frame.style.height = step.height + "px";
    var url = step.url === "@exit" ? lastExit : step.url;
    frame.onload = function () {
      frame.onload = null;
      setTimeout(function () {
        act(step, function () {
          var r;
          try { r = inspect(step); r.loadedUrl = url; } catch (e) { r = { name: step.name, error: String(e) }; }
          results.push(r);
          run(steps, i + 1);
        });
      }, 150);
    };
    frame.src = url || "about:blank";
  }
  /* Answer the analytics consent banner (js/analytics.js shows it on
     127.0.0.1 and alumoimpact.ca) so it never sits over what is hit-tested.
     Same origin as the frames, so they read it too. */
  try { localStorage.setItem("alumo-analytics-consent", "denied"); } catch (e) {}
  var x = new XMLHttpRequest();
  x.open("GET", "/__schedtest__/plan.json" + location.search);
  x.onload = function () { run(JSON.parse(x.responseText), 0); };
  x.send();
})();
</script>
</body></html>
"""

FRAME = r"""<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>frame</title></head>
<body style="margin:0;background:#888">
<iframe id="f" style="border:0;display:block;background:#fff"></iframe>
<script>
(function () {
  /* Answer the localhost analytics consent banner (js/analytics.js) up front
     so it doesn't cover what the screenshot is for. Fresh profile each run. */
  try { localStorage.setItem("alumo-analytics-consent", "denied"); } catch (e) {}
  var q = new URLSearchParams(location.search);
  var f = document.getElementById("f");
  f.style.width = q.get("w") + "px";
  f.style.height = q.get("h") + "px";
  f.onload = function () {
    var el = q.get("scroll") && f.contentDocument.querySelector(q.get("scroll"));
    if (el) f.contentWindow.scrollTo(0, el.getBoundingClientRect().top + f.contentWindow.scrollY - 120);
  };
  f.src = q.get("src");
})();
</script>
</body></html>
"""


# Put at the top of <head> while the harness's sifFakeNow cookie is set:
# Date (no arguments) and Date.now() answer that moment; everything else is
# the real Date.
CLOCK_SCRIPT = r"""<script>/* test_schedule_preview.py: fake clock */
(function () {
  var Real = Date, at = Real.parse(%s);
  function Fake() {
    if (!(this instanceof Fake)) return new Real(at).toString();
    if (!arguments.length) return new Real(at);
    var args = [null].concat(Array.prototype.slice.call(arguments));
    return new (Function.prototype.bind.apply(Real, args))();
  }
  Fake.prototype = Real.prototype;
  Fake.now = function () { return at; };
  Fake.parse = Real.parse;
  Fake.UTC = Real.UTC;
  window.Date = Fake;
})();
</script>"""


# PROBE_PATH: the window's element as the pages carry it, then one that opens
# on PROBE_OPENS (no close). Loads the real /js/main.js.
PROBE = """<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>probe</title></head>
<body>
<div data-opens-at="%(opens)s" data-closes-at="%(closes)s">
  <p class="apply-form-closed">closed</p><p class="apply-form-open" hidden>open</p>
</div>
<p class="probe" data-opens-at="%(probe)s" hidden>probe</p>
<script src="/js/main.js"></script>
</body></html>
"""


def probe_html():
    opens, closes = window_raw()
    return PROBE % {"opens": opens, "closes": closes, "probe": PROBE_OPENS}


def with_clock(html, clock):
    """html with the fake-clock script at the top of its <head>, or None."""
    html, n = re.subn(r"<head[^>]*>",
                      lambda m: m.group(0) + CLOCK_SCRIPT % json.dumps(clock),
                      html, count=1)
    return html if n == 1 else None


def fake_clock(cookie_header):
    """The moment in the sifFakeNow cookie, or None."""
    for part in (cookie_header or "").split(";"):
        name, _, value = part.strip().partition("=")
        if name == FAKE_CLOCK_COOKIE and value:
            value = urllib.parse.unquote(value)
            if not ISO_RE.match(value):
                raise ValueError("bad fake clock %r" % value)
            return value
    return None


# ---------------------------------------------------------------- server
class State:
    plans = {}          # name -> steps; the harness asks for ?set=<name>
    report = None
    got_report = threading.Event()


class Handler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *a, **kw):
        super().__init__(*a, directory=ROOT, **kw)

    def log_message(self, *a):
        pass

    def end_headers(self):
        self.send_header("Cache-Control", "no-store")
        super().end_headers()

    def _send(self, body, ctype):
        data = body.encode("utf-8")
        self.send_response(200)
        self.send_header("Content-Type", ctype)
        self.send_header("Content-Length", str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def do_GET(self):
        parts = urllib.parse.urlsplit(self.path)
        path = parts.path
        if path == "/__schedtest__/harness.html":
            return self._send(HARNESS, "text/html; charset=utf-8")
        if path == "/__schedtest__/frame.html":
            return self._send(FRAME, "text/html; charset=utf-8")
        if path == "/__schedtest__/plan.json":
            name = urllib.parse.parse_qs(parts.query).get("set", ["main"])[0]
            plan = [{k: v for k, v in s.items() if not k.startswith("_")}
                    for s in State.plans.get(name, [])]
            return self._send(json.dumps(plan), "application/json")
        clock = fake_clock(self.headers.get("Cookie"))
        if path == PROBE_PATH:
            html = probe_html()
            return self._send(with_clock(html, clock) if clock else html,
                              "text/html; charset=utf-8")
        if clock:
            fs = self.translate_path(path)
            if path.endswith("/") and os.path.isdir(fs):
                fs = os.path.join(fs, "index.html")
            if fs.endswith(".html") and os.path.isfile(fs):
                html = with_clock(io.open(fs, encoding="utf-8").read(), clock)
                if html is None:
                    self.send_error(500, "no <head> to put the fake clock in")
                    return
                return self._send(html, "text/html; charset=utf-8")
        return super().do_GET()

    def do_POST(self):
        if urllib.parse.urlsplit(self.path).path != "/__schedtest__/report":
            self.send_error(404)
            return
        n = int(self.headers.get("Content-Length") or 0)
        State.report = json.loads(self.rfile.read(n).decode("utf-8"))
        self._send("{}", "application/json")
        State.got_report.set()


class Server(socketserver.ThreadingMixIn, http.server.HTTPServer):
    daemon_threads = True


# ---------------------------------------------------------------- browser
def find_browser():
    env = os.environ.get("BROWSER")
    if env:
        return env
    for p in DEFAULT_BROWSERS:
        if os.path.isfile(p):
            return p
    return None


def kill_profile(profile, proc):
    """The Edge launcher detaches on Windows, so kill by the unique profile dir."""
    try:
        proc.kill()
    except Exception:
        pass
    if os.name == "nt":
        ps = ("Get-CimInstance Win32_Process -Filter \"Name='msedge.exe' or Name='chrome.exe'\" | "
              "Where-Object { $_.CommandLine -like '*%s*' } | "
              "ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }"
              % os.path.basename(profile))
        subprocess.run(["powershell", "-NoProfile", "-Command", ps],
                       capture_output=True, timeout=60)


def browser_args(browser, profile):
    return [browser, "--headless=new", "--disable-gpu", "--no-first-run",
            "--no-default-browser-check", "--disable-extensions",
            "--user-data-dir=" + profile]


def run_harness(browser, base, plan_name, extra_args=(), timeout=240):
    State.report = None
    State.got_report.clear()
    profile = tempfile.mkdtemp(prefix="sif-schedtest-")
    proc = subprocess.Popen(browser_args(browser, profile) + list(extra_args) +
                            ["--window-size=1400,1100",
                             base + "/__schedtest__/harness.html?set=" + plan_name],
                            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        if not State.got_report.wait(timeout):
            return None
        return State.report
    finally:
        kill_profile(profile, proc)
        shutil.rmtree(profile, ignore_errors=True)


def take_screenshots(browser, port):
    out_dir = os.path.join(tempfile.gettempdir(), "sif-schedule-screenshots")
    os.makedirs(out_dir, exist_ok=True)
    shots = [
        ("home-open-375", "/?preview-at=2026-10-15T12:00:00-04:00", ".period"),
        ("about-open-375", "/about-the-fund/?preview-at=2026-10-15T12:00:00-04:00", ".period"),
        ("fr-home-open-375", "/fr/?preview-at=2026-10-15T12:00:00-04:00", ".period"),
        ("fr-about-open-375", "/fr/a-propos/?preview-at=2026-10-15T12:00:00-04:00", ".period"),
        ("home-closed-375", "/?preview-at=" + AFTER, ".period"),
        ("fr-home-closed-375", "/fr/?preview-at=" + AFTER, ".period"),
        ("submit-closed-375", "/apply-now/?preview-at=" + AFTER, ".apply-hero"),
        ("fr-submit-closed-375", "/fr/soumettre/?preview-at=" + AFTER, ".apply-hero"),
        ("partners-closed-375", "/partner-schools/?preview-at=" + AFTER, ".partners-card-text"),
        ("fr-partners-closed-375", "/fr/ecoles-partenaires/?preview-at=" + AFTER, ".partners-card-text"),
        ("partners-open-375", "/partner-schools/", ".partners-card-text"),
        # The pinned copies' banners (host prefix = served under that name).
        ("before-copy-home-375", "before:/", ".period"),
        ("before-copy-fr-home-375", "before:/fr/", ".period"),
        ("open-copy-home-375", "open:/", ".period"),
        ("open-copy-fr-submit-375", "open:/fr/soumettre/", ".apply-hero"),
        ("closed-copy-home-375", "closed:/", ".period"),
        ("closed-copy-fr-home-375", "closed:/fr/", ".period"),
        ("closed-copy-fr-partners-375", "closed:/fr/ecoles-partenaires/", ".partners-card-text"),
    ]
    for name, url, scroll in shots:
        host, extra = "127.0.0.1", []
        if ":" in url.split("/")[0]:
            view, url = url.split(":", 1)
            host = PINNED_HOSTS[view]
            extra = ["--host-resolver-rules=MAP %s 127.0.0.1" % host]
        base = "http://%s:%d" % (host, port)
        profile = tempfile.mkdtemp(prefix="sif-schedshot-")
        path = os.path.join(out_dir, name + ".png")
        if os.path.exists(path):
            os.remove(path)
        frame = (base + "/__schedtest__/frame.html?w=375&h=812&scroll=" +
                 urllib.parse.quote(scroll) + "&src=" + urllib.parse.quote(url))
        proc = subprocess.Popen(browser_args(browser, profile) + extra +
                                ["--window-size=420,840", "--hide-scrollbars",
                                 "--virtual-time-budget=4000",
                                 "--screenshot=" + path, frame],
                                stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        deadline = time.time() + 30
        while time.time() < deadline and not os.path.exists(path):
            time.sleep(0.25)
        time.sleep(0.5)
        kill_profile(profile, proc)
        shutil.rmtree(profile, ignore_errors=True)
        print("  screenshot %s: %s" % (name, path if os.path.exists(path) else "MISSING"))


# ---------------------------------------------------------------- checks
def page_state(result):
    """'open' / 'closed' / 'none' (nothing scheduled) / 'mixed: ...'."""
    states = set()
    for s in result["sched"]:
        if s["closedHidden"] is not None and s["openHidden"] is not None:
            if s["closedHidden"] and not s["openHidden"]:
                states.add("open")
            elif s["openHidden"] and not s["closedHidden"]:
                states.add("closed")
            else:
                states.add("broken-swap")
        elif s["showWhen"] == "closed":
            states.add("closed" if (not s["hidden"] and s["displayed"]) else "open")
        else:
            states.add("open" if not s["hidden"] else "closed")
    if not states:
        return "none"
    if len(states) == 1:
        return states.pop()
    return "mixed: " + ", ".join(sorted(states))


def staging_link(location):
    """Where a pinned copy's banner link should go from this location."""
    parts = urllib.parse.urlsplit(location)
    kept = [q for q in parts.query.split("&")
            if q and not q.startswith("preview-at=") and not q.startswith("preview-form=")]
    return (STAGING_URL + parts.path + ("?" + "&".join(kept) if kept else "") +
            ("#" + parts.fragment if parts.fragment else ""))


def check(plan, report, host="127.0.0.1"):
    failures = []
    if len(report) != len(plan):
        failures.append("harness returned %d results for %d steps" % (len(report), len(plan)))
    for step, res in zip(plan, report):
        name = step["name"]

        def fail(msg):
            failures.append("%s [%s]: %s" % (name, res.get("loadedUrl", step["url"]), msg))

        if "error" in res:
            fail("harness error: " + res["error"])
            continue
        if res["host"] != host:
            fail("ran on host %r, expected %r" % (res["host"], host))
        path = urllib.parse.urlsplit(res["location"]).path
        if path in PAGES and step["width"] == 1280:
            want = PAGES[path][1]
            if len(res["sched"]) != want:
                fail("%d scheduled elements, expected %d" % (len(res["sched"]), want))
        state = page_state(res)
        if state != step["_expect"]:
            fail("state %r, expected %r (%s)" % (state, step["_expect"], res["sched"]))
        lang = step["_lang"]
        if lang and not res["lang"].lower().startswith(lang):
            fail("page lang %r, expected %s" % (res["lang"], lang))
        if step["clock"]:
            want_ms = datetime.datetime.fromisoformat(step["clock"]).timestamp() * 1000
            if abs(res["pageNow"] - want_ms) > 1:
                fail("fake clock not applied (page time %r, expected %s)"
                     % (res["pageNow"], step["clock"]))
        banner = res["banner"]
        if step["_banner"] in PINNED_TEXT:
            kind = step["_banner"]
            if not banner:
                fail("no %s-copy banner" % kind)
            else:
                blang = lang or ("fr" if res["lang"].lower().startswith("fr") else "en")
                date = (PINNED_DATE_ON[blang] + PINNED_DATE[kind][blang]
                        if res["sched"] and kind in PINNED_DATE else "")
                want = PINNED_TEXT[kind][blang].format(date=date) + PINNED_LINK[blang]
                if banner["text"] != want:
                    fail("banner text %r, expected %r" % (banner["text"], want))
                want_href = step["_exit"] or staging_link(res["location"])
                if banner["exitHref"] != want_href:
                    fail("staging link %r, expected %r" % (banner["exitHref"], want_href))
                if banner["position"] != "fixed" or banner["role"] != "status":
                    fail("banner position/role %r/%r" % (banner["position"], banner["role"]))
        elif step["_banner"] is True:
            if not banner:
                fail("no preview banner")
            else:
                if lang and not banner["text"].startswith(BANNER_PREFIX[lang]):
                    fail("banner text %r" % banner["text"])
                if lang and not banner["text"].endswith(". " + BANNER_EXIT[lang]):
                    fail("banner text does not end with the exit link: %r" % banner["text"])
                if step["_date"] and step["_date"] not in banner["text"]:
                    fail("banner date: %r not in %r" % (step["_date"], banner["text"]))
                if "preview-at=off" not in (banner["exitHref"] or ""):
                    fail("exit link %r has no preview-at=off" % banner["exitHref"])
                if banner["position"] != "fixed" or banner["role"] != "status":
                    fail("banner position/role %r/%r" % (banner["position"], banner["role"]))
        elif step["_banner"] is False and banner:
            fail("unexpected preview banner: %r" % banner["text"])
        if step["_stored"] is not Ellipsis and res["stored"] != step["_stored"]:
            fail("stored preview %r, expected %r" % (res["stored"], step["_stored"]))
        if step["url"] == "@exit" and "preview-at=off" not in res["location"]:
            fail("exit link went to %r" % res["location"])
        if step["action"] == "focus":
            f = res.get("focus")
            if not f or not f["active"]:
                fail("the banner's link did not take focus (%r)" % f)
            elif f["tabIndex"] < 0 or f["outlineStyle"] == "none" or f["outlineWidth"] == "0px":
                fail("focused banner link: tabIndex %r, outline %r %r"
                     % (f["tabIndex"], f["outlineStyle"], f["outlineWidth"]))
        if step["_exit"]:
            if not banner or banner["exitHref"] != step["_exit"]:
                fail("exit href %r, expected %r" % (banner and banner["exitHref"], step["_exit"]))
        if step["probe"]:
            probes = res["probes"]
            found = {p["sel"] for p in probes}
            for sel in step["probe"]:
                if sel not in found:
                    fail("nothing visible matches %s" % sel)
            b = banner and banner["rect"]
            for p in probes:
                what = "%s %r" % (p["sel"], p["text"])
                r = p["rect"]
                if p["hitBanner"]:
                    fail("%s is under the preview banner" % what)
                elif step["action"] == "menu" and not p["hitSelf"]:
                    fail("%s is covered by %s" % (what, p["hit"]))
                if step["action"] == "bottom":
                    if r["top"] < 0 or r["bottom"] > res["viewportH"] + 0.5:
                        fail("%s not on screen at the page end (top %.0f, bottom %.0f, "
                             "viewport %s)" % (what, r["top"], r["bottom"], res["viewportH"]))
                    elif b and not (r["right"] <= b["left"] or r["left"] >= b["right"] or
                                    r["bottom"] <= b["top"] or r["top"] >= b["bottom"]):
                        fail("%s overlaps the preview banner" % what)
        if step["measure"]:
            if res["viewport"] != step["width"]:
                fail("viewport %s, expected %s" % (res["viewport"], step["width"]))
            if res["docScrollWidth"] > res["viewport"]:
                fail("page scrolls sideways: scrollWidth %s > %s"
                     % (res["docScrollWidth"], res["viewport"]))
            m = res["measures"]
            for sel, r in m.items():
                if r is None:
                    fail("nothing matches %s" % sel)
                elif r["right"] > res["viewport"] + 0.5:
                    fail("%s sticks out to x=%.1f (viewport %s)" % (sel, r["right"], res["viewport"]))
                elif r["scrollWidth"] > r["clientWidth"] + 1 and r["display"] != "inline":
                    fail("%s overflows its own box (%s > %s)" % (sel, r["scrollWidth"], r["clientWidth"]))
            b, burger = m.get(".preview-banner"), m.get(".hamburger-menu-toggle-btn")
            if b and burger and burger["width"] > 0:
                overlap = not (b["right"] <= burger["left"] or b["left"] >= burger["right"] or
                               b["bottom"] <= burger["top"] or b["top"] >= burger["bottom"])
                if overlap:
                    fail("preview banner overlaps the menu button")
    return failures


def main():
    browser = find_browser()
    if not browser:
        print("No Edge/Chrome found; set BROWSER=<path to msedge/chrome>.")
        return 2
    plan = build_plan()
    # (plan name, host the harness is loaded from, steps); every host but
    # 127.0.0.1 is mapped to it by the browser.
    runs = [
        ("main", "127.0.0.1", plan),
        ("prod", PROD_HOST, build_prod_plan()),
        ("before", PINNED_HOSTS["before"], build_pinned_plan("before")),
        ("open", PINNED_HOSTS["open"], build_pinned_plan("open")),
        ("closed", PINNED_HOSTS["closed"], build_pinned_plan("closed")),
        ("wwwclosed", WWW_CLOSED_HOST, build_www_pinned_plan()),
        ("wwwprod", WWW_PROD_HOST, build_prod_plan("www production")),
        ("staging", STAGING_HOST, build_staging_plan()),
        ("retired", RETIRED_HOST, build_retired_plan()),
    ]
    State.plans = {name: steps for name, _host, steps in runs}
    server = Server(("127.0.0.1", 0), Handler)
    port = server.server_address[1]
    base = "http://127.0.0.1:%d" % port
    threading.Thread(target=server.serve_forever, daemon=True).start()
    print("Serving %s at %s; browser %s" % (ROOT, base, browser))
    print("Real-clock state today: %s" % real_state())
    try:
        report = run_harness(browser, base, "main")
        if report is None:
            print("FAILED: the harness never reported back (timeout).")
            return 2
        failures = check(plan, report)
        counts = ["127.0.0.1 %d" % len(plan)]
        # Same server, reached under the other host names.
        for name, host, steps in runs[1:]:
            other_report = run_harness(
                browser, "http://%s:%d" % (host, port), name,
                ["--host-resolver-rules=MAP %s 127.0.0.1" % host], timeout=180)
            if other_report is None:
                print("FAILED: the %s harness never reported back (timeout)." % host)
                return 2
            failures += check(steps, other_report, host=host)
            counts.append("%s %d" % (host, len(steps)))
        print("Steps per host: " + ", ".join(counts))
        for step, res in zip(plan, report):
            if step["measure"]:
                m = res.get("measures", {})
                parts = []
                for sel in step["measure"]:
                    r = m.get(sel)
                    parts.append("%s=%s" % (sel.split()[-1], "%dx%d" % (r["width"], r["height"]) if r else "-"))
                print("  %-34s %s" % (step["name"], "  ".join(parts)))
        if "--screenshots" in sys.argv:
            take_screenshots(browser, port)
    finally:
        server.shutdown()
    if failures:
        print("FAILURES (%d):" % len(failures))
        for f in failures:
            print("  " + f)
        return 1
    print("ALL %d SCHEDULE/PREVIEW CHECKS PASSED." % sum(len(st) for _n, _h, st in runs))
    return 0


if __name__ == "__main__":
    sys.exit(main())
