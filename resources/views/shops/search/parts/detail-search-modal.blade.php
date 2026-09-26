{{-- 店舗用：キャスト詳細検索モーダル --}}
@php
    $savedPrefs = request()->boolean('filters_applied') ? [] : ($savedPreferences ?? []);
    $tagsByCat = $castTagsByCategory ?? ['looks' => [], 'personality' => []];

    $ageMin = (string) (request('age_min', $savedPrefs['age_min'] ?? ''));
    $ageMax = (string) (request('age_max', $savedPrefs['age_max'] ?? ''));

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
                {{-- 年齢範囲 --}}
                <div class="detail-search-accordion detail-search-accordion--panel" data-accordion data-summary-group="年齢" data-open="false">
                    <button type="button" class="detail-search-accordion__head" data-accordion-trigger aria-expanded="false">
                        <span><i class="fas fa-cake-candles" aria-hidden="true"></i>年齢</span>
                        <span class="detail-search-accordion__icon">+</span>
                    </button>
                    <div class="detail-search-accordion__body" hidden>
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

                {{-- 出勤頻度・時間帯 --}}
                <div class="detail-search-accordion detail-search-accordion--panel" data-accordion data-summary-group="出勤頻度・時間帯" data-open="false">
                    <button type="button" class="detail-search-accordion__head" data-accordion-trigger aria-expanded="false">
                        <span><i class="fas fa-clock" aria-hidden="true"></i>希望の出勤頻度・時間帯</span>
                        <span class="detail-search-accordion__icon">+</span>
                    </button>
                    <div class="detail-search-accordion__body" hidden>
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

                {{-- すぐに入れる日（複数選択可。この日にすぐ入れると宣言しているキャストを探す） --}}
                @php
                    $availableOnRaw = request('available_on', []);
                    if (is_string($availableOnRaw)) { $availableOnRaw = [$availableOnRaw]; }
                    $availableOnDates = app(\App\Services\AvailabilityService::class)->normalizeFilterDates($availableOnRaw);
                    $availableOnMin = \Carbon\Carbon::today()->toDateString();
                    $availableOnMax = \Carbon\Carbon::today()->addDays(\App\Services\AvailabilityService::MAX_DAYS_AHEAD)->toDateString();
                @endphp
                <div class="detail-search-accordion detail-search-accordion--panel" data-accordion data-summary-group="すぐに入れる日" data-open="false">
                    <button type="button" class="detail-search-accordion__head" data-accordion-trigger aria-expanded="false">
                        <span><i class="fas fa-calendar-days" aria-hidden="true"></i>すぐに入れる日</span>
                        <span class="detail-search-accordion__icon">+</span>
                    </button>
                    <div class="detail-search-accordion__body" hidden>
                        <div class="detail-search-subsection">
                            <span class="detail-search-subsection__label">この日にすぐ入れると宣言している子を探す（複数選択可）</span>
                            <div class="detail-search-date-picker" data-date-picker>
                                <input type="date" class="detail-search-select" data-date-picker-input
                                       min="{{ $availableOnMin }}" max="{{ $availableOnMax }}">
                                <div class="detail-search-date-picker__selected" data-date-picker-selected>
                                    @foreach($availableOnDates as $d)
                                        <span class="detail-search-area-chip" data-date-chip>
                                            <span class="detail-search-area-chip__label">{{ \App\Services\AvailabilityService::shortLabel($d) }}</span>
                                            <button type="button" class="detail-search-area-chip__remove" data-date-chip-remove aria-label="{{ $d }} を外す">&times;</button>
                                            <input type="hidden" name="available_on[]" value="{{ $d }}">
                                        </span>
                                    @endforeach
                                </div>
                                <p class="detail-search-date-picker__hint">日付を選ぶと下にタグとして追加されます。</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 経験 --}}
                <div class="detail-search-accordion detail-search-accordion--panel" data-accordion data-summary-group="ナイトワーク経験" data-open="false">
                    <button type="button" class="detail-search-accordion__head" data-accordion-trigger aria-expanded="false">
                        <span><i class="fas fa-star" aria-hidden="true"></i>ナイトワーク経験</span>
                        <span class="detail-search-accordion__icon">+</span>
                    </button>
                    <div class="detail-search-accordion__body" hidden>
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
    var form = document.getElementById('detail-search-form');
    if (!form) return;

    // ===== Multi-date picker (すぐに入れる日) =====
    form.querySelectorAll('[data-date-picker]').forEach(function (block) {
        var input = block.querySelector('[data-date-picker-input]');
        var selected = block.querySelector('[data-date-picker-selected]');
        if (!input || !selected) return;

        function currentDates() {
            return Array.from(selected.querySelectorAll('input[name="available_on[]"]'))
                .map(function (i) { return i.value; });
        }

        function shortLabel(iso) {
            var d = new Date(iso + 'T00:00:00');
            if (isNaN(d.getTime())) return iso;
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            if (d.getTime() === today.getTime()) return '本日';
            var weekdays = ['日', '月', '火', '水', '木', '金', '土'];
            return (d.getMonth() + 1) + '/' + d.getDate() + '（' + weekdays[d.getDay()] + '）';
        }

        function addDate(iso) {
            if (!iso) return;
            if (currentDates().indexOf(iso) !== -1) return;
            var wrap = document.createElement('span');
            wrap.className = 'detail-search-area-chip';
            wrap.setAttribute('data-date-chip', '');
            var labelEl = document.createElement('span');
            labelEl.className = 'detail-search-area-chip__label';
            labelEl.textContent = shortLabel(iso);
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'detail-search-area-chip__remove';
            btn.setAttribute('data-date-chip-remove', '');
            btn.setAttribute('aria-label', iso + ' を外す');
            btn.innerHTML = '&times;';
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'available_on[]';
            hidden.value = iso;
            wrap.appendChild(labelEl);
            wrap.appendChild(btn);
            wrap.appendChild(hidden);
            selected.appendChild(wrap);
            form.dispatchEvent(new Event('change', { bubbles: true }));
        }

        input.addEventListener('change', function () {
            var v = input.value;
            if (!v) return;
            addDate(v);
            input.value = '';
        });

        selected.addEventListener('click', function (e) {
            var btn = e.target.closest && e.target.closest('[data-date-chip-remove]');
            if (!btn) return;
            var chip = btn.closest('[data-date-chip]');
            if (chip) chip.remove();
            form.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });

    // Auto-save conditions on submit
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
        var payload = new FormData();
        payload.append('_token', (document.querySelector('meta[name="csrf-token"]') || {}).content || '');

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
        } catch (e) { /* save failure must not block search */ }
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

/* ===== Multi-date picker (すぐに入れる日) ===== */
.detail-search-date-picker { display: flex; flex-direction: column; gap: 10px; }
.detail-search-date-picker__selected { display: flex; flex-wrap: wrap; gap: 6px; min-height: 4px; }
.detail-search-date-picker__hint {
    margin: 0;
    font-size: 0.72rem;
    color: rgba(255, 255, 255, 0.5);
}
.detail-search-area-chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 6px 4px 10px;
    border-radius: 999px;
    background: #E8C372;
    border: 0;
    color: #1a1015;
    font-size: 0.78rem;
    font-weight: 700;
}
.detail-search-area-chip__label { line-height: 1.2; }
.detail-search-area-chip__remove {
    display: inline-flex; align-items: center; justify-content: center;
    width: 22px; height: 22px;
    border: 0;
    background: rgba(26, 16, 21, 0.18);
    color: #1a1015;
    border-radius: 50%;
    font-size: 0.9rem;
    line-height: 1;
    cursor: pointer;
    transition: background 0.15s ease;
}
.detail-search-area-chip__remove:hover { background: rgba(220, 38, 38, 0.85); color: #fff; }
</style>
@endpush
