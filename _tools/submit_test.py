# -*- coding: utf-8 -*-
"""
One-command test submission to a TEST copy of the site (stdlib only).

  python _tools/submit_test.py --email you@example.com
      [--site staging|before|open|closed|https://<test host>|http://localhost:<port>]
      [--locale en|fr] [--contact] [--dry-run]

Sends a COMPLETE, valid application to <site>/api/apply.php: every required
field filled with obviously-test values ("Test" / "TEST submission <time>"),
a province + institution taken from js/schools-data.js (the same label the
form's dropdown submits), a valid category, amounts, counts and both
tick-boxes, the five required documents as tiny valid PDFs made in memory,
the honeypot left empty, and the locale. --contact sends a contact-form
message to <site>/api/contact.php instead. --dry-run prints every field and
file it would send, and sends nothing.

Before anything is sent the payload is checked against the server's own
rules, read from api/apply.php ($FIELDS: required + length caps, $PROVINCES,
$CATEGORIES, $ALLOWED_EXT) and api/_lib.php (apply_upload_slots), so a change
there that this script does not follow stops it instead of sending a 422.

Test hosts only: the names in SITE_TEST_HOSTS in api/_lib.php (a leading www.
is ignored) — the same list that makes the server mark the submission
"[TEST]" — plus http://localhost:<port> / http://127.0.0.1:<port> for local
Docker. alumoimpact.ca and www.alumoimpact.ca are refused outright, and so is
september.alumoimpact.ca (RETIRED_HOSTS: still listed there, but its folder
runs the Aug 31 apply.php, which marks nothing). The NAS marks only once it
runs this release (redeployed from main). before./open./closed. share
staging's files and config, so all four end up in staging's api/ (and its
SharePoint/email setup).

Prints the HTTP status, the JSON answer, and where the result shows up.
Exit code 0 = accepted (200, ok), 1 = refused by the server or no answer,
2 = not sent (bad arguments, not a test host, payload check failed).
"""
import argparse
import datetime
import io
import json
import os
import re
import sys
import urllib.error
import urllib.parse
import urllib.request
import uuid

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
PRODUCTION_HOSTS = ("alumoimpact.ca", "www.alumoimpact.ca")
# In SITE_TEST_HOSTS, but the server behind it still runs the Aug 31
# apply.php (no [TEST] marking, no close gate): a test would arrive unmarked.
RETIRED_HOSTS = ("september.alumoimpact.ca",)
NAS_HOST = "impactfund.wareham.stream"
LOCAL_HOSTS = ("localhost", "127.0.0.1")
SITE_SHORTCUTS = ("staging", "before", "open", "closed")
APPLY_LIMIT = "5 applications per hour per IP address"     # rate_limit('apply', 5, 3600)
CONTACT_LIMIT = "10 messages per hour per IP address"      # rate_limit('contact', 10, 3600)


def read(rel):
    return io.open(os.path.join(ROOT, rel), encoding="utf-8").read()


def die(message, code=2):
    print(message, file=sys.stderr)
    sys.exit(code)


# ---------------------------------------------------------------- the server's rules
def server_rules():
    """What api/apply.php and api/_lib.php validate, read from the files."""
    apply_php, lib_php = read("api/apply.php"), read("api/_lib.php")
    fields = {}
    for name, required, cap, _multi in re.findall(
            r"'([a-z_]+)'\s*=>\s*\[(true|false),\s*(\d+),\s*(true|false)\]", apply_php):
        fields[name] = (required == "true", int(cap))

    def quoted_list(src, var):
        m = re.search(r"\$%s\s*=\s*\[([^\]]*)\]" % var, src)
        if not m:
            die("submit_test.py: could not find $%s in api/apply.php — update this script." % var)
        return re.findall(r"'([^']*)'", m.group(1))

    m = re.search(r"const SITE_TEST_HOSTS\s*=\s*\[([^\]]*)\]", lib_php)
    slots_src = re.search(r"function apply_upload_slots\(\).*?\n}", lib_php, re.S)
    if not fields or not m or not slots_src:
        die("submit_test.py: could not read the rules from api/apply.php / api/_lib.php "
            "— update this script.")
    slots = [(name, req == "true", label) for name, req, label in re.findall(
        r"'(file_[a-z_]+)'\s*=>\s*\[(true|false),\s*'([^']*)'\]", slots_src.group(0))]
    return {
        "fields": fields,
        "provinces": quoted_list(apply_php, "PROVINCES"),
        "categories": quoted_list(apply_php, "CATEGORIES"),
        "extensions": quoted_list(apply_php, "ALLOWED_EXT"),
        "slots": slots,
        "test_hosts": re.findall(r"'([^']+)'", m.group(1)),
    }


