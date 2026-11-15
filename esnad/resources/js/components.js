// Small Alpine components shared by every page. Kept light: public pages
// must stay under 150 KB of JavaScript (section 18.4).

const EASTERN = /[٠-٩۰-۹]/g;
export const toWestern = (s) =>
    String(s).replace(EASTERN, (d) => {
        const c = d.charCodeAt(0);
        return String(c >= 0x06f0 ? c - 0x06f0 : c - 0x0660);
    });

export function register(Alpine) {
    // Converts Eastern Arabic digits to Western as the user types.
    Alpine.directive('western-digits', (el) => {
        el.addEventListener('input', () => {
            const pos = el.selectionStart;
            const v = toWestern(el.value);
            if (v !== el.value) {
                el.value = v;
                try { el.setSelectionRange(pos, pos); } catch (e) {}
            }
        });
    });

    // One-time password: separate boxes that behave as one field, paste
    // support and the browser's one-time-code autofill.
    Alpine.data('otpInput', (length = 6, autosubmit = true) => ({
        length,
        digits: Array(length).fill(''),
        get value() { return this.digits.join(''); },
        focus(i) { const el = this.$refs['d' + i]; if (el) { el.focus(); el.select(); } },
        onInput(i, e) {
            const raw = toWestern(e.target.value).replace(/\D/g, '');
            if (raw.length > 1) { this.fill(raw, i); return; }
            this.digits[i] = raw;
            e.target.value = raw;
            if (raw && i < this.length - 1) this.focus(i + 1);
            this.maybeSubmit();
        },
        onKeydown(i, e) {
            if (e.key === 'Backspace' && !this.digits[i] && i > 0) { this.digits[i - 1] = ''; this.focus(i - 1); e.preventDefault(); }
            const rtl = document.dir === 'rtl';
            if (e.key === (rtl ? 'ArrowRight' : 'ArrowLeft') && i > 0) { this.focus(i - 1); e.preventDefault(); }
            if (e.key === (rtl ? 'ArrowLeft' : 'ArrowRight') && i < this.length - 1) { this.focus(i + 1); e.preventDefault(); }
        },
        onPaste(e) {
            const text = toWestern((e.clipboardData || window.clipboardData).getData('text')).replace(/\D/g, '');
            if (text) { e.preventDefault(); this.fill(text, 0); }
        },
        fill(text, from) {
            for (let k = 0; k < text.length && from + k < this.length; k++) this.digits[from + k] = text[k];
            this.focus(Math.min(from + text.length, this.length - 1));
            this.maybeSubmit();
        },
        maybeSubmit() {
            if (autosubmit && this.value.length === this.length) {
                this.$nextTick(() => this.$root.closest('form')?.requestSubmit());
            }
        },
    }));

    // Countdown for "send the code again".
    Alpine.data('countdown', (seconds, template = ':seconds') => ({
        left: seconds,
        get message() { return template.replace(':seconds', this.left); },
        init() { const t = setInterval(() => { if (--this.left <= 0) clearInterval(t); }, 1000); },
    }));

    // Accessible tabs: arrow keys follow reading direction.
    Alpine.data('tabs', (initial = 0) => ({
        active: initial,
        select(i) { this.active = i; this.$nextTick(() => this.$refs['tab' + i]?.focus()); },
        key(e, count) {
            const rtl = document.dir === 'rtl';
            const next = rtl ? 'ArrowLeft' : 'ArrowRight';
            const prev = rtl ? 'ArrowRight' : 'ArrowLeft';
            if (e.key === next) this.select((this.active + 1) % count);
            if (e.key === prev) this.select((this.active - 1 + count) % count);
            if (e.key === 'Home') this.select(0);
            if (e.key === 'End') this.select(count - 1);
        },
    }));

    // Modal and bottom sheet share the same behaviour: focus trap light,
    // Escape closes, focus returns to the opener.
    Alpine.data('dialog', () => ({
        open: false,
        opener: null,
        show() { this.opener = document.activeElement; this.open = true; this.$nextTick(() => this.$refs.panel?.querySelector('button, [href], input, select, textarea')?.focus()); },
        hide() { this.open = false; this.opener?.focus(); },
    }));

    Alpine.store('toast', {
        items: [],
        push(message, kind = 'info') {
            const id = Date.now() + Math.random();
            this.items.push({ id, message, kind });
            setTimeout(() => (this.items = this.items.filter((t) => t.id !== id)), 5000);
        },
    });
}
