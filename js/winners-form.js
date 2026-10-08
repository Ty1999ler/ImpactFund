/* ============================================================
   Winners page — /winners-fall-2026/ and /fr/gagnants-automne-2026/.

   1. Asks /api/winners.php?state=1 which state the page is in, then shows
      EITHER the open content (finance form download, tax block, form) OR
      exactly one state card: closed (with the deadline), not open yet, or not
      ready. A check that fails for any reason shows "not ready"; without
      JavaScript the page's <noscript> card shows instead. The schedule lives
      in the server's config — this file never decides it.
   2. Fills the school <select> from window.ALUMO_WINNERS_SCHOOLS (the frozen
      Fall 2026 list, /js/winners-schools-fall-2026.js): one <optgroup> per
      province, then "My school isn't listed", which reveals a required box.
   3. Validates exactly what api/winners.php validates, with inline errors,
      then uploads with XMLHttpRequest rather than fetch so the status line can
      show progress — up to three phone photos over mobile data. The server
      answers error KEYS (fields: {name: key}); they are mapped onto the EN/FR
      copy below, so the PHP never has to know the page's language.
   Strings: FR_DRAFTS.md (Oct 2026), French pending Hafsa's approval.
   ============================================================ */
(function () {
  "use strict";

  var form = document.querySelector('form[data-handler="winners"]');
  if (!form) return;

  var ENDPOINT = "/api/winners.php";
  var FILE_MAX_BYTES = 10 * 1024 * 1024; /* 10 MB — must match $maxBytes in api/winners.php */
  var SCHOOL_OTHER = "__other";          /* must match $SCHOOL_OTHER in api/winners.php */

  /* ---- UI strings (EN / FR, keyed off <html lang>, as in apply-form.js) ---- */
  var IS_FR = (document.documentElement.lang || "").toLowerCase().indexOf("fr") === 0;
  var T = IS_FR ? {
    deadline: function (date) { return "Veuillez envoyer vos documents au plus tard le " + date + "."; },
    closedText: function (date) { return "La date limite pour envoyer vos documents était le " + date + "."; },
    closedHelp: "Si vous devez encore les envoyer, répondez au courriel qu'Alumo vous a envoyé.",
    sending: function (n) { return "Envoi de vos documents en cours… " + n + " %"; },
    keepOpen: "Veuillez garder cette page ouverte jusqu'à la fin de l'envoi.",
    errSchool: "Veuillez sélectionner votre école dans la liste.",
    errSchoolOther: "Veuillez indiquer le nom de votre école.",
    errName: "Veuillez indiquer votre nom complet.",
    errTitle: "Veuillez indiquer le titre de votre projet.",
    errEmail: "Veuillez indiquer votre adresse courriel.",
    errEmailInvalid: "Veuillez entrer une adresse courriel valide.",
    errAgreement: "Veuillez téléverser votre entente signée.",
    errFinance: "Veuillez téléverser votre formulaire financier rempli.",
    errCheque: "Veuillez téléverser votre spécimen de chèque.",
    errConfirm: "Veuillez cocher cette case pour confirmer.",
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
    sending: function (n) { return "Sending your documents… " + n + "%"; },
    keepOpen: "Please keep this page open until it's finished.",
    errSchool: "Choose your school.",
    errSchoolOther: "Enter your school's name.",
    errName: "Enter your full name.",
    errTitle: "Enter your project title.",
    errEmail: "Enter your email address.",
    errEmailInvalid: "Enter a valid email address.",
    errAgreement: "Upload your signed agreement.",
    errFinance: "Upload your completed finance form.",
    errCheque: "Upload your void cheque.",
    errConfirm: "Please tick this box to confirm.",
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

  /* The three documents, in page order. exts must match
     winners_upload_slots() in api/_lib.php and the inputs' accept= lists. */
  var DOC_EXTS = ["pdf", "docx", "jpg", "jpeg", "png"];
  var SLOTS = [
    { name: "file_agreement", exts: DOC_EXTS, missing: T.errAgreement, type: T.errTypeDocs },
    { name: "file_finance_form", exts: DOC_EXTS, missing: T.errFinance, type: T.errTypeDocs },
    { name: "file_void_cheque", exts: ["pdf", "jpg", "jpeg", "png"], missing: T.errCheque, type: T.errTypeCheque }
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
    confirm: T.errConfirm
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
  var FIELD_ORDER = ["school", "school_other", "full_name", "project_title", "email",
    "file_agreement", "file_finance_form", "file_void_cheque", "confirm"];

  var statesSection = document.querySelector("[data-winners-states]");
  var openContent = document.querySelector("[data-winners-open]");
  var intro = document.querySelector("[data-winners-intro]");
  var deadlineLine = document.querySelector("[data-winners-deadline]");
  var closedText = document.querySelector("[data-winners-closed-text]");
  var success = document.querySelector("[data-winners-success]");
  var cards = {};
  Array.prototype.forEach.call(document.querySelectorAll("[data-winners-state]"), function (card) {
    cards[card.getAttribute("data-winners-state")] = card;
  });

  var schoolSelect = form.querySelector('select[name="school"]');
  var otherWrap = form.querySelector("#wf-school-other-wrap");
  var otherInput = form.querySelector('input[name="school_other"]');
  var submitBtn = form.querySelector('[type="submit"]');
  var honeypot = form.querySelector(".hp-field input");
  /* Status line: statusMain is what everyone sees; while sending it is
     aria-hidden, and screen readers hear statusSr instead, which only changes
     at 0/25/50/75% (showProgress). aria-atomic="false" (role=status implies
     true) so each change reads only itself, not the "keep this page open"
     line again. */
  var statusEl = form.querySelector(".form-status");
  var statusMain = document.createElement("span");
  var statusSr = document.createElement("span");
  var statusSub = document.createElement("span");
  statusSr.className = "visually-hidden";
  statusSub.className = "winners-status-sub";
  statusSub.hidden = true;
  statusEl.setAttribute("aria-atomic", "false");
  statusEl.appendChild(statusMain);
  statusEl.appendChild(statusSr);
  statusEl.appendChild(statusSub);

  function field(name) { return form.querySelector('[name="' + name + '"]'); }

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
           no-break spaces around the "h" (and before "%" in T.sending),
           as the French pages write 23&nbsp;h&nbsp;59 */
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
      intro.hidden = false;
      deadlineLine.hidden = !date;
      deadlineLine.textContent = date ? T.deadline(date) : "";
      return null;
    }
    if (!cards[state]) state = "unconfigured";
    openContent.hidden = true;
    intro.hidden = true;
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
      if (intro) intro.hidden = true;
      if (deadlineLine) deadlineLine.hidden = true;
      statesSection.hidden = false;
      if (cards.unconfigured) cards.unconfigured.hidden = false;
      return cards.unconfigured || null;
    }
  }

  function checkState() {
    var settled = false;
    function settle(state, ts) {
      if (settled) return;
      settled = true;
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
          settle(j.state, j.closes_at_ts);
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

  /* ---- School select (frozen Fall 2026 list) ---- */
  function fillSchools() {
    var rows = window.ALUMO_WINNERS_SCHOOLS;
    var otherOption = schoolSelect.querySelector('option[value="' + SCHOOL_OTHER + '"]');
    if (!Array.isArray(rows)) return; /* list missing: "not listed" still works */
    PROVINCES.forEach(function (province) {
      var names = [];
      var seen = {};
      rows.forEach(function (row) {
        var name = String((row && row.school) || "").trim();
        if (!row || row.province !== province[0] || !name || seen[name.toLowerCase()]) return;
        seen[name.toLowerCase()] = true;
        names.push(name);
      });
      if (!names.length) return;
      names.sort(function (a, b) { return a.localeCompare(b); });
      var group = document.createElement("optgroup");
      group.label = province[1];
      names.forEach(function (name) {
        var option = document.createElement("option");
        option.value = name;
        option.textContent = name;
        group.appendChild(option);
      });
      schoolSelect.insertBefore(group, otherOption);
    });
  }

  /* "My school isn't listed" reveals and requires the name box; any other
     choice hides and EMPTIES it, so a retracted name is never submitted. */
  function syncSchoolOther() {
    var isOther = schoolSelect.value === SCHOOL_OTHER;
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

  function refreshSelectTint() {
    schoolSelect.classList.toggle("is-placeholder", schoolSelect.value === "");
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
  function fileProblem(input) {
    var slot = SLOT_BY_NAME[input.name];
    var file = input.files && input.files[0];
    /* A file refused on pick was cleared from the input: keep saying why
       ("HEIC photos aren't supported…") rather than "Upload your…", or the
       winner would just pick the same photo again. */
    if (!file) return input.getAttribute("data-rejected") || slot.missing;
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

  /* ---- Whole-form validation, mirroring api/winners.php. Returns the
     invalid fields in page order. ---- */
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
    check(schoolSelect, schoolSelect.value === "" ? T.errSchool : "");
    if (schoolSelect.value === SCHOOL_OTHER) {
      check(otherInput, otherInput.value.trim() === "" ? T.errSchoolOther : "");
    }
    ["full_name", "project_title"].forEach(function (name) {
      var el = field(name);
      check(el, el.value.trim() === "" ? REQUIRED[name] : "");
    });
    var email = field("email");
    var value = email.value.trim();
    check(email, value === "" ? T.errEmail
      : (!EMAIL_RE.test(value) || (email.validity && email.validity.typeMismatch)) ? T.errEmailInvalid : "");
    SLOTS.forEach(function (slot) {
      var input = field(slot.name);
      check(input, fileProblem(input));
    });
    var confirm = field("confirm");
    check(confirm, confirm.checked ? "" : T.errConfirm);
    return invalid;
  }

  function focusField(el) {
    var wrap = fieldWrap(el);
    if (wrap && wrap.scrollIntoView) wrap.scrollIntoView({ behavior: "smooth", block: "center" });
    try { el.focus({ preventScroll: true }); } catch (e) { el.focus(); }
  }

  /* ---- Status line ---- */
  function showStatus(message, isError) {
    statusMain.removeAttribute("aria-hidden");
    statusMain.textContent = message;
    statusSr.textContent = "";
    statusSub.textContent = "";
    statusSub.hidden = true;
    statusEl.classList.toggle("is-error", !!isError);
    statusEl.hidden = false;
  }
  /* The visible line counts every percent; screen readers hear 0, 25, 50 and
     75 only — a polite live region would otherwise queue ~100 announcements
     during one upload. The "keep this page open" line is set once, in send(). */
  var lastPercent = -1;
  var lastMilestone = -1;
  function showProgress(percent) {
    if (percent === lastPercent) return;
    lastPercent = percent;
    statusMain.setAttribute("aria-hidden", "true");
    statusMain.textContent = T.sending(percent);
    var milestone = Math.floor(percent / 25) * 25;
    if (milestone !== lastMilestone) {
      lastMilestone = milestone;
      statusSr.textContent = T.sending(milestone);
    }
    statusEl.classList.remove("is-error");
    statusEl.hidden = false;
  }

  /* ---- Submit ---- */
  var sending = false;

  function finishSending() {
    sending = false;
    submitBtn.disabled = false;
    form.removeAttribute("aria-busy");
  }

  function showSuccess() {
    sending = false;
    form.removeAttribute("aria-busy");
    form.reset();
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
          : Object.prototype.hasOwnProperty.call(FIELD_ERRORS, key) ? FIELD_ERRORS[key]
          : T.errFields;
        setError(el, text);
        if (!first) first = el;
      });
      message = T.errFields;
      if (first) focusField(first);
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
    form.setAttribute("aria-busy", "true");
    lastPercent = -1;
    lastMilestone = -1;
    statusSub.textContent = T.keepOpen;
    statusSub.hidden = false;
    showProgress(0);

    var xhr = new XMLHttpRequest();
    xhr.open("POST", ENDPOINT, true);
    xhr.timeout = 15 * 60 * 1000; /* three 10 MB files over a slow connection */
    xhr.setRequestHeader("Accept", "application/json");
    if (xhr.upload) {
      xhr.upload.onprogress = function (e) {
        /* Capped at 99 until the server answers: "upload finished" is not
           "received". */
        if (e.lengthComputable && e.total > 0) {
          showProgress(Math.min(99, Math.floor(e.loaded / e.total * 100)));
        }
      };
    }
    xhr.onload = function () { handleResponse(xhr.status, parseJson(xhr.responseText)); };
    xhr.onerror = xhr.ontimeout = xhr.onabort = function () {
      showStatus(T.errNetwork, true);
      finishSending();
    };
    xhr.send(new FormData(form));
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

  /* Correcting a field clears its error right away. */
  form.addEventListener("input", function (e) {
    var t = e.target;
    if (t && t.type !== "file" && t.matches && t.matches("input, select, textarea")) clearError(t);
  });
  form.addEventListener("change", function (e) {
    var t = e.target;
    if (!t || !t.matches) return;
    if (t.type === "file") {
      onFileChosen(t);
    } else if (t.matches("input, select, textarea")) {
      clearError(t);
    }
    if (t === schoolSelect) {
      syncSchoolOther();
      refreshSelectTint();
    }
  });

  /* Leaving mid-upload loses it; the status line already asks them to stay. */
  window.addEventListener("beforeunload", function (e) {
    if (!sending) return;
    e.preventDefault();
    e.returnValue = "";
  });

  fillSchools();
  syncSchoolOther();   /* also covers a browser restoring the select on reload */
  refreshSelectTint();
  checkState();
})();
