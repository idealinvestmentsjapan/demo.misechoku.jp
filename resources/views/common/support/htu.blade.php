@extends('layouts.app-v2')

@section('title', 'ご利用ガイド')

@php
    $roleLabel = $isCast ? 'キャスト' : '店舗';
    $faqs = $isCast ? [
        ['q' => '利用料はかかりますか？', 'a' => 'キャストの方は登録・利用ともに無料です。採用が決まった際のボーナス金は、アプリ内の「採用・入金管理」で申請から振込まで管理できます。'],
        ['q' => '個人の連絡先を教えずにやりとりできますか？', 'a' => 'はい。トーク機能を使えば、電話番号やLINEを開示せずにお店とやりとりできます。条件がまとまるまではアプリ内での連絡をおすすめします。'],
        ['q' => '本人確認は必須ですか？', 'a' => '安全のため、本人確認書類の提出をお願いしています。サイドメニューの「アカウント管理 > 本人確認」から提出でき、運営の承認後にすべての機能が利用できます。'],
        ['q' => 'ボーナス金はいつ受け取れますか？', 'a' => '採用後に「採用・入金管理」からボーナス申請を行うと、店舗の承認 → 運営の請求 → 店舗の入金 → 運営からあなたの口座へ振込、という流れで進みます。進捗は案件カードでいつでも確認できます。'],
        ['q' => '退会したい場合は？', 'a' => 'サイドメニューの「アカウント設定」から手続きできます。ご不明点は問い合わせ窓口までご連絡ください。'],
        // ▼ 問い合わせ窓口から統合した FAQ（重複掲載の解消）
        ['q' => 'ログインできない場合はどうすれば良いですか？', 'a' => 'メールアドレス・パスワードに誤りがないかをご確認ください。それでも解決しない場合は、問い合わせ窓口より「アカウント・ログインについて」を選択しお問い合わせください。'],
        ['q' => 'お店からのオファーはどこで確認できますか？', 'a' => 'さがす（SEARCH）タブのキープ一覧とトーク画面から、やりとり中のお店を一覧で確認できます。'],
        ['q' => '通知のオン／オフはどこで変更できますか？', 'a' => 'サイドメニューの「SETTING > 通知設定」から、リマインダー通知の受け取り設定を変更できます。'],
    ] : [
        ['q' => '掲載に必要なものはありますか？', 'a' => '営業許可証の提出と運営の承認が必要です。サイドメニューの「アカウント管理 > ライセンス（許可証）管理」から提出できます。承認されると求人票を公開できます。'],
        ['q' => '料金はいつ発生しますか？', 'a' => '採用が確定し、キャストのボーナス申請を承認した時点で、運営から請求書が発行されます。請求・入金の状況は「採用・入金管理」で確認できます。'],
        ['q' => '複数のスタッフでアカウントを使えますか？', 'a' => 'はい。サイドメニューの「アカウント管理 > スタッフ・アカウント管理」から、権限（オーナー/スタッフ）つきでログインアカウントを追加できます。'],
        ['q' => '優良店バッヂとは何ですか？', 'a' => '過去3ヶ月の請求をすべて期日内にお支払いいただいた店舗に自動で付与されます。プロフィールやスワイプカードに表示され、キャストからの信頼につながります。'],
        ['q' => '求人が検索に表示されません', 'a' => '求人票が「非公開」になっている可能性があります。マイページのJOBタブ、または「ステータス管理」から公開状態をご確認ください。許可証が未承認の間は公開できません。'],
        // ▼ 問い合わせ窓口から統合した FAQ（重複掲載の解消）
        ['q' => 'ログインできない場合はどうすれば良いですか？', 'a' => 'メールアドレス・パスワードに誤りがないかをご確認ください。それでも解決しない場合は、問い合わせ窓口より「アカウント・ログインについて」を選択しお問い合わせください。'],
        ['q' => 'キャストからの応募やメッセージはどこで確認できますか？', 'a' => 'さがす（SEARCH）タブのキープ一覧とトーク画面から、やりとり中のキャストを一覧で確認できます。'],
        ['q' => '通知のオン／オフはどこで変更できますか？', 'a' => 'サイドメニューの「SETTING > 通知設定」から、リマインダー通知の受け取り設定を変更できます。'],
        ['q' => '退会やアカウント削除はできますか？', 'a' => 'サイドメニューの「SETTING > アカウント管理」から退会手続きへ進めます。'],
    ];
