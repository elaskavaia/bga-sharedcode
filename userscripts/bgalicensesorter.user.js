// ==UserScript==
// @name       BGA Licenses Table Sorter
// @description  adds sorting fields to license table
// @author       elaskavaia
// @match        *://studio.boardgamearena.com/licensing
// @icon         https://www.google.com/s2/favicons?sz=64&domain=boardgamearena.com
// @downloadURL  https://raw.githubusercontent.com/elaskavaia/bga-sharedcode/master/userscripts/bgalicensesorter.user.js
// @updateURL  https://raw.githubusercontent.com/elaskavaia/bga-sharedcode/master/userscripts/bgalicensesorter.user.js
// @run-at document-idle
// @version     0.4
// @grant       none

// ==/UserScript==

(function () {
  "use strict";
  console.log("BGA sorter is loaded");

  function sortByColumn(column) {
    function compareFuncComplexity(a, b) {
      const avalue = a.children[column].textContent.trim();
      const bvalue = b.children[column].textContent.trim();
      try {
        return parseFloat(avalue) > parseFloat(bvalue) ? -1 : 1;
      } catch (e) {
        return avalue > bvalue ? -1 : 1;
      }
    }

    var container = document.querySelector(".statstable tbody");
    var children = Array.prototype.slice.call(container.children);

    var sortedChildren = children.sort(compareFuncComplexity);

    sortedChildren.forEach((element) => {
      container.appendChild(element);
    });
  }

  function addButton(name, column) {
    let selector = document.getElementById("sort_selector");
    let div = document.createElement("div");
    div.innerHTML = `
    <input type="radio" id="licenses_by_${name}" name="sort_switch2" value="by_${name}" style="vertical-align: middle;"></input>
    <label for="by_${name}">By ${name}</label>
    `;
    selector.appendChild(div);
    let button = selector.querySelector(`#licenses_by_${name}`);
    button.addEventListener("click", () => setTimeout(() => sortByColumn(column), 1));
  }
  
  addButton("bggrating", 5);
  addButton("complexity", 6);
})();
