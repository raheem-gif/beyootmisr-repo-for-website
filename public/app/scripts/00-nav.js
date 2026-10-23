/* Header: sticky shadow, mobile drawer, sub-menus. */
(function () {
  "use strict";
  var doc = document.documentElement;
  var body = document.body;
  var header = document.querySelector("[data-header]");
  var nav = document.querySelector("[data-nav]");
  var toggle = document.querySelector("[data-nav-toggle]");
  var toggleLabel = document.querySelector("[data-nav-toggle-label]");
  var desktop = window.matchMedia("(min-width: 1280px)");

  doc.classList.add("js");

  if (header) {
    var onScroll = function () {
      header.classList.toggle("is-scrolled", window.scrollY > 8);
    };
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
  }

  function setOpen(open) {
    if (!toggle || !nav) return;
    toggle.setAttribute("aria-expanded", open ? "true" : "false");
    if (toggleLabel) {
      toggleLabel.textContent = open ? toggle.getAttribute("data-label-close") : toggle.getAttribute("data-label-open");
    }
    body.classList.toggle("nav-open", open);
    if (open) {
      var first = nav.querySelector("a, button");
      if (first) first.focus({ preventScroll: true });
    }
  }

  if (toggle && nav) {
    toggle.addEventListener("click", function () {
      setOpen(toggle.getAttribute("aria-expanded") !== "true");
    });
    document.addEventListener("keydown", function (e) {
      if (e.key !== "Escape") return;
      if (body.classList.contains("nav-open")) {
        setOpen(false);
        toggle.focus();
      }
      closeSubs();
    });
    // Following an in-page link (e.g. #donate) closes the drawer.
    nav.addEventListener("click", function (e) {
      var link = e.target.closest("a");
      if (link && !desktop.matches) setOpen(false);
    });
    desktop.addEventListener("change", function () {
      setOpen(false);
      closeSubs();
    });
    // Keep the drawer within the viewport if focus moves past it.
    document.addEventListener("focusin", function (e) {
      if (!body.classList.contains("nav-open") || desktop.matches) return;
      if (!nav.contains(e.target) && !toggle.contains(e.target) && !header.contains(e.target)) {
        setOpen(false);
      }
    });
  }

  var subToggles = Array.prototype.slice.call(document.querySelectorAll("[data-sub-toggle]"));

  function closeSubs(except) {
    subToggles.forEach(function (btn) {
      if (btn === except) return;
      btn.setAttribute("aria-expanded", "false");
      var panel = document.getElementById(btn.getAttribute("aria-controls"));
      if (panel) panel.classList.remove("is-open");
    });
  }

  subToggles.forEach(function (btn) {
    var panel = document.getElementById(btn.getAttribute("aria-controls"));
    if (!panel) return;
    btn.addEventListener("click", function (e) {
      e.stopPropagation();
      var open = btn.getAttribute("aria-expanded") !== "true";
      if (desktop.matches) closeSubs(btn);
      btn.setAttribute("aria-expanded", open ? "true" : "false");
      panel.classList.toggle("is-open", open);
    });
  });

  document.addEventListener("click", function (e) {
    if (desktop.matches && !e.target.closest(".has-sub")) closeSubs();
  });
})();
