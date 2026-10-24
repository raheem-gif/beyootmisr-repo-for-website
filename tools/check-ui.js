// Browser regression checks for the shared header, menus, WhatsApp composer and lightbox.
// Usage: node tools/check-ui.js <node_modules with playwright> <baseUrl> [page1.html,page2.html]
// Exits with 1 when a check fails. Runs against any build (no gallery photos needed:
// the lightbox check adds a test gallery to the page it loads).
const path = require('path');
const { chromium } = require(path.join(process.argv[2], 'playwright'));
const base = (process.argv[3] || 'http://127.0.0.1:8080').replace(/\/$/, '');
const pages = (process.argv[4] || 'index.html,about.html,activities.html,contact.html,get-involved.html,ehna-maaki.html').split(',');
const exe = process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium-1243/chrome-linux64/chrome';
let failed = 0;
const check = (ok, name, detail) => {
  console.log((ok ? 'ok   ' : 'FAIL ') + name + (ok || detail === undefined ? '' : ' — ' + detail));
  if (!ok) failed++;
};
const panelShown = (p, id) => p.evaluate(id => {
  const cs = getComputedStyle(document.getElementById(id));
  return cs.visibility === 'visible' && cs.opacity !== '0';
}, id);

(async () => {
  const browser = await chromium.launch({ executablePath: exe });
  const ctx = (w, h, extra) => browser.newContext(Object.assign({ viewport: { width: w, height: h || 844 }, reducedMotion: 'reduce' }, extra || {}));

  // 1) Every page: no horizontal scroll at 360px, no console errors (CSP included).
  for (const w of [360, 1440]) {
    const c = await ctx(w);
    const p = await c.newPage();
    const errors = [];
    p.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
    p.on('pageerror', e => errors.push(String(e)));
    for (const pg of pages) {
      await p.goto(base + '/' + pg, { waitUntil: 'load' });
      const over = await p.evaluate(() => document.documentElement.scrollWidth - innerWidth);
      check(over <= 0, `${pg} @${w}: no horizontal scroll`, over + 'px');
    }
    check(!errors.length, `no console errors @${w}`, errors.slice(0, 3).join(' | '));
    await c.close();
  }

  // 2) Mobile/tablet drawer fills the viewport below the header (backdrop-filter must not
  //    become the containing block of the fixed drawer).
  for (const w of [390, 1024]) {
    const c = await ctx(w);
    const p = await c.newPage();
    await p.goto(base + '/' + pages[0]);
    await p.click('[data-nav-toggle]');
    await p.waitForTimeout(300);
    const r = await p.evaluate(() => {
      const rc = document.querySelector('[data-nav]').getBoundingClientRect();
      const hh = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--header-h'));
      return { h: Math.round(rc.height), want: Math.round(innerHeight - hh) };
    });
    check(Math.abs(r.h - r.want) <= 2, `drawer height @${w}`, `${r.h}px, expected ${r.want}px`);
    await c.close();
  }

  // 3) Desktop sub-menus: only the toggle opens them; Esc and leaving close them.
  {
    const c = await ctx(1440, 900);
    const p = await c.newPage();
    await p.goto(base + '/' + pages[0]);
    await p.focus('.has-sub > .nav-link');
    await p.waitForTimeout(250);
    check(!(await panelShown(p, 'sub-about')), 'focusing «من نحن» does not open its panel');
    await p.focus('[aria-controls=sub-about]');
    await p.keyboard.press('Enter');
    await p.waitForTimeout(250);
    check(await panelShown(p, 'sub-about'), 'toggle opens the panel');
    await p.keyboard.press('Tab');
    await p.keyboard.press('Escape');
    await p.waitForTimeout(250);
    const st = await p.evaluate(() => ({ exp: document.querySelector('[aria-controls=sub-about]').getAttribute('aria-expanded'), back: document.activeElement === document.querySelector('[aria-controls=sub-about]') }));
    check(!(await panelShown(p, 'sub-about')) && st.exp === 'false' && st.back, 'Esc closes the panel and returns focus to its toggle', JSON.stringify(st));
    await p.keyboard.press('Enter');
    for (let i = 0; i < 4; i++) await p.keyboard.press('Tab');
    await p.waitForTimeout(250);
    check(!(await panelShown(p, 'sub-about')), 'tabbing out of the item closes the panel');
    await p.hover('.has-sub > .nav-link[href$="activities.html"]');
    await p.waitForTimeout(250);
    await p.keyboard.press('Escape');
    await p.waitForTimeout(250);
    check(!(await panelShown(p, 'sub-activities')), 'Esc dismisses a hovered panel');
    await c.close();
  }

  // 4) WhatsApp composer: exactly one wa.me navigation, in a new tab; the site tab stays.
  if (pages.includes('contact.html')) {
    const c = await ctx(390);
    const hits = [];
    await c.route('https://wa.me/**', r => { hits.push(r.request().url()); r.fulfill({ status: 200, body: 'wa' }); });
    const p = await c.newPage();
    await p.goto(base + '/contact.html');
    const form = await p.$('[data-wa-form]');
    if (form) {
      await p.fill('[data-wa-form] textarea', '   ');
      await p.click('[data-wa-form] [type=submit]');
      await p.waitForTimeout(200);
      check(hits.length === 0 && (await p.evaluate(() => !document.querySelector('[data-wa-form] textarea').validity.valid)), 'spaces-only message is refused with the required message');
      await p.fill('[data-wa-form] textarea', 'اختبار');
      await p.click('[data-wa-form] [type=submit]');
      await p.waitForTimeout(800);
      check(hits.length === 1 && p.url().startsWith(base) && c.pages().length === 2, 'one WhatsApp tab, site tab kept', `${hits.length} requests, tab at ${p.url()}`);
    }
    await c.close();
  }

  // 5) Lightbox labels are text, never HTML (they are editable texts on <html data-l-*>).
  {
    const c = await ctx(1280, 800);
    const payload = 'x"><img src=x onerror="document.documentElement.dataset.pwned=1">';
    const attr = payload.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    await c.route(base + '/' + pages[0], async route => {
      const res = await route.fetch();
      let html = await res.text();
      html = html.replace(/data-l-close="[^"]*"/, `data-l-close="${attr}"`).replace(/data-l-gallery="[^"]*"/, `data-l-gallery="${attr}"`)
        .replace('<main id="main" tabindex="-1">', '<main id="main" tabindex="-1"><ul data-lightbox="t"><li><a href="assets/img/og-image.png" data-caption="1"><img alt="t1" src="assets/img/og-image.png"></a></li><li><a href="assets/img/og-image.png" data-caption="2"><img alt="t2" src="assets/img/og-image.png"></a></li></ul>');
      route.fulfill({ response: res, body: html });
    });
    const p = await c.newPage();
    await p.goto(base + '/' + pages[0]);
    await p.click('[data-lightbox] a');
    await p.waitForTimeout(400);
    const r = await p.evaluate(() => ({
      pwned: document.documentElement.dataset.pwned || '',
      imgs: document.querySelectorAll('dialog.lightbox img').length,
      label: document.querySelector('.lightbox__close').getAttribute('aria-label'),
      name: document.querySelector('dialog.lightbox').getAttribute('aria-label'),
      live: (document.querySelector('dialog.lightbox [aria-live]') || {}).textContent || '',
    }));
    check(!r.pwned && r.imgs === 1 && r.label === payload && r.name === payload, 'lightbox labels are set as text', JSON.stringify(r));
    check(/1/.test(r.live) && /2/.test(r.live), 'lightbox announces the position in the set', r.live);
    const cap = await p.evaluate(() => {
      const a = document.querySelector('.lightbox__caption').getBoundingClientRect();
      return ['.lightbox__prev', '.lightbox__next'].some(s => { const b = document.querySelector(s).getBoundingClientRect(); return !(a.right <= b.left || b.right <= a.left || a.bottom <= b.top || b.bottom <= a.top); });
    });
    check(!cap, 'lightbox caption is not covered by the arrows');
    await c.close();
  }

  await browser.close();
  console.log(failed ? `\n${failed} check(s) failed` : '\nall checks passed');
  process.exit(failed ? 1 : 0);
})();
