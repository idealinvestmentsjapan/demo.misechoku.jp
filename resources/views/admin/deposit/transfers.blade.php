@extends('layouts.admin')

@section('title', 'キャスト振込')
@section('admin_page_title', 'キャスト振込')

@section('content')
@php
    use App\Services\BillingManagementService as BMS;

    // Task status labels for payment_tasks:
    //   1 = 支払準備中 (WAITING)  … START ロック前
    //   2 = 振込中 (IN_PROGRESS) … START済み、支払完了前
    //   3 = 支払済 (COMPLETED)   … 完了
    //   4 = 無効 (INVALID)       … 口座誤りなど
    $taskLabel = function (?int $ts) {
        return match ($ts) {
            1 => ['label' => '振込待ち', 'cls' => 'is-admin', 'icon' => 'fa-hourglass-half'],
            2 => ['label' => '振込中',   'cls' => 'is-admin', 'icon' => 'fa-spinner'],
            3 => ['label' => '支払済',   'cls' => 'is-done', 'icon' => 'fa-circle-check'],
            4 => ['label' => 'タスク無効', 'cls' => 'is-admin-soft', 'icon' => 'fa-ban'],
            default => ['label' => '未設定', 'cls' => 'is-admin-soft', 'icon' => 'fa-circle-question'],
        };
    };
    $stateBadge = function (int $sc, ?object $task) use ($taskLabel) {
        if ($sc === BMS::STATUS_SHOP_PAYMENT_CONFIRMED) {
            return $taskLabel($task ? (int) $task->status : null);
        }
        if ($sc === BMS::STATUS_CAST_TRANSFERRED) {
            return ['label' => 'キャスト確認待ち', 'cls' => 'is-cast', 'icon' => 'fa-user'];
        }
        if ($sc >= BMS::STATUS_COMPLETED) {
            return ['label' => '完了', 'cls' => 'is-done', 'icon' => 'fa-circle-check'];
        }
        return ['label' => '確認中', 'cls' => 'is-admin-soft', 'icon' => 'fa-circle-question'];
    };
@endphp

