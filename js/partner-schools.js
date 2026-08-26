/* Student Impact Fund by Alumo — partner schools list
   Used by /partner-schools/ and /fr/partner-schools/.

   Renders a single always-visible table of ALL partners, grouped by
   province (full-width province header row before each group), with the
   pill search box live-filtering the rows (accent-insensitive).

   DATA lives in /js/schools-data.js (window.ALUMO_SCHOOLS — the single source
   of truth, also used by the application form). To update the list, edit that
   file only; this script just renders it. Each row: { school, association,
   province (two-letter code), email }. Load order matters: schools-data.js
   must be included before this file. */

(function () {
  "use strict";

  var table = document.getElementById("schools-table");
  var searchInput = document.getElementById("school-search");
  var schools = window.ALUMO_SCHOOLS;
  if (!table || !schools) return;

  var tbody = table.querySelector("tbody");
  var tableWrap = document.querySelector(".table-data-list-loader-wrap");
  var noResult = document.querySelector(".no-result-found");

  var IS_FR = (document.documentElement.lang || "").toLowerCase().indexOf("fr") === 0;

  /* Client-specified province order; names localized per page language. */
  var PROVINCES = [
    { code: "AB", en: "Alberta",          fr: "Alberta" },
    { code: "BC", en: "British Columbia", fr: "Colombie-Britannique" },
    { code: "MB", en: "Manitoba",         fr: "Manitoba" },
    { code: "NB", en: "New Brunswick",    fr: "Nouveau-Brunswick" },
    { code: "NS", en: "Nova Scotia",      fr: "Nouvelle-Écosse" },
    { code: "ON", en: "Ontario",          fr: "Ontario" },
    { code: "QC", en: "Quebec",           fr: "Québec" },
    { code: "SK", en: "Saskatchewan",     fr: "Saskatchewan" }
  ];

  /* Accent-insensitive, case-insensitive matching (École = ecole) */
  function fold(text) {
    return String(text || "")
      .toLowerCase()
      .normalize("NFD")
      .replace(/[̀-ͯ]/g, "");
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
      /* "Contact Email Coming Soon" and malformed values (no @) stay plain
         text — shown verbatim, never turned into a link. */
      td.textContent = email || "";
    }
    return td;
  }

  function matches(row, query) {
    if (!query) return true;
    return fold(row.school).indexOf(query) !== -1 ||
           fold(row.association).indexOf(query) !== -1 ||
           fold(row.email).indexOf(query) !== -1;
  }

  /* Rebuild the grouped table for the current search; returns the row count.
     Province groups with no matching rows are omitted entirely. */
  function render() {
    var query = fold(searchInput ? searchInput.value.trim() : "");
    var total = 0;
    tbody.innerHTML = "";

    PROVINCES.forEach(function (province) {
      var rows = schools.filter(function (row) {
        return row.province === province.code && matches(row, query);
      });
      if (!rows.length) return;
      rows.sort(function (a, b) { return a.school.localeCompare(b.school); });

      var headerTr = document.createElement("tr");
      headerTr.className = "province-row";
      var th = document.createElement("th");
      th.colSpan = 2;
      th.scope = "colgroup";
      th.textContent = IS_FR ? province.fr : province.en;
      headerTr.appendChild(th);
      tbody.appendChild(headerTr);

      rows.forEach(function (row) {
        var tr = document.createElement("tr");
        var nameTd = document.createElement("td");
        nameTd.className = "name-col";
        nameTd.textContent = rowLabel(row);
        tr.appendChild(nameTd);
        tr.appendChild(emailCell(row.email));
        tbody.appendChild(tr);
      });
      total += rows.length;
    });

    var hasRows = total > 0;
    if (tableWrap) tableWrap.style.display = hasRows ? "" : "none";
    if (noResult) noResult.style.display = hasRows ? "none" : "";
  }

  if (searchInput) {
    searchInput.addEventListener("input", render);
    searchInput.addEventListener("search", render); /* clear (x) button */
  }

  render();
})();
