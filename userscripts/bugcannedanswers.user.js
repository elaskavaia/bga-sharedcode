// ==UserScript==
// @name         BGA Bug report canned replies
// @namespace    https://boardgamearena.com/
// @version      0.1
// @description  select canned reply
// @author       elaskavaia
// @match        https://boardgamearena.com/bug?id=*
// @icon         https://www.google.com/s2/favicons?sz=64&domain=boardgamearena.com
// @downloadURL  https://raw.githubusercontent.com/elaskavaia/bga-sharedcode/master/userscripts/bugcannedanswers.user.js
// @updateURL  https://raw.githubusercontent.com/elaskavaia/bga-sharedcode/master/userscripts/bugcannedanswers.user.js
// @run-at document-idle
// @grant        none
// ==/UserScript==

(function () {
  "use strict";

  // Your code here...
  //debugger;
  let table = [
    {
      title: "Rule X",
      text: "Not a bug. Please conslult the rule book.",
      resolution: "notabug",
      acton: "change_bug_status"
    },
    {
      title: "Missing screenshot",
      text: "Insufficient information. Please provide table number, move number and screenshot",
      resolution: "infoneeded",
      acton: "change_bug_status"
    },
    {
      title: "Missing move",
      text: "Insufficient information. Please provide table number, move number and/or log details",
      resolution: "infoneeded",
      acton: "change_bug_status"
    }
  ];
  let tools = document.querySelector("#moderator_quicktools .pagesection__content");
  tools.innerHTML += "<p>Canned answers:</p>";
  const report_log = document.getElementById("report_log");
  for (let i in table) {
    const id = `${table[i].acton}_${table[i].resolution}`;
    const a = document.createElement("a");
    a.id = id;
    a.classList.add("bgabutton", "bgabutton_blue", table[i].acton);
    a.innerHTML = table[i].title;
    tools.appendChild(a);
 	tools.appendChild(document.createTextNode(" "));
    const text = table[i].text;

    a.addEventListener("click", (event) => {
      report_log.innerHTML = text;
    });
  }
})();
