/**
 * MyPage candidate-date availability card behavior (cast and shop share this).
 * Calendar-based month grid inside a modal popup.
 * Selectable range is today .. today + daysAhead.
 *
 * Dependencies (provided by the view):
 *   #availability-card
 *     data-availability-declare-url  POST endpoint (save dates[])
 *     data-availability-clear-url    DELETE endpoint (clear all)
 *     data-availability-max          max selectable dates (5)
 *     data-availability-days-ahead   selectable range in days from today (30)
 *     data-availability-selected     JSON array of saved 'Y-m-d' dates
 *   [data-availability-calendar]     calendar mount point (inside modal)
 *   [data-availability-open]         button that opens the modal
 *   [data-availability-close]        elements that close the modal (backdrop, X, cancel)
 *   [data-availability-save] / [data-availability-clear] buttons
 *   #availability-modal              modal container (hidden by default)
 *
 *   window.MYPAGE_AVAILABILITY_CONFIG = { csrfToken: '...' }
 *
 * Selection lives in a JS Set; "save" replaces the whole set server-side.
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
        var daysAhead = Number(availCard.getAttribute('data-availability-days-ahead') || 30);

        var calendarEl = availCard.querySelector('[data-availability-calendar]');
        var summaryEl = availCard.querySelector('[data-availability-summary]');
        var saveBtn = availCard.querySelector('[data-availability-save]');
        var modalEl = availCard.querySelector('#availability-modal');
        var openBtn = availCard.querySelector('[data-availability-open]');
        var savedSnapshot = null;

        // Sync the visual state of the trigger button and inline icon with is-active.
        function syncActiveVisual(isActive) {
            if (openBtn) {
                var labelEl = openBtn.querySelector('span');
                if (labelEl) labelEl.textContent = isActive ? '変更' : '日を選ぶ';
            }
            var iconWrap = availCard.querySelector('.cast-avail__icon i');
            if (iconWrap) {
                iconWrap.classList.remove('fa-bolt', 'fa-calendar-days');
                iconWrap.classList.add(isActive ? 'fa-bolt' : 'fa-calendar-days');
            }
        }

        function openModal() {
            if (!modalEl) return;
            savedSnapshot = Array.from(selected);
            modalEl.hidden = false;
            document.body.classList.add('has-availability-modal');
            if (openBtn) openBtn.setAttribute('aria-expanded', 'true');
        }

        function closeModal(revert) {
            if (!modalEl) return;
            if (revert && savedSnapshot) {
                selected = new Set(savedSnapshot);
                renderCalendar();
                refreshSummary(availCard.classList.contains('is-active'));
            }
            modalEl.hidden = true;
            document.body.classList.remove('has-availability-modal');
            if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
        }

        var WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

        function toDateStr(d) {
            var m = ('0' + (d.getMonth() + 1)).slice(-2);
            var day = ('0' + d.getDate()).slice(-2);
            return d.getFullYear() + '-' + m + '-' + day;
        }

        var today = new Date();
        today.setHours(0, 0, 0, 0);
        var rangeEnd = new Date(today.getTime());
        rangeEnd.setDate(rangeEnd.getDate() + daysAhead);

        var selected = new Set();
        try {
            var initial = JSON.parse(availCard.getAttribute('data-availability-selected') || '[]');
            (initial || []).forEach(function (d) {
                if (typeof d === 'string') selected.add(d);
            });
        } catch (e) { /* start empty when attribute is malformed */ }

        // Visible month: start at today's month.
        var viewYear = today.getFullYear();
        var viewMonth = today.getMonth();

        function monthDiff(y1, m1, y2, m2) {
            return (y2 - y1) * 12 + (m2 - m1);
        }

        function canGoPrev() {
            return monthDiff(today.getFullYear(), today.getMonth(), viewYear, viewMonth) > 0;
        }

        function canGoNext() {
            return monthDiff(viewYear, viewMonth, rangeEnd.getFullYear(), rangeEnd.getMonth()) > 0;
        }

        function shortLabel(dateStr) {
            var parts = dateStr.split('-');
            var d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
            if (d.getTime() === today.getTime()) return '本日';
            return (d.getMonth() + 1) + '/' + d.getDate();
        }

        function refreshSummary(saved) {
            var dates = Array.from(selected).sort();
            if (summaryEl) {
                if (dates.length === 0) {
                    summaryEl.textContent = '選ぶだけで店舗の検索・SWIPE で優先表示';
                } else {
                    var tagLabel = saved ? '宣言中' : '未保存';
                    summaryEl.innerHTML = dates.map(shortLabel).map(function (label) {
                        return label.replace(/[&<>"']/g, function (c) {
                            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                        });
                    }).join('・') + ' <span class="cast-avail__lead-tag">' + tagLabel + '</span>';
                }
            }
            if (saveBtn) saveBtn.disabled = false;
        }

        function renderCalendar() {
            if (!calendarEl) return;
            calendarEl.innerHTML = '';

            var header = document.createElement('div');
            header.className = 'cast-avail__cal-header';

            var prevBtn = document.createElement('button');
            prevBtn.type = 'button';
            prevBtn.className = 'cast-avail__cal-nav';
            prevBtn.setAttribute('data-availability-nav', 'prev');
            prevBtn.setAttribute('aria-label', '前の月');
            prevBtn.innerHTML = '<i class="fas fa-chevron-left" aria-hidden="true"></i>';
            prevBtn.disabled = !canGoPrev();

            var label = document.createElement('span');
            label.className = 'cast-avail__cal-month';
            label.textContent = viewYear + '年' + (viewMonth + 1) + '月';

            var nextBtn = document.createElement('button');
            nextBtn.type = 'button';
            nextBtn.className = 'cast-avail__cal-nav';
            nextBtn.setAttribute('data-availability-nav', 'next');
            nextBtn.setAttribute('aria-label', '次の月');
            nextBtn.innerHTML = '<i class="fas fa-chevron-right" aria-hidden="true"></i>';
            nextBtn.disabled = !canGoNext();

            header.appendChild(prevBtn);
            header.appendChild(label);
            header.appendChild(nextBtn);
            calendarEl.appendChild(header);

            var grid = document.createElement('div');
            grid.className = 'cast-avail__cal-grid';
            grid.setAttribute('role', 'group');
            grid.setAttribute('aria-label', '入れる候補日の選択（最大' + maxDates + '日）');

            WEEKDAYS.forEach(function (w, i) {
                var wd = document.createElement('span');
                wd.className = 'cast-avail__cal-weekday'
                    + (i === 0 ? ' is-sun' : '')
                    + (i === 6 ? ' is-sat' : '');
                wd.textContent = w;
                grid.appendChild(wd);
            });

            var firstDay = new Date(viewYear, viewMonth, 1);
            var lastDate = new Date(viewYear, viewMonth + 1, 0).getDate();

            for (var b = 0; b < firstDay.getDay(); b++) {
                var blank = document.createElement('span');
                blank.className = 'cast-avail__cal-blank';
                grid.appendChild(blank);
            }

            for (var day = 1; day <= lastDate; day++) {
                var d = new Date(viewYear, viewMonth, day);
                var dateStr = toDateStr(d);
                var inRange = d.getTime() >= today.getTime() && d.getTime() <= rangeEnd.getTime();

                if (!inRange) {
                    var off = document.createElement('span');
                    off.className = 'cast-avail__cal-day is-disabled';
                    off.textContent = String(day);
                    grid.appendChild(off);
                    continue;
                }

                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'cast-avail__cal-day'
                    + (selected.has(dateStr) ? ' is-selected' : '')
                    + (d.getTime() === today.getTime() ? ' is-today' : '')
                    + (d.getDay() === 0 ? ' is-sun' : '')
                    + (d.getDay() === 6 ? ' is-sat' : '');
                btn.setAttribute('data-availability-date', dateStr);
                btn.setAttribute('aria-pressed', selected.has(dateStr) ? 'true' : 'false');
                btn.setAttribute('aria-label', (viewMonth + 1) + '月' + day + '日');
                btn.textContent = String(day);
                grid.appendChild(btn);
            }

            calendarEl.appendChild(grid);
        }

        function toggleDate(dateStr) {
            if (selected.has(dateStr)) {
                selected.delete(dateStr);
            } else {
                if (selected.size >= maxDates) {
                    (window.appToast || window.alert)('候補日は最大' + maxDates + '日分までです', 'error');
                    return;
                }
                selected.add(dateStr);
            }
            renderCalendar();
            refreshSummary(false);
        }

        availCard.addEventListener('click', function (e) {
            var nav = e.target.closest('[data-availability-nav]');
            if (nav && !nav.disabled) {
                if (nav.getAttribute('data-availability-nav') === 'prev' && canGoPrev()) {
                    viewMonth -= 1;
                } else if (nav.getAttribute('data-availability-nav') === 'next' && canGoNext()) {
                    viewMonth += 1;
                }
                if (viewMonth < 0) { viewMonth += 12; viewYear -= 1; }
                if (viewMonth > 11) { viewMonth -= 12; viewYear += 1; }
                renderCalendar();
                return;
            }

            var dayBtn = e.target.closest('[data-availability-date]');
            if (dayBtn) {
                toggleDate(dayBtn.getAttribute('data-availability-date'));
                return;
            }

            var openBtnHit = e.target.closest('[data-availability-open]');
            if (openBtnHit) {
                openModal();
                return;
            }

            var closeHit = e.target.closest('[data-availability-close]');
            if (closeHit) {
                closeModal(true);
                return;
            }

            var save = e.target.closest('[data-availability-save]');
            if (save) {
                var dates = Array.from(selected).sort();
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
                        var nowActive = dates.length > 0;
                        availCard.classList.toggle('is-active', nowActive);
                        syncActiveVisual(nowActive);
                        savedSnapshot = dates.slice();
                        refreshSummary(true);
                        closeModal(false);
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
                        selected.clear();
                        savedSnapshot = [];
                        availCard.classList.remove('is-active');
                        syncActiveVisual(false);
                        if (clearBtn.parentNode) clearBtn.parentNode.removeChild(clearBtn);
                        renderCalendar();
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

        // Close the modal with Escape while it is open.
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modalEl && !modalEl.hidden) {
                closeModal(true);
            }
        });

        renderCalendar();
    });
}());
