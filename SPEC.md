# SPEC.md — ミセチョク システム仕様書

本ドキュメントはミセチョク（demo.misechoku.jp）の**システム全体仕様の正**。
実装ルール・デザイン・テスト方針の詳細は下記に分離する。

| ドキュメント | 責務 |
|---|---|
| `SPEC.md`（本ファイル） | サービス定義、業務フロー、データモデル、外部連携、非機能要件 |
| `CLAUDE.md` | 実装ルール（DB / Tailwind / コーディング規約 / 業務ロジック要点） |
| `DESIGN.md` | デザインシステム（トークン / コンポーネント / ライトモード / タイトル方針） |
| `AUTO-TEST.md` | テスト方針と実装済みテスト一覧 |
| `Agent.md` | Claude Code サブエージェント運用と作業パターン |
| `README.md` | セットアップ手順（自動テスト環境・PWA・Push） |

---

## 1. サービス概要

### 1.1 サービス定義
水商売・夜職特化のマッチングプラットフォーム。キャスト（求職者）と店舗（求人）を
繋ぎ、入店・面談・採用までの一連の会話・記録・**採用ボーナスの請求 / 振込**を
アプリ内で完結させる。店舗向けには **Premium プラン**（月額 / 年額）を提供する。

### 1.2 ユーザー種別

| ロール | ID | 目的 |
|---|---|---|
| キャスト | `c0001…` | 店舗の検索、応募、トーク、レビュー、本人確認、ボーナス受取 |
| 店舗（オーナー） | `s0001…` | 求人票の編集、書類提出、応募者管理、入金確認、スタッフ管理、Premium 契約 |
| 店舗（スタッフ） | `m0001…` | オーナーが招待した副ログイン。求人閲覧・トーク・面談日常業務が中心。オーナー限定操作は不可 |
| 管理者 admin | 固定 | すべての権限を持つスーパー管理者 |
| 管理者 staff | 固定 | 権限をチェックボックスで個別許可されたオペレーター |

### 1.3 サポートするプラットフォーム
- Web（レスポンシブ、モバイル優先）
- **PWA**（HTTPS 配信時にインストール可能。iOS Safari / Android Chrome）
- **Web Push 通知**（VAPID / `minishlink/web-push`）

---

## 2. 業務フロー

### 2.1 登録〜利用開始

```
[キャスト]                                    [店舗]
① /cast/register で登録                      ① /shop/register で登録
② メール認証（署名 URL 6回/60分）             ② メール認証
③ /cast/tutorial（初回のみ）                  ③ /shop/tutorial（初回のみ）
④ /cast/mypage/identity で本人確認書類提出    ④ /shop/mypage/documents で許可証提出
   → /admin/verification で承認                  （風営 / 深夜酒類、オーナーのみ）
                                                → /admin/verification で承認
⑤ /cast/profile/edit でプロフィール完成       ⑤ /shop/profile/edit で店舗情報 + 求人編集
```

- パスワードリセット: `/password/forgot` → `/password/reset`（enumeration 対策済み）
- LINE ログイン: `/login/line` → OAuth コールバック
- 停止中アカウントは `/account/suspended` に強制リダイレクト

### 2.2 検索・マッチング

**キャスト側（`/cast/search/{tab}`）**
- タブ: `search` / `ai` / `timeline` / `list` / `keep`
- `list` は `SearchScoringService` によるマッチ度スコアで並び替え
  - Premium 店舗は上位グループに固定表示（`PlanSubscriptionService::activeFor()` で判定）
- `ai` は `AiConciergeService` + `AiChatController::respond` の対話 UI（`RecommendationService` が候補生成）
- `keep` はキープ済み店舗一覧（旧 LIKE 廃止・SEARCH タブに統合）

**店舗側（`/shop/search`）**
- キャストを検索。Premium 契約に関わらず利用可能。
- 「今すぐ入れる」宣言（後述）中のキャストが優先。

**DISCOVERY（`/(cast|shop)/home`）**
- `DiscoveryController::getHomeCasts()` / `getHomeShops()` が Tier A/B/C の順で並び替え
  - Tier A: 「今すぐ入れる」宣言中
  - Tier B: 直近アクティブ
  - Tier C: その他

### 2.3 応募・トーク・面談

