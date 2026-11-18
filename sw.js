// Service worker : rend l'application installable et affiche une page hors ligne.
// Les pages et les photos viennent toujours du serveur (données à jour, accès protégé) ;
// seuls les fichiers statiques sont mis en cache.
const VERSION = 'cxa-1.1.1'; // suivre APP_VERSION (inc/version.php)
const STATIQUES = ['assets/style.css', 'assets/icons/icon-192.png', 'assets/icons/favicon.png', 'offline.html'];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(VERSION).then(c => c.addAll(STATIQUES)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', e => {
  e.waitUntil(caches.keys()
    .then(cles => Promise.all(cles.filter(k => k !== VERSION).map(k => caches.delete(k))))
    .then(() => self.clients.claim()));
});

self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET' || new URL(req.url).origin !== location.origin) return;

  // Pages : réseau d'abord, page hors ligne si pas de connexion
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match('offline.html')));
    return;
  }
  // CSS / icônes : cache puis mise à jour en arrière-plan
  if (/\/assets\//.test(new URL(req.url).pathname)) {
    e.respondWith(caches.open(VERSION).then(async c => {
      const enCache = await c.match(req);
      const reseau = fetch(req).then(r => { if (r.ok) c.put(req, r.clone()); return r; }).catch(() => enCache);
      return enCache || reseau;
    }));
  }
});