<div class="admin-page">
    <div class="u-flex-between">
        @include('admin.parts.page-title', [
            'eyebrow' => 'CAST TRANSFERS',
            'title' => 'キャスト振込',
            'info' => '
                <p><strong>この画面の役割：</strong>店舗入金の照合が完了した案件について、キャストへの振込を実行・記録します。</p>
                <p>入金照合前の案件は「<strong>入金確認</strong>」画面に表示されます。</p>
                <p>振込作業は<strong>ネットバンキング画面のスクリーンショット</strong>と<strong>チェックリスト</strong>で証跡を残します。</p>
            ',
        ])
        @include('admin.parts.operation-achievement', ['operationAchievementRoute' => 'admin.deposits.transfers'])
    </div>

    @if(session('status'))
        <div class="admin-alert admin-alert-success">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="admin-alert admin-alert-error">{{ session('error') }}</div>
    @endif
    @if(!empty($summary['unconfirmed_cast_over_7days']))
        <div class="admin-alert admin-alert-error">
            <strong>要確認：</strong> 振込済みのうち、キャストの入金確認が7日以上ない案件が {{ $summary['unconfirmed_cast_over_7days'] }} 件あります。個別フォローを推奨します。
        </div>
    @endif
    @if($errors->any())
        <div class="admin-alert admin-alert-error">{{ $errors->first() }}</div>
    @endif

    {{-- KPI（表示のみ） --}}
    <section class="dashboard-kpi-grid deposit-kpi-grid">
        <article class="dashboard-kpi-card {{ ($summary['cast_transfer_pending'] ?? 0) > 0 ? 'is-attention' : '' }}">
            <div class="dashboard-kpi-head">
                <div class="dashboard-kpi-title">振込待ち</div>
                <i class="fas fa-paper-plane"></i>
            </div>
            <div class="dashboard-kpi-main">
                <span class="dashboard-kpi-value">{{ number_format($summary['cast_transfer_pending'] ?? 0) }}</span>
                <span class="dashboard-kpi-unit">件</span>
            </div>
        </article>
        <article class="dashboard-kpi-card">
            <div class="dashboard-kpi-head">
                <div class="dashboard-kpi-title">キャスト確認待ち</div>
                <i class="fas fa-user-clock"></i>
            </div>
            <div class="dashboard-kpi-main">
                <span class="dashboard-kpi-value">{{ number_format($summary['in_transit'] ?? 0) }}</span>
                <span class="dashboard-kpi-unit">件</span>
            </div>
        </article>
        <article class="dashboard-kpi-card {{ ($summary['unconfirmed_cast_over_7days'] ?? 0) > 0 ? 'is-critical' : '' }}">
            <div class="dashboard-kpi-head">
                <div class="dashboard-kpi-title">要確認（7日）</div>
                <i class="fas fa-triangle-exclamation"></i>
            </div>
            <div class="dashboard-kpi-main">
                <span class="dashboard-kpi-value">{{ number_format($summary['unconfirmed_cast_over_7days'] ?? 0) }}</span>
                <span class="dashboard-kpi-unit">件</span>
            </div>
        </article>
        <article class="dashboard-kpi-card">
            <div class="dashboard-kpi-head">
                <div class="dashboard-kpi-title">完了</div>
                <i class="fas fa-circle-check"></i>
            </div>
            <div class="dashboard-kpi-main">
                <span class="dashboard-kpi-value">{{ number_format($summary['completed_recent'] ?? 0) }}</span>
                <span class="dashboard-kpi-unit">件</span>
            </div>
        </article>
    </section>

    {{-- フィルタ：運営対応のみ / すべて --}}
    <div class="admin-page-toolbar-filters" data-deposit-filters>
        <button type="button" class="admin-filter-chip is-active" data-deposit-filter="transfer_pending">
            <span>運営対応の要対応のみ</span>
            <strong>{{ number_format($summary['cast_transfer_pending'] ?? 0) }}</strong>
        </button>
        <button type="button" class="admin-filter-chip" data-deposit-filter="all">
            <span>すべて表示</span>
        </button>
    </div>

    {{-- Compact list --}}
    <section class="admin-panel">
        <h2 class="admin-panel-title">振込案件一覧</h2>
        <p class="admin-note u-mb-12">行をタップすると詳細ウィンドウが開きます。振込作業もそこから行います。</p>

        @forelse($deposits as $deposit)
            @php
                $sc = (int) $deposit['status_code'];
                $task = $deposit['payment_task'] ?? null;
                $taskStatus = $task ? (int) $task->status : null;
                $badge = $stateBadge($sc, $task);
                $isUnconfirmedOver7 = $sc === BMS::STATUS_CAST_TRANSFERRED && !empty($deposit['cast_transferred_at'])
                    && \Carbon\Carbon::parse($deposit['cast_transferred_at'])->lt(now()->subDays(7));

                if ($isUnconfirmedOver7) {
                    $filter = 'alert';
                } elseif ($sc === BMS::STATUS_SHOP_PAYMENT_CONFIRMED) {
                    $filter = ($taskStatus === 3) ? 'in_transit' : 'transfer_pending';
                } elseif ($sc === BMS::STATUS_CAST_TRANSFERRED) {
                    $filter = 'in_transit';
                } else {
                    $filter = 'completed';
                }
                $modalId = 'transfer-modal-' . $deposit['id'];
                $castBank = $deposit['cast_bank'] ?? ['exists' => false];
            @endphp
            <div class="deposit-row {{ $isUnconfirmedOver7 ? 'is-alert' : '' }}" data-deposit-row data-deposit-cat="{{ $filter }}"
                 role="button" tabindex="0" data-open-modal="{{ $modalId }}">
                <div class="deposit-row__col deposit-row__col-id">#{{ $deposit['id'] }}</div>
                <div class="deposit-row__col deposit-row__col-name">
                    <div class="deposit-row__shop">{{ $deposit['shop_name'] }}</div>
                    <div class="deposit-row__cast">{{ $deposit['cast_name'] }}</div>
                </div>
                <div class="deposit-row__col deposit-row__col-badge">
                    <span class="actor-pill {{ $badge['cls'] }}">
                        <i class="fas {{ $badge['icon'] }}"></i> {{ $badge['label'] }}
                    </span>
                    @if($isUnconfirmedOver7)
                        <span class="deposit-row__days is-critical">
                            <i class="fas fa-triangle-exclamation"></i> 7日以上未確認
                        </span>
                    @endif
                </div>
                <div class="deposit-row__col deposit-row__col-amount">
                    ¥{{ number_format((int) ($task->payout_amount ?? $deposit['cast_transfer_amount'])) }}
                    <div class="deposit-row__amount-sub">キャスト振込額</div>
                </div>
                <div class="deposit-row__col deposit-row__col-cta">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </div>

            {{-- Modal --}}
            <dialog class="ops-modal ops-modal--transfer" id="{{ $modalId }}" aria-labelledby="{{ $modalId }}-title" data-ops-modal>
                <form method="dialog" class="ops-modal__close-form">
                    <button type="submit" class="ops-modal__close" aria-label="閉じる">
                        <i class="fas fa-xmark"></i>
                    </button>
                </form>
                <header class="ops-modal__head">
                    <div>
                        <div class="ops-modal__eyebrow">キャスト振込 #{{ $deposit['id'] }}</div>
                        <h3 id="{{ $modalId }}-title" class="ops-modal__title">
                            {{ $deposit['shop_name'] }} / {{ $deposit['cast_name'] }}
                        </h3>
                    </div>
                    <span class="actor-pill {{ $badge['cls'] }}"><i class="fas {{ $badge['icon'] }}"></i> {{ $badge['label'] }}</span>
                </header>

                <div class="ops-modal__body">
                    {{-- 参考情報 --}}
                    <section class="ops-ref">
                        <div class="ops-ref__label">
                            <i class="fas fa-circle-info"></i> 参考情報（見るだけ・入力しません）
                        </div>
                        <dl class="ops-ref__grid">
                            <div class="ops-ref__row">
                                <dt>請求番号</dt>
                                <dd>{{ $deposit['invoice_number'] ?: '—' }}</dd>
                            </div>
                            <div class="ops-ref__row">
                                <dt>店舗入金額（照合済み）</dt>
                                <dd>¥{{ number_format((int) ($task->shop_received_amount ?? $deposit['invoice_amount'])) }}</dd>
                            </div>
                            <div class="ops-ref__row">
                                <dt>プラットフォーム手数料</dt>
                                <dd>¥{{ number_format((int) ($task->platform_fee_amount ?? $deposit['system_fee_amount'])) }}</dd>
                            </div>
                            <div class="ops-ref__row">
                                <dt>銀行振込手数料</dt>
                                <dd>¥{{ number_format((int) ($task->bank_fee_amount ?? 220)) }}</dd>
                            </div>
                            <div class="ops-ref__row">
                                <dt>店舗入金確認日時</dt>
                                <dd>{{ $deposit['shop_payment_confirmed_at'] ?: '—' }}</dd>
                            </div>
                        </dl>
                    </section>

                    @if($sc === BMS::STATUS_SHOP_PAYMENT_CONFIRMED && $task && in_array($taskStatus, [1, 2], true))
                        {{-- 振込先情報（コピーして銀行に入力する情報）--}}
                        <section class="ops-input ops-input--copy">
                            <div class="ops-input__label">
                                <i class="fas fa-copy"></i> 振込入力データ（コピーしてネットバンキングへ）
                            </div>
                            <div class="ops-copy-list">
                                <div class="ops-copy-row">
                                    <div class="ops-copy-row__label">キャスト振込額</div>
                                    <div class="ops-copy-row__value">¥{{ number_format((int) $task->payout_amount) }}</div>
                                    <button type="button" class="ops-copy-btn" data-copy-target="{{ (int) $task->payout_amount }}">
                                        <i class="fas fa-copy"></i> コピー
                                    </button>
                                </div>
                                @if(!empty($castBank['exists']))
                                    <div class="ops-copy-row">
                                        <div class="ops-copy-row__label">金融機関</div>
                                        <div class="ops-copy-row__value">{{ $castBank['bank_name'] }} {{ $castBank['bank_code'] ? '(' . $castBank['bank_code'] . ')' : '' }}</div>
                                        <button type="button" class="ops-copy-btn" data-copy-target="{{ $castBank['bank_name'] }}">
                                            <i class="fas fa-copy"></i> コピー
                                        </button>
                                    </div>
                                    <div class="ops-copy-row">
                                        <div class="ops-copy-row__label">支店名</div>
                                        <div class="ops-copy-row__value">{{ $castBank['branch_name'] }} {{ $castBank['branch_code'] ? '(' . $castBank['branch_code'] . ')' : '' }}</div>
                                        <button type="button" class="ops-copy-btn" data-copy-target="{{ $castBank['branch_name'] }}">
                                            <i class="fas fa-copy"></i> コピー
                                        </button>
                                    </div>
                                    <div class="ops-copy-row">
                                        <div class="ops-copy-row__label">口座種別 / 口座番号</div>
                                        <div class="ops-copy-row__value">{{ $castBank['account_type_label'] }} / {{ $castBank['account_number'] }}</div>
                                        <button type="button" class="ops-copy-btn" data-copy-target="{{ $castBank['account_number'] }}">
                                            <i class="fas fa-copy"></i> 口座番号コピー
                                        </button>
                                    </div>
                                    <div class="ops-copy-row">
                                        <div class="ops-copy-row__label">口座名義</div>
                                        <div class="ops-copy-row__value">{{ $castBank['account_holder_name'] ?: $castBank['account_name'] }}</div>
                                        <button type="button" class="ops-copy-btn" data-copy-target="{{ $castBank['account_holder_name'] ?: $castBank['account_name'] }}">
                                            <i class="fas fa-copy"></i> コピー
                                        </button>
                                    </div>
                                @else
                                    <div class="ops-copy-row is-warning">
                                        <div class="ops-copy-row__label">キャスト口座</div>
                                        <div class="ops-copy-row__value">未登録 — キャスト側で口座登録が必要です</div>
                                    </div>
                                @endif
                            </div>
                        </section>

                        @if($taskStatus === 1)
                            {{-- タスク開始 --}}
                            <section class="ops-input">
                                <div class="ops-input__label">
                                    <i class="fas fa-lock"></i> ① 振込ロック
                                </div>
                                <p class="admin-note">ネットバンキングで振込を実行する前に、他の担当者と同時作業しないようロックしてください。</p>
                                <form method="POST" action="{{ route('admin.deposits.transfer-start', $deposit['id']) }}">
                                    @csrf
                                    <div class="management-actions">
                                        <button type="submit" class="btn-action manage">
                                            <i class="fas fa-lock"></i> 振込チェック開始
                                        </button>
                                    </div>
                                </form>
                                <form method="POST" action="{{ route('admin.deposits.payment-task.invalidate', $deposit['id']) }}"
                                      style="margin-top:10px;" onsubmit="return confirm('振込タスクを無効にしますか？口座修正後は別タスクで再発行してください。');">
                                    @csrf
                                    <button type="submit" class="btn-action danger">
                                        <i class="fas fa-ban"></i> 振込タスクを無効にする（組戻し・口座誤り時）
                                    </button>
                                </form>
                            </section>
                        @elseif($taskStatus === 2)
                            {{-- 振込完了フォーム --}}
                            <section class="ops-input">
                                <div class="ops-input__label">
                                    <i class="fas fa-pen-to-square"></i> ② 振込完了の入力（この画面で入力）
                                </div>
                                <form method="POST" action="{{ route('admin.deposits.transfer-complete', $deposit['id']) }}"
                                      enctype="multipart/form-data" data-transfer-complete-form>
                                    @csrf
                                    <div class="admin-form-row">
                                        <label class="admin-label">振込作業完了日時 <span class="required">必須</span></label>
                                        <input type="datetime-local" name="transferred_at" class="admin-input"
                                               value="{{ now()->format('Y-m-d\\TH:i') }}" required>
                                    </div>
                                    <div class="admin-form-row">
                                        <label class="admin-label">振込管理番号</label>
                                        <input type="text" name="reference" class="admin-input" placeholder="TRF-20260313-01">
                                    </div>
                                    <div class="admin-form-row">
                                        <label class="admin-label">振込完了画面のスクリーンショット <span class="required">必須</span></label>
                                        <input type="file" name="evidence_screenshot" accept="image/*" class="admin-input" data-evidence-file required>
                                    </div>
                                    <div class="billing-check-grid" data-check-group>
                                        <label class="billing-check-item"><input type="checkbox" name="checklist_confirmed_account" value="1" data-check-item> 振込先名義・口座番号が正しいことを確認した</label>
                                        <label class="billing-check-item"><input type="checkbox" name="checklist_confirmed_amount" value="1" data-check-item> 振込金額が正しいことを確認した</label>
                                    </div>
                                    <div class="management-actions">
                                        <button type="submit" class="btn-action manage" data-check-submit disabled data-complete-submit>
                                            <i class="fas fa-yen-sign"></i> 支払済にする
                                        </button>
                                    </div>
                                </form>
                                <form method="POST" action="{{ route('admin.deposits.payment-task.invalidate', $deposit['id']) }}"
                                      style="margin-top:10px;" onsubmit="return confirm('振込タスクを無効にしますか？');">
                                    @csrf
                                    <button type="submit" class="btn-action danger">
                                        <i class="fas fa-ban"></i> 振込タスクを無効にする
                                    </button>
                                </form>
                            </section>
                        @endif
                    @elseif($sc === BMS::STATUS_SHOP_PAYMENT_CONFIRMED && $task && in_array($taskStatus, [3, 4], true))
                        <section class="ops-info-note {{ $taskStatus === 3 ? 'is-success' : '' }}">
                            @if($taskStatus === 3)
                                <div>
                                    <strong>支払済</strong>：この案件はキャストへの振込を完了しました。
                                    @if(!empty($task->completed_at))／ {{ $task->completed_at }}@endif
                                    @if(empty($task->refund_required))
                                        <div style="margin-top:8px;">
                                            <form method="POST" action="{{ route('admin.deposits.payment-task.refund-flag', $deposit['id']) }}"
                                                  onsubmit="return confirm('要返金フラグを立てますか？');">
                                                @csrf
                                                <button type="submit" class="btn-action warning">
                                                    <i class="fas fa-flag"></i> 要返金フラグを立てる
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="admin-status-badge is-warning" style="margin-top:8px;">要返金フラグが立っています</span>
                                    @endif
                                </div>
                            @else
                                <div>この振込タスクは無効です。口座修正後は別タスクで再発行してください。</div>
                            @endif
                        </section>
                    @elseif($sc === BMS::STATUS_SHOP_PAYMENT_CONFIRMED && !$task)
                        {{-- Legacy fallback: no payment_task, manual cast transfer form --}}
                        <section class="ops-input">
                            <div class="ops-input__label">
                                <i class="fas fa-pen-to-square"></i> キャスト振込を記録する（レガシー・タスク未生成）
                            </div>
                            <form method="POST" action="{{ route('admin.deposits.cast-transfer.execute', $deposit['id']) }}"
                                  enctype="multipart/form-data" data-transfer-legacy-form>
                                @csrf
                                <div class="admin-form-row">
                                    <label class="admin-label">振込日時 <span class="required">必須</span></label>
                                    <input type="datetime-local" name="transferred_at" class="admin-input" value="{{ now()->format('Y-m-d\\TH:i') }}" required>
                                </div>
                                <div class="admin-form-row">
                                    <label class="admin-label">振込管理番号</label>
                                    <input type="text" name="reference" class="admin-input" placeholder="TRF-20260313-01">
                                </div>
                                <div class="admin-form-row">
                                    <label class="admin-label">備考</label>
                                    <textarea name="note" class="admin-input" rows="3" placeholder="銀行窓口で実行、受付票確認済み"></textarea>
                                </div>
                                <div class="admin-form-row">
                                    <label class="admin-label">振込完了画面のスクリーンショット <span class="required">必須</span></label>
                                    <input type="file" name="evidence_screenshot" class="admin-input" accept="image/*" data-legacy-evidence-file required>
                                </div>
                                <div class="billing-check-grid" data-check-group>
                                    <label class="billing-check-item"><input type="checkbox" name="confirm_transfer_amount" value="1" data-check-item> 金額を確認した</label>
                                    <label class="billing-check-item"><input type="checkbox" name="confirm_account_name" value="1" data-check-item> 口座名義を確認した</label>
                                    <label class="billing-check-item"><input type="checkbox" name="confirm_transfer_executed" value="1" data-check-item> 銀行で振込を実行した</label>
                                    <label class="billing-check-item"><input type="checkbox" name="confirm_receipt_checked" value="1" data-check-item> 受付票を確認した</label>
                                </div>
                                <div class="management-actions">
                                    <button type="submit" class="btn-action manage" data-check-submit disabled data-legacy-submit>
                                        <i class="fas fa-yen-sign"></i> キャスト振込を記録する
                                    </button>
                                </div>
                            </form>
                        </section>
                    @else
                        <section class="ops-info-note is-success">
                            <div>
                                <strong>{{ $badge['label'] }}</strong>：この案件で今すぐ必要な運営アクションはありません。
                                @if($sc === BMS::STATUS_CAST_TRANSFERRED && !empty($deposit['cast_transferred_at']))
                                    振込完了: {{ $deposit['cast_transferred_at'] }}
                                @endif
                                @if($sc >= BMS::STATUS_COMPLETED && !empty($deposit['completed_at']))
                                    完了: {{ $deposit['completed_at'] }}
                                @endif
                            </div>
                        </section>
                    @endif

                    <div class="ops-modal__meta">
                        <a href="{{ route('admin.deposits.invoice.show', $deposit['id']) }}" target="_blank" rel="noopener" class="ops-modal__meta-link">
                            <i class="fas fa-file-invoice"></i> 請求書を参照
                        </a>
                    </div>
                </div>
            </dialog>
        @empty
            <p class="admin-note">キャスト振込の対象となる案件はありません。</p>
        @endforelse
    </section>
