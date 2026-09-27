@extends('layouts.admin')

@section('title', '請求書発行')

@section('content')
    @php
        // ペンディング案件の集計
        $castWaitingCount = 0;
        $issueWaitingCount = 0;
        if (!empty($pending)) {
            foreach ($pending as $d) {
                if ((int) ($d['status_code'] ?? 0) === \App\Services\BillingManagementService::STATUS_CAST_REQUESTED) {
                    $castWaitingCount++;
                } else {
                    $issueWaitingCount++;
                }
            }
        }
        $totalAchievement = (int) (($adminOperationAchievements ?? [])['admin.invoices.index'] ?? 0);
    @endphp

    <div class="admin-page">
        <div class="u-flex-between">
            @include('admin.parts.page-title', [
                'eyebrow' => 'INVOICES',
                'title' => '請求書発行',
                'info' => '
                    <p>キャストからの<strong>入金依頼（店舗承認待ち）</strong>と、<strong>店舗承認後の請求書発行</strong>をまとめて扱います。</p>
                    <p>入金照合・キャスト振込は「<strong>入金確認・振込</strong>」画面で行ってください。</p>
                ',
            ])
            @include('admin.parts.operation-achievement', ['operationAchievementRoute' => 'admin.invoices.index'])
        </div>

        @if(session('status'))
            <div class="admin-alert admin-alert-success">{{ session('status') }}</div>
        @endif
        @if(session('error'))
            <div class="admin-alert admin-alert-error">{{ session('error') }}</div>
        @endif

        {{-- KPI: ボタンとして機能（クリックで下の一覧をフィルタ） --}}
        @php
            $overdueCount = 0;
            if (!empty($pending)) {
                foreach ($pending as $d) {
                    if (!empty($d['created_at'])) {
                        $days = (int) \Carbon\Carbon::parse($d['created_at'])->diffInDays(now());
                        if ($days >= 7) $overdueCount++;
                    }
                }
            }
        @endphp
        <section class="dashboard-kpi-grid invoice-filter-kpis" data-invoice-filter-host>
            <button type="button" class="dashboard-kpi-card dashboard-kpi-card--link is-active" data-filter="all" aria-pressed="true">
                <div class="dashboard-kpi-head">
                    <div class="dashboard-kpi-title">未処理（合計）</div>
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="dashboard-kpi-main">
                    <span class="dashboard-kpi-value">{{ number_format(count($pending ?? [])) }}</span>
                    <span class="dashboard-kpi-unit">件</span>
                </div>
                <div class="dashboard-kpi-trend">クリックですべて表示</div>
            </button>
            <button type="button" class="dashboard-kpi-card dashboard-kpi-card--link" data-filter="cast_request" aria-pressed="false">
                <div class="dashboard-kpi-head">
                    <div class="dashboard-kpi-title">店舗承認待ち</div>
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="dashboard-kpi-main">
                    <span class="dashboard-kpi-value">{{ number_format($castWaitingCount) }}</span>
                    <span class="dashboard-kpi-unit">件</span>
                </div>
                <div class="dashboard-kpi-trend">運営の対応は不要</div>
            </button>
            <button type="button" class="dashboard-kpi-card dashboard-kpi-card--link" data-filter="issue_wait" aria-pressed="false">
                <div class="dashboard-kpi-head">
                    <div class="dashboard-kpi-title">発行待ち</div>
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="dashboard-kpi-main">
                    <span class="dashboard-kpi-value">{{ number_format($issueWaitingCount) }}</span>
                    <span class="dashboard-kpi-unit">件</span>
                </div>
                <div class="dashboard-kpi-trend is-up">運営が今すぐ発行可</div>
            </button>
            <button type="button" class="dashboard-kpi-card dashboard-kpi-card--link {{ $overdueCount > 0 ? 'is-critical' : '' }}" data-filter="overdue" aria-pressed="false">
                <div class="dashboard-kpi-head">
                    <div class="dashboard-kpi-title">7日経過</div>
                    <i class="fas fa-triangle-exclamation"></i>
                </div>
                <div class="dashboard-kpi-main">
                    <span class="dashboard-kpi-value">{{ number_format($overdueCount) }}</span>
                    <span class="dashboard-kpi-unit">件</span>
                </div>
                <div class="dashboard-kpi-trend is-down">放置リスクあり</div>
            </button>
        </section>

        {{-- 入金依頼・請求書発行待ち --}}
        <section class="admin-panel">
            <h2 class="admin-panel-title">入金依頼・請求書発行</h2>
            <p class="admin-note u-mb-12">
                キャストから入金依頼が届いて店舗承認前の案件、および店舗承認済みで請求書未発行の案件です。
            </p>

            @if(!empty($pending))
                {{-- ソート --}}
                <div class="invoice-toolbar" data-invoice-toolbar>
                    <label class="invoice-toolbar__sort">
                        <span><i class="fas fa-arrow-down-wide-short"></i> 並び順</span>
                        <select data-invoice-sort>
                            <option value="age_desc" selected>申請が古い順（緊急優先）</option>
                            <option value="age_asc">新しい順</option>
                            <option value="amount_desc">金額が高い順</option>
                            <option value="amount_asc">金額が低い順</option>
                        </select>
                    </label>
                </div>

                <div class="invoice-pending-list" data-invoice-list>
                    @foreach($pending as $deposit)
                        @php
                            $statusCode = (int) ($deposit['status_code'] ?? 0);
                            $isCastRequestOnly = $statusCode === \App\Services\BillingManagementService::STATUS_CAST_REQUESTED;
                            $createdAt = !empty($deposit['created_at']) ? \Carbon\Carbon::parse($deposit['created_at']) : null;
                            $daysElapsed = $createdAt ? (int) $createdAt->diffInDays(now()) : null;
                            $dueClass = $daysElapsed === null ? '' : ($daysElapsed >= 7 ? 'is-overdue' : ($daysElapsed >= 3 ? 'is-soon' : ''));
                            $invoiceAmount = (int) ($deposit['invoice_amount'] ?? 0);
                            $searchKey = mb_strtolower(implode(' ', array_filter([
                                (string) ($deposit['shop_name'] ?? ''),
                                (string) ($deposit['cast_name'] ?? ''),
                                '#' . ($deposit['id'] ?? ''),
                            ])));
                            $cardFilter = $isCastRequestOnly ? 'cast_request' : 'issue_wait';
                            $isOverdue = $daysElapsed !== null && $daysElapsed >= 7;
                        @endphp
                        <div
                            class="invoice-pending-card {{ $isOverdue ? 'is-overdue-card' : '' }}"
                            id="invoice-pending-{{ $deposit['id'] }}"
                            data-invoice-card
                            data-status="{{ $cardFilter }}"
                            data-overdue="{{ $isOverdue ? '1' : '0' }}"
                            data-age="{{ $daysElapsed ?? -1 }}"
                            data-amount="{{ $invoiceAmount }}"
                            data-search="{{ $searchKey }}"
                        >
                            <div class="invoice-pending-card-info">
                                <div class="invoice-pending-card-title">
                                    #{{ $deposit['id'] }} {{ $deposit['shop_name'] }} / {{ $deposit['cast_name'] }}
                                </div>
                                <div class="invoice-pending-card-meta">
                                    @if($isCastRequestOnly)
                                        <span class="actor-pill is-shop">
                                            <i class="fas fa-store"></i> 店舗待ち
                                        </span>
                                        <span class="admin-status-badge is-warning">店舗承認待ち</span>
                                    @else
                                        <span class="actor-pill is-admin">
                                            <i class="fas fa-bell"></i> 運営対応
                                        </span>
                                        <span class="admin-status-badge is-info">請求書発行待ち</span>
                                    @endif
                                    @if($daysElapsed !== null)
                                        <span class="invoice-pending-card-due {{ $dueClass }}">
                                            <i class="fas fa-clock"></i>
                                            申請から {{ $daysElapsed }} 日経過
                                        </span>
                                    @endif
                                    @if(!empty($deposit['next_action']))
                                        <div class="admin-note u-mt-4">{{ $deposit['next_action'] }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="invoice-pending-card-amount">¥{{ number_format($deposit['invoice_amount'] ?? 0) }}</div>
                            <div class="invoice-pending-card-actions">
                                @if($isCastRequestOnly)
                                    <span class="admin-note">店舗承認後に発行できます</span>
                                @elseif($adminBank)
                                    <a href="{{ route('admin.deposits.invoice.show', $deposit['id']) }}" class="btn-action manage">
                                        <i class="fas fa-file-invoice"></i> 発行する
                                    </a>
                                @else
                                    <a href="{{ route('admin.bank.index') }}" class="btn-action manage" title="請求書プレビューには運営口座の登録が必要です">
                                        <i class="fas fa-university"></i> 口座登録へ
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="invoice-empty-note" data-invoice-empty hidden>
                    <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                    絞り込み条件に一致する案件はありません。
                </div>
            @else
                <div class="invoice-empty-note">
                    <i class="fas fa-circle-check" aria-hidden="true"></i>
                    現在、入金依頼待ち・請求書発行待ちの案件はありません。
                </div>
            @endif

            <div class="u-mt-16">
                <a href="{{ route('admin.deposits.index') }}" class="invoice-link-deposits">
                    <i class="fas fa-list"></i> 入金確認・振込一覧へ
                </a>
            </div>
        </section>

        {{-- 発行済み請求書の送付状況（直近20件） --}}
        @if(!empty($issued))
        <section class="admin-panel">
            <h2 class="admin-panel-title">発行済み請求書の送付状況</h2>
            <p class="admin-note u-mb-12">
                発行後に店舗へメールが届いたかを確認できます。<strong>送付済み</strong>=メール送信に成功した記録あり。<strong>未送付</strong>=メール送信が失敗したため再送してください。
            </p>
            <div class="dashboard-kpi-grid invoice-filter-kpis u-mb-12">
                <div class="dashboard-kpi-card">
                    <div class="dashboard-kpi-head">
                        <div class="dashboard-kpi-title">送付済み</div>
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div class="dashboard-kpi-main">
                        <span class="dashboard-kpi-value">{{ number_format($deliverySummary['sent'] ?? 0) }}</span>
                        <span class="dashboard-kpi-unit">件</span>
                    </div>
                </div>
                <div class="dashboard-kpi-card {{ ($deliverySummary['unsent'] ?? 0) > 0 ? 'is-critical' : '' }}">
                    <div class="dashboard-kpi-head">
                        <div class="dashboard-kpi-title">未送付（要再送）</div>
                        <i class="fas fa-triangle-exclamation"></i>
                    </div>
                    <div class="dashboard-kpi-main">
                        <span class="dashboard-kpi-value">{{ number_format($deliverySummary['unsent'] ?? 0) }}</span>
                        <span class="dashboard-kpi-unit">件</span>
                    </div>
                </div>
                <div class="dashboard-kpi-card">
                    <div class="dashboard-kpi-head">
                        <div class="dashboard-kpi-title">店舗メール未登録</div>
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="dashboard-kpi-main">
                        <span class="dashboard-kpi-value">{{ number_format($deliverySummary['no_email'] ?? 0) }}</span>
                        <span class="dashboard-kpi-unit">件</span>
                    </div>
                </div>
            </div>

            <div class="invoice-pending-list">
                @foreach($issued as $d)
                    @php $del = $d['mail_delivery_status'] ?? ['code' => 'pre_issue', 'label' => '未発行', 'sent_at' => null]; @endphp
                    <div class="invoice-pending-card">
                        <div class="invoice-pending-card-info">
                            <div class="invoice-pending-card-title">
                                {{ $d['invoice_number'] }} ／ {{ $d['shop_name'] }} / {{ $d['cast_name'] }}
                            </div>
                            <div class="invoice-pending-card-meta">
                                @if($del['code'] === 'sent')
                                    <span class="admin-status-badge is-success">
                                        <i class="fas fa-circle-check"></i> 送付済み {{ $del['sent_at'] }}
                                    </span>
                                @elseif($del['code'] === 'unsent')
                                    <span class="admin-status-badge is-warning">
                                        <i class="fas fa-triangle-exclamation"></i> 未送付（要再送）
                                    </span>
                                @elseif($del['code'] === 'no_email')
                                    <span class="admin-status-badge is-info">
                                        <i class="fas fa-envelope"></i> 店舗メール未登録
                                    </span>
                                @endif
                                <span class="admin-note">発行 {{ $d['invoice_issued_at'] ?: '—' }} ／ 支払期限 {{ $d['invoice_due_date'] ?: '—' }}</span>
                            </div>
                        </div>
                        <div class="invoice-pending-card-amount">¥{{ number_format($d['invoice_amount'] ?? 0) }}</div>
                        <div class="invoice-pending-card-actions" style="display:flex; gap:8px; flex-wrap:wrap;">
                            <a href="{{ route('admin.deposits.invoice.show', $d['id']) }}" class="btn-action btn-action-secondary" target="_blank" rel="noopener">
                                <i class="fas fa-file-invoice"></i> 請求書
                            </a>
                            @if(in_array($del['code'], ['unsent', 'sent'], true))
                                <form method="POST" action="{{ route('admin.deposits.invoice.resend', $d['id']) }}" style="margin:0;">
                                    @csrf
                                    <button type="submit" class="btn-action {{ $del['code'] === 'unsent' ? 'manage' : 'btn-action-secondary' }}">
                                        <i class="fas fa-paper-plane"></i> {{ $del['code'] === 'sent' ? 'メール再送' : 'メール送信' }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
        @endif

        {{-- 運営口座 --}}
        <section class="admin-panel invoice-admin-bank">
            <div class="billing-detail-row u-mb-12">
                <h2 class="admin-panel-title u-mb-0">運営口座</h2>
                <a href="{{ route('admin.bank.index') }}" class="btn-action btn-action-secondary">
                    <i class="fas fa-university"></i> 口座登録・編集
                </a>
            </div>
            <p class="admin-note">請求書に印字される振込先です。発行前に内容を確認してください。</p>
            @if($adminBank)
                <div class="billing-meta-list">
                    <div class="billing-meta-item">
                        <div class="billing-meta-label">金融機関</div>
                        <div class="billing-meta-value">{{ $adminBank->bank_name }}</div>
                    </div>
                    <div class="billing-meta-item">
                        <div class="billing-meta-label">支店名</div>
                        <div class="billing-meta-value">{{ $adminBank->branch_name ?: '未設定' }}</div>
                    </div>
                    <div class="billing-meta-item">
                        <div class="billing-meta-label">口座種別 / 口座番号</div>
                        <div class="billing-meta-value">{{ in_array($adminBank->account_type, ['current', 'checking'], true) ? '当座' : '普通' }} / {{ $adminBank->account_number }}</div>
                    </div>
                    <div class="billing-meta-item">
                        <div class="billing-meta-label">口座名義</div>
                        <div class="billing-meta-value">{{ $adminBank->account_name }}</div>
                    </div>
                </div>
            @else
                <div class="admin-alert admin-alert-warning u-mt-12">
                    <strong>運営口座が未登録です。</strong> 請求書発行前に上の「口座登録・編集」から登録してください。
                </div>
            @endif
        </section>

        {{-- 帳票テンプレート（折りたたみ） --}}
        <details class="admin-accordion">
            <summary class="admin-accordion-summary">
                <div class="admin-accordion-title">
                    <span class="admin-accordion-title-main"><i class="fas fa-file-pdf"></i> 帳票テンプレート設定・プレビュー</span>
                    <span class="admin-accordion-title-sub">発行元名・ロゴ・備考文の設定とテンプレート PDF のダウンロード</span>
                </div>
            </summary>
            <div class="admin-accordion-body">
                <p class="admin-note u-mb-12">
                    発行元名・ロゴ・備考文は「テンプレートを設定」で変更できます。ダウンロードする帳票はレイアウト確認用で、金額・宛先・日付の数字は含みません。
                </p>
                <div class="invoice-issue-hero-actions">
                    <a href="{{ route('admin.invoices.template-settings') }}" class="btn-action">
                        <i class="fas fa-cog"></i> テンプレートを設定
                    </a>
                    <a href="{{ route('admin.deposits.invoice-template.download') }}" class="invoice-template-dl" target="_blank" rel="noopener">
                        <i class="fas fa-file-pdf"></i> テンプレートを PDF プレビュー
                    </a>
                </div>
            </div>
        </details>

        {{-- 手動発行（折りたたみ・警告強化） --}}
        <details class="admin-accordion">
            <summary class="admin-accordion-summary">
                <div class="admin-accordion-title">
                    <span class="admin-accordion-title-main"><i class="fas fa-triangle-exclamation"></i> 手動で請求書を発行（回避策）</span>
                    <span class="admin-accordion-title-sub">障害時のみ使用。通常は上の一覧から発行してください</span>
                </div>
            </summary>
            <div class="admin-accordion-body">
                <div class="invoice-manual-warning">
                    <strong>注意事項（回避策としての利用に限定してください）</strong><br>
                    通常は上の「入金依頼・請求書発行」一覧からプレビュー経由で発行してください。<br>
                    システム不具合や運用上の理由で、ステータスが「入金依頼確認済み」でない場合に限り、ここから宛先（入金申請）を指定して手動で請求書を発行できます。<br>
                    誤用するとフローと実態がずれるため、必要な場合のみご利用ください。
                </div>
                @if(!empty($manualTargets))
                    @include('admin.invoices.partials.manual-issue-form', ['manualTargets' => $manualTargets])
                @else
                    <p class="invoice-empty-note">手動発行の対象（請求書未発行の入金申請）はありません。</p>
                @endif
            </div>
        </details>
    </div>
@endsection

@push('admin-scripts')
<script>
// =========================================================
// 請求書発行一覧: KPIタブ + 検索 + ソート
// =========================================================
document.addEventListener('DOMContentLoaded', function () {
    var host = document.querySelector('[data-invoice-filter-host]');
    var list = document.querySelector('[data-invoice-list]');
    var toolbar = document.querySelector('[data-invoice-toolbar]');
    if (!host || !list || !toolbar) return;

    var filterButtons = host.querySelectorAll('[data-filter]');
    var searchInput = toolbar.querySelector('[data-invoice-search]');
    var sortSelect = toolbar.querySelector('[data-invoice-sort]');
    var hitsEl = toolbar.querySelector('[data-invoice-hits]');
    var emptyEl = document.querySelector('[data-invoice-empty]');
    var cards = Array.prototype.slice.call(list.querySelectorAll('[data-invoice-card]'));

    var state = { filter: 'all', search: '', sort: 'age_desc' };

    function applySort() {
        var sorted = cards.slice().sort(function (a, b) {
            var av, bv;
            switch (state.sort) {
                case 'age_asc':    av = +a.dataset.age; bv = +b.dataset.age; return av - bv;
                case 'age_desc':   av = +a.dataset.age; bv = +b.dataset.age; return bv - av;
                case 'amount_desc':av = +a.dataset.amount; bv = +b.dataset.amount; return bv - av;
                case 'amount_asc': av = +a.dataset.amount; bv = +b.dataset.amount; return av - bv;
            }
            return 0;
        });
        sorted.forEach(function (el) { list.appendChild(el); });
    }

    function matches(card) {
        if (state.filter === 'overdue' && card.dataset.overdue !== '1') return false;
        if (state.filter !== 'all' && state.filter !== 'overdue' && card.dataset.status !== state.filter) return false;
        if (state.search) {
            var q = state.search.toLowerCase();
            if ((card.dataset.search || '').indexOf(q) === -1) return false;
        }
        return true;
    }

    function refresh() {
        var visible = 0;
        cards.forEach(function (card) {
            var show = matches(card);
            card.hidden = !show;
            if (show) visible++;
        });
        if (hitsEl) {
            hitsEl.textContent = visible + ' 件表示中';
        }
        if (emptyEl) {
            emptyEl.hidden = visible !== 0;
        }
    }

    filterButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            state.filter = btn.dataset.filter || 'all';
            filterButtons.forEach(function (b) {
                var on = b === btn;
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            refresh();
        });
    });
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            state.search = searchInput.value.trim();
            refresh();
        });
    }
    if (sortSelect) {
        sortSelect.addEventListener('change', function () {
            state.sort = sortSelect.value;
            applySort();
        });
    }

    applySort();
    refresh();
});

