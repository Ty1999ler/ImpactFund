/* ============================================================
   Apply-now — multi-step application form.

   Steps + client-side validation here; on submit the form is
   POSTed as multipart FormData (fields + up to 5 files) to
   /api/apply.php, which archives the submission on the server
   and delivers it per api/config.php (email relay or SharePoint
   via Microsoft Graph — see api/config.example.php). The server
   answers JSON {ok, error, fields} with per-field error strings,
   and 503 "not configured" until api/config.php exists on the
   server.
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
     contact handler in main.js). The 1000-char counter is localized too
     (client request, September sheet). ---- */
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
    selectSchool: "Veuillez sélectionner votre école dans la liste.",
    chooseOption: "Veuillez choisir une option.",
    submitting: "Envoi de votre demande en cours…",
    successTitle: "Demande reçue !",
    successBody: "Merci — votre candidature a bien été reçue",
    successRef: "référence",
    successOutro: ". Nous vous contacterons par courriel.",
    uploadFailed: "Le téléversement a échoué — veuillez réessayer.",
    errFields: "Veuillez vérifier les champs surlignés.",
    errNotOpen: "Les soumissions ne sont pas encore ouvertes.",
    errTooLarge: "La soumission est trop volumineuse. Chaque fichier doit être de 10 MB ou moins.",
    errServer: "Votre demande n'a pas pu être envoyée. Veuillez réessayer plus tard.",
    errNetwork: "Votre demande n'a pas pu être envoyée. Veuillez vérifier votre connexion et réessayer.",
    charCounter: " de 1000 caractères maximum"
  } : {
    fileType: "This type of file is not allowed. Accepted file types: doc, docx, xls, xlsx, csv, pdf.",
    fileSize: "This file exceeds the maximum size of 10 MB.",
    uploadFile: "Please upload a file.",
    selectOption: "Please select an option.",
    required: "This field is required.",
    invalidEmail: "Please enter a valid email address.",
    invalidNumber: "Please enter a valid number.",
    checkField: "Please check this field.",
    selectSchool: "Please select your school from the list.",
    chooseOption: "Please choose an option.",
    submitting: "Submitting your application…",
    successTitle: "Application received!",
    successBody: "Thank you — your application has been received",
    successRef: "reference",
    successOutro: ". We will be in touch by email.",
    uploadFailed: "Upload failed — please retry.",
    errFields: "Please check the highlighted fields.",
    errNotOpen: "Submissions are not open yet.",
    errTooLarge: "The submission is too large. Each file must be 10 MB or less.",
    errServer: "Your application could not be submitted. Please try again later.",
    errNetwork: "Your application could not be submitted. Please check your connection and try again.",
    charCounter: " of 1000 max characters"
  };

  /* ---- api/apply.php answers in English only: map its known error strings
     onto the T table so they localize on /fr/. Unknown strings pass through
     verbatim (better an English message than none). ---- */
  var SERVER_FIELD_ERRORS = {
    "Required": T.required,
    "Invalid email": T.invalidEmail,
    "Invalid category": T.selectOption,
    "Invalid number": T.invalidNumber,
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

  /* ---- Institution / Association combobox from window.ALUMO_SCHOOLS ----
     A text input (#af-institution) with a dropdown panel of options grouped
     by province. Option label: "School - Association" (just the school when
     the association is "N/A"). Submitted value must exactly match a label.
     Both /apply-now/ and /fr/soumettre/ ship the combobox markup; if the
     field is ever the legacy <select> again, fall back to populating it
     with the same labels. */
  var institutionCombo = (function () {
    var field = form.querySelector("#af-institution");
    var rows = window.ALUMO_SCHOOLS;
    if (!field || !Array.isArray(rows)) return null;

    /* Group headings shown in the dropdown, localized like T above. The codes
       must match the #af-province option values (identical on /apply-now/ and
       /fr/soumettre/); the order mirrors both province selects. */
    var PROVINCES = IS_FR ? [
      ["AB", "Alberta"], ["BC", "Colombie-Britannique"], ["MB", "Manitoba"],
      ["NB", "Nouveau-Brunswick"], ["NS", "Nouvelle-Écosse"], ["ON", "Ontario"],
      ["QC", "Québec"], ["SK", "Saskatchewan"]
    ] : [
      ["AB", "Alberta"], ["BC", "British Columbia"], ["MB", "Manitoba"],
      ["NB", "New Brunswick"], ["NS", "Nova Scotia"], ["ON", "Ontario"],
      ["QC", "Quebec"], ["SK", "Saskatchewan"]
    ];

    function fold(text) {
      return String(text || "")
        .toLowerCase()
        .normalize("NFD")
        .replace(/[̀-ͯ]/g, "");
    }
    function rowLabel(row) {
      var association = String(row.association || "").trim();
      var school = String(row.school || "").trim();
      if (!association || /^n\/?a$/i.test(association)) return school;
      return school + " - " + association;
    }

    /* [{ name, options: [label…] }] in client-specified province order */
    var groups = [];
    var labels = {};
    PROVINCES.forEach(function (province) {
      var options = [];
      var seen = {};
      rows.forEach(function (row) {
        if (row.province !== province[0]) return;
        var label = rowLabel(row);
        if (!label || seen[label.toLowerCase()]) return;
        seen[label.toLowerCase()] = true;
        options.push(label);
        labels[label] = true;
      });
      options.sort(function (a, b) { return a.localeCompare(b); });
      if (options.length) groups.push({ name: province[1], options: options });
    });

    /* ---- Province filter ----
       #af-province is chosen first and narrows this list to that province.
       With no province picked we show everything, so the field still works
       if the select is ever removed. Changing province clears an institution
       that no longer belongs to it. */
    var provinceField = form.querySelector("#af-province");
    function selectedProvince() {
      return provinceField ? provinceField.value : "";
    }
    function groupsForProvince() {
      var code = selectedProvince();
      if (!code) return groups;
      var name = null;
      PROVINCES.forEach(function (p) { if (p[0] === code) name = p[1]; });
      return groups.filter(function (g) { return g.name === name; });
    }
    function labelInCurrentProvince(label) {
      return groupsForProvince().some(function (g) {
        return g.options.indexOf(label) !== -1;
      });
    }

    if (field.tagName === "SELECT") {
      /* Legacy select (no longer shipped, kept for safety): repopulate on
         every province change. Never runs when #af-institution is the
         combobox <input>. */
      var placeholder = field.querySelector("option[value='']");
      var fillSelect = function () {
        field.innerHTML = "";
        if (placeholder) field.appendChild(placeholder);
        groupsForProvince().forEach(function (group) {
          group.options.forEach(function (label) {
            var option = document.createElement("option");
            option.value = label;
            option.textContent = label;
            field.appendChild(option);
          });
        });
      };
      fillSelect();
      if (provinceField) {
        provinceField.addEventListener("change", function () {
          fillSelect();
          if (typeof refreshSelectTint === "function") refreshSelectTint(field);
        });
      }
      return null;
    }

    var panel = document.getElementById("af-institution-listbox");
    if (!panel) return null;

    var visibleOptions = []; /* option elements currently in the panel */
    var activeIndex = -1;

    function setActive(index) {
      if (activeIndex >= 0 && visibleOptions[activeIndex]) {
        visibleOptions[activeIndex].classList.remove("is-active");
        visibleOptions[activeIndex].setAttribute("aria-selected", "false");
      }
      activeIndex = index;
      if (index >= 0 && visibleOptions[index]) {
        var el = visibleOptions[index];
        el.classList.add("is-active");
        el.setAttribute("aria-selected", "true");
        field.setAttribute("aria-activedescendant", el.id);
        if (el.scrollIntoView) el.scrollIntoView({ block: "nearest" });
      } else {
        field.removeAttribute("aria-activedescendant");
      }
    }

    function close() {
      panel.hidden = true;
      field.setAttribute("aria-expanded", "false");
      setActive(-1);
    }

    function choose(label) {
      field.value = label;
      clearError(field);
      close();
    }

    /* Rebuild the panel for the current query; empty query shows everything */
    function open() {
      var query = fold(field.value.trim());
      /* A query identical to an already-chosen label shows everything again */
      if (labels[field.value.trim()]) query = "";
      panel.innerHTML = "";
      visibleOptions = [];
      activeIndex = -1;
      var counter = 0;
      groupsForProvince().forEach(function (group) {
        var matched = group.options.filter(function (label) {
          return !query || fold(label).indexOf(query) !== -1;
        });
        if (!matched.length) return;
        var heading = document.createElement("div");
        heading.className = "combo-group-label";
        heading.setAttribute("role", "presentation");
        heading.textContent = group.name;
        panel.appendChild(heading);
        matched.forEach(function (label) {
          var option = document.createElement("div");
          option.className = "combo-option";
          option.setAttribute("role", "option");
          option.setAttribute("aria-selected", "false");
          option.id = "af-institution-option-" + (counter++);
          option.textContent = label;
          /* mousedown fires before the input's blur */
          option.addEventListener("mousedown", function (e) {
            e.preventDefault();
            choose(label);
          });
          panel.appendChild(option);
          visibleOptions.push(option);
        });
      });
      var hasOptions = visibleOptions.length > 0;
      panel.hidden = !hasOptions;
      field.setAttribute("aria-expanded", hasOptions ? "true" : "false");
    }

    /* Switching province drops a school that belongs to the old one, so the
       submitted value can never disagree with the submitted province. */
    if (provinceField) {
      provinceField.addEventListener("change", function () {
        var current = field.value.trim();
        if (current && !labelInCurrentProvince(current)) {
          field.value = "";
          clearError(field);
        }
        if (document.activeElement === field) open();
      });
    }

    field.addEventListener("focus", open);
    field.addEventListener("input", open);
    field.addEventListener("keydown", function (e) {
      if (e.key === "Escape") {
        close();
        return;
      }
      if (e.key === "ArrowDown" || e.key === "ArrowUp") {
        e.preventDefault();
        if (panel.hidden) open();
        if (!visibleOptions.length) return;
        var next = e.key === "ArrowDown"
          ? (activeIndex + 1) % visibleOptions.length
          : (activeIndex <= 0 ? visibleOptions.length - 1 : activeIndex - 1);
        setActive(next);
        return;
      }
      if (e.key === "Enter" && !panel.hidden && activeIndex >= 0) {
        e.preventDefault(); /* select instead of submitting/advancing */
        choose(visibleOptions[activeIndex].textContent);
      }
    });
    document.addEventListener("click", function (e) {
      if (!panel.hidden && !field.contains(e.target) && !panel.contains(e.target)) close();
    });

    return {
      field: field,
      isValid: function () {
        var value = field.value.trim();
        if (!Object.prototype.hasOwnProperty.call(labels, value)) return false;
        /* Must also belong to the province that was selected. */
        return labelInCurrentProvince(value);
      }
    };
  })();

  /* ---- Category "Other": reveal + require the specify field ---- */
  var categorySelect = form.querySelector("#af-category");
  var categoryOtherWrap = form.querySelector("#af-category-other-wrap");
  var categoryOtherInput = form.querySelector("#af-category-other");
  if (categorySelect && categoryOtherWrap && categoryOtherInput) {
    var syncCategoryOther = function () {
      var isOther = categorySelect.value === "Other";
      categoryOtherWrap.hidden = !isOther;
      categoryOtherInput.required = isOther;
      if (!isOther) { categoryOtherInput.value = ""; clearError(categoryOtherInput); }
    };
    categorySelect.addEventListener("change", syncCategoryOther);
    syncCategoryOther();
  }

  /* ---- Select placeholder tint ---- */
  function refreshSelectTint(select) {
    select.classList.toggle("is-placeholder", select.value === "");
  }
  Array.prototype.forEach.call(form.querySelectorAll("select"), function (select) {
    refreshSelectTint(select);
    select.addEventListener("change", function () { refreshSelectTint(select); });
  });

  /* ---- Project summary character counter (1000 max), localized via T ---- */
  var summaryField = form.querySelector("#af-summary");
  var summaryCount = form.querySelector("#af-summary-count");
  if (summaryField && summaryCount) {
    var updateCount = function () {
      if (summaryField.value.length > 1000) {
        summaryField.value = summaryField.value.slice(0, 1000);
      }
      summaryCount.textContent = summaryField.value.length + T.charCounter;
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
        return;
      }

      /* Institution combobox: the value must exactly match a list label */
      if (institutionCombo && field === institutionCombo.field && !institutionCombo.isValid()) {
        setError(field, T.selectSchool);
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
    /* Enter in a field on an earlier step acts like the next-step button
       ("Move forward" / "Suivant") */
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
          /* The submission id is deliberately NOT shown: it is an internal
             tracking reference, and quoting it invites applicants to use it
             as a case number. The id is still returned by the API and stored
             with the archive and the SharePoint item. */
          form.innerHTML =
            '<div class="apply-success"><h2>' + T.successTitle + "</h2>" +
            "<p>" + T.successBody + T.successOutro + "</p></div>";
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
          /* showStep() hides the status banner — re-show it so the summary
             message is visible (and announced via aria-live). */
          statusEl.hidden = false;
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
