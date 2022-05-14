console.log('[firebase-messaging-sw.js] Loading...');

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

// Get registration token. Initially this makes a network call, once retrieved
// subsequent calls to getToken will return from cache.
const messaging = firebase.messaging();

messaging.onBackgroundMessage((payload) => {
    console.log('[firebase-messaging-sw.js] Received background message:', payload);
});

// getToken(messaging, {
//     serviceWorkerRegistration: registration,
//     vapidKey: 'BEOPEe1UKGRRW91-qm3DN_AZOuBPB1ljTaJaXqyXSOSMundJvfjYzD89Do4f4-GoH06p91mEkU_ItrAi2xJ_tqM'
// }).then((currentToken) => {
//     if (currentToken) {
//         console.log(currentToken);
//         // Send the token to your server and update the UI if necessary
//         // ...
//     } else {
//         // Show permission request UI
//         console.log('No registration token available. Request permission to generate one.');
//         // ...
//     }
// }).catch((err) => {
//     console.log('An error occurred while retrieving token. ', err);
//     // ...
// });

// onBackgroundMessage(messaging, (payload) => {
//     console.log('BACKGROUND MESSAGE AAAA');
//   console.log('[firebase-messaging-sw.js] Received background message ', payload);
//   // Customize notification here
//   const notificationTitle = 'Background Message Title';
//   const notificationOptions = {
//     body: 'Background Message body.',
//     icon: '/firebase-logo.png'
//   };

//   self.registration.showNotification(notificationTitle,
//     notificationOptions);
// });

console.log('[firebase-messaging-sw.js] Loaded!');