/**
 * 新規登録：先進的ウィザード
 * - 1画面1ステップ + 進捗バー
 * - すべての入力を sessionStorage に自動保存（エラー戻り・タブ復帰で復元、24h TTL）
 * - リアルタイムバリデーション（✓/✗を欄内に即表示）
 * - パスワード強度メーター + 表示切替
 * - Enter で次の入力欄へ自動ジャンプ、最終欄で「次へ」発火
 * - 生年月日 → 年齢バッジを即計算
 * - AI補完/候補プリセット（自己紹介・キャッチコピー等）
 * - カメラ直撮り（HTML capture 属性は blade 側で付与済み）
 * - ドラフト復元トースト（前回入力が残っている時）
 */
(function () {
    'use strict';

    var STORAGE_PREFIX = 'register-form-draft-v2:';

    // -------- 候補プリセット --------
    var SUGGESTS = {
        intro: [
            '明るく元気に、お客様と楽しい時間を作るのが得意です。お酒の知識もアップデート中！',
            '聞き上手なタイプ。仕事終わりに癒される時間を過ごしてほしくて頑張っています。',
            '未経験ですが、覚えるのは早いと思います。丁寧に接客していきたいです。',
            '大学と両立中。トーク・お酒どちらも一生懸命勉強しています。'
        ],
        overview: [
            '落ち着いた大人の空間で、上質な会話とお酒を楽しめる会員制ラウンジです。',
            '未経験でも安心のサポート体制。ノルマなし・自由出勤で働きやすさを追求しています。',
            'ゆったりとしたBGMと洗練された内装。初来店のお客様にも寛いでいただけます。'
        ],
        catch: [
            '最高級の夜を、あなたに。',
            'いつもの一杯を、特別な時間に。',
            '大人だけの、静かな贅沢。',
            '未経験も安心のサポート。'
        ]
    };

    function computePasswordStrength(v) {
        if (!v) return { score: 0, label: '' };
        var s = 0;
        if (v.length >= 8) s++;
        if (v.length >= 12) s++;
        if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
        if (/\d/.test(v)) s++;
        if (/[^A-Za-z0-9]/.test(v)) s++;
        s = Math.min(4, s);
        var labels = ['非常に弱い', '弱い', '普通', '強い', '非常に強い'];
        return { score: s, label: labels[s] };
    }

    function isValidEmail(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }
    function isValidTel(v) { return /^0\d{9,10}$/.test(String(v || '').replace(/[-\s]/g, '')); }
    function isValidZip(v) { return /^\d{3}-?\d{4}$/.test(String(v || '').replace(/\s/g, '')); }

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('.register-form');
        if (!form) return;

        var role = document.body.classList.contains('page-auth-register-cast') ? 'cast'
            : document.body.classList.contains('page-auth-register-shop') ? 'shop'
            : 'default';
        var STORAGE_KEY = STORAGE_PREFIX + role;

        // ============================ 自動保存/復元 ============================
        var restored = false;
        function saveDraft() {
            try {
                var data = {};
                form.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (el) {
                    if (/password/i.test(el.name) || el.type === 'password' || el.type === 'file' || el.type === 'hidden') return;
                    var key = el.name;
                    if (el.type === 'checkbox') {
                        if (/\[\]$/.test(key)) {
                            data[key] = data[key] || [];
                            if (el.checked) data[key].push(el.value);
                        } else {
                            data['@bool:' + key] = el.checked ? '1' : '';
                        }
                    } else if (el.type === 'radio') {
                        if (el.checked) data[key] = el.value;
                    } else {
                        data[key] = el.value;
                    }
                });
                sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ ts: Date.now(), data: data }));
            } catch (e) {}
        }
        function restoreDraft() {
            try {
                var raw = sessionStorage.getItem(STORAGE_KEY);
                if (!raw) return false;
                var payload = JSON.parse(raw);
                if (!payload || !payload.data || (Date.now() - (payload.ts || 0)) > 24 * 3600 * 1000) {
                    sessionStorage.removeItem(STORAGE_KEY);
                    return false;
                }
                var data = payload.data;
                Object.keys(data).forEach(function (key) { if (/password/i.test(key)) delete data[key]; });
                sessionStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
                // サーバーが返した入力値を古いドラフトで上書きしない。
                if (form.querySelector('.register-alert-error')) return false;
                var did = false;
                form.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (el) {
                    if (/password/i.test(el.name) || el.type === 'password' || el.type === 'file' || el.type === 'hidden') return;
                    var key = el.name;
                    if (el.type === 'checkbox') {
                        if (/\[\]$/.test(key)) {
                            var arr = data[key];
                            if (Array.isArray(arr) && el.value) { el.checked = arr.indexOf(el.value) !== -1; did = did || el.checked; }
                        } else if (data['@bool:' + key] !== undefined) {
                            el.checked = data['@bool:' + key] === '1';
                        }
                    } else if (el.type === 'radio') {
                        if (data[key] !== undefined) el.checked = (el.value === data[key]);
                    } else if (data[key] !== undefined) {
                        if (!el.value) { el.value = data[key]; did = did || !!el.value; }
                    }
                });
                return did;
            } catch (e) { return false; }
        }
        function clearDraft() { try { sessionStorage.removeItem(STORAGE_KEY); } catch (e) {} }
        restored = restoreDraft();
        ['input', 'change'].forEach(function (evt) { form.addEventListener(evt, saveDraft, true); });
        // 登録成功のフラッシュがあれば破棄
        if (form.parentElement && form.parentElement.querySelector('.register-alert-success')) clearDraft();

        // ============================ ドラフト復元トースト ============================
        if (restored) {
            var toast = document.createElement('div');
            toast.className = 'rw-toast';
            toast.innerHTML = '<i class="fas fa-clock-rotate-left"></i> 前回の入力を復元しました' +
                '<button type="button" class="rw-toast__x" aria-label="閉じる"><i class="fas fa-times"></i></button>';
            document.body.appendChild(toast);
            var closeToast = function () { toast.classList.remove('is-visible'); setTimeout(function () { toast.remove(); }, 400); };
            toast.querySelector('.rw-toast__x').addEventListener('click', closeToast);
            requestAnimationFrame(function () { toast.classList.add('is-visible'); });
            setTimeout(closeToast, 5000);
        }

        // ============================ パスワード表示切替 + 強度メーター ============================
        form.querySelectorAll('input[type="password"]').forEach(function (input) {
            var wrap = document.createElement('span');
            wrap.className = 'rw-pass-wrap';
            input.parentNode.insertBefore(wrap, input);
            wrap.appendChild(input);
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'rw-pass-toggle';
            btn.setAttribute('aria-label', 'パスワードを表示');
            btn.innerHTML = '<i class="fas fa-eye" aria-hidden="true"></i>';
            wrap.appendChild(btn);
            btn.addEventListener('click', function () {
                var showing = input.type === 'text';
                input.type = showing ? 'password' : 'text';
                btn.setAttribute('aria-label', showing ? 'パスワードを表示' : 'パスワードを隠す');
                btn.querySelector('i').className = showing ? 'fas fa-eye' : 'fas fa-eye-slash';
            });
            // 「パスワード」欄（confirmation でない）のみ強度メーターを付ける
            if (input.name === 'password') {
                var meter = document.createElement('div');
                meter.className = 'rw-strength';
                meter.innerHTML = '<div class="rw-strength__bars">' +
                    '<span></span><span></span><span></span><span></span>' +
                    '</div><span class="rw-strength__label"></span>';
                wrap.parentNode.insertBefore(meter, wrap.nextSibling);
                input.addEventListener('input', function () {
                    var res = computePasswordStrength(input.value);
                    meter.className = 'rw-strength rw-strength--' + res.score;
                    meter.querySelector('.rw-strength__label').textContent = res.label;
                });
            }
        });

        // ============================ リアルタイムバリデーション ============================
        function markValid(input, isOk, hint) {
            var field = input.closest('.register-field, label');
            if (!field) return;
            var mark = field.querySelector('.rw-inline-mark');
            if (!mark) {
                mark = document.createElement('span');
                mark.className = 'rw-inline-mark';
                (input.parentElement || field).appendChild(mark);
            }
            if (isOk === null) {
                mark.className = 'rw-inline-mark';
                mark.textContent = '';
            } else if (isOk) {
                mark.className = 'rw-inline-mark is-ok';
                mark.innerHTML = '<i class="fas fa-check"></i>';
            } else {
                mark.className = 'rw-inline-mark is-ng';
                mark.innerHTML = '<i class="fas fa-times"></i>';
            }
            if (hint) {
                var msg = field.querySelector('.rw-inline-hint');
                if (!msg) {
                    msg = document.createElement('span');
                    msg.className = 'rw-inline-hint';
                    field.appendChild(msg);
                }
                msg.textContent = hint;
                msg.hidden = isOk;
            } else {
                var m = field.querySelector('.rw-inline-hint');
                if (m) m.remove();
            }
        }

        function attachLiveValidator(name, checker, hintNg) {
            form.querySelectorAll('input[name="' + name + '"]').forEach(function (input) {
                var pos = document.createElement('span');
                pos.className = 'rw-input-wrap';
                input.parentNode.insertBefore(pos, input);
                pos.appendChild(input);
                var handler = function () {
                    if (!input.value.trim()) { markValid(input, null); return; }
                    var ok = checker(input.value);
                    markValid(input, ok, ok ? null : hintNg);
                };
                input.addEventListener('input', handler);
                input.addEventListener('blur', handler);
                handler();
            });
        }
        attachLiveValidator('email', isValidEmail, 'メールアドレスの形式が正しくありません');
        attachLiveValidator('phone', isValidTel, '電話番号は 09012345678 のような形式で入力してください');
        attachLiveValidator('zip', isValidZip, '郵便番号は 000-0000 の形式で入力してください');

        // パスワード確認：一致チェック
        var pw = form.querySelector('input[name="password"]');
        var pwc = form.querySelector('input[name="password_confirmation"]');
        if (pw && pwc) {
            var mark = function () {
                if (!pwc.value) { markValid(pwc, null); return; }
                markValid(pwc, pw.value === pwc.value, 'パスワードが一致しません');
            };
            pw.addEventListener('input', mark);
            pwc.addEventListener('input', mark);
        }

        // ============================ 生年月日 → 年齢バッジ ============================
        var birth = form.querySelector('[data-rw-birth]');
        var birthBadge = form.querySelector('[data-rw-age-badge]');
        if (birth && birthBadge) {
            var updateAge = function () {
                if (!birth.value) { birthBadge.hidden = true; return; }
                var d = new Date(birth.value); if (isNaN(d.getTime())) { birthBadge.hidden = true; return; }
                var now = new Date();
                var age = now.getFullYear() - d.getFullYear();
                var m = now.getMonth() - d.getMonth();
                if (m < 0 || (m === 0 && now.getDate() < d.getDate())) age--;
                if (age < 0 || age > 120) { birthBadge.hidden = true; return; }
                birthBadge.hidden = false;
                birthBadge.textContent = age + '歳';
                birthBadge.classList.toggle('is-under', age < 18);
            };
            birth.addEventListener('input', updateAge);
            birth.addEventListener('change', updateAge);
            updateAge();
        }

        // ============================ AI/候補プリセット ============================
        form.querySelectorAll('[data-rw-suggest]').forEach(function (target) {
            var key = target.getAttribute('data-rw-suggest');
            var list = SUGGESTS[key];
            if (!Array.isArray(list) || !list.length) return;
            var wrap = document.createElement('div');
            wrap.className = 'rw-suggest';
            wrap.innerHTML = '<div class="rw-suggest__head"><i class="fas fa-wand-magic-sparkles"></i> ' +
                '候補から選んで貼り付け</div>';
            var listEl = document.createElement('div');
            listEl.className = 'rw-suggest__list';
            list.forEach(function (text) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'rw-suggest__item';
                btn.textContent = text;
                btn.addEventListener('click', function () {
                    target.value = text;
                    target.dispatchEvent(new Event('input', { bubbles: true }));
                    target.focus();
                });
                listEl.appendChild(btn);
            });
            wrap.appendChild(listEl);
            target.parentNode.appendChild(wrap);
        });

        // ============================ Enter で次のフィールドへ自動フォーカス ============================
        form.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' || e.isComposing || e.keyCode === 229) return;
            var t = e.target;
            if (!t || t.tagName === 'TEXTAREA') return;
            if (['submit', 'button'].indexOf(t.type) !== -1) return;
            e.preventDefault();
            var currentCard = t.closest('.register-card');
            if (!currentCard) return;
            var focusables = Array.prototype.slice.call(
                currentCard.querySelectorAll('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])')
            );
            var idx = focusables.indexOf(t);
            if (idx !== -1 && idx < focusables.length - 1) {
                focusables[idx + 1].focus();
            } else {
                // カード最後の欄で Enter → 「次へ」を発火
                var nextBtn = form.querySelector('[data-rw-next]');
                if (nextBtn && !nextBtn.hidden) nextBtn.click();
            }
        });

        // ============================ ステップウィザード本体 ============================
        // サーバーエラー後もステップ構成を維持し、該当する入力へ案内する。

        var cards = Array.prototype.slice.call(form.querySelectorAll('.register-card'));
        if (cards.length < 2) return;
        var termsCard = cards.find(function (c) { return c.classList.contains('register-card-compact'); }) || null;
        var stepCards = cards.filter(function (c) { return c !== termsCard; });
        var actions = form.querySelector('.register-actions');
        if (!actions) return;
        actions.classList.add('register-actions--wizard-hidden');
        // 非表示ステップの項目にブラウザが先にフォーカスしないよう、送信時にまとめて検証する。
        form.noValidate = true;

        Array.prototype.forEach.call(form.querySelectorAll('[required]'), function (el) {
            el.removeAttribute('required');
            el.setAttribute('data-was-required', '1');
        });

        var header = document.createElement('div');
        header.className = 'rw-header';
        header.innerHTML =
            '<div class="rw-progress"><span class="rw-progress__bar"></span></div>' +
            '<div class="rw-progress__meta">' +
            '<span class="rw-step-num" data-rw-num></span>' +
            '<span class="rw-step-title" data-rw-title></span>' +
            '<span class="rw-fill-pct" data-rw-fill hidden></span>' +
            '</div>' +
            '<p class="rw-draft-hint">入力した文章はこのタブで24時間一時保存されます。画像・書類・パスワードは保存されません。</p>';
        form.insertBefore(header, form.firstElementChild);

        var nav = document.createElement('div');
        nav.className = 'rw-nav';
        nav.innerHTML =
            '<p class="rw-error" data-rw-error role="alert" hidden><i class="fas fa-circle-exclamation"></i> <span></span></p>' +
            '<div class="rw-nav__buttons">' +
            '  <button type="button" class="rw-btn rw-btn--back" data-rw-back><i class="fas fa-chevron-left"></i> 戻る</button>' +
            '  <button type="button" class="rw-btn rw-btn--next" data-rw-next>次へ <i class="fas fa-chevron-right"></i></button>' +
            '  <button type="submit" class="rw-btn rw-btn--submit" data-rw-submit hidden><i class="fas fa-check"></i> 登録する</button>' +
            '</div>';
        form.appendChild(nav);

        var backBtn = nav.querySelector('[data-rw-back]');
        var nextBtn = nav.querySelector('[data-rw-next]');
        var submitBtn = nav.querySelector('[data-rw-submit]');
        var errBox = nav.querySelector('[data-rw-error]');
        var errTxt = errBox.querySelector('span');
        var numEl = header.querySelector('[data-rw-num]');
        var titleEl = header.querySelector('[data-rw-title]');
        var fillEl = header.querySelector('[data-rw-fill]');
        var barEl = header.querySelector('.rw-progress__bar');

        var step = 0;
        var serverErrors = {};
        try { serverErrors = JSON.parse(form.dataset.validationErrors || '{}'); } catch (_) {}
        var errorFields = [];
        Object.keys(serverErrors).forEach(function (name, index) {
            var inputs = Array.from(form.elements).filter(function (el) { return el.name === name || el.name === name.replace(/\.(\d+)$/, '[]') || el.name === name + '[]'; });
            if (!inputs.length) return;
            var input = inputs[0];
            var field = input.closest('.register-field, .register-check, label') || input.parentElement;
            var hint = document.createElement('span');
            hint.id = 'register-server-error-' + index;
            hint.className = 'rw-inline-hint';
            hint.textContent = [].concat(serverErrors[name]).join(' ');
            field.appendChild(hint);
            inputs.forEach(function (el) {
                el.setAttribute('aria-invalid', 'true');
                el.setAttribute('aria-describedby', ((el.getAttribute('aria-describedby') || '') + ' ' + hint.id).trim());
                el.addEventListener('input', function () { el.removeAttribute('aria-invalid'); hint.hidden = true; });
            });
            errorFields.push(input);
        });
        if (errorFields.length) {
            var errorStep = stepCards.indexOf(errorFields[0].closest('.register-card'));
            step = errorStep >= 0 ? errorStep : stepCards.length - 1;
            var summary = form.querySelector('.register-alert-error');
            if (summary) errorFields.forEach(function (input) {
                var link = document.createElement('button');
                link.type = 'button';
                link.className = 'btn-secondary-cta';
                var field = input.closest('.register-field, label');
                var label = field && field.querySelector('span');
                link.textContent = (label ? label.textContent.trim() : '該当項目') + 'を確認';
                link.addEventListener('click', function () {
                    var targetStep = stepCards.indexOf(input.closest('.register-card'));
                    step = targetStep >= 0 ? targetStep : stepCards.length - 1;
                    render(); input.focus();
                });
                summary.appendChild(link);
            });
        }

        function cardTitle(card) { var h = card.querySelector('.register-card-head h2'); return h ? h.textContent.trim() : ''; }
        function isVisible(el) { return el.offsetParent !== null; }
        function fieldFilled(field) {
            var input = field.querySelector('input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]), select, textarea');
            if (input) { if (input.type === 'file') return !!(input.files && input.files.length); return String(input.value || '').trim() !== ''; }
            var cb = field.querySelector('input[type="checkbox"]:checked');
            var rb = field.querySelector('input[type="radio"]:checked');
            return !!cb || !!rb;
        }
        function requiredMissing(card) {
            var missing = [];
            card.querySelectorAll('.register-field, .register-check, .metric-field, .bwh-field').forEach(function (field) {
                field.classList.remove('is-missing');
                var em = field.querySelector('em');
                var required = field.hasAttribute('data-required') || (em && em.textContent.indexOf('必須') !== -1) || !!field.querySelector('[data-was-required]');
                if (!required || !isVisible(field)) return;
                if (!fieldFilled(field)) missing.push(field);
            });
            missing.forEach(function (f) { f.classList.add('is-missing'); });
            return missing;
        }
        function cardFillRate(card) {
            var total = 0, filled = 0;
            card.querySelectorAll('.register-field, .register-check, .metric-field, .bwh-field').forEach(function (field) {
                var required = field.hasAttribute('data-required') || !!field.querySelector('[data-was-required]') || Array.from(field.querySelectorAll('em')).some(function (em) { return em.textContent.includes('必須'); });
                if (!required || !isVisible(field)) return;
                total++;
                if (fieldFilled(field)) filled++;
            });
            return total ? Math.round(filled / total * 100) : 100;
        }

        function render() {
            stepCards.forEach(function (c, i) { c.hidden = (i !== step); });
            if (termsCard) termsCard.hidden = (step !== stepCards.length - 1);
            var last = step === stepCards.length - 1;
            nextBtn.hidden = last;
            submitBtn.hidden = !last;
            backBtn.disabled = step === 0;
            errBox.hidden = true;
            numEl.textContent = 'STEP ' + (step + 1) + ' / ' + stepCards.length;
            titleEl.textContent = cardTitle(stepCards[step]);
            barEl.style.width = Math.round(((step + 1) / stepCards.length) * 100) + '%';
            var pct = cardFillRate(stepCards[step]);
            if (pct < 100) { fillEl.hidden = false; fillEl.textContent = '必須入力 ' + pct + '%'; }
            else { fillEl.hidden = true; }
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        form.addEventListener('input', function () {
            var pct = cardFillRate(stepCards[step]);
            if (pct < 100) { fillEl.hidden = false; fillEl.textContent = '必須入力 ' + pct + '%'; }
            else { fillEl.hidden = true; }
        });

        nextBtn.addEventListener('click', function () {
            var missing = requiredMissing(stepCards[step]);
            if (missing.length) {
                errBox.hidden = false; errTxt.textContent = '必須項目を入力してください（' + missing.length + '件）';
                missing[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                var input = missing[0].querySelector('input, select, textarea');
                if (input) { try { input.focus({ preventScroll: true }); } catch (e) {} }
                return;
            }
            if (step < stepCards.length - 1) { step++; render(); }
        });
        backBtn.addEventListener('click', function () { if (step > 0) { step--; render(); } });
        submitBtn.addEventListener('click', function (e) {
            var terms = form.querySelector('input[name="terms"]');
            if (terms && !terms.checked) {
                e.preventDefault(); errBox.hidden = false; errTxt.textContent = '利用規約とプライバシーポリシーに同意してください';
                if (termsCard) termsCard.classList.add('is-missing');
                return;
            }
            var missing = requiredMissing(stepCards[step]);
            if (missing.length) {
                e.preventDefault(); errBox.hidden = false; errTxt.textContent = '必須項目を入力してください（' + missing.length + '件）';
                missing[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
        });
        form.addEventListener('submit', function (event) {
            for (var i = 0; i < stepCards.length; i++) {
                var card = stepCards[i];
                var wasHidden = card.hidden;
                card.hidden = false;
                var missing = requiredMissing(card);
                var invalid = Array.from(card.querySelectorAll('input, select, textarea')).find(function (el) {
                    return isVisible(el) && !el.disabled && el.willValidate && !el.validity.valid;
                });
                card.hidden = wasHidden;
                if (!missing.length && !invalid) continue;
                event.preventDefault();
                step = i; render();
                errBox.hidden = false;
                errTxt.textContent = invalid ? invalid.validationMessage : '必須項目を入力してください（' + missing.length + '件）';
                var target = invalid || missing[0].querySelector('input, select, textarea');
                if (target) target.focus();
                return;
            }
            var terms = form.querySelector('input[name="terms"]');
            if (terms && !terms.checked) {
                event.preventDefault();
                step = stepCards.length - 1; render();
                errBox.hidden = false; errTxt.textContent = '利用規約とプライバシーポリシーに同意してください';
                terms.focus();
            }
        }, true);
        render();
        if (errorFields.length) errorFields[0].focus({ preventScroll: true });
    });
})();
