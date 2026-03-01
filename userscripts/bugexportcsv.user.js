// ==UserScript==
// @name         BGA Bug Export CSV
// @namespace    https://boardgamearena.com/
// @version      0.4
// @description  Export bug list to CSV file
// @author       elaskavaia
// @match        https://boardgamearena.com/bugs?*
// @icon         https://www.google.com/s2/favicons?sz=64&domain=boardgamearena.com
// @run-at       document-idle
// @grant        none
// ==/UserScript==

(function () {
    "use strict";

    // Scrape one page of bug rows from the current DOM
    function scrapeCurrentPage() {
        const bugs = [];
        document.querySelectorAll("tr.bugrow").forEach((tr) => {
            const tds = tr.querySelectorAll("td");
            if (tds.length < 7) return;
            const idLink = tds[0].querySelector("a");
            const id = idLink?.href.match(/id=(\d+)/)?.[1] ?? "";
            bugs.push({
                id,
                status: tds[1].textContent.trim(),
                votes: tds[2].textContent.trim().split(/\s/)[0],
                game: tds[3].textContent.trim(),
                type: tds[4].textContent.trim(),
                title: tds[5].textContent.trim(),
                lastupdate: tds[6].textContent.trim(),
                url: `https://boardgamearena.com/bug?id=${id}`
            });
        });
        return bugs;
    }

    // Wait for tbody content to change after clicking next page
    function waitForPageChange(tbody, timeout = 3000) {
        return new Promise((resolve, reject) => {
            const timer = setTimeout(() => {
                observer.disconnect();
                reject(new Error("Timeout waiting for page change"));
            }, timeout);
            const observer = new MutationObserver(() => {
                observer.disconnect();
                clearTimeout(timer);
                resolve();
            });
            observer.observe(tbody, { childList: true, subtree: true });
        });
    }

    async function scrapeAllPages(btn) {
        const allBugs = [];
        const seen = new Set();

        for (let pageNum = 0; pageNum < 100; pageNum++) {
            const pageBugs = scrapeCurrentPage();
            pageBugs.forEach((b) => {
                if (!seen.has(b.id)) {
                    seen.add(b.id);
                    allBugs.push(b);
                }
            });
            btn.textContent = `Scraping... (${allBugs.length} bugs)`;

            const nextBtn = [...document.querySelectorAll(".bga-bugs-table a.bga-button")].find((a) => a.textContent.trim() === ">");
            if (!nextBtn || nextBtn.closest(".invisible")) break;

            const tbody = document.querySelector(".bga-bugs-table tbody");
            nextBtn.click();
            await waitForPageChange(tbody);
        }
        return allBugs;
    }

    function toCSV(bugs) {
        if (bugs.length === 0) return "";
        const headers = Object.keys(bugs[0]);
        const rows = bugs.map((b) => headers.map((h) => `"${String(b[h] ?? "").replace(/"/g, '""')}"`).join(","));
        return [headers.join(","), ...rows].join("\n");
    }

    function downloadCSV(csv, filename) {
        const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
        const a = document.createElement("a");
        a.href = URL.createObjectURL(blob);
        a.download = filename;
        a.click();
        URL.revokeObjectURL(a.href);
    }

    async function fetchBugDescription(id) {
        const resp = await fetch(`/bug?id=${id}`);
        const html = await resp.text();
        const doc = new DOMParser().parseFromString(html, "text/html");
        // Grab all filled-in answer paragraphs across all sections (display style not set in SSR HTML)
        const paras = [...doc.querySelectorAll("p.instruction_desc")];
        console.log(`BGA Bug Export CSV: bug ${id} — found ${paras.length} instruction_desc paragraphs`);
        const result = paras.map((p) => p.textContent.trim()).filter(Boolean).join(" | ");
        if (!result) {
            // Fallback: log nearby structure to help diagnose selector issues
            const sections = doc.querySelectorAll(".report_instruction");
            console.log(`BGA Bug Export CSV: bug ${id} — found ${sections.length} .report_instruction sections`);
            sections.forEach((s, i) => console.log(`  section[${i}] display="${s.style.display}" text="${s.textContent.trim().substring(0, 80)}"`));
        }
        return result;
    }

    function makeButton(svelteClass, text, color) {
        const btn = document.createElement("a");
        btn.classList.add("bga-button", "bga-button--blue", "bga-button-inner", "flex-1", "truncate");
        if (svelteClass) btn.classList.add(svelteClass);
        btn.style.cssText = `--progressionTransition: 200ms; background-position: 0px 100%, 100% 100%; background-size: 100% 100%, 100% 100%; background-color: ${color} !important; background-image: none !important;`;
        btn.href = "#";
        btn.textContent = text;

        const holder = document.createElement("div");
        holder.classList.add("flex", "bga-button-holder");
        if (svelteClass) holder.classList.add(svelteClass);
        holder.style.cssText = "--horizontalPadding: 11; --verticalPadding: 14;";
        holder.appendChild(btn);

        const wrapper = document.createElement("div");
        wrapper.classList.add("inline-block", "relative", "z-0", "select-none");
        if (svelteClass) wrapper.classList.add(svelteClass);
        wrapper.appendChild(holder);

        return { btn, wrapper };
    }

    function addExportButton(retries = 0) {
        const urlParams = new URLSearchParams(window.location.search);
        const gameId = urlParams.get("game");
        if (!gameId) return;

        // Find the row that contains Search and Reset filter buttons
        const btnRow = document.querySelector("div.flex.items-center.justify-center.gap-3");
        if (!btnRow) {
            if (retries < 20) {
                setTimeout(() => addExportButton(retries + 1), 500);
            } else {
                console.log("BGA Bug Export CSV: giving up, button row not found");
            }
            return;
        }

        console.log("BGA Bug Export CSV: adding export button for game", gameId);

        // Extract the Svelte scoped class from existing buttons so our button gets the same styles
        const existingBtn = btnRow.querySelector("a.bga-button");
        const svelteClass = [...(existingBtn?.classList ?? [])].find((c) => c.startsWith("svelte-")) ?? "";

        // --- Export CSV (list only) ---
        const { btn, wrapper } = makeButton(svelteClass, "Export CSV", "#7b1fa2");
        btnRow.appendChild(wrapper);

        btn.addEventListener("click", async (e) => {
            e.preventDefault();
            btn.style.setProperty("background-color", "#4a148c", "important");
            try {
                const bugs = await scrapeAllPages(btn);
                downloadCSV(toCSV(bugs), `bugs-${gameId}.csv`);
                btn.textContent = `Export CSV (${bugs.length})`;
            } catch (err) {
                btn.textContent = "Error: " + err.message;
                console.error(err);
            }
            btn.style.setProperty("background-color", "#7b1fa2", "important");
        });

        // --- Export CSV+Desc (fetches each bug page for description) ---
        const { btn: btn2, wrapper: wrapper2 } = makeButton(svelteClass, "Export CSV+Desc", "#e65100");
        btnRow.appendChild(wrapper2);

        btn2.addEventListener("click", async (e) => {
            e.preventDefault();
            btn2.style.setProperty("background-color", "#bf360c", "important");
            try {
                const bugs = await scrapeAllPages(btn2);
                for (let i = 0; i < bugs.length; i++) {
                    btn2.textContent = `Fetching desc... (${i + 1}/${bugs.length})`;
                    bugs[i].description = await fetchBugDescription(bugs[i].id);
                }
                downloadCSV(toCSV(bugs), `bugs-${gameId}-desc.csv`);
                btn2.textContent = `Export CSV+Desc (${bugs.length})`;
            } catch (err) {
                btn2.textContent = "Error: " + err.message;
                console.error(err);
            }
            btn2.style.setProperty("background-color", "#e65100", "important");
        });
    }

    console.log("BGA Bug Export CSV: loaded");
    setTimeout(() => addExportButton(0), 500);
})();