</div>
@endsection

@push('admin-scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ---- Modal open/close ----
    document.querySelectorAll('[data-open-modal]').forEach(function (row) {
        row.addEventListener('click', function (e) {
            // Avoid opening when clicking a button/link within the row (defensive)
            if (e.target.closest('button, a')) return;
            var id = row.getAttribute('data-open-modal');
            var dlg = document.getElementById(id);
            if (dlg && typeof dlg.showModal === 'function') dlg.showModal();
            else if (dlg) dlg.setAttribute('open', '');
        });
        row.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                row.click();
            }
        });
    });
    document.querySelectorAll('[data-ops-modal]').forEach(function (dlg) {
        dlg.addEventListener('click', function (e) {
            var rect = dlg.getBoundingClientRect();
            if (e.clientX < rect.left || e.clientX > rect.right || e.clientY < rect.top || e.clientY > rect.bottom) {
                dlg.close();
            }
        });
    });

    // ---- Copy buttons ----
    document.querySelectorAll('.ops-copy-btn[data-copy-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var value = String(this.getAttribute('data-copy-target') || '');
            var done = function () {
                var t = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> コピーしました';
                setTimeout(function () { btn.innerHTML = t; }, 1500);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(done, done);
            } else {
                var ta = document.createElement('textarea');
                ta.value = value;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                done();
            }
        });
    });

    // ---- transfer-complete form: enable submit only when file + all checks + date ----
    document.querySelectorAll('[data-transfer-complete-form]').forEach(function (form) {
        var submit = form.querySelector('[data-complete-submit]');
        var checks = form.querySelectorAll('[data-check-item]');
        var file = form.querySelector('[data-evidence-file]');
        var date = form.querySelector('input[name="transferred_at"]');
        function sync() {
            var checksOk = checks.length && Array.from(checks).every(function (c) { return c.checked; });
            var hasFile = file && file.files && file.files.length > 0;
            var hasDate = date && date.value.trim() !== '';
            submit.disabled = !(checksOk && hasFile && hasDate);
        }
        checks.forEach(function (c) { c.addEventListener('change', sync); });
        if (file) file.addEventListener('change', sync);
        if (date) date.addEventListener('change', sync);
        sync();
    });

    // ---- legacy transfer form ----
    document.querySelectorAll('[data-transfer-legacy-form]').forEach(function (form) {
        var submit = form.querySelector('[data-legacy-submit]');
        var checks = form.querySelectorAll('[data-check-item]');
        var file = form.querySelector('[data-legacy-evidence-file]');
        function sync() {
            var checksOk = checks.length && Array.from(checks).every(function (c) { return c.checked; });
            var hasFile = file && file.files && file.files.length > 0;
            submit.disabled = !(checksOk && hasFile);
        }
        checks.forEach(function (c) { c.addEventListener('change', sync); });
        if (file) file.addEventListener('change', sync);
        sync();
    });

    // ---- 2択フィルタ（運営対応の要対応のみ / すべて） ----
    var chips = document.querySelectorAll('[data-deposit-filters] [data-deposit-filter]');
    var rows = document.querySelectorAll('[data-deposit-row]');
    function applyFilter(key) {
        rows.forEach(function (r) {
            var cat = r.getAttribute('data-deposit-cat') || '';
            r.style.display = (key === 'all' || cat === key) ? '' : 'none';
        });
    }
    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            var next = chip.getAttribute('data-deposit-filter') || 'transfer_pending';
            chips.forEach(function (c) { c.classList.toggle('is-active', c === chip); });
            applyFilter(next);
        });
    });
    applyFilter('transfer_pending');
});
</script>
@endpush