1. キャストが求人に応募 → `shop_job_applications` レコード作成 + トークルーム発生
2. トーク（`/(cast|shop)/talk`）で会話。定型文は `/setting/talk-templates/*`（ユーザー個別 4 スロット）+ `talk_quick_reply_templates` テーブル（運営が状況×役割ごとに管理する候補文、`/admin/talk-quick-replies`）
3. 店舗が `interview_offer`（候補日提示）→ キャストが `interview_confirm`（受諾）
4. 面談後、店舗が `hired` / `rejected`（両方 shop-only）
5. 双方の取消: 店舗発 `interview_cancel_request` → キャスト受諾 `interview_cancel_accept`
6. キャストの日常業務: `work_complete_report` / `bonus_achievement_report`（フルタイム化リクエスト `fulltime_request` も含む）
7. `set_job_kind` は双方から可能な業務区分の合意
8. 権限の正: `App\Support\TalkActionRegistry`
9. メッセージ削除は送信から **10 分以内 + 自分 + type=TEXT** のみ
10. **ブロック**は `talk_blocks (cast_id, shop_id)` の複合 UNIQUE。相互ブロック不可・重複不可
11. **NG ワード**は `NgWordDetector` が電話番号 / LINE ID / URL / `ng_words` テーブルを検出
    （非アクティブ NG 語は除外）

### 2.4 採用ボーナス（金銭フロー）

`BillingManagementService` に集約。ステータス（`application_deposits.status`）は 7 段階：

| Status | 定数 | 意味 |
|---|---|---|
| 1 | `STATUS_CAST_REQUESTED` | キャストが請求 |
| 2 | `STATUS_SHOP_APPROVED` | 店舗が承認 |
| 3 | `STATUS_INVOICE_ISSUED` | 運営が請求書発行（`INV-YYYYMM-{id:04d}`、期限 7 日） |
| 4 | `STATUS_SHOP_PAYMENT_REPORTED` | 店舗が振込を申告 |
| 5 | `STATUS_SHOP_PAYMENT_CONFIRMED` | 運営が入金確認 |
| 6 | `STATUS_CAST_TRANSFERRED` | 運営がキャストへ振込済 |
| 7 | `STATUS_COMPLETED` | 完了 |

- **システム手数料 = 10%**、**銀行振込手数料 = 220 円**（マスタ化推奨）
- 履歴は `application_deposit_histories`
- 支払タスク（未了フォロー）は `payment_tasks`
- 口座名義バリデーションは `KouzaMeig` ルール（全角カナ）、口座番号 7〜8 桁、銀行 4 桁 / 支店 3 桁
- 請求書 / 領収書は dompdf、未導入環境では `$printMode = true` で印刷用 HTML にフォールバック

### 2.5 Premium プラン

- 対象: 店舗のみ
- 料金（税込）: **月 ¥29,000 / 年 ¥290,000**（`PlanSubscriptionService::PRICES`）
- フロー:
  1. `/subscription` から `contract`（billing_cycle 選択）→ `shop_plan_subscriptions.status = 1 (PENDING_PAYMENT)`
     請求書番号 `PLN-YYYYMM-xxxx` 発行、期限 = 今日 + 7 日
  2. 店舗が指定口座へ振込
  3. 運営が `/admin/plans` で目視確認 → `confirmPayment` で `status = 2 (ACTIVE)` に遷移
     `starts_at = now` / `ends_at = now + 1ヶ月` or `+ 1年`。領収書番号 `RCT-YYYYMM-xxxx`
  4. 期限切れは `activeFor()` 内で遅延評価 → `STATUS_EXPIRED`
- **Premium 提供機能**:
  1. AI レコメンド優先表示（キャストの relevance ソートで上位グループに固定）
  2. 求人閲覧キャスト一覧（`/shop/mypage/viewers`）— 非 Premium にはティーザー表示
  3. スカウト送信上限緩和: **無料 5 件/日 → Premium 30 件/日**（既存やりとりのある相手は無制限）
     スカウト = 既存メッセージがない相手への店舗発 1 通目

### 2.6 レビュー・通報・問合せ

- レビュー: キャスト → 店舗 / 店舗 → キャストの双方向。`reviews` + `review_contents` + `review_details`。
  店舗からの返信は `ReviewController::reply`、公開・非公開切替あり。
- 通報: `/user-report`（トークルーム / プロフィール画面から）— 20 件/60 分。自己通報禁止・重複排除
- 問合せ: `/support/form` — 5 件/60 分。管理は `/admin/support-inquiries`
- お知らせ: `/support/notices` 公開ページ + `notifications` テーブルで既読管理

### 2.7 「今すぐ入れる」宣言

