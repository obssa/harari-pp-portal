// The Facebook page plugin (ADR-0072), loaded once the card comes near the screen and only when Facebook answers:
// where facebook.com is slow or blocked the frame would stay white or show the browser's error page, so the card shows
// its own message with a link to the page instead. Nothing here runs for visitors who never scroll to the card.
const PROBE_TIMEOUT = 8000;
const LOAD_TIMEOUT = 20000;

const reachable = () => Promise.race([
    fetch('https://www.facebook.com/favicon.ico', { mode: 'no-cors', cache: 'no-store', credentials: 'omit' }).then(() => true, () => false),
    new Promise((resolve) => window.setTimeout(() => resolve(false), PROBE_TIMEOUT)),
]);

const start = async (box) => {
    const fail = () => box.querySelector('[data-facebook-fallback]')?.removeAttribute('hidden');
    if (!(await reachable())) {
        fail();
        return;
    }

    const frame = document.createElement('iframe');
    frame.src = box.dataset.src;
    frame.title = box.dataset.title || '';
    frame.className = 'absolute inset-0 block size-full border-0';
    frame.allow = 'encrypted-media; web-share';
    frame.referrerPolicy = 'strict-origin-when-cross-origin';
    let loaded = false;
    frame.addEventListener('load', () => {
        loaded = true;
    });
    window.setTimeout(() => {
        if (!loaded) {
            frame.remove();
            fail();
        }
    }, LOAD_TIMEOUT);
    box.append(frame);
};

const boxes = document.querySelectorAll('[data-facebook]');
if ('IntersectionObserver' in window) {
    const watcher = new IntersectionObserver((entries) => {
        entries.filter((entry) => entry.isIntersecting).forEach((entry) => {
            watcher.unobserve(entry.target);
            start(entry.target);
        });
    }, { rootMargin: '400px 0px' });
    boxes.forEach((box) => watcher.observe(box));
} else {
    boxes.forEach(start);
}
