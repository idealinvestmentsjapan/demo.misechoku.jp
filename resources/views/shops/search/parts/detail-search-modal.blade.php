{{-- 店舗用：キャスト詳細検索モーダル --}}
@php
    $savedPrefs = request()->boolean('filters_applied') ? [] : ($savedPreferences ?? []);
    $tagsByCat = $castTagsByCategory ?? ['looks' => [], 'personality' => []];

    $maxDistanceKm = (int) (request('distance_km', $savedPrefs['max_distance_km'] ?? 20));
    $ageMin = (string) (request('age_min', $savedPrefs['age_min'] ?? ''));
    $ageMax = (string) (request('age_max', $savedPrefs['age_max'] ?? ''));

    // 位置情報設定（MyPage 保存値を初期表示に使う。クエリパラメータが優先）
    $searchLocationSettings = $searchLocationSettings ?? [];
    $loc = is_array($searchLocationSettings) ? $searchLocationSettings : [];
    $reqLocationMode = (string) request('location_mode', '');
    $allowedLocationModes = ['', 'none', 'profile', 'passport', 'current'];
    if (!in_array($reqLocationMode, $allowedLocationModes, true)) {
        $reqLocationMode = '';
    }
    $detailLocationMode = $reqLocationMode !== '' ? $reqLocationMode : (string) ($loc['mode'] ?? '');
    if (!in_array($detailLocationMode, $allowedLocationModes, true)) {
        $detailLocationMode = '';
    }
    $detailPassportAddress = (string) (request('passport_address', $loc['passport_address'] ?? ''));
    $detailPassportLat = request('passport_lat', $loc['passport_latitude'] ?? '');
    $detailPassportLng = request('passport_lng', $loc['passport_longitude'] ?? '');
    $detailPassportLabel = (string) ($loc['passport_label'] ?? '');
    $detailCurrentLat = (string) request('current_lat', '');
    $detailCurrentLng = (string) request('current_lng', '');
    $hasProfileAddress = !empty($loc['has_address']);
    $hasProfileLocation = !empty($loc['profile_location']);
    $profileAddressText = (string) ($loc['profile_address'] ?? '');

    // 4段階スライダー
    $detailLocSliderMarks = [
        ['value' => 5,   'label' => '5km以内'],
        ['value' => 20,  'label' => '20km'],
        ['value' => 30,  'label' => '30km'],
        ['value' => 100, 'label' => '40km以上'],
    ];
    $detailSliderIndex = 1;
    $minDelta = PHP_INT_MAX;
    foreach ($detailLocSliderMarks as $i => $m) {
        $d = abs($m['value'] - $maxDistanceKm);
        if ($d < $minDelta) { $minDelta = $d; $detailSliderIndex = $i; }
    }
    if ($maxDistanceKm >= 40) {
        $detailSliderIndex = 3;
    }

    $reqShiftFrequency = (string) request('shift_frequency', '');
    $shiftFrequency = $reqShiftFrequency !== '' ? $reqShiftFrequency : (string) ($savedPrefs['shift_frequency'] ?? '');

    $reqWorkPeriods = array_values(array_filter((array) request('work_periods', []), 'is_string'));
    $workPeriods = $reqWorkPeriods !== [] ? $reqWorkPeriods : array_values(array_filter((array) ($savedPrefs['work_periods'] ?? []), 'is_string'));

    $reqLooks = array_map('intval', (array) request('looks_tag_ids', []));
    $selectedLooks = $reqLooks !== [] ? $reqLooks : array_map('intval', $savedPrefs['looks_tag_ids'] ?? []);

    $reqPers = array_map('intval', (array) request('personality_tag_ids', []));
    $selectedPersonality = $reqPers !== [] ? $reqPers : array_map('intval', $savedPrefs['personality_tag_ids'] ?? []);

    $reqExp = (string) request('night_work_exp', '');
    $nightWorkExp = $reqExp !== '' ? $reqExp : (string) ($savedPrefs['night_work_exp'] ?? '');

    $workPeriodLabels = ['morning' => '朝', 'day' => '昼', 'night' => '夜'];
    $nightWorkExpLabels = ['any' => '指定なし', 'yes' => '経験あり', 'none' => '未経験'];
@endphp

