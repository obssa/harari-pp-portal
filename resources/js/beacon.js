// Cookieless page view count (ADR-0040): the page's address, the site the visitor came from and the
// window width. Nothing that identifies the visitor; skipped when the browser asks not to be tracked.
const send = () => {
    if (navigator.webdriver || navigator.doNotTrack === '1' || navigator.globalPrivacyControl) {
        return;
    }
    let referrer = '';
    try {
        referrer = document.referrer ? new URL(document.referrer).host : '';
    } catch {
        referrer = '';
    }
    const body = JSON.stringify({ p: location.pathname, r: referrer, w: window.innerWidth });
    if (!(navigator.sendBeacon && navigator.sendBeacon('/pv', body))) {
        fetch('/pv', { method: 'POST', body, keepalive: true, credentials: 'omit' }).catch(() => {});
    }
};

if (document.visibilityState === 'prerender') {
    document.addEventListener('visibilitychange', send, { once: true });
} else {
    send();
}
