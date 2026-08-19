/* Student Impact Fund by Alumo — partner schools list
   Used by /partner-schools/ and /fr/partner-schools/.
   Renders the schools table, filters it by province and sorts A–Z / Z–A.

   DATA lives in /js/schools-data.js (window.ALUMO_SCHOOLS — the single source
   of truth, also used by the application form). To update the list, edit that
   file only; this script just renders it. Each row: { school, association,
   province (two-letter code), email }. Load order matters: schools-data.js
   must be included before this file. */

(function () {
  "use strict";

  var table = document.getElementById("schools-table");
  var provinceSelect = document.getElementById("school-province");
  var sortSelect = document.getElementById("school-sort-table");
  var searchInput = document.getElementById("school-search");
  var schools = window.ALUMO_SCHOOLS;
  if (!table || !provinceSelect || !sortSelect || !schools) return;

  /* Accent-insensitive, case-insensitive matching (École = ecole) */
  function fold(text) {
    return String(text || "")
      .toLowerCase()
      .normalize("NFD")
      .replace(/[̀-ͯ]/g, "");
  }

  var tbody = table.querySelector("tbody");
  var tableWrap = document.querySelector(".table-data-list-loader-wrap");
  var noResult = document.querySelector(".no-result-found");

  /* Two-letter province codes (as used in schools-data.js) → the English
     province names used as <option value> on both the EN and FR pages
     (the FR page shows French labels from its own option text). */
  var PROVINCE_NAMES = {
    AB: "Alberta",
    BC: "British Columbia",
    MB: "Manitoba",
    NB: "New Brunswick",
    NL: "Newfoundland and Labrador",
    NT: "Northwest Territories",
    NS: "Nova Scotia",
    NU: "Nunavut",
    ON: "Ontario",
    PE: "Prince Edward Island",
    QC: "Quebec",
    SK: "Saskatchewan",
    YT: "Yukon"
  };

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

  function render() {
    var province = provinceSelect.value;
    var order = sortSelect.value;

    var query = searchInput ? fold(searchInput.value.trim()) : "";
    var rows = schools.filter(function (school) {
      if (province && PROVINCE_NAMES[school.province] !== province) return false;
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
      nameTd.textContent = school.school;
      var associationTd = document.createElement("td");
      associationTd.className = "association-col";
      associationTd.textContent = school.association; /* verbatim, incl. "N/A" */
      tr.appendChild(nameTd);
      tr.appendChild(associationTd);
      tr.appendChild(emailCell(school.email));
      tbody.appendChild(tr);
    });

    var hasRows = rows.length > 0;
    if (tableWrap) tableWrap.style.display = hasRows ? "" : "none";
    if (noResult) noResult.style.display = hasRows ? "none" : "";
  }

  provinceSelect.addEventListener("change", render);
  sortSelect.addEventListener("change", render);
  if (searchInput) {
    searchInput.addEventListener("input", render);
    searchInput.addEventListener("search", render); /* clear (x) button */
  }
  render();
})();
