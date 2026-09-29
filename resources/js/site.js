// Small Alpine components for the public site (bundled with app.js).

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export function toast(message) {
    window.dispatchEvent(new CustomEvent('toast', { detail: { message } }));
}

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    /** Counts up to `target` when scrolled into view (Home stats). */
    Alpine.data('countUp', (target) => ({
        value: 0,
        init() {
            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const run = () => {
                if (reduce || target === 0) {
                    this.value = target;
                    return;
                }
                const start = performance.now();
                const step = (now) => {
                    const progress = Math.min(1, (now - start) / 1200);
                    this.value = Math.round(target * (1 - Math.pow(1 - progress, 3)));
                    if (progress < 1) requestAnimationFrame(step);
                };
                requestAnimationFrame(step);
            };
            const observer = new IntersectionObserver((entries) => {
                if (entries.some((e) => e.isIntersecting)) {
                    run();
                    observer.disconnect();
                }
            });
            observer.observe(this.$el);
        },
        get formatted() {
            return this.value.toLocaleString();
        },
    }));

    /** Simple auto-advancing carousel; pauses on hover/focus. */
    Alpine.data('carousel', (count, interval = 6000) => ({
        index: 0,
        count,
        timer: null,
        init() {
            this.play();
        },
        play() {
            if (this.count > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                this.timer = setInterval(() => this.next(), interval);
            }
        },
        pause() {
            clearInterval(this.timer);
        },
        next() {
            this.index = (this.index + 1) % this.count;
        },
        prev() {
            this.index = (this.index - 1 + this.count) % this.count;
        },
    }));

    /** Photo gallery lightbox with keyboard navigation. */
    Alpine.data('lightbox', (photos) => ({
        photos,
        open: false,
        index: 0,
        show(i) {
            this.index = i;
            this.open = true;
            this.$nextTick(() => this.$refs.close?.focus());
        },
        next() {
            this.index = (this.index + 1) % this.photos.length;
        },
        prev() {
            this.index = (this.index - 1 + this.photos.length) % this.photos.length;
        },
        get current() {
            return this.photos[this.index];
        },
    }));

    /** "Add to my trip" toggle; works without JS through the form fallback. */
    Alpine.data('addToTrip', ({ url, added = false }) => ({
        added,
        busy: false,
        async toggle() {
            this.busy = true;
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.message);
                this.added = data.added;
                toast(data.message);
            } catch {
                toast('Sorry, something went wrong. Please try again.');
            } finally {
                this.busy = false;
            }
        },
    }));

    /** AI travel assistant; the conversation survives page changes via sessionStorage. */
    Alpine.data('chatbot', ({ url }) => ({
        open: false,
        busy: false,
        draft: '',
        messages: [],
        suggestions: ['Best beaches in December?', 'Plan a 7-day trip', 'Hidden gems near Kandy', 'Where can I see elephants?'],

        init() {
            try {
                this.messages = JSON.parse(sessionStorage.getItem('lg-chat') || '[]');
                this.open = sessionStorage.getItem('lg-chat-open') === '1';
            } catch {
                this.messages = [];
            }
            this.$watch('open', (value) => {
                try { sessionStorage.setItem('lg-chat-open', value ? '1' : '0'); } catch {}
                if (value) this.$nextTick(() => { this.scroll(); this.$refs.input?.focus(); });
            });
        },

        toggle() {
            this.open = !this.open;
        },

        persist() {
            try { sessionStorage.setItem('lg-chat', JSON.stringify(this.messages.slice(-20))); } catch {}
        },

        scroll() {
            this.$nextTick(() => { if (this.$refs.log) this.$refs.log.scrollTop = this.$refs.log.scrollHeight; });
        },

        reset() {
            this.messages = [];
            this.persist();
        },

        async send(text = null) {
            const message = (text ?? this.draft).trim();
            if (!message || this.busy) return;

            const history = this.messages.slice(-6).map(({ role, content }) => ({ role, content }));
            this.messages.push({ role: 'user', content: message });
            this.draft = '';
            this.busy = true;
            this.scroll();

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ message, history }),
                });
                if (response.status === 429) {
                    throw new Error('You have sent a lot of messages. Please wait a few minutes and try again.');
                }
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || 'Sorry, something went wrong.');
                this.messages.push({ role: 'assistant', content: data.reply, links: data.links || [] });
            } catch (e) {
                this.messages.push({ role: 'assistant', content: e.message || 'Sorry, I could not answer right now. Please try again.', links: [] });
            } finally {
                this.busy = false;
                this.persist();
                this.scroll();
            }
        },
    }));

    /** Toast area listening for `toast` window events. */
    Alpine.data('toaster', (initial = null) => ({
        items: [],
        init() {
            if (initial) this.push(initial);
            window.addEventListener('toast', (e) => this.push(e.detail.message));
        },
        push(message) {
            const id = Date.now() + Math.random();
            this.items.push({ id, message });
            setTimeout(() => (this.items = this.items.filter((t) => t.id !== id)), 4000);
        },
    }));
});
