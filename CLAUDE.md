# ミセチョク — Claude Code プロジェクト設定

## プロジェクト概要

水商売・夜職特化のマッチングプラットフォーム。
キャスト（求職者）と店舗（求人）をマッチングし、採用ボーナスの請求・振込、
店舗の Premium プラン契約、書類審査までを一貫して管理する。

- **フレームワーク**: Laravel 9（PHP ^8.0.2）
- **テンプレートエンジン**: Blade
- **フロントエンド**: Vanilla JS（一部 jQuery）、Tailwind CSS v4（CSS-first config / @tailwindcss/cli）
- **ビルド**: Tailwind は CLI で `public/assets/css/tailwind.css` に直接出力（Vite はバイパス運用中）
- **DB**: MySQL 8.0（テストは SQLite）
- **PDF**: `barryvdh/laravel-dompdf`（未導入環境では印刷用 HTML にフォールバック）
- **Push**: `minishlink/web-push`（VAPID / Web Push API）
- **外部連携**: LINE Login + Messaging API Webhook、ジオコーディング API
- **デプロイ先**: Plesk サーバ
- **認証**: キャスト / 店舗（オーナー・スタッフ）/ 管理者（admin・staff） の3ロール

---

## ディレクトリ構成（主要部分）

```
app/
  Http/
    Controllers/
      Admin/          # 運営ポータル（Dashboard, Deposit, Invoice, Sales, Master, Shop, Cast,
                      #  NgWord, Notice, Column, Task, AdminAccount, Verification, Bank, Policy,
                      #  NotificationSpec, CharacterGuide, PlanSubscription, SupportInquiry,
                      #  UserReport, Inquiry ...）
      Auth/           # 認証（Cast/LoginController, Shop/LoginController,
                      #  LineLoginController, PasswordResetController, EmailVerificationController）
      Casts/          # キャスト機能（Profile, Mypage, Search, Recruitment, AiChat, Identity）
      Shops/          # 店舗機能（Profile, Mypage, Search, Recruitment, Review, Interaction,
                      #  Staff, Viewer）
      Common/         # ロール共通（Talk, Discovery, Registration, DemoLogin, Setting,
                      #  SupportInquiry, SupportNotice, ColumnArticle, Tutorial, Location,
                      #  BankLookup, Page, TalkTemplate, Notification, UserReport, Suspended）
      Api/            # Push 通知（PushController）、キープ（FavoriteController）
      LineWebhookController.php       # LINE Messaging API Webhook
    Middleware/
      MemberAuth / ShopAuth / ShopOwner / AdminAuth / AdminPermission
  Support/
    TalkActionRegistry.php            # トークアクションの権限マトリクス（cast/shop/both）
  Services/
    # 課金・審査系
    BillingManagementService.php      # 採用ボーナスの請求・振込ロジック
    PlanSubscriptionService.php       # Premium プラン契約・機能ゲート
    DocumentReviewService.php         # 本人確認・店舗許可証の審査
    InvoiceTemplateSettingsService.php
    RecruitPublicationService.php     # 求人公開・非公開切替
    ShopMemberService.php             # 店舗スタッフ（1店舗 N ログイン）管理
    ReviewPortalService.php           # レビュー投稿・返信のドメインロジック
    KeepListService.php               # キープ機能（旧 LIKE 廃止）
    ProfileViewService.php            # プロフィール閲覧記録
    UserTaskService.php               # 「やることリスト」（都度計算・保存なし）
    NotificationService.php / NotificationPreferenceService.php / NotificationSpecService.php
    PushNotificationService.php       # Web Push（VAPID / web-push）
    # 検索・レコメンド
    SearchScoringService.php          # マッチ度計算・Premium 優先グループ
    RecommendationService.php         # AI レコメンド（cast → shop）
    CastSearchPreferenceService.php / ShopSearchPreferenceService.php
    UserLocationService.php / ShopProfileLocationSyncService.php
    GeocodingService.php / StationService.php
    # メッセージング・AI
    LlmChatService.php / AiChatTemplateService.php / AiConciergeService.php
    MessageTemplateService.php
    NgWordDetector.php                # 電話 / LINE ID / URL / NG 語検出
    # LINE 連携
    LineLoginService.php / LineMessageService.php / LineNotificationService.php
    # 管理・運用
    AdminMasterService.php / AdminOperationalSummaryService.php
    AdminOperationLogService.php / AdminPermissionService.php / AdminPrivateAccessService.php
    CharacterGuideService.php         # オコジョガイド（画面別説明）
    ColumnArticleStorageService.php   # コラム記事の Markdown ストア
    BankLookupService.php             # 銀行・支店マスタ
    PersonalityTypeCatalog.php        # 相性診断のカタログ
    ShopJobApplicationJobSnapshotService.php  # 応募時の求人スナップショット
    AuthService.php
  Models/
    Cast.php / Shop.php / Manager.php / Member.php     # 認証主体
    ShopManager.php                                    # 店舗スタッフ（1店舗 N ログイン）
    CastProfile.php / ShopProfile.php
    CastImage.php / ShopImage.php / CastProvider.php
    CastIdentityDocument.php / ShopLicenseDocument.php # 本人確認・許可証
    ShopJob.php / ShopJobApplication.php               # 求人・応募
    ApplicationDeposit.php / ApplicationDepositHistory.php / PaymentTask.php
    Message.php / TalkBlock.php / UserTalkTemplate.php # トーク
    Favorite.php                                       # キープ
    ProfileView.php                                    # プロフィール閲覧ログ
    Review.php / ReviewContent.php / ReviewDetail.php
    ShopPlanSubscription.php                           # 店舗 Premium プラン契約
    Notice.php / Notification.php                      # おしらせ・通知
    UserReport.php / SupportInquiry.php                # 通報・問合せ
    NgWord.php / Industry.php / Job.php
    BankAccount.php / SystemAccount.php
    CharacterGuideSetting.php
    PolicyDocument.php / PolicyChapter.php / PolicyRevision.php  # 規約管理
    ColumnArticle.php / ShopPost.php
    Master/…                                           # マスタ CRUD 用モデル群

resources/views/
  layouts/
    app-v2.blade.php   # 現行の唯一のフロント用レイアウト（ライト/ダーク/プレミアムホワイト判定）
    admin.blade.php    # 管理画面用レイアウト
    lp.blade.php       # LP 用
    parts/             # header / sub-header / footer / sidebar / character-guide /
                       # interview-reminder-banner / location-pill / header_popover
  common/              # ロール共通（トーク、検索、設定、サポート、welcome、tutorial 等）
  casts/               # キャスト向け
  shops/               # 店舗向け
  admin/               # 管理者
  billing/             # 請求書・領収書テンプレート（PDF / 印刷 HTML）
  emails/              # パスワードリセット・メール認証
  errors/              # 4xx / 5xx
  lp/                  # LP

resources/css/
  app.css              # Tailwind v4 の @theme トークン正本

public/assets/
  css/
    tailwind.css              # Tailwind CLI の出力先（Blade を @source で走査）
    light-theme.css           # ライトモード上書き（§1-14）
    premium-white.css         # プレミアムホワイト（MyPage / プロフィール詳細）
    admin.css / admin-mobile.css / admin-detail.css / admin-policy-page.css
    ui-consistency.css        # ボタン・バッジ・共通部品の統一（レガシー吸収）
    home.css / search.css / talk.css / talk-light.css / mypage.css / recruitment.css …
    modal-readability.css / motion.css / form-controls.css / form-enhance.css …
  js/
    home.js / search.js / talk-room.js / talk-list.js / character-guide.js / motion.js …

database/
  schema.sql           # 本番適用用（スキーマ定義の正）
  mock_demo.sql        # スキーマ + 参照テーブル + 最小データ（初期構築時これ 1 本）
  test_reset.sql       # ### demo function and data for test ### テストデータ投入
  test_reset_images.sql# Unsplash 写真 ID キュレーション（テスト用画像 URL）
  testing.sqlite       # ローカル自動テスト用 SQLite
  factories/ seeders/
```