# ---------------------------------------------------------------- where to send
def resolve_site(site, test_hosts):
    """(base URL, host) for --site, or exit: test hosts and local Docker only."""
    if site in SITE_SHORTCUTS:
        url = "https://%s.alumoimpact.ca" % site
    else:
        url = site if "://" in site else "https://" + site
    parts = urllib.parse.urlsplit(url)
    try:
        port = parts.port
    except ValueError:
        die("Not a usable address: %s" % site)
    host = (parts.hostname or "").lower().rstrip(".")
    if parts.scheme not in ("http", "https") or not host:
        die("Not a usable address: %s" % site)
    bare = host[4:] if host.startswith("www.") else host
    if host in PRODUCTION_HOSTS or bare in PRODUCTION_HOSTS:
        die("REFUSED: %s is the live site. Test submissions go to a test copy only — "
            "--site staging (or before / open / closed), or http://localhost:<port>."
            % host)
    if bare in RETIRED_HOSTS:
        die("REFUSED: %s is retired: it still runs the Aug 31 api/apply.php, which marks "
            "nothing [TEST], so a test would arrive as a real application. "
            "Use --site staging (or before / open / closed)." % host)
    allowed = [h for h in test_hosts if h not in RETIRED_HOSTS]
    local = host in LOCAL_HOSTS and parts.scheme == "http"
    if bare not in allowed and not local:
        die("REFUSED: %s is not a test host. Allowed: %s, or http://localhost:<port>."
            % (host, ", ".join(allowed)))
    # The test hosts redirect http to https, and a redirected POST arrives as
    # a GET without its body — so go straight to https.
    scheme = "http" if local else "https"
    return "%s://%s%s" % (scheme, host, ":%d" % port if port else ""), host


# ---------------------------------------------------------------- the payload
def institution_choice(provinces):
    """(province, label) — the first Ontario option of the form's dropdown,
    built like rowLabel() in js/apply-form.js ("School - Association", or
    just the school when the association is N/A)."""
    src = read("js/schools-data.js")
    m = re.search(r"window\.ALUMO_SCHOOLS\s*=\s*(\[.*\])\s*;?\s*$", src, re.S)
    if not m:
        die("submit_test.py: could not read js/schools-data.js — update this script.")
    labels = {}
    for row in json.loads(m.group(1)):
        school = str(row.get("school") or "").strip()
        association = str(row.get("association") or "").strip()
        if not school or row.get("province") not in provinces:
            continue
        label = school if (not association or re.match(r"^n/?a$", association, re.I)) \
            else school + " - " + association
        labels.setdefault(row["province"], []).append(label)
    province = "ON" if "ON" in labels else sorted(labels)[0]
    return province, sorted(labels[province], key=str.lower)[0]


def tiny_pdf(text):
    """A one-page PDF saying `text`, with a correct xref table."""
    text = text.replace("\\", "\\\\").replace("(", "\\(").replace(")", "\\)")
    stream = ("BT /F1 16 Tf 72 720 Td (%s) Tj ET" % text).encode("latin-1", "replace")
    objects = [
        b"<< /Type /Catalog /Pages 2 0 R >>",
        b"<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
        b"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
        b"/Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>",
        b"<< /Length %d >>\nstream\n" % len(stream) + stream + b"\nendstream",
        b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
    ]
    out = b"%PDF-1.4\n"
    offsets = []
    for number, body in enumerate(objects, 1):
        offsets.append(len(out))
        out += b"%d 0 obj\n" % number + body + b"\nendobj\n"
    xref = len(out)
    out += b"xref\n0 %d\n0000000000 65535 f \n" % (len(objects) + 1)
    out += b"".join(b"%010d 00000 n \n" % offset for offset in offsets)
    out += b"trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF\n" % (
        len(objects) + 1, xref)
    return out