- キャスト: `POST /cast/mypage/availability`（2h/4h/8h、`DELETE` で取消）
- 店舗: `POST /shop/mypage/availability`（同上、店舗ホームの Tier A 判定に使用）
- 24 時間で自動失効

---

## 3. 画面構成

### 3.1 URL 一覧（主要）

| URL | 画面 ID | 概要 |
|---|---|---|
| `/welcome`, `/welcome/shop` | — | LP（キャスト向け / 店舗向け） |
| `/login/demo` | — | デモログイン（本番除外対象） |
| `/(cast|shop)/register` | — | 新規登録 |
| `/(cast|shop)/login` | — | 本番ログイン |
| `/password/{forgot,reset}` | — | パスワードリセット |
| `/auth/email/verify/{type}/{id}` | — | メール認証 |
| `/(cast|shop)/tutorial` | — | 初回チュートリアル |
| `/cast/home` | SCR-200 | SWIPE（ダーク維持） |
| `/shop/home` | SCR-100 | 店舗ホーム DISCOVERY（ダーク維持） |
| `/cast/search/{tab}` | SCR-201 | 店舗検索（search/ai/timeline/list/keep） |
| `/shop/search` | SCR-101 | キャスト検索 |
| `/cast/shopprofiles/{id}` | — | 店舗プロフィール詳細（プレミアムホワイト） |
| `/shop/castprofileview/{id}` | — | キャストプロフィール詳細（プレミアムホワイト） |
| `/cast/mypage` | SCR-204 | キャストマイページ（プレミアムホワイト） |
| `/shop/mypage` | — | 店舗マイページ（プレミアムホワイト） |
| `/cast/mypage/management` | — | 採用・入金管理 |
| `/shop/mypage/management` | SCR-111 | 採用・入金管理 |
| `/shop/recruits/edit` | SCR-112 | 求人票編集（オーナー） |
| `/shop/mypage/staff` | — | スタッフ管理（追加はオーナー） |
| `/shop/mypage/viewers` | — | 閲覧キャスト一覧（Premium 限定） |
| `/shop/mypage/documents` | — | 許可証提出・管理（提出はオーナー） |
| `/cast/mypage/identity` | — | 本人確認 |
| `/(cast|shop)/interaction[/keep]` | — | つながり一覧 / キープ |
| `/(cast|shop)/talk[/*]` | — | トーク一覧 / ルーム / 送信 / 削除 / アクション / ブロック |
| `/subscription[/*]` | — | Premium 契約・請求書 / 領収書 DL |
| `/setting/{notification,account}` | — | 通知設定・アカウント設定・退会 |
| `/setting/talk-templates/*` | — | 定型文スロット管理 |
| `/support/{form,column[/{slug}],notices[/{slug}]}` | — | サポート・コラム・お知らせ |
| `/account/suspended` | — | 停止中アカウント |
| `/share/{recruit,cast}/{id}` | — | 公開共有ページ（未ログインも閲覧可） |
| `/api/push/{vapid-public-key,subscribe}` | — | Push 通知 |
| `/api/favorites/toggle` | — | キープトグル |
| `/api/bank-lookup/{banks,branches}` | — | 銀行・支店検索 |
| `/api/geocoding/{lookup,suggest}` | — | 住所→緯度経度 |
| `/notifications/{unread-count,{id}/read,read-all}` | — | 通知既読 API |
| `/line/webhook` | — | LINE Messaging API |
| `/manifest.json` | — | PWA マニフェスト |
| `/admin` | SCR-300 | 管理ダッシュボード |
| `/admin/deposits` | SCR-303 | 入金・振込管理 |
| `/admin/invoices` | — | 請求書 |
| `/admin/plans` | — | Premium プラン入金管理 |
| `/admin/verification` | — | 本人確認・書類審査 |
| `/admin/(shops|casts)[/*]` | — | アカウント管理 |
| `/admin/masters` | — | マスタメンテナンス |
| `/admin/ngwords` | — | NG ワード管理 |
| `/admin/notices`, `/admin/columns` | — | コンテンツ管理 |
| `/admin/inquiries`, `/admin/support-inquiries`, `/admin/user-reports` | — | 問合せ・通報管理 |
| `/admin/character-guide` | — | オコジョガイド設定 |
| `/admin/talk-quick-replies` | — | トーククイック定型文マスタ（状況×役割） |
| `/admin/notification-spec` | — | 通知・タスク仕様 |
| `/admin/policies/{about,terms,privacy}` | — | 規約管理 |
| `/admin/bank`, `/admin/admin-accounts` | — | 運営口座・アカウント |

