@extends('layouts.app-v2')

@section('title', '通知設定')

@section('content')
<div class="setting-page">
    {{-- タイトルはヘッダー中央、説明はオコジョガイド（character_guide_settings）に集約 --}}

    @if (session('message'))
        <div class="setting-alert setting-alert-success">{{ session('message') }}</div>
    @endif
    @if ($errors->any())
        <div class="setting-alert setting-alert-error">
            @foreach ($errors->all() as $error) {{ $error }} @endforeach
        </div>
    @endif

    @if ($isLoggedIn ?? false)
    <section class="setting-section">
        <h2 class="setting-section-title">ホーム追加（PWA）</h2>
        <div class="setting-row">
            <div class="setting-row-main">
                <div class="setting-row-label">アプリとしてホームに追加</div>
                <div class="setting-row-desc">追加後はブラウザUIなしで起動でき、通知やバッジ表示に対応します。</div>
            </div>
            <button type="button" id="pwa-install-inline-btn" class="setting-btn setting-btn-install" style="display:none;">ホームに追加</button>
        </div>
        <div id="pwa-ios-guide" class="setting-guide" style="display:none;">
            iPhone/iPad は Safari の「共有」→「ホーム画面に追加」からインストールできます。
        </div>
    </section>

    <section class="setting-section">
        <h2 class="setting-section-title">通知の受け取り設定</h2>
        <form method="POST" action="{{ route('setting.notification.update') }}">
            @csrf
            <div class="setting-subheading">通知チャンネル</div>
            <div class="setting-row">
                <div class="setting-row-main">
                    <div class="setting-row-label">通知チャンネル（Push）</div>
                    <div class="setting-row-desc">PWAのプッシュ通知で端末にお知らせします。</div>
                </div>
                <label class="setting-check"><input type="checkbox" name="push_enabled" value="1" {{ !empty($notificationPrefs['push_enabled']) ? 'checked' : '' }}> ON</label>
            </div>
            <div class="setting-row">
                <div class="setting-row-main">
                    <div class="setting-row-label">通知チャンネル（LINE）</div>
                    <div class="setting-row-desc">LINE連携済みアカウントへ同内容の通知を送ります。</div>
                </div>
                <label class="setting-check"><input type="checkbox" name="line_enabled" value="1" {{ !empty($notificationPrefs['line_enabled']) ? 'checked' : '' }}> ON</label>
            </div>

            <div class="setting-subheading">通知カテゴリ</div>
            <div class="setting-guide">OFFにしたカテゴリは端末への通知（Push・LINE）が止まります。アプリ内のおしらせ一覧には残ります。</div>
            <div class="setting-row">
                <div class="setting-row-main">
                    <div class="setting-row-label">トークメッセージ</div>
                    <div class="setting-row-desc">新着メッセージが届いたときに通知します。</div>
                </div>
                <label class="setting-check"><input type="hidden" name="notify_talk_enabled" value="0"><input type="checkbox" name="notify_talk_enabled" value="1" {{ !empty($notificationPrefs['notify_talk_enabled']) ? 'checked' : '' }}> ON</label>
            </div>
            <div class="setting-row">
                <div class="setting-row-main">
                    <div class="setting-row-label">面談・採用</div>
                    <div class="setting-row-desc">面談候補日・日程確定・選考結果・各種報告の通知です。</div>
                </div>
                <label class="setting-check"><input type="hidden" name="notify_selection_enabled" value="0"><input type="checkbox" name="notify_selection_enabled" value="1" {{ !empty($notificationPrefs['notify_selection_enabled']) ? 'checked' : '' }}> ON</label>
            </div>
            <div class="setting-row">
                <div class="setting-row-main">
                    <div class="setting-row-label">請求・入金</div>
                    <div class="setting-row-desc">請求書発行・入金確認・振込実行の通知です。</div>
                </div>
                <label class="setting-check"><input type="hidden" name="notify_billing_enabled" value="0"><input type="checkbox" name="notify_billing_enabled" value="1" {{ !empty($notificationPrefs['notify_billing_enabled']) ? 'checked' : '' }}> ON</label>
            </div>
            <div class="setting-row">
                <div class="setting-row-main">
                    <div class="setting-row-label">書類審査</div>
                    <div class="setting-row-desc">{{ $isCast ? '本人確認書類' : '許可書類' }}の承認・差戻しの通知です。</div>
                </div>
                <label class="setting-check"><input type="hidden" name="notify_verification_enabled" value="0"><input type="checkbox" name="notify_verification_enabled" value="1" {{ !empty($notificationPrefs['notify_verification_enabled']) ? 'checked' : '' }}> ON</label>
            </div>
            @if (!$isCast)
                <div class="setting-row">
                    <div class="setting-row-main">
                        <div class="setting-row-label">レビュー</div>
                        <div class="setting-row-desc">キャストからレビューが投稿されたときに通知します。</div>
                    </div>
                    <label class="setting-check"><input type="hidden" name="notify_review_enabled" value="0"><input type="checkbox" name="notify_review_enabled" value="1" {{ !empty($notificationPrefs['notify_review_enabled']) ? 'checked' : '' }}> ON</label>
                </div>
            @endif

            <div class="setting-subheading">リマインダー通知</div>
            <div class="setting-row">
                <div class="setting-row-main">
                    <div class="setting-row-label">面接リマインダー</div>
                    <div class="setting-row-desc">面接24時間前と3時間前に通知します。</div>
                </div>
                <label class="setting-check"><input type="checkbox" name="interview_reminder_enabled" value="1" {{ !empty($notificationPrefs['interview_reminder_enabled']) ? 'checked' : '' }}> ON</label>
            </div>
            <div class="setting-row">
                <div class="setting-row-main">
                    <div class="setting-row-label">期限リマインダー</div>
                    <div class="setting-row-desc">{{ $isCast ? '採用ボーナス振込の受領確認リマインドを通知します。' : '請求の支払期限（前日/当日/超過）を通知します。' }}</div>
                </div>
                <label class="setting-check"><input type="checkbox" name="deadline_reminder_enabled" value="1" {{ !empty($notificationPrefs['deadline_reminder_enabled']) ? 'checked' : '' }}> ON</label>
            </div>
            <div class="setting-row">
                <div class="setting-row-main">
                    <div class="setting-row-label">Push購読（端末登録）</div>
                    <div class="setting-row-desc">この端末を通知先として登録します。</div>
                </div>
                <button type="button" id="push-enable-btn" class="setting-btn setting-btn-push">通知を有効化</button>
            </div>
            @if (config('demo.enabled') && config('demo.test_push'))
                {{-- ### demo function and data for test ###
                     DEMO_MODE 有効時のみ表示: 自分にテスト Push を送る --}}
                <div class="setting-row">
                    <div class="setting-row-main">
                        <div class="setting-row-label">[デモ] テスト Push を送る</div>
                        <div class="setting-row-desc">上の「通知を有効化」を実施した後、この端末にテスト通知が届くか確認できます。</div>
                    </div>
                    <button type="button" id="push-test-btn" class="setting-btn setting-btn-test">テスト送信</button>
                </div>
            @endif
            <div class="setting-row" style="justify-content:flex-end;">
                <button type="submit" class="setting-btn setting-btn-test">設定を保存</button>
            </div>
        </form>

        @if (config('demo.enabled') && config('demo.mock_line'))
            {{-- ### demo function and data for test ###
                 DEMO_MODE 有効時のみ表示: モック LINE 連携（本番 OAuth 不要） --}}
            <div class="setting-row" style="margin-top:12px;border-top:1px dashed rgba(168,85,247,0.4);padding-top:14px;">
                <div class="setting-row-main">
                    <div class="setting-row-label">[デモ] モック LINE 連携</div>
                    <div class="setting-row-desc">LINE OAuth をスキップし、任意の ID で連携します（未入力なら自動生成）。</div>
                </div>
                <form method="POST" action="{{ route('setting.line.mock.link') }}" style="display:flex;gap:6px;align-items:center;">
                    @csrf
                    <input type="text" name="user_id" placeholder="任意 (空欄で自動生成)" style="padding:6px 8px;border-radius:8px;background:rgba(255,255,255,0.06);border:1px solid rgba(168,85,247,0.4);color:#f5f5f5;font-size:12px;width:150px;">
                    <button type="submit" class="setting-btn setting-btn-push">連携</button>
                </form>
            </div>
        @endif
    </section>
    @if (config('demo.enabled') && config('demo.test_push'))
        <script>
        (function(){
            var btn = document.getElementById('push-test-btn');
            if (!btn) return;
            btn.addEventListener('click', function(){
                fetch('{{ route('push.test') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).then(function(r){ return r.json().then(function(j){ return {status:r.status,body:j}; }); })
                .then(function(res){
                    var toast = (window.appToast || function(m){ alert(m); });
                    if (res.status === 200 && res.body.sent > 0) {
                        toast('テスト Push を送信しました。端末の通知を確認してください。', 'success');
                    } else if (res.body.error === 'subscription_not_found') {
                        toast('先に「通知を有効化」で端末を登録してください。', 'error');
                    } else if (res.body.error === 'vapid_not_configured') {
                        toast('サーバ側で VAPID キーが未設定です。運営に連絡してください。', 'error');
                    } else {
                        toast('テスト Push 送信結果: ' + JSON.stringify(res.body), 'info');
                    }
                }).catch(function(){
                    (window.appToast || function(m){ alert(m); })('通信エラー', 'error');
                });
            });
        })();
        </script>
    @endif
    @else
    <p class="setting-guest-note">通知の設定はログイン後に利用できます。</p>
    @endif
</div>
@endsection

@push('styles')
<style>
.setting-page { padding: 24px 16px 32px; color: #f5f5f5; }
@media (min-width: 768px) { .setting-page { padding: 32px 24px 40px; } }
.setting-header { margin-bottom: 24px; }
.setting-title { font-family: var(--font-sans); font-size: 1.4rem; margin-bottom: 8px; color: var(--color-gold, #a78bfa); }
.setting-lead { font-size: 0.9rem; line-height: 1.6; color: #c0c0c0; }
.setting-section { margin-bottom: 18px; background: rgba(20, 7, 15, 0.9); border-radius: 16px; padding: 14px 12px 10px; border: 1px solid rgba(168, 85, 247, 0.4); }
@media (min-width: 768px) { .setting-section { padding: 16px 16px 12px; } }
.setting-section-title { font-size: 0.95rem; margin-bottom: 8px; color: #f5f5f5; }
.setting-row { padding: 10px 4px; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.setting-row + .setting-row { border-top: 1px solid rgba(255,255,255,0.05); }
.setting-row-main { flex: 1; min-width: 0; }
.setting-row-label { font-size: 0.9rem; margin-bottom: 2px; }
.setting-row-desc { font-size: 0.78rem; color: #a0a0a0; }
.setting-check { font-size: 0.86rem; color: #f5f5f5; display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; }
.setting-check input { width: 16px; height: 16px; }
.setting-subheading { margin: 14px 4px 2px; font-size: 0.82rem; font-weight: 600; color: #c4b5fd; letter-spacing: 0.04em; }
.setting-subheading:first-of-type { margin-top: 4px; }
.setting-alert { padding: 12px 14px; border-radius: 12px; margin-bottom: 16px; }
.setting-alert-success { background: rgba(22,163,74,0.2); border: 1px solid rgba(22,163,74,0.5); color: #bbf7d0; }
.setting-alert-error { background: rgba(185,28,28,0.2); border: 1px solid rgba(248,113,113,0.5); color: #fecaca; }
.setting-btn { display: inline-block; padding: 10px 18px; border-radius: 12px; font-size: 0.88rem; font-weight: 600; text-decoration: none; border: none; transition: opacity 0.2s; cursor: pointer; }
.setting-btn:hover { opacity: 0.9; }
.setting-btn-install { background: var(--accent, #d670a2); color: var(--on-accent, #1a0814); box-shadow: 0 6px 14px rgba(0,0,0,.45), inset 0 1px 0 rgba(255,255,255,.20), inset 0 -1px 0 rgba(0,0,0,.18); }
.setting-btn-push    { background: rgba(255,255,255,0.06); color: var(--color-text-main, #f5f5f5); border: 1px solid rgba(168, 85, 247, 0.40); }
.setting-btn-test    { background: rgba(16, 185, 129, 0.12); color: #6ee7b7; border: 1px solid rgba(16, 185, 129, 0.45); }
.setting-guide { margin: 2px 4px 8px; font-size: 0.78rem; color: #c0c0c0; }
.setting-guest-note { color: #a0a0a0; font-size: 0.9rem; margin-bottom: 20px; }
</style>
@endpush
