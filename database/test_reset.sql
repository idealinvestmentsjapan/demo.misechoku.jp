-- =============================================================================
-- ### demo function and data for test ###
-- Test dataset for full-feature verification. DO NOT run on production DB.
-- =============================================================================
-- ミセチョク：テスト用データセット（全機能検証向け）
--
-- 使い方:
--     mysql -u root -p misechoku < database/test_reset.sql
--
-- 前提:
--     - スキーマは database/schema.sql または database/mock_demo.sql で
--       既に作成済みであること（industries / cast_tags / shop_tags /
--       review_contents / character_guide_settings / ng_words /
--       column_categories / policy_* が投入済み）
--     - 初回セットアップ手順:
--         mysql -u root -p -e "CREATE DATABASE misechoku CHARACTER SET utf8mb4;"
--         mysql -u root -p misechoku < database/mock_demo.sql
--         mysql -u root -p misechoku < database/test_reset.sql
--
-- 画像ファイル（2026-08 rev）:
--     - cast_images / shop_images は images.unsplash.com の高解像度写真を
--       photo-XXXX ID 指定で直接読み込む（1200x1500 の 4:5 クロップ）。
--       assetPathForStored() は http/https を素通しするため URL をそのまま
--       image_path に格納で OK。差し替えは photo-XXXX を別 ID に置換するだけ。
--     - cast_identity_documents / shop_license_documents は private/dummy/... の
--       ダミーパス（承認・差戻し等のワークフロー検証のみが目的）
--
-- スケール（2026-08 rev）:
--     casts 50 / shops 25 / applications 35+ / deposits 15+ / messages 60+
--     を投入し、旧 c001-c010, s001-s005 のシナリオはそのまま温存。
--
-- ログイン情報:
--     全アカウントのパスワード = 「password」
--     ハッシュ: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
--
--     ─── 管理者 ─────────────────────
--       admin@test.jp
--
--     ─── キャスト（50 人。c001-c010 は旧シナリオ温存、c011-c050 は多様なパターン）─────
--       cast01-10@test.jp  旧シナリオ（Tier A/B/C・identity 各種）
--       cast11-30@test.jp  Tier A/B 中心（active 求職者）
--       cast31-50@test.jp  Tier B/C・地方在住など幅広く
--
--     ─── 店舗（25 店）─────────────
--       shop01-05@test.jp  旧シナリオ（Premium 各種状態）
--       shop06-25@test.jp  High-end/Pop/Cafe/Snack/Lounge 5ジャンル × 4店舗ずつ
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;
SET time_zone = '+09:00';

-- =============================================================================
-- Phase 1: TRUNCATE 対象（ユーザースケール／セッション系）
-- =============================================================================

TRUNCATE TABLE `application_deposit_histories`;
TRUNCATE TABLE `application_deposits`;
TRUNCATE TABLE `shop_job_applications`;
TRUNCATE TABLE `shop_jobs`;
TRUNCATE TABLE `messages`;
TRUNCATE TABLE `talk_blocks`;
TRUNCATE TABLE `reviews`;
TRUNCATE TABLE `review_details`;
TRUNCATE TABLE `favorites`;
TRUNCATE TABLE `keeps`;
TRUNCATE TABLE `footprints`;
TRUNCATE TABLE `profile_views`;
TRUNCATE TABLE `cast_identity_documents`;
TRUNCATE TABLE `shop_license_documents`;
TRUNCATE TABLE `cast_images`;
TRUNCATE TABLE `shop_images`;
TRUNCATE TABLE `cast_posts`;
TRUNCATE TABLE `shop_posts`;
TRUNCATE TABLE `cast_profiles`;
TRUNCATE TABLE `shop_profiles`;
TRUNCATE TABLE `cast_tag_relations`;
TRUNCATE TABLE `shop_tag_relations`;
TRUNCATE TABLE `shop_job_tag_relations`;
TRUNCATE TABLE `bank_accounts`;
TRUNCATE TABLE `cast_search_preferences`;
TRUNCATE TABLE `shop_search_preferences`;
TRUNCATE TABLE `cast_providers`;
TRUNCATE TABLE `cast_shop_relation`;
TRUNCATE TABLE `notifications`;
TRUNCATE TABLE `notification_preferences`;
TRUNCATE TABLE `push_subscriptions`;
TRUNCATE TABLE `payment_tasks`;
TRUNCATE TABLE `shop_managers`;
TRUNCATE TABLE `casts`;
TRUNCATE TABLE `shops`;
TRUNCATE TABLE `shop_plan_subscriptions`;
TRUNCATE TABLE `user_talk_templates`;
TRUNCATE TABLE `support_inquiries`;
TRUNCATE TABLE `line_messages`;
TRUNCATE TABLE `admin_operation_logs`;
TRUNCATE TABLE `admin_role_permissions`;
TRUNCATE TABLE `user_reports`;

-- =============================================================================
-- Phase 2: casts（50 人）— password は全員 "password"
-- =============================================================================

