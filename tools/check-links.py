#!/usr/bin/env python3
"""Check every internal link, image, script and stylesheet in the built site.

Usage: python3 tools/check-links.py [public_dir]
Reports missing files and missing #fragment targets. Exit code 1 on problems.
"""
import os
import sys
from html.parser import HTMLParser
from urllib.parse import urlsplit, unquote

ROOT = os.path.abspath(sys.argv[1] if len(sys.argv) > 1 else os.path.join(os.path.dirname(__file__), '..', 'public'))
SKIP_DIRS = {'admin', 'app'}


class Collector(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.refs = []   # (attr, value)
        self.ids = set()

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if 'id' in a:
            self.ids.add(a['id'])
        if tag == 'a' and 'name' in a:
            self.ids.add(a['name'])
        for key in ('href', 'src'):
            if key in a and a[key]:
                self.refs.append((tag, a[key]))
        if 'srcset' in a and a['srcset']:
            for part in a['srcset'].split(','):
                url = part.strip().split(' ')[0]
                if url:
                    self.refs.append((tag, url))


def parse(path):
    c = Collector()
    with open(path, encoding='utf-8') as f:
        c.feed(f.read())
    return c


def main():
    pages = []
    for dirpath, dirnames, filenames in os.walk(ROOT):
        rel = os.path.relpath(dirpath, ROOT)
        if rel.split(os.sep)[0] in SKIP_DIRS:
            continue
        for fn in filenames:
            if fn.endswith('.html') and fn != 'index.html' or (fn == 'index.html' and rel == '.'):
                pages.append(os.path.join(dirpath, fn))
    cache = {}
    problems = []
    checked = 0
    for page in sorted(pages):
        doc = cache.setdefault(page, parse(page))
        is404 = os.path.basename(page) == '404.html' and os.path.dirname(page) == ROOT
        for tag, ref in doc.refs:
            parts = urlsplit(ref)
            if parts.scheme in ('http', 'https', 'mailto', 'tel', 'data', 'whatsapp'):
                continue
            if parts.scheme or parts.netloc:
                problems.append(f'{os.path.relpath(page, ROOT)}: unexpected URL {ref}')
                continue
            checked += 1
            path = unquote(parts.path)
            if path == '':
                target = page
            elif path.startswith('/'):
                target = os.path.join(ROOT, path.lstrip('/'))
            else:
                if is404:
                    problems.append(f'404.html: relative URL {ref} (404 page must use root-absolute URLs)')
                target = os.path.normpath(os.path.join(os.path.dirname(page), path))
            if os.path.isdir(target):
                target = os.path.join(target, 'index.html')
            if not os.path.isfile(target):
                problems.append(f'{os.path.relpath(page, ROOT)}: missing {ref}')
                continue
            if parts.fragment and target.endswith('.html'):
                tdoc = cache.setdefault(target, parse(target))
                if parts.fragment not in tdoc.ids:
                    problems.append(f'{os.path.relpath(page, ROOT)}: missing anchor #{parts.fragment} in {os.path.relpath(target, ROOT)}')
    print(f'{len(pages)} pages, {checked} internal references checked')
    for p in problems:
        print('  PROBLEM', p)
    return 1 if problems else 0


if __name__ == '__main__':
    sys.exit(main())
