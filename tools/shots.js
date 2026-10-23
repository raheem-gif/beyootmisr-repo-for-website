// Full-page screenshots of built pages at phone/tablet/desktop widths.
// Usage: node tools/shots.js <node_modules> <baseUrl> <outDir> <page1.html,page2.html> [widths=390,1440]
const path = require('path');
const fs = require('fs');
const { chromium } = require(path.join(process.argv[2], 'playwright'));
const [,, , base, out, pagesArg, widthsArg] = process.argv;
const widths = (widthsArg || '390,1440').split(',').map(Number);
const exe = process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium-1243/chrome-linux64/chrome';
(async () => {
  fs.mkdirSync(out, { recursive: true });
  const browser = await chromium.launch({ executablePath: exe });
  for (const w of widths) {
    const ctx = await browser.newContext({ viewport: { width: w, height: w < 800 ? 844 : 900 }, deviceScaleFactor: 1, reducedMotion: 'reduce' });
    const page = await ctx.newPage();
    const errors = [];
    page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
    page.on('pageerror', e => errors.push(String(e)));
    for (const p of pagesArg.split(',')) {
      await page.goto(base.replace(/\/$/, '') + '/' + p, { waitUntil: 'networkidle' });
      await page.waitForTimeout(250);
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
      const file = path.join(out, p.replace(/[\/#?]/g, '_').replace(/\.html$/, '') + '-' + w + '.png');
      await page.screenshot({ path: file, fullPage: true });
      console.log(`${file}${overflow > 0 ? '  HORIZONTAL OVERFLOW ' + overflow + 'px' : ''}`);
    }
    if (errors.length) console.log('console errors @' + w + ':\n  ' + errors.join('\n  '));
    await ctx.close();
  }
  await browser.close();
})();
