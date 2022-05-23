console.log('[firebase-messaging-sw.js] Loading...');

// So the service worker doesn't get flagged with 'Page does not work offline'
self.addEventListener('fetch', () => {return;});

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
    console.log('[init-firebase.js] Received background message:', payload);

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

console.log('[firebase-messaging-sw.js] Loaded!');