### 3.2 テーマ振り分け

| テーマ | 対象 | CSS |
|---|---|---|
| ダーク（既定） | `*/home*`（SWIPE / DISCOVERY）, `share/*` | Tailwind ネイティブ |
| プレミアムホワイト | `cast.mypage.index` / `shop.mypage.index` / `cast.shopprofile.show` / `shop.castprofileview.show` | `premium-white.css` |
| ライト | 上記以外すべて（検索・トーク・採用/入金管理・書類・設定・サポート・認証・Premium） | `light-theme.css` §1-14 |

ユーザーがヘッダーのライト/ダークトグルでダーク強制した場合（Cookie `theme_mode=dark`）は
上記に関わらず全画面ダーク維持（クロームは常に紫ダーク）。

---

## 4. データモデル

### 4.1 主要テーブル（`database/mock_demo.sql` が正）

**認証・プロフィール**
- `casts` / `shops` / `managers` / `members` / `shop_managers`
- `cast_profiles` / `shop_profiles`
- `cast_images` / `shop_images` / `cast_providers`
- `cast_identity_documents` / `shop_license_documents`（審査対象）

**求人・応募・金銭**
- `shop_jobs` / `shop_job_applications` / `shop_job_tag_relations`
- `application_deposits` / `application_deposit_histories` / `payment_tasks`
- `shop_plan_subscriptions`（Premium）
- `bank_accounts` / `system_accounts` / `invoice_template_settings`

**トーク・つながり**
- `messages` / `talk_blocks` / `user_talk_templates`
- `favorites`（キープ）/ `keeps`（旧）/ `footprints`（旧）
- `profile_views`

**評価・タグ・辞書**
- `reviews` / `review_contents` / `review_details`
- `cast_tags` / `cast_tag_relations` / `shop_tags` / `shop_tag_relations`
- `shop_stations` / `industries` / `ng_words`

**通知・コンテンツ**
- `notifications` / `notification_preferences` / `push_subscriptions`
- `notices` / `column_articles` / `column_categories`
- `line_messages` / `ai_suggestion_templates`

**運用・規約**
- `admin_operation_logs` / `admin_notification_settings` / `admin_role_permissions`
- `policy_documents` / `policy_chapters` / `policy_revisions`
- `character_guide_settings`
- `talk_quick_reply_templates`（トークルームのステータス×役割別クイック定型文マスタ）
- `support_inquiries` / `user_reports`

**Laravel 標準**
- `migrations` / `failed_jobs` / `password_reset_tokens`

### 4.2 主要な不変条件

- `talk_blocks (cast_id, shop_id)` = UNIQUE
- `character_guide_settings.route_name` = UNIQUE
- `shop_managers` は 1 店舗につき role=owner が 1 名のみ（`ShopMemberService` が保証）
- `favorites` は viewer + target 単位で重複不可
- `profile_views` は重複除去せず、閲覧ごとに INSERT（`ProfileViewService`）
- `shop_plan_subscriptions.status` = 1(PENDING) / 2(ACTIVE) / 3(CANCELED) / 4(EXPIRED)
- `application_deposits.status` = 1..7（`BillingManagementService::STATUS_*`）

---

## 5. 権限モデル

### 5.1 ミドルウェア
- `member.auth` — キャストログイン必須（`/cast/*`）
- `shop.auth` — 店舗ログイン必須（`/shop/*`）
- `shop.owner` — 店舗オーナー限定（`shop.auth` の上に追加）
- `admin.auth` — 管理者ログイン必須（`/admin/*`）
- `admin.permission:<key>` — 個別権限（`AdminPermissionService::permissionCatalog()` が正）

### 5.2 店舗オーナー限定操作
- `/shop/profile/*`（店舗情報・住所・電話・営業時間・画像）
- `/shop/recruits/edit`, `/shop/recruits/update`, `/shop/recruits/toggle-status`（給与・ボーナス・公開）
- `/shop/mypage/documents/{upload,request-review,withdraw-review}`
- `/shop/mypage/payment/bank`（銀行口座）
- `/shop/mypage/deposit/{approve,pay}`（入金確認・振込）
- `/shop/mypage/staff/{create,store,destroy}`

