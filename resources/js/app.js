import Alpine from 'alpinejs';

// Theme: honour a stored choice, else the OS preference. Applied in the <head>
// inline script too, to avoid a flash before this module loads.
window.theme = {
    get current() {
        return localStorage.getItem('theme')
            ?? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    },
    apply(value) {
        document.documentElement.classList.toggle('dark', value === 'dark');
        localStorage.setItem('theme', value);
    },
    toggle() {
        this.apply(this.current === 'dark' ? 'light' : 'dark');
    },
};

// Shared background polling (notification bell, message thread). The server has no
// WebSockets or queue worker (cPanel), so the browser polls — which on shared hosting
// multiplies by every open tab. This keeps that cheap:
//   - nothing is fetched while the tab is hidden; coming back refreshes at once,
//   - while nothing changes the interval backs off (×1.5, up to `max`); a change or an
//     explicit reset() (e.g. the user just sent a message) returns it to `base`,
//   - ±10% jitter, so a classroom of students opened at the same moment does not hit the
//     server in lockstep.
// `task` returns true when it found something new. Must be defined before Alpine starts.
window.pollWhenVisible = function (task, { base, max = base * 4, backoff = 1.5 } = {}) {
    let delay = base;
    let timer = null;
    let stopped = false;

    const schedule = () => {
        if (stopped) return;
        clearTimeout(timer);
        timer = setTimeout(run, delay * (0.9 + Math.random() * 0.2));
    };

    const run = async () => {
        if (stopped || document.hidden) return;   // paused; onVisible resumes

        let changed = false;
        try { changed = (await task()) === true; } catch (e) { /* a failed poll just backs off */ }

        delay = changed ? base : Math.min(delay * backoff, max);
        schedule();
    };

    const onVisible = () => {
        if (stopped || document.hidden) return;
        delay = base;
        clearTimeout(timer);
        run();
    };

    document.addEventListener('visibilitychange', onVisible);
    schedule();

    return {
        reset() { delay = base; schedule(); },
        stop() { stopped = true; clearTimeout(timer); document.removeEventListener('visibilitychange', onVisible); },
    };
};

window.Alpine = Alpine;
Alpine.start();

// Homepage scroll-reveal: each [data-reveal] section fades + rises into view the
// first time it enters the viewport, then stays revealed (no re-triggering on
// scroll-back). If IntersectionObserver is unavailable, everything is revealed
// immediately — content is never left permanently hidden waiting on JS.
const revealTargets = document.querySelectorAll('[data-reveal]');
if (revealTargets.length) {
    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-revealed');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -10% 0px' });

        revealTargets.forEach((el) => revealObserver.observe(el));
    } else {
        revealTargets.forEach((el) => el.classList.add('is-revealed'));
    }
}

// Homepage stat counters: each [data-count-up] animates from 0 up to its real value
// (already server-rendered, correct, and locale-digit-formatted) the first time it
// scrolls into view — on page load if it's already visible, same as the reveal above.
// Digits are localized in JS the same way the bn() helper does it server-side, since
// every intermediate frame needs formatting, not just the final one.
const countTargets = document.querySelectorAll('[data-count-up]');
if (countTargets.length) {
    const BENGALI_DIGITS = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
    const isBengaliLocale = document.documentElement.lang.startsWith('bn');
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const formatCount = (n) => isBengaliLocale
        ? String(n).replace(/[0-9]/g, (digit) => BENGALI_DIGITS[Number(digit)])
        : String(n);

    const animateCount = (el) => {
        const target = parseInt(el.dataset.countUp, 10);
        if (! Number.isFinite(target) || target <= 0 || prefersReducedMotion) {
            return;   // already showing the correct server-rendered value
        }

        const duration = 1200;
        const start = performance.now();

        const step = (now) => {
            const progress = Math.min((now - start) / duration, 1);
            const eased = 1 - (1 - progress) ** 3;   // ease-out cubic
            el.textContent = formatCount(Math.round(target * eased));
            if (progress < 1) {
                requestAnimationFrame(step);
            }
        };

        requestAnimationFrame(step);
    };

    if ('IntersectionObserver' in window) {
        const countObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    animateCount(entry.target);
                    countObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.4 });

        countTargets.forEach((el) => countObserver.observe(el));
    }
    // No IntersectionObserver fallback needed: the server-rendered value is already
    // correct and visible, so skipping the animation there is a silent, safe no-op.
}
