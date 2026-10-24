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
      if (e.key !== "Escape" || !body.classList.contains("nav-open")) return;
      setOpen(false);
      toggle.focus();
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

  /* Open/close one sub-menu. Closing a desktop panel while the pointer is over it
     also marks the item .is-dismissed, so CSS :hover does not keep it on screen
     (Esc and the toggle always dismiss; the class clears when the pointer leaves). */
  function setSub(btn, open, dismiss) {
    var panel = document.getElementById(btn.getAttribute("aria-controls"));
    var item = btn.closest(".has-sub");
    btn.setAttribute("aria-expanded", open ? "true" : "false");
    if (panel) panel.classList.toggle("is-open", open);
    if (!item) return;
    if (open) item.classList.remove("is-dismissed");
    else if (dismiss && desktop.matches && item.matches(":hover")) item.classList.add("is-dismissed");
  }

  function closeSubs(except, dismiss) {
    subToggles.forEach(function (btn) {
      if (btn !== except) setSub(btn, false, dismiss);
    });
  }

  subToggles.forEach(function (btn) {
    var panel = document.getElementById(btn.getAttribute("aria-controls"));
    var item = btn.closest(".has-sub");
    if (!panel || !item) return;
    btn.addEventListener("click", function (e) {
      e.stopPropagation();
      var open = btn.getAttribute("aria-expanded") !== "true";
      if (desktop.matches) closeSubs(btn);
      setSub(btn, open, true);
    });
    item.addEventListener("mouseleave", function () {
      item.classList.remove("is-dismissed");
    });
    // Desktop: a panel opened from the keyboard closes once focus leaves its item,
    // so it never covers the content the visitor tabs to next.
    item.addEventListener("focusout", function (e) {
      if (!desktop.matches || (e.relatedTarget && item.contains(e.relatedTarget))) return;
      setSub(btn, false, false);
    });
  });

  // Desktop: Esc closes any open or hovered panel and returns focus to its toggle.
  document.addEventListener("keydown", function (e) {
    if (e.key !== "Escape" || !desktop.matches) return;
    var active = document.activeElement;
    var item = active && active.closest ? active.closest(".has-sub") : null;
    closeSubs(null, true);
    var btn = item ? item.querySelector("[data-sub-toggle]") : null;
    var panel = btn ? document.getElementById(btn.getAttribute("aria-controls")) : null;
    if (panel && panel.contains(active)) btn.focus();
  });

  document.addEventListener("click", function (e) {
    if (desktop.matches && !e.target.closest(".has-sub")) closeSubs();
  });
})();
