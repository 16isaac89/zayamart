importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-messaging.js');
importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-auth.js');

firebase.initializeApp({
    apiKey: "AIzaSyCFGqSEiWMItei_AFIUgdM53PWrvyGmjFY",
    authDomain: "",
    projectId: "drivevalley-fdb7f",
    storageBucket: "",
    messagingSenderId: "76471554747",
    appId: "1:76471554747:android:3aa5d58a094e2a036d0f9e",
    measurementId: ""
});

const messaging = firebase.messaging();
messaging.setBackgroundMessageHandler(function(payload) {
    return self.registration.showNotification(payload.data.title, {
        body: payload.data.body || '',
        icon: payload.data.icon || '',
        data: payload.data || {}
    });
});

// Deep-link to the relevant order/conversation on click (brief §5/§16) —
// the push payload only ever carries an order_id, never full order
// details; the vendor dashboard fetches the actual data after opening,
// over an authenticated request (brief §16: "prefer security over
// convenience").
self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    const orderId = event.notification.data && event.notification.data.order_id;
    const targetUrl = orderId
        ? self.registration.scope + 'vendor/orders/details/' + orderId
        : self.registration.scope + 'vendor/notifications';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windowClients) {
            for (const client of windowClients) {
                if (client.url.indexOf(self.registration.scope) === 0 && 'focus' in client) {
                    client.navigate(targetUrl);
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});