@endphp

@section('content')
<div class="support-htu-page">
    {{-- ヘッダー --}}
    <header class="support-htu-header">
        {{-- タイトルはヘッダー中央、説明はオコジョガイド（character_guide_settings）に集約 --}}
        <p class="support-htu-badge-row"><span class="support-htu-badge">{{ $roleLabel }}向け</span></p>
    </header>

    {{-- 特徴 --}}
    <div class="support-htu-feature-grid">
        <section class="support-htu-feature-card">
            <span class="support-htu-feature-icon"><i class="fas fa-bolt"></i></span>
            <h2 class="support-htu-feature-title">直感的にさがせる検索体験</h2>
            <p class="support-htu-feature-body">
                スワイプとタイムライン形式の検索で、雰囲気や条件を直感的に比較。
                {{ $isCast ? '自分に合いそうなお店を感覚的に見つけられます。' : '理想のキャスト像に近い人をスムーズに探せます。' }}
            </p>
        </section>
        <section class="support-htu-feature-card">
            <span class="support-htu-feature-icon"><i class="fas fa-comments"></i></span>
            <h2 class="support-htu-feature-title">アプリ内で完結するやりとり</h2>
            <p class="support-htu-feature-body">
                トーク機能で、個人の連絡先を教えずにやりとりできます。
                {{ $isCast ? 'お店とのやりとり履歴も1つの画面で確認できます。' : '複数キャストとの調整もアプリ内で完結します。' }}
            </p>
        </section>
        <section class="support-htu-feature-card">
            <span class="support-htu-feature-icon"><i class="fas fa-yen-sign"></i></span>
            <h2 class="support-htu-feature-title">採用ボーナスまで一気通貫</h2>
            <p class="support-htu-feature-body">
                {{ $isCast ? '採用ボーナスの申請から振込確認まで、進み具合をアプリで見える化。' : '採用後の請求・入金もアプリ内で管理。期日内のお支払いで優良店バッヂが付与されます。' }}
            </p>
        </section>
        <section class="support-htu-feature-card">
            <span class="support-htu-feature-icon"><i class="fas fa-shield-halved"></i></span>
            <h2 class="support-htu-feature-title">安心のサポート体制</h2>
            <p class="support-htu-feature-body">
                {{ $isCast ? '本人確認' : '許可証審査' }}・問い合わせ窓口・お役立ちコラムで、利用中の不明点をサポートします。
            </p>
        </section>
    </div>

    {{-- 使い方ステップ（縦のタイムライン） --}}
    <h2 class="support-htu-section-title"><i class="fas fa-route"></i> 使い方の流れ</h2>
    <ol class="support-htu-steps">
        <li class="support-htu-step">
            <span class="support-htu-step-dot">1</span>
            <div class="support-htu-step-content">
                <h3 class="support-htu-step-title">アカウント登録・ログイン</h3>
                <p class="support-htu-step-body">
                    ログイン画面から{{ $isCast ? 'キャスト用' : '店舗用' }}のアカウントを作成します。登録後は同じメールアドレスとパスワードでログインできます。
                </p>
            </div>
        </li>
        <li class="support-htu-step">
            <span class="support-htu-step-dot">2</span>
            <div class="support-htu-step-content">
                <h3 class="support-htu-step-title">{{ $isCast ? 'プロフィールを整える' : '店舗情報・求人票を整える' }}</h3>
                <p class="support-htu-step-body">
                    マイページから{{ $isCast ? 'プロフィールや希望条件' : '店舗プロフィールや求人票' }}を編集します。写真・紹介文・エリアなど、ミスマッチを防ぐ情報を充実させましょう。
                    {{ $isCast ? '本人確認の提出もお忘れなく。' : '許可証の承認後に求人を公開できます。' }}
                </p>
            </div>
        </li>
        <li class="support-htu-step">
            <span class="support-htu-step-dot">3</span>
            <div class="support-htu-step-content">
                <h3 class="support-htu-step-title">{{ $isCast ? 'お店をさがす・いいねを送る' : 'キャストをさがす・いいねを送る' }}</h3>
                <p class="support-htu-step-body">
                    ホームのスワイプや「SEARCH」から気になる{{ $isCast ? 'お店' : 'キャスト' }}をチェック。気になる相手には「いいね」や「キープ」で意思表示できます。
                </p>
            </div>
        </li>
        <li class="support-htu-step">
            <span class="support-htu-step-dot">4</span>
            <div class="support-htu-step-content">
                <h3 class="support-htu-step-title">トークで条件をすり合わせる</h3>
                <p class="support-htu-step-body">
                    トーク画面で日程や条件を相談します。面談の打診や採用の確定もトーク内のアクションから行えます。
                </p>
            </div>
        </li>
        <li class="support-htu-step">
            <span class="support-htu-step-dot">5</span>
            <div class="support-htu-step-content">
                <h3 class="support-htu-step-title">{{ $isCast ? '採用ボーナスを受け取る' : '採用・お支払いを管理する' }}</h3>
                <p class="support-htu-step-body">
                    {{ $isCast
                        ? '採用が決まって勤務を終えたら「採用・入金管理」から勤務完了報告（レビュー投稿＋ボーナス金申請）。振込までの進み具合はいつでも確認できます。'
                        : '採用が確定したら「採用・入金管理」で勤務完了報告を承認し、請求書のお支払いまでアプリ内で完結します。' }}
                </p>
            </div>
        </li>
    </ol>

    {{-- FAQ --}}
    <h2 class="support-htu-section-title"><i class="fas fa-circle-question"></i> よくある質問</h2>
    <div class="support-htu-faq">
        @foreach ($faqs as $faq)
            <details class="support-htu-faq-item">
                <summary class="support-htu-faq-q">
                    <span class="support-htu-faq-mark">Q</span>
                    <span class="support-htu-faq-qtext">{{ $faq['q'] }}</span>
                    <i class="fas fa-chevron-down support-htu-faq-chev" aria-hidden="true"></i>
                </summary>
                <div class="support-htu-faq-a">
                    <span class="support-htu-faq-mark support-htu-faq-mark--a">A</span>
                    <p>{{ $faq['a'] }}</p>
                </div>
            </details>
        @endforeach
    </div>

    {{-- 問い合わせ導線 --}}
    <div class="support-htu-contact">
        <p class="support-htu-contact-text">解決しない場合は、お気軽にお問い合わせください。</p>
        <a href="{{ url('/support/form') }}" class="support-htu-contact-btn">
            <i class="fas fa-paper-plane"></i> 問い合わせる
        </a>
    </div>
