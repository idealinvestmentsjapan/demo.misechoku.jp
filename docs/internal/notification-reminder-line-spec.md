# 通知・リマインダー・LINE通知 仕様書

## 1. 目的と適用範囲
- 本仕様は、ミセチョクにおける以下3系統の通知仕様を定義する。
  - Web Push通知（リアルタイム通知）
  - アプリ内リマインダー（定期バッチによるPush通知）
  - LINE通知（日次ダイジェスト通知）
- 対象ユーザーは `cast`（キャスト）と `shop_manager`（店舗管理者）。
- 送信可否はユーザー別通知設定（`notification_preferences`）で制御する。

## 2. 用語定義
- **Push購読**: ブラウザ端末が `push_subscriptions` に登録された状態。
- **業務アクション**: トーク画面での面談提案/確定、採用/不採用、本入店リクエストなど。
- **日次ダイジェスト**: 1日分の重要通知を1ユーザー1メッセージに集約してLINE送信する方式。

## 3. チャンネル別仕様

### 3.1 Web Push通知
- 送信サービス: `App\Services\PushNotificationService::sendToUser()`
- 送信条件:
  - `notification_preferences.push_enabled = true`
  - 対象ユーザーの `push_subscriptions` レコードが存在
  - VAPIDキー設定済み（`services.push.vapid_public/private`）
- 送信失敗時:
  - ログ出力し、業務処理は継続
  - 購読期限切れ時は該当 `push_subscriptions` を削除

### 3.2 リマインダー（Push）
- コマンド:
  - `reminders:send-unread-talk`（未読トーク）
  - `reminders:send-interview-deadline`（面接日・支払期限）
- スケジュール:
  - 未読トーク: 10分毎
  - 面接日・期限: 毎時
- 送信先:
  - `cast` / `shop_manager` のうち条件該当ユーザー

### 3.3 LINE通知（日次ダイジェスト）
- コマンド: `line:send-daily-digest`
- スケジュール: 毎日 `09:00`
- 集約ルール:
  - 1ユーザーにつき1日1通
  - 同一行文面は重複排除
  - `notification_preferences.line_enabled = true` のみ送信
- SDK未導入時:
  - `--dry-run` は正常実行
  - 本送信時は警告を出してスキップ

## 4. ユーザー別オペレーションフロー

### 4.1 キャスト（cast）
1. ブラウザで「通知を有効化」操作を実行  
2. Push購読が `push_subscriptions` に登録される  
3. 店舗からトーク/業務アクションが発生するとPush受信  
4. 未読が残る場合、30分後/3時間後のPushリマインダーを受信  
5. LINE連携ユーザーは、重要通知のみ日次ダイジェスト受信  

**LINEダイジェスト対象（キャスト）**
- 面談候補日が送られてきた
- 体験採用後の「本入店リクエスト」促進
- 採用後14日経過で「完了/入金依頼」未実施

### 4.2 店舗管理者（shop_manager）
1. ブラウザで通知を有効化  
2. Push購読が `push_subscriptions` に登録  
3. キャストからトーク/本入店リクエスト受信時にPush  
4. 未読継続時にPushリマインダー受信  
5. LINE連携ユーザーは重要通知の日次ダイジェスト受信  

**LINEダイジェスト対象（店舗）**
- 入金依頼到着
- 入金依頼から14日経過
- 面談日超過後に採用/不採用未登録

## 5. 業務イベントと通知文面（Push）
- 新着メッセージ: `新着メッセージ`
- 面談候補日: `面談候補日が届きました`
- 面談確定: `面談日が確定しました` / `面談日時が確定しました`
- 採用: `選考結果: 採用`
- 不採用: `選考結果: 不採用`
- ステータス取消: `面談ステータスが変更されました`
- 本入店リクエスト: `本入店リクエスト`

## 6. テーブル・カラム定義（通知関連）

### 6.1 `push_subscriptions`
- 目的: 端末Push購読の保持
- 主カラム:
  - `id`
  - `user_type` (`cast` / `shop_manager`)
  - `user_id`
  - `endpoint`（unique）
  - `public_key`
  - `auth_token`
  - `user_agent`
  - `created_at`, `updated_at`

### 6.2 `notification_preferences`
- 目的: ユーザー通知ON/OFF設定
- 主カラム:
  - `user_type`, `user_id`（unique）
  - `push_enabled`
  - `line_enabled`
  - `interview_reminder_enabled`
  - `deadline_reminder_enabled`
  - `created_at`, `updated_at`

