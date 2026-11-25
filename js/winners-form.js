/* ============================================================
   Winners page — /winners-fall-2026/ and /fr/gagnants-automne-2026/ — and,
   since Nov 2026, the project completion page —
   /project-completion-fall-2026/ and /fr/fin-de-projet-automne-2026/.
   One script, two forms: <form data-handler="winners"> posts to
   /api/winners.php, <form data-handler="completion"> to /api/completion.php.
   Both pages use the same hooks (data-winners-*) and the same markup; what
   differs — copy, fields, upload slots — is picked by KIND below.

   1. Asks the endpoint (?state=1) which state the page is in, then shows
      EITHER the open content OR exactly one state card: closed (with the
      deadline), not open yet, or not ready. A check that fails for any reason
      shows "not ready"; without JavaScript the page's <noscript> card shows
      instead. The schedule lives in the server's config — this file never
      decides it. So does the confirmation email to the winner
      (winners.send_confirmation, off by default; never sent for a completion
      report): the copy that promises it ([data-winners-confirmation]) stays
      hidden unless the check answers confirmation: true.
   2. Runs the school box: a type-ahead over window.ALUMO_WINNERS_SCHOOLS
      (the frozen Fall 2026 list, /js/winners-schools-fall-2026.js), grouped
      by province, then "My school isn't listed", which reveals a required box.
   3. Validates exactly what the endpoint validates, with inline errors, then
      sends the form with XMLHttpRequest (a 15-minute timeout: phone photos
      over mobile data); meanwhile the button is disabled and reads
      "Sending…". The server answers error KEYS (fields: {name: key}); they
      are mapped onto the EN/FR copy below, so the PHP never has to know the
      page's language.
   Strings: FR_DRAFTS.md (Oct and Nov 2026), French pending Hafsa's approval.
   ============================================================ */
