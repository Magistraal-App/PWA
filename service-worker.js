self.addEventListener('fetch', function (e) {
	// https://stackoverflow.com/a/49719964
    if (e.request.cache === 'only-if-cached' && e.request.mode !== 'same-origin') return;
    
    // Always load from server, except for when an error occurs (offline)
    e.respondWith(
        networkFirst(e.request, '/sw-test/gallery/myLittleVader.jpg')
    );
})

self.addEventListener('install', function (e) {
    e.waitUntil(
        caches.open(cacheName).then(function (cache) {
            return cache.addAll(resources)
        })
    )
})

self.addEventListener('activate', function (e) {
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