def build_application(email, locale, rules, now):
    """([(name, value)], [(slot, filename, bytes)]) — what the form would send."""
    province, institution = institution_choice(rules["provinces"])
    stamp = now.strftime("%Y-%m-%d %H-%M-%S")
    fields = [
        ("locale", locale),
        ("primary_first_name", "Test"),
        ("primary_last_name", "Submission"),
        ("primary_email", email),
        ("primary_role", "TEST role"),
        ("secondary_first_name", ""),
        ("secondary_last_name", ""),
        ("secondary_email", ""),
        ("secondary_role", ""),
        ("province", province),
        ("institution", institution),
        ("campus_recognised", "Yes"),
        ("organization_name", "TEST group"),
        ("off_campus_org", "No"),
        ("off_campus_org_name", ""),
        ("project_title", "TEST submission " + stamp),
        ("category", "Sustainability"),
        ("category_other", ""),
        ("funding_requested", "1000"),
        ("total_cost", "1500"),
        ("fund_acknowledgement", "I acknowledge"),
        ("project_summary", "TEST submission sent by _tools/submit_test.py on %s. "
                            "Not a real application — please ignore." % stamp),
        ("students_in_org", "12"),
        ("students_reached", "300"),
        ("consent", "I confirm"),
        ("website", ""),          # the honeypot: must stay empty
    ]
    files = [(slot, "TEST-%s.pdf" % slot[len("file_"):].replace("_", "-"),
              tiny_pdf("TEST - %s - not a real document (%s)" % (label, stamp)))
             for slot, _required, label in rules["slots"]]
    return fields, files


def check_application(fields, files, rules):
    """Problems with the payload by the server's rules ([] = none)."""
    problems = []
    values = dict(fields)
    for name, value in fields:
        if name == "website":
            if value:
                problems.append("website (honeypot) must be empty")
        elif name not in rules["fields"]:
            problems.append("%s is not a field api/apply.php reads" % name)
        elif len(value) > rules["fields"][name][1]:
            problems.append("%s is longer than %d characters" % (name, rules["fields"][name][1]))
    for name, (required, _cap) in rules["fields"].items():
        if required and not values.get(name, "").strip():
            problems.append("%s is required" % name)
    if not re.match(r"^[^@\s]+@[^@\s]+\.[^@\s]+$", values.get("primary_email", "")):
        problems.append("primary_email is not an email address")
    if values.get("province") not in rules["provinces"]:
        problems.append("province %r not in %s" % (values.get("province"), rules["provinces"]))
    if values.get("category") not in rules["categories"]:
        problems.append("category %r not in %s" % (values.get("category"), rules["categories"]))
    if values.get("category") == "Other" and not values.get("category_other", "").strip():
        problems.append("category_other is required with Other")
    for name in ("students_in_org", "students_reached"):
        if not values.get(name, "").isdigit():
            problems.append("%s must be a whole number" % name)
    for name in ("funding_requested", "total_cost"):
        try:
            if float(re.sub(r"[$, \u00a0]", "", values.get(name, ""))) < 0:
                problems.append("%s must not be negative" % name)
        except ValueError:
            problems.append("%s is not a number" % name)
    sent = {slot for slot, _n, _b in files}
    for slot, required, _label in rules["slots"]:
        if required and slot not in sent:
            problems.append("%s (required document) is missing" % slot)
    for slot, filename, body in files:
        if filename.rsplit(".", 1)[-1].lower() not in rules["extensions"]:
            problems.append("%s: .%s is not accepted" % (slot, filename.rsplit(".", 1)[-1]))
        if not body.startswith(b"%PDF-") or len(body) > 10 * 1024 * 1024:
            problems.append("%s: not a small PDF" % slot)
    return problems


def build_contact(email, now):
    return [("name", "TEST Contact"), ("email", email),
            ("message", "TEST message sent by _tools/submit_test.py on %s. Please ignore."
                        % now.strftime("%Y-%m-%d %H:%M:%S")),
            ("website", "")]


