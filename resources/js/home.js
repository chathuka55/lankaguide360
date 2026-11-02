// Home page interactions (loaded only on the home page):
//   GSAP + ScrollTrigger  reveals, hero intro, parallax, the sticky "How it works" steps
//   Lenis                 smooth scrolling, kept in sync with ScrollTrigger
//   Swiper                hero slideshow (fade) and the draggable card carousels
//   plus 3D tilt cards and a pointer "spotlight" glow.
// Everything respects "reduce motion": no smooth scroll, no parallax, no autoplay.
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import Lenis from 'lenis';
import Swiper from 'swiper';
import { A11y, Autoplay, EffectFade, FreeMode, Keyboard, Navigation } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/effect-fade';

gsap.registerPlugin(ScrollTrigger);

const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

// Hide reveal targets before the first paint; removed again if anything below fails.
if (!reduce) {
    document.documentElement.classList.add('motion-ready');
}

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    /** Full-width hero slideshow with a synced "featured place" card and progress bars. */
    Alpine.data('heroSlider', (count) => ({
        index: 0,
        progress: reduce || count < 2 ? 100 : 0,
        swiper: null,
        init() {
            if (!this.$refs.slides) return;
            this.swiper = new Swiper(this.$refs.slides, {
                modules: [EffectFade, Autoplay],
                effect: 'fade',
                fadeEffect: { crossFade: true },
                loop: count > 1,
                speed: 1200,
                allowTouchMove: false,
                autoplay: reduce || count < 2 ? false : { delay: 6500, disableOnInteraction: false },
                on: {
                    slideChange: (s) => (this.index = s.realIndex),
                    autoplayTimeLeft: (s, time, ratio) => (this.progress = Math.round((1 - ratio) * 100)),
                },
            });
        },
        next() {
            this.swiper?.slideNext();
        },
        prev() {
            this.swiper?.slidePrev();
        },
        go(i) {
            this.swiper?.slideToLoop(i);
        },
    }));

    /** Accessible "I'm into …" listbox that feeds ?category= to the Trip Builder. */
    Alpine.data('interestPicker', (options) => ({
        options,
        open: false,
        active: -1,
        selected: null,
        toggle() {
            this.open ? this.close() : this.openList();
        },
        openList() {
            this.open = true;
            this.active = this.selected ? this.options.indexOf(this.selected) : -1;
            this.$nextTick(() => this.$refs.list?.focus());
        },
        close() {
            if (!this.open) return;
            this.open = false;
            this.$refs.button?.focus();
        },
        move(step) {
            this.active = Math.max(-1, Math.min(this.options.length - 1, this.active + step));
        },
        choose(i) {
            this.selected = i < 0 ? null : this.options[i];
            this.close();
        },
    }));

    /** Draggable card row with arrow buttons (free mode, keyboard). */
    Alpine.data('cardCarousel', () => ({
        init() {
            new Swiper(this.$refs.swiper, {
                modules: [Navigation, FreeMode, Keyboard, A11y],
                slidesPerView: 'auto',
                spaceBetween: 20,
                grabCursor: true,
                freeMode: { enabled: true, momentumRatio: 0.6 },
                keyboard: { enabled: true, onlyInViewport: true },
                navigation: { prevEl: this.$refs.prev, nextEl: this.$refs.next },
            });
        },
    }));
});

function smoothScroll() {
    const lenis = new Lenis({ lerp: 0.1, anchors: true });
    lenis.on('scroll', ScrollTrigger.update);
    gsap.ticker.add((time) => lenis.raf(time * 1000));
    gsap.ticker.lagSmoothing(0);
}

function animations() {
    // Hero: lines and cards rise in, the photo drifts slower than the page.
    gsap.fromTo('[data-hero-line]', { y: 40, opacity: 0 }, { y: 0, opacity: 1, duration: 1, ease: 'power3.out', stagger: 0.1, delay: 0.1 });
    gsap.fromTo('[data-hero-card], [data-hero-cards] > *', { y: 60, opacity: 0 }, { y: 0, opacity: 1, duration: 1.1, ease: 'power3.out', stagger: 0.1, delay: 0.35 });

    const hero = document.querySelector('[data-hero]');
    if (hero) {
        gsap.to(hero.querySelector('.swiper'), { yPercent: 14, ease: 'none', scrollTrigger: { trigger: hero, start: 'top top', end: 'bottom top', scrub: true } });
    }

    ScrollTrigger.batch('[data-reveal]', {
        start: 'top 88%',
        once: true,
        onEnter: (elements) => gsap.to(elements, { opacity: 1, y: 0, duration: 0.9, ease: 'power3.out', stagger: 0.12 }),
    });

    document.querySelectorAll('[data-parallax]').forEach((el) => {
        gsap.to(el, {
            yPercent: parseFloat(el.dataset.parallax) || -15,
            ease: 'none',
            scrollTrigger: { trigger: el.closest('section') ?? el, start: 'top bottom', end: 'bottom top', scrub: true },
        });
    });
}

/** "How it works": the step in the middle of the screen is highlighted in the sticky list. */
function stickySteps() {
    const section = document.querySelector('[data-steps]');
    if (!section) return;
    section.querySelectorAll('[data-step]').forEach((el) => {
        ScrollTrigger.create({
            trigger: el,
            start: 'top 60%',
            end: 'bottom 40%',
            onToggle: (self) => {
                if (self.isActive) window.Alpine.$data(section).active = Number(el.dataset.step);
            },
        });
    });
}

function pointerEffects() {
    document.querySelectorAll('.spotlight').forEach((el) => {
        el.addEventListener('pointermove', (e) => {
            const r = el.getBoundingClientRect();
            el.style.setProperty('--mx', `${e.clientX - r.left}px`);
            el.style.setProperty('--my', `${e.clientY - r.top}px`);
        });
    });

    if (reduce || !finePointer) return;
    document.querySelectorAll('.tilt-card').forEach((el) => {
        el.addEventListener('pointermove', (e) => {
            const r = el.getBoundingClientRect();
            el.style.setProperty('--ry', `${((e.clientX - r.left) / r.width - 0.5) * 12}deg`);
            el.style.setProperty('--rx', `${-((e.clientY - r.top) / r.height - 0.5) * 12}deg`);
        });
        el.addEventListener('pointerleave', () => {
            el.style.setProperty('--rx', '0deg');
            el.style.setProperty('--ry', '0deg');
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    pointerEffects();
    if (reduce) return;

    try {
        smoothScroll();
        animations();
        stickySteps();
    } catch (error) {
        document.documentElement.classList.remove('motion-ready');
        gsap.set('[data-hero-line], [data-hero-card], [data-hero-cards] > *', { clearProps: 'all' });
        console.error(error);
    }
});
