@php
    $options = $detailSearchOptions ?? [];
    $industries = $options['industries'] ?? collect();
    $areas = $options['areas'] ?? collect();
    $hourlyWages = $options['hourly_wages'] ?? collect();
    $rewards = $options['rewards'] ?? collect();
    $workStyleTags = $options['work_style'] ?? collect();
    $welcomeTags = $options['welcome'] ?? collect();
    $benefitTags = $options['benefit'] ?? collect();
    $facilityTags = $options['facility'] ?? collect();
    $atmosphereTags = $options['atmosphere'] ?? collect();

    // 保存済み検索条件（cast_search_preferences）。フォーム未送信時は保存値をデフォルトに使う。
    $savedPrefs = request()->boolean('filters_applied') ? [] : ($savedPreferences ?? []);
    $savedIndustryIds = array_map('intval', $savedPrefs['industry_ids'] ?? []);
    $savedShiftFrequency = (string) ($savedPrefs['shift_frequency'] ?? '');
    $savedWorkPeriods = array_values(array_filter((array) ($savedPrefs['work_periods'] ?? []), 'is_string'));
    $savedHourlyWageMin = $savedPrefs['hourly_wage_min'] ?? null;

    // 業種は industry_ids[] と industry[] (name) の二系統。
    // - industry_ids[]: 保存用 / 既存検索パラメータ（POST 後はこちらが優先）
    // - industry[]   : 既存 GET クエリパラメータ（互換維持、業種名で検索）
    $reqIndustryIds = array_map('intval', (array) request('industry_ids', []));
    $selectedIndustryIds = $reqIndustryIds !== [] ? $reqIndustryIds : $savedIndustryIds;
    $selectedIndustries = array_values((array) request('industry', []));
    $selectedAreas = array_values(array_filter((array) request('area', []), 'is_string'));
    $selectedWorkStyleTags = array_map('intval', (array) request('work_style_tag_ids', []));
    $selectedWelcomeTags = array_map('intval', (array) request('welcome_tag_ids', []));
    $selectedBenefitTags = array_map('intval', (array) request('benefit_tag_ids', []));
    $selectedFacilityTags = array_map('intval', (array) request('facility_tag_ids', []));
    $selectedAtmosphereTags = array_map('intval', (array) request('atmosphere_tag_ids', []));

    $reqHourlyWage = (string) request('hourly_wage', '');
    $selectedHourlyWage = $reqHourlyWage !== ''
        ? $reqHourlyWage
        : ($savedHourlyWageMin !== null ? (string) $savedHourlyWageMin : '');
    $selectedReward = (string) request('reward', '');

    $reqShiftFrequency = (string) request('shift_frequency', '');
    $selectedShiftFrequency = $reqShiftFrequency !== '' ? $reqShiftFrequency : $savedShiftFrequency;

    $reqWorkPeriods = array_values(array_filter((array) request('work_periods', []), 'is_string'));
    $selectedWorkPeriods = $reqWorkPeriods !== [] ? $reqWorkPeriods : $savedWorkPeriods;

    $workPeriodLabels = ['morning' => '朝', 'day' => '昼', 'night' => '夜'];

    // Areas expressed as flat list of "都道府県 市区町村" strings for the suggest input.
    $areaNames = $areas
        ->map(fn ($a) => (string) ($a->name ?? ''))
        ->filter(fn ($n) => $n !== '')
        ->values()
        ->all();
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
                {{-- 希望業種 --}}
                <div class="detail-search-accordion detail-search-accordion--panel" data-accordion data-summary-group="業種" data-open="false">
                    <button type="button" class="detail-search-accordion__head" data-accordion-trigger aria-expanded="false">
                        <span><i class="fas fa-briefcase" aria-hidden="true"></i>希望業種</span>
                        <span class="detail-search-accordion__icon">+</span>
                    </button>
                    <div class="detail-search-accordion__body" hidden>
                        <div class="detail-search-chips detail-search-chips--search">
                            @foreach($industries as $industry)
                                @php
                                    $iid = (int) $industry->id;
                                    $checked = in_array($iid, $selectedIndustryIds, true)
                                        || in_array($industry->name, $selectedIndustries, true);
                                @endphp
                                <label class="detail-search-chip detail-search-chip--search">
                                    <input type="checkbox" name="industry_ids[]" value="{{ $iid }}" data-industry-name="{{ $industry->name }}" {{ $checked ? 'checked' : '' }}>
                                    <span>{{ $industry->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- 希望の出勤頻度・時間帯 --}}
                <div class="detail-search-accordion detail-search-accordion--panel" data-accordion data-summary-group="出勤頻度・時間帯" data-open="false">
                    <button type="button" class="detail-search-accordion__head" data-accordion-trigger aria-expanded="false">
                        <span><i class="fas fa-clock" aria-hidden="true"></i>希望の出勤頻度・時間帯</span>
                        <span class="detail-search-accordion__icon">+</span>
                    </button>
                    <div class="detail-search-accordion__body" hidden>
                        <div class="detail-search-subsection">
                            <span class="detail-search-subsection__label">出勤頻度（1つ選択）</span>
                            <select name="shift_frequency" class="detail-search-select">
                                <option value="" {{ $selectedShiftFrequency === '' ? 'selected' : '' }}>指定なし</option>
                                @foreach(['週1回出勤', '週2回出勤', '週3回以上'] as $freq)
                                    <option value="{{ $freq }}" {{ $selectedShiftFrequency === $freq ? 'selected' : '' }}>{{ $freq }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="detail-search-subsection">
                            <span class="detail-search-subsection__label">時間帯（複数選択可）</span>
                            <div class="detail-search-chips detail-search-chips--search">
                                @foreach($workPeriodLabels as $key => $label)
                                    <label class="detail-search-chip detail-search-chip--search">
                                        <input type="checkbox" name="work_periods[]" value="{{ $key }}" {{ in_array($key, $selectedWorkPeriods, true) ? 'checked' : '' }}>
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                {{-- すぐに入れる日（複数選択可。この日にヘルプ募集している店舗を探す） --}}
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
                            <span class="detail-search-subsection__label">この日にヘルプ募集している店舗を探す（複数選択可）</span>
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
                                <p class="detail-search-area-suggest__hint">日付を選ぶと下にタグとして追加されます。</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- エリア（サジェスト入力） --}}
                <div class="detail-search-accordion detail-search-accordion--panel" data-accordion data-summary-group="エリア" data-open="false">
                    <button type="button" class="detail-search-accordion__head" data-accordion-trigger aria-expanded="false">
                        <span><i class="fas fa-location-dot" aria-hidden="true"></i>エリア</span>
                        <span class="detail-search-accordion__icon">+</span>
                    </button>
                    <div class="detail-search-accordion__body" hidden>
                        <div class="detail-search-area-suggest"
                             data-area-suggest
                             data-area-source='@json($areaNames)'>
                            <div class="detail-search-area-suggest__input-wrap">
                                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                                <input type="text"
                                       class="detail-search-area-suggest__input"
                                       data-area-suggest-input
                                       placeholder="エリア名を入力（例: 新宿, 港区）"
                                       autocomplete="off"
                                       role="combobox"
                                       aria-autocomplete="list"
                                       aria-expanded="false">
                                <ul class="detail-search-area-suggest__list" data-area-suggest-list role="listbox" hidden></ul>
                            </div>
                            <div class="detail-search-area-suggest__selected" data-area-suggest-selected>
                                @foreach($selectedAreas as $areaName)
                                    <span class="detail-search-area-chip" data-area-chip>
                                        <span class="detail-search-area-chip__label">{{ $areaName }}</span>
                                        <button type="button" class="detail-search-area-chip__remove" data-area-chip-remove aria-label="{{ $areaName }} を外す">&times;</button>
                                        <input type="hidden" name="area[]" value="{{ $areaName }}">
                                    </span>
                                @endforeach
                            </div>
                            <p class="detail-search-area-suggest__hint">入力欄に文字を入れると候補が出ます。選ぶとタグとして追加されます。</p>
                        </div>
                    </div>
                </div>

                @php
                    // マスタが空でもスライダーが機能するようフォールバック候補を用意
                    $wageOptions = $hourlyWages instanceof \Illuminate\Support\Collection ? $hourlyWages->all() : (array) $hourlyWages;
                    if (empty($wageOptions)) $wageOptions = [3000, 4000, 5000, 6000, 8000, 10000];
                    $rewardOptions = $rewards instanceof \Illuminate\Support\Collection ? $rewards->all() : (array) $rewards;
                    if (empty($rewardOptions)) $rewardOptions = [10000, 30000, 50000, 100000, 200000, 300000];
                @endphp

                {{-- 給与(時給)：スライドバー（hidden input に値を直接書き込む方式） --}}
                <div class="detail-search-section detail-search-section--panel" data-summary-group="給与(時給)">
                    <label class="detail-search-label detail-search-label--panel" for="detail-search-hourly-wage-range"><i class="fas fa-yen-sign" aria-hidden="true"></i>給与(時給)</label>
                    <input type="hidden" name="hourly_wage" id="detail-search-hourly-wage" value="{{ $selectedHourlyWage }}">
                    <div class="detail-search-range"
                         data-range-target="detail-search-hourly-wage"
                         data-range-values='@json(array_map("intval", $wageOptions))'
                         data-range-labeler="wage"
                         data-range-initial="{{ $selectedHourlyWage }}">
                        <input type="range" id="detail-search-hourly-wage-range"
                               class="detail-search-range__input"
                               min="0" step="1" value="0"
                               aria-label="希望時給">
                        <div class="detail-search-range__value" data-range-value>指定なし</div>
                    </div>
                </div>

                {{-- 採用報酬（ボーナス金）：スライドバー --}}
                <div class="detail-search-section detail-search-section--panel" data-summary-group="採用報酬">
                    <label class="detail-search-label detail-search-label--panel" for="detail-search-reward-range"><i class="fas fa-gift" aria-hidden="true"></i>採用報酬（ボーナス金）</label>
                    <input type="hidden" name="reward" id="detail-search-reward" value="{{ $selectedReward }}">
                    <div class="detail-search-range"
                         data-range-target="detail-search-reward"
                         data-range-values='@json(array_map("intval", $rewardOptions))'
                         data-range-labeler="reward"
                         data-range-initial="{{ $selectedReward }}">
                        <input type="range" id="detail-search-reward-range"
                               class="detail-search-range__input"
                               min="0" step="1" value="0"
                               aria-label="採用報酬">
                        <div class="detail-search-range__value" data-range-value>指定なし</div>
                    </div>
                </div>

                <div class="detail-search-accordion detail-search-accordion--panel" data-accordion data-summary-group="働き方・給与">
                    <button type="button" class="detail-search-accordion__head" data-accordion-trigger aria-expanded="false">
                        <span><i class="fas fa-user-clock" aria-hidden="true"></i>働き方・給与</span>
                        <span class="detail-search-accordion__icon">+</span>
                    </button>
                    <div class="detail-search-accordion__body" hidden>
                        <div class="detail-search-chips detail-search-chips--search">
                            @foreach($workStyleTags as $tag)
                                <label class="detail-search-chip detail-search-chip--search">
                                    <input type="checkbox" name="work_style_tag_ids[]" value="{{ $tag->id }}" {{ in_array((int) $tag->id, $selectedWorkStyleTags, true) ? 'checked' : '' }}>
                                    <span>{{ $tag->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="detail-search-accordion detail-search-accordion--panel" data-accordion data-summary-group="歓迎条件">
                    <button type="button" class="detail-search-accordion__head" data-accordion-trigger aria-expanded="false">
                        <span><i class="fas fa-heart" aria-hidden="true"></i>歓迎条件</span>
                        <span class="detail-search-accordion__icon">+</span>
                    </button>
                    <div class="detail-search-accordion__body" hidden>
                        <div class="detail-search-chips detail-search-chips--search">
                            @foreach($welcomeTags as $tag)
                                <label class="detail-search-chip detail-search-chip--search">
                                    <input type="checkbox" name="welcome_tag_ids[]" value="{{ $tag->id }}" {{ in_array((int) $tag->id, $selectedWelcomeTags, true) ? 'checked' : '' }}>
                                    <span>{{ $tag->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="detail-search-accordion detail-search-accordion--panel" data-accordion data-summary-group="待遇・サポート">
                    <button type="button" class="detail-search-accordion__head" data-accordion-trigger aria-expanded="false">
                        <span><i class="fas fa-hand-holding-heart" aria-hidden="true"></i>待遇・サポート</span>
                        <span class="detail-search-accordion__icon">+</span>
                    </button>
                    <div class="detail-search-accordion__body" hidden>
                        <div class="detail-search-chips detail-search-chips--search">
                            @foreach($benefitTags as $tag)
                                <label class="detail-search-chip detail-search-chip--search">
                                    <input type="checkbox" name="benefit_tag_ids[]" value="{{ $tag->id }}" {{ in_array((int) $tag->id, $selectedBenefitTags, true) ? 'checked' : '' }}>
                                    <span>{{ $tag->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="detail-search-accordion detail-search-accordion--panel" data-accordion data-summary-group="店舗の雰囲気・設備" data-open="false">
                    <button type="button" class="detail-search-accordion__head" data-accordion-trigger aria-expanded="false">
                        <span><i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i>店舗の雰囲気・設備</span>
                        <span class="detail-search-accordion__icon">+</span>
                    </button>
                    <div class="detail-search-accordion__body" hidden>
                        <div class="detail-search-chips detail-search-chips--search">
                            @foreach($atmosphereTags as $tag)
                                <label class="detail-search-chip detail-search-chip--search">
                                    <input type="checkbox" name="atmosphere_tag_ids[]" value="{{ $tag->id }}" {{ in_array((int) $tag->id, $selectedAtmosphereTags, true) ? 'checked' : '' }}>
                                    <span>{{ $tag->name }}</span>
                                </label>
                            @endforeach
                            @foreach($facilityTags as $tag)
                                <label class="detail-search-chip detail-search-chip--search">
                                    <input type="checkbox" name="facility_tag_ids[]" value="{{ $tag->id }}" {{ in_array((int) $tag->id, $selectedFacilityTags, true) ? 'checked' : '' }}>
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
                    data-save-url="{{ route('cast.search-preferences.save') }}">検索</button>
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
            // iso = 'YYYY-MM-DD' -> 'M/D（曜）' or '本日'
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

    // ===== Area suggest input =====
    var areaBlocks = form.querySelectorAll('[data-area-suggest]');
    areaBlocks.forEach(function (block) {
        var input = block.querySelector('[data-area-suggest-input]');
        var list = block.querySelector('[data-area-suggest-list]');
        var selected = block.querySelector('[data-area-suggest-selected]');
        var source = [];
        try { source = JSON.parse(block.getAttribute('data-area-source') || '[]'); }
        catch (e) { source = []; }
        if (!input || !list || !selected) return;

        function currentSelectedNames() {
            return Array.from(selected.querySelectorAll('input[name="area[]"]'))
                .map(function (i) { return i.value; });
        }

        function closeList() {
            list.hidden = true;
            list.innerHTML = '';
            input.setAttribute('aria-expanded', 'false');
        }

        function addArea(name) {
            if (!name) return;
            if (currentSelectedNames().indexOf(name) !== -1) return;
            var wrap = document.createElement('span');
            wrap.className = 'detail-search-area-chip';
            wrap.setAttribute('data-area-chip', '');
            var labelEl = document.createElement('span');
            labelEl.className = 'detail-search-area-chip__label';
            labelEl.textContent = name;
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'detail-search-area-chip__remove';
            btn.setAttribute('data-area-chip-remove', '');
            btn.setAttribute('aria-label', name + ' を外す');
            btn.innerHTML = '&times;';
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'area[]';
            hidden.value = name;
            wrap.appendChild(labelEl);
            wrap.appendChild(btn);
            wrap.appendChild(hidden);
            selected.appendChild(wrap);
            form.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function renderList(q) {
            list.innerHTML = '';
            var already = currentSelectedNames();
            var candidates = source
                .filter(function (n) { return already.indexOf(n) === -1; })
                .filter(function (n) { return q === '' || n.indexOf(q) !== -1; })
                .slice(0, 20);
            if (candidates.length === 0) {
                var empty = document.createElement('li');
                empty.className = 'detail-search-area-suggest__empty';
                empty.textContent = '候補が見つかりません';
                list.appendChild(empty);
            } else {
                candidates.forEach(function (name) {
                    var li = document.createElement('li');
                    li.className = 'detail-search-area-suggest__item';
                    li.setAttribute('role', 'option');
                    li.setAttribute('data-area-value', name);
                    li.innerHTML = '<i class="fas fa-map-marker-alt" aria-hidden="true"></i><span></span>';
                    li.querySelector('span').textContent = name;
                    list.appendChild(li);
                });
            }
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }

        input.addEventListener('input', function () {
            var q = (input.value || '').trim();
            if (q === '') { closeList(); return; }
            renderList(q);
        });
        input.addEventListener('focus', function () {
            var q = (input.value || '').trim();
            if (q !== '') renderList(q);
        });
        input.addEventListener('blur', function () { setTimeout(closeList, 150); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.isComposing && e.keyCode !== 229) {
                e.preventDefault();
                var q = (input.value || '').trim();
                if (q !== '' && source.indexOf(q) !== -1) {
                    addArea(q);
                    input.value = '';
                    closeList();
                }
            } else if (e.key === 'Escape') {
                closeList();
            }
        });

        list.addEventListener('mousedown', function (e) {
            var li = e.target.closest && e.target.closest('.detail-search-area-suggest__item');
            if (!li) return;
            e.preventDefault();
            var name = li.getAttribute('data-area-value') || '';
            addArea(name);
            input.value = '';
            closeList();
            input.focus();
        });

        selected.addEventListener('click', function (e) {
            var btn = e.target.closest && e.target.closest('[data-area-chip-remove]');
            if (!btn) return;
            var chip = btn.closest('[data-area-chip]');
            if (chip) chip.remove();
            form.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });

    // ===== Auto-save conditions on submit =====
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

    saveBtn.addEventListener('click', function () {
        var payload = new FormData();
        payload.append('_token', (document.querySelector('meta[name="csrf-token"]') || {}).content || '');
        readCheckedValues('industry_ids[]').forEach(function (v) { payload.append('industry_ids[]', v); });
        readCheckedValues('work_periods[]').forEach(function (v) { payload.append('work_periods[]', v); });
        var freq = readRadioValue('shift_frequency');
        if (freq !== '') payload.append('shift_frequency', freq);
        var wage = (form.querySelector('[name="hourly_wage"]') || {}).value || '';
        if (wage !== '') payload.append('hourly_wage_min', wage);

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

/* ===== Area suggest input ===== */
.detail-search-area-suggest { display: flex; flex-direction: column; gap: 10px; }
.detail-search-area-suggest__input-wrap { position: relative; }
.detail-search-area-suggest__input-wrap > i {
    position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
    color: rgba(255, 255, 255, 0.45);
    font-size: 0.85rem;
    pointer-events: none;
}
.detail-search-area-suggest__input {
    width: 100%; box-sizing: border-box;
    padding: 10px 14px 10px 36px;
    border-radius: 10px;
    border: 1px solid var(--color-border, #4a2f3e);
    background: rgba(0, 0, 0, 0.25);
    color: #fff;
    font-size: 0.86rem;
}
.detail-search-area-suggest__input::placeholder { color: rgba(255, 255, 255, 0.35); }
.detail-search-area-suggest__input:focus { outline: none; border-color: #E8C372; box-shadow: 0 0 0 3px rgba(232, 195, 114, 0.15); }
.detail-search-area-suggest__list {
    position: absolute;
    top: calc(100% + 4px);
    left: 0; right: 0;
    z-index: 20;
    margin: 0;
    padding: 4px;
    list-style: none;
    max-height: 240px;
    overflow-y: auto;
    background: var(--color-card-strong, #1a1a1a);
    border: 1px solid var(--color-border-strong, rgba(168, 85, 247, 0.4));
    border-radius: 10px;
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.55);
}
.detail-search-area-suggest__list[hidden] { display: none; }
.detail-search-area-suggest__item {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 10px;
    border-radius: 8px;
    font-size: 0.82rem;
    color: var(--color-text, #d8c9a8);
    cursor: pointer;
    transition: background 0.12s ease, color 0.12s ease;
}
.detail-search-area-suggest__item:hover,
.detail-search-area-suggest__item.is-active {
    background: rgba(168, 85, 247, 0.12);
    color: var(--color-text-header, #c4b5fd);
}
.detail-search-area-suggest__item i { color: var(--gold, #a78bfa); font-size: 0.78rem; }
.detail-search-area-suggest__empty {
    padding: 10px;
    font-size: 0.76rem;
    color: var(--color-text-muted, rgba(216,201,168,0.65));
    text-align: center;
}
.detail-search-area-suggest__hint {
    margin: 0;
    font-size: 0.72rem;
    color: rgba(255, 255, 255, 0.5);
}
.detail-search-area-suggest__selected {
    display: flex; flex-wrap: wrap; gap: 6px;
    min-height: 4px;
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