---

## ロール・ルートの対応

| ロール | URL プレフィックス | ミドルウェア |
|--------|-----------------|-------------|
| キャスト | `/cast/*` | `member.auth` |
| 店舗（共通） | `/shop/*` | `shop.auth` |
| 店舗オーナー限定 | `/shop/profile/*`, `/shop/recruits/{edit,update,toggle-status}`, `/shop/mypage/{documents 提出, payment/*, deposit/*, staff/{create,store,destroy}}` | `shop.auth` + `shop.owner` |
| 管理者 | `/admin/*` | `admin.auth` + `admin.permission:<key>` |
| 共通 | `/setting/*`, `/subscription`, `/support/*`, `/api/*`, `/notifications/*`, `/share/*` | ログイン共通（一部は未ログイン許可） |

### 管理者権限キー（`admin.permission:<key>`）
`AdminPermissionService::permissionCatalog()` が正。主なキー:

- `dashboard.view`
- `operations.invoices` / `operations.deposits` / `operations.verification` / `operations.inquiries`
- `content.notices` / `content.columns`
- `master.ngwords` / `master.masters` / `master.notification_spec` / `master.character_guide` / `master.talk_quick_replies`
- `analytics.sales`
- `accounts.shops.view|manage|private` / `accounts.casts.view|manage|private` / `accounts.admins`
- `policies.manage`

