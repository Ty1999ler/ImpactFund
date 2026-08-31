/* ============================================================
   Google Analytics 4 + consent banner.

   Live on alumoimpact.ca since 2026-08-31 (client approved). It is inert on
   any host not listed in ENABLED_HOSTS, which is how the same file can ship to
   the September preview without reporting anything from there.

   HOW IT BEHAVES
     - On a host with no ID configured: does nothing at all. No banner, no
       network request, no storage.
     - On an enabled host: shows a consent banner. NOTHING from Google is
       loaded until the visitor presses Accept. That is stricter than
       Google's own "consent mode default denied" pattern, which still
       sends cookieless pings before any choice is made — Quebec's Law 25
       applies to this site (it serves Quebec CEGEPs and universities), so
       "no third-party request until yes" is the defensible position.
     - Decline is remembered and changes nothing else about the site.
     - The choice is kept in localStorage, NOT a cookie, so declining
       leaves no cookie behind at all.

   STILL OUTSTANDING
     /cookies-policy/ is a redirect stub — the client had it removed back
     when the site set no cookies at all. The banner's policy link therefore
     falls back to the ASEQ privacy PDF, which says nothing about Google
     Analytics and is not a document we control. Either Alumo amends that
     PDF or a real cookies page comes back. Flagged and accepted at launch.
   ============================================================ */
