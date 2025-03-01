// ==UserScript==
// @name         BGA bug report view
// @namespace    https://boardgamearena.com/
// @version      0.1
// @description  makes bug link open new window
// @author       elaskavaia
// @match        https://boardgamearena.com/bugs?*
// @icon         https://www.google.com/s2/favicons?sz=64&domain=boardgamearena.com
// @run-at document-idle
// @grant        none
// ==/UserScript==

function updateBugs() {
  console.log("BGA bug report view");
  // Your code here...
  const targetNode = document.querySelector("#buglist_inner");

  // Callback function to execute when mutations are observed
  const callback = (mutationList, _observer) => {
    for (const mutation of mutationList) {
      if (mutation.type === "childList") {
        console.log("A child node has been added or removed.", mutation);
        blanket(mutation.target);
      }
    }
  };

  // Create an observer instance linked to the callback function
  const observer = new MutationObserver(callback);

  // Start observing the target node for configured mutations
  observer.observe(targetNode, { childList: true });

  blanket(document);

  // Later, you can stop observing
  //observer.disconnect();
}

function blanket(parent) {
  parent.querySelectorAll(".bugrow").forEach((tr) => {
    tr.querySelectorAll("a").forEach((node) => {
      if (node.target != "_blank") {
        node.target = "_blank"; // bugs should open in new window
        //console.log("fixing", node.href);
        //node.innerHTML += " ^";
      }
    });
    tr.style.backgroundColor = "gray";
  });
}

(function () {
  "use strict";

  setTimeout(updateBugs, 500);
})();