document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('form-manual-invoice');
    if (!form) return;
    var submit = form.querySelector('[data-check-submit]');
    var items = form.querySelectorAll('[data-check-item]');
    var select = document.getElementById('manual-deposit-select');
    var bonus = document.getElementById('manual_bonus');
    var system = document.getElementById('manual_system');
    var invoiceTotal = document.getElementById('manual_invoice_total');

    function syncSubmit() {
        if (!submit || !items.length) return;
        submit.disabled = Array.from(items).some(function (el) { return !el.checked; });
    }
    items.forEach(function (el) { el.addEventListener('change', syncSubmit); });
    syncSubmit();

    function syncInvoiceTotalFromParts() {
        if (!bonus || !system || !invoiceTotal) return;
        var b = parseInt(bonus.value, 10) || 0;
        var s = parseInt(system.value, 10) || 0;
        invoiceTotal.value = String(b + s);
    }
    if (bonus) bonus.addEventListener('input', syncInvoiceTotalFromParts);
    if (system) system.addEventListener('input', syncInvoiceTotalFromParts);

    function fillFromDepositOption(opt) {
        if (!opt || !opt.value) return;
        var sn = document.getElementById('manual_shop_name');
        var sa = document.getElementById('manual_shop_address');
        var se = document.getElementById('manual_shop_email');
        var cn = document.getElementById('manual_cast_name');
        var ct = document.getElementById('manual_cast_transfer');
        if (sn) sn.value = opt.dataset.shopName || '';
        if (sa) sa.value = opt.dataset.shopAddress || '';
        if (se) se.value = opt.dataset.shopEmail || '';
        if (cn) cn.value = opt.dataset.castName || '';
        if (bonus) bonus.value = opt.dataset.bonus || '0';
        if (system) system.value = opt.dataset.system || '0';
        if (invoiceTotal) invoiceTotal.value = opt.dataset.invoice || '0';
        if (ct) ct.value = opt.dataset.castTransfer || '0';
    }

    if (select) {
        select.addEventListener('change', function () {
            fillFromDepositOption(select.options[select.selectedIndex]);
        });
    }
});
</script>
@endpush
