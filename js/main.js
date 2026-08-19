/* Student Impact Fund by Alumo — shared behavior
   - mobile burger menu
   - language dropdown
   - sticky / hide-on-scroll header (matches the live site's headroom behavior)
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

  /* ---- Contact form (backend wired later) ---- */
  document.querySelectorAll('form[data-handler="email"]').forEach(function (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      // Placeholder: email backend is wired in a later phase.
    });
  });

  /* ---- Scheduled content reveal (application form) ----
     A section with data-opens-at="<ISO date>" shows its .apply-form-closed
     card before that moment and its .apply-form-open card from then on.
     Append ?preview-form=1 to the URL to preview the open state early. */
  document.querySelectorAll("[data-opens-at]").forEach(function (section) {
    var opensAt = new Date(section.getAttribute("data-opens-at"));
    var closed = section.querySelector(".apply-form-closed");
    var open = section.querySelector(".apply-form-open");
    if (!closed || !open || isNaN(opensAt)) return;
    var preview = /[?&]preview-form=1/.test(window.location.search);
    if (preview || new Date() >= opensAt) {
      closed.hidden = true;
      open.hidden = false;
    }
  });
})();