`admin` ロールは全許可（`locked`）。`staff` はチェックボックスで個別許可。

---

## 開発ルール

### テスト
- テスト方針・実装ルールは `AUTO-TEST.md` を参照
- スモークテストは `composer test:smoke`（`.env.testing` + `database/testing.sqlite`）
- Feature テストは `tests/Feature/**`（`php artisan test --testsuite=Feature`）

### テスト用機能（本番デプロイ時に除外すべきもの）
本番運用では意図しない動作 / 情報漏洩を招く可能性があるため、以下は**「テスト用」と明記**して開発・
デモ用途に限定する。新規追加時も同じルールで**用途と本番での扱い**をコメント / ドキュメントに残すこと。

**マーカー**: ファイル冒頭コメント / セクション見出しに以下の一行を必ず入れる（英語 ASCII でエディタ検索性を確保）:

```
### demo function and data for test ###
```

- **DEMO_MODE**（`config/demo.php`） — `.env` の `DEMO_MODE=true` で以下 3 種の
  外部テスター向けモックを有効化する。**本番は必ず `DEMO_MODE=false`**。
  - `DEMO_AUTO_VERIFY_EMAIL=true`: `EmailVerificationController::send()` が SMTP を
    スキップし、`email_verified_at` を即時更新
  - `DEMO_MOCK_LINE=true`: `MockLineController` の `/login/line/mock`（GET/POST）と
    `/setting/line/mock-link`（POST）が有効化される。provider_id は `mock:` プレフィックス
  - `DEMO_TEST_PUSH=true`: `POST /api/push/test` で自分にテスト Web Push を送信可能
    （事前に `/api/push/subscribe` で端末登録が必要）
- **デモ用ログイン画面** — `/login/demo`（`App\Http\Controllers\Common\DemoLoginController`、view `resources/views/common/demo-login.blade.php`）。
  1画面から複数ロール（cast / shop / admin）へワンクリックログインできる利便性優先の画面で、
  正式なログイン画面（`cast.login` / `shop.login` / `admin.login`）とは別扱い。**本番デプロイ時は
  ルート除外 or `.env` フラグでガード**すること。
- **テスト用データセット** — `database/test_reset.sql` / `database/test_reset_images.sql`。
  ユーザースケールのテーブルを TRUNCATE し、`cast01@test.jp` … `shop01@test.jp` … などの
  テスト用パーソナを投入。**本番 DB には絶対に流さない**。
- **`@test.jp` ドメインのメールアドレス** — テスト用アカウントで使用中。本番の実ユーザー登録時に
  意図せず同ドメインが混入しないよう、必要に応じて登録バリデーションで拒否できるよう検討。
- **外部プレースホルダー画像 URL**（`images.unsplash.com` — `database/test_reset_images.sql` で
  キュレートされた Unsplash 写真 ID を 1200x1500 の 4:5 クロップで読み込む）— テストデータの
  `cast_images.image_path` / `shop_images.image_path` に格納されている URL は検証用のフリー素材。
- 新しくテスト用機能・エンドポイント・シード等を追加する場合は、**上記マーカーをファイル冒頭に必ず記述**し、
  本セクションにも追記すること。

### コメント記述ルール（非 md ファイル）
- **PHP / JS / CSS / SQL / Blade（Blade は制約が厳しめ）ファイル内のコメント・識別子は原則英語（ASCII）**。
  Windows / Plesk 環境や別ツールでファイルを開いた時の文字化けを防ぐため。
- Blade テンプレートの**ユーザー向け文言**（`{{ }}` の中のラベル・ボタン名等）は日本語で OK。
- コミット時のログ・エラーメッセージ・変数名は英語で。
- CLAUDE.md / DESIGN.md / AUTO-TEST.md / SPEC.md / Agent.md 等の**ドキュメントは日本語 OK**。

### エージェント運用
- Claude Code のサブエージェント（Explore / Plan / general-purpose）の使い分けは `Agent.md` を参照

### Git
- Git コマンドは開発者が個別に実行する。Claude は git 操作を一切行わない。
- コミットメッセージの形式やブランチ命名などの規約:
  - ブランチ: `feature/SCR-xxx-機能名`
  - コミット prefix: `feat` / `fix` / `refactor` / `style` / `test` / `docs`
  - 例: `feat(SCR-xxx): 機能名の実装`

### DB 操作
- DB 操作は Claude が直接実行しない。
- マイグレーションやデータ変更が必要な場合、**MySQL 8.0 対応の SQL 文を生成して開発者に渡し、実行を促す**。
- スキーマ変更（CREATE TABLE / ALTER TABLE / DROP 等）を行った場合、`database/schema.sql` と `database/mock_demo.sql` を **両方** 最新化する。
- 生クエリは避け、Eloquent ORM を使用する。
- **最新のテーブル構造は `database/mock_demo.sql` を参照**。コード・ロジック・テストを書く際は必ずこのファイルのスキーマと照合し、カラム名・型・制約の不整合があれば指摘または修正すること。
- **`database/` に置く SQL は 4 種類のみ**：
  1. `schema.sql` — スキーマ定義（初期構築時 / 本番適用参照）
  2. `mock_demo.sql` — スキーマ + 参照テーブル + 最小データ（初期構築時。これ 1 本で全部入る）
  3. `test_reset.sql` — テストデータ投入（`mock_demo.sql` を流した後で使用）
  4. `test_reset_images.sql` — テストデータの画像 URL キュレーション
- 追加のマイグレーション用 SQL（ALTER TABLE 等）は**上記ファイルに直接反映**する。単発 SQL ファイルを新設して残さない。
- サービスクラスは `Schema::hasTable(...)` で DB 未準備を安全に扱う（`PlanSubscriptionService` / `CharacterGuideService` / `AdminPermissionService` などが実装済み）。

### デザイン
- デザインは `DESIGN.md` の定義に従う。コード・ビュー生成時は必ず参照すること。
- 深階層ページのタイトルは **ヘッダー中央（日本語）** に統一。ページ内の h1・説明文は置かない。
- 説明文は **オコジョガイド**（`character_guide_settings` テーブル + `resources/views/layouts/parts/character-guide.blade.php`）に集約する。ページ内に `p.page-lead` 等を新設しない。
- 画面テーマ（ライト / ダーク / プレミアムホワイト）は `layouts/app-v2.blade.php` の以下フラグで自動判定される：
  - **ダーク画面**: `*/home*`（SWIPE）と `share/*`（公開共有）のみ
  - **プレミアムホワイト**: `cast.mypage.index` / `shop.mypage.index` / `cast.shopprofile.show` / `shop.castprofileview.show`
  - **ライト画面**: 上記以外すべて（検索・トーク・採用/入金管理・書類・設定・サポート・コラム・認証・Premium 画面）
  - ユーザーがヘッダーのライト/ダークトグルでダーク強制した場合（Cookie `theme_mode=dark`）は上記に関わらずダーク維持
  - ライト画面向けの上書きは `public/assets/css/light-theme.css` に集約（§1 トークン反転 / §2 クローム維持 / §3-14 個別コンポーネント）
  - プレミアムホワイトの上書きは `public/assets/css/premium-white.css` に集約

