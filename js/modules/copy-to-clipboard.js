/**
 * Copy to Clipboard Utility Module
 * Handles copy-to-clipboard functionality for sequence displays with custom tooltips
 * Can be used by any page that has elements with class "copyable"
 */

function initializeCopyToClipboard() {
    // Handle copy to clipboard for sequences
    const copyables = document.querySelectorAll(".copyable");
    copyables.forEach(el => {
        let resetColorTimeout;
        el.addEventListener("click", function () {
            const text = el.innerText.trim();
            
            // Check if clipboard API is available
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => {
                    el.classList.add("bg-success", "text-white");
                    if (resetColorTimeout) clearTimeout(resetColorTimeout);
                    resetColorTimeout = setTimeout(() => {
                        el.classList.remove("bg-success", "text-white");
                    }, 1500);
                }).catch(err => console.error("Copy failed:", err));
            } else {
                // Fallback for older browsers or HTTP contexts
                const textArea = document.createElement("textarea");
                textArea.value = text;
                textArea.style.position = "fixed";
                textArea.style.opacity = "0";
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    el.classList.add("bg-success", "text-white");
                    if (resetColorTimeout) clearTimeout(resetColorTimeout);
                    resetColorTimeout = setTimeout(() => {
                        el.classList.remove("bg-success", "text-white");
                    }, 1500);
                } catch (err) {
                    console.error("Copy fallback failed:", err);
                }
                document.body.removeChild(textArea);
            }
        });
    });

    // Initialize tooltips after a small delay to ensure Bootstrap is fully loaded
    setTimeout(() => {
        initializeCopyTooltips();
    }, 500);
}

function initializeCopyTooltips() {
    const copyables = document.querySelectorAll(".copyable");
    copyables.forEach(el => {
        // Custom simple tooltip that follows cursor
        el.addEventListener("mouseenter", function() {
            // Remove any existing tooltip
            const existing = document.getElementById("custom-copy-tooltip");
            if (existing) existing.remove();
            
            // Create simple tooltip
            const tooltip = document.createElement("div");
            tooltip.id = "custom-copy-tooltip";
            tooltip.textContent = "Click to copy";
            tooltip.style.cssText = `
                position: fixed;
                background-color: #000;
                color: #fff;
                padding: 5px 10px;
                border-radius: 4px;
                font-size: 12px;
                white-space: nowrap;
                pointer-events: none;
                z-index: 9999;
            `;
            document.body.appendChild(tooltip);
            
            // Update position on mousemove
            const updatePosition = (e) => {
                tooltip.style.left = (e.clientX + 10) + "px";
                tooltip.style.top = (e.clientY - 30) + "px";
            };
            
            el.addEventListener("mousemove", updatePosition);
            
            // Initial position
            updatePosition(event);
            
            el.addEventListener("mouseleave", function() {
                const existing = document.getElementById("custom-copy-tooltip");
                if (existing) existing.remove();
                el.removeEventListener("mousemove", updatePosition);
            }, { once: true });
        });
    });
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeCopyToClipboard);
} else {
    // Already loaded
    initializeCopyToClipboard();
}

/* ── Copy an explicit payload, not an element's own text ─────────────────────
 *
 * .copyable above copies what the element displays, which is right for a sequence block.
 * This copies a string prepared server-side and carried in data-copy-text, for the case
 * where what you want on the clipboard is NOT what is on screen — the gene overview is a
 * definition list with labels and links, and selecting it by hand picks up stray blank
 * lines and layout whitespace.
 *
 * Same HTTP-safe fallback as above: navigator.clipboard is unavailable on plain http://,
 * which is how this site is served today (see the DNS/HTTPS blocker), so the textarea +
 * execCommand path is the one that actually runs here, not a legacy nicety.
 */
function initializeCopyPayload() {
    document.querySelectorAll("[data-copy-text]").forEach(el => {
        if (el.dataset.copyBound) return;          // idempotent — safe to re-init
        el.dataset.copyBound = "1";

        el.addEventListener("click", function (e) {
            e.preventDefault();
            const text = el.getAttribute("data-copy-text") || "";
            const done = () => {
                const label = el.querySelector(".copy-label");
                const icon  = el.querySelector("i");
                const oldL  = label ? label.textContent : null;
                const oldI  = icon ? icon.className : null;
                if (label) label.textContent = "Copied";
                if (icon)  icon.className = "fa fa-check";
                setTimeout(() => {
                    if (label && oldL !== null) label.textContent = oldL;
                    if (icon  && oldI !== null) icon.className = oldI;
                }, 1500);
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done)
                    .catch(err => console.error("Copy failed:", err));
            } else {
                const ta = document.createElement("textarea");
                ta.value = text;
                ta.style.position = "fixed";
                ta.style.opacity = "0";
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand("copy"); done(); }
                catch (err) { console.error("Copy fallback failed:", err); }
                document.body.removeChild(ta);
            }
        });
    });
}
document.addEventListener("DOMContentLoaded", initializeCopyPayload);
