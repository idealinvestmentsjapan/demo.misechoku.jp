(function () {
    'use strict';

    // 背景を操作できないダイアログでは、フォーカスもダイアログ内に保つ。
    var active = [];
    var inerted = new Map();
    var focusSelector = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    function visible(el) {
        return !el.hidden && el.getAttribute('aria-hidden') !== 'true' && el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden';
    }
    function focusables(dialog) {
        return Array.from(dialog.querySelectorAll(focusSelector)).filter(function (el) { return visible(el) && !el.closest('[inert]'); });
    }
    function syncDialogs() {
        if (typeof document === 'undefined' || !document.body) return;
        var dialogs = Array.from(document.querySelectorAll('[role="dialog"][aria-modal="true"]')).filter(visible);
        var closed = active.filter(function (entry) { return !dialogs.includes(entry.dialog); });
        active = active.filter(function (entry) { return dialogs.includes(entry.dialog); });
        var added = dialogs.filter(function (dialog) { return !active.some(function (entry) { return entry.dialog === dialog; }); });
        added.forEach(function (dialog) { active.push({ dialog: dialog, opener: document.activeElement }); });
        inerted.forEach(function (original, el) { el.inert = original; });
        inerted.clear();
        var top = active.length ? active[active.length - 1].dialog : null;
        if (top) {
            var branch = top;
            while (branch.parentElement && branch !== document.body) {
                Array.from(branch.parentElement.children).forEach(function (sibling) {
                    if (sibling === branch || ['SCRIPT', 'STYLE', 'LINK'].includes(sibling.tagName)) return;
                    inerted.set(sibling, sibling.inert);
                    sibling.inert = true;
                });
                branch = branch.parentElement;
            }
            if (added.length || !top.contains(document.activeElement)) {
                if (!top.hasAttribute('tabindex')) top.tabIndex = -1;
                (focusables(top)[0] || top).focus({ preventScroll: true });
            }
        } else if (closed.length) {
            var opener = closed[0].opener;
            if (opener && opener.isConnected && visible(opener)) opener.focus({ preventScroll: true });
        }
    }
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Tab' || !active.length) return;
        var dialog = active[active.length - 1].dialog;
        var items = focusables(dialog);
        var first = items[0] || dialog;
        var last = items[items.length - 1] || dialog;
        if (!dialog.contains(document.activeElement) || (!event.shiftKey && document.activeElement === last) || (event.shiftKey && document.activeElement === first) || !items.length) {
            event.preventDefault();
            (event.shiftKey ? last : first).focus();
        }
    });
    new MutationObserver(syncDialogs).observe(document.body, { subtree: true, childList: true, attributes: true, attributeFilter: ['hidden', 'aria-hidden', 'class', 'style'] });
    syncDialogs();

    // 戻る先は同じロール・アカウントの一覧に限定して記録する。
    var scope = document.body.dataset.navigationScope;
    if (!scope) return;
    var key = 'misechoku-navigation-v1:' + scope;
    function isList(url) { return url.origin === location.origin && /^\/(cast|shop)\/(search|home|keeps)(\/|$)/.test(url.pathname); }
    var current = new URL(location.href);
    if (isList(current)) {
        document.addEventListener('click', function (event) {
            var link = event.target.closest('a[href]');
            if (!link || event.defaultPrevented || event.ctrlKey || event.metaKey || event.shiftKey || link.target === '_blank') return;
            var target = new URL(link.href, location.href);
            if (target.origin !== current.origin || !/^\/(cast|shop)\//.test(target.pathname)) return;
            try { sessionStorage.setItem(key, JSON.stringify({ from: current.href, to: target.href, y: window.scrollY, ts: Date.now() })); } catch (_) {}
        });
    }
    function restoreNavigation() {
        try {
            var saved = JSON.parse(sessionStorage.getItem(key) || 'null');
            if (!saved || Date.now() - saved.ts > 30 * 60 * 1000) return;
            var from = new URL(saved.from);
            if (!isList(from) || from.pathname.split('/')[1] !== scope.split(':')[0]) return;
            if (saved.to === current.href) {
                var back = document.querySelector('#global-header .btn-back');
                if (back) back.href = from.href;
            }
            if (saved.from === current.href) requestAnimationFrame(function () { window.scrollTo({ top: Math.max(0, Number(saved.y) || 0), behavior: 'instant' }); });
        } catch (_) {}
    }
    window.addEventListener('pageshow', restoreNavigation);
    restoreNavigation();
})();