def encode_multipart(fields, files):
    """(body, content type) — multipart/form-data, as a browser's FormData."""
    boundary = "----submit-test-" + uuid.uuid4().hex
    out = io.BytesIO()
    for name, value in fields:
        out.write(("--%s\r\nContent-Disposition: form-data; name=\"%s\"\r\n\r\n"
                   % (boundary, name)).encode("utf-8"))
        out.write(value.encode("utf-8") + b"\r\n")
    for name, filename, body in files:
        out.write(("--%s\r\nContent-Disposition: form-data; name=\"%s\"; filename=\"%s\"\r\n"
                   "Content-Type: application/pdf\r\n\r\n" % (boundary, name, filename))
                  .encode("utf-8"))
        out.write(body + b"\r\n")
    out.write(("--%s--\r\n" % boundary).encode("utf-8"))
    return out.getvalue(), "multipart/form-data; boundary=" + boundary


def post(url, body, content_type, timeout):
    """(status, text) — HTTP errors are answers too; (None, reason) = no answer."""
    request = urllib.request.Request(url, data=body, method="POST", headers={
        "Content-Type": content_type,
        "User-Agent": "Alumo-submit_test.py (test submission)",
        "Accept": "application/json",
    })
    try:
        with urllib.request.urlopen(request, timeout=timeout) as response:
            return response.status, response.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", "replace")
    except (urllib.error.URLError, OSError) as e:
        return None, str(getattr(e, "reason", e))


