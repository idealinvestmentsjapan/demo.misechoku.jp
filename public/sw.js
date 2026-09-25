/**
 * ミセチョク PWA Service Worker
 * 静的アセットをキャッシュし、オフラインでも基本動作をサポート
 */
const CACHE_NAME = 'misechoku-v52-nav-safearea-centering';
const BADGE_CACHE = 'misechoku-badge';
const BADGE_KEY_URL = '/__pwa_badge_count__';
const STATIC_ASSETS = [
  '/offline.html',
  '/assets/css/app.css',
  '/assets/css/tailwind.css',
  '/assets/css/layout-header.css',
  '/assets/css/layout-footer.css',
  '/assets/css/layout-sidebar.css',
  '/assets/css/sub-header.css',
  '/assets/css/character-guide.css',
  '/assets/js/app.js',
  '/assets/js/app-toast.js',
  '/assets/js/favorite-quick.js',
  '/assets/js/character-guide.js',
  '/manifest.json',
  '/assets/images/pwa/icon-192.png',
  '/assets/images/pwa/icon-512.png'
];

// インストール: 静的アセットをキャッシュ
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return Promise.allSettled(STATIC_ASSETS.map((asset) => cache.add(asset)));
    }).then(() => self.skipWaiting())
  );
});

// アクティベート: 古いキャッシュを削除
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((names) => {
      return Promise.all(
        names.filter((name) => name.startsWith('misechoku-v') && name !== CACHE_NAME).map((name) => caches.delete(name))
      );
    }).then(() => self.clients.claim())
  );
});

// フェッチ: ネットワーク優先、失敗時はキャッシュ
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // 同一オリジンのナビゲーション・静的リソースのみキャッシュ対象
  if (url.origin !== self.location.origin) {
    return;
  }

  // API・POST 等はキャッシュしない
  if (request.method !== 'GET') {
    return;
  }

  const navigation = request.mode === 'navigate';
  if (!navigation && !isCacheable(url)) return;

  event.respondWith(
    fetch(request)
      .then((response) => {
        const clone = response.clone();
        if (!navigation && !response.redirected && response.status === 200 && isCacheable(url)) {
          event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.put(request, clone)));
        }
        return response;
      })
      .catch(async () => {
        const cache = await caches.open(CACHE_NAME);
        if (navigation) return (await cache.match('/offline.html')) || new Response('通信できません。接続を確認して再読み込みしてください。', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
        return (await cache.match(request)) || Response.error();
      })
  );
});

function isCacheable(url) {
  const path = url.pathname;
  return path.startsWith('/assets/') || path === '/manifest.json';
}

async function readBadgeCount() {
  try {
    const cache = await caches.open(BADGE_CACHE);
    const res = await cache.match(BADGE_KEY_URL);
    if (!res) return 0;
    const txt = await res.text();
    const count = parseInt(txt, 10);
    return Number.isFinite(count) && count > 0 ? count : 0;
  } catch (_) {
    return 0;
  }
}

async function writeBadgeCount(count) {
  const normalized = Number.isFinite(count) && count > 0 ? Math.floor(count) : 0;
  const cache = await caches.open(BADGE_CACHE);
  await cache.put(BADGE_KEY_URL, new Response(String(normalized)));
  return normalized;
}

async function applyAppBadge(count) {
  const normalized = await writeBadgeCount(count);
  if (typeof self.registration.setAppBadge === 'function') {
    if (normalized > 0) {
      await self.registration.setAppBadge(normalized).catch(function () {});
    } else if (typeof self.registration.clearAppBadge === 'function') {
      await self.registration.clearAppBadge().catch(function () {});
    }
  }
  return normalized;
}

// --- Push 通知: サーバーから届いたメッセージをデスクトップ通知で表示 ---
self.addEventListener('push', function (event) {
  if (!event.data) return;
  let data = { title: 'ミセチョク', body: '', url: '/' };
  try {
    data = { ...data, ...event.data.json() };
  } catch (_) {
    data.body = event.data.text();
  }

  const options = {
    body: data.body,
    icon: self.location.origin + '/assets/images/pwa/icon-192.png',
    badge: self.location.origin + '/assets/images/pwa/icon-192.png',
    data: { url: data.url || '/' },
    tag: 'misechoku-notification',
    renotify: true,
  };

  event.waitUntil((async function () {
    await self.registration.showNotification(data.title, options);
    const current = await readBadgeCount();
    const next = typeof data.badge === 'number' ? data.badge : current + 1;
    const applied = await applyAppBadge(next);
    const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    clients.forEach(function (client) {
      client.postMessage({ type: 'badge-update', count: applied });
    });
  })());
});

// 通知クリックでアプリを開く
self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  const url = event.notification.data && event.notification.data.url ? event.notification.data.url : '/';
  event.waitUntil((async function () {
    await applyAppBadge(0);
    const clientList = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (let i = 0; i < clientList.length; i++) {
      if (clientList[i].url.indexOf(self.location.origin) === 0 && 'focus' in clientList[i]) {
        clientList[i].postMessage({ type: 'badge-update', count: 0 });
        clientList[i].navigate(url);
        return clientList[i].focus();
      }
    }
    if (self.clients.openWindow) {
      return self.clients.openWindow(self.location.origin + url);
    }
  })());
});

self.addEventListener('message', function (event) {
  if (!event || !event.data || typeof event.data !== 'object') return;
  if (event.data.type === 'badge-sync') {
    const count = Number(event.data.count || 0);
    event.waitUntil(applyAppBadge(count));
  } else if (event.data.type === 'badge-clear') {
    event.waitUntil(applyAppBadge(0));
  }
});
