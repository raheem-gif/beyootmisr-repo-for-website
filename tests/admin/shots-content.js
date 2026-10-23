// Screenshots of the stage-2 content sections, driving the real UI (uploads, per-photo
// descriptions, client-side reordering, story guides, the «منجز» confirmation).
// Usage: node tests/admin/shots-content.js <node_modules> <baseUrl> <outDir> <user> <password> <fixturesDir> [widths=390,1280]
// Fixtures: php tests/admin/make-fixtures.php <fixturesDir>. Run against a SANDBOX copy only.
// Prints HORIZONTAL OVERFLOW and browser console errors (CSP violations show up there).
const path = require('path');
const fs = require('fs');
const { chromium } = require(path.join(process.argv[2], 'playwright'));
const [,, , base, out, user, pass, fixtures, widthsArg] = process.argv;
const widths = (widthsArg || '390,1280').split(',').map(Number);
const exe = process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium-1243/chrome-linux64/chrome';
const A = base.replace(/\/$/, '') + '/admin/index.php';
const fx = (n) => path.join(fixtures, n);
const today = new Date().toISOString().slice(0, 10);

(async () => {
  fs.mkdirSync(out, { recursive: true });
  const browser = await chromium.launch({ executablePath: exe });
  const errors = [];
  const open = async (w) => {
    const ctx = await browser.newContext({ viewport: { width: w, height: w < 800 ? 844 : 900 }, deviceScaleFactor: 1, reducedMotion: 'reduce' });
    const page = await ctx.newPage();
    page.on('console', (m) => { if (m.type() === 'error') errors.push('@' + w + ' ' + m.text()); });
    page.on('pageerror', (e) => errors.push('@' + w + ' ' + String(e)));
    page.on('dialog', (d) => d.accept());
    await page.goto(A + '?p=login', { waitUntil: 'networkidle' });
    await page.fill('#f-username', user);
    await page.fill('#f-password', pass);
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
    return { ctx, page };
  };
  const save = async (page) => {
    await Promise.all([page.waitForNavigation(), page.click('.adm-savebar button[type=submit]')]);
  };

  /* ---------- Seed realistic content through the UI (once) ---------- */
  let projectUrl = '';
  {
    const { ctx, page } = await open(1280);
    // Numbers: two verified values + review date.
    await page.goto(A + '?p=stats', { waitUntil: 'networkidle' });
    await page.fill('#f-updated_at', today);
    await page.fill('#f-period', 'منذ التأسيس حتى ' + today.slice(0, 4));
    await page.fill('#f-items-0-value', '1250');
    await page.fill('#f-items-4-value', '320');
    await page.check('#f-verified');
    await save(page);
    // Project with three photos (per-photo descriptions appear after choosing the files).
    await page.goto(A + '?p=projects&id=new', { waitUntil: 'networkidle' });
    await page.fill('#f-title', 'سقف آمن لبيت في المنيا');
    await page.selectOption('#f-activity', 'maa-wa-namaa');
    await page.fill('#f-location', 'المنيا');
    await page.fill('#f-summary', 'استبدال سقف متهالك بسقف خرساني آمن، مع توثيق مراحل التنفيذ بالصور.');
    await page.check('#f-published');
    await page.setInputFiles('#f-imgs_new', [fx('roof.jpg'), fx('walls.jpg'), fx('boxes.jpg')]);
    await page.fill('#f-imgs_new-0-alt', 'السقف بعد اكتمال الصب');
    await page.fill('#f-imgs_new-1-alt', 'رفع الجدران الداعمة');
    await page.fill('#f-imgs_new-1-cap', 'المرحلة الثانية');
    await page.fill('#f-imgs_new-2-alt', 'تجهيز الخامات في الموقع');
    await save(page);
    projectUrl = page.url();
    // One stage with a photo.
    await page.goto(projectUrl + '&stage=new', { waitUntil: 'networkidle' });
    await page.fill('#f-title', 'تجهيز الموقع وإزالة السقف القديم');
    await page.fill('#f-text', 'أُزيل السقف القديم بأمان وجُهزت الخامات اللازمة.');
    await page.setInputFiles('#f-imgs_new', [fx('water.jpg')]);
    await page.fill('#f-imgs_new-0-alt', 'الموقع بعد إزالة السقف القديم');
    await save(page);
    // A published story.
    await page.goto(A + '?p=stories&id=new', { waitUntil: 'networkidle' });
    await page.selectOption('#f-type', 'roof');
    await page.fill('#f-title', 'بيت صار أكثر أمانًا');
    await page.fill('#f-summary', 'سقف جديد أعاد الطمأنينة لأسرة في المنيا.');
    await page.fill('#f-body', 'كان السقف متهالكًا ولا يحمي من المطر.\n\nبعد التنفيذ صار البيت أكثر أمانًا.');
    await page.selectOption('#f-activity', 'maa-wa-namaa');
    await page.check('#f-published');
    await page.check('#f-privacy_confirmed');
    await save(page);
    // A report.
    await page.goto(A + '?p=reports&id=new', { waitUntil: 'networkidle' });
    await page.fill('#f-title', 'تقرير النشاط السنوي');
    await page.fill('#f-year', String(new Date().getFullYear() - 1));
    await page.fill('#f-description', 'ملخص أنشطة المؤسسة وإنجازاتها خلال العام.');
    await page.setInputFiles('#f-pdf', fx('report.pdf'));
    await page.check('#f-published');
    await page.check('#f-review_confirmed');
    await save(page);
    // Gallery photos for «سلامة».
    await page.goto(A + '?p=galleries&a=salama', { waitUntil: 'networkidle' });
    await page.setInputFiles('#f-imgs_new', [fx('boxes.jpg'), fx('walls.jpg')]);
    await page.fill('#f-imgs_new-0-alt', 'تجهيز حقائب المستلزمات العلاجية');
    await page.fill('#f-imgs_new-1-alt', 'مقر استقبال الطلبات');
    await save(page);
    await ctx.close();
  }

  /* ---------- Screenshots at each width ---------- */
  for (const w of widths) {
    const { ctx, page } = await open(w);
    const shot = async (name) => {
      // Full-page captures do not scroll, so lazy images below the fold would stay blank.
      await page.evaluate(async () => {
        const imgs = Array.from(document.images);
        imgs.forEach((i) => { i.loading = 'eager'; });
        await Promise.all(imgs.map((i) => (i.complete ? null : new Promise((r) => { i.onload = r; i.onerror = r; }))));
      });
      await page.waitForTimeout(250);
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
      const file = path.join(out, name + '-' + w + '.png');
      await page.screenshot({ path: file, fullPage: true });
      console.log(file + (overflow > 0 ? '  HORIZONTAL OVERFLOW ' + overflow + 'px' : ''));
    };
    await page.goto(A + '?p=stats', { waitUntil: 'networkidle' });
    await shot('c-stats');
    await page.goto(A + '?p=projects', { waitUntil: 'networkidle' });
    await shot('c-projects');
    await page.goto(projectUrl, { waitUntil: 'networkidle' });
    await shot('c-project-edit');
    // Client-side reorder + delete mark + «منجز» confirmation.
    await page.click('.adm-img:first-child [data-img-move="down"]');
    await page.check('.adm-img:nth-child(3) [data-img-delete]');
    await page.check('input[name="status"][value="done"]');
    await page.locator('#project-images').scrollIntoViewIfNeeded();
    await shot('c-project-edit-changed');
    await page.goto(A + '?p=projects&id=new', { waitUntil: 'networkidle' });
    await page.setInputFiles('#f-imgs_new', [fx('roof.jpg'), fx('water.jpg')]);
    await shot('c-project-new-files');
    // Server-side validation (English description) → error summary + field errors.
    await page.fill('#f-title', 'مشروع للتجربة');
    await page.selectOption('#f-activity', 'najah');
    await page.fill('#f-imgs_new-0-alt', 'roof photo');
    await page.fill('#f-imgs_new-1-alt', 'water photo');
    await save(page);
    await shot('c-project-error');
    await page.goto(projectUrl + '&stage=0', { waitUntil: 'networkidle' });
    await shot('c-project-stage');
    await page.goto(A + '?p=stories', { waitUntil: 'networkidle' });
    await shot('c-stories');
    await page.goto(A + '?p=stories&id=new', { waitUntil: 'networkidle' });
    await page.selectOption('#f-type', 'mosque');
    await shot('c-story-new');
    await page.goto(A + '?p=reports', { waitUntil: 'networkidle' });
    await shot('c-reports');
    await page.click('.adm-item__title a');
    await page.waitForLoadState('networkidle');
    await shot('c-report-edit');
    await page.goto(A + '?p=galleries', { waitUntil: 'networkidle' });
    await shot('c-galleries');
    await page.goto(A + '?p=galleries&a=salama', { waitUntil: 'networkidle' });
    await shot('c-gallery-salama');
    await page.goto(A, { waitUntil: 'networkidle' });
    await shot('c-dashboard');
    await ctx.close();
  }
  if (errors.length) console.log('console errors:\n  ' + errors.join('\n  '));
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