INSERT INTO `casts` (`id`, `email`, `email_verified_at`, `password`, `status`, `identity_status`, `last_login_at`, `created_at`, `updated_at`) VALUES
-- c001-c010: 旧シナリオ温存
('c00000001', 'cast01@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 5 MINUTE), '2026-01-15 10:00:00', NOW()),
('c00000002', 'cast02@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 15 MINUTE), '2026-02-01 10:00:00', NOW()),
('c00000003', 'cast03@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 2, DATE_SUB(NOW(), INTERVAL 20 MINUTE), '2026-03-01 10:00:00', NOW()),
('c00000004', 'cast04@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 2, DATE_SUB(NOW(), INTERVAL 2 HOUR), '2026-03-10 10:00:00', NOW()),
('c00000005', 'cast05@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 2, DATE_SUB(NOW(), INTERVAL 10 HOUR), '2026-03-20 10:00:00', NOW()),
('c00000006', 'cast06@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_SUB(NOW(), INTERVAL 7 DAY), '2026-04-01 10:00:00', NOW()),
('c00000007', 'cast07@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_SUB(NOW(), INTERVAL 3 DAY), '2026-04-10 10:00:00', NOW()),
('c00000008', 'cast08@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 30 MINUTE), '2026-05-01 10:00:00', NOW()),
('c00000009', 'cast09@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 2 DAY), '2026-05-15 10:00:00', NOW()),
('c00000010', 'cast10@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 5 DAY), '2026-06-01 10:00:00', NOW()),
-- c011-c020: Tier A/B（宣言中・オンライン中）承認済み中心
('c00000011', 'cast11@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 10 MINUTE), '2026-06-10 10:00:00', NOW()),
('c00000012', 'cast12@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 25 MINUTE), '2026-06-12 10:00:00', NOW()),
('c00000013', 'cast13@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 40 MINUTE), '2026-06-15 10:00:00', NOW()),
('c00000014', 'cast14@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 1 HOUR), '2026-06-18 10:00:00', NOW()),
('c00000015', 'cast15@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 90 MINUTE), '2026-06-20 10:00:00', NOW()),
('c00000016', 'cast16@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 3 HOUR), '2026-06-22 10:00:00', NOW()),
('c00000017', 'cast17@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 4 HOUR), '2026-06-25 10:00:00', NOW()),
('c00000018', 'cast18@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 2, DATE_SUB(NOW(), INTERVAL 5 HOUR), '2026-06-28 10:00:00', NOW()),
('c00000019', 'cast19@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 2, DATE_SUB(NOW(), INTERVAL 6 HOUR), '2026-07-01 10:00:00', NOW()),
('c00000020', 'cast20@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 8 HOUR), '2026-07-05 10:00:00', NOW()),
-- c021-c030: Tier B（数時間〜1日前ログイン）
('c00000021', 'cast21@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 12 HOUR), '2026-07-08 10:00:00', NOW()),
('c00000022', 'cast22@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 18 HOUR), '2026-07-10 10:00:00', NOW()),
('c00000023', 'cast23@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 2, DATE_SUB(NOW(), INTERVAL 1 DAY), '2026-07-12 10:00:00', NOW()),
('c00000024', 'cast24@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 1 DAY), '2026-07-15 10:00:00', NOW()),
('c00000025', 'cast25@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 2 DAY), '2026-07-18 10:00:00', NOW()),
('c00000026', 'cast26@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 2, DATE_SUB(NOW(), INTERVAL 2 DAY), '2026-07-20 10:00:00', NOW()),
('c00000027', 'cast27@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 3 DAY), '2026-07-22 10:00:00', NOW()),
('c00000028', 'cast28@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_SUB(NOW(), INTERVAL 3 DAY), '2026-07-25 10:00:00', NOW()),
('c00000029', 'cast29@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 4 DAY), '2026-07-28 10:00:00', NOW()),
('c00000030', 'cast30@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 5 DAY), '2026-07-30 10:00:00', NOW()),
-- c031-c040: Tier C（5日〜3週間前）
('c00000031', 'cast31@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 6 DAY), '2026-06-01 10:00:00', NOW()),
('c00000032', 'cast32@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 2, DATE_SUB(NOW(), INTERVAL 8 DAY), '2026-06-05 10:00:00', NOW()),
('c00000033', 'cast33@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 10 DAY), '2026-06-08 10:00:00', NOW()),
('c00000034', 'cast34@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 12 DAY), '2026-06-12 10:00:00', NOW()),
('c00000035', 'cast35@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, DATE_SUB(NOW(), INTERVAL 14 DAY), '2026-06-15 10:00:00', NOW()),
('c00000036', 'cast36@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 16 DAY), '2026-06-18 10:00:00', NOW()),
('c00000037', 'cast37@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 18 DAY), '2026-06-22 10:00:00', NOW()),
('c00000038', 'cast38@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 2, DATE_SUB(NOW(), INTERVAL 20 DAY), '2026-06-25 10:00:00', NOW()),
('c00000039', 'cast39@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 21 DAY), '2026-06-28 10:00:00', NOW()),
('c00000040', 'cast40@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 25 DAY), '2026-07-01 10:00:00', NOW()),
-- c041-c050: 直近ログイン ~1〜数時間の Tier A/B（追加の active カスト）
('c00000041', 'cast41@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 45 MINUTE), '2026-07-03 10:00:00', NOW()),
('c00000042', 'cast42@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 2 HOUR), '2026-07-05 10:00:00', NOW()),
('c00000043', 'cast43@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 5 HOUR), '2026-07-08 10:00:00', NOW()),
('c00000044', 'cast44@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 7 HOUR), '2026-07-10 10:00:00', NOW()),
('c00000045', 'cast45@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 2, DATE_SUB(NOW(), INTERVAL 9 HOUR), '2026-07-12 10:00:00', NOW()),
('c00000046', 'cast46@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 11 HOUR), '2026-07-15 10:00:00', NOW()),
('c00000047', 'cast47@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 20 MINUTE), '2026-07-18 10:00:00', NOW()),
('c00000048', 'cast48@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 35 MINUTE), '2026-07-20 10:00:00', NOW()),
('c00000049', 'cast49@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 55 MINUTE), '2026-07-22 10:00:00', NOW()),
('c00000050', 'cast50@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3, DATE_SUB(NOW(), INTERVAL 75 MINUTE), '2026-07-25 10:00:00', NOW());

-- =============================================================================
-- Phase 3: cast_profiles（50 人分）
-- =============================================================================

INSERT INTO `cast_profiles` (`id`, `industry_id`, `cast_id`, `nickname`, `name`, `birthday`, `pref`, `city`, `latitude`, `longitude`, `available_until`, `available_declared_at`, `pr`, `exp`, `profession`, `personality_type`, `created_at`, `updated_at`) VALUES
(1, 1, 'c00000001', 'みさき', '桜井美咲', '2001-05-15', '東京都', '中央区', 35.6826780, 139.7807160, DATE_ADD(NOW(), INTERVAL 2 HOUR), NOW(), '経験1年です！素敵なお店で頑張りたいです。', 1, '大学生', 'LCOH', NOW(), NOW()),
(2, 1, 'c00000002', 'ゆい', '田中結衣', '1994-04-24', '東京都', '渋谷区', 35.6580000, 139.7016000, DATE_ADD(NOW(), INTERVAL 4 HOUR), NOW(), 'よろしくお願いします！', 1, 'モデル', 'LDMV', NOW(), NOW()),
(3, 1, 'c00000003', 'マリ', '田端麻里奈', '1999-10-15', '東京都', '江戸川区', 35.7062820, 139.8683050, NULL, NULL, '面談まちです', 0, '学生', 'HXOL', NOW(), NOW()),
(4, 1, 'c00000004', '政子', '田所政子', '2005-05-10', '東京都', '品川区', 35.6058540, 139.7325590, NULL, NULL, '初めてです、優しく教えてください。', 0, 'アルバイト', 'PXTQ', NOW(), NOW()),
(5, 1, 'c00000005', 'のりりん', '間瀬紀子', '1998-05-10', '東京都', '港区', 35.6598140, 139.7290560, NULL, NULL, '5年経験あります。', 1, '会社員', 'LDOH', NOW(), NOW()),
(6, NULL, 'c00000006', 'あや', '藤原彩', '2000-08-20', '東京都', '練馬区', NULL, NULL, NULL, NULL, 'よろしく', 0, NULL, NULL, NOW(), NOW()),
(7, 1, 'c00000007', 'さくら', '桜庭さくら', '1996-03-14', '大阪府', '大阪市中央区', 34.6873000, 135.5259000, NULL, NULL, '関西です！東京進出予定！', 1, 'キャバ嬢', 'PDOH', NOW(), NOW()),
(8, 1, 'c00000008', 'みほ', '田村美帆', '2003-11-05', '東京都', '中野区', NULL, NULL, DATE_ADD(NOW(), INTERVAL 8 HOUR), NOW(), '長時間対応できます', 0, 'アルバイト', NULL, NOW(), NOW()),
(9, 1, 'c00000009', 'えりか', '安倍絵里加', '1997-01-25', '東京都', '中央区', 35.6812780, 139.7671250, NULL, NULL, 'よろしくお願いします', 1, 'OL', 'LDOV', NOW(), NOW()),
(10, 1, 'c00000010', 'かな', '木村奏', '1995-12-08', '東京都', '中央区', 35.6712780, 139.7501250, NULL, NULL, '毎日出勤できます', 1, 'フリーター', 'LXOH', NOW(), NOW()),
(11, 2, 'c00000011', 'りな', '林梨奈', '2000-07-01', '東京都', '新宿区', 35.6938340, 139.7034540, DATE_ADD(NOW(), INTERVAL 3 HOUR), NOW(), '銀座のお店希望です', 1, 'モデル', 'LDMH', NOW(), NOW()),
(12, 2, 'c00000012', 'まゆ', '松田真由', '1998-11-11', '東京都', '港区', 35.6595050, 139.7295060, DATE_ADD(NOW(), INTERVAL 5 HOUR), NOW(), '週3で入れます', 1, '大学生', 'LCOV', NOW(), NOW()),
(13, 3, 'c00000013', 'あかね', '赤木あかね', '2001-02-14', '東京都', '中央区', 35.6702730, 139.7726300, DATE_ADD(NOW(), INTERVAL 6 HOUR), NOW(), 'ラウンジ経験あり', 1, '会社員', 'LDOH', NOW(), NOW()),
(14, 1, 'c00000014', 'ひなの', '日野ひなの', '2003-08-22', '東京都', '渋谷区', 35.6580000, 139.7016000, DATE_ADD(NOW(), INTERVAL 2 HOUR), NOW(), '未経験ですが頑張ります', 0, '学生', 'PCOV', NOW(), NOW()),
(15, 2, 'c00000015', 'ちさと', '千葉千聡', '1995-03-30', '東京都', '中央区', 35.6712780, 139.7601250, DATE_ADD(NOW(), INTERVAL 4 HOUR), NOW(), '銀座で5年です', 1, 'キャバ嬢', 'LDMV', NOW(), NOW()),
(16, 1, 'c00000016', 'ゆうな', '佐々木優奈', '1999-12-05', '東京都', '目黒区', 35.6337690, 139.7156960, DATE_ADD(NOW(), INTERVAL 3 HOUR), NOW(), '接客大好きです', 1, 'アルバイト', 'LCOH', NOW(), NOW()),
(17, 4, 'c00000017', 'のあ', '野田乃愛', '2002-05-08', '東京都', '世田谷区', 35.6465170, 139.6532930, DATE_ADD(NOW(), INTERVAL 5 HOUR), NOW(), 'ガールズバー経験あり', 0, '大学生', 'PCOL', NOW(), NOW()),
(18, 1, 'c00000018', 'まりん', '小林麻鈴', '2004-09-19', '東京都', '豊島区', 35.7295030, 139.7141470, NULL, NULL, '週末のみ出勤希望', 0, '学生', 'HCOH', NOW(), NOW()),
(19, 3, 'c00000019', 'るか', '伊藤瑠花', '1997-06-25', '東京都', '港区', 35.6641820, 139.7301560, NULL, NULL, '池袋周辺希望です', 1, 'OL', 'LDOV', NOW(), NOW()),
(20, 2, 'c00000020', 'みく', '三村美空', '2000-11-02', '東京都', '中央区', 35.6712780, 139.7601250, DATE_ADD(NOW(), INTERVAL 7 HOUR), NOW(), 'クラブで働きたいです', 1, 'フリーター', 'LDMH', NOW(), NOW()),
(21, 1, 'c00000021', 'ここみ', '石田心美', '1996-04-17', '東京都', '新宿区', 35.6919760, 139.7031560, NULL, NULL, '新宿希望です', 1, 'キャバ嬢', 'LDOV', NOW(), NOW()),
(22, 5, 'c00000022', 'なつき', '夏木菜月', '2002-08-08', '東京都', '渋谷区', 35.6595050, 139.7005460, DATE_ADD(NOW(), INTERVAL 4 HOUR), NOW(), 'コンカフェ経験あり', 0, '大学生', 'PCOL', NOW(), NOW()),
(23, 1, 'c00000023', 'れいな', '田中礼奈', '1998-01-13', '神奈川県', '横浜市中区', 35.4478280, 139.6425160, NULL, NULL, '横浜からでも通えます', 1, 'モデル', 'LDMV', NOW(), NOW()),
(24, 2, 'c00000024', 'ひまり', '中村陽向', '2001-06-06', '東京都', '中央区', 35.6702730, 139.7726300, DATE_ADD(NOW(), INTERVAL 3 HOUR), NOW(), 'クラブで長く続けたいです', 1, 'フリーター', 'LDOH', NOW(), NOW()),
(25, 3, 'c00000025', 'きらり', '吉川きらり', '1999-10-21', '東京都', '港区', 35.6641820, 139.7301560, DATE_ADD(NOW(), INTERVAL 6 HOUR), NOW(), '六本木のラウンジで働きたい', 1, 'OL', 'LDMV', NOW(), NOW()),
(26, 1, 'c00000026', 'かえで', '楓', '2003-03-03', '東京都', '文京区', 35.7080370, 139.7523090, NULL, NULL, '初心者ですが頑張ります', 0, '大学生', 'PCOH', NOW(), NOW()),
(27, 6, 'c00000027', 'いちか', '市川一花', '1997-07-27', '東京都', '中野区', 35.7079240, 139.6633330, DATE_ADD(NOW(), INTERVAL 5 HOUR), NOW(), 'スナックで週3〜', 1, 'アルバイト', 'LDOH', NOW(), NOW()),
(28, 1, 'c00000028', 'みゆ', '深津みゆ', '2000-12-15', '大阪府', '大阪市北区', 34.7024540, 135.4959370, NULL, NULL, '大阪でも東京でも', 0, 'フリーター', 'PCOV', NOW(), NOW()),
(29, 2, 'c00000029', 'まなみ', '真波', '1998-09-09', '東京都', '中央区', 35.6712780, 139.7601250, NULL, NULL, '銀座メインで', 1, 'キャバ嬢', 'LDMV', NOW(), NOW()),
(30, 1, 'c00000030', 'あんな', '安藤杏那', '2001-05-30', '東京都', '渋谷区', 35.6580000, 139.7016000, NULL, NULL, '渋谷 or 六本木', 1, '大学生', 'LCOH', NOW(), NOW()),
(31, 1, 'c00000031', 'のぞみ', '望月希', '1996-02-11', '東京都', '中央区', 35.6702730, 139.7726300, NULL, NULL, '銀座希望です', 1, 'モデル', 'LDMV', NOW(), NOW()),
(32, 3, 'c00000032', 'らん', '中西蘭', '2002-04-04', '東京都', '港区', 35.6641820, 139.7301560, NULL, NULL, '六本木のラウンジ', 0, '学生', 'PCOL', NOW(), NOW()),
(33, 4, 'c00000033', 'えみり', '江藤絵美里', '1999-08-18', '東京都', '渋谷区', 35.6595050, 139.7005460, NULL, NULL, 'ガールズバー経験', 1, 'フリーター', 'LDOH', NOW(), NOW()),
(34, 1, 'c00000034', 'なな', '奈々', '2000-11-23', '東京都', '中央区', 35.6812780, 139.7671250, NULL, NULL, '接客経験3年', 1, 'キャバ嬢', 'LDMV', NOW(), NOW()),
(35, 6, 'c00000035', 'みお', '澪', '2003-06-18', '愛知県', '名古屋市中区', 35.1815090, 136.9066160, NULL, NULL, '名古屋でスナック希望', 0, '大学生', 'PCOV', NOW(), NOW()),
(36, 2, 'c00000036', 'ふうか', '福井風香', '1997-12-01', '東京都', '中央区', 35.6712780, 139.7601250, NULL, NULL, '銀座クラブ経験', 1, 'モデル', 'LDMV', NOW(), NOW()),
(37, 5, 'c00000037', 'ももか', '百花', '2002-01-25', '東京都', '渋谷区', 35.6595050, 139.7005460, NULL, NULL, 'コンカフェ大好き', 0, '大学生', 'PCOH', NOW(), NOW()),
(38, 1, 'c00000038', 'さやか', '清川さやか', '1998-10-10', '東京都', '港区', 35.6598140, 139.7290560, NULL, NULL, '港区周辺希望', 1, 'OL', 'LDOV', NOW(), NOW()),
(39, 1, 'c00000039', 'あき', '秋田亜紀', '1995-05-05', '東京都', '中央区', 35.6812780, 139.7671250, NULL, NULL, 'ベテランです', 1, 'キャバ嬢', 'LDMV', NOW(), NOW()),
(40, 3, 'c00000040', 'みおん', '三上澪音', '2001-09-12', '東京都', '豊島区', 35.7295030, 139.7141470, NULL, NULL, '池袋ラウンジ希望', 1, 'アルバイト', 'LDOH', NOW(), NOW()),
(41, 1, 'c00000041', 'るり', '瑠璃', '2000-03-20', '東京都', '中央区', 35.6702730, 139.7726300, DATE_ADD(NOW(), INTERVAL 4 HOUR), NOW(), '銀座で長く', 1, 'モデル', 'LDMV', NOW(), NOW()),
(42, 1, 'c00000042', 'ひな', '雛', '2003-07-07', '東京都', '渋谷区', 35.6580000, 139.7016000, DATE_ADD(NOW(), INTERVAL 6 HOUR), NOW(), '未経験でも頑張ります', 0, '学生', 'PCOV', NOW(), NOW()),
(43, 2, 'c00000043', 'かのん', '加納カノン', '1998-12-30', '東京都', '中央区', 35.6712780, 139.7601250, DATE_ADD(NOW(), INTERVAL 3 HOUR), NOW(), '週2〜5希望', 1, 'フリーター', 'LDOH', NOW(), NOW()),
(44, 1, 'c00000044', 'みか', '三上美佳', '1996-11-11', '東京都', '港区', 35.6598140, 139.7290560, DATE_ADD(NOW(), INTERVAL 5 HOUR), NOW(), '高収入希望', 1, 'キャバ嬢', 'LDMV', NOW(), NOW()),
(45, 4, 'c00000045', 'あゆ', '歩', '2002-06-13', '東京都', '新宿区', 35.6919760, 139.7031560, NULL, NULL, 'ガールズバー経験3年', 0, 'アルバイト', 'PCOL', NOW(), NOW()),
(46, 3, 'c00000046', 'めい', '芽衣', '1999-04-22', '東京都', '中央区', 35.6702730, 139.7726300, DATE_ADD(NOW(), INTERVAL 7 HOUR), NOW(), 'ラウンジ経験2年', 1, 'OL', 'LDOV', NOW(), NOW()),
(47, 5, 'c00000047', 'ゆず', '柚', '2004-02-02', '東京都', '渋谷区', 35.6595050, 139.7005460, DATE_ADD(NOW(), INTERVAL 4 HOUR), NOW(), 'メイド経験あり', 0, '大学生', 'PCOH', NOW(), NOW()),
(48, 1, 'c00000048', 'とわ', '十和', '2000-08-08', '福岡県', '福岡市中央区', 33.5822010, 130.4067020, DATE_ADD(NOW(), INTERVAL 3 HOUR), NOW(), '福岡でキャバクラ', 1, 'モデル', 'LDMV', NOW(), NOW()),
(49, 2, 'c00000049', 'ここ', '心', '2001-11-19', '東京都', '中央区', 35.6712780, 139.7601250, DATE_ADD(NOW(), INTERVAL 5 HOUR), NOW(), 'クラブ長期希望', 1, 'キャバ嬢', 'LDMV', NOW(), NOW()),
(50, 3, 'c00000050', 'みさ', '美紗', '1998-05-25', '東京都', '港区', 35.6641820, 139.7301560, DATE_ADD(NOW(), INTERVAL 6 HOUR), NOW(), 'ラウンジで安定', 1, 'OL', 'LDOH', NOW(), NOW());

-- =============================================================================
-- Phase 4: shops（25 店）+ shop_managers + shop_profiles
--   ジャンル分布: High-end 6 / Pop 5 / Cafe 4 / Snack 5 / Lounge 5
-- =============================================================================

INSERT INTO `shops` (`id`, `email`, `status`, `license_status`, `business_license_status`, `entertainment_license_status`, `created_at`, `updated_at`) VALUES
-- 旧シナリオ温存
('s00000001', 'shop01@test.jp', 1, 3, 3, 3, '2026-01-10 10:00:00', NOW()),
('s00000002', 'shop02@test.jp', 1, 2, 2, 1, '2026-02-01 10:00:00', NOW()),
('s00000003', 'shop03@test.jp', 1, 2, 3, 1, '2026-02-10 10:00:00', NOW()),
('s00000004', 'shop04@test.jp', 1, 1, 1, 1, '2026-03-01 10:00:00', NOW()),
('s00000005', 'shop05@test.jp', 1, 3, 3, 3, '2026-01-05 10:00:00', NOW()),
-- 新規（s006-s025）: license は概ね承認済み。一部混在
('s00000006', 'shop06@test.jp', 1, 3, 3, 3, '2026-03-15 10:00:00', NOW()),
('s00000007', 'shop07@test.jp', 1, 3, 3, 3, '2026-03-20 10:00:00', NOW()),
('s00000008', 'shop08@test.jp', 1, 2, 3, 2, '2026-04-01 10:00:00', NOW()),
('s00000009', 'shop09@test.jp', 1, 3, 3, 3, '2026-04-05 10:00:00', NOW()),
('s00000010', 'shop10@test.jp', 1, 3, 3, 3, '2026-04-10 10:00:00', NOW()),
('s00000011', 'shop11@test.jp', 1, 3, 3, 3, '2026-04-15 10:00:00', NOW()),
('s00000012', 'shop12@test.jp', 1, 3, 3, 3, '2026-04-20 10:00:00', NOW()),
('s00000013', 'shop13@test.jp', 1, 2, 2, 1, '2026-05-01 10:00:00', NOW()),
('s00000014', 'shop14@test.jp', 1, 3, 3, 3, '2026-05-05 10:00:00', NOW()),
('s00000015', 'shop15@test.jp', 1, 3, 3, 3, '2026-05-10 10:00:00', NOW()),
('s00000016', 'shop16@test.jp', 1, 3, 3, 3, '2026-05-15 10:00:00', NOW()),
('s00000017', 'shop17@test.jp', 1, 3, 3, 3, '2026-05-20 10:00:00', NOW()),
('s00000018', 'shop18@test.jp', 1, 3, 3, 3, '2026-06-01 10:00:00', NOW()),
('s00000019', 'shop19@test.jp', 1, 3, 3, 3, '2026-06-05 10:00:00', NOW()),
('s00000020', 'shop20@test.jp', 1, 3, 3, 3, '2026-06-10 10:00:00', NOW()),
('s00000021', 'shop21@test.jp', 1, 3, 3, 3, '2026-06-15 10:00:00', NOW()),
('s00000022', 'shop22@test.jp', 1, 3, 3, 3, '2026-06-20 10:00:00', NOW()),
('s00000023', 'shop23@test.jp', 1, 3, 3, 3, '2026-06-25 10:00:00', NOW()),
('s00000024', 'shop24@test.jp', 1, 3, 3, 3, '2026-07-01 10:00:00', NOW()),
('s00000025', 'shop25@test.jp', 1, 3, 3, 3, '2026-07-05 10:00:00', NOW());

INSERT INTO `shop_managers` (`id`, `shop_id`, `name`, `email`, `email_verified_at`, `password`, `role`, `status`, `last_login_at`, `created_at`, `updated_at`) VALUES
('m00000001', 's00000001', '佐藤 店長', 'shop01@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NOW(), '2026-01-10 10:00:00', NOW()),
('m00000002', 's00000002', '山田 マネージャー', 'shop02@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NOW(), '2026-02-01 10:00:00', NOW()),
('m00000003', 's00000003', '鈴木 オーナー', 'shop03@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NOW(), '2026-02-10 10:00:00', NOW()),
('m00000004', 's00000004', '中村 店長', 'shop04@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NOW(), '2026-03-01 10:00:00', NOW()),
('m00000005', 's00000005', '高橋 店長', 'shop05@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NOW(), '2026-01-05 10:00:00', NOW()),
('m00000006', 's00000006', '井上 店長', 'shop06@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NOW(), '2026-03-15 10:00:00', NOW()),
('m00000007', 's00000007', '木下 マネージャー', 'shop07@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NOW(), '2026-03-20 10:00:00', NOW()),
('m00000008', 's00000008', '林 オーナー', 'shop08@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NOW(), '2026-04-01 10:00:00', NOW()),
('m00000009', 's00000009', '斉藤 店長', 'shop09@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NOW(), '2026-04-05 10:00:00', NOW()),
('m00000010', 's00000010', '森 店長', 'shop10@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NOW(), '2026-04-10 10:00:00', NOW()),
('m00000011', 's00000011', '池田 店長', 'shop11@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NOW(), '2026-04-15 10:00:00', NOW()),
('m00000012', 's00000012', '橋本 店長', 'shop12@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NOW(), '2026-04-20 10:00:00', NOW()),
('m00000013', 's00000013', '藤田 店長', 'shop13@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NOW(), '2026-05-01 10:00:00', NOW()),
('m00000014', 's00000014', '岡本 店長', 'shop14@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NOW(), '2026-05-05 10:00:00', NOW()),
('m00000015', 's00000015', '福田 店長', 'shop15@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NOW(), '2026-05-10 10:00:00', NOW()),
('m00000016', 's00000016', '西村 店長', 'shop16@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NOW(), '2026-05-15 10:00:00', NOW()),
('m00000017', 's00000017', '青木 店長', 'shop17@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NOW(), '2026-05-20 10:00:00', NOW()),
('m00000018', 's00000018', '前田 店長', 'shop18@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NOW(), '2026-06-01 10:00:00', NOW()),
('m00000019', 's00000019', '安田 店長', 'shop19@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NOW(), '2026-06-05 10:00:00', NOW()),
('m00000020', 's00000020', '長谷川 店長', 'shop20@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NOW(), '2026-06-10 10:00:00', NOW()),
('m00000021', 's00000021', '藤井 店長', 'shop21@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NOW(), '2026-06-15 10:00:00', NOW()),
('m00000022', 's00000022', '内田 店長', 'shop22@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NOW(), '2026-06-20 10:00:00', NOW()),
('m00000023', 's00000023', '古川 店長', 'shop23@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NOW(), '2026-06-25 10:00:00', NOW()),
('m00000024', 's00000024', '中島 店長', 'shop24@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NOW(), '2026-07-01 10:00:00', NOW()),
('m00000025', 's00000025', '石川 店長', 'shop25@test.jp', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NOW(), '2026-07-05 10:00:00', NOW());

INSERT INTO `shop_profiles` (`id`, `industry_id`, `shop_id`, `shop_name`, `zip`, `pref`, `city`, `addr`, `tel`, `open_time`, `close_is_last`, `latitude`, `longitude`, `created_at`, `updated_at`) VALUES
(1, 2, 's00000001', 'CLUB LUMINOUS (ルミナス)', '104-0061', '東京都', '中央区', '銀座 8-10-5', '03-1111-1111', '19:00:00', 1, 35.6712780, 139.7601250, '2026-01-10 10:00:00', NOW()),
(2, 1, 's00000002', 'CUTE club', '106-0032', '東京都', '港区', '六本木 5-1-2', '03-2222-2222', '19:00:00', 1, 35.6641820, 139.7301560, '2026-02-01 10:00:00', NOW()),
(3, 5, 's00000003', 'CAFE MOCHA', '150-0002', '東京都', '渋谷区', '渋谷 1-5-3', '03-3333-3333', '18:00:00', 0, 35.6595050, 139.7005460, '2026-02-10 10:00:00', NOW()),
(4, 6, 's00000004', 'SNACK PEARL', '160-0022', '東京都', '新宿区', '新宿 3-14-8', '03-4444-4444', '20:00:00', 1, 35.6919760, 139.7031560, '2026-03-01 10:00:00', NOW()),
(5, 3, 's00000005', 'LOUNGE STAR', '170-0013', '東京都', '豊島区', '東池袋 1-1-1', '03-5555-5555', '19:00:00', 1, 35.7295030, 139.7141470, '2026-01-05 10:00:00', NOW()),
(6, 2, 's00000006', 'CLUB VELVET', '104-0061', '東京都', '中央区', '銀座 6-8-1', '03-1111-2001', '19:30:00', 1, 35.6720000, 139.7620000, '2026-03-15 10:00:00', NOW()),
(7, 2, 's00000007', 'CLUB ORCHID', '106-0032', '東京都', '港区', '六本木 3-14-2', '03-2222-3001', '20:00:00', 1, 35.6640000, 139.7310000, '2026-03-20 10:00:00', NOW()),
(8, 1, 's00000008', 'SUGAR club', '150-0042', '東京都', '渋谷区', '宇田川町 15-3', '03-3333-4001', '19:00:00', 1, 35.6600000, 139.6980000, '2026-04-01 10:00:00', NOW()),
(9, 1, 's00000009', 'PINK KISS', '160-0022', '東京都', '新宿区', '歌舞伎町 1-25-6', '03-4444-5001', '20:00:00', 0, 35.6960000, 139.7020000, '2026-04-05 10:00:00', NOW()),
(10, 5, 's00000010', 'CAFE BLOOM', '107-0061', '東京都', '港区', '北青山 3-6-8', '03-2222-6001', '11:00:00', 0, 35.6660000, 139.7130000, '2026-04-10 10:00:00', NOW()),
(11, 5, 's00000011', 'CAFE MILLE', '155-0031', '東京都', '世田谷区', '北沢 2-14-15', '03-3333-7001', '10:00:00', 0, 35.6620000, 139.6680000, '2026-04-15 10:00:00', NOW()),
(12, 6, 's00000012', 'SNACK YURI', '164-0001', '東京都', '中野区', '中野 5-52-15', '03-4444-8001', '20:00:00', 1, 35.7080000, 139.6640000, '2026-04-20 10:00:00', NOW()),
(13, 6, 's00000013', 'SNACK MAI', '110-0005', '東京都', '台東区', '上野 6-10-11', '03-4444-9001', '19:30:00', 1, 35.7100000, 139.7770000, '2026-05-01 10:00:00', NOW()),
(14, 3, 's00000014', 'LOUNGE PLATINUM', '106-0032', '東京都', '港区', '六本木 4-3-9', '03-2222-1002', '19:00:00', 1, 35.6650000, 139.7290000, '2026-05-05 10:00:00', NOW()),
(15, 3, 's00000015', 'LOUNGE AMBER', '104-0061', '東京都', '中央区', '銀座 5-6-8', '03-1111-2002', '19:30:00', 1, 35.6720000, 139.7640000, '2026-05-10 10:00:00', NOW()),
(16, 3, 's00000016', 'LOUNGE ETOILE', '107-0062', '東京都', '港区', '南青山 5-4-30', '03-2222-2002', '19:00:00', 1, 35.6650000, 139.7180000, '2026-05-15 10:00:00', NOW()),
(17, 2, 's00000017', 'CLUB DIAMOND', '542-0086', '大阪府', '大阪市中央区', '西心斎橋 2-4-4', '06-6666-1001', '19:30:00', 1, 34.6710000, 135.5010000, '2026-05-20 10:00:00', NOW()),
(18, 2, 's00000018', 'CLUB SAKURA', '650-0004', '兵庫県', '神戸市中央区', '中山手通 1-24-1', '078-777-1001', '19:00:00', 1, 34.6950000, 135.1970000, '2026-06-01 10:00:00', NOW()),
(19, 1, 's00000019', 'CLUB CHERRY', '460-0008', '愛知県', '名古屋市中区', '栄 3-15-10', '052-333-1001', '20:00:00', 1, 35.1690000, 136.9080000, '2026-06-05 10:00:00', NOW()),
(20, 6, 's00000020', 'SNACK KUMI', '101-0047', '東京都', '千代田区', '内神田 3-4-2', '03-1111-3002', '19:00:00', 1, 35.6910000, 139.7710000, '2026-06-10 10:00:00', NOW()),
(21, 6, 's00000021', 'SNACK KAZE', '060-0063', '北海道', '札幌市中央区', '南三条西 5-32', '011-222-1001', '20:00:00', 1, 43.0570000, 141.3540000, '2026-06-15 10:00:00', NOW()),
(22, 5, 's00000022', 'CAFE STELLA', '810-0021', '福岡県', '福岡市中央区', '今泉 1-19-25', '092-777-1001', '11:00:00', 0, 33.5860000, 130.3970000, '2026-06-20 10:00:00', NOW()),
(23, 3, 's00000023', 'LOUNGE VENUS', '231-0005', '神奈川県', '横浜市中区', '本町 2-22', '045-333-1001', '19:00:00', 1, 35.4470000, 139.6430000, '2026-06-25 10:00:00', NOW()),
(24, 2, 's00000024', 'CLUB MOONLIGHT', '104-0061', '東京都', '中央区', '銀座 7-9-15', '03-1111-4002', '19:30:00', 1, 35.6710000, 139.7610000, '2026-07-01 10:00:00', NOW()),
(25, 1, 's00000025', 'GIRL POWER', '150-0042', '東京都', '渋谷区', '宇田川町 22-3', '03-3333-5002', '19:00:00', 1, 35.6610000, 139.6970000, '2026-07-05 10:00:00', NOW());

-- =============================================================================
-- Phase 5: shop_jobs（求人票 25 件）
-- =============================================================================

INSERT INTO `shop_jobs` (`id`, `shop_id`, `pr`, `catch_copy`, `job_content`, `regular_status`, `regular_hourly_wage`, `regular_hourly_wage_max`, `norma_day`, `bonus_reward`, `trial_hourly_wage`, `trial_hourly_wage_max`, `trial_status`, `has_help`, `help_hourly_wage`, `help_hourly_wage_max`, `help_status`, `working_day`, `working_hours`, `shift_time_start`, `shift_time_end`, `shift_end_is_last`, `qualification`, `created_at`, `updated_at`) VALUES
(1, 's00000001', '銀座エリアの高級クラブです。未経験も歓迎！', '入店祝い金 15万円！', '接客業務全般', 1, '6000', 12000, 5, 150000, '4000', 6000, 1, 1, '4500', 6000, 1, '週2〜', '19:00〜LAST', '19:00:00', NULL, 1, '18歳以上（高校生不可）', '2026-01-10 10:00:00', NOW()),
(2, 's00000002', '六本木の隠れ家的スナック', 'ボーナス 10万円！', '会話と接客', 1, '5000', 10000, 3, 100000, '3500', 5000, 1, 1, '4000', 5500, 1, '週1〜', '19:00〜3:00', '19:00:00', '03:00:00', 0, '20歳以上', '2026-02-01 10:00:00', NOW()),
(3, 's00000003', '渋谷のオシャレカフェ', NULL, 'カフェ業務', 0, '1500', 1800, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, '2026-02-10 10:00:00', NOW()),
(4, 's00000004', '新宿のアットホームスナック', 'ボーナス 5万円', '接客', 0, '4000', NULL, NULL, 50000, '3000', NULL, 0, 0, NULL, NULL, 0, '週3〜', '20:00〜2:00', '20:00:00', '02:00:00', 0, '20歳以上', '2026-03-01 10:00:00', NOW()),
(5, 's00000005', '池袋の落ち着いたラウンジ', 'ボーナス 8万円', 'ラウンジ接客', 1, '4500', 8000, 5, 80000, '3200', 4500, 1, 1, '4000', 5000, 1, '週2〜', '19:00〜LAST', '19:00:00', NULL, 1, '18歳以上（高校生不可）', '2026-01-05 10:00:00', NOW()),
(6, 's00000006', '銀座の老舗高級クラブ、体験入店大歓迎', '入店祝い金 20万円！', '接客・会話', 1, '7000', 15000, 5, 200000, '5000', 8000, 1, 1, '5500', 7000, 1, '週2〜', '19:00〜LAST', '19:30:00', NULL, 1, '18歳以上（高校生不可）', '2026-03-15 10:00:00', NOW()),
(7, 's00000007', '六本木の華やかな高級店', 'ボーナス 12万円', '接客業務', 1, '6500', 13000, 5, 120000, '4500', 7000, 1, 1, '5000', 6500, 1, '週2〜', '20:00〜LAST', '20:00:00', NULL, 1, '18歳以上', '2026-03-20 10:00:00', NOW()),
(8, 's00000008', '渋谷の可愛い系ポップクラブ', 'ボーナス 8万円', '接客', 1, '5000', 9000, 3, 80000, '3800', 5500, 1, 1, '4200', 5500, 1, '週1〜', '19:00〜LAST', '19:00:00', NULL, 1, '18歳以上', '2026-04-01 10:00:00', NOW()),
(9, 's00000009', '歌舞伎町のギャル系店', 'ボーナス 7万円', 'ホール・接客', 1, '4500', 8000, 3, 70000, '3500', 5000, 1, 1, '4000', 5000, 1, '週2〜', '20:00〜LAST', '20:00:00', NULL, 1, '18歳以上（高校生不可）', '2026-04-05 10:00:00', NOW()),
(10, 's00000010', '青山のカフェ、朝〜夜まで', NULL, 'カフェ業務・軽い接客', 0, '1600', 2000, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, 0, '週3〜', '11:00〜21:00', '11:00:00', '21:00:00', 0, NULL, '2026-04-10 10:00:00', NOW()),
(11, 's00000011', '下北沢のカジュアルカフェ', NULL, 'カフェ・接客', 0, '1400', 1700, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, 0, '週2〜', '10:00〜19:00', '10:00:00', '19:00:00', 0, NULL, '2026-04-15 10:00:00', NOW()),
(12, 's00000012', '中野の下町スナック', 'ボーナス 3万円', '接客・会話', 0, '3800', NULL, NULL, 30000, '2800', NULL, 0, 0, NULL, NULL, 0, '週2〜', '20:00〜2:00', '20:00:00', '02:00:00', 0, '20歳以上', '2026-04-20 10:00:00', NOW()),
(13, 's00000013', '上野の老舗スナック、ママ大好き', 'ボーナス 4万円', '接客', 0, '4000', NULL, NULL, 40000, '3000', NULL, 0, 0, NULL, NULL, 0, '週2〜', '19:30〜2:00', '19:30:00', '02:00:00', 0, '20歳以上', '2026-05-01 10:00:00', NOW()),
(14, 's00000014', '六本木の落ち着いたラウンジ', 'ボーナス 10万円', 'ラウンジ接客', 1, '5500', 10000, 5, 100000, '4000', 5500, 1, 1, '5000', 6000, 1, '週2〜', '19:00〜LAST', '19:00:00', NULL, 1, '18歳以上', '2026-05-05 10:00:00', NOW()),
(15, 's00000015', '銀座の隠れ家ラウンジ', 'ボーナス 9万円', 'ラウンジ接客', 1, '5000', 9000, 5, 90000, '3800', 5000, 1, 1, '4500', 5500, 1, '週2〜', '19:30〜LAST', '19:30:00', NULL, 1, '18歳以上', '2026-05-10 10:00:00', NOW()),
(16, 's00000016', '青山の大人ラウンジ', 'ボーナス 8万円', 'ラウンジ接客', 1, '4800', 8500, 5, 80000, '3500', 4800, 1, 1, '4200', 5200, 1, '週2〜', '19:00〜LAST', '19:00:00', NULL, 1, '18歳以上', '2026-05-15 10:00:00', NOW()),
(17, 's00000017', '大阪ミナミの高級クラブ', 'ボーナス 15万円', '接客業務', 1, '6000', 12000, 5, 150000, '4000', 6000, 1, 1, '4500', 6000, 1, '週2〜', '19:30〜LAST', '19:30:00', NULL, 1, '18歳以上（高校生不可）', '2026-05-20 10:00:00', NOW()),
(18, 's00000018', '神戸の落ち着いた高級クラブ', 'ボーナス 12万円', '接客', 1, '5500', 11000, 5, 120000, '3800', 5500, 1, 1, '4200', 5500, 1, '週2〜', '19:00〜LAST', '19:00:00', NULL, 1, '18歳以上', '2026-06-01 10:00:00', NOW()),
(19, 's00000019', '名古屋・栄のポップクラブ', 'ボーナス 8万円', '接客', 1, '4800', 9000, 3, 80000, '3500', 5000, 1, 1, '4000', 5000, 1, '週2〜', '20:00〜LAST', '20:00:00', NULL, 1, '18歳以上', '2026-06-05 10:00:00', NOW()),
(20, 's00000020', '神田のカウンター中心スナック', 'ボーナス 3万円', '接客・会話', 0, '3800', NULL, NULL, 30000, '2800', NULL, 0, 0, NULL, NULL, 0, '週2〜', '19:00〜1:00', '19:00:00', '01:00:00', 0, '20歳以上', '2026-06-10 10:00:00', NOW()),
(21, 's00000021', '札幌ススキノのアットホームスナック', 'ボーナス 4万円', '接客', 0, '3500', NULL, NULL, 40000, '2500', NULL, 0, 0, NULL, NULL, 0, '週2〜', '20:00〜3:00', '20:00:00', '03:00:00', 0, '20歳以上', '2026-06-15 10:00:00', NOW()),
(22, 's00000022', '福岡・今泉のオシャレカフェ', NULL, 'カフェ業務', 0, '1300', 1600, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, 0, '週3〜', '11:00〜20:00', '11:00:00', '20:00:00', 0, NULL, '2026-06-20 10:00:00', NOW()),
(23, 's00000023', '横浜・関内の大人ラウンジ', 'ボーナス 7万円', 'ラウンジ接客', 1, '4500', 8000, 5, 70000, '3200', 4500, 1, 1, '4000', 5000, 1, '週2〜', '19:00〜LAST', '19:00:00', NULL, 1, '18歳以上', '2026-06-25 10:00:00', NOW()),
(24, 's00000024', '銀座の月明かりが似合う高級クラブ', 'ボーナス 15万円', '接客業務', 1, '6500', 13000, 5, 150000, '4500', 7000, 1, 1, '5000', 6500, 1, '週2〜', '19:30〜LAST', '19:30:00', NULL, 1, '18歳以上（高校生不可）', '2026-07-01 10:00:00', NOW()),
(25, 's00000025', '渋谷の元気なガールズバー', 'ボーナス 5万円', 'ホール・接客', 1, '4500', 7500, 3, 50000, '3500', 4500, 1, 1, '4000', 5000, 1, '週1〜', '19:00〜LAST', '19:00:00', NULL, 1, '18歳以上', '2026-07-05 10:00:00', NOW());

-- =============================================================================
-- Phase 6: cast_identity_documents（40 件）
--   0=DRAFT / 1=PENDING / 2=APPROVED / 3=REJECTED
-- =============================================================================

INSERT INTO `cast_identity_documents` (`id`, `cast_id`, `category`, `type`, `image_path_front`, `image_path_back`, `status`, `ng_reason`, `expired_at`, `approved_at`, `created_at`, `updated_at`) VALUES
-- 旧シナリオ
(1, 'c00000001', 'photo_id', 'driver_license', 'private/dummy/c001_front.jpg', 'private/dummy/c001_back.jpg', 2, NULL, '2030-12-31', DATE_SUB(NOW(), INTERVAL 30 DAY), '2026-01-15 10:00:00', DATE_SUB(NOW(), INTERVAL 30 DAY)),
(2, 'c00000002', 'photo_id', 'mynumber_card', 'private/dummy/c002_front.jpg', NULL, 2, NULL, '2029-06-30', DATE_SUB(NOW(), INTERVAL 45 DAY), '2026-02-01 10:00:00', DATE_SUB(NOW(), INTERVAL 45 DAY)),
(3, 'c00000003', 'photo_id', 'passport', 'private/dummy/c003_front.jpg', 'private/dummy/c003_back.jpg', 0, NULL, '2028-03-15', NULL, DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(4, 'c00000004', 'photo_id', 'driver_license', 'private/dummy/c004_front.jpg', 'private/dummy/c004_back.jpg', 1, NULL, '2031-05-10', NULL, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(5, 'c00000005', 'photo_id', 'driver_license', 'private/dummy/c005_front.jpg', NULL, 3, '書類が不鮮明です。もう一度撮影し直してご提出ください。', '2030-08-20', NULL, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),
(6, 'c00000008', 'photo_id', 'mynumber_card', 'private/dummy/c008_front.jpg', NULL, 2, NULL, '2029-11-05', DATE_SUB(NOW(), INTERVAL 10 DAY), '2026-05-01 10:00:00', DATE_SUB(NOW(), INTERVAL 10 DAY)),
(7, 'c00000009', 'photo_id', 'driver_license', 'private/dummy/c009_front.jpg', 'private/dummy/c009_back.jpg', 2, NULL, '2030-01-25', DATE_SUB(NOW(), INTERVAL 60 DAY), '2026-05-15 10:00:00', DATE_SUB(NOW(), INTERVAL 60 DAY)),
(8, 'c00000010', 'photo_id', 'passport', 'private/dummy/c010_front.jpg', 'private/dummy/c010_back.jpg', 2, NULL, '2028-12-08', DATE_SUB(NOW(), INTERVAL 90 DAY), '2026-06-01 10:00:00', DATE_SUB(NOW(), INTERVAL 90 DAY)),
-- c011-c050: 承認済み中心（Tier A/B の active カストは identity_status=3 のため）
(9, 'c00000011', 'photo_id', 'driver_license', 'private/dummy/c011_front.jpg', 'private/dummy/c011_back.jpg', 2, NULL, '2030-06-01', DATE_SUB(NOW(), INTERVAL 20 DAY), '2026-06-10 10:00:00', DATE_SUB(NOW(), INTERVAL 20 DAY)),
(10, 'c00000012', 'photo_id', 'mynumber_card', 'private/dummy/c012_front.jpg', NULL, 2, NULL, '2029-08-11', DATE_SUB(NOW(), INTERVAL 18 DAY), '2026-06-12 10:00:00', DATE_SUB(NOW(), INTERVAL 18 DAY)),
(11, 'c00000013', 'photo_id', 'driver_license', 'private/dummy/c013_front.jpg', 'private/dummy/c013_back.jpg', 2, NULL, '2030-02-14', DATE_SUB(NOW(), INTERVAL 15 DAY), '2026-06-15 10:00:00', DATE_SUB(NOW(), INTERVAL 15 DAY)),
(12, 'c00000014', 'photo_id', 'passport', 'private/dummy/c014_front.jpg', 'private/dummy/c014_back.jpg', 2, NULL, '2029-08-22', DATE_SUB(NOW(), INTERVAL 12 DAY), '2026-06-18 10:00:00', DATE_SUB(NOW(), INTERVAL 12 DAY)),
(13, 'c00000015', 'photo_id', 'driver_license', 'private/dummy/c015_front.jpg', 'private/dummy/c015_back.jpg', 2, NULL, '2031-03-30', DATE_SUB(NOW(), INTERVAL 10 DAY), '2026-06-20 10:00:00', DATE_SUB(NOW(), INTERVAL 10 DAY)),
(14, 'c00000016', 'photo_id', 'mynumber_card', 'private/dummy/c016_front.jpg', NULL, 2, NULL, '2028-12-05', DATE_SUB(NOW(), INTERVAL 9 DAY), '2026-06-22 10:00:00', DATE_SUB(NOW(), INTERVAL 9 DAY)),
(15, 'c00000017', 'photo_id', 'driver_license', 'private/dummy/c017_front.jpg', 'private/dummy/c017_back.jpg', 2, NULL, '2030-05-08', DATE_SUB(NOW(), INTERVAL 8 DAY), '2026-06-25 10:00:00', DATE_SUB(NOW(), INTERVAL 8 DAY)),
(16, 'c00000018', 'photo_id', 'passport', 'private/dummy/c018_front.jpg', 'private/dummy/c018_back.jpg', 1, NULL, '2029-09-19', NULL, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),
(17, 'c00000019', 'photo_id', 'driver_license', 'private/dummy/c019_front.jpg', 'private/dummy/c019_back.jpg', 1, NULL, '2030-06-25', NULL, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(18, 'c00000020', 'photo_id', 'driver_license', 'private/dummy/c020_front.jpg', 'private/dummy/c020_back.jpg', 2, NULL, '2029-11-02', DATE_SUB(NOW(), INTERVAL 7 DAY), '2026-07-05 10:00:00', DATE_SUB(NOW(), INTERVAL 7 DAY)),
(19, 'c00000021', 'photo_id', 'mynumber_card', 'private/dummy/c021_front.jpg', NULL, 2, NULL, '2028-04-17', DATE_SUB(NOW(), INTERVAL 6 DAY), '2026-07-08 10:00:00', DATE_SUB(NOW(), INTERVAL 6 DAY)),
(20, 'c00000022', 'photo_id', 'driver_license', 'private/dummy/c022_front.jpg', 'private/dummy/c022_back.jpg', 1, NULL, '2029-08-08', NULL, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(21, 'c00000023', 'photo_id', 'driver_license', 'private/dummy/c023_front.jpg', 'private/dummy/c023_back.jpg', 2, NULL, '2030-01-13', DATE_SUB(NOW(), INTERVAL 5 DAY), '2026-07-12 10:00:00', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(22, 'c00000024', 'photo_id', 'passport', 'private/dummy/c024_front.jpg', 'private/dummy/c024_back.jpg', 2, NULL, '2029-06-06', DATE_SUB(NOW(), INTERVAL 4 DAY), '2026-07-15 10:00:00', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(23, 'c00000025', 'photo_id', 'driver_license', 'private/dummy/c025_front.jpg', 'private/dummy/c025_back.jpg', 2, NULL, '2030-10-21', DATE_SUB(NOW(), INTERVAL 3 DAY), '2026-07-18 10:00:00', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(24, 'c00000026', 'photo_id', 'passport', 'private/dummy/c026_front.jpg', 'private/dummy/c026_back.jpg', 0, NULL, '2029-03-03', NULL, DATE_SUB(NOW(), INTERVAL 12 HOUR), DATE_SUB(NOW(), INTERVAL 12 HOUR)),
(25, 'c00000027', 'photo_id', 'driver_license', 'private/dummy/c027_front.jpg', 'private/dummy/c027_back.jpg', 2, NULL, '2028-07-27', DATE_SUB(NOW(), INTERVAL 25 DAY), '2026-07-22 10:00:00', DATE_SUB(NOW(), INTERVAL 25 DAY)),
(26, 'c00000028', 'photo_id', 'driver_license', 'private/dummy/c028_front.jpg', 'private/dummy/c028_back.jpg', 1, NULL, '2030-12-15', NULL, DATE_SUB(NOW(), INTERVAL 6 HOUR), DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(27, 'c00000029', 'photo_id', 'mynumber_card', 'private/dummy/c029_front.jpg', NULL, 2, NULL, '2029-09-09', DATE_SUB(NOW(), INTERVAL 40 DAY), '2026-07-25 10:00:00', DATE_SUB(NOW(), INTERVAL 40 DAY)),
(28, 'c00000030', 'photo_id', 'driver_license', 'private/dummy/c030_front.jpg', 'private/dummy/c030_back.jpg', 2, NULL, '2031-05-30', DATE_SUB(NOW(), INTERVAL 30 DAY), '2026-07-28 10:00:00', DATE_SUB(NOW(), INTERVAL 30 DAY)),
(29, 'c00000031', 'photo_id', 'driver_license', 'private/dummy/c031_front.jpg', 'private/dummy/c031_back.jpg', 2, NULL, '2029-02-11', DATE_SUB(NOW(), INTERVAL 45 DAY), '2026-07-30 10:00:00', DATE_SUB(NOW(), INTERVAL 45 DAY)),
(30, 'c00000032', 'photo_id', 'passport', 'private/dummy/c032_front.jpg', 'private/dummy/c032_back.jpg', 1, NULL, '2030-04-04', NULL, DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY)),
(31, 'c00000033', 'photo_id', 'driver_license', 'private/dummy/c033_front.jpg', 'private/dummy/c033_back.jpg', 2, NULL, '2029-08-18', DATE_SUB(NOW(), INTERVAL 50 DAY), '2026-06-01 10:00:00', DATE_SUB(NOW(), INTERVAL 50 DAY)),
(32, 'c00000034', 'photo_id', 'mynumber_card', 'private/dummy/c034_front.jpg', NULL, 2, NULL, '2028-11-23', DATE_SUB(NOW(), INTERVAL 55 DAY), '2026-06-05 10:00:00', DATE_SUB(NOW(), INTERVAL 55 DAY)),
(33, 'c00000035', 'photo_id', 'driver_license', 'private/dummy/c035_front.jpg', 'private/dummy/c035_back.jpg', 1, NULL, '2029-06-18', NULL, DATE_SUB(NOW(), INTERVAL 14 DAY), DATE_SUB(NOW(), INTERVAL 14 DAY)),
(34, 'c00000036', 'photo_id', 'driver_license', 'private/dummy/c036_front.jpg', 'private/dummy/c036_back.jpg', 2, NULL, '2029-12-01', DATE_SUB(NOW(), INTERVAL 60 DAY), '2026-06-12 10:00:00', DATE_SUB(NOW(), INTERVAL 60 DAY)),
(35, 'c00000037', 'photo_id', 'passport', 'private/dummy/c037_front.jpg', 'private/dummy/c037_back.jpg', 2, NULL, '2028-01-25', DATE_SUB(NOW(), INTERVAL 65 DAY), '2026-06-18 10:00:00', DATE_SUB(NOW(), INTERVAL 65 DAY)),
(36, 'c00000038', 'photo_id', 'driver_license', 'private/dummy/c038_front.jpg', 'private/dummy/c038_back.jpg', 2, NULL, '2030-10-10', DATE_SUB(NOW(), INTERVAL 70 DAY), '2026-06-22 10:00:00', DATE_SUB(NOW(), INTERVAL 70 DAY)),
(37, 'c00000039', 'photo_id', 'mynumber_card', 'private/dummy/c039_front.jpg', NULL, 2, NULL, '2029-05-05', DATE_SUB(NOW(), INTERVAL 80 DAY), '2026-06-25 10:00:00', DATE_SUB(NOW(), INTERVAL 80 DAY)),
(38, 'c00000041', 'photo_id', 'driver_license', 'private/dummy/c041_front.jpg', 'private/dummy/c041_back.jpg', 2, NULL, '2030-03-20', DATE_SUB(NOW(), INTERVAL 25 DAY), '2026-07-03 10:00:00', DATE_SUB(NOW(), INTERVAL 25 DAY)),
(39, 'c00000045', 'photo_id', 'driver_license', 'private/dummy/c045_front.jpg', 'private/dummy/c045_back.jpg', 1, NULL, '2029-06-13', NULL, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY)),
(40, 'c00000048', 'photo_id', 'passport', 'private/dummy/c048_front.jpg', 'private/dummy/c048_back.jpg', 2, NULL, '2028-08-08', DATE_SUB(NOW(), INTERVAL 20 DAY), '2026-07-20 10:00:00', DATE_SUB(NOW(), INTERVAL 20 DAY));

-- =============================================================================
-- Phase 7: shop_license_documents（30 件）
--   0=DRAFT / 1=PENDING / 2=APPROVED / 3=REJECTED
-- =============================================================================

INSERT INTO `shop_license_documents` (`id`, `shop_id`, `type`, `image_path`, `status`, `ng_reason`, `expired_at`, `approved_at`, `created_at`, `updated_at`) VALUES
-- 旧シナリオ
(1, 's00000001', 'business', 'private/dummy/s001_business.jpg', 2, NULL, '2030-06-30', DATE_SUB(NOW(), INTERVAL 60 DAY), '2026-01-10 10:00:00', DATE_SUB(NOW(), INTERVAL 60 DAY)),
(2, 's00000001', 'entertainment', 'private/dummy/s001_entertainment.jpg', 2, NULL, NULL, DATE_SUB(NOW(), INTERVAL 60 DAY), '2026-01-10 10:00:00', DATE_SUB(NOW(), INTERVAL 60 DAY)),
(3, 's00000002', 'business', 'private/dummy/s002_business.jpg', 1, NULL, '2029-12-15', NULL, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(4, 's00000002', 'entertainment', 'private/dummy/s002_entertainment.jpg', 0, NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(5, 's00000003', 'business', 'private/dummy/s003_business.jpg', 3, '許可書の日付が判読できません。全体が写るように再度アップロードしてください。', '2028-08-31', NULL, DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY)),
(6, 's00000005', 'business', 'private/dummy/s005_business.jpg', 2, NULL, '2029-05-01', DATE_SUB(NOW(), INTERVAL 200 DAY), '2026-01-05 10:00:00', DATE_SUB(NOW(), INTERVAL 200 DAY)),
(7, 's00000005', 'entertainment', 'private/dummy/s005_entertainment.jpg', 2, NULL, NULL, DATE_SUB(NOW(), INTERVAL 200 DAY), '2026-01-05 10:00:00', DATE_SUB(NOW(), INTERVAL 200 DAY)),
-- s006-s025: 概ね承認済み
(8, 's00000006', 'business', 'private/dummy/s006_business.jpg', 2, NULL, '2030-09-15', DATE_SUB(NOW(), INTERVAL 100 DAY), '2026-03-15 10:00:00', DATE_SUB(NOW(), INTERVAL 100 DAY)),
(9, 's00000006', 'entertainment', 'private/dummy/s006_entertainment.jpg', 2, NULL, NULL, DATE_SUB(NOW(), INTERVAL 100 DAY), '2026-03-15 10:00:00', DATE_SUB(NOW(), INTERVAL 100 DAY)),
(10, 's00000007', 'business', 'private/dummy/s007_business.jpg', 2, NULL, '2029-11-20', DATE_SUB(NOW(), INTERVAL 90 DAY), '2026-03-20 10:00:00', DATE_SUB(NOW(), INTERVAL 90 DAY)),
(11, 's00000007', 'entertainment', 'private/dummy/s007_entertainment.jpg', 2, NULL, NULL, DATE_SUB(NOW(), INTERVAL 90 DAY), '2026-03-20 10:00:00', DATE_SUB(NOW(), INTERVAL 90 DAY)),
(12, 's00000008', 'business', 'private/dummy/s008_business.jpg', 2, NULL, '2030-04-01', DATE_SUB(NOW(), INTERVAL 80 DAY), '2026-04-01 10:00:00', DATE_SUB(NOW(), INTERVAL 80 DAY)),
(13, 's00000008', 'entertainment', 'private/dummy/s008_entertainment.jpg', 1, NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),
(14, 's00000009', 'business', 'private/dummy/s009_business.jpg', 2, NULL, '2029-10-05', DATE_SUB(NOW(), INTERVAL 75 DAY), '2026-04-05 10:00:00', DATE_SUB(NOW(), INTERVAL 75 DAY)),
(15, 's00000009', 'entertainment', 'private/dummy/s009_entertainment.jpg', 2, NULL, NULL, DATE_SUB(NOW(), INTERVAL 75 DAY), '2026-04-05 10:00:00', DATE_SUB(NOW(), INTERVAL 75 DAY)),
(16, 's00000010', 'business', 'private/dummy/s010_business.jpg', 2, NULL, '2030-04-10', DATE_SUB(NOW(), INTERVAL 70 DAY), '2026-04-10 10:00:00', DATE_SUB(NOW(), INTERVAL 70 DAY)),
(17, 's00000011', 'business', 'private/dummy/s011_business.jpg', 2, NULL, '2029-04-15', DATE_SUB(NOW(), INTERVAL 65 DAY), '2026-04-15 10:00:00', DATE_SUB(NOW(), INTERVAL 65 DAY)),
(18, 's00000012', 'business', 'private/dummy/s012_business.jpg', 2, NULL, '2028-04-20', DATE_SUB(NOW(), INTERVAL 60 DAY), '2026-04-20 10:00:00', DATE_SUB(NOW(), INTERVAL 60 DAY)),
(19, 's00000014', 'business', 'private/dummy/s014_business.jpg', 2, NULL, '2030-05-05', DATE_SUB(NOW(), INTERVAL 55 DAY), '2026-05-05 10:00:00', DATE_SUB(NOW(), INTERVAL 55 DAY)),
(20, 's00000014', 'entertainment', 'private/dummy/s014_entertainment.jpg', 2, NULL, NULL, DATE_SUB(NOW(), INTERVAL 55 DAY), '2026-05-05 10:00:00', DATE_SUB(NOW(), INTERVAL 55 DAY)),
(21, 's00000015', 'business', 'private/dummy/s015_business.jpg', 2, NULL, '2029-05-10', DATE_SUB(NOW(), INTERVAL 50 DAY), '2026-05-10 10:00:00', DATE_SUB(NOW(), INTERVAL 50 DAY)),
(22, 's00000015', 'entertainment', 'private/dummy/s015_entertainment.jpg', 2, NULL, NULL, DATE_SUB(NOW(), INTERVAL 50 DAY), '2026-05-10 10:00:00', DATE_SUB(NOW(), INTERVAL 50 DAY)),
(23, 's00000016', 'business', 'private/dummy/s016_business.jpg', 2, NULL, '2030-05-15', DATE_SUB(NOW(), INTERVAL 45 DAY), '2026-05-15 10:00:00', DATE_SUB(NOW(), INTERVAL 45 DAY)),
(24, 's00000017', 'business', 'private/dummy/s017_business.jpg', 2, NULL, '2030-05-20', DATE_SUB(NOW(), INTERVAL 40 DAY), '2026-05-20 10:00:00', DATE_SUB(NOW(), INTERVAL 40 DAY)),
(25, 's00000018', 'business', 'private/dummy/s018_business.jpg', 2, NULL, '2029-06-01', DATE_SUB(NOW(), INTERVAL 35 DAY), '2026-06-01 10:00:00', DATE_SUB(NOW(), INTERVAL 35 DAY)),
(26, 's00000019', 'business', 'private/dummy/s019_business.jpg', 2, NULL, '2029-06-05', DATE_SUB(NOW(), INTERVAL 30 DAY), '2026-06-05 10:00:00', DATE_SUB(NOW(), INTERVAL 30 DAY)),
(27, 's00000020', 'business', 'private/dummy/s020_business.jpg', 2, NULL, '2028-06-10', DATE_SUB(NOW(), INTERVAL 25 DAY), '2026-06-10 10:00:00', DATE_SUB(NOW(), INTERVAL 25 DAY)),
(28, 's00000021', 'business', 'private/dummy/s021_business.jpg', 2, NULL, '2029-06-15', DATE_SUB(NOW(), INTERVAL 20 DAY), '2026-06-15 10:00:00', DATE_SUB(NOW(), INTERVAL 20 DAY)),
(29, 's00000023', 'business', 'private/dummy/s023_business.jpg', 2, NULL, '2030-06-25', DATE_SUB(NOW(), INTERVAL 15 DAY), '2026-06-25 10:00:00', DATE_SUB(NOW(), INTERVAL 15 DAY)),
(30, 's00000024', 'business', 'private/dummy/s024_business.jpg', 2, NULL, '2030-07-01', DATE_SUB(NOW(), INTERVAL 10 DAY), '2026-07-01 10:00:00', DATE_SUB(NOW(), INTERVAL 10 DAY));

-- =============================================================================
-- Phase 8: shop_plan_subscriptions（Premium）
--   status: 1=入金待ち 2=有効 3=期間満了 4=キャンセル
-- =============================================================================

INSERT INTO `shop_plan_subscriptions` (`shop_id`, `plan`, `billing_cycle`, `amount`, `status`, `invoice_number`, `invoice_issued_at`, `payment_due_date`, `paid_confirmed_at`, `confirmed_by`, `receipt_number`, `starts_at`, `ends_at`, `created_at`, `updated_at`) VALUES
-- 旧シナリオ
('s00000001', 'premium', 'monthly', 29000, 2, 'PLN-202608-0001', DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_ADD(DATE_SUB(NOW(), INTERVAL 15 DAY), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY), '1', 'RCT-202608-0001', DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_ADD(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),
('s00000004', 'premium', 'monthly', 29000, 1, 'PLN-202608-0002', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_ADD(NOW(), INTERVAL 4 DAY), NULL, NULL, NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),
('s00000005', 'premium', 'monthly', 29000, 3, 'PLN-202605-0001', '2026-05-01 10:00:00', '2026-05-08', '2026-05-05 10:00:00', '1', 'RCT-202605-0001', '2026-05-05 10:00:00', '2026-06-05 10:00:00', '2026-05-01 10:00:00', '2026-06-06 00:00:00'),
-- 新規: 有効な Premium 店舗（AIレコメンド優先表示テスト用）
('s00000006', 'premium', 'yearly', 290000, 2, 'PLN-202604-0001', '2026-04-01 10:00:00', '2026-04-08', '2026-04-05 10:00:00', '1', 'RCT-202604-0001', '2026-04-05 10:00:00', '2027-04-05 10:00:00', '2026-04-01 10:00:00', '2026-04-05 10:00:00'),
('s00000007', 'premium', 'monthly', 29000, 2, 'PLN-202608-0003', DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_ADD(DATE_SUB(NOW(), INTERVAL 20 DAY), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY), '1', 'RCT-202608-0003', DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_ADD(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY)),
('s00000014', 'premium', 'monthly', 29000, 2, 'PLN-202608-0004', DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_ADD(DATE_SUB(NOW(), INTERVAL 8 DAY), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY), '1', 'RCT-202608-0004', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_ADD(NOW(), INTERVAL 25 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY)),
('s00000017', 'premium', 'yearly', 290000, 2, 'PLN-202605-0002', '2026-05-20 10:00:00', '2026-05-27', '2026-05-25 10:00:00', '1', 'RCT-202605-0002', '2026-05-25 10:00:00', '2027-05-25 10:00:00', '2026-05-20 10:00:00', '2026-05-25 10:00:00'),
('s00000018', 'premium', 'monthly', 29000, 2, 'PLN-202608-0005', DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_ADD(DATE_SUB(NOW(), INTERVAL 6 DAY), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY), '1', 'RCT-202608-0005', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_ADD(NOW(), INTERVAL 27 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),
('s00000024', 'premium', 'monthly', 29000, 2, 'PLN-202608-0006', DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_ADD(DATE_SUB(NOW(), INTERVAL 4 DAY), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), '1', 'RCT-202608-0006', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_ADD(NOW(), INTERVAL 28 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
-- 入金待ちが更に 2 件
('s00000015', 'premium', 'monthly', 29000, 1, 'PLN-202608-0007', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_ADD(NOW(), INTERVAL 5 DAY), NULL, NULL, NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
('s00000023', 'premium', 'monthly', 29000, 1, 'PLN-202608-0008', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 6 DAY), NULL, NULL, NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY));

-- =============================================================================
-- Phase 9: shop_job_applications（35 件）
--   status: 1=やり取り中 2=面談日調整中 3=面談日決定 4=採用(体験) 5=不採用(体験) 6=採用(本入) 7=不採用(本入)
-- =============================================================================

INSERT INTO `shop_job_applications` (`id`, `cast_id`, `shop_job_id`, `status`, `hired_bonus_amount`, `talk_job_kind`, `result_date`, `real_start_date`, `hourly_wage_regular`, `created_at`, `updated_at`) VALUES
-- 旧シナリオ
(1, 'c00000003', 2, 2, NULL, 'trial', NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 'c00000004', 1, 1, NULL, NULL, NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, 'c00000009', 1, 4, 150000, 'trial', DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_SUB(CURDATE(), INTERVAL 3 DAY), '6000', DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),
(4, 'c00000010', 1, 6, 150000, 'fulltime', DATE_SUB(CURDATE(), INTERVAL 30 DAY), DATE_SUB(CURDATE(), INTERVAL 25 DAY), '6000', DATE_SUB(NOW(), INTERVAL 40 DAY), DATE_SUB(NOW(), INTERVAL 20 DAY)),
(5, 'c00000002', 1, 3, NULL, 'trial', DATE_ADD(CURDATE(), INTERVAL 3 DAY), NULL, NULL, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(6, 'c00000001', 2, 1, NULL, NULL, NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY), NOW()),
(7, 'c00000007', 5, 5, NULL, 'trial', DATE_SUB(CURDATE(), INTERVAL 10 DAY), NULL, NULL, DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),
-- 新規シナリオ（c011-c050 の絡み）
(8, 'c00000011', 6, 6, 200000, 'fulltime', DATE_SUB(CURDATE(), INTERVAL 20 DAY), DATE_SUB(CURDATE(), INTERVAL 15 DAY), '7000', DATE_SUB(NOW(), INTERVAL 35 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY)),
(9, 'c00000012', 6, 4, 200000, 'trial', DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_SUB(CURDATE(), INTERVAL 5 DAY), '7000', DATE_SUB(NOW(), INTERVAL 12 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY)),
(10, 'c00000013', 14, 4, 100000, 'trial', DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_SUB(CURDATE(), INTERVAL 2 DAY), '5500', DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(11, 'c00000014', 8, 3, NULL, 'trial', DATE_ADD(CURDATE(), INTERVAL 2 DAY), NULL, NULL, DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(12, 'c00000015', 6, 6, 200000, 'fulltime', DATE_SUB(CURDATE(), INTERVAL 40 DAY), DATE_SUB(CURDATE(), INTERVAL 35 DAY), '7000', DATE_SUB(NOW(), INTERVAL 50 DAY), DATE_SUB(NOW(), INTERVAL 35 DAY)),
(13, 'c00000016', 7, 1, NULL, NULL, NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(14, 'c00000017', 25, 4, 50000, 'trial', DATE_SUB(CURDATE(), INTERVAL 4 DAY), DATE_SUB(CURDATE(), INTERVAL 4 DAY), '4500', DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY)),
(15, 'c00000018', 9, 2, NULL, 'trial', NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(16, 'c00000019', 14, 3, NULL, 'trial', DATE_ADD(CURDATE(), INTERVAL 1 DAY), NULL, NULL, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(17, 'c00000020', 1, 1, NULL, NULL, NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 6 HOUR), NOW()),
(18, 'c00000021', 4, 4, 50000, 'trial', DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_SUB(CURDATE(), INTERVAL 1 DAY), '4000', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(19, 'c00000022', 5, 1, NULL, NULL, NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 12 HOUR), DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(20, 'c00000023', 23, 3, NULL, 'trial', DATE_ADD(CURDATE(), INTERVAL 5 DAY), NULL, NULL, DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(21, 'c00000024', 24, 4, 150000, 'trial', DATE_SUB(CURDATE(), INTERVAL 6 DAY), DATE_SUB(CURDATE(), INTERVAL 6 DAY), '6500', DATE_SUB(NOW(), INTERVAL 14 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY)),
(22, 'c00000025', 14, 1, NULL, NULL, NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(23, 'c00000027', 12, 4, 30000, 'trial', DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_SUB(CURDATE(), INTERVAL 2 DAY), '3800', DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(24, 'c00000028', 17, 5, NULL, 'trial', DATE_SUB(CURDATE(), INTERVAL 8 DAY), NULL, NULL, DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY)),
(25, 'c00000029', 1, 6, 150000, 'fulltime', DATE_SUB(CURDATE(), INTERVAL 50 DAY), DATE_SUB(CURDATE(), INTERVAL 45 DAY), '6000', DATE_SUB(NOW(), INTERVAL 60 DAY), DATE_SUB(NOW(), INTERVAL 45 DAY)),
(26, 'c00000030', 8, 2, NULL, 'trial', NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(27, 'c00000031', 6, 6, 200000, 'fulltime', DATE_SUB(CURDATE(), INTERVAL 25 DAY), DATE_SUB(CURDATE(), INTERVAL 20 DAY), '7000', DATE_SUB(NOW(), INTERVAL 35 DAY), DATE_SUB(NOW(), INTERVAL 20 DAY)),
(28, 'c00000033', 4, 7, NULL, 'fulltime', DATE_SUB(CURDATE(), INTERVAL 15 DAY), NULL, NULL, DATE_SUB(NOW(), INTERVAL 25 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY)),
(29, 'c00000034', 15, 6, 90000, 'fulltime', DATE_SUB(CURDATE(), INTERVAL 30 DAY), DATE_SUB(CURDATE(), INTERVAL 25 DAY), '5000', DATE_SUB(NOW(), INTERVAL 40 DAY), DATE_SUB(NOW(), INTERVAL 25 DAY)),
(30, 'c00000036', 24, 3, NULL, 'trial', DATE_ADD(CURDATE(), INTERVAL 4 DAY), NULL, NULL, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(31, 'c00000041', 6, 1, NULL, NULL, NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 6 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(32, 'c00000043', 7, 4, 120000, 'trial', DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_SUB(CURDATE(), INTERVAL 1 DAY), '6500', DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(33, 'c00000044', 24, 2, NULL, 'trial', NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(34, 'c00000046', 15, 3, NULL, 'trial', DATE_ADD(CURDATE(), INTERVAL 2 DAY), NULL, NULL, DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(35, 'c00000048', 22, 1, NULL, NULL, NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 8 HOUR), DATE_SUB(NOW(), INTERVAL 3 HOUR));

-- =============================================================================
-- Phase 10: application_deposits + histories（15 件）
--   status: 1=請求書発行 2=店舗入金報告 3=店舗入金確認 4=キャスト振込済 5=完了
-- =============================================================================

INSERT INTO `application_deposits` (`id`, `shop_job_application_id`, `status`, `is_read`, `invoice_number`, `bonus_amount`, `system_fee_amount`, `invoice_amount`, `cast_transfer_amount`, `invoice_issued_at`, `invoice_due_date`, `shop_payment_confirmed_at`, `cast_transferred_at`, `completed_at`, `created_at`, `updated_at`) VALUES
-- 旧シナリオ
(1, 3, 1, 0, 'INV-202608-0001', 150000, 15000, 165000, 135000, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY), NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 4, 5, 1, 'INV-202607-0001', 150000, 15000, 165000, 135000, DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(CURDATE(), INTERVAL 13 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),
(3, 7, 1, 1, 'INV-202607-0002', NULL, NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 15 DAY), NULL, NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),
-- 新規: application ID 8, 9, 10, 12, 14, 18, 21, 23, 25, 27, 29, 32 の採用系
(4, 8, 5, 1, 'INV-202606-0001', 200000, 20000, 220000, 180000, '2026-07-25 10:00:00', '2026-08-01', '2026-07-27 10:00:00', '2026-07-30 10:00:00', '2026-07-30 10:00:00', '2026-07-25 10:00:00', '2026-07-30 10:00:00'),
(5, 9, 3, 0, 'INV-202608-0003', 200000, 20000, 220000, 180000, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), NULL, NULL, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(6, 10, 2, 0, 'INV-202608-0004', 100000, 10000, 110000, 90000, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY), NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(7, 12, 5, 1, 'INV-202606-0002', 200000, 20000, 220000, 180000, '2026-07-05 10:00:00', '2026-07-12', '2026-07-08 10:00:00', '2026-07-15 10:00:00', '2026-07-15 10:00:00', '2026-07-05 10:00:00', '2026-07-15 10:00:00'),
(8, 14, 4, 1, 'INV-202608-0005', 50000, 5000, 55000, 45000, DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_ADD(CURDATE(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), NULL, DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(9, 18, 3, 0, 'INV-202608-0006', 50000, 5000, 55000, 45000, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 6 HOUR), NULL, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(10, 21, 5, 1, 'INV-202607-0003', 150000, 15000, 165000, 135000, DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(11, 23, 2, 0, 'INV-202608-0007', 30000, 3000, 33000, 27000, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY), NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(12, 25, 5, 1, 'INV-202606-0003', 150000, 15000, 165000, 135000, '2026-06-25 10:00:00', '2026-07-02', '2026-06-28 10:00:00', '2026-07-05 10:00:00', '2026-07-05 10:00:00', '2026-06-25 10:00:00', '2026-07-05 10:00:00'),
(13, 27, 5, 1, 'INV-202607-0004', 200000, 20000, 220000, 180000, '2026-07-15 10:00:00', '2026-07-22', '2026-07-18 10:00:00', '2026-07-25 10:00:00', '2026-07-25 10:00:00', '2026-07-15 10:00:00', '2026-07-25 10:00:00'),
(14, 29, 5, 1, 'INV-202607-0005', 90000, 9000, 99000, 81000, '2026-07-10 10:00:00', '2026-07-17', '2026-07-13 10:00:00', '2026-07-20 10:00:00', '2026-07-20 10:00:00', '2026-07-10 10:00:00', '2026-07-20 10:00:00'),
(15, 32, 1, 0, 'INV-202608-0008', 120000, 12000, 132000, 108000, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 6 DAY), NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO `application_deposit_histories` (`application_deposit_id`, `status`, `status_date`, `created_at`) VALUES
(1, 1, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 1, DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 20 DAY)),
(2, 2, DATE_SUB(NOW(), INTERVAL 16 DAY), DATE_SUB(NOW(), INTERVAL 16 DAY)),
(2, 3, DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY)),
(2, 4, DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),
(2, 5, DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),
(4, 1, '2026-07-25 10:00:00', '2026-07-25 10:00:00'),
(4, 2, '2026-07-26 10:00:00', '2026-07-26 10:00:00'),
(4, 3, '2026-07-27 10:00:00', '2026-07-27 10:00:00'),
(4, 4, '2026-07-30 10:00:00', '2026-07-30 10:00:00'),
(4, 5, '2026-07-30 10:00:00', '2026-07-30 10:00:00'),
(5, 1, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY)),
(5, 2, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5, 3, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(6, 1, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(6, 2, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(7, 1, '2026-07-05 10:00:00', '2026-07-05 10:00:00'),
(7, 5, '2026-07-15 10:00:00', '2026-07-15 10:00:00'),
(8, 1, DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY)),
(8, 3, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(8, 4, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(9, 1, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(9, 3, DATE_SUB(NOW(), INTERVAL 6 HOUR), DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(10, 5, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(11, 1, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(11, 2, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(12, 5, '2026-07-05 10:00:00', '2026-07-05 10:00:00'),
(13, 5, '2026-07-25 10:00:00', '2026-07-25 10:00:00'),
(14, 5, '2026-07-20 10:00:00', '2026-07-20 10:00:00'),
(15, 1, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY));

-- =============================================================================
-- Phase 11: bank_accounts（採用済みキャスト + システム口座）
-- =============================================================================

INSERT INTO `bank_accounts` (`holder_type`, `holder_id`, `bank_code`, `bank_name`, `bank_name_kana`, `branch_code`, `branch_name`, `branch_name_kana`, `account_type`, `account_number`, `account_name`, `created_at`, `updated_at`) VALUES
('casts', 'c00000009', '0001', 'みずほ銀行', 'ミズホ', '001', '東京営業部', 'トウキヨウ', 'ordinary', '1234567', 'アベ エリカ', NOW(), NOW()),
('casts', 'c00000010', '0005', '三菱UFJ銀行', 'ミツビシユーエフジェイ', '051', '新宿支店', 'シンジユク', 'ordinary', '7654321', 'キムラ カナ', NOW(), NOW()),
('casts', 'c00000011', '0001', 'みずほ銀行', 'ミズホ', '015', '銀座支店', 'ギンザ', 'ordinary', '2345678', 'ハヤシ リナ', NOW(), NOW()),
('casts', 'c00000012', '0005', '三菱UFJ銀行', 'ミツビシユーエフジェイ', '004', '丸の内支店', 'マルノウチ', 'ordinary', '3456789', 'マツダ マユ', NOW(), NOW()),
('casts', 'c00000013', '0009', '三井住友銀行', 'ミツイスミトモ', '815', '本店営業部', 'ホンテン', 'ordinary', '4567890', 'アカギ アカネ', NOW(), NOW()),
('casts', 'c00000014', '0033', 'PayPay銀行', 'ペイペイ', '001', '本店営業部', 'ホンテン', 'ordinary', '5678901', 'ヒノ ヒナノ', NOW(), NOW()),
('casts', 'c00000015', '0001', 'みずほ銀行', 'ミズホ', '015', '銀座支店', 'ギンザ', 'ordinary', '6789012', 'チバ チサト', NOW(), NOW()),
('casts', 'c00000017', '0005', '三菱UFJ銀行', 'ミツビシユーエフジェイ', '053', '渋谷支店', 'シブヤ', 'ordinary', '7890123', 'ノダ ノア', NOW(), NOW()),
('casts', 'c00000021', '0009', '三井住友銀行', 'ミツイスミトモ', '600', '新宿西口支店', 'シンジユクニシグチ', 'ordinary', '8901234', 'イシダ ココミ', NOW(), NOW()),
('casts', 'c00000024', '0001', 'みずほ銀行', 'ミズホ', '015', '銀座支店', 'ギンザ', 'ordinary', '9012345', 'ナカムラ ヒマリ', NOW(), NOW()),
('casts', 'c00000027', '0033', 'PayPay銀行', 'ペイペイ', '001', '本店営業部', 'ホンテン', 'ordinary', '0123456', 'イチカワ イチカ', NOW(), NOW()),
('casts', 'c00000029', '0005', '三菱UFJ銀行', 'ミツビシユーエフジェイ', '051', '新宿支店', 'シンジユク', 'ordinary', '1029384', 'マナミ', NOW(), NOW()),
('casts', 'c00000031', '0009', '三井住友銀行', 'ミツイスミトモ', '815', '本店営業部', 'ホンテン', 'ordinary', '2938475', 'モチヅキ ノゾミ', NOW(), NOW()),
('casts', 'c00000034', '0001', 'みずほ銀行', 'ミズホ', '015', '銀座支店', 'ギンザ', 'ordinary', '3847562', 'ナナ', NOW(), NOW()),
('casts', 'c00000043', '0033', 'PayPay銀行', 'ペイペイ', '001', '本店営業部', 'ホンテン', 'ordinary', '4756382', 'カノウ カノン', NOW(), NOW()),
('system_accounts', '1', '0033', 'PayPay銀行', 'ペイペイ', '001', '本店営業部', 'ホンテン', 'ordinary', '99999999', 'ミセチヨク', NOW(), NOW());

-- =============================================================================
-- Phase 12: messages（60+ 件、複数ペアのトーク）
--   sender_type: 1=cast 2=shop
--   type: 1=TEXT 4=SYSTEM(action)
-- =============================================================================

INSERT INTO `messages` (`cast_id`, `shop_id`, `sender_type`, `type`, `content`, `is_read`, `created_at`, `updated_at`) VALUES
-- 旧シナリオ
('c00000003', 's00000002', 1, 1, 'はじめまして！求人を拝見してご連絡しました。', 1, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),
('c00000003', 's00000002', 2, 1, 'こちらこそご連絡ありがとうございます！ぜひ一度お話しできればと思います。', 1, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),
('c00000003', 's00000002', 2, 1, '面談の候補日をお送りしました。ご都合はいかがでしょうか？', 0, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
('c00000004', 's00000001', 1, 1, 'プロフィールを拝見して興味を持ちました！', 1, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
('c00000004', 's00000001', 2, 1, '未経験でも大歓迎です！詳しくお話しできればと思います。', 1, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
('c00000001', 's00000002', 2, 1, '今からヘルプで入れませんか？急遽ピンチヒッターを探しています。', 0, DATE_SUB(NOW(), INTERVAL 10 MINUTE), DATE_SUB(NOW(), INTERVAL 10 MINUTE)),
('c00000009', 's00000001', 1, 1, 'はじめまして！応募させていただきました。', 1, DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY)),
('c00000009', 's00000001', 2, 4, '面談ありがとうございました。ぜひ採用で進めさせていただきたいと考えております。', 1, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY)),
('c00000009', 's00000001', 1, 1, '採用ありがとうございます！精一杯頑張ります。', 1, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),
('c00000010', 's00000001', 2, 1, 'この度は本入店ありがとうございます！', 1, DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 30 DAY)),
('c00000010', 's00000001', 1, 1, 'よろしくお願いします！', 1, DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 30 DAY)),
-- 新規シナリオ: 面談前後 / 採用系のやり取り
('c00000011', 's00000006', 1, 1, '銀座で長く続けたいので応募しました！', 1, DATE_SUB(NOW(), INTERVAL 35 DAY), DATE_SUB(NOW(), INTERVAL 35 DAY)),
('c00000011', 's00000006', 2, 1, 'ご応募ありがとうございます。ぜひ体験入店からお願いします。', 1, DATE_SUB(NOW(), INTERVAL 34 DAY), DATE_SUB(NOW(), INTERVAL 34 DAY)),
('c00000011', 's00000006', 1, 1, 'ありがとうございます！', 1, DATE_SUB(NOW(), INTERVAL 34 DAY), DATE_SUB(NOW(), INTERVAL 34 DAY)),
('c00000011', 's00000006', 2, 4, '本入店で確定させていただきました。今後ともよろしくお願いします！', 1, DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY)),
('c00000012', 's00000006', 1, 1, '銀座エリアで働きたく、ご連絡しました。', 1, DATE_SUB(NOW(), INTERVAL 12 DAY), DATE_SUB(NOW(), INTERVAL 12 DAY)),
('c00000012', 's00000006', 2, 1, 'まずは体験入店で雰囲気を見にいらしてください。', 1, DATE_SUB(NOW(), INTERVAL 11 DAY), DATE_SUB(NOW(), INTERVAL 11 DAY)),
('c00000012', 's00000006', 2, 4, '体験入店ありがとうございました！本入店のご案内をさせていただきます。', 1, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY)),
('c00000013', 's00000014', 1, 1, 'ラウンジ経験を活かして働きたいです', 1, DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY)),
('c00000013', 's00000014', 2, 1, 'ラウンジ経験者は即戦力で嬉しいです！ぜひお願いします。', 1, DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY)),
('c00000013', 's00000014', 2, 4, '体験入店ありがとうございました', 1, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
('c00000014', 's00000008', 1, 1, '渋谷のポップな雰囲気に憧れて応募しました！', 1, DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY)),
('c00000014', 's00000008', 2, 1, '未経験でも安心してください。面談で詳しくお話ししましょう。', 0, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
('c00000015', 's00000006', 1, 1, '銀座ベテランです。長く働けるお店を探しています', 1, DATE_SUB(NOW(), INTERVAL 50 DAY), DATE_SUB(NOW(), INTERVAL 50 DAY)),
('c00000015', 's00000006', 2, 1, 'ベテランさんは大歓迎です！条件面もご相談させてください。', 1, DATE_SUB(NOW(), INTERVAL 49 DAY), DATE_SUB(NOW(), INTERVAL 49 DAY)),
('c00000015', 's00000006', 2, 4, '本入店ありがとうございます！', 1, DATE_SUB(NOW(), INTERVAL 35 DAY), DATE_SUB(NOW(), INTERVAL 35 DAY)),
('c00000016', 's00000007', 1, 1, '六本木で働いてみたいです', 1, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
('c00000016', 's00000007', 2, 1, 'ぜひお話ししましょう', 0, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
('c00000017', 's00000025', 1, 1, 'ガールズバー興味あります！', 1, DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),
('c00000017', 's00000025', 2, 1, '体験入店から始めましょう', 1, DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY)),
('c00000017', 's00000025', 2, 4, '体験入店ありがとうございました！', 1, DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY)),
('c00000018', 's00000009', 1, 1, '週末のみ働きたいのですが可能でしょうか？', 1, DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY)),
('c00000018', 's00000009', 2, 1, '週末のみ大歓迎です！面談日調整しましょう', 0, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
('c00000019', 's00000014', 1, 1, '池袋周辺希望していましたがラウンジ気になっています', 1, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),
('c00000019', 's00000014', 2, 1, 'ぜひ面談させてください', 1, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
('c00000019', 's00000014', 2, 1, '明日の18時いかがでしょうか？', 0, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
('c00000020', 's00000001', 1, 1, '銀座クラブ憧れです', 1, DATE_SUB(NOW(), INTERVAL 6 HOUR), DATE_SUB(NOW(), INTERVAL 6 HOUR)),
('c00000020', 's00000001', 2, 1, 'ご応募ありがとうございます', 0, DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR)),
('c00000021', 's00000004', 1, 1, '新宿でスナック探しています', 1, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY)),
('c00000021', 's00000004', 2, 1, 'ぜひ体験入店お願いします', 1, DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY)),
('c00000021', 's00000004', 2, 4, '体験入店ありがとうございました！', 1, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
('c00000022', 's00000005', 1, 1, 'コンカフェ経験ありますがラウンジも興味あります', 1, DATE_SUB(NOW(), INTERVAL 12 HOUR), DATE_SUB(NOW(), INTERVAL 12 HOUR)),
('c00000022', 's00000005', 2, 1, 'コンカフェ経験者も歓迎ですよ', 1, DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('c00000023', 's00000023', 1, 1, '横浜住みなので通いやすくて助かります', 1, DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY)),
('c00000023', 's00000023', 2, 1, '通勤便利で長く続けやすいと思います', 1, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),
('c00000023', 's00000023', 2, 1, '面談日決まりました', 0, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
('c00000024', 's00000024', 1, 1, '銀座の高級クラブ経験積みたいです！', 1, DATE_SUB(NOW(), INTERVAL 14 DAY), DATE_SUB(NOW(), INTERVAL 14 DAY)),
('c00000024', 's00000024', 2, 1, 'ぜひ体験入店お願いします', 1, DATE_SUB(NOW(), INTERVAL 12 DAY), DATE_SUB(NOW(), INTERVAL 12 DAY)),
('c00000024', 's00000024', 2, 4, '体験入店ありがとうございました！', 1, DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY)),
('c00000027', 's00000012', 1, 1, '中野で働きたいです', 1, DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY)),
('c00000027', 's00000012', 2, 1, '週2〜大歓迎です', 1, DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY)),
('c00000027', 's00000012', 2, 4, '体験入店ありがとうございました', 1, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
('c00000029', 's00000001', 1, 1, '銀座で長く働きたいです', 1, DATE_SUB(NOW(), INTERVAL 60 DAY), DATE_SUB(NOW(), INTERVAL 60 DAY)),
('c00000029', 's00000001', 2, 1, 'ベテランさん歓迎です', 1, DATE_SUB(NOW(), INTERVAL 55 DAY), DATE_SUB(NOW(), INTERVAL 55 DAY)),
('c00000029', 's00000001', 2, 4, '本入店ありがとうございます', 1, DATE_SUB(NOW(), INTERVAL 45 DAY), DATE_SUB(NOW(), INTERVAL 45 DAY)),
('c00000031', 's00000006', 1, 1, '銀座で長く続けたいです', 1, DATE_SUB(NOW(), INTERVAL 35 DAY), DATE_SUB(NOW(), INTERVAL 35 DAY)),
('c00000031', 's00000006', 2, 4, '本入店ありがとうございます！', 1, DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 20 DAY)),
('c00000034', 's00000015', 1, 1, '銀座ラウンジ経験あります', 1, DATE_SUB(NOW(), INTERVAL 40 DAY), DATE_SUB(NOW(), INTERVAL 40 DAY)),
('c00000034', 's00000015', 2, 4, '本入店ありがとうございました', 1, DATE_SUB(NOW(), INTERVAL 25 DAY), DATE_SUB(NOW(), INTERVAL 25 DAY)),
('c00000043', 's00000007', 1, 1, '六本木の華やかな雰囲気に憧れます', 1, DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY)),
('c00000043', 's00000007', 2, 4, '体験入店ありがとうございました', 1, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
('c00000044', 's00000024', 1, 1, '高収入希望で応募しました', 1, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),
('c00000044', 's00000024', 2, 1, '銀座では高収入目指せます。面談させてください', 0, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
('c00000046', 's00000015', 1, 1, '銀座ラウンジ経験2年です', 1, DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY)),
('c00000046', 's00000015', 2, 1, 'ぜひ来週面談させてください', 0, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
('c00000048', 's00000022', 1, 1, '福岡で長く働きたいです', 1, DATE_SUB(NOW(), INTERVAL 8 HOUR), DATE_SUB(NOW(), INTERVAL 8 HOUR)),
('c00000048', 's00000022', 2, 1, 'ご応募ありがとうございます', 0, DATE_SUB(NOW(), INTERVAL 3 HOUR), DATE_SUB(NOW(), INTERVAL 3 HOUR));

-- =============================================================================
-- Phase 13: favorites（KEEP）40 件
-- =============================================================================

INSERT INTO `favorites` (`cast_id`, `shop_id`, `action_type`, `sender_type`, `created_at`) VALUES
-- 旧
('c00000001', 's00000001', 'KEEP', 'shop', DATE_SUB(NOW(), INTERVAL 2 DAY)),
('c00000002', 's00000001', 'KEEP', 'shop', DATE_SUB(NOW(), INTERVAL 3 DAY)),
('c00000003', 's00000002', 'KEEP', 'shop', DATE_SUB(NOW(), INTERVAL 5 DAY)),
('c00000008', 's00000001', 'KEEP', 'shop', DATE_SUB(NOW(), INTERVAL 1 DAY)),
('c00000001', 's00000002', 'KEEP', 'cast', DATE_SUB(NOW(), INTERVAL 4 DAY)),
('c00000002', 's00000005', 'KEEP', 'cast', DATE_SUB(NOW(), INTERVAL 6 DAY)),
('c00000004', 's00000001', 'KEEP', 'cast', DATE_SUB(NOW(), INTERVAL 2 DAY)),
('c00000007', 's00000001', 'KEEP', 'cast', DATE_SUB(NOW(), INTERVAL 8 DAY)),
-- 新規: 店舗→キャストの KEEP（Premium 店舗が active カストにマーク）
('c00000011', 's00000001', 'KEEP', 'shop', DATE_SUB(NOW(), INTERVAL 3 DAY)),
('c00000011', 's00000006', 'KEEP', 'shop', DATE_SUB(NOW(), INTERVAL 5 DAY)),
('c00000012', 's00000006', 'KEEP', 'shop', DATE_SUB(NOW(), INTERVAL 2 DAY)),
('c00000013', 's00000014', 'KEEP', 'shop', DATE_SUB(NOW(), INTERVAL 4 DAY)),
('c00000015', 's00000024', 'KEEP', 'shop', DATE_SUB(NOW(), INTERVAL 1 DAY)),
('c00000016', 's00000007', 'KEEP', 'shop', DATE_SUB(NOW(), INTERVAL 2 DAY)),
('c00000020', 's00000006', 'KEEP', 'shop', DATE_SUB(NOW(), INTERVAL 3 DAY)),
('c00000024', 's00000024', 'KEEP', 'shop', DATE_SUB(NOW(), INTERVAL 2 DAY)),
('c00000029', 's00000001', 'KEEP', 'shop', DATE_SUB(NOW(), INTERVAL 5 DAY)),
('c00000041', 's00000006', 'KEEP', 'shop', DATE_SUB(NOW(), INTERVAL 1 DAY)),
('c00000043', 's00000007', 'KEEP', 'shop', DATE_SUB(NOW(), INTERVAL 3 DAY)),
('c00000044', 's00000024', 'KEEP', 'shop', DATE_SUB(NOW(), INTERVAL 2 DAY)),
('c00000047', 's00000005', 'KEEP', 'shop', DATE_SUB(NOW(), INTERVAL 4 DAY)),
('c00000049', 's00000006', 'KEEP', 'shop', DATE_SUB(NOW(), INTERVAL 6 DAY)),
-- 新規: キャスト→店舗の KEEP
('c00000011', 's00000006', 'KEEP', 'cast', DATE_SUB(NOW(), INTERVAL 6 DAY)),
('c00000012', 's00000006', 'KEEP', 'cast', DATE_SUB(NOW(), INTERVAL 4 DAY)),
('c00000013', 's00000014', 'KEEP', 'cast', DATE_SUB(NOW(), INTERVAL 3 DAY)),
('c00000014', 's00000008', 'KEEP', 'cast', DATE_SUB(NOW(), INTERVAL 5 DAY)),
('c00000015', 's00000001', 'KEEP', 'cast', DATE_SUB(NOW(), INTERVAL 12 DAY)),
('c00000016', 's00000007', 'KEEP', 'cast', DATE_SUB(NOW(), INTERVAL 2 DAY)),
('c00000017', 's00000025', 'KEEP', 'cast', DATE_SUB(NOW(), INTERVAL 8 DAY)),
('c00000018', 's00000009', 'KEEP', 'cast', DATE_SUB(NOW(), INTERVAL 4 DAY)),
('c00000020', 's00000001', 'KEEP', 'cast', DATE_SUB(NOW(), INTERVAL 6 HOUR)),
('c00000023', 's00000023', 'KEEP', 'cast', DATE_SUB(NOW(), INTERVAL 5 DAY)),
('c00000024', 's00000024', 'KEEP', 'cast', DATE_SUB(NOW(), INTERVAL 13 DAY)),
('c00000025', 's00000014', 'KEEP', 'cast', DATE_SUB(NOW(), INTERVAL 3 DAY)),
('c00000030', 's00000008', 'KEEP', 'cast', DATE_SUB(NOW(), INTERVAL 1 DAY)),
('c00000041', 's00000006', 'KEEP', 'cast', DATE_SUB(NOW(), INTERVAL 2 DAY)),
('c00000042', 's00000008', 'KEEP', 'cast', DATE_SUB(NOW(), INTERVAL 4 DAY)),
('c00000046', 's00000015', 'KEEP', 'cast', DATE_SUB(NOW(), INTERVAL 5 DAY)),
('c00000047', 's00000005', 'KEEP', 'cast', DATE_SUB(NOW(), INTERVAL 6 DAY)),
('c00000048', 's00000022', 'KEEP', 'cast', DATE_SUB(NOW(), INTERVAL 12 HOUR));

-- =============================================================================
-- Phase 14: profile_views（プロフィール閲覧ログ）40 件
-- =============================================================================

INSERT INTO `profile_views` (`viewer_type`, `viewer_id`, `target_type`, `target_id`, `created_at`) VALUES
-- Premium 店舗（s001, s006, s007, s014, s017, s018, s024）を見に来たキャスト
('cast', 'c00000001', 'shop', 's00000001', DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
('cast', 'c00000002', 'shop', 's00000001', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('cast', 'c00000004', 'shop', 's00000001', DATE_SUB(NOW(), INTERVAL 5 HOUR)),
('cast', 'c00000007', 'shop', 's00000001', DATE_SUB(NOW(), INTERVAL 1 DAY)),
('cast', 'c00000011', 'shop', 's00000001', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
('cast', 'c00000015', 'shop', 's00000001', DATE_SUB(NOW(), INTERVAL 8 HOUR)),
('cast', 'c00000020', 'shop', 's00000001', DATE_SUB(NOW(), INTERVAL 20 MINUTE)),
('cast', 'c00000029', 'shop', 's00000001', DATE_SUB(NOW(), INTERVAL 6 HOUR)),
('cast', 'c00000011', 'shop', 's00000006', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
('cast', 'c00000012', 'shop', 's00000006', DATE_SUB(NOW(), INTERVAL 6 HOUR)),
('cast', 'c00000015', 'shop', 's00000006', DATE_SUB(NOW(), INTERVAL 10 HOUR)),
('cast', 'c00000031', 'shop', 's00000006', DATE_SUB(NOW(), INTERVAL 12 HOUR)),
('cast', 'c00000041', 'shop', 's00000006', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('cast', 'c00000049', 'shop', 's00000006', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
('cast', 'c00000016', 'shop', 's00000007', DATE_SUB(NOW(), INTERVAL 5 HOUR)),
('cast', 'c00000043', 'shop', 's00000007', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
('cast', 'c00000050', 'shop', 's00000007', DATE_SUB(NOW(), INTERVAL 8 HOUR)),
('cast', 'c00000013', 'shop', 's00000014', DATE_SUB(NOW(), INTERVAL 12 HOUR)),
('cast', 'c00000019', 'shop', 's00000014', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('cast', 'c00000025', 'shop', 's00000014', DATE_SUB(NOW(), INTERVAL 6 HOUR)),
('cast', 'c00000024', 'shop', 's00000024', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
('cast', 'c00000044', 'shop', 's00000024', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
-- 店舗→キャスト
('shop', 's00000001', 'cast', 'c00000001', DATE_SUB(NOW(), INTERVAL 10 MINUTE)),
('shop', 's00000002', 'cast', 'c00000002', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
('shop', 's00000001', 'cast', 'c00000003', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
('shop', 's00000001', 'cast', 'c00000008', DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
('shop', 's00000001', 'cast', 'c00000011', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('shop', 's00000006', 'cast', 'c00000011', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
('shop', 's00000006', 'cast', 'c00000012', DATE_SUB(NOW(), INTERVAL 5 HOUR)),
('shop', 's00000006', 'cast', 'c00000015', DATE_SUB(NOW(), INTERVAL 10 HOUR)),
('shop', 's00000006', 'cast', 'c00000020', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
('shop', 's00000007', 'cast', 'c00000016', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
('shop', 's00000007', 'cast', 'c00000043', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('shop', 's00000014', 'cast', 'c00000013', DATE_SUB(NOW(), INTERVAL 6 HOUR)),
('shop', 's00000014', 'cast', 'c00000019', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
('shop', 's00000024', 'cast', 'c00000024', DATE_SUB(NOW(), INTERVAL 8 HOUR)),
('shop', 's00000024', 'cast', 'c00000044', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
('shop', 's00000004', 'cast', 'c00000021', DATE_SUB(NOW(), INTERVAL 12 HOUR)),
('shop', 's00000005', 'cast', 'c00000022', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
('shop', 's00000023', 'cast', 'c00000023', DATE_SUB(NOW(), INTERVAL 2 HOUR));

-- =============================================================================
-- Phase 15: reviews + review_details（採用済み案件のみ）8 レビュー
-- =============================================================================

INSERT INTO `reviews` (`id`, `cast_id`, `shop_id`, `contents`, `eva`, `is_anonymous`, `created_at`, `updated_at`) VALUES
(1, 'c00000010', 's00000001', 'とても優しく丁寧な指導で、初心者でも安心して働けました。おすすめです！', 4.7, 1, DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY)),
(2, 'c00000011', 's00000006', '銀座らしい落ち着いた雰囲気で、お客様の層も良かったです。時給もしっかり出ました。', 4.8, 0, DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),
(3, 'c00000015', 's00000006', 'ベテランでも学びの多いお店。長く続けられそうです。', 4.5, 1, DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 30 DAY)),
(4, 'c00000012', 's00000006', '体験入店の対応が丁寧で、そのまま本入店しました。おすすめです。', 4.6, 0, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5, 'c00000029', 's00000001', 'スタッフさんが皆優しくて、安心して働けています。', 4.4, 1, DATE_SUB(NOW(), INTERVAL 40 DAY), DATE_SUB(NOW(), INTERVAL 40 DAY)),
(6, 'c00000021', 's00000004', 'アットホームで気楽に働けます。ママも優しい方でした。', 4.2, 1, DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(7, 'c00000031', 's00000006', '接客のマナーもしっかり教えてもらえます。おすすめです。', 4.7, 0, DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY)),
(8, 'c00000034', 's00000015', 'ラウンジ経験を活かせて、時給もアップしました。', 4.5, 1, DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 20 DAY));

INSERT INTO `review_details` (`review_id`, `val`, `score`, `created_at`, `updated_at`) VALUES
(1, 1, 5.0, DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY)),
(1, 2, 5.0, DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY)),
(1, 3, 4.0, DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY)),
(2, 1, 5.0, DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),
(2, 2, 5.0, DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),
(2, 4, 5.0, DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),
(3, 2, 4.0, DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 30 DAY)),
(3, 4, 5.0, DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 30 DAY)),
(3, 5, 5.0, DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 30 DAY)),
(4, 1, 5.0, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(4, 2, 5.0, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(4, 3, 4.0, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5, 1, 4.0, DATE_SUB(NOW(), INTERVAL 40 DAY), DATE_SUB(NOW(), INTERVAL 40 DAY)),
(5, 2, 5.0, DATE_SUB(NOW(), INTERVAL 40 DAY), DATE_SUB(NOW(), INTERVAL 40 DAY)),
(5, 5, 4.0, DATE_SUB(NOW(), INTERVAL 40 DAY), DATE_SUB(NOW(), INTERVAL 40 DAY)),
(6, 2, 4.0, DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(6, 3, 4.0, DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(6, 5, 5.0, DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(7, 1, 5.0, DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY)),
(7, 2, 5.0, DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY)),
(7, 4, 4.0, DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY)),
(8, 2, 5.0, DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 20 DAY)),
(8, 4, 5.0, DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 20 DAY)),
(8, 5, 4.0, DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 20 DAY));

-- =============================================================================
-- Phase 15b: cast_images / shop_images
--
--   images.unsplash.com のキュレート済み写真 ID を 1200x1500 の 4:5 で読み込む。
--   URL 形式: https://images.unsplash.com/photo-{ID}?w=1200&h=1500&fit=crop&auto=format&q=80
--   ID は事前に HTTP 200 で存在確認済み。差し替えは photo-XXXX 部分だけ置換で OK。
--
--   キャストは c001-c031 に一意な写真、c032-c050 は c001-c019 の写真をリユース。
--   店舗はジャンル別（High-end/Pop/Cafe/Snack/Lounge）で写真プールを分けて
--   スワイパー内でのビジュアル一貫性を保つ。
-- =============================================================================

INSERT INTO `cast_images` (`cast_id`, `image_path`, `status`, `is_main`, `main_order`, `created_at`, `updated_at`) VALUES
('c00000001', 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000001', 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 0, 1, NOW(), NOW()),
('c00000001', 'https://images.unsplash.com/photo-1508214751196-bcfd4ca60f91?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 0, 2, NOW(), NOW()),
('c00000002', 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000002', 'https://images.unsplash.com/photo-1502768040783-423da5fd5fa0?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 0, 1, NOW(), NOW()),
('c00000003', 'https://images.unsplash.com/photo-1531123897727-8f129e1688ce?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000004', 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000005', 'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000006', 'https://images.unsplash.com/photo-1503104834685-7205e8607eb9?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000007', 'https://images.unsplash.com/photo-1554151228-14d9def656e4?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000008', 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000009', 'https://images.unsplash.com/photo-1489424731084-a5d8b219a5bb?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000010', 'https://images.unsplash.com/photo-1523824921871-d6f1a15151f1?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000011', 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000011', 'https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 0, 1, NOW(), NOW()),
('c00000012', 'https://images.unsplash.com/photo-1508214751196-bcfd4ca60f91?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000012', 'https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 0, 1, NOW(), NOW()),
('c00000013', 'https://images.unsplash.com/photo-1502768040783-423da5fd5fa0?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000014', 'https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000015', 'https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000015', 'https://images.unsplash.com/photo-1499651681375-8afc5a4db253?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 0, 1, NOW(), NOW()),
('c00000016', 'https://images.unsplash.com/photo-1499651681375-8afc5a4db253?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000017', 'https://images.unsplash.com/photo-1500917293891-ef795e70e1f6?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000018', 'https://images.unsplash.com/photo-1502823403499-6ccfcf4fb453?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000019', 'https://images.unsplash.com/photo-1509967419530-da38b4704bc6?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000020', 'https://images.unsplash.com/photo-1512310604669-443f26c35f52?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000021', 'https://images.unsplash.com/photo-1516726817505-f5ed825624d8?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000022', 'https://images.unsplash.com/photo-1521252659862-eec69941b071?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000023', 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000024', 'https://images.unsplash.com/photo-1524638431109-93d95c968f03?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000025', 'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000026', 'https://images.unsplash.com/photo-1541823709867-1b206113eafd?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000027', 'https://images.unsplash.com/photo-1553514029-1318c9127859?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000028', 'https://images.unsplash.com/photo-1560787313-5dff3307e257?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000029', 'https://images.unsplash.com/photo-1567532939604-b6b5b0db2604?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000030', 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000031', 'https://images.unsplash.com/photo-1590650153855-d9e808231d41?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
-- c032-c050: c001-c019 の写真をリユース
('c00000032', 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000033', 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000034', 'https://images.unsplash.com/photo-1531123897727-8f129e1688ce?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000035', 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000036', 'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000037', 'https://images.unsplash.com/photo-1503104834685-7205e8607eb9?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000038', 'https://images.unsplash.com/photo-1554151228-14d9def656e4?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000039', 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000040', 'https://images.unsplash.com/photo-1489424731084-a5d8b219a5bb?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000041', 'https://images.unsplash.com/photo-1523824921871-d6f1a15151f1?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000042', 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000043', 'https://images.unsplash.com/photo-1508214751196-bcfd4ca60f91?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000044', 'https://images.unsplash.com/photo-1502768040783-423da5fd5fa0?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000045', 'https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000046', 'https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000047', 'https://images.unsplash.com/photo-1499651681375-8afc5a4db253?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000048', 'https://images.unsplash.com/photo-1500917293891-ef795e70e1f6?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000049', 'https://images.unsplash.com/photo-1502823403499-6ccfcf4fb453?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW()),
('c00000050', 'https://images.unsplash.com/photo-1509967419530-da38b4704bc6?w=1200&h=1500&fit=crop&auto=format&q=80', 0, 1, 0, NOW(), NOW());

-- 店舗画像: ジャンル別プールから 3〜5 枚ずつ割り当て（同ジャンル内で写真リユース可）
INSERT INTO `shop_images` (`shop_id`, `image_path`, `type`, `is_main`, `main_order`, `created_at`, `updated_at`) VALUES
-- High-end clubs (s001, s006, s007, s017, s018, s024): luxury 15 枚プール
('s00000001', 'https://images.unsplash.com/photo-1543007630-9710e4a00a20?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 1, 0, NOW(), NOW()),
('s00000001', 'https://images.unsplash.com/photo-1470337458703-46ad1756a187?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 1, NOW(), NOW()),
('s00000001', 'https://images.unsplash.com/photo-1533777857889-4be7c70b33f7?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 2, NOW(), NOW()),
('s00000001', 'https://images.unsplash.com/photo-1514933651103-005eec06c04b?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 3, NOW(), NOW()),
('s00000001', 'https://images.unsplash.com/photo-1519671482749-fd09be7ccebf?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 4, NOW(), NOW()),
('s00000006', 'https://images.unsplash.com/photo-1424847651672-bf20a4b0982b?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 1, 0, NOW(), NOW()),
('s00000006', 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 1, NOW(), NOW()),
('s00000006', 'https://images.unsplash.com/photo-1508615039623-a25605d2b022?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 2, NOW(), NOW()),
('s00000006', 'https://images.unsplash.com/photo-1445116572660-236099ec97a0?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 3, NOW(), NOW()),
('s00000007', 'https://images.unsplash.com/photo-1517697471339-4aa32003c11a?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 1, 0, NOW(), NOW()),
('s00000007', 'https://images.unsplash.com/photo-1600880292203-757bb62b4baf?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 1, NOW(), NOW()),
('s00000007', 'https://images.unsplash.com/photo-1544025162-d76694265947?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 2, NOW(), NOW()),
('s00000007', 'https://images.unsplash.com/photo-1531058020387-3be344556be6?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 3, NOW(), NOW()),
('s00000017', 'https://images.unsplash.com/photo-1568644396922-5c3bfae12521?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 1, 0, NOW(), NOW()),
('s00000017', 'https://images.unsplash.com/photo-1517457373958-b7bdd4587205?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 1, NOW(), NOW()),
('s00000017', 'https://images.unsplash.com/photo-1543007630-9710e4a00a20?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 2, NOW(), NOW()),
('s00000017', 'https://images.unsplash.com/photo-1470337458703-46ad1756a187?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 3, NOW(), NOW()),
('s00000018', 'https://images.unsplash.com/photo-1533777857889-4be7c70b33f7?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 1, 0, NOW(), NOW()),
('s00000018', 'https://images.unsplash.com/photo-1514933651103-005eec06c04b?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 1, NOW(), NOW()),
('s00000018', 'https://images.unsplash.com/photo-1519671482749-fd09be7ccebf?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 2, NOW(), NOW()),
('s00000018', 'https://images.unsplash.com/photo-1424847651672-bf20a4b0982b?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 3, NOW(), NOW()),
('s00000024', 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 1, 0, NOW(), NOW()),
('s00000024', 'https://images.unsplash.com/photo-1508615039623-a25605d2b022?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 1, NOW(), NOW()),
('s00000024', 'https://images.unsplash.com/photo-1445116572660-236099ec97a0?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 2, NOW(), NOW()),
('s00000024', 'https://images.unsplash.com/photo-1517697471339-4aa32003c11a?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 3, NOW(), NOW()),
-- Pop clubs (s002, s008, s009, s019, s025): pink 7 枚プール
('s00000002', 'https://images.unsplash.com/photo-1551024506-0bccd828d307?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 1, 0, NOW(), NOW()),
('s00000002', 'https://images.unsplash.com/photo-1572116469696-31de0f17cc34?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 1, NOW(), NOW()),
('s00000002', 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 2, NOW(), NOW()),
('s00000002', 'https://images.unsplash.com/photo-1541544181051-e46607bc22a4?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 3, NOW(), NOW()),
('s00000008', 'https://images.unsplash.com/photo-1552566626-52f8b828add9?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 1, 0, NOW(), NOW()),
('s00000008', 'https://images.unsplash.com/photo-1567095761054-7a02e69e5c43?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 1, NOW(), NOW()),
('s00000008', 'https://images.unsplash.com/photo-1550966871-3ed3cdb5ed0c?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 2, NOW(), NOW()),
('s00000008', 'https://images.unsplash.com/photo-1551024506-0bccd828d307?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 3, NOW(), NOW()),
('s00000009', 'https://images.unsplash.com/photo-1572116469696-31de0f17cc34?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 1, 0, NOW(), NOW()),
('s00000009', 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 1, NOW(), NOW()),
('s00000009', 'https://images.unsplash.com/photo-1541544181051-e46607bc22a4?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 2, NOW(), NOW()),
('s00000009', 'https://images.unsplash.com/photo-1552566626-52f8b828add9?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 3, NOW(), NOW()),
('s00000019', 'https://images.unsplash.com/photo-1567095761054-7a02e69e5c43?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 1, 0, NOW(), NOW()),
('s00000019', 'https://images.unsplash.com/photo-1550966871-3ed3cdb5ed0c?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 1, NOW(), NOW()),
('s00000019', 'https://images.unsplash.com/photo-1551024506-0bccd828d307?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 2, NOW(), NOW()),
('s00000019', 'https://images.unsplash.com/photo-1572116469696-31de0f17cc34?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 3, NOW(), NOW()),
('s00000025', 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 1, 0, NOW(), NOW()),
('s00000025', 'https://images.unsplash.com/photo-1541544181051-e46607bc22a4?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 1, NOW(), NOW()),
('s00000025', 'https://images.unsplash.com/photo-1552566626-52f8b828add9?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 2, NOW(), NOW()),
('s00000025', 'https://images.unsplash.com/photo-1567095761054-7a02e69e5c43?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 3, NOW(), NOW()),
-- Cafes (s003, s010, s011, s022): cafe 7 枚プール
('s00000003', 'https://images.unsplash.com/photo-1554118811-1e0d58224f24?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 1, 0, NOW(), NOW()),
('s00000003', 'https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 1, NOW(), NOW()),
('s00000003', 'https://images.unsplash.com/photo-1466978913421-dad2ebd01d17?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 2, NOW(), NOW()),
('s00000010', 'https://images.unsplash.com/photo-1521017432531-fbd92d768814?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 1, 0, NOW(), NOW()),
('s00000010', 'https://images.unsplash.com/photo-1497534446932-c925b458314e?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 1, NOW(), NOW()),
('s00000010', 'https://images.unsplash.com/photo-1585637071663-799845ad5212?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 2, NOW(), NOW()),
('s00000011', 'https://images.unsplash.com/photo-1499933374294-4584851497cc?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 1, 0, NOW(), NOW()),
('s00000011', 'https://images.unsplash.com/photo-1554118811-1e0d58224f24?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 1, NOW(), NOW()),
('s00000011', 'https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 2, NOW(), NOW()),
('s00000022', 'https://images.unsplash.com/photo-1466978913421-dad2ebd01d17?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 1, 0, NOW(), NOW()),
('s00000022', 'https://images.unsplash.com/photo-1521017432531-fbd92d768814?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 1, NOW(), NOW()),
('s00000022', 'https://images.unsplash.com/photo-1497534446932-c925b458314e?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 2, NOW(), NOW()),
-- Snacks (s004, s012, s013, s020, s021): snack 11 枚プール
('s00000004', 'https://images.unsplash.com/photo-1560840067-ddcaeb7831d2?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 1, 0, NOW(), NOW()),
('s00000004', 'https://images.unsplash.com/photo-1493857671505-72967e2e2760?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 1, NOW(), NOW()),
('s00000004', 'https://images.unsplash.com/photo-1554306297-0c86e837d24b?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 2, NOW(), NOW()),
('s00000004', 'https://images.unsplash.com/photo-1541873676-a18131494184?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 3, NOW(), NOW()),
('s00000012', 'https://images.unsplash.com/photo-1509909756405-be0199881695?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 1, 0, NOW(), NOW()),
('s00000012', 'https://images.unsplash.com/photo-1571997478779-2adcbbe9ab2f?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 1, NOW(), NOW()),
('s00000012', 'https://images.unsplash.com/photo-1587574293340-e0011c4e8ecf?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 2, NOW(), NOW()),
('s00000012', 'https://images.unsplash.com/photo-1436076863939-06870fe779c2?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 3, NOW(), NOW()),
('s00000013', 'https://images.unsplash.com/photo-1508233620467-f79f1e317a05?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 1, 0, NOW(), NOW()),
('s00000013', 'https://images.unsplash.com/photo-1481833761820-0509d3217039?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 1, NOW(), NOW()),
('s00000013', 'https://images.unsplash.com/photo-1590846406792-0adc7f938f1d?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 2, NOW(), NOW()),
('s00000013', 'https://images.unsplash.com/photo-1560840067-ddcaeb7831d2?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 3, NOW(), NOW()),
('s00000020', 'https://images.unsplash.com/photo-1493857671505-72967e2e2760?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 1, 0, NOW(), NOW()),
('s00000020', 'https://images.unsplash.com/photo-1554306297-0c86e837d24b?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 1, NOW(), NOW()),
('s00000020', 'https://images.unsplash.com/photo-1541873676-a18131494184?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 2, NOW(), NOW()),
('s00000020', 'https://images.unsplash.com/photo-1509909756405-be0199881695?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 3, NOW(), NOW()),
('s00000021', 'https://images.unsplash.com/photo-1571997478779-2adcbbe9ab2f?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 1, 0, NOW(), NOW()),
('s00000021', 'https://images.unsplash.com/photo-1587574293340-e0011c4e8ecf?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 1, NOW(), NOW()),
('s00000021', 'https://images.unsplash.com/photo-1436076863939-06870fe779c2?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 2, NOW(), NOW()),
('s00000021', 'https://images.unsplash.com/photo-1508233620467-f79f1e317a05?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 3, NOW(), NOW()),
-- Lounges (s005, s014, s015, s016, s023): luxury pool を流用
('s00000005', 'https://images.unsplash.com/photo-1600880292203-757bb62b4baf?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 1, 0, NOW(), NOW()),
('s00000005', 'https://images.unsplash.com/photo-1544025162-d76694265947?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 1, NOW(), NOW()),
('s00000005', 'https://images.unsplash.com/photo-1531058020387-3be344556be6?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 2, NOW(), NOW()),
('s00000005', 'https://images.unsplash.com/photo-1568644396922-5c3bfae12521?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 3, NOW(), NOW()),
('s00000005', 'https://images.unsplash.com/photo-1517457373958-b7bdd4587205?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 4, NOW(), NOW()),
('s00000014', 'https://images.unsplash.com/photo-1543007630-9710e4a00a20?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 1, 0, NOW(), NOW()),
('s00000014', 'https://images.unsplash.com/photo-1470337458703-46ad1756a187?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 1, NOW(), NOW()),
('s00000014', 'https://images.unsplash.com/photo-1533777857889-4be7c70b33f7?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 2, NOW(), NOW()),
('s00000014', 'https://images.unsplash.com/photo-1514933651103-005eec06c04b?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 3, NOW(), NOW()),
('s00000015', 'https://images.unsplash.com/photo-1519671482749-fd09be7ccebf?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 1, 0, NOW(), NOW()),
('s00000015', 'https://images.unsplash.com/photo-1424847651672-bf20a4b0982b?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 1, NOW(), NOW()),
('s00000015', 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 2, NOW(), NOW()),
('s00000015', 'https://images.unsplash.com/photo-1508615039623-a25605d2b022?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 3, NOW(), NOW()),
('s00000016', 'https://images.unsplash.com/photo-1445116572660-236099ec97a0?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 1, 0, NOW(), NOW()),
('s00000016', 'https://images.unsplash.com/photo-1517697471339-4aa32003c11a?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 1, NOW(), NOW()),
('s00000016', 'https://images.unsplash.com/photo-1600880292203-757bb62b4baf?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 2, NOW(), NOW()),
('s00000016', 'https://images.unsplash.com/photo-1544025162-d76694265947?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 3, NOW(), NOW()),
('s00000023', 'https://images.unsplash.com/photo-1531058020387-3be344556be6?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 1, 0, NOW(), NOW()),
('s00000023', 'https://images.unsplash.com/photo-1568644396922-5c3bfae12521?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 1, NOW(), NOW()),
('s00000023', 'https://images.unsplash.com/photo-1517457373958-b7bdd4587205?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 2, NOW(), NOW()),
('s00000023', 'https://images.unsplash.com/photo-1543007630-9710e4a00a20?w=1200&h=1500&fit=crop&auto=format&q=80', 1, 0, 3, NOW(), NOW());

-- =============================================================================
-- Phase 16: cast_posts / shop_posts（ひとこと）
-- =============================================================================

INSERT INTO `cast_posts` (`cast_id`, `body`, `created_at`, `updated_at`) VALUES
('c00000001', '今日は元気です！', DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR)),
('c00000002', '今週末入れます', DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('c00000003', 'よろしくお願いします', DATE_SUB(NOW(), INTERVAL 3 HOUR), DATE_SUB(NOW(), INTERVAL 3 HOUR)),
('c00000011', '銀座で頑張ってます！', DATE_SUB(NOW(), INTERVAL 4 HOUR), DATE_SUB(NOW(), INTERVAL 4 HOUR)),
('c00000012', '週3で頑張ります', DATE_SUB(NOW(), INTERVAL 5 HOUR), DATE_SUB(NOW(), INTERVAL 5 HOUR)),
('c00000013', 'ラウンジ大好き', DATE_SUB(NOW(), INTERVAL 6 HOUR), DATE_SUB(NOW(), INTERVAL 6 HOUR)),
('c00000014', '未経験ですがよろしくお願いします！', DATE_SUB(NOW(), INTERVAL 8 HOUR), DATE_SUB(NOW(), INTERVAL 8 HOUR)),
('c00000015', 'ベテランです！', DATE_SUB(NOW(), INTERVAL 10 HOUR), DATE_SUB(NOW(), INTERVAL 10 HOUR)),
('c00000020', '銀座に憧れています', DATE_SUB(NOW(), INTERVAL 12 HOUR), DATE_SUB(NOW(), INTERVAL 12 HOUR)),
('c00000024', '銀座で長く続けたい', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
('c00000029', 'いつもありがとうございます', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
('c00000031', '本入店しました！', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
('c00000041', '今日入れます', DATE_SUB(NOW(), INTERVAL 30 MINUTE), DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
('c00000043', '銀座で経験積みたい', DATE_SUB(NOW(), INTERVAL 3 HOUR), DATE_SUB(NOW(), INTERVAL 3 HOUR)),
('c00000048', '福岡でお仕事探しています', DATE_SUB(NOW(), INTERVAL 4 HOUR), DATE_SUB(NOW(), INTERVAL 4 HOUR));

INSERT INTO `shop_posts` (`shop_id`, `body`, `created_at`, `updated_at`) VALUES
('s00000001', '本日体験入店募集中！ボーナス最大 15 万円', NOW(), NOW()),
('s00000002', 'ヘルプ大歓迎です', DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR)),
('s00000006', '銀座の老舗クラブが体験入店を大募集！', DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('s00000007', '六本木でお洒落な体験入店', DATE_SUB(NOW(), INTERVAL 3 HOUR), DATE_SUB(NOW(), INTERVAL 3 HOUR)),
('s00000008', '渋谷でポップなお店で働きませんか？', DATE_SUB(NOW(), INTERVAL 4 HOUR), DATE_SUB(NOW(), INTERVAL 4 HOUR)),
('s00000014', 'ラウンジ経験者優遇です', DATE_SUB(NOW(), INTERVAL 5 HOUR), DATE_SUB(NOW(), INTERVAL 5 HOUR)),
('s00000017', '大阪ミナミで働きませんか？', DATE_SUB(NOW(), INTERVAL 8 HOUR), DATE_SUB(NOW(), INTERVAL 8 HOUR)),
('s00000018', '神戸の落ち着いたクラブ、体験入店募集', DATE_SUB(NOW(), INTERVAL 12 HOUR), DATE_SUB(NOW(), INTERVAL 12 HOUR)),
('s00000024', '銀座の月明かりが似合う高級クラブ、募集中', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
('s00000025', '渋谷のガールズバー、週1〜OK！', DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 2 HOUR));

-- =============================================================================
-- Phase 17: cast_tag_relations（プロフィールタグ）60+ 件
--   タグは既存の cast_tags（looks 1-16 / personality 17-N）を利用
-- =============================================================================

INSERT INTO `cast_tag_relations` (`cast_id`, `tag_id`, `tag_type`, `created_at`, `updated_at`) VALUES
('c00000001', 1, 'looks', NOW(), NOW()),
('c00000001', 3, 'looks', NOW(), NOW()),
('c00000001', 5, 'personality', NOW(), NOW()),
('c00000002', 2, 'looks', NOW(), NOW()),
('c00000002', 7, 'personality', NOW(), NOW()),
('c00000003', 1, 'looks', NOW(), NOW()),
('c00000005', 4, 'looks', NOW(), NOW()),
('c00000005', 6, 'personality', NOW(), NOW()),
('c00000007', 2, 'looks', NOW(), NOW()),
('c00000008', 8, 'personality', NOW(), NOW()),
('c00000009', 3, 'looks', NOW(), NOW()),
('c00000010', 5, 'personality', NOW(), NOW()),
('c00000011', 1, 'looks', NOW(), NOW()),
('c00000011', 8, 'looks', NOW(), NOW()),
('c00000011', 17, 'personality', NOW(), NOW()),
('c00000012', 7, 'looks', NOW(), NOW()),
('c00000012', 14, 'looks', NOW(), NOW()),
('c00000013', 13, 'looks', NOW(), NOW()),
('c00000013', 15, 'looks', NOW(), NOW()),
('c00000014', 9, 'looks', NOW(), NOW()),
('c00000014', 6, 'looks', NOW(), NOW()),
('c00000015', 10, 'looks', NOW(), NOW()),
('c00000015', 14, 'looks', NOW(), NOW()),
('c00000016', 2, 'looks', NOW(), NOW()),
('c00000016', 13, 'looks', NOW(), NOW()),
('c00000017', 12, 'looks', NOW(), NOW()),
('c00000017', 9, 'looks', NOW(), NOW()),
('c00000018', 9, 'looks', NOW(), NOW()),
('c00000018', 15, 'looks', NOW(), NOW()),
('c00000019', 14, 'looks', NOW(), NOW()),
('c00000019', 10, 'looks', NOW(), NOW()),
('c00000020', 8, 'looks', NOW(), NOW()),
('c00000020', 3, 'looks', NOW(), NOW()),
('c00000021', 13, 'looks', NOW(), NOW()),
('c00000022', 16, 'looks', NOW(), NOW()),
('c00000022', 9, 'looks', NOW(), NOW()),
('c00000023', 14, 'looks', NOW(), NOW()),
('c00000024', 10, 'looks', NOW(), NOW()),
('c00000025', 8, 'looks', NOW(), NOW()),
('c00000025', 14, 'looks', NOW(), NOW()),
('c00000026', 9, 'looks', NOW(), NOW()),
('c00000027', 2, 'looks', NOW(), NOW()),
('c00000027', 15, 'looks', NOW(), NOW()),
('c00000028', 9, 'looks', NOW(), NOW()),
('c00000029', 10, 'looks', NOW(), NOW()),
('c00000030', 8, 'looks', NOW(), NOW()),
('c00000031', 8, 'looks', NOW(), NOW()),
('c00000031', 14, 'looks', NOW(), NOW()),
('c00000032', 1, 'looks', NOW(), NOW()),
('c00000033', 12, 'looks', NOW(), NOW()),
('c00000034', 8, 'looks', NOW(), NOW()),
('c00000034', 10, 'looks', NOW(), NOW()),
('c00000035', 16, 'looks', NOW(), NOW()),
('c00000036', 10, 'looks', NOW(), NOW()),
('c00000037', 16, 'looks', NOW(), NOW()),
('c00000038', 13, 'looks', NOW(), NOW()),
('c00000039', 14, 'looks', NOW(), NOW()),
('c00000040', 3, 'looks', NOW(), NOW()),
('c00000041', 10, 'looks', NOW(), NOW()),
('c00000042', 6, 'looks', NOW(), NOW()),
('c00000043', 8, 'looks', NOW(), NOW()),
('c00000044', 10, 'looks', NOW(), NOW()),
('c00000045', 12, 'looks', NOW(), NOW()),
('c00000046', 14, 'looks', NOW(), NOW()),
('c00000047', 16, 'looks', NOW(), NOW()),
('c00000048', 8, 'looks', NOW(), NOW()),
('c00000049', 10, 'looks', NOW(), NOW()),
('c00000050', 14, 'looks', NOW(), NOW());

-- =============================================================================
-- Phase 18: notification_preferences（主要ロールにデフォルト設定）
-- =============================================================================

INSERT INTO `notification_preferences` (`user_type`, `user_id`, `push_enabled`, `line_enabled`, `interview_reminder_enabled`, `deadline_reminder_enabled`, `created_at`, `updated_at`) VALUES
('cast', 'c00000001', 1, 1, 1, 1, NOW(), NOW()),
('cast', 'c00000002', 1, 1, 1, 1, NOW(), NOW()),
('cast', 'c00000003', 1, 1, 1, 1, NOW(), NOW()),
('cast', 'c00000009', 1, 1, 1, 1, NOW(), NOW()),
('cast', 'c00000010', 1, 1, 1, 1, NOW(), NOW()),
('cast', 'c00000011', 1, 1, 1, 1, NOW(), NOW()),
('cast', 'c00000012', 1, 1, 1, 1, NOW(), NOW()),
('cast', 'c00000013', 1, 1, 1, 1, NOW(), NOW()),
('cast', 'c00000014', 1, 1, 1, 1, NOW(), NOW()),
('cast', 'c00000015', 1, 1, 1, 1, NOW(), NOW()),
('cast', 'c00000020', 1, 1, 1, 1, NOW(), NOW()),
('cast', 'c00000024', 1, 1, 1, 1, NOW(), NOW()),
('cast', 'c00000029', 1, 1, 1, 1, NOW(), NOW()),
('cast', 'c00000041', 1, 1, 1, 1, NOW(), NOW()),
('cast', 'c00000043', 1, 1, 1, 1, NOW(), NOW()),
('shop', 's00000001', 1, 1, 1, 1, NOW(), NOW()),
('shop', 's00000002', 1, 1, 1, 1, NOW(), NOW()),
('shop', 's00000004', 1, 1, 1, 1, NOW(), NOW()),
('shop', 's00000005', 1, 1, 1, 1, NOW(), NOW()),
('shop', 's00000006', 1, 1, 1, 1, NOW(), NOW()),
('shop', 's00000007', 1, 1, 1, 1, NOW(), NOW()),
('shop', 's00000014', 1, 1, 1, 1, NOW(), NOW()),
('shop', 's00000017', 1, 1, 1, 1, NOW(), NOW()),
('shop', 's00000018', 1, 1, 1, 1, NOW(), NOW()),
('shop', 's00000024', 1, 1, 1, 1, NOW(), NOW());

-- =============================================================================
-- Phase 19: system_accounts（管理者アカウントを再作成）
-- =============================================================================

TRUNCATE TABLE `system_accounts`;
INSERT INTO `system_accounts` (`id`, `name`, `email`, `password`, `role`, `is_active`, `created_at`, `updated_at`) VALUES
(1, '管理者テスト', 'admin@test.jp', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, NOW(), NOW());

-- =============================================================================
-- 完了
-- =============================================================================
SET FOREIGN_KEY_CHECKS = 1;

SELECT 'テストデータの投入が完了しました' AS status,
       (SELECT COUNT(*) FROM casts) AS casts,
       (SELECT COUNT(*) FROM shops) AS shops,
       (SELECT COUNT(*) FROM shop_job_applications) AS applications,
       (SELECT COUNT(*) FROM application_deposits) AS deposits,
       (SELECT COUNT(*) FROM messages) AS messages,
       (SELECT COUNT(*) FROM cast_images) AS cast_images,
       (SELECT COUNT(*) FROM shop_images) AS shop_images;
