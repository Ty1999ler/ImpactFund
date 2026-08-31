/* ============================================================
   Google Analytics 4 + consent banner.

   NOT APPROVED BY THE CLIENT YET (as of 2026-08-29). This file is
   deliberately inert everywhere except the hosts listed in ENABLED_HOSTS,
   so it can sit in the repo without doing anything on alumoimpact.ca.

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

   TO GO LIVE ON PRODUCTION
     1. Paste the Measurement ID below.
     2. Add "alumoimpact.ca" and "www.alumoimpact.ca" to ENABLED_HOSTS.
     3. Restore a cookies-policy page — the banner links to it, and right
        now /cookies-policy/ is a redirect stub (the client had it removed
        back when the site set no cookies at all).
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

  /* Hosts where analytics is offered at all. Production is deliberately
     absent until the client approves. */
  var ENABLED_HOSTS = ["september.alumoimpact.ca", "localhost", "127.0.0.1"];

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

  function start() {
    addFooterLink();
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
