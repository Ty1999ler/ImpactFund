# Site review — mistakes found (no fixes applied)

## UPDATE 2026-08-26 (later) — client edits rounds shipped
Nav renamed ("How to submit"/"Comment soumettre" — resolves the FR header/footer
label mismatch, 2 findings flipped below), Home tab added, scheduled "Apply Now"
tab + orange-card Sept-1 auto-swap, Step 2 document list + Step 4 help block,
FAQ document answer aligned with Step 2 (EN+FR), category "Other" option added
to the form (front-end + backend), 993–1200px banner quirk removed on How to
submit, empty province cards auto-hidden (YT/NT/NL/PE). None of the remaining
open findings were affected except the two flipped nav findings.

## UPDATE 2026-08-26 — new partner list loaded (supersedes school-data findings below)

Alumo's "Partner List Sept 2026" spreadsheet (165 rows) replaced the original
2026-08-18 data. Loaded to js/schools-data.js after cleaning: **155 partners**.

**Resolved by the new list itself:** "confirm with Darren" note gone; Kwatlen→Kwantlen;
"(SA)/(Institution)" markers mostly gone; association misspellings (Assoication etc.)
fixed; contact names dropped (matches our display); "TBD" replaced with
"Contact Email Coming Soon".

**Fixed in our cleaning pass (35 changes, all in the 2026-08-26 commit):**
10 exact duplicate rows removed (SK block + UQAM/UQAT pairs); Fanshawe email
missing "@" re-fixed (regressed in the sheet); remaining name corrections applied
(Wilfrid Laurier, Queen's, University of Waterloo, U of the Fraser Valley, NAIT,
AUArts, Université Laval, UQ en Outaouais, Ahuntsic, Bois-de-Boulogne, FR plurals);
last "(SA)" markers stripped; 4 factual province corrections: Burman University and
The King's University → Alberta, Crandall University and Maritime College of Forest
Technology → New Brunswick (all four were listed under Ontario).

**Post-verification fix (2026-08-26):** the Sept-2026 list's Fanshawe College row
carried a raw pasted contact ("McLean, Jennifer <jmclean@FanshaweC.ca>") in the
email column — normalized to jmclean@FanshaweC.ca in js/schools-data.js.

**Still open / flag to Alumo:**
- 10 partners show "Contact Email Coming Soon" (renders as plain text on the site).
- The 4 province corrections above should be confirmed by Alumo.
- Duplicate contact emails to confirm intentional: kminer@unb.ca (UNB Fredericton +
  Saint John), maryliz.warwick@ (Cambrian College + Cambrian International),
  permanence@asso-cstj.org (Cégep de Sainte-Foy + St-Jérôme — was flagged before,
  persists in the new list).
- New list drops the FR-accent province issue (cards unchanged) but the FR page
  card labels still carry the hyphen/accent gaps (separate finding, still open).

Findings in "Partner-schools data quality" and the school-name items in
"Spelling & grammar — EN" below are now HISTORICAL — kept for the record.


Audited 2026-08-19 against https://impactfund.wareham.stream (deployed content
verified byte-identical to this repo) and the original alumoimpact.ca mirror.
`[inherited]` = the mistake exists on the original WordPress site too and was
copied faithfully; everything else was introduced in the rebuild or the dataset.
**Totals (re-verified 2026-08-26): 131 findings — 99 open (14 major, 85 minor), 32 resolved, 0 superseded.** (Original 2026-08-19 tally: 21 major, 110 minor; 109 inherited.)

**Currently open — MAJOR (2026-08-26):**
- EN privacy-policy body is "Subtitle #01-#03" placeholder (needs Alumo)
- EN cookies-policy body is the same placeholder (needs Alumo)
- fr/how-to-apply H1 truncated to "Soumettre un projet"
- Partner-list update note dropped from FR (and EN) partner-schools intro card
- FR past-winners intro paragraph is untranslated English (both pages)
- FR privacy-policy body is English placeholder (needs Alumo)
- FR cookies-policy body is English placeholder (needs Alumo)
- FR past-winners English intro (spelling/grammar-FR duplicate of the parity finding)
- FR privacy placeholder (spelling/grammar-FR duplicate, needs Alumo)
- FR cookies placeholder (spelling/grammar-FR duplicate, needs Alumo)
- Footer "Consent preferences"/"Gérer mon consentement" is a dead href="#" on all 20 pages
- Cégep de Sainte-Foy still shares permanence@asso-cstj.org with St-Jérôme (needs Alumo)
- 10 partner rows show "Contact Email Coming Soon" as the public contact email (needs Alumo)
- hreflang alternates are relative URLs on all 20 pages (spec requires absolute)

Major findings were adversarially re-verified by a second, independent pass.

## Content parity — EN (home, about, how-to-apply)

