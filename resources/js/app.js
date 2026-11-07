import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';

// Every kiosk action is a normal form post handled by a Laravel controller.
// This file only adds the touch-screen helpers around those forms.

// A double tap must not add the same medicine twice: once a form is sent,
// its buttons stay disabled until the next page loads.
document.addEventListener('submit', (event) => {
    if (event.defaultPrevented) return;
    event.target.querySelectorAll('button').forEach((button) => (button.disabled = true));
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

Alpine.data('receipt', ({ seconds, home }) => ({
    seconds,
    remaining: seconds,

    init() {
        let endsAt = Date.now() + seconds * 1000;

        this.extend = (extra) => (endsAt += extra * 1000);

        this.timer = setInterval(() => {
            this.remaining = Math.max(0, Math.ceil((endsAt - Date.now()) / 1000));
            if (this.remaining === 0) this.done();
        }, 250);
    },

    print() {
        this.extend(15);
        window.print();
    },

    done() {
        clearInterval(this.timer);
        window.location.replace(home);
    },
}));

// The +/- counters in the "How many would you like?" chooser. Strips and
// boxes come off the same shelf stock (counted in strips), so adding a box
// lowers how many strips are left and the other way round. `lineLeft` is
// what the per-item limit still allows after the basket. The server checks
// everything again when the form is sent.
Alpine.data('packCounter', ({ shelf, stripsPerBox, lineLeft, prices, currency }) => ({
    counts: { strip: 0, box: 0 },

    init() {
        if (this.max('strip') > 0) this.counts.strip = 1;
    },

    max(packaging) {
        const stripsPer = packaging === 'box' ? stripsPerBox : 1;
        const heldByOther = packaging === 'box' ? this.counts.strip : this.counts.box * stripsPerBox;

        return Math.max(0, Math.min(lineLeft[packaging], Math.floor((shelf - heldByOther) / stripsPer)));
    },

    step(packaging, by) {
        this.counts[packaging] = Math.max(0, Math.min(this.counts[packaging] + by, this.max(packaging)));
    },

    get empty() {
        return this.counts.strip + this.counts.box === 0;
    },

    get total() {
        return this.counts.strip * prices.strip + this.counts.box * prices.box;
    },

    get summary() {
        const parts = [
            this.counts.strip && `${this.counts.strip} ${this.counts.strip === 1 ? 'strip' : 'strips'}`,
            this.counts.box && `${this.counts.box} ${this.counts.box === 1 ? 'box' : 'boxes'}`,
        ].filter(Boolean);

        return parts.length ? parts.join(' and ') : 'Tap + to choose how many';
    },

    money(amount) {
        return new Intl.NumberFormat('en', { style: 'currency', currency, currencyDisplay: 'narrowSymbol' }).format(amount);
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
