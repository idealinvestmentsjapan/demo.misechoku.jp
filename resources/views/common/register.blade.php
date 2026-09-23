@extends('layouts.app-v2')

@section('title', $title)
@section('body-class', $bodyClass)

@if ($role === 'shop')
@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/image-editor.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/register-shop-profile-crop.css') }}">
@endpush
@endif

@if ($role === 'cast')
@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/image-editor.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/register-cast-profile-crop.css') }}">
<style>
.register-skip-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    margin-bottom: 14px;
    border: 1px dashed rgba(168, 85, 247, .35);
    border-radius: 10px;
    background: rgba(168, 85, 247, .05);
    color: #e6dffc;
    font-size: 0.86rem;
    cursor: pointer;
}
.register-skip-toggle input { accent-color: #a78bfa; }
.register-field-compact input { max-width: 280px; }
</style>
@endpush
@endif

@push('scripts')
    <script src="https://yubinbango.github.io/yubinbango/yubinbango.js" charset="UTF-8"></script>
    @if ($role === 'shop')
    <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
    <script src="{{ asset('assets/js/image-editor.js') }}"></script>
    <script src="{{ asset('assets/js/register-shop-profile-crop.js') }}"></script>
    @endif
    @if ($role === 'cast')
    <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
    <script src="{{ asset('assets/js/image-editor.js') }}"></script>
    <script src="{{ asset('assets/js/register-cast-profile-crop.js') }}"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // 「あとで登録する」トグル：チェック時に対象フィールドを非表示＋disabled で送信対象から外す
        document.querySelectorAll('[data-skip-section]').forEach(function (section) {
            var checkbox = section.querySelector('.register-skip-toggle input[type="checkbox"]');
            var target = section.querySelector('.register-skip-target');
            if (!checkbox || !target) return;
            var apply = function () {
                var skipped = checkbox.checked;
                target.hidden = skipped;
                target.querySelectorAll('input, select, textarea').forEach(function (el) {
                    el.disabled = skipped;
                });
            };
            checkbox.addEventListener('change', apply);
            apply();
        });

        // 本人確認書類のパターン切替（A: 顔写真付き / B: 顔写真なし＋住所確認）
        // 1つ選択はプルダウン統一に伴い select の change で切り替える
        var identityCategorySelect = document.querySelector('select[name="identity_category"]');
        if (identityCategorySelect) {
            identityCategorySelect.addEventListener('change', function () {
                document.querySelectorAll('[data-identity-pane]').forEach(function (pane) {
                    pane.hidden = pane.getAttribute('data-identity-pane') !== identityCategorySelect.value;
                });
            });
        }
    });
    </script>
    @endif
    <script>
    // 登録フォーム送信中のインジケータ表示。submit直後にオーバーレイを出し、ボタン二重押下を防ぐ。
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('form.register-form');
        var overlay = document.getElementById('register-submit-overlay');
        if (!form || !overlay) return;
        form.addEventListener('submit', function (event) {
            if (event.defaultPrevented) return;
            overlay.classList.add('is-visible');
            var btn = form.querySelector('.register-submit');
            if (btn) {
                btn.disabled = true;
                btn.dataset.originalLabel = btn.textContent;
                btn.textContent = '登録処理中…';
            }
        });
    });
    </script>
@endpush