# ---------------------------------------------------------------- main
def main(argv=None):
    parser = argparse.ArgumentParser(
        description="Send a complete test application (or contact message) to a TEST "
                    "copy of the site. Never to alumoimpact.ca.")
    parser.add_argument("--email", required=True,
                        help="your address: the acknowledgement (or reply-to) goes here")
    parser.add_argument("--site", default="staging",
                        help="staging (default), before, open, closed, https://<test host> "
                             "or http://localhost:<port>")
    parser.add_argument("--locale", choices=("en", "fr"), default="en",
                        help="language of the acknowledgement email (default en)")
    parser.add_argument("--contact", action="store_true",
                        help="send a contact-form message instead of an application")
    parser.add_argument("--dry-run", action="store_true",
                        help="print what would be sent, send nothing")
    parser.add_argument("--timeout", type=float, default=120, help=argparse.SUPPRESS)
    args = parser.parse_args(argv)
    # UTF-8 even into a Windows pipe (Git Bash, a file), where Python would
    # otherwise write cp1252 and "—" would arrive as one stray byte; and a
    # console or pipe that can't print "—" / "ç" must not crash the report.
    for stream in (sys.stdout, sys.stderr):
        try:
            stream.reconfigure(encoding="utf-8", errors="replace")
        except (AttributeError, ValueError):
            pass

    if not re.match(r"^[^@\s]+@[^@\s]+\.[^@\s]+$", args.email):
        die("--email %r is not an email address." % args.email)
    rules = server_rules()
    base, host = resolve_site(args.site, rules["test_hosts"])
    now = datetime.datetime.now()
    if (host[4:] if host.startswith("www.") else host) == NAS_HOST:
        print("Note: the NAS marks submissions [TEST] only once it runs this release "
              "(redeployed from main); before that this arrives unmarked.")

    if args.contact:
        url = base + "/api/contact.php"
        fields, files = build_contact(args.email, now), []
        body = urllib.parse.urlencode(fields).encode("utf-8")
        content_type = "application/x-www-form-urlencoded"
    else:
        url = base + "/api/apply.php"
        fields, files = build_application(args.email, args.locale, rules, now)
        problems = check_application(fields, files, rules)
        if problems:
            die("NOT SENT — the payload breaks api/apply.php's rules (update this script):\n  "
                + "\n  ".join(problems))
        body, content_type = encode_multipart(fields, files)

    if args.dry_run:
        print("DRY RUN — nothing sent. Would POST %s (%d bytes, %s)" % (
            url, len(body), content_type.split(";")[0]))
        for name, value in fields:
            print("  %-22s = %r" % (name, value))
        for name, filename, data in files:
            print("  %-22s = %s (%d bytes, application/pdf)" % (name, filename, len(data)))
        if not args.contact:
            print("Payload check against api/apply.php: OK (%d fields, %d documents)"
                  % (len(fields), len(files)))
        return 0

    print("POST %s" % url)
    status, text = post(url, body, content_type, args.timeout)
    if status is None:
        print("No answer: %s" % text)
        return 1
    print("HTTP %d" % status)
    try:
        answer = json.loads(text)
        print(json.dumps(answer, indent=2, ensure_ascii=False))
    except ValueError:
        answer = None
        print(text[:2000])

    local = host in LOCAL_HOSTS
    accepted = status == 200 and isinstance(answer, dict) and answer.get("ok") is True
    # Explain a refusal by the JSON "error" the PHP sent (api/apply.php,
    # contact.php, _lib.php), never by the status alone: a 403/503 can just as
    # well come from Cloudflare, a WAF/ModSecurity rule or a proxy.
    error = answer.get("error") if isinstance(answer, dict) else None
    bad = answer.get("fields") if isinstance(answer, dict) else None
    bad = sorted(bad) if isinstance(bad, dict) else []
    mark = "" if local else "[TEST] "   # localhost is not a test host
    print()
    if accepted and args.contact:
        print("Sent. Look for \"%sContact form — TEST Contact\" in the contact_to inbox "
              "of that server's api/config.php." % mark)
    elif accepted:
        title = dict(fields)["project_title"]
        school = dict(fields)["institution"].split(" - ", 1)[0]
        # institution_choice() picks an Ontario school: region folder "Ontario".
        print("Accepted (id %s). Where to look:" % answer.get("id"))
        print("  - Email to %s: \"%s%s\"" % (args.email, mark, (
            "Nous avons bien reçu votre candidature — Fonds d'impact étudiant"
            if args.locale == "fr" else "We received your application — Student Impact Fund")))
        print("  - delivery_mode 'graph': SharePoint list item \"%s%s\" (SubmissionId %s),"
              % (mark, title, answer.get("id")))
        print("    documents in the library under  %sOntario / %s - %s /"
              % ("" if local else "TEST / ", title, school))
        print("  - delivery_mode 'email': \"%sApplication — %s (%s)\" in the relay_to inbox"
              % (mark, title, answer.get("id")))
        print("  - A failed delivery: \"%sSubmission delivery FAILED — will retry (...)\" "
              "to failure_notify_to%s." % (mark, "" if local else "; the retry cron keeps the [TEST] marks"))
        print("  - Archive on the server: <submissions_dir>/%s/submission.json (\"test\": %s)"
              % (answer.get("id"), "false" if local else "true"))
    elif error in ("Submissions are now closed.", "Submissions are not open yet."):
        print("Refused by the schedule gate: that server's api/config.php (or the committed "
              "DEFAULT_CLOSES_AT) says submissions are closed or not open yet.")
    elif error == "Form backend is not configured yet.":
        print("That server has no api/config.php yet (form backend not configured).")
    elif status == 429 and error:
        print("Rate-limited: try again within the hour.")
    elif status == 422 and bad and set(bad) <= {"primary_email", "email"}:
        print("The server rejected the email address: check --email.")
    elif status == 422 and bad:
        print("The server rejected fields (see \"fields\" above) — api/apply.php and this "
              "script disagree; please update the script.")
    elif error:
        print("Refused by the form's PHP: %s" % error)
    else:
        print("Not an answer from the form's PHP (no JSON \"error\"): something in front of it "
              "(Cloudflare, a WAF/ModSecurity rule, a proxy?) answered HTTP %d — see the "
              "body above." % status)
    if local:
        print("Note: localhost is not a test host, so nothing is marked [TEST] there unless "
              "its api/config.php sets 'test_mode' => true.")
    print("Limits: %s (refusals by the schedule gate don't count); the contact form %s."
          % (APPLY_LIMIT, CONTACT_LIMIT))
    return 0 if accepted else 1


if __name__ == "__main__":
    sys.exit(main())
