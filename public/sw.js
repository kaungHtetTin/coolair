// Retire the previous offline worker. Business features require a connection.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', event => event.waitUntil((async () => {
  const prefix = `cool-air-${self.registration.scope}-`;
  for (const key of await caches.keys()) if (key.startsWith(prefix) || /^cool-air-[a-f0-9]{12}$/.test(key)) await caches.delete(key);
  await self.registration.unregister();
  await self.clients.claim();
})()));
