/**
 * Cast MyPage "Available Today" declaration card behavior
 *
 * Dependencies (provided by the view):
 *   #availability-card
 *     data-availability-declare-url  POST endpoint (declare)
 *     data-availability-clear-url    DELETE endpoint (clear)
 *
 *   window.MYPAGE_AVAILABILITY_CONFIG = { csrfToken: '...' }
 *
 * Buttons are swapped between the declared / cleared states, so clicks are
 * delegated on the card element.
 * Casts choose a 2 / 4 / 8 hour availability window.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var availCard = document.getElementById('availability-card');
        if (!availCard) return;

        var config = window.MYPAGE_AVAILABILITY_CONFIG || {};
        var csrfToken = config.csrfToken || '';
        var declareUrl = availCard.getAttribute('data-availability-declare-url');
        var clearUrl = availCard.getAttribute('data-availability-clear-url');

        var actionsEl = availCard.querySelector('[data-availability-actions]');
        var titleEl = availCard.querySelector('.cast-avail__title');
        var iconEl = availCard.querySelector('.cast-avail__icon i');
        var leadEl = availCard.querySelector('[data-availability-remaining]');

        function renderActiveState(remainingLabel) {
            availCard.classList.add('is-active');
            if (iconEl) iconEl.className = 'fas fa-bolt';
            if (titleEl) titleEl.textContent = '今から入れます：宣言中';
            if (leadEl) leadEl.textContent = (remainingLabel || '有効中') + '有効';
            if (actionsEl) {
                actionsEl.innerHTML = '<button type="button" class="cast-avail__btn cast-avail__btn--danger" data-availability-clear><i class="fas fa-xmark"></i> OFF</button>';
            }
        }

        function renderInactiveState() {
            availCard.classList.remove('is-active');
            if (iconEl) iconEl.className = 'fas fa-clock';
            if (titleEl) titleEl.textContent = '今から入れます';
            if (leadEl) leadEl.textContent = '有効時間を選ぶと、近くの店舗の SWIPE で優先表示されます';
            if (actionsEl) {
                actionsEl.innerHTML = [2, 4, 8].map(function (hours) {
                    return '<button type="button" class="cast-avail__btn cast-avail__btn--primary" data-availability-declare data-hours="' + hours + '"><i class="fas fa-bolt"></i> ' + hours + '時間</button>';
                }).join('');
            }
        }

        if (!availCard.classList.contains('is-active')) {
            renderInactiveState();
        }

        availCard.addEventListener('click', function (e) {
            var declareBtn = e.target.closest('[data-availability-declare]');
            var clearBtn = e.target.closest('[data-availability-clear]');

            if (declareBtn) {
                var hours = Number(declareBtn.getAttribute('data-hours'));
                declareBtn.disabled = true;

                fetch(declareUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ hours: hours })
                })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
                .then(function (res) {
                    if (res.ok && res.body && res.body.success) {
                        renderActiveState(res.body.remaining_label || (hours + '時間'));
                        (window.appToast || function () {})('「今から入れます」を' + hours + '時間有効にしました', 'success');
                    } else {
                        declareBtn.disabled = false;
                        (window.appToast || window.alert)('宣言できませんでした。もう一度お試しください', 'error');
                    }
                })
                .catch(function () {
                    declareBtn.disabled = false;
                    (window.appToast || window.alert)('通信エラーで宣言できませんでした', 'error');
                });
                return;
            }

            if (clearBtn) {
                clearBtn.disabled = true;
                fetch(clearUrl, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
                .then(function (res) {
                    if (res.ok && res.body && res.body.success) {
                        renderInactiveState();
                        (window.appToast || function () {})('宣言を取り消しました', 'success');
                    } else {
                        clearBtn.disabled = false;
                        (window.appToast || window.alert)('取り消せませんでした', 'error');
                    }
                })
                .catch(function () {
                    clearBtn.disabled = false;
                    (window.appToast || window.alert)('通信エラーで取り消せませんでした', 'error');
                });
            }
        });
    });
}());