(function () {
  "use strict";

  var form = document.querySelector('form[data-handler="winners"], form[data-handler="completion"]');
  if (!form) return;

  var IS_COMPLETION = form.getAttribute("data-handler") === "completion";
  var ENDPOINT = IS_COMPLETION ? "/api/completion.php" : "/api/winners.php";
  var FILE_MAX_BYTES = 10 * 1024 * 1024;  /* 10 MB — must match $maxBytes in both endpoints */
  var TOTAL_MAX_BYTES = 50 * 1024 * 1024; /* all files together — must match $maxTotalBytes in api/completion.php */
  var SCHOOL_OTHER = "__other";           /* must match $SCHOOL_OTHER in both endpoints */

  /* ---- UI strings (EN / FR, keyed off <html lang>, as in apply-form.js) ---- */
  var IS_FR = (document.documentElement.lang || "").toLowerCase().indexOf("fr") === 0;
  var T = IS_FR ? {
    deadline: function (date) { return "Veuillez envoyer vos documents au plus tard le " + date + "."; },
    closedText: function (date) { return "La date limite pour envoyer vos documents était le " + date + "."; },
    closedHelp: "Si vous devez encore les envoyer, répondez au courriel qu'Alumo vous a envoyé.",
    sending: "Envoi en cours…",
    errSchool: "Veuillez sélectionner votre école dans la liste.",
    errSchoolOther: "Veuillez indiquer le nom de votre école.",
    errName: "Veuillez indiquer votre nom complet.",
    errTitle: "Veuillez indiquer le titre de votre projet.",
    errEmail: "Veuillez indiquer votre adresse courriel.",
    errEmailInvalid: "Veuillez entrer une adresse courriel valide.",
    errFunds: "Veuillez indiquer à qui les fonds sont versés.",
    errAgreement: "Veuillez téléverser votre contrat signé.",
    errCheque: "Veuillez téléverser votre spécimen de chèque.",
    errTypeDocs: "Ce type de fichier n'est pas autorisé. Veuillez utiliser un fichier PDF, DOCX, JPG ou PNG.",
    errTypeCheque: "Ce type de fichier n'est pas autorisé. Veuillez utiliser un fichier PDF, JPG ou PNG.",
    errHeic: "Les photos HEIC ne sont pas acceptées. Veuillez enregistrer la photo en format JPG ou PNG (ou en faire une capture d'écran), puis réessayer.",
    errSize: "Ce fichier dépasse la taille maximale de 10 MB. Veuillez utiliser une photo ou un document numérisé moins volumineux.",
    errUploadFailed: "Le téléversement a échoué — veuillez réessayer.",
    errFields: "Veuillez vérifier les champs surlignés.",
    errTooLarge: "La soumission est trop volumineuse. Chaque fichier doit être de 10 MB ou moins.",
    errRateLimit: "Trop de tentatives. Veuillez patienter un moment, puis réessayer.",
    errNetwork: "Une erreur s'est produite et vos documents n'ont pas été envoyés. Veuillez vérifier votre connexion et réessayer.",
    errServer: "Vos documents n'ont pas pu être envoyés. Veuillez réessayer plus tard."
  } : {
    deadline: function (date) { return "Please send your documents by " + date + "."; },
    closedText: function (date) { return "The deadline to send your documents was " + date + "."; },
    closedHelp: "If you still need to send them, reply to the email Alumo sent you.",
    sending: "Sending…",
    errSchool: "Choose your school.",
    errSchoolOther: "Enter your school's name.",
    errName: "Enter your full name.",
    errTitle: "Enter your project title.",
    errEmail: "Enter your email address.",
    errEmailInvalid: "Enter a valid email address.",
    errFunds: "Enter who the funds are sent to.",
    errAgreement: "Upload your signed agreement.",
    errCheque: "Upload your void cheque.",
    errTypeDocs: "That file type isn't supported. Try PDF, DOCX, JPG or PNG.",
    errTypeCheque: "That file type isn't supported. Try PDF, JPG or PNG.",
    errHeic: "HEIC photos aren't supported. Save the photo as a JPG or PNG (or take a screenshot of it) and try again.",
    errSize: "That file is over 10 MB. Try a smaller photo or scan.",
    errUploadFailed: "Upload failed — please retry.",
    errFields: "Please check the highlighted fields.",
    errTooLarge: "The submission is too large. Each file must be 10 MB or less.",
    errRateLimit: "Too many attempts. Please wait a while and try again.",
    errNetwork: "Something went wrong and your documents weren't sent. Check your connection and try again.",
    errServer: "Your documents could not be sent. Please try again later."
  };

  /* The project completion page's own copy: what it says differently from
     the winners page ("your report", not "your documents") and what only it
     can say (several files in one box, the photo-sharing question). */
  if (IS_COMPLETION) {
    var C = IS_FR ? {
      deadline: function (date) { return "Veuillez envoyer votre rapport au plus tard le " + date + "."; },
      closedText: function (date) { return "La date limite pour envoyer votre rapport était le " + date + "."; },
      closedHelp: "Si vous devez encore l'envoyer, répondez au courriel qu'Alumo vous a envoyé.",
      errReport: "Veuillez téléverser votre rapport de fin de projet.",
      errReceipts: "Veuillez téléverser au moins un reçu.",
      errTooManyReceipts: "Vous pouvez téléverser jusqu'à 20 reçus. Si vous en avez davantage, regroupez-les dans un seul fichier PDF.",
      errTooManyPhotos: "Vous pouvez téléverser jusqu'à 10 photos.",
      errConsent: "Veuillez nous indiquer si nous pouvons partager vos photos.",
      errAttest: "Veuillez cocher la case pour confirmer avant d'envoyer votre rapport.",
      errIncomplete: "Certains de vos fichiers ne nous sont pas parvenus. Essayez d'en envoyer moins à la fois, ou envoyez-les par courriel à impact@alumo.ca.",
      errTotal: "Ensemble, vos fichiers dépassent 50 MB. Veuillez en retirer quelques-uns ou utiliser des fichiers moins volumineux.",
      errTooLarge: "La soumission est trop volumineuse. Chaque fichier doit être de 10 MB ou moins, et l'ensemble des fichiers, de 50 MB ou moins.",
      errNetwork: "Une erreur s'est produite et votre rapport n'a pas été envoyé. Veuillez vérifier votre connexion et réessayer.",
      errServer: "Votre rapport n'a pas pu être envoyé. Veuillez réessayer plus tard."
    } : {
      deadline: function (date) { return "Please send your report by " + date + "."; },
      closedText: function (date) { return "The deadline to send your report was " + date + "."; },
      closedHelp: "If you still need to send it, reply to the email Alumo sent you.",
      errReport: "Upload your Project Completion Report.",
      errReceipts: "Upload at least one receipt.",
      errTooManyReceipts: "You can upload up to 20 receipts. If you have more, combine them into one PDF.",
      errTooManyPhotos: "You can upload up to 10 photos.",
      errConsent: "Tell us whether we can share your photos.",
      errAttest: "Tick the box to confirm before sending your report.",
      errIncomplete: "Some of your files didn't reach us. Try sending fewer files at once, or email them to impact@alumo.ca.",
      errTotal: "Together, your files are over 50 MB. Remove a few or use smaller files.",
      errTooLarge: "The submission is too large. Each file must be 10 MB or less, and all files together 50 MB or less.",
      errNetwork: "Something went wrong and your report wasn't sent. Check your connection and try again.",
      errServer: "Your report could not be sent. Please try again later."
    };
    Object.keys(C).forEach(function (key) { T[key] = C[key]; });
  }

  /* Province group labels, same codes/order/names as apply-form.js. */
  var PROVINCES = IS_FR ? [
    ["AB", "Alberta"], ["BC", "Colombie-Britannique"], ["MB", "Manitoba"],
    ["NB", "Nouveau-Brunswick"], ["NS", "Nouvelle-Écosse"], ["ON", "Ontario"],
    ["QC", "Québec"], ["SK", "Saskatchewan"]
  ] : [
    ["AB", "Alberta"], ["BC", "British Columbia"], ["MB", "Manitoba"],
    ["NB", "New Brunswick"], ["NS", "Nova Scotia"], ["ON", "Ontario"],
    ["QC", "Quebec"], ["SK", "Saskatchewan"]
  ];

  /* The upload slots, in page order: name (the server's key — the input is
     name or name[]), accepted extensions, and how many files (min..max).
     Must match winners_upload_slots() / completion_upload_slots() in
     api/_lib.php and the inputs' accept= lists. */
  var DOC_EXTS = ["pdf", "docx", "jpg", "jpeg", "png"];
  var SLOTS = IS_COMPLETION ? [
    { name: "file_report", exts: DOC_EXTS, min: 1, max: 1, missing: T.errReport, type: T.errTypeDocs },
    { name: "file_receipts", exts: DOC_EXTS, min: 1, max: 20, missing: T.errReceipts, type: T.errTypeDocs, tooMany: T.errTooManyReceipts },
    { name: "file_photos", exts: DOC_EXTS, min: 0, max: 10, missing: "", type: T.errTypeDocs, tooMany: T.errTooManyPhotos }
  ] : [
    { name: "file_agreement", exts: DOC_EXTS, min: 1, max: 1, missing: T.errAgreement, type: T.errTypeDocs },
    { name: "file_void_cheque", exts: ["pdf", "jpg", "jpeg", "png"], min: 1, max: 1, missing: T.errCheque, type: T.errTypeCheque }
  ];
  /* Some Android pickers hand over a file with no extension; judge it by its
     type when the browser knows one. When it doesn't ("" on desktop,
     application/octet-stream from some Android and cloud pickers), only the
     content can tell, so the file goes up and the server sniffs it — exactly
     as it would judge it (fileProblem). */
  var TYPE_EXT = { "application/pdf": "pdf", "image/jpeg": "jpg", "image/png": "png" };
  var TYPE_UNKNOWN = { "": true, "application/octet-stream": true };

  /* Server keys -> copy. "required" is per field; "file_type" per slot. */
  var REQUIRED = {
    school: T.errSchool,
    school_other: T.errSchoolOther,
    full_name: T.errName,
    project_title: T.errTitle,
    email: T.errEmail,
    funds_sent_to: T.errFunds,       /* winners page only */
    photo_consent: T.errConsent,     /* completion page only */
    attestation: T.errAttest         /* completion page only */
  };
  var SLOT_BY_NAME = {};
  SLOTS.forEach(function (slot) {
    SLOT_BY_NAME[slot.name] = slot;
    REQUIRED[slot.name] = slot.missing;
  });
  var FIELD_ERRORS = {
    invalid_email: T.errEmailInvalid,
    file_heic: T.errHeic,
    file_size: T.errSize,
    upload_failed: T.errUploadFailed
  };
  /* Page order, for "focus the first problem". */
  var FIELD_ORDER = IS_COMPLETION
    ? ["school", "school_other", "full_name", "project_title", "email",
      "file_report", "file_receipts", "file_photos", "photo_consent", "attestation"]
    : ["school", "school_other", "full_name", "project_title", "email", "funds_sent_to",
      "file_agreement", "file_void_cheque"];

  var statesSection = document.querySelector("[data-winners-states]");
  var openContent = document.querySelector("[data-winners-open]");
  /* Every intro paragraph (the completion page has two). */
  var intros = Array.prototype.slice.call(document.querySelectorAll("[data-winners-intro]"));
  var deadlineLine = document.querySelector("[data-winners-deadline]");
  var closedText = document.querySelector("[data-winners-closed-text]");
  var success = document.querySelector("[data-winners-success]");
  var cards = {};
  Array.prototype.forEach.call(document.querySelectorAll("[data-winners-state]"), function (card) {
    cards[card.getAttribute("data-winners-state")] = card;
  });

  /* The school box (client, Nov 2026: a plain dropdown "didn't look great"
     when typed in): a text box people type in — #wf-school, which has no
     name and is never sent — over a list of matches, like the application
     form's; and the hidden input the form really sends (name="school"): a
     listed school's exact name, SCHOOL_OTHER, or "" while nothing is chosen. */
  var schoolInput = form.querySelector("#wf-school");
  var schoolValue = form.querySelector('input[type="hidden"][name="school"]');
  var schoolPanel = document.getElementById("wf-school-listbox");
  var otherWrap = form.querySelector("#wf-school-other-wrap");
  var otherInput = form.querySelector('input[name="school_other"]');
  var submitBtn = form.querySelector('[type="submit"]');
  var honeypot = form.querySelector(".hp-field input");
  var statusEl = form.querySelector(".form-status");
  /* The button's own words, swapped for T.sending while the form goes. */
  var submitLabel = submitBtn.querySelector("span") || submitBtn;
  var submitText = submitLabel.textContent;

  /* The control for a server key: name="x", or name="x[]" for a box that
     takes several files. For a radio pair, the first radio. For the school,
     the box people see, not the hidden input that carries its value. */
  function field(name) {
    if (name === "school") return schoolInput;
    return form.querySelector('[name="' + name + '"]') || form.querySelector('[name="' + name + '[]"]');
  }
  /* The server key for a control ("file_receipts[]" -> "file_receipts"). */
  function keyOf(el) { return String(el.name || "").replace(/\[\]$/, ""); }

  /* "Can we share your photos?" — completion page only (null elsewhere). */
  var consentRadios = Array.prototype.slice.call(form.querySelectorAll('input[type="radio"][name="photo_consent"]'));
  var photosInput = IS_COMPLETION ? field("file_photos") : null;

  function parseJson(text) {
    try {
      var j = JSON.parse(text);
      return j && typeof j === "object" ? j : null;
    } catch (e) {
      return null;
    }
  }

  /* ---- Deadline formatting ----
     The server sends epoch seconds (or null). Format only a real positive
     number — new Date(null) is 1970 and an Invalid Date still reads as true —
     and in Toronto time whatever the reader's own zone. Any failure (an old
     browser without formatToParts or the zone data) returns null and the
     page simply leaves the date out.
       EN  October 30, 2026 at 11:59 p.m. ET
       FR  30 octobre 2026 à 23 h 59 (heure de l'Est)   — day 1 is "1er";
           no-break spaces around the "h", as the French pages write
           23&nbsp;h&nbsp;59 */
  function formatDeadline(ts) {
    if (typeof ts !== "number" || !(ts > 0) || !isFinite(ts)) return null;
    try {
      var date = new Date(ts * 1000);
      if (isNaN(date.getTime())) return null;
      var parts = {};
      new Intl.DateTimeFormat(IS_FR ? "fr-CA" : "en-CA", {
        timeZone: "America/Toronto",
        year: "numeric", month: "long", day: "numeric",
        hour: "numeric", minute: "2-digit", hourCycle: "h23", hour12: false
      }).formatToParts(date).forEach(function (p) { parts[p.type] = p.value; });
      var hour = parseInt(parts.hour, 10);
      if (hour === 24) hour = 0; /* some engines say 24:00 for midnight under hour12:false */
      var minute = String(parts.minute || "");
      if (minute.length === 1) minute = "0" + minute;
      var day = parseInt(parts.day, 10);
      if (isNaN(hour) || isNaN(day) || !parts.month || !parts.year || !minute) return null;
      if (IS_FR) {
        return (day === 1 ? "1er" : String(day)) + " " + parts.month + " " + parts.year +
          " à " + hour + " h " + minute + " (heure de l'Est)";
      }
      return parts.month + " " + day + ", " + parts.year + " at " +
        (hour % 12 === 0 ? 12 : hour % 12) + ":" + minute + " " +
        (hour < 12 ? "a.m." : "p.m.") + " ET";
    } catch (e) {
      return null;
    }
  }

  /* ---- Page state ----
     Shows the open content, or exactly one card. The intro ("Send us your
     signed agreement…") and the deadline line belong to the open state only:
     above a closed card they would invite what the card refuses. Returns the
     card shown (or null when open) so the caller can move focus to it. */
  function showState(state, closesTs) {
    var date = formatDeadline(closesTs);
    if (state === "open") {
      statesSection.hidden = true;
      openContent.hidden = false;
      intros.forEach(function (el) { el.hidden = false; });
      deadlineLine.hidden = !date;
      deadlineLine.textContent = date ? T.deadline(date) : "";
      return null;
    }
    if (!cards[state]) state = "unconfigured";
    openContent.hidden = true;
    intros.forEach(function (el) { el.hidden = true; });
    deadlineLine.hidden = true;
    Object.keys(cards).forEach(function (key) { cards[key].hidden = key !== state; });
    if (state === "closed") {
      closedText.textContent = date ? T.closedText(date) + " " + T.closedHelp : T.closedHelp;
    }
    statesSection.hidden = false;
    return cards[state];
  }

  /* Never leave a visitor on a blank page: anything unexpected while
     rendering a state falls back to the "not ready" card. */
  function renderState(state, closesTs) {
    try {
      return showState(state, closesTs);
    } catch (e) {
      openContent.hidden = true;
      intros.forEach(function (el) { el.hidden = true; });
      if (deadlineLine) deadlineLine.hidden = true;
      statesSection.hidden = false;
      if (cards.unconfigured) cards.unconfigured.hidden = false;
      return cards.unconfigured || null;
    }
  }

  /* The email hint and the success panel's "on its way" line promise a
     confirmation email, which the server sends only when
     winners.send_confirmation is on (off by default). Both are hidden in the
     markup; this shows them — and ties the hint to the email field, since
     aria-describedby would read it even while hidden. */
  function showConfirmationCopy() {
    Array.prototype.forEach.call(document.querySelectorAll("[data-winners-confirmation]"), function (el) {
      el.hidden = false;
    });
    var hint = document.getElementById("wf-email-hint");
    if (hint) describedBy(field("email"), hint.id, true);
  }

  /* The payment note's date ("…the week of January 11, 2027"). The page
     carries one as written; when the server's config sets
     winners.payment_week ('YYYY-MM-DD'), that date is written over it here,
     in the page's language: "February 1, 2027" / "1er février 2027". A
     calendar date, so it is formatted in UTC and never shifts by a time
     zone. Anything unexpected leaves the page's own wording. */
  function showPaymentWeek(day) {
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(day || ""));
    if (!m) return;
    var date = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3]));
    if (isNaN(date.getTime()) || date.getUTCDate() !== +m[3]) return;
    var parts = {};
    new Intl.DateTimeFormat(IS_FR ? "fr-CA" : "en-CA", {
      timeZone: "UTC", year: "numeric", month: "long", day: "numeric"
    }).formatToParts(date).forEach(function (p) { parts[p.type] = p.value; });
    var dayNumber = parseInt(parts.day, 10);
    if (isNaN(dayNumber) || !parts.month || !parts.year) return;
    var text = IS_FR
      ? (dayNumber === 1 ? "1er" : String(dayNumber)) + " " + parts.month + " " + parts.year
      : parts.month + " " + dayNumber + ", " + parts.year;
    Array.prototype.forEach.call(document.querySelectorAll("[data-winners-payment-week]"), function (el) {
      el.textContent = text;
    });
  }

  function checkState() {
    var settled = false;
    function settle(state, ts, confirmation, paymentWeek) {
      if (settled) return;
      settled = true;
      if (confirmation) {
        try { showConfirmationCopy(); } catch (e) { /* the copy simply stays hidden */ }
      }
      try { showPaymentWeek(paymentWeek); } catch (e) { /* the page's own date stays */ }
      renderState(state, ts);
    }
    try {
      var xhr = new XMLHttpRequest();
      xhr.open("GET", ENDPOINT + "?state=1", true);
      xhr.timeout = 15000;
      xhr.setRequestHeader("Accept", "application/json");
      xhr.onload = function () {
        var j = parseJson(xhr.responseText);
        if (xhr.status === 200 && j && j.ok === true &&
            (j.state === "open" || j.state === "closed" || j.state === "not-open")) {
          settle(j.state, j.closes_at_ts, j.confirmation === true, j.payment_week);
        } else {
          settle("unconfigured", null); /* 503, a stray 200, or no PHP at all */
        }
      };
      xhr.onerror = xhr.ontimeout = xhr.onabort = function () { settle("unconfigured", null); };
      xhr.send();
    } catch (e) {
      settle("unconfigured", null);
    }
  }

  function focusCard(card) {
    if (!card) return;
    if (card.scrollIntoView) card.scrollIntoView({ behavior: "smooth", block: "center" });
    try { card.focus({ preventScroll: true }); } catch (e) { card.focus(); }
  }

  /* ---- School box (frozen Fall 2026 list) ----
     The same type-ahead as the application form's institution box
     (js/apply-form.js), with its styles (.combo-* in apply-now.css): typing
     narrows a list grouped by province, the arrow keys and Enter or a click
     choose. Two differences: the last line is always "My school isn't
     listed" (the box's data-other-label), whatever was typed, and it reveals
     a required name box; and what is sent is the hidden input, so the server
     still gets a listed school's exact name or SCHOOL_OTHER, never a
     half-typed one. */
  var schoolGroups = [];   /* [{ name: province, options: [school…] }] */
  var schoolByKey = {};    /* folded name -> the list's own spelling */
  var OTHER_LABEL = schoolInput.getAttribute("data-other-label") || "";
  var schoolOptions = [];  /* the option elements now in the panel */
  var schoolActive = -1;

  /* Case and accents don't count when matching ("universite laval"). */
  function fold(text) {
    var s = String(text || "").toLowerCase().trim();
    try { s = s.normalize("NFD").replace(/[̀-ͯ]/g, ""); } catch (e) { /* old browser: case only */ }
    return s;
  }

  function loadSchools() {
    var rows = window.ALUMO_WINNERS_SCHOOLS;
    if (!Array.isArray(rows)) return; /* list missing: "not listed" still works */
    PROVINCES.forEach(function (province) {
      var names = [];
      var seen = {};
      rows.forEach(function (row) {
        var name = String((row && row.school) || "").trim();
        if (!row || row.province !== province[0] || !name || seen[name.toLowerCase()]) return;
        seen[name.toLowerCase()] = true;
        names.push(name);
        schoolByKey[fold(name)] = name;
      });
      if (!names.length) return;
      names.sort(function (a, b) { return a.localeCompare(b); });
      schoolGroups.push({ name: province[1], options: names });
    });
  }

  /* What the text in the box stands for: a listed school's exact name,
     SCHOOL_OTHER for the "not listed" line, or "" (nothing chosen yet). */
  function schoolFor(text) {
    var key = fold(text);
    if (!key) return "";
    if (OTHER_LABEL && key === fold(OTHER_LABEL)) return SCHOOL_OTHER;
    return Object.prototype.hasOwnProperty.call(schoolByKey, key) ? schoolByKey[key] : "";
  }

  /* "My school isn't listed" reveals and requires the name box; any other
     choice hides and EMPTIES it, so a retracted name is never submitted. */
  function syncSchoolOther() {
    var isOther = schoolValue.value === SCHOOL_OTHER;
    otherWrap.hidden = !isOther;
    otherInput.required = isOther;
    if (isOther) {
      otherInput.setAttribute("aria-required", "true");
    } else {
      otherInput.removeAttribute("aria-required");
      otherInput.value = "";
      clearError(otherInput);
    }
  }

  /* Keep the hidden input — what is sent — in step with the box. */
  function syncSchool() {
    schoolValue.value = schoolFor(schoolInput.value);
    syncSchoolOther();
  }

  function setSchoolActive(index) {
    if (schoolActive >= 0 && schoolOptions[schoolActive]) {
      schoolOptions[schoolActive].classList.remove("is-active");
      schoolOptions[schoolActive].setAttribute("aria-selected", "false");
    }
    schoolActive = index;
    if (index >= 0 && schoolOptions[index]) {
      var el = schoolOptions[index];
      el.classList.add("is-active");
      el.setAttribute("aria-selected", "true");
      schoolInput.setAttribute("aria-activedescendant", el.id);
      if (el.scrollIntoView) el.scrollIntoView({ block: "nearest" });
    } else {
      schoolInput.removeAttribute("aria-activedescendant");
    }
  }

  function closeSchools() {
    schoolPanel.hidden = true;
    schoolInput.setAttribute("aria-expanded", "false");
    setSchoolActive(-1);
  }

  function chooseSchool(label) {
    schoolInput.value = label;
    clearError(schoolInput);
    closeSchools();
    syncSchool();
    /* "Not listed" asks for the name next: go there. */
    if (schoolValue.value === SCHOOL_OTHER) {
      try { otherInput.focus(); } catch (e) { /* stays on the box */ }
    }
  }

  /* Rebuild the panel for what is typed; an empty box, or one that already
     holds a choice, shows the whole list. */
  function openSchools() {
    var typed = schoolInput.value.trim();
    var query = schoolFor(typed) !== "" ? "" : fold(typed);
    schoolPanel.innerHTML = "";
    schoolOptions = [];
    schoolActive = -1;
    var counter = 0;
    function addOption(label, extraClass) {
      var option = document.createElement("div");
      option.className = "combo-option" + (extraClass ? " " + extraClass : "");
      option.setAttribute("role", "option");
      option.setAttribute("aria-selected", "false");
      option.id = "wf-school-option-" + (counter++);
      option.textContent = label;
      option.addEventListener("click", function () { chooseSchool(label); });
      schoolPanel.appendChild(option);
      schoolOptions.push(option);
    }
    schoolGroups.forEach(function (group) {
      var matched = group.options.filter(function (name) {
        return !query || fold(name).indexOf(query) !== -1;
      });
      if (!matched.length) return;
      var heading = document.createElement("div");
      heading.className = "combo-group-label";
      heading.setAttribute("role", "presentation");
      heading.textContent = group.name;
      schoolPanel.appendChild(heading);
      matched.forEach(function (name) { addOption(name, ""); });
    });
    /* Always the last line, whatever was typed: the way out when nothing
       matches. */
    if (OTHER_LABEL) addOption(OTHER_LABEL, "combo-option-other");
    schoolPanel.hidden = schoolOptions.length === 0;
    schoolInput.setAttribute("aria-expanded", schoolPanel.hidden ? "false" : "true");
  }

  function initSchools() {
    loadSchools();
    schoolInput.addEventListener("focus", openSchools);
    schoolInput.addEventListener("input", function () {
      syncSchool();
      openSchools();
    });
    /* A press inside the panel (a line, or its scrollbar) must not take the
       focus from the box — the blur below would close the list under it. */
    schoolPanel.addEventListener("mousedown", function (e) { e.preventDefault(); });
    schoolInput.addEventListener("blur", function () {
      /* Typed in full rather than picked: take the list's own spelling. */
      var value = schoolFor(schoolInput.value);
      if (value === SCHOOL_OTHER) schoolInput.value = OTHER_LABEL;
      else if (value) schoolInput.value = value;
      closeSchools();
    });
    schoolInput.addEventListener("keydown", function (e) {
      if (e.key === "Escape") {
        closeSchools();
        return;
      }
      if (e.key === "ArrowDown" || e.key === "ArrowUp") {
        e.preventDefault();
        if (schoolPanel.hidden) openSchools();
        if (!schoolOptions.length) return;
        setSchoolActive(e.key === "ArrowDown"
          ? (schoolActive + 1) % schoolOptions.length
          : (schoolActive <= 0 ? schoolOptions.length - 1 : schoolActive - 1));
        return;
      }
      if (e.key === "Enter" && !schoolPanel.hidden && schoolActive >= 0) {
        e.preventDefault(); /* choose, don't send the form */
        chooseSchool(schoolOptions[schoolActive].textContent);
      }
    });
    syncSchool(); /* also covers a browser restoring the box on reload */
  }

  /* ---- Inline field errors (same markup as apply-form.js, plus the note is
     tied to its field with aria-describedby so it is read on focus) ---- */
  function fieldWrap(el) {
    return el.closest(".form-field") || el.parentElement;
  }
  function describedBy(el, id, add) {
    var ids = (el.getAttribute("aria-describedby") || "").split(/\s+/).filter(function (x) {
      return x && x !== id;
    });
    if (add) ids.push(id);
    if (ids.length) el.setAttribute("aria-describedby", ids.join(" "));
    else el.removeAttribute("aria-describedby");
  }
  /* announce: role="alert", for a problem the winner didn't ask about and
     where focus doesn't move (a file refused as soon as it is picked). Submit
     errors don't need it: focus lands on the first one and its note is read
     through aria-describedby. */
  function setError(el, message, announce) {
    var wrap = fieldWrap(el);
    wrap.classList.add("has-error");
    el.setAttribute("aria-invalid", "true");
    var note = wrap.querySelector(".field-error");
    if (!note) {
      note = document.createElement("p");
      note.className = "field-error";
      note.id = el.id + "-error";
      wrap.appendChild(note);
      describedBy(el, note.id, true);
    }
    if (announce) note.setAttribute("role", "alert");
    else note.removeAttribute("role");
    note.textContent = message;
  }
  function clearError(el) {
    var wrap = fieldWrap(el);
    if (!wrap) return;
    wrap.classList.remove("has-error");
    el.removeAttribute("aria-invalid");
    var note = wrap.querySelector(".field-error");
    if (note) {
      describedBy(el, note.id, false);
      note.parentNode.removeChild(note);
    }
  }

  /* ---- File rules — same order as winners_check_upload() on the server:
     HEIC, then type, then size. Returns a message, or "" when fine. ---- */
  function oneFileProblem(file, slot) {
    var name = String(file.name || "").toLowerCase();
    var type = String(file.type || "").toLowerCase();
    var dot = name.lastIndexOf(".");
    var ext = dot >= 0 ? name.slice(dot + 1) : "";
    if (ext === "heic" || ext === "heif" || /^image\/hei[cf]/.test(type)) return T.errHeic;
    var unknown = !ext && TYPE_UNKNOWN[type] === true;
    if (!ext) ext = TYPE_EXT[type] || "";
    if (!unknown && slot.exts.indexOf(ext) === -1) return slot.type;
    if (file.size > FILE_MAX_BYTES) return T.errSize;
    if (file.size === 0) return T.errUploadFailed;
    return "";
  }
  /* Everything picked in one box, in the server's order (completion_check_slot):
     too few, too many, then each file. In a box holding several files the
     message names the file it is about. */
  function fileProblem(input) {
    var slot = SLOT_BY_NAME[keyOf(input)];
    var files = input.files ? Array.prototype.slice.call(input.files) : [];
    if (!files.length) {
      /* A file refused on pick was cleared from the input: keep saying why
         ("HEIC photos aren't supported…") rather than "Upload your…", or the
         winner would just pick the same photo again. Only where a file is
         required, though: an optional box that is empty is fine however it
         came to be empty — a refused photo must never stop someone sending
         their report without photos. */
      return slot.min > 0 ? (input.getAttribute("data-rejected") || slot.missing) : "";
    }
    if (files.length > slot.max) return slot.tooMany || slot.type;
    for (var i = 0; i < files.length; i++) {
      var problem = oneFileProblem(files[i], slot);
      if (problem) return files.length > 1 ? String(files[i].name || "") + " — " + problem : problem;
    }
    return "";
  }
  function filesBytes(input) {
    var total = 0;
    if (input && input.files) {
      Array.prototype.forEach.call(input.files, function (file) { total += file.size || 0; });
    }
    return total;
  }

  /* A rejected file is cleared straight away (as on the application form),
     so it can never be sent by mistake; the input remembers why
     (data-rejected) and the reason is announced. */
  function onFileChosen(input) {
    clearError(input);
    input.removeAttribute("data-rejected");
    if (!(input.files && input.files.length)) return;
    var problem = fileProblem(input);
    if (problem) {
      input.value = "";
      input.setAttribute("data-rejected", problem);
      setError(input, problem, true);
    }
  }

  /* ---- Whole-form validation, mirroring the endpoint (api/winners.php or
     api/completion.php). Returns the invalid fields in page order. ---- */
  var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  function validateAll() {
    var invalid = [];
    function check(el, message) {
      clearError(el);
      if (message) {
        setError(el, message);
        invalid.push(el);
      }
    }
    syncSchool();   /* the hidden value, from what the box holds right now */
    check(schoolInput, schoolValue.value === "" ? T.errSchool : "");
    if (schoolValue.value === SCHOOL_OTHER) {
      check(otherInput, otherInput.value.trim() === "" ? T.errSchoolOther : "");
    }
    /* In page order, so the first problem found is the first on screen. */
    function checkText(name) {
      var el = field(name);
      check(el, el.value.trim() === "" ? REQUIRED[name] : "");
    }
    checkText("full_name");
    checkText("project_title");
    var email = field("email");
    var value = email.value.trim();
    check(email, value === "" ? T.errEmail
      : (!EMAIL_RE.test(value) || (email.validity && email.validity.typeMismatch)) ? T.errEmailInvalid : "");
    if (!IS_COMPLETION) checkText("funds_sent_to");

    var total = 0;
    var lastWithFiles = null;
    SLOTS.forEach(function (slot) {
      var input = field(slot.name);
      check(input, fileProblem(input));
      total += filesBytes(input);
      if (input.files && input.files.length) lastWithFiles = input;
    });
    /* All files together (completion page: up to 31 of them). Said on the
       last box that holds any, and only once each box is fine on its own. */
    if (IS_COMPLETION && !invalid.length && total > TOTAL_MAX_BYTES && lastWithFiles) {
      check(lastWithFiles, T.errTotal);
    }
    /* The sharing question is about the photos: it needs an answer only when
       there are some. */
    if (consentRadios.length) {
      var answered = consentRadios.some(function (radio) { return radio.checked; });
      var hasPhotos = !!(photosInput && photosInput.files && photosInput.files.length);
      check(consentRadios[0], hasPhotos && !answered ? T.errConsent : "");
    }
    /* The attestation tick box (completion page): must be ticked. */
    var attest = field("attestation");
    if (attest) check(attest, attest.checked ? "" : T.errAttest);
    return invalid;
  }

  function focusField(el) {
    var wrap = fieldWrap(el);
    if (wrap && wrap.scrollIntoView) wrap.scrollIntoView({ behavior: "smooth", block: "center" });
    try { el.focus({ preventScroll: true }); } catch (e) { el.focus(); }
  }

  /* ---- Status line ---- */
  function showStatus(message, isError) {
    statusEl.textContent = message;
    statusEl.classList.toggle("is-error", !!isError);
    statusEl.hidden = false;
  }

  /* ---- Submit ---- */
  var sending = false;

  function finishSending() {
    sending = false;
    submitBtn.disabled = false;
    submitLabel.textContent = submitText;
    form.removeAttribute("aria-busy");
  }

  function showSuccess() {
    sending = false;
    form.removeAttribute("aria-busy");
    form.reset();
    syncSchool();   /* reset() empties the box but not a hidden input */
    form.hidden = true;
    success.hidden = false;
    if (success.scrollIntoView) success.scrollIntoView({ behavior: "smooth", block: "center" });
    try { success.focus({ preventScroll: true }); } catch (e) { success.focus(); }
  }

  function handleResponse(status, j) {
    if (status === 200 && j && j.ok === true) {
      showSuccess();
      return;
    }
    /* The deadline passed (or the page closed) while the form was open: show
       the matching card rather than an error the winner can do nothing with. */
    if (status === 403 && j && (j.state === "closed" || j.state === "not-open")) {
      finishSending();
      focusCard(renderState(j.state, j.closes_at_ts));
      return;
    }
    var message = T.errServer;
    if (status === 422 && j && j.fields && typeof j.fields === "object") {
      var first = null;
      FIELD_ORDER.forEach(function (name) {
        if (!Object.prototype.hasOwnProperty.call(j.fields, name)) return;
        var el = field(name);
        if (!el) return;
        var key = String(j.fields[name]);
        var text = key === "required" ? (REQUIRED[name] || T.errFields)
          : key === "file_type" ? (SLOT_BY_NAME[name] ? SLOT_BY_NAME[name].type : T.errTypeDocs)
          : key === "too_many" ? ((SLOT_BY_NAME[name] && SLOT_BY_NAME[name].tooMany) || T.errFields)
          : Object.prototype.hasOwnProperty.call(FIELD_ERRORS, key) ? FIELD_ERRORS[key]
          : T.errFields;
        setError(el, text);
        if (!first) first = el;
      });
      message = T.errFields;
      if (first) focusField(first);
    } else if (j && j.error === "incomplete" && T.errIncomplete) {
      message = T.errIncomplete;   /* the server got fewer files than were sent */
    } else if (status === 413 || (j && j.error === "too_large")) {
      message = T.errTooLarge;
    } else if (status === 429) {
      message = T.errRateLimit;
    } else if (status === 0) {
      message = T.errNetwork;
    }
    showStatus(message, true);
    finishSending();
  }

  function send() {
    sending = true;
    submitBtn.disabled = true;
    submitLabel.textContent = T.sending;
    form.setAttribute("aria-busy", "true");
    statusEl.hidden = true;   /* an earlier attempt's error no longer applies */

    var xhr = new XMLHttpRequest();
    xhr.open("POST", ENDPOINT, true);
    xhr.timeout = 15 * 60 * 1000; /* 10 MB files (up to 50 MB of them) over a slow connection */
    xhr.setRequestHeader("Accept", "application/json");
    xhr.onload = function () { handleResponse(xhr.status, parseJson(xhr.responseText)); };
    xhr.onerror = xhr.ontimeout = xhr.onabort = function () {
      showStatus(T.errNetwork, true);
      finishSending();
    };
    var data = new FormData(form);
    if (IS_COMPLETION) {
      /* How many files are on their way: a server that drops some (PHP does,
         silently, past its max_file_uploads) can then say so instead of
         filing a report with receipts missing — api/completion.php. */
      var count = 0;
      SLOTS.forEach(function (slot) {
        var input = field(slot.name);
        count += input && input.files ? input.files.length : 0;
      });
      data.append("file_count", String(count));
      /* The statement beside the tick box, as this page showed it: the
         wording is Alumo's to change, so what was agreed to goes on file
         with the report. */
      var statement = form.querySelector("[data-winners-attest-text]");
      if (statement) {
        data.append("attestation_text", statement.textContent.replace(/\*\s*$/, "").replace(/\s+/g, " ").trim());
      }
    }
    xhr.send(data);
  }

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    if (sending) return;                         /* no double submit */
    if (honeypot && honeypot.value) return;      /* spam trap — silently drop */
    var invalid = validateAll();
    if (invalid.length) {
      statusEl.hidden = true;
      focusField(invalid[0]);
      return;
    }
    send();
  });

  /* Correcting a field clears its error right away. The sharing question's
     error lives on its first radio, whichever of the two is clicked. */
  function errorOwner(el) {
    return consentRadios.indexOf(el) !== -1 ? consentRadios[0] : el;
  }
  form.addEventListener("input", function (e) {
    var t = e.target;
    if (t && t.type !== "file" && t.matches && t.matches("input, select, textarea")) clearError(errorOwner(t));
  });
  form.addEventListener("change", function (e) {
    var t = e.target;
    if (!t || !t.matches) return;
    if (t.type === "file") {
      onFileChosen(t);
      /* Photos taken back out: the sharing question no longer needs an answer. */
      if (t === photosInput && consentRadios.length && !(t.files && t.files.length)) clearError(consentRadios[0]);
    } else if (t.matches("input, select, textarea")) {
      clearError(errorOwner(t));
    }
  });

  /* Leaving mid-upload loses it: the browser asks first. */
  window.addEventListener("beforeunload", function (e) {
    if (!sending) return;
    e.preventDefault();
    e.returnValue = "";
  });

  /* "i" buttons: each shows or hides the note it names (aria-controls), and
     while the note shows it is read with the box it explains. */
  Array.prototype.forEach.call(form.querySelectorAll("[data-winners-info]"), function (button) {
    var note = document.getElementById(button.getAttribute("aria-controls") || "");
    if (!note) return;
    var input = note.parentNode ? note.parentNode.querySelector("input, select, textarea") : null;
    button.addEventListener("click", function () {
      var show = note.hidden;
      note.hidden = !show;
      button.setAttribute("aria-expanded", show ? "true" : "false");
      if (input) describedBy(input, note.id, show);
    });
  });

  initSchools();
  checkState();
})();
