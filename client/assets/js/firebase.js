// Initialize the Firebase app in the service worker by passing in
// your app's Firebase config object.
// https://firebase.google.com/docs/web/setup#config-object
firebase.initializeApp({
    apiKey: "AIzaSyCeEjw-h8t6-7EAVtcxe2mK0gV52JVe938",
    authDomain: "magistraal-ed92d.firebaseapp.com",
    projectId: "magistraal-ed92d",
    storageBucket: "magistraal-ed92d.appspot.com",
    messagingSenderId: "276429310436",
    appId: "1:276429310436:web:0e7fc77f5ded2c9a3d9ff1"
});

// Load service worker
navigator.serviceWorker.register('../../firebase-messaging-sw.js').then(registration => {
    // Get messaging instance
    const messaging = firebase.messaging();

    // Get messaging token
    messaging.getToken({
        serviceWorkerRegistration: registration,
        vapidKey: 'BEOPEe1UKGRRW91-qm3DN_AZOuBPB1ljTaJaXqyXSOSMundJvfjYzD89Do4f4-GoH06p91mEkU_ItrAi2xJ_tqM'
    }).then((currentToken) => {
        if (!currentToken) {
            return;
        }

        console.log('[init-firebase.js] Token:', currentToken);
        
        // Send message token to server
        magistraal.api.call({
            url: 'firebase/notifications/register-token',
            data: {
                token: currentToken,
                user_uuid: magistraalPersistentStorage.get('user_uuid').value || null
            },
            source: 'server_only'
        }).then(res => {
            console.log('res from serv', res);
        })

        messaging.onMessage(payload => {
            console.log('[init-firebase.js] Received foreground message:', payload);

            // Only show notifications is the PWA is installed
            if (!window.matchMedia('(display-mode: standalone)').matches) {
                console.log('Message was received, but PWA is not installed.');
                return;
            }

            if(!isSet(payload.data)) {
                return;
            }

            const data = typeof payload.data == 'object' ? payload.data : JSON.parse(payload.data['gcm.notification.data'] || '{}') || {};
            
            registration.showNotification(data.title || undefined, {
                body: data.body || undefined,
                icon: '/magistraal/client/assets/images/app/logo-transparent/512x512.png',
                badge: '/magistraal/client/assets/images/app/badge/128x128.png'
            });
        });
    }).catch((err) => {
        console.log('An error occurred while retrieving token. ', err);
        // ...
    });
})
