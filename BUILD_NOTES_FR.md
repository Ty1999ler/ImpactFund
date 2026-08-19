# BUILD_NOTES_FR — French pages (`/fr/…`)

Contract for the FR page builders. Read `BUILD_NOTES.md` first — everything there
still applies. This file only covers what CHANGES for French pages.
Reference mirror: `_source/pages-fr/<slug>.html` (read-only; TranslatePress output
of the live `/fr/` pages). The finished FR home `/fr/index.html` is the canonical
worked example — copy its header/footer/contact blocks.

## 0. Method (per page)

1. Start from the **built EN page** (`/<slug>/index.html`) verbatim — same structure,
   classes, ids, images, CSS/JS links. Change ONLY: `lang`, visible text, link targets,
   `<title>`/meta description, hreflang pair, alt/aria text.
2. Replace every visible string with the FR text from `_source/pages-fr/<slug>.html`
   **VERBATIM** (see §6 for how to find the right string among EN/FR duplicates).
3. Output: `/fr/<slug>/index.html` (home is `/fr/index.html`). UTF-8.

## 1. `<head>` differences

```html
<html lang="fr">
...
<title><!-- from _source/pages-fr/<slug>.html <title> — home is "Alumo Fund" unchanged --></title>
<meta name="description" content="<!-- FR source has NO meta descriptions: author a short factual French one from the page intro -->">
<link rel="alternate" hreflang="en" href="/<slug>/">
<link rel="alternate" hreflang="fr" href="/fr/<slug>/">
```

- Add the SAME hreflang pair to the EN counterpart's `<head>` (right after its meta
  description) — that is the ONLY edit allowed on EN files.
- Stylesheets identical: `/css/style.css` + `/css/pages/<slug>.css` (root-relative works
  from `/fr/`). Only add `/css/pages/fr-<slug>.css` if the FR page GENUINELY needs an
  extra rule — the home page needed **none** (FR strings are longer but the responsive
  type utilities absorb them). Never edit shared CSS.

## 2. FR header (paste from `/fr/index.html`; per-page: only the EN switcher link changes)

Differences vs the EN header:
- Logo: `/assets/img/logo-fr.svg` (the FR site shows the French logo), `href="/fr/"`,
  `width="117" height="52"` (FR logo is 363×161, ≈2.25:1 — NOT the EN 117×60),
  alt `Fonds d'impact étudiant par Alumo`, aria-label `… — accueil`.
- Nav labels (header — note PLURAL "Soumissions"):

| EN | FR | href |
|---|---|---|
| About | À propos | `/fr/about-the-fund/` |
| Submitting a Project | Soumissions de projets | `/fr/how-to-apply/` |
| Partners | Partenaires | `/fr/partner-schools/` |
| FAQ | FAQ | `/fr/#faq-section` |
| Contact | Nous joindre | `/fr/#contact-form` |

- Hidden submit button: label `Soumettre`, `href="/fr/apply-now/"`, keep `hidden` + `hidden-all`.
- Language switcher: current = `FR`, dropdown offers `EN` linking to the **EN counterpart
  page** (`/` on home, `/about-the-fund/` on about, etc.):

```html
<div class="lang-switcher">
  <button class="lang-current" type="button" aria-haspopup="true" aria-expanded="false">
    FR
    <svg width="10" height="6" viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M1 1L5 5L9 1" stroke="#222222" stroke-width="1.5" stroke-linecap="round"/></svg>
  </button>
  <div class="lang-dropdown">
    <a href="/<en-counterpart-path>">EN</a>
  </div>
</div>
```

- Skip link text: `Aller au contenu`. Aria-labels in FR: `Basculer le menu`,
  `Menu principal`, `Menu de pied de page`, `Défiler vers la section suivante`.

## 3. FR footer (paste from `/fr/index.html` — identical on every FR page)

- Same FR logo block as the header (`/fr/`, logo-fr.svg, 117×52).
- Footer nav: `À propos` / **`Soumission de projets`** (SINGULAR "Soumission" — header is
  plural; this matches the live source) / `Partenaires` / `FAQ` / `Nous joindre` — same
  hrefs as header.
- Button: `En savoir plus` → `/fr/about-the-fund/`.
- Bottom line: `Alumo 2026 - Tous droits réservés`.
- Legal links: `Conditions d'utilisation` → `/fr/terms-conditions/`;
  `Gérer mon consentement` → `#` (dead, `role="button"`); `Politique de confidentialité` →
  `https://aseq.ca/RTEContent/Document/Privacy_Policy/ASEQ_Politique_confidentialit%C3%A9_2025.pdf`
  (**different external PDF than EN** — the FR site links the ASEQ French policy; keep
  `target="_blank" rel="noopener"`).

## 4. FR contact form (paste from `/fr/index.html` wherever the EN page has the form)

Keep the EN build's markup (label + `<span aria-hidden="true">*</span>`; the live FR
form shows "(Nécessaire)" via Gravity Forms — we keep the * convention):
- Title: `Contactez notre équipe`
- `Nom` / placeholder `Votre nom`
- `Courriel` / placeholder `Votre courriel`
- `Message` / placeholder `Écrivez ici vos questions ou commentaires`
- Submit: `Envoyer`
Only ONE `id="contact-form"` per page, as before.

## 5. Link mapping (EN → FR)

