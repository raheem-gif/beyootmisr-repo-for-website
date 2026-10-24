// Renders favicon PNGs and the Open Graph image from the site's own SVG/HTML.
// Usage: node tools/make-brand-images.js <path-to-node_modules-with-playwright>
const path = require('path');
const { chromium } = require(path.join(process.argv[2], 'playwright'));
const root = path.resolve(__dirname, '..', 'public');
const exe = process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium-1243/chrome-linux64/chrome';
(async () => {
  const browser = await chromium.launch({ executablePath: exe });
  const page = await browser.newPage();
  const favSvg = require('fs').readFileSync(path.join(root, 'assets/img/favicon.svg'), 'utf8');
  await page.setViewportSize({ width: 600, height: 600 });
  for (const [size, name, pad, bg] of [[32, 'favicon-32.png', 0, 'transparent'], [180, 'apple-touch-icon.png', 22, '#FFFFFF'], [512, 'icon-512.png', 64, '#FFFFFF']]) {
    const inner = size - pad * 2;
    await page.setContent(`<html><body style="margin:0;background:transparent"><div id="i" style="width:${size}px;height:${size}px;background:${bg};display:grid;place-items:center">${favSvg.replace('<svg ', `<svg width="${inner}" height="${inner}" `)}</div></body></html>`);
    await page.locator('#i').screenshot({ path: path.join(root, 'assets/img', name), omitBackground: bg === 'transparent', timeout: 10000 });
  }
  // OG image: skyline art + slogan, using the built home page's stylesheet and fonts.
  await page.setViewportSize({ width: 1200, height: 630 });
  await page.goto('file://' + path.join(root, 'index.html'));
  const svg = await page.$eval('.skyline', el => el.outerHTML);
  // core.css carries the font and tokens; home.css the skyline colours.
  const css = ['core', 'home'].map(n => `<link rel="stylesheet" href="file://${path.join(root, 'assets/css', n + '.css')}">`).join('');
  await page.setContent(`<!doctype html><html lang="ar" dir="rtl"><head>${css}<style>
    body{margin:0;width:1200px;height:630px;overflow:hidden;background:linear-gradient(180deg,#EEF6FB,#fff);display:grid;grid-template-columns:1.1fr .9fr;align-items:end;font-family:Cairo}
    .t{padding:0 72px 96px 24px} .t p{color:#1572A6;font-weight:700;font-size:28px;margin:0 0 12px}
    .t h1{font-size:64px;line-height:1.25;color:#1984C0;margin:0} .t h2{font-size:30px;color:#122A3D;margin:18px 0 0;font-weight:700}
    .a svg{width:100%;height:auto} .skyline .lit{animation:none!important;fill:#EFA927}
  </style></head><body><div class="t"><p>مؤسسة بيوت مصر لدعم الأرامل والأيتام</p><h1>بيوت مصر.. حكاية من مليون حكاية</h1><h2>من أسوان للسلوم.. مع كل بيوت مصر</h2></div><div class="a">${svg}</div></body></html>`);
  await page.waitForTimeout(400);
  await page.screenshot({ path: path.join(root, 'assets/img/og-image.png') });
  await browser.close();
  console.log('brand images written');
})();