### 5.3 管理者権限グループ（AdminPermissionService）
- ダッシュボード: `dashboard.view`
- オペレーション: `operations.invoices` / `operations.deposits` / `operations.verification` / `operations.inquiries`
- コンテンツ: `content.notices` / `content.columns`
- マスタ: `master.ngwords` / `master.masters` / `master.notification_spec` / `master.character_guide` / `master.talk_quick_replies`
- アナリティクス: `analytics.sales`
- アカウント: `accounts.{shops,casts}.{view,manage,private}` / `accounts.admins`
- 規約: `policies.manage`

`admin` ロールは全許可・カスタマイズ不可（`locked`）。`staff` はチェックボックスで個別許可。
非公開情報閲覧（`accounts.*.private`）は要パスワード再入力（`AdminPrivateAccessService`）。

---

## 6. 外部連携

| 連携先 | 用途 | 実装 |
|---|---|---|
| LINE Login | ソーシャルログイン | `LineLoginService`, `/login/line` |
| LINE Messaging API | Webhook 受信・通知配信 | `LineWebhookController`, `LineMessageService`, `LineNotificationService` |
| Web Push（VAPID） | ブラウザ・PWA へのプッシュ通知 | `minishlink/web-push`, `PushNotificationService`, `push_subscriptions` |
| ジオコーディング API | 住所→緯度経度 | `GeocodingService`, `/api/geocoding/*` |
| 銀行 / 支店マスタ | 口座入力補助 | `BankLookupService`, `/api/bank-lookup/*` |
| Unsplash（テストのみ） | プレースホルダー画像 | `test_reset_images.sql` |

Webhook の署名検証は Channel secret（`.env`）と一致するものだけを受け入れる。

---

## 7. 非機能要件

### 7.1 セキュリティ
- ログイン試行制限: `throttle:5,15`（IP 単位・15 分 5 回）
- メール認証再送信: `throttle:6,60`
- 通報 / 問合せ: `throttle:20,60` / `throttle:5,60`
- CSRF: Laravel 標準（Push API 系は同一オリジンで許可）
- 本人確認 / 許可証ファイルは **private disk からストリーム配信のみ**。Web 直アクセス禁止。
- 保持期間ポリシーに基づく完全削除は運営の手動操作のみ（`/admin/verification/*/purge`）
- 停止中アカウントは `/account/suspended` に強制リダイレクト
- 退会時: PII 匿名化 + 最後のオーナー保護 + パスワード再入力

### 7.2 パフォーマンス / 運用
- テストは本番 MySQL に一切向けない（`.env.testing` + SQLite）
- Plesk デプロイ後は `php artisan cache:clear`
- 全アセットは `?v=YYYYMMDD-識別子` 付きキャッシュバスト
- Service Worker は `/sw.js?v={assetVersion}`
- ダウンタイムポリシー: `/maintenance` で告知（`maintenance-screen.png`）

### 7.3 PWA
- `/manifest.json` は未ログイン開放（インストール判定用）
- `start_url` = `/login/demo?utm_source=pwa`
- アイコン: 192 / 512 PNG（`php artisan pwa:icons` で生成）
- `display: standalone`、`theme_color: #190509`

### 7.4 監視・ログ
- 運営操作ログ: `admin_operation_logs`（`AdminOperationLogService`）
- 通知運用サマリー: `AdminOperationalSummaryService`
- Laravel 標準 `storage/logs/laravel.log`（Git 管理外）

---

## 8. デザインシステム（要約）

詳細は `DESIGN.md`。要点：
- Tailwind v4 CSS-first config。トークンは `resources/css/app.css` の `@theme` に集約
- テーマ切替: `<html data-theme="amethyst|lilac|light_blue|light_pink">`（既定は amethyst）
- 深階層ページのタイトルは**ヘッダー中央（日本語）**に統一。ページ内 h1 は置かない
- 説明文は**オコジョガイド**に集約（`character_guide_settings`）
- CTA は 4 クラス（`.btn-primary-cta` / `.btn-secondary-cta` / `.btn-ghost-cta` / `.btn-destructive-cta`）を第一選択
- Phosphor アイコンは `<x-ui.icon name="...">` で意味名アクセス（生クラス直書き禁止）
- グローバルトースト `window.appToast(msg, variant)`（`alert()` 禁止）
- 管理画面テーブルは `admin-table--stack` + `data-label`（モバイル最適化）

---

## 9. テスト方針（要約）

