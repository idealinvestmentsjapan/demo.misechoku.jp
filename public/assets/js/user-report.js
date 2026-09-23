/**
 * Misechoku - user report modal (cast and shop, talk room header).
 *
 * Extracted from an inline script in common/talk/room.blade.php so that it
 * loads unconditionally for both roles (it was once trapped inside a
 * cast-only Blade block, which left the shop-side button dead) and so the
 * behavior is covered by the JSDOM frontend tests.
 *
 * Expected markup:
 *   [data-user-report-open][data-target-type][data-target-id]  trigger button
 *   [data-user-report-modal]                                   modal wrapper (hidden)
 *     [data-user-report-form][data-endpoint]                   form
 *     [data-target-type] / [data-target-id]                    hidden inputs
 *     [data-user-report-feedback] / [data-user-report-submit]
 *     [data-user-report-close]                                 overlay / close buttons
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.querySelector('[data-user-report-modal]');
        if (!modal) return;
        var form = modal.querySelector('[data-user-report-form]');
        var feedback = modal.querySelector('[data-user-report-feedback]');
        var submitBtn = modal.querySelector('[data-user-report-submit]');
        var targetTypeInput = modal.querySelector('[data-target-type]');
        var targetIdInput = modal.querySelector('[data-target-id]');
        if (!form || !targetTypeInput || !targetIdInput) return;
        var endpoint = form.getAttribute('data-endpoint');
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

        function setFeedback(kind, text) {
            if (!feedback) return;
            if (!kind) { feedback.hidden = true; feedback.className = 'user-report-modal__feedback'; return; }
            feedback.className = 'user-report-modal__feedback is-' + kind;
            feedback.textContent = text;
            feedback.hidden = false;
        }

        function openModal(targetType, targetId) {
            form.reset();
            setFeedback(null);
            if (submitBtn) submitBtn.disabled = false;
            targetTypeInput.value = targetType;
            targetIdInput.value = targetId;
            modal.hidden = false;
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            modal.hidden = true;
            document.body.style.overflow = '';
        }

        document.querySelectorAll('[data-user-report-open]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openModal(btn.getAttribute('data-target-type'), btn.getAttribute('data-target-id'));
            });
        });
        modal.querySelectorAll('[data-user-report-close]').forEach(function (el) {
            el.addEventListener('click', closeModal);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.hidden) closeModal();
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            setFeedback(null);
            if (submitBtn) submitBtn.disabled = true;

            var fd = new FormData(form);
            var payload = {};
            fd.forEach(function (v, k) { payload[k] = v; });

            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
            .then(function (res) {
                if (res.ok && res.body && res.body.success) {
                    setFeedback('success', res.body.message || '通報を受け付けました。');
                    window.setTimeout(closeModal, 1800);
                } else {
                    if (submitBtn) submitBtn.disabled = false;
                    setFeedback('error', (res.body && res.body.message) || '通報の送信に失敗しました。時間をおいて再度お試しください。');
                }
            })
            .catch(function () {
                if (submitBtn) submitBtn.disabled = false;
                setFeedback('error', '通信エラーで通報を送信できませんでした。');
            });
        });
    });
})();
