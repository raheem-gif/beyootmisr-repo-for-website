// Screenshots of the admin panel at phone and desktop widths, logged in.
// Usage: node tests/admin/shots.js <node_modules> <baseUrl> <outDir> <username> <password> [widths=390,1280]
// Prints HORIZONTAL OVERFLOW and browser console errors (CSP violations show up there).
const path = require('path');
const fs = require('fs');
const { chromium } = require(path.join(process.argv[2], 'playwright'));
const [,, , base, out, user, pass, widthsArg] = process.argv;
const widths = (widthsArg || '390,1280').split(',').map(Number);
const exe = process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium-1243/chrome-linux64/chrome';
const A = base.replace(/\/$/, '') + '/admin/index.php';

(async () => {
  fs.mkdirSync(out, { recursive: true });
  const browser = await chromium.launch({ executablePath: exe });
  for (const w of widths) {
    const ctx = await browser.newContext({ viewport: { width: w, height: w < 800 ? 844 : 900 }, deviceScaleFactor: 1, reducedMotion: 'reduce' });
    const page = await ctx.newPage();
    const errors = [];
    page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
    page.on('pageerror', e => errors.push(String(e)));
    const shot = async (name) => {
      await page.waitForTimeout(200);
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
      const file = path.join(out, name + '-' + w + '.png');
      await page.screenshot({ path: file, fullPage: true });
      console.log(file + (overflow > 0 ? '  HORIZONTAL OVERFLOW ' + overflow + 'px' : ''));
    };
    await page.goto(A + '?p=login', { waitUntil: 'networkidle' });
    await shot('login');
    await page.fill('#f-username', user);
    await page.fill('#f-password', 'wrong password');
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle');
    await shot('login-error');
    await page.fill('#f-password', pass);
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
    await shot('dashboard');
    if (w < 960) {
      await page.click('[data-menu]');
      await shot('nav-open');
      await page.click('[data-menu]');
    }
    await page.goto(A + '?p=texts', { waitUntil: 'networkidle' });
    await shot('texts');
    await page.goto(A + '?p=texts&open=home#g-home', { waitUntil: 'networkidle' });
    await shot('texts-home');
    await page.goto(A + '?p=texts', { waitUntil: 'networkidle' });
    await page.fill('[data-text-search]', 'واتساب');
    await page.waitForTimeout(400);
    await shot('texts-search');
    await page.goto(A + '?p=texts&open=contact#g-contact', { waitUntil: 'networkidle' });
    await page.click('#g-contact [data-row-add]');
    await shot('texts-contact-faq');
    await page.goto(A + '?p=settings', { waitUntil: 'networkidle' });
    await shot('settings');
    await page.fill('#f-contact-hotline', 'رقم غير صحيح');
    await Promise.all([page.waitForNavigation(), page.click('.adm-savebar button[type=submit]')]);
    await shot('settings-error');
    for (const p of ['users', 'account', 'audit', 'backup', 'stats']) {
      await page.goto(A + '?p=' + p, { waitUntil: 'networkidle' });
      await shot(p);
    }
    if (errors.length) console.log('console errors @' + w + ':\n  ' + errors.join('\n  '));
    await ctx.close();
  }
  await browser.close();
})();
