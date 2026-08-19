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
  var FILE_MAX_BYTES = 10 * 1024 * 1024; /* 10 MB — must match api/config max_file_mb */

  /* ---- UI strings (EN / FR, keyed off <html lang>, same pattern as the
     contact handler in main.js). The 1000-char counter is NOT translated:
     the live FR form shows it in English. ---- */
  var IS_FR = (document.documentElement.lang || "").toLowerCase().indexOf("fr") === 0;
  var T = IS_FR ? {
    fileType: "Ce type de fichier n'est pas autorisé. Types de fichier acceptés : doc, docx, xls, xlsx, csv, pdf.",
    fileSize: "Ce fichier dépasse la taille maximale de 10 MB.",
    uploadFile: "Veuillez téléverser un fichier.",
    selectOption: "Veuillez sélectionner une option.",
    required: "Ce champ est obligatoire.",
    invalidEmail: "Veuillez entrer une adresse courriel valide.",
    invalidNumber: "Veuillez entrer un nombre valide.",
    checkField: "Veuillez vérifier ce champ.",
    chooseOption: "Veuillez choisir une option.",
    submitting: "Envoi de votre demande en cours…",
    successTitle: "Demande reçue !",
    successBody: "Merci — votre soumission a bien été reçue",
    successRef: "référence",
    successOutro: ". Nous vous contacterons par courriel.",
    uploadFailed: "Le téléversement a échoué — veuillez réessayer.",
    errFields: "Veuillez vérifier les champs surlignés.",
    errNotOpen: "Les soumissions ne sont pas encore ouvertes.",
    errTooLarge: "La soumission est trop volumineuse. Chaque fichier doit être de 10 MB ou moins.",
    errServer: "Votre demande n'a pas pu être envoyée. Veuillez réessayer plus tard.",
    errNetwork: "Votre demande n'a pas pu être envoyée. Veuillez vérifier votre connexion et réessayer."
  } : {
    fileType: "This type of file is not allowed. Accepted file types: doc, docx, xls, xlsx, csv, pdf.",
    fileSize: "This file exceeds the maximum size of 10 MB.",
    uploadFile: "Please upload a file.",
    selectOption: "Please select an option.",
    required: "This field is required.",
    invalidEmail: "Please enter a valid email address.",
    invalidNumber: "Please enter a valid number.",
    checkField: "Please check this field.",
    chooseOption: "Please choose an option.",
    submitting: "Submitting your application…",
    successTitle: "Application received!",
    successBody: "Thank you — your submission has been received",
    successRef: "reference",
    successOutro: ". We will be in touch by email.",
    uploadFailed: "Upload failed — please retry.",
    errFields: "Please check the highlighted fields.",
    errNotOpen: "Submissions are not open yet.",
    errTooLarge: "The submission is too large. Each file must be 10 MB or less.",
    errServer: "Your application could not be submitted. Please try again later.",
    errNetwork: "Your application could not be submitted. Please check your connection and try again."
  };

  /* ---- api/apply.php answers in English only: map its known error strings
     onto the T table so they localize on /fr/. Unknown strings pass through
     verbatim (better an English message than none). ---- */
  var SERVER_FIELD_ERRORS = {
    "Required": T.required,
    "Invalid email": T.invalidEmail,
    "Invalid category": T.selectOption,
    "File type not accepted": T.fileType,
    "Upload failed — please retry": T.uploadFailed
  };
  var SERVER_ERRORS = {
    "Please check the highlighted fields.": T.errFields,
    "Submissions are not open yet.": T.errNotOpen,
    "The submission is too large. Each file must be 10 MB or less.": T.errTooLarge,
    "Could not store the submission. Please try again later.": T.errServer
  };
  function localizeFieldError(message) {
    message = String(message);
    if (Object.prototype.hasOwnProperty.call(SERVER_FIELD_ERRORS, message)) {
      return SERVER_FIELD_ERRORS[message];
    }
    if (message.indexOf("File is larger than") === 0) return T.fileSize;
    return message;
  }
  function localizeServerError(message) {
    message = String(message);
    return Object.prototype.hasOwnProperty.call(SERVER_ERRORS, message)
      ? SERVER_ERRORS[message] : message;
  }

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
      setError(input, T.fileType);
      return false;
    }
    if (file.size > FILE_MAX_BYTES) {
      input.value = "";
      setError(input, T.fileSize);
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
    if (field.type === "file") return T.uploadFile;
    if (v.valueMissing) {
      if (field.tagName === "SELECT") return T.selectOption;
      return T.required;
    }
    if (v.typeMismatch && field.type === "email") return T.invalidEmail;
    if (v.badInput || v.rangeUnderflow || v.stepMismatch) return T.invalidNumber;
    return T.checkField;
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
          setError(field, T.chooseOption);
          invalid.push(field);
        }
        return;
      }

      if (field.type === "file") {
        if (!validateFileField(field)) {
          invalid.push(field);
        } else if (field.required && !(field.files && field.files.length)) {
          setError(field, T.uploadFile);
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
    statusEl.textContent = T.submitting;

    fetch("/api/apply.php", { method: "POST", body: new FormData(form) })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok && j.ok, j: j }; }); })
      .then(function (res) {
        if (res.ok) {
          form.innerHTML =
            '<div class="apply-success"><h2>' + T.successTitle + "</h2>" +
            "<p>" + T.successBody +
            (res.j.id ? " (" + T.successRef + " <strong>" + res.j.id + "</strong>)" : "") +
            T.successOutro + "</p></div>";
          form.scrollIntoView({ behavior: "smooth", block: "center" });
        } else {
          /* Surface the server's per-field errors on the matching inputs and
             jump back to the first step that has one. */
          var firstErrStep = -1;
          if (res.j && res.j.fields) {
            Object.keys(res.j.fields).forEach(function (name) {
              var el = form.querySelector('[name="' + name + '"]');
              if (!el) return;
              setError(el, localizeFieldError(res.j.fields[name]));
              panels.forEach(function (p, i) {
                if (p.contains(el) && (firstErrStep === -1 || i < firstErrStep)) firstErrStep = i;
              });
            });
          }
          if (firstErrStep !== -1) showStep(firstErrStep, true);
          statusEl.textContent = (res.j && res.j.error)
            ? localizeServerError(res.j.error) : T.errServer;
          statusEl.classList.add("is-error");
          submitBtn.disabled = false;
        }
      })
      .catch(function () {
        statusEl.textContent = T.errNetwork;
        statusEl.classList.add("is-error");
        submitBtn.disabled = false;
      });
  });

  showStep(0, false);
})();
