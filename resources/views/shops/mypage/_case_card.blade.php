{{-- 採用→入金 を一気通貫で見せるケースカード（店舗視点） --}}
@php
    $stages = $case['stages'] ?? [];
    $progressIndex = (int) ($case['progress_index'] ?? 0);
    $isCompleted = (bool) ($case['is_completed'] ?? false);
    $isActionable = !empty($case['actionable']);
    $deposit = $case['deposit'] ?? null;

    $actionIcon = match ($case['actionable'] ?? '') {
        'approve' => 'fa-check-circle',
        'pay'     => 'fa-yen-sign',
        default   => 'fa-bolt',
    };

    // ---- 今誰のボールか（店舗視点）----
    //   0: キャスト（申請待ち）   1: あなた（承認）   2: 運営（請求書）
    //   3: あなた（入金）         4: 運営（照合・振込） 5: キャスト（受領確認） 6: 完了
    // ラベルは「要対応 / 待ち（誰） / 完了」の3状態で明確に表記する
    $actor = match (true) {
        $isCompleted          => ['cls' => 'case-actor--done',  'icon' => 'fa-circle-check',    'label' => '完了'],
        $progressIndex === 0  => ['cls' => 'case-actor--cast',  'icon' => 'fa-hourglass-half',  'label' => '待ち（キャスト）'],
        $progressIndex === 1  => ['cls' => 'case-actor--you',   'icon' => 'fa-bolt',            'label' => '要対応'],
        $progressIndex === 2  => ['cls' => 'case-actor--admin', 'icon' => 'fa-hourglass-half',  'label' => '待ち（運営）'],
        $progressIndex === 3  => ['cls' => 'case-actor--you',   'icon' => 'fa-bolt',            'label' => '要対応'],
        $progressIndex === 4  => ['cls' => 'case-actor--admin', 'icon' => 'fa-hourglass-half',  'label' => '待ち（運営）'],
        $progressIndex === 5  => ['cls' => 'case-actor--cast',  'icon' => 'fa-hourglass-half',  'label' => '待ち（キャスト）'],
        default               => ['cls' => 'case-actor--admin', 'icon' => 'fa-circle-question', 'label' => '確認中'],
    };
    $caseState = $isCompleted ? 'done' : ($isActionable ? 'action' : 'waiting');

    // ---- 現在ステージの説明（店舗視点）----
    $currentStage = $stages[$progressIndex] ?? null;
    $nextStage    = $stages[$progressIndex + 1] ?? null;
    $nowNote = match ($progressIndex) {
        0 => '採用が確定しました。キャストからボーナス申請が届くとフローが始まります。',
        1 => 'キャストからボーナス申請が届いています。内容を確認して承認してください。',
        2 => '運営が請求書を準備しています。発行され次第、お支払いのご案内が届きます。',
        3 => '請求書が発行されました。支払期限までにお振込のうえ、入金報告をお願いします。',
        4 => '入金報告を受け付けました。運営が照合し、キャストへの振込を実行します。',
        5 => 'キャストの口座へ振込済みです。キャスト側の受領確認をお待ちください。',
        default => null,
    };

    // ---- 停滞警告（5日以上更新なし・進行中のみ）----
    $stallDays = null;
    if (!$isCompleted && !empty($deposit['updated_at_label'])) {
        try {
            $lastUpdated = \Carbon\Carbon::parse($deposit['updated_at_label']);
            $days = $lastUpdated->diffInDays(now());
            if ($days >= 5) $stallDays = $days;
        } catch (\Throwable $e) { /* パース不可なら非表示 */ }
    }