<div id="detail-search-modal"
     class="detail-search-modal"
     role="dialog"
     aria-modal="true"
     aria-labelledby="detail-search-modal-title"
     aria-hidden="true">
    <div class="detail-search-modal__overlay" data-close-modal aria-hidden="true"></div>
    <div class="detail-search-modal__window detail-search-modal__window--search">
        <div class="detail-search-modal__header detail-search-modal__header--search">
            <div class="detail-search-modal__header-line" aria-hidden="true"></div>
            <h2 id="detail-search-modal-title" class="detail-search-modal__title">詳細検索</h2>
            <button type="button" class="detail-search-modal__close" data-close-modal aria-label="詳細検索を閉じる">&times;</button>
        </div>

        <div class="detail-search-modal__body detail-search-modal__body--search">
            <form id="detail-search-form" class="detail-search-form detail-search-form--search">
                {{-- 位置情報 --}}
                <div class="detail-search-section detail-search-section--panel">
                    <div class="detail-search-section__head">
                        <span class="detail-search-section__title"><i class="fas fa-location-crosshairs" aria-hidden="true"></i>位置情報から探す</span>
                    </div>

                    {{-- 店舗は常に「店舗の登録住所」を基準に距離検索する（拠点選択なし・仕様） --}}
                    <fieldset class="detail-search-location-modes">
                        <legend class="sr-only">距離の絞り込み</legend>
                        <label><input type="radio" name="location_mode" value="profile" @checked($detailLocationMode !== 'none')> 店舗住所からの距離で絞り込む</label>
                        <label><input type="radio" name="location_mode" value="none" @checked($detailLocationMode === 'none')> 距離で絞り込まない</label>
                    </fieldset>
                    <div class="detail-search-location-fixed">
                        <i class="fas fa-store detail-search-location-fixed__icon" aria-hidden="true"></i>
                        <span class="detail-search-location-fixed__body">
                            <span class="detail-search-location-fixed__title">店舗の住所を基準に検索します</span>
                            <span class="detail-search-location-fixed__sub {{ $hasProfileAddress ? '' : 'is-warn' }}">
                                @if($hasProfileAddress)
                                    {{ $profileAddressText !== '' ? $profileAddressText : '登録済みの店舗住所からの距離で表示されます' }}
                                @else
                                    店舗の住所が未登録です。PROFILE から住所を登録してください
                                @endif
                            </span>
                        </span>
                    </div>

                    <div class="detail-search-location-radius">
                        <div class="detail-search-location-slider">
                            <div class="detail-search-location-slider__marks" id="detail-search-location-slider-marks">
                                @foreach($detailLocSliderMarks as $i => $m)
                                    <span class="detail-search-location-slider__mark {{ $i === $detailSliderIndex ? 'is-active' : '' }}" data-slider-mark="{{ $i }}">{{ $m['label'] }}</span>
                                @endforeach
                            </div>
                            <input type="range"
                                   id="detail-search-location-slider"
                                   class="detail-search-location-range"
                                   min="0" max="{{ count($detailLocSliderMarks) - 1 }}" step="1"
                                   value="{{ $detailSliderIndex }}"
                                   aria-label="距離">
                            <div class="detail-search-location-slider__value" id="detail-search-location-slider-value">{{ $detailLocSliderMarks[$detailSliderIndex]['label'] }}</div>
                        </div>
                        <input type="hidden" name="distance_km" id="detail-search-location-distance-km" value="{{ $detailLocSliderMarks[$detailSliderIndex]['value'] }}">
                    </div>
                </div>

                {{-- 年齢範囲 --}}
                <div class="detail-search-accordion detail-search-accordion--panel" data-accordion data-summary-group="年齢" data-open="true">
                    <button type="button" class="detail-search-accordion__head" data-accordion-trigger aria-expanded="true">
                        <span><i class="fas fa-cake-candles" aria-hidden="true"></i>年齢</span>
                        <span class="detail-search-accordion__icon">−</span>
                    </button>
                    <div class="detail-search-accordion__body">
                        <div class="detail-search-age-range">
                            <label class="detail-search-age-range__field">
                                <span>下限</span>
                                <input type="number" name="age_min" min="18" max="99" value="{{ $ageMin }}" placeholder="18">
                                <span class="detail-search-age-range__unit">歳</span>
                            </label>
                            <span class="detail-search-age-range__separator">〜</span>
                            <label class="detail-search-age-range__field">
                                <span>上限</span>
                                <input type="number" name="age_max" min="18" max="99" value="{{ $ageMax }}" placeholder="30">
                                <span class="detail-search-age-range__unit">歳</span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- 出勤頻度・時間帯（短いのでデフォルト開く） --}}
                <div class="detail-search-accordion detail-search-accordion--panel" data-accordion data-summary-group="出勤頻度・時間帯" data-open="true">
                    <button type="button" class="detail-search-accordion__head" data-accordion-trigger aria-expanded="true">
                        <span><i class="fas fa-clock" aria-hidden="true"></i>希望の出勤頻度・時間帯</span>
                        <span class="detail-search-accordion__icon">−</span>
                    </button>
                    <div class="detail-search-accordion__body">
                        <div class="detail-search-subsection">
                            <span class="detail-search-subsection__label">出勤頻度（1つ選択）</span>
                            <select name="shift_frequency" class="detail-search-select">
                                <option value="" {{ $shiftFrequency === '' ? 'selected' : '' }}>指定なし</option>
                                @foreach(['週1回出勤', '週2回出勤', '週3回以上'] as $freq)
                                    <option value="{{ $freq }}" {{ $shiftFrequency === $freq ? 'selected' : '' }}>{{ $freq }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="detail-search-subsection">
                            <span class="detail-search-subsection__label">時間帯（複数選択可）</span>
                            <div class="detail-search-chips detail-search-chips--search">
                                @foreach($workPeriodLabels as $key => $label)
                                    <label class="detail-search-chip detail-search-chip--search">
                                        <input type="checkbox" name="work_periods[]" value="{{ $key }}" {{ in_array($key, $workPeriods, true) ? 'checked' : '' }}>
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                {{-- すぐに入れる日（〇月〇日にすぐ入れると宣言しているキャストを探す） --}}
                @php
                    $availableOnValue = (string) request('available_on', '');
                    $availableOnMin = \Carbon\Carbon::today()->toDateString();
                    $availableOnMax = \Carbon\Carbon::today()->addDays(\App\Services\AvailabilityService::MAX_DAYS_AHEAD)->toDateString();
                @endphp
                <div class="detail-search-accordion detail-search-accordion--panel" data-accordion data-summary-group="すぐに入れる日" data-open="true">
                    <button type="button" class="detail-search-accordion__head" data-accordion-trigger aria-expanded="true">
                        <span><i class="fas fa-calendar-days" aria-hidden="true"></i>すぐに入れる日</span>
                        <span class="detail-search-accordion__icon">−</span>
                    </button>
                    <div class="detail-search-accordion__body">
                        <div class="detail-search-subsection">
                            <span class="detail-search-subsection__label">この日にすぐ入れると宣言している子を探す</span>
                            <input type="date" name="available_on" class="detail-search-select"
                                   value="{{ $availableOnValue }}"
                                   min="{{ $availableOnMin }}" max="{{ $availableOnMax }}">
                        </div>
                    </div>
                </div>

                {{-- 経験（短いのでデフォルト開く） --}}
                <div class="detail-search-accordion detail-search-accordion--panel" data-accordion data-summary-group="ナイトワーク経験" data-open="true">
                    <button type="button" class="detail-search-accordion__head" data-accordion-trigger aria-expanded="true">
                        <span><i class="fas fa-star" aria-hidden="true"></i>ナイトワーク経験</span>
                        <span class="detail-search-accordion__icon">−</span>
                    </button>
                    <div class="detail-search-accordion__body">
                        <div class="detail-search-chips detail-search-chips--search">
                            @foreach($nightWorkExpLabels as $key => $label)
                                @php $checked = ($nightWorkExp === '' && $key === 'any') || $nightWorkExp === $key; @endphp
                                <label class="detail-search-chip detail-search-chip--search">
                                    <input type="radio" name="night_work_exp" value="{{ $key }}" {{ $checked ? 'checked' : '' }}>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- ルックス --}}
                <div class="detail-search-accordion detail-search-accordion--panel" data-accordion data-summary-group="ルックス">
                    <button type="button" class="detail-search-accordion__head" data-accordion-trigger aria-expanded="false">
                        <span><i class="fas fa-face-smile" aria-hidden="true"></i>ルックス</span>
                        <span class="detail-search-accordion__icon">+</span>
                    </button>
                    <div class="detail-search-accordion__body" hidden>
                        <div class="detail-search-chips detail-search-chips--search">
                            @foreach($tagsByCat['looks'] ?? [] as $tag)
                                <label class="detail-search-chip detail-search-chip--search">
                                    <input type="checkbox" name="looks_tag_ids[]" value="{{ $tag->id }}" {{ in_array((int) $tag->id, $selectedLooks, true) ? 'checked' : '' }}>
                                    <span>{{ $tag->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- 性格・内面 --}}
                <div class="detail-search-accordion detail-search-accordion--panel" data-accordion data-summary-group="性格・内面">
                    <button type="button" class="detail-search-accordion__head" data-accordion-trigger aria-expanded="false">
                        <span><i class="fas fa-heart" aria-hidden="true"></i>性格・内面</span>
                        <span class="detail-search-accordion__icon">+</span>
                    </button>
                    <div class="detail-search-accordion__body" hidden>
                        <div class="detail-search-chips detail-search-chips--search">
                            @foreach($tagsByCat['personality'] ?? [] as $tag)
                                <label class="detail-search-chip detail-search-chip--search">
                                    <input type="checkbox" name="personality_tag_ids[]" value="{{ $tag->id }}" {{ in_array((int) $tag->id, $selectedPersonality, true) ? 'checked' : '' }}>
                                    <span>{{ $tag->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="detail-search-modal__footer detail-search-modal__footer--search">
            <button type="button" class="detail-search-modal__btn detail-search-modal__btn--reset" data-detail-search-reset>クリア</button>
            {{-- 保存ボタンは廃止：検索実行時に条件を自動保存する --}}
            <button type="button" class="detail-search-modal__btn detail-search-modal__btn--submit" data-detail-search-submit
                    data-save-url="{{ route('shop.search-preferences.save') }}">検索</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    // ===== 位置情報セクション（shop 詳細検索） =====
    var form = document.getElementById('detail-search-form');
    if (!form) return;

    var sliderMarks = @json($detailLocSliderMarks);
    var range = document.getElementById('detail-search-location-slider');
    var rangeMarks = document.querySelectorAll('#detail-search-location-slider-marks .detail-search-location-slider__mark');
    var rangeValueLabel = document.getElementById('detail-search-location-slider-value');
    var distanceKmInput = document.getElementById('detail-search-location-distance-km');
    var modeCards = form.querySelectorAll('[data-mode-card]');
    var modeInputs = form.querySelectorAll('input[name="location_mode"]');
    var passportInput = document.getElementById('detail-search-location-passport-address');
    var passportLatEl = document.getElementById('detail-search-location-passport-lat');
    var passportLngEl = document.getElementById('detail-search-location-passport-lng');
    var lookupBtn = document.getElementById('detail-search-location-lookup-btn');
    var lookupLabelEl = lookupBtn ? lookupBtn.querySelector('[data-lookup-label]') : null;
    var passportStatus = document.getElementById('detail-search-location-passport-status');
    var lookupUrl = @json(route('api.geocoding.lookup'));
    var suggestUrl = @json(route('api.geocoding.suggest'));
    var currentBtn = document.getElementById('detail-search-location-current-btn');
    var currentBtnLabel = currentBtn ? currentBtn.querySelector('[data-current-btn-label]') : null;
    var currentLatEl = document.getElementById('detail-search-location-current-lat');
    var currentLngEl = document.getElementById('detail-search-location-current-lng');
    var currentLabelEl = document.getElementById('detail-search-location-current-label');
    var currentCheck = form.querySelector('[data-current-check]');

    function syncMode() {
        var checked = form.querySelector('input[name="location_mode"]:checked');
        var current = checked ? checked.value : '';
        modeCards.forEach(function (card) {
            card.classList.toggle('is-selected', card.getAttribute('data-mode-card') === current);
        });
    }
    modeInputs.forEach(function (i) { i.addEventListener('change', syncMode); });
    syncMode();

    function syncSlider() {
        var idx = parseInt(range && range.value, 10);
        if (isNaN(idx) || idx < 0 || idx >= sliderMarks.length) idx = 0;
        var mark = sliderMarks[idx];
        if (rangeValueLabel) rangeValueLabel.textContent = mark.label;
        if (distanceKmInput) distanceKmInput.value = String(mark.value);
        rangeMarks.forEach(function (el) {
            el.classList.toggle('is-active', parseInt(el.getAttribute('data-slider-mark'), 10) === idx);
        });
    }
    if (range) {
        range.addEventListener('input', syncSlider);
        range.addEventListener('change', syncSlider);
    }
    rangeMarks.forEach(function (el) {
        el.addEventListener('click', function () {
            var i = parseInt(el.getAttribute('data-slider-mark'), 10);
            if (!isNaN(i) && range) { range.value = String(i); syncSlider(); }
        });
    });
    syncSlider();

    function escapeHtml(s) {
        return String(s).replace(/[<>&"]/g, function (c) {
            return ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;' })[c];
        });
    }
    function setPassportStatus(state, html) {
        if (!passportStatus) return;
        passportStatus.setAttribute('data-state', state);
        passportStatus.innerHTML = html;
    }
    function clearPassportCoords() {
        if (passportLatEl) passportLatEl.value = '';
        if (passportLngEl) passportLngEl.value = '';
    }
    function setLookupLoading(isLoading) {
        if (!lookupBtn) return;
        lookupBtn.disabled = !!isLoading;
        lookupBtn.classList.toggle('is-loading', !!isLoading);
        if (lookupLabelEl) lookupLabelEl.textContent = isLoading ? '検索中...' : '検索';
    }
    function performLookup() {
        if (!passportInput) return;
        var q = (passportInput.value || '').trim();
        if (q === '') {
            setPassportStatus('error', '<i class="fas fa-circle-exclamation"></i><span>住所または駅名を入力してください</span>');
            return;
        }
        setLookupLoading(true);
        setPassportStatus('loading', '<i class="fas fa-spinner"></i><span>位置情報を検索中...</span>');
        var url = lookupUrl + (lookupUrl.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(q);
        fetch(url, { method: 'GET', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
            .then(function (res) {
                setLookupLoading(false);
                if (res.ok && res.body && res.body.success) {
                    if (passportLatEl) passportLatEl.value = String(res.body.latitude);
                    if (passportLngEl) passportLngEl.value = String(res.body.longitude);
                    var label = res.body.label || q;
                    var lat = parseFloat(res.body.latitude).toFixed(4);
                    var lng = parseFloat(res.body.longitude).toFixed(4);
                    setPassportStatus('resolved',
                        '<i class="fas fa-circle-check"></i><span>解決済み: <strong>' +
                        escapeHtml(label) + '</strong>（' + lat + ', ' + lng + '）</span>'
                    );
                } else {
                    clearPassportCoords();
                    var msg = (res.body && res.body.message) ? res.body.message : '位置情報を取得できませんでした。';
                    setPassportStatus('error', '<i class="fas fa-circle-xmark"></i><span>' + escapeHtml(msg) + '</span>');
                }
            })
            .catch(function () {
                setLookupLoading(false);
                clearPassportCoords();
                setPassportStatus('error', '<i class="fas fa-circle-xmark"></i><span>通信に失敗しました</span>');
            });
    }
    if (lookupBtn) lookupBtn.addEventListener('click', performLookup);
    if (passportInput) {
        passportInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.isComposing && e.keyCode !== 229) { e.preventDefault(); performLookup(); }
        });
        passportInput.addEventListener('input', function () {
            if (passportStatus && passportStatus.getAttribute('data-state') === 'resolved') {
                clearPassportCoords();
                var defaultMsg = passportStatus.getAttribute('data-default-message') || '住所・駅名を入れて『検索』を押してください';
                setPassportStatus('idle', '<i class="fas fa-info-circle"></i><span>' + defaultMsg + '</span>');
            }
        });
    }

    // サジェスト
    var suggestListEl = document.getElementById('detail-search-location-suggest-list');
    var suggestSeq = 0;
    var suggestDebounceTimer = null;
    function closeSuggest() {
        if (!suggestListEl) return;
        suggestListEl.hidden = true;
        suggestListEl.innerHTML = '';
        if (passportInput) passportInput.setAttribute('aria-expanded', 'false');
    }
    function renderSuggest(candidates) {
        if (!suggestListEl) return;
        suggestListEl.innerHTML = '';
        if (!candidates || candidates.length === 0) {
            var empty = document.createElement('li');
            empty.className = 'detail-search-location-suggest-empty';
            empty.textContent = '候補が見つかりません';
            suggestListEl.appendChild(empty);
        } else {
            candidates.forEach(function (c) {
                var li = document.createElement('li');
                li.className = 'detail-search-location-suggest-item';
                li.setAttribute('role', 'option');
                li.setAttribute('data-lat', String(c.latitude));
                li.setAttribute('data-lng', String(c.longitude));
                li.setAttribute('data-label', c.label);
                li.innerHTML = '<i class="fas fa-map-marker-alt"></i><span>' + escapeHtml(c.label) + '</span>';
                suggestListEl.appendChild(li);
            });
        }
        suggestListEl.hidden = false;
        if (passportInput) passportInput.setAttribute('aria-expanded', 'true');
    }
    function fetchSuggest(q) {
        if (!suggestUrl) return;
        var seq = ++suggestSeq;
        var url = suggestUrl + (suggestUrl.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(q);
        fetch(url, { method: 'GET', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (json) {
                if (seq !== suggestSeq) return;
                if (!json) { closeSuggest(); return; }
                renderSuggest(json.candidates || []);
            })
            .catch(function () { closeSuggest(); });
    }
    if (passportInput && suggestListEl) {
        passportInput.addEventListener('input', function () {
            var q = (passportInput.value || '').trim();
            clearTimeout(suggestDebounceTimer);
            if (q.length < 2) { closeSuggest(); return; }
            suggestDebounceTimer = setTimeout(function () { fetchSuggest(q); }, 220);
        });
        passportInput.addEventListener('focus', function () {
            var q = (passportInput.value || '').trim();
            if (q.length >= 2) fetchSuggest(q);
        });
        passportInput.addEventListener('blur', function () { setTimeout(closeSuggest, 150); });
        suggestListEl.addEventListener('mousedown', function (e) {
            var li = e.target.closest && e.target.closest('.detail-search-location-suggest-item');
            if (!li) return;
            e.preventDefault();
            var label = li.getAttribute('data-label') || '';
            var lat = li.getAttribute('data-lat') || '';
            var lng = li.getAttribute('data-lng') || '';
            passportInput.value = label;
            if (passportLatEl) passportLatEl.value = lat;
            if (passportLngEl) passportLngEl.value = lng;
            setPassportStatus('resolved',
                '<i class="fas fa-circle-check"></i><span>解決済み: <strong>' + escapeHtml(label) +
                '</strong>（' + parseFloat(lat).toFixed(4) + ', ' + parseFloat(lng).toFixed(4) + '）</span>'
            );
            closeSuggest();
        });
    }

    function setCurrentLoading(isLoading) {
        if (!currentBtn) return;
        currentBtn.disabled = !!isLoading;
        currentBtn.classList.toggle('is-loading', !!isLoading);
        if (currentBtnLabel) currentBtnLabel.textContent = isLoading ? '取得中...' : '最新の現在地を取得する';
    }
    if (currentBtn) {
        currentBtn.addEventListener('click', function () {
            if (!navigator.geolocation) {
                setPassportStatus('error', '<i class="fas fa-circle-xmark"></i><span>この端末では位置情報を取得できません。</span>');
                return;
            }
            setCurrentLoading(true);
            navigator.geolocation.getCurrentPosition(function (pos) {
                setCurrentLoading(false);
                if (currentLatEl) currentLatEl.value = pos.coords.latitude.toFixed(7);
                if (currentLngEl) currentLngEl.value = pos.coords.longitude.toFixed(7);
                if (currentLabelEl) currentLabelEl.hidden = false;
                if (currentCheck) currentCheck.hidden = false;
            }, function () {
                setCurrentLoading(false);
            }, { enableHighAccuracy: true, maximumAge: 60000, timeout: 8000 });
        });
    }

    // ===== 既存：条件保存 =====
    // ===== 検索実行時に条件を自動保存（保存ボタンは廃止） =====
    var saveBtn = form.closest('.detail-search-modal__window')?.querySelector('[data-detail-search-submit]');
    if (!saveBtn || !saveBtn.dataset.saveUrl) return;

    function readCheckedValues(name) {
        return Array.from(form.querySelectorAll('input[name="' + name + '"]:checked'))
            .map(function (el) { return el.value; })
            .filter(function (v) { return v !== ''; });
    }
    function readRadioValue(name) {
        var el = form.querySelector('input[name="' + name + '"]:checked');
        return el ? el.value : '';
    }
    function readInputValue(name) {
        var el = form.querySelector('[name="' + name + '"]');
        return el ? String(el.value || '').trim() : '';
    }

    saveBtn.addEventListener('click', function () {
        // 検索遷移と並行して条件を保存（keepalive でナビゲーション後も送信継続）
        var payload = new FormData();
        payload.append('_token', (document.querySelector('meta[name="csrf-token"]') || {}).content || '');

        var distance = readInputValue('distance_km');
        if (distance !== '') {
            // 旧仕様の最大値 '40' は MyPage 互換のため '100' にスケールアップして保存
            payload.append('max_distance_km', distance === '40' ? '100' : distance);
        }

        var ageMin = readInputValue('age_min');
        if (ageMin !== '') payload.append('age_min', ageMin);
        var ageMax = readInputValue('age_max');
        if (ageMax !== '') payload.append('age_max', ageMax);

        var freq = readRadioValue('shift_frequency');
        if (freq !== '') payload.append('shift_frequency', freq);
        readCheckedValues('work_periods[]').forEach(function (v) { payload.append('work_periods[]', v); });
        readCheckedValues('looks_tag_ids[]').forEach(function (v) { payload.append('looks_tag_ids[]', v); });
        readCheckedValues('personality_tag_ids[]').forEach(function (v) { payload.append('personality_tag_ids[]', v); });
        var exp = readRadioValue('night_work_exp');
        if (exp !== '') payload.append('night_work_exp', exp);

        try {
            fetch(saveBtn.dataset.saveUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: payload,
                credentials: 'same-origin',
                keepalive: true
            });
        } catch (e) { /* 保存失敗は検索を妨げない */ }
    });
})();
</script>
<style>
.detail-search-subsection { margin-bottom: 12px; }
.detail-search-subsection__label {
    display: block;
    font-size: 0.74rem;
    font-weight: 700;
    color: var(--gold);
    letter-spacing: 0.04em;
    margin: 0 0 6px;
}
.detail-search-age-range { display: flex; align-items: center; gap: 10px; padding: 4px 2px; }
.detail-search-age-range__field {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex: 1;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid var(--color-border);
    border-radius: 10px;
    padding: 8px 10px;
}
.detail-search-age-range__field > span:first-child {
    font-size: 0.7rem;
    color: var(--color-text-muted);
    font-weight: 700;
}
.detail-search-age-range__field input {
    flex: 1;
    width: 100%;
    background: transparent;
    border: 0;
    color: var(--color-text-header);
    font-size: 0.95rem;
    font-weight: 700;
    text-align: right;
    outline: none;
    min-height: 28px;
}
.detail-search-age-range__unit {
    font-size: 0.75rem;
    color: var(--gold);
    font-weight: 700;
}
.detail-search-age-range__separator {
    color: var(--color-text-muted);
    font-weight: 700;
}
.detail-search-modal__footer--search {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.detail-search-modal__btn--save {
    flex: 1 1 auto;
    background: rgba(168, 85, 247, 0.10);
    border: 1px solid var(--color-border-strong);
    color: var(--gold-light);
    padding: 10px 14px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.84rem;
    cursor: pointer;
    transition: background 0.15s ease;
}
.detail-search-modal__btn--save:hover { background: rgba(168, 85, 247, 0.18); }
.detail-search-modal__save-feedback {
    padding: 8px 16px 0;
    margin: 0;
    font-size: 0.76rem;
    text-align: center;
}
.detail-search-modal__save-feedback.is-success { color: var(--color-success); }
.detail-search-modal__save-feedback.is-error { color: var(--color-danger); }

/* ===== 位置情報設定（shop 詳細検索：インライン UI） ===== */
.detail-search-location-modes { border: 0; padding: 0; margin: 0 0 14px; display: flex; flex-direction: column; gap: 8px; }
.detail-search-location-card {
    position: relative;
    display: block;
    padding: 12px 14px;
    border-radius: 14px;
    border: 1px solid var(--color-border, #4a2f3e);
    background: rgba(255, 255, 255, 0.03);
    cursor: pointer;
    transition: border-color 0.18s ease, background 0.18s ease, opacity 0.18s ease;
}
.detail-search-location-card:hover { border-color: rgba(232, 195, 114, 0.5); }
.detail-search-location-card.is-selected {
    border-color: #E8C372;
    background: rgba(232, 195, 114, 0.10);
}
.detail-search-location-card.is-disabled-soft:not(.is-selected) { opacity: 0.7; }
.detail-search-location-card input[type="radio"] {
    position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none;
}
.detail-search-location-card__row { display: flex; align-items: flex-start; gap: 12px; }
.detail-search-location-card__icon { color: rgba(255, 255, 255, 0.45); font-size: 1.05rem; margin-top: 2px; transition: color 0.18s ease; }
.detail-search-location-card.is-selected .detail-search-location-card__icon { color: #E8C372; }
.detail-search-location-card__main { flex: 1; min-width: 0; }
.detail-search-location-card__title {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    font-size: 0.92rem; font-weight: 700;
    color: rgba(255, 255, 255, 0.85);
}
.detail-search-location-card.is-selected .detail-search-location-card__title { color: #fff; }
.detail-search-location-card__badge {
    font-size: 0.60rem; letter-spacing: 0.14em; font-weight: 800;
    padding: 2px 8px; border-radius: 4px;
    background: linear-gradient(135deg, #E8C372, #a78bfa);
    color: #1a1015;
}
.detail-search-location-card__sub {
    display: block;
    margin-top: 4px;
    font-size: 0.72rem; line-height: 1.5;
    color: rgba(255, 255, 255, 0.55);
}
.detail-search-location-card__sub.is-warn { color: #fca5a5; }
.detail-search-location-card__check { color: #34d399; font-size: 1.05rem; margin-top: 2px; }
.detail-search-location-card__expand { display: none; margin: 12px 0 0 28px; }
.detail-search-location-card.is-selected .detail-search-location-card__expand { display: block; }

.detail-search-location-passport-row { display: flex; gap: 8px; align-items: stretch; }
.detail-search-location-passport-row .detail-search-location-input-wrap { flex: 1; min-width: 0; }
.detail-search-location-input-wrap { position: relative; display: block; }
.detail-search-location-input-wrap > i {
    position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
    color: rgba(255, 255, 255, 0.45);
    font-size: 0.85rem;
}
.detail-search-location-input {
    width: 100%; box-sizing: border-box;
    padding: 10px 14px 10px 36px;
    border-radius: 10px;
    border: 1px solid var(--color-border, #4a2f3e);
    background: rgba(0, 0, 0, 0.25);
    color: #fff;
    font-size: 0.86rem;
}
.detail-search-location-input::placeholder { color: rgba(255, 255, 255, 0.35); }
.detail-search-location-input:focus { outline: none; border-color: #E8C372; box-shadow: 0 0 0 3px rgba(232, 195, 114, 0.15); }

.detail-search-location-lookup-btn {
    flex-shrink: 0;
    display: inline-flex; align-items: center; gap: 6px;
    padding: 0 14px;
    border: 0;
    border-radius: 10px;
    background: linear-gradient(135deg, #E8C372, #a78bfa);
    color: #1a1015;
    font-size: 0.80rem; font-weight: 800;
    cursor: pointer;
    transition: filter 0.15s ease;
}
.detail-search-location-lookup-btn:hover { filter: brightness(1.05); }
.detail-search-location-lookup-btn:disabled { opacity: 0.6; cursor: wait; }
.detail-search-location-lookup-btn.is-loading [data-lookup-icon] { animation: detail-search-location-spin 0.9s linear infinite; }

.detail-search-location-passport-status {
    display: flex; align-items: flex-start; gap: 6px;
    margin: 10px 0 0;
    font-size: 0.72rem; line-height: 1.55;
    color: rgba(255, 255, 255, 0.6);
}
.detail-search-location-passport-status i { color: rgba(232, 195, 114, 0.6); margin-top: 2px; }
.detail-search-location-passport-status[data-state="resolved"] { color: rgba(110, 231, 183, 0.95); }
.detail-search-location-passport-status[data-state="resolved"] i { color: #34d399; }
.detail-search-location-passport-status[data-state="resolved"] strong { color: #fff; font-weight: 800; }
.detail-search-location-passport-status[data-state="error"] { color: #fca5a5; }
.detail-search-location-passport-status[data-state="error"] i { color: #fca5a5; }
.detail-search-location-passport-status[data-state="loading"] { color: rgba(232, 195, 114, 0.85); }
.detail-search-location-passport-status[data-state="loading"] i { color: #E8C372; animation: detail-search-location-spin 0.9s linear infinite; }
@keyframes detail-search-location-spin { to { transform: rotate(360deg); } }

.detail-search-location-suggest-wrap { position: relative; }
.detail-search-location-suggest-list {
    position: absolute;
    top: calc(100% + 4px);
    left: 0; right: 0;
    z-index: 20;
    margin: 0;
    padding: 4px;
    list-style: none;
    max-height: 220px;
    overflow-y: auto;
    background: var(--color-card-strong, #1a1a1a);
    border: 1px solid var(--color-border-strong, rgba(168, 85, 247, 0.4));
    border-radius: 10px;
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.55);
}
.detail-search-location-suggest-list[hidden] { display: none; }
.detail-search-location-suggest-item {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 10px;
    border-radius: 8px;
    font-size: 0.80rem;
    color: var(--color-text, #d8c9a8);
    cursor: pointer;
    transition: background 0.12s ease, color 0.12s ease;
}
.detail-search-location-suggest-item:hover,
.detail-search-location-suggest-item.is-active {
    background: rgba(168, 85, 247, 0.12);
    color: var(--color-text-header, #c4b5fd);
}
.detail-search-location-suggest-item i { color: var(--gold, #a78bfa); font-size: 0.78rem; }
.detail-search-location-suggest-empty {
    padding: 10px;
    font-size: 0.76rem;
    color: var(--color-text-muted, rgba(216,201,168,0.65));
    text-align: center;
}

.detail-search-location-current-btn {
    width: 100%;
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    padding: 10px 14px;
    border-radius: 10px;
    border: 0;
    background: rgba(255, 255, 255, 0.06);
    color: #fff;
    font-size: 0.84rem; font-weight: 600;
    cursor: pointer;
    transition: background 0.15s ease;
}
.detail-search-location-current-btn:hover { background: rgba(255, 255, 255, 0.12); }
.detail-search-location-current-btn:disabled { opacity: 0.6; cursor: wait; }
.detail-search-location-current-btn i { color: #E8C372; }
.detail-search-location-current-btn.is-loading i { animation: detail-search-location-spin 0.9s linear infinite; }
.detail-search-location-hint {
    display: flex; align-items: center; gap: 6px;
    margin: 10px 0 0;
    font-size: 0.70rem;
    color: rgba(255, 255, 255, 0.5);
}
.detail-search-location-hint i { color: rgba(232, 195, 114, 0.6); }

.detail-search-location-radius { margin-top: 8px; padding-top: 12px; border-top: 1px solid rgba(74, 47, 62, 0.6); }
.detail-search-location-slider { padding: 6px 4px 0; }
.detail-search-location-slider__marks {
    display: flex; justify-content: space-between;
    font-size: 0.68rem;
    color: rgba(255, 255, 255, 0.4);
    padding: 0 2px;
    margin-bottom: 10px;
}
.detail-search-location-slider__mark { transition: color 0.15s ease; cursor: pointer; }
.detail-search-location-slider__mark.is-active { color: #E8C372; font-weight: 700; }
.detail-search-location-range {
    -webkit-appearance: none; appearance: none;
    width: 100%; background: transparent;
    margin: 6px 0;
    cursor: pointer;
}
.detail-search-location-range:focus { outline: none; }
.detail-search-location-range::-webkit-slider-runnable-track {
    width: 100%; height: 16px;
    background: rgba(0, 0, 0, 0.35);
    border-radius: 999px;
    border: 1px solid #6b4c5a;
}
.detail-search-location-range::-webkit-slider-thumb {
    -webkit-appearance: none; appearance: none;
    height: 22px; width: 22px;
    border-radius: 50%;
    background: #E8C372;
    border: 2px solid #1a1015;
    box-shadow: 0 0 14px rgba(232, 195, 114, 0.5);
    margin-top: -4px;
    cursor: pointer;
}
.detail-search-location-range::-moz-range-track {
    width: 100%; height: 16px;
    background: rgba(0, 0, 0, 0.35);
    border-radius: 999px;
    border: 1px solid #6b4c5a;
}
.detail-search-location-range::-moz-range-thumb {
    height: 22px; width: 22px;
    border-radius: 50%;
    background: #E8C372;
    border: 2px solid #1a1015;
    box-shadow: 0 0 14px rgba(232, 195, 114, 0.5);
    cursor: pointer;
}
.detail-search-location-slider__value {
    text-align: center;
    margin-top: 10px;
    font-size: 1.0rem; font-weight: 800;
    color: #E8C372;
    letter-spacing: 0.04em;
}
</style>
@endpush
