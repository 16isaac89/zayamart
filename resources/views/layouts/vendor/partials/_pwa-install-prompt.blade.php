{{-- Subtle install prompt (brief §4/§29) — uses the standard browser
     beforeinstallprompt mechanism only; does nothing on browsers/devices
     that don't support installation (brief: "do not make installation
     mandatory"), and never reappears after a dismissal in this browser. --}}
<div id="pwa-install-banner" class="d-none" style="position:fixed;left:16px;right:16px;bottom:16px;z-index:1060;background:#fff;border:1px solid #e5e7eb;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.15);padding:14px 16px;max-width:420px;margin:0 auto;">
    <div class="d-flex justify-content-between align-items-start gap-3">
        <div>
            <strong>{{ translate('Install_Seller_App') }}</strong>
            <p class="small text-muted mb-0">{{ translate('Get_instant_order_notifications_and_faster_access_to_your_dashboard') }}</p>
        </div>
    </div>
    <div class="d-flex gap-2 mt-2">
        <button type="button" id="pwa-install-accept" class="btn btn-primary btn-sm">{{ translate('Install') }}</button>
        <button type="button" id="pwa-install-dismiss" class="btn btn-outline-secondary btn-sm">{{ translate('Not_now') }}</button>
    </div>
</div>

<script>
    (function () {
        let deferredPrompt = null;
        const banner = document.getElementById('pwa-install-banner');
        const STORAGE_KEY = 'zaya_seller_pwa_install_dismissed';

        window.addEventListener('beforeinstallprompt', function (event) {
            event.preventDefault();
            if (localStorage.getItem(STORAGE_KEY)) {
                return; // never re-shown after a dismissal, per brief §29
            }
            deferredPrompt = event;
            banner.classList.remove('d-none');
        });

        document.getElementById('pwa-install-accept')?.addEventListener('click', function () {
            banner.classList.add('d-none');
            if (deferredPrompt) {
                deferredPrompt.prompt();
            }
        });

        document.getElementById('pwa-install-dismiss')?.addEventListener('click', function () {
            banner.classList.add('d-none');
            localStorage.setItem(STORAGE_KEY, '1');
        });

        window.addEventListener('appinstalled', function () {
            banner.classList.add('d-none');
        });
    })();
</script>
