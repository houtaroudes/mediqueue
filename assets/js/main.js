// MediQueue base JavaScript (Phase 1)
// Keep everything inside the MediQueue namespace so pages don't fight each other

const MediQueue = {
    // mobile hamburger menu
    initNav: function () {
        const toggle = document.getElementById('navToggle');
        const links = document.getElementById('navLinks');

        if (toggle && links) {
            toggle.addEventListener('click', function () {
                links.classList.toggle('open');
            });
        }
    },

    // landing-page NOW SERVING board: poll the JSON feed and flip digits
    initBoard: function () {
        const board = document.querySelector('[data-board]');
        if (!board) return;

        const feedUrl = board.getAttribute('data-feed');
        if (!feedUrl) return;

        let current = board.getAttribute('data-number') || '';
        let timer = null;
        const POLL_MS = 5000;

        const numberEl = board.querySelector('.board-number');
        const statusEl = board.querySelector('.board-status');
        const nextEl = board.querySelector('.board-next-item');
        const nextWrap = board.querySelector('.board-next');
        const timeEl = board.querySelector('.board-time');

        const esc = function (s) {
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        };

        const render = function (data) {
            // number digit flip when it actually changed
            const num = data.number || null;
            if ((num || '') !== current) {
                current = num || '';
                if (numberEl) {
                    numberEl.classList.remove('board-idle');
                    if (num) {
                        numberEl.textContent = num;
                        // retrigger the power-on flicker
                        numberEl.style.animation = 'none';
                        void numberEl.offsetWidth;
                        numberEl.style.animation = '';
                    } else {
                        numberEl.textContent = '--:--';
                        numberEl.classList.add('board-idle');
                    }
                }
            }

            if (statusEl) {
                if (num) {
                    const waiting = data.waiting > 0 ? ' \u00b7 ' + data.waiting + ' waiting' : '';
                    statusEl.textContent = (data.status === 'in_consultation' ? 'In consultation' : 'Called to the consultation room') + waiting;
                } else {
                    statusEl.textContent = 'No one is being served right now.';
                }
            }

            if (nextEl && nextWrap) {
                if (data.next && data.next.length) {
                    nextEl.textContent = data.next.join('  ');
                    nextWrap.style.display = '';
                } else {
                    nextWrap.style.display = 'none';
                }
            }

            if (timeEl) {
                timeEl.textContent = data.time || timeEl.textContent;
            }
        };

        const poll = function () {
            fetch(feedUrl, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) { if (data) render(data); })
                .catch(function () { /* keep last state; retry next tick */ });
        };

        const start = function () {
            if (timer) return;
            timer = setInterval(poll, POLL_MS);
        };

        const stop = function () {
            clearInterval(timer);
            timer = null;
        };

        // pause polling when the tab is hidden, resume on return
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) { stop(); } else { poll(); start(); }
        });

        start();
    },

    // auth + long forms: show the submit was received while the next page loads
    initSubmitButtons: function () {
        const form = document.querySelector('form.js-submit');
        if (!form) return;

        form.addEventListener('submit', function () {
            if (form.dataset.submitted) return; // double-submit guard
            form.dataset.submitted = '1';

            const btn = form.querySelector('.js-btn');
            if (btn) {
                btn.classList.add('is-busy');
                btn.disabled = true;
            }
            form.classList.add('is-sending');
        });

        // we survived a redirect and a flash message exists -> the previous
        // action succeeded, so confirm the button that caused it
        if (document.querySelector('.alert-success')) {
            form.classList.add('is-confirmed');
        }
    },

    // sign-out interstitial: board winds down to --:-- then navigates
    initBye: function () {
        const bye = document.querySelector('.bye');
        if (!bye) return;

        const digits = bye.querySelector('.bye-board');
        if (!digits) return;

        const finalText = digits.textContent; // --:--
        const frames = ['A---', '-A--', '--A-', '--:-', '---:'];
        let i = 0;

        const tick = function () {
            if (i < frames.length) {
                digits.textContent = frames[i];
                i++;
                setTimeout(tick, 180);
            } else {
                digits.textContent = finalText;
                bye.classList.add('bye-done');
            }
        };
        setTimeout(tick, 250);
    }

    // later phases add: form validation helpers, queue auto-refresh, etc.
};

document.addEventListener('DOMContentLoaded', function () {
    // one quiet fade as the page paints (disabled under reduced motion via CSS)
    document.body.classList.add('is-entering');

    MediQueue.initNav();
    MediQueue.initBoard();
    MediQueue.initSubmitButtons();
    MediQueue.initBye();
});
