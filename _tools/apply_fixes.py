# One-shot fix batch from the user's review list (2026-08-19). Run from repo root.
import io, re

results = []

def edit(path, old, new, label, count=1):
    s = io.open(path, encoding='utf-8').read()
    n = s.count(old)
    if n == 0:
        results.append(f"MISS  {label} ({path})")
        return
    s = s.replace(old, new, count)
    io.open(path, 'w', encoding='utf-8').write(s)
    results.append(f"OK    {label}")

# ---------- HOME EN ----------
edit('index.html',
     '<a href="/apply-now/"><span>Learn more</span></a>',
     '<a href="/about-the-fund/"><span>Learn more</span></a>',
     'home hero Learn more -> About')
edit('index.html', 'inititatives', 'initiatives', 'home idea-card typo')
edit('index.html',
     '<a href="/how-to-apply/"><strong>letter of support</strong></a>',
     '<a href="/assets/docs/support-letter-en.pdf" target="_blank" rel="noopener"><strong>letter of support</strong></a>',
     'home FAQ letter -> PDF')
edit('index.html',
     '<p class="period-note text-size-16-24-tightpx">Have a cool project in mind but need money to make it happen? The Student Impact Fund is here to help!</p>',
     '''<p class="period-note text-size-16-24-tightpx">Have a cool project in mind but need money to make it happen? The Student Impact Fund is here to help!</p>
            <div class="btn btn-white btn-icon btn-icon-primary period-btn">
              <a href="/how-to-apply/"><span>Learn more</span></a>
            </div>''',
     'home period card + Learn more btn')

# ---------- HOME FR ----------
edit('fr/index.html',
     '<a href="/fr/apply-now/"><span>En savoir plus</span></a>',
     '<a href="/fr/about-the-fund/"><span>En savoir plus</span></a>',
     'FR home hero -> About')
edit('fr/index.html',
     '<a href="/fr/how-to-apply/"><strong>lettre de parrainage.</strong></a>.',
     '<a href="/assets/docs/support-letter-fr.pdf" target="_blank" rel="noopener"><strong>lettre de parrainage.</strong></a>',
     'FR FAQ letter -> PDF + drop double dot')
edit('fr/index.html',
     '<p class="period-note text-size-16-24-tightpx">Vous avez une bonne idée, mais vous avez besoin de financement pour la concrétiser ? Le Fonds d’impact étudiant est là pour vous aider !</p>',
     '''<p class="period-note text-size-16-24-tightpx">Vous avez une bonne idée, mais vous avez besoin de financement pour la concrétiser ? Le Fonds d’impact étudiant est là pour vous aider !</p>
            <div class="btn btn-white btn-icon btn-icon-primary period-btn">
              <a href="/fr/how-to-apply/"><span>En savoir plus</span></a>
            </div>''',
     'FR home period card + btn')

# ---------- smooth scroll off ----------
edit('css/style.css', '  scroll-behavior: smooth;\n', '', 'remove smooth scroll')

# ---------- idea-card overlap guard ----------
s = io.open('css/pages/home.css', encoding='utf-8').read()
guard = '''
/* Overlap guard (993-1240px): the absolute-positioned idea art shrank slower
   than the card and covered the text ("wording covered by the M"). */
@media (max-width: 1240px) and (min-width: 993px) {
  .every-idea .idea-art { width: 40%; right: 40px; }
}
'''
if 'Overlap guard (993-1240px)' not in s:
    io.open('css/pages/home.css', 'a', encoding='utf-8').write(guard)
    results.append('OK    idea-card overlap guard CSS')

# ---------- HOW TO APPLY EN ----------
edit('how-to-apply/index.html', 'Have questions? Contact us!.', 'Have questions? Contact us!', 'how-to-apply extra period')
edit('how-to-apply/index.html',
     '<strong>You’re all set!</strong>',
     '<a href="/apply-now/"><strong>You’re all set!</strong></a>',
     'how-to-apply all-set link')
edit('how-to-apply/index.html',
     '''<!-- Source quirk: this banner image is visible ONLY in the 993–1200px
             ("laptop") range — hidden at desktop, tablet and mobile. -->
        <div class="hero-banner">''',
     '''<!-- Banner hidden at ALL widths per user fix list (source showed it only
             in the 993-1200px band, which read as a glitch). -->
        <div class="hero-banner" hidden>''',
     'how-to-apply stray banner hidden')

