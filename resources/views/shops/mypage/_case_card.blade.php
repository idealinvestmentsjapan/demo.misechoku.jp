{{-- Case card (shop side): recruitment -> deposit unified timeline row.
     Status is expressed by which accordion group the card lives in, so no
     top-right actor tag. The bottom action menu lists all available actions
     as identically-shaped rows; the operational task for the current status
     is highlighted with --primary. --}}
@php
    $stages = $case['stages'] ?? [];
    $progressIndex = (int) ($case['progress_index'] ?? 0);
    $isCompleted = (bool) ($case['is_completed'] ?? false);
    $isActionable = !empty($case['actionable']);
    $deposit = $case['deposit'] ?? null;

    $caseState = $isCompleted ? 'done' : ($isActionable ? 'action' : 'waiting');

    $primaryActionIcon = match ($case['actionable'] ?? '') {
        'approve' => 'fa-check-circle',
        'pay'     => 'fa-yen-sign',
        default   => 'fa-bolt',
    };

    // Current-stage description (shop perspective).
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

    {{-- Numeric highlights --}}
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

    {{-- Unified vertical action menu: primary task first (highlighted), then
         invoice PDF (when available), then talk. All rows share the same shape. --}}
    @php
        $hasInvoice = $deposit && !empty($deposit['invoice_pdf_url']);
        $hasTalk = !empty($case['talk_link']);
    @endphp
    @if($isActionable || $hasInvoice || $hasTalk)
        <div class="case-card__actions">
            @if($isActionable)
                <button type="button" class="case-card__action-item case-card__action-item--primary"
                        data-case-action="{{ $case['actionable'] }}"
                        data-application-id="{{ $case['application_id'] }}"
                        data-deposit-id="{{ $deposit['id'] ?? '' }}"
                        data-job-kind="{{ $deposit['job_kind'] ?? '' }}">
                    <span class="case-card__action-item__icon"><i class="fas {{ $primaryActionIcon }}"></i></span>
                    <span class="case-card__action-item__label">{{ $case['actionable_label'] }}</span>
                    <i class="fas fa-chevron-right case-card__action-item__chev" aria-hidden="true"></i>
                </button>
            @endif
            @if($hasInvoice)
                <a href="{{ $deposit['invoice_pdf_url'] }}" target="_blank" rel="noopener" class="case-card__action-item">
                    <span class="case-card__action-item__icon"><i class="fas fa-file-pdf"></i></span>
                    <span class="case-card__action-item__label">請求書を確認（PDF）</span>
                    <i class="fas fa-chevron-right case-card__action-item__chev" aria-hidden="true"></i>
                </a>
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
