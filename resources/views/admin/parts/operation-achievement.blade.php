{{-- オペレーション各画面用：実績（累計件数）。コンパクトなピル表示。
     使い方: @include('admin.parts.operation-achievement', ['operationAchievementRoute' => 'admin.invoices.index'])
--}}
@php
    $key = $operationAchievementRoute ?? '';
    $counts = $adminOperationAchievements ?? [];
    $n = (int) ($counts[$key] ?? 0);
    $tooltips = [
        'admin.invoices.index' => '請求書番号が登録済みの入金申請の累計件数',
        'admin.deposits.index' => 'ステータスが「完了」となった案件の累計件数',
        'admin.verification.index' => '承認または却下まで完了した本人確認・書類の累計件数',
        'admin.support-inquiries.index' => '対応済み・クローズとした問合せの累計件数',
    ];
@endphp
@if ($key !== '')
    <span class="admin-achievement-pill" title="{{ $tooltips[$key] ?? '' }}">
        <i class="fas fa-circle-check" aria-hidden="true"></i>
        実績 <strong>{{ number_format($n) }}</strong> 件
    </span>
@endif
