# French drafts — October 2026 (for Hafsa to approve)

Written 2026-10-06. Nothing here is on the site yet. Each line is what will be built;
"Was" is the current French where the text replaces something.

## How these were written
- "vous" throughout, like every other French page (client "tu" drafts were all converted).
- Already-approved site wording reused wherever it fits, so Hafsa only has to read what's new:
  "Les soumissions sont présentement fermées.", "du 4 janvier au 1er mars 2027",
  "courriel", "téléverser", "Titre du projet", "Écrivez votre …", "Veuillez …" errors,
  "Types de fichier acceptés : … Taille maximum des fichiers : 10 MB.".
- Typography as on the FR pages: narrow no-break space before ! ? ; and a no-break space
  before : (in the HTML as &#8239; / &nbsp;); months lowercase; "1er"; time "23 h 59".
- Never "postuler" (banned site-wide); "soumettre / soumission" instead. No "candidature"
  on the winners page — winners aren't applying any more.
- Gender-neutral where it costs nothing ("projet gagnant", "signataire").
- Quebec terms: spécimen de chèque, dépôt direct, entente de financement, institution
  financière (covers Desjardins caisses), renseignements (Law 25's term for personal info),
  société (not "corporation"), Agence du revenu du Canada (ARC), Revenu Québec.
- Every draft went through a second, independent French review; its corrections are applied.

## A. October 15 close (shows only after 11:59 pm ET, Oct 15)

| Where | English | French |
|---|---|---|
| Home hero pill | Submissions open in January | Les soumissions ouvrent en janvier *(was "… en septembre")* |
| Home + About closed card, heading | The next submission period: *(unchanged)* | Prochaine période de soumission&nbsp;: *(was "Prochaine date limite de soumission :" — "deadline", wrong above a date range)* |
| Home + About closed card, dates | January 4 to March 1, 2027 | Du 4 janvier au 1er mars 2027 |
| Home + About closed card, status | Submissions are currently closed. | Les soumissions sont présentement fermées. *(approved wording, from the Submit page)* |
| Partners page note | We're updating the list of participating schools for Winter 2027. Please check back on January 4, 2027 for the new list! | Nous mettons à jour la liste des écoles participantes pour l'hiver 2027. Revenez consulter cette page le 4 janvier 2027 pour découvrir la nouvelle liste&#8239;! |
| Submit page note (under the title) | Submissions are closed for now. Please check back on January 4, 2027, when they reopen for Winter 2027! | Les soumissions sont fermées pour le moment. Revenez consulter cette page le 4 janvier 2027, date à laquelle elles rouvriront pour l'hiver 2027&#8239;! |
| Form error if someone submits after the close | *(same as the Submit note)* | *(same as the Submit note)* |
| Partners meta description (search snippet) | Students at Alumo partner schools are eligible to apply for the Student Impact Fund. *(stale "updated before September" sentence removed)* | Les étudiant·es des écoles partenaires d'Alumo peuvent soumettre un projet au Fonds d'impact étudiant. *(approved first sentence; stale second sentence removed)* |
| Preview banner, bottom of every page — seen ONLY with a `?preview-at=` link on staging or the preview copies, never by visitors; the last words are the exit link | Preview: the site as it will look on {date}. Exit preview | Aperçu&nbsp;: le site tel qu'il apparaîtra le {date}. Quitter l'aperçu |
| Preview banner {date} format (Eastern time) | October 16, 2026, 9:00 a.m. ET | 16 octobre 2026 à 9&nbsp;h&nbsp;00 (heure de l'Est) *(day 1 → "1er novembre")* |
| Preview-copy banner, open copy, bottom of every page — seen only on the before./open./closed. preview copies (here: open.alumoimpact.ca), never by visitors; the last words link to the same page on staging | Preview copy: the site as it looks while submissions are open. Go to staging | Copie d'aperçu&nbsp;: le site tel qu'il apparaît pendant la période de soumission. Aller au site d'essai |
| Preview-copy banner, closed copy — seen only on the before./open./closed. preview copies (here: closed.alumoimpact.ca, Hafsa's review links); {date} = the close moment, same format as above; on pages with nothing scheduled (terms, privacy) "on {date}" / "le {date}" is left out | Preview copy: the site as it will look after submissions close on {date}. Go to staging *(e.g. "October 15, 2026, 11:59 p.m. ET")* | Copie d'aperçu&nbsp;: le site tel qu'il apparaîtra après la fermeture des soumissions le {date}. Aller au site d'essai *(e.g. "15 octobre 2026 à 23&nbsp;h&nbsp;59 (heure de l'Est)"; was "… des soumissions ({date}).", which put a parenthesis inside a parenthesis)* |
| Preview-copy banner, before copy — seen only on the before./open./closed. preview copies (here: before.alumoimpact.ca); {date} = the moment submissions open, same format as above; on pages with nothing scheduled "on {date}" / "le {date}" is left out. Approve it from this row, not from before.alumoimpact.ca: until the pages carry the next window's dates, that copy shows the same closed site as closed. (Winter 2027 text) under this Aug 31 banner | Preview copy: the site as it looks before submissions open on {date}. Go to staging *(e.g. "August 31, 2026, 6:30 p.m. ET")* | Copie d'aperçu&nbsp;: le site tel qu'il apparaît avant l'ouverture des soumissions le {date}. Aller au site d'essai *(e.g. "31 août 2026 à 18&nbsp;h&nbsp;30 (heure de l'Est)")* |

Points for Hafsa:
- The Submit note deliberately doesn't repeat "Les soumissions sont présentement fermées.",
  because that exact sentence is already in the closed box further down the same page.
- "Prochaine période de soumission" fixes Alumo's original kicker, which said "deadline".

## B. Winners page (/fr/gagnants-automne-2026/)

FLAG = needs Alumo input, not just a language check.

### Page
| Item | English | French |
|---|---|---|
| Browser tab title | Winner documents – Alumo Fund | Documents des projets gagnants – Fonds Alumo |
| Search description | Send the documents for your winning project to the Student Impact Fund by Alumo. | Envoyez les documents de votre projet gagnant au Fonds d'impact étudiant par Alumo. |
| Small label above title | Winner submission | Projet gagnant&nbsp;: envoi des documents |
| Title | You won. Let's get your funding moving. | Vous avez gagné. Passons maintenant au versement de votre financement. |
| Intro | Send us your signed agreement, finance form and void cheque. | Envoyez-nous votre entente signée, votre formulaire financier et votre spécimen de chèque. |
| Deadline line | Please send your documents by {date}. | Veuillez envoyer vos documents au plus tard le {date}. |
| {date} format (example — deadline not set yet) | October 30, 2026 at 11:59 p.m. ET | 30 octobre 2026 à 23 h 59 (heure de l'Est) *(day 1 → "1er novembre")* |
| Step 1 heading | Step 1: Download and complete the finance form | Étape 1&nbsp;: Téléchargez et remplissez le formulaire financier |
| Step 1 text | We need this information to process your payment. | Nous avons besoin de ces renseignements pour procéder au versement de votre financement. |
| Download button | Download finance form | Télécharger le formulaire financier |

### Tax block — FLAG: Alumo's own text, translated faithfully, for their finance team to review
| Item | English (verbatim from the mock) | French |
|---|---|---|
| Heading | Tax information requirements | Exigences relatives aux renseignements fiscaux |
| Paragraph | Alumo is a Canadian corporation. Under the Canadian Income Tax Act and the Quebec Taxation Act, we must report all business grants, awards and funding over $500 CAD to the Canada Revenue Agency (CRA) and Revenu Québec. To process your funding of up to $5,000, we need your tax details. | Alumo est une société canadienne. En vertu de la <em>Loi de l'impôt sur le revenu</em> (Canada) et de la <em>Loi sur les impôts</em> (Québec), nous devons déclarer à l'Agence du revenu du Canada (ARC) et à Revenu Québec toute subvention, tout prix et tout financement d'entreprise de plus de 500&nbsp;$&nbsp;CA. Pour traiter votre financement, qui peut aller jusqu'à 5&#8239;000&nbsp;$, nous avons besoin de vos renseignements fiscaux. |
| Box, line 1 | **Good to know:** there are no tax implications for these funds. | **Bon à savoir&nbsp;:** ces fonds n'ont aucune incidence fiscale. |
| Box, line 2 | For income tax purposes, funds you receive from Alumo through the Student Impact Fund should be reported on your tax forms under Box 028: Other income / gifts and awards provided by a person other than the employer. | Aux fins de l'impôt sur le revenu, les fonds que vous recevez d'Alumo dans le cadre du Fonds d'impact étudiant devraient être déclarés sur vos formulaires fiscaux à la case 028&nbsp;: Autres revenus / cadeaux et récompenses fournis par une personne autre que l'employeur. |

(Same caveat as the English: line 1 says "no tax implications" and line 2 says to report
it. That's for Alumo's finance team — the French mirrors the English exactly.)

### Form
| Item | English | French |
|---|---|---|
| Form heading | Step 2: Send us your documents | Étape 2&nbsp;: Envoyez-nous vos documents |
| Section | About you and your project | Vous et votre projet |
| School label | School | École *(option: "Établissement", see notes)* |
| School placeholder | Choose your school | Sélectionnez votre école |
| Last option | My school isn't listed | Mon école ne figure pas dans la liste |
| Revealed field | Your school's name | Nom de votre école |
| Name label | Full name | Nom complet |
| Name placeholder | Type your full name | Écrivez votre nom complet |
| Name hint | As it appears on your void cheque. | Tel qu'il figure sur votre spécimen de chèque. |
| Project label | Project title | Titre du projet |
| Project placeholder | Type your project's name | Écrivez le titre de votre projet |
| Email label | Email | Courriel |
| Email placeholder | Type your email | Écrivez votre courriel |
| Email hint | We'll send a confirmation to this address. | Nous vous enverrons une confirmation à cette adresse. |
| Section | Upload your documents | Téléversez vos documents |
| Upload 1 | Signed funding agreement | Entente de financement signée |
| Upload 1 hint | Make sure it's signed by you and your second approver, if you have one. | Assurez-vous qu'elle est signée par vous et par votre deuxième signataire, s'il y a lieu. |
| Upload 2 | Completed finance form | Formulaire financier rempli |
| Upload 2 hint | The form you downloaded above, filled in. | Le formulaire téléchargé ci-dessus, une fois rempli. |
| Upload 3 | Void cheque | Spécimen de chèque |
| Upload 3 hint | Or a direct deposit form from your bank. A clear photo works. | Ou un formulaire de dépôt direct de votre institution financière. Une photo nette fait l'affaire. |
| Cheque note | We only use your void cheque to set up your payment. | Nous utilisons votre spécimen de chèque uniquement pour préparer le versement de votre financement. |
| File types (1–2) | Accepted file types: pdf, docx, jpg, jpeg, png. Max. file size: 10 MB. | Types de fichier acceptés&nbsp;: pdf, docx, jpg, jpeg, png. Taille maximum des fichiers&nbsp;: 10&nbsp;MB. |
| File types (cheque) | Accepted file types: pdf, jpg, jpeg, png. Max. file size: 10 MB. | Types de fichier acceptés&nbsp;: pdf, jpg, jpeg, png. Taille maximum des fichiers&nbsp;: 10&nbsp;MB. |
| Confirm box — FLAG (account-in-my-name clause is our proposal) | I confirm that the information and documents I'm sending are accurate, and that the void cheque or direct deposit form is for a bank account in my name. | Je confirme que les informations et les documents que j'envoie sont exacts et que le spécimen de chèque ou le formulaire de dépôt direct correspond à un compte bancaire à mon nom. |
| Privacy line | Your information and documents will be used by Alumo only to process your funding and will be handled in accordance with our privacy policy. | Vos renseignements et vos documents seront utilisés par Alumo uniquement pour le versement de votre financement et seront traités conformément à notre politique de confidentialité. |
| Hidden spam field | Leave this field empty | Laissez ce champ vide |
| Button | Send my documents | Envoyer mes documents |
| While sending | Sending your documents… {n}% | Envoi de vos documents en cours… {n} % |
| While sending, line 2 | Please keep this page open until it's finished. | Veuillez garder cette page ouverte jusqu'à la fin de l'envoi. |

### Errors
| English | French |
|---|---|
| Choose your school. | Veuillez sélectionner votre école dans la liste. |
| Enter your school's name. | Veuillez indiquer le nom de votre école. |
| Enter your full name. | Veuillez indiquer votre nom complet. |
| Enter your project title. | Veuillez indiquer le titre de votre projet. |
| Enter your email address. | Veuillez indiquer votre adresse courriel. |
| Enter a valid email address. | Veuillez entrer une adresse courriel valide. |
| Upload your signed agreement. | Veuillez téléverser votre entente signée. |
| Upload your completed finance form. | Veuillez téléverser votre formulaire financier rempli. |
| Upload your void cheque. | Veuillez téléverser votre spécimen de chèque. |
| Please tick this box to confirm. | Veuillez cocher cette case pour confirmer. |
| That file type isn't supported. Try PDF, DOCX, JPG or PNG. | Ce type de fichier n'est pas autorisé. Veuillez utiliser un fichier PDF, DOCX, JPG ou PNG. |
| That file type isn't supported. Try PDF, JPG or PNG. | Ce type de fichier n'est pas autorisé. Veuillez utiliser un fichier PDF, JPG ou PNG. |
| HEIC photos aren't supported. Save the photo as a JPG or PNG (or take a screenshot of it) and try again. | Les photos HEIC ne sont pas acceptées. Veuillez enregistrer la photo en format JPG ou PNG (ou en faire une capture d'écran), puis réessayer. |
| That file is over 10 MB. Try a smaller photo or scan. | Ce fichier dépasse la taille maximale de 10 MB. Veuillez utiliser une photo ou un document numérisé moins volumineux. |
| Upload failed — please retry. | Le téléversement a échoué — veuillez réessayer. |
| Please check the highlighted fields. | Veuillez vérifier les champs surlignés. |
| The submission is too large. Each file must be 10 MB or less. | La soumission est trop volumineuse. Chaque fichier doit être de 10 MB ou moins. |
| Too many attempts. Please wait a while and try again. | Trop de tentatives. Veuillez patienter un moment, puis réessayer. |
| Something went wrong and your documents weren't sent. Check your connection and try again. | Une erreur s'est produite et vos documents n'ont pas été envoyés. Veuillez vérifier votre connexion et réessayer. |
| Your documents could not be sent. Please try again later. | Vos documents n'ont pas pu être envoyés. Veuillez réessayer plus tard. |

### Page states
| Item | English | French |
|---|---|---|
| After the deadline, heading | The deadline has passed | La date limite est passée |
| After the deadline, text | The deadline to send your documents was {date}. If you still need to send them, reply to the email Alumo sent you. | La date limite pour envoyer vos documents était le {date}. Si vous devez encore les envoyer, répondez au courriel qu'Alumo vous a envoyé. |
| Not set up yet | This page isn't ready yet. Please try again later. | Cette page n'est pas encore prête. Veuillez réessayer plus tard. |
| JavaScript off | This page needs JavaScript to send your documents. Please turn it on and refresh the page. | Cette page nécessite JavaScript pour envoyer vos documents. Veuillez l'activer, puis actualiser la page. |
| Success heading | Got it, thank you! | Bien reçu, merci&#8239;! |
| Success text | We received your documents. We'll review them and be in touch about your payment. | Nous avons bien reçu vos documents. Nous les examinerons et communiquerons avec vous au sujet de votre paiement. |
| Success, line 2 | A confirmation is on its way to your inbox. | Vous recevrez sous peu un courriel de confirmation dans votre boîte de réception. |

### Confirmation email to the winner
Subject — EN: We received your documents — Student Impact Fund
FR: Nous avons bien reçu vos documents — Fonds d'impact étudiant

EN body:
> Hi {full_name},
>
> Thank you! We received your documents for "{project_title}": your signed funding agreement, your completed finance form and your void cheque.
>
> Our team will review them and be in touch about your payment. If anything needs to change, just reply to this email.
>
> Student Impact Fund by Alumo

FR body:
> Bonjour {full_name},
>
> Merci ! Nous avons bien reçu vos documents pour « {project_title} » : votre entente de financement signée, votre formulaire financier rempli et votre spécimen de chèque.
>
> Notre équipe les examinera et communiquera avec vous au sujet de votre paiement. S'il faut modifier quoi que ce soit, répondez simplement à ce courriel.
>
> Fonds d'impact étudiant par Alumo

(Internal emails to Alumo's team stay English-only.)

### Points for Hafsa (winners page)
1. "École" matches the application form; "Établissement" is the more natural Quebec word
   for universities and cégeps (Alumo's own workbook used it in one place). Her pick.
2. "10 MB" matches every approved French string on the site; the standard French symbol is
   "Mo". If she wants "Mo", it changes everywhere at once.
3. "Spécimen de chèque" (Quebec usage) vs "chèque annulé" (also understood). Kept spécimen.
4. FLAG — the French titles of the finance form and funding agreement must match Alumo's own
   documents; if there's no French finance form, the French page needs a line saying the form
   is in English.
5. FLAG — the tax block (above) and the account-in-my-name clause.

### Added during the build (2026-10-06)
| Where | English | French |
|---|---|---|
| "Not open yet" card (shown only if an opening date is set for the page) | This page isn't open yet | Cette page n'est pas encore ouverte |
| — its text | Please check back soon. If you have questions, reply to the email Alumo sent you. | Revenez bientôt. Si vous avez des questions, répondez au courriel qu'Alumo vous a envoyé. |
| Confirmation email, when the typed name is left out (it looks like a link or is too long — anti-abuse) | Hi, | Bonjour, |
| Confirmation email, when the typed project title is left out (same reason) | …your documents for your project: … | …vos documents pour votre projet&nbsp;: … |

## Parked with the private link (translated, not needed until the key is built)
| English | French |
|---|---|
| Checking your link… | Vérification de votre lien… |
| This link isn't working | Ce lien ne fonctionne pas |
| Please open the link from the email Alumo sent you. If it still doesn't work, reply to that email and we'll help. | Veuillez ouvrir le lien qui se trouve dans le courriel qu'Alumo vous a envoyé. S'il ne fonctionne toujours pas, répondez à ce courriel et nous vous aiderons. |
| We couldn't check your link. Check your connection and refresh the page. | Nous n'avons pas pu valider votre lien. Veuillez vérifier votre connexion et actualiser la page. |
