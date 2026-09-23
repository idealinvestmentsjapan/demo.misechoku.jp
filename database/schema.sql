-- =============================================================================
-- ミセチョク データベーススキーマ
-- Database: mock_demo
-- Generated: 2026-05-09
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

-- -----------------------------------------------------------------------------
-- push_subscriptions
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `push_subscriptions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_type` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `endpoint` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `public_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auth_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `push_subscriptions_endpoint_unique` (`endpoint`),
  KEY `push_subscriptions_user_idx` (`user_type`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- casts
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `casts` (
  `id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '主キー (c00000001~)',
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL COMMENT 'メール認証完了日時（NULL=未認証）',
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '0',
  `identity_status` tinyint NOT NULL DEFAULT '1' COMMENT '1:未提出, 2:未承認, 3:承認済',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `casts_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- cast_profiles
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cast_profiles` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `industry_id` bigint UNSIGNED DEFAULT NULL COMMENT '業種ID (industries)',
  `cast_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nickname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_kana` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `zip` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pref` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addr` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `building` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tel` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `height` smallint DEFAULT NULL,
  `weight` smallint DEFAULT NULL,
  `bust` smallint DEFAULT NULL,
  `waist` smallint DEFAULT NULL,
  `hip` smallint DEFAULT NULL,
  `profession` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exp` tinyint DEFAULT NULL,
  `work_where` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pr` text COLLATE utf8mb4_unicode_ci,
  `personality_type` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `available_until` timestamp NULL DEFAULT NULL COMMENT '「今すぐ入れる」宣言の有効期限。NULL または過去なら宣言なし',
  `available_declared_at` timestamp NULL DEFAULT NULL COMMENT '直近の available_until を宣言した時刻（同時刻タイブレーク用）',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cast_profiles_cast_id_foreign` (`cast_id`),
  KEY `fk_cast_profiles_industry` (`industry_id`),
  KEY `idx_cast_profiles_available_until` (`available_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- cast_providers
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cast_providers` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `cast_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'line',
  `provider_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cast_providers_provider_id_unique` (`provider`, `provider_id`),
  KEY `cast_providers_cast_id_foreign` (`cast_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- cast_images
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cast_images` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `cast_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint NOT NULL DEFAULT '0',
  `is_main` tinyint(1) NOT NULL DEFAULT '0',
  `main_order` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cast_images_cast_id` (`cast_id`),
  -- 1キャストにつき is_main=1 は最大1枚（非メインは無制限）。
  -- 関数 UNIQUE インデックス：CASE 式が NULL の行は重複扱いされない MySQL 仕様を利用。
  UNIQUE KEY `uq_cast_images_main_per_cast` (
    `cast_id`,
    (CASE WHEN `is_main` = 1 THEN 1 ELSE NULL END)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- cast_identity_documents
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cast_identity_documents` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `cast_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'photo_id' COMMENT 'photo_id:顔写真付身分証 / non_photo_id:顔写真なし身分証 / address_proof:住所確認書類',
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'photo_id: driver_license/passport/mynumber_card/residence_card | non_photo_id: health_insurance/pension_book | address_proof: residence_certificate/utility_bill',
  `image_path_front` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `image_path_back` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0:下書き(未提出), 1:未審査(提出済み), 2:承認済, 3:不備・却下',
  `ng_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `expired_at` date DEFAULT NULL COMMENT '有効期限',
  `approved_at` timestamp NULL DEFAULT NULL COMMENT '承認日時',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cast_identity_documents_cast_category_unique` (`cast_id`, `category`),
  KEY `cast_id` (`cast_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- cast_posts
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cast_posts` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `cast_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cast_posts_cast_id_foreign` (`cast_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- cast_shop_relation
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cast_shop_relation` (
  `cast_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `relation_type` tinyint NOT NULL COMMENT '1:ブロック, 2:追加等',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`cast_id`, `shop_id`),
  KEY `c_s_rel_shop_id_foreign` (`shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- cast_tags  (マスターデータ)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cast_tags` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'looks / personality',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `del_flg` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cast_tag` (`category`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- cast_tag_relations
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cast_tag_relations` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `cast_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'キャストID',
  `tag_id` bigint UNSIGNED NOT NULL COMMENT '各種タグID',
  `tag_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'タグの種類 (例: looks, personalityなど)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cast_tag` (`cast_id`, `tag_id`, `tag_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- cast_industry
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cast_industry` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `cast_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `industry_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cast_industry` (`cast_id`, `industry_id`),
  KEY `idx_cast_industry_cast` (`cast_id`),
  KEY `idx_cast_industry_industry` (`industry_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- shops
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shops` (
  `id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '主キー (s00000001~)',
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '0',
  `license_status` tinyint NOT NULL DEFAULT '1',
  `business_license_status` tinyint NOT NULL DEFAULT '1' COMMENT '営業許可証 (1:未提出, 2:未承認, 3:承認済)',
  `entertainment_license_status` tinyint NOT NULL DEFAULT '1' COMMENT '風営法許可証 (1:未提出, 2:未承認, 3:承認済)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- shop_profiles
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shop_profiles` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `industry_id` bigint UNSIGNED DEFAULT NULL COMMENT '業種ID (industries) ※検索用',
  `industry_label` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '表示用の業種名（フリーテキスト、例：高級ラウンジ）',
  `shop_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `zip` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pref` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addr` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '番地・丁目',
  `building` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '建物名・部屋番号',
  `tel` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `open_time` time DEFAULT NULL COMMENT '開店時刻',
  `close_is_last` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1=LAST',
  `close_time` time DEFAULT NULL COMMENT '閉店時刻',
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `available_until` timestamp NULL DEFAULT NULL COMMENT '「本日すぐ入れます」宣言の有効期限。NULL または過去なら宣言なし',
  `available_declared_at` timestamp NULL DEFAULT NULL COMMENT '直近の available_until を宣言した時刻',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_profiles_shop_id` (`shop_id`),
  KEY `fk_shop_profiles_industry` (`industry_id`),
  KEY `idx_shop_profiles_available_until` (`available_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- shop_managers
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shop_managers` (
  `id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '主キー (m00000001~)',
  `shop_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '所属する shops.id（1店舗で複数アカウント可）',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ログイン用メールアドレス（全shop_managersでユニーク）',
  `email_verified_at` timestamp NULL DEFAULT NULL COMMENT 'メール認証完了日時（NULL=未認証）',
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` tinyint NOT NULL DEFAULT '0' COMMENT '権限 (1:オーナー, 2:スタッフ)',
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '稼働 (0:停止, 1:有効)',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shop_managers_email_unique` (`email`),
  KEY `shop_managers_shop_id_foreign` (`shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- notifications：ユーザー宛おしらせ（インボックス）
--   user_type: 'cast' | 'shop_manager' | 'admin'
--   type:      機能キー（例: talk.message_received, billing.invoice_issued）
--   url:       タップで遷移する詳細URL（任意）
--   payload:   付随データ（JSON, 任意）
--   read_at:   既読時刻（NULL=未読）
--   dispatched_push_at: PWA Push 配信済み時刻（重複配信防止）
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_type` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` json DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `dispatched_push_at` timestamp NULL DEFAULT NULL,
  `dispatched_line_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_user_idx` (`user_type`, `user_id`, `read_at`),
  KEY `notifications_created_idx` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- shop_images
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shop_images` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` tinyint DEFAULT NULL,
  `is_main` tinyint(1) NOT NULL DEFAULT '0',
  `main_order` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_shop_images_shop_id` (`shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- shop_posts
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shop_posts` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci COMMENT '店舗のひとこと',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shop_posts_shop_id_unique` (`shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- shop_stations
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shop_stations` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `station_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_shop_stations_shop_id` (`shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- shop_jobs
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shop_jobs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `pr` text COLLATE utf8mb4_unicode_ci COMMENT '店長からのメッセージ',
  `catch_copy` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'キャッチコピー',
  `job_content` text COLLATE utf8mb4_unicode_ci COMMENT 'お仕事内容',
  `regular_status` tinyint NOT NULL DEFAULT '0' COMMENT '0:非公開, 1:公開',
  `regular_hourly_wage` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '本入店時給',
  `norma_day` int DEFAULT NULL COMMENT 'ボーナス達成に必要な在籍日数',
  `norma_hours` int DEFAULT NULL COMMENT '1日の勤務時間(ボーナス条件)',
  `bonus_reward` int DEFAULT NULL COMMENT 'ボーナス金額',
  `bonus_remarks` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ボーナス金額補足',
  `bonus_condition` varchar(2000) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ボーナス達成条件テキスト',
  `trial_hourly_wage` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trial_status` tinyint NOT NULL DEFAULT '0' COMMENT '0:非公開, 1:公開',
  `has_help` tinyint(1) NOT NULL DEFAULT '0',
  `help_hourly_wage` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `help_status` tinyint NOT NULL DEFAULT '0' COMMENT '0:非公開, 1:公開',
  `working_day` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `working_hours` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `regular_holiday` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qualification` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shift_time_start` time DEFAULT NULL COMMENT '勤務開始時刻',
  `shift_time_end` time DEFAULT NULL COMMENT '勤務終了時刻',
  `shift_end_is_last` tinyint UNSIGNED NOT NULL DEFAULT '0' COMMENT '終了がLASTのとき1',
  `regular_hourly_wage_max` int UNSIGNED DEFAULT NULL COMMENT '本入時給上限（円）',
  `trial_hourly_wage_max` int UNSIGNED DEFAULT NULL COMMENT '体験入店時給上限（円）',
  `help_hourly_wage_max` int UNSIGNED DEFAULT NULL COMMENT 'ヘルプ時給上限（円）',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_jobs_shop_id` (`shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- shop_job_applications
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shop_job_applications` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `cast_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_job_id` bigint UNSIGNED NOT NULL,
  `applied_regular_status` tinyint NOT NULL DEFAULT '0' COMMENT '申請時点: 本入 0:非公開 1:公開',
  `applied_regular_hourly_wage` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '申請時点: 本入店時給',
  `applied_norma_day` int DEFAULT NULL COMMENT '申請時点: ボーナス達成に必要な在籍日数',
  `applied_norma_hours` int DEFAULT NULL COMMENT '申請時点: 1日の勤務時間(ボーナス条件)',
  `applied_bonus_reward` int DEFAULT NULL COMMENT '申請時点: ボーナス金額',
  `applied_bonus_remarks` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '申請時点: ボーナス金額補足',
  `applied_bonus_condition` varchar(2000) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '申請時点: ボーナス達成条件テキスト',
  `applied_trial_hourly_wage` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '申請時点: 体験入店時給',
  `applied_trial_status` tinyint NOT NULL DEFAULT '0' COMMENT '申請時点: 体験 0:非公開 1:公開',
  `applied_has_help` tinyint(1) NOT NULL DEFAULT '0' COMMENT '申請時点: ヘルプあり',
  `applied_help_hourly_wage` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '申請時点: ヘルプ時給',
  `applied_help_status` tinyint NOT NULL DEFAULT '0' COMMENT '申請時点: ヘルプ 0:非公開 1:公開',
  `applied_working_day` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '申請時点: 勤務日',
  `applied_working_hours` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '申請時点: 勤務時間',
  `applied_regular_holiday` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '申請時点: 定休日',
  `applied_qualification` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '申請時点: 応募資格',
  `applied_shift_time_start` time DEFAULT NULL COMMENT '申請時点: 勤務開始時刻',
  `applied_shift_time_end` time DEFAULT NULL COMMENT '申請時点: 勤務終了時刻',
  `applied_shift_end_is_last` tinyint UNSIGNED NOT NULL DEFAULT '0' COMMENT '申請時点: 終了がLASTのとき1',
  `applied_regular_hourly_wage_max` int UNSIGNED DEFAULT NULL COMMENT '申請時点: 本入時給上限（円）',
  `applied_trial_hourly_wage_max` int UNSIGNED DEFAULT NULL COMMENT '申請時点: 体験入店時給上限（円）',
  `applied_help_hourly_wage_max` int UNSIGNED DEFAULT NULL COMMENT '申請時点: ヘルプ時給上限（円）',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '1:やり取り中, 2:面談日調整中, 3:面談日決定, 4:採用, 5:不採用',
  `result_date` date DEFAULT NULL,
  `real_start_date` date DEFAULT NULL,
  `hourly_wage_regular` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `normal_time` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hired_bonus_amount` int DEFAULT NULL,
  `hired_bonus_condition` text COLLATE utf8mb4_unicode_ci,
  `reason_rejection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `talk_job_kind` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'fulltime|trial|help',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `s_j_app_cast_id_foreign` (`cast_id`),
  KEY `s_j_app_job_id_foreign` (`shop_job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- application_deposits
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `application_deposits` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_job_application_id` bigint UNSIGNED NOT NULL,
  `status` tinyint NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `invoice_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bonus_amount` int DEFAULT NULL,
  `system_fee_amount` int DEFAULT NULL,
  `invoice_amount` int DEFAULT NULL,
  `cast_transfer_amount` int DEFAULT NULL,
  `invoice_issued_at` timestamp NULL DEFAULT NULL,
  `invoice_due_date` date DEFAULT NULL,
  `invoice_sent_at` timestamp NULL DEFAULT NULL,
  `shop_payment_reported_at` timestamp NULL DEFAULT NULL,
  `shop_payment_reported_amount` int DEFAULT NULL,
  `shop_payment_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shop_payment_confirmed_at` timestamp NULL DEFAULT NULL,
  `cast_transferred_at` timestamp NULL DEFAULT NULL,
  `cast_transfer_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cast_transfer_note` text COLLATE utf8mb4_unicode_ci,
  `cast_transfer_evidence_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'キャスト振込の証跡画像パス（public disk）',
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `app_dep_app_id_foreign` (`shop_job_application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- application_deposit_histories
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `application_deposit_histories` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_deposit_id` bigint UNSIGNED NOT NULL,
  `status` tinyint NOT NULL,
  `status_date` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `app_dep_hist_dep_id_foreign` (`application_deposit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- payment_tasks
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_tasks` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_deposit_id` bigint UNSIGNED NOT NULL,
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0=待機 1=支払準備中 2=振込中 3=支払済 4=無効',
  `shop_received_amount` int UNSIGNED NOT NULL COMMENT '店舗入金額スナップショット',
  `platform_fee_amount` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'プラットフォーム手数料',
  `bank_fee_amount` int UNSIGNED NOT NULL DEFAULT '0' COMMENT '銀行振込手数料',
  `payout_amount` int UNSIGNED NOT NULL COMMENT 'キャスト振込額（自動計算）',
  `transferred_at` timestamp NULL DEFAULT NULL COMMENT '振込作業完了日時',
  `completed_at` timestamp NULL DEFAULT NULL COMMENT '支払済確定日時',
  `evidence_file_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '振込完了証跡画像',
  `checklist_confirmed_account` tinyint(1) NOT NULL DEFAULT '0',
  `checklist_confirmed_amount` tinyint(1) NOT NULL DEFAULT '0',
  `operator_id` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '振込作業担当者ID',
  `refund_required` tinyint(1) NOT NULL DEFAULT '0' COMMENT '要返金フラグ',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_tasks_application_deposit_id_unique` (`application_deposit_id`),
  KEY `payment_tasks_application_deposit_id_foreign` (`application_deposit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- favorites
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `favorites` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `cast_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shop_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action_type` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_type` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `favorites_unique_per_pair_action_sender` (`cast_id`, `shop_id`, `action_type`, `sender_type`),
  KEY `favorites_shop_id_foreign` (`shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- keeps
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `keeps` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `cast_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` tinyint NOT NULL DEFAULT '1',
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `keeps_cast_id_foreign` (`cast_id`),
  KEY `keeps_shop_id_foreign` (`shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- footprints
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `footprints` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `cast_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `footprints_cast_id_foreign` (`cast_id`),
  KEY `footprints_shop_id_foreign` (`shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- profile_views（プロフィール閲覧ログ。閲覧回数の表示に使用）
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `profile_views` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `viewer_type` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '閲覧者ロール cast|shop',
  `viewer_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '閲覧者ID',
  `target_type` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '閲覧されたロール cast|shop',
  `target_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '閲覧されたID',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `profile_views_target` (`target_type`,`target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- messages
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `messages` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `cast_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_type` tinyint NOT NULL COMMENT '1:Cast, 2:Shop, 3:System to cast, 4:System to shop',
  `type` tinyint NOT NULL DEFAULT '1' COMMENT '1:Text, 2:InterviewOffer, 3:InterviewConfirmed, 4:Hired, 5:Rejected, 6:Image, 7:InterviewCancelRequest, 8:BillingSystem',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `messages_cast_id_foreign` (`cast_id`),
  KEY `messages_shop_id_foreign` (`shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- talk_blocks
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `talk_blocks` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `cast_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `blocked_by` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'cast or shop',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `talk_blocks_cast_id_shop_id_unique` (`cast_id`, `shop_id`),
  KEY `talk_blocks_shop_id_index` (`shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- reviews
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reviews` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `cast_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contents` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `eva` decimal(3,1) NOT NULL DEFAULT '0.0',
  `is_anonymous` tinyint(1) NOT NULL DEFAULT '1',
  `release` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0:非公開, 1:公開',
  `reply_body` text COLLATE utf8mb4_unicode_ci COMMENT '店舗からの返信本文（1件のみ）',
  `reply_at` timestamp NULL DEFAULT NULL COMMENT '返信投稿日時',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reviews_cast_id_foreign` (`cast_id`),
  KEY `reviews_shop_id_foreign` (`shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- review_contents  (マスターデータ)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `review_contents` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `content` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '設問内容',
  `del_flg` tinyint(1) NOT NULL DEFAULT '0' COMMENT '削除フラグ',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- review_details
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `review_details` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `review_id` bigint UNSIGNED NOT NULL,
  `review_content_id` bigint UNSIGNED DEFAULT NULL,
  `val` bigint UNSIGNED NOT NULL,
  `score` decimal(3,1) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_review_details_review_id_val` (`review_id`, `val`),
  KEY `idx_review_details_val` (`val`),
  KEY `review_details_review_content_id_foreign` (`review_content_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- industries  (マスターデータ)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `industries` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '業種名',
  `del_flg` tinyint(1) NOT NULL DEFAULT '0' COMMENT '削除フラグ',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 店舗の業種は shop_profiles.industry_id（1 店舗 1 業種）に一本化済み。
-- かつての中間テーブル shop_industry は廃止。

-- -----------------------------------------------------------------------------
-- shop_tags  (マスターデータ)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shop_tags` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'work_style / atmosphere / facility / welcome / benefit',
  `target` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'shop' COMMENT 'shop: 店舗Profile用 / job: 求人票用',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `del_flg` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_tag` (`category`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- shop_tag_relations
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shop_tag_relations` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '店舗ID',
  `tag_id` bigint UNSIGNED NOT NULL COMMENT '各種タグID',
  `tag_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'タグの種類 (例: atmospheres, facilities, salaryなど)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_shop_tag` (`shop_id`, `tag_id`, `tag_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- shop_job_tag_relations
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shop_job_tag_relations` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_job_id` bigint UNSIGNED NOT NULL COMMENT '求人ID (shop_jobs.id)',
  `tag_id` bigint UNSIGNED NOT NULL COMMENT 'shop_tags.id',
  `tag_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'work_style / welcome / benefit',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_job_tag` (`shop_job_id`, `tag_id`, `tag_type`),
  KEY `fk_sj_tag_rel_tag` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- shop_license_documents
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shop_license_documents` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '種類(business:営業許可証, entertainment:風営法許可等)',
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0:アップロード済み, 1:審査中, 2:承認済み, 3:不備・却下',
  `ng_reason` text COLLATE utf8mb4_unicode_ci COMMENT '却下理由',
  `expired_at` date DEFAULT NULL COMMENT '有効期限',
  `approved_at` timestamp NULL DEFAULT NULL COMMENT '承認日時',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `shop_id` (`shop_id`),
  KEY `idx_shop_license_documents_expired_at` (`expired_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- availability_dates
-- Candidate work dates (cast) / dated help recruitment (shop).
-- Max 5 future dates per owner, enforced in AvailabilityService.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `availability_dates` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_type` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'cast or shop',
  `owner_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Cast.id (c...) or Shop.id (s...)',
  `available_on` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `availability_dates_owner_date_unique` (`owner_type`, `owner_id`, `available_on`),
  KEY `availability_dates_available_on_index` (`available_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- bank_accounts
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bank_accounts` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `holder_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '所有者モデル (casts, shops, users等)',
  `holder_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '所有者のID (c0001, s0001等)',
  `bank_code` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '銀行コード (4桁)',
  `bank_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '銀行名',
  `bank_name_kana` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '銀行名（カナ）',
  `branch_code` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '支店コード (3桁)',
  `branch_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '支店名',
  `branch_name_kana` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '支店名（カナ）',
  `account_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '預金種別 (ordinary:普通, current:当座)',
  `account_number` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '口座番号 (7〜8桁)',
  `account_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '口座名義 (カナ)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_holder` (`holder_type`, `holder_id`),
  KEY `idx_holder` (`holder_type`, `holder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- ng_words  (マスターデータ)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ng_words` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `word` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '有効フラグ',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- system_accounts
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `system_accounts` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '管理者名',
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ログインメールアドレス',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ハッシュ化パスワード',
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'staff' COMMENT '権限(admin:全機能, staff:一部機能)',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '有効フラグ(falseでログイン不可)',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- invoice_template_settings
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `invoice_template_settings` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'issuer_name, issuer_email, logo_url, footer_text 等',
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_template_settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- notices
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notices` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '本文（プレーンテキスト想定）',
  `is_published` tinyint(1) NOT NULL DEFAULT '0',
  `published_at` timestamp NULL DEFAULT NULL,
  `visible_to_cast` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'キャスト向けに表示',
  `visible_to_shop` tinyint(1) NOT NULL DEFAULT '1' COMMENT '店舗向けに表示',
  `visible_to_guest` tinyint(1) NOT NULL DEFAULT '0' COMMENT '未ログインの/support/noticesに表示',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notices_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- column_categories  (マスターデータ)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `column_categories` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'カテゴリ名',
  `directory` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL用ディレクトリ',
  `del_flg` tinyint(1) NOT NULL DEFAULT '0' COMMENT '削除フラグ',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- column_articles
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `column_articles` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `column_category_id` bigint UNSIGNED DEFAULT NULL,
  `image_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '公開ディレクトリ相対パス',
  `tags` json DEFAULT NULL COMMENT 'タグ文字列のJSON配列',
  `body` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '本文（プレーンテキスト想定）',
  `is_published` tinyint(1) NOT NULL DEFAULT '0',
  `published_at` timestamp NULL DEFAULT NULL,
  `visible_to_cast` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'キャスト向けに表示',
  `visible_to_shop` tinyint(1) NOT NULL DEFAULT '1' COMMENT '店舗向けに表示',
  `visible_to_guest` tinyint(1) NOT NULL DEFAULT '0' COMMENT '未ログインの/support/columnに表示',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `column_articles_slug_unique` (`slug`),
  KEY `column_articles_column_category_id_index` (`column_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- notification_preferences
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notification_preferences` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `push_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `line_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `notify_talk_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `notify_selection_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `notify_billing_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `notify_verification_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `notify_review_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `interview_reminder_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `deadline_reminder_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notification_preferences_user_unique` (`user_type`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- admin_operation_logs
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_operation_logs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` bigint UNSIGNED NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detail` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_operation_logs_admin_id_index` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- admin_notification_settings
-- Stores admin overrides for the notification/reminder/task spec catalog
-- managed by NotificationSpecService (NULL column = use catalog default).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_notification_settings` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `enabled` tinyint(1) DEFAULT NULL,
  `offset_value` int DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_notification_settings_type_key_unique` (`type`, `key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- admin_role_permissions
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_role_permissions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `role` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_role_permissions_role_permission_unique` (`role`, `permission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- line_messages
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `line_messages` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `line_user_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `line_message_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- character_guide_settings
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `character_guide_settings` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `route_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `screen_label` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `character_guide_settings_route_name_unique` (`route_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- policy_documents  (コンテンツデータ)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `policy_documents` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'about / terms / privacy',
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ページタイトル',
  `lead_title` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'リード見出し',
  `lead_body` text COLLATE utf8mb4_unicode_ci COMMENT 'リード本文',
  `meta` json DEFAULT NULL COMMENT '協会概要などの構造化データ',
  `is_locked` tinyint(1) NOT NULL DEFAULT '1' COMMENT '既定はロック状態（編集不可）',
  `updated_by_id` bigint UNSIGNED DEFAULT NULL COMMENT '最終更新者の system_account.id',
  `updated_by_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '最終更新者の表示名',
  `content_updated_at` timestamp NULL DEFAULT NULL COMMENT '最終更新日時',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `policy_documents_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- policy_chapters  (コンテンツデータ)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `policy_chapters` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `policy_document_id` bigint UNSIGNED NOT NULL,
  `sort_order` int UNSIGNED NOT NULL DEFAULT '0',
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `policy_chapters_doc_sort_index` (`policy_document_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- policy_revisions
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `policy_revisions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `policy_document_id` bigint UNSIGNED NOT NULL,
  `action` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'created / updated / locked / unlocked',
  `summary` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `snapshot` json DEFAULT NULL COMMENT '更新後スナップショット',
  `updated_by_id` bigint UNSIGNED DEFAULT NULL,
  `updated_by_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `policy_revisions_doc_created_index` (`policy_document_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- user_reports（ユーザー間の通報）
-- reporter が target を通報。管理者が確認・対応する。
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_reports` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `reporter_type` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '通報者ロール cast|shop',
  `reporter_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_type` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '通報対象ロール cast|shop',
  `target_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'harassment|contact_info|inappropriate|fake|other',
  `detail` text COLLATE utf8mb4_unicode_ci COMMENT '通報者の自由記述（任意）',
  `context_type` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '通報起点 talk|profile など',
  `context_message_id` bigint UNSIGNED DEFAULT NULL COMMENT 'トーク発の場合の messages.id',
  `status` tinyint NOT NULL DEFAULT 0 COMMENT '0:未対応 1:対応中 2:完了 3:却下',
  `admin_note` text COLLATE utf8mb4_unicode_ci COMMENT '運営メモ',
  `handled_by` bigint UNSIGNED DEFAULT NULL COMMENT '対応した system_accounts.id',
  `handled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_reports_target_idx` (`target_type`, `target_id`),
  KEY `user_reports_status_idx` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- password_reset_tokens
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- failed_jobs  (Laravel キューシステム)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- migrations  (Laravel マイグレーション管理)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- VIEWs
-- =============================================================================

DROP VIEW IF EXISTS `managers`;
CREATE ALGORITHM=UNDEFINED DEFINER=`risadmin`@`%` SQL SECURITY DEFINER VIEW `managers` AS
  SELECT
    `shop_managers`.`id` AS `id`,
    `shop_managers`.`shop_id` AS `shop_id`,
    `shop_managers`.`name` AS `name`,
    `shop_managers`.`email` AS `email`,
    `shop_managers`.`password` AS `password`,
    `shop_managers`.`role` AS `role`,
    `shop_managers`.`status` AS `status`,
    `shop_managers`.`last_login_at` AS `last_login_at`,
    `shop_managers`.`created_at` AS `created_at`,
    `shop_managers`.`updated_at` AS `updated_at`,
    `shop_profiles`.`shop_name` AS `shop_name`
  FROM (`shop_managers`
    LEFT JOIN `shop_profiles` ON (`shop_managers`.`shop_id` = `shop_profiles`.`shop_id`));

DROP VIEW IF EXISTS `members`;
CREATE ALGORITHM=UNDEFINED DEFINER=`risadmin`@`%` SQL SECURITY DEFINER VIEW `members` AS
  SELECT
    `casts`.`id` AS `id`,
    `casts`.`email` AS `email`,
    `casts`.`password` AS `password`,
    `casts`.`status` AS `status`,
    `casts`.`status` AS `approval`,
    `casts`.`identity_status` AS `identity_status`,
    `casts`.`last_login_at` AS `last_login_at`,
    `casts`.`remember_token` AS `remember_token`,
    `casts`.`created_at` AS `created_at`,
    `casts`.`updated_at` AS `updated_at`,
    `casts`.`deleted_at` AS `deleted_at`,
    (CASE WHEN (`casts`.`deleted_at` IS NULL) THEN 0 ELSE 1 END) AS `del_flg`,
    `cast_profiles`.`nickname` AS `nickname`,
    `cast_profiles`.`name` AS `name`,
    `cast_profiles`.`name_kana` AS `kana`,
    `cast_profiles`.`birthday` AS `birthday`,
    YEAR(`cast_profiles`.`birthday`) AS `birthday_y`,
    MONTH(`cast_profiles`.`birthday`) AS `birthday_m`,
    DAYOFMONTH(`cast_profiles`.`birthday`) AS `birthday_d`,
    `cast_profiles`.`zip` AS `zip`,
    `cast_profiles`.`pref` AS `pref`,
    `cast_profiles`.`city` AS `city`,
    `cast_profiles`.`tel` AS `tel`,
    `cast_profiles`.`height` AS `height`,
    `cast_profiles`.`weight` AS `weight`,
    `cast_profiles`.`bust` AS `b`,
    `cast_profiles`.`waist` AS `w`,
    `cast_profiles`.`hip` AS `h`,
    `cast_profiles`.`profession` AS `profession`,
    `cast_profiles`.`exp` AS `exp`,
    `cast_profiles`.`work_where` AS `where_work`,
    `cast_profiles`.`pr` AS `pr`,
    `cast_profiles`.`latitude` AS `latitude`,
    `cast_profiles`.`longitude` AS `longitude`,
    (SELECT `cp`.`provider_id` FROM `cast_providers` `cp`
     WHERE (`cp`.`cast_id` = `casts`.`id` AND `cp`.`provider` = 'line') LIMIT 1) AS `line_user_id`,
    0 AS `matching`,
    0 AS `release`,
    0 AS `footprints`,
    NULL AS `shop_name`
  FROM (`casts`
    LEFT JOIN `cast_profiles` ON (`casts`.`id` = `cast_profiles`.`cast_id`));

-- =============================================================================
-- 外部キー制約
-- =============================================================================

ALTER TABLE `cast_profiles`
  ADD CONSTRAINT `cast_profiles_cast_id_foreign` FOREIGN KEY (`cast_id`) REFERENCES `casts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cast_profiles_industry` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`) ON DELETE SET NULL;

ALTER TABLE `cast_providers`
  ADD CONSTRAINT `cast_providers_cast_id_foreign` FOREIGN KEY (`cast_id`) REFERENCES `casts` (`id`) ON DELETE CASCADE;

ALTER TABLE `cast_images`
  ADD CONSTRAINT `cast_images_cast_id_foreign` FOREIGN KEY (`cast_id`) REFERENCES `casts` (`id`) ON DELETE CASCADE;

ALTER TABLE `cast_identity_documents`
  ADD CONSTRAINT `cast_identity_documents_cast_id_foreign` FOREIGN KEY (`cast_id`) REFERENCES `casts` (`id`) ON DELETE CASCADE;

ALTER TABLE `cast_posts`
  ADD CONSTRAINT `cast_posts_cast_id_foreign` FOREIGN KEY (`cast_id`) REFERENCES `casts` (`id`) ON DELETE CASCADE;

ALTER TABLE `cast_shop_relation`
  ADD CONSTRAINT `c_s_rel_cast_id_foreign` FOREIGN KEY (`cast_id`) REFERENCES `casts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `c_s_rel_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

ALTER TABLE `cast_tag_relations`
  ADD CONSTRAINT `fk_cast_tag_relations_cast` FOREIGN KEY (`cast_id`) REFERENCES `casts` (`id`) ON DELETE CASCADE;

ALTER TABLE `shop_profiles`
  ADD CONSTRAINT `shop_profiles_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shop_profiles_industry` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`) ON DELETE SET NULL;

ALTER TABLE `shop_managers`
  ADD CONSTRAINT `shop_managers_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

ALTER TABLE `shop_images`
  ADD CONSTRAINT `shop_images_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

ALTER TABLE `shop_posts`
  ADD CONSTRAINT `shop_posts_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

ALTER TABLE `shop_stations`
  ADD CONSTRAINT `fk_shop_stations_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

ALTER TABLE `shop_jobs`
  ADD CONSTRAINT `shop_jobs_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

ALTER TABLE `shop_job_applications`
  ADD CONSTRAINT `s_j_app_cast_id_foreign` FOREIGN KEY (`cast_id`) REFERENCES `casts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `s_j_app_job_id_foreign` FOREIGN KEY (`shop_job_id`) REFERENCES `shop_jobs` (`id`) ON DELETE CASCADE;

ALTER TABLE `shop_job_tag_relations`
  ADD CONSTRAINT `fk_sj_tag_rel_job` FOREIGN KEY (`shop_job_id`) REFERENCES `shop_jobs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sj_tag_rel_tag` FOREIGN KEY (`tag_id`) REFERENCES `shop_tags` (`id`) ON DELETE CASCADE;

ALTER TABLE `shop_license_documents`
  ADD CONSTRAINT `shop_license_documents_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

ALTER TABLE `shop_tag_relations`
  ADD CONSTRAINT `fk_shop_tag_relations_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

ALTER TABLE `application_deposits`
  ADD CONSTRAINT `app_dep_app_id_foreign` FOREIGN KEY (`shop_job_application_id`) REFERENCES `shop_job_applications` (`id`) ON DELETE CASCADE;

ALTER TABLE `application_deposit_histories`
  ADD CONSTRAINT `app_dep_hist_dep_id_foreign` FOREIGN KEY (`application_deposit_id`) REFERENCES `application_deposits` (`id`) ON DELETE CASCADE;

ALTER TABLE `payment_tasks`
  ADD CONSTRAINT `payment_tasks_application_deposit_id_foreign` FOREIGN KEY (`application_deposit_id`) REFERENCES `application_deposits` (`id`) ON DELETE CASCADE;

ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_cast_id_foreign` FOREIGN KEY (`cast_id`) REFERENCES `casts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

ALTER TABLE `keeps`
  ADD CONSTRAINT `keeps_cast_id_foreign` FOREIGN KEY (`cast_id`) REFERENCES `casts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `keeps_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

ALTER TABLE `footprints`
  ADD CONSTRAINT `footprints_cast_id_foreign` FOREIGN KEY (`cast_id`) REFERENCES `casts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `footprints_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

ALTER TABLE `messages`
  ADD CONSTRAINT `messages_cast_id_foreign` FOREIGN KEY (`cast_id`) REFERENCES `casts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

ALTER TABLE `talk_blocks`
  ADD CONSTRAINT `talk_blocks_cast_id_foreign` FOREIGN KEY (`cast_id`) REFERENCES `casts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `talk_blocks_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_cast_id_foreign` FOREIGN KEY (`cast_id`) REFERENCES `casts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

ALTER TABLE `review_details`
  ADD CONSTRAINT `fk_review_details_review_id` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_review_details_val` FOREIGN KEY (`val`) REFERENCES `review_contents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `review_details_review_content_id_foreign` FOREIGN KEY (`review_content_id`) REFERENCES `review_contents` (`id`) ON DELETE CASCADE;

ALTER TABLE `column_articles`
  ADD CONSTRAINT `column_articles_column_category_id_foreign` FOREIGN KEY (`column_category_id`) REFERENCES `column_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `policy_chapters`
  ADD CONSTRAINT `policy_chapters_policy_document_id_foreign` FOREIGN KEY (`policy_document_id`) REFERENCES `policy_documents` (`id`) ON DELETE CASCADE;

ALTER TABLE `policy_revisions`
  ADD CONSTRAINT `policy_revisions_policy_document_id_foreign` FOREIGN KEY (`policy_document_id`) REFERENCES `policy_documents` (`id`) ON DELETE CASCADE;

-- ------------------------------------------------------------------------------
-- 検索希望条件：cast / shop それぞれ専用テーブル（mock_demo.sql と整合）
-- ------------------------------------------------------------------------------
-- shop_managers にアカウント設定で使うカラムを追加（退会用 soft delete と LINE 連携用）
ALTER TABLE `shop_managers`
  ADD COLUMN IF NOT EXISTS `line_user_id` text DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `deleted_at`   timestamp NULL DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `cast_search_preferences` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `cast_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  -- 位置情報
  `mode`               varchar(16)   COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'profile / passport / current',
  `passport_address`   text          COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `passport_latitude`  decimal(10,7) DEFAULT NULL,
  `passport_longitude` decimal(10,7) DEFAULT NULL,
  `passport_label`     varchar(80)   COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `max_distance_km`    smallint      DEFAULT NULL COMMENT '0=制限なし、>0 で半径 km',
  -- 詳細検索フォームから保存される希望条件
  `shift_frequency`    varchar(16)   COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '週1回出勤 / 週2回出勤 / 週3回以上',
  `work_periods`       json          DEFAULT NULL COMMENT '時間帯配列 morning/day/night',
  `hourly_wage_min`    int UNSIGNED  DEFAULT NULL COMMENT '希望時給（円以上）',
  `industry_ids`       json          DEFAULT NULL COMMENT '希望業種ID配列',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cast_search_preferences_cast` (`cast_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shop_search_preferences` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `max_distance_km`     smallint        DEFAULT NULL COMMENT '0=制限なし、>0 で半径 km',
  `age_min`             tinyint UNSIGNED DEFAULT NULL,
  `age_max`             tinyint UNSIGNED DEFAULT NULL,
  `shift_frequency`     varchar(16)     COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `work_periods`        json            DEFAULT NULL,
  `looks_tag_ids`       json            DEFAULT NULL,
  `personality_tag_ids` json            DEFAULT NULL,
  `night_work_exp`      varchar(8)      COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'none / yes / any',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_search_preferences_shop` (`shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- user_talk_templates : キャスト／店舗ごとに編集できるトーク定型文
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_talk_templates` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_type` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'cast または shop',
  `owner_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'その他',
  `title` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_talk_templates_owner_idx` (`owner_type`, `owner_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- talk_quick_reply_templates : 運営が管理するステータス別クイック定型文（マスタ）
--   - トークルーム下部のクイック定型文パネルに、応募ステータス x 役割で出し分ける
--   - TalkQuickReplyCatalog がこのテーブルを読み、無ければハードコード配列にフォールバック
--   - 運営は /admin/talk-quick-replies から編集
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `talk_quick_reply_templates` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_type` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'cast (キャスト -> 店舗) / shop (店舗 -> キャスト)',
  `status_code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'chatting / interview_pending / interview_fixed / hired / rejected',
  `category` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'thanks / schedule / question / status / intro / help',
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tqrt_lookup_idx` (`owner_type`,`status_code`,`is_active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- support_inquiries : サポート問い合わせフォーム送信内容
--   - キャスト / 店舗 / 未ログイン（ゲスト）からの問い合わせを保存
--   - 運営は admin 画面で確認 → 対応ステータスを更新
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `support_inquiries` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `sender_type` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'cast / shop / guest',
  `sender_id` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '認証済の場合のキャスト/店舗ID',
  `category` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'account / feature / bug / feedback / other',
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '返信用メールアドレス',
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '問い合わせ本文',
  `status` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new' COMMENT 'new / in_progress / resolved / dismissed',
  `assigned_admin_id` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '担当運営アカウントID',
  `admin_note` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '運営側メモ（応対履歴）',
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '送信時 UA（バグ報告調査用）',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '送信元 IP（スパム調査用）',
  `responded_at` timestamp NULL DEFAULT NULL COMMENT '運営が最初に返信/対応した日時',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_inquiries_status_idx` (`status`, `created_at`),
  KEY `support_inquiries_sender_idx` (`sender_type`, `sender_id`),
  KEY `support_inquiries_category_idx` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 店舗有料プラン契約（Premium）
--   - 振込 + 運営の目視確認で有効化（①契約=入金待ち → ②振込 → ③管理画面で入金確認済み → 機能開放）
--   - 請求書番号 PLN-YYYYMM-xxxx / 領収書番号 RCT-YYYYMM-xxxx
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shop_plan_subscriptions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `plan` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'premium',
  `billing_cycle` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'monthly / yearly',
  `amount` int UNSIGNED NOT NULL COMMENT '請求額（税込円） monthly=29000 / yearly=290000',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '1=入金待ち 2=有効 3=期間満了 4=キャンセル',
  `invoice_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '請求書番号 PLN-YYYYMM-xxxx',
  `invoice_issued_at` datetime DEFAULT NULL COMMENT '請求書発行日時（契約時）',
  `payment_due_date` date DEFAULT NULL COMMENT '振込期限（契約から7日）',
  `paid_confirmed_at` datetime DEFAULT NULL COMMENT '運営が入金を目視確認した日時',
  `confirmed_by` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '確認した運営アカウントID',
  `receipt_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '領収書番号 RCT-YYYYMM-xxxx',
  `starts_at` datetime DEFAULT NULL COMMENT 'Premium開始日時（入金確認時）',
  `ends_at` datetime DEFAULT NULL COMMENT 'Premium終了日時（開始+1ヶ月/+1年）',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sps_shop_status_idx` (`shop_id`,`status`),
  KEY `sps_status_due_idx` (`status`,`payment_due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- ai_suggestion_templates : ランダム自動補完テンプレート
--   - shop recruit editor uses this table for the "ランダム" auto-fill button
--     on catchcopy and manager-message fields
--   - category: 'catch_copy' or 'manager_message'
--   - operations can add / remove entries from admin (out of scope here)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_suggestion_templates` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `category` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'catch_copy / manager_message',
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ast_category_enabled_idx` (`category`,`is_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
