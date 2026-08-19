# BUILD_NOTES — Student Impact Fund by Alumo (static rebuild)

Contract for all page builders. Read this before building any page.
Reference mirror: `_source/` (read-only — never edit, never link to it from output).

---

## 1. Standard `<head>` boilerplate

```html
<!DOCTYPE html>
<html lang="en-US">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><!-- from _source/pages/<slug>.html <title> --></title>
  <meta name="description" content="<!-- from source if present; the source pages mostly have NO meta description — write a short factual one from the page's intro copy -->">
  <link rel="icon" href="/assets/img/favicon-32.png" sizes="32x32">
  <link rel="icon" href="/assets/img/favicon-192.png" sizes="192x192">
  <link rel="stylesheet" href="/css/style.css">
  <link rel="stylesheet" href="/css/pages/<slug>.css">
</head>
```

- All URLs are **root-relative** (`/css/...`, `/assets/img/...`, `/about-the-fund/`).
- Page-specific CSS lives at `/css/pages/<slug>.css`, linked **after** `/css/style.css`.
- Load `<script src="/js/main.js"></script>` just before `</body>`. Do not add other JS files; if a page truly needs page-specific behavior, discuss/extend `main.js`.
- Each page lives at `/<slug>/index.html` (e.g. `/about-the-fund/index.html`). Home is `/index.html`.

## 2. Design tokens (defined in `/css/style.css` `:root`)

| Token | Value | Use |
|---|---|---|
| `--primary` | `#fbbc7e` | orange/peach — buttons, orange cards, accents |
| `--primary-light-1` | `#fbf6ed` | cream — **page background**, light text on colored buttons |
| `--secondary` | `#b9d989` | green — green bands/cards, submit-arrow circle |
| `--secondary-dark-1..4` | `#607a3b` `#a4c178` `#7fa24b` `#6f9635` | form details (apply form) |
| `--text` | `#222222` | headings, body on light bg, borders |
| `--accent` | `#161616` | default body text color (kit) |
| `--white` / `--black` | `#ffffff` / `#000000` | |
| `--gray`,`--gray-1..3` | `#dfdfdf` `#d6d6d6` `#d8d8d8` `#dbdbdb` | hairlines, pagination borders |
| `--font-inter` | `"Inter", sans-serif` | UI text, buttons, nav, labels |
| `--font-source-serif` | `"Source Serif Pro", serif` | ALL headings + serif body blocks |
| `--container-max` | `1280px` | boxed container incl. padding |
| `--container-pad` | `80px` (16px ≤1200px) | container side padding |
| `--radius-card` | `20px` | big colored cards |
| `--radius-pill` | `50px` | buttons, inputs, nav pill |