@section('content')
    <div class="register-page">
        <section class="register-hero">
            <img src="{{ asset('assets/images/common/logo-yoko.png') }}" alt="ミセチョク" class="register-logo">
            <h1 class="register-title">{{ $heroTitle }}</h1>
        </section>

        <form method="POST" action="{{ $formAction }}" class="register-form h-adr" enctype="multipart/form-data"
              data-validation-errors="{{ json_encode($errors->messages(), JSON_UNESCAPED_UNICODE) }}"
              data-rw-escape-url="{{ $loginUrl }}">
            @csrf
            <span class="p-country-name" style="display:none;">Japan</span>

            @if (session('success'))
                <div class="register-alert register-alert-success">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="register-alert register-alert-error" role="alert" tabindex="-1">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            @if ($role === 'cast')
                <section class="register-card">
                    <div class="register-card-head">
                        <h2>基本情報</h2>
                    </div>

                    <div class="register-grid register-grid-two">
                        <label class="register-field">
                            <span>ニックネーム <em>必須</em></span>
                            <input type="text" name="nickname" value="{{ old('nickname') }}" placeholder="例：ゆな">
                        </label>

                        <label class="register-field">
                            <span>氏名 <em>必須</em></span>
                            <input type="text" name="name" value="{{ old('name') }}" placeholder="例：山田 花子">
                        </label>
                    </div>

                    <div class="register-field">
                        <span>生年月日 <em>必須</em></span>
                        <input type="date" name="birth_date" value="{{ old('birth_date') }}" data-rw-birth>
                        <span class="rw-age-badge" data-rw-age-badge hidden></span>
                    </div>

                    <label class="register-field">
                        <span>郵便番号 <em>必須</em></span>
                        <input
                            type="text"
                            name="zip"
                            value="{{ old('zip') }}"
                            class="p-postal-code"
                            data-postal-code
                            maxlength="8"
                            pattern="[0-9-]*"
                            inputmode="numeric"
                            autocomplete="postal-code"
                            placeholder="例：160-0021"
                        >
                        <small class="register-field-hint">住所を自動入力します。</small>
                    </label>

                    <div class="register-grid register-grid-two">
                        <label class="register-field">
                            <span>都道府県 <em>必須</em></span>
                            <select name="pref" class="p-region">
                                <option value="">選択してください</option>
                                @foreach ($prefOptions as $pref)
                                    <option value="{{ $pref }}" @selected(old('pref') === $pref)>{{ $pref }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="register-field">
                            <span>市区町村 <em>必須</em></span>
                            <input
                                type="text"
                                name="city"
                                value="{{ old('city') }}"
                                class="p-locality"
                                autocomplete="address-level2"
                                placeholder="例：新宿区歌舞伎町"
                            >
                        </label>
                    </div>

                    <label class="register-field">
                        <span>町名・番地</span>
                        <input
                            type="text"
                            name="addr1"
                            value="{{ old('addr1') }}"
                            class="p-street-address"
                            autocomplete="address-line1"
                            placeholder="例：1-2-3"
                        >
                    </label>
                </section>

                {{-- プロフィール詳細（編集画面と同等。あとでマイページでも登録可能） --}}
                <section class="register-card" data-skip-section="profile">
                    <div class="register-card-head">
                        <h2>プロフィール詳細</h2>
                    </div>

                    <label class="register-skip-toggle">
                        <input type="checkbox" name="profile_skip" value="1" @checked(old('profile_skip'))>
                        <span>あとで登録する（マイページから後で編集できます）</span>
                    </label>

                    <div class="register-skip-target" @if(old('profile_skip')) hidden @endif>
                    {{-- === 自己PR === （編集画面と同順） --}}
                    <label class="register-field">
                        <span>自己PR</span>
                        <textarea name="intro" rows="4" placeholder="自己紹介" data-rw-suggest="intro">{{ old('intro') }}</textarea>
                    </label>

                    {{-- === 体型・ルックス情報 === （編集画面と同順：身長→体重→BWH→ルックス→性格・内面） --}}
                    <div class="metric-pair">
                        <div class="metric-field">
                            <label class="metric-field-label" for="reg-height">身長 <small>cm</small></label>
                            <div class="metric-input-wrap" data-stepper data-step="1">
                                <input type="number" id="reg-height" name="height" value="{{ old('height') }}" inputmode="numeric" pattern="[0-9]*" min="130" max="200" placeholder="160">
                                <span class="metric-unit">cm</span>
                            </div>
                        </div>
                        <div class="metric-field">
                            <label class="metric-field-label" for="reg-weight">体重 <small>kg</small></label>
                            <div class="metric-input-wrap" data-stepper data-step="1">
                                <input type="number" id="reg-weight" name="weight" value="{{ old('weight') }}" inputmode="numeric" pattern="[0-9]*" min="30" max="150" placeholder="48">
                                <span class="metric-unit">kg</span>
                            </div>
                        </div>
                    </div>
                    <div class="bwh-group">
                        <span class="metric-field-label">3サイズ <small>cm</small></span>
                        <div class="bwh-row">
                            <label class="bwh-field" aria-label="バスト">
                                <span class="bwh-letter">B</span>
                                <input type="number" name="bust" value="{{ old('bust') }}" inputmode="numeric" pattern="[0-9]*" min="50" max="120" placeholder="--">
                            </label>
                            <label class="bwh-field" aria-label="ウエスト">
                                <span class="bwh-letter">W</span>
                                <input type="number" name="waist" value="{{ old('waist') }}" inputmode="numeric" pattern="[0-9]*" min="40" max="120" placeholder="--">
                            </label>
                            <label class="bwh-field" aria-label="ヒップ">
                                <span class="bwh-letter">H</span>
                                <input type="number" name="hip" value="{{ old('hip') }}" inputmode="numeric" pattern="[0-9]*" min="50" max="120" placeholder="--">
                            </label>
                        </div>
                    </div>

                    @if(!empty($masters['looks']) && $masters['looks']->isNotEmpty())
                    <div class="register-field">
                        <span>ルックス</span>
                        <div class="register-chip-grid">
                            @foreach($masters['looks'] as $look)
                                <label class="register-chip">
                                    <input type="checkbox" name="look_tag_ids[]" value="{{ $look->id }}" @checked(in_array((int)$look->id, old('look_tag_ids', []), true))>
                                    <span>{{ $look->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    @if(!empty($masters['personalities']) && $masters['personalities']->isNotEmpty())
                    <div class="register-field">
                        <span>性格・内面</span>
                        <div class="register-chip-grid">
                            @foreach($masters['personalities'] as $personality)
                                <label class="register-chip">
                                    <input type="checkbox" name="personality_tag_ids[]" value="{{ $personality->id }}" @checked(in_array((int)$personality->id, old('personality_tag_ids', []), true))>
                                    <span>{{ $personality->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- === 希望の働き方 === （編集画面と同順：希望職種→現職業→ナイトワーク経験） --}}
                    @if(!empty($masters['industries']) && $masters['industries']->isNotEmpty())
                    <div class="register-field">
                        <span>希望職種</span>
                        <div class="register-chip-grid">
                            @foreach($masters['industries'] as $industry)
                                <label class="register-chip">
                                    <input type="checkbox" name="industry_ids[]" value="{{ $industry->id }}" @checked(in_array((int)$industry->id, old('industry_ids', []), true))>
                                    <span>{{ $industry->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <label class="register-field register-field-compact">
                        <span>現職業</span>
                        <input type="text" name="profession" value="{{ old('profession', old('current_job')) }}" maxlength="40" placeholder="例：会社員 / 学生 / フリーター">
                    </label>

                    <label class="register-field">
                        <span>ナイトワーク経験</span>
                        {{-- 1つ選択はプルダウンに統一（入力コンポーネント規約） --}}
                        <select name="exp">
                            <option value="none" @selected(old('exp', old('night_work_exp', 'none')) === 'none')>無し</option>
                            <option value="yes" @selected(old('exp', old('night_work_exp')) === 'yes')>有り</option>
                        </select>
                    </label>
                    </div>{{-- /.register-skip-target --}}
                </section>

                {{-- プロフィール画像（必須 1枚） --}}
                <section class="register-card">
                    <div class="register-card-head">
                        <h2>プロフィール画像</h2>
                    </div>
                    <label class="register-field">
                        <span>メイン画像 <em>必須</em></span>
                        <input type="file" id="cast-register-profile-image" name="profile_image" accept="image/jpeg,image/png,image/gif,image/webp" capture="user" required>
                        <small class="register-field-hint">顔が分かる画像を1枚選び、次の画面で<strong>縦長（3:4）</strong>の範囲を調整してから登録してください。（JPEG / PNG / GIF / WebP、最大2MB）</small>
                    </label>
                </section>

                {{-- 本人確認書類（任意・あとでマイページで登録可） --}}
                <section class="register-card" data-skip-section="identity">
                    <div class="register-card-head">
                        <h2>本人確認書類</h2>
                    </div>

                    <p class="register-field-hint" style="margin: 0 0 12px;">
                        本人確認書類はサービスご利用前に必ず必要です。今すぐ提出するか、登録後にマイページから提出するかを選べます。
                    </p>

                    <label class="register-skip-toggle">
                        <input type="hidden" name="identity_skip" value="0">
                        <input type="checkbox" name="identity_skip" value="1" @checked(old('identity_skip', '1') == '1')>
                        <span>あとで登録する（マイページから後でアップロードできます）</span>
                    </label>

                    <div class="register-skip-target" @if(old('identity_skip', '1') == '1') hidden @endif>
                        <label class="register-field">
                            <span>提出パターン</span>
                            {{-- 1つ選択はプルダウンに統一（入力コンポーネント規約） --}}
                            <select name="identity_category">
                                <option value="photo_id" @selected(old('identity_category', 'photo_id') === 'photo_id')>パターンA：顔写真付き身分証 1枚</option>
                                <option value="non_photo_id" @selected(old('identity_category') === 'non_photo_id')>パターンB：顔写真なし身分証 ＋ 住所確認書類</option>
                            </select>
                        </label>

                        <div data-identity-pane="photo_id" @if(old('identity_category', 'photo_id') !== 'photo_id') hidden @endif>
                            <label class="register-field">
                                <span>書類種別</span>
                                <select name="identity_type">
                                    <option value="driver_license" @selected(old('identity_type') === 'driver_license')>運転免許証</option>
                                    <option value="passport" @selected(old('identity_type') === 'passport')>パスポート</option>
                                    <option value="mynumber_card" @selected(old('identity_type') === 'mynumber_card')>マイナンバーカード</option>
                                    <option value="residence_card" @selected(old('identity_type') === 'residence_card')>在留カード</option>
                                </select>
                            </label>

                            <div class="register-grid register-grid-two">
                                <label class="register-field">
                                    <span>表面（画像 / PDF）</span>
                                    <input type="file" name="identity_front_file" accept=".pdf,image/*" capture="environment">
                                </label>
                                <label class="register-field">
                                    <span>裏面（任意）</span>
                                    <input type="file" name="identity_back_file" accept=".pdf,image/*" capture="environment">
                                </label>
                            </div>

                            <label class="register-field">
                                <span>有効期限（任意）</span>
                                <input type="date" name="identity_expired_at" value="{{ old('identity_expired_at') }}">
                            </label>
                        </div>

                        <div data-identity-pane="non_photo_id" @if(old('identity_category') !== 'non_photo_id') hidden @endif>
                            <p class="register-field-hint" style="margin: 0 0 8px;">
                                <strong>① 顔写真なし身分証</strong> と <strong>② 住所確認書類</strong> の両方をアップロードしてください。
                            </p>

                            <label class="register-field">
                                <span>① 書類種別（顔写真なし身分証）</span>
                                <select name="identity_type">
                                    <option value="health_insurance" @selected(old('identity_type') === 'health_insurance')>健康保険証</option>
                                    <option value="pension_book" @selected(old('identity_type') === 'pension_book')>年金手帳</option>
                                </select>
                            </label>

                            <div class="register-grid register-grid-two">
                                <label class="register-field">
                                    <span>① 表面（画像 / PDF）</span>
                                    <input type="file" name="identity_front_file" accept=".pdf,image/*" capture="environment">
                                </label>
                                <label class="register-field">
                                    <span>① 裏面（任意）</span>
                                    <input type="file" name="identity_back_file" accept=".pdf,image/*" capture="environment">
                                </label>
                            </div>

                            <label class="register-field">
                                <span>② 書類種別（住所確認書類）</span>
                                <select name="identity_address_type">
                                    <option value="residence_certificate" @selected(old('identity_address_type') === 'residence_certificate')>住民票</option>
                                    <option value="utility_bill" @selected(old('identity_address_type') === 'utility_bill')>公共料金領収書</option>
                                </select>
                            </label>

                            <label class="register-field">
                                <span>② 表面（画像 / PDF）</span>
                                <input type="file" name="identity_address_front_file" accept=".pdf,image/*" capture="environment">
                            </label>
                        </div>
                    </div>
                </section>
            @else
                <section class="register-card">
                    <div class="register-card-head">
                        <h2>店舗情報</h2>
                    </div>

                    <div class="register-grid register-grid-two">
                        <label class="register-field">
                            <span>運営会社名 <em>必須</em></span>
                            <input type="text" name="company_name" value="{{ old('company_name') }}" placeholder="例：株式会社ミセチョク">
                        </label>

                        <label class="register-field">
                            <span>店舗名 <em>必須</em></span>
                            <input type="text" name="shop_name" value="{{ old('shop_name') }}" placeholder="例：CLUB LUMIERE">
                        </label>
                    </div>

                    <label class="register-field">
                        <span>担当者名 <em>必須</em></span>
                        <input type="text" name="contact_name" value="{{ old('contact_name') }}" placeholder="例：田中 一郎">
                    </label>

                    <label class="register-field">
                        <span>郵便番号 <em>必須</em></span>
                        <input
                            type="text"
                            name="zip"
                            value="{{ old('zip') }}"
                            class="p-postal-code"
                            data-postal-code
                            maxlength="8"
                            pattern="[0-9-]*"
                            inputmode="numeric"
                            autocomplete="postal-code"
                            placeholder="例：106-0032"
                        >
                        <small class="register-field-hint">住所を自動入力します。</small>
                    </label>

                    <div class="register-grid register-grid-two">
                        <label class="register-field">
                            <span>都道府県 <em>必須</em></span>
                            <select name="pref" class="p-region">
                                <option value="">選択してください</option>
                                @foreach ($prefOptions as $pref)
                                    <option value="{{ $pref }}" @selected(old('pref') === $pref)>{{ $pref }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="register-field">
                            <span>市区町村 <em>必須</em></span>
                            <input
                                type="text"
                                name="city"
                                value="{{ old('city') }}"
                                class="p-locality"
                                autocomplete="address-level2"
                                placeholder="例：港区六本木"
                            >
                        </label>
                    </div>

                    <div class="register-grid register-grid-two">
                    <label class="register-field">
                        <span>番地・丁目 <em>必須</em></span>
                        <input
                            type="text"
                            name="addr"
                            value="{{ old('addr') }}"
                            class="p-street-address"
                            autocomplete="address-line1"
                            placeholder="例：7-12-34"
                        >
                    </label>
                    <label class="register-field">
                        <span>建物名・部屋番号</span>
                        <input
                            type="text"
                            name="building"
                            value="{{ old('building') }}"
                            autocomplete="address-line2"
                            placeholder="例：ミセチョクビル 5F"
                        >
                    </label>
                    </div>

                    <label class="register-field">
                        <span>キャッチコピー（ひとこと）</span>
                        <input type="text" name="word" value="{{ old('word') }}" placeholder="例：最高級の夜を、あなたに。" data-rw-suggest="catch">
                        <small class="register-field-hint">一覧やプロフィールに表示される短い紹介文です。</small>
                    </label>
                    <label class="register-field">
                        <span>お店の紹介文</span>
                        <textarea name="overview" rows="5" placeholder="お店のコンセプト、雰囲気、客層などを入力" data-rw-suggest="overview">{{ old('overview') }}</textarea>
                    </label>
                    @if(!empty($masters['industries']) && $masters['industries']->isNotEmpty())
                    <div class="register-field">
                        <span>業種</span>
                        <div class="register-chip-grid">
                            @foreach($masters['industries'] as $industry)
                                <label class="register-chip">
                                    <input type="checkbox" name="industry_ids[]" value="{{ $industry->id }}" @checked(in_array((int)$industry->id, old('industry_ids', []), true))>
                                    <span>{{ $industry->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <label class="register-field">
                        <span>ご利用プラン <em>必須</em></span>
                        {{-- 1つ選択はプルダウンに統一（入力コンポーネント規約） --}}
                        <select name="plan" required>
                            <option value="basic" @selected(old('plan', 'basic') === 'basic')>Basic</option>
                            <option value="premium" disabled>Premium（実装中・近日公開）</option>
                        </select>
                    </label>
                </section>

                {{-- 店舗プロフィール画像（必須 1枚） --}}
                <section class="register-card">
                    <div class="register-card-head">
                        <h2>店舗プロフィール画像</h2>
                    </div>
                    <label class="register-field">
                        <span>メイン画像 <em>必須</em></span>
                        <input type="file" id="shop-register-profile-image" name="shop_profile_image" accept="image/jpeg,image/png,image/gif,image/webp" required>
                        <small class="register-field-hint">店舗の雰囲気が伝わる画像を1枚選び、次の画面で<strong>縦長（4:5）</strong>の範囲を調整してから登録してください。（JPEG / PNG / GIF / WebP、最大2MB）</small>
                    </label>
                </section>
            @endif

            <section class="register-card">
                <div class="register-card-head">
                    <h2>アカウント情報</h2>
                </div>

                <div class="register-grid register-grid-two">
                    <label class="register-field">
                        <span>メールアドレス <em>必須</em></span>
                        <input type="email" name="email" value="{{ old('email') }}" placeholder="example@misechoku.jp">
                    </label>

                    <label class="register-field">
                        <span>電話番号 <em>必須</em></span>
                        <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="09012345678">
                    </label>
                </div>

                <div class="register-grid register-grid-two">
                    <label class="register-field">
                        <span>パスワード <em>必須</em></span>
                        <input type="password" name="password" placeholder="8文字以上で入力">
                    </label>

                    <label class="register-field">
                        <span>パスワード確認 <em>必須</em></span>
                        <input type="password" name="password_confirmation" placeholder="もう一度入力">
                    </label>
                </div>
            </section>

            <section class="register-card register-card-compact">
                <label class="register-check">
                    <input type="checkbox" name="terms" value="1" @checked(old('terms'))>
                    <span>
                        <a href="{{ route('pages.official.terms') }}">利用規約</a> と
                        <a href="{{ route('pages.official.privacy') }}">プライバシーポリシー</a> に同意します。
                    </span>
                </label>
            </section>

            <div class="register-actions">
                <button type="submit" class="register-submit">{{ $submitLabel }}</button>
                <a href="{{ $loginUrl }}" class="register-secondary">ログインへ戻る</a>
                <a href="{{ $loginUrl }}" class="register-login-link">デモログインへ戻る</a>
            </div>
        </form>
    </div>

    <div id="register-submit-overlay" class="register-submit-overlay" aria-hidden="true">
        <div class="register-submit-overlay-inner">
            <div class="register-submit-spinner" aria-hidden="true"></div>
            <p class="register-submit-overlay-text">登録処理中です</p>
            <p class="register-submit-overlay-sub">画像のアップロードや書類の保存を行っています。<br>このまま少々お待ちください。</p>
        </div>
    </div>

    @if ($role === 'cast')
    <div id="register-cast-crop-modal" class="register-cast-crop-overlay" role="dialog" aria-modal="true" aria-labelledby="register-cast-crop-title" style="display:none;">
        <div class="register-cast-crop-inner">
            <div>
                <h3 id="register-cast-crop-title">プロフィール画像の調整</h3>
                <p class="register-cast-crop-guide">表示枠は縦長（3:4）です。ズームや位置を調整し、「この画像で続ける」を押すと登録フォームに反映されます。</p>
            </div>
            <div class="register-cast-crop-frame">
                <img id="register-cast-crop-preview" src="" alt="">
            </div>
            <div class="register-cast-crop-actions">
                <button type="button" class="register-cast-crop-btn-secondary" id="register-cast-crop-cancel">別の画像を選ぶ</button>
                <button type="button" class="register-cast-crop-btn-primary" id="register-cast-crop-confirm">この画像で続ける</button>
            </div>
        </div>
    </div>
    @endif

    @if ($role === 'shop')
    <div id="register-shop-crop-modal" class="register-shop-crop-overlay" role="dialog" aria-modal="true" aria-labelledby="register-shop-crop-title" style="display:none;">
        <div class="register-shop-crop-inner">
            <div>
                <h3 id="register-shop-crop-title">店舗メイン画像の調整</h3>
                <p class="register-shop-crop-guide">表示枠は縦長（4:5）です。ズームや位置を調整し、「この画像で続ける」を押すと登録フォームに反映されます。</p>
            </div>
            <div class="register-shop-crop-frame">
                <img id="register-shop-crop-preview" src="" alt="">
            </div>
            <div class="register-shop-crop-actions">
                <button type="button" class="register-shop-crop-btn-secondary" id="register-shop-crop-cancel">別の画像を選ぶ</button>
                <button type="button" class="register-shop-crop-btn-primary" id="register-shop-crop-confirm">この画像で続ける</button>
            </div>
        </div>
    </div>
    @endif

    <style>
        body.page-auth-register #bottom-nav,
        body.page-auth-register #side-menu,
        body.page-auth-register .header-right {
            display: none !important;
        }

        body.page-auth-register main {
            padding-bottom: 40px;
        }

        body.page-auth-register.page-auth-register-cast {
            background:
                radial-gradient(circle at top left, rgba(168, 85, 247, 0.18), transparent 32%),
                linear-gradient(180deg, #050505 0%, #050505 45%, #050505 100%);
        }

        body.page-auth-register.page-auth-register-shop {
            background:
                radial-gradient(circle at top right, rgba(168, 85, 247, 0.18), transparent 32%),
                linear-gradient(180deg, #0a0a0a 0%, #141414 45%, #0a0a0a 100%);
        }

        body.page-auth-register #bg-layer {
            background:
                radial-gradient(circle at 15% 10%, rgba(168, 85, 247, 0.08), transparent 22%),
                radial-gradient(circle at 82% 16%, rgba(255, 255, 255, 0.04), transparent 18%),
                radial-gradient(circle at 50% 100%, rgba(122, 24, 44, 0.22), transparent 30%);
        }

        .register-page {
            width: min(100%, 760px);
            margin: 0 auto;
            padding: 28px 0 0;
        }

        .register-hero,
        .register-card {
            border: 1px solid rgba(168, 85, 247, 0.18);
            border-radius: 28px;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.02)),
                linear-gradient(135deg, rgba(20, 20, 20, 0.96), rgba(10, 10, 10, 0.98));
            box-shadow:
                0 22px 60px rgba(0, 0, 0, 0.36),
                inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }

        .register-hero {
            padding: 32px 28px 26px;
            text-align: center;
        }

        .register-role-switch {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 20px;
        }

        .register-role {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            border: 1px solid #3d1a1f;
            border-radius: 14px;
            background: rgba(18, 4, 5, 0.58);
            color: #a0a0a0;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .register-role.is-active {
            border-color: rgba(168, 85, 247, 0.42);
            color: #a78bfa;
            background: rgba(35, 15, 18, 0.95);
        }

        .register-logo {
            width: 210px;
            max-width: 70%;
            margin-bottom: 14px;
            filter: drop-shadow(0 10px 24px rgba(0, 0, 0, 0.35));
        }

        .register-title {
            margin: 0;
            font-size: clamp(1.5rem, 4vw, 1.9rem);
            line-height: 1.4;
            color: var(--accent-text, #f0a6c4);
        }

        .register-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-top: 18px;
        }

        .register-alert {
            padding: 14px 16px;
            border-radius: 18px;
            font-size: 0.84rem;
            line-height: 1.75;
        }

        .register-alert-success {
            border: 1px solid rgba(134, 239, 172, 0.28);
            background: rgba(20, 83, 45, 0.42);
            color: #bbf7d0;
        }

        .register-alert-error {
            border: 1px solid rgba(255, 177, 177, 0.3);
            background: rgba(122, 24, 44, 0.42);
            color: #fff;
        }

        .register-card {
            padding: 24px 22px;
        }

        .register-card-compact {
            padding: 18px 22px;
        }

        .register-card-head {
            margin-bottom: 18px;
        }

        .register-card-head h2 {
            margin: 0;
            font-size: 1.05rem;
            color: var(--accent-text, #f0a6c4);
        }

        .register-grid {
            display: grid;
            gap: 14px;
        }

        .register-grid-two {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin-bottom: 14px;
        }

        .register-grid-birth {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .register-grid-three {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .register-chip-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .register-chip {
            position: relative;
            display: inline-flex;
            align-items: center;
        }

        .register-chip input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .register-chip span {
            display: inline-flex;
            align-items: center;
            min-height: 38px;
            padding: 8px 14px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: rgba(255, 255, 255, 0.04);
            color: #fff;
            font-size: 0.85rem;
        }

        .register-chip input:checked + span {
            border-color: rgba(168, 85, 247, 0.5);
            background: rgba(168, 85, 247, 0.18);
            color: var(--accent-text, #f0a6c4);
        }

        .register-three-size {
            display: flex;
            gap: 8px;
        }

        .register-three-size input {
            flex: 1;
            min-width: 0;
        }

        .register-radio-row {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .register-radio {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            color: #f5f5f5;
        }

        .register-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
            color: #f5f5f5;
            font-size: 0.84rem;
        }

        .register-field span {
            color: #f5f5f5;
        }

        .register-field em {
            font-style: normal;
            color: var(--accent-text, #f0a6c4);
            font-size: 0.73rem;
            margin-left: 6px;
        }

        .register-field-hint {
            color: rgba(218, 199, 199, 0.7);
            font-size: 0.73rem;
            line-height: 1.6;
        }

        .register-field input,
        .register-field select,
        .register-field textarea {
            width: 100%;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid rgba(168, 85, 247, 0.18);
            background: rgba(255, 255, 255, 0.04);
            color: #fff;
            font-size: 16px; /* iOSズーム回避 */
            min-height: 46px;
            line-height: 1.4;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .register-field input[type="date"] {
            min-height: 46px;
            color-scheme: dark;
            font-variant-numeric: tabular-nums;
        }

        .register-field input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(79%) sepia(29%) saturate(566%) hue-rotate(5deg) brightness(91%) contrast(89%);
            cursor: pointer;
        }

        .register-field textarea {
            min-height: 100px;
            resize: vertical;
        }

        .register-field input::placeholder,
        .register-field textarea::placeholder {
            color: rgba(255, 255, 255, 0.40);
        }

        .register-field input:focus,
        .register-field select:focus,
        .register-field textarea:focus {
            outline: none;
            border-color: rgba(196, 181, 253, 0.72);
            box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.12);
        }

        .register-plan-grid {
            display: grid;
            gap: 12px;
        }

        .register-plan-option {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            padding: 14px 16px;
            border-radius: 18px;
            border: 1px solid rgba(168, 85, 247, 0.16);
            background: rgba(255, 255, 255, 0.03);
            cursor: pointer;
        }

        .register-plan-option input {
            width: auto;
            margin-top: 3px;
            accent-color: #a78bfa;
        }

        .register-plan-option strong {
            display: block;
            color: var(--accent-text, #f0a6c4);
        }

        .register-plan-option.is-disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .register-plan-coming {
            display: block;
            margin-top: 4px;
            font-size: 0.72rem;
            color: #a0a0a0;
        }

        .register-check {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            color: #f5f5f5;
            font-size: 0.84rem;
            line-height: 1.8;
        }

        .register-check input {
            width: auto;
            margin-top: 4px;
            accent-color: #a78bfa;
        }

        .register-check a,
        .register-login-link {
            color: var(--accent-text, #f0a6c4);
            text-decoration: none;
        }

        .register-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 4px;
        }

        .register-submit,
        .register-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 54px;
            padding: 14px 18px;
            border-radius: 16px;
            text-decoration: none;
            font-weight: 700;
            transition: transform 0.12s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        }

        .register-submit {
            border: none;
            background: linear-gradient(135deg, var(--accent-grad-from, #e88bb2), var(--accent-grad-to, #a83d70));
            color: var(--on-accent-strong, #ffffff);
            box-shadow: 0 6px 14px rgba(0,0,0,.45), inset 0 1px 0 rgba(255,255,255,.20), inset 0 -1px 0 rgba(0,0,0,.18);
            cursor: pointer;
        }
        .register-submit:hover { filter: none; }
        .register-submit:active { transform: scale(.97); box-shadow: 0 2px 5px rgba(0,0,0,.45), inset 0 2px 4px rgba(0,0,0,.2); }

        .register-secondary {
            border: 1px solid rgba(var(--accent-rgb, 214, 112, 162), 0.40);
            background: rgba(255, 255, 255, 0.04);
            color: var(--accent-text, #f0a6c4);
        }
        .register-secondary:hover {
            background: rgba(var(--accent-rgb, 214, 112, 162), 0.10);
            border-color: rgba(var(--accent-rgb, 214, 112, 162), 0.60);
        }

        .register-submit:disabled {
            opacity: 0.7;
            cursor: progress;
            transform: none;
        }

        .register-submit-overlay {
            position: fixed;
            inset: 0;
            z-index: 5000;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(8, 4, 2, 0.78);
            backdrop-filter: blur(4px);
        }
        .register-submit-overlay.is-visible { display: flex; }
        .register-submit-overlay-inner {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
            padding: 28px 32px;
            border-radius: 18px;
            background: rgba(20, 14, 8, 0.92);
            border: 1px solid rgba(168, 85, 247, 0.32);
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.55);
            color: var(--accent-text, #f0a6c4);
            text-align: center;
            max-width: min(92vw, 360px);
        }
        .register-submit-overlay-text {
            margin: 0;
            font-size: 1.02rem;
            font-weight: 800;
            color: #ffe7a8;
        }
        .register-submit-overlay-sub {
            margin: 0;
            font-size: 0.78rem;
            line-height: 1.7;
            color: rgba(246, 217, 139, 0.78);
        }
        .register-submit-spinner {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 3px solid rgba(168, 85, 247, 0.22);
            border-top-color: #f4df9c;
            animation: register-submit-spin 0.9s linear infinite;
        }
        @keyframes register-submit-spin {
            to { transform: rotate(360deg); }
        }

        .register-login-link {
            text-align: center;
            font-size: 0.84rem;
            color: rgba(246, 217, 139, 0.9);
        }

        @media (max-width: 640px) {
            .register-page {
                padding-top: 18px;
            }

            .register-hero,
            .register-card {
                border-radius: 24px;
            }

            .register-hero {
                padding: 28px 20px 24px;
            }

            .register-card {
                padding: 20px 18px;
            }

            .register-grid-two,
            .register-grid-birth {
                grid-template-columns: 1fr;
            }

            .register-role-switch {
                grid-template-columns: 1fr;
            }
        }

        /* ============================================================
           ライトモード（標準）：新規登録画面を薄ラベンダー基調に反転
           ============================================================ */
        body.theme-light.page-auth-register.page-auth-register-cast,
        body.theme-light.page-auth-register.page-auth-register-shop {
            background:
                radial-gradient(circle at top left, rgba(167, 139, 250, 0.16), transparent 32%),
                linear-gradient(180deg, #f5f2fb 0%, #f5f2fb 100%);
        }
        body.theme-light.page-auth-register #bg-layer {
            background:
                radial-gradient(circle at 15% 10%, rgba(167, 139, 250, 0.12), transparent 22%),
                radial-gradient(circle at 82% 16%, rgba(124, 58, 237, 0.06), transparent 18%);
        }
        body.theme-light .register-hero,
        body.theme-light .register-card {
            border: 1px solid rgba(124, 58, 237, 0.18);
            background: #ffffff;
            box-shadow:
                0 12px 36px rgba(76, 29, 149, 0.10),
                inset 0 1px 0 rgba(255, 255, 255, 0.9);
        }
        body.theme-light .register-role {
            border: 1px solid rgba(124, 58, 237, 0.20);
            background: #ffffff;
            color: #574d6f;
        }
        body.theme-light .register-role.is-active {
            border-color: rgba(124, 58, 237, 0.50);
            background: rgba(124, 58, 237, 0.10);
            color: #5b21b6;
        }
        body.theme-light .register-title { color: #6d28d9; }
        body.theme-light .register-card-head h2 { color: #6d28d9; }
        body.theme-light .register-alert-success {
            border-color: rgba(5, 150, 105, 0.35);
            background: rgba(5, 150, 105, 0.08);
            color: #047857;
        }
        body.theme-light .register-alert-error {
            border-color: rgba(220, 38, 38, 0.35);
            background: rgba(220, 38, 38, 0.07);
            color: #b91c1c;
        }
        body.theme-light .register-chip span {
            border: 1px solid rgba(124, 58, 237, 0.24);
            background: #ffffff;
            color: #4b465c;
        }
        body.theme-light .register-chip input:checked + span {
            border-color: rgba(124, 58, 237, 0.55);
            background: rgba(124, 58, 237, 0.12);
            color: #5b21b6;
        }
        body.theme-light .register-radio,
        body.theme-light .register-field,
        body.theme-light .register-field span,
        body.theme-light .register-check {
            color: #4b465c;
        }
        body.theme-light .register-field em { color: #6d28d9; }
        body.theme-light .register-field-hint { color: #6d6685; }
        body.theme-light .register-field input,
        body.theme-light .register-field select,
        body.theme-light .register-field textarea {
            border: 1px solid rgba(124, 58, 237, 0.24);
            background: #ffffff;
            color: #4b465c;
        }
        body.theme-light .register-field input::placeholder,
        body.theme-light .register-field textarea::placeholder {
            color: rgba(36, 31, 51, 0.38);
        }
        body.theme-light .register-field input[type="date"] { color-scheme: light; }
        body.theme-light .register-field input[type="date"]::-webkit-calendar-picker-indicator { filter: none; }
        body.theme-light .register-field input:focus,
        body.theme-light .register-field select:focus,
        body.theme-light .register-field textarea:focus {
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.12);
        }
        body.theme-light .register-plan-option {
            border: 1px solid rgba(124, 58, 237, 0.20);
            background: #ffffff;
        }
        body.theme-light .register-plan-option strong { color: #6d28d9; }
        body.theme-light .register-plan-coming { color: #857ca0; }
        body.theme-light .register-check a,
        body.theme-light .register-login-link { color: #6d28d9; }
        body.theme-light .register-secondary {
            border: 1px solid rgba(124, 58, 237, 0.30);
            background: #ffffff;
            color: #6d28d9;
        }
        body.theme-light .register-submit {
            box-shadow: 0 6px 14px rgba(76, 29, 149, 0.22), inset 0 1px 0 rgba(255, 255, 255, 0.30);
        }
        body.theme-light .register-logo { filter: drop-shadow(0 6px 16px rgba(76, 29, 149, 0.18)); }

        /* ============================================================
           ★ 新規登録 UI 大幅リニューアル (2026-07-20)
           ステップ式ウィザード + 洗練されたフォーム体験
           ============================================================ */
        body.page-auth-register {
            background: linear-gradient(160deg, #f8f5ff 0%, #eee6fb 100%) !important;
            min-height: 100dvh;
        }
        body.page-auth-register main#main-content { padding: 0 !important; }

        .register-page {
            width: 100%;
            max-width: 560px;
            margin: 0 auto;
            padding: 24px 16px 140px;  /* 下部固定ナビ分の余白 */
        }
        /* ヒーロー：ロゴ控えめ、タイトル大きく */
        .register-hero {
            text-align: center;
            padding: 8px 0 20px !important;
            background: transparent !important;
            border: 0 !important;
            box-shadow: none !important;
        }
        .register-hero .register-logo {
            width: 140px !important;
            max-width: 60% !important;
            margin: 0 auto 10px !important;
            filter: drop-shadow(0 3px 10px rgba(124, 58, 237, 0.18)) !important;
        }
        .register-hero .register-title {
            margin: 0 !important;
            font-size: 1.35rem !important;
            font-weight: 800;
            color: #4c1d95 !important;
            letter-spacing: 0.02em;
        }

        /* ウィザード：進捗バー + STEP n/N + タイトル */
        .rw-header {
            position: sticky;
            top: 0;
            z-index: 5;
            padding: 12px 4px 14px;
            background: linear-gradient(180deg, rgba(248, 245, 255, 0.98) 60%, rgba(248, 245, 255, 0.4) 100%);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            margin-bottom: 10px;
        }
        .rw-progress {
            height: 5px;
            border-radius: 999px;
            background: rgba(124, 58, 237, 0.14);
            overflow: hidden;
        }
        .rw-progress__bar {
            display: block;
            width: 0;
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #a78bfa, #7c3aed);
            box-shadow: 0 0 12px rgba(124, 58, 237, 0.35);
            transition: width 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .rw-progress__meta {
            display: flex;
            align-items: baseline;
            gap: 12px;
            margin-top: 10px;
            padding: 0 4px;
        }
        .rw-step-num {
            font-family: 'Cinzel', 'Playfair Display', serif;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            color: #7c3aed;
        }
        .rw-step-title {
            font-size: 1.05rem;
            font-weight: 800;
            color: #4c1d95;
            letter-spacing: 0.01em;
        }

        /* カード：白面+紫影+丸め */
        body.page-auth-register .register-card {
            border-radius: 20px !important;
            border: 1px solid rgba(124, 58, 237, 0.14) !important;
            background: #ffffff !important;
            box-shadow: 0 12px 32px rgba(76, 29, 149, 0.10), inset 0 1px 0 rgba(255, 255, 255, 0.9) !important;
            padding: 22px 20px !important;
            margin-bottom: 14px;
        }
        body.page-auth-register .register-card-head { margin-bottom: 16px; }
        body.page-auth-register .register-card-head h2 {
            font-size: 0.72rem !important;
            font-weight: 800;
            letter-spacing: 0.20em;
            text-transform: uppercase;
            color: #7c3aed !important;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(124, 58, 237, 0.10);
        }

        /* フィールド */
        body.page-auth-register .register-field { gap: 6px; margin-bottom: 4px; }
        body.page-auth-register .register-field > span {
            font-size: 0.82rem !important;
            font-weight: 700 !important;
            color: #4c1d95 !important;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        body.page-auth-register .register-field > span em {
            font-style: normal;
            font-size: 0.62rem;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 999px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: #ffffff !important;
            margin-left: 4px !important;
            letter-spacing: 0.06em;
        }
        body.page-auth-register .register-field input[type="text"],
        body.page-auth-register .register-field input[type="email"],
        body.page-auth-register .register-field input[type="tel"],
        body.page-auth-register .register-field input[type="password"],
        body.page-auth-register .register-field input[type="date"],
        body.page-auth-register .register-field input[type="number"],
        body.page-auth-register .register-field select,
        body.page-auth-register .register-field textarea {
            width: 100%;
            padding: 14px 16px !important;
            border-radius: 12px !important;
            border: 1.5px solid rgba(124, 58, 237, 0.22) !important;
            background: #faf7ff !important;
            color: #1e1a2e !important;
            font-size: 16px !important;
            font-weight: 500;
            min-height: 52px;
            line-height: 1.5;
            transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1);
        }
        body.page-auth-register .register-field input:focus,
        body.page-auth-register .register-field select:focus,
        body.page-auth-register .register-field textarea:focus {
            outline: none !important;
            border-color: #7c3aed !important;
            background: #ffffff !important;
            box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.12), 0 4px 12px rgba(124, 58, 237, 0.08) !important;
            transform: translateY(-1px);
        }
        body.page-auth-register .register-field input::placeholder,
        body.page-auth-register .register-field textarea::placeholder {
            color: rgba(76, 29, 149, 0.30) !important;
            font-weight: 400;
        }
        body.page-auth-register .register-field-hint {
            font-size: 0.72rem !important;
            color: #6d6685 !important;
            line-height: 1.6;
            margin-top: 2px;
        }
        /* 必須未入力の強調 */
        body.page-auth-register .register-field.is-missing input,
        body.page-auth-register .register-field.is-missing select,
        body.page-auth-register .register-field.is-missing textarea {
            border-color: #ef4444 !important;
            background: rgba(239, 68, 68, 0.04) !important;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.10) !important;
        }
        body.page-auth-register .register-card.is-missing {
            border-color: rgba(239, 68, 68, 0.35) !important;
        }

        /* ファイル入力 */
        body.page-auth-register .register-field input[type="file"] {
            padding: 12px 14px !important;
            background: #faf7ff !important;
            border: 1.5px dashed rgba(124, 58, 237, 0.35) !important;
            border-radius: 12px !important;
            font-size: 0.85rem !important;
            cursor: pointer;
            min-height: 56px;
        }
        body.page-auth-register .register-field input[type="file"]:hover {
            background: rgba(124, 58, 237, 0.06) !important;
            border-color: #7c3aed !important;
        }

        /* チップグリッド */
        body.page-auth-register .register-chip-grid { gap: 8px; }
        body.page-auth-register .register-chip span {
            padding: 10px 16px !important;
            font-size: 0.86rem !important;
            font-weight: 600;
            border-radius: 12px !important;
            border: 1.5px solid rgba(124, 58, 237, 0.22) !important;
            background: #faf7ff !important;
            color: #4c1d95 !important;
            transition: all 0.15s;
        }
        body.page-auth-register .register-chip input:checked + span {
            background: linear-gradient(135deg, #a78bfa, #7c3aed) !important;
            border-color: transparent !important;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.30);
            transform: translateY(-1px);
        }

        /* スキップトグル */
        body.page-auth-register .register-skip-toggle {
            border: 1px solid rgba(124, 58, 237, 0.18) !important;
            background: rgba(124, 58, 237, 0.04) !important;
            color: #4c1d95 !important;
            border-radius: 12px !important;
            padding: 12px 14px !important;
        }

        /* 3サイズ・身長体重の共通スタイルは form-controls.css の §数値入力コンポーネント
           に統一済み（2026-08-01）。ここではラベル色だけ register 用に上書き。 */
        body.page-auth-register .metric-field-label,
        body.page-auth-register .bwh-group > span {
            color: #4c1d95 !important;
        }

        /* アラート */
        body.page-auth-register .register-alert {
            border-radius: 14px !important;
            border-width: 1.5px !important;
            padding: 14px 18px !important;
            font-weight: 600;
        }
        body.page-auth-register .register-alert-error {
            background: rgba(239, 68, 68, 0.06) !important;
            border-color: rgba(239, 68, 68, 0.40) !important;
            color: #b91c1c !important;
        }
        body.page-auth-register .register-alert-success {
            background: rgba(5, 150, 105, 0.06) !important;
            border-color: rgba(5, 150, 105, 0.40) !important;
            color: #047857 !important;
        }

        /* 同意チェック */
        body.page-auth-register .register-check {
            font-size: 0.88rem;
            color: #4c1d95;
            line-height: 1.65;
        }
        body.page-auth-register .register-check a { color: #7c3aed; text-decoration: underline; font-weight: 700; }
        body.page-auth-register .register-check input {
            width: 22px !important; height: 22px !important;
            accent-color: #7c3aed;
        }

        /* ---------- ウィザード：下部固定ナビ ---------- */
        .register-actions--wizard-hidden { display: none !important; }
        .rw-nav {
            position: fixed;
            left: 0; right: 0; bottom: 0;
            z-index: 50;
            padding: 12px max(16px, env(safe-area-inset-left)) calc(12px + env(safe-area-inset-bottom));
            background: linear-gradient(0deg, rgba(255, 255, 255, 0.98) 60%, rgba(255, 255, 255, 0));
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-top: 1px solid rgba(124, 58, 237, 0.10);
        }
        .rw-nav__buttons {
            display: flex;
            gap: 10px;
            max-width: 560px;
            margin: 0 auto;
        }
        .rw-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 54px;
            padding: 0 20px;
            border-radius: 14px;
            font-size: 0.95rem;
            font-weight: 800;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .rw-btn--back {
            flex: 0 0 auto;
            min-width: 90px;
            background: #ffffff;
            border: 1.5px solid rgba(124, 58, 237, 0.30);
            color: #6d28d9;
        }
        .rw-btn--back:hover { background: rgba(124, 58, 237, 0.06); border-color: #7c3aed; }
        .rw-btn--back:disabled { opacity: 0.35; cursor: not-allowed; }
        .rw-btn--next,
        .rw-btn--submit {
            flex: 1 1 auto;
            background: linear-gradient(135deg, var(--accent-grad-from, #a78bfa), var(--accent-grad-to, #7c3aed));
            color: var(--on-accent-strong, #ffffff);
            border: 0;
            box-shadow:
                0 6px 14px rgba(0, 0, 0, 0.20),
                inset 0 1px 0 rgba(255, 255, 255, 0.20),
                inset 0 -1px 0 rgba(0, 0, 0, 0.10);
        }
        .rw-btn--next:hover,
        .rw-btn--submit:hover { filter: none; }
        .rw-btn--next:active,
        .rw-btn--submit:active {
            transform: scale(0.97);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.30), inset 0 2px 4px rgba(0, 0, 0, 0.15);
        }
        .rw-btn[hidden] { display: none !important; }
        .rw-error {
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 0 auto 10px;
            padding: 8px 14px;
            max-width: 560px;
            border-radius: 10px;
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.28);
            color: #b91c1c;
            font-size: 0.82rem;
            font-weight: 700;
        }
        .rw-error[hidden] { display: none; }
        .rw-error i { font-size: 0.9rem; }



        /* ★★ 先進的な機能スタイル（2026-07-20 追加） */
        /* 入力欄の右端に置く ✓/× マーク */
        .rw-input-wrap { position: relative; display: block; width: 100%; }
        .rw-input-wrap > input { padding-right: 40px !important; }
        .rw-inline-mark {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            width: 22px; height: 22px;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 0.72rem;
            color: #ffffff;
            pointer-events: none;
        }
        .rw-inline-mark.is-ok { display: inline-flex; background: #059669; }
        .rw-inline-mark.is-ng { display: inline-flex; background: #ef4444; }
        .rw-inline-hint {
            display: block;
            margin-top: 4px;
            font-size: 0.72rem;
            font-weight: 700;
            color: #b91c1c;
        }
        /* パスワード強度メーター */
        .rw-strength {
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .rw-strength__bars {
            display: flex;
            gap: 4px;
            flex: 1;
        }
        .rw-strength__bars > span {
            flex: 1;
            height: 4px;
            border-radius: 999px;
            background: rgba(124, 58, 237, 0.14);
        }
        .rw-strength--1 .rw-strength__bars > span:nth-child(-n+1) { background: #ef4444; }
        .rw-strength--2 .rw-strength__bars > span:nth-child(-n+2) { background: #f59e0b; }
        .rw-strength--3 .rw-strength__bars > span:nth-child(-n+3) { background: #7c3aed; }
        .rw-strength--4 .rw-strength__bars > span { background: #059669; }
        .rw-strength__label {
            font-size: 0.72rem;
            font-weight: 800;
            color: #6d6685;
            min-width: 68px;
            text-align: right;
        }
        .rw-strength--1 .rw-strength__label { color: #ef4444; }
        .rw-strength--2 .rw-strength__label { color: #b45309; }
        .rw-strength--3 .rw-strength__label { color: #7c3aed; }
        .rw-strength--4 .rw-strength__label { color: #059669; }

        /* 年齢バッジ */
        .rw-age-badge {
            display: inline-block;
            margin-left: 8px;
            padding: 3px 12px;
            border-radius: 999px;
            background: linear-gradient(135deg, #a78bfa, #7c3aed);
            color: #ffffff;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.04em;
        }
        .rw-age-badge.is-under {
            background: linear-gradient(135deg, #f87171, #dc2626);
        }

        /* 候補プリセット（AI補完） */
        .rw-suggest {
            margin-top: 8px;
            padding: 10px 12px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(167, 139, 250, 0.08), rgba(124, 58, 237, 0.04));
            border: 1px dashed rgba(124, 58, 237, 0.28);
        }
        .rw-suggest__head {
            font-size: 0.72rem;
            font-weight: 800;
            color: #6d28d9;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .rw-suggest__head i { color: #a78bfa; }
        .rw-suggest__list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .rw-suggest__item {
            font-family: inherit;
            font-size: 0.76rem;
            font-weight: 600;
            padding: 8px 12px;
            border-radius: 999px;
            background: #ffffff;
            border: 1px solid rgba(124, 58, 237, 0.30);
            color: #4c1d95;
            cursor: pointer;
            transition: all 0.15s;
            text-align: left;
            line-height: 1.4;
            max-width: 100%;
        }
        .rw-suggest__item:hover {
            background: rgba(124, 58, 237, 0.08);
            border-color: #7c3aed;
            transform: translateY(-1px);
        }

        /* ドラフト復元トースト */
        .rw-toast {
            position: fixed;
            top: 16px;
            left: 50%;
            transform: translate(-50%, -20px);
            z-index: 200;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px 10px 16px;
            border-radius: 999px;
            background: linear-gradient(135deg, #a78bfa, #7c3aed);
            color: #ffffff;
            font-size: 0.82rem;
            font-weight: 700;
            box-shadow: 0 10px 24px rgba(124, 58, 237, 0.35);
            opacity: 0;
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.3s;
        }
        .rw-toast.is-visible { transform: translate(-50%, 0); opacity: 1; }
        .rw-toast__x {
            border: 0;
            background: rgba(255, 255, 255, 0.20);
            color: #ffffff;
            width: 26px; height: 26px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .rw-toast__x:hover { background: rgba(255, 255, 255, 0.32); }

        /* ステップ入力充足率チップ */
        .rw-fill-pct {
            margin-left: auto;
            padding: 3px 10px;
            border-radius: 999px;
            background: rgba(124, 58, 237, 0.10);
            border: 1px solid rgba(124, 58, 237, 0.28);
            color: #6d28d9;
            font-size: 0.7rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }

                /* ★ モダン入力UI強化（2026-07-20 追加） */
        /* 自動保存ヒント */
        .rw-draft-hint {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin: 8px 4px 0;
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(124, 58, 237, 0.06);
            border: 1px solid rgba(124, 58, 237, 0.14);
            color: #6d28d9;
            font-size: 0.7rem;
            font-weight: 700;
        }
        .rw-draft-hint i { font-size: 0.72rem; color: #7c3aed; }
        /* パスワード表示切替 */
        .rw-pass-wrap {
            position: relative;
            display: block;
            width: 100%;
        }
        .rw-pass-wrap > input { padding-right: 48px !important; }
        .rw-pass-toggle {
            position: absolute;
            top: 50%;
            right: 8px;
            transform: translateY(-50%);
            width: 36px; height: 36px;
            border: 0;
            background: transparent;
            border-radius: 10px;
            color: #7c3aed;
            font-size: 0.95rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.12s ease;
        }
        .rw-pass-toggle:hover { background: rgba(124, 58, 237, 0.10); }
        /* 入力欄のフォーカスマイクロインタラクション拡張 */
        body.page-auth-register .register-field select {
            background-image: linear-gradient(45deg, transparent 50%, #7c3aed 50%),
                              linear-gradient(135deg, #7c3aed 50%, transparent 50%);
            background-position: calc(100% - 20px) calc(50% - 3px), calc(100% - 14px) calc(50% - 3px);
            background-size: 6px 6px, 6px 6px;
            background-repeat: no-repeat;
            padding-right: 40px !important;
            appearance: none;
            -webkit-appearance: none;
        }
        /* テキストエリア：最低高さ + リサイズ縦のみ */
        body.page-auth-register .register-field textarea {
            min-height: 96px !important;
            resize: vertical;
        }
        /* 日付入力：カレンダーアイコンを紫に */
        body.page-auth-register .register-field input[type="date"] {
            color-scheme: light;
        }
        body.page-auth-register .register-field input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(30%) sepia(90%) saturate(1500%) hue-rotate(255deg);
            cursor: pointer;
        }
        /* 郵便番号：数字らしい tabular-nums */
        body.page-auth-register .register-field input[name="zip"],
        body.page-auth-register .register-field input[name="phone"],
        body.page-auth-register .register-field input[type="number"] {
            font-variant-numeric: tabular-nums;
            letter-spacing: 0.02em;
        }
        /* Grid の gap を統一 */
        body.page-auth-register .register-grid { gap: 14px !important; }

        @media (max-width: 480px) {
            .register-hero .register-title { font-size: 1.2rem !important; }
            body.page-auth-register .register-card { padding: 18px 16px !important; }
            .rw-nav { padding: 10px 12px calc(10px + env(safe-area-inset-bottom)); }
            .rw-btn { min-height: 50px; font-size: 0.9rem; }
        }
    </style>

    <script src="{{ asset('assets/js/register-wizard.js') }}?v=20260924-back-escape"></script>

@endsection
