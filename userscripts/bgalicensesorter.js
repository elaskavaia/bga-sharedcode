// ==UserScript==
// @name       BGA Licenses Table Sorter
// @description  adds sorting fields to license table
// @author       elaskavaia
// @match        *://studio.boardgamearena.com/licensing
// @icon         https://www.google.com/s2/favicons?sz=64&domain=boardgamearena.com
// @run-at document-idle
// @version     0.2
// @grant       none

// ==/UserScript==

// call the tablesorter plugin

(function () {
  'use strict';
  console.error("BGA sorter is loaded");

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

    sortedChildren.forEach(function (element) {
      container.appendChild(element);
    });
  }

  function addButton(name, column) {
    let selector = document.getElementById("sort_selector");
    selector.innerHTML += `
    <input type="radio" id="licenses_by_${name}" name="sort_switch" value="by_${name}" style="vertical-align: middle;"></input>
    <label for="by_${name}">By ${name}</label>
    `;
    let button = document.getElementById(`licenses_by_${name}`);
    button.addEventListener("click", () => sortByColumn(column));
  }


  addButton("bggrating", 5);
  addButton("complexity", 6);
})();