| EN href | FR href |
|---|---|
| `/` | `/fr/` |
| `/about-the-fund/` | `/fr/about-the-fund/` |
| `/how-to-apply/` | `/fr/how-to-apply/` |
| `/apply-now/` | `/fr/apply-now/` |
| `/partner-schools/` | `/fr/partner-schools/` |
| `/past-winners/` | `/fr/past-winners/` |
| `/past-winners/2/` | `/fr/past-winners/2/` |
| `/privacy-policy/` | `/fr/privacy-policy/` |
| `/terms-conditions/` | `/fr/terms-conditions/` |
| `/cookies-policy/` | `/fr/cookies-policy/` |
| `/#faq-section`, `/#contact-form` | `/fr/#faq-section`, `/fr/#contact-form` |
| same-page `#contact-form` etc. | unchanged |
| `/assets/docs/support-letter.pdf` (apply-now) | `/assets/docs/support-letter-fr.pdf` (file exists) |
| external links | unchanged, EXCEPT the footer privacy PDF (see §3) |

Normalize FR-source junk prefixes: `https://alumoimpact.ca/fr/...` and
`/fr/ImpactFund/...` → `/fr/...`. Never link `wp-content` or `alumoimpact.ca`.

## 6. Finding the right FR string in the source (gotchas)

1. **`lang` toggle classes**: the source shows/hides bilingual duplicate blocks via
   `html[lang="fr-CA"] .text-en { display:none }` (same for `.btn-en`; `.text-fr`/`.btn-fr`
   are the visible ones on FR pages). So when a block exists twice: take the `text-fr`
   copy, SKIP the `text-en` copy. Blocks without either class were translated in-place
   by TranslatePress — use them directly.
2. **Hidden-at-every-breakpoint blocks** (all five `elementor-hidden-*` classes) are
   still hidden on FR — same skips as the EN build (home: "Stimuler l'innovation…"
   heading, the alternate intro band data-id 852266a, "Soumettre"/"En savoir plus"
   hidden buttons, past-initiatives carousel, mission section).
3. **NO `&nbsp;` anywhere** in the FR home source. French punctuation mostly uses
   plain spaces (`étudiant ?`, `soumission :`) — copy exactly. EXCEPTION: the orange
   period card note uses **U+2009 THIN SPACE** before `?` and `!`
   ("…la concrétiser ? Le Fonds…aider !") — the built page encodes them as `&thinsp;`.
   Check your page for ` ` / ` ` with python before assuming.
4. **Apostrophes are inconsistent in the source** — straight `'` and curly `’` are both
   used, sometimes in the same sentence ("l'accord" vs "l’accord"). Copy VERBATIM,
   never normalize. Same for `·` (étudiant·es), literal `→` arrows (FR uses the
   character, not `&rarr;`), and double spaces ("Alumo soutient  les initiatives").
5. **Source typos are intentional** — reproduce: "dois-soumettre" (missing "je"),
   "les semaines suivants", "un personne", "résulats", "attributés",
   "projets gagnant", "Soumissions ouvrent en Septembre".
6. **Header vs footer nav labels differ**: "Soumissions de projets" (header) vs
   "Soumission de projets" (footer). Both correct.
7. Links with **no href** in the source (TranslatePress strips some): map to the obvious
   FR page — `Consultez la liste (complète) ici.` → `/fr/partner-schools/`,
   `Soumission de projets` / `lettre de parrainage.` → `/fr/how-to-apply/`.
8. Some FAQ/card paragraphs have odd spacing around links ("…projets</a> **&nbsp;.**" —
   actually `</a> .` with a space before the period). Copy verbatim.
9. Alt text / aria-labels: FR source has empty alts; we author short French alts
   mirroring the EN build's descriptions (see `/fr/index.html`). Not part of the
   verbatim-text requirement.
10. `<title>`: FR home title is `Alumo Fund` — unchanged from EN. Check each page's
    FR source title; don't assume it's translated.
11. Extraction tip (files are ~250 KB): use python + bs4, find widgets by `data-id`
    (they match the EN source's ids), and print `str(el)` to see `<br>`/link structure.
    Set `PYTHONIOENCODING=utf-8` on Windows or prints will crash on `→`/`·`.

## 7. Self-check before finishing a page

- Every `/assets`, `/css`, `/js` path referenced exists on disk.
- Zero occurrences of `wp-content`, `alumoimpact.ca`, `_source` in the output.
- Extract your page's visible text with python and confirm each block appears verbatim
  (whitespace-normalized) in `_source/pages-fr/<slug>.html` — only allowed misses:
  the form's `Nom*`/`Courriel*`/`Message*` labels (source shows "(Nécessaire)").
- hreflang pair present in BOTH the FR page and its EN counterpart, pointing at each other.
- All internal hrefs start with `/fr/`, `#`, `/assets`, `/css`, or are the switcher's
  EN counterpart link / unchanged external URLs.

## 8. Pages remaining (source → output; reuse EN page CSS)

| FR source | Template (EN build) | Output |
|---|---|---|
| `_source/pages-fr/about-the-fund.html` | `/about-the-fund/index.html` | `/fr/about-the-fund/index.html` |
| `_source/pages-fr/how-to-apply.html` | `/how-to-apply/index.html` | `/fr/how-to-apply/index.html` |
| `_source/pages-fr/apply-now.html` | `/apply-now/index.html` | `/fr/apply-now/index.html` |
| `_source/pages-fr/partner-schools.html` | `/partner-schools/index.html` | `/fr/partner-schools/index.html` |
| `_source/pages-fr/past-winners.html` | `/past-winners/index.html` | `/fr/past-winners/index.html` |
| `_source/pages-fr/privacy-policy.html` | `/privacy-policy/index.html` | `/fr/privacy-policy/index.html` |
| `_source/pages-fr/terms-conditions.html` | `/terms-conditions/index.html` | `/fr/terms-conditions/index.html` |
| `_source/pages-fr/cookies-policy.html` | `/cookies-policy/index.html` | `/fr/cookies-policy/index.html` |
