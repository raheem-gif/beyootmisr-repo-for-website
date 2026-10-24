# موقع مؤسسة بيوت مصر لدعم الأرامل والأيتام

**«حكاية من بيوت مصر.. حكاية من مليون حكاية» — من أسوان للسلوم.. مع كل بيوت مصر**

The official website of Beyoot Misr Foundation, built from the foundation's content
document («المحتوى التحريري والتقسيم الكامل للموقع الإلكتروني»، أكتوبر 2026). Arabic, RTL,
mobile-first, fast, and ready to upload to **Turbify** hosting.

---

## ملخص بالعربية

- موقع تعريفي عربي كامل بكل نصوص ملف المحتوى حرفيًا، بألوان الهوية (الأزرق `#1984C0` والأصفر `#EFA927`) وخلفية بيضاء ومساحات مريحة.
- الصفحات: الرئيسية، من نحن، ما نؤمن به (الميثاق)، أنشطتنا ومبادراتنا + صفحة لكل نشاط من الأنشطة العشرة، مبادرة «إحنا معاكي»، حكايات من بيوت مصر، إنجازاتنا ومشروعاتنا + صفحة لكل مشروع، شارك في الخير، تواصل معنا، سياسة الخصوصية، وصفحة 404.
- زرّا «تبرع الآن» و«اطلب مساعدة» ثابتان أعلى كل صفحة، وزر «تواصل واتساب» ظاهر دائمًا على الهاتف.
- لا تُعرض أي أرقام غير موثقة: بطاقات «أثرنا بالأرقام» تظهر «يُعلن بعد التوثيق» حتى تُدخل الأرقام وتاريخ مراجعتها من لوحة الإدارة.
- لا صور نمطية ولا وجوه مستفيدين: رسومات خاصة بالهوية (بيوت مصرية تضيء نوافذها) لحين إضافة صور حقيقية معتمدة.
- لوحة إدارة عربية بسيطة (`/admin/`) لتحديث النصوص والأرقام والمشروعات وصورها والحكايات والتقارير والإعدادات دون تعديل الكود. الصور تُعاد معالجتها تلقائيًا لحذف بيانات الموقع الجغرافي (GPS) حفاظًا على الخصوصية.
- دليل استخدام لوحة الإدارة بالعربية: [`docs/ADMIN.md`](docs/ADMIN.md).

---

## 1. Upload to Turbify (first installation)

Turbify Web Hosting uses **cPanel**. The public web folder is **`public_html`**.

1. Get the package **`dist/beyoot-misr-site-full.zip`** — create it with `tools/package.sh`
   (needs PHP, Python 3 and `zip`), or download this repository as a ZIP from GitHub and
   use the contents of its `public/` folder (zip them, or upload them as they are).
2. In Turbify: **My Services → Hosting → File Manager** (in the *Files* section). Open `public_html`.
3. **Upload** the zip, then right-click it → **Extract** into `public_html`. Delete the zip afterwards.
   *No Extract option?* Unzip on your computer and upload the contents of the `public/`
   folder with an FTPS client (FileZilla: host `ftp.yourdomain`, port 21, "explicit FTP over TLS").
   Make sure hidden files (`.htaccess`) are uploaded too.
4. Open your domain — the site is live. Nothing else is required for the public site
   (it is plain HTML; it does not need PHP or a database).

> **If you see "500 Internal Server Error"** right after uploading, delete the file
> `public_html/.htaccess` (Turbify supports `.htaccess` only partially). The site works
> without it; the file only adds caching/compression and a custom 404 page.

### Set up the admin panel (needs PHP 7.4+, recommended 8.2+)

1. In cPanel → **MultiPHP Manager**, select PHP **8.2 or newer** for the domain.
2. With File Manager, create an **empty file** named `setup.allow` inside
   `public_html/app/storage/`. (This proves you control the hosting account.)
3. Open `https://your-domain/admin/` and create the first administrator account
   (password ≥ 10 characters). The `setup.allow` file is deleted automatically.
4. Log in, open **الإعدادات** (Settings) and fill in:
   - **رابط الموقع** — e.g. `https://www.your-domain.org` (enables canonical URLs and `sitemap.xml`).
   - The official **logo** (PNG/JPG) — replaces the temporary house mark.
   - Email, Facebook/Instagram links, message hours, detailed address — each appears
     on the site only once it is filled in.
5. If saving fails with a permissions message: in File Manager set folders to `755` and
   files to `644`; PHP must be able to write to `public_html` (pages are regenerated),
   `public_html/app/content`, `public_html/app/storage` and `public_html/uploads`.

## 2. Updating the code later

After launch, **the server is the source of truth for content** (texts, numbers, projects,
stories and photos edited in the admin). To deploy a new version of the code without
overwriting that content:

1. Upload **`dist/beyoot-misr-code-update.zip`** to `public_html` and extract it
   (it excludes `app/content`, `uploads`, `app/storage`, the generated pages and the root
   `.htaccess` — so a `.htaccess` you deleted because of a 500 error is not brought back;
   if you do use it, copy changes from `public/.htaccess` by hand).
   It includes the current default texts (`app/lib/texts-defaults.json.php`): a text that a
   new version adds appears with the document's wording until you edit it; texts already
   edited in the admin are never replaced.