@push('admin-styles')
<style>
/* Compact row list (shared style with confirmations) */
.deposit-row {
    display: grid;
    grid-template-columns: 60px 1fr auto auto 24px;
    gap: 14px;
    align-items: center;
    padding: 14px 16px;
    border: 1px solid var(--admin-line);
    background: var(--admin-surface);
    border-radius: 10px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
}
.deposit-row:hover { background: var(--admin-surface-alt); border-color: var(--admin-line-strong); }
.deposit-row:focus-visible { outline: 2px solid var(--admin-primary); outline-offset: 2px; }
.deposit-row.is-alert { border-color: rgba(220, 38, 38, 0.4); background: rgba(220, 38, 38, 0.05); }
.deposit-row__col-id { font-family: Menlo, monospace; color: var(--admin-sub); font-size: 0.85rem; }
.deposit-row__shop { font-weight: 700; color: var(--admin-text); }
.deposit-row__cast { font-size: 0.8rem; color: var(--admin-sub); margin-top: 2px; }
.deposit-row__col-badge { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
.deposit-row__days { font-size: 0.72rem; color: var(--admin-sub); }
.deposit-row__days.is-critical { color: #fca5a5; font-weight: 700; }
.deposit-row__col-amount { text-align: right; font-weight: 800; font-variant-numeric: tabular-nums; color: var(--admin-text); }
.deposit-row__amount-sub { font-size: 0.68rem; color: var(--admin-sub); font-weight: 500; margin-top: 2px; }
.deposit-row__col-cta { color: var(--admin-sub); }
@media (max-width: 700px) {
    .deposit-row { grid-template-columns: 1fr auto 24px; row-gap: 6px; }
    .deposit-row__col-id { grid-column: 1 / 2; grid-row: 1; }
    .deposit-row__col-name { grid-column: 1 / 3; grid-row: 2; }
    .deposit-row__col-badge { grid-column: 1 / 3; grid-row: 3; align-items: flex-start; }
    .deposit-row__col-amount { grid-column: 2 / 3; grid-row: 1; }
    .deposit-row__col-cta { grid-column: 3; grid-row: 1 / 4; align-self: center; }
}

/* Modal shell */
.ops-modal {
    padding: 0; border: 0; border-radius: 16px;
    max-width: 760px; width: calc(100vw - 32px);
    background: var(--admin-surface); color: var(--admin-text);
    box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5);
}
.ops-modal--transfer { max-width: 820px; }
.ops-modal::backdrop { background: rgba(0, 0, 0, 0.55); backdrop-filter: blur(2px); }
.ops-modal__close-form { position: absolute; top: 12px; right: 12px; margin: 0; }
.ops-modal__close {
    background: transparent; border: 0; color: var(--admin-sub); font-size: 20px; cursor: pointer;
    width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;
}
.ops-modal__close:hover { background: var(--admin-surface-alt); color: var(--admin-text); }
.ops-modal__head {
    display: flex; justify-content: space-between; align-items: flex-start; gap: 12px;
    padding: 20px 24px 12px; border-bottom: 1px solid var(--admin-line);
}
.ops-modal__eyebrow { font-size: 0.68rem; letter-spacing: 0.14em; color: var(--admin-sub); font-weight: 700; }
.ops-modal__title { font-size: 1.05rem; font-weight: 800; color: var(--admin-text); margin: 4px 0 0; }
.ops-modal__body { padding: 20px 24px; display: flex; flex-direction: column; gap: 18px; }

/* Reference (muted, read-only) */
.ops-ref {
    background: var(--admin-surface-alt);
    border: 1px dashed var(--admin-line);
    border-radius: 12px; padding: 14px 16px;
}
.ops-ref__label {
    font-size: 0.72rem; letter-spacing: 0.06em; font-weight: 700;
    color: var(--admin-sub); margin-bottom: 10px; display: inline-flex; gap: 6px; align-items: center;
}
.ops-ref__grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px 16px; margin: 0; }
.ops-ref__row dt { font-size: 0.7rem; color: var(--admin-sub); margin: 0 0 2px; font-weight: 600; }
.ops-ref__row dd { font-size: 0.9rem; color: var(--admin-text); margin: 0; font-variant-numeric: tabular-nums; }
@media (max-width: 500px) { .ops-ref__grid { grid-template-columns: 1fr; } }

