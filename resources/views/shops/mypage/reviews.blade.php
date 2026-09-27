@extends('layouts.app-v2')

@section('title', 'Reviews')
@section('body-class', 'page-shop-mypage-reviews')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/review.css') }}?v=20260927-flat">
    <link rel="stylesheet" href="{{ asset('assets/css/mypage.css') }}">
    <style>
        /* Shop reply block (kept as a filled sub-area so it reads distinct from the review text) */
        .shop-review-reply {
            margin: 12px 0 0;
            padding: 12px 14px;
            border-radius: 10px;
            background: rgba(124, 58, 237, 0.05);
            border: 1px solid rgba(124, 58, 237, 0.18);
        }
        .shop-review-reply__head {
            margin: 0 0 6px;
            font-size: 0.78rem; font-weight: 800;
            color: #6d28d9;
            display: flex; align-items: center; gap: 8px;
        }
        .shop-review-reply__head i { color: #7c3aed; }
        .shop-review-reply__date {
            margin-left: auto;
            font-size: 0.7rem; font-weight: 500;
            color: #8b84a1;
        }
        .shop-review-reply__body {
            font-size: 0.86rem; color: #4a4560; line-height: 1.65;
            white-space: pre-wrap;
            margin: 0 0 8px;
        }
        .shop-review-reply__toggle {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 14px; border-radius: 8px;
            background: rgba(124, 58, 237, 0.10);
            color: #7c3aed;
            border: 1px solid rgba(124, 58, 237, 0.30);
            font-size: 0.82rem; font-weight: 700;
            cursor: pointer;
        }
        .shop-review-reply__toggle:hover {
            background: rgba(124, 58, 237, 0.18);
        }
        .shop-review-reply__form textarea {
            width: 100%; box-sizing: border-box;
            padding: 10px 12px; border-radius: 10px;
            border: 1px solid rgba(124, 58, 237, 0.24);
            background: #fff; color: #1e1a30;
            font-size: 0.9rem; font-family: inherit;
            resize: vertical; min-height: 80px; line-height: 1.6;
        }
        .shop-review-reply__form textarea:focus {
            outline: none; border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.14);
        }
        .shop-review-reply__actions {
            display: flex; gap: 8px; justify-content: flex-end;
            margin-top: 8px;
        }
        .shop-review-reply__btn {
            padding: 7px 14px; border-radius: 8px;
            font-size: 0.8rem; font-weight: 700;
            border: 1px solid transparent; cursor: pointer;
        }
        .shop-review-reply__btn--ghost {
            background: #fff; color: #4a4560;
            border-color: rgba(124, 58, 237, 0.24);
        }
        .shop-review-reply__btn--primary {
            background: linear-gradient(135deg, #a78bfa, #7c3aed);
            color: #fff;
        }
        .shop-review-reply__btn--danger {
            background: rgba(220, 38, 38, 0.10);
            color: #dc2626;
            border-color: rgba(220, 38, 38, 0.30);
        }
        .shop-review-reply__feedback {
            margin: 8px 0 0; padding: 8px 10px; border-radius: 8px;
            font-size: 0.8rem;
        }
        .shop-review-reply__feedback.is-success {
            background: rgba(16, 185, 129, 0.10); color: #047857;
            border: 1px solid rgba(16, 185, 129, 0.32);
        }
        .shop-review-reply__feedback.is-error {
            background: rgba(220, 38, 38, 0.08); color: #b91c1c;
            border: 1px solid rgba(220, 38, 38, 0.32);
        }
    </style>
@endpush

@section('content')
<div class="content-wrapper animate-fadeIn shop-mypage-reviews-page">
    <section class="mypage-area">
        <h1 class="sr-only">Reviews</h1>

        <section class="review-list-area">
            <div class="shop-review-summary">
                <p class="shop-review-summary__label">総合評価</p>
                <div class="shop-review-summary__main">
                    <span class="shop-review-summary__score">{{ number_format((float) $shopData['review_avg'], 1) }}</span>
                    <div class="shop-review-summary__right">
                        @include('partials.review_stars', ['score' => (float) $shopData['review_avg'], 'size' => 'lg'])
                        <p class="shop-review-summary__count">全 <span>{{ $shopData['review_count'] }}</span> 件のレビュー</p>
                    </div>
                </div>
            </div>

            <div class="shop-review-items">
                @if (empty($reviews))
                    <div class="no-data-wrapper text-center py-20 opacity-50 shop-review-items__empty">
                        <p>まだレビューはありません。</p>
                    </div>
                @else
                    @foreach($reviews as $rev)
                        @php
                            $hasDetails = !empty($rev['details']);
                            // MyPage 上では匿名扱いにせず実名（ニックネーム）を表示する
                            $displayName = $rev['user_name'];
                        @endphp
                        <article
                            id="review-{{ $rev['id'] }}"
                            data-cast-id="{{ $rev['cast_id'] ?? '' }}"
                            class="shop-review-item {{ $rev['release'] == 0 ? 'is-hidden' : '' }}"
                            @if($hasDetails) data-shop-review-accordion @endif
                        >
                            <div
                                class="shop-review-item__surface @if($hasDetails) shop-review-item__surface--interactive @endif"
                                @if($hasDetails)
                                    role="button"
                                    tabindex="0"
                                    aria-expanded="false"
                                    aria-controls="shop-review-breakdown-{{ $rev['id'] }}"
                                @endif
                            >
                                <div class="shop-review-item__head">
                                    <div class="shop-review-item__user">
                                        <div class="shop-review-item__avatar-wrap">
                                            <img
                                                src="{{ $rev['user_img'] ?: asset('assets/images/common/user-default.svg') }}"
                                                alt=""
                                                class="shop-review-item__avatar"
                                                width="48"
                                                height="48"
                                                loading="lazy"
                                            >
                                        </div>
                                        <div class="shop-review-item__user-text">
                                            <span class="shop-review-item__name">{{ $displayName }}</span>
                                            <div class="shop-review-item__meta-row">
                                                @include('partials.review_stars', ['score' => (float) $rev['avg_score'], 'size' => 'sm'])
                                                <span class="shop-review-item__meta-score">{{ number_format($rev['avg_score'], 1) }}</span>
                                                @if(!empty($rev['created_at_label']))
                                                    <p class="shop-review-item__date">{{ $rev['created_at_label'] }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                @if ($rev['release'] == 0)
                                    <span class="badge-hidden shop-review-item__badge-hidden">非表示設定中</span>
                                @endif

                                <div class="shop-review-item__comment">{!! nl2br(e($rev['text'])) !!}</div>

                                @if($hasDetails)
                                    <button
                                        type="button"
                                        class="shop-review-item__details-toggle"
                                        aria-expanded="false"
                                        aria-controls="shop-review-breakdown-{{ $rev['id'] }}"
                                    >
                                        <span>設問別スコアを表示</span>
                                        <i class="fas fa-chevron-down shop-review-item__chevron-icon shop-review-item__chevron-icon--down" aria-hidden="true"></i>
                                        <i class="fas fa-chevron-up shop-review-item__chevron-icon shop-review-item__chevron-icon--up" aria-hidden="true"></i>
                                    </button>

                                    <div
                                        id="shop-review-breakdown-{{ $rev['id'] }}"
                                        class="shop-review-item__breakdown"
                                        aria-hidden="true"
                                    >
                                        <div class="shop-review-item__breakdown-inner">
                                            <h3 class="shop-review-item__breakdown-title">詳細評価</h3>
                                            <ul class="shop-review-item__detail-list">
                                                @foreach($rev['details'] as $det)
                                                    @php
                                                        $qLabel = $det['content'] ?? $det['name'] ?? '';
                                                        $qScore = (float) ($det['score'] ?? 0);
                                                    @endphp
                                                    <li class="shop-review-item__detail-row">
                                                        <span class="shop-review-item__detail-label">{{ $qLabel !== '' ? $qLabel : '（設問）' }}</span>
                                                        <span class="shop-review-item__detail-score">
                                                            @include('partials.review_stars', ['score' => $qScore, 'size' => 'sm'])
                                                            <span class="shop-review-item__detail-val">{{ number_format($qScore, 1) }}</span>
                                                        </span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            @if ($isPaidPlan)
                                <div class="shop-review-item__admin">
                                    @if ($rev['release'] == 1)
                                        <button type="button" class="btn-hide" onclick="toggleReviewStatus(this, {{ $rev['id'] }}, 1)">
                                            このレビューを非表示にする<span class="plan-label">※プレミアムプラン</span>
                                        </button>
                                    @else
                                        <button type="button" class="btn-show-again" onclick="toggleReviewStatus(this, {{ $rev['id'] }}, 0)">
                                            このレビューを表示する<span class="plan-label">※プレミアムプラン</span>
                                        </button>
                                    @endif
                                </div>
                            @endif

                            {{-- ===== 店舗からの返信ブロック ===== --}}
                            @shopowner
                            <div class="shop-review-reply" data-review-reply-block data-review-id="{{ $rev['id'] }}">
                                @if(!empty($rev['reply_body']))
                                    <div data-reply-view>
                                        <p class="shop-review-reply__head">
                                            <i class="fas fa-store" aria-hidden="true"></i> 店舗からの返信
                                            @if(!empty($rev['reply_at_label']))
                                                <span class="shop-review-reply__date">{{ $rev['reply_at_label'] }}</span>
                                            @endif
                                        </p>
                                        <div class="shop-review-reply__body" data-reply-view-body>{!! nl2br(e($rev['reply_body'])) !!}</div>
                                        <div class="shop-review-reply__actions">
                                            <button type="button" class="shop-review-reply__btn shop-review-reply__btn--ghost" data-reply-edit>編集</button>
                                            <button type="button" class="shop-review-reply__btn shop-review-reply__btn--danger" data-reply-delete>削除</button>
                                        </div>
                                    </div>
                                @endif

                                <div data-reply-form-wrap {{ !empty($rev['reply_body']) ? 'hidden' : '' }}>
                                    @if(empty($rev['reply_body']))
                                        <button type="button" class="shop-review-reply__toggle" data-reply-open>
                                            <i class="fas fa-reply"></i> このレビューに返信する
                                        </button>
                                    @endif
                                    <form class="shop-review-reply__form" data-reply-form hidden>
                                        <textarea rows="3" maxlength="1000" placeholder="ご来店ありがとうございます..." data-reply-input>{{ $rev['reply_body'] ?? '' }}</textarea>
                                        <div class="shop-review-reply__actions">
                                            <button type="button" class="shop-review-reply__btn shop-review-reply__btn--ghost" data-reply-cancel>キャンセル</button>
                                            <button type="submit" class="shop-review-reply__btn shop-review-reply__btn--primary">返信を投稿</button>
                                        </div>
                                        <p class="shop-review-reply__feedback" data-reply-feedback hidden></p>
                                    </form>
                                </div>
                            </div>
                            @endshopowner
                        </article>
                    @endforeach
                @endif
            </div>
        </section>
    </section>
</div>
@endsection

@push('scripts')
<script>
    /**
     * 「レビュー確認」導線から遷移して来たときの自動スクロール／ハイライト。
     * 承認モーダル（shop.mypage.management）の「レビュー内容を確認する」リンクは
     * `?cast=<cast_id>&highlight=1` を付けて別タブでこの画面を開くため、
     * ここで対象キャストの直近レビュー要素へスクロールし、一時的にハイライトする。
     */
    (function () {
        var params = new URLSearchParams(window.location.search);
        var targetCast = params.get('cast');
        var highlight  = params.get('highlight');
        if (!targetCast) return;
        // reviews は controller 側で id DESC ソート済み。DOM 上で最初にヒットするものが直近。
        var el = document.querySelector('.shop-review-item[data-cast-id="' + CSS.escape(targetCast) + '"]');
        if (!el) return;
        // ヘッダー高さ分オフセットしてスクロール
        var headerH = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--header-height'), 10) || 60;
        var y = el.getBoundingClientRect().top + window.scrollY - headerH - 12;
        window.scrollTo({ top: Math.max(0, y), behavior: 'smooth' });
        if (highlight === '1') {
            el.classList.add('shop-review-item--highlight');
            setTimeout(function () { el.classList.remove('shop-review-item--highlight'); }, 3200);
        }
    })();
</script>
<style>
    /* 「レビュー確認」導線経由で開いた直近レビューをハイライト */
    .shop-review-item.shop-review-item--highlight {
        animation: shop-review-highlight 3s ease-out;
        box-shadow: 0 0 0 2px rgba(168, 85, 247, 0.55), 0 12px 32px rgba(124, 58, 237, 0.22);
        border-radius: 12px;
    }
    @keyframes shop-review-highlight {
        0%, 60% { background-color: rgba(196, 181, 253, 0.22); }
        100%    { background-color: transparent; }
    }
</style>
<script>
    (function () {
        document.querySelectorAll('[data-shop-review-accordion]').forEach(function (article) {
            var surface = article.querySelector('.shop-review-item__surface--interactive');
            var panel = article.querySelector('.shop-review-item__breakdown');
            var toggleBtn = article.querySelector('.shop-review-item__details-toggle');
            if (!surface || !panel || !toggleBtn) return;

            function toggle() {
                var open = article.classList.toggle('is-open');
                surface.setAttribute('aria-expanded', open ? 'true' : 'false');
                panel.setAttribute('aria-hidden', open ? 'false' : 'true');
                toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            }

            surface.addEventListener('click', toggle);
            surface.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggle();
                }
            });
            toggleBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                toggle();
            });
        });
    })();

    function toggleReviewStatus(btn, id, currentRelease) {
        const nextRelease = currentRelease === 1 ? 0 : 1;
        const actionText = nextRelease === 0 ? '非表示に' : '表示に';

        if (!confirm(`このレビューを${actionText}しますか？`)) return;

        fetch('{{ route("shop.review.update") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ id: id, release: nextRelease })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                (window.appToast || window.alert)('更新に失敗しました', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            (window.appToast || window.alert)('通信エラーが発生しました', 'error');
        });
    }

    // ===== 店舗からの返信 CRUD =====
    (function () {
        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
        var endpoint = '{{ route("shop.review.reply") }}';

        document.querySelectorAll('[data-review-reply-block]').forEach(function (block) {
            var reviewId = block.getAttribute('data-review-id');
            var openBtn = block.querySelector('[data-reply-open]');
            var editBtn = block.querySelector('[data-reply-edit]');
            var deleteBtn = block.querySelector('[data-reply-delete]');
            var cancelBtn = block.querySelector('[data-reply-cancel]');
            var form = block.querySelector('[data-reply-form]');
            var input = block.querySelector('[data-reply-input]');
            var viewEl = block.querySelector('[data-reply-view]');
            var wrapEl = block.querySelector('[data-reply-form-wrap]');
            var feedback = block.querySelector('[data-reply-feedback]');

            function setFeedback(kind, text) {
                if (!feedback) return;
                if (!kind) { feedback.hidden = true; feedback.className = 'shop-review-reply__feedback'; return; }
                feedback.className = 'shop-review-reply__feedback is-' + kind;
                feedback.textContent = text;
                feedback.hidden = false;
            }
            function showForm() {
                if (openBtn) openBtn.hidden = true;
                if (form) form.hidden = false;
                if (input) input.focus();
            }
            function hideForm() {
                if (form) form.hidden = true;
                if (openBtn) openBtn.hidden = false;
                setFeedback(null);
            }

            if (openBtn) openBtn.addEventListener('click', showForm);
            if (cancelBtn) cancelBtn.addEventListener('click', hideForm);
            if (editBtn) editBtn.addEventListener('click', function () {
                if (viewEl) viewEl.hidden = true;
                if (wrapEl) wrapEl.hidden = false;
                if (form) form.hidden = false;
                if (input) input.focus();
            });
            if (deleteBtn) deleteBtn.addEventListener('click', function () {
                if (!confirm('この返信を削除しますか？')) return;
                submitReply('');
            });

            function submitReply(body) {
                fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ id: parseInt(reviewId, 10), reply_body: body }),
                })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
                .then(function (res) {
                    if (res.ok && res.body && res.body.success) {
                        // 成功時はページリロードで最新化（DOM 再構築を省く）
                        window.location.reload();
                    } else {
                        setFeedback('error', (res.body && res.body.message) || '返信の保存に失敗しました。');
                    }
                })
                .catch(function () {
                    setFeedback('error', '通信エラーで返信を保存できませんでした。');
                });
            }

            if (form) form.addEventListener('submit', function (e) {
                e.preventDefault();
                setFeedback(null);
                var body = (input.value || '').trim();
                if (body === '') { setFeedback('error', '返信本文を入力してください。'); return; }
                submitReply(body);
            });
        });
    })();
</script>
@endpush