### 6.3 `messages`
- 目的: トーク本文と業務アクションイベントの保存
- 主カラム:
  - `id`
  - `cast_id`
  - `shop_id`
  - `sender_type`（1: cast, 2: shop）
  - `type`（1: text, 2: interview_offer, 3: interview_confirmed, 4: hired, 5: rejected, 6: image）
  - `content`（JSON含む）
  - `is_read`
  - `created_at`, `updated_at`

### 6.4 `shop_job_applications`
- 目的: 応募ステータスの業務進行管理
- 通知関連で参照/更新する主カラム:
  - `id`
  - `cast_id`
  - `shop_job_id`
  - `status`
  - `result_date`
  - `reason_rejection`
  - `hired_regular_hourly_wage`
  - `talk_job_kind`
  - `updated_at`

### 6.5 `application_deposits`
- 目的: 入金依頼〜請求〜振込の進行管理
- 通知関連で参照する主カラム:
  - `id`
  - `shop_job_application_id`
  - `status`
  - `invoice_due_date`
  - `invoice_number`
  - `created_at`, `updated_at`
  - `cast_transferred_at`
  - `completed_at`

### 6.6 LINE連携参照テーブル
- `cast_providers`
  - `cast_id`, `provider='line'`, `provider_id`（LINE userId）
- `shop_managers`
  - `id`, `shop_id`, `line_user_id`

## 7. トランザクションフロー（DB更新/参照）

### 7.1 Push購読登録フロー
1. API `POST /api/push/subscribe`
2. `PushController::resolveActor()` で `user_type/user_id` 解決
3. `push_subscriptions` を `endpoint` でUPSERT相当更新
   - 既存: `update`
   - 新規: `insert`
4. 結果をJSON返却

### 7.2 トーク通常送信フロー
1. `TalkController::store()`
2. `messages` に `insert`（`type=1` または `type=6`）
3. `notifyConversationPartner()` 実行
   - 店舗宛: `shop_managers` から対象管理者を取得
   - キャスト宛: 対象castを直接指定
4. `notification_preferences.push_enabled` 判定後、`PushNotificationService::sendToUser()` 実行
5. `push_subscriptions` 参照してPush送信

### 7.3 業務アクション送信フロー
1. `TalkController::action()`
2. `messages` にアクションメッセージを `insert`
3. 応募情報更新
   - `syncApplicationStatusFromTalkAction()` で `shop_job_applications` 更新
   - 必要時 `syncApplicationEmploymentKindFromTalkAction()` で採用区分反映
4. `notifyTalkAction()` でイベント別Push通知

### 7.4 未読リマインダーフロー（Push）
1. `SendUnreadTalkReminders`
2. `messages` の `is_read=false` を会話単位で集計（最古未読時刻）
3. 経過30分/3時間の窓判定
4. ユーザー設定（`push_enabled`）判定
5. `sendToUser()` でPush送信

### 7.5 面接日・期限リマインダーフロー（Push）
1. `SendInterviewDeadlineReminders`
2. 面接確定案件（`shop_job_applications.status=3`）参照
3. 24時間前/3時間前の窓判定
4. 請求期限（`application_deposits.invoice_due_date`）参照
5. 前日/当日/3日超過の窓判定
6. 設定（`interview_reminder_enabled` / `deadline_reminder_enabled`）と `push_enabled` 判定
7. Push送信

### 7.6 LINE日次ダイジェストフロー
1. `SendLineDailyDigest`
2. 重要イベントをクエリで収集
   - 店舗:
     - `application_deposits.status=1`（当日作成）
     - `application_deposits.status=1`（14日超）
     - `shop_job_applications.status=3` かつ `result_date < 今日`
   - キャスト:
     - `messages.type=2`（当日）
     - `shop_job_applications.status=4` & `talk_job_kind=trial` かつ本入店リクエスト未送信
     - `shop_job_applications.status in (4,6)` & 14日超 & `application_deposits` 未作成
3. `user_type:user_id` 単位で文面を集約
4. `notification_preferences.line_enabled` 判定
5. `LineNotificationService` で送信
   - cast: `cast_providers.provider_id`
   - shop_manager: `shop_managers.line_user_id`

## 8. スケジュール実行仕様
- `line:send-daily-digest` : 毎日 09:00
- `reminders:send-interview-deadline` : 毎時
- `reminders:send-unread-talk` : 10分毎
- `billing:remind-cast-transfer-confirmation` : 毎時（現状はログ中心）

## 9. 障害時挙動
- Push送信失敗: 警告ログ出力、業務処理は継続
- LINE SDK未導入:
  - `--dry-run`: 実行可能
  - 本送信: 該当処理をスキップし警告表示

## 10. 運用ルール
- 高頻度通知はPush優先
- LINEは重要通知のみ（日次ダイジェスト）
- 送信可否は `notification_preferences` に従う
- 実運用前に `--dry-run` で対象件数と文面を確認すること

