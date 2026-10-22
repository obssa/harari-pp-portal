// The homepage carousel (ADR-0070), after the WAI carousel pattern. Without this script the slides can
// still be swiped (a scroll-snapping track). With it: previous/next, a dot per slide, a pause/play
// button, turning by itself with a time bar, pausing while the pointer or keyboard focus is inside,
// while the tab is hidden, and never by itself for visitors who ask their device for less motion.
const lessMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

const setup = (root) => {
    const track = root.querySelector('[data-track]');
    const slides = track ? [...track.children] : [];
    if (slides.length < 2) {
        return;
    }

    const focusMode = root.dataset.mode === 'focus';
    const dots = [...root.querySelectorAll('[data-dot]')];
    const toggle = root.querySelector('[data-toggle]');
    const interval = Number(root.dataset.interval) || 7000;
    let index = 0;
    let timer = null;
    let stopped = root.dataset.autoplay !== 'true' || lessMotion.matches;
    let hovering = false;
    let focused = false;

    const held = () => stopped || hovering || focused || document.hidden;
    const duration = (n) => Number(slides[n].dataset.duration) || interval;

    const mark = () => {
        slides.forEach((slide, n) => {
            const current = n === index;
            slide.classList.toggle('is-current', current);
            slide.inert = !current;
            slide.setAttribute('aria-hidden', current ? 'false' : 'true');
        });
        dots.forEach((dot, n) => dot.setAttribute('aria-current', n === index ? 'true' : 'false'));
    };

    const restartBar = () => {
        root.style.setProperty('--slide-duration', `${duration(index)}ms`);
        root.removeAttribute('data-running');
        void root.offsetWidth;
        if (!stopped) {
            root.setAttribute('data-running', '');
        }
    };

    const schedule = () => {
        window.clearTimeout(timer);
        timer = null;
        if (held()) {
            root.toggleAttribute('data-held', !stopped);
            return;
        }
        root.removeAttribute('data-held');
        timer = window.setTimeout(() => show(index + 1), duration(index));
    };

    const show = (target, { scroll = true } = {}) => {
        const previous = index;
        index = (target + slides.length) % slides.length;
        if (focusMode && previous !== index) {
            slides[previous].classList.add('is-leaving');
            slides[index].classList.add('is-entering');
            window.setTimeout(() => {
                slides[previous].classList.remove('is-leaving');
                slides[index].classList.remove('is-entering');
            }, 900);
        }
        mark();
        if (scroll && !focusMode) {
            track.scrollTo({ left: slides[index].offsetLeft - track.offsetLeft, behavior: lessMotion.matches ? 'auto' : 'smooth' });
        }
        restartBar();
        schedule();
    };

    const setStopped = (value) => {
        stopped = value;
        track.setAttribute('aria-live', stopped ? 'polite' : 'off');
        if (toggle) {
            toggle.setAttribute('aria-label', stopped ? root.dataset.labelPlay : root.dataset.labelPause);
            toggle.querySelector('[data-icon="pause"]')?.classList.toggle('hidden', stopped);
            toggle.querySelector('[data-icon="play"]')?.classList.toggle('hidden', !stopped);
        }
        restartBar();
        schedule();
    };

    root.querySelector('[data-prev]')?.addEventListener('click', () => show(index - 1));
    root.querySelector('[data-next]')?.addEventListener('click', () => show(index + 1));
    dots.forEach((dot) => dot.addEventListener('click', () => show(Number(dot.dataset.dot))));
    toggle?.addEventListener('click', () => setStopped(!stopped));

    if (root.dataset.hover === 'pause') {
        root.addEventListener('pointerenter', (event) => {
            if (event.pointerType === 'mouse') {
                hovering = true;
                schedule();
            }
        });
        root.addEventListener('pointerleave', () => {
            hovering = false;
            restartBar();
            schedule();
        });
    }
    root.addEventListener('focusin', () => {
        focused = true;
        schedule();
    });
    root.addEventListener('focusout', (event) => {
        if (!root.contains(event.relatedTarget)) {
            focused = false;
            schedule();
        }
    });
    document.addEventListener('visibilitychange', () => {
        restartBar();
        schedule();
    });
    lessMotion.addEventListener('change', () => setStopped(lessMotion.matches || stopped));

    if (focusMode) {
        // Swipes, since the slides sit on top of each other instead of in a scrolling row.
        let startX = null;
        track.addEventListener('pointerdown', (event) => {
            startX = event.clientX;
        });
        track.addEventListener('pointerup', (event) => {
            if (startX !== null && Math.abs(event.clientX - startX) > 48) {
                show(index + (event.clientX < startX ? 1 : -1));
            }
            startX = null;
        });
    } else {
        // A swipe or a scroll in the row picks the slide that came to rest in view.
        let settle = null;
        track.addEventListener('scroll', () => {
            window.clearTimeout(settle);
            settle = window.setTimeout(() => {
                const nearest = Math.round(track.scrollLeft / track.clientWidth);
                if (nearest !== index && nearest >= 0 && nearest < slides.length) {
                    show(nearest, { scroll: false });
                }
            }, 120);
        }, { passive: true });
    }

    root.setAttribute('data-ready', '');
    mark();
    setStopped(stopped);
};

document.querySelectorAll('[data-carousel]').forEach(setup);
