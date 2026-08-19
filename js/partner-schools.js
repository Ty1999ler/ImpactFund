/* Student Impact Fund by Alumo — partner schools list
   Used by /partner-schools/ and /fr/partner-schools/.
   Renders the schools table (school | association | contact email), filters it
   by province and sorts A–Z / Z–A by school name.
   DATA: window.ALUMO_SCHOOLS in /js/schools-data.js — edit the list there only.
   (The live WordPress site loads this via DataTables/AJAX from a custom post
   type; here it is a static file.) */

(function () {
  "use strict";

  var table = document.getElementById("schools-table");
  var provinceSelect = document.getElementById("school-province");
  var sortSelect = document.getElementById("school-sort-table");
  if (!table || !provinceSelect || !sortSelect || !window.ALUMO_SCHOOLS) return;

  var tbody = table.querySelector("tbody");
  var tableContainer = document.getElementById("schools-table-container");
  var noResult = document.querySelector(".no-result-found");

  /* schools-data.js stores two-letter codes; the <option value="..."> values
     are full English province names on both language pages. */
  var CODE_TO_PROVINCE = {
    AB: "Alberta", BC: "British Columbia", MB: "Manitoba",
    NB: "New Brunswick", NL: "Newfoundland and Labrador",
    NT: "Northwest Territories", NS: "Nova Scotia", NU: "Nunavut",
    ON: "Ontario", PE: "Prince Edward Island", QC: "Quebec",
    SK: "Saskatchewan", YT: "Yukon"
  };

  function cell(tr, cls, content) {
    var td = document.createElement("td");
    td.className = cls;
    if (content) td.appendChild(content);
    tr.appendChild(td);
    return td;
  }

  function render() {
    var province = provinceSelect.value;
    var order = sortSelect.value;

    var rows = window.ALUMO_SCHOOLS.filter(function (s) {
      return !province || CODE_TO_PROVINCE[s.province] === province;
    });
    rows.sort(function (a, b) {
      return order === "desc"
        ? b.school.localeCompare(a.school)
        : a.school.localeCompare(b.school);
    });

    tbody.innerHTML = "";
    rows.forEach(function (s) {
      var tr = document.createElement("tr");
      cell(tr, "name-col", document.createTextNode(s.school));
      cell(tr, "association-col", document.createTextNode(s.association || ""));
      var emailContent;
      if (s.email && s.email.indexOf("@") !== -1) {
        emailContent = document.createElement("a");
        emailContent.href = "mailto:" + s.email;
        emailContent.textContent = s.email;
      } else {
        emailContent = document.createTextNode(s.email || "");
      }
      cell(tr, "email-col", emailContent);
      tbody.appendChild(tr);
    });

    var hasRows = rows.length > 0;
    if (tableContainer) tableContainer.style.display = hasRows ? "" : "none";
    if (noResult) noResult.style.display = hasRows ? "none" : "";
  }

  provinceSelect.addEventListener("change", render);
  sortSelect.addEventListener("change", render);
  render();
})();