- **[OPEN · MINOR]** C:/Users/atp2txw/PycharmProjects/Alumo Website/index.html line 215 (https://impactfund.wareham.stream/, green "Every idea" card); original: _source/pages/home.html line 934
  - [inherited] Typo "social engagment" in the green idea-card paragraph ("We know campus life is built on social engagment."). Present on the original site and reproduced verbatim in the rebuild (BUILD_NOTES.md line 240 lists it as an intentional verbatim reproduction), but it is a genuine spelling error users see.
  - **Should be:** "social engagement" — correct the spelling in index.html (and ideally on the original WordPress site).

- **[OPEN · MINOR]** C:/Users/atp2txw/PycharmProjects/Alumo Website/index.html line 215 (https://impactfund.wareham.stream/, same sentence as above); original: _source/pages/home.html line 934
  - Half-corrected sentence breaks the rebuild's own parity policy: the original says "...more positive inititatives in your student community" but the rebuild renders "initiatives" (silently fixed), while "engagment" two words earlier was left verbatim. BUILD_NOTES.md line 240 explicitly says the typo "inititatives" is intentional and must be reproduced verbatim, so the file contradicts its own build notes and the deployed original.
  - **Should be:** Pick one policy for the sentence: either fix both typos ("engagement" + "initiatives") or restore verbatim "inititatives" to match the original; update BUILD_NOTES.md line 240 to match the decision.

- **[OPEN · MINOR]** C:/Users/atp2txw/PycharmProjects/Alumo Website/how-to-apply/index.html line 100 (https://impactfund.wareham.stream/how-to-apply/, step 2 "Complete your application"); original: _source/pages/how-to-apply.html line 365
  - [inherited] The original page shows "Have questions? Contact us!." with a stray period after the exclamation mark; the rebuild silently corrected it to "Have questions? Contact us!". The rebuilt text is the better copy, but it deviates from the live original and from the BUILD_NOTES verbatim-typo policy without any documenting comment.
  - **Should be:** Keep the corrected "Contact us!" in the rebuild and fix the stray "!." on the original WordPress page; document the deliberate deviation (comment or BUILD_NOTES entry) so it is not mistaken for drift.

- **[OPEN · MINOR]** C:/Users/atp2txw/PycharmProjects/Alumo Website/index.html line 285 (https://impactfund.wareham.stream/, FAQ answer "What is the maximum amount I can receive for my project?"); original: _source/pages/home.html lines 1123-1125
  - [inherited] Inconsistent terminology inside one FAQ answer: the funding-limit list says "- Recurrent projects: up to $3,000*" while the footnote directly below says "*Recurring projects that include a significant new or innovative component...". Inherited from the original site and reproduced verbatim in the rebuild.
  - **Should be:** Use one term consistently, e.g. "- Recurring projects: up to $3,000*" so the list item matches the "*Recurring projects..." footnote.

## Content parity — EN (apply, partners, winners, legal)

- **[OPEN · MAJOR (needs Alumo)]** privacy-policy/index.html lines 82-89 (https://impactfund.wareham.stream/privacy-policy/) — page body
  - [inherited] CONFIRMED: the entire Privacy policy body is placeholder filler — three headings literally titled "Subtitle #01", "Subtitle #02", "Subtitle #03", with the same dummy sentence ("Our protection is straightforward and easy to understand, designed with students in mind...") repeated 10 times total (6/3/1 across the three sections). Identical placeholder text (same headings, same 10 repetitions) exists in the original mirror _source/pages/privacy-policy.html, and the live page serves it (verified via curl). Note: the earlier claim that the form stubs link to this page is incorrect — the apply-now fineprint "privacy policy" link (apply-now/index.html:356) and every footer "Privacy Policy" link point to the external Studentcare PDF (studentcare.ca/.../Studentcare_Privacy_Policy_2025.pdf); nothing on the site links to /privacy-policy/ except its own EN/FR language toggle and hreflang tags, so the placeholder page is live, indexed via canonical/hreflang, and effectively orphaned.
  - **Should be:** Real privacy-policy copy (real section headings and text) once available from Alumo, and internal links (footer/form fineprint) repointed from the Studentcare PDF to /privacy-policy/ if the local page is meant to be authoritative. The rebuild's parity with the original is itself correct.

- **[OPEN · MAJOR (needs Alumo)]** cookies-policy/index.html lines 80-87 (https://impactfund.wareham.stream/cookies-policy/) — page body
  - [inherited] CONFIRMED: the Cookies page body is the same placeholder filler as the privacy page — headings "Subtitle #01/#02/#03" with the identical dummy sentence repeated 10 times (6/3/1 per section). Identical text exists in the original mirror _source/pages/cookies-policy.html, and the live page serves it (verified via curl). Note: the earlier claim that the footer "Consent preferences" link points to this page is incorrect — "Consent preferences" is href="#" on every page (e.g. index.html:374); nothing on the site links to /cookies-policy/ except its own EN/FR language toggle and hreflang tags, so the placeholder page is live, indexed via canonical/hreflang, and effectively orphaned.
  - **Should be:** Real cookies-policy copy (which cookies are set, purposes, opt-out) once available from Alumo; separately, the footer "Consent preferences" href="#" dead link could point here or open a consent manager. The rebuild's parity with the original is itself correct.

- **[OPEN · MINOR]** past-winners/2/index.html (https://impactfund.wareham.stream/past-winners/2/) — contact form (~line 199, "Reach out to our team")
  - The contact form on past-winners page 2 is missing the honeypot block (<div class="hp-field" aria-hidden="true"> with label "Leave this field empty" and input name="website") that the identical form includes on past-winners/index.html (lines 204-207), index.html, about-the-fund/index.html, and apply-now/index.html. BUILD_NOTES.md section 8 specifies the contact form markup is "identical markup wherever it appears"; when the PHP mail backend lands, page 2's form will lack spam protection (or fail if the backend expects the field).
  - **Should be:** Insert the same hp-field honeypot block (label "Leave this field empty" + hidden text input name="website", tabindex=-1) between .form-fields and .form-footer, matching past-winners/index.html.

- **[OPEN · MINOR]** partner-schools/index.html lines 88-94 (https://impactfund.wareham.stream/partner-schools/) — "Partners" list header
  - [inherited] The now-visible partner-list header shows the paragraph "Download the Support letter form that you can send to your contact that you will need to provide during the submission" while its accompanying "Support letter" button (line 92) is kept hidden — so users see a call-to-download with no download control. The sentence itself is also the old garbled copy carried over from the original's hidden section (_source/pages/partner-schools.html line 396); the current wording used on apply-now is "Download the letter of support, which you can send to your contact at your school. You will need to submit it as part of your application."
  - **Should be:** Either hide the download paragraph together with its button until the letter is re-enabled, or unhide the button and replace the paragraph with the current apply-now wording.

- **[OPEN · MINOR]** past-winners/index.html ("Brighstart initiative program" card, ~2025 grid) and past-winners/2/index.html (first card) (https://impactfund.wareham.stream/past-winners/ and /past-winners/2/)
  - [inherited] Winner card title reads "Brighstart initiative program" — misspelling of "Brightstart" (the card's own description says "The Brightstart initiative helps students..."). Present identically in _source/pages/past-winners.html and past-winners-2.html; the rebuild faithfully copied the typo.
  - **Should be:** Card title "Brightstart initiative program" (with the second "t").

- **[OPEN · MINOR (needs Alumo)]** past-winners/index.html and past-winners/2/index.html — all winner cards (https://impactfund.wareham.stream/past-winners/)
  - [inherited] All winner data is placeholder: every card on both pages (featured "MindWell student program" plus all grid cards) shares the exact same two-paragraph description about the "Brightstart initiative" regardless of the card's title, every award amount is "CAD 1.2M", and the schools cycle among three fictional names (Lakewood University, Riverton College Campus, Northbridge Polytechnic). Parity with the original is exact, but the page presents dummy data as past winners.
  - **Should be:** Real winner titles/descriptions/schools/amounts once the fund has actual winners (or an interim "winners coming soon" treatment); rebuild parity itself is correct.

- **[OPEN · MINOR]** apply-now/index.html, application form step 2 (https://impactfund.wareham.stream/apply-now/, visible with ?preview-form=1) — funding-amount acknowledgement text
  - [inherited] (form recreated from the live Gravity Forms form per the in-file comment; not present in the _source mirror) Grammar: "The Student Impact Fund won't cover cost of alcohol, prize giveaways, charitable donations." is missing an article and a conjunction.
  - **Should be:** "The Student Impact Fund won't cover the cost of alcohol, prize giveaways, or charitable donations."

- **[OPEN · MINOR]** apply-now/index.html, application form (https://impactfund.wareham.stream/apply-now/) — step labels, document labels, and question wording
  - [inherited] (form recreated from the live GF form) Inconsistent copy conventions within the form: step tabs mix case ("1. Contact information" vs "2. Project Information"); document labels mix case ("Team Members" in Title Case vs "Detailed budget", "Action plan and schedule", "Letter of support" in sentence case); spelling mixes -ise/-ize styles ("officially recognised", "analyse" alongside "Organization", "organization").
  - **Should be:** One convention throughout: sentence case for all step/field/document labels ("2. Project information", "Team members") and consistent Canadian -ize spelling ("recognized", "analyze").

- **[OPEN · MINOR (needs Alumo)]** terms-conditions/index.html (https://impactfund.wareham.stream/terms-conditions/) — line under the H1
  - [inherited] "Last updated: 2026" gives only a year, not an actual revision date, even though section 8 promises "Any changes will be posted on this page with a revised date." Identical in _source/pages/terms-conditions.html.
  - **Should be:** A full revision date, e.g. "Last updated: January 15, 2026".

## Content parity & language — FR

- **[OPEN · MAJOR]** fr/how-to-apply/index.html line 78 — https://impactfund.wareham.stream/fr/how-to-apply/
  - VERIFIED. The page H1 reads "Soumettre un projet" but the original FR page's visible H1 (_source/pages-fr/how-to-apply.html line 321, no elementor-hidden classes) is "Comment soumettre un projet"; the string "Soumettre un projet" never appears as a heading in the source. The EN rebuild (how-to-apply/index.html line 78) correctly kept the full original H1 "How to Submit a Project" (_source/pages/how-to-apply.html line 321), so this truncation is FR-only. No comment in the rebuild documents it as a deliberate deviation (the adjacent comment only covers hiding the banner).
  - **Should be:** H1 text "Comment soumettre un projet", matching _source/pages-fr/how-to-apply.html line 321.

- **[OPEN · MAJOR]** fr/partner-schools/index.html lines 83-92 (intro card, paragraph at line 85) — https://impactfund.wareham.stream/fr/partner-schools/
  - VERIFIED. The visible note sentence from the original intro card is missing: "Note : La liste des partenaires admissibles sera mise à jour avant l'ouverture de la période de candidature en septembre prochain. Consultez cette page en août pour obtenir la liste actualisée !" (_source/pages-fr/partner-schools.html line 368, inside a paragraph widget with no elementor-hidden classes). The rebuild keeps only a paraphrase in the meta/og description (lines 7 and 14), not in visible body copy. The same note was also dropped from the EN rebuild (partner-schools/index.html; original at _source/pages/partner-schools.html line 368: "Please note: The list of eligible partner schools will be updated before applications open this September..."). Context: the rebuild deliberately shows the school directory that the original hides at every breakpoint (documented in comments at lines 96-98), and this note was the original's visible stand-in for that hidden list — so the drop may be intentional but is undocumented.
  - **Should be:** Restore the note paragraph inside the intro card on both FR and EN pages (or, if its removal is a conscious choice because the school list is now shown, document it in the file comment; if the list is ever re-hidden to match the original, the note must return).

- **[OPEN · MAJOR]** fr/past-winners/index.html line 84 and fr/past-winners/2/index.html line 85 — https://impactfund.wareham.stream/fr/past-winners/ and /fr/past-winners/2/
  - [inherited] VERIFIED. The intro paragraph on both FR winners pages is untranslated English: "Get inspired by past winning projects that have helped improve campus life, submitted by students across Canada." The original FR site shows the identical English text as visible copy (_source/pages-fr/past-winners.html line 332, no elementor-hidden classes), so the rebuild copied it faithfully — but it is English body copy on a French page.
  - **Should be:** A French translation; the original FR home page already contains a suitable string for the same message (_source/pages-fr/home.html line 627): "Laissez-vous inspirer par les projets gagnants menés par des étudiant·es qui ont été soumis à travers le Québec et le Canada."

- **[OPEN · MAJOR (needs Alumo)]** fr/privacy-policy/index.html lines 85-90 — https://impactfund.wareham.stream/fr/privacy-policy/
  - [inherited] VERIFIED. The entire page body under the French H1 "Politique de confidentialité" is English lorem-style placeholder: headings "Subtitle #01/#02/#03" and the repeated paragraph "Our protection is straightforward and easy to understand..." (duplicated multiple times within single paragraphs). Identical placeholder exists in the original (_source/pages-fr/privacy-policy.html contains the same Subtitle #01-#03 headings and placeholder text), and the rebuild's own comment (lines 76-77) acknowledges reproducing it verbatim. This is consequential: every page footer links here, and fr/terms-conditions/index.html line 121 explicitly tells users to "consulter notre Politique de confidentialité" for data-handling details — which do not exist.
  - **Should be:** A real French privacy policy replacing the Subtitle #01-#03 placeholder headings and paragraphs.

- **[OPEN · MAJOR (needs Alumo)]** fr/cookies-policy/index.html lines 83-88 — https://impactfund.wareham.stream/fr/cookies-policy/
  - [inherited] VERIFIED. Same defect as the privacy page: under the "Cookies" H1, the whole body is the English "Subtitle #01/#02/#03" + "Our protection is straightforward..." placeholder, with no actual cookies policy. The original FR page carries the identical placeholder (_source/pages-fr/cookies-policy.html), and the rebuild's comment (lines 76-77) notes it was reproduced verbatim because TranslatePress never translated it. Footer links on every page point here.
  - **Should be:** A real French cookies policy replacing the placeholder content.

- **[OPEN · MINOR]** fr/index.html lines 98, 260, 269, 283, 288 — https://impactfund.wareham.stream/fr/
  - [inherited] French typos in the home hero and FAQ, all reproduced verbatim from the original: line 98 "Soumissions ouvrent en Septembre" (month capitalized, missing article); line 260 "Quels types de documents dois-soumettre avec mon projet ?" (missing "je"); line 269 "dans les semaines suivants la date limite", "un personne du Fonds", "des résulats", "des montants attributés"; line 283 "à un niveau différent que la demande formulée"; line 288 "Comment les projets gagnant sont-ils sélectionnés ?". (Note: the rebuild already fixed the original's "lettre de parrainage.." double period.)
  - **Should be:** "Les soumissions ouvrent en septembre"; "dois-je soumettre"; "dans les semaines suivant la date limite"; "une personne"; "résultats"; "attribués"; "différent de la demande"; "les projets gagnants".

- **[OPEN · MINOR]** fr/about-the-fund/index.html lines 73, 121, 211, 218, 219, 227 — https://impactfund.wareham.stream/fr/about-the-fund/
  - [inherited] Grammar/typo issues copied from the original: line 73 "un programme national de subventions créées par Alumo" (participle should agree with "programme"); line 121 "Être neutre sur le plan politique et dépourvu d'objectif commercial" (subject is plural "Les projets doivent"); line 211 "Notre but est de vous donnez les moyens"; line 218 "ouvrent en Septembre 2026" (capitalized month); line 219 "notre prochain période de soumission" (période is feminine); line 227 "Les projets étudiants renforce le sentiment…".
  - **Should be:** "créé par Alumo"; "Être neutres … et dépourvus"; "de vous donner les moyens"; "en septembre 2026"; "notre prochaine période"; "Les projets étudiants renforcent".

- **[OPEN · MINOR]** fr/apply-now/index.html lines 79 and 92 — https://impactfund.wareham.stream/fr/apply-now/
  - [inherited] Line 79: visible download button "Lettre de parraînage" misspells parrainage with a circumflex (verbatim from source; the same typo also sits in the hidden button stubs at fr/partner-schools/index.html lines 96 and 113). Line 92: "Les étudiant·es inscrit·es dans une école partenaire peut soumettre un projet" — plural subject with singular verb.
  - **Should be:** "Lettre de parrainage" (all occurrences); "peuvent soumettre un projet".

- **[OPEN · MINOR]** fr/past-winners/index.html lines 88, 160 (and fr/past-winners/2/index.html lines 89, 96) — https://impactfund.wareham.stream/fr/past-winners/
  - [inherited] Year-filter "all" button is labeled "Toute" (original's odd label; EN uses "All"); winner card title "Programme d'initiative Brighstart" misspells Brightstart (its own body text spells it correctly). Additionally, all six winner cards on both FR pages carry identical placeholder demo copy (same two Brightstart paragraphs, fictional schools "Université Lakewood"/"Polytechnique de Northbridge"/"Campus universitaire de Riverton", amount "CAD 1.2M") — faithful to the original, which never had real winner data.
  - **Should be:** "Tous" (or "Toutes les années") for the filter; "Brightstart" in the card title; eventually real winner content to replace the demo cards.

- **[OPEN · MINOR]** fr/partner-schools/index.html lines 137-178 (province cards) — https://impactfund.wareham.stream/fr/partner-schools/
  - [inherited] French province names lack their hyphens/accents, copied verbatim from the original's (hidden) FR list: "Colombie Britannique", "Nouveau Brunswick", "Terre Neuve et Labrador", "Nouvelle Écosse", "Île du Prince Edward". Now user-visible because the rebuild shows the directory that the original kept hidden.
  - **Should be:** "Colombie-Britannique", "Nouveau-Brunswick", "Terre-Neuve-et-Labrador", "Nouvelle-Écosse", "Île-du-Prince-Édouard".

- **[OPEN · MINOR]** fr/index.html line 189 (period card button) — https://impactfund.wareham.stream/fr/
  - The submission-period card's "En savoir plus" button is visible at every width in the rebuild, but the original hides it: the EN original hides it at all five Elementor breakpoints, and the FR original hides it everywhere except the "laptop" breakpoint (an authoring quirk). Other always-hidden elements (header "Soumettre" button, About/past-winners contact forms) were correctly kept hidden with explanatory comments — this one has no comment, so it looks like an oversight rather than a decision.
  - **Should be:** Either hide the button (matching the original's intent while submissions are closed, like the "Soumettre" button pattern) or add a comment documenting it as a deliberate deviation. Same applies to the EN home (index.html line ~186).

- **[OPEN · MINOR]** fr/index.html lines 309-317 (contact form labels) — https://impactfund.wareham.stream/fr/#contact-form
  - The contact form's required-field indicator is an asterisk ("Nom*", "Courriel*", "Message*") whereas the original Gravity Forms rendering shows the visible text "(Nécessaire)" after each label (class gfield_required_text, not screen-reader-only).
  - **Should be:** "Nom (Nécessaire)", "Courriel (Nécessaire)", "Message (Nécessaire)" for exact parity — or keep the asterisk as a conscious simplification (EN page has the same deviation vs "(Required)").

- **[RESOLVED · MINOR]** fr/index.html line 37 (header nav) vs line 346 (footer nav) — all 10 FR pages
  - [inherited] The header menu labels the how-to-apply link "Soumissions de projets" (plural) while the footer menu labels the same link "Soumission de projets" (singular); body copy also references "la page Soumission de projets" (singular). The inconsistency exists on the original and was copied to every FR page.
  - **Should be:** One consistent label (the original's body text favors the singular "Soumission de projets") in both header and footer across the 10 FR pages.
  - Status: RESOLVED 2026-08-26 — header/footer nav renamed to "Comment soumettre" / "How to submit" (client edits round), eliminating the plural/singular mismatch.

## Spelling & grammar — EN (incl. schools data)

- **[RESOLVED · MAJOR]** C:/Users/atp2txw/PycharmProjects/Alumo Website/js/schools-data.js lines 407-408 — rendered verbatim in the Ontario schools table on https://impactfund.wareham.stream/partner-schools/ (via js/partner-schools.js lines 118-121) and as an option in the Institution/Association dropdown on https://impactfund.wareham.stream/apply-now/ (via js/apply-form.js lines 116-126); same strings on the FR pages /fr/partner-schools/ and /fr/apply-now/
  - [inherited — present verbatim in the client-provided schools dataset (_tools/partner-schools-raw.tsv line 68, provided 2026-08-18), not introduced by the rebuild] An internal editorial note is shown to the public: both the school and association fields read "Lambton College (Institution - confirm with Darren)". CONFIRMED: the render paths use textContent/option text verbatim, so site visitors see the "confirm with Darren" note in the table and the form dropdown.
  - **Should be:** "Lambton College (Institution)" (matching the tag convention of sibling entries such as "Loyalist College (Institution)") or plain "Lambton College" — the "- confirm with Darren" note must be removed from both fields and resolved with the client outside display strings.
  - Status: RESOLVED — 'confirm with Darren' note gone from js/schools-data.js — fixed by Sept-2026 partner list (2026-08-26).

- **[RESOLVED · MAJOR]** C:/Users/atp2txw/PycharmProjects/Alumo Website/js/schools-data.js lines 383-385 — rendered verbatim on https://impactfund.wareham.stream/partner-schools/ and in the Institution/Association dropdown on https://impactfund.wareham.stream/apply-now/ (and the FR equivalents)
  - [inherited — identical strings and province code in the client-provided dataset, _tools/partner-schools-raw.tsv line 64] Two defects in one entry: (1) "Instituion" is misspelled in both the school and association fields ("Burman University (Instituion)"); (2) the entry has "province": "ON" although Burman University is in Lacombe, Alberta (email domain burmanu.ca matches), so it appears under the Ontario province card and is missing from the Alberta filter. CONFIRMED against the data file and the province-filter logic in js/partner-schools.js (rows filtered by school.province === two-letter code).
  - **Should be:** "Burman University (Institution)" (or plain "Burman University") in both fields, with "province": "AB".
  - Status: RESOLVED — Burman University now province AB, association N/A, no 'Instituion' — fixed by Sept-2026 list (2026-08-26).

- **[OPEN · MINOR]** index.html line 215 (https://impactfund.wareham.stream/ — "Every idea is a good idea" section)
  - [inherited] Misspelling: "We know campus life is built on social engagment." (Note: the original's second typo in this sentence, "inititatives", was already fixed in the rebuild.)
  - **Should be:** "social engagement"

- **[OPEN · MINOR]** index.html line 214 (https://impactfund.wareham.stream/)
  - [inherited] Heading "Every idea is a good idea - Submit it!" uses a spaced hyphen as a dash.
  - **Should be:** An em dash (or en dash): "Every idea is a good idea — Submit it!"

- **[OPEN · MINOR]** index.html line 285 (https://impactfund.wareham.stream/ — FAQ "What is the maximum amount I can receive for my project?")
  - [inherited] Inconsistent terminology within one answer: the bullet says "Recurrent projects: up to $3,000*" but the footnote says "*Recurring projects that include...".
  - **Should be:** Use one term in both places, preferably "Recurring projects: up to $3,000*".

- **[OPEN · MINOR]** index.html lines 158, 255, and 262 (https://impactfund.wareham.stream/)
  - [inherited] Singular/plural inconsistency: section heading "What type of projects are eligible?" (line 158) vs FAQ "What types of projects are eligible?" (line 255); FAQ line 262 also has "What type of documents do I need to submit".
  - **Should be:** Standardize on the plural: "What types of projects are eligible?" and "What types of documents do I need to submit with my project?"

- **[OPEN · MINOR]** about-the-fund/index.html line 151 (https://impactfund.wareham.stream/about-the-fund/ — "Ineligible projects" list)
  - [inherited] Punctuation/usage error: "Projects without clear benefit to students on campus. i.e. can’t be for personal gain or travel" — a full stop is used mid-sentence before a lowercase "i.e.", and "i.e." (that is) is misused where examples are meant.
  - **Should be:** Something like "Projects without clear benefit to students on campus (e.g., projects for personal gain or travel)" — comma or parenthesis instead of the period, and "e.g." instead of "i.e."

- **[OPEN · MINOR]** about-the-fund/index.html line 180 (https://impactfund.wareham.stream/about-the-fund/ — "The Fund cannot be used for" list)
  - [inherited] Logic/grammar mismatch with the list heading: the item "Food costs can be covered but cannot make up more than 30% of funding." sits under the heading "The Fund cannot be used for", so it reads as a contradiction.
  - **Should be:** Reword the item to fit the heading, e.g. "Food costs exceeding 30% of the funding (food can be covered up to 30%)".

- **[OPEN · MINOR]** partner-schools/index.html line 88 (https://impactfund.wareham.stream/partner-schools/ — Partners intro)
  - [inherited] Garbled sentence with stacked "that" clauses and no terminal period: "Download the Support letter form that you can send to your contact that you will need to provide during the submission". The apply-now page already carries a cleaned-up rewrite of this same sentence.
  - **Should be:** Match the apply-now wording, e.g. "Download the letter of support, which you can send to your contact at your school. You will need to submit it as part of your application."

- **[OPEN · MINOR]** apply-now/index.html line 206 (https://impactfund.wareham.stream/apply-now/ — Organization information) vs terms-conditions/index.html line 79
  - Inconsistent -ise/-ize spelling across the site: the apply form asks "Is this a group officially recognised by your campus?" while the Terms page uses "recognized post-secondary institution". (Not verifiable against the _source mirror — the original form markup isn't in it — so possibly transcribed from the live form.)
  - **Should be:** "recognized" (Canadian English prefers -ize, and it matches the Terms page).

- **[OPEN · MINOR]** apply-now/index.html line 344 (https://impactfund.wareham.stream/apply-now/ — fine print above submit)
  - Inconsistent -yse/-yze spelling: "All data provided will be used by Alumo to analyse project submissions" while the rest of the site uses -ize forms (recognized, prioritized, maximize). (Not verifiable against the _source mirror.)
  - **Should be:** "analyze"

- **[OPEN · MINOR]** apply-now/index.html line 263 (https://impactfund.wareham.stream/apply-now/ — acknowledgement checkbox)
  - Grammar: "The Student Impact Fund won't cover cost of alcohol, prize giveaways, charitable donations." — missing article before "cost" and missing conjunction before the last list item. (Not verifiable against the _source mirror.)
  - **Should be:** "The Student Impact Fund won't cover the cost of alcohol, prize giveaways, or charitable donations."

- **[OPEN · MINOR]** apply-now/index.html lines 308, 314, 320, 326, 332 and the letter-of-support field (https://impactfund.wareham.stream/apply-now/ — Required documents)
  - Comma splice in all five file-upload hints: "Accepted file types: doc, docx, xls, xlsx, csv, pdf, Max. file size: 10 MB." — a comma joins two independent statements (this mirrors Gravity Forms' default rendering on the live original, so likely inherited from the live form).
  - **Should be:** "Accepted file types: doc, docx, xls, xlsx, csv, pdf. Max. file size: 10 MB."

- **[OPEN · MINOR]** past-winners/index.html line 159 and past-winners/2/index.html line 94 (https://impactfund.wareham.stream/past-winners/ and /past-winners/2/)
  - [inherited] Misspelled winner-card title "Brighstart initiative program" — the card's own description spells it "Brightstart".
  - **Should be:** "Brightstart initiative program"

- **[OPEN · MINOR (needs Alumo)]** terms-conditions/index.html line 67 (https://impactfund.wareham.stream/terms-conditions/)
  - [inherited] Incomplete date: "Last updated: 2026" gives only a year, though section 8 promises "a revised date" for changes.
  - **Should be:** A full date, e.g. "Last updated: January 15, 2026" (confirm actual date with Alumo).

- **[RESOLVED · MINOR]** js/schools-data.js lines 24, 60, 66, 78, 84, 270, 324, 330 (displayed on /partner-schools/ and /apply-now/)
  - [inherited — client-provided dataset] "Association" is misspelled in eight association names: "Assoication" (lines 24, 78), "Assocation" (lines 60, 66, 84, 270, 330), and "Asscoiation" (line 324, WUSA).
  - **Should be:** "Association" in all eight strings (e.g. "Concordia University of Edmonton Student Association", "Students' Association of MacEwan University", "Waterloo Undergraduate Student Association (WUSA)", etc.).
  - Status: RESOLVED — all Assoication/Assocation/Asscoiation misspellings gone — fixed by Sept-2026 list + cleaning pass (2026-08-26).

- **[RESOLVED · MINOR]** js/schools-data.js line 167 (displayed on /partner-schools/ and /apply-now/)
  - [inherited — client-provided dataset] School name misspelled: "Kwatlen Polytechnic". Students typing the correct "Kwantlen" into the partner-schools search box get "No results found".
  - **Should be:** "Kwantlen Polytechnic University"
  - Status: RESOLVED — now 'Kwantlen Polytechnic University' — fixed by Sept-2026 list (2026-08-26).

- **[RESOLVED · MINOR]** js/schools-data.js lines 335-336 (displayed on /partner-schools/ and /apply-now/)
  - [inherited — client-provided dataset] "Wilfred Laurier University" misspelled in both the school and association fields; searching the correct "Wilfrid" finds nothing.
  - **Should be:** "Wilfrid Laurier University" / "Wilfrid Laurier University Graduate Students' Association (WLUGSA)"
  - Status: RESOLVED — now 'Wilfrid Laurier University' — fixed in the 2026-08-26 cleaning pass.

- **[RESOLVED · MINOR]** js/schools-data.js line 198 (displayed on /partner-schools/)
  - [inherited — client-provided dataset] Misspelling: "University of Victoria Gradaute Students' Society (UVicGSS)".
  - **Should be:** "University of Victoria Graduate Students' Society (UVicGSS)"
  - Status: RESOLVED — now 'Graduate' (UVicGSS) — fixed by Sept-2026 list (2026-08-26).

- **[RESOLVED · MINOR]** js/schools-data.js lines 293-294 and 299-300 (displayed on /partner-schools/ and /apply-now/)
  - [inherited — client-provided dataset] Missing apostrophe: "Queens University" in two school entries and their association names ("Queens AMS", "Queens SGPS").
  - **Should be:** "Queen's University" (and "Queen's AMS" / "Queen's SGPS" in the association strings).
  - Status: RESOLVED — school now 'Queen's University'; note the '(Queens AMS)' abbreviation still lacks the apostrophe (Sept-2026 list, 2026-08-26).

- **[RESOLVED · MINOR]** js/schools-data.js line 185 (displayed on /partner-schools/ and /apply-now/)
  - [inherited — client-provided dataset] School name wrong: "University of Fraser Valley".
  - **Should be:** "University of the Fraser Valley"
  - Status: RESOLVED — now 'University of the Fraser Valley' — cleaning pass (2026-08-26).

- **[RESOLVED · MINOR]** js/schools-data.js lines 323 and 329 (displayed on /partner-schools/ and /apply-now/)
  - [inherited — client-provided dataset] School name inverted: "Waterloo University" in two entries; the line-330 association string itself uses the correct "University of Waterloo".
  - **Should be:** "University of Waterloo"
  - Status: RESOLVED — now 'University of Waterloo' — cleaning pass (2026-08-26).

- **[OPEN · MINOR]** js/schools-data.js line 5 (displayed on /partner-schools/ and /apply-now/)
  - [inherited — client-provided dataset] Wrong capitalization: school "Norquest College" while its own association string (line 6) spells it "NorQuest College".
  - **Should be:** "NorQuest College"

- **[RESOLVED · MINOR]** js/schools-data.js line 41 (displayed on /partner-schools/ and /apply-now/)
  - [inherited — client-provided dataset] School name abbreviated/garbled: "Northern Alberta IT" (paired with association "NAITSA").
  - **Should be:** "Northern Alberta Institute of Technology (NAIT)"
  - Status: RESOLVED — now 'Northern Alberta Institute of Technology (NAIT)' — cleaning pass (2026-08-26).

- **[RESOLVED · MINOR]** js/schools-data.js lines 47-48 (displayed on /partner-schools/ and /apply-now/)
  - [inherited — client-provided dataset] Institution name wrong: "University of Alberta Arts" — the school is Alberta University of the Arts (AUArts; the entry's own email domain is auarts.ca). The association string "University of Alberta Arts Students Union" repeats the error.
  - **Should be:** School: "Alberta University of the Arts"; association: "Alberta University of the Arts Students' Association" (confirm exact association name with Alumo).
  - Status: RESOLVED — now 'Alberta University of the Arts' / 'Alberta University of the Arts Students' Union (AUArts)' — cleaning pass (2026-08-26).

- **[OPEN · MINOR]** js/schools-data.js line 252 (displayed on /partner-schools/)
  - [inherited — client-provided dataset] Missing space / non-standard styling: "St.FX Students' Union".
  - **Should be:** "StFX Students' Union" (the union's own styling) or "St. FX Students' Union".

- **[RESOLVED · MINOR]** js/schools-data.js lines 395-396 (displayed on /partner-schools/ and /apply-now/)
  - [inherited — client-provided dataset] Wrong possessive: "Fanshawe Student's Union - FSU (SA)" — the organization's name is Fanshawe Student Union (no apostrophe-s).
  - **Should be:** "Fanshawe Student Union - FSU (SA)" (or just "Fanshawe Student Union (FSU)").
  - Status: RESOLVED — now 'Fanshawe Student Union (FSU)' — Sept-2026 list (2026-08-26).

- **[RESOLVED · MINOR]** js/schools-data.js lines 527 and 533 (displayed on /partner-schools/ and /apply-now/)
  - [inherited — client-provided dataset] Institution name wrong: "Université de Laval" — the university's name is Université Laval (no "de"); both entries' own association strings say "de l'Université Laval".
  - **Should be:** "Université Laval"
  - Status: RESOLVED — now 'Université Laval' — cleaning pass (2026-08-26).

- **[RESOLVED · MINOR]** js/schools-data.js line 528 (displayed on /partner-schools/)
  - [inherited — client-provided dataset] Missing plural in the CADEUL name: "Confédération des association d'étudiants et d'étudiantes de l'Université Laval".
  - **Should be:** "Confédération des associations d'étudiants et d'étudiantes de l'Université Laval (CADEUL)"
  - Status: RESOLVED — now 'Confédération des associations…' — Sept-2026 list (2026-08-26).

- **[RESOLVED · MINOR]** js/schools-data.js line 492 (displayed on /partner-schools/)
  - [inherited — client-provided dataset] Grammar error in the AECS name: "Association des étudiants au cycles supérieurs de HEC Montréal" — singular "au" with plural "cycles".
  - **Should be:** "Association des étudiants aux cycles supérieurs de HEC Montréal (AECS-HEC)"
  - Status: RESOLVED — now 'aux cycles supérieurs' (AECS-HEC) — Sept-2026 list (2026-08-26).

- **[RESOLVED · MINOR]** js/schools-data.js line 540 (displayed on /partner-schools/)
  - [inherited — client-provided dataset] Missing plural in the AFESH name: "Association facultaire étudiante des science humaines de l'UQAM".
  - **Should be:** "Association facultaire étudiante des sciences humaines de l'UQAM (AFESH)"
  - Status: RESOLVED — now 'des sciences humaines' (AFESH) — Sept-2026 list (2026-08-26).

- **[RESOLVED · MINOR]** js/schools-data.js line 581 (displayed on /partner-schools/ and /apply-now/)
  - [inherited — client-provided dataset] Institution name wrong: "Université du Québec à Gatineau" — no such name; the institution is Université du Québec en Outaouais, as its own association string (AGE-UQO, "en Outaouais") shows.
  - **Should be:** "Université du Québec en Outaouais"
  - Status: RESOLVED — now 'Université du Québec en Outaouais' — cleaning pass (2026-08-26).

- **[RESOLVED · MINOR]** js/schools-data.js line 671 (displayed on /partner-schools/ and /apply-now/)
  - [inherited — client-provided dataset] School name misspelled: "Collège Ahunstic" — its own association string (line 672) spells it "Ahuntsic" correctly; searching "Ahuntsic" would miss this school-name match.
  - **Should be:** "Collège Ahuntsic"
  - Status: RESOLVED — now 'Collège Ahuntsic' — cleaning pass (2026-08-26).

- **[RESOLVED · MINOR]** js/schools-data.js line 713 (displayed on /partner-schools/ and /apply-now/)
  - [inherited — client-provided dataset] Capitalization: "Collège de Bois-de-boulogne" — lowercase second "boulogne".
  - **Should be:** "Collège de Bois-de-Boulogne"
  - Status: RESOLVED — now 'Collège de Bois-de-Boulogne' — cleaning pass (2026-08-26).

- **[OPEN · MINOR]** js/schools-data.js line 665 (displayed on /partner-schools/ and /apply-now/)
  - [inherited — client-provided dataset] Hyphenation inconsistency: school "Cégep du Vieux-Montréal" vs its association string "Cégep du Vieux Montréal" (line 666); the cégep's official name has no hyphen.
  - **Should be:** "Cégep du Vieux Montréal"

## Spelling & grammar — FR

- **[OPEN · MAJOR]** fr/past-winners/index.html line 84 and fr/past-winners/2/index.html line 85 — https://impactfund.wareham.stream/fr/past-winners/ and https://impactfund.wareham.stream/fr/past-winners/2/
  - [inherited] CONFIRMED: the intro paragraph on both French Gagnants pages is entirely in English: "Get inspired by past winning projects that have helped improve campus life, submitted by students across Canada." Inherited from the original WordPress FR pages (_source/pages-fr/past-winners.html line 332 and _source/pages-fr/past-winners-2.html line 374 contain the identical English sentence). The rebuild even flags it with an HTML comment noting it was kept verbatim.
  - **Should be:** A French translation, e.g. "Inspirez-vous des projets gagnants qui ont contribué à améliorer la vie sur les campus, soumis par des étudiants de partout au Canada." — the same page's own French meta description (fr/past-winners/index.html line 7) already uses this phrasing: "Inspirez-vous des projets gagnants du Fonds d'impact étudiant par Alumo…"

- **[OPEN · MAJOR (needs Alumo)]** fr/privacy-policy/index.html lines 85-90 (legal-text-box) — https://impactfund.wareham.stream/fr/privacy-policy/
  - [inherited] CONFIRMED: the entire body of the French Politique de confidentialité page is untranslated English placeholder copy — headings "Subtitle #01/#02/#03" and the repeated paragraph "Our protection is straightforward and easy to understand, designed with students in mind…". Identical placeholder confirmed in the original _source/pages-fr/privacy-policy.html; the rebuild reproduces it verbatim (noted in an HTML comment at lines 76-77). Note the same text is placeholder copy on the EN page too, so the underlying fix is real legal copy in both languages.
  - **Should be:** Real French privacy-policy copy under French subheadings (or, at minimum, a French translation of the placeholder text until real legal copy exists).

- **[OPEN · MAJOR (needs Alumo)]** fr/cookies-policy/index.html lines 83-88 (legal-text-box) — https://impactfund.wareham.stream/fr/cookies-policy/
  - [inherited] CONFIRMED: the entire body of the French Cookies page is the same untranslated English placeholder copy ("Subtitle #01/#02/#03", "Our protection is straightforward and easy to understand…"). Identical placeholder confirmed in the original _source/pages-fr/cookies-policy.html; the rebuild reproduces it verbatim (HTML comment at lines 76-77 acknowledges TranslatePress never translated it). As with the privacy page, the EN version carries the same placeholder, so real cookies-policy copy is ultimately needed in both languages.
  - **Should be:** Real French cookies-policy copy (or a French translation of the placeholder text until real legal copy exists).

- **[OPEN · MINOR]** fr/index.html line 98 (hero status pill) — https://impactfund.wareham.stream/fr/
  - [inherited] "Soumissions ouvrent en Septembre": month capitalized (months are lowercase in French) and the article is missing before "Soumissions".
  - **Should be:** "Les soumissions ouvrent en septembre".

- **[OPEN · MINOR]** fr/index.html line 257 (FAQ question 3) — https://impactfund.wareham.stream/fr/#faq-section
  - [inherited] "Quels types de documents dois-soumettre avec mon projet ?" — the subject pronoun "je" is missing after "dois".
  - **Should be:** "Quels types de documents dois-je soumettre avec mon projet ?"

- **[RESOLVED · MINOR]** fr/index.html line 259 (FAQ answer 3, end of list) — https://impactfund.wareham.stream/fr/#faq-section
  - [inherited] Double period at the end of the documents list: "Une lettre de parrainage.</strong></a>." renders as "parrainage..".
  - **Should be:** A single period: "Une lettre de parrainage."
  - Status: RESOLVED — current fr/index.html has no trailing period after the parrainage link — already fixed in the rebuild (verified 2026-08-26).

- **[OPEN · MINOR]** fr/index.html line 259 (FAQ answer 3) — https://impactfund.wareham.stream/fr/#faq-section
  - [inherited] Anglicisms in the documents list: "l'introduction des membres du groupe" (calque of English "introduction") and "Une ligne du temps détaillée" (calque of "timeline" — OQLF prefers échéancier/calendrier for project schedules).
  - **Should be:** "la présentation des membres du groupe" and "Un échéancier détaillé" (or "Un calendrier détaillé").

- **[OPEN · MINOR]** fr/index.html line 266 (FAQ answer 4, "À quoi dois-je m'attendre…") — https://impactfund.wareham.stream/fr/#faq-section
  - [inherited] Five faults in one paragraph: "dans les semaines suivants la date limite" (invariable preposition), "Lorsqu'applicable" (calque of "when applicable"), "un personne" (wrong gender), "résulats" (misspelling), "montants attributés" (misspelling).
  - **Should be:** "dans les semaines suivant la date limite. Le cas échéant, une personne du Fonds d'impact étudiant communiquera avec vous afin de vous informer des résultats et des montants attribués."

- **[OPEN · MINOR]** fr/index.html line 280 (FAQ answer 6) — https://impactfund.wareham.stream/fr/#faq-section
  - [inherited] "financer un projet à un niveau différent que la demande formulée" — "différent que" is incorrect; "différent" takes "de".
  - **Should be:** "à un niveau différent de la demande formulée" (or "de celui demandé").

- **[OPEN · MINOR]** fr/index.html line 285 (FAQ question 7) — https://impactfund.wareham.stream/fr/#faq-section
  - [inherited] "Comment les projets gagnant sont-ils sélectionnés ?" — missing plural agreement on "gagnant".
  - **Should be:** "Comment les projets gagnants sont-ils sélectionnés ?"

- **[OPEN · MINOR]** fr/index.html line 145 (card "Qui est Alumo ?") — https://impactfund.wareham.stream/fr/
  - [inherited] "Nous offrons des Régimes de santé et dentaires" — unjustified mid-sentence capital on "Régimes" and awkward coordination ("régimes de santé et dentaires").
  - **Should be:** "des régimes de soins de santé et dentaires" (lowercase r).

- **[OPEN · MINOR]** fr/index.html line 74 (hero subtitle) — https://impactfund.wareham.stream/fr/
  - [inherited] "250 000 $ entièrement dédiés aux projets" — "dédié" for funds is an anglicism (dedicated); the About page correctly uses "consacré" for the same idea, so it is also inconsistent.
  - **Should be:** "250 000 $ entièrement consacrés aux projets…"

- **[RESOLVED · MINOR]** fr/index.html line 37 (header nav) vs line 343 (footer nav) — all 10 FR pages, e.g. https://impactfund.wareham.stream/fr/
  - [inherited] The header menu says "Soumissions de projets" (plural) while the footer menu and all in-text links say "Soumission de projets" (singular). Original site has the same header/footer mismatch.
  - **Should be:** One consistent label everywhere — "Soumission de projets" (singular, the majority form) in both header and footer.
  - Status: RESOLVED 2026-08-26 — header/footer nav renamed to "Comment soumettre" / "How to submit" (client edits round), eliminating the plural/singular mismatch.

- **[OPEN · MINOR]** fr/index.html line 187 (period-note); also fr/about-the-fund/index.html lines 106, 198, 245; fr/how-to-apply/index.html line 125; fr/partner-schools/index.html line 123
  - [inherited] Spacing before punctuation is inconsistent across the FR pages: the orange-card note uses &thinsp; before ? and ! ("concrétiser&thinsp;?"), about-the-fund uses &thinsp; before ! and &nbsp; before : in one paragraph (line 106), while every other ?, ! and : sits after a plain breaking space ("Des questions ?", "date limite de soumission :", "Sélectionnez votre province :"), and one semicolon has a plain space before it ("reversés ; toutefois", about line 198). Original mixes literal U+2009/U+00A0/plain spaces the same way.
  - **Should be:** One convention site-wide: either OQLF style (no space before ? ! ;, non-breaking space before :) or French-France style (narrow no-break space before ? ! ; and non-breaking space before :) — applied to every occurrence.

- **[OPEN · MINOR]** fr/about-the-fund/index.html line 73 (hero paragraph) — https://impactfund.wareham.stream/fr/about-the-fund/
  - [inherited] "un programme national de subventions créées par Alumo" — the participle agrees with "subventions" but the meaning is that the program was created by Alumo (the page's own meta description says "créé par Alumo").
  - **Should be:** "un programme national de subventions créé par Alumo".

- **[OPEN · MINOR]** fr/about-the-fund/index.html line 73 (hero paragraph) — https://impactfund.wareham.stream/fr/about-the-fund/
  - [inherited] "à travers tout le Québec et le Canada" — "à travers" for geographic extent is a calque of "across".
  - **Should be:** "partout au Québec et au Canada" (as used in the past-winners meta description).

- **[OPEN · MINOR]** fr/about-the-fund/index.html line 121 ("Les projets doivent" list, item 2) — https://impactfund.wareham.stream/fr/about-the-fund/#eligibility
  - [inherited] "Être neutre sur le plan politique et dépourvu d'objectif commercial" — singular adjectives while the subject is "Les projets" (item 1 correctly uses plural "Être initiés").
  - **Should be:** "Être neutres sur le plan politique et dépourvus d'objectif commercial".

- **[OPEN · MINOR]** fr/about-the-fund/index.html line 211 (goal heading) — https://impactfund.wareham.stream/fr/about-the-fund/
  - [inherited] "Notre but est de vous donnez les moyens…" — conjugated "donnez" instead of the infinitive after "de", in a large display heading.
  - **Should be:** "Notre but est de vous donner les moyens et le soutien nécessaires pour réaliser les projets qui vous tiennent à cœur." (also pluralize "nécessaires" to cover both nouns).

- **[OPEN · MINOR]** fr/about-the-fund/index.html line 218 (goal card 1) — https://impactfund.wareham.stream/fr/about-the-fund/
  - [inherited] "Les soumissions ouvrent en Septembre 2026" — month capitalized.
  - **Should be:** "Les soumissions ouvrent en septembre 2026".

- **[OPEN · MINOR]** fr/about-the-fund/index.html line 219 (goal card 1) — https://impactfund.wareham.stream/fr/about-the-fund/
  - [inherited] "Restez à l'affût de notre prochain période de soumission" — wrong gender agreement, "période" is feminine.
  - **Should be:** "notre prochaine période de soumission".

- **[OPEN · MINOR]** fr/about-the-fund/index.html line 227 (goal card 2) — https://impactfund.wareham.stream/fr/about-the-fund/
  - [inherited] "Les projets étudiants renforce le sentiment d'appartenance … de contribuer à sa communauté" — verb not agreeing with plural subject, and "sa" should be "leur" with the plural subject (the home-page version with singular "Chaque projet étudiant" is correct).
  - **Should be:** "Les projets étudiants renforcent le sentiment d'appartenance, la confiance et la volonté de contribuer à leur communauté…"

- **[OPEN · MINOR]** fr/how-to-apply/index.html line 79 (intro) — https://impactfund.wareham.stream/fr/how-to-apply/
  - [inherited] "C'est facile de postuler !" — "postuler" is job-application vocabulary (calque of "to apply"); the rest of the site consistently says "soumettre un projet".
  - **Should be:** "C'est facile de soumettre un projet !" (or "de soumettre votre projet").

- **[OPEN · MINOR]** fr/how-to-apply/index.html lines 89, 94, 103 (step titles) — https://impactfund.wareham.stream/fr/how-to-apply/
  - [inherited] Mixed verb moods across the three steps — "1. Vérifier l'admissibilité" (infinitive), "2. Complétez votre demande" (imperative), "3. Vérifier et soumettre" (infinitive) — and "compléter une demande" is an OQLF-flagged anglicism (to complete).
  - **Should be:** Uniform mood, e.g. "1. Vérifiez l'admissibilité / 2. Remplissez votre demande / 3. Vérifiez et soumettez" (or all infinitives with "Remplir").

- **[OPEN · MINOR]** fr/how-to-apply/index.html line 97 (step 2 document list) — https://impactfund.wareham.stream/fr/how-to-apply/
  - [inherited] Terminology inconsistency: this page calls the required letter "Lettre de soutien" while the home FAQ, apply-now page and partner-schools page all call it "lettre de parrainage".
  - **Should be:** Use "Lettre de parrainage" here to match the rest of the FR site (and the actual PDF offered).

- **[OPEN · MINOR]** fr/apply-now/index.html line 79 (download button) — https://impactfund.wareham.stream/fr/apply-now/
  - [inherited] Button label "Lettre de parraînage" — spurious circumflex; the word is "parrainage". Same misspelling sits in hidden buttons at fr/partner-schools/index.html lines 96 and 113 (will surface if those are re-enabled). Original source has "parraînage" twice.
  - **Should be:** "Lettre de parrainage".

- **[OPEN · MINOR]** fr/apply-now/index.html line 92 (info card 1) — https://impactfund.wareham.stream/fr/apply-now/
  - [inherited] "Les étudiant·es inscrit·es dans une école partenaire peut soumettre un projet" — singular verb with plural subject.
  - **Should be:** "…peuvent soumettre un projet, avec l'accord du campus."

- **[OPEN · MINOR]** fr/apply-now/index.html line 97 (info card 2) — https://impactfund.wareham.stream/fr/apply-now/
  - [inherited] "consultez la page <a>À propos</a> ." — stray space before the final period (renders "À propos .").
  - **Should be:** Period immediately after the link: "…la page À propos."

- **[OPEN · MINOR]** fr/index.html line 252 (FAQ answer 2) — https://impactfund.wareham.stream/fr/#faq-section
  - [inherited] "visitez la page <a>Soumission de projets</a> ." — stray space before the final period (renders "Soumission de projets .").
  - **Should be:** Period immediately after the link: "…Soumission de projets."

- **[OPEN · MINOR]** fr/past-winners/index.html line 88 and fr/past-winners/2/index.html line 89 (year filter) — https://impactfund.wareham.stream/fr/past-winners/
  - [inherited] Default filter button reads "Toute" — wrong number/gender for an "all years" filter.
  - **Should be:** "Toutes" (les années) — or "Tous" if it means all projects.

- **[OPEN · MINOR]** fr/past-winners/index.html line 160 and fr/past-winners/2/index.html line 96 (winner card title) — https://impactfund.wareham.stream/fr/past-winners/
  - [inherited] Card title "Programme d'initiative Brighstart" — missing the second t; the card body spells the same project "Brightstart" three times.
  - **Should be:** "Programme d'initiative Brightstart" (note: the matching image file is also named winner-brighstart.jpg).

- **[OPEN · MINOR]** fr/past-winners/index.html lines 102, 115, 128, 141, 154, 167 (and same spots in fr/past-winners/2/index.html) — https://impactfund.wareham.stream/fr/past-winners/
  - [inherited] Award amounts shown as "CAD 1.2M" on the French pages — English currency format (code before amount, decimal point, M abbreviation); placeholder demo data carried over verbatim.
  - **Should be:** French-Canadian format, e.g. "1,2 M$ CA" (comma decimal, symbol after the amount).

- **[OPEN · MINOR]** fr/partner-schools/index.html lines 137, 145, 149, 157, 166 (province cards) — https://impactfund.wareham.stream/fr/partner-schools/
  - [inherited] Province names lack the hyphens of their official French forms, and one keeps the English first name: "Colombie Britannique", "Nouveau Brunswick", "Terre Neuve et Labrador", "Nouvelle Écosse", "Île du Prince Edward".
  - **Should be:** "Colombie-Britannique", "Nouveau-Brunswick", "Terre-Neuve-et-Labrador", "Nouvelle-Écosse", "Île-du-Prince-Édouard".

- **[OPEN · MINOR]** fr/partner-schools/index.html lines 200 and 215 (loader and empty-search message) — https://impactfund.wareham.stream/fr/partner-schools/
  - [inherited] Untranslated English UI strings on the FR page: "Loading schools..." (loader, normally never shown) and "No results found" (shown to users when a school search matches nothing). Build comments confirm the live FR site has the same.
  - **Should be:** "Chargement des écoles..." and "Aucun résultat trouvé".

## Links & assets (live crawl)

- **[OPEN · MAJOR]** index.html:374, fr/index.html:372, and the same footer block on all 20 pages (e.g. about-the-fund/index.html:351, fr/past-winners/2/index.html:271) — footer of every page at https://impactfund.wareham.stream/ and /fr/
  - [inherited] The footer 'Consent preferences' / 'Gérer mon consentement' button is a non-functional control: <a href="#" role="button"> with no JS handler anywhere (no consent/cookie/Didomi code in js/main.js or any script; the only preventDefault handlers are the form submitters). Verified fully inherited: on the original site the same anchor was also dead — href="" with no didomi-open-preferences class and no JS wiring to its Elementor id 47bae88 — so clicking it there merely reloaded the page. The real rebuild delta is site-level: the original loaded the Didomi consent SDK (sdk.privacy-center.org loader, API key cbf229c1-109a-46b2-80ba-20e070b390f9) which auto-displayed a consent notice, whereas the rebuild loads no consent mechanism at all, even though the cookies-policy and privacy-policy pages still describe managing consent.
  - **Should be:** Either wire the button to a real consent-preferences dialog (whatever tool replaces the Didomi/privacy-center SDK) or remove/hide it until one exists; if a consent tool is added, also ensure the cookies-policy and privacy-policy pages' descriptions of consent management match it. Note the button-opens-dialog behavior never worked on the original either, so 'parity with original' alone would still leave a dead control.

- **[OPEN · MINOR]** index.html:95 and fr/index.html:96 — https://impactfund.wareham.stream/ and /fr/ hero
  - [inherited] The hero status pill ('Submissions open in September' / FR equivalent) is an <a class="hero-status-pill" href="#"> styled as a clickable pill with an icon; clicking it just jumps to the top of the page. The original site had the same dead href="#" on this Elementor button.
  - **Should be:** Make it a non-interactive element (span/div) since it's informational, or point it at a meaningful target such as /how-to-apply/ or the #info-section dates block.

- **[OPEN · MINOR]** Lines 8-9 of every page's <head>, e.g. index.html:8-9, fr/past-winners/2/index.html:9-10 — all 20 pages
  - hreflang alternate links use relative URLs (e.g. <link rel="alternate" hreflang="fr" href="/fr/">). The hreflang spec (and Google) requires fully-qualified absolute URLs; relative values are ignored by crawlers. The original used absolute URLs and additionally declared an hreflang="fr-CA" alternate that the rebuild dropped. (All pairs do point at the correct existing counterparts — the pairing itself is right.)
  - **Should be:** Absolute URLs, e.g. href="https://impactfund.wareham.stream/fr/" (or the production domain once cut over), optionally restoring the fr-CA alternate for parity with the original.

- **[OPEN · MINOR]** privacy-policy/index.html, cookies-policy/index.html, fr/privacy-policy/index.html, fr/cookies-policy/index.html — https://impactfund.wareham.stream/privacy-policy/ etc.
  - [inherited] The on-site Privacy Policy and Cookies Policy pages are orphaned: no nav or footer link on any other page reaches them (only their own EN/FR language switcher references them). The footer 'Privacy Policy' link instead goes to the external Studentcare/ASEQ PDF. The original site had the same structure (its policy pages were only reachable via the consent SDK dialog, which the rebuild doesn't have), so these four pages are now unreachable by any navigation path.
  - **Should be:** Add footer links to /privacy-policy/ and /cookies-policy/ (and FR counterparts), or link them from the future consent-preferences dialog, so the pages are reachable.

- **[OPEN · MINOR]** how-to-apply/index.html:100 and fr/how-to-apply/index.html:99 — https://impactfund.wareham.stream/how-to-apply/ step text
  - [inherited] The 'Have questions? Contact us!' / 'Vous avez des questions ? Contactez-nous !' link to /#contact-form (an internal same-site anchor) carries target="_blank", so it opens the homepage in a new tab instead of navigating in place. Copied from the original, which had target="_blank" on its (also broken-URL) version of this link.
  - **Should be:** Remove target="_blank" (and the now-unneeded rel="noopener") so the internal contact-form anchor navigates in the same tab, matching every other /#contact-form link on the site.

## Forms front-end ↔ PHP backend contract

- **[OPEN · MINOR]** js/apply-form.js lines 273-276 (showStep hides statusEl at line 215) — https://impactfund.wareham.stream/apply-now/
  - When api/apply.php returns 422 with per-field errors, the error branch calls showStep(firstErrStep, true) — which sets statusEl.hidden = true — and only afterwards writes statusEl.textContent ('Please check the highlighted fields.') without unhiding it. The top-level error banner is therefore never visible whenever the server reports field errors (this happens for every 422, since all server field keys map to real inputs). Users still get the inline per-field messages and are scrolled to the offending step, so information is not lost, but the aria-live status announcement and visible banner are silently suppressed.
  - **Should be:** Set statusEl.hidden = false (and the text) after the showStep call in the error branch, or make showStep not reset statusEl during error handling.

- **[OPEN · MINOR]** past-winners/2/index.html lines 191-210 and fr/past-winners/2/index.html lines 193-212 — https://impactfund.wareham.stream/past-winners/2/ and https://impactfund.wareham.stream/fr/past-winners/2/
  - These two contact forms are missing the honeypot block (<div class="hp-field"> with <input name="website">) that every other contact-form instance has (e.g. index.html lines 320-323). js/main.js tolerates the absence (querySelector returns null) and api/contact.php reads $_POST['website'] ?? '' so submissions still work, but the spam trap is absent on these two instances — bots that parse and post these forms (the sections are hidden but still present in the DOM and submittable) bypass honeypot_check() entirely once PHP goes live.
  - **Should be:** Add the identical hp-field div (label + input name="website" tabindex="-1" autocomplete="off") before the form-footer in both files, matching index.html.

- **[OPEN · MINOR]** js/main.js lines 87-118 (contact submit handler) with novalidate forms at index.html line 304, fr/index.html line 302, about-the-fund, past-winners, past-winners/2 (EN+FR) — https://impactfund.wareham.stream/
  - The contact form has no client-side validation at all: the <form> carries novalidate (suppressing native required/email checks) and the js/main.js handler posts immediately without calling checkValidity()/reportValidity(). Validation relies entirely on the server's 422 response — and on 422 the handler only sets aria-invalid="true" on offending fields (red border via css/style.css line 1012) with no per-field messages, and never removes aria-invalid afterwards: fields stay red after the user corrects them and even after a later successful submit (form.reset() does not clear attributes). On the current nginx image an empty-form submit just shows the generic failure message with no field feedback. Contrast: the apply form implements full client-side validation.
  - **Should be:** Validate before fetch (e.g. if (!form.checkValidity()) { form.reportValidity(); return; } or drop novalidate), and clear aria-invalid on input/successful submit.

- **[RESOLVED · MINOR]** nginx.conf (no /api handling) + Dockerfile ('COPY . /usr/share/nginx/html/') — verified live: GET https://impactfund.wareham.stream/api/apply.php returns 200 application/octet-stream with full PHP source
  - The current nginx image serves the raw PHP source of api/apply.php, api/contact.php, api/_lib.php, api/config.example.php and api/.user.ini as downloadable static files (verified via curl). No secrets are exposed today (api/config.php is git- and docker-ignored), but if a real config.php were ever mounted or copied into the nginx-based deployment its mail addresses and Graph client_secret would be served in plain text. Related known behavior confirmed: POST to /api/*.php returns nginx 405, and both front-ends fail gracefully — generic error message shown, submit button re-enabled (the graceful-failure check passes).
  - **Should be:** Add a location block to nginx.conf denying /api/ (e.g. location /api/ { return 404; }) for as long as the nginx image is deployed; the Dockerfile.php image executes PHP so the block is only needed in nginx.conf.
  - Status: RESOLVED — live /api/*.php now executes PHP (GET returns 405 JSON, POST 422); raw source no longer served (verified 2026-08-26). nginx.conf still lacks a /api deny should a static-only image ever be redeployed.

- **[OPEN · MINOR]** apply-now/index.html line 338 (input id="af-consent" name="consent") — https://impactfund.wareham.stream/apply-now/
  - The final consent checkbox has no value attribute, so a checked box submits the browser default "on". api/apply.php only requires the field to be non-empty (max 50), so submission works, but the archived submission.json and any SharePoint/email record will store consent: "on" — meaningless for audit purposes, and inconsistent with the sibling fund_acknowledgement checkbox which sets value="I acknowledge".
  - **Should be:** Give the consent checkbox an explicit value (e.g. value="I confirm") matching the pattern used by fund_acknowledgement at line 264.

- **[OPEN · MINOR]** api/apply.php lines 44-51 and 62-71 — https://impactfund.wareham.stream/apply-now/
  - Front-end/back-end validation asymmetry on the numeric fields: apply-now inputs enforce type="number" min="0" step="1" for students_in_org/students_reached and inputmode="decimal" for funding_requested/total_cost, but api/apply.php validates them only as free-form strings (max 20/100 chars) with no numeric check — a direct POST (bypassing the UI) can archive and deliver non-numeric values like 'abc' into fields the SharePoint mapping treats as amounts (FundingRequested, TotalCost).
  - **Should be:** Server-side numeric validation, e.g. ctype_digit for the two counts and a currency-style filter (FILTER_VALIDATE_FLOAT after stripping $ and commas) for the two amounts, returning a 422 field error otherwise.

- **[OPEN · MINOR]** js/apply-form.js lines 3-10 (header comment) and PLANS.md line 56-57 — https://impactfund.wareham.stream/js/apply-form.js
  - Stale TODO comment: the file header still says 'submissions are NOT wired yet' and instructs replacing a stub with fetch("/api/apply", …) 'once that endpoint exists', but the submit handler already POSTs to /api/apply.php (line 248) and the endpoint file exists. The endpoint name in the comment and in PLANS.md ('multipart POST to /api/apply') also disagrees with the actual path /api/apply.php — a maintainer following either doc would target the wrong URL.
  - **Should be:** Update or remove the header TODO and correct the endpoint reference in both the comment and PLANS.md to /api/apply.php.

## Partner-schools data quality

- **[RESOLVED · MAJOR]** C:/Users/atp2txw/PycharmProjects/Alumo Website/js/schools-data.js lines 382-387 (row 64; same in _tools/partner-schools-raw.tsv row 64) — displays under the Ontario province card at https://impactfund.wareham.stream/partner-schools/
  - [inherited from Alumo's 2026-08-18 source data, not introduced by the rebuild] Burman University is assigned province "ON", but the school is located in Lacombe, Alberta, so it renders under the Ontario card instead of Alberta on the partner-schools page. (The apply-now dropdown is unaffected by this specific error — it has no province grouping.)
  - **Should be:** province: "AB" — Alberta count becomes 16, Ontario becomes 32 (verified current counts: AB=15, ON=33)
  - Status: RESOLVED — Burman University now province AB — Sept-2026 list (2026-08-26).

- **[RESOLVED · MAJOR]** C:/Users/atp2txw/PycharmProjects/Alumo Website/js/schools-data.js lines 406-411 (row 68; same in _tools/partner-schools-raw.tsv row 68) — displays in both the school and association columns at https://impactfund.wareham.stream/partner-schools/ and as an option in the /apply-now/ institution dropdown
  - [inherited from Alumo's 2026-08-18 source data] Internal editorial to-do rendered publicly: both school and association read "Lambton College (Institution - confirm with Darren)" verbatim. The apply-form dropdown builds option text from the association field, so the note appears there as well.
  - **Should be:** school: "Lambton College", association: "N/A" (or the confirmed student association name) — the "(Institution - confirm with Darren)" note removed
  - Status: RESOLVED — Lambton row now clean (school only, association N/A, no editorial note) — Sept-2026 list (2026-08-26).

- **[OPEN · MAJOR (needs Alumo)]** C:/Users/atp2txw/PycharmProjects/Alumo Website/js/schools-data.js line 620 (row 103, Cégep de Sainte-Foy; same in _tools/partner-schools-raw.tsv row 103) — displays at https://impactfund.wareham.stream/partner-schools/ under Quebec
  - [inherited from Alumo's 2026-08-18 source data] Cégep de Sainte-Foy (association AECSF) lists email permanence@asso-cstj.org — the identical address already used by the Cégep de St-Jérôme row (line 710, association AGES), and the asso-cstj.org domain corresponds to Cégep de Saint-Jérôme (CSTJ). Two different cégeps share one association's inbox; the Sainte-Foy value is almost certainly a copy-paste error (only 2 occurrences of this email in the file, on these two rows).
  - **Should be:** AECSF's own contact address for the Sainte-Foy row (obtain from Alumo); permanence@asso-cstj.org remains only on the Cégep de St-Jérôme row
  - Status: OPEN — permanence@asso-cstj.org is still on both the Sainte-Foy and St-Jérôme rows in the Sept-2026 list (needs Alumo, 2026-08-26).

- **[RESOLVED · MAJOR]** C:/Users/atp2txw/PycharmProjects/Alumo Website/js/schools-data.js lines 382-477 (rows 64-79, 16 consecutive entries; same in _tools/partner-schools-raw.tsv rows 64-79) — displays at https://impactfund.wareham.stream/partner-schools/ (both columns) and in the /apply-now/ institution dropdown
  - [inherited from Alumo's 2026-08-18 source data] Sixteen consecutive rows are unnormalized: school and association are identical strings carrying spreadsheet workflow markers "(SA)" / "(Institution)" that render publicly (e.g. "Confederation College - SUCCI (SA)", "McMaster University MSU (SA)", "Loyalist College (Institution)", "UNB Graduate Students (SA)"). Association names sit in the school column — "Fanshawe Student's Union - FSU (SA)" appears as a school (line 395) while "Fanshawe College (Institution)" is a separate row (line 461). The Burman row additionally misspells the marker as "(Instituion)". Because the association field duplicates the school string, the markers also appear as option text in the apply-form dropdown.
  - **Should be:** Each row split properly: school = institution name only, association = the student association name (or "N/A" for institution-level partners), all "(SA)"/"(Institution)" markers removed, and the "Instituion" misspelling gone
  - Status: RESOLVED — all '(SA)'/'(Institution)' markers gone; school and association columns properly split — Sept-2026 list + cleaning pass (2026-08-26).

- **[OPEN · MAJOR (needs Alumo)]** C:/Users/atp2txw/PycharmProjects/Alumo Website/js/schools-data.js lines 14, 38, 44, 164, 170, 176, 764 (Keyano College, Mount Royal University, Northern Alberta IT, Douglas College, Kwatlen Polytechnic, Camosun College, University of Regina) — displays in the email column at https://impactfund.wareham.stream/partner-schools/
  - [inherited from Alumo's 2026-08-18 source data] Seven rows have email "TBD", and the literal string "TBD" renders publicly as plain text in the contact-email column (js/partner-schools.js lines 86-88 deliberately show non-@ values verbatim without linkifying, so the data gap is exposed to visitors).
  - **Should be:** Real contact emails from Alumo, or render these cells empty/"—" until addresses exist
  - Status: OPEN — 'TBD' replaced by 'Contact Email Coming Soon' on 10 rows in the Sept-2026 list; still a plain-text placeholder rendered in the email column (needs Alumo, 2026-08-26).

- **[RESOLVED · MAJOR]** C:/Users/atp2txw/PycharmProjects/Alumo Website/js/schools-data.js lines 47-50 (row 8; same in _tools/partner-schools-raw.tsv row 8) — displays at https://impactfund.wareham.stream/partner-schools/ under Alberta and in the /apply-now/ dropdown
  - [inherited from Alumo's 2026-08-18 source data] Institution misidentified: "University of Alberta Arts" / "University of Alberta Arts Students Union" reads as a University of Alberta faculty, but the contact email office.sa@auarts.ca belongs to Alberta University of the Arts (AUArts), a separate institution in Calgary.
  - **Should be:** school: "Alberta University of the Arts", association: "Students' Association of Alberta University of the Arts" (SAAUArts)
  - Status: RESOLVED — now 'Alberta University of the Arts' — cleaning pass (2026-08-26).

- **[RESOLVED · MINOR]** js/schools-data.js lines 383-384 (row 64) — displays at https://impactfund.wareham.stream/partner-schools/ and /apply-now/ dropdown
  - [inherited] "Instituion" misspelling shown publicly in both school and association fields: "Burman University (Instituion)".
  - **Should be:** "Institution" — or better, drop the marker entirely per the row-normalization fix
  - Status: RESOLVED — 'Instituion' gone (Burman association now N/A) — Sept-2026 list (2026-08-26).

- **[RESOLVED · MINOR]** js/schools-data.js line 167 (row 28) — displays at https://impactfund.wareham.stream/partner-schools/ under British Columbia
  - [inherited] School misspelled "Kwatlen Polytechnic"; correct name is "Kwantlen Polytechnic University". Side effect: searching the correctly spelled "Kwantlen" in the page's search box returns no results.
  - **Should be:** "Kwantlen Polytechnic University"
  - Status: RESOLVED — now 'Kwantlen Polytechnic University' — Sept-2026 list (2026-08-26).

- **[RESOLVED · MINOR]** js/schools-data.js lines 335-336 (row 56) — displays at https://impactfund.wareham.stream/partner-schools/ under Ontario
  - [inherited] "Wilfred Laurier University" misspelled in both school and association fields; the university is "Wilfrid Laurier University". Searching the correct spelling "Wilfrid" finds nothing.
  - **Should be:** "Wilfrid Laurier University" / "Wilfrid Laurier University Graduate Students' Association (WLUGSA)"
  - Status: RESOLVED — now 'Wilfrid Laurier University' — cleaning pass (2026-08-26).

- **[RESOLVED · MINOR]** js/schools-data.js line 671 (row 112) — displays at https://impactfund.wareham.stream/partner-schools/ under Québec
  - [inherited] School misspelled "Collège Ahunstic"; the association field on the same row spells it correctly ("Collège Ahuntsic (AGECA)").
  - **Should be:** "Collège Ahuntsic"
  - Status: RESOLVED — now 'Collège Ahuntsic' — cleaning pass (2026-08-26).

- **[OPEN · MINOR]** js/schools-data.js lines 5, 41, 185, 293, 299, 323, 329, 527, 533, 581 — displays at https://impactfund.wareham.stream/partner-schools/
  - [inherited] Non-official institution names throughout: "Norquest College" (official: NorQuest College), "Northern Alberta IT" (Northern Alberta Institute of Technology / NAIT), "University of Fraser Valley" (University of the Fraser Valley), "Queens University" x2 (Queen's University), "Waterloo University" x2 (University of Waterloo), "Université de Laval" x2 (Université Laval), "Université du Québec à Gatineau" (no such institution — it is Université du Québec en Outaouais, as the row's own association name says).
  - **Should be:** Official names: NorQuest College; Northern Alberta Institute of Technology (NAIT); University of the Fraser Valley; Queen's University; University of Waterloo; Université Laval; Université du Québec en Outaouais
  - Status: OPEN (narrowed) — all fixed except 'Norquest College' (should be NorQuest), js/schools-data.js line 55 (2026-08-26).

- **[RESOLVED · MINOR]** js/schools-data.js lines 24, 60, 66, 78, 84, 198, 270, 324, 330, 528, 540 — displays at https://impactfund.wareham.stream/partner-schools/ and /apply-now/ dropdown
  - [inherited] Misspellings inside association names shown publicly: "Assoication" (lines 24, 78), "Assocation" (lines 60, 66, 84, 270, 330), "Asscoiation" (line 324, WUSA), "Gradaute" (line 198, UVicGSS); French: "des association d'étudiants" should be "des associations" (line 528, CADEUL) and "des science humaines" should be "des sciences humaines" (line 540, AFESH).
  - **Should be:** "Association", "Graduate", "des associations", "des sciences humaines" respectively
  - Status: RESOLVED — all listed misspellings fixed (Association, Graduate, des associations, des sciences humaines) — Sept-2026 list + cleaning pass (2026-08-26).

- **[OPEN · MINOR]** js/schools-data.js lines 258, 372, 732 — displays at https://impactfund.wareham.stream/partner-schools/
  - [inherited] Association column contains non-association values: Nova Scotia Community College's association is just the school name repeated (line 258); University of Guelph row has "Ridgetown Campus" — a campus, not an association (line 372); École nationale de théâtre's association is the school name repeated with "(ENT)" (line 732).
  - **Should be:** "N/A" (matching the convention used by other institution-level rows) or the actual student-association name
  - Status: OPEN (narrowed) — NSCC association now 'N/A'; 'Ridgetown Campus' (line 542) and 'École nationale de théâtre (ENT)' (line 698) persist (2026-08-26).

- **[OPEN · MINOR]** js/schools-data.js line 234 (row 39) — displays at https://impactfund.wareham.stream/partner-schools/ under Manitoba
  - [inherited] Association listed as "University of Manitoba Graduate Students' Association (UMGSA)" but the organization has renamed: its email domain umgps.org resolves to the "University of Manitoba Graduate & Postdoctoral Society" — the displayed name is stale relative to the row's own email.
  - **Should be:** "University of Manitoba Graduate & Postdoctoral Society (UMGPS)" (verify current branding with the org)

- **[OPEN · MINOR (needs Alumo)]** js/schools-data.js lines 446 and 452 (rows 74-75) — displays at https://impactfund.wareham.stream/partner-schools/ under New Brunswick
  - [inherited] Duplicate email kminer@unb.ca on two different student unions (UNB Fredericton Undergrad Students and UNB Saint John Undergrad Students). The raw TSV shows the same contact (Karen Miner) for both, so it is plausibly intentional, but worth confirming — the Saint John row in particular may deserve its own contact.
  - **Should be:** Confirm with Alumo that one contact covers both campuses; otherwise obtain the Saint John union's own address

## Meta, SEO & accessibility

- **[RESOLVED · MAJOR]** Deployed response headers (all pages, verified on https://impactfund.wareham.stream/ and /fr/) + C:/Users/atp2txw/PycharmProjects/Alumo Website/nginx.conf + robots.txt line 5 + sitemap.xml
  - [decision-needed] VERIFIED indexing posture mismatch: the original alumoimpact.ca sends `X-Robots-Tag: noindex, nofollow` (confirmed via curl on both / and /fr/), while the deployment sends no X-Robots-Tag, has no <meta name="robots"> on any page (grep over repo: zero matches), and nginx.conf adds no such header — so the byte-for-byte clone of the Alumo brand site is fully indexable on impactfund.wareham.stream (duplicate-content/brand-impersonation exposure if unintended). Two details from the earlier report were wrong and are corrected here: robots.txt is NOT a Cloudflare stub — the repo/deployed robots.txt contains `User-agent: * / Allow: / / Disallow: /api/` (still effectively allow-all, so the substance stands); and sitemap.xml is NOT 404 — it exists and serves 200. However both point at the WRONG HOST for this deployment: robots.txt's Sitemap directive and every <loc>/hreflang URL in sitemap.xml reference https://alumoimpact.ca/... instead of the deployed domain, so as served the sitemap is invalid (crawlers ignore cross-host URLs in a sitemap) and actively advertises the original domain from the mirror.
  - **Should be:** Decide the posture. If this is a pre-launch/personal mirror: add `add_header X-Robots-Tag "noindex, nofollow" always;` in nginx.conf (or a Cloudflare Transform Rule) to match the original. If indexing on this domain is intended: keep it indexable but rewrite the Sitemap URL in robots.txt and all URLs in sitemap.xml to https://impactfund.wareham.stream/..., and add self-referencing canonicals (the original had <link rel="canonical">; the rebuild has none).
  - Status: RESOLVED — staging now sends X-Robots-Tag: noindex, nofollow (commit d099d86, verified live 2026-08-26), and self-canonicals to https://alumoimpact.ca/... were added to all 20 pages — the alumoimpact.ca URLs in robots.txt/sitemap.xml are now the intentional production host.

- **[OPEN · MAJOR]** All 20 pages, <head> lines 8-9 — e.g. C:/Users/atp2txw/PycharmProjects/Alumo Website/index.html:8-9 (`<link rel="alternate" hreflang="en" href="/">`, `hreflang="fr" href="/fr/"`) — https://impactfund.wareham.stream/ and every other EN/FR page
  - VERIFIED: hreflang alternate links use relative hrefs on all 20 rebuilt pages (confirmed by grep: every EN page at root and every /fr/ page has exactly two relative-href alternates). Google's hreflang spec requires fully-qualified absolute URLs including protocol and host, so the EN/FR language annotation is non-compliant and unreliable/ignored. There is no x-default in any page <head>. The original site (mirror: _source/pages/home.html lines 183-186) used absolute URLs (https://alumoimpact.ca/...) with four entries per page: en-US, fr-CA, en, fr. The rebuild's pairs DO reference each other correctly (each page self-references and points at its counterpart) — only the URL form (relative) and the dropped en-US/fr-CA locale variants differ. Note: sitemap.xml does declare en/fr/x-default alternates, but with alumoimpact.ca URLs (see the indexing-posture finding), so it does not rescue the annotation. [inherited] The missing x-default in the <head> is inherited — the original had none either.
  - **Should be:** Absolute URLs on all 20 pages, e.g. <link rel="alternate" hreflang="en" href="https://impactfund.wareham.stream/about-the-fund/"> plus the matching fr href="https://impactfund.wareham.stream/fr/about-the-fund/">, and add an x-default pointing at the EN version (matching sitemap.xml's x-default choice). Optionally restore the en-US/fr-CA locale pairs the original carried.

- **[RESOLVED · MINOR]** All 20 pages, <head> — e.g. C:/Users/atp2txw/PycharmProjects/Alumo Website/about-the-fund/index.html — https://impactfund.wareham.stream/about-the-fund/
  - No <link rel="canonical"> on any of the 20 rebuilt pages. The original site had an absolute canonical on every page (e.g. https://alumoimpact.ca/about-the-fund/). Dropping it entirely leaves the pages without a declared canonical URL (matters once/if the site is indexable; also protects against ?query-string duplicates).
  - **Should be:** Add a self-referencing canonical to each page, e.g. <link rel="canonical" href="https://impactfund.wareham.stream/about-the-fund/"> (host choice depends on the indexing decision above).
  - Status: RESOLVED — <link rel="canonical"> added to all 20 pages, pointing at the production host https://alumoimpact.ca/... (verified 2026-08-26).

- **[OPEN · MINOR]** 10 pages: all 9 FR pages except /fr/cookies-policy/ (e.g. C:/Users/atp2txw/PycharmProjects/Alumo Website/fr/partner-schools/index.html, 217 chars) + EN /partner-schools/ (182 chars)
  - Meta descriptions exceed the ~160-character SERP display limit: fr/partner-schools 217, fr/about-the-fund 186, fr/ 185, fr/apply-now 183, partner-schools 182, fr/privacy-policy 177, fr/how-to-apply 173, fr/past-winners and fr/past-winners/2 171, fr/terms-conditions 168. They will be truncated mid-sentence in search results. (Descriptions are a rebuild improvement — the original had none — but the long ones need trimming.)
  - **Should be:** Trim each affected description to roughly 150–160 characters, keeping the key message in the first 150 chars.

- **[OPEN · MINOR]** C:/Users/atp2txw/PycharmProjects/Alumo Website/partner-schools/index.html lines 117 and 184, and fr/partner-schools/index.html lines 124 and 190 — https://impactfund.wareham.stream/partner-schools/ and /fr/partner-schools/
  - [inherited] The two sort dropdowns (<select id="school-sort"> and <select id="school-sort-table">, options A–Z / Z–A) have no accessible name — no <label>, aria-label, or aria-labelledby. Screen-reader users hear an unnamed combobox. Identical markup in the original (_source/pages/partner-schools.html).
  - **Should be:** Add aria-label="Sort schools" (EN) / aria-label="Trier les écoles" (FR) to both selects on both language versions.

- **[OPEN · MINOR]** C:/Users/atp2txw/PycharmProjects/Alumo Website/past-winners/index.html line 94 (also past-winners/2/, fr/past-winners/, fr/past-winners/2/) — https://impactfund.wareham.stream/past-winners/
  - [inherited] Heading level jump h1 → h3: the six winner-card titles ("MindWell student program" etc.) are <h3> with no intervening <h2>, on all four past-winners pages (EN, EN page 2, FR, FR page 2). Same structure in the original (_source/pages/past-winners.html).
  - **Should be:** Promote the winner-card titles to <h2>, or add an <h2> section heading (e.g. visually-hidden "Winning projects") above the card grid.

- **[OPEN · MINOR]** C:/Users/atp2txw/PycharmProjects/Alumo Website/how-to-apply/index.html line 90 (and fr/how-to-apply/index.html line 89) — https://impactfund.wareham.stream/how-to-apply/
  - [inherited] Heading level jump h1 → h3: the three step-card headings ("1. Check eligibility", "2. Complete your application", "3. Review and submit") are <h3> directly after the page <h1>. The original technically had <h2>s before them, but every one carried elementor-hidden-* classes for all breakpoints, so the visible/accessible outline was identical — effectively inherited.
  - **Should be:** Make the three step headings <h2> (they are top-level sections of the page), on both EN and FR versions.

- **[OPEN · MINOR]** C:/Users/atp2txw/PycharmProjects/Alumo Website/past-winners/index.html lines 93–158 (same on past-winners/2/, fr/past-winners/, fr/past-winners/2/) — https://impactfund.wareham.stream/past-winners/
  - [inherited] The six winner project photos (winner-mindwell.jpg, winner-pathway.jpg, winner-studysafe.jpg, winner-campus-care.jpg, winner-tomorrow-fund.jpg, winner-brighstart.jpg) all have alt="" although they are content-bearing images of the winning projects. Inherited from the original, which had alt="" on every image site-wide (the rebuild already fixed the logos and banner alts — these were left empty).
  - **Should be:** Give each photo a short descriptive alt (e.g. "Students at a MindWell workshop"), or make an explicit call that they are decorative alongside the card titles and leave alt="" deliberately.

- **[OPEN · MINOR]** Deployed response headers, all URLs (e.g. https://impactfund.wareham.stream/) — configure in C:/Users/atp2txw/PycharmProjects/Alumo Website/nginx.conf
  - Missing security headers: no X-Content-Type-Options: nosniff and no Strict-Transport-Security on any response. Charset is correctly declared (Content-Type: text/html; charset=utf-8) and the Cache-Control scheme (no-cache HTML/CSS/JS, 7-day images) is deliberate per nginx.conf comments — those are fine.
  - **Should be:** Add `add_header X-Content-Type-Options "nosniff" always;` (and optionally HSTS) — note nginx's add_header inheritance: because the location blocks already use add_header, the new header must be repeated in each location (or set via a Cloudflare Transform Rule) or it will be dropped there.

- **[OPEN · MINOR]** C:/Users/atp2txw/PycharmProjects/Alumo Website/index.html <title> — https://impactfund.wareham.stream/ (and /fr/)
  - [inherited] The homepage <title> is just "Alumo Fund" — it never mentions "Student Impact Fund", the product the whole site is about, making it the weakest title on the site for search and for browser tabs/bookmarks. Matches the original's title exactly.
  - **Should be:** Something like "Student Impact Fund by Alumo" (EN homepage; FR title intentionally stays English per the known-context decision, so apply the same string there if changed).