2. In the admin dashboard press **«إعادة بناء الموقع»** to regenerate all pages.

Use **نسخة احتياطية** in the admin panel to download a backup (content + uploads) before any update.

## 3. Before launch — checklist (content document §13)

| Item from the document | Where to complete it |
|---|---|
| مطابقة رقم الإشهار والاسم القانوني مع المستندات الرسمية | Admin → الإعدادات → الهوية القانونية (currently **11108 لسنة 2020** from the document — verify against the original certificate) |
| إثبات الصفة الرسمية للنفع العام وتصريح جمع التبرعات بأرقامها ونطاقها وتواريخ صلاحيتها | Admin → الإعدادات → الهوية القانونية (fields stay hidden until filled) |
| اعتماد أرقام الإنجازات والفترة الزمنية لكل رقم | Admin → أثرنا بالأرقام (numbers appear only with a value **and** a review date) |
| اعتماد صور المشروعات وحقوق استخدامها وعدم ظهور أشخاص يمكن تحديد هويتهم | Admin → المشروعات / معرض صور الأنشطة |
| التأكد من الحسابات البنكية ووسائل الدفع قبل نشرها | Admin → الإعدادات → التبرعات (disabled by default; the site shows "تُعلن وسائل التبرع الرسمية هنا فور اعتمادها") |
| اعتماد العنوان الرسمي ووسائل الاتصال ومواعيد العمل | Admin → الإعدادات → التواصل |
| مراجعة سياسة الخصوصية والنماذج الإلكترونية مع المختص القانوني | `privacy.html` is a **draft** written from the document's privacy principles — editable in Admin → النصوص → سياسة الخصوصية |
| تدقيق محتوى الأنشطة والتأكد من أن المساعدات الموصوفة متاحة بالفعل | Admin → النصوص → أنشطتنا العشرة |

Also pending in the document and therefore not shown yet: official email, social links,
message hours, detailed address, official logo file, real photos, and the documentation of
the example project «مسجد سيدنا عيسى ابن مريم — الطور، جنوب سيناء». The document lists it as
«مثال على مشروع قابل للعرض بعد التوثيق», so it ships as an **unpublished draft** (status
**جارٍ**, never «منجز» until proven, per §08): in Admin → المشروعات add its last-update date
and at least one approved photo or stage, then tick «منشور».
Pages of projects/stories that are unpublished or deleted are removed at the next build.

## 4. How the site maps to the content document

| Document section | Page |
|---|---|
| 01 هيكل الموقع | Header navigation, fixed «تبرع الآن» / «اطلب مساعدة», WhatsApp button |
| 02 الصفحة الرئيسية | `index.html` |
| 03 من نحن | `about.html` |
| 04 ما نؤمن به — ميثاق بيوت مصر | `charter.html` |
| 05 أنشطتنا العشرة | `activities.html` + `activities/<slug>.html` (10 pages) |
| 06 «إحنا معاكي» | `ehna-maaki.html` |
| 07 حكايات من بيوت مصر | `stories.html` + `stories/<id>.html` per published story |
| 08 إنجازاتنا ومشروعاتنا | `achievements.html` + `projects/<id>.html` per published project |
| 09 شارك في الخير | `get-involved.html` (`#donate`, `#volunteer`, `#projects`, `#in-kind`, `#request-help`) |
| 10 تواصل معنا (+ FAQ) | `contact.html` |
| 11 نصوص الأزرار، التذييل | Buttons and footer across the site |
| 12 تعليمات تنفيذية | Identity, mobile experience, admin panel, privacy, donations rules |
| 13 قائمة المراجعة | The checklist above + reminders on the admin dashboard |

Privacy by design (§12): the site has **no forms that store data** and **no file uploads
for visitors**. The contact/volunteer forms only open WhatsApp with a prepared message;
nothing is sent to or stored on the server. No cookies, no trackers, no third-party scripts.

## 5. Development

See [`docs/DEVELOPMENT.md`](docs/DEVELOPMENT.md) for the architecture, conventions and
verification commands, and [`docs/ADMIN.md`](docs/ADMIN.md) for the admin panel.

```bash
php public/app/build.php --strict          # regenerate all pages from templates + content
php -S 127.0.0.1:8080 -t public            # preview at http://127.0.0.1:8080/
python3 tools/check-links.py public        # internal links/anchors
python3 tools/check-content.py docs/content-source.txt public   # PDF copy is all on the site
bash tests/admin/run.sh /tmp/bm-sandbox 8095                    # admin test suite (throwaway copy)
tools/package.sh                           # build + checks + dist/*.zip
```

## Credits

- Font: [Cairo](https://github.com/Gue3bara/Cairo) — SIL Open Font License 1.1 (`public/assets/fonts/OFL-Cairo.txt`), self-hosted.
- Icons: [Lucide](https://lucide.dev) (ISC License) and [Simple Icons](https://simpleicons.org) brand glyphs (CC0); mosque glyph and illustrations drawn for this site.
