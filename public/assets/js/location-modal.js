/**
 * Search-origin modal (current location / passport / profile address / radius).
 * - Address input shows autosuggest candidates from /api/geocoding/suggest;
 *   picking one saves lat/lng directly (no second geocode, no typo failures).
 * - Area preset chips set a popular district in one tap.
 * - Radius chips apply instantly via /setting/location/radius.
 * Results are reflected with window.location.reload().
 */
(function () {
    function ready(fn) {
        if (document.readyState !== 'loading') fn();
        else document.addEventListener('DOMContentLoaded', fn);
    }

    ready(function () {
        var trigger = document.getElementById('location-pill-trigger');
        var overlay = document.getElementById('location-modal-overlay');
        if (!trigger || !overlay) return;

        var msgEl = document.getElementById('location-modal-message');
        var passportForm = document.getElementById('location-passport-form');
        var passportInput = document.getElementById('location-passport-input');
        var suggestList = document.getElementById('location-suggest-list');
        var areaPresets = document.getElementById('location-area-presets');
        var radiusChips = document.getElementById('location-radius-chips');
        var btnCurrent = document.getElementById('location-use-current');
        var btnProfile = document.getElementById('location-use-profile');
        var btnClear = document.getElementById('location-clear');
        var closeBtns = overlay.querySelectorAll('.js-location-close');

        function open() {
            overlay.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }
        function close() {
            overlay.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            hideSuggest();
        }
        function showMessage(text, isSuccess) {
            if (!msgEl) return;
            msgEl.hidden = false;
            msgEl.textContent = text;
            msgEl.classList.toggle('is-success', !!isSuccess);
        }
        function clearMessage() {
            if (!msgEl) return;
            msgEl.hidden = true;
            msgEl.textContent = '';
            msgEl.classList.remove('is-success');
        }
        function csrf() {
            var meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        }

        function postJson(url, payload) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                },
                body: JSON.stringify(payload),
            }).then(function (r) {
                return r.json().then(function (json) {
                    if (!r.ok) throw json;
                    return json;
                });
            });
        }

        function saveLocation(payload, successMsg) {
            return postJson('/setting/location', payload).then(function () {
                showMessage(successMsg || '拠点を設定しました。反映します...', true);
                setTimeout(function () { window.location.reload(); }, 450);
            });
        }

        trigger.addEventListener('click', function () {
            clearMessage();
            open();
        });
        closeBtns.forEach(function (btn) { btn.addEventListener('click', close); });
        overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.getAttribute('aria-hidden') === 'false') close();
        });

        // ---- current location -------------------------------------------------
        if (btnCurrent) {
            var btnCurrentHtml = btnCurrent.innerHTML;
            btnCurrent.addEventListener('click', function () {
                clearMessage();
                if (!('geolocation' in navigator)) {
                    showMessage('このブラウザは位置情報に対応していません。', false);
                    return;
                }
                btnCurrent.disabled = true;
                btnCurrent.textContent = '位置情報を取得中...';
                navigator.geolocation.getCurrentPosition(function (pos) {
                    saveLocation({
                        mode: 'current',
                        lat: pos.coords.latitude,
                        lng: pos.coords.longitude,
                        label: '現在地',
                    }, '現在地を拠点にしました。反映します...').catch(function (err) {
                        btnCurrent.disabled = false;
                        btnCurrent.innerHTML = btnCurrentHtml;
                        showMessage((err && err.message) || '保存に失敗しました。', false);
                    });
                }, function (err) {
                    btnCurrent.disabled = false;
                    btnCurrent.innerHTML = btnCurrentHtml;
                    var msg = '位置情報の取得に失敗しました。';
                    if (err && err.code === 1) msg = '位置情報の利用が許可されていません。ブラウザ設定をご確認ください。';
                    showMessage(msg, false);
                }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 });
            });
        }

        // ---- address autosuggest ---------------------------------------------
        var suggestTimer = null;
        var suggestAbort = null;

        function hideSuggest() {
            if (!suggestList) return;
            suggestList.hidden = true;
            suggestList.innerHTML = '';
            if (passportInput) passportInput.setAttribute('aria-expanded', 'false');
        }

        function renderSuggest(candidates) {
            if (!suggestList) return;
            suggestList.innerHTML = '';
            if (!candidates.length) {
                hideSuggest();
                return;
            }
            candidates.forEach(function (c) {
                var li = document.createElement('li');
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'location-modal__suggest-item';
                btn.setAttribute('role', 'option');
                btn.innerHTML = '<i class="fas fa-location-dot" aria-hidden="true"></i>';
                btn.appendChild(document.createTextNode(c.label));
                btn.addEventListener('click', function () {
                    hideSuggest();
                    passportInput.value = c.label;
                    clearMessage();
                    saveLocation({
                        mode: 'passport',
                        lat: c.latitude,
                        lng: c.longitude,
                        label: c.label,
                        address: c.label,
                    }, '「' + c.label + '」を拠点にしました。反映します...').catch(function (err) {
                        showMessage((err && err.message) || '保存に失敗しました。', false);
                    });
                });
                li.appendChild(btn);
                suggestList.appendChild(li);
            });
            suggestList.hidden = false;
            passportInput.setAttribute('aria-expanded', 'true');
        }

        function fetchSuggest(q) {
            if (suggestAbort) suggestAbort.abort();
            suggestAbort = ('AbortController' in window) ? new AbortController() : null;
            fetch('/api/geocoding/suggest?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json' },
                signal: suggestAbort ? suggestAbort.signal : undefined,
            }).then(function (r) { return r.ok ? r.json() : { candidates: [] }; })
            .then(function (json) {
                renderSuggest((json && json.candidates) || []);
            }).catch(function () { /* aborted or offline: keep silent */ });
        }

        if (passportInput && suggestList) {
            passportInput.addEventListener('input', function () {
                var q = passportInput.value.trim();
                if (suggestTimer) clearTimeout(suggestTimer);
                if (q.length < 2) {
                    hideSuggest();
                    return;
                }
                suggestTimer = setTimeout(function () { fetchSuggest(q); }, 300);
            });
            passportInput.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') hideSuggest();
            });
            document.addEventListener('click', function (e) {
                if (!suggestList.hidden && !suggestList.contains(e.target) && e.target !== passportInput) {
                    hideSuggest();
                }
            });
        }

        // ---- passport form submit (fallback: server-side geocode) ------------
        if (passportForm) {
            passportForm.addEventListener('submit', function (e) {
                e.preventDefault();
                clearMessage();
                hideSuggest();
                var address = passportInput ? passportInput.value.trim() : '';
                if (!address) {
                    showMessage('住所または駅名を入力してください。', false);
                    return;
                }
                var submitBtn = passportForm.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;
                saveLocation({ mode: 'passport', address: address, label: address },
                    '「' + address + '」を拠点にしました。反映します...')
                    .catch(function (err) {
                        if (submitBtn) submitBtn.disabled = false;
                        showMessage((err && err.message) || '保存に失敗しました。', false);
                    });
            });
        }

        // ---- area preset chips (one-tap passport) ----------------------------
        if (areaPresets) {
            areaPresets.querySelectorAll('.location-modal__chip').forEach(function (chip) {
                chip.addEventListener('click', function () {
                    clearMessage();
                    hideSuggest();
                    var area = chip.getAttribute('data-area') || '';
                    if (!area) return;
                    areaPresets.querySelectorAll('.location-modal__chip').forEach(function (c) { c.disabled = true; });
                    saveLocation({ mode: 'passport', address: area, label: area },
                        '「' + area + '」を拠点にしました。反映します...')
                        .catch(function (err) {
                            areaPresets.querySelectorAll('.location-modal__chip').forEach(function (c) { c.disabled = false; });
                            showMessage((err && err.message) || '保存に失敗しました。', false);
                        });
                });
            });
        }

        // ---- profile address shortcut ----------------------------------------
        if (btnProfile) {
            btnProfile.addEventListener('click', function () {
                clearMessage();
                btnProfile.disabled = true;
                saveLocation({ mode: 'profile' }, 'プロフィール住所を拠点にしました。反映します...')
                    .catch(function (err) {
                        btnProfile.disabled = false;
                        showMessage((err && err.message) || '保存に失敗しました。', false);
                    });
            });
        }

        // ---- radius chips (apply instantly) ----------------------------------
        if (radiusChips) {
            radiusChips.querySelectorAll('.location-modal__radius-chip').forEach(function (chip) {
                chip.addEventListener('click', function () {
                    if (chip.classList.contains('is-active')) return;
                    clearMessage();
                    var km = parseInt(chip.getAttribute('data-km'), 10);
                    if (isNaN(km)) return;
                    radiusChips.querySelectorAll('.location-modal__radius-chip').forEach(function (c) { c.disabled = true; });
                    postJson('/setting/location/radius', { max_distance_km: km })
                        .then(function () {
                            radiusChips.querySelectorAll('.location-modal__radius-chip').forEach(function (c) {
                                c.classList.toggle('is-active', c === chip);
                            });
                            showMessage(km === 0 ? '距離制限を解除しました。反映します...' : '検索半径を' + km + 'kmにしました。反映します...', true);
                            setTimeout(function () { window.location.reload(); }, 450);
                        })
                        .catch(function (err) {
                            radiusChips.querySelectorAll('.location-modal__radius-chip').forEach(function (c) { c.disabled = false; });
                            showMessage((err && err.message) || '保存に失敗しました。', false);
                        });
                });
            });
        }

        // ---- clear (back to profile address) ---------------------------------
        if (btnClear) {
            btnClear.addEventListener('click', function () {
                clearMessage();
                btnClear.disabled = true;
                fetch('/setting/location', {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf(),
                    },
                }).then(function (r) { return r.json(); })
                .then(function () {
                    showMessage('解除しました。反映します...', true);
                    setTimeout(function () { window.location.reload(); }, 450);
                }).catch(function () {
                    btnClear.disabled = false;
                    showMessage('解除に失敗しました。', false);
                });
            });
        }
    });
})();
