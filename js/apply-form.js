/* ============================================================
   Apply-now — multi-step application form (front-end only)

   TODO (backend): submissions are NOT wired yet. Per PLANS.md,
   the backend is the self-hosted forms-api container: replace
   the stub inside the submit handler below with
   fetch("/api/apply", { method: "POST", body: new FormData(form) })
   once that endpoint exists (multipart: fields + 5 files
   -> local backup + SharePoint via Graph or email relay).
   ============================================================ */
(function () {
  "use strict";

  var form = document.querySelector('.apply-form[data-handler="sharepoint"]');
  if (!form) return;

  var panels = Array.prototype.slice.call(form.querySelectorAll(".form-step-panel"));
  var stepItems = Array.prototype.slice.call(form.querySelectorAll(".form-steps .form-step"));
  var stepButtons = Array.prototype.slice.call(form.querySelectorAll(".form-step-btn"));
  var prevBtn = form.querySelector('[data-nav="prev"]');
  var nextBtn = form.querySelector('[data-nav="next"]');
  var submitBtn = form.querySelector(".apply-btn-submit");
  var statusEl = form.querySelector(".form-status");
  var honeypot = form.querySelector(".hp-field input");
  var current = 0;

  var FILE_TYPES = [".doc", ".docx", ".xls", ".xlsx", ".csv", ".pdf"];
  var FILE_MAX_BYTES = 128 * 1024 * 1024; /* 128 MB */

  /* ---- Institution / Association options from window.ALUMO_SCHOOLS ----
     Option text: the association, unless it is "N/A"/"NA" — then the
     school name. De-duplicated, source order preserved. */
  (function populateInstitutions() {
    var select = form.querySelector("#af-institution");
    var rows = window.ALUMO_SCHOOLS;
    if (!select || !Array.isArray(rows)) return;
    var seen = {};
    rows.forEach(function (row) {
      var association = String(row.association || "").trim();
      var school = String(row.school || "").trim();
      var text = (!association || /^n\/?a$/i.test(association)) ? school : association;
      if (!text || seen[text.toLowerCase()]) return;
      seen[text.toLowerCase()] = true;
      var option = document.createElement("option");
      option.value = text;
      option.textContent = text;
      select.appendChild(option);
    });
  })();

  /* ---- Select placeholder tint ---- */
  function refreshSelectTint(select) {
    select.classList.toggle("is-placeholder", select.value === "");
  }
  Array.prototype.forEach.call(form.querySelectorAll("select"), function (select) {
    refreshSelectTint(select);
    select.addEventListener("change", function () { refreshSelectTint(select); });
  });

  /* ---- Project summary character counter (1000 max) ---- */
  var summaryField = form.querySelector("#af-summary");
  var summaryCount = form.querySelector("#af-summary-count");
  if (summaryField && summaryCount) {
    var updateCount = function () {
      if (summaryField.value.length > 1000) {
        summaryField.value = summaryField.value.slice(0, 1000);
      }
      summaryCount.textContent = summaryField.value.length + " of 1000 max characters";
    };
    summaryField.addEventListener("input", updateCount);
    updateCount();
  }

  /* ---- Inline field errors ---- */
  function fieldWrap(field) {
    return field.closest(".form-field") || field.parentElement;
  }

  function setError(field, message) {
    var wrap = fieldWrap(field);
    wrap.classList.add("has-error");
    field.setAttribute("aria-invalid", "true");
    var note = wrap.querySelector(".field-error");
    if (!note) {
      note = document.createElement("p");
      note.className = "field-error";
      wrap.appendChild(note);
    }
    note.textContent = message;
  }

  function clearError(field) {
    var wrap = fieldWrap(field);
    if (!wrap) return;
    wrap.classList.remove("has-error");
    field.removeAttribute("aria-invalid");
    var note = wrap.querySelector(".field-error");
    if (note) note.parentNode.removeChild(note);
  }

  /* ---- File rules: extension + size (native input shows the filename) ---- */
  function validateFileField(input) {
    clearError(input);
    var file = input.files && input.files[0];
    if (!file) return true;
    var name = file.name.toLowerCase();
    var allowed = FILE_TYPES.some(function (ext) { return name.endsWith(ext); });
    if (!allowed) {
      input.value = "";
      setError(input, "This type of file is not allowed. Accepted file types: doc, docx, xls, xlsx, csv, pdf.");
      return false;
    }
    if (file.size > FILE_MAX_BYTES) {
      input.value = "";
      setError(input, "This file exceeds the maximum size of 128 MB.");
      return false;
    }
    return true;
  }

  /* Clear a field's error as soon as the user edits it */
  form.addEventListener("input", function (e) {
    if (e.target.matches("input, select, textarea")) clearError(e.target);
  });
  form.addEventListener("change", function (e) {
    if (e.target.type === "file") {
      validateFileField(e.target);
    } else if (e.target.matches("input, select, textarea")) {
      clearError(e.target);
    }
  });

  /* ---- Step validation ---- */
  function messageFor(field) {
    var v = field.validity;
    if (field.type === "file") return "Please upload a file.";
    if (v.valueMissing) {
      if (field.tagName === "SELECT") return "Please select an option.";
      return "This field is required.";
    }
    if (v.typeMismatch && field.type === "email") return "Please enter a valid email address.";
    if (v.badInput || v.rangeUnderflow || v.stepMismatch) return "Please enter a valid number.";
    return "Please check this field.";
  }

  function validateStep(index) {
    var panel = panels[index];
    var fields = panel.querySelectorAll("input, select, textarea");
    var invalid = [];
    var seenRadioGroups = {};

    Array.prototype.forEach.call(fields, function (field) {
      if (field.disabled || field.closest(".hp-field")) return;
      clearError(field);

      if (field.type === "radio") {
        if (seenRadioGroups[field.name]) return;
        seenRadioGroups[field.name] = true;
        var group = panel.querySelectorAll('input[type="radio"][name="' + field.name + '"]');
        var required = Array.prototype.some.call(group, function (r) { return r.required; });
        var checked = Array.prototype.some.call(group, function (r) { return r.checked; });
        if (required && !checked) {
          setError(field, "Please choose an option.");
          invalid.push(field);
        }
        return;
      }

      if (field.type === "file") {
        if (!validateFileField(field)) {
          invalid.push(field);
        } else if (field.required && !(field.files && field.files.length)) {
          setError(field, "Please upload a file.");
          invalid.push(field);
        }
        return;
      }

      if (!field.checkValidity()) {
        setError(field, messageFor(field));
        invalid.push(field);
      }
    });

    if (invalid.length) {
      fieldWrap(invalid[0]).scrollIntoView({ behavior: "smooth", block: "center" });
      try {
        invalid[0].focus({ preventScroll: true });
      } catch (err) {
        invalid[0].focus();
      }
      return false;
    }
    return true;
  }

  /* ---- Step navigation ---- */
  function showStep(index, scrollToTop) {
    current = index;
    panels.forEach(function (panel, i) { panel.hidden = i !== index; });
    stepItems.forEach(function (item, i) {
      item.classList.toggle("is-active", i === index);
      item.classList.toggle("is-complete", i < index);
      if (i === index) {
        item.setAttribute("aria-current", "step");
      } else {
        item.removeAttribute("aria-current");
      }
      /* Completed step labels are clickable (go back), like the live form */
      stepButtons[i].disabled = i >= index;
    });
    prevBtn.hidden = index === 0;
    nextBtn.hidden = index === panels.length - 1;
    submitBtn.hidden = index !== panels.length - 1;
    statusEl.hidden = true;
    if (scrollToTop) form.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  nextBtn.addEventListener("click", function () {
    if (validateStep(current)) showStep(current + 1, true);
  });
  prevBtn.addEventListener("click", function () {
    showStep(current - 1, true); /* going back never validates */
  });
  stepButtons.forEach(function (btn, i) {
    btn.addEventListener("click", function () {
      if (i < current) showStep(i, true);
    });
  });

  /* ---- Submit ---- */
  form.addEventListener("submit", function (e) {
    e.preventDefault();
    /* Enter in a field on an earlier step acts like "Move forward" */
    if (current !== panels.length - 1) {
      if (validateStep(current)) showStep(current + 1, true);
      return;
    }
    if (honeypot && honeypot.value) return; /* spam trap — silently drop */
    if (!validateStep(current)) return;

    /* POST to the PHP handler (see PLANS.md / api/apply.php). */
    submitBtn.disabled = true;
    statusEl.hidden = false;
    statusEl.classList.remove("is-error");
    statusEl.textContent = "Submitting your application…";

    fetch("/api/apply.php", { method: "POST", body: new FormData(form) })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok && j.ok, j: j }; }); })
      .then(function (res) {
        if (res.ok) {
          form.innerHTML =
            '<div class="apply-success"><h2>Application received!</h2>' +
            "<p>Thank you — your submission has been received" +
            (res.j.id ? " (reference <strong>" + res.j.id + "</strong>)" : "") +
            ". We will be in touch by email.</p></div>";
          form.scrollIntoView({ behavior: "smooth", block: "center" });
        } else {
          statusEl.textContent = (res.j && res.j.error) ||
            "Your application could not be submitted. Please try again later.";
          statusEl.classList.add("is-error");
          submitBtn.disabled = false;
        }
      })
      .catch(function () {
        statusEl.textContent = "Your application could not be submitted. Please check your connection and try again.";
        statusEl.classList.add("is-error");
        submitBtn.disabled = false;
      });
  });

  showStep(0, false);
})();
