/**
 * MyPage candidate-date availability card behavior (cast and shop share this).
 *
 * Dependencies (provided by the view):
 *   #availability-card
 *     data-availability-declare-url  POST endpoint (save dates[])
 *     data-availability-clear-url    DELETE endpoint (clear all)
 *     data-availability-max          max selectable dates (5)
 *   [data-availability-date="Y-m-d"] toggle chips
 *   [data-availability-save] / [data-availability-clear] buttons
 *
 *   window.MYPAGE_AVAILABILITY_CONFIG = { csrfToken: '...' }
 *
 * Selection is toggled client-side; "save" replaces the whole set server-side.
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
        var maxDates = Number(availCard.getAttribute('data-availability-max') || 5);

        var summaryEl = availCard.querySelector('[data-availability-summary]');
        var saveBtn = availCard.querySelector('[data-availability-save]');

        function selectedChips() {
            return Array.prototype.slice.call(
                availCard.querySelectorAll('[data-availability-date].is-selected')
            );
        }

        function selectedDates() {
            return selectedChips().map(function (chip) {
                return chip.getAttribute('data-availability-date');
            });
        }

        function shortLabel(dateStr) {
            var parts = dateStr.split('-');
            var d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            if (d.getTime() === today.getTime()) return '本日';
            return (d.getMonth() + 1) + '/' + d.getDate();
        }

        function refreshSummary(saved) {
            var dates = selectedDates();
            if (summaryEl) {
                if (dates.length === 0) {
                    summaryEl.textContent = '入れる日を選ぶと（最大' + maxDates + '日）、優先表示・日付検索の対象になります';
                } else {
                    summaryEl.textContent = dates.map(shortLabel).join('・') + (saved ? ' を宣言中' : ' を選択中（未保存）');
                }
            }
            if (saveBtn) saveBtn.disabled = false;
        }

        availCard.addEventListener('click', function (e) {
            var chip = e.target.closest('[data-availability-date]');
            if (chip) {
                if (chip.classList.contains('is-selected')) {
                    chip.classList.remove('is-selected');
                    chip.setAttribute('aria-pressed', 'false');
                } else {
                    if (selectedChips().length >= maxDates) {
                        (window.appToast || window.alert)('候補日は最大' + maxDates + '日分までです', 'error');
                        return;
                    }
                    chip.classList.add('is-selected');
                    chip.setAttribute('aria-pressed', 'true');
                }
                refreshSummary(false);
                return;
            }

            var save = e.target.closest('[data-availability-save]');
            if (save) {
                var dates = selectedDates();
                save.disabled = true;

                fetch(declareUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ dates: dates })
                })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
                .then(function (res) {
                    save.disabled = false;
                    if (res.ok && res.body && res.body.success) {
                        availCard.classList.toggle('is-active', dates.length > 0);
                        refreshSummary(true);
                        (window.appToast || function () {})(res.body.message || '候補日を保存しました', 'success');
                    } else {
                        var msg = (res.body && res.body.message) || '保存できませんでした。もう一度お試しください';
                        (window.appToast || window.alert)(msg, 'error');
                    }
                })
                .catch(function () {
                    save.disabled = false;
                    (window.appToast || window.alert)('通信エラーで保存できませんでした', 'error');
                });
                return;
            }

            var clearBtn = e.target.closest('[data-availability-clear]');
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
                    clearBtn.disabled = false;
                    if (res.ok && res.body && res.body.success) {
                        selectedChips().forEach(function (chip) {
                            chip.classList.remove('is-selected');
                            chip.setAttribute('aria-pressed', 'false');
                        });
                        availCard.classList.remove('is-active');
                        refreshSummary(true);
                        (window.appToast || function () {})('候補日の設定を取り消しました', 'success');
                    } else {
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
