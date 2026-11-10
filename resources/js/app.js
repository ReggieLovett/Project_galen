import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';

// Every kiosk action is handled by a Laravel controller. Basket forms use
// fetch so the page can be refreshed without navigating away: each element
// marked data-kiosk-region (catalogue, basket, toasts, the prescription
// popup, the assistant's chat) is swapped for its copy in the response.
// Anything the controller flashes must render inside one of them, or the
// customer never sees it.
document.addEventListener('submit', async (event) => {
    if (event.defaultPrevented || !(event.target instanceof HTMLFormElement)) return;

    const form = event.target;
    const buttons = form.querySelectorAll('button');
    const isBasketAction = new URL(form.action, window.location.origin).pathname.includes('/kiosk/basket');

    if (!isBasketAction) {
        buttons.forEach((button) => (button.disabled = true));
        return;
    }

    event.preventDefault();

    if (form.dataset.submitting === 'true') return;

    form.dataset.submitting = 'true';
    buttons.forEach((button) => (button.disabled = true));

    // Scrolled lists keep their place across the swap.
    const scrollTops = new Map([...document.querySelectorAll('[data-keep-scroll]')].map((element) => [element.dataset.keepScroll, element.scrollTop]));

    try {
        const response = await fetch(form.action, {
            method: form.method || 'GET',
            body: new FormData(form),
            headers: {
                Accept: 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) throw new Error(`Basket request failed: ${response.status}`);

        const documentHtml = new DOMParser().parseFromString(await response.text(), 'text/html');
        const currentRegions = document.querySelectorAll('[data-kiosk-region]');

        currentRegions.forEach((region) => {
            const replacement = documentHtml.querySelector(`[data-kiosk-region="${region.dataset.kioskRegion}"]`);

            if (!replacement) throw new Error(`Missing kiosk region: ${region.dataset.kioskRegion}`);

            Alpine.destroyTree(region);
            region.replaceWith(replacement);
            Alpine.initTree(replacement);
        });

        history.replaceState(null, '', response.url);

        // After Alpine's own $nextTick work (the chat scrolls itself to the end), before the next paint.
        requestAnimationFrame(() => {
            scrollTops.forEach((top, name) => document.querySelector(`[data-keep-scroll="${name}"]`)?.scrollTo({ top }));
        });
    } catch (error) {
        console.error('Unable to update the basket without navigating.', error);
        window.location.reload();
    }
});

// Never show a previous customer's screen from the back/forward cache.
window.addEventListener('pageshow', (event) => event.persisted && window.location.reload());

// "Are you still there?" after `timeout` seconds without a touch, then the
// reset form is submitted after `countdown` more.
Alpine.data('idleTimer', ({ timeout, countdown }) => ({
    open: false,
    remaining: countdown,
    lastTouch: Date.now(),

    init() {
        const touched = () => {
            if (!this.open) this.lastTouch = Date.now();
        };
        window.addEventListener('pointerdown', touched);
        window.addEventListener('keydown', touched);

        setInterval(() => {
            const idleFor = (Date.now() - this.lastTouch) / 1000;
            if (idleFor < timeout) return;

            this.open = true;
            this.remaining = Math.max(0, Math.ceil(timeout + countdown - idleFor));
            if (this.remaining === 0) this.$refs.reset.submit();
        }, 1000);
    },

    stillHere() {
        this.lastTouch = Date.now();
        this.open = false;
    },
}));

// On-screen keyboard. It types into the input whose id it was opened for.
// "Go" moves on to the form's next empty keyboard field, like email then
// password, and submits once they are all filled. Shift lasts one letter.
Alpine.data('keyboard', () => ({
    open: false,
    target: null,
    shift: false,

    show(id) {
        this.target = id;
        this.open = true;
        this.$nextTick(() => document.getElementById(id)?.scrollIntoView({ block: 'nearest' }));
    },

    toggle(id) {
        this.open && this.target === id ? (this.open = false) : this.show(id);
    },

    press(key) {
        const input = document.getElementById(this.target);
        if (!input) return;

        if (key === 'shift') {
            this.shift = !this.shift;
            return;
        }

        if (key === 'enter') {
            const next = [...(input.form?.querySelectorAll('input[inputmode="none"]') ?? [])].find((field) => field !== input && !field.value);
            if (next) {
                this.show(next.id);
                next.focus();
                return;
            }

            this.open = false;
            input.form?.requestSubmit();
            return;
        }

        if (key === 'backspace') input.value = input.value.slice(0, -1);
        else if (key === 'clear') input.value = '';
        else {
            const typed = key === 'space' ? ' ' : this.shift ? key.toUpperCase() : key;
            this.shift = false;
            input.value = (input.value + typed).slice(0, input.maxLength > 0 ? input.maxLength : undefined);
        }

        input.focus();
    },
}));

// Scroll the category chips in a predictable touch-sized step. The arrows
// remain hidden when there is no more content in that direction.
Alpine.data('horizontalScroller', () => ({
    atStart: true,
    atEnd: false,

    init() {
        const scroller = this.$refs.scroller;

        this.update = () => {
            this.atStart = scroller.scrollLeft <= 1;
            this.atEnd = scroller.scrollLeft + scroller.clientWidth >= scroller.scrollWidth - 1;
        };

        scroller.addEventListener('scroll', this.update, { passive: true });
        window.addEventListener('resize', this.update);
        this.$nextTick(this.update);
    },

    // A basket form swaps the catalogue (and this nav) for a fresh copy: let go of the window.
    destroy() {
        window.removeEventListener('resize', this.update);
    },

    scrollBy(direction) {
        this.$refs.scroller.scrollBy({
            left: direction * Math.max(220, this.$refs.scroller.clientWidth * 0.65),
            behavior: 'smooth',
        });
    },
}));

// The receipt screen. The slip is on screen from the start; printing pulls
// it up into the on-screen printer and out of view, as the kiosk's real
// printer takes it: on its own after `printDelay` seconds, or at once when
// the customer taps Print. A demo, so nothing goes to a real printer. The
// motion itself is CSS (.slip in app.css); this only moves `printState`
// through waiting → printing → printed, and through `returning` (the slip
// back on screen for a moment) when it is printed again. The screen never
// resets, and Done never leaves, before the slip has printed.
const SLIP_READING_SECONDS = 20;

Alpine.data('receipt', ({ seconds, home, printDelay }) => ({
    seconds,
    remaining: seconds,
    printDelay,
    printIn: printDelay,
    printState: 'waiting',
    leaving: false,

    init() {
        let endsAt = Date.now() + seconds * 1000;
        const printAt = Date.now() + printDelay * 1000;

        this.extend = (extra) => (endsAt += extra * 1000);

        this.timer = setInterval(() => {
            this.remaining = Math.max(0, Math.ceil((endsAt - Date.now()) / 1000));

            if (this.printState === 'waiting') {
                this.printIn = Math.max(0, Math.ceil((printAt - Date.now()) / 1000));
                if (this.printIn === 0) this.print();
            }

            if (this.remaining === 0) this.leave();
        }, 250);
    },

    print() {
        if (this.leaving || this.printState === 'printing' || this.printState === 'returning') return;

        if (this.printState === 'waiting') {
            this.startFeed();
            return;
        }

        // Printing again: the slip comes back on screen, then goes into the printer again.
        this.extend(15);
        this.printState = 'returning';
        setTimeout(() => this.startFeed(), 650);
    },

    startFeed() {
        this.printState = 'printing';
        this.showPrinter();

        // If the browser never plays the animation (animations off, screen not drawing),
        // don't leave the slip stuck half-way: it counts as printed anyway.
        clearTimeout(this.feedFallback);
        this.feedFallback = setTimeout(() => this.printState === 'printing' && this.finishPrint(), 3500);
    },

    // The slip has gone into the printer (or, with reduced motion, faded out).
    printed(event) {
        if (event.target === this.$refs.slip && ['slip-print', 'slip-vanish'].includes(event.animationName)) {
            this.finishPrint();
        }
    },

    // The slip has printed: leave time to take it, or go if Done was already tapped.
    finishPrint() {
        clearTimeout(this.feedFallback);
        this.printState = 'printed';

        if (this.leaving) {
            setTimeout(() => this.leave(), 1200);
        } else if (this.remaining < SLIP_READING_SECONDS) {
            this.extend(SLIP_READING_SECONDS - this.remaining);
        }
    },

    // On a narrow screen the printer sits below the buttons: bring it into view as it prints.
    showPrinter() {
        const { top } = this.$refs.printer.getBoundingClientRect();

        if (top >= 0 && top <= window.innerHeight - 160) return;

        this.$refs.printer.scrollIntoView({
            block: 'start',
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
        });
    },

    // Done: never send the customer away without their slip.
    done() {
        if (this.leaving) return;

        this.leaving = true;

        if (this.printState === 'printed') {
            this.leave();
        } else if (this.printState === 'waiting') {
            this.startFeed();
        }
        // Printing already: finishPrint() leaves once the slip is out.
    },

    leave() {
        clearInterval(this.timer);
        window.location.replace(home);
    },
}));

// The +/- counters on each medicine card: single pieces (tablets, capsules)
// and whole boxes, both starting at 0. They come off the same shelf stock,
// counted in pieces, so adding a box lowers how many single pieces are left
// and the other way round. `lineLeft` is what a per-item limit still allows
// after the basket (null: no limit). The server checks everything again
// when the form is sent.
Alpine.data('packCounter', ({ shelf, piecesPerBox, lineLeft, prices, currency }) => ({
    counts: { piece: 0, box: 0 },

    max(packaging) {
        const piecesPer = packaging === 'box' ? piecesPerBox : 1;
        const heldByOther = packaging === 'box' ? this.counts.piece : this.counts.box * piecesPerBox;

        return Math.max(0, Math.min(lineLeft[packaging] ?? Infinity, Math.floor((shelf - heldByOther) / piecesPer)));
    },

    step(packaging, by) {
        this.counts[packaging] = Math.max(0, Math.min(this.counts[packaging] + by, this.max(packaging)));
    },

    get empty() {
        return this.counts.piece + this.counts.box === 0;
    },

    get total() {
        return this.counts.piece * prices.piece + this.counts.box * prices.box;
    },

    // A counter has reached what we can sell, so its + is greyed out.
    get atLimit() {
        return ['piece', 'box'].some((packaging) => this.counts[packaging] > 0 && this.counts[packaging] >= this.max(packaging));
    },

    money(amount) {
        return new Intl.NumberFormat('en', { style: 'currency', currency, currencyDisplay: 'narrowSymbol' }).format(amount);
    },
}));

// A medicine card's "What is it for?" description. Tapping the card's
// picture or the button opens it, and closes any other card's. Scrolling
// the catalogue on by more than a little closes it again, so the list
// stays short while the customer browses.
Alpine.data('medicineDetails', (key) => ({
    key,
    open: false,
    openedAt: 0,

    toggle() {
        this.open = !this.open;

        if (this.open) {
            this.openedAt = this.$root.closest('[data-catalogue]')?.scrollTop ?? 0;
            this.$dispatch('details-opened', key);
        }
    },

    scrolled(top) {
        if (this.open && Math.abs(top - this.openedAt) > 64) this.open = false;
    },
}));

// A double tap (or double click) on the idle screen's logo opens the staff
// login. A single tap behaves like the rest of the screen and starts a
// session, once it is clear no second tap is coming.
Alpine.data('staffDoor', (url) => ({
    taps: 0,

    tap() {
        clearTimeout(this.timer);

        if (++this.taps >= 2) {
            window.location.assign(url);
            return;
        }

        this.timer = setTimeout(() => {
            this.taps = 0;
            this.$el.closest('form').requestSubmit();
        }, 400);
    },
}));

Alpine.data('clock', () => ({
    time: '',

    init() {
        const format = new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' });
        const update = () => (this.time = format.format(new Date()));
        update();
        setInterval(update, 10_000);
    },
}));

Alpine.plugin(focus);
Alpine.start();
