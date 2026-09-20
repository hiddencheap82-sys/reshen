// Reshen PWA shell worker. Deliberately network-first for everything —
// the queue is live data; caching it would show a barber a stale line
// that's actively wrong, which is worse than no offline support at all.
// We only cache the static shell so the app still *opens* on a flaky
// connection (doc 8.1 "mobile-first, tolerant of the salon's wifi dropping").
const CACHE = 'reshen-shell-v1';
const SHELL = ['./manifest.webmanifest', './assets/icons/icon.svg'];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(SHELL)));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;

  event.respondWith(
    fetch(event.request)
      .then((response) => response)
      .catch(() => caches.match(event.request).then((cached) => cached || caches.match('./manifest.webmanifest')))
  );
});
