@extends('layouts.app-v2')

@section('title', 'プラン設定')

@section('content')
<div class="setting-page plan-page">
    {{-- タイトルはヘッダー中央、説明はオコジョガイド（character_guide_settings）に集約 --}}

    @if(session('message'))
        <div class="plan-flash">{{ session('message') }}</div>
    @endif
    @if($errors->any())
        <div class="plan-flash plan-flash--error">{{ $errors->first() }}</div>
    @endif

    @if(!$isShop)
        <div class="plan-guest-note">プランのお申し込みは店舗アカウントでログインすると行えます。</div>
    @endif

    {{-- 現在の状態 --}}
    <div class="plan-status-card {{ $activeSub ? 'is-premium' : ($pendingSub ? 'is-pending' : '') }}">
        <div class="plan-status-label">現在のプラン</div>
        @if($activeSub)
            <div class="plan-status-name"><i class="fas fa-crown"></i> Premiumプラン（{{ $activeSub->cycleLabel() }}）</div>
            <div class="plan-status-meta">有効期限: {{ optional($activeSub->ends_at)->format('Y年n月j日') }} まで</div>
            <div class="plan-doc-links">
                <a href="{{ route('subscription.receipt') }}" class="plan-doc-link"><i class="fas fa-file-circle-check"></i> 領収書をダウンロード</a>
                <a href="{{ route('subscription.invoice') }}" class="plan-doc-link"><i class="fas fa-file-invoice"></i> 請求書をダウンロード</a>
            </div>
        @elseif($pendingSub)
            <div class="plan-status-name"><i class="fas fa-hourglass-half"></i> Premiumプラン お振込待ち</div>
            <div class="plan-status-meta">お振込の確認が取れ次第、Premium機能が有効になります。</div>
        @else
            <div class="plan-status-name">無料プラン</div>
            <div class="plan-status-meta">基本機能をご利用いただけます。</div>
        @endif
    </div>

    {{-- 振込案内（入金待ちのとき） --}}
    @if($pendingSub)
        <section class="plan-transfer-card">
            <h2 class="plan-section-title"><i class="fas fa-university"></i> お振込のご案内</h2>
            <div class="plan-transfer-rows">
                <div class="plan-transfer-row"><span>請求書番号</span><strong>{{ $pendingSub->invoice_number }}</strong></div>
                <div class="plan-transfer-row"><span>お支払い金額（税込）</span><strong class="plan-amount">¥{{ number_format((int) $pendingSub->amount) }}</strong></div>
                <div class="plan-transfer-row"><span>プラン</span><strong>Premium（{{ $pendingSub->cycleLabel() }}）</strong></div>
                <div class="plan-transfer-row"><span>振込期限</span><strong class="plan-due">{{ optional($pendingSub->payment_due_date)->format('Y年n月j日') }}</strong></div>
            </div>
            @if($adminBank)
                <div class="plan-bank-box">
                    <div class="plan-bank-box__label">お振込先（プラン専用口座）</div>
                    <div class="plan-bank-box__body">
                        {{ $adminBank->bank_name }} {{ $adminBank->branch_name }}<br>
                        口座番号: {{ $adminBank->account_number }}<br>
                        口座名義: {{ $adminBank->account_name }}
                    </div>
                </div>
            @endif
            <p class="plan-transfer-note">
                <i class="fas fa-circle-info"></i>
                運営がネットバンキングの入出金明細でお振込を確認した後、Premium機能が開放されます（確認まで1〜2営業日ほどかかる場合があります）。
            </p>
            <div class="plan-doc-links">
                <a href="{{ route('subscription.invoice') }}" class="plan-doc-link plan-doc-link--primary"><i class="fas fa-file-invoice"></i> 請求書をダウンロード</a>
                <form method="POST" action="{{ route('subscription.cancel') }}" onsubmit="return confirm('お申し込みをキャンセルしますか？');" style="display:inline;">
                    @csrf
                    <button type="submit" class="plan-cancel-btn">キャンセル</button>
                </form>
            </div>
        </section>
    @endif

    {{-- プラン内容 --}}
    @php
        $yearlyMonthlyEquivalent = (int) round($prices['yearly'] / 12);
        $yearlySavings           = max(0, $prices['monthly'] * 12 - $prices['yearly']);
    @endphp
    <section class="plan-compare">
        {{-- Free plan --}}
        <div class="plan-card">
            <div class="plan-card__head">
                <div class="plan-card__name">無料プラン</div>
                @if(!$activeSub && !$pendingSub)<span class="plan-chip">適用中</span>@endif
            </div>
            <div class="plan-card__price-block">
                <div class="plan-card__price-main">¥0<span class="plan-card__price-unit">／月</span></div>
                <div class="plan-card__price-sub">ずっと無料</div>
            </div>
            <ul class="plan-card__features">
                <li><i class="fas fa-check"></i> 求人掲載・応募管理・トーク</li>
                <li><i class="fas fa-check"></i> スカウト送信 1日{{ $scoutLimitFree }}件まで</li>
                <li class="is-off"><i class="fas fa-xmark"></i> AIレコメンドの優先表示</li>
                <li class="is-off"><i class="fas fa-xmark"></i> 求人を閲覧したキャスト一覧</li>
            </ul>
        </div>

        {{-- Premium plan --}}
        <div class="plan-card plan-card--premium">
            <span class="plan-card__ribbon" aria-hidden="true">おすすめ</span>
            <div class="plan-card__head">
                <div class="plan-card__name"><i class="fas fa-crown"></i> Premium プラン</div>
                @if($activeSub)<span class="plan-chip plan-chip--premium">適用中</span>@endif
            </div>
            <div class="plan-card__price-block">
                <div class="plan-card__price-main">¥{{ number_format($prices['monthly']) }}<span class="plan-card__price-unit">／月</span></div>
                <div class="plan-card__price-sub">
                    年払いなら
                    <strong>¥{{ number_format($prices['yearly']) }}／年</strong>
                    <span class="plan-card__price-hint">（月換算 ¥{{ number_format($yearlyMonthlyEquivalent) }}）</span>
                </div>
                @if($yearlySavings > 0)
                    <div class="plan-card__save-badge">
                        <i class="fas fa-piggy-bank"></i>
                        年払いなら年間 <strong>¥{{ number_format($yearlySavings) }}</strong> おトク
                    </div>
                @endif
            </div>
            <ul class="plan-card__features">
                <li><i class="fas fa-check"></i> <strong>AIレコメンドで上位表示</strong>（キャスト検索の推薦枠）</li>
                <li><i class="fas fa-check"></i> <strong>求人を閲覧したキャスト一覧</strong>の表示</li>
                <li><i class="fas fa-check"></i> <strong>スカウト送信 1日{{ $scoutLimitPremium }}件まで</strong>（既存キャストは無制限）</li>
                <li><i class="fas fa-check"></i> 請求書・領収書の発行</li>
            </ul>

            @if($isShop && !$activeSub && !$pendingSub)
                <div class="plan-contract-actions">
                    {{-- Yearly CTA is the recommended (お得) option — placed first. --}}
                    <form method="POST" action="{{ route('subscription.contract') }}" onsubmit="return confirm('Premiumプラン（年払い ¥{{ number_format($prices['yearly']) }}）を申し込みます。よろしいですか？');">
                        @csrf
                        <input type="hidden" name="billing_cycle" value="yearly">
                        <button type="submit" class="plan-contract-btn plan-contract-btn--yearly">
                            <span class="plan-contract-btn__title">年払いで申し込む</span>
                            <span class="plan-contract-btn__amount">¥{{ number_format($prices['yearly']) }}／年</span>
                            @if($yearlySavings > 0)
                                <span class="plan-contract-btn__save">年間 ¥{{ number_format($yearlySavings) }} おトク</span>
                            @endif
                        </button>
                    </form>
                    <form method="POST" action="{{ route('subscription.contract') }}" onsubmit="return confirm('Premiumプラン（月払い ¥{{ number_format($prices['monthly']) }}）を申し込みます。よろしいですか？');">
                        @csrf
                        <input type="hidden" name="billing_cycle" value="monthly">
                        <button type="submit" class="plan-contract-btn">
                            <span class="plan-contract-btn__title">月払いで申し込む</span>
                            <span class="plan-contract-btn__amount">¥{{ number_format($prices['monthly']) }}／月</span>
                        </button>
                    </form>
                    <p class="plan-contract-note">お申し込み後、振込先・金額・期限をメールと画面でご案内します。</p>
                </div>
            @endif
        </div>
    </section>
