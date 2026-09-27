@extends('layouts.admin')

@section('title', '問合せ対応')

@section('content')
<div class="admin-page">
    @include('admin.parts.page-title', [
        'eyebrow' => 'SUPPORT',
        'title' => '問合せ対応',
        'info' => '
            <p>未対応の問い合わせを、発生日が古い順（経過日数の長い順）に表示します。</p>
            <p>詳細画面でステータスを更新すると、この一覧から自動的に外れます。</p>
        ',
    ])

    @if(session('status'))
        <div class="admin-alert admin-alert-success">{{ session('status') }}</div>
    @endif

    <div class="admin-inline-stat" aria-live="polite">
        <span class="admin-inline-stat__label">要対応</span>
        <strong class="admin-inline-stat__value">{{ number_format($pendingCount) }}</strong>
        <span class="admin-inline-stat__unit">件</span>
    </div>

    <div class="table-wrapper">
        <table class="admin-table admin-table--stack">
            <thead>
                <tr>
                    <th>経過</th>
                    <th>受付日時</th>
                    <th>送信者（ID）</th>
                    <th>カテゴリ</th>
                    <th>本文（抜粋）</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($inquiries as $inquiry)
                    @php
                        $createdAt = $inquiry->created_at;
                        $days = $createdAt ? (int) $createdAt->diffInDays(now()) : null;
                        $ageTone = $days === null ? '' : ($days >= 7 ? 'is-critical' : ($days >= 3 ? 'is-warning' : ''));
                        $senderBadge = match ($inquiry->sender_type) {
                            \App\Models\SupportInquiry::SENDER_CAST => '👤 キャスト',
                            \App\Models\SupportInquiry::SENDER_SHOP => '🏪 店舗',
                            default => '👻 ゲスト',
                        };
                    @endphp
                    <tr>
                        <td data-label="経過">
                            <span class="admin-age-pill {{ $ageTone }}">
                                @if($days === null)
                                    —
                                @else
                                    {{ $days }}日
                                @endif
                            </span>
                        </td>
                        <td data-label="受付日時">{{ optional($createdAt)->format('Y-m-d H:i') }}</td>
                        <td data-label="送信者（ID）">
                            <div>{{ $senderBadge }} <span class="admin-table-sub">#{{ $inquiry->id }}</span></div>
                            @if($inquiry->sender_id)
                                <div class="admin-table-sub">{{ $inquiry->sender_id }}</div>
                            @endif
                        </td>
                        <td data-label="カテゴリ">{{ $inquiry->categoryLabel() }}</td>
                        <td data-label="本文（抜粋）" class="admin-table-cell-clip">{{ \Illuminate\Support\Str::limit($inquiry->body, 60) }}</td>
                        <td class="stack-actions">
                            <a href="{{ route('admin.support-inquiries.show', $inquiry->id) }}" class="btn-action">
                                <i class="fas fa-eye"></i> 詳細
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="admin-table-empty">
                            <i class="fas fa-inbox"></i> 未対応の問い合わせはありません。
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($inquiries->hasPages())
        <div class="admin-pagination">
            {{ $inquiries->links() }}
        </div>
    @endif
</div>
@endsection