(function () {
  "use strict";

  /* GA4 Measurement ID (Admin -> Data streams -> Web). Not a secret — it ships
     in the page source of every site that uses GA, which is why it lives in
     this repo rather than in server-side config.
     Switching properties is this one string; nothing else in the file changes.
       2026-08-29  G-KJYPC798JQ  first preview property
       2026-08-29  G-1Q7HZYT3F1  current
     Google's own install snippet loads gtag immediately on page load. We
     deliberately do NOT do that — see the consent note at the top of the file.
     If this is ever reset to the G-XXXX placeholder the banner still renders,
     but no Google script is loaded — handy for demoing the flow safely. */
  var MEASUREMENT_ID = "G-1Q7HZYT3F1";

  /* Hosts where analytics is offered at all. Anywhere else this file loads and
     does nothing: no banner, no request, no storage.
     september.alumoimpact.ca is deliberately NOT listed — the preview exists to
     be poked at, and that traffic would be indistinguishable from real visitors
     in the reports. The file still ships there; it just stays inert. */
  var ENABLED_HOSTS = [
    "alumoimpact.ca",
    "www.alumoimpact.ca",
    "localhost",
    "127.0.0.1"
  ];

  var STORAGE_KEY = "alumo-analytics-consent"; // "granted" | "denied"
  var host = location.hostname;

  if (ENABLED_HOSTS.indexOf(host) === -1) return;

  var IS_FR = (document.documentElement.lang || "").toLowerCase().indexOf("fr") === 0;
  var isPlaceholder = /^G-X+$/.test(MEASUREMENT_ID);

  var T = IS_FR ? {
    body: "Nous aimerions utiliser Google Analytics pour comprendre comment ce site est utilisé. Des témoins seraient alors déposés sur votre appareil.",
    note: "Refuser ne change rien au fonctionnement du site.",
    accept: "Accepter",
    decline: "Refuser",
    policy: "Politique de confidentialité",
    manage: "Gérer mon consentement",
    label: "Avis concernant les témoins"
  } : {
    body: "We'd like to use Google Analytics to understand how people use this site. That would place cookies on your device.",
    note: "Declining changes nothing about how the site works.",
    accept: "Accept",
    decline: "Decline",
    policy: "Privacy policy",
    manage: "Consent preferences",
    label: "Cookie notice"
  };

  /* The footer's Privacy Policy link is the ASEQ PDF — reuse whatever this
     page already points at rather than hardcoding a second copy of it. */
  function policyHref() {
    var a = document.querySelector('footer a[href*="privacy" i], footer a[href$=".pdf"]');
    return a ? a.getAttribute("href") : null;
  }

  function stored() {
    try { return localStorage.getItem(STORAGE_KEY); } catch (e) { return null; }
  }
  function remember(v) {
    try { localStorage.setItem(STORAGE_KEY, v); } catch (e) {}
  }

  /* ---------- Google tag ---------- */
  var loaded = false;
  function loadGoogle() {
    if (loaded || isPlaceholder) return;
    loaded = true;

    window.dataLayer = window.dataLayer || [];
    function gtag() { window.dataLayer.push(arguments); }
    window.gtag = gtag;

    /* Declare consent state before the tag initialises. Ads storage stays
       denied — this property is for audience measurement only. */
    gtag("consent", "default", {
      ad_storage: "denied",
      ad_user_data: "denied",
      ad_personalization: "denied",
      analytics_storage: "granted"
    });

    var s = document.createElement("script");
    s.async = true;
    s.src = "https://www.googletagmanager.com/gtag/js?id=" + encodeURIComponent(MEASUREMENT_ID);
    document.head.appendChild(s);

    gtag("js", new Date());
    gtag("config", MEASUREMENT_ID);
  }

  /* ---------- banner ---------- */
  var banner = null;

  function injectStyles() {
    if (document.getElementById("alumo-consent-styles")) return;
    var css =
      ".alumo-consent{position:fixed;left:16px;right:16px;bottom:16px;z-index:9999;" +
      "max-width:640px;margin-inline:auto;background:#fbf6ed;color:var(--text,#222);" +
      "border:1px solid rgba(34,34,34,.15);border-radius:16px;padding:20px 22px;" +
      "box-shadow:0 8px 30px rgba(0,0,0,.14);font-family:var(--font-inter,system-ui,sans-serif);" +
      "font-size:15px;line-height:1.5}" +
      ".alumo-consent p{margin:0 0 6px}" +
      ".alumo-consent .alumo-consent-note{opacity:.75;font-size:13.5px;margin-bottom:14px}" +
      ".alumo-consent-actions{display:flex;flex-wrap:wrap;gap:10px;align-items:center}" +
      ".alumo-consent button{font:inherit;font-weight:600;cursor:pointer;border-radius:50px;" +
      "padding:9px 20px;border:1px solid var(--text,#222);transition:.2s}" +
      ".alumo-consent .alumo-accept{background:var(--primary,#fbbc7e);border-color:var(--primary,#fbbc7e)}" +
      ".alumo-consent .alumo-accept:hover{background:var(--text,#222);border-color:var(--text,#222);color:#fbf6ed}" +
      ".alumo-consent .alumo-decline{background:transparent}" +
      ".alumo-consent .alumo-decline:hover{background:var(--text,#222);color:#fbf6ed}" +
      ".alumo-consent a{color:inherit;text-decoration:underline;margin-left:auto;font-size:13.5px}" +
      "@media(max-width:560px){.alumo-consent a{margin-left:0;width:100%}}";
    var el = document.createElement("style");
    el.id = "alumo-consent-styles";
    el.textContent = css;
    document.head.appendChild(el);
  }

  function closeBanner() {
    if (banner && banner.parentNode) banner.parentNode.removeChild(banner);
    banner = null;
  }

  function showBanner() {
    if (banner) return;
    injectStyles();

    banner = document.createElement("div");
    banner.className = "alumo-consent";
    banner.setAttribute("role", "dialog");
    banner.setAttribute("aria-live", "polite");
    banner.setAttribute("aria-label", T.label);

    var p = document.createElement("p");
    p.textContent = T.body;
    var note = document.createElement("p");
    note.className = "alumo-consent-note";
    note.textContent = T.note;

    var actions = document.createElement("div");
    actions.className = "alumo-consent-actions";

    var accept = document.createElement("button");
    accept.type = "button";
    accept.className = "alumo-accept";
    accept.textContent = T.accept;
    accept.addEventListener("click", function () {
      remember("granted");
      closeBanner();
      loadGoogle();
    });

    var decline = document.createElement("button");
    decline.type = "button";
    decline.className = "alumo-decline";
    decline.textContent = T.decline;
    decline.addEventListener("click", function () {
      remember("denied");
      closeBanner();
    });

    actions.appendChild(accept);
    actions.appendChild(decline);

    var href = policyHref();
    if (href) {
      var link = document.createElement("a");
      link.href = href;
      link.target = "_blank";
      link.rel = "noopener";
      link.textContent = T.policy;
      actions.appendChild(link);
    }

    banner.appendChild(p);
    banner.appendChild(note);
    banner.appendChild(actions);
    document.body.appendChild(banner);
    accept.focus();
  }

  /* Withdrawing has to be as easy as granting, so every page gets a footer
     link back to the banner once analytics is in play. */
  function addFooterLink() {
    var footer = document.querySelector("footer");
    if (!footer || document.getElementById("alumo-consent-link")) return;
    var wrap = document.createElement("div");
    wrap.style.cssText = "text-align:center;padding:0 0 18px";
    var a = document.createElement("a");
    a.id = "alumo-consent-link";
    a.href = "#";
    a.textContent = T.manage;
    a.style.cssText = "font-family:var(--font-inter,system-ui,sans-serif);font-size:13.5px;text-decoration:underline;color:inherit";
    a.addEventListener("click", function (e) {
      e.preventDefault();
      showBanner();
    });
    wrap.appendChild(a);
    footer.appendChild(wrap);
  }

  /* ---------- events ----------
     Everything below OBSERVES the page; it never calls into apply-form.js and
     apply-form.js knows nothing about it. That is deliberate: the application
     form is the one thing on this site that must not break, so analytics is
     wired as a passive listener that cannot interfere with a submission even
     if it throws.

     NO PERSONAL DATA IS EVER SENT. Not names, emails, schools or project
     titles — only which step was reached, which file was downloaded, and the
     page language. Google's terms forbid it, and shipping applicant details to
     a third party would be indefensible for this client in particular.

     Nothing fires before consent: track() is a no-op until gtag exists, and
     gtag only exists after Accept. Events are not replayed retroactively if
     someone consents later — that is the correct behaviour, not a gap. */
  function track(name, params) {
    if (typeof window.gtag !== "function") return;
    try { window.gtag("event", name, params || {}); } catch (e) {}
  }

  var lang = IS_FR ? "fr" : "en";

  function wireEvents() {
    /* Template / guide downloads. Delegated, so it also covers buttons added
       later without needing to be rewired. */
    document.addEventListener("click", function (e) {
      var a = e.target && e.target.closest ? e.target.closest('a[href*="/assets/docs/"]') : null;
      if (!a) return;
      var href = a.getAttribute("href") || "";
      track("file_download", {
        file_name: href.split("/").pop().split("?")[0],
        language: lang
      });
    });

    var form = document.querySelector("form.apply-form");
    if (!form) return;

    /* apply_start = someone actually began filling it in, which is a more
       useful denominator than "loaded the page". Fires once. */
    var started = false;
    form.addEventListener("focusin", function (e) {
      if (started) return;
      var t = e.target;
      if (!t || !/^(INPUT|SELECT|TEXTAREA)$/.test(t.tagName)) return;
      if (t.type === "hidden") return; // the locale field, which carries no intent
      started = true;
      track("apply_start", { language: lang });
    });

    /* apply_step = which of the multi-step panels was reached. apply-form.js
       toggles .is-active on the .form-step items; watching that class is how we
       stay decoupled from its internals. Each step reports once. */
    var stepsList = form.querySelector(".form-steps");
    if (stepsList) {
      var seenSteps = {};
      var reportStep = function () {
        var items = stepsList.querySelectorAll(".form-step");
        for (var i = 0; i < items.length; i++) {
          if (!items[i].classList.contains("is-active")) continue;
          if (seenSteps[i]) return;
          seenSteps[i] = true;
          var label = items[i].querySelector(".form-step-label");
          track("apply_step", {
            step_number: i + 1,
            step_name: label ? label.textContent.trim() : "step " + (i + 1),
            language: lang
          });
          return;
        }
      };
      reportStep();
      new MutationObserver(reportStep).observe(stepsList, {
        subtree: true, attributes: true, attributeFilter: ["class"]
      });
    }

    /* apply_submit = the success panel that apply-form.js swaps in once the
       server has accepted the submission. Watching for it means we only count
       real successes, never a click on a button that then failed validation. */
    var submitted = false;
    new MutationObserver(function () {
      if (submitted || !form.querySelector(".apply-success")) return;
      submitted = true;
      track("apply_submit", { language: lang });
    }).observe(form, { childList: true, subtree: true });
  }

  function start() {
    addFooterLink();
    wireEvents();
    var choice = stored();
    if (choice === "granted") loadGoogle();
    else if (choice !== "denied") showBanner();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start);
  } else {
    start();
  }
})();
