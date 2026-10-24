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
