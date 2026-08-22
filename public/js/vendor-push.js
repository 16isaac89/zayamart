"use strict";

/**
 * PWA push permission flow (brief §8) — user-initiated only, never
 * requested on page load. Reuses this project's existing Firebase JS SDK
 * config (admin-configured 'fcm_credentials' business setting, already
 * loaded into #Firebase_Configuration_Config by the shared firebase-script
 * partial) and the existing firebase-messaging-sw.js service worker —
 * see the notification architecture report for why this rides on the
 * project's already-working FCM infrastructure instead of a parallel
 * VAPID Web Push implementation.
 */
(function () {
    const button = document.getElementById('js-enable-push');
    const statusEl = document.getElementById('js-push-status');
    if (!button) {
        return;
    }

    function setStatus(text, isError) {
        if (statusEl) {
            statusEl.textContent = text;
            statusEl.className = 'ms-2 small ' + (isError ? 'text-danger' : 'text-success');
        }
    }

    button.addEventListener('click', function () {
        if (!('Notification' in window) || !('serviceWorker' in navigator) || !window.firebase) {
            setStatus('Browser not supported', true);
            return;
        }

        if (Notification.permission === 'denied') {
            setStatus('Notifications blocked — enable them in your browser settings.', true);
            return;
        }

        button.disabled = true;
        setStatus('Requesting permission…', false);

        Notification.requestPermission()
            .then(function (permission) {
                if (permission !== 'granted') {
                    setStatus('Permission not granted.', true);
                    button.disabled = false;
                    return null;
                }

                return navigator.serviceWorker.register('/firebase-messaging-sw.js').then(function (registration) {
                    const messaging = firebase.messaging();
                    return messaging.getToken({ serviceWorkerRegistration: registration });
                });
            })
            .then(function (token) {
                if (!token) {
                    return;
                }

                return fetch('/vendor/push-subscriptions', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    },
                    body: JSON.stringify({ token: token, device_type: 'web' }),
                }).then(function (response) {
                    if (!response.ok) {
                        throw new Error('subscribe_failed');
                    }
                    setStatus('Notifications enabled on this device.', false);
                    button.disabled = true;
                    button.textContent = 'Notifications enabled';
                });
            })
            .catch(function (error) {
                console.warn('Push subscription error:', error);
                setStatus('Something went wrong. Please try again.', true);
                button.disabled = false;
            });
    });
})();
