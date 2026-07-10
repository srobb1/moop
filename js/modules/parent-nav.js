/* ============================================================================
 * parent-nav.js — section navigation for the parent (gene) page
 *
 * Builds a Table-of-Contents by SCANNING THE RENDERED DOM (top-level sections
 * marked with [data-nav-label], plus the annotation child cards / type sections
 * that already carry #annot_section_* anchors) — so the nav always matches what
 * is actually on the page, including the "only shows if it has data" behaviour.
 *
 * Features: sticky sidebar (wide) / "Jump to" bar (narrow), scrollspy highlight,
 * collapsible sidebar (remembered), nested child -> annotation-type depth.
 * Paired with css/parent-nav.css and the markup in tools/pages/parent.php.
 * ==========================================================================*/
(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    var main = document.querySelector(".pnav-main");
    var layout = document.querySelector(".pnav-layout");
    var sideToc = document.getElementById("pnavToc");
    var jbDd = document.getElementById("pnavJbDd");
    if (!main || !layout || !sideToc) return;

    var chevron =
      '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M4 6l4 4 4-4" ' +
      'stroke="currentColor" stroke-width="1.6" fill="none"/></svg>';

    // ---- helpers -----------------------------------------------------------
    function esc(s) {
      return String(s).replace(/[&<>"']/g, function (c) {
        return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
      });
    }
    function typeLabel(section) {
      var b = section.querySelector("h5 .badge");
      return b ? b.textContent.trim() : (section.id || "Annotations");
    }
    // split "NAME (mRNA)" -> {label:"NAME", tag:"mRNA"}
    function splitChild(text) {
      var m = /^(.*?)\s*\(([^)]+)\)\s*$/.exec(text.trim());
      return m ? { label: m[1], tag: m[2] } : { label: text.trim(), tag: "" };
    }

    // ---- build model (registry of nodes keyed for lookup) ------------------
    var reg = {};       // key -> node
    var order = [];     // node keys in document order (for scrollspy)
    var kc = 0;
    function mk(el, label, lvl, tag) {
      var node = { key: "pn" + kc++, el: el, label: label, lvl: lvl, tag: tag || "", children: [], parent: null };
      reg[node.key] = node;
      return node;
    }

    // annotation-type sections that live directly under `scope` (not in a deeper card)
    function directSections(scope, ownerCard) {
      var out = [];
      scope.querySelectorAll(".annotation-section").forEach(function (sec) {
        if (sec.closest(".annotation-card") === ownerCard) out.push(sec);
      });
      return out;
    }
    // child annotation cards that are direct descendants of `scope`
    function directCards(scope, ownerCard) {
      var out = [];
      scope.querySelectorAll(".annotation-card").forEach(function (card) {
        var above = card.parentElement ? card.parentElement.closest(".annotation-card") : null;
        if (above === ownerCard) out.push(card);
      });
      return out;
    }
    function buildCard(card, lvl) {
      var badge = card.querySelector(".child-feature-badge");
      var sc = splitChild(badge ? badge.textContent : "Feature");
      var node = mk(card, sc.label, lvl, sc.tag);
      directSections(card, card).forEach(function (sec) {
        var leaf = mk(sec, typeLabel(sec), lvl + 1);
        leaf.parent = node; node.children.push(leaf);
      });
      directCards(card, card).forEach(function (sub) {
        var child = buildCard(sub, lvl + 1);
        child.parent = node; node.children.push(child);
      });
      return node;
    }

    var roots = [];
    main.querySelectorAll("[data-nav-label]").forEach(function (el) {
      var node = mk(el, el.getAttribute("data-nav-label"), 1);
      roots.push(node);
      if (el.id === "pnav-annotations") {
        directSections(el, null).forEach(function (sec) {         // gene-level types
          var leaf = mk(sec, typeLabel(sec), 2);
          leaf.parent = node; node.children.push(leaf);
        });
        directCards(el, null).forEach(function (card) {           // per-transcript cards
          var child = buildCard(card, 2);
          child.parent = node; node.children.push(child);
        });
      }
    });
    if (!roots.length) return;

    (function collectOrder(list) {                                // document order
      list.forEach(function (n) { order.push(n.key); collectOrder(n.children); });
    })(roots);

    // ---- render TOC markup -------------------------------------------------
    function renderList(list) {
      return '<ul class="pnav-list">' + list.map(function (n) {
        var hasKids = n.children.length > 0;
        var caret = hasKids
          ? '<button class="pnav-caret" aria-expanded="true" aria-label="Toggle section" data-key="' + n.key + '">' + chevron + "</button>"
          : '<span class="pnav-spacer"></span>';
        var tagSlug = n.tag ? n.tag.toLowerCase().replace(/[^a-z0-9]+/g, "") : "";
        var tag = n.tag ? '<span class="pnav-tag pnav-tag--' + tagSlug + '">' + esc(n.tag) + "</span>" : "";
        var link =
          '<a class="pnav-link lvl-' + n.lvl + '" href="#" data-key="' + n.key + '" title="' + esc(n.label) + '">' +
          tag + '<span class="pnav-label">' + esc(n.label) + "</span></a>";
        var sub = hasKids ? '<div class="pnav-sub">' + renderList(n.children) + "</div>" : "";
        return '<li class="pnav-item"><div class="pnav-row">' + caret + link + "</div>" + sub + "</li>";
      }).join("") + "</ul>";
    }
    var html = renderList(roots);
    sideToc.innerHTML = html;
    if (jbDd) jbDd.innerHTML = html;

    // ---- link registry across both TOC copies ------------------------------
    var linkMap = {};
    [sideToc, jbDd].forEach(function (root) {
      if (!root) return;
      root.querySelectorAll(".pnav-link").forEach(function (a) {
        var k = a.getAttribute("data-key");
        (linkMap[k] = linkMap[k] || []).push(a);
      });
    });

    // ---- caret expand / collapse -------------------------------------------
    function wireCarets(root) {
      root.querySelectorAll(".pnav-caret").forEach(function (btn) {
        var sub = btn.closest(".pnav-item").querySelector(".pnav-sub");
        if (!sub) return;
        sub.style.height = "auto";
        btn.addEventListener("click", function (e) {
          e.preventDefault();
          var open = btn.getAttribute("aria-expanded") !== "false";
          if (open) {
            sub.style.height = sub.scrollHeight + "px";
            requestAnimationFrame(function () { sub.style.height = "0px"; });
            btn.setAttribute("aria-expanded", "false");
          } else {
            sub.style.height = sub.scrollHeight + "px";
            btn.setAttribute("aria-expanded", "true");
            sub.addEventListener("transitionend", function te() {
              sub.style.height = "auto"; sub.removeEventListener("transitionend", te);
            });
          }
        });
      });
    }
    wireCarets(sideToc);
    if (jbDd) wireCarets(jbDd);

    // ---- expand any Bootstrap collapse hiding the target, then scroll ------
    function expandAncestors(el) {
      var p = el.parentElement;
      while (p) {
        if (p.classList && p.classList.contains("collapse") && !p.classList.contains("show")) {
          if (window.bootstrap && window.bootstrap.Collapse) {
            window.bootstrap.Collapse.getOrCreateInstance(p).show();
          } else {
            p.classList.add("show");
          }
        }
        p = p.parentElement;
      }
    }
    var reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    function goTo(key) {
      var node = reg[key];
      if (!node) return;
      expandAncestors(node.el);
      node.el.scrollIntoView({ behavior: reduce ? "auto" : "smooth", block: "start" });
    }

    function wireLinks(root, isDropdown) {
      root.querySelectorAll(".pnav-link").forEach(function (a) {
        a.addEventListener("click", function (e) {
          e.preventDefault();
          goTo(a.getAttribute("data-key"));
          if (isDropdown) closeDropdown();
        });
      });
    }
    wireLinks(sideToc, false);
    if (jbDd) wireLinks(jbDd, true);

    // ---- scrollspy ---------------------------------------------------------
    var jbCurrent = document.getElementById("pnavJbCurrent");
    var current = null;
    function setActive(key) {
      if (key === current) return;
      current = key;
      Object.keys(linkMap).forEach(function (k) {
        linkMap[k].forEach(function (a) { a.classList.remove("is-active", "is-ancestor"); });
      });
      (linkMap[key] || []).forEach(function (a) { a.classList.add("is-active"); });
      var node = reg[key], p = node ? node.parent : null;
      while (p) {
        (linkMap[p.key] || []).forEach(function (a) { a.classList.add("is-ancestor"); });
        // auto-open the branch that contains the active item
        (linkMap[p.key] || []).forEach(function (a) {
          var caret = a.closest(".pnav-row").querySelector(".pnav-caret");
          if (caret && caret.getAttribute("aria-expanded") === "false") caret.click();
        });
        p = p.parent;
      }
      if (jbCurrent && node) jbCurrent.textContent = node.label;
      var act = sideToc.querySelector(".pnav-link.is-active");
      if (act) {
        var ar = act.getBoundingClientRect(), sr = sideToc.getBoundingClientRect();
        if (ar.top < sr.top || ar.bottom > sr.bottom) act.scrollIntoView({ block: "nearest" });
      }
    }

    // Pick the section whose top has most-recently crossed a trigger line near
    // the top of the viewport. Using max(top) among tops <= trigger selects the
    // DEEPEST current section: a container's top passes the line before its
    // children, so once a child's top passes, the child (larger top) wins.
    var spy = order.map(function (k) { return { key: k, el: reg[k].el }; });
    var ticking = false;
    function updateSpy() {
      ticking = false;
      var jb = document.querySelector(".pnav-jumpbar");
      var trigger = (jb && jb.offsetParent !== null ? jb.getBoundingClientRect().bottom : 0) + 90;
      var bestKey = spy.length ? spy[0].key : null, bestTop = -Infinity;
      for (var i = 0; i < spy.length; i++) {
        var top = spy[i].el.getBoundingClientRect().top;
        if (top <= trigger && top > bestTop) { bestTop = top; bestKey = spy[i].key; }
      }
      if (bestKey) setActive(bestKey);
    }
    function onScroll() { if (!ticking) { ticking = true; requestAnimationFrame(updateSpy); } }
    window.addEventListener("scroll", onScroll, { passive: true });
    window.addEventListener("resize", onScroll);
    setActive(order[0]);
    updateSpy();

    // ---- collapse sidebar (remembered) -------------------------------------
    var toggle = document.getElementById("pnavToggle");
    var KEY = "moop_pnav_collapsed";
    try { if (localStorage.getItem(KEY) === "1") layout.classList.add("pnav-collapsed"); } catch (e) {}
    function reflect() {
      var c = layout.classList.contains("pnav-collapsed");
      if (toggle) {
        toggle.setAttribute("aria-label", c ? "Expand navigation" : "Collapse navigation");
        toggle.setAttribute("aria-expanded", c ? "false" : "true");
        toggle.title = c ? "Show section navigation" : "Collapse (content goes full width)";
      }
    }
    if (toggle) {
      toggle.addEventListener("click", function () {
        layout.classList.toggle("pnav-collapsed");
        try { localStorage.setItem(KEY, layout.classList.contains("pnav-collapsed") ? "1" : "0"); } catch (e) {}
        reflect();
      });
    }
    reflect();

    // ---- narrow jump-to dropdown ------------------------------------------
    var jbBtn = document.getElementById("pnavJbBtn");
    function closeDropdown() {
      if (!jbDd) return;
      jbDd.classList.remove("open");
      if (jbBtn) jbBtn.setAttribute("aria-expanded", "false");
    }
    if (jbBtn && jbDd) {
      jbBtn.addEventListener("click", function (e) {
        e.stopPropagation();
        var open = jbDd.classList.toggle("open");
        jbBtn.setAttribute("aria-expanded", open ? "true" : "false");
      });
      document.addEventListener("click", function (e) {
        if (!jbDd.contains(e.target) && !jbBtn.contains(e.target)) closeDropdown();
      });
    }
  });
})();
