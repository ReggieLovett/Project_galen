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

// On-screen keyboard. It types into the input whose id it was opened for;
// "Go" submits that input's form.
Alpine.data('keyboard', () => ({
    open: false,
    target: null,

    show(id) {
        this.target = id;
        this.open = true;
    },

    toggle(id) {
        this.open && this.target === id ? (this.open = false) : this.show(id);
    },

    press(key) {
        const input = document.getElementById(this.target);
        if (!input) return;

        if (key === 'enter') {
            this.open = false;
            input.form?.requestSubmit();
            return;
        }

        if (key === 'backspace') input.value = input.value.slice(0, -1);
        else if (key === 'clear') input.value = '';
        else input.value = (input.value + (key === 'space' ? ' ' : key)).slice(0, input.maxLength > 0 ? input.maxLength : undefined);

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

// Five quick taps on the idle screen's logo opens the staff login. A single
// tap behaves like the rest of the screen and starts a session.
Alpine.data('staffDoor', (url) => ({
    taps: 0,

    tap() {
        clearTimeout(this.timer);

        if (++this.taps >= 5) {
            window.location.assign(url);
            return;
        }

        this.timer = setTimeout(() => {
            if (this.taps === 1) this.$el.closest('form').requestSubmit();
            this.taps = 0;
        }, 600);
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
