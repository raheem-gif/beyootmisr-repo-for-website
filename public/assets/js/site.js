/* Beyoot Misr — generated from app/scripts by the build. Do not edit here. */

/* ---- 00-nav.js ---- */
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


/* ---- 10-widgets.js ---- */
/* Page widgets: number count-up, card filters, WhatsApp composer, copy buttons. */
(function () {
  "use strict";
  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* ---- Count-up for verified numbers (data-count) ---- */
  var counters = document.querySelectorAll("[data-count]");
  if (counters.length && "IntersectionObserver" in window && !reduceMotion) {
    var fmt = function (n) {
      return Math.round(n).toLocaleString("en-US");
    };
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target;
        io.unobserve(el);
        var target = parseFloat(el.getAttribute("data-count"));
        if (!isFinite(target) || target <= 0) return;
        var final = el.textContent;
        var start = null;
        var dur = 1400;
        var step = function (ts) {
          if (start === null) start = ts;
          var p = Math.min(1, (ts - start) / dur);
          var eased = 1 - Math.pow(1 - p, 3);
          el.textContent = fmt(target * eased);
          if (p < 1) requestAnimationFrame(step);
          else el.textContent = final;
        };
        requestAnimationFrame(step);
      });
    }, { threshold: 0.4 });
    Array.prototype.forEach.call(counters, function (el) { io.observe(el); });
  }

  /* ---- Filters: <div data-filter-group="x"> buttons [data-filter="value|all"] + items [data-x] ---- */
  Array.prototype.forEach.call(document.querySelectorAll("[data-filter-group]"), function (group) {
    var key = group.getAttribute("data-filter-group");
    var target = document.querySelector(group.getAttribute("data-filter-target"));
    if (!target) return;
    var buttons = group.querySelectorAll("[data-filter]");
    var status = group.parentNode.querySelector("[data-filter-status]");
    group.hidden = false;
    Array.prototype.forEach.call(buttons, function (btn) {
      btn.addEventListener("click", function () {
        var value = btn.getAttribute("data-filter");
        var shown = 0;
        Array.prototype.forEach.call(buttons, function (b) {
          b.setAttribute("aria-pressed", b === btn ? "true" : "false");
        });
        Array.prototype.forEach.call(target.children, function (item) {
          var card = item.querySelector("[data-" + key + "]") || item;
          var match = value === "all" || card.getAttribute("data-" + key) === value;
          item.hidden = !match;
          if (match) shown++;
        });
        if (status) status.textContent = status.getAttribute("data-template").replace("{n}", shown);
      });
    });
  });

  /* ---- WhatsApp composer: builds a message and opens wa.me — nothing is stored ---- */
  Array.prototype.forEach.call(document.querySelectorAll("[data-wa-form]"), function (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var name = (form.elements.name && form.elements.name.value || "").trim();
      var topicSel = form.elements.topic;
      var opt = topicSel ? topicSel.options[topicSel.selectedIndex] : null;
      var legal = opt && opt.getAttribute("data-legal") === "1";
      var topic = opt ? opt.text.trim() : "";
      var text = (form.elements.text && form.elements.text.value || "").trim();
      if (!text) {
        form.elements.text.focus();
        return;
      }
      var lines = [form.getAttribute("data-greeting")];
      if (name) lines.push(form.getAttribute("data-name-prefix") + ": " + name);
      if (topic) lines.push(form.getAttribute("data-topic-prefix") + ": " + topic);
      lines.push("", text);
      var number = legal && form.getAttribute("data-wa-legal") ? form.getAttribute("data-wa-legal") : form.getAttribute("data-wa-general");
      var url = "https://wa.me/" + number + "?text=" + encodeURIComponent(lines.join("\n"));
      var win = window.open(url, "_blank", "noopener");
      if (!win) window.location.href = url;
    });
  });

  /* ---- Copy buttons (e.g. official bank details) ---- */
  Array.prototype.forEach.call(document.querySelectorAll("[data-copy]"), function (btn) {
    btn.hidden = !navigator.clipboard;
    btn.addEventListener("click", function () {
      var source = document.getElementById(btn.getAttribute("data-copy"));
      if (!source || !navigator.clipboard) return;
      navigator.clipboard.writeText(source.innerText.trim()).then(function () {
        var label = btn.querySelector("span");
        var original = label.textContent;
        label.textContent = btn.getAttribute("data-copied");
        setTimeout(function () { label.textContent = original; }, 1800);
      });
    });
  });
})();


/* ---- 20-lightbox.js ---- */
/* Accessible photo lightbox for [data-lightbox] galleries (uses <dialog>). */
(function () {
  "use strict";
  var galleries = document.querySelectorAll("[data-lightbox]");
  if (!galleries.length || typeof HTMLDialogElement !== "function") return;

  var lang = document.documentElement;
  var labels = {
    close: lang.getAttribute("data-l-close") || "إغلاق",
    prev: lang.getAttribute("data-l-prev") || "السابق",
    next: lang.getAttribute("data-l-next") || "التالي"
  };

  var dlg = document.createElement("dialog");
  dlg.className = "lightbox";
  dlg.innerHTML =
    '<div class="lightbox__inner"><figure><img alt=""><figcaption class="lightbox__caption"></figcaption></figure></div>' +
    '<button class="lightbox__btn lightbox__close" type="button" aria-label="' + labels.close + '">✕</button>' +
    '<button class="lightbox__btn lightbox__prev" type="button" aria-label="' + labels.prev + '">→</button>' +
    '<button class="lightbox__btn lightbox__next" type="button" aria-label="' + labels.next + '">←</button>';
  document.body.appendChild(dlg);
  var img = dlg.querySelector("img");
  var cap = dlg.querySelector("figcaption");
  var items = [];
  var index = 0;

  function show(i) {
    index = (i + items.length) % items.length;
    var a = items[index];
    var thumb = a.querySelector("img");
    img.src = a.href;
    img.alt = thumb ? thumb.alt : "";
    cap.textContent = a.getAttribute("data-caption") || "";
    var multi = items.length > 1;
    dlg.querySelector(".lightbox__prev").hidden = !multi;
    dlg.querySelector(".lightbox__next").hidden = !multi;
  }

  Array.prototype.forEach.call(galleries, function (g) {
    var links = Array.prototype.slice.call(g.querySelectorAll("a"));
    links.forEach(function (a, i) {
      a.addEventListener("click", function (e) {
        e.preventDefault();
        items = links;
        show(i);
        dlg.showModal();
      });
    });
  });

  dlg.querySelector(".lightbox__close").addEventListener("click", function () { dlg.close(); });
  // RTL: "previous" sits on the right.
  dlg.querySelector(".lightbox__prev").addEventListener("click", function () { show(index - 1); });
  dlg.querySelector(".lightbox__next").addEventListener("click", function () { show(index + 1); });
  dlg.addEventListener("click", function (e) { if (e.target === dlg || e.target.classList.contains("lightbox__inner")) dlg.close(); });
  dlg.addEventListener("keydown", function (e) {
    if (e.key === "ArrowLeft") show(index + 1);
    if (e.key === "ArrowRight") show(index - 1);
  });
  dlg.addEventListener("close", function () { img.removeAttribute("src"); });
})();

