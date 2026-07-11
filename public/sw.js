// renters.rent service worker.
// Boundary: HTML navigations are NEVER cached — case/correspondence state must
// never be served stale on a due-process tool. Only the static shell is cached.
// Offline navigations fall back to the /offline notice, not a stale page.

const CACHE_VERSION = 'rr-shell-v1';   // bump to invalidate on deploy

// Static shell only. No HTML pages, no case/correspondence data.
const SHELL_ASSETS = [
  '/offline',
  '/favicon.svg',
  '/apple-touch-icon.png',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
];

const STATIC_DESTINATIONS = ['style', 'script', 'image', 'font'];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE_VERSION).then((cache) => cache.addAll(SHELL_ASSETS)));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE_VERSION).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;                 // never touch POST/case actions

  const url = new URL(req.url);

  // 1. HTML navigations: network-only, NEVER cached. Offline -> /offline notice.
  //    This is what guarantees case/correspondence pages are never stale.
  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).catch(() => caches.match('/offline')));
    return;
  }

  // 2. Same-origin static assets (css/js/img/font): cache-first, then network.
  if (url.origin === self.location.origin && STATIC_DESTINATIONS.includes(req.destination)) {
    event.respondWith(caches.match(req).then((cached) => cached || fetch(req)));
    return;
  }

  // 3. Everything else (data fetches, cross-origin CDN): straight to network, no cache.
});