/* Input (prominent, actionable) */
.ops-input {
    background: rgba(168, 85, 247, 0.06);
    border: 1px solid var(--admin-primary-border);
    border-radius: 12px; padding: 14px 16px;
}
.ops-input__label {
    font-size: 0.78rem; letter-spacing: 0.04em; font-weight: 800;
    color: var(--admin-primary); margin-bottom: 12px; display: inline-flex; gap: 6px; align-items: center;
}

/* Copy list (transfer-specific) */
.ops-input--copy .ops-copy-list { display: flex; flex-direction: column; gap: 6px; }
.ops-copy-row {
    display: grid; grid-template-columns: 140px 1fr auto; gap: 12px; align-items: center;
    padding: 8px 12px; border-radius: 8px;
    background: var(--admin-surface);
    border: 1px solid var(--admin-line);
}
.ops-copy-row.is-warning {
    border-color: rgba(220, 38, 38, 0.4);
    background: rgba(220, 38, 38, 0.05);
}
.ops-copy-row__label { font-size: 0.72rem; color: var(--admin-sub); font-weight: 600; }
.ops-copy-row__value { font-size: 0.95rem; color: var(--admin-text); font-weight: 700; font-variant-numeric: tabular-nums; word-break: break-all; }
.ops-copy-btn {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 5px 10px; font-size: 0.78rem; font-weight: 700;
    background: var(--admin-primary); color: var(--admin-ink);
    border: 0; border-radius: 6px; cursor: pointer;
    white-space: nowrap;
}
.ops-copy-btn:hover { background: var(--admin-primary-hover); }
@media (max-width: 500px) {
    .ops-copy-row { grid-template-columns: 1fr; row-gap: 4px; }
    .ops-copy-btn { justify-self: end; }
}

/* Status notes */
.ops-info-note {
    padding: 12px 14px; border-radius: 10px;
    background: rgba(148, 163, 184, 0.10); border: 1px solid var(--admin-line);
}
.ops-info-note.is-success {
    background: rgba(4, 120, 87, 0.10);
    border-color: rgba(4, 120, 87, 0.35);
    color: #86efac;
}

.ops-modal__meta { display: flex; justify-content: flex-end; padding-top: 4px; border-top: 1px dashed var(--admin-line); }
.ops-modal__meta-link { color: var(--admin-sub); font-size: 0.85rem; text-decoration: none; }
.ops-modal__meta-link:hover { color: var(--admin-text); text-decoration: underline; }
</style>
@endpush
