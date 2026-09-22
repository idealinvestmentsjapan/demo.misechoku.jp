/**
 * ミセチョク — フォーム UX 強化（プロフィール・求人票の登録/更新画面用）
 *
 * 1. 変更ガード (data-form-guard)
 *    フォームに変更があるままページを離れようとすると確認ダイアログを出す。
 *    送信時はガードを自動解除。
 *
 * 2. 入力完成度メーター (data-completion-meter)
 *    フォーム内の <input/select/textarea> のうち「name を持つ可視フィールド」を分母に、
 *    入力済みの数をリアルタイム表示する。data-completion-target で表示先を指定、
 *    未指定ならフォーム先頭に自動挿入。
 *    分母から除外したいフィールドは data-meter-ignore を付ける。
 *
 * 使い方:
 *   <form data-form-guard data-completion-meter> ... </form>
 */
(function () {
    'use strict';

    // ================================================================
    // 1. 変更ガード
    // ================================================================
    function initFormGuard(form) {
        var isDirty = false;
        var isSubmitting = false;

        function markDirty() { isDirty = true; }

        form.addEventListener('input', markDirty, { passive: true });
        form.addEventListener('change', markDirty, { passive: true });

        form.addEventListener('submit', function (event) {
            queueMicrotask(function () { isSubmitting = !event.defaultPrevented; });
        });

        window.addEventListener('beforeunload', function (e) {
            if (!isDirty || isSubmitting) return;
            e.preventDefault();
            // Chrome は returnValue の設定が必要
            e.returnValue = '';
        });

        // フォーム外の「キャンセル」リンクを押した時もガードする（data-form-guard-bypass が付いたリンクは素通し）
        document.addEventListener('click', function (e) {
            if (!isDirty || isSubmitting) return;
            var a = e.target.closest('a[href]');
            if (!a) return;
            if (a.hasAttribute('data-form-guard-bypass')) return;
            if (a.getAttribute('href').indexOf('#') === 0) return;
            if (a.target === '_blank') return;
            if (!window.confirm('編集中の内容が保存されていません。ページを離れますか？')) {
                e.preventDefault();
                e.stopPropagation();
            } else {
                isDirty = false; // 確認済みなので beforeunload は出さない
            }
        }, true);
    }

    // ================================================================
    // 2. 入力完成度メーター
    // ================================================================
    function isCountableField(el) {
        if (!el.name) return false;
        if (el.type === 'hidden' || el.type === 'submit' || el.type === 'button') return false;
        if (el.disabled) return false;
        if (el.closest('[data-meter-ignore]')) return false;
        if (el.hasAttribute('data-meter-ignore')) return false;
        // CSRF などの Laravel 内部フィールド
        if (el.name === '_token' || el.name === '_method') return false;
        if (el.closest('[hidden]')) return false;
        var visibleHost = el.closest('label, .register-field, .form-group') || el;
        if (visibleHost.getClientRects().length === 0) return false;
        return true;
    }

    function collectGroups(form) {
        // ラジオ／チェックボックスは name 単位で 1 グループとして数える
        var groups = {};
        Array.prototype.forEach.call(form.elements, function (el) {
            if (!isCountableField(el)) return;
            var key = el.name;
            if (!groups[key]) groups[key] = [];
            groups[key].push(el);
        });
        return groups;
    }

    function isGroupFilled(fields) {
        for (var i = 0; i < fields.length; i++) {
            var el = fields[i];
            if (el.type === 'checkbox' || el.type === 'radio') {
                if (el.checked) return true;
            } else if (el.tagName === 'SELECT') {
                if (el.value !== '' && el.value != null) return true;
            } else {
                if (String(el.value || '').trim() !== '') return true;
            }
        }
        return false;
    }

    function initCompletionMeter(form) {
        var targetSel = form.getAttribute('data-completion-target');
        var host = targetSel ? document.querySelector(targetSel) : null;

        var meter = document.createElement('div');
        meter.className = 'form-meter';
        meter.setAttribute('role', 'status');
        meter.innerHTML =
            '<div class="form-meter__head">' +
            '  <span class="form-meter__label"><i class="fas fa-list-check" aria-hidden="true"></i> 入力状況</span>' +
            '  <span class="form-meter__value" data-meter-value>—</span>' +
            '</div>' +
            '<div class="form-meter__bar"><span class="form-meter__fill" data-meter-fill style="width:0%"></span></div>';

        if (host) {
            host.appendChild(meter);
        } else {
            form.insertBefore(meter, form.firstChild);
        }

        var valueEl = meter.querySelector('[data-meter-value]');
        var fillEl = meter.querySelector('[data-meter-fill]');

        function update() {
            var groups = collectGroups(form);
            var keys = Object.keys(groups);
            var total = keys.length;
            if (total === 0) { meter.hidden = true; return; }
            meter.hidden = false;
            var filled = 0;
            keys.forEach(function (k) { if (isGroupFilled(groups[k])) filled++; });
            var requiredKeys = keys.filter(function (key) {
                return groups[key].some(function (el) {
                    var field = el.closest('.register-field, .form-group, label');
                    return el.required || el.hasAttribute('data-was-required') || el.getAttribute('aria-required') === 'true' ||
                        (field && (field.hasAttribute('data-required') || Array.from(field.querySelectorAll('em')).some(function (em) { return em.textContent.includes('必須'); })));
                });
            });
            var requiredFilled = requiredKeys.filter(function (key) { return isGroupFilled(groups[key]); }).length;
            var pct = requiredKeys.length ? Math.round(requiredFilled / requiredKeys.length * 100) : Math.round(filled / total * 100);
            valueEl.textContent = requiredKeys.length
                ? '必須 ' + requiredFilled + ' / ' + requiredKeys.length + '・任意 ' + (filled - requiredFilled) + ' / ' + (total - requiredKeys.length)
                : '入力済み ' + filled + ' / ' + total + ' 項目';
            fillEl.style.width = pct + '%';
            fillEl.classList.toggle('is-complete', pct >= 100);
            meter.classList.toggle('is-complete', pct >= 100);
        }

        form.addEventListener('input', update, { passive: true });
        form.addEventListener('change', update, { passive: true });
        update();
    }

    // ================================================================
    // 3. 保存フラッシュ → グローバルトースト（統一）
    //    session('message') / session('status') / session('success') を
    //    Blade で <p class="*-flash" data-flash-toast="success">...</p> と書けば、
    //    ページ表示時に window.appToast() で通知して、インラインの p は非表示にする。
    // ================================================================
    function promoteFlashToToast() {
        if (typeof window.appToast !== 'function') return;
        document.querySelectorAll('[data-flash-toast]').forEach(function (el) {
            var msg = (el.textContent || '').trim();
            if (!msg) return;
            var variant = el.getAttribute('data-flash-toast') || 'success';
            window.appToast(msg, variant);
            // エラー・保存結果はページ内にも残し、通知を読み逃しても確認できるようにする。
            el.setAttribute('role', variant === 'error' ? 'alert' : 'status');
        });
    }

    // ================================================================
    // 起動
    // ================================================================
    function boot() {
        document.querySelectorAll('form[data-form-guard]').forEach(initFormGuard);
        document.querySelectorAll('form[data-completion-meter]').forEach(initCompletionMeter);
        promoteFlashToToast();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