### Tailwind CSS（v4）
- Tailwind v4 を使用。`tailwind.config.js` は **使わない**（v4 から CSS-first config に変更）。
- 設定の正本は `resources/css/app.css`:
  - `@import "tailwindcss";` で本体を読み込む
  - `@source "../views";` で Blade を走査対象に追加
  - 色・影などのデザイントークンは `@theme { ... }` ブロックに集約
- ビルドは **`@tailwindcss/cli` 直叩き** で `public/assets/css/tailwind.css` に出力（Vite は使わない）。
  - 本番: `npm run tw:build`（minify あり）
  - 開発: `npm run tw:watch`（差分監視）
- ビュー側からは `<link rel="stylesheet" href="{{ asset('assets/css/tailwind.css') }}">` で読み込む。
  - 既存のページ固有 CSS **より前** に置く（preflight を既存スタイルで上書き可能にして回帰を防ぐ）。
- PostCSS 設定や `@tailwind base/components/utilities` 旧ディレクティブは **書かない**。
- Blade を新規追加・移動したら `npm run tw:build` を必ず再実行（CLI は起動時の `@source` 走査結果でクラスを抽出するため）。

### 静的アセットのキャッシュバスト
- 全アセットは `?v=YYYYMMDD-識別子` 付きで読み込む。
- グローバルは `layouts/app-v2.blade.php` の `$assetVersion` を進める（例: `20260802-phase3`）。個別 CSS/JS は個別に `?v=` を付ける。
- Blade 変更後は毎回 `php artisan view:clear` を実行する。

### コーディング規約
- PSR-12 準拠（PHP）
- コントローラは薄く、ロジックはサービスクラスに集約
- バリデーションは FormRequest または `$request->validate()` を使用
- Blade テンプレートは `@component` / `@include` を活用
- レート制限が必要な入口は `throttle:*` で保護（`login.post` = 5/15min、`support.form.submit` = 5/60min、`user-report.store` = 20/60min、`auth.email.send` = 6/60min）

### 命名規則
- コントローラ: `PascalCase` + `Controller` suffix
- モデル: `PascalCase` 単数形
- テーブル: `snake_case` 複数形
- ルート名: `role.resource.action`（例: `shop.recruits.edit`）
- JS ファイル: `kebab-case.js`
- ID プレフィックス:
  - キャスト = `c0001`（`Cast.id`）
  - 店舗 = `s0001`（`Shop.id`）
  - 店舗スタッフログイン = `m0001`（`ShopManager.id` / `Member.id`）
  - 管理者 = `admin` / `staff`

### 重要な業務ロジック
- **採用ボーナス**: `application_deposits` テーブルで管理、ステータス履歴は `application_deposit_histories`。
  ステータス遷移は `BillingManagementService` のクラス定数（`STATUS_CAST_REQUESTED` … `STATUS_COMPLETED`）で管理。システム手数料 10% / 銀行振込手数料 220 円 / 請求期限 7 日。請求書番号は `INV-YYYYMM-{id:04d}`。
- **トークアクション**: `App\Support\TalkActionRegistry` に権限マトリクスを集約。`TalkController@action` の
  `action_type` は以下:
  - キャストのみ: `interview_confirm` / `interview_cancel_accept` / `fulltime_request` / `work_complete_report` / `bonus_achievement_report`
  - 店舗のみ: `interview_offer` / `interview_cancel_request` / `hired` / `rejected` / `cancel_status`
  - 両側: `set_job_kind`
  - 未定義の action_type は Rule::in で 422 拒否
