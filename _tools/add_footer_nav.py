# One-shot (2026-08-27): mirror the header nav in the footer — add "Home" and the
# Sept-1-gated "Apply Now" to every footer menu (EN + FR). Idempotent.
import io, re, sys

ROOT = r"C:\Users\atp2txw\PycharmProjects\Alumo Website"
PAGES = ["index.html", "about-the-fund/index.html", "how-to-apply/index.html",
         "apply-now/index.html", "partner-schools/index.html", "terms-conditions/index.html"]
PAGES += ["fr/" + p for p in PAGES]

for page in PAGES:
    path = ROOT + "\\" + page.replace("/", "\\")
    t = io.open(path, encoding="utf-8").read()
    fr = page.startswith("fr/")
    home_href, home_label = ("/fr/", "Accueil") if fr else ("/", "Home")
    apply_href, apply_label = ("/fr/apply-now/", "Soumettre") if fr else ("/apply-now/", "Apply Now")

    m = re.search(r'(<ul class="footer-menu">\n)(.*?)(\n\s*</ul>)', t, re.S)
    if not m:
        print(f"SKIP (no footer-menu): {page}"); continue
    block = m.group(2)
    if f'"{home_href}">{home_label}</a>' in block and "data-opens-at" in block:
        print(f"already-done: {page}"); continue

    new = block
    if f'"{home_href}">{home_label}</a>' not in new:
        new = f'            <li class="menu-item"><a href="{home_href}">{home_label}</a></li>\n' + new
    if "data-opens-at" not in new:
        how = re.search(r'^(\s*<li class="menu-item"><a href="[^"]*how-to-apply/">[^\n]*</li>)$',
                        new, re.M)
        if not how:
            print(f"SKIP (no how-to-apply li): {page}"); continue
        gated = (how.group(1) + "\n"
                 "            <!-- Revealed automatically on Sept 1, in step with the header tab. -->\n"
                 f'            <li class="menu-item" data-opens-at="2026-09-01T00:00:00-04:00" hidden>'
                 f'<a href="{apply_href}">{apply_label}</a></li>')
        new = new.replace(how.group(1), gated, 1)

    t = t.replace(m.group(0), m.group(1) + new + m.group(3), 1)
    io.open(path, "w", encoding="utf-8", newline="\n").write(t)
    print(f"updated: {page}")