# ---------- HOW TO APPLY FR ----------
edit('fr/how-to-apply/index.html', 'Comment soumettre un projet</h1>', 'Soumettre un projet</h1>', 'FR h1 Soumettre un projet')
edit('fr/how-to-apply/index.html',
     '''<!-- Source quirk: this banner image is visible ONLY in the 993–1200px
             ("laptop") range — hidden at desktop, tablet and mobile. -->
        <div class="hero-banner">''',
     '''<!-- Banner hidden at ALL widths per user fix list. -->
        <div class="hero-banner" hidden>''',
     'FR stray banner hidden')

# FR "all set" equivalent
s = io.open('fr/how-to-apply/index.html', encoding='utf-8').read()
m = re.search(r'<strong>([^<]*prêt[^<]*)</strong>', s)
if m and '/fr/apply-now/' not in s[max(0, m.start() - 120):m.start()]:
    s = s.replace(m.group(0), '<a href="/fr/apply-now/"><strong>' + m.group(1) + '</strong></a>', 1)
    io.open('fr/how-to-apply/index.html', 'w', encoding='utf-8').write(s)
    results.append('OK    FR all-set link (' + m.group(1)[:36] + ')')
else:
    results.append('note  FR all-set: ' + ('already linked' if m else 'pattern not found - check manually'))

# FR extra period after "Nous joindre !" style
edit('fr/how-to-apply/index.html', 'Nous pouvons vous aider !.', 'Nous pouvons vous aider !', 'FR extra period (if present)')

# ---------- APPLY NOW ----------
edit('apply-now/index.html',
     '<h3 class="block-title">Secondary contact</h3>',
     '''<h3 class="block-title">Secondary contact</h3>
                  <p class="block-note">Optional — a second person we can reach about this application if the primary contact is unavailable.</p>''',
     'apply secondary-contact note')
edit('apply-now/index.html',
     'placeholder="Type your group\'s name"',
     'placeholder="Type your group\'s name — e.g. Campus Sustainability Club"',
     'apply org-name example')
s = io.open('css/pages/apply-now.css', encoding='utf-8').read()
if '.block-note' not in s:
    io.open('css/pages/apply-now.css', 'a', encoding='utf-8').write('''
/* Helper note under section titles (e.g. secondary contact purpose) */
.apply-form .block-note {
  margin: -8px 0 16px;
  font-family: var(--font-inter);
  font-size: var(--fs-14);
  line-height: var(--lh-24);
  color: var(--accent);
  opacity: .75;
}
''')
    results.append('OK    block-note CSS')

# ---------- PARTNER SCHOOLS ----------
edit('partner-schools/index.html',
     '<br><br>Please note: The list of eligible partner schools will be updated before applications open this September. Be sure to check back in August for the latest list, along with the required Letter of Support template.',
     '', 'partners remove Please note')
edit('partner-schools/index.html',
     'Browse the list below to see if your institution is included.',
     '<a href="#partner-list"><strong>Browse the list below</strong></a> to see if your institution is included.',
     'partners link to list')
edit('partner-schools/index.html', '<section class="partner-list">', '<section class="partner-list" id="partner-list">', 'partners list anchor id')
edit('partner-schools/index.html',
     '''<div class="btn btn-primary btn-lg">
          <a href="/about-the-fund/"><span>Learn more</span></a>
        </div>''',
     '<!-- Footer "Learn more" removed on this page per user fix list -->',
     'partners remove footer Learn more')

edit('fr/partner-schools/index.html',
     "<br><br>Note : La liste des partenaires admissibles sera mise à jour avant l'ouverture de la période de candidature en septembre prochain. Consultez cette page en août pour obtenir la liste actualisée !",
     '', 'FR partners remove Note')
edit('fr/partner-schools/index.html',
     "Vérifiez si votre établissement d'enseignement est dans la liste ci-dessous.",
     '<a href="#partner-list"><strong>Vérifiez si votre établissement d\'enseignement est dans la liste ci-dessous.</strong></a>',
     'FR partners link to list')
edit('fr/partner-schools/index.html', '<section class="partner-list">', '<section class="partner-list" id="partner-list">', 'FR partners anchor id')
edit('fr/partner-schools/index.html',
     '''<div class="btn btn-primary btn-lg">
          <a href="/fr/about-the-fund/"><span>En savoir plus</span></a>
        </div>''',
     '<!-- Footer "En savoir plus" removed on this page per user fix list -->',
     'FR partners remove footer btn')

print('\n'.join(results))