- **メッセージ削除**: 送信から 10 分以内、自分のメッセージ、`type=TEXT` のみ削除可
- **ブロック**: `talk_blocks`（cast_id, shop_id）に複合 UNIQUE 制約
- **NG ワード**: `ng_words` テーブル + `NgWordDetector` サービス。レビュー・メッセージ・プロフィールに適用。電話番号 / LINE ID / URL / NG 語テーブルを検出（非アクティブレコードは除外）。
- **プロフィール閲覧**: `profile_views`（viewer_type/id, target_type/id）で全閲覧を記録。閲覧数は `ProfileViewService::countFor()` で集計、閲覧者一覧は Premium 店舗向けに `/shop/mypage/viewers` で提供
- **キープ**（旧 LIKE から刷新）: `favorites` テーブル。`POST /api/favorites/toggle` でトグル。検索の `keep` タブに統合。
- **「今すぐ入れる」宣言**: キャスト・店舗の両方に `POST /(cast|shop)/mypage/availability` があり、2h / 4h / 8h の宣言と取消をサポート。店舗ホーム（DISCOVERY）の Tier A ランキングに使用。
- **DISCOVERY**: `DiscoveryController::getHomeCasts()` が Tier A（今すぐ入れる） / B / C の順で並べる
- **Premium プラン**（店舗向け・月 ¥29,000 / 年 ¥290,000）: `shop_plan_subscriptions` テーブル。銀行振込 + 運営の目視確認で有効化（`STATUS_PENDING_PAYMENT` → `STATUS_ACTIVE`）
  - 提供機能: ①AI レコメンド優先表示（キャストの店舗検索 relevance ソートで上位グループ）、②求人閲覧キャスト一覧（`/shop/mypage/viewers`）、③スカウト送信上限緩和（無料 5 件/日 → Premium 30 件/日、既存やりとりは無制限）
  - 実装: `PlanSubscriptionService`（契約・入金確認・機能ゲート）
  - 請求書番号 `PLN-YYYYMM-xxxx` / 領収書番号 `RCT-YYYYMM-xxxx`
- **店舗スタッフ**: `shop_managers` で 1 店舗に複数ログインアカウントを持たせられる。オーナー（`role=owner`）は 1 店舗 1 名で制約。オーナー限定操作は `shop.owner` ミドルウェアで防御（求人編集・給与・書類提出・入金確認・振込・銀行口座・スタッフ CRUD など）。
- **書類審査**: `DocumentReviewService` がキャスト本人確認（`cast_identity_documents`）と店舗許可証（`shop_license_documents`、風営 / 深夜酒類）の審査を担当。運営が `/admin/verification` から承認 / 差戻し。原本ファイルは private disk からストリーム配信のみ（Web 直アクセス禁止）。
- **オコジョガイド**: `character_guide_settings`（route_name UNIQUE）に画面別 ON/OFF・メッセージを保存。ページ内説明文の正本。`CharacterGuideService::CATALOG` にカタログ定義。
- **トーククイック定型文**: `talk_quick_reply_templates`（owner_type × status_code × sort_order）に候補文を保存。`TalkQuickReplyCatalog` が読み込み、DB 未登録／未整備時は `DEFAULT_TEMPLATES` にフォールバック。運営は `/admin/talk-quick-replies`（`master.talk_quick_replies` 権限）から編集。
- **通報 / 問合せ / お知らせ / 規約**: `user_reports` / `support_inquiries` / `notices` / `notifications` / `policy_documents` + `policy_chapters` + `policy_revisions`
- **通知**: `notifications` テーブル + `NotificationService`。Web Push は `push_subscriptions` + `PushNotificationService`。`/api/push/subscribe` で購読、テスト送信はヘッダーのベルポップから。
- **退会**: `POST /setting/account/withdraw` は PII 匿名化 + 最後のオーナー保護 + パスワード再入力必須。

---

## 画面 ID リファレンス（主要）

| 画面 ID | URL | 説明 |
|--------|-----|------|
| SCR-100 | /shop/home | 店舗ホーム（DISCOVERY、Tier A/B/C） |
| SCR-101 | /shop/search | キャスト検索（keep タブに統合） |
| SCR-200 | /cast/home | キャストホーム（SWIPE） |
| SCR-201 | /cast/search/{tab} | 店舗検索 + AI レコメンド（Premium 優先表示あり） |
| SCR-111 | /shop/mypage/management | 求人ステータス（採用・入金管理） |
| SCR-112 | /shop/recruits/edit | 求人票編集（オーナー限定） |
| SCR-204 | /cast/mypage | キャストマイページ（プレミアムホワイト） |
| SCR-300 | /admin | 管理ダッシュボード |
| SCR-303 | /admin/deposits | 入金・振込管理 |
| —      | /admin/plans | Premium プラン入金管理 |
| —      | /admin/verification | 本人確認・書類審査 |
| —      | /admin/masters | マスタメンテナンス |
| —      | /admin/character-guide | オコジョガイド設定 |
| —      | /admin/talk-quick-replies | トーククイック定型文マスタ（状況×役割） |
| —      | /subscription | Premium プラン申込・請求書 / 領収書 DL（店舗） |
| —      | /shop/mypage/viewers | 閲覧キャスト一覧（Premium 限定） |
| —      | /shop/mypage/management | 店舗：採用・入金管理 |
| —      | /shop/mypage/staff | 店舗スタッフ管理（追加はオーナーのみ） |
| —      | /shop/mypage/documents | 店舗許可証の提出・管理（提出はオーナーのみ） |
| —      | /cast/mypage/management | キャスト：採用・入金管理 |
| —      | /cast/mypage/identity | キャスト本人確認 |
| —      | /(cast|shop)/mypage/availability | 「今すぐ入れる」宣言 |
| —      | /(cast|shop)/interaction/keep | キープ一覧 |
| —      | /(cast|shop)/tutorial | 新規登録直後のチュートリアル |
| —      | /account/suspended | 停止中アカウント向けランディング |
| —      | /support/form | 問合せフォーム |
| —      | /support/column, /support/notices | コラム・お知らせ |
| —      | /login/demo | 1 画面で cast/shop/admin にワンクリックログイン（デモ用） |
| —      | /login/line | LINE ログイン |
| —      | /password/{forgot,reset} | パスワードリセット |
| —      | /auth/email/verify/{type}/{id} | メール認証（署名 URL） |
| —      | /line/webhook | LINE Messaging API Webhook |

---

## ライト / ダーク / プレミアムホワイトの振り分け（app-v2）

`layouts/app-v2.blade.php` のフラグ計算:

```php
// ダーク画面は SWIPE と share/* のみ
$isDarkPage = request()->is('*/home*') || request()->is('share/*');

// プレミアムホワイト対象ページ（トークンベースの MyPage / プロフィール詳細）
$naturalPremiumWhite = request()->routeIs(
    'cast.mypage.index',
    'shop.mypage.index',
    'cast.shopprofile.show',
    'shop.castprofileview.show',
);

// 自然状態（ヘッダーのライト/ダークトグル未使用）でのライトテーマ
$naturalLightTheme = !$isDarkPage && !$naturalPremiumWhite;

// ユーザーがヘッダーのライト/ダークトグルでダーク強制した場合（Cookie）
$isForcedDark = request()->cookie('theme_mode') === 'dark';

$isPremiumWhite = $naturalPremiumWhite && !$isForcedDark;
$isLightTheme   = $naturalLightTheme && !$isForcedDark;
```

body クラス:
- `theme-light` = ライトモード（`light-theme.css` 発動）
- `theme-premium-white` = プレミアムホワイト（`premium-white.css` 発動）
- `mode-dark` / `mode-light` = ヘッダートグル状態

---

## テスト（要約）

- スモーク: `composer test:smoke`（SQLite）— `tests/Feature/Smoke/{Admin,Cast,Public,Shop}PagesTest.php`
- Feature: `php artisan test --testsuite=Feature` — Auth / Cast / Discovery / Setting / Shop / Support 配下
- 詳細は `AUTO-TEST.md` を参照

---

## 注意事項

- `storage/` 配下のファイルは Git 管理外（画像・ログ）
- `.env` は絶対にコミットしない
- マイグレーション済みのカラム変更は新しいマイグレーションで対応
- Plesk デプロイ後は `php artisan cache:clear` が必要
- dompdf 未導入環境では帳票（請求書・領収書）は印刷用 HTML にフォールバック（`$printMode = true`）
- グローバルトースト `window.appToast(msg, variant)` はライト / ダーク共通の濃色パネル（`light-theme.css §14`）
- PWA アイコンは `php artisan pwa:icons` で `public/assets/images/pwa/icon-{192,512}.png` を生成（インストール判定に必要）
- Push 通知 VAPID キーは `php artisan push:vapid` で生成し `.env` に登録
