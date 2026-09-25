/**
 * 検索：ミニマルバー・並び替えパネル・詳細検索（FAB）・条件サマリ
 */
(function () {
    var keywordInput = document.getElementById('search-keyword');
    var simpleSubmitBtn = document.getElementById('search-keyword-submit');
    var sortTrigger = document.getElementById('search-sort-trigger');
    var sortPanel = document.getElementById('search-sort-panel');
    var sortCurrent = document.getElementById('search-sort-current');
    var modal = document.getElementById('detail-search-modal');
    var openBtn = document.getElementById('open-detail-search');
    var form = modal ? document.getElementById('detail-search-form') : null;
    var badgeEl = document.getElementById('detail-search-badge');
    var summaryEl = document.getElementById('search-condition-summary');
    var summaryTextEl = document.getElementById('search-condition-summary-text');
    var locationOptions = modal ? modal.querySelectorAll('.detail-search-location-option') : [];
    var distanceSlider = modal ? modal.querySelector('#search-distance-km') : null;
    var distanceValueEl = modal ? modal.querySelector('#search-distance-value') : null;

    function closeSortPanel() {
        if (!sortPanel || !sortTrigger) return;
        sortPanel.hidden = true;
        sortTrigger.setAttribute('aria-expanded', 'false');
    }

    function toggleSortPanel() {
        if (!sortPanel || !sortTrigger) return;
        var open = sortPanel.hidden;
        sortPanel.hidden = !open;
        sortTrigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    if (sortTrigger && sortPanel) {
        sortTrigger.addEventListener('click', function (e) {
            e.stopPropagation();
            toggleSortPanel();
        });
        document.addEventListener('click', function (e) {
            if (sortPanel.hidden) return;
            if (sortTrigger.contains(e.target) || sortPanel.contains(e.target)) return;
            closeSortPanel();
        });
    }

    if (sortPanel) {
        sortPanel.querySelectorAll('[data-search-sort-value]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var v = btn.getAttribute('data-search-sort-value');
                if (sortCurrent) sortCurrent.value = v || '';
                sortPanel.querySelectorAll('[data-search-sort-value]').forEach(function (b) {
                    b.classList.toggle('is-active', b.getAttribute('data-search-sort-value') === v);
                });
                closeSortPanel();
                doSearch(buildSearchParams());
            });
        });
    }

    function openModal() {
        if (!modal) return;
        closeSortPanel();
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        if (openBtn) {
            openBtn.setAttribute('aria-expanded', 'true');
            openBtn.setAttribute('aria-label', '詳細検索を閉じる');
        }
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        if (openBtn) {
            openBtn.setAttribute('aria-expanded', 'false');
            openBtn.setAttribute('aria-label', '詳細検索');
        }
        document.body.style.overflow = '';
    }

    if (modal && openBtn) {
        openBtn.addEventListener('click', function () {
            if (modal.classList.contains('is-open')) {
                closeModal();
            } else {
                openModal();
            }
        });
        modal.querySelectorAll('[data-close-modal]').forEach(function (el) {
            el.addEventListener('click', closeModal);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            if (modal.classList.contains('is-open')) {
                closeModal();
                return;
            }
            if (sortPanel && !sortPanel.hidden) closeSortPanel();
        });
    }

    locationOptions.forEach(function (label) {
        var radio = label.querySelector('input[type="radio"]');
        if (!radio) return;
        function syncSelected() {
            locationOptions.forEach(function (l) { l.classList.remove('is-selected'); });
            if (radio.checked) label.classList.add('is-selected');
        }
        radio.addEventListener('change', syncSelected);
        syncSelected();
    });

    if (distanceSlider && distanceValueEl) {
        function updateDistanceOutput() {
            var v = distanceSlider.value;
            distanceValueEl.textContent = v === '40' ? '40km以上' : v + 'km';
            var min = Number(distanceSlider.min || 0);
            var max = Number(distanceSlider.max || 100);
            var progress = max > min ? ((Number(v) - min) / (max - min)) * 100 : 0;
            distanceSlider.style.setProperty('--detail-search-slider-progress', progress + '%');
        }
        distanceSlider.addEventListener('input', updateDistanceOutput);
        updateDistanceOutput();
    }

    // ---------------------------------------------------------------------
    // 手順: accordion / chip filter の共通ヘルパ
    // ---------------------------------------------------------------------
    function injectAccordionCountChip(block) {
        var head = block.querySelector('[data-accordion-trigger]');
        if (!head) return null;
        // 既存の badge を優先。無ければ挿入
        var badge = head.querySelector('[data-accordion-count]');
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'detail-search-accordion__count';
            badge.setAttribute('data-accordion-count', '');
            badge.hidden = true;
            // アイコン (icon) の直前に入れる
            var icon = head.querySelector('.detail-search-accordion__icon');
            if (icon) {
                head.insertBefore(badge, icon);
            } else {
                head.appendChild(badge);
            }
        }
        return badge;
    }

    function updateAccordionCount(block) {
        var head = block.querySelector('[data-accordion-trigger]');
        var badge = head ? head.querySelector('[data-accordion-count]') : null;
        if (!badge) return;
        var checked = block.querySelectorAll('input[type="checkbox"]:checked, input[type="radio"]:checked[value]:not([value=""])');
        // radio の "指定なし" 相当 (value 空) は数えない
        var count = 0;
        checked.forEach(function (el) {
            if (el.type === 'radio' && el.value === '') return;
            count++;
        });
        // 1つ選択のプルダウン（出勤頻度など）も値ありなら件数に含める
        block.querySelectorAll('select').forEach(function (sel) {
            if (sel.name && sel.value !== '') count++;
        });
        if (count > 0) {
            badge.hidden = false;
            badge.textContent = count + '件選択';
            block.classList.add('has-selection');
        } else {
            badge.hidden = true;
            badge.textContent = '';
            block.classList.remove('has-selection');
        }
    }

    // 閉じたアコーディオンに「選択中の内容」を1行プレビュー表示する
    function updateAccordionPreview(block) {
        var body = block.querySelector('.detail-search-accordion__body');
        var head = block.querySelector('[data-accordion-trigger]');
        if (!body || !head) return;
        var preview = block.querySelector('[data-accordion-preview]');
        if (!preview) {
            preview = document.createElement('div');
            preview.className = 'detail-search-accordion__preview';
            preview.setAttribute('data-accordion-preview', '');
            preview.hidden = true;
            head.insertAdjacentElement('afterend', preview);
        }
        var labels = [];
        block.querySelectorAll('input[type="checkbox"]:checked').forEach(function (input) {
            var label = input.closest('label');
            var span = label ? label.querySelector('span:last-child') : null;
            var t = span ? span.textContent.trim() : '';
            if (t) labels.push(t);
        });
        block.querySelectorAll('select').forEach(function (sel) {
            if (!sel.value) return;
            var opt = sel.options[sel.selectedIndex];
            if (opt && opt.textContent) labels.push(opt.textContent.trim());
        });
        var isOpen = !body.hidden;
        if (isOpen || labels.length === 0) {
            preview.hidden = true;
            preview.textContent = '';
        } else {
            var shown = labels.slice(0, 3).join('・');
            if (labels.length > 3) shown += ' 他' + (labels.length - 3) + '件';
            preview.hidden = false;
            preview.textContent = shown;
        }
    }

    // =====================================================================
    // レンジスライダー（給与/採用報酬）：data-range-values の配列を段階として
    // hidden input（#detail-search-hourly-wage / #detail-search-reward）へ書き込む。
    // 旧「hidden <select>」方式は「マスタが空 → option が生成されずスライダーが動かない」
    // 問題があったため撤廃し、Blade 側でフォールバック候補を必ず持たせる方式に変更。
    // =====================================================================
    if (modal) {
        modal.querySelectorAll('.detail-search-range[data-range-target]').forEach(function (wrap) {
            var range = wrap.querySelector('input[type="range"]');
            var valueEl = wrap.querySelector('[data-range-value]');
            var hidden = document.getElementById(wrap.getAttribute('data-range-target'));
            if (!range || !hidden) return;
            var values;
            try { values = JSON.parse(wrap.getAttribute('data-range-values') || '[]'); }
            catch (e) { values = []; }
            // 「指定なし」を先頭に必ず入れる
            values = [0].concat((values || []).filter(function (v) { return v > 0; }));
            var labeler = wrap.getAttribute('data-range-labeler') || 'wage';
            range.max = String(Math.max(1, values.length - 1));

            function fmt(n) { return '¥' + n.toLocaleString('en-US') + '以上'; }
            function labelFor(n) {
                if (!n) return '指定なし';
                return (labeler === 'wage' ? '時給 ' : '') + fmt(n);
            }

            function paint() {
                var idx = parseInt(range.value, 10) || 0;
                var v = values[idx] || 0;
                hidden.value = v > 0 ? String(v) : '';
                if (valueEl) valueEl.textContent = labelFor(v);
                var max = parseInt(range.max, 10) || 1;
                range.style.setProperty('--range-progress', ((idx / Math.max(1, max)) * 100) + '%');
                range.classList.toggle('is-unset', idx === 0);
            }
            // 初期値：hidden の value（サーバ/クエリ復元）→ もっとも近い段階へスナップ
            (function initFromValue() {
                var initial = parseInt(wrap.getAttribute('data-range-initial') || hidden.value || '0', 10) || 0;
                var idx = 0;
                if (initial > 0) {
                    var best = Infinity;
                    values.forEach(function (v, i) {
                        var d = Math.abs(v - initial);
                        if (d < best) { best = d; idx = i; }
                    });
                }
                range.value = String(idx);
                paint();
            })();

            range.addEventListener('input', paint);
            // クリア（外部からの hidden.value = '' 変化）でも追従
            hidden.addEventListener('change', function () {
                if (!hidden.value) { range.value = '0'; paint(); }
            });
        });

        // =================================================================
        // エリア：テキストで絞り込めるミニ検索（並びは維持・非一致のみ非表示）
        // =================================================================
        modal.querySelectorAll('.detail-search-section--area').forEach(function (section) {
            var chipsWrap = section.querySelector('.detail-search-chips');
            if (!chipsWrap || section.querySelector('[data-area-filter]')) return;
            var box = document.createElement('div');
            box.className = 'detail-search-area-filter';
            box.innerHTML = '<i class="fas fa-magnifying-glass" aria-hidden="true"></i>'
                + '<input type="text" data-area-filter placeholder="エリア名で検索（例: 新宿）" aria-label="エリアを検索">';
            chipsWrap.parentNode.insertBefore(box, chipsWrap);
            var input = box.querySelector('input');
            input.addEventListener('input', function () {
                var q = (input.value || '').trim();
                chipsWrap.querySelectorAll('.detail-search-chip').forEach(function (chip) {
                    var label = chip.textContent || '';
                    var checked = !!chip.querySelector('input:checked');
                    // 選択済みは常に表示。未選択は部分一致のみ表示（並びは不変）
                    chip.style.display = (checked || q === '' || label.indexOf(q) !== -1) ? '' : 'none';
                });
            });
        });
    }

    if (modal) {
        modal.querySelectorAll('[data-accordion]').forEach(function (block) {
            var head = block.querySelector('[data-accordion-trigger]');
            var body = block.querySelector('.detail-search-accordion__body');
            var icon = block.querySelector('.detail-search-accordion__icon');
            if (!head || !body) return;

            // 選択件数チップをヘッド左に注入
            injectAccordionCountChip(block);
            updateAccordionCount(block);

            function syncAccordion(isOpen) {
                body.hidden = !isOpen;
                block.setAttribute('data-open', isOpen ? 'true' : 'false');
                head.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                if (icon) icon.textContent = isOpen ? '−' : '+';
                updateAccordionPreview(block);
            }

            // タグ大量セクションはデフォルトで閉じる（data-open の値をリスペクトしつつ、選択がある場合は開く）
            var initialOpen = block.getAttribute('data-open') === 'true';
            var hasSelection = block.querySelectorAll('input[type="checkbox"]:checked').length > 0;
            if (hasSelection) initialOpen = true;
            syncAccordion(initialOpen);

            head.addEventListener('click', function () {
                syncAccordion(body.hidden);
            });

            // change 時にヘッドのカウント・プレビューを更新
            block.addEventListener('change', function () {
                updateAccordionCount(block);
                updateAccordionPreview(block);
            });
        });

        // チップ数が多いコンテナに検索フィルタ UI を挿入
        // タグは常に全件表示（折りたたみ・並び替え・ピン留めは行わない 2026-07-20）
    }

    function countConditions() {
        if (!form) return 0;
        var n = 0;
        form.querySelectorAll('input[type="checkbox"]:checked').forEach(function () { n++; });
        form.querySelectorAll('select').forEach(function (select) {
            if (select.value) n++;
        });
        form.querySelectorAll('input[name="hourly_wage"], input[name="reward"]').forEach(function (input) {
            if (Number(input.value) > 0) n++;
        });
        return n;
    }

    function getCheckedLabels(container) {
        var values = [];
        container.querySelectorAll('input[type="checkbox"]:checked').forEach(function (input) {
            var label = input.closest('label');
            var textEl = label ? label.querySelector('span:last-child') : null;
            var text = textEl ? textEl.textContent.trim() : input.value;
            if (text) values.push(text);
        });

        container.querySelectorAll('select').forEach(function (select) {
            if (!select.value) return;
            var option = select.options[select.selectedIndex];
            if (option && option.textContent) {
                values.push(option.textContent.trim());
            }
        });

        return values;
    }

    function getSummaryLines() {
        if (!form) return [];
        var lines = [];
        form.querySelectorAll('[data-summary-group]').forEach(function (group) {
            var label = group.getAttribute('data-summary-group');
            var values = getCheckedLabels(group);
            if (label && values.length) {
                lines.push(label + '：' + values.join('・'));
            }
        });
        var wage = form.querySelector('input[name="hourly_wage"]');
        var reward = form.querySelector('input[name="reward"]');
        if (wage && Number(wage.value) > 0) lines.push('時給：' + Number(wage.value).toLocaleString('ja-JP') + '円以上');
        if (reward && Number(reward.value) > 0) lines.push('ボーナス：' + Number(reward.value).toLocaleString('ja-JP') + '円以上');
        return lines;
    }

    function updateSelectionBadges() {
        if (!form) return;
        form.querySelectorAll('[data-selection-count]').forEach(function (badge) {
            var scope = badge.closest('[data-summary-group], .detail-search-section, .detail-search-accordion') || form;
            var count = scope.querySelectorAll('input[type="checkbox"]:checked').length;
            if (count > 0) {
                badge.hidden = false;
                badge.textContent = count + '件選択中';
            } else {
                badge.hidden = true;
                badge.textContent = badge.getAttribute('data-empty-label') || '';
            }
        });
    }

    function updateBadgeAndSummary() {
        var count = countConditions();
        if (badgeEl) {
            badgeEl.textContent = count;
            badgeEl.style.display = count > 0 ? 'inline-flex' : 'none';
        }
        // フッターの「検索」ボタンに選択中の条件数をライブ表示
        var submitBtnEl = modal ? modal.querySelector('[data-detail-search-submit]') : null;
        if (submitBtnEl) {
            var submitChip = submitBtnEl.querySelector('[data-submit-count]');
            if (!submitChip) {
                submitChip = document.createElement('span');
                submitChip.className = 'detail-search-submit-count';
                submitChip.setAttribute('data-submit-count', '');
                submitBtnEl.appendChild(submitChip);
            }
            submitChip.textContent = String(count);
            submitChip.hidden = count === 0;
        }
        if (summaryTextEl && summaryEl) {
            var lines = getSummaryLines();
            if (lines.length) {
                summaryTextEl.textContent = lines.slice(0, 3).join(' / ') + (lines.length > 3 ? ' …' : '');
                summaryEl.style.display = 'block';
            } else {
                summaryEl.style.display = 'none';
            }
        }

        updateSelectionBadges();
    }

    if (form) {
        form.addEventListener('change', updateBadgeAndSummary);
        form.addEventListener('input', updateBadgeAndSummary);
        updateBadgeAndSummary();
    }

    if (modal) {
        var resetBtn = modal.querySelector('[data-detail-search-reset]');
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                if (!form) return;
                form.querySelectorAll('input[type="checkbox"]').forEach(function (c) { c.checked = false; });
                form.querySelectorAll('input[type="text"], input[type="number"]').forEach(function (i) { i.value = ''; });
                form.querySelectorAll('input[type="radio"]').forEach(function (r) {
                    r.checked = r.value === 'none';
                    r.dispatchEvent(new Event('change', { bubbles: true }));
                });
                form.querySelectorAll('select').forEach(function (select) {
                    select.value = '';
                });
                form.querySelectorAll('input[name="hourly_wage"], input[name="reward"]').forEach(function (input) {
                    input.value = '';
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });
                var distanceInput = form.querySelector('input[name="distance_km"]');
                if (distanceInput) {
                    distanceInput.value = 20;
                    if (distanceValueEl) distanceValueEl.textContent = '20km';
                    distanceInput.dispatchEvent(new Event('input'));
                }
                var locationRange = document.getElementById('detail-search-location-slider');
                if (locationRange) {
                    locationRange.value = '1';
                    locationRange.dispatchEvent(new Event('input', { bubbles: true }));
                }
                locationOptions.forEach(function (l) { l.classList.remove('is-selected'); });
                var firstLocation = modal.querySelector('.detail-search-location-option input[value="none"]');
                if (firstLocation) firstLocation.closest('.detail-search-location-option').classList.add('is-selected');
                if (keywordInput) keywordInput.value = '';
                updateBadgeAndSummary();
                // アコーディオンの件数チップ・プレビュー・タグ絞り込みUIも即時リフレッシュ
                modal.querySelectorAll('[data-accordion]').forEach(function (block) {
                    updateAccordionCount(block);
                    updateAccordionPreview(block);
                });
                modal.querySelectorAll('.detail-search-chips').forEach(function (c) {
                    c.dispatchEvent(new Event('change'));
                });
            });
        }
    }

    function buildSearchParams(extraParams) {
        var params = ['filters_applied=1'];
        if (keywordInput && keywordInput.value.trim()) {
            params.push('keyword=' + encodeURIComponent(keywordInput.value.trim()));
        }
        if (sortCurrent && sortCurrent.value) {
            params.push('sort=' + encodeURIComponent(sortCurrent.value));
        }
        if (form) {
            var modeField = form.querySelector('[name="location_mode"]:checked') || form.querySelector('[name="location_mode"][type="hidden"]');
            var selectedMode = modeField ? modeField.value : '';
            new FormData(form).forEach(function (value, name) {
                if (['_token', '_method', 'filters_applied'].indexOf(name) !== -1 || typeof value !== 'string' || value === '') return;
                if (name.indexOf('current_') === 0 && selectedMode !== 'current') return;
                if (name.indexOf('passport_') === 0 && selectedMode !== 'passport') return;
                if (name === 'distance_km' && selectedMode === 'none') return;
                params.push(encodeURIComponent(name) + '=' + encodeURIComponent(value));
            });
        }
        if (extraParams && typeof extraParams === 'object') {
            Object.keys(extraParams).forEach(function (key) {
                if (extraParams[key] !== null && extraParams[key] !== undefined && extraParams[key] !== '') {
                    params.push(key + '=' + encodeURIComponent(extraParams[key]));
                }
            });
        }
        return params;
    }

    function getSearchUrl() {
        var pathname = window.location.pathname;
        return pathname.indexOf('/cast/') === 0 ? '/cast/search/list' : '/shop/search';
    }

    // 一覧側には実際に適用した条件だけを表示する（モーダル編集中は変更しない）。
    // 旧: プレーンテキスト（'A / B / C' の羅列）→ 新: 各条件を独立したチップとして描画
    //     チップは絞込モーダルを開くための CTA を兼ねる（クリックで #open-detail-search を起動）。
    var appliedChipsRoot = document.querySelector('[data-applied-search-chips]');
    if (appliedChipsRoot && form) {
        var chipItems = [];
        if (keywordInput && keywordInput.value.trim()) {
            chipItems.push({ icon: 'fa-magnifying-glass', label: keywordInput.value.trim() });
        }
        getSummaryLines().forEach(function (line) {
            // getSummaryLines() 返す文字列は「エリア：港区・新宿区」「業種：ラウンジ」等の
            // "ラベル：値" 形式なので、そのままチップ 1 個に対応させる（icon はグループで振り分け）。
            var iconByPrefix = {
                'エリア':   'fa-location-dot',
                '業種':     'fa-tags',
                '時給':     'fa-yen-sign',
                'ボーナス': 'fa-yen-sign',
                '年齢':     'fa-user',
                '出勤':     'fa-clock',
                'キープ':   'fa-bookmark',
                '歓迎':     'fa-heart',
            };
            var icon = 'fa-filter';
            Object.keys(iconByPrefix).forEach(function (k) {
                if (line.indexOf(k) === 0) icon = iconByPrefix[k];
            });
            chipItems.push({ icon: icon, label: line });
        });
        var locationMode = form.querySelector('[name="location_mode"]:checked') || form.querySelector('[name="location_mode"][type="hidden"]');
        if (locationMode && locationMode.value !== 'none') {
            var mode = locationMode.value;
            var lat = form.querySelector('[name="' + (mode === 'passport' ? 'passport_lat' : 'current_lat') + '"]');
            var km = form.querySelector('[name="distance_km"]');
            var locLabel;
            if (mode === 'profile' || (lat && lat.value !== '')) {
                locLabel = (mode === 'profile' ? '店舗住所' : mode === 'passport' ? '指定地' : '現在地') + 'から' + (km ? km.value : '') + 'km以内';
            } else {
                locLabel = '位置情報未取得';
            }
            chipItems.push({ icon: 'fa-location-crosshairs', label: locLabel });
        }

        while (appliedChipsRoot.firstChild) appliedChipsRoot.removeChild(appliedChipsRoot.firstChild);
        if (chipItems.length) {
            chipItems.forEach(function (item) {
                var chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'search-summary__chip';
                chip.setAttribute('aria-label', item.label + '（詳細フィルターを開く）');
                chip.addEventListener('click', function () {
                    var t = document.getElementById('open-detail-search');
                    if (t) t.click();
                });
                var i = document.createElement('i');
                i.className = 'fas ' + item.icon;
                i.setAttribute('aria-hidden', 'true');
                var span = document.createElement('span');
                span.textContent = item.label;
                chip.appendChild(i);
                chip.appendChild(span);
                appliedChipsRoot.appendChild(chip);
            });
            appliedChipsRoot.hidden = false;
        } else {
            appliedChipsRoot.hidden = true;
        }
    }

    function doSearch(params) {
        var listUrl = getSearchUrl();
        var query = params.length ? '?' + params.join('&') : '';
        window.location.href = listUrl + query;
    }

    if (simpleSubmitBtn && keywordInput) {
        simpleSubmitBtn.addEventListener('click', function () {
            doSearch(buildSearchParams());
        });
    }

    if (keywordInput) {
        keywordInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.isComposing && e.keyCode !== 229) {
                e.preventDefault();
                doSearch(buildSearchParams());
            }
        });
    }

    if (modal) {
        var submitBtn = modal.querySelector('[data-detail-search-submit]');
        if (submitBtn) {
            submitBtn.addEventListener('click', function () {
                closeModal();
                doSearch(buildSearchParams());
            });
        }
    }

    // クイックフィルタチップは廃止（絞り込みは詳細検索＝保存機能つきに一本化）
})();
