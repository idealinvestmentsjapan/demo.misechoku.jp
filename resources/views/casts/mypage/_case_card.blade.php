{{-- Case card (cast side): recruitment -> deposit unified timeline row.
     Status is expressed by the enclosing accordion group. The action menu
     stacks all rows in the same shape; the operational task for the current
     status (bonus request / receipt confirmation) is highlighted with --primary. --}}
@php
    $stages = $case['stages'] ?? [];
    $progressIndex = (int) ($case['progress_index'] ?? 0);
    $isCompleted = (bool) ($case['is_completed'] ?? false);
    $isActionable = !empty($case['actionable']);
    $deposit = $case['deposit'] ?? null;

    $caseState = $isCompleted ? 'done' : ($isActionable ? 'action' : 'waiting');

    // Current-stage description (cast perspective).
    $currentStage = $stages[$progressIndex] ?? null;
    $nextStage    = $stages[$progressIndex + 1] ?? null;
    $nowNote = match ($progressIndex) {
        0 => '採用が確定しました。ボーナス申請を行うと入金フローが始まります。',
        1 => '申請内容を店舗が確認しています。承認されると運営が請求書を発行します。',
        2 => '運営が店舗宛の請求書を準備しています。',
        3 => '店舗が請求書のお支払いを進めています。入金が確認されると振込準備に入ります。',
        4 => '店舗の入金を確認しました。運営からあなたの口座へ振込を実行します。',
        5 => 'あなたの口座へ振込済みです。通帳・アプリで着金を確認して「入金を確認しました」を押してください。',
        default => null,
    };

    // Stall warning (no updates for 5+ days) — inline note, not a pill.
    $stallDays = null;
    if (!$isCompleted && !empty($deposit['updated_at_label'])) {
        try {
            $lastUpdated = \Carbon\Carbon::parse($deposit['updated_at_label']);
            $days = $lastUpdated->diffInDays(now());
            if ($days >= 5) $stallDays = $days;
        } catch (\Throwable $e) { /* skip on parse failure */ }
    }
@endphp
<article class="case-card {{ $isActionable ? 'is-actionable' : '' }} {{ $isCompleted ? 'is-completed' : '' }}"
         data-case-state="{{ $caseState }}">
    <header class="case-card__head">
        <div class="case-card__icon">
            <i class="fas {{ $isCompleted ? 'fa-check' : 'fa-store' }}"></i>
        </div>
        <div class="case-card__main">
            <h3 class="case-card__shop-name">{{ $case['shop_name'] }}</h3>
            <div class="case-card__meta">
                @if(!empty($case['hired_at']))
                    <span><i class="fas fa-calendar-check"></i> {{ $case['hired_at'] }}</span>
                @endif
                @if(!empty($case['hired_hourly_wage_display']))
                    <span>時給 <strong>{{ $case['hired_hourly_wage_display'] }}円</strong></span>
                @else
                    <span class="case-card__meta-muted"><i class="fas fa-clock"></i> 時給設定待ち</span>
                @endif
            </div>
        </div>
    </header>

    @if($stallDays !== null)
        <div class="case-card__stall-note">
            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
            {{ $stallDays }}日間更新がありません
        </div>
    @endif

    {{-- Pipeline: hire -> bonus request -> shop approve -> invoice -> shop pay -> transfer -> receipt --}}
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

    {{-- Current-stage description + what's next --}}
    @if(!$isCompleted && $nowNote !== null)
        <div class="case-now {{ $isActionable ? 'case-now--action' : '' }}">
            <i class="fas {{ $isActionable ? 'fa-bolt' : 'fa-circle-info' }} case-now__icon" aria-hidden="true"></i>
            <span class="case-now__body">
                @if($currentStage)
                    <span class="case-now__stage">{{ $currentStage['label'] }}</span>
                @endif
                {{ $nowNote }}
                @if($nextStage)
                    <span class="case-now__next"><i class="fas fa-arrow-right"></i>次のステップ: {{ $nextStage['label'] }}（{{ $nextStage['desc'] ?? '' }}）</span>
                @endif
            </span>
        </div>
    @elseif($isCompleted)
        <div class="case-now case-now--done">
            <i class="fas fa-circle-check case-now__icon" aria-hidden="true"></i>
            <span class="case-now__body">全てのステップが完了しました。お疲れさまでした！</span>
        </div>
    @endif

    {{-- Numeric highlights --}}
    @if($deposit)
        <div class="case-card__highlights">
            @if(!empty($deposit['cast_transfer_amount']))
                <div class="case-card__highlight">
                    <i class="fas fa-yen-sign"></i>振込額
                    <strong>¥{{ number_format((int) $deposit['cast_transfer_amount']) }}</strong>
                </div>
            @elseif(!empty($deposit['bonus_amount']))
                <div class="case-card__highlight">
                    <i class="fas fa-gift"></i>ボーナス
                    <strong>¥{{ number_format((int) $deposit['bonus_amount']) }}</strong>
                </div>
            @endif
            @if(!empty($deposit['cast_transferred_at']))
                <div class="case-card__highlight">
                    <i class="fas fa-paper-plane"></i>振込日
                    <strong>{{ $deposit['cast_transferred_at'] }}</strong>
                </div>
            @elseif(!empty($deposit['shop_payment_confirmed_at']))
                <div class="case-card__highlight">
                    <i class="fas fa-check"></i>店舗入金確認
                    <strong>{{ $deposit['shop_payment_confirmed_at'] }}</strong>
                </div>
            @elseif(!empty($deposit['invoice_issued_at']))
                <div class="case-card__highlight">
                    <i class="fas fa-file-invoice"></i>請求書発行
                    <strong>{{ $deposit['invoice_issued_at'] }}</strong>
                </div>
            @elseif(!empty($deposit['updated_at_label']))
                <div class="case-card__highlight">
                    <i class="fas fa-clock"></i>最終更新
                    <strong>{{ $deposit['updated_at_label'] }}</strong>
                </div>
            @endif
        </div>
    @endif

    {{-- Unified vertical action menu. All rows share the same shape;
         primary task for this status gets --primary. --}}
    @php
        $hasTalk = !empty($case['talk_link']);
        $primaryIcon = match ($case['actionable'] ?? '') {
            'request' => 'fa-paper-plane',
            'confirm' => 'fa-check-circle',
            default   => 'fa-bolt',
        };
    @endphp
    @if($isActionable || $hasTalk)
        <div class="case-card__actions">
            @if($isActionable)
                <button type="button" class="case-card__action-item case-card__action-item--primary"
                        data-case-action="{{ $case['actionable'] }}"
                        data-application-id="{{ $case['application_id'] }}"
                        data-deposit-id="{{ $case['deposit']['id'] ?? '' }}">
                    <span class="case-card__action-item__icon"><i class="fas {{ $primaryIcon }}"></i></span>
                    <span class="case-card__action-item__label">{{ $case['actionable_label'] }}</span>
                    <i class="fas fa-chevron-right case-card__action-item__chev" aria-hidden="true"></i>
                </button>
            @endif
            @if($hasTalk)
                <a href="{{ $case['talk_link'] }}" class="case-card__action-item">
                    <span class="case-card__action-item__icon"><i class="fas fa-comment-dots"></i></span>
                    <span class="case-card__action-item__label">トーク画面へ</span>
                    <i class="fas fa-chevron-right case-card__action-item__chev" aria-hidden="true"></i>
                </a>
            @endif
        </div>
    @endif
</article>
