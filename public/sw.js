// WoodStone service worker: makes the app installable on waiter phones and tablets (FR-2.1).
// It never caches pages or data — orders and bills always come live from the server.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) =>
    event.waitUntil(self.clients.claim()),
);
self.addEventListener('fetch', () => {});
