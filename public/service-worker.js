/* OyubiaCYF service worker — app-shell caching + offline navigation fallback. */
var CACHE = 'oyubiacyf-v11';

// Paths are relative to the SW scope, so they work at root or in a subfolder.
var SHELL = [
  './',
  './assets/css/styles.css',
  './assets/js/app.js',
  './manifest.webmanifest',
  './offline.html'
];

self.addEventListener('install', function (e) {
  e.waitUntil(
    caches.open(CACHE).then(function (c) {
      return Promise.all(SHELL.map(function (url) {
        return c.add(url)['catch'](function () { /* ignore individual failures */ });
      }));
    }).then(function () { return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function (e) {
  e.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.map(function (k) { if (k !== CACHE) { return caches['delete'](k); } }));
    }).then(function () { return self.clients.claim(); })
  );
});

self.addEventListener('fetch', function (e) {
  var req = e.request;
  if (req.method !== 'GET') { return; } // never cache POST (sync, form posts)

  var url = new URL(req.url);

  // Don't cache the JSON API or PHP POST endpoints.
  if (url.pathname.indexOf('/api/') !== -1) { return; }

  // Always serve forms fresh from the network — they carry CSRF tokens and
  // live response/status, which must never be served from a stale cache.
  if (url.pathname.indexOf('/f/') !== -1 || url.pathname.indexOf('/admin/forms') !== -1
      || url.pathname.indexOf('/join') !== -1 || url.pathname.indexOf('/admin/self-register') !== -1) { return; }

  // Navigations: network-first, fall back to cache, then offline page.
  if (req.mode === 'navigate') {
    e.respondWith(
      fetch(req).then(function (resp) {
        var copy = resp.clone();
        caches.open(CACHE).then(function (c) { c.put(req, copy); });
        return resp;
      })['catch'](function () {
        return caches.match(req).then(function (hit) {
          return hit || caches.match('./offline.html');
        });
      })
    );
    return;
  }

  // Static assets: cache-first, then network (and cache it).
  e.respondWith(
    caches.match(req).then(function (hit) {
      return hit || fetch(req).then(function (resp) {
        if (resp && resp.status === 200 && resp.type === 'basic') {
          var copy = resp.clone();
          caches.open(CACHE).then(function (c) { c.put(req, copy); });
        }
        return resp;
      })['catch'](function () { return hit; });
    })
  );
});
