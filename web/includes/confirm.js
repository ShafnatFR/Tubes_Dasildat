(function (global) {
    'use strict';

    let pendingCallback = null;
    let bound = false;

    function bind() {
        if (bound) return;
        bound = true;

        const modal = document.getElementById('confirmModal');
        if (!modal) return;

        modal.addEventListener('click', function (e) {
            if (e.target === modal) close();
        });

        document.getElementById('confirmCancel')?.addEventListener('click', close);
        document.getElementById('confirmOk')?.addEventListener('click', function () {
            const cb = pendingCallback;
            close();
            if (typeof cb === 'function') cb();
        });
    }

    function show(title, message, onOk) {
        bind();
        const modal = document.getElementById('confirmModal');
        const titleEl = document.getElementById('confirmTitle');
        const msgEl = document.getElementById('confirmMessage');
        if (!modal || !titleEl || !msgEl) {
            if (typeof onOk === 'function') onOk();
            return;
        }
        titleEl.textContent = title;
        msgEl.textContent = message;
        pendingCallback = onOk;
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function close() {
        document.getElementById('confirmModal')?.classList.remove('open');
        document.body.style.overflow = '';
        pendingCallback = null;
    }

    global.ConfirmUI = { show: show, close: close, bind: bind };
})(window);
