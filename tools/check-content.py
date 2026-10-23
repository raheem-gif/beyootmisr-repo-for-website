#!/usr/bin/env python3
"""Content fidelity check: is every public line of the client's content document on the site?

Usage: python3 -I tools/check-content.py <content.txt> [public_dir]

<content.txt> is the text extracted from the PDF with per-line colour tags
("[ink|x55-507] ...", "[BLUE|...]", "[gray|...]"). Gray lines are editorial notes
for the developer; they are reported separately and not required on the site.
Every ink/BLUE line (≥ 3 words) must appear, after normalisation, somewhere in
the visible text of the built pages.
"""
import os
import re
import sys
from html.parser import HTMLParser

SRC = sys.argv[1]
ROOT = os.path.abspath(sys.argv[2] if len(sys.argv) > 2 else os.path.join(os.path.dirname(__file__), '..', 'public'))

# Lines from the document that are structure/editorial labels or bracketed placeholders,
# not visitor-facing copy. Each must be justified.
EDITORIAL = [
    'بيوت مصر | محتوى الموقع الإلكتروني',            # running header
    'محتوى أولي قابل للمراجعة',                      # running footer
    'المحتوى التحريري والتقسيم الكامل للموقع',         # cover title
    'هيكل الموقع والقائمة الرئيسية',                  # section heading of the spec
    'الهدف: موقع تعريفي عربي',                         # brief for the developer
    'والمشاركة والتواصل، دون بناء منصة',
    'الرسالة الرئيسية، لمحة عن المؤسسة',               # sitemap descriptions (§01)
    'حكاية المؤسسة، الرؤية، الرسالة، الأهداف',
    'الأنشطة العشرة مع صفحة لكل نشاط',
    'قصص أثر تحافظ على الخصوصية',
    'أرقام موثقة، مشروعات جارية ومنجزة',
    'التبرع، التطوع، دعم المشروعات',
    'الخط الساخن والوسائل الرسمية والعناوين',
    'الصفحة الرئيسية — النصوص الجاهزة',
    'الشاشة الأولى | الرسالة الرئيسية',
    'تعريف قصير بالمؤسسة',
    'أنشطتنا العشرة — المحتوى التعريفي',
    'لكل نشاط صفحة فرعية تتضمن',
    'هذا النشاط» حيث تتاح مساهمات رسمية',
    'مبادراتنا الخاصة — «إحنا معاكي»',
    'مقدمة القسم',
    'قوالب قصص جاهزة للنشر',
    'إنجازاتنا ومشروعاتنا',                            # §08 heading (page title on site)
    'أثرنا بالأرقام — لا تُنشر أرقام غير موثقة',
    'نعرض كل مشروع بصفحة أو بطاقة',
    'تحديث؛ وتظهر تكلفة المشروع والتبرعات',
    'مثال على مشروع قابل للعرض بعد التوثيق',
    'مسجد سيدنا عيسى ابن مريم — الطور، جنوب سيناء: مشروع مسجد',
    'تقدم الأعمال المعتمدة. لا يوصف المشروع',
    'يمكن إتاحة تقرير نشاط سنوي',
    'مالية أولية.',
    'نصوص قصيرة جاهزة لأزرار الموقع',
    'تذييل الموقع — Footer',
    ']بعد مراجعة أصل شهادة الإشهار[',
    'روابط مختصرة: من نحن',
    'تعليمات تنفيذية مختصرة لرحيم',
    'قائمة المراجعة قبل إطلاق الموقع',
    '09 | شارك في الخير', '10 | تواصل معنا', '07 | حكايات من بيوت مصر', '03 | من نحن',
    '04 | ما نؤمن به — ميثاق بيوت مصر',
    'تُدرج هنا بيانات جهة الإشهار',                   # "insert the registration details here" (developer note)
    'الرسمية للنفع العام وتصاريح جمع التبرعات، بالنص المطابق',
]
STORY_GUIDES = ['عنوان المشروع أو نوع التدخل', 'المريض.', 'نوع مشروع متناهي الصغر', 'صور التنفيذ قبل/أثناء/بعد',
                'نوع الدعم التعليمي والنتيجة العامة', 'اسم المشروع العام وموقعه العام']


class Text(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.skip = 0
        self.parts = []

    def handle_starttag(self, tag, attrs):
        if tag in ('script', 'style', 'svg'):
            self.skip += 1

    def handle_endtag(self, tag):
        if tag in ('script', 'style', 'svg') and self.skip:
            self.skip -= 1

    def handle_data(self, data):
        if not self.skip:
            self.parts.append(data)


DIAC = re.compile('[ؗ-ًؚ-ْٰـ]')


def norm(s):
    s = re.sub(r'\s+(?=[ً-ْ])', '', s)      # PDF puts tanween after a stray space
    s = DIAC.sub('', s)
    s = s.replace('أ', 'ا').replace('إ', 'ا').replace('آ', 'ا').replace('ى', 'ي').replace('ة', 'ه')
    s = re.sub(r'[\[\]«»"\'()،,.:;؛؟?!—–\-|/•]', ' ', s)
    return re.sub(r'\s+', ' ', s).strip()


def squash(s):
    # The PDF extraction splits words around diacritics and writes the lam-alef
    # ligature (لا) as "ال" next to tanween, so compare without spaces, lam or alef.
    return re.sub(r'[\sلا]+', '', s)


def site_text():
    chunks = []
    for dirpath, _, files in os.walk(ROOT):
        rel = os.path.relpath(dirpath, ROOT).split(os.sep)[0]
        if rel in ('admin', 'app'):
            continue
        for fn in files:
            if fn.endswith('.html'):
                t = Text()
                with open(os.path.join(dirpath, fn), encoding='utf-8') as f:
                    t.feed(f.read())
                chunks.append(' '.join(t.parts))
    return squash(norm(' '.join(chunks)))


def main():
    site = site_text()
    missing, editorial, gray = [], 0, 0
    dev_section = False
    for raw in open(SRC, encoding='utf-8'):
        m = re.match(r'\[([^|\]]*)\|[^\]]*\]\s?(.*)', raw.strip())
        if not m:
            continue
        color, text = m.group(1), m.group(2).strip()
        if re.match(r'^(12|13) \|', text):
            dev_section = True          # §12 instructions for the developer, §13 launch checklist
        if dev_section or '[' in text or ']' in text:
            editorial += 1               # bracketed placeholders are notes, not copy
            continue
        words = [w for w in norm(text).split(' ') if re.search('[؀-ۿ]', w)]
        if len(words) < 3:
            continue
        if any(text.startswith(x) or x in text for x in EDITORIAL + STORY_GUIDES):
            editorial += 1
            continue
        if color.startswith('gray'):
            gray += 1
            continue
        n = norm(text)
        # strip the "01. " style numbering and bullets used in the document
        n = squash(re.sub(r'^\d+\s*', '', n))
        if n in site:
            continue
        # A line can hold the end of one sentence and the start of the next, which the
        # site may show in separate cards: accept it when every sentence part is present.
        parts = [squash(norm(x)) for x in re.split(r'[.؟]\s+', text) if len(norm(x).split(' ')) >= 3]
        if not parts or any(part not in site for part in parts):
            missing.append(text)
    print(f'checked document lines; editorial skipped: {editorial}; gray notes skipped: {gray}; missing: {len(missing)}')
    for line in missing:
        print('  MISSING', line)
    return 1 if missing else 0


if __name__ == '__main__':
    sys.exit(main())
