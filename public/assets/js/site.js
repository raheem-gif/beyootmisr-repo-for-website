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
      var field = form.elements.text;
      var text = (field && field.value || "").trim();
      if (!text) {
        // Spaces only: clear them so the browser shows its usual "required" message.
        if (field) {
          field.value = "";
          if (field.reportValidity) field.reportValidity();
          else field.focus();
        }
        return;
      }
      var lines = [form.getAttribute("data-greeting")];
      if (name) lines.push(form.getAttribute("data-name-prefix") + ": " + name);
      if (topic) lines.push(form.getAttribute("data-topic-prefix") + ": " + topic);
      lines.push("", text);
      var number = legal && form.getAttribute("data-wa-legal") ? form.getAttribute("data-wa-legal") : form.getAttribute("data-wa-general");
      var url = "https://wa.me/" + number + "?text=" + encodeURIComponent(lines.join("\n"));
      // No "noopener" feature here: with it window.open() always returns null, which
      // looked like a blocked pop-up and opened WhatsApp a second time in this tab.
      var win = window.open(url, "_blank");
      if (win) {
        try { win.opener = null; } catch (err) { /* cross-origin: nothing to clear */ }
      } else {
        window.location.href = url;
      }
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

  // Labels are editable texts carried on <html data-l-*>. They are only ever set
  // with setAttribute/textContent — never parsed as HTML.
  var root = document.documentElement;
  var label = function (name, fallback) {
    return root.getAttribute("data-l-" + name) || fallback;
  };
  var counterText = label("image-of", "{n} / {total}");

  var el = function (tag, cls, text) {
    var node = document.createElement(tag);
    if (cls) node.className = cls;
    if (text) node.textContent = text;
    return node;
  };
  var button = function (cls, name, glyph) {
    var b = el("button", "lightbox__btn " + cls, glyph);
    b.type = "button";
    b.setAttribute("aria-label", name);
    return b;
  };

  var dlg = el("dialog", "lightbox");
  dlg.setAttribute("aria-label", label("gallery", "الصور"));
  var inner = el("div", "lightbox__inner");
  var fig = el("figure");
  var img = el("img");
  img.alt = "";
  var cap = el("figcaption", "lightbox__caption");
  var status = el("p", "sr-only");
  status.setAttribute("aria-live", "polite");
  var closeBtn = button("lightbox__close", label("close", "إغلاق"), "✕");
  var prevBtn = button("lightbox__prev", label("prev", "السابق"), "→");
  var nextBtn = button("lightbox__next", label("next", "التالي"), "←");
  fig.appendChild(img);
  fig.appendChild(cap);
  inner.appendChild(fig);
  dlg.appendChild(inner);
  dlg.appendChild(status);
  dlg.appendChild(closeBtn);
  dlg.appendChild(prevBtn);
  dlg.appendChild(nextBtn);
  document.body.appendChild(dlg);
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
    prevBtn.hidden = !multi;
    nextBtn.hidden = !multi;
    // Announce the position in the set ("صورة 2 من 6") and the caption.
    status.textContent = multi
      ? counterText.replace("{n}", String(index + 1)).replace("{total}", String(items.length)) + (cap.textContent ? "، " + cap.textContent : "")
      : "";
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

  closeBtn.addEventListener("click", function () { dlg.close(); });
  // RTL: "previous" sits on the right.
  prevBtn.addEventListener("click", function () { show(index - 1); });
  nextBtn.addEventListener("click", function () { show(index + 1); });
  dlg.addEventListener("click", function (e) { if (e.target === dlg || e.target.classList.contains("lightbox__inner")) dlg.close(); });
  dlg.addEventListener("keydown", function (e) {
    if (e.key === "ArrowLeft") show(index + 1);
    if (e.key === "ArrowRight") show(index - 1);
  });
  dlg.addEventListener("close", function () {
    img.removeAttribute("src");
    status.textContent = "";
  });
})();

