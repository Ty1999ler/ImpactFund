/* Student Impact Fund by Alumo — partner schools list
   Used by /partner-schools/ and /fr/partner-schools/.

   Recreates the live hidden UI: a search box, a grid of province cards
   (default view), and — after picking a province or typing a search — a
   breadcrumb ("← All provinces · <Province>") above the schools table.
   Table format: two columns with visible headers — "School - Association"
   combined (school alone when the association is "N/A") | contact email.
   A–Z/Z–A sorts the cards in grid view and the rows in table view.

   DATA lives in /js/schools-data.js (window.ALUMO_SCHOOLS — the single source
   of truth, also used by the application form). To update the list, edit that
   file only; this script just renders it. Each row: { school, association,
   province (two-letter code), email }. Load order matters: schools-data.js
   must be included before this file. */

(function () {
  "use strict";

  var table = document.getElementById("schools-table");
  var searchInput = document.getElementById("school-search");
  var sortGrid = document.getElementById("school-sort");
  var sortTable = document.getElementById("school-sort-table");
  var provinceListWrap = document.querySelector(".province-list-wrap");
  var schoolList = document.querySelector(".partner-school-list");
  var provinceGrid = document.querySelector(".province-grid");
  var backButton = document.querySelector(".back-to-provinces");
  var currentProvinceEl = document.querySelector(".current-province");
  var schools = window.ALUMO_SCHOOLS;
  if (!table || !sortTable || !provinceListWrap || !schoolList || !provinceGrid || !schools) return;

  var tbody = table.querySelector("tbody");
  var tableWrap = document.querySelector(".table-data-list-loader-wrap");
  var noResult = document.querySelector(".no-result-found");
  var cards = Array.prototype.slice.call(provinceGrid.querySelectorAll(".province-card"));

  /* Card data-province slugs → the two-letter codes used in schools-data.js */
  var SLUG_TO_CODE = {
    "alberta": "AB",
    "british-columbia": "BC",
    "manitoba": "MB",
    "new-brunswick": "NB",
    "newfoundland-and-labrador": "NL",
    "northwest-territories": "NT",
    "nova-scotia": "NS",
    "nunavut": "NU",
    "ontario": "ON",
    "prince-edward-island": "PE",
    "quebec": "QC",
    "saskatchewan": "SK",
    "yukon": "YT"
  };

  var currentProvince = ""; /* slug of the selected province card, "" = grid view */

  /* Accent-insensitive, case-insensitive matching (École = ecole) */
  function fold(text) {
    return String(text || "")
      .toLowerCase()
      .normalize("NFD")
      .replace(/[̀-ͯ]/g, "");
  }

  function searchQuery() {
    return searchInput ? searchInput.value.trim() : "";
  }

  /* Localized display name comes from the card markup (FR names on /fr/) */
  function provinceLabel(slug) {
    for (var i = 0; i < cards.length; i++) {
      if (cards[i].getAttribute("data-province") === slug) {
        var name = cards[i].querySelector(".province-name");
        return name ? name.textContent.trim() : "";
      }
    }
    return "";
  }

  /* "School - Association"; just the school when association is "N/A" */
  function rowLabel(row) {
    var association = String(row.association || "").trim();
    if (!association || /^n\/?a$/i.test(association)) return row.school;
    return row.school + " - " + association;
  }

  function emailCell(email) {
    var td = document.createElement("td");
    td.className = "email-col";
    if (email && email.indexOf("@") !== -1) {
      var link = document.createElement("a");
      link.href = "mailto:" + email;
      link.textContent = email;
      td.appendChild(link);
    } else {
      /* "TBD" and malformed values (no @) stay plain text — shown verbatim,
         never turned into a link. */
      td.textContent = email || "";
    }
    return td;
  }

  /* Fill the table for the current province + search; returns the row count */
  function renderRows() {
    var code = currentProvince ? SLUG_TO_CODE[currentProvince] : "";
    var query = fold(searchQuery());
    var order = sortTable.value;

    var rows = schools.filter(function (school) {
      if (code && school.province !== code) return false;
      if (!query) return true;
      return fold(school.school).indexOf(query) !== -1 ||
             fold(school.association).indexOf(query) !== -1 ||
             fold(school.email).indexOf(query) !== -1;
    });
    rows = rows.slice().sort(function (a, b) {
      return order === "desc"
        ? b.school.localeCompare(a.school)
        : a.school.localeCompare(b.school);
    });

    tbody.innerHTML = "";
    rows.forEach(function (school) {
      var tr = document.createElement("tr");
      var nameTd = document.createElement("td");
      nameTd.className = "name-col";
      nameTd.textContent = rowLabel(school);
      tr.appendChild(nameTd);
      tr.appendChild(emailCell(school.email));
      tbody.appendChild(tr);
    });
    return rows.length;
  }

  /* A–Z / Z–A over the province cards (grid view), like the live sortProvinces */
  function sortProvinceCards() {
    var order = sortGrid ? sortGrid.value : "asc";
    var sorted = cards.slice().sort(function (a, b) {
      var nameA = a.querySelector(".province-name").textContent.trim();
      var nameB = b.querySelector(".province-name").textContent.trim();
      return order === "desc" ? nameB.localeCompare(nameA) : nameA.localeCompare(nameB);
    });
    sorted.forEach(function (card) {
      provinceGrid.appendChild(card);
    });
  }

  function update() {
    if (searchQuery() || currentProvince) {
      /* Table view (selected province and/or active search) */
      provinceListWrap.style.display = "none";
      schoolList.style.display = "";
      if (currentProvinceEl) {
        /* "Global Search Results" is the live site's wording for a search
           with no province selected (untranslated there too). */
        currentProvinceEl.textContent = currentProvince
          ? provinceLabel(currentProvince)
          : "Global Search Results";
      }
      var count = renderRows();
      var hasRows = count > 0;
      if (tableWrap) tableWrap.style.display = hasRows ? "" : "none";
      if (noResult) noResult.style.display = hasRows ? "none" : "";
    } else {
      /* Grid view */
      schoolList.style.display = "none";
      provinceListWrap.style.display = "";
      if (noResult) noResult.style.display = "none";
      if (currentProvinceEl) currentProvinceEl.textContent = "";
      sortProvinceCards();
    }
  }

  cards.forEach(function (card) {
    card.addEventListener("click", function () {
      if (searchInput) searchInput.value = ""; /* live clears search on card click */
      currentProvince = card.getAttribute("data-province");
      update();
    });
  });

  if (backButton) {
    backButton.addEventListener("click", function () {
      currentProvince = "";
      if (searchInput) searchInput.value = "";
      /* live resets both sorts to A–Z when going back */
      if (sortGrid) sortGrid.value = "asc";
      sortTable.value = "asc";
      update();
    });
  }

  function onSortChange(e) {
    /* keep the two dropdowns in sync, like the live site */
    if (sortGrid && e.target !== sortGrid) sortGrid.value = e.target.value;
    if (e.target !== sortTable) sortTable.value = e.target.value;
    update();
  }
  if (sortGrid) sortGrid.addEventListener("change", onSortChange);
  sortTable.addEventListener("change", onSortChange);

  if (searchInput) {
    searchInput.addEventListener("input", update);
    searchInput.addEventListener("search", update); /* clear (x) button */
  }

  update();
})();