</div>
@endsection

@push('styles')
<style>
.plan-page { padding: 8px 16px 32px; color: #241f33; }
.setting-header { margin-bottom: 18px; }

.plan-flash {
    margin: 0 0 14px; padding: 10px 14px; border-radius: 10px;
    background: rgba(5, 150, 105, 0.08); border: 1px solid rgba(5, 150, 105, 0.35);
    color: #047857; font-size: 0.82rem; line-height: 1.6;
}
.plan-flash--error { background: rgba(220, 38, 38, 0.06); border-color: rgba(220, 38, 38, 0.35); color: #b91c1c; }
.plan-guest-note { margin: 0 0 14px; font-size: 0.8rem; color: #6d6685; }

.plan-status-card {
    background: #ffffff; border: 1px solid rgba(124, 58, 237, 0.20);
    border-radius: 14px; padding: 14px 16px; margin-bottom: 14px;
}
.plan-status-card.is-premium { border-color: rgba(212, 160, 23, 0.55); background: linear-gradient(180deg, rgba(212, 160, 23, 0.07), #ffffff 55%); }
.plan-status-card.is-pending { border-color: rgba(217, 119, 6, 0.5); }
.plan-status-label { font-size: 0.66rem; font-weight: 700; color: #6d6685; letter-spacing: 0.06em; margin-bottom: 4px; }
.plan-status-name { font-size: 1.02rem; font-weight: 800; color: #241f33; }
.plan-status-name i { color: #b8860b; margin-right: 4px; }
.plan-status-card.is-pending .plan-status-name i { color: #b45309; }
.plan-status-meta { font-size: 0.76rem; color: #5f5876; margin-top: 4px; }

.plan-transfer-card {
    background: #ffffff; border: 1px solid rgba(217, 119, 6, 0.45);
    border-radius: 14px; padding: 14px 16px; margin-bottom: 16px;
}
.plan-section-title { font-size: 0.9rem; font-weight: 800; color: #241f33; margin: 0 0 10px; }
.plan-section-title i { color: #b45309; margin-right: 6px; }
.plan-transfer-rows { display: flex; flex-direction: column; }
.plan-transfer-row {
    display: flex; justify-content: space-between; align-items: center; gap: 10px;
    padding: 8px 0; border-bottom: 1px solid rgba(124, 58, 237, 0.10);
    font-size: 0.8rem; color: #5f5876;
}
.plan-transfer-row strong { color: #241f33; font-weight: 800; }
.plan-transfer-row .plan-amount { font-size: 1.05rem; }
.plan-transfer-row .plan-due { color: #b45309; }
.plan-bank-box { margin-top: 12px; background: #faf8fe; border: 1px solid rgba(124, 58, 237, 0.16); border-radius: 10px; padding: 10px 12px; }
.plan-bank-box__label { font-size: 0.68rem; font-weight: 700; color: #6d28d9; margin-bottom: 4px; }
.plan-bank-box__body { font-size: 0.86rem; color: #241f33; line-height: 1.7; font-weight: 600; }
.plan-transfer-note { margin: 10px 0 0; font-size: 0.72rem; color: #6d6685; line-height: 1.7; }
.plan-transfer-note i { color: #7c3aed; margin-right: 4px; }

.plan-doc-links { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; align-items: center; }
.plan-doc-link {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 0.78rem; font-weight: 700; padding: 8px 14px; border-radius: 999px;
    background: #ffffff; border: 1px solid rgba(124, 58, 237, 0.35); color: #6d28d9; text-decoration: none;
}
.plan-doc-link--primary { background: rgba(124, 58, 237, 0.08); }
.plan-cancel-btn {
    font-size: 0.74rem; padding: 8px 14px; border-radius: 999px;
    background: transparent; border: 1px solid rgba(109, 102, 133, 0.4); color: #6d6685; cursor: pointer;
}

/* ============================================================
   Plan comparison cards (2026-09-24 clarity pass)
   - Larger, more prominent prices
   - "おすすめ" ribbon on Premium
   - Explicit yearly-savings badge
   - Yearly CTA promoted to first + labelled with savings
   ============================================================ */
.plan-compare { display: flex; flex-direction: column; gap: 14px; }

.plan-card {
    background: #ffffff;
    border: 1px solid rgba(124, 58, 237, 0.20);
    border-radius: 14px;
    padding: 16px 18px;
    position: relative;
}
.plan-card--premium {
    border: 2px solid rgba(212, 160, 23, 0.60);
    background: linear-gradient(180deg, rgba(212, 160, 23, 0.08), #ffffff 45%);
    box-shadow: 0 8px 26px rgba(180, 130, 10, 0.16);
    padding-top: 22px; /* extra room for the ribbon */
}

/* おすすめ ribbon（Premium 専用） */
.plan-card__ribbon {
    position: absolute;
    top: -1px;
    right: 14px;
    padding: 4px 14px 5px;
    border-radius: 0 0 8px 8px;
    background: linear-gradient(135deg, #f0b842, #b8860b);
    color: #ffffff;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.14em;
    box-shadow: 0 4px 10px rgba(184, 134, 11, 0.35);
}

.plan-card__head {
    display: flex; justify-content: space-between; align-items: center; gap: 10px;
    margin-bottom: 4px;
}
.plan-card__name { font-size: 1.02rem; font-weight: 800; color: #241f33; }
.plan-card__name i { color: #b8860b; margin-right: 4px; }
.plan-chip {
    flex: 0 0 auto; font-size: 0.68rem; font-weight: 700;
    padding: 4px 10px; border-radius: 999px;
    background: rgba(5, 150, 105, 0.10); color: #047857;
}
.plan-chip--premium { background: rgba(212, 160, 23, 0.14); color: #92650a; }

/* Price block — reads as one clear "price sticker" */
.plan-card__price-block {
    margin: 4px 0 14px;
    padding: 10px 12px;
    background: rgba(124, 58, 237, 0.04);
    border-radius: 10px;
    border: 1px solid rgba(124, 58, 237, 0.10);
}
.plan-card--premium .plan-card__price-block {
    background: rgba(212, 160, 23, 0.06);
    border-color: rgba(212, 160, 23, 0.22);
}
.plan-card__price-main {
    font-size: 1.85rem;
    font-weight: 900;
    color: #241f33;
    line-height: 1.1;
    letter-spacing: -0.01em;
    font-variant-numeric: tabular-nums;
}
.plan-card--premium .plan-card__price-main { color: #7c1a06; color: #241f33; }
.plan-card__price-unit {
    font-size: 0.78rem;
    font-weight: 700;
    color: #5f5876;
    margin-left: 4px;
    letter-spacing: 0;
}
.plan-card__price-sub {
    margin-top: 6px;
    font-size: 0.78rem;
    color: #5f5876;
    line-height: 1.55;
}
.plan-card__price-sub strong {
    color: #241f33;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}
.plan-card__price-hint { color: #8b84a1; font-size: 0.72rem; }

.plan-card__save-badge {
    margin-top: 10px;
    padding: 6px 10px;
    border-radius: 999px;
    background: linear-gradient(135deg, rgba(212, 160, 23, 0.16), rgba(240, 184, 66, 0.10));
    border: 1px solid rgba(184, 134, 11, 0.35);
    color: #92650a;
    font-size: 0.74rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.plan-card__save-badge i { font-size: 0.75rem; }
.plan-card__save-badge strong { font-weight: 900; font-variant-numeric: tabular-nums; }

.plan-card__features { list-style: none; margin: 0; padding: 0; font-size: 0.86rem; color: #2d2742; }
.plan-card__features li {
    padding: 8px 0;
    line-height: 1.55;
    border-bottom: 1px solid rgba(124, 58, 237, 0.08);
    display: flex;
    align-items: flex-start;
    gap: 8px;
}
.plan-card__features li:last-child { border-bottom: 0; }
.plan-card__features li i { color: #059669; font-size: 0.78rem; margin-top: 3px; flex: 0 0 auto; }
.plan-card__features li.is-off { color: #8b84a1; }
.plan-card__features li.is-off i { color: #b9b3c9; }

/* Contract CTAs — Yearly = recommended (gold, prominent), Monthly = secondary. */
.plan-contract-actions { margin-top: 16px; display: flex; flex-direction: column; gap: 10px; }
.plan-contract-btn {
    display: flex; flex-direction: column; align-items: center; gap: 3px; width: 100%;
    padding: 14px 18px; border-radius: 12px; border: 0; cursor: pointer;
    background: linear-gradient(135deg, #a78bfa, #7c3aed);
    color: #ffffff; font-weight: 800; font-size: 0.86rem;
    box-shadow: 0 6px 16px rgba(124, 58, 237, 0.30);
    transition: transform 0.12s ease, box-shadow 0.15s ease;
}
.plan-contract-btn:hover { transform: translateY(-1px); box-shadow: 0 10px 22px rgba(124, 58, 237, 0.35); }
.plan-contract-btn__title { font-size: 0.94rem; font-weight: 800; letter-spacing: 0.02em; }
.plan-contract-btn__amount { font-size: 1.02rem; font-weight: 900; font-variant-numeric: tabular-nums; letter-spacing: -0.005em; }
.plan-contract-btn__save {
    display: inline-block;
    margin-top: 2px;
    padding: 2px 10px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.22);
    color: #fff;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.02em;
}

/* Yearly variant (recommended) — golden, biggest visual weight. */
.plan-contract-btn--yearly {
    background: linear-gradient(135deg, #e3b94a, #b8860b);
    box-shadow: 0 8px 22px rgba(184, 134, 11, 0.36);
    padding: 16px 18px;
}
.plan-contract-btn--yearly:hover { box-shadow: 0 12px 28px rgba(184, 134, 11, 0.42); }

.plan-contract-note { font-size: 0.72rem; color: #6d6685; margin: 4px 0 0; text-align: center; line-height: 1.6; }
</style>
@endpush
