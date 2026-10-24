/* Beyoot Misr — admin panel behaviour (no inline JS: the CSP only allows this file).
   Progressive enhancement: every form works without JavaScript. */
(function () {
  'use strict';
  var d = document;
  var root = d.documentElement;
  root.classList.add('js');

  function ready(fn) {
    if (d.readyState !== 'loading') { fn(); } else { d.addEventListener('DOMContentLoaded', fn); }
  }
  function $all(sel, ctx) { return Array.prototype.slice.call((ctx || d).querySelectorAll(sel)); }
  function closest(el, sel) {
    while (el && el.nodeType === 1) { if (el.matches(sel)) { return el; } el = el.parentElement; }
    return null;
  }

  /* Arabic-friendly search normalisation: no diacritics, unified alef/yaa/taa marbuta. */
  function norm(s) {
    return String(s || '')
      .toLowerCase()
      .replace(/[ً-ٰٟـ]/g, '')
      .replace(/[أإآٱ]/g, 'ا')
      .replace(/ى/g, 'ي')
      .replace(/ة/g, 'ه')
      .replace(/\s+/g, ' ')
      .trim();
  }

  /* Arabic count + noun, same rules as adm_plural() in app/admin/ui.php:
     plural(n, 'حرف واحد', 'حرفان', 'أحرف', 'حرفًا') → «حرف واحد», «حرفان», «5 أحرف», «120 حرفًا», «100 حرف». */
  function plural(n, one, two, few, many) {
    if (n === 1) { return one; }
    if (n === 2) { return two; }
    var r = n % 100;
    if (r >= 3 && r <= 10) { return n + ' ' + few; }
    if (r >= 11) { return n + ' ' + many; }
    return n + ' ' + many.replace(/(?:\u064B\u0627|\u0627\u064B)$/, '');
  }

  ready(function () {
    var body = d.body;

    /* ---- Mobile navigation ---- */
    var menuBtn = d.querySelector('[data-menu]');
    var nav = d.getElementById('adm-nav');
    if (menuBtn && nav) {
      menuBtn.addEventListener('click', function () {
        var open = nav.classList.toggle('is-open');
        menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
      d.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && nav.classList.contains('is-open')) {
          nav.classList.remove('is-open');
          menuBtn.setAttribute('aria-expanded', 'false');
          menuBtn.focus();
        }
      });
    }

    /* ---- Dirty forms ---- */
    var leaving = false;
    function markDirty(form) {
      if (!form || !form.hasAttribute('data-dirty')) { return; }
      form.setAttribute('data-changed', '1');
      $all('[data-dirty-note]', form).forEach(function (n) { n.hidden = false; });
    }
    d.addEventListener('input', function (e) { markDirty(closest(e.target, 'form')); });
    d.addEventListener('change', function (e) { markDirty(closest(e.target, 'form')); });
    window.addEventListener('beforeunload', function (e) {
      if (leaving) { return; }
      if (d.querySelector('form[data-changed]')) {
        e.preventDefault();
        e.returnValue = '';
      }
    });

    /* ---- Submit: confirm, unsaved work elsewhere, busy state ---- */
    d.addEventListener('submit', function (e) {
      var form = e.target;
      var sub = e.submitter || form.querySelector('[type=submit]');
      if (form.getAttribute('data-submitting')) { e.preventDefault(); return; }
      var msg = (sub && sub.getAttribute('data-confirm')) || form.getAttribute('data-confirm');
      if (msg && !window.confirm(msg)) { e.preventDefault(); return; }
      var others = $all('form[data-changed]').filter(function (f) { return f !== form; });
      if (others.length && form.method.toLowerCase() === 'post' &&
          !window.confirm('لديك تعديلات غير محفوظة في قسم آخر من هذه الصفحة وستضيع إذا تابعت. هل تريد المتابعة؟')) {
        e.preventDefault();
        return;
      }
      leaving = true;
      form.setAttribute('data-submitting', '1');
      if (sub) {
        window.setTimeout(function () {
          sub.disabled = true;
          sub.setAttribute('aria-busy', 'true');
          var label = sub.querySelector('span');
          if (label && sub.getAttribute('data-busy')) { label.textContent = sub.getAttribute('data-busy'); }
        }, 0);
      }
    });
    window.addEventListener('pageshow', function (e) {
      if (e.persisted) {
        leaving = false;
        $all('form[data-submitting]').forEach(function (f) { f.removeAttribute('data-submitting'); });
        $all('[aria-busy=true]').forEach(function (b) { b.disabled = false; b.removeAttribute('aria-busy'); });
      }
    });

    /* ---- Repeaters (add / remove rows from a <template>) ---- */
    function renumber(rep) {
      var prefix = rep.getAttribute('data-legend') || '';
      $all('[data-rows] > [data-row]', rep).forEach(function (row, i) {
        var t = row.querySelector('[data-row-title]');
        if (t) { t.textContent = prefix + ' ' + (i + 1); }
      });
      var empty = rep.querySelector('[data-empty]');
      if (empty) { empty.hidden = rep.querySelectorAll('[data-rows] > [data-row]').length > 0; }
    }
    d.addEventListener('click', function (e) {
      var add = closest(e.target, '[data-row-add]');
      if (add) {
        var rep = closest(add, '[data-repeat]');
        var rows = rep.querySelector('[data-rows]');
        var tpl = rep.querySelector('template[data-template]');
        var existing = rows.querySelectorAll('[data-row]');
        var max = parseInt(rep.getAttribute('data-max') || '50', 10);
        if (existing.length >= max) { window.alert('وصلت إلى الحد الأقصى (' + max + ').'); return; }
        var key = 0;
        Array.prototype.forEach.call(existing, function (r) {
          var k = parseInt(r.getAttribute('data-row-key'), 10);
          if (!isNaN(k) && k >= key) { key = k + 1; }
        });
        var wrap = d.createElement('div');
        wrap.innerHTML = tpl.innerHTML.replace(/__i__/g, String(key));
        var row = wrap.firstElementChild;
        rows.appendChild(row);
        renumber(rep);
        markDirty(closest(rep, 'form'));
        var first = row.querySelector('input, textarea, select');
        if (first) { first.focus(); }
        return;
      }
      var rm = closest(e.target, '[data-row-remove]');
      if (rm) {
        var msg = rm.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) { return; }
        var rep2 = closest(rm, '[data-repeat]');
        var row2 = closest(rm, '[data-row]');
        var form = closest(rep2, 'form');
        row2.parentNode.removeChild(row2);
        renumber(rep2);
        markDirty(form);
        var addBtn = rep2.querySelector('[data-row-add]');
        if (addBtn) { addBtn.focus(); }
        return;
      }
      var opener = closest(e.target, '[data-open-group]');
      if (opener) {
        var g = d.getElementById(opener.getAttribute('data-open-group'));
        if (g) { g.open = true; }
      }
      if (closest(e.target, '[data-expand-all]')) {
        $all('details[data-group]').forEach(function (x) { x.open = true; });
      }
      if (closest(e.target, '[data-collapse-all]')) {
        $all('details[data-group]').forEach(function (x) { x.open = false; });
      }
    });

    /* ---- Open the group named in the URL hash ---- */
    if (location.hash && /^#g-[\w-]+$/.test(location.hash)) {
      var target = d.getElementById(location.hash.slice(1));
      if (target && target.tagName === 'DETAILS') { target.open = true; target.scrollIntoView(); }
    }

    /* ---- Texts search ---- */
    var search = d.querySelector('[data-text-search]');
    if (search) {
      var status = d.querySelector('[data-search-status]');
      var emptyMsg = d.querySelector('[data-search-empty]');
      var groups = $all('details[data-group]');
      var fields = $all('[data-tfield]');
      var timer = null;
      var haystack = function (f) {
        var parts = [f.getAttribute('data-key') || ''];
        $all('label, legend, summary', f).forEach(function (l) { parts.push(l.textContent); });
        $all('input:not([type=hidden]), textarea', f).forEach(function (c) { parts.push(c.value); });
        return norm(parts.join(' '));
      };
      var run = function () {
        var q = norm(search.value);
        var total = 0;
        var inGroups = 0;
        fields.forEach(function (f) {
          var hit = q === '' || haystack(f).indexOf(q) !== -1;
          f.hidden = !hit;
          if (hit && q !== '') { total++; }
        });
        $all('.adm-groups details[data-fold], .adm-groups fieldset.adm-sub').forEach(function (box) {
          var visible = q === '' || box.querySelector('[data-tfield]:not([hidden])') !== null || (box.hasAttribute('data-tfield') && !box.hidden);
          box.hidden = !visible;
          if (box.tagName === 'DETAILS' && q !== '' && visible) { box.open = true; }
        });
        groups.forEach(function (g) {
          var visible = q === '' || g.querySelector('[data-tfield]:not([hidden])') !== null;
          g.hidden = !visible;
          if (q !== '' && visible) { g.open = true; inGroups++; }
        });
        if (status) {
          status.textContent = q === '' ? '' : (total === 0 ? 'لا توجد نتائج.' : 'الحقول المطابقة: ' + total + ' · الأقسام: ' + inGroups);
        }
        if (emptyMsg) { emptyMsg.hidden = !(q !== '' && total === 0); }
      };
      search.addEventListener('input', function () {
        window.clearTimeout(timer);
        timer = window.setTimeout(run, 160);
      });
      search.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); run(); }
        if (e.key === 'Escape') { search.value = ''; run(); }
      });
    }

    /* ---- Textareas grow with their content (CSSOM styles are allowed by the CSP) ---- */
    var autosize = function (t) {
      if (!t.offsetParent) { return; }
      t.style.height = 'auto';
      t.style.height = Math.min(t.scrollHeight + 4, Math.round(window.innerHeight * 0.7)) + 'px';
    };
    var sizeAll = function (ctx) { $all('.adm-field textarea', ctx).forEach(autosize); };
    d.addEventListener('input', function (e) {
      if (e.target.tagName === 'TEXTAREA') { autosize(e.target); }
    });
    $all('details').forEach(function (det) {
      det.addEventListener('toggle', function () { if (det.open) { sizeAll(det); } });
    });
    d.addEventListener('click', function (e) {
      if (closest(e.target, '[data-row-add]')) { window.setTimeout(function () { sizeAll(d); }, 0); }
    });
    sizeAll(d);
    var resizeTimer = null;
    window.addEventListener('resize', function () {
      window.clearTimeout(resizeTimer);
      resizeTimer = window.setTimeout(function () { sizeAll(d); }, 200);
    });

    /* ---- Character counters ---- */
    $all('[data-counter]').forEach(function (c) {
      var out = d.createElement('span');
      out.className = 'adm-counter';
      out.setAttribute('aria-hidden', 'true');
      c.parentNode.insertBefore(out, c.nextSibling);
      var upd = function () { out.textContent = plural(c.value.length, 'حرف واحد', 'حرفان', 'أحرف', 'حرفًا'); };
      c.addEventListener('input', upd);
      upd();
    });

    /* ---- Show / hide password ---- */
    $all('input[type=password][data-reveal]').forEach(function (inp) {
      var b = d.createElement('button');
      b.type = 'button';
      b.className = 'adm-reveal';
      b.textContent = 'إظهار';
      b.setAttribute('aria-pressed', 'false');
      b.setAttribute('aria-label', 'إظهار كلمة المرور');
      var wrap = d.createElement('span');
      wrap.className = 'adm-reveal-wrap';
      inp.parentNode.insertBefore(wrap, inp);
      wrap.appendChild(inp);
      wrap.appendChild(b);
      b.addEventListener('click', function () {
        var show = inp.type === 'password';
        inp.type = show ? 'text' : 'password';
        b.textContent = show ? 'إخفاء' : 'إظهار';
        b.setAttribute('aria-pressed', show ? 'true' : 'false');
      });
    });

    /* ---- Image previews (data: URLs are allowed by the CSP) ----
       With data-each="<prefix>" (image lists) every chosen photo gets its own required
       description + optional caption (<prefix>_alt_each[i], <prefix>_caption_each[i]),
       and the shared description fields are hidden. */
    var field = function (labelText, name, id, max, required) {
      var wrap = d.createElement('div');
      wrap.className = 'adm-field';
      var lab = d.createElement('label');
      lab.className = 'adm-label';
      lab.setAttribute('for', id);
      lab.textContent = labelText;
      if (required) {
        var star = d.createElement('span');
        star.className = 'adm-req';
        star.setAttribute('aria-hidden', 'true');
        star.textContent = ' *';
        lab.appendChild(star);
      }
      var inp = d.createElement('input');
      inp.type = 'text';
      inp.id = id;
      inp.name = name;
      inp.maxLength = max;
      if (required) { inp.required = true; }
      wrap.appendChild(lab);
      wrap.appendChild(inp);
      return wrap;
    };
    $all('input[type=file][data-preview]').forEach(function (inp) {
      var box = d.getElementById(inp.getAttribute('data-preview'));
      if (!box || !window.FileReader) { return; }
      var each = inp.getAttribute('data-each');
      var maxFiles = parseInt(inp.getAttribute('data-max-files') || '0', 10);
      var upload = closest(inp, '.adm-upload');
      var shared = upload ? upload.querySelector('[data-shared-alt]') : null;
      inp.addEventListener('change', function () {
        box.innerHTML = '';
        var all = Array.prototype.slice.call(inp.files || []);
        var files = each ? all : all.slice(0, 12);
        box.hidden = files.length === 0;
        if (shared) { shared.hidden = !!(each && files.length); }
        if (maxFiles && all.length > maxFiles) {
          var warn = d.createElement('p');
          warn.className = 'adm-error adm-newimgs__warn';
          warn.setAttribute('role', 'alert');
          warn.textContent = 'اخترت ' + plural(all.length, 'صورة واحدة', 'صورتين', 'صور', 'صورة') + '، والحد في المرة الواحدة '
            + plural(maxFiles, 'صورة واحدة', 'صورتان', 'صور', 'صورة') + '. اختر عددًا أقل ثم أضف الباقي بعد الحفظ.';
          box.appendChild(warn);
        }
        files.forEach(function (file, i) {
          var fig = d.createElement(each ? 'div' : 'figure');
          var cap = d.createElement(each ? 'p' : 'figcaption');
          var big = file.size > 10 * 1024 * 1024;
          var okType = /^image\/(jpeg|png|webp)$/.test(file.type);
          cap.textContent = file.name + (big ? ' — أكبر من 10 ميجابايت ولن يُقبل' : '');
          if (each) {
            fig.className = 'adm-newimg';
            cap.className = 'adm-newimg__name';
          }
          if (big) { fig.className += ' is-error'; }
          if (okType) {
            var img = d.createElement('img');
            img.alt = '';
            var reader = new FileReader();
            reader.onload = function () { img.src = String(reader.result); };
            reader.readAsDataURL(file);
            fig.appendChild(img);
          } else {
            fig.className += ' is-error';
            cap.textContent = file.name + ' — نوع غير مسموح (JPG أو PNG أو WebP فقط) ولن يُرفع';
          }
          fig.appendChild(cap);
          if (each && okType && !big) {
            var base = inp.id + '-' + i;
            fig.appendChild(field('وصف هذه الصورة (بالعربية)', each + '_alt_each[' + i + ']', base + '-alt', 250, true));
            fig.appendChild(field('تعليق تحت الصورة (اختياري)', each + '_caption_each[' + i + ']', base + '-cap', 300, false));
          }
          box.appendChild(fig);
        });
        var firstAlt = box.querySelector('input[required]');
        if (firstAlt && each) { window.setTimeout(function () { firstAlt.focus(); }, 0); }
      });
    });

    /* ---- Image lists: reorder («تقديم» / «تأخير») and mark for deletion, without a round trip ---- */
    var renumberImgs = function (list) {
      var rows = Array.prototype.filter.call(list.children, function (c) { return c.hasAttribute('data-img-row'); });
      rows.forEach(function (r, i) {
        var n = i + 1;
        var num = r.querySelector('[data-img-num]');
        if (num) { num.textContent = String(n); }
        var up = r.querySelector('[data-img-move="up"]');
        var dn = r.querySelector('[data-img-move="down"]');
        if (up) { up.disabled = i === 0; up.setAttribute('aria-label', 'تقديم الصورة ' + n); }
        if (dn) { dn.disabled = i === rows.length - 1; dn.setAttribute('aria-label', 'تأخير الصورة ' + n); }
        var alt = r.querySelector('[data-img-label]');
        var lab = alt ? d.querySelector('label[for="' + alt.id + '"]') : null;
        if (lab) { lab.textContent = alt.getAttribute('data-img-label') + ' ' + n; }
      });
      return rows.length;
    };
    d.addEventListener('click', function (e) {
      var mv = closest(e.target, '[data-img-move]');
      if (!mv) { return; }
      e.preventDefault();
      var row = closest(mv, '[data-img-row]');
      var list = row ? row.parentNode : null;
      if (!list) { return; }
      if (mv.getAttribute('data-img-move') === 'up') {
        if (row.previousElementSibling) { list.insertBefore(row, row.previousElementSibling); }
      } else if (row.nextElementSibling) {
        list.insertBefore(row.nextElementSibling, row);
      }
      var total = renumberImgs(list);
      markDirty(closest(list, 'form'));
      var target = mv.disabled ? row.querySelector('[data-img-move]:not([disabled])') : mv;
      if (target) { target.focus(); }
      var status = closest(list, '[data-imgs]');
      status = status ? status.querySelector('[data-img-status]') : null;
      if (status) {
        var pos = Array.prototype.indexOf.call(list.children, row) + 1;
        status.textContent = 'الصورة الآن في الموضع ' + pos + ' من ' + total + '. اضغط «حفظ» لاعتماد الترتيب.';
      }
    });
    d.addEventListener('change', function (e) {
      if (e.target.matches && e.target.matches('[data-img-delete]')) {
        var row = closest(e.target, '[data-img-row]');
        if (row) { row.classList.toggle('is-deleted', e.target.checked); }
      }
    });

    /* ---- Story type → show that type's writing guide ---- */
    $all('[data-guide-select]').forEach(function (sel) {
      var scope = closest(sel, 'form') || d;
      var upd = function () {
        $all('[data-guide]', scope).forEach(function (g) { g.hidden = g.getAttribute('data-guide') !== sel.value; });
      };
      sel.addEventListener('change', upd);
      upd();
    });

    /* ---- Number cards: live icon preview ---- */
    var iconSet = d.querySelector('[data-icon-set]');
    if (iconSet) {
      d.addEventListener('change', function (e) {
        if (!e.target.matches || !e.target.matches('[data-icon-select]')) { return; }
        var row = closest(e.target, '[data-row]');
        var prev = row ? row.querySelector('[data-icon-preview]') : null;
        var src = null;
        Array.prototype.forEach.call(iconSet.children, function (c) {
          if (c.getAttribute('data-icon') === e.target.value) { src = c; }
        });
        if (prev && src) { prev.innerHTML = src.innerHTML; }
      });
    }

    /* ---- Project status: the «تم إثبات الإنجاز الفعلي» confirmation only matters for «منجز» ---- */
    $all('[data-status-choice]').forEach(function (fs) {
      var form = closest(fs, 'form');
      var box = form ? form.querySelector('[data-done-confirm]') : null;
      if (!box) { return; }
      var upd = function () {
        var c = fs.querySelector('input[name="status"]:checked');
        box.hidden = !c || c.value !== 'done';
      };
      fs.addEventListener('change', upd);
      upd();
    });

    /* ---- Move focus to error summaries for screen-reader users ---- */
    var focusMe = d.querySelector('[data-focus]');
    if (focusMe) {
      var grp = closest(focusMe, 'details');
      if (grp) { grp.open = true; }
      focusMe.focus();
    }

    /* ---- Keep an active editor's session alive; warn before it expires ---- */
    if (body.classList.contains('adm') && !body.classList.contains('adm-plain')) {
      var IDLE = 30 * 60 * 1000;
      var lastServer = Date.now();
      var lastPing = Date.now();
      var banner = null;
      var showBanner = function (text) {
        if (!banner) {
          banner = d.createElement('div');
          banner.className = 'adm-session-banner';
          banner.setAttribute('role', 'alert');
          body.appendChild(banner);
        }
        banner.textContent = text;
      };
      var ping = function () {
        lastPing = Date.now();
        if (!window.fetch) { return; }
        window.fetch('index.php?p=ping', { credentials: 'same-origin', cache: 'no-store' }).then(function (r) {
          if (r.status === 204) {
            lastServer = Date.now();
            if (banner) { banner.parentNode.removeChild(banner); banner = null; }
          } else if (r.status === 401) {
            showBanner('انتهت جلستك. انسخ أي نص لم تحفظه، ثم أعد تحميل الصفحة وسجّل الدخول من جديد.');
          }
        }).catch(function () {});
      };
      var activity = function () {
        if (Date.now() - lastPing > 4 * 60 * 1000) { ping(); }
      };
      d.addEventListener('input', activity);
      d.addEventListener('keydown', activity);
      d.addEventListener('click', activity);
      window.setInterval(function () {
        var idle = Date.now() - lastServer;
        if (idle > IDLE - 3 * 60 * 1000 && idle < IDLE) {
          showBanner('ستنتهي جلستك خلال دقائق بسبب عدم النشاط. احفظ عملك الآن.');
        } else if (idle >= IDLE) {
          showBanner('انتهت جلستك بسبب عدم النشاط. انسخ أي نص لم تحفظه، ثم أعد تحميل الصفحة وسجّل الدخول من جديد.');
        }
      }, 30000);
    }
  });
})();
