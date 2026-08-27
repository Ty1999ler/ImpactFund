/* Student Impact Fund by Alumo — shared behavior
   - mobile burger menu
   - language dropdown
   - sticky / hide-on-scroll header (matches the live site's headroom behavior)
   - contact form: validates client-side, then POSTs to /api/contact.php,
     which answers JSON {ok, error, fields} (503 until api/config.php exists
     on the server) — per-field errors land inline on the matching inputs
   - scheduled content reveal ([data-opens-at])
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

  /* ---- Scheduled content reveal ----
     An element with data-opens-at="<ISO date>" either:
     - swaps its .apply-form-closed card for its .apply-form-open card from
       that moment on (when it contains both), or
     - simply un-hides itself from that moment on (it carries the `hidden`
       attribute in the markup until then).
     Append ?preview-form=1 to the URL to preview the open state early. */
  document.querySelectorAll("[data-opens-at]").forEach(function (section) {
    var opensAt = new Date(section.getAttribute("data-opens-at"));
    if (isNaN(opensAt)) return;
    var preview = /[?&]preview-form=1/.test(window.location.search);
    var isOpen = preview || new Date() >= opensAt;
    if (!isOpen) return;
    var closed = section.querySelector(".apply-form-closed");
    var open = section.querySelector(".apply-form-open");
    if (closed && open) {
      closed.hidden = true;
      open.hidden = false;
    } else {
      section.hidden = false;
    }
  });
})();
