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
