/* ============================ */
/*    FIREBASE NOTIFICATIONS    */
/* ============================ */

console.log('[service-worker.js] Loading Firebase...');

importScripts('https://cdnjs.cloudflare.com/ajax/libs/localforage/1.10.0/localforage.min.js');

// Compat scripts provide the v8 API
importScripts('https://www.gstatic.com/firebasejs/9.8.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.8.1/firebase-messaging-compat.js');

// Initialize Firebase (v8)
firebase.initializeApp({
    apiKey: "AIzaSyCeEjw-h8t6-7EAVtcxe2mK0gV52JVe938",
    authDomain: "magistraal-ed92d.firebaseapp.com",
    projectId: "magistraal-ed92d",
    storageBucket: "magistraal-ed92d.appspot.com",
    messagingSenderId: "276429310436",
    appId: "1:276429310436:web:0e7fc77f5ded2c9a3d9ff1"
});

// Get messaging instance
const messaging = firebase.messaging();

console.log(ServiceWorkerRegistration);

messaging.onBackgroundMessage((payload) => {
    console.log('[service-worker.js] Received background message:', payload);

    if(typeof payload.data == 'undefined' || payload.data === null) {
        return;
    }

    const data = typeof payload.data == 'object' ? payload.data : JSON.parse(payload.data['gcm.notification.data'] || '{}') || {};

    return self.registration.showNotification(data.title || undefined, {
        body: data.body || undefined,
        icon: '/magistraal/client/assets/images/app/logo-transparent/512x512.png',
        badge: '/magistraal/client/assets/images/app/badge/128x128.png',
        timestamp: Math.floor(data.timestamp * 1000 || Date.now())
    });
});

console.log('[service-worker.js] Loaded Firebase!');

self.addEventListener('fetch', function (e) {
	// https://stackoverflow.com/a/49719964
    if (e.request.cache === 'only-if-cached' && e.request.mode !== 'same-origin') return;
    
    // Always load from server, except for when an error occurs (offline)
    e.respondWith(
        networkFirst(e.request, '/magistraal/client/offline/')
    );
})

self.addEventListener('install', function (e) {
    console.log('[service-worker.js] Installed!');
    e.waitUntil(
        caches.open(cacheName).then(function (cache) {
            cache.add(new Request('/magistraal/client/', {cache: 'reload'}));
            cache.add(new Request('/magistraal/client/main/', {cache: 'reload'}));
            cache.add(new Request('/magistraal/client/login/', {cache: 'reload'}));
            cache.add(new Request('/magistraal/client/offline/', {cache: 'reload'}));
            return cache.addAll(resources)
        })
    )
})

self.addEventListener('activate', function (e) {
    console.log('[service-worker.js] Activated!');
    e.waitUntil(
        caches.keys().then(function (keyList) {
            return Promise.all(keyList.map(function (key, i) {
                if(key !== cacheName) {
                    return caches.delete(keyList[i])
                }
            }))
        })
    )
})

async function networkFirst(request, fallbackUrl) {
    request.url = removeHash(request.url);
    // Try to get the resource from server
    try {
        const responseFromNetwork = await fetch(request);
        // response may be used only once
        // we need to save clone to put one copy in cache
        // and serve second one
        saveResponseInCache(request, responseFromNetwork.clone());
        return responseFromNetwork;
    } catch (err) { console.error(err); }

    // Try to get resource from cache
    try {
        const responseFromCache = await caches.match(request);
        return responseFromCache;
    } catch (err) { console.error(err); }

    // Try to get fallback resource
    try {
        const fallbackResponse = await caches.match(fallbackUrl);
        return fallbackResponse;
    } catch (err) { console.error(err); }

    // when even the fallback resource is not available,
    // there is nothing we can do, but we must always
    // return a Response object
    return new Response('Network error happened', {
        status: 408,
        headers: { 'Content-Type': 'text/plain' },
    });
}

function removeHash(url) {
    let urlObj = new URL(url);
    urlObj.hash = '';
    return urlObj.href;
}

async function saveResponseInCache(request, response) {
    if(request.method != 'POST') {
        caches.open(cacheName).then(function (cache) {
            return cache.put(request, response);
        });
    }
}