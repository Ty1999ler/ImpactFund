/* Student Impact Fund by Alumo — shared behavior
   - mobile burger menu
   - language dropdown
   - sticky / hide-on-scroll header (matches the live site's headroom behavior)
   - contact form: validates client-side, then POSTs to /api/contact.php,
     which answers JSON {ok, error, fields} (503 until api/config.php exists
     on the server) — per-field errors land inline on the matching inputs
   - scheduled content ([data-opens-at] / [data-closes-at]) + the
     ?preview-at= site preview, the pinned before./open./closed. preview
     copies, and their banner
*/
(function () {
  "use strict";

  var header = document.querySelector(".site-header");

  /* ---- Mobile burger menu ---- */
  var burger = document.querySelector(".hamburger-menu-toggle-btn");
  if (burger && header) {
    burger.addEventListener("click", function () {
      burger.classList.toggle("open");
      header.classList.toggle("show");
      document.body.style.overflow = header.classList.contains("show") ? "hidden" : "";
    });
    // Close the menu when a nav link is chosen (anchor links on the same page)
    header.querySelectorAll(".nav-menu a").forEach(function (link) {
      link.addEventListener("click", function () {
        burger.classList.remove("open");
        header.classList.remove("show");
        document.body.style.overflow = "";
      });
    });
  }

  /* ---- Language dropdown ---- */
  var langSwitcher = document.querySelector(".lang-switcher");
  if (langSwitcher) {
    var toggle = langSwitcher.querySelector(".lang-current");
    if (toggle) {
      toggle.addEventListener("click", function (e) {
        e.stopPropagation();
        langSwitcher.classList.toggle("is-open");
      });
    }
    document.addEventListener("click", function (e) {
      if (!langSwitcher.contains(e.target)) {
        langSwitcher.classList.remove("is-open");
      }
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") langSwitcher.classList.remove("is-open");
    });
  }

  /* ---- Sticky header (headroom-style) ---- */
  if (header) {
    var lastY = window.scrollY;
    var onScroll = function () {
      var y = window.scrollY;
      if (y > 10) {
        header.classList.add("sticky-header");
      } else {
        header.classList.remove("sticky-header");
      }
      if (y > lastY && y > 120 && !header.classList.contains("show")) {
        header.classList.add("is-unpinned");
        header.classList.remove("is-pinned");
      } else {
        header.classList.add("is-pinned");
        header.classList.remove("is-unpinned");
      }
      lastY = y;
    };
    window.addEventListener("scroll", onScroll, { passive: true });
    onScroll();
  }

  /* ---- Contact form → POST /api/contact.php (PHP handler, see PLANS.md) ---- */
  document.querySelectorAll('form[data-handler="email"]').forEach(function (form) {
    var fr = (document.documentElement.lang || "").toLowerCase().indexOf("fr") === 0;
    var MSG = {
      sending: fr ? "Envoi en cours…" : "Sending…",
      ok: fr ? "Merci ! Votre message a bien été envoyé." : "Thank you! Your message has been sent.",
      fail: fr ? "Le message n'a pas pu être envoyé. Veuillez réessayer plus tard."
               : "The message could not be sent. Please try again later.",
      required: fr ? "Ce champ est obligatoire." : "This field is required.",
      invalidEmail: fr ? "Veuillez entrer une adresse courriel valide."
                       : "Please enter a valid email address."
    };
    var status = document.createElement("p");
    status.className = "form-status";
    status.hidden = true;
    status.setAttribute("role", "status");
    form.appendChild(status);

    /* Inline field errors — same pattern as the apply form. The note is
       styled inline because the shared stylesheet only carries the
       aria-invalid red border for contact forms. */
    function fieldWrap(el) { return el.closest(".form-field") || el.parentElement; }
    function setFieldError(el, message) {
      el.setAttribute("aria-invalid", "true");
      var wrap = fieldWrap(el);
      var note = wrap.querySelector(".field-error");
      if (!note) {
        note = document.createElement("p");
        note.className = "field-error";
        note.style.color = "#b3261e";
        note.style.fontSize = "0.875rem";
        note.style.margin = "4px 0 0";
        wrap.appendChild(note);
      }
      note.textContent = message;
    }
    function clearFieldError(el) {
      el.removeAttribute("aria-invalid");
      var wrap = fieldWrap(el);
      if (!wrap) return;
      var note = wrap.querySelector(".field-error");
      if (note) note.parentNode.removeChild(note);
    }
    function fields() {
      return Array.prototype.filter.call(
        form.querySelectorAll("input, textarea"),
        function (el) { return !el.closest(".hp-field"); }
      );
    }
    /* Correcting a field clears its error right away (it used to stay red
       even after being fixed). */
    form.addEventListener("input", function (e) {
      if (e.target.matches("input, textarea")) clearFieldError(e.target);
    });
    form.addEventListener("change", function (e) {
      if (e.target.matches("input, textarea")) clearFieldError(e.target);
    });

    /* Client-side validation: required fields + email shape, with inline
       messages — nothing is sent until the form passes. */
    function validate() {
      var invalid = [];
      fields().forEach(function (el) {
        clearFieldError(el);
        if (el.required && el.value.trim() === "") {
          setFieldError(el, MSG.required);
          invalid.push(el);
        } else if (el.type === "email" && el.value.trim() !== "" && !el.checkValidity()) {
          setFieldError(el, MSG.invalidEmail);
          invalid.push(el);
        }
      });
      if (invalid.length) invalid[0].focus();
      return !invalid.length;
    }

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var hp = form.querySelector('input[name="website"]');
      if (hp && hp.value) return; /* spam trap — silently drop */
      if (!validate()) { status.hidden = true; return; }
      var btn = form.querySelector('[type="submit"]');
      if (btn) btn.disabled = true;
      status.hidden = false;
      status.textContent = MSG.sending;
      status.classList.remove("is-error");

      fetch("/api/contact.php", { method: "POST", body: new FormData(form) })
        .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok && j.ok, j: j }; }); })
        .then(function (res) {
          if (res.ok) {
            fields().forEach(clearFieldError);
            form.reset();
            status.textContent = MSG.ok;
          } else {
            if (res.j && res.j.fields) {
              Object.keys(res.j.fields).forEach(function (n) {
                var el = form.querySelector('[name="' + n + '"]');
                if (!el) return;
                /* api/contact.php answers in English — localize its two
                   known field errors. */
                setFieldError(el, res.j.fields[n] === "Invalid email"
                  ? MSG.invalidEmail : MSG.required);
              });
            }
            status.textContent = (res.j && res.j.error) || MSG.fail;
            status.classList.add("is-error");
          }
        })
        .catch(function () {
          status.textContent = MSG.fail;
          status.classList.add("is-error");
        })
        .finally(function () { if (btn) btn.disabled = false; });
    });
  });

  /* ---- Scheduled content (the submission window) ----
     data-opens-at / data-closes-at hold ISO dates WITH an offset
     ("2026-10-15T23:59:59-04:00"); together they define the window
     [opens-at, closes-at). Either may be left off (no bound on that side).
     An element carrying them either:
     - holds an .apply-form-closed and an .apply-form-open child: the open
       one shows inside the window, the closed one outside it; or
     - is shown/hidden itself (author it with `hidden`): shown inside the
       window, or, with data-show-when="closed", only OUTSIDE it (the
       post-close notes on the Partners and Submit pages).
     A date that is present but does not parse (or has no offset) leaves the
     element exactly as authored — never new Date(null), which is 1970.
     Evaluated once per page load ON PURPOSE, no timer: flipping an open tab
     at the deadline would take a half-filled form away from someone
     api/apply.php would still accept during its grace window.
     Previews are visual only (api/apply.php gates on its own config and
     never sees these):
     - ?preview-form=1 forces the open state (this page only);
     - ?preview-at=2026-10-16T09:00:00-04:00 renders the site as at that
       moment and STICKS for the tab (sessionStorage), so one link can be
       clicked around; a banner says so and links to ?preview-at=off, which
       ends it. A value that does not parse is ignored. Given twice, the
       LAST one wins (so a hand-appended value or "off" takes effect).
       Honoured on PREVIEW_HOSTS only: on production a shared link would
       show real visitors a closed site on every page they click to, with
       the consent banner covering the only sign that it is a preview;
     - before.alumoimpact.ca / open.alumoimpact.ca / closed.alumoimpact.ca
       always show the site before the window / the window open / the
       window closed (the pinned preview copies, below). */
  var ISO_WITH_OFFSET = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?([+-]\d{2}:\d{2}|Z)$/;
  function parseIso(value) {
    if (typeof value !== "string" || !ISO_WITH_OFFSET.test(value)) return null;
    var d = new Date(value);
    return isNaN(d) ? null : d;
  }

  var PREVIEW_KEY = "sifPreviewAt";
  var PREVIEW_HOSTS = ["staging.alumoimpact.ca", "before.alumoimpact.ca",
    "open.alumoimpact.ca", "closed.alumoimpact.ca", "impactfund.wareham.stream",
    "localhost", "127.0.0.1"];
  /* Without a leading "www.": cPanel gives each subdomain a www. alias, and
     www.closed.alumoimpact.ca must not quietly look like production.
     www.alumoimpact.ca becomes alumoimpact.ca, which is in neither list. */
  var schedHost = window.location.hostname.replace(/^www\./, "");
  var schedQuery = window.location.search;
  var store = null;
  try { store = window.sessionStorage; } catch (e) { /* storage blocked: preview lasts one page */ }
  /* Split rather than regex the query: the last preview-at= wins, and the
     exit link (keptQuery) drops every copy of it. The pinned copies' link
     to staging (plainQuery) drops preview-form= as well. */
  var previewParam = null;
  var keptQuery = [];
  var plainQuery = [];
  schedQuery.replace(/^\?/, "").split("&").forEach(function (part) {
    if (part.indexOf("preview-at=") === 0) {
      previewParam = part.slice("preview-at=".length);
    } else if (part) {
      keptQuery.push(part);
      if (part.indexOf("preview-form=") !== 0) plainQuery.push(part);
    }
  });
  var previewRaw = null;
  if (previewParam !== null) {
    try { previewRaw = decodeURIComponent(previewParam); } catch (e) { /* bad %-sequence: ignore */ }
  }
  if (previewRaw === "off" || PREVIEW_HOSTS.indexOf(schedHost) === -1) {
    previewRaw = null;
    try { if (store) store.removeItem(PREVIEW_KEY); } catch (e) { /* ignore */ }
  } else if (parseIso(previewRaw)) {
    try { if (store) store.setItem(PREVIEW_KEY, previewRaw); } catch (e) { /* ignore */ }
  } else {
    /* No (usable) value in the URL: carry on with the tab's stored one. */
    previewRaw = null;
    try { if (store) previewRaw = store.getItem(PREVIEW_KEY); } catch (e) { /* ignore */ }
  }
  var previewAt = parseIso(previewRaw);
  var realNow = new Date();
  var schedNow = previewAt || realNow;
  var forceOpen = /[?&]preview-form=1/.test(schedQuery);

  /* ---- Pinned preview copies ----
     before.alumoimpact.ca, open.alumoimpact.ca and closed.alumoimpact.ca are
     cPanel subdomains on staging's own document root: the same files as
     staging, and the host name alone picks the view, so a plain link (no
     parameter to lose on the way) shows each state on any day:
     - before.: the site just before the window opens — the real clock until
       then, the last second before its opening after that;
     - open.: the window open — the real clock while it is open, its first
       moment before that, its last second after it has closed;
     - closed.: the site as from the close moment (the real clock once that
       has passed).
     The moments come from the page's own dates: the first element that
     carries data-opens-at AND data-closes-at, both parsing. So nothing here
     needs editing from one round to the next. A page without one (the legal
     pages) has nothing scheduled and only gets the banner, undated.
     A ?preview-at= (in the URL or stored for the tab) wins over the pin, and
     its exit link comes back to it; ?preview-form=1 still forces open (and
     then gets the open copy's banner, even on before. and closed.).
     Visual only, like the previews: a form sent from the open copy goes to
     staging's api/apply.php, which gates on staging's own config (and marks
     it [TEST], like everything sent from staging). */
  var PINNED_VIEWS = { "before.alumoimpact.ca": "before", "open.alumoimpact.ca": "open",
    "closed.alumoimpact.ca": "closed" };
  var pinnedView = null;
  if (!previewAt && Object.prototype.hasOwnProperty.call(PINNED_VIEWS, schedHost)) {
    pinnedView = PINNED_VIEWS[schedHost];
  }
  var pinnedOpen = null;        /* the opening moment, for the before banner */
  var pinnedOpenRaw = null;     /* ...and as authored, if it can't be formatted */
  var pinnedClose = null;       /* the close moment, for the closed banner */
  var pinnedCloseRaw = null;
  if (pinnedView) {
    var bounded = document.querySelectorAll("[data-opens-at][data-closes-at]");
    for (var k = 0; k < bounded.length && !pinnedClose; k++) {
      var pinOpens = parseIso(bounded[k].getAttribute("data-opens-at"));
      var pinCloses = parseIso(bounded[k].getAttribute("data-closes-at"));
      if (!pinOpens || !pinCloses) continue;
      pinnedOpen = pinOpens;
      pinnedOpenRaw = bounded[k].getAttribute("data-opens-at");
      pinnedClose = pinCloses;
      pinnedCloseRaw = bounded[k].getAttribute("data-closes-at");
      if (pinnedView === "before") {
        if (realNow >= pinOpens) schedNow = new Date(pinOpens.getTime() - 1000);
      } else if (pinnedView === "closed") {
        schedNow = realNow >= pinCloses ? realNow : pinCloses;
      } else if (realNow < pinOpens) {
        schedNow = pinOpens;
      } else if (realNow >= pinCloses) {
        schedNow = new Date(pinCloses.getTime() - 1000);
      }
    }
  }

  function schedDate(el, name) {
    if (!el.hasAttribute(name)) return undefined;   /* no bound on that side */
    return parseIso(el.getAttribute(name));         /* null = malformed */
  }
  document.querySelectorAll("[data-opens-at], [data-closes-at]").forEach(function (el) {
    var opensAt = schedDate(el, "data-opens-at");
    var closesAt = schedDate(el, "data-closes-at");
    if (opensAt === null || closesAt === null) return;
    var isOpen = forceOpen ||
      ((!opensAt || schedNow >= opensAt) && (!closesAt || schedNow < closesAt));
    var closed = el.querySelector(".apply-form-closed");
    var open = el.querySelector(".apply-form-open");
    if (closed && open) {
      closed.hidden = isOpen;
      open.hidden = !isOpen;
    } else {
      el.hidden = el.getAttribute("data-show-when") === "closed" ? isOpen : !isOpen;
    }
  });

  /* ---- Preview banner (while a ?preview-at= preview is active, and on
     the pinned copies) ----
     Fixed bottom-left so it never covers the header's menu button; z-index
     98, just under the header's 99, so the open mobile menu (language
     switcher included) covers it; the body gets bottom padding the banner's
     height so the end of the footer scrolls clear of it. Styled inline
     because no stylesheet owns it. Its link (exit the preview / go to
     staging) is a real link, so it is reachable by keyboard; the site
     strips focus outlines globally (style.css), so this one draws its own.
     The date is written out in Eastern time; if the browser can't do that,
     the raw value is shown.
     French spacing: these strings go in via text nodes, where an HTML
     entity would print literally, so the no-break spaces (before ":" and
     around "h" in "9 h 00") are written as \u00A0 escapes. */
  function formatPreviewDate(d, fr) {
    var MONTHS = fr
      ? ["janvier", "février", "mars", "avril", "mai", "juin", "juillet",
         "août", "septembre", "octobre", "novembre", "décembre"]
      : ["January", "February", "March", "April", "May", "June", "July",
         "August", "September", "October", "November", "December"];
    try {
      var p = {};
      new Intl.DateTimeFormat("en-US", {
        timeZone: "America/Toronto", hour12: false,
        year: "numeric", month: "numeric", day: "numeric",
        hour: "numeric", minute: "numeric"
      }).formatToParts(d).forEach(function (part) { p[part.type] = part.value; });
      var year = parseInt(p.year, 10);
      var month = parseInt(p.month, 10);
      var day = parseInt(p.day, 10);
      var hour = parseInt(p.hour, 10) % 24;   /* some engines say 24 for midnight */
      var minute = parseInt(p.minute, 10);
      if (isNaN(year) || !MONTHS[month - 1] || isNaN(day) || isNaN(hour) || isNaN(minute)) {
        return null;
      }
      minute = (minute < 10 ? "0" : "") + minute;
      if (fr) {
        return day + (day === 1 ? "er" : "") + " " + MONTHS[month - 1] + " " + year +
          " à " + hour + "\u00A0h\u00A0" + minute + " (heure de l'Est)";
      }
      return MONTHS[month - 1] + " " + day + ", " + year + ", " + (hour % 12 || 12) + ":" +
        minute + " " + (hour < 12 ? "a.m." : "p.m.") + " ET";
    } catch (e) {
      return null;   /* no Intl / formatToParts / time-zone data */
    }
  }
  function showBanner(text, href, linkText) {
    var banner = document.createElement("div");
    banner.className = "preview-banner";
    banner.setAttribute("role", "status");
    banner.style.cssText =
      "position:fixed;left:16px;bottom:16px;z-index:98;box-sizing:border-box;" +
      "max-width:calc(100% - 32px);padding:10px 16px;border-radius:12px;" +
      "background:var(--text);color:var(--primary-light-1);" +
      "font-family:var(--font-inter);font-size:14px;line-height:1.4;" +
      "box-shadow:0 6px 24px rgba(0,0,0,.18)";
    banner.appendChild(document.createTextNode(text));
    var link = document.createElement("a");
    link.href = href;
    link.textContent = linkText;
    link.style.cssText = "color:var(--primary);font-weight:600;text-decoration:underline";
    link.addEventListener("focus", function () {
      link.style.outline = "2px solid var(--primary)";
      link.style.outlineOffset = "2px";
    });
    link.addEventListener("blur", function () { link.style.outline = ""; });
    banner.appendChild(link);
    /* Right after the skip link, so keyboard users meet it early. */
    var skip = document.querySelector(".skip-link");
    if (skip && skip.parentNode === document.body) {
      document.body.insertBefore(banner, skip.nextSibling);
    } else {
      document.body.insertBefore(banner, document.body.firstChild);
    }
    var reserveRoom = function () {
      document.body.style.paddingBottom = (banner.offsetHeight + 32) + "px";
    };
    reserveRoom();
    window.addEventListener("resize", reserveRoom);
  }
  var bannerFr = (document.documentElement.lang || "").toLowerCase().indexOf("fr") === 0;
  if (previewAt) {
    var exitSearch = keptQuery.length ? "?" + keptQuery.join("&") : "";
    showBanner(
      (bannerFr ? "Aperçu\u00A0: le site tel qu'il apparaîtra le "
                : "Preview: the site as it will look on ") +
        (formatPreviewDate(previewAt, bannerFr) || previewRaw) + ". ",
      window.location.pathname + exitSearch + (exitSearch ? "&" : "?") +
        "preview-at=off" + window.location.hash,
      bannerFr ? "Quitter l'aperçu" : "Exit preview");
  } else if (pinnedView) {
    /* The before copy names the opening moment and the closed copy the close
       moment, as "on {date}" / "le {date}" rather than in parentheses (the
       French date already ends in "(heure de l'Est)"); a page with nothing
       scheduled has no date to name, so that part is left out. A page forced
       open by ?preview-form=1 says open, whichever copy it is on. The link
       goes to the same page on staging, minus the preview parameters. */
    var pinnedOn = function (d, raw) {
      return d ? (bannerFr ? " le " : " on ") + (formatPreviewDate(d, bannerFr) || raw) : "";
    };
    var bannerView = forceOpen ? "open" : pinnedView;
    var pinnedText;
    if (bannerView === "open") {
      pinnedText = bannerFr
        ? "Copie d'aperçu\u00A0: le site tel qu'il apparaît pendant la période de soumission. "
        : "Preview copy: the site as it looks while submissions are open. ";
    } else if (bannerView === "before") {
      pinnedText = bannerFr
        ? "Copie d'aperçu\u00A0: le site tel qu'il apparaît avant l'ouverture des soumissions" +
          pinnedOn(pinnedOpen, pinnedOpenRaw) + ". "
        : "Preview copy: the site as it looks before submissions open" +
          pinnedOn(pinnedOpen, pinnedOpenRaw) + ". ";
    } else {
      pinnedText = bannerFr
        ? "Copie d'aperçu\u00A0: le site tel qu'il apparaîtra après la fermeture des soumissions" +
          pinnedOn(pinnedClose, pinnedCloseRaw) + ". "
        : "Preview copy: the site as it will look after submissions close" +
          pinnedOn(pinnedClose, pinnedCloseRaw) + ". ";
    }
    showBanner(pinnedText,
      "https://staging.alumoimpact.ca" + window.location.pathname +
        (plainQuery.length ? "?" + plainQuery.join("&") : "") + window.location.hash,
      bannerFr ? "Aller au site d'essai" : "Go to staging");
  }
})();
