{{-- Loads the Firebase JS SDK for the vendor panel's PWA push flow
     (public/js/vendor-push.js) — reuses the same admin-configured
     'fcm_credentials' business setting and the same firebase-messaging-sw.js
     service worker the admin panel already uses (see
     layouts.admin.partials._firebase-script), but does NOT auto-subscribe
     to any topic: registration only happens on the vendor's explicit
     "Enable notifications" click (brief §8). --}}
@php($fcmCredentials = getWebConfig('fcm_credentials'))
@if(isset($fcmCredentials['apiKey']) && $fcmCredentials['apiKey'])
    <span id="Firebase_Configuration_Config" data-api-key="{{ $fcmCredentials['apiKey'] ?? '' }}"
          data-auth-domain="{{ $fcmCredentials['authDomain'] ?? '' }}"
          data-project-id="{{ $fcmCredentials['projectId'] ?? '' }}"
          data-storage-bucket="{{ $fcmCredentials['storageBucket'] ?? '' }}"
          data-messaging-sender-id="{{ $fcmCredentials['messagingSenderId'] ?? '' }}"
          data-app-id="{{ $fcmCredentials['appId'] ?? '' }}"
          data-measurement-id="{{ $fcmCredentials['measurementId'] ?? '' }}"
    ></span>

    <script src="{{ dynamicAsset(path: 'public/assets/backend/libs/firebase/firebase.min.js') }}"></script>
    <script src="https://www.gstatic.com/firebasejs/8.3.2/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.3.2/firebase-messaging.js"></script>
    <script src="{{ dynamicAsset(path: 'public/assets/backend/libs/firebase/firebase-init.js') }}"></script>
@endif