@endphp
<article class="case-card {{ $isActionable ? 'is-actionable' : '' }} {{ $isCompleted ? 'is-completed' : '' }}"
         data-case-state="{{ $caseState }}">
    <span class="case-actor {{ $actor['cls'] }}">
        <i class="fas {{ $actor['icon'] }}" aria-hidden="true"></i>{{ $actor['label'] }}
        @if($stallDays !== null)
            <span class="case-stall"><i class="fas fa-triangle-exclamation"></i>{{ $stallDays }}日停滞</span>
        @endif
    </span>
    <header class="case-card__head">
        @if(!empty($case['cast_avatar_url']))
            <img loading="lazy" decoding="async" src="{{ $case['cast_avatar_url'] }}" alt="" class="case-card__avatar">
        @else
            <div class="case-card__icon">
                <i class="fas {{ $isCompleted ? 'fa-check' : 'fa-user' }}"></i>
            </div>
        @endif
        <div class="case-card__main">
            <h3 class="case-card__shop-name">{{ $case['cast_name'] }}</h3>
            <div class="case-card__meta">
                @if(!empty($case['job_kind_label']))
                    <span><i class="fas fa-briefcase"></i> {{ $case['job_kind_label'] }}</span>
                @endif
                @if(!empty($case['hired_at']))
                    <span><i class="fas fa-calendar-check"></i> {{ $case['hired_at'] }}</span>
                @endif
                @if(!empty($case['hired_hourly_wage_display']))
                    <span>時給 <strong>{{ $case['hired_hourly_wage_display'] }}円</strong></span>
                @endif
            </div>
        </div>
    </header>

    {{-- パイプライン：採用 → ボーナス申請 → 店舗承認 → 請求書発行 → 店舗入金 → 振込実行 → 受領完了 --}}
    <ol class="case-pipeline" aria-label="採用から入金完了までの進捗">
        @foreach($stages as $idx => $stage)
            @php
                $state = match (true) {
                    $idx < $progressIndex => 'is-done',
                    $idx === $progressIndex && !$isCompleted => 'is-current',
                    $idx <= $progressIndex && $isCompleted => 'is-done',
                    default => '',
                };
            @endphp
            <li class="case-pipeline__step {{ $state }}">
                <span class="case-pipeline__bullet" aria-hidden="true">
                    @if($state === 'is-done')
                        <i class="fas fa-check"></i>
                    @else
                        {{ $idx + 1 }}
                    @endif
                </span>
                <span class="case-pipeline__label">{{ $stage['label'] }}</span>
            </li>
        @endforeach
    </ol>

    {{-- 現在ステージの説明 + 次に起こること --}}
    @if(!$isCompleted && $nowNote !== null)
        <div class="case-now {{ $isActionable ? 'case-now--action' : '' }}">
            <i class="fas {{ $isActionable ? 'fa-bolt' : 'fa-circle-info' }} case-now__icon" aria-hidden="true"></i>
            <span class="case-now__body">
                @if($currentStage)
                    <span class="case-now__stage">{{ $currentStage['label'] }}</span>
                @endif
                {{ $nowNote }}
                @if($nextStage)
                    <span class="case-now__next"><i class="fas fa-arrow-right"></i>次のステップ: {{ $nextStage['label'] }}@if(!empty($nextStage['desc']))（{{ $nextStage['desc'] }}）@endif</span>
                @endif
            </span>
        </div>
    @elseif($isCompleted)
        <div class="case-now case-now--done">
            <i class="fas fa-circle-check case-now__icon" aria-hidden="true"></i>
            <span class="case-now__body">全てのステップが完了しました。</span>
        </div>
    @endif

    {{-- 数値ハイライト --}}
    @if($deposit)
        <div class="case-card__highlights">
            @if(!empty($deposit['invoice_amount']))
                <div class="case-card__highlight">
                    <i class="fas fa-file-invoice-dollar"></i>請求金額
                    <strong>¥{{ number_format((int) $deposit['invoice_amount']) }}</strong>
                </div>
            @elseif(!empty($deposit['bonus_amount']))
                <div class="case-card__highlight">
                    <i class="fas fa-gift"></i>ボーナス
                    <strong>¥{{ number_format((int) $deposit['bonus_amount']) }}</strong>
                </div>
            @endif
            @if(!empty($deposit['invoice_due_date']))
                <div class="case-card__highlight">
                    <i class="fas fa-calendar-day"></i>支払期限
                    <strong>{{ $deposit['invoice_due_date'] }}</strong>
                </div>
            @elseif(!empty($deposit['invoice_issued_at']))
                <div class="case-card__highlight">
                    <i class="fas fa-file-invoice"></i>請求書発行
                    <strong>{{ $deposit['invoice_issued_at'] }}</strong>
                </div>
            @elseif(!empty($deposit['shop_payment_confirmed_at']))
                <div class="case-card__highlight">
                    <i class="fas fa-check"></i>入金確認
                    <strong>{{ $deposit['shop_payment_confirmed_at'] }}</strong>
                </div>
            @elseif(!empty($deposit['updated_at_label']))
                <div class="case-card__highlight">
                    <i class="fas fa-clock"></i>最終更新
                    <strong>{{ $deposit['updated_at_label'] }}</strong>
                </div>
            @endif
        </div>
    @endif

    {{-- 請求書ダウンロード（発行後はいつでも参照可能） --}}
    @if($deposit && !empty($deposit['invoice_pdf_url']))
        <div class="case-card__invoice-link-row">
            <a href="{{ $deposit['invoice_pdf_url'] }}" target="_blank" rel="noopener" class="case-card__invoice-link">
                <i class="fas fa-file-pdf"></i> 請求書を確認（PDF）
            </a>
        </div>
    @endif

    {{-- アクション行：要対応時の主ボタンのみ（待ち/完了テキストは右上バッジに集約済み） --}}
    @if($isActionable)
        <div class="case-card__action-row">
            <button type="button" class="case-card__action-btn"
                    data-case-action="{{ $case['actionable'] }}"
                    data-application-id="{{ $case['application_id'] }}"
                    data-deposit-id="{{ $deposit['id'] ?? '' }}"
                    data-job-kind="{{ $deposit['job_kind'] ?? '' }}">
                <i class="fas {{ $actionIcon }}"></i>
                {{ $case['actionable_label'] }}
            </button>
        </div>
    @endif

    {{-- トーク導線：全幅の明確なボタン --}}
    @if(!empty($case['talk_link']))
        <a href="{{ $case['talk_link'] }}" class="case-card__talk-open">
            <i class="fas fa-comment-dots"></i> トークを開く
            <i class="fas fa-chevron-right case-card__talk-open-chev" aria-hidden="true"></i>
        </a>
    @endif
</article>
