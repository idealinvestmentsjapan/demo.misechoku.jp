@extends('layouts.app-v2')

@section('title', 'ヘルプ募集')
@section('header_title', 'ヘルプ募集')
@section('body-class', 'page-help-recruitment')

@php
    use App\Services\AvailabilityService;

    $availSelected  = $availabilityDates ?? [];
    $availActive    = count($availSelected) > 0;
    $availMaxDates  = $availabilityMax ?? AvailabilityService::MAX_DATES;
    $availDaysAhead = $availabilityDaysAhead ?? AvailabilityService::MAX_DAYS_AHEAD;

    $wage = $helpWage ?? ['min' => null, 'max' => null, 'published' => false];
    $wageMinVal = $wage['min'] !== null ? number_format((int) $wage['min']) : '';
    $wageMaxVal = $wage['max'] !== null ? number_format((int) $wage['max']) : '';
    $applicantList = $applicants ?? [];
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/mypage-availability.css') }}?v=20260926-availability-modal">
    <link rel="stylesheet" href="{{ asset('assets/css/help-recruitment.css') }}?v=20260924-init">
@endpush

@section('content')
<div class="help-recruit-shell">

    {{-- Intro line: what this page does at a glance --}}
    <p class="help-recruit-intro">
        募集日を選び、ヘルプ時給を設定すると、キャストの検索・ホームで優先的に表示されます。
    </p>

    {{-- ============ Section 1: 募集日 ============ --}}
    <section class="help-recruit-section" aria-labelledby="help-recruit-dates-h">
        <header class="help-recruit-section__head">
            <h2 id="help-recruit-dates-h" class="help-recruit-section__title">
                <i class="fas fa-calendar-days" aria-hidden="true"></i>
                募集日
            </h2>
            <p class="help-recruit-section__hint">最大{{ $availMaxDates }}日・{{ $availDaysAhead }}日先まで</p>
        </header>

        <div id="availability-card"
             class="cast-avail is-open {{ $availActive ? 'is-active' : '' }}"
             data-availability-declare-url="{{ route('shop.help-recruitment.dates.declare') }}"
             data-availability-clear-url="{{ route('shop.help-recruitment.dates.clear') }}"
             data-availability-max="{{ $availMaxDates }}"
             data-availability-days-ahead="{{ $availDaysAhead }}"
             data-availability-selected="{{ json_encode(array_values($availSelected)) }}"
             aria-labelledby="availability-card-title">
            <div class="cast-avail__row">
                <span class="cast-avail__icon" aria-hidden="true">
                    <i class="fas {{ $availActive ? 'fa-bolt' : 'fa-calendar-days' }}"></i>
                </span>
                <div class="cast-avail__title-block">
                    <p id="availability-card-title" class="cast-avail__title">選択中の日</p>
                    <p class="cast-avail__lead" data-availability-summary>
                        @if($availActive)
                            {{ collect($availSelected)->map(fn ($d) => AvailabilityService::shortLabel($d))->implode('・') }} で募集中
                        @else
                            カレンダーから日付をタップして選択してください
                        @endif
                    </p>
                </div>
                <div class="cast-avail__actions">
                    <button type="button" class="cast-avail__btn cast-avail__btn--primary" data-availability-save disabled>
                        <i class="fas fa-check"></i> 保存
                    </button>
                    @if($availActive)
                        <button type="button" class="cast-avail__btn cast-avail__btn--danger" data-availability-clear>
                            <i class="fas fa-xmark"></i> 取消
                        </button>
                    @endif
                </div>
            </div>
            <div class="cast-avail__cal" data-availability-calendar></div>
        </div>
    </section>

    {{-- ============ Section 2: 時給 ============ --}}
    <section class="help-recruit-section" aria-labelledby="help-recruit-wage-h">
        <header class="help-recruit-section__head">
            <h2 id="help-recruit-wage-h" class="help-recruit-section__title">
                <i class="fas fa-yen-sign" aria-hidden="true"></i>
                ヘルプ時給
            </h2>
            <p class="help-recruit-section__hint">請求時給の 135% を店舗へ請求、時給の 50% をキャストへ振込</p>
        </header>

        <form id="help-wage-form" class="help-recruit-wage" data-wage-url="{{ route('shop.help-recruitment.wage.update') }}">
            @csrf
            <div class="help-recruit-wage__toggle">
                <label class="help-recruit-wage__toggle-label" for="published_help">
                    <span>ヘルプ募集を公開する</span>
                    <span class="help-recruit-wage__toggle-hint">オフにすると検索・ホームに出なくなります</span>
                </label>
                <label class="help-recruit-switch">
                    <input type="checkbox" id="published_help" name="published_help" value="1" @checked($wage['published'])>
                    <span class="help-recruit-switch__track"><span class="help-recruit-switch__knob"></span></span>
                </label>
            </div>

            <div class="help-recruit-wage__grid">
                <div class="help-recruit-wage__field">
                    <label class="help-recruit-wage__label" for="help_hourly_wage">時給（下限）<span class="help-recruit-wage__req">必須</span></label>
                    <div class="help-recruit-wage__unit-wrap">
                        <input type="text" id="help_hourly_wage" name="help_hourly_wage"
                               class="help-recruit-wage__input"
                               value="{{ $wageMinVal }}"
                               placeholder="4,000" inputmode="numeric" data-numeric>
                        <span class="help-recruit-wage__unit">円</span>
                    </div>
                </div>
                <div class="help-recruit-wage__field">
                    <label class="help-recruit-wage__label" for="help_hourly_wage_max">時給（上限）</label>
                    <div class="help-recruit-wage__unit-wrap">
                        <input type="text" id="help_hourly_wage_max" name="help_hourly_wage_max"
                               class="help-recruit-wage__input"
                               value="{{ $wageMaxVal }}"
                               placeholder="任意" inputmode="numeric" data-numeric>
                        <span class="help-recruit-wage__unit">円</span>
                    </div>
                </div>
            </div>

            <div class="help-recruit-wage__foot">
                <p class="help-recruit-wage__note" data-wage-msg role="status" aria-live="polite"></p>
                <button type="submit" class="help-recruit-wage__save">
                    <i class="fas fa-check"></i> 時給を保存
                </button>
            </div>
        </form>
    </section>

    {{-- ============ Section 3: 応募状況 ============ --}}
    <section class="help-recruit-section" aria-labelledby="help-recruit-applicants-h">
        <header class="help-recruit-section__head">
            <h2 id="help-recruit-applicants-h" class="help-recruit-section__title">
                <i class="fas fa-user-group" aria-hidden="true"></i>
                応募中のキャスト
            </h2>
            <p class="help-recruit-section__hint">直近20件・新しい順</p>
        </header>

        @if(count($applicantList) === 0)
            <p class="help-recruit-empty">まだ応募はありません。募集日と時給を公開すると、キャストからの応募がここに並びます。</p>
        @else
            <ul class="help-recruit-applicants">
                @foreach($applicantList as $ap)
                    <li class="help-recruit-applicants__item">
                        <a class="help-recruit-applicants__row" href="{{ route('shop.talk.room', ['id' => $ap['cast_id']]) }}">
                            <span class="help-recruit-applicants__name">
                                {{ $ap['nickname'] }}
                                @if(!empty($ap['unread']) && (int) $ap['unread'] > 0)
                                    <span class="help-recruit-applicants__unread">{{ $ap['unread'] }}</span>
                                @endif
                            </span>
                            <span class="help-recruit-applicants__meta">
                                @if(!empty($ap['latest_at']))
                                    最終やり取り {{ $ap['latest_at'] }}
                                @endif
                                <i class="fas fa-chevron-right" aria-hidden="true"></i>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/mypage-availability.js') }}?v=20260926-availability-modal"></script>