**Typography utilities** (use these instead of raw font-size):
`.text-size-<fs>-<lh>-tight[px]` e.g. `.text-size-72-70-tight`, `.text-size-52-63-tight`,
`.text-size-16-24-tight`, `.text-size-16-31-9-tightpx`, `.text-size-80-89-7-tight`, …
They set size/line-height/letter-spacing via CSS vars **which shrink automatically at 992/768/576** (the source's responsive scale). Headings default to Source Serif Pro 400; add `font-family: var(--font-inter)` in page CSS when the source shows Inter (e.g. small intro paragraphs use Inter 500).
Serif body paragraphs (card text, FAQ answers): Source Serif Pro 400 — set in page CSS or reuse existing component classes.

## 3. Breakpoints (source uses these, NOT the Elementor defaults)

- `@media (max-width: 1200px)` — tablet-landscape / small desktop (burger menu switches on here!)
- `@media (max-width: 992px)` — tablet portrait (type scale shrinks)
- `@media (max-width: 768px)` — large mobile (type shrinks again)
- `@media (max-width: 576px)` — mobile
- `@media (max-width: 380px)` — tiny mobile (form buttons)

## 4. Class naming scheme

- Semantic, kebab-case, section-scoped: `.hero`, `.hero-title`, `.intro-row`, `.period-card`, `.faq-list`.
- Shared components come from `style.css`: `.container`, `.btn …`, `.site-header`, `.site-footer`, `.contact-section`, `.contact-form`, `.accordion`, `.lang-switcher`, text-size utilities.
- No Elementor classes, no wrapper soup. One `<section class="…">` per visual band, `.container` directly inside.

## 5. Buttons

Markup: `<div class="btn <variants>"><a href="…"><span>Label</span></a></div>` (or `<button>`).
Variants (combinable):
- `btn-primary` — orange fill pill, cream text; hover: fill sweeps away, text turns orange. Add `btn-lg` for wider padding (footer "Learn more").
- `btn-secondary` / `btn-white` — same mechanics, green / white.
- `btn-outline-dark btn-icon btn-icon-primary` — the site's most common CTA: dark outline pill with orange arrow-circle at right that slides to the left on hover ("Learn more", "Find out more").
- `icon-light` — cream arrow glyph, `icon-down` — arrow rotated down, `btn-icon-secondary` — green circle.

## 6. Header (paste exactly; only change nothing — it's global)

```html
<header class="site-header is-pinned">
  <div class="container">
    <div class="nav-bar">
      <a class="brand-logo" href="/" aria-label="Student Impact Fund by Alumo — home">
        <img src="/assets/img/logo-en.svg" alt="Student Impact Fund by Alumo" width="117" height="60">
      </a>
      <div class="hamburger-menu-toggle-btn-wrap">
        <button class="hamburger-menu-toggle-btn" type="button" aria-label="Toggle menu">
          <span></span><span></span><span></span><span></span>
        </button>
      </div>
      <div class="nav-menu-container">
        <nav class="nav-menu-wrap" aria-label="Main menu">
          <ul class="nav-menu">
            <li class="menu-item"><a href="/about-the-fund/">About</a></li>
            <li class="menu-item"><a href="/how-to-apply/">Submitting a Project</a></li>
            <li class="menu-item"><a href="/partner-schools/">Partners</a></li>
            <li class="menu-item"><a href="/#faq-section">FAQ</a></li>
            <li class="menu-item"><a href="/#contact-form">Contact</a></li>
          </ul>
        </nav>
        <div class="header-button-wrap">
          <!-- "Submit now" exists in the source but is hidden at every breakpoint
               (submissions closed). Keep it hidden until re-enabled. -->
          <div class="btn btn-primary" hidden>
            <a href="/apply-now/"><span>Submit now</span></a>
          </div>
          <div class="lang-switcher">
            <button class="lang-current" type="button" aria-haspopup="true" aria-expanded="false">
              EN
              <svg width="10" height="6" viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M1 1L5 5L9 1" stroke="#222222" stroke-width="1.5" stroke-linecap="round"/></svg>
            </button>
            <div class="lang-dropdown">
              <a href="/fr/<current-page-path>/">FR</a> <!-- per-page FR equivalent: /fr/ on home, /fr/about-the-fund/ on about, etc. -->
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</header>
```

- The header is **fixed** (top 32px desktop; top 0, 92px tall ≤1200px) and turns into a blurred cream sticky bar on scroll (JS adds `.sticky-header`, hides on scroll-down via `.is-unpinned`).
- FR pages are **future work**: every FR link points at `/fr/<path>/`. The FR logo is `/assets/img/logo-fr.svg` (already copied).
- Dark-hero pages: the source inverts the header (`filter: brightness(0) invert(1)`) when the page has a `.dark-banner-section` hero. If your page's hero is dark, add class `dark-banner-section` to the hero section and port that rule into your page CSS (see `_source/...custom.css` `body:has(.dark-banner-section)` rules).

## 7. Footer (paste exactly)

```html
<footer class="site-footer">
  <div class="container">
    <div class="footer-main">
      <a class="brand-logo" href="/" aria-label="Student Impact Fund by Alumo — home">
        <img src="/assets/img/logo-en.svg" alt="Student Impact Fund by Alumo" width="117" height="60">
      </a>
      <nav aria-label="Footer menu">
        <ul class="footer-menu">
          <li class="menu-item"><a href="/about-the-fund/">About</a></li>
          <li class="menu-item"><a href="/how-to-apply/">Submitting a project</a></li>
          <li class="menu-item"><a href="/partner-schools/">Partners</a></li>
          <li class="menu-item"><a href="/#faq-section">FAQ</a></li>
          <li class="menu-item"><a href="/#contact-form">Contact</a></li>
        </ul>
      </nav>
      <div class="btn btn-primary btn-lg">
        <a href="/about-the-fund/"><span>Learn more</span></a>
      </div>
    </div>
    <div class="footer-bottom">
      <p>Alumo 2026 - All rights reserved</p>
      <div class="footer-legal">
        <a href="/terms-conditions/">Terms and conditions</a>
        <a href="#" role="button">Consent preferences</a>
        <a href="https://studentcare.ca/RTEContent/Document/Privacy_Policy/Studentcare_Privacy_Policy_2025.pdf" target="_blank" rel="noopener">Privacy Policy</a>
      </div>
    </div>
  </div>
</footer>
<script src="/js/main.js"></script>
```

Note: footer nav label is "Submitting a project" (lowercase p) — header uses "Submitting a Project". That matches the source.

## 8. Contact form (identical markup wherever it appears)

Wrap in the shared section (green card, M-shape background bottom-right):

```html
<section class="contact-section" id="contact-form">
  <div class="container">
    <div class="contact-card">
      <div class="contact-form-col">
        <form class="contact-form" action="#" method="post" data-handler="email" novalidate>
          <h2 class="form-title">Reach out to our team</h2>
          <div class="form-fields">
            <div class="form-field">
              <label for="cf-name">Name<span aria-hidden="true">*</span></label>
              <input type="text" id="cf-name" name="name" placeholder="Your name" required aria-required="true">
            </div>
            <div class="form-field">
              <label for="cf-email">Email<span aria-hidden="true">*</span></label>
              <input type="email" id="cf-email" name="email" placeholder="Your email" required aria-required="true">
            </div>
            <div class="form-field">
              <label for="cf-message">Message<span aria-hidden="true">*</span></label>
              <textarea id="cf-message" name="message" rows="5" placeholder="Type your questions or comments here" required aria-required="true"></textarea>
            </div>
          </div>
          <div class="form-footer">
            <button type="submit" class="form-submit"><span class="form-submit-label">Send</span></button>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>
```

`action="#" data-handler="email"` — backend wired later; `main.js` currently prevents default submit. Only ONE element on the whole page may have `id="contact-form"`.

## 9. Accordion (FAQ pattern — native `<details>`)

```html
<div class="accordion">
  <details open> <!-- first item open on the home page; match your page's source -->
    <summary><h3>Question?</h3><span class="accordion-icon" aria-hidden="true"></span></summary>
    <div class="accordion-content">
      <p class="text-size-16-24-tight">Answer…</p>
    </div>
  </details>
  …
</div>
```

Plus icon (closed) / minus (open) sits right after the question text, top-aligned. Answers are Source Serif Pro 16/24.

## 10. Images

- Copy needed images from `_source/assets/alumoimpact.ca/ImpactFund/wp-content/uploads/...` into `/assets/img/` with **descriptive kebab-case names**. Use full-size originals, not `-300x200` crops.
- Watch out: `_source/.../2026/07/6180330_trans.png` is **73 MB (9000×13493)** — never copy raw; it is already resized at `/assets/img/every-idea-student.png` (1108px wide). Downscale any similar monster with PIL before copying.
- Already available in `/assets/img/`:
  `logo-en.svg`, `logo-fr.svg`, `logo-alumo.svg` (small square logo), `hero-m-banner.svg`, `hero-m-half-banner.svg`, `hero-student.png`, `shape-blob-orange.svg` (shap-1), `shape-wave-green.svg` (shap-2), `shape-blob-peach.svg` (shap-3), `shape-contact-m.svg` (shap-5-new, contact card bg), `every-idea-student.png`, `every-idea-student-mobile.png`, `icon-m.svg` (orange 50×50 "M" card icon), `icon-m-ticker.svg` (cream 28×13 "M"), `favicon-32.png`, `favicon-192.png`.

## 11. Fonts

`/assets/fonts/` holds all Inter (`Inter18pt-*.woff`, weights 100–900) and Source Serif Pro (`SourceSerifPro-*.woff`, 200–900) files; `@font-face` rules are already in `style.css`. Do not add font links (no Google Fonts — the source's Roboto links are unused legacy).

## 12. Source-mirror gotchas (apply to every page)

1. **Bilingual duplicates**: the source contains hidden FR copies of many text blocks (`.text-fr`, `btn-fr`, `lettre de parrainage`, …). Build **EN only**; FR is future work under `/fr/`.
2. **Hidden sections**: Elementor classes `elementor-hidden-desktop elementor-hidden-laptop elementor-hidden-tablet_extra elementor-hidden-tablet elementor-hidden-mobile` **all together** mean the block is disabled at every real viewport — skip it. On the home page that includes: the "Past Initiatives" carousel, the "Our Mission" section, an alternate "What is the Student Impact Fund?" band, and the "Submit now"/"Learn more" buttons in the header, orange period card, and green idea card. Check each block's class list before building it.
3. Elementor's per-element CSS is in `_source/.../uploads/elementor/css/post-<id>.css`. Page IDs: home=12, about-the-fund=23(?), how-to-apply/apply pages, partner-schools, past-winners, legal pages → check `<body class="… page-id-N …">` in each source page. Header=88, footer=98, hero template=359, contact template=697, kit (global)=1919, winners loop item=1224.
4. Design-system truths live in `_source/.../themes/hello-elementor-child/assets/css/custom.css` + `responsive.css` — prefer those values over per-element ones.
5. Some source links have **no href** (e.g. "letter of support", "See the list here" inside FAQ text). Link them to the obvious local page (`/partner-schools/`, `/how-to-apply/`) and keep the underline+bold styling.
6. Normalize link targets: strip `https://alumoimpact.ca` and `/ImpactFund` prefixes → root-relative (`/about-the-fund/`).
7. Anchors used by the global nav: home page must contain `id="faq-section"` (FAQ) and `id="contact-form"` (contact). Other pages may reuse `#contact-form` on their own contact section; the nav links point at `/#…` (home).
8. No cookie banner exists in the source — don't invent one. "Consent preferences" in the footer is a dead link (`#`).
9. The kit body background is cream `#FBF6ED` — never set white page backgrounds.
10. Page files: write UTF-8; typos in the source ("eligibile", "engagment", "inititatives", "Brighstart") are **intentional — reproduce verbatim**.
11. **Known link exceptions** (targets that intentionally do not resolve in this build): `/fr/<path>/` (FR pages, future work), `action="#"` / `href="#"` dead links per source, per source. (`/past-winners/2/` and `/fr/past-winners/2/` are now built.) FR pages are also built under `/fr/`.

## 13. Pages to build (source → output)

| Source | Output | Page CSS |
|---|---|---|
| `_source/pages/about-the-fund.html` | `/about-the-fund/index.html` | `/css/pages/about-the-fund.css` |
| `_source/pages/how-to-apply.html` | `/how-to-apply/index.html` | `/css/pages/how-to-apply.css` |
| `_source/pages/apply-now.html` | `/apply-now/index.html` | `/css/pages/apply-now.css` |
| `_source/pages/partner-schools.html` | `/partner-schools/index.html` | `/css/pages/partner-schools.css` |
| `_source/pages/past-winners.html` | `/past-winners/index.html` | `/css/pages/past-winners.css` |
| `_source/pages/privacy-policy.html` | `/privacy-policy/index.html` | `/css/pages/privacy-policy.css` |
| `_source/pages/terms-conditions.html` | `/terms-conditions/index.html` | `/css/pages/terms-conditions.css` |
| `_source/pages/cookies-policy.html` | `/cookies-policy/index.html` | `/css/pages/cookies-policy.css` |

Legal pages: use the `.legal-text-box` component from `style.css`.
