# -*- coding: utf-8 -*-
"""
Freeze the Fall 2026 winners' school list -> js/winners-schools-fall-2026.js

The winners page (/winners-fall-2026/, /fr/gagnants-automne-2026/) must offer
every school a Fall 2026 applicant could have picked, not just the schools on
the list today: a winner may have applied before their school was removed
(University of Regina went in 4200fd5, Maritime College of Forest Technology
in 8399397). So the frozen list is the UNION of every js/schools-data.js
committed inside the Fall window, de-duplicated case-insensitively (there was
an old "Norquest" / "NorQuest" variant), newest spelling wins.

The page never loads js/schools-data.js, so the Winter 2027 partner list that
replaces it on January 4 cannot change what Fall winners see. api/winners.php
reads the same output file to decide school_listed (winners_school_listed in
api/_lib.php parses the JSON array after "= ["), so keep that format.

Run it from anywhere inside the repo:
    python _tools/freeze_winner_schools.py

Rerun ONCE on Oct 16, 2026 (after the 11:59 pm Oct 15 close) to pick up any
partner-list commit made between now and the close, then never again: after
Oct 16 the script refuses unless --force is given. Winter 2027 gets its own
file (and its own copy of this script) rather than an edit to this one.

Window bounds are explicit timestamps on purpose: `git log --since=2026-08-31`
with a bare date means "Aug 31 at the CURRENT time of day", which would drop
88d30df (committed 17:44 on Aug 31) whenever the script ran after 17:44.
"""
import datetime
import io
import json
import os
import subprocess
import sys

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
SOURCE = "js/schools-data.js"
OUTPUT = os.path.join(ROOT, "js", "winners-schools-fall-2026.js")

ET = datetime.timezone(datetime.timedelta(hours=-4))  # EDT; DST ends Nov 1
WINDOW_START = datetime.datetime(2026, 8, 31, 0, 0, 0, tzinfo=ET)
WINDOW_END = datetime.datetime(2026, 10, 15, 23, 59, 59, tzinfo=ET)
# Last day a regeneration is still legitimate (the Oct 16 run).
FREEZE_DEADLINE = datetime.datetime(2026, 10, 16, 23, 59, 59, tzinfo=ET)

# Same order as the province selects on the application form.
PROVINCE_ORDER = ["AB", "BC", "MB", "NB", "NS", "ON", "QC", "SK"]


def git(*args):
    return subprocess.check_output(["git"] + list(args), cwd=ROOT).decode("utf-8")


def parse_schools(src, where):
    """window.ALUMO_SCHOOLS = [ ...JSON... ]; -> list of rows."""
    try:
        rows = json.loads(src[src.index("["):src.rindex("]") + 1])
    except ValueError as e:
        sys.exit("%s: could not parse the school array (%s)" % (where, e))
    out = []
    for row in rows:
        school = " ".join(str(row.get("school", "")).split())
        province = str(row.get("province", "")).strip().upper()
        if school and province:
            out.append((school, province))
    return out


def main(argv):
    now = datetime.datetime.now(datetime.timezone.utc)
    if now > FREEZE_DEADLINE and "--force" not in argv:
        sys.exit("The Fall 2026 list is FROZEN (last regeneration was due Oct 16, 2026).\n"
                 "Winter 2027 gets its own file. Pass --force only to rebuild the Fall\n"
                 "file from git history (e.g. if it was deleted by mistake) — the\n"
                 "explicit window bounds make that reproduce the same school list.")

    # Newest first, so the latest spelling / province of a school wins.
    shas = git("log", "--since=" + WINDOW_START.isoformat(),
               "--until=" + WINDOW_END.isoformat(),
               "--format=%H %cI", "--", SOURCE).split("\n")
    versions = []
    for line in shas:
        if not line.strip():
            continue
        sha, when = line.split(" ", 1)
        versions.append(("%s (%s)" % (sha[:7], when), git("show", "%s:%s" % (sha, SOURCE))))

    # The working copy counts only while the window is still open: an
    # uncommitted edit made after the close is not part of the Fall list.
    if now <= WINDOW_END:
        with io.open(os.path.join(ROOT, *SOURCE.split("/")), encoding="utf-8") as f:
            working = f.read()
        if not versions or working != versions[0][1]:
            versions.insert(0, ("working copy (%s)" % now.strftime("%Y-%m-%d"), working))

    if not versions:
        sys.exit("No version of %s found inside the window — nothing to freeze." % SOURCE)

    seen = {}  # casefolded school -> (school, province)
    for where, src in versions:
        for school, province in parse_schools(src, where):
            seen.setdefault(school.casefold(), (school, province))

    unknown = sorted({p for _, p in seen.values() if p not in PROVINCE_ORDER})
    order = PROVINCE_ORDER + unknown  # never silently drop a new province
    rows = sorted(seen.values(), key=lambda r: (order.index(r[1]), r[0].casefold()))

    counts = ", ".join("%s %d" % (p, sum(1 for r in rows if r[1] == p))
                       for p in order if any(r[1] == p for r in rows))
    sources = "\n".join("     - %s" % where for where, _ in versions)
    header = (
        "/* FROZEN Fall 2026 winners' school list — never regenerate after Oct 16, 2026;\n"
        "   Winter 2027 gets its own file.\n"
        "\n"
        "   Union of every js/schools-data.js committed between %s and\n"
        "   %s (the Fall 2026 submission window), de-duplicated\n"
        "   case-insensitively, newest spelling wins. It keeps schools removed from\n"
        "   the partner list mid-window, because a winner may have applied while\n"
        "   theirs was still on it. Only /winners-fall-2026/ and\n"
        "   /fr/gagnants-automne-2026/ load this file; they never load\n"
        "   js/schools-data.js.\n"
        "\n"
        "   GENERATED by _tools/freeze_winner_schools.py on %s — do not edit by\n"
        "   hand. Rerun once on Oct 16, 2026 to pick up any partner-list commit made\n"
        "   before the close, then leave it alone.\n"
        "   Sources (newest first):\n%s\n"
        "   %d schools: %s. */\n"
    ) % (WINDOW_START.isoformat(), WINDOW_END.isoformat(),
         now.strftime("%Y-%m-%d %H:%M UTC"), sources, len(rows), counts)

    body = ",\n".join('  {"school": %s, "province": %s}'
                      % (json.dumps(s, ensure_ascii=False), json.dumps(p)) for s, p in rows)
    with io.open(OUTPUT, "w", encoding="utf-8", newline="\n") as f:
        f.write(header + "window.ALUMO_WINNERS_SCHOOLS = [\n" + body + "\n];\n")
    sys.stdout.write("Wrote %s: %d schools from %d version(s).\n"
                     % (os.path.relpath(OUTPUT, ROOT), len(rows), len(versions)))
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
