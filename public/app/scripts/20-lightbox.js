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