</div>
@endsection

@push('styles')
<style>
.support-htu-page {
    padding: 24px 16px 40px;
    max-width: 42rem;
    margin: 0 auto;
    color: var(--color-text-main, #f5f5f5);
}

/* ヘッダー */
.support-htu-header { margin-bottom: 22px; }
.support-htu-badge-row { margin: 14px 0 0; }
.support-htu-title {
    font-family: var(--font-sans);
    font-size: 1.25rem;
    font-weight: 800;
    margin: 0 0 8px;
    color: var(--color-text-main, #f5f5f5);
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}
.support-htu-title i { color: var(--accent-text, #a78bfa); font-size: 1rem; }
.support-htu-badge {
    font-size: 0.68rem;
    font-weight: 800;
    padding: 3px 10px;
    border-radius: 999px;
    background: rgba(var(--accent-rgb, 139, 92, 246), 0.15);
    border: 1px solid rgba(var(--accent-rgb, 139, 92, 246), 0.45);
    color: var(--accent-text, #a78bfa);
}
.support-htu-lead {
    font-size: 0.86rem;
    line-height: 1.8;
    color: var(--color-text-sub, #b8b8b8);
    margin: 0;
}

/* セクション見出し */
.support-htu-section-title {
    margin: 30px 0 14px;
    font-size: 0.95rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--color-text-main, #f5f5f5);
}
.support-htu-section-title i { color: var(--accent-text, #a78bfa); font-size: 0.85rem; }
.support-htu-section-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: linear-gradient(to right, rgba(var(--accent-rgb, 139, 92, 246), 0.35), transparent);
}

/* 特徴カード */
.support-htu-feature-grid { display: grid; grid-template-columns: 1fr; gap: 10px; }
@media (min-width: 640px) {
    .support-htu-feature-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
}
.support-htu-feature-card {
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.035), rgba(255, 255, 255, 0.012));
    border: 1px solid rgba(168, 85, 247, 0.22);
    border-radius: 16px;
    padding: 16px 14px;
}
.support-htu-feature-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: rgba(var(--accent-rgb, 139, 92, 246), 0.14);
    border: 1px solid rgba(var(--accent-rgb, 139, 92, 246), 0.35);
    color: var(--accent-text, #a78bfa);
    font-size: 0.9rem;
    margin-bottom: 10px;
}
.support-htu-feature-title {
    font-size: 0.9rem;
    font-weight: 800;
    margin: 0 0 6px;
}
.support-htu-feature-body {
    font-size: 0.8rem;
    line-height: 1.75;
    color: var(--color-text-sub, #b8b8b8);
    margin: 0;
}

/* 使い方ステップ：縦タイムライン */
.support-htu-steps {
    list-style: none;
    padding: 0;
    margin: 0;
    position: relative;
}
.support-htu-step {
    position: relative;
    display: flex;
    gap: 14px;
    padding: 0 0 22px 0;
}
/* 縦の接続線 */
.support-htu-step::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 34px;
    bottom: -2px;
    width: 2px;
    background: linear-gradient(to bottom, rgba(var(--accent-rgb, 139, 92, 246), 0.4), rgba(var(--accent-rgb, 139, 92, 246), 0.08));
}
.support-htu-step:last-child { padding-bottom: 0; }
.support-htu-step:last-child::before { display: none; }
.support-htu-step-dot {
    flex: 0 0 auto;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(var(--accent-rgb, 139, 92, 246), 0.16);
    border: 2px solid rgba(var(--accent-rgb, 139, 92, 246), 0.55);
    color: var(--accent-text, #a78bfa);
    font-size: 0.86rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    position: relative;
    z-index: 1;
}
.support-htu-step-content { flex: 1; min-width: 0; padding-top: 4px; }
.support-htu-step-title {
    font-size: 0.94rem;
    font-weight: 800;
    margin: 0 0 5px;
    color: var(--color-text-main, #f5f5f5);
}
.support-htu-step-body {
    font-size: 0.82rem;
    line-height: 1.8;
    color: var(--color-text-sub, #b8b8b8);
    margin: 0;
}

/* FAQ：アコーディオン */
.support-htu-faq { display: flex; flex-direction: column; gap: 8px; }
.support-htu-faq-item {
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.025);
    overflow: hidden;
    transition: border-color 0.15s ease;
}
.support-htu-faq-item[open] { border-color: rgba(var(--accent-rgb, 139, 92, 246), 0.45); }
.support-htu-faq-q {
    list-style: none;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 13px 14px;
    cursor: pointer;
    user-select: none;
    font-size: 0.86rem;
    font-weight: 700;
    color: var(--color-text-main, #f5f5f5);
    -webkit-tap-highlight-color: transparent;
}
.support-htu-faq-q::-webkit-details-marker { display: none; }
.support-htu-faq-mark {
    flex: 0 0 auto;
    width: 24px;
    height: 24px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
    font-weight: 800;
    background: rgba(var(--accent-rgb, 139, 92, 246), 0.16);
    color: var(--accent-text, #a78bfa);
}
.support-htu-faq-mark--a {
    background: rgba(110, 231, 183, 0.12);
    color: var(--color-success, #6ee7b7);
}
.support-htu-faq-qtext { flex: 1; min-width: 0; line-height: 1.5; }
.support-htu-faq-chev {
    flex: 0 0 auto;
    font-size: 0.7rem;
    color: var(--color-text-sub, #b8b8b8);
    transition: transform 0.2s var(--ease-out, ease);
}
.support-htu-faq-item[open] .support-htu-faq-chev { transform: rotate(180deg); }
.support-htu-faq-a {
    display: flex;
    gap: 10px;
    padding: 0 14px 14px;
}
.support-htu-faq-a p {
    margin: 0;
    font-size: 0.82rem;
    line-height: 1.8;
    color: var(--color-text-sub, #b8b8b8);
}

/* 問い合わせ導線 */
.support-htu-contact {
    margin-top: 28px;
    padding: 20px 16px;
    border-radius: 16px;
    border: 1px dashed rgba(var(--accent-rgb, 139, 92, 246), 0.4);
    text-align: center;
}
.support-htu-contact-text {
    margin: 0 0 12px;
    font-size: 0.84rem;
    color: var(--color-text-sub, #b8b8b8);
}
.support-htu-contact-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 28px;
    border-radius: 999px;
    background: var(--accent, #8b5cf6);
    color: var(--on-accent, #e6dffc);
    font-size: 0.88rem;
    font-weight: 800;
    text-decoration: none;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
    transition: transform 0.15s var(--ease-out, ease);
}
.support-htu-contact-btn:active { transform: scale(0.97); }
</style>
@endpush
