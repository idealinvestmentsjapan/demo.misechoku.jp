/* グローバル トースト
 * 使い方:
 *   window.appToast('保存しました')
 *   window.appToast('保存に失敗しました', 'error')
 *   window.appToast('処理中…', 'info', 3000)
 * variant: 'success' | 'error' | 'info' | null（default = success系）
 */
(function () {
    'use strict';
    if (window.appToast) return;

    let toastEl = null;
    let toastTimer = null;

    function ensureEl() {
        if (toastEl) return toastEl;
        toastEl = document.createElement('div');
        toastEl.className = 'app-toast';
        toastEl.setAttribute('role', 'status');
        toastEl.setAttribute('aria-live', 'polite');
        toastEl.inert = true;
        document.body.appendChild(toastEl);
        return toastEl;
    }

    window.appToast = function (msg, variant, duration) {
        if (!msg) return;
        const el = ensureEl();
        const dialogs = Array.from(document.querySelectorAll('[role="dialog"][aria-modal="true"]')).filter(function (dialog) {
            return !dialog.hidden && dialog.getAttribute('aria-hidden') !== 'true' && dialog.getClientRects().length;
        });
        (dialogs[dialogs.length - 1] || document.body).appendChild(el);
        el.inert = false;
        el.textContent = '';
        var text = document.createElement('span');
        text.textContent = msg;
        el.appendChild(text);
        el.setAttribute('role', variant === 'error' ? 'alert' : 'status');
        el.setAttribute('aria-live', variant === 'error' ? 'assertive' : 'polite');
        if (variant === 'error') {
            var close = document.createElement('button');
            close.type = 'button';
            close.className = 'btn-ghost-cta';
            close.style.pointerEvents = 'auto';
            close.textContent = '閉じる';
            close.addEventListener('click', function () { el.classList.remove('is-visible'); el.inert = true; });
            el.appendChild(close);
        }
        el.classList.remove('is-success', 'is-error', 'is-info');
        if (variant === 'error') el.classList.add('is-error');
        else if (variant === 'info') el.classList.add('is-info');
        else el.classList.add('is-success');
        el.classList.add('is-visible');
        clearTimeout(toastTimer);
        const d = typeof duration === 'number' ? duration : (variant === 'error' ? 0 : 5000);
        if (d > 0) toastTimer = setTimeout(function () { el.classList.remove('is-visible'); el.inert = true; }, d);
    };
})();