<script>
(function () {
    'use strict';
    const form = document.getElementById('help-wage-form');
    if (!form) return;
    const url = form.getAttribute('data-wage-url');
    const msgEl = form.querySelector('[data-wage-msg]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // Numeric-only input with thousands separator (mirrors recruit-input behavior)
    form.querySelectorAll('[data-numeric]').forEach(function (input) {
        input.addEventListener('input', function () {
            const digits = input.value.replace(/[^\d]/g, '');
            input.value = digits === '' ? '' : Number(digits).toLocaleString();
        });
    });

    function setMsg(text, ok) {
        if (!msgEl) return;
        msgEl.textContent = text;
        msgEl.dataset.state = ok ? 'ok' : 'ng';
    }

    form.addEventListener('submit', async function (ev) {
        ev.preventDefault();
        setMsg('保存中…', true);

        const fd = new FormData(form);
        // strip commas from numeric fields
        ['help_hourly_wage', 'help_hourly_wage_max'].forEach(function (k) {
            const v = (fd.get(k) || '').toString().replace(/,/g, '');
            fd.set(k, v);
        });
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: fd,
            });
            const json = await res.json().catch(function () { return {}; });
            if (!res.ok || json.success === false) {
                setMsg(json.message || '保存に失敗しました。', false);
                return;
            }
            setMsg(json.message || '保存しました。', true);
            if (window.appToast) window.appToast(json.message || '保存しました。', 'success');
        } catch (err) {
            setMsg('通信エラーが発生しました。', false);
        }
    });
})();
</script>
@endpush
