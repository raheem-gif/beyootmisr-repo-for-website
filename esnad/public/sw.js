// ESNAD service worker. Caches the app shell and the offline page.
// Account pages and anything with personal data are never cached.
const VERSION = 'esnad-v1';
const SHELL = ['/offline', '/manifest.webmanifest', '/brand/icon-192.png', '/brand/esnad-logo-480.webp'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(VERSION).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k)))).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== location.origin) return;

    // Built assets and fonts are content-hashed: cache first.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/brand/')) {
        event.respondWith(
            caches.match(req).then((hit) => hit || fetch(req).then((res) => {
                const copy = res.clone();
                caches.open(VERSION).then((cache) => cache.put(req, copy));
                return res;
            }))
        );
        return;
    }

    // Pages: network only, with the offline page when there is no connection.
    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).catch(() => caches.match('/offline')));
    }
});