詳細は `AUTO-TEST.md`。要点：
- スモーク: `composer test:smoke`（SQLite）
- Feature: `php artisan test --testsuite=Feature`
- 優先度 1 = 金銭ロジック（`BillingManagementService`）
- 優先度 2 = Premium プラン（`PlanSubscriptionService`）
- 優先度 3 = トークアクション遷移（`TalkActionRegistry` に集約）
- 実装済み: 認証・退会・通報・NG ワード・スタッフ・書類・レビュー返信・DISCOVERY・「今すぐ入れる」等

---

## 10. デプロイ / 環境

### 10.1 環境
| 環境 | 用途 |
|---|---|
| ローカル | 開発。`npm run tw:watch` + `php artisan serve` |
| Plesk（本番 / ステージング） | 本番配信。デプロイ後 `php artisan cache:clear` |
| GitHub Actions（想定） | CI。`composer test:smoke` を回す |

### 10.2 主要コマンド
```bash
# Tailwind ビルド（本番）
npm run tw:build
# Tailwind ウォッチ（開発）
npm run tw:watch

# ビュー キャッシュクリア（Blade 変更のたび）
php artisan view:clear

# PWA アイコン生成
php artisan pwa:icons

# Push VAPID キー生成
php artisan push:vapid

# スモークテスト
composer test:smoke
```

### 10.3 デモモード（外部テスター向け）
`DEMO_MODE=true`（`config/demo.php`）で以下の Mock が有効化される。**本番は必ず false**。

| フラグ | 動作 | 影響 |
|---|---|---|
| `DEMO_AUTO_VERIFY_EMAIL=true` | 認証メール送信で SMTP をスキップし `email_verified_at` を即時更新 | `EmailVerificationController::send()` |
| `DEMO_MOCK_LINE=true` | LINE OAuth なしで `/login/line/mock` + `/setting/line/mock-link` が使える | `MockLineController`。provider_id は `mock:` プレフィックス |
| `DEMO_TEST_PUSH=true` | `POST /api/push/test` で自分にテスト Web Push を送信できる | `PushController::testSend`。事前に `/api/push/subscribe` 済みが必要 |

外部テスター向けシナリオは `TEST-SCENARIOS.md` を参照。

### 10.4 環境変数（.env）
- `APP_URL`, `APP_KEY`, `APP_ENV`, `APP_DEBUG`
- DB: `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT=3306`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- メール: `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`
- Push: `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`
- LINE: `LINE_LOGIN_CHANNEL_ID`, `LINE_LOGIN_CHANNEL_SECRET`, `LINE_MESSAGING_CHANNEL_SECRET`, `LINE_MESSAGING_ACCESS_TOKEN`
- ジオコーディング（利用時）: 該当プロバイダのキー
- デモ / テスト: `DEMO_MODE`, `DEMO_AUTO_VERIFY_EMAIL`, `DEMO_MOCK_LINE`, `DEMO_TEST_PUSH`（詳細は §10.3）

### 10.5 テスト環境
- `.env.testing` を用意。`DB_CONNECTION=sqlite`、`DB_DATABASE=database/testing.sqlite`
- 本番 DB を向けたまま流さない

---

## 11. 用語集

| 用語 | 意味 |
|---|---|
| キャスト | 求職者側のユーザー |
| 店舗 | 求人側のユーザー |
| オーナー / スタッフ | 店舗の主 / 副ログイン。オーナーは 1 店舗 1 名 |
| DISCOVERY | 店舗 / キャストホームの発見型リスト（Tier A/B/C） |
| SWIPE | キャストホームのスワイプ UI（`/cast/home`） |
| キープ | ブックマーク（旧 LIKE を置き換え） |
| 「今すぐ入れる」 | 2h/4h/8h の即応可能宣言。Tier A ランキングに使用 |
| スカウト | 店舗発の新規（既存やりとりなし）1 通目メッセージ |
| Premium | 店舗向け有料プラン（月 ¥29,000 / 年 ¥290,000） |
| オコジョガイド | 画面別の説明文キャラクター（`character_guide_settings`） |
| プレミアムホワイト | MyPage / プロフィール詳細用の白基調テーマ |
| デモログイン | `/login/demo`。cast/shop/admin にワンクリックログインするテスト用画面 |

---

## 12. 変更履歴の管理

- **本仕様の変更は原則 PR 単位**でこのファイルに追記 / 修正する
- コード側の変更で仕様が変わる場合、実装前に本ファイルを更新する（コードと乖離させない）
- 数値定数（料金・上限・期日など）を変更する場合は、対応するサービスクラスの定数と同時に更新する
