-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- ホスト: localhost:3306
-- 生成日時: 2026 年 5 月 17 日 13:40
-- サーバのバージョン： 8.0.45
-- PHP のバージョン: 8.4.19

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- データベース: `mock_demo`
--

-- --------------------------------------------------------

--
-- テーブルの構造 `application_deposits`
--

CREATE TABLE `application_deposits` (
  `id` bigint UNSIGNED NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `application_deposits`
--

INSERT INTO `application_deposits` (`id`, `shop_job_application_id`, `status`, `is_read`, `invoice_number`, `bonus_amount`, `system_fee_amount`, `invoice_amount`, `cast_transfer_amount`, `invoice_issued_at`, `invoice_due_date`, `invoice_sent_at`, `shop_payment_reported_at`, `shop_payment_reported_amount`, `shop_payment_reference`, `shop_payment_confirmed_at`, `cast_transferred_at`, `cast_transfer_reference`, `cast_transfer_note`, `completed_at`, `created_at`, `updated_at`) VALUES
(7, 15, 5, 0, 'INV-TEST-20260503154953', NULL, NULL, NULL, NULL, '2026-04-30 06:49:53', NULL, NULL, NULL, NULL, NULL, '2026-05-01 06:49:53', NULL, NULL, NULL, NULL, '2026-05-03 06:49:53', '2026-05-03 06:49:53');

-- --------------------------------------------------------

--
-- テーブルの構造 `application_deposit_histories`
--

CREATE TABLE `application_deposit_histories` (
  `id` bigint UNSIGNED NOT NULL,
  `application_deposit_id` bigint UNSIGNED NOT NULL,
  `status` tinyint NOT NULL,
  `status_date` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `bank_accounts`
--

CREATE TABLE `bank_accounts` (
  `id` bigint UNSIGNED NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `bank_accounts`
--

INSERT INTO `bank_accounts` (`id`, `holder_type`, `holder_id`, `bank_code`, `bank_name`, `bank_name_kana`, `branch_code`, `branch_name`, `branch_name_kana`, `account_type`, `account_number`, `account_name`, `created_at`, `updated_at`) VALUES
(1, 'casts', 'c00000002', '0001', 'みずほ銀行', 'ミズホ', '001', '東京営業部', 'トウキヨウ', 'ordinary', '1233333', 'テストカメワリ', '2026-03-15 04:20:26', '2026-03-15 04:20:26'),
(2, 'casts', 'c00000001', '0001', 'みずほ銀行', 'ミズホ', '020', '押上支店', 'オシアゲ', 'ordinary', '1233333', 'テストカメワリ', '2026-03-15 14:55:25', '2026-03-15 14:55:25'),
(3, 'casts', 'c00000003', '0006', 'テスト銀行', 'テストギンコウ', '001', '本店', 'ホンテン', 'ordinary', '9988776', 'ヤマダ タロウ', '2026-03-26 06:08:55', '2026-03-26 06:08:55'),
(5, 'system_accounts', '1', '0033', 'PayPay銀行', 'ペイペイ', '001', '本店営業部', 'ホンテン', 'ordinary', '99999999', 'テストカメワリ', '2026-03-26 10:46:57', '2026-03-26 10:46:57');

-- --------------------------------------------------------

--
-- テーブルの構造 `casts`
--

CREATE TABLE `casts` (
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
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `casts`
--

INSERT INTO `casts` (`id`, `email`, `password`, `status`, `identity_status`, `last_login_at`, `remember_token`, `created_at`, `updated_at`, `deleted_at`) VALUES
('c00000001', 'cast01@example.com', NULL, 1, 3, NULL, NULL, NULL, NULL, NULL),
('c00000002', 'kamewari@ideal-inv.co.jp', '$2y$10$w4D9ffo9GfsnhICAShK5EOVwJyYm/GrRPnJ12N1UJCfO3dwjWtbtW', 1, 3, '2026-05-10 17:17:03', NULL, '2026-03-13 07:19:12', '2026-05-10 17:17:03', NULL),
('c00000003', 'marina@ideal-inv.co.jp', '$2y$10$aJNyUiK5ytU731x.gULileBjYVBFWmKkXqYC73uNvhnZHmaBaD7ze', 1, 1, '2026-05-04 07:42:28', NULL, '2026-05-04 07:42:28', '2026-05-04 07:42:28', NULL),
('c00000004', 'test@test', '$2y$10$m2ZRLSxDKoOeleWi.weLa.xSTaCdvMzPwhGMVM/XZ6U3DM0XdIZp6', 1, 1, '2026-05-10 13:38:39', NULL, '2026-05-10 13:38:39', '2026-05-10 13:38:39', NULL),
('c00000005', 'test@test2', '$2y$10$Fzo3wKs62iX7K.xn0osDo.n3qzUSDLTqYqzCFQgAgI4YSDFqjp9i.', 1, 1, '2026-05-10 13:47:29', NULL, '2026-05-10 13:47:29', '2026-05-10 13:47:29', NULL);

-- --------------------------------------------------------

--
-- テーブルの構造 `cast_identity_documents`
--

CREATE TABLE `cast_identity_documents` (
  `id` bigint UNSIGNED NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `cast_identity_documents`
--

INSERT INTO `cast_identity_documents` (`id`, `cast_id`, `category`, `type`, `image_path_front`, `image_path_back`, `status`, `ng_reason`, `expired_at`, `approved_at`, `created_at`, `updated_at`) VALUES
(1, 'c00000002', 'photo_id', 'id_card', 'public/casts/identity/test-mock-front.txt', 'public/casts/identity/test-mock-back.txt', 2, NULL, NULL, '2026-05-05 16:50:04', '2026-03-26 06:14:32', '2026-05-05 16:50:04');

-- --------------------------------------------------------

--
-- テーブルの構造 `cast_images`
--

CREATE TABLE `cast_images` (
  `id` bigint UNSIGNED NOT NULL,
  `cast_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint NOT NULL DEFAULT '0',
  `is_main` tinyint(1) NOT NULL DEFAULT '0',
  `main_order` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `cast_images`
--

INSERT INTO `cast_images` (`id`, `cast_id`, `image_path`, `status`, `is_main`, `main_order`, `created_at`, `updated_at`) VALUES
(6, 'c00000002', 'uploads/casts/gallery/Iu3pJkIIuubRr1yH4nYUueQNHVkp1lt5shbHcwRt.png', 0, 1, 0, '2026-03-13 07:20:18', '2026-05-04 08:42:53'),
(19, 'c00000003', 'uploads/casts/gallery/3ZqsZxHB2C6JrbkCzXJnk9y3L8gVoy7zEcTxZqfn.jpg', 0, 1, 0, '2026-05-04 08:03:57', '2026-05-04 08:06:52'),
(21, 'c00000003', 'uploads/casts/gallery/OLfZr4zHbtSjgYwOjD0IszG7hPexDAPH10kIE3pC.jpg', 0, 0, 1, '2026-05-04 08:06:03', '2026-05-04 08:06:52'),
(22, 'c00000003', 'uploads/casts/gallery/N0p2CcUJcbVE8vCd8M37KmJTEqb3eTHMEXwCyvLz.jpg', 0, 0, 2, '2026-05-04 08:06:52', '2026-05-04 08:06:52'),
(24, 'c00000001', 'uploads/casts/gallery/mUsEhOGZxgjhS8x8xs9bucuxiEXl10SevNQsxUzB.jpg', 0, 0, 1, '2026-05-04 08:42:06', '2026-05-16 15:13:12'),
(25, 'c00000001', 'uploads/casts/gallery/Bo2WsVwXhydS0eUJJObjrVPrxu9ytRY7qzCZISfE.jpg', 0, 0, 3, '2026-05-04 08:42:19', '2026-05-16 15:13:12'),
(26, 'c00000002', 'uploads/casts/gallery/HSyTRaSjRF0JuXbA1gakOdzvZfXgAHUAFsexywcx.jpg', 0, 0, 1, '2026-05-04 08:42:53', '2026-05-04 08:42:53'),
(28, 'c00000004', 'uploads/casts/gallery/iB7FCvX1L26xsjiE0JYlwO8J28vORZFE2Kx6jFyj.jpg', 0, 0, 1, '2026-05-10 13:38:39', '2026-05-10 13:38:59'),
(29, 'c00000004', 'uploads/casts/gallery/SnzSjGOsCRaR60btz4cLCtS4mcfuAmc3mQxbeEft.jpg', 0, 1, 0, '2026-05-10 13:38:57', '2026-05-10 13:38:59'),
(30, 'c00000005', 'uploads/casts/gallery/UO5TPAl5uAYKtek8NvndlzZ8AZpBZoffq7sxAk5s.jpg', 0, 1, 0, '2026-05-10 13:47:29', '2026-05-10 13:47:29'),
(31, 'c00000001', 'uploads/casts/gallery/zTzpkWgd31Zvy0xYInW5nNOtWR0Jj4g2l1fXZgrn.jpg', 0, 1, 0, '2026-05-10 14:25:52', '2026-05-16 15:13:12'),
(32, 'c00000001', 'uploads/casts/gallery/UewCSGB4xAQMhiXBu5SoCgqSH4NrKzEN1h3ep0XQ.jpg', 0, 0, 2, '2026-05-10 14:26:05', '2026-05-16 15:13:12');

-- --------------------------------------------------------

--
-- テーブルの構造 `cast_posts`
--

CREATE TABLE `cast_posts` (
  `id` bigint UNSIGNED NOT NULL,
  `cast_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `cast_posts`
--

INSERT INTO `cast_posts` (`id`, `cast_id`, `body`, `created_at`, `updated_at`) VALUES
(1, 'c00000003', 'test', '2026-05-06 16:42:43', '2026-05-06 16:42:43'),
(2, 'c00000001', 'ひとこと！', '2026-05-08 01:37:56', '2026-05-08 01:37:56');

-- --------------------------------------------------------

--
-- テーブルの構造 `cast_profiles`
--

CREATE TABLE `cast_profiles` (
  `id` bigint UNSIGNED NOT NULL,
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
  `pr` text COLLATE utf8mb4_unicode_ci,
  `personality_type` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `available_until` timestamp NULL DEFAULT NULL COMMENT '「今すぐ入れる」宣言の有効期限。NULL または過去なら宣言なし',
  `available_declared_at` timestamp NULL DEFAULT NULL COMMENT '直近の available_until を宣言した時刻（同時刻タイブレーク用）',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `cast_profiles`
-- （動作確認用: id=1 は 2h 宣言中／id=5 は 4h 宣言中）
--

INSERT INTO `cast_profiles` (`id`, `industry_id`, `cast_id`, `nickname`, `name`, `name_kana`, `birthday`, `zip`, `pref`, `city`, `addr`, `building`, `tel`, `height`, `weight`, `bust`, `waist`, `hip`, `profession`, `exp`, `pr`, `personality_type`, `latitude`, `longitude`, `available_until`, `available_declared_at`, `created_at`, `updated_at`) VALUES
(1, 1, 'c00000001', 'みさき', '桜井美咲', NULL, '2001-05-15', '103-0016', '東京都', '中央区', '日本橋小網町', NULL, NULL, 156, 55, 50, 60, 70, '学生', 1, '自己紹介文です！', 'LCOH', 35.6826780, 139.7807160, DATE_ADD(NOW(), INTERVAL 2 HOUR), NOW(), '2026-05-06 13:57:04', '2026-05-16 15:13:12'),
(2, NULL, 'c00000002', 'Yui', '田中結衣', NULL, '1994-04-24', '103-0016', '東京都', '中央区', '日本橋小網町', NULL, '07099999999', NULL, NULL, 55, 40, 50, NULL, 0, '自己PR文', NULL, NULL, NULL, NULL, NULL, '2026-03-15 06:24:25', '2026-05-04 08:42:53'),
(3, 1, 'c00000003', 'マリ', '田端麻里奈', NULL, '1999-10-15', '134-0088', '東京都', '江戸川区', '西葛西', NULL, '07099999999', 160, 50, 50, 50, 50, '学生', 1, '自己PRテスト文章\r\n自己PRテスト文章\r\n自己PRテスト文章', NULL, NULL, NULL, NULL, NULL, '2026-05-06 15:32:47', '2026-05-06 15:32:47'),
(4, 1, 'c00000004', '政子', '田所政子', NULL, '2005-05-10', '140-0014', '東京都', '品川区', '大井', NULL, '0356743525', 160, 48, 55, 55, 60, '学生', 0, 'こんにちは。', NULL, NULL, NULL, NULL, NULL, '2026-05-10 13:38:39', '2026-05-10 13:38:59'),
(5, NULL, 'c00000005', 'のりりん', '間瀬紀子', NULL, '1998-05-10', '106-0045', '東京都', '港区', '麻布十番', NULL, '05033333333', 170, 55, 70, 80, 60, '学生', 1, 'はじめまして！お願いします！', NULL, NULL, NULL, DATE_ADD(NOW(), INTERVAL 4 HOUR), NOW(), '2026-05-10 13:47:29', '2026-05-10 13:47:29');

-- --------------------------------------------------------

--
-- テーブルの構造 `cast_providers`
--

CREATE TABLE `cast_providers` (
  `id` bigint UNSIGNED NOT NULL,
  `cast_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'line',
  `provider_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `cast_providers`
--

INSERT INTO `cast_providers` (`id`, `cast_id`, `provider`, `provider_id`, `created_at`, `updated_at`) VALUES
(1, 'c00000002', 'line', 'U1fe64016c3694dd0b1193fd7f55572cd', '2026-05-06 12:24:28', '2026-05-06 12:24:28');

-- --------------------------------------------------------

--
-- テーブルの構造 `cast_search_preferences`
--

CREATE TABLE `cast_search_preferences` (
  `id` bigint UNSIGNED NOT NULL,
  `cast_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mode` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'profile / passport /\r\n  current',
  `passport_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `passport_latitude` decimal(10,7) DEFAULT NULL,
  `passport_longitude` decimal(10,7) DEFAULT NULL,
  `passport_label` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `max_distance_km` smallint DEFAULT NULL COMMENT '0=制限なし、>0 で半径 km',
  `shift_frequency` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '週1回出勤 / 週2回出勤 /\r\n   週3回以上',
  `work_periods` json DEFAULT NULL COMMENT '時間帯配列 morning/day/night',
  `hourly_wage_min` int UNSIGNED DEFAULT NULL COMMENT '希望時給（円以上）',
  `industry_ids` json DEFAULT NULL COMMENT '希望業種ID配列',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `cast_shop_relation`
--

CREATE TABLE `cast_shop_relation` (
  `cast_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `relation_type` tinyint NOT NULL COMMENT '1:ブロック, 2:追加等',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `cast_tags`
--

CREATE TABLE `cast_tags` (
  `id` bigint UNSIGNED NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'looks / personality',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `del_flg` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `cast_tags`
--

INSERT INTO `cast_tags` (`id`, `category`, `name`, `del_flg`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'looks', 'スレンダー', 0, 1, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(2, 'looks', '普通', 0, 2, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(3, 'looks', 'グラマー', 0, 3, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(4, 'looks', 'ぽっちゃり', 0, 4, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(5, 'looks', '高長身', 0, 5, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(6, 'looks', '小柄', 0, 6, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(7, 'looks', 'スタイル抜群', 0, 7, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(8, 'looks', 'キレイ系', 0, 8, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(9, 'looks', '可愛い系', 0, 9, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(10, 'looks', 'セクシー系', 0, 10, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(11, 'looks', '巨乳', 0, 11, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(12, 'looks', 'ギャル', 0, 12, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(13, 'looks', '清楚系', 0, 13, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(14, 'looks', 'お姉さん系', 0, 14, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(15, 'looks', '癒し系', 0, 15, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(16, 'looks', '萌え系', 0, 16, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(17, 'personality', 'モデル経験あり', 0, 17, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(18, 'personality', 'ハーフ', 0, 18, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(19, 'personality', 'アイドル経験有り', 0, 19, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(20, 'personality', 'インフルエンサー', 0, 20, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(21, 'personality', '芸能人', 0, 21, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(22, 'personality', 'OL/一般職', 0, 22, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(23, 'personality', '学生', 0, 23, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(24, 'personality', '顔出しOK', 0, 24, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(26, 'looks', '初心者/未経験', 0, 26, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(32, 'personality', '社交的', 0, 1, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(33, 'personality', '明るい', 0, 2, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(34, 'personality', 'おしゃべり上手', 0, 3, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(35, 'personality', 'わいわい系', 0, 4, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(36, 'personality', 'パリピ系', 0, 5, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(37, 'personality', 'おとなしめ', 0, 6, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(38, 'personality', 'おっとり', 0, 7, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(39, 'personality', 'しっとり', 0, 8, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(40, 'personality', '接客上手', 0, 9, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(41, 'personality', 'お酒飲める人', 0, 10, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(42, 'personality', 'お酒苦手', 0, 11, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(43, 'personality', '姉御肌', 0, 12, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(44, 'personality', '妹気質', 0, 13, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(45, 'personality', '連絡マメ', 0, 14, '2026-03-13 02:32:51', '2026-03-13 02:32:51');

-- --------------------------------------------------------

--
-- テーブルの構造 `cast_tag_relations`
--

CREATE TABLE `cast_tag_relations` (
  `id` bigint UNSIGNED NOT NULL,
  `cast_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'キャストID',
  `tag_id` bigint UNSIGNED NOT NULL COMMENT '各種タグID',
  `tag_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'タグの種類 (例: looks, personalityなど)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `cast_tag_relations`
--

INSERT INTO `cast_tag_relations` (`id`, `cast_id`, `tag_id`, `tag_type`, `created_at`, `updated_at`) VALUES
(10, 'c00000001', 7, 'looks', '2026-05-06 13:57:04', '2026-05-06 13:57:04'),
(11, 'c00000001', 37, 'personality', '2026-05-06 13:57:04', '2026-05-06 13:57:04'),
(12, 'c00000001', 45, 'personality', '2026-05-06 13:57:04', '2026-05-06 13:57:04'),
(18, 'c00000003', 6, 'look', '2026-05-06 15:32:47', '2026-05-06 15:32:47'),
(19, 'c00000003', 7, 'look', '2026-05-06 15:32:47', '2026-05-06 15:32:47'),
(20, 'c00000003', 11, 'look', '2026-05-06 15:32:47', '2026-05-06 15:32:47'),
(21, 'c00000003', 38, 'personality', '2026-05-06 15:32:47', '2026-05-06 15:32:47'),
(22, 'c00000003', 42, 'personality', '2026-05-06 15:32:47', '2026-05-06 15:32:47'),
(23, 'c00000004', 1, 'look', '2026-05-10 13:38:39', '2026-05-10 13:38:39'),
(24, 'c00000004', 12, 'look', '2026-05-10 13:38:39', '2026-05-10 13:38:39'),
(25, 'c00000004', 20, 'personality', '2026-05-10 13:38:39', '2026-05-10 13:38:39'),
(26, 'c00000004', 22, 'personality', '2026-05-10 13:38:39', '2026-05-10 13:38:39');

-- --------------------------------------------------------

--
-- テーブルの構造 `character_guide_settings`
--

CREATE TABLE `character_guide_settings` (
  `id` bigint UNSIGNED NOT NULL,
  `route_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `screen_label` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `character_guide_settings`
--

INSERT INTO `character_guide_settings` (`id`, `route_name`, `screen_label`, `is_enabled`, `message`, `created_at`, `updated_at`) VALUES
(1, 'cast.profile.edit', 'キャスト：プロフィール編集', 1, 'プロフィールを充実させていただくと、よりマッチしやすくなります。公開したくない内容は、無理にご記入いただかなくても問題ございません。', '2026-05-08 22:11:18', '2026-05-08 22:11:18'),
(2, 'cast.search.index', 'キャスト：店舗検索（一覧/AI）', 1, 'あなたの希望に合うお店を探そう！\nひとこと更新が新しい順に並んでいるよ。', '2026-05-08 22:11:18', '2026-05-08 22:11:18'),
(3, 'cast.interaction.index', 'キャスト：つながり（LIKES）', 1, '「優良店」バッヂは、直近の請求・入金がスムーズな店舗に付く信頼の目印です。', '2026-05-08 22:11:18', '2026-05-08 22:11:18'),
(4, 'cast.register', 'キャスト：新規登録', 1, 'キャスト登録です。\n必要項目を入力してください。', '2026-05-08 22:11:18', '2026-05-08 22:11:18'),
(5, 'shop.search.index', '店舗：キャスト検索', 1, '気になるキャストを探そう！\nひとこと更新が新しい順に並んでいるよ。', '2026-05-08 22:11:18', '2026-05-08 22:11:18'),
(7, 'shop.mypage.index', '店舗：マイページ', 1, '営業許可証と風営許可証の両方を提出し、運営の承認がおりるまで求人を公開できません。面談日設定などの機能も、書類が整い承認後にご利用いただけます。', '2026-05-08 22:11:18', '2026-05-08 22:11:18'),
(8, 'shop.mypage.documents.onboarding', '店舗：許可証提出オンボーディング', 1, '営業許可証と風営許可証の2種類をアップロードし、運営の承認がおりるまで求人を公開できません。「あとで」にして先に求人票だけ登録することもできますが、公開は審査が完了してからになります。', '2026-05-08 22:11:18', '2026-05-08 22:11:18'),
(9, 'shop.register', '店舗：新規登録', 1, '店舗登録です。\n必要項目を入力してください。', '2026-05-08 22:11:18', '2026-05-08 22:11:18');

-- --------------------------------------------------------

--
-- テーブルの構造 `column_articles`
--

CREATE TABLE `column_articles` (
  `id` bigint UNSIGNED NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `column_articles`
--

INSERT INTO `column_articles` (`id`, `title`, `slug`, `column_category_id`, `image_path`, `tags`, `body`, `is_published`, `published_at`, `visible_to_cast`, `visible_to_shop`, `visible_to_guest`, `created_at`, `updated_at`) VALUES
(1, 'コラムのテスト', '1', 4, NULL, NULL, '面接対策しようね！', 1, '2026-05-06 08:23:00', 1, 1, 1, '2026-05-06 08:23:57', '2026-05-06 08:23:57');

-- --------------------------------------------------------

--
-- テーブルの構造 `column_categories`
--

CREATE TABLE `column_categories` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'カテゴリ名',
  `directory` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL用ディレクトリ',
  `del_flg` tinyint(1) NOT NULL DEFAULT '0' COMMENT '削除フラグ',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `column_categories`
--

INSERT INTO `column_categories` (`id`, `name`, `directory`, `del_flg`, `created_at`, `updated_at`) VALUES
(1, 'キャバクラ情報', 'cabaret-info', 0, '2026-03-13 02:32:50', '2026-03-13 02:32:50'),
(2, 'ラウンジ情報', 'lounge-info', 0, '2026-03-13 02:32:50', '2026-03-13 02:32:50'),
(4, '面接対策', 'interview', 0, '2026-03-13 02:32:50', '2026-03-13 02:32:50');

-- --------------------------------------------------------

--
-- テーブルの構造 `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `favorites`
--

CREATE TABLE `favorites` (
  `id` bigint UNSIGNED NOT NULL,
  `cast_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shop_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action_type` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_type` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `favorites`
--

INSERT INTO `favorites` (`id`, `cast_id`, `shop_id`, `action_type`, `sender_type`, `created_at`) VALUES
(4, 'c00000001', 's00000001', 'KEEP', 'shop', '2026-05-05 08:20:19'),
(10, 'c00000002', 's00000002', 'KEEP', 'shop', '2026-05-05 10:56:30'),
(11, 'c00000001', 's00000002', 'KEEP', 'shop', '2026-05-05 11:07:09'),
(14, 'c00000003', 's00000001', 'KEEP', 'shop', '2026-05-05 13:50:13');

-- --------------------------------------------------------

--
-- テーブルの構造 `footprints`
--

CREATE TABLE `footprints` (
  `id` bigint UNSIGNED NOT NULL,
  `cast_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `profile_views`（プロフィール閲覧ログ）
--

CREATE TABLE `profile_views` (
  `id` bigint UNSIGNED NOT NULL,
  `viewer_type` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '閲覧者ロール cast|shop',
  `viewer_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '閲覧者ID',
  `target_type` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '閲覧されたロール cast|shop',
  `target_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '閲覧されたID',
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `industries`
--

CREATE TABLE `industries` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '業種名',
  `del_flg` tinyint(1) NOT NULL DEFAULT '0' COMMENT '削除フラグ',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `industries`
--

INSERT INTO `industries` (`id`, `name`, `del_flg`, `created_at`, `updated_at`) VALUES
(1, 'キャバクラ', 0, '2026-03-13 02:32:50', '2026-03-13 02:32:50'),
(2, 'クラブ', 0, '2026-03-13 02:32:50', '2026-03-13 02:32:50'),
(3, 'ラウンジ', 0, '2026-03-13 02:32:50', '2026-03-13 02:32:50'),
(4, 'ガールズバー', 0, '2026-03-13 02:32:50', '2026-03-13 02:32:50'),
(5, 'コンカフェ', 0, '2026-03-13 02:32:50', '2026-03-13 02:32:50'),
(6, 'スナック', 0, '2026-03-13 02:32:50', '2026-03-13 02:32:50'),
(7, '朝キャバ', 0, '2026-05-17 03:59:10', '2026-05-17 03:59:19'),
(8, '昼キャバ', 0, '2026-05-17 03:59:26', '2026-05-17 12:59:26'),
(9, 'その他', 0, '2026-03-13 02:32:50', '2026-03-13 02:32:50');

-- --------------------------------------------------------

--
-- テーブルの構造 `invoice_template_settings`
--

CREATE TABLE `invoice_template_settings` (
  `id` bigint UNSIGNED NOT NULL,
  `key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'issuer_name, issuer_email, logo_url, footer_text 等',
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `keeps`
--

CREATE TABLE `keeps` (
  `id` bigint UNSIGNED NOT NULL,
  `cast_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` tinyint NOT NULL DEFAULT '1',
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `line_messages`
--

CREATE TABLE `line_messages` (
  `id` bigint UNSIGNED NOT NULL,
  `line_user_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `line_message_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- ビュー用の代替構造 `managers`
-- (実際のビューを参照するには下にあります)
--
CREATE TABLE `managers` (
`id` varchar(20)
,`shop_id` varchar(20)
,`name` varchar(255)
,`email` varchar(255)
,`password` varchar(255)
,`role` tinyint
,`status` tinyint
,`last_login_at` timestamp
,`created_at` timestamp
,`updated_at` timestamp
,`shop_name` varchar(255)
);

-- --------------------------------------------------------

--
-- ビュー用の代替構造 `members`
-- (実際のビューを参照するには下にあります)
--
CREATE TABLE `members` (
);

-- --------------------------------------------------------

--
-- テーブルの構造 `messages`
--

CREATE TABLE `messages` (
  `id` bigint UNSIGNED NOT NULL,
  `cast_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_type` tinyint NOT NULL COMMENT '1:Cast, 2:Shop',
  `type` tinyint NOT NULL DEFAULT '1' COMMENT '1:Text, 2:Image',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `messages`
--

INSERT INTO `messages` (`id`, `cast_id`, `shop_id`, `sender_type`, `type`, `content`, `is_read`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'c00000001', 's00000001', 1, 1, 'Message Test !!', 1, '2026-03-13 12:16:50', '2026-03-13 12:27:00', NULL),
(2, 'c00000001', 's00000001', 2, 2, '{\"offer_token\":\"35abd9fb-f906-41d8-8e1d-c51100faa98b\",\"options\":[\"2026-03-14 23:00\",\"2026-03-13 21:57\"]}', 1, '2026-03-13 12:58:03', '2026-03-13 12:59:08', NULL),
(3, 'c00000001', 's00000001', 1, 3, '{\"offer_token\":\"35abd9fb-f906-41d8-8e1d-c51100faa98b\",\"selected_option\":\"2026-03-14 23:00\"}', 1, '2026-03-13 12:59:11', '2026-03-13 13:15:05', NULL),
(4, 'c00000001', 's00000001', 2, 4, 'この度は面談ありがとうございました。採用で進めさせていただきたいと考えております。詳細は追ってご連絡いたします。', 1, '2026-03-13 13:15:22', '2026-03-13 13:19:48', NULL),
(5, 'c00000001', 's00000001', 2, 2, '{\"offer_token\":\"0f001b62-0736-4d54-a28d-c7ea2d508ff9\",\"options\":[\"2026-03-13 22:16\"]}', 1, '2026-03-13 13:16:05', '2026-03-13 13:19:48', NULL),
(6, 'c00000001', 's00000001', 2, 1, 'TEST', 1, '2026-03-13 13:16:19', '2026-03-13 13:19:48', NULL),
(7, 'c00000001', 's00000001', 1, 3, '{\"offer_token\":\"0f001b62-0736-4d54-a28d-c7ea2d508ff9\",\"selected_option\":\"2026-03-13 22:16\"}', 1, '2026-03-13 13:31:14', '2026-03-13 16:28:48', NULL),
(8, 'c00000001', 's00000001', 2, 4, 'この度は面談ありがとうございました。採用で進めさせていただきたいと考えております。詳細は追ってご連絡いたします。', 1, '2026-03-13 16:28:54', '2026-03-13 17:16:49', NULL),
(9, 'c00000002', 's00000001', 1, 1, 'やり取りテスト', 1, '2026-03-15 03:08:07', '2026-03-15 03:12:46', NULL),
(12, 'c00000002', 's00000001', 1, 1, 'やり取りテスト', 1, '2026-03-15 03:08:08', '2026-03-15 03:12:46', NULL),
(14, 'c00000002', 's00000001', 1, 1, 'え？', 1, '2026-03-15 03:08:32', '2026-03-15 03:12:46', NULL),
(18, 'c00000002', 's00000001', 1, 1, 'テスト', 1, '2026-03-15 04:28:22', '2026-03-15 12:49:08', NULL),
(22, 'c00000002', 's00000001', 1, 1, 'あああ', 1, '2026-03-15 05:08:50', '2026-03-15 12:49:08', NULL),
(24, 'c00000002', 's00000001', 1, 1, 'テスト', 1, '2026-03-15 06:20:15', '2026-03-15 12:49:08', NULL),
(25, 'c00000002', 's00000001', 2, 1, 'test', 1, '2026-03-15 12:49:18', '2026-05-06 12:25:11', NULL),
(27, 'c00000001', 's00000001', 2, 1, 'あれ？', 1, '2026-03-15 14:52:15', '2026-03-15 14:59:32', NULL),
(29, 'c00000001', 's00000001', 1, 1, 'ありがとうございました。', 1, '2026-03-15 14:59:39', '2026-03-16 04:12:05', NULL),
(30, 'c00000001', 's00000001', 1, 1, 'テスト', 1, '2026-03-20 08:17:18', '2026-03-20 08:18:48', NULL),
(31, 'c00000003', 's00000001', 2, 1, '初めてのメッセージ', 1, '2026-05-04 11:10:29', '2026-05-04 11:11:03', NULL),
(32, 'c00000003', 's00000001', 1, 1, 'ななな', 1, '2026-05-04 11:11:07', '2026-05-04 11:11:36', NULL),
(33, 'c00000003', 's00000001', 2, 1, 'ヘルプはいれますか？', 1, '2026-05-06 09:14:40', '2026-05-06 15:20:31', NULL),
(34, 'c00000003', 's00000001', 2, 2, '{\"offer_token\":\"fa8ea0fe-c961-420c-a7c9-0b6f1f710927\",\"options\":[\"2026-05-10 11:00\"],\"bonus_meta\":{\"bonus_amount\":0,\"working_days\":\"5\",\"working_hours\":\"\",\"extra_condition\":\"\"},\"invalidated\":true,\"invalidated_at\":\"2026-05-07 00:18:47\"}', 1, '2026-05-06 10:32:30', '2026-05-06 15:20:31', NULL),
(35, 'c00000003', 's00000001', 2, 1, 'テスト', 1, '2026-05-06 10:32:47', '2026-05-06 15:20:31', NULL),
(36, 'c00000002', 's00000001', 1, 1, 'テスト', 1, '2026-05-06 12:25:15', '2026-05-06 12:25:23', NULL),
(37, 'c00000002', 's00000001', 2, 1, 'テスト', 1, '2026-05-06 12:25:26', '2026-05-06 12:28:19', NULL),
(38, 'c00000002', 's00000001', 2, 6, '{\"image_path\":\"public\\/uploads\\/messages\\/pOsmpilmofYEJGMjDqxKWrE9hn3zxxYK90saIRlE.png\",\"caption\":\"\"}', 0, '2026-05-06 14:26:57', '2026-05-06 14:26:57', NULL),
(40, 'c00000003', 's00000001', 2, 2, '{\"offer_token\":\"f716cc22-4399-4284-b868-af8a9ac857f5\",\"options\":[\"2026-10-15 11:20:00\"],\"bonus_meta\":{\"bonus_amount\":0,\"working_days\":\"5\",\"working_hours\":\"\",\"extra_condition\":\"\"},\"invalidated\":true,\"invalidated_at\":\"2026-05-07 00:38:15\"}', 1, '2026-05-06 15:18:47', '2026-05-06 15:38:15', NULL),
(41, 'c00000003', 's00000001', 1, 3, '{\"offer_token\":\"f716cc22-4399-4284-b868-af8a9ac857f5\",\"selected_option\":\"2026-10-15 11:20:00\",\"bonus_meta\":{\"bonus_amount\":0,\"working_days\":\"5\",\"working_hours\":\"\",\"extra_condition\":\"\"}}', 1, '2026-05-06 15:20:39', '2026-05-06 15:23:04', NULL),
(42, 'c00000003', 's00000001', 2, 7, '{\"requested_at\":\"2026-05-07 00:23:14\"}', 1, '2026-05-06 15:23:14', '2026-05-06 15:32:15', NULL),
(43, 'c00000003', 's00000001', 1, 1, '面談キャンセルを承諾しました。やり取り中に戻します。', 1, '2026-05-06 15:32:19', '2026-05-06 15:34:41', NULL),
(44, 'c00000003', 's00000001', 2, 2, '{\"offer_token\":\"8c912675-0ef3-4081-bec7-ab2935b436fc\",\"options\":[\"2026-05-07 12:00:00\",\"2026-05-30 15:00:00\"],\"bonus_meta\":{\"bonus_amount\":0,\"working_days\":\"5\",\"working_hours\":\"\",\"extra_condition\":\"\"},\"invalidated\":true,\"invalidated_at\":\"2026-05-07 00:38:15\"}', 1, '2026-05-06 15:35:18', '2026-05-06 15:39:47', NULL),
(45, 'c00000003', 's00000001', 2, 2, '{\"offer_token\":\"ade12351-4ec1-4ef9-8e98-26b616b945d3\",\"options\":[\"2026-05-09 12:00:00\"],\"bonus_meta\":{\"bonus_amount\":0,\"working_days\":\"5\",\"working_hours\":\"\",\"extra_condition\":\"\"}}', 1, '2026-05-06 15:38:15', '2026-05-06 15:39:47', NULL),
(46, 'c00000003', 's00000001', 1, 3, '{\"offer_token\":\"ade12351-4ec1-4ef9-8e98-26b616b945d3\",\"selected_option\":\"2026-05-09 12:00:00\",\"bonus_meta\":{\"bonus_amount\":0,\"working_days\":\"5\",\"working_hours\":\"\",\"extra_condition\":\"\"}}', 1, '2026-05-06 15:39:51', '2026-05-06 15:46:13', NULL),
(47, 'c00000003', 's00000001', 2, 4, 'この度は面談ありがとうございました。ぜひ採用で進めさせていただきたいと考えております。今後の流れについて、あらためてご連絡いたします。', 1, '2026-05-06 15:51:05', '2026-05-06 16:17:01', NULL),
(48, 'c00000003', 's00000001', 2, 1, '承知しました。よろしくお願いします。', 1, '2026-05-06 16:16:41', '2026-05-06 16:17:01', NULL);

-- --------------------------------------------------------

--
-- テーブルの構造 `migrations`
--

CREATE TABLE `migrations` (
  `id` int UNSIGNED NOT NULL,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `ng_words`
--

CREATE TABLE `ng_words` (
  `id` bigint UNSIGNED NOT NULL,
  `word` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '有効フラグ',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `ng_words`
--

INSERT INTO `ng_words` (`id`, `word`, `is_active`, `created_at`, `updated_at`) VALUES
(1, '個人連絡先', 1, '2026-03-13 02:03:33', '2026-03-13 02:03:33'),
(2, '連絡先交換', 1, '2026-03-13 02:03:33', '2026-03-13 02:03:33'),
(3, 'LINE交換', 1, '2026-03-13 02:03:33', '2026-03-13 02:03:33'),
(4, 'ライン交換', 1, '2026-03-13 02:03:33', '2026-03-13 02:03:33'),
(5, 'Instagram', 1, '2026-03-13 02:03:33', '2026-03-13 02:03:33'),
(6, 'インスタ', 1, '2026-03-13 02:03:33', '2026-03-13 02:03:33'),
(7, 'X交換', 1, '2026-03-13 02:03:33', '2026-03-13 02:03:33'),
(8, 'Twitter交換', 1, '2026-03-13 02:03:33', '2026-03-13 02:03:33'),
(9, 'カカオ', 1, '2026-03-13 02:03:33', '2026-03-13 02:03:33'),
(10, 'Kakao', 1, '2026-03-13 02:03:33', '2026-03-13 02:03:33'),
(11, 'Telegram', 1, '2026-03-13 02:03:33', '2026-03-13 02:03:33'),
(12, 'テレグラム', 1, '2026-03-13 02:03:33', '2026-03-13 02:03:33'),
(13, '直引き', 1, '2026-03-13 02:03:33', '2026-03-13 02:03:33'),
(14, '店外', 1, '2026-03-13 02:03:33', '2026-03-13 02:03:33'),
(15, '裏オプ', 1, '2026-03-13 02:03:33', '2026-03-13 02:03:33'),
(16, '本番', 1, '2026-03-13 02:03:33', '2026-03-13 02:03:33'),
(17, 'ホ別', 1, '2026-03-13 02:03:33', '2026-03-13 02:03:33'),
(18, '会うだけ', 1, '2026-03-13 02:03:33', '2026-03-13 02:03:33'),
(19, '条件あり', 1, '2026-03-13 02:03:33', '2026-03-13 02:03:33'),
(20, '個別契約', 1, '2026-03-13 02:03:33', '2026-05-08 20:09:43');

-- --------------------------------------------------------

--
-- テーブルの構造 `notices`
--

CREATE TABLE `notices` (
  `id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '本文（プレーンテキスト想定）',
  `is_published` tinyint(1) NOT NULL DEFAULT '0',
  `published_at` timestamp NULL DEFAULT NULL,
  `visible_to_cast` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'キャスト向けに表示',
  `visible_to_shop` tinyint(1) NOT NULL DEFAULT '1' COMMENT '店舗向けに表示',
  `visible_to_guest` tinyint(1) NOT NULL DEFAULT '0' COMMENT '未ログインの/support/noticesに表示',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `notices`
--

INSERT INTO `notices` (`id`, `title`, `slug`, `body`, `is_published`, `published_at`, `visible_to_cast`, `visible_to_shop`, `visible_to_guest`, `created_at`, `updated_at`) VALUES
(1, 'おしらせテスト', '1', 'テスト\r\n改行１\r\n改行２', 0, NULL, 1, 1, 1, '2026-03-26 06:21:14', '2026-03-26 06:21:14');

-- --------------------------------------------------------

--
-- テーブルの構造 `notification_preferences`
--

CREATE TABLE `notification_preferences` (
  `id` bigint UNSIGNED NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `notification_preferences`
--

INSERT INTO `notification_preferences` (`id`, `user_type`, `user_id`, `push_enabled`, `line_enabled`, `interview_reminder_enabled`, `deadline_reminder_enabled`, `created_at`, `updated_at`) VALUES
(1, 'cast', 'c00000002', 1, 1, 1, 1, '2026-05-06 12:24:56', '2026-05-06 12:24:56');

-- --------------------------------------------------------

--
-- テーブルの構造 `admin_notification_settings`
-- NotificationSpecService の通知・リマインダー・タスク仕様の上書き保存先
-- （NULL のカラムはカタログのデフォルト値を使用）
--

CREATE TABLE `admin_notification_settings` (
  `id` bigint UNSIGNED NOT NULL,
  `type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `enabled` tinyint(1) DEFAULT NULL,
  `offset_value` int DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `user_reports`（ユーザー間の通報）
--

CREATE TABLE `user_reports` (
  `id` bigint UNSIGNED NOT NULL,
  `reporter_type` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '通報者ロール cast|shop',
  `reporter_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_type` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '通報対象ロール cast|shop',
  `target_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'harassment|contact_info|inappropriate|fake|other',
  `detail` text COLLATE utf8mb4_unicode_ci,
  `context_type` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'talk|profile など',
  `context_message_id` bigint UNSIGNED DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0:未対応 1:対応中 2:完了 3:却下',
  `admin_note` text COLLATE utf8mb4_unicode_ci,
  `handled_by` bigint UNSIGNED DEFAULT NULL,
  `handled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `payment_tasks`
--

CREATE TABLE `payment_tasks` (
  `id` bigint UNSIGNED NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `payment_tasks`
--

INSERT INTO `payment_tasks` (`id`, `application_deposit_id`, `status`, `shop_received_amount`, `platform_fee_amount`, `bank_fee_amount`, `payout_amount`, `transferred_at`, `completed_at`, `evidence_file_path`, `checklist_confirmed_account`, `checklist_confirmed_amount`, `operator_id`, `refund_required`, `created_at`, `updated_at`) VALUES
(2, 7, 2, 0, 0, 220, 0, NULL, NULL, NULL, 0, 0, '1', 0, '2026-05-03 07:12:38', '2026-05-07 03:24:28');

-- --------------------------------------------------------

--
-- テーブルの構造 `policy_chapters`
--

CREATE TABLE `policy_chapters` (
  `id` bigint UNSIGNED NOT NULL,
  `policy_document_id` bigint UNSIGNED NOT NULL,
  `sort_order` int UNSIGNED NOT NULL DEFAULT '0',
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `policy_chapters`
--

INSERT INTO `policy_chapters` (`id`, `policy_document_id`, `sort_order`, `title`, `body`, `created_at`, `updated_at`) VALUES
(1, 2, 0, '第1条（適用）', '本規約は、利用者と当協会との間の本サービスの利用に関わる一切の関係に適用されるものとします。\n当協会は本サービスに関し、本規約のほか、ご利用にあたってのルール等、各種の定め（以下「個別規定」といいます）をすることがあります。これら個別規定はその名称の如何に関わらず、本規約の一部を構成するものとします。', '2026-05-03 06:50:51', '2026-05-03 06:50:51'),
(2, 2, 1, '第2条（利用登録）', '本サービスにおいては、登録希望者が本規約に同意の上、当協会の定める方法によって利用登録を申請し、当協会がこれを承認することによって、利用登録が完了するものとします。', '2026-05-03 06:50:51', '2026-05-03 06:50:51'),
(3, 2, 2, '第3条（アカウント情報の管理）', '利用者は、自己の責任において、本サービスのユーザーID、パスワード、その他のログイン情報を適切に管理するものとします。\n利用者は、いかなる場合にも、アカウントを第三者に譲渡または貸与し、もしくは第三者と共用することはできません。', '2026-05-03 06:50:51', '2026-05-03 06:50:51'),
(4, 2, 3, '第4条（利用料金および支払方法）', '本サービスの一部機能は有料です。利用者は、有料機能の利用にあたっては、当協会が別途定め、本サービス上に表示する利用料金を、当協会が指定する方法により支払うものとします。', '2026-05-03 06:50:51', '2026-05-03 06:50:51'),
(5, 2, 4, '第5条（禁止事項）', '利用者は、本サービスの利用にあたり、以下の行為をしてはなりません。\n・法令または公序良俗に違反する行為\n・犯罪行為に関連する行為\n・当協会、他の利用者、または第三者の知的財産権、肖像権、プライバシー、名誉その他の権利または利益を侵害する行為\n・本サービスの運営を妨害するおそれのある行為\n・不正アクセスをし、またはこれを試みる行為\n・反社会的勢力等への利益供与\n・その他、当協会が不適切と判断する行為', '2026-05-03 06:50:51', '2026-05-03 06:50:51'),
(6, 2, 5, '第6条（本サービスの提供の停止等）', '当協会は、以下のいずれかの事由があると判断した場合、利用者に事前に通知することなく本サービスの全部または一部の提供を停止または中断することができるものとします。\n・本サービスにかかるシステムの保守点検または更新を行う場合\n・地震、落雷、火災、停電または天災などの不可抗力により、本サービスの提供が困難となった場合\n・コンピュータまたは通信回線等が事故により停止した場合\n・その他、当協会が本サービスの提供が困難と判断した場合', '2026-05-03 06:50:51', '2026-05-03 06:50:51'),
(7, 2, 6, '第7条（利用制限および登録抹消）', '当協会は、利用者が以下のいずれかに該当する場合には、事前の通知なく、利用者に対して本サービスの全部もしくは一部の利用を制限し、または利用者としての登録を抹消することができるものとします。\n・本規約のいずれかの条項に違反した場合\n・登録事項に虚偽の事実があることが判明した場合\n・料金等の支払債務の不履行があった場合\n・当協会からの連絡に対し、一定期間返答がない場合\n・本サービスについて、最終の利用から一定期間利用がない場合\n・その他、当協会が本サービスの利用を適当でないと判断した場合', '2026-05-03 06:50:51', '2026-05-03 06:50:51'),
(8, 2, 7, '第8条（保証の否認および免責事項）', '当協会は、本サービスに事実上または法律上の瑕疵（安全性、信頼性、正確性、完全性、有効性、特定の目的への適合性、セキュリティなどに関する欠陥、エラーやバグ、権利侵害などを含みます）がないことを明示的にも黙示的にも保証しておりません。', '2026-05-03 06:50:51', '2026-05-03 06:50:51'),
(9, 2, 8, '第9条（サービス内容の変更等）', '当協会は、利用者に通知することなく、本サービスの内容を変更し、または提供を中止することができるものとし、これによって利用者に生じた損害について一切の責任を負いません。', '2026-05-03 06:50:51', '2026-05-03 06:50:51'),
(10, 2, 9, '第10条（利用規約の変更）', '当協会は、必要と判断した場合には、利用者に通知することなくいつでも本規約を変更することができるものとします。なお、本規約の変更後、本サービスの利用を開始した場合には、当該利用者は変更後の規約に同意したものとみなします。', '2026-05-03 06:50:51', '2026-05-03 06:50:51'),
(11, 2, 10, '第11条（個人情報の取扱い）', '当協会は、本サービスの利用によって取得する個人情報については、当協会「プライバシーポリシー」に従い適切に取り扱うものとします。', '2026-05-03 06:50:51', '2026-05-03 06:50:51'),
(12, 2, 11, '第12条（通知または連絡）', '利用者と当協会との間の通知または連絡は、当協会の定める方法によって行うものとします。', '2026-05-03 06:50:51', '2026-05-03 06:50:51'),
(13, 2, 12, '第13条（権利義務の譲渡の禁止）', '利用者は、当協会の書面による事前の承諾なく、利用契約上の地位または本規約に基づく権利もしくは義務を第三者に譲渡し、または担保に供することはできません。', '2026-05-03 06:50:51', '2026-05-03 06:50:51'),
(14, 2, 13, '第14条（準拠法・裁判管轄）', '本規約の解釈にあたっては、日本法を準拠法とします。\n本サービスに関して紛争が生じた場合には、当協会の本店所在地を管轄する裁判所を専属的合意管轄とします。', '2026-05-03 06:50:51', '2026-05-03 06:50:51'),
(15, 3, 0, '個人情報の定義', '本ポリシーにおいて「個人情報」とは、個人情報保護法に定める「個人情報」を指し、生存する個人に関する情報であって、当該情報に含まれる氏名、生年月日、住所、電話番号、連絡先、メールアドレス、その他の記述等により特定の個人を識別できる情報を指します。', '2026-05-03 06:50:51', '2026-05-03 06:50:51'),
(16, 3, 1, 'Cookie・広告配信・アクセス解析', '本サービスは、サービス向上およびユーザー体験の最適化のため、Cookieおよび類似技術を使用する場合があります。これらの技術により取得される情報には個人を特定する情報は含まれません。\nまた、第三者の広告配信ツール（Google Adsense等）およびアクセス解析ツール（Google Analytics等）を利用する場合があります。これらのツールはCookieを使用してトラフィックデータを収集します。', '2026-05-03 06:50:51', '2026-05-03 06:50:51'),
(17, 3, 2, '取得する情報の利用目的', '当協会は、取得した個人情報を以下の目的のために利用します。\n・本サービスの提供および本人確認のため\n・本サービスに関する各種ご案内・お知らせ・問い合わせ対応のため\n・本サービスの改善・新機能開発のため\n・利用料金の請求のため\n・利用規約に違反するユーザーや、不正・不当な目的でサービスを利用しようとするユーザーの特定と、ご利用をお断りするため\n・上記の利用目的に付随する目的', '2026-05-03 06:50:51', '2026-05-03 06:50:51'),
(18, 3, 3, '取得情報の管理', '当協会は、取得した個人情報を厳重に管理し、不正アクセス・紛失・破壊・改ざん・漏えい等の防止に必要かつ適切な安全管理措置を講じます。', '2026-05-03 06:50:51', '2026-05-03 06:50:51'),
(19, 3, 4, '取得情報の第三者への提供', '当協会は、次に掲げる場合を除いて、あらかじめユーザーの同意を得ることなく、第三者に個人情報を提供することはありません。ただし、個人情報保護法その他の法令で認められる場合を除きます。\n・人の生命、身体または財産の保護のために必要がある場合であって、本人の同意を得ることが困難であるとき\n・公衆衛生の向上または児童の健全な育成の推進のために特に必要がある場合であって、本人の同意を得ることが困難であるとき\n・国の機関もしくは地方公共団体またはその委託を受けた者が法令の定める事務を遂行することに対して協力する必要がある場合', '2026-05-03 06:50:51', '2026-05-03 06:50:51'),
(20, 3, 5, '取得情報の保管・委託', '当協会は、利用目的の達成に必要な範囲内において、個人情報の取扱いの全部または一部を第三者に委託する場合があります。この場合、委託先について必要かつ適切な監督を行います。', '2026-05-03 06:50:51', '2026-05-03 06:50:51'),
(21, 3, 6, '取得情報の保有・消去', '当協会は、個人情報の利用目的の達成に必要な期間に限り、個人情報を保有します。利用目的が達成された個人情報については、合理的な期間内に消去します。', '2026-05-03 06:50:51', '2026-05-03 06:50:51'),
(22, 3, 7, '取得情報の開示・訂正等', 'ユーザーは、当協会が保有する自身の個人情報について、開示・訂正・追加・削除・利用停止を求めることができます。これらのご請求がある場合は、本人確認のうえ、合理的な期間内に対応いたします。', '2026-05-03 06:50:51', '2026-05-03 06:50:51'),
(23, 3, 8, 'お問い合わせ窓口', '本ポリシーに関するお問い合わせは、本サービス内のお問い合わせフォームよりご連絡ください。', '2026-05-03 06:50:51', '2026-05-03 06:50:51'),
(24, 3, 9, 'プライバシーポリシーの変更', '本ポリシーの内容は、ユーザーに通知することなく変更することがあります。変更後のプライバシーポリシーは、本サービス上に表示した時点から効力を生じるものとします。', '2026-05-03 06:50:51', '2026-05-03 06:50:51');

-- --------------------------------------------------------

--
-- テーブルの構造 `policy_documents`
--

CREATE TABLE `policy_documents` (
  `id` bigint UNSIGNED NOT NULL,
  `key` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'about / terms / privacy',
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ページタイトル',
  `lead_title` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'リード見出し（例: GREETING / 理事長 挨拶）',
  `lead_body` text COLLATE utf8mb4_unicode_ci COMMENT 'リード本文（運営協会の挨拶文等）',
  `meta` json DEFAULT NULL COMMENT '協会概要などの構造化データ',
  `is_locked` tinyint(1) NOT NULL DEFAULT '1' COMMENT '既定はロック状態（編集不可）',
  `updated_by_id` bigint UNSIGNED DEFAULT NULL COMMENT '最終更新者の system_account.id',
  `updated_by_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '最終更新者の表示名',
  `content_updated_at` timestamp NULL DEFAULT NULL COMMENT '最終更新日時',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `policy_documents`
--

INSERT INTO `policy_documents` (`id`, `key`, `title`, `lead_title`, `lead_body`, `meta`, `is_locked`, `updated_by_id`, `updated_by_name`, `content_updated_at`, `created_at`, `updated_at`) VALUES
(1, 'about', '運営協会', 'GREETING / 理事長 挨拶', '本ホームページにお越しいただき誠にありがとうございます。\r\n一般社団法人 日本ナイトワーク適正化協会の理事長を務めさせていただいております森下翼と申します。\r\n\r\n2019年から本格的に始まった新型コロナウイルス感染症（COVID-19）の影響で、ナイトワーク業界でも数々の店舗、それに準ずる全ての方々が現在も大打撃を受けながら日々不安な運用を行っております。\r\n\r\n私はナイトワークのコンサルタントして活動する中で数々の店舗関係者様から相談を受ける日々を過ごしていました。\r\n\r\n中でも店舗様からのコストカットに関するご相談が非常に多く、店舗の運営継続における「求人のコスト」には全国の店舗様は日々頭を抱えております。\r\n\r\n同時にキャストさんも店舗様が不景気になればおのずと業務委託契約を解消されてしまい手取りが減ってしまう悪循環が起きています。\r\n\r\nキャストさんを含めた良い人材を揃えるには求人広告はもちろん、近年路上での迷惑防止条例違反や反社会勢力との関わりもあるスカウト会社にも頼らなくては安定した人材供給ができない業界なのは周知の事実かと思います。\r\n\r\nそこで、当協会では店舗様にとってもキャストさんにとっても条件が良くなるシステムを開発いたしました。\r\n\r\n店舗様にとっては今までより安い求人コストを実現。キャスト様は本来店舗様からスカウト会社へ支払われる所謂「スカウトバック」を「入店準備金」として受け取れる仕組みになります。\r\n\r\nこの双方にメリットのあるシステムを全国に広める活動を通し、ナイトワークの活性化に繋がれるよう尽力している次第でございます。\r\n\r\n皆様のご協力で、ナイトワークに関わる全ての方が豊かになれるよう心から願っております。\r\n\r\n\r\n一般社団法人 日本ナイトワーク適正化協会\r\n\r\n理事長　森下翼', '{\"address\": {\"label\": \"所在地\", \"value\": \"\"}, \"capital\": {\"label\": \"資本金\", \"value\": \"10,000,000円（準備金含む）\"}, \"business\": {\"label\": \"事業内容\", \"value\": \"ナイトワーク業界における人材サービスの適正化推進、各種システムの企画・運営\"}, \"org_name\": {\"label\": \"協会名\", \"value\": \"一般社団法人日本ナイトワーク適正化協会\"}, \"established_at\": {\"label\": \"設立年月日\", \"value\": \"\"}, \"representative\": {\"label\": \"理事長\", \"value\": \"森下翼\"}}', 1, 1, '管理者アカウント1', '2026-05-03 10:20:33', '2026-05-03 06:50:51', '2026-05-03 10:20:33'),
(2, 'terms', '利用規約', NULL, 'この利用規約（以下「本規約」といいます）は、一般社団法人 日本ナイトワーク適正化協会（以下「当協会」といいます）が提供するサービス「ミセチョク」（以下「本サービス」といいます）の利用条件を定めるものです。利用者の皆様には本規約に従って本サービスをご利用いただきます。', NULL, 1, NULL, NULL, NULL, '2026-05-03 06:50:51', '2026-05-03 06:50:51'),
(3, 'privacy', 'プライバシーポリシー', NULL, '一般社団法人 日本ナイトワーク適正化協会（以下「当協会」といいます）は、本サービスにおけるユーザーの個人情報の取扱いについて、以下のとおりプライバシーポリシー（以下「本ポリシー」といいます）を定めます。', NULL, 1, NULL, NULL, NULL, '2026-05-03 06:50:51', '2026-05-03 06:50:51');

-- --------------------------------------------------------

--
-- テーブルの構造 `policy_revisions`
--

CREATE TABLE `policy_revisions` (
  `id` bigint UNSIGNED NOT NULL,
  `policy_document_id` bigint UNSIGNED NOT NULL,
  `action` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'created / updated / locked / unlocked',
  `summary` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `snapshot` json DEFAULT NULL COMMENT '更新後スナップショット',
  `updated_by_id` bigint UNSIGNED DEFAULT NULL,
  `updated_by_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `policy_revisions`
--

INSERT INTO `policy_revisions` (`id`, `policy_document_id`, `action`, `summary`, `snapshot`, `updated_by_id`, `updated_by_name`, `created_at`) VALUES
(1, 1, 'updated', NULL, '{\"meta\": {\"address\": {\"label\": \"所在地\", \"value\": \"\"}, \"capital\": {\"label\": \"資本金\", \"value\": \"10,000,000円（準備金含む）\"}, \"business\": {\"label\": \"事業内容\", \"value\": \"ナイトワーク業界における人材サービスの適正化推進、各種システムの企画・運営\"}, \"org_name\": {\"label\": \"協会名\", \"value\": \"一般社団法人日本ナイトワーク適正化協会\"}, \"established_at\": {\"label\": \"設立年月日\", \"value\": \"\"}, \"representative\": {\"label\": \"理事長\", \"value\": \"森下翼\"}}, \"title\": \"運営協会\", \"chapters\": [], \"lead_body\": \"本ホームページにお越しいただき誠にありがとうございます。\\r\\n一般社団法人 日本ナイトワーク適正化協会の理事長を務めさせていただいております森下翼と申します。\\r\\n\\r\\n2019年から本格的に始まった新型コロナウイルス感染症（COVID-19）の影響で、ナイトワーク業界でも数々の店舗、それに準ずる全ての方々が現在も大打撃を受けながら日々不安な運用を行っております。\\r\\n\\r\\n私はナイトワークのコンサルタントして活動する中で数々の店舗関係者様から相談を受ける日々を過ごしていました。\\r\\n\\r\\n中でも店舗様からのコストカットに関するご相談が非常に多く、店舗の運営継続における「求人のコスト」には全国の店舗様は日々頭を抱えております。\\r\\n\\r\\n同時にキャストさんも店舗様が不景気になればおのずと業務委託契約を解消されてしまい手取りが減ってしまう悪循環が起きています。\\r\\n\\r\\nキャストさんを含めた良い人材を揃えるには求人広告はもちろん、近年路上での迷惑防止条例違反や反社会勢力との関わりもあるスカウト会社にも頼らなくては安定した人材供給ができない業界なのは周知の事実かと思います。\\r\\n\\r\\nそこで、当協会では店舗様にとってもキャストさんにとっても条件が良くなるシステムを開発いたしました。\\r\\n\\r\\n店舗様にとっては今までより安い求人コストを実現。キャスト様は本来店舗様からスカウト会社へ支払われる所謂「スカウトバック」を「入店準備金」として受け取れる仕組みになります。\\r\\n\\r\\nこの双方にメリットのあるシステムを全国に広める活動を通し、ナイトワークの活性化に繋がれるよう尽力している次第でございます。\\r\\n\\r\\n皆様のご協力で、ナイトワークに関わる全ての方が豊かになれるよう心から願っております。\\r\\n\\r\\n\\r\\n一般社団法人 日本ナイトワーク適正化協会\\r\\n理事長　森下翼\", \"lead_title\": \"GREETING / 理事長 挨拶\"}', 1, '管理者アカウント1', '2026-05-03 09:04:27'),
(2, 1, 'updated', NULL, '{\"meta\": {\"address\": {\"label\": \"所在地\", \"value\": \"\"}, \"capital\": {\"label\": \"資本金\", \"value\": \"10,000,000円（準備金含む）\"}, \"business\": {\"label\": \"事業内容\", \"value\": \"ナイトワーク業界における人材サービスの適正化推進、各種システムの企画・運営\"}, \"org_name\": {\"label\": \"協会名\", \"value\": \"一般社団法人日本ナイトワーク適正化協会\"}, \"established_at\": {\"label\": \"設立年月日\", \"value\": \"\"}, \"representative\": {\"label\": \"理事長\", \"value\": \"森下翼\"}}, \"title\": \"運営協会\", \"chapters\": [], \"lead_body\": \"本ホームページにお越しいただき誠にありがとうございます。\\r\\n一般社団法人 日本ナイトワーク適正化協会の理事長を務めさせていただいております森下翼と申します。\\r\\n\\r\\n2019年から本格的に始まった新型コロナウイルス感染症（COVID-19）の影響で、ナイトワーク業界でも数々の店舗、それに準ずる全ての方々が現在も大打撃を受けながら日々不安な運用を行っております。\\r\\n\\r\\n私はナイトワークのコンサルタントして活動する中で数々の店舗関係者様から相談を受ける日々を過ごしていました。\\r\\n\\r\\n中でも店舗様からのコストカットに関するご相談が非常に多く、店舗の運営継続における「求人のコスト」には全国の店舗様は日々頭を抱えております。\\r\\n\\r\\n同時にキャストさんも店舗様が不景気になればおのずと業務委託契約を解消されてしまい手取りが減ってしまう悪循環が起きています。\\r\\n\\r\\nキャストさんを含めた良い人材を揃えるには求人広告はもちろん、近年路上での迷惑防止条例違反や反社会勢力との関わりもあるスカウト会社にも頼らなくては安定した人材供給ができない業界なのは周知の事実かと思います。\\r\\n\\r\\nそこで、当協会では店舗様にとってもキャストさんにとっても条件が良くなるシステムを開発いたしました。\\r\\n\\r\\n店舗様にとっては今までより安い求人コストを実現。キャスト様は本来店舗様からスカウト会社へ支払われる所謂「スカウトバック」を「入店準備金」として受け取れる仕組みになります。\\r\\n\\r\\nこの双方にメリットのあるシステムを全国に広める活動を通し、ナイトワークの活性化に繋がれるよう尽力している次第でございます。\\r\\n\\r\\n皆様のご協力で、ナイトワークに関わる全ての方が豊かになれるよう心から願っております。\\r\\n\\r\\n\\r\\n一般社団法人 日本ナイトワーク適正化協会\\r\\n理事長　森下翼\", \"lead_title\": \"GREETING / 理事長 挨拶\"}', 1, '管理者アカウント1', '2026-05-03 10:20:01'),
(3, 1, 'updated', NULL, '{\"meta\": {\"address\": {\"label\": \"所在地\", \"value\": \"\"}, \"capital\": {\"label\": \"資本金\", \"value\": \"10,000,000円（準備金含む）\"}, \"business\": {\"label\": \"事業内容\", \"value\": \"ナイトワーク業界における人材サービスの適正化推進、各種システムの企画・運営\"}, \"org_name\": {\"label\": \"協会名\", \"value\": \"一般社団法人日本ナイトワーク適正化協会\"}, \"established_at\": {\"label\": \"設立年月日\", \"value\": \"\"}, \"representative\": {\"label\": \"理事長\", \"value\": \"森下翼\"}}, \"title\": \"運営協会\", \"chapters\": [], \"lead_body\": \"本ホームページにお越しいただき誠にありがとうございます。\\r\\n一般社団法人 日本ナイトワーク適正化協会の理事長を務めさせていただいております森下翼と申します。\\r\\n\\r\\n2019年から本格的に始まった新型コロナウイルス感染症（COVID-19）の影響で、ナイトワーク業界でも数々の店舗、それに準ずる全ての方々が現在も大打撃を受けながら日々不安な運用を行っております。\\r\\n\\r\\n私はナイトワークのコンサルタントして活動する中で数々の店舗関係者様から相談を受ける日々を過ごしていました。\\r\\n\\r\\n中でも店舗様からのコストカットに関するご相談が非常に多く、店舗の運営継続における「求人のコスト」には全国の店舗様は日々頭を抱えております。\\r\\n\\r\\n同時にキャストさんも店舗様が不景気になればおのずと業務委託契約を解消されてしまい手取りが減ってしまう悪循環が起きています。\\r\\n\\r\\nキャストさんを含めた良い人材を揃えるには求人広告はもちろん、近年路上での迷惑防止条例違反や反社会勢力との関わりもあるスカウト会社にも頼らなくては安定した人材供給ができない業界なのは周知の事実かと思います。\\r\\n\\r\\nそこで、当協会では店舗様にとってもキャストさんにとっても条件が良くなるシステムを開発いたしました。\\r\\n\\r\\n店舗様にとっては今までより安い求人コストを実現。キャスト様は本来店舗様からスカウト会社へ支払われる所謂「スカウトバック」を「入店準備金」として受け取れる仕組みになります。\\r\\n\\r\\nこの双方にメリットのあるシステムを全国に広める活動を通し、ナイトワークの活性化に繋がれるよう尽力している次第でございます。\\r\\n\\r\\n皆様のご協力で、ナイトワークに関わる全ての方が豊かになれるよう心から願っております。\\r\\n\\r\\n\\r\\n一般社団法人 日本ナイトワーク適正化協会\\r\\n\\r\\n理事長　森下翼\", \"lead_title\": \"GREETING / 理事長 挨拶\"}', 1, '管理者アカウント1', '2026-05-03 10:20:33');

-- --------------------------------------------------------

--
-- テーブルの構造 `push_subscriptions`
--

CREATE TABLE `push_subscriptions` (
  `id` bigint UNSIGNED NOT NULL,
  `user_type` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `endpoint` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `public_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auth_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `push_subscriptions`
--

INSERT INTO `push_subscriptions` (`id`, `user_type`, `user_id`, `endpoint`, `public_key`, `auth_token`, `user_agent`, `created_at`, `updated_at`) VALUES
(1, 'cast', 'c00000001', 'https://fcm.googleapis.com/fcm/send/fu1NB6hV-Iw:APA91bF7zaiWGh-qsw8Q84h8SLWj-St23_6VLhHlPzPlS_tFjiw88mMgBnECQ_PMEHVA1bfuQSkHze2kVqz0U90RCTBMghrdzrhhbANKbzlUYbEG4s8NspHS8uLygpqVQMSZnSkeeJu6', 'BDmabC4j5McY_bH1mqAL8Lre9in91vH6TBoNAOvLUz_UgP3mkMA41BX4iNQ5CxZgXmYjToDey2iseG-irKJQva4', 'BZ_Z1f4oskk7uiTRs5oY4Q', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-06 11:50:43', '2026-05-06 12:01:07'),
(4, 'cast', 'c00000002', 'https://fcm.googleapis.com/fcm/send/e0cMgB70HxY:APA91bFeof4xAzHM9v4znKbYhxc_lu3j6rBHmnZ29BITbAeuj1EtKy4JvUjN3dFAHdBTxylQU2uGYJq3Zu28Wv9IFWBQir3asNpPyCHqQlmUlzblRb0lrTtp6ISyN00Mpn9A2Xb9rL81', 'BFsz7kgrhq2XZ22R6WMrGV0FEF0Y-GVsnkDdi8yWclLONrkVv6lHdnQbZKMDYofgUh8ELeu6wW3areRin6xj1vI', 'nVsBRDYranijp5U7Lyb9Pg', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-06 12:24:05', '2026-05-06 12:24:05'),
(5, 'shop_manager', 'm00000001', 'https://fcm.googleapis.com/fcm/send/dsODydsrpdI:APA91bFS9nG3b_0CPpiIwByEiEW5ytYepK8LWBs8JiPNMxwpGhMEJpYXzL6V5gMvBecBqeCJ60qvomCbXF9hTbuuXIhTeMLvWeCkErZn7HyyAIrXKX9-SGJsngerGPo7IV18E0YvEla3', 'BBV_4ACJPXIdsUL2IUy3R00TDR0w7uBlv4kU-_E5l9TDSqKr_PhRXY80Lc48NTWrZC3nrjrbc9UZMfmkE26EvAw', 'tibwUse94zlKMD-muQcl9g', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36', '2026-05-08 06:53:36', '2026-05-08 06:53:36'),
(6, 'cast', 'c00000001', 'https://fcm.googleapis.com/fcm/send/fymej2Gr914:APA91bH5DwzLnrjCvJJ8Q-4D5tZD4rZz-5kwJczUdv4LZb8H1fIAKV2nzwwr_nwHky47GqZtg6nKMcy0jtkse-LRz92jEWGPblxOyfoAfsAwB27uZt4zuk75D-47qIknTZpfDwW3o64E', 'BDS7xC48K6hMDDTSxXCKb-bKfZaqejBGDqMgx06Y6Xj45RJGtKSDT6-TvVpr5ZdOrf1zjsQYBBB9p3MNnviUadI', 'CJcgUBn1OU8ZUmCkPamOmw', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-10 17:17:39', '2026-05-10 17:17:39'),
(7, 'cast', 'c00000001', 'https://fcm.googleapis.com/fcm/send/dHEaGD3IrZE:APA91bFEggi3NUoiaYQLJ6bGOBtvI4kZii1zWgdvvd7WojSoUzcJK-ah2w-se6E6XNNKK4_jW9p10LuoDrOxrMrkURz7zqIB1Vn_dgU_0orZ_OHMzH58PVyBQvI1GSQcmea5COGEx7CA', 'BA-zhWw7I46cHJVoWY7u4gkdXS7zDImk6UOgsMDzZ8q5S_dWN_j4i7bLgR74Axr6QVpRUVQaJhZtD-IGDVC88cE', 'XacgXPYIcN2F1sWBi6f3Xg', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-05-10 17:17:42', '2026-05-10 17:17:42');

-- --------------------------------------------------------

--
-- テーブルの構造 `reviews`
--

CREATE TABLE `reviews` (
  `id` bigint UNSIGNED NOT NULL,
  `cast_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contents` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `eva` decimal(3,1) NOT NULL DEFAULT '0.0',
  `is_anonymous` tinyint(1) NOT NULL DEFAULT '1',
  `release` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0:非公開, 1:公開',
  `reply_body` text COLLATE utf8mb4_unicode_ci COMMENT '店舗からの返信本文（1件のみ）',
  `reply_at` timestamp NULL DEFAULT NULL COMMENT '返信投稿日時',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `reviews`
--

INSERT INTO `reviews` (`id`, `cast_id`, `shop_id`, `contents`, `eva`, `is_anonymous`, `created_at`, `updated_at`) VALUES
(33, 'c00000003', 's00000002', '初回勤務でしたが、事前説明が丁寧で安心して働けました。', 4.0, 1, '2026-03-10 03:10:00', NULL),
(34, 'c00000001', 's00000002', '店内が清潔で、スタッフさんのフォローも早かったです。', 4.5, 0, '2026-03-11 09:20:00', NULL),
(35, 'c00000002', 's00000002', 'シフト相談に柔軟に対応していただき助かりました。', 5.0, 1, '2026-03-12 12:00:00', NULL),
(36, 'c00000003', 's00000001', '忙しい時間帯は少し大変でしたが、全体的には働きやすいです。', 3.5, 0, '2026-03-14 00:30:00', NULL),
(37, 'c00000001', 's00000001', '教育がしっかりしていて、未経験でも不安が少なかったです。', 4.0, 1, '2026-03-15 07:45:00', NULL),
(38, 'c00000002', 's00000001', 'お客様の雰囲気も良く、また勤務したいと思いました。', 4.5, 0, '2026-03-16 14:10:00', NULL);

-- --------------------------------------------------------

--
-- テーブルの構造 `review_contents`
--

CREATE TABLE `review_contents` (
  `id` bigint UNSIGNED NOT NULL,
  `content` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '設問内容',
  `del_flg` tinyint(1) NOT NULL DEFAULT '0' COMMENT '削除フラグ',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `review_contents`
--

INSERT INTO `review_contents` (`id`, `content`, `del_flg`, `created_at`, `updated_at`) VALUES
(1, '店内は清潔に保たれていますか？', 0, '2026-03-13 11:32:50', '2026-03-13 11:32:50'),
(2, 'スタッフの対応は親切ですか？', 0, '2026-03-13 11:32:50', '2026-03-13 11:32:50'),
(3, '店内の雰囲気はリラックスできますか？', 0, '2026-03-13 11:32:50', '2026-03-13 11:32:50'),
(4, '給料や待遇に満足していますか？', 0, '2026-03-13 11:32:50', '2026-03-13 11:32:50'),
(5, 'シフトの調整や融通が効きますか？', 0, '2026-03-13 11:32:50', '2026-03-13 11:32:50'),
(6, '来店するお客様の質に満足していますか？', 0, '2026-03-13 11:32:50', '2026-03-13 11:32:50'),
(7, '店舗内での安全性は確保されていますか？', 0, '2026-03-13 11:32:50', '2026-03-13 11:32:50'),
(8, '教育やサポート体制は十分ですか？', 0, '2026-03-13 11:32:50', '2026-03-13 11:32:50'),
(9, '店舗での働きやすさに満足していますか？', 0, '2026-03-13 11:32:50', '2026-03-13 11:32:50'),
(10, '店舗の立地条件は良いですか？', 0, '2026-03-13 11:32:50', '2026-03-13 11:32:50');

-- --------------------------------------------------------

--
-- テーブルの構造 `review_details`
--

CREATE TABLE `review_details` (
  `id` bigint UNSIGNED NOT NULL,
  `review_id` bigint UNSIGNED NOT NULL,
  `review_content_id` bigint UNSIGNED DEFAULT NULL,
  `val` bigint UNSIGNED NOT NULL,
  `score` decimal(3,1) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `review_details`
--

INSERT INTO `review_details` (`id`, `review_id`, `review_content_id`, `val`, `score`, `created_at`, `updated_at`) VALUES
(3301, 33, 1, 1, 4.0, '2026-03-10 03:15:00', '2026-03-10 03:15:00'),
(3302, 33, 2, 2, 4.0, '2026-03-10 03:15:00', '2026-03-10 03:15:00'),
(3303, 33, 3, 3, 3.5, '2026-03-10 03:15:00', '2026-03-10 03:15:00'),
(3304, 33, 8, 8, 4.5, '2026-03-10 03:15:00', '2026-03-10 03:15:00'),
(3305, 33, 9, 9, 4.0, '2026-03-10 03:15:00', '2026-03-10 03:15:00'),
(3401, 34, 1, 1, 5.0, '2026-03-11 09:25:00', '2026-03-11 09:25:00'),
(3402, 34, 2, 2, 4.5, '2026-03-11 09:25:00', '2026-03-11 09:25:00'),
(3403, 34, 3, 3, 4.0, '2026-03-11 09:25:00', '2026-03-11 09:25:00'),
(3404, 34, 7, 7, 4.5, '2026-03-11 09:25:00', '2026-03-11 09:25:00'),
(3405, 34, 10, 10, 4.5, '2026-03-11 09:25:00', '2026-03-11 09:25:00'),
(3501, 35, 1, 1, 5.0, '2026-03-12 12:05:00', '2026-03-12 12:05:00'),
(3502, 35, 2, 2, 5.0, '2026-03-12 12:05:00', '2026-03-12 12:05:00'),
(3503, 35, 4, 4, 5.0, '2026-03-12 12:05:00', '2026-03-12 12:05:00'),
(3504, 35, 5, 5, 4.5, '2026-03-12 12:05:00', '2026-03-12 12:05:00'),
(3505, 35, 9, 9, 5.0, '2026-03-12 12:05:00', '2026-03-12 12:05:00'),
(3601, 36, 2, 2, 3.5, '2026-03-14 00:35:00', '2026-03-14 00:35:00'),
(3602, 36, 3, 3, 3.5, '2026-03-14 00:35:00', '2026-03-14 00:35:00'),
(3603, 36, 4, 4, 3.0, '2026-03-14 00:35:00', '2026-03-14 00:35:00'),
(3604, 36, 6, 6, 3.5, '2026-03-14 00:35:00', '2026-03-14 00:35:00'),
(3605, 36, 9, 9, 4.0, '2026-03-14 00:35:00', '2026-03-14 00:35:00'),
(3701, 37, 2, 2, 4.0, '2026-03-15 07:50:00', '2026-03-15 07:50:00'),
(3702, 37, 5, 5, 4.0, '2026-03-15 07:50:00', '2026-03-15 07:50:00'),
(3703, 37, 7, 7, 4.0, '2026-03-15 07:50:00', '2026-03-15 07:50:00'),
(3704, 37, 8, 8, 4.5, '2026-03-15 07:50:00', '2026-03-15 07:50:00'),
(3705, 37, 9, 9, 4.0, '2026-03-15 07:50:00', '2026-03-15 07:50:00'),
(3801, 38, 1, 1, 4.5, '2026-03-16 14:15:00', '2026-03-16 14:15:00'),
(3802, 38, 3, 3, 4.5, '2026-03-16 14:15:00', '2026-03-16 14:15:00'),
(3803, 38, 6, 6, 4.0, '2026-03-16 14:15:00', '2026-03-16 14:15:00'),
(3804, 38, 9, 9, 4.5, '2026-03-16 14:15:00', '2026-03-16 14:15:00'),
(3805, 38, 10, 10, 5.0, '2026-03-16 14:15:00', '2026-03-16 14:15:00');

-- --------------------------------------------------------

--
-- テーブルの構造 `shops`
--

CREATE TABLE `shops` (
  `id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '主キー (s00000001~)',
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '0',
  `license_status` tinyint NOT NULL DEFAULT '1',
  `business_license_status` tinyint NOT NULL DEFAULT '1' COMMENT '営業許可証 (1:未提出, 2:未承認, 3:承認済)',
  `entertainment_license_status` tinyint NOT NULL DEFAULT '1' COMMENT '風営法許可証 (1:未提出, 2:未承認, 3:承認済)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `shops`
--

INSERT INTO `shops` (`id`, `email`, `status`, `license_status`, `business_license_status`, `entertainment_license_status`, `created_at`, `updated_at`, `deleted_at`) VALUES
('s00000001', 'info@club-luminous.example.com', 1, 2, 3, 1, NULL, '2026-05-16 16:42:47', NULL),
('s00000002', 'cute@mesechoku.jp', 1, 2, 1, 1, '2026-03-16 13:08:08', '2026-05-05 11:02:49', NULL),
('s00000003', 'test@testtest', 1, 3, 3, 3, '2026-05-10 14:14:14', '2026-05-10 14:24:47', NULL),
('s00000004', 'mearin0424@gmail.com', 1, 1, 1, 1, '2026-05-10 14:18:59', '2026-05-10 14:18:59', NULL);

-- --------------------------------------------------------

--
-- テーブルの構造 `shop_images`
--

CREATE TABLE `shop_images` (
  `id` bigint UNSIGNED NOT NULL,
  `shop_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` tinyint DEFAULT NULL,
  `is_main` tinyint(1) NOT NULL DEFAULT '0',
  `main_order` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `shop_images`
--

INSERT INTO `shop_images` (`id`, `shop_id`, `image_path`, `type`, `is_main`, `main_order`, `created_at`, `updated_at`) VALUES
(3, 's00000001', 'uploads/shops/gallery/kBwAjhPmK4Hskcaid1HonWaylFKjzTty4n1Qgk0u.png', NULL, 0, 1, '2026-03-13 06:33:03', '2026-05-04 08:40:19'),
(4, 's00000001', 'uploads/shops/gallery/wkSXye6MTxdNnXhVtmMNh9QjwneV71iSllc4SJcu.png', NULL, 1, 0, '2026-03-13 06:33:16', '2026-05-04 08:40:19'),
(6, 's00000002', 'uploads/shops/gallery/WyZG6VFz57hvcaf9PmyqxCOJt5uV53VTMPmo22uO.jpg', NULL, 1, 0, '2026-03-16 13:08:42', '2026-03-20 08:22:07'),
(7, 's00000002', 'uploads/shops/gallery/F0NfzVyayNcHKZW9YeAxvIAYEnEPZvNW2NBzkIgK.png', NULL, 0, 1, '2026-03-16 13:10:21', '2026-03-20 08:22:07'),
(8, 's00000001', 'uploads/shops/gallery/q7W5j1QBMm2OWQ9y6OrzNXgr1C3ynsqtf7wC8OdY.jpg', NULL, 0, 3, '2026-03-16 14:32:17', '2026-05-04 08:40:19'),
(13, 's00000001', 'uploads/shops/gallery/WisFRBX8DtdexJp49bUlPpOoonr261yNhQ8BxrQi.jpg', NULL, 0, 2, '2026-05-04 08:40:19', '2026-05-04 08:40:19'),
(14, 's00000003', 'uploads/shops/gallery/uNH7rj1AblNdPPZCv7i7ZTB6vEiPF4Bt08JzGOdG.jpg', NULL, 1, 0, '2026-05-10 14:14:23', '2026-05-10 14:14:23'),
(15, 's00000003', 'uploads/shops/gallery/7msdTlMTIwuplFvVoLwO3Ub5KWuJZk3IKkOp4S5V.jpg', NULL, 0, 1, '2026-05-10 14:15:26', '2026-05-10 14:15:26'),
(17, 's00000004', 'uploads/shops/gallery/ZjoW50WeRrXqJnYf5KIVW2aHJHmJkRTQL80nCrdE.jpg', NULL, 1, 0, '2026-05-10 14:19:06', '2026-05-10 14:19:06'),
(18, 's00000004', 'uploads/shops/gallery/3Ovlw8owuZ16JQgQzerHg1yZeHX4sCL9mFDW3nq6.jpg', NULL, 0, 1, '2026-05-10 14:19:39', '2026-05-10 14:19:39');

-- --------------------------------------------------------

--
-- テーブルの構造 `shop_jobs`
--

CREATE TABLE `shop_jobs` (
  `id` bigint UNSIGNED NOT NULL,
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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `shift_time_start` time DEFAULT NULL COMMENT '勤務開始時刻',
  `shift_time_end` time DEFAULT NULL COMMENT '勤務終了時刻',
  `shift_end_is_last` tinyint UNSIGNED NOT NULL DEFAULT '0' COMMENT '終了がLASTのとき1',
  `regular_hourly_wage_max` int UNSIGNED DEFAULT NULL COMMENT '本入時給上限（円）',
  `trial_hourly_wage_max` int UNSIGNED DEFAULT NULL COMMENT '体験入店時給上限（円）',
  `help_hourly_wage_max` int UNSIGNED DEFAULT NULL COMMENT 'ヘルプ時給上限（円）'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `shop_jobs`
--

INSERT INTO `shop_jobs` (`id`, `shop_id`, `pr`, `catch_copy`, `job_content`, `regular_status`, `regular_hourly_wage`, `norma_day`, `norma_hours`, `bonus_reward`, `bonus_remarks`, `bonus_condition`, `trial_hourly_wage`, `trial_status`, `has_help`, `help_hourly_wage`, `help_status`, `working_day`, `working_hours`, `regular_holiday`, `qualification`, `created_at`, `updated_at`, `deleted_at`, `shift_time_start`, `shift_time_end`, `shift_end_is_last`, `regular_hourly_wage_max`, `trial_hourly_wage_max`, `help_hourly_wage_max`) VALUES
(1, 's00000001', NULL, NULL, NULL, 1, '5000', 5, NULL, NULL, NULL, NULL, NULL, 1, 1, NULL, 1, NULL, NULL, NULL, NULL, NULL, '2026-05-02 11:29:02', NULL, NULL, NULL, 0, NULL, NULL, NULL),
(2, 's00000002', '店長からのメッセージ！', 'キャッチコピー！', 'お仕事内容の概要説明！', 0, '5000', 5, 30, 50000, 'ボーナス金の備考', 'ボーナス金の獲得条件', '3500', 0, 0, NULL, 1, '週1から', '15時から', '', '18歳以上（高校生不可）は当たり前', '2026-03-16 23:35:13', '2026-05-07 13:11:05', NULL, NULL, NULL, 0, NULL, NULL, NULL),
(9, 's00000003', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, '2026-05-10 14:14:23', '2026-05-10 14:26:19', NULL, NULL, NULL, 0, NULL, NULL, NULL),
(10, 's00000004', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, '2026-05-10 14:19:06', '2026-05-10 14:19:06', NULL, NULL, NULL, 0, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- テーブルの構造 `shop_job_applications`
--

CREATE TABLE `shop_job_applications` (
  `id` bigint UNSIGNED NOT NULL,
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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `talk_job_kind` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'fulltime|trial|help'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `shop_job_applications`
--

INSERT INTO `shop_job_applications` (`id`, `cast_id`, `shop_job_id`, `applied_regular_status`, `applied_regular_hourly_wage`, `applied_norma_day`, `applied_norma_hours`, `applied_bonus_reward`, `applied_bonus_remarks`, `applied_bonus_condition`, `applied_trial_hourly_wage`, `applied_trial_status`, `applied_has_help`, `applied_help_hourly_wage`, `applied_help_status`, `applied_working_day`, `applied_working_hours`, `applied_regular_holiday`, `applied_qualification`, `applied_shift_time_start`, `applied_shift_time_end`, `applied_shift_end_is_last`, `applied_regular_hourly_wage_max`, `applied_trial_hourly_wage_max`, `applied_help_hourly_wage_max`, `status`, `result_date`, `real_start_date`, `hourly_wage_regular`, `normal_time`, `hired_bonus_amount`, `hired_bonus_condition`, `reason_rejection`, `created_at`, `updated_at`, `talk_job_kind`) VALUES
(1, 'c00000001', 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 4, '2026-03-13', NULL, '5000', '5', 120000, NULL, NULL, '2026-03-13 13:31:14', '2026-03-13 16:28:54', 'fulltime'),
(15, 'c00000002', 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 5, '2026-05-03', NULL, '5000', NULL, NULL, NULL, '不採用理由', '2026-05-03 06:49:53', '2026-05-03 06:49:53', 'fulltime'),
(16, 'c00000003', 1, 1, '5000', 5, NULL, NULL, NULL, NULL, NULL, 1, 1, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 4, '2026-05-09', NULL, NULL, NULL, 0, '', NULL, '2026-05-06 10:03:13', '2026-05-06 15:51:05', 'help');

-- --------------------------------------------------------

--
-- テーブルの構造 `shop_job_tag_relations`
--

CREATE TABLE `shop_job_tag_relations` (
  `id` bigint UNSIGNED NOT NULL,
  `shop_job_id` bigint UNSIGNED NOT NULL COMMENT '求人ID (shop_jobs.id)',
  `tag_id` bigint UNSIGNED NOT NULL COMMENT 'shop_tags.id',
  `tag_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'work_style / welcome / benefit',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `shop_job_tag_relations`
--

INSERT INTO `shop_job_tag_relations` (`id`, `shop_job_id`, `tag_id`, `tag_type`, `created_at`, `updated_at`) VALUES
(3, 2, 1, 'work_style', '2026-03-16 23:35:13', '2026-03-16 23:35:13'),
(6, 2, 18, 'work_style', '2026-03-16 23:35:13', '2026-03-16 23:35:13'),
(9, 2, 32, 'work_style', '2026-03-16 23:35:13', '2026-03-16 23:35:13'),
(12, 2, 36, 'work_style', '2026-03-16 23:35:13', '2026-03-16 23:35:13'),
(15, 2, 149, 'benefit', '2026-03-16 23:35:13', '2026-03-16 23:35:13'),
(18, 2, 155, 'benefit', '2026-03-16 23:35:13', '2026-03-16 23:35:13'),
(21, 2, 115, 'welcome', '2026-03-16 23:35:13', '2026-03-16 23:35:13');

-- --------------------------------------------------------

--
-- テーブルの構造 `shop_license_documents`
--

CREATE TABLE `shop_license_documents` (
  `id` bigint UNSIGNED NOT NULL,
  `shop_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '種類(business:営業許可証, entertainment:風営法許可等)',
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0:アップロード済み, 1:審査中, 2:承認済み, 3:不備・却下',
  `ng_reason` text COLLATE utf8mb4_unicode_ci COMMENT '却下理由',
  `expired_at` date DEFAULT NULL COMMENT '有効期限',
  `approved_at` timestamp NULL DEFAULT NULL COMMENT '承認日時',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `shop_license_documents`
--

INSERT INTO `shop_license_documents` (`id`, `shop_id`, `type`, `image_path`, `status`, `ng_reason`, `expired_at`, `approved_at`, `created_at`, `updated_at`) VALUES
(1, 's00000001', 'business', 'shops/documents/uAHFIVMfGfcfsrSjZX41aHdsQvgIBHfuOLFIJAXk.pdf', 2, NULL, '2026-05-09', '2026-05-10 14:24:56', '2026-03-26 06:14:32', '2026-05-10 14:24:56'),
(2, 's00000001', 'entertainment', 'shops/documents/Ca5AYCP3lyIN4E1afPjMZKMSzapXDoLt2MyDJyke.png', 0, NULL, NULL, NULL, '2026-05-02 10:47:35', '2026-05-16 16:42:47'),
(3, 's00000002', 'business', 'shops/documents/xwcYGrqz5GTbC2iV7mZxrI3wrc0yNHL6jL2rEJjI.pdf', 0, NULL, '2030-05-10', NULL, '2026-05-05 11:02:31', '2026-05-05 11:02:49'),
(4, 's00000003', 'business', 'private/shops/documents/4OgPPHRJRtxqnAd3MlfkU8kwTEZDWT0vkcUizGkv.jpg', 2, NULL, '2026-07-31', '2026-05-10 14:24:47', '2026-05-10 14:24:12', '2026-05-10 14:24:47'),
(5, 's00000003', 'entertainment', 'private/shops/documents/GWLFtq9OVTPYPBkFPqsptud2Qt5J2gWzarQe0Mgs.jpg', 2, NULL, NULL, '2026-05-10 14:24:42', '2026-05-10 14:24:22', '2026-05-10 14:24:42');

-- --------------------------------------------------------

--
-- テーブルの構造 `shop_managers`
--

CREATE TABLE `shop_managers` (
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
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `shop_managers`
--

INSERT INTO `shop_managers` (`id`, `shop_id`, `name`, `email`, `password`, `role`, `status`, `last_login_at`, `created_at`, `updated_at`) VALUES
('m00000001', 's00000001', '佐藤 店長', 'sato.mgr@club-luminous.example.com', NULL, 1, 1, NULL, NULL, NULL),
('m00000002', 's00000002', '亀田寿郎', 'cute@mesechoku.jp', '$2y$10$ZegcFUoEKJNEaasyfc.8iOqMeUJl6Hfrtt/yC4weg5xZTraFPekiq', 1, 1, '2026-03-16 13:08:08', '2026-03-16 13:08:08', '2026-03-16 13:08:08'),
('m00000003', 's00000003', '溝口奈緒子', 'test@testtest', '$2y$10$Ck5NR9.7stKnF8cyYLyFvO.4mlMbzmS4F2s8ZxqgjLpTNt3uAhdOC', 1, 1, '2026-05-10 14:14:23', '2026-05-10 14:14:23', '2026-05-10 14:14:23'),
('m00000004', 's00000004', '久保田洋介', 'mearin0424@gmail.com', '$2y$10$NigfipseWkW2vmuUYJ3CkuWkjtBbVg8AeKOBnJaQpgZH1AsuUnuCW', 1, 1, '2026-05-10 14:19:06', '2026-05-10 14:19:06', '2026-05-10 14:19:06');

-- --------------------------------------------------------

--
-- テーブルの構造 `notifications`
-- ユーザー宛おしらせ（インボックス）
--

CREATE TABLE `notifications` (
  `id` bigint UNSIGNED NOT NULL,
  `user_type` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'cast / shop_manager / admin',
  `user_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '機能キー (例: talk.message_received)',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` json DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL COMMENT '既読時刻 (NULL=未読)',
  `dispatched_push_at` timestamp NULL DEFAULT NULL,
  `dispatched_line_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `shop_posts`
--

CREATE TABLE `shop_posts` (
  `id` bigint UNSIGNED NOT NULL,
  `shop_id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci COMMENT '店舗のひとこと',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `shop_posts`
--

INSERT INTO `shop_posts` (`id`, `shop_id`, `body`, `created_at`, `updated_at`) VALUES
(1, 's00000001', '今から入れます', '2026-05-02 16:50:58', '2026-05-02 16:50:58'),
(2, 's00000002', '西葛西にて新規オープンです！', '2026-05-02 13:39:44', '2026-03-20 08:22:07'),
(10, 's00000003', '可愛い女の子たくさん！', '2026-05-10 14:14:23', '2026-05-10 14:14:23'),
(11, 's00000004', '場末です！', '2026-05-10 14:19:06', '2026-05-10 14:19:06');

-- --------------------------------------------------------

--
-- テーブルの構造 `shop_profiles`
--

CREATE TABLE `shop_profiles` (
  `id` bigint UNSIGNED NOT NULL,
  `industry_id` bigint UNSIGNED DEFAULT NULL COMMENT '業種ID (industries)',
  `industry_label` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '表示用の業種名（フリーテキスト）',
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
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `shop_profiles`
--

INSERT INTO `shop_profiles` (`id`, `industry_id`, `industry_label`, `shop_id`, `shop_name`, `zip`, `pref`, `city`, `addr`, `building`, `tel`, `open_time`, `close_is_last`, `close_time`, `latitude`, `longitude`, `available_until`, `available_declared_at`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 's00000001', 'Club Luminous (ルミナス)', '103-0016', '東京都', '中央区', '日本橋小網町', 'ヂューエ日本橋 101', '+817099999999', '12:00:00', 1, NULL, 35.6826780, 139.7807160, NULL, NULL, '2026-05-06 15:15:40', '2026-05-06 15:15:40'),
(2, NULL, NULL, 's00000002', 'CUTE', '134-0088', '東京都', '江戸川区', '西葛西', NULL, '07012345678', NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-03-16 13:08:08', '2026-03-20 08:22:07'),
(8, 1, NULL, 's00000003', 'スナック奈緒子', '140-0014', '東京都', '品川区', '大井', '２８－３ＤｕｏＣｏｕｒｔ大井１０１号　室', '0356743525', NULL, 0, NULL, 35.6058540, 139.7325590, NULL, NULL, '2026-05-10 14:14:14', '2026-05-10 14:14:23'),
(9, 1, NULL, 's00000004', 'USA', '192-0046', '東京都', '八王子市', '明神町', NULL, '99999999', NULL, 0, NULL, 35.6583670, 139.3493350, NULL, NULL, '2026-05-10 14:18:59', '2026-05-10 14:19:06');

-- --------------------------------------------------------

--
-- テーブルの構造 `shop_search_preferences`
--

CREATE TABLE `shop_search_preferences` (
  `id` bigint UNSIGNED NOT NULL,
  `shop_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `max_distance_km` smallint DEFAULT NULL COMMENT '0=制限なし、>0 で半径 km',
  `age_min` tinyint UNSIGNED DEFAULT NULL,
  `age_max` tinyint UNSIGNED DEFAULT NULL,
  `shift_frequency` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `work_periods` json DEFAULT NULL,
  `looks_tag_ids` json DEFAULT NULL,
  `personality_tag_ids` json DEFAULT NULL,
  `night_work_exp` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'none / yes / any',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `shop_stations`
--

CREATE TABLE `shop_stations` (
  `id` bigint UNSIGNED NOT NULL,
  `shop_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `station_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `shop_stations`
--

INSERT INTO `shop_stations` (`id`, `shop_id`, `station_name`, `sort_order`, `created_at`, `updated_at`) VALUES
(6, 's00000001', '茅場町駅 徒歩5分', 0, '2026-05-06 15:15:40', '2026-05-06 15:15:40'),
(8, 's00000001', '水天宮前駅 徒歩6分', 2, '2026-05-06 15:15:40', '2026-05-06 15:15:40'),
(15, 's00000003', '大井町駅 徒歩3分', 0, '2026-05-10 14:14:23', '2026-05-10 14:14:23'),
(16, 's00000003', '大井町駅 徒歩4分', 1, '2026-05-10 14:14:23', '2026-05-10 14:14:23'),
(17, 's00000003', '大井町駅 徒歩4分', 2, '2026-05-10 14:14:23', '2026-05-10 14:14:23'),
(18, 's00000004', '京王八王子駅 徒歩7分', 0, '2026-05-10 14:19:06', '2026-05-10 14:19:06'),
(19, 's00000004', '八王子駅 徒歩13分', 1, '2026-05-10 14:19:06', '2026-05-10 14:19:06'),
(20, 's00000004', '八王子駅 徒歩13分', 2, '2026-05-10 14:19:06', '2026-05-10 14:19:06');

-- --------------------------------------------------------

--
-- テーブルの構造 `shop_tags`
--

CREATE TABLE `shop_tags` (
  `id` bigint UNSIGNED NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'work_style / atmosphere / facility / welcome / benefit',
  `target` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'shop' COMMENT 'shop: 店舗Profile用 / job: 求人票用',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `del_flg` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `shop_tags`
--

INSERT INTO `shop_tags` (`id`, `category`, `target`, `name`, `del_flg`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'work_style', 'job', '1ヶ月払い', 0, 1, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(2, 'work_style', 'job', '15日払い', 0, 2, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(3, 'work_style', 'job', '10日払い', 0, 3, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(4, 'work_style', 'job', '1週間払い', 0, 4, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(5, 'work_style', 'job', '翌日払い', 0, 5, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(6, 'work_style', 'job', '全額日払い', 0, 6, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(7, 'work_style', 'job', '日払い可', 0, 7, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(8, 'work_style', 'job', '交通費支給', 0, 8, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(9, 'work_style', 'job', '高額時給', 0, 9, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(10, 'work_style', 'job', '高額バック支給', 0, 10, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(11, 'work_style', 'job', '入店祝い金支給', 0, 11, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(12, 'work_style', 'job', '給料手渡し', 0, 12, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(13, 'work_style', 'job', '給料UP', 0, 13, '2026-03-13 02:32:51', '2026-03-13 02:32:51'),
(14, 'work_style', 'job', '売上バック有り', 0, 14, '2026-03-13 02:32:52', '2026-03-13 02:32:52'),
(15, 'work_style', 'job', '同伴バック有り', 0, 15, '2026-03-13 02:32:52', '2026-03-13 02:32:52'),
(16, 'work_style', 'job', 'シャンパンバック有り', 0, 16, '2026-03-13 02:32:52', '2026-03-13 02:32:52'),
(17, 'work_style', 'job', 'その他バック有り', 0, 17, '2026-03-13 02:32:52', '2026-03-13 02:32:52'),
(18, 'work_style', 'job', 'ドリンクバック', 0, 18, '2026-03-13 02:32:52', '2026-03-13 02:32:52'),
(19, 'work_style', 'job', '指名バック', 0, 19, '2026-03-13 02:32:52', '2026-03-13 02:32:52'),
(32, 'work_style', 'job', '週1からOK', 0, 1, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(33, 'work_style', 'job', '短期OK', 0, 2, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(34, 'work_style', 'job', '1日1h以内', 0, 3, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(35, 'work_style', 'job', '1日2h以内', 0, 4, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(36, 'work_style', 'job', '1日3h以内', 0, 5, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(37, 'work_style', 'job', '1日4h以内', 0, 6, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(38, 'work_style', 'job', '未経験者歓迎', 0, 7, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(39, 'work_style', 'job', '出稼ぎOK', 0, 8, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(40, 'work_style', 'job', '終電上がりOK', 0, 9, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(41, 'work_style', 'job', 'WワークOK', 0, 10, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(47, 'work_style', 'job', '登録制有り', 0, 3, '2026-03-13 02:32:52', '2026-03-13 02:32:52'),
(48, 'work_style', 'job', '何回か体入OK', 0, 4, '2026-03-13 02:32:52', '2026-03-13 02:32:52'),
(49, 'work_style', 'job', '早上げ無し', 0, 12, '2026-03-13 02:32:52', '2026-03-13 02:32:52'),
(50, 'work_style', 'job', '早上がり有り', 0, 13, '2026-03-13 02:32:52', '2026-03-13 02:32:52'),
(54, 'atmosphere', 'shop', 'わいわい', 0, 1, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(55, 'atmosphere', 'shop', 'しっとり', 0, 2, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(56, 'atmosphere', 'shop', 'おっとり', 0, 3, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(57, 'atmosphere', 'shop', 'アットホーム', 0, 4, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(58, 'atmosphere', 'shop', '大型店', 0, 5, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(59, 'atmosphere', 'shop', '中型店', 0, 6, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(60, 'atmosphere', 'shop', '小さいお店', 0, 7, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(61, 'atmosphere', 'shop', '高級店', 0, 8, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(62, 'atmosphere', 'shop', '大衆店', 0, 9, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(63, 'atmosphere', 'shop', 'キャスト多数', 0, 10, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(64, 'atmosphere', 'shop', '少人数', 0, 11, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(65, 'atmosphere', 'shop', '上下関係無し', 0, 12, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(66, 'atmosphere', 'shop', '派閥無し', 0, 13, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(67, 'atmosphere', 'shop', '新規オープン', 0, 14, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(68, 'atmosphere', 'shop', 'リニューアルオープン', 0, 15, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(69, 'atmosphere', 'shop', 'ステージ有り', 0, 16, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(70, 'atmosphere', 'shop', 'カウンターのみ', 0, 17, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(71, 'atmosphere', 'shop', 'カラオケ有り', 0, 18, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(72, 'atmosphere', 'shop', 'カラオケ無し', 0, 19, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(73, 'atmosphere', 'shop', 'VIPルール完備', 0, 20, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(86, 'atmosphere', 'shop', '日曜営業', 0, 3, '2026-03-13 02:32:52', '2026-03-13 02:32:52'),
(87, 'atmosphere', 'shop', '定休日無し', 0, 4, '2026-03-13 02:32:52', '2026-03-13 02:32:52'),
(89, 'facility', 'shop', '駐車場有り', 0, 1, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(90, 'facility', 'shop', '車通勤OK', 0, 2, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(91, 'facility', 'shop', '寮有り', 0, 3, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(92, 'facility', 'shop', '即日入居可寮有り', 0, 4, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(93, 'facility', 'shop', '託児所有り', 0, 5, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(94, 'facility', 'shop', '個人ロッカー有り', 0, 6, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(95, 'facility', 'shop', 'キャスト専用トイレ有り', 0, 7, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(96, 'facility', 'shop', 'ステージ有り', 0, 3, '2026-03-13 02:32:52', '2026-03-13 02:32:52'),
(97, 'facility', 'shop', 'カウンターのみ', 0, 4, '2026-03-13 02:32:52', '2026-03-13 02:32:52'),
(98, 'facility', 'shop', 'カラオケ有り', 0, 5, '2026-03-13 02:32:52', '2026-03-13 02:32:52'),
(99, 'facility', 'shop', 'カラオケ無し', 0, 6, '2026-03-13 02:32:52', '2026-03-13 02:32:52'),
(100, 'facility', 'shop', 'VIPルール完備', 0, 7, '2026-03-13 02:32:52', '2026-03-13 02:32:52'),
(101, 'facility', 'shop', '禁煙店', 0, 8, '2026-03-13 02:32:52', '2026-03-13 02:32:52'),
(102, 'facility', 'shop', 'コロナ対策実施', 0, 9, '2026-03-13 02:32:52', '2026-03-13 02:32:52'),
(103, 'facility', 'shop', '送り有り', 0, 1, '2026-03-13 02:32:52', '2026-03-13 02:32:52'),
(104, 'facility', 'shop', '迎え有り', 0, 2, '2026-03-13 02:32:52', '2026-03-13 02:32:52'),
(105, 'facility', 'shop', '駅からスグ', 0, 5, '2026-03-13 02:32:52', '2026-03-13 02:32:52'),
(106, 'facility', 'shop', '駅徒歩5分', 0, 6, '2026-03-13 02:32:52', '2026-03-13 02:32:52'),
(107, 'facility', 'shop', '駅徒歩10分', 0, 7, '2026-03-13 02:32:52', '2026-03-13 02:32:52'),
(115, 'welcome', 'job', '未経験', 0, 1, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(116, 'welcome', 'job', 'シングルマザーOK', 0, 2, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(117, 'welcome', 'job', '経験者優遇', 0, 3, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(118, 'welcome', 'job', '学生歓迎', 0, 4, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(119, 'welcome', 'job', '主婦歓迎', 0, 5, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(120, 'welcome', 'job', 'ブランク歓迎', 0, 6, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(121, 'welcome', 'job', 'お酒NG歓迎', 0, 7, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(122, 'welcome', 'job', 'ニューオープン', 0, 8, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(123, 'welcome', 'job', '登録制有り', 0, 9, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(124, 'welcome', 'job', '日曜営業', 0, 10, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(125, 'welcome', 'job', '10代歓迎', 0, 11, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(126, 'welcome', 'job', '30代歓迎', 0, 12, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(127, 'welcome', 'job', '40代歓迎', 0, 13, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(128, 'welcome', 'job', '50代歓迎', 0, 14, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(129, 'welcome', 'job', 'コロナ対策実施', 0, 15, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(130, 'welcome', 'job', 'タトゥーOK', 0, 16, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(131, 'welcome', 'job', '禁煙店', 0, 17, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(132, 'welcome', 'job', '定休日無し', 0, 18, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(133, 'welcome', 'job', 'ぽっちゃりOK', 0, 19, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(134, 'welcome', 'job', '何回か体入OK', 0, 20, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(146, 'welcome', 'job', '未経験者歓迎', 0, 1, '2026-03-13 02:32:52', '2026-03-13 02:32:52'),
(149, 'benefit', 'job', 'レンタル衣装有り', 0, 1, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(150, 'benefit', 'job', '服装自由', 0, 2, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(151, 'benefit', 'job', 'ヘアメイク有り', 0, 3, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(152, 'benefit', 'job', 'ヘアメイク無料', 0, 4, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(153, 'benefit', 'job', 'ヘアメイク不要', 0, 5, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(154, 'benefit', 'job', '髪型自由', 0, 6, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(155, 'benefit', 'job', '小物レンタル無料', 0, 7, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(156, 'benefit', 'job', 'レンタルドレス', 0, 8, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(157, 'benefit', 'job', 'レンタル衣装無料', 0, 9, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(158, 'benefit', 'job', '手ぶらで体入OK', 0, 10, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(159, 'benefit', 'job', '送り有り', 0, 11, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(160, 'benefit', 'job', '迎え有り', 0, 12, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(161, 'benefit', 'job', '駅からスグ', 0, 13, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(162, 'benefit', 'job', '早上げ無し', 0, 14, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(163, 'benefit', 'job', 'ノルマ無し', 0, 15, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(164, 'benefit', 'job', '福利厚生・提携先割引有り', 0, 16, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(165, 'benefit', 'job', '早上がり有り', 0, 17, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(166, 'benefit', 'job', 'ドリンクバック', 0, 18, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(167, 'benefit', 'job', '指名バック', 0, 19, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(168, 'benefit', 'job', 'ヘアメイク提携割引有り', 0, 20, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(169, 'benefit', 'job', '託児用提携割引有り', 0, 21, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(170, 'benefit', 'job', '衣装割引き有り', 0, 22, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(171, 'benefit', 'job', '駅徒歩5分', 0, 23, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(172, 'benefit', 'job', '駅徒歩10分', 0, 24, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(173, 'benefit', 'job', '売上バック有り', 0, 25, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(174, 'benefit', 'job', '同伴バック有り', 0, 26, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(175, 'benefit', 'job', 'シャンパンバック有り', 0, 27, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(176, 'benefit', 'job', 'その他バック有り', 0, 28, '2026-03-13 11:32:52', '2026-03-13 11:32:52'),
(180, 'benefit', 'job', '寮有り', 0, 11, '2026-03-13 02:32:52', '2026-03-13 02:32:52'),
(181, 'benefit', 'job', '即日入居可寮有り', 0, 12, '2026-03-13 02:32:52', '2026-03-13 02:32:52'),
(182, 'benefit', 'job', '託児所有り', 0, 13, '2026-03-13 02:32:52', '2026-03-13 02:32:52');

-- --------------------------------------------------------

--
-- テーブルの構造 `shop_tag_relations`
--

CREATE TABLE `shop_tag_relations` (
  `id` bigint UNSIGNED NOT NULL,
  `shop_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '店舗ID',
  `tag_id` bigint UNSIGNED NOT NULL COMMENT '各種タグID',
  `tag_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'タグの種類 (例: atmospheres, facilities, salaryなど)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `shop_tag_relations`
--

INSERT INTO `shop_tag_relations` (`id`, `shop_id`, `tag_id`, `tag_type`, `created_at`, `updated_at`) VALUES
(8, 's00000002', 54, 'atmosphere', '2026-03-16 23:35:13', '2026-03-16 23:35:13'),
(9, 's00000002', 66, 'atmosphere', '2026-03-16 23:35:13', '2026-03-16 23:35:13'),
(33, 's00000001', 54, 'atmosphere', '2026-05-06 15:15:40', '2026-05-06 15:15:40'),
(34, 's00000001', 67, 'atmosphere', '2026-05-06 15:15:40', '2026-05-06 15:15:40'),
(35, 's00000001', 69, 'atmosphere', '2026-05-06 15:15:40', '2026-05-06 15:15:40'),
(36, 's00000001', 98, 'facility', '2026-05-06 15:15:40', '2026-05-06 15:15:40'),
(37, 's00000001', 99, 'facility', '2026-05-06 15:15:40', '2026-05-06 15:15:40'),
(38, 's00000001', 103, 'facility', '2026-05-06 15:15:40', '2026-05-06 15:15:40'),
(39, 's00000001', 105, 'facility', '2026-05-06 15:15:40', '2026-05-06 15:15:40');

-- --------------------------------------------------------

--
-- テーブルの構造 `system_accounts`
--

CREATE TABLE `system_accounts` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '管理者名',
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ログインメールアドレス',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ハッシュ化パスワード',
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'staff' COMMENT '権限(admin:全機能, staff:一部機能)',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '有効フラグ(falseでログイン不可)',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `system_accounts`
--

INSERT INTO `system_accounts` (`id`, `name`, `email`, `email_verified_at`, `password`, `role`, `is_active`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, '管理者アカウント1', 'admin@misechoku.jp', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, NULL, '2026-03-15 14:49:44', '2026-03-15 14:49:44');

-- --------------------------------------------------------

--
-- テーブルの構造 `talk_blocks`
--

CREATE TABLE `talk_blocks` (
  `id` bigint UNSIGNED NOT NULL,
  `cast_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `blocked_by` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'cast or shop',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `user_talk_templates`
--

CREATE TABLE `user_talk_templates` (
  `id` bigint UNSIGNED NOT NULL,
  `owner_type` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'cast または shop',
  `owner_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'その他',
  `title` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `talk_quick_reply_templates`
-- 運営管理のステータス別クイック定型文マスタ (owner_type x status_code)
--

CREATE TABLE `talk_quick_reply_templates` (
  `id` bigint UNSIGNED NOT NULL,
  `owner_type` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'cast / shop',
  `status_code` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'chatting / interview_pending / interview_fixed / hired / rejected',
  `category` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'thanks / schedule / question / status / intro / help',
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `talk_quick_reply_templates`
--   SPEC.md §2.3-2.4 のフローに沿ってステータス別に配置
--

INSERT INTO `talk_quick_reply_templates` (`id`, `owner_type`, `status_code`, `category`, `body`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'cast', 'chatting', 'intro', 'はじめまして。求人を拝見してご連絡いたしました。ぜひ詳しくお伺いできますと幸いです。', 1, 1, NOW(), NOW()),
(2, 'cast', 'chatting', 'intro', 'プロフィールをご覧いただきありがとうございます。前向きに検討したく、ご連絡いたしました。', 2, 1, NOW(), NOW()),
(3, 'cast', 'chatting', 'question', 'お店の雰囲気やお客様層について教えてください。', 3, 1, NOW(), NOW()),
(4, 'cast', 'chatting', 'question', '時給・バック率・体入時の条件について詳しく知りたいです。', 4, 1, NOW(), NOW()),
(5, 'cast', 'chatting', 'question', '出勤可能なシフトや最低出勤本数はどれくらいでしょうか？', 5, 1, NOW(), NOW()),
(6, 'cast', 'chatting', 'question', '未経験ですが、安心して働ける環境でしょうか？', 6, 1, NOW(), NOW()),
(7, 'cast', 'chatting', 'schedule', 'ぜひ一度、体入または面談をお願いしたいです。ご都合はいかがでしょうか？', 7, 1, NOW(), NOW()),
(8, 'cast', 'interview_pending', 'thanks', '面談候補日をお送りいただきありがとうございます。確認してすぐご返信いたします。', 1, 1, NOW(), NOW()),
(9, 'cast', 'interview_pending', 'schedule', 'ご提示いただいた第一希望の日程で問題ございません。当日よろしくお願いいたします。', 2, 1, NOW(), NOW()),
(10, 'cast', 'interview_pending', 'schedule', '申し訳ございません、いただいた日程は都合が合わないため、別日をご提案いただけますでしょうか。', 3, 1, NOW(), NOW()),
(11, 'cast', 'interview_pending', 'question', '面談はどれくらいのお時間を予定していますか？', 4, 1, NOW(), NOW()),
(12, 'cast', 'interview_pending', 'question', '面談は対面／オンラインどちらをご希望でしょうか？', 5, 1, NOW(), NOW()),
(13, 'cast', 'interview_pending', 'question', '当日の持ち物や服装の指定があれば事前に教えてください。', 6, 1, NOW(), NOW()),
(14, 'cast', 'interview_fixed', 'thanks', '面談当日、よろしくお願いいたします！', 1, 1, NOW(), NOW()),
(15, 'cast', 'interview_fixed', 'question', '当日の持ち物・服装の指定があれば教えてください。', 2, 1, NOW(), NOW()),
(16, 'cast', 'interview_fixed', 'question', '店舗までの詳しいアクセスを教えていただけますでしょうか。', 3, 1, NOW(), NOW()),
(17, 'cast', 'interview_fixed', 'question', '当日はどなたをお訪ねすればよいですか？', 4, 1, NOW(), NOW()),
(18, 'cast', 'interview_fixed', 'status', '大変申し訳ございません、少し遅れそうです。到着次第ご連絡いたします。', 5, 1, NOW(), NOW()),
(19, 'cast', 'interview_fixed', 'status', '到着いたしました。入口はどちらでしょうか？', 6, 1, NOW(), NOW()),
(20, 'cast', 'interview_fixed', 'schedule', '大変恐縮ですが、体調不良のため面談日程を再調整させていただけますでしょうか。', 7, 1, NOW(), NOW()),
(21, 'cast', 'hired', 'thanks', 'この度は採用いただきありがとうございます！精一杯頑張ります。', 1, 1, NOW(), NOW()),
(22, 'cast', 'hired', 'schedule', '初出勤日についてご相談させてください。', 2, 1, NOW(), NOW()),
(23, 'cast', 'hired', 'question', '初出勤当日の集合時間・持ち物・服装を教えてください。', 3, 1, NOW(), NOW()),
(24, 'cast', 'hired', 'question', '入店時の手続きで必要な書類はありますか？', 4, 1, NOW(), NOW()),
(25, 'cast', 'hired', 'status', '本日勤務完了いたしました。ありがとうございました。', 5, 1, NOW(), NOW()),
(26, 'cast', 'hired', 'schedule', 'ぜひ本入店で継続させていただきたいです。ご検討いただけますでしょうか。', 6, 1, NOW(), NOW()),
(27, 'cast', 'hired', 'schedule', 'ボーナス条件を達成いたしましたので、ご確認とご承認をお願いいたします。', 7, 1, NOW(), NOW()),
(28, 'cast', 'hired', 'thanks', 'ご入金の確認が取れました。この度はありがとうございました。', 8, 1, NOW(), NOW()),
(29, 'cast', 'rejected', 'thanks', 'この度はご連絡いただきありがとうございました。', 1, 1, NOW(), NOW()),
(30, 'cast', 'rejected', 'thanks', 'またご縁がありましたら、ぜひよろしくお願いいたします。', 2, 1, NOW(), NOW()),
(31, 'shop', 'chatting', 'thanks', 'この度はご応募（お問い合わせ）ありがとうございます。当店にご興味を持っていただき嬉しく思います。', 1, 1, NOW(), NOW()),
(32, 'shop', 'chatting', 'intro', 'ご返信ありがとうございます。ご不明な点があればお気軽にご質問くださいませ。', 2, 1, NOW(), NOW()),
(33, 'shop', 'chatting', 'question', '差し支えなければ、勤務開始のご希望時期や週の出勤可能日数を教えていただけますか？', 3, 1, NOW(), NOW()),
(34, 'shop', 'chatting', 'question', 'これまでのご経験や現在の在籍状況について教えていただけますでしょうか。', 4, 1, NOW(), NOW()),
(35, 'shop', 'chatting', 'schedule', 'ぜひ一度、面談または体入にお越しいただきたく思います。候補日をお送りしましょうか？', 5, 1, NOW(), NOW()),
(36, 'shop', 'chatting', 'intro', 'プロフィール拝見しました。ぜひ一度お話しできれば嬉しいです。', 6, 1, NOW(), NOW()),
(37, 'shop', 'chatting', 'help', '「今すぐ入れる」宣言を拝見しました。本日◯時から◯時まで、ヘルプでお願いできませんか？', 7, 1, NOW(), NOW()),
(38, 'shop', 'chatting', 'help', '急遽ピンチヒッターを探しております。ご対応可能でしたら折り返しお願いいたします！', 8, 1, NOW(), NOW()),
(39, 'shop', 'interview_pending', 'schedule', '面談の候補日をお送りしました。ご都合はいかがでしょうか？', 1, 1, NOW(), NOW()),
(40, 'shop', 'interview_pending', 'schedule', 'ご都合の良い日程があれば追加でお気軽にお知らせください。', 2, 1, NOW(), NOW()),
(41, 'shop', 'interview_pending', 'schedule', '日程が合わない場合は改めて候補日をお送りいたします。', 3, 1, NOW(), NOW()),
(42, 'shop', 'interview_pending', 'question', '面談は対面／オンラインどちらをご希望ですか？', 4, 1, NOW(), NOW()),
(43, 'shop', 'interview_pending', 'question', '当日の所要時間は30分〜1時間程度を予定しております。', 5, 1, NOW(), NOW()),
(44, 'shop', 'interview_pending', 'question', 'ご不明点があればお気軽にご質問ください。', 6, 1, NOW(), NOW()),
(45, 'shop', 'interview_fixed', 'status', '面談当日、お待ちしております！', 1, 1, NOW(), NOW()),
(46, 'shop', 'interview_fixed', 'status', 'お気をつけてお越しくださいませ。', 2, 1, NOW(), NOW()),
(47, 'shop', 'interview_fixed', 'status', '当日は私服でお越しいただいて大丈夫です。', 3, 1, NOW(), NOW()),
(48, 'shop', 'interview_fixed', 'status', '到着されましたらこのトークでお知らせください。', 4, 1, NOW(), NOW()),
(49, 'shop', 'interview_fixed', 'question', '当日は身分証（顔写真付き）と印鑑をお持ちください。', 5, 1, NOW(), NOW()),
(50, 'shop', 'interview_fixed', 'question', '店舗までのアクセス情報をお送りします。ご不明な点があればご連絡ください。', 6, 1, NOW(), NOW()),
(51, 'shop', 'interview_fixed', 'schedule', '大変申し訳ございません、店舗都合により日程の再調整をお願いできますでしょうか。', 7, 1, NOW(), NOW()),
(52, 'shop', 'hired', 'thanks', 'この度は採用となりました！おめでとうございます。これからよろしくお願いいたします。', 1, 1, NOW(), NOW()),
(53, 'shop', 'hired', 'schedule', '初出勤日について改めてご案内いたします。ご都合の良い日程を教えてください。', 2, 1, NOW(), NOW()),
(54, 'shop', 'hired', 'question', '当日の集合時間・持ち物・服装のご案内です。ご確認をお願いします。', 3, 1, NOW(), NOW()),
(55, 'shop', 'hired', 'status', '本日はお疲れさまでした！ありがとうございました。', 4, 1, NOW(), NOW()),
(56, 'shop', 'hired', 'thanks', 'ボーナス達成条件の確認が取れました。承認処理を進めさせていただきます。', 5, 1, NOW(), NOW()),
(57, 'shop', 'hired', 'status', 'ご請求内容を確認しました。承認いたしましたので、運営からの請求書発行をお待ちください。', 6, 1, NOW(), NOW()),
(58, 'shop', 'hired', 'schedule', 'ぜひ本入店で継続をご検討いただけますと嬉しいです。', 7, 1, NOW(), NOW()),
(59, 'shop', 'hired', 'status', 'ご不明点があればいつでもご連絡ください。', 8, 1, NOW(), NOW()),
(60, 'shop', 'rejected', 'thanks', 'この度はご応募いただきありがとうございました。', 1, 1, NOW(), NOW()),
(61, 'shop', 'rejected', 'thanks', 'またのご縁がありましたら、ぜひよろしくお願いいたします。', 2, 1, NOW(), NOW());

-- --------------------------------------------------------

--
-- テーブルの構造 `support_inquiries`
-- サポート問い合わせフォーム送信内容
--

CREATE TABLE `support_inquiries` (
  `id` bigint UNSIGNED NOT NULL,
  `sender_type` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'cast / shop / guest',
  `sender_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'account / feature / bug / feedback / other',
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new' COMMENT 'new / in_progress / resolved / dismissed',
  `assigned_admin_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `responded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- ダンプしたテーブルのインデックス
--

--
-- テーブルのインデックス `application_deposits`
--
ALTER TABLE `application_deposits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `app_dep_app_id_foreign` (`shop_job_application_id`);

--
-- テーブルのインデックス `application_deposit_histories`
--
ALTER TABLE `application_deposit_histories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `app_dep_hist_dep_id_foreign` (`application_deposit_id`);

--
-- テーブルのインデックス `bank_accounts`
--
ALTER TABLE `bank_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_holder` (`holder_type`,`holder_id`),
  ADD KEY `idx_holder` (`holder_type`,`holder_id`);

--
-- テーブルのインデックス `casts`
--
ALTER TABLE `casts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `casts_email_unique` (`email`);

--
-- テーブルのインデックス `cast_identity_documents`
--
ALTER TABLE `cast_identity_documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cast_identity_documents_cast_category_unique` (`cast_id`,`category`),
  ADD KEY `cast_id` (`cast_id`);

--
-- テーブルのインデックス `cast_images`
--
ALTER TABLE `cast_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cast_images_cast_id` (`cast_id`),
  ADD KEY `idx_cast_images_cast_id_type` (`cast_id`,`type`);

--
-- テーブルのインデックス `cast_posts`
--
ALTER TABLE `cast_posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cast_posts_cast_id_foreign` (`cast_id`);

--
-- テーブルのインデックス `cast_profiles`
--
ALTER TABLE `cast_profiles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cast_profiles_cast_id_foreign` (`cast_id`),
  ADD KEY `fk_cast_profiles_industry` (`industry_id`);

--
-- テーブルのインデックス `cast_providers`
--
ALTER TABLE `cast_providers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cast_providers_provider_id_unique` (`provider`,`provider_id`),
  ADD KEY `cast_providers_cast_id_foreign` (`cast_id`);

--
-- テーブルのインデックス `cast_search_preferences`
--
ALTER TABLE `cast_search_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cast_search_preferences_cast` (`cast_id`);

--
-- テーブルのインデックス `cast_shop_relation`
--
ALTER TABLE `cast_shop_relation`
  ADD PRIMARY KEY (`cast_id`,`shop_id`),
  ADD KEY `c_s_rel_shop_id_foreign` (`shop_id`);

--
-- テーブルのインデックス `cast_tags`
--
ALTER TABLE `cast_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cast_tag` (`category`,`name`);

--
-- テーブルのインデックス `cast_tag_relations`
--
ALTER TABLE `cast_tag_relations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cast_tag` (`cast_id`,`tag_id`,`tag_type`);

--
-- テーブルのインデックス `character_guide_settings`
--
ALTER TABLE `character_guide_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `character_guide_settings_route_name_unique` (`route_name`);

--
-- テーブルのインデックス `column_articles`
--
ALTER TABLE `column_articles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `column_articles_slug_unique` (`slug`),
  ADD KEY `column_articles_column_category_id_index` (`column_category_id`);

--
-- テーブルのインデックス `column_categories`
--
ALTER TABLE `column_categories`
  ADD PRIMARY KEY (`id`);

--
-- テーブルのインデックス `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- テーブルのインデックス `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `favorites_unique_per_pair_action_sender` (`cast_id`,`shop_id`,`action_type`,`sender_type`),
  ADD KEY `favorites_shop_id_foreign` (`shop_id`);

--
-- テーブルのインデックス `footprints`
--
ALTER TABLE `footprints`
  ADD PRIMARY KEY (`id`),
  ADD KEY `footprints_cast_id_foreign` (`cast_id`),
  ADD KEY `footprints_shop_id_foreign` (`shop_id`);

--
-- テーブルのインデックス `profile_views`
--
ALTER TABLE `profile_views`
  ADD PRIMARY KEY (`id`),
  ADD KEY `profile_views_target` (`target_type`,`target_id`);

--
-- テーブルのインデックス `industries`
--
ALTER TABLE `industries`
  ADD PRIMARY KEY (`id`);

--
-- テーブルのインデックス `invoice_template_settings`
--
ALTER TABLE `invoice_template_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_template_settings_key_unique` (`key`);

--
-- テーブルのインデックス `keeps`
--
ALTER TABLE `keeps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `keeps_cast_id_foreign` (`cast_id`),
  ADD KEY `keeps_shop_id_foreign` (`shop_id`);

--
-- テーブルのインデックス `line_messages`
--
ALTER TABLE `line_messages`
  ADD PRIMARY KEY (`id`);

--
-- テーブルのインデックス `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `messages_cast_id_foreign` (`cast_id`),
  ADD KEY `messages_shop_id_foreign` (`shop_id`);

--
-- テーブルのインデックス `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- テーブルのインデックス `ng_words`
--
ALTER TABLE `ng_words`
  ADD PRIMARY KEY (`id`);

--
-- テーブルのインデックス `notices`
--
ALTER TABLE `notices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `notices_slug_unique` (`slug`);

--
-- テーブルのインデックス `notification_preferences`
--
ALTER TABLE `notification_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `notification_preferences_user_unique` (`user_type`,`user_id`);

--
-- テーブルのインデックス `admin_notification_settings`
--
ALTER TABLE `admin_notification_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `admin_notification_settings_type_key_unique` (`type`,`key`);

--
-- テーブルのインデックス `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- テーブルのインデックス `user_reports`
--
ALTER TABLE `user_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_reports_target_idx` (`target_type`, `target_id`),
  ADD KEY `user_reports_status_idx` (`status`, `created_at`);
ALTER TABLE `user_reports`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- テーブルのインデックス `payment_tasks`
--
ALTER TABLE `payment_tasks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_tasks_application_deposit_id_unique` (`application_deposit_id`),
  ADD KEY `payment_tasks_application_deposit_id_foreign` (`application_deposit_id`);

--
-- テーブルのインデックス `policy_chapters`
--
ALTER TABLE `policy_chapters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `policy_chapters_doc_sort_index` (`policy_document_id`,`sort_order`);

--
-- テーブルのインデックス `policy_documents`
--
ALTER TABLE `policy_documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `policy_documents_key_unique` (`key`);

--
-- テーブルのインデックス `policy_revisions`
--
ALTER TABLE `policy_revisions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `policy_revisions_doc_created_index` (`policy_document_id`,`created_at`);

--
-- テーブルのインデックス `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `push_subscriptions_endpoint_unique` (`endpoint`),
  ADD KEY `push_subscriptions_user_idx` (`user_type`,`user_id`);

--
-- テーブルのインデックス `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reviews_cast_id_foreign` (`cast_id`),
  ADD KEY `reviews_shop_id_foreign` (`shop_id`);

--
-- テーブルのインデックス `review_contents`
--
ALTER TABLE `review_contents`
  ADD PRIMARY KEY (`id`);

--
-- テーブルのインデックス `review_details`
--
ALTER TABLE `review_details`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_review_details_review_id_val` (`review_id`,`val`),
  ADD KEY `idx_review_details_val` (`val`),
  ADD KEY `review_details_review_content_id_foreign` (`review_content_id`);

--
-- テーブルのインデックス `shops`
--
ALTER TABLE `shops`
  ADD PRIMARY KEY (`id`);

--
-- テーブルのインデックス `shop_images`
--
ALTER TABLE `shop_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shop_images_shop_id` (`shop_id`);

--
-- テーブルのインデックス `shop_jobs`
--
ALTER TABLE `shop_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_shop_jobs_shop_id` (`shop_id`);

--
-- テーブルのインデックス `shop_job_applications`
--
ALTER TABLE `shop_job_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `s_j_app_cast_id_foreign` (`cast_id`),
  ADD KEY `s_j_app_job_id_foreign` (`shop_job_id`);

--
-- テーブルのインデックス `shop_job_tag_relations`
--
ALTER TABLE `shop_job_tag_relations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_job_tag` (`shop_job_id`,`tag_id`,`tag_type`),
  ADD KEY `fk_sj_tag_rel_tag` (`tag_id`);

--
-- テーブルのインデックス `shop_license_documents`
--
ALTER TABLE `shop_license_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `shop_id` (`shop_id`),
  ADD KEY `idx_shop_license_documents_expired_at` (`expired_at`);

--
-- テーブルのインデックス `shop_managers`
--
ALTER TABLE `shop_managers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `shop_managers_email_unique` (`email`),
  ADD KEY `shop_managers_shop_id_foreign` (`shop_id`);

--
-- テーブルのインデックス `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notifications_user_idx` (`user_type`, `user_id`, `read_at`),
  ADD KEY `notifications_created_idx` (`created_at`);

--
-- AUTO_INCREMENT for `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- テーブルのインデックス `shop_posts`
--
ALTER TABLE `shop_posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `shop_posts_shop_id_unique` (`shop_id`);

--
-- テーブルのインデックス `shop_profiles`
--
ALTER TABLE `shop_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_shop_profiles_shop_id` (`shop_id`),
  ADD KEY `fk_shop_profiles_industry` (`industry_id`);

--
-- テーブルのインデックス `shop_search_preferences`
--
ALTER TABLE `shop_search_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_shop_search_preferences_shop` (`shop_id`);

--
-- テーブルのインデックス `shop_stations`
--
ALTER TABLE `shop_stations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shop_stations_shop_id` (`shop_id`);

--
-- テーブルのインデックス `shop_tags`
--
ALTER TABLE `shop_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_shop_tag` (`category`,`name`);

--
-- テーブルのインデックス `shop_tag_relations`
--
ALTER TABLE `shop_tag_relations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_shop_tag` (`shop_id`,`tag_id`,`tag_type`);

--
-- テーブルのインデックス `system_accounts`
--
ALTER TABLE `system_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_email` (`email`);

--
-- テーブルのインデックス `talk_blocks`
--
ALTER TABLE `talk_blocks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `talk_blocks_cast_id_shop_id_unique` (`cast_id`,`shop_id`),
  ADD KEY `talk_blocks_shop_id_index` (`shop_id`);

--
-- テーブルのインデックス `user_talk_templates`
--
ALTER TABLE `user_talk_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_talk_templates_owner_idx` (`owner_type`,`owner_id`,`sort_order`);

--
-- テーブルのインデックス `talk_quick_reply_templates`
--
ALTER TABLE `talk_quick_reply_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tqrt_lookup_idx` (`owner_type`,`status_code`,`is_active`,`sort_order`);

--
-- テーブルのインデックス `support_inquiries`
--
ALTER TABLE `support_inquiries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `support_inquiries_status_idx` (`status`,`created_at`),
  ADD KEY `support_inquiries_sender_idx` (`sender_type`,`sender_id`),
  ADD KEY `support_inquiries_category_idx` (`category`);

--
-- ダンプしたテーブルの AUTO_INCREMENT
--

--
-- テーブルの AUTO_INCREMENT `application_deposits`
--
ALTER TABLE `application_deposits`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- テーブルの AUTO_INCREMENT `application_deposit_histories`
--
ALTER TABLE `application_deposit_histories`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- テーブルの AUTO_INCREMENT `bank_accounts`
--
ALTER TABLE `bank_accounts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- テーブルの AUTO_INCREMENT `cast_identity_documents`
--
ALTER TABLE `cast_identity_documents`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- テーブルの AUTO_INCREMENT `cast_images`
--
ALTER TABLE `cast_images`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- テーブルの AUTO_INCREMENT `cast_posts`
--
ALTER TABLE `cast_posts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- テーブルの AUTO_INCREMENT `cast_profiles`
--
ALTER TABLE `cast_profiles`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- テーブルの AUTO_INCREMENT `cast_providers`
--
ALTER TABLE `cast_providers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- テーブルの AUTO_INCREMENT `cast_search_preferences`
--
ALTER TABLE `cast_search_preferences`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `cast_tags`
--
ALTER TABLE `cast_tags`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- テーブルの AUTO_INCREMENT `cast_tag_relations`
--
ALTER TABLE `cast_tag_relations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- テーブルの AUTO_INCREMENT `character_guide_settings`
--
ALTER TABLE `character_guide_settings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- テーブルの AUTO_INCREMENT `column_articles`
--
ALTER TABLE `column_articles`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- テーブルの AUTO_INCREMENT `column_categories`
--
ALTER TABLE `column_categories`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- テーブルの AUTO_INCREMENT `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- テーブルの AUTO_INCREMENT `footprints`
--
ALTER TABLE `footprints`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `profile_views`
--
ALTER TABLE `profile_views`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `industries`
--
ALTER TABLE `industries`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- テーブルの AUTO_INCREMENT `invoice_template_settings`
--
ALTER TABLE `invoice_template_settings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `keeps`
--
ALTER TABLE `keeps`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `line_messages`
--
ALTER TABLE `line_messages`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `messages`
--
ALTER TABLE `messages`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- テーブルの AUTO_INCREMENT `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `ng_words`
--
ALTER TABLE `ng_words`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- テーブルの AUTO_INCREMENT `notices`
--
ALTER TABLE `notices`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- テーブルの AUTO_INCREMENT `notification_preferences`
--
ALTER TABLE `notification_preferences`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- テーブルの AUTO_INCREMENT `admin_notification_settings`
--
ALTER TABLE `admin_notification_settings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `payment_tasks`
--
ALTER TABLE `payment_tasks`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- テーブルの AUTO_INCREMENT `policy_chapters`
--
ALTER TABLE `policy_chapters`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- テーブルの AUTO_INCREMENT `policy_documents`
--
ALTER TABLE `policy_documents`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- テーブルの AUTO_INCREMENT `policy_revisions`
--
ALTER TABLE `policy_revisions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- テーブルの AUTO_INCREMENT `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- テーブルの AUTO_INCREMENT `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- テーブルの AUTO_INCREMENT `review_contents`
--
ALTER TABLE `review_contents`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- テーブルの AUTO_INCREMENT `review_details`
--
ALTER TABLE `review_details`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3806;

--
-- テーブルの AUTO_INCREMENT `shop_images`
--
ALTER TABLE `shop_images`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- テーブルの AUTO_INCREMENT `shop_jobs`
--
ALTER TABLE `shop_jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- テーブルの AUTO_INCREMENT `shop_job_applications`
--
ALTER TABLE `shop_job_applications`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- テーブルの AUTO_INCREMENT `shop_job_tag_relations`
--
ALTER TABLE `shop_job_tag_relations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- テーブルの AUTO_INCREMENT `shop_license_documents`
--
ALTER TABLE `shop_license_documents`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- テーブルの AUTO_INCREMENT `shop_posts`
--
ALTER TABLE `shop_posts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- テーブルの AUTO_INCREMENT `shop_profiles`
--
ALTER TABLE `shop_profiles`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- テーブルの AUTO_INCREMENT `shop_search_preferences`
--
ALTER TABLE `shop_search_preferences`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `shop_stations`
--
ALTER TABLE `shop_stations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- テーブルの AUTO_INCREMENT `shop_tags`
--
ALTER TABLE `shop_tags`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=188;

--
-- テーブルの AUTO_INCREMENT `shop_tag_relations`
--
ALTER TABLE `shop_tag_relations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- テーブルの AUTO_INCREMENT `system_accounts`
--
ALTER TABLE `system_accounts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- テーブルの AUTO_INCREMENT `talk_blocks`
--
ALTER TABLE `talk_blocks`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- テーブルの AUTO_INCREMENT `user_talk_templates`
--
ALTER TABLE `user_talk_templates`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `talk_quick_reply_templates`
--
ALTER TABLE `talk_quick_reply_templates`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- テーブルの AUTO_INCREMENT `support_inquiries`
--
ALTER TABLE `support_inquiries`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- ビュー用の構造 `managers`
--
DROP TABLE IF EXISTS `managers`;

CREATE ALGORITHM=UNDEFINED DEFINER=`risadmin`@`%` SQL SECURITY DEFINER VIEW `managers`  AS SELECT `shop_managers`.`id` AS `id`, `shop_managers`.`shop_id` AS `shop_id`, `shop_managers`.`name` AS `name`, `shop_managers`.`email` AS `email`, `shop_managers`.`password` AS `password`, `shop_managers`.`role` AS `role`, `shop_managers`.`status` AS `status`, `shop_managers`.`last_login_at` AS `last_login_at`, `shop_managers`.`created_at` AS `created_at`, `shop_managers`.`updated_at` AS `updated_at`, `shop_profiles`.`shop_name` AS `shop_name` FROM (`shop_managers` left join `shop_profiles` on((`shop_managers`.`shop_id` = `shop_profiles`.`shop_id`))) ;

-- --------------------------------------------------------

--
-- ビュー用の構造 `members`
--
DROP TABLE IF EXISTS `members`;

CREATE ALGORITHM=UNDEFINED DEFINER=`risadmin`@`%` SQL SECURITY DEFINER VIEW `members`  AS SELECT `casts`.`id` AS `id`, `casts`.`email` AS `email`, `casts`.`password` AS `password`, `casts`.`status` AS `status`, `casts`.`status` AS `approval`, `casts`.`identity_status` AS `identity_status`, `casts`.`last_login_at` AS `last_login_at`, `casts`.`remember_token` AS `remember_token`, `casts`.`created_at` AS `created_at`, `casts`.`updated_at` AS `updated_at`, `casts`.`deleted_at` AS `deleted_at`, (case when (`casts`.`deleted_at` is null) then 0 else 1 end) AS `del_flg`, `cast_profiles`.`nickname` AS `nickname`, `cast_profiles`.`name` AS `name`, `cast_profiles`.`name_kana` AS `kana`, `cast_profiles`.`birthday` AS `birthday`, year(`cast_profiles`.`birthday`) AS `birthday_y`, month(`cast_profiles`.`birthday`) AS `birthday_m`, dayofmonth(`cast_profiles`.`birthday`) AS `birthday_d`, `cast_profiles`.`gender` AS `gender`, `cast_profiles`.`zip` AS `zip`, `cast_profiles`.`pref` AS `pref`, `cast_profiles`.`city` AS `city`, `cast_profiles`.`addr1` AS `addr1`, `cast_profiles`.`addr2` AS `addr2`, `cast_profiles`.`addr3` AS `addr3`, `cast_profiles`.`tel` AS `tel`, `cast_profiles`.`height` AS `height`, `cast_profiles`.`weight` AS `weight`, `cast_profiles`.`bust` AS `b`, `cast_profiles`.`waist` AS `w`, `cast_profiles`.`hip` AS `h`, `cast_profiles`.`shift` AS `shift`, `cast_profiles`.`profession` AS `profession`, `cast_profiles`.`exp` AS `exp`, `cast_profiles`.`years_exp` AS `years_exp`, `cast_profiles`.`where_work` AS `where_work`, `cast_profiles`.`pr` AS `pr`, `cast_profiles`.`charm_point` AS `charm_point`, `cast_profiles`.`memo` AS `memo`, `cast_profiles`.`ng_reason` AS `ng_reason`, `cast_profiles`.`latitude` AS `latitude`, `cast_profiles`.`longitude` AS `longitude`, (select `cp`.`provider_id` from `cast_providers` `cp` where ((`cp`.`cast_id` = `casts`.`id`) and (`cp`.`provider` = 'line')) limit 1) AS `line_user_id`, 0 AS `matching`, 0 AS `release`, 0 AS `footprints`, NULL AS `shop_name` FROM (`casts` left join `cast_profiles` on((`casts`.`id` = `cast_profiles`.`cast_id`))) ;

--
-- ダンプしたテーブルの制約
--

--
-- テーブルの制約 `application_deposits`
--
ALTER TABLE `application_deposits`
  ADD CONSTRAINT `app_dep_app_id_foreign` FOREIGN KEY (`shop_job_application_id`) REFERENCES `shop_job_applications` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `application_deposit_histories`
--
ALTER TABLE `application_deposit_histories`
  ADD CONSTRAINT `app_dep_hist_dep_id_foreign` FOREIGN KEY (`application_deposit_id`) REFERENCES `application_deposits` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `cast_images`
--
ALTER TABLE `cast_images`
  ADD CONSTRAINT `cast_images_cast_id_foreign` FOREIGN KEY (`cast_id`) REFERENCES `casts` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `cast_posts`
--
ALTER TABLE `cast_posts`
  ADD CONSTRAINT `cast_posts_cast_id_foreign` FOREIGN KEY (`cast_id`) REFERENCES `casts` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `cast_profiles`
--
ALTER TABLE `cast_profiles`
  ADD CONSTRAINT `cast_profiles_cast_id_foreign` FOREIGN KEY (`cast_id`) REFERENCES `casts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cast_profiles_industry` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`) ON DELETE SET NULL;

--
-- テーブルの制約 `cast_providers`
--
ALTER TABLE `cast_providers`
  ADD CONSTRAINT `cast_providers_cast_id_foreign` FOREIGN KEY (`cast_id`) REFERENCES `casts` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `cast_shop_relation`
--
ALTER TABLE `cast_shop_relation`
  ADD CONSTRAINT `c_s_rel_cast_id_foreign` FOREIGN KEY (`cast_id`) REFERENCES `casts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `c_s_rel_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `cast_tag_relations`
--
ALTER TABLE `cast_tag_relations`
  ADD CONSTRAINT `fk_cast_tag_relations_cast` FOREIGN KEY (`cast_id`) REFERENCES `casts` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `column_articles`
--
ALTER TABLE `column_articles`
  ADD CONSTRAINT `column_articles_column_category_id_foreign` FOREIGN KEY (`column_category_id`) REFERENCES `column_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- テーブルの制約 `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_cast_id_foreign` FOREIGN KEY (`cast_id`) REFERENCES `casts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `footprints`
--
ALTER TABLE `footprints`
  ADD CONSTRAINT `footprints_cast_id_foreign` FOREIGN KEY (`cast_id`) REFERENCES `casts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `footprints_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `keeps`
--
ALTER TABLE `keeps`
  ADD CONSTRAINT `keeps_cast_id_foreign` FOREIGN KEY (`cast_id`) REFERENCES `casts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `keeps_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_cast_id_foreign` FOREIGN KEY (`cast_id`) REFERENCES `casts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `payment_tasks`
--
ALTER TABLE `payment_tasks`
  ADD CONSTRAINT `payment_tasks_application_deposit_id_foreign` FOREIGN KEY (`application_deposit_id`) REFERENCES `application_deposits` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `policy_chapters`
--
ALTER TABLE `policy_chapters`
  ADD CONSTRAINT `policy_chapters_policy_document_id_foreign` FOREIGN KEY (`policy_document_id`) REFERENCES `policy_documents` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `policy_revisions`
--
ALTER TABLE `policy_revisions`
  ADD CONSTRAINT `policy_revisions_policy_document_id_foreign` FOREIGN KEY (`policy_document_id`) REFERENCES `policy_documents` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_cast_id_foreign` FOREIGN KEY (`cast_id`) REFERENCES `casts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `review_details`
--
ALTER TABLE `review_details`
  ADD CONSTRAINT `fk_review_details_review_id` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_review_details_val` FOREIGN KEY (`val`) REFERENCES `review_contents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `review_details_review_content_id_foreign` FOREIGN KEY (`review_content_id`) REFERENCES `review_contents` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `shop_images`
--
ALTER TABLE `shop_images`
  ADD CONSTRAINT `shop_images_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `shop_jobs`
--
ALTER TABLE `shop_jobs`
  ADD CONSTRAINT `shop_jobs_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `shop_job_applications`
--
ALTER TABLE `shop_job_applications`
  ADD CONSTRAINT `s_j_app_cast_id_foreign` FOREIGN KEY (`cast_id`) REFERENCES `casts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `s_j_app_job_id_foreign` FOREIGN KEY (`shop_job_id`) REFERENCES `shop_jobs` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `shop_job_tag_relations`
--
ALTER TABLE `shop_job_tag_relations`
  ADD CONSTRAINT `fk_sj_tag_rel_job` FOREIGN KEY (`shop_job_id`) REFERENCES `shop_jobs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sj_tag_rel_tag` FOREIGN KEY (`tag_id`) REFERENCES `shop_tags` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `shop_managers`
--
ALTER TABLE `shop_managers`
  ADD CONSTRAINT `shop_managers_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `shop_posts`
--
ALTER TABLE `shop_posts`
  ADD CONSTRAINT `shop_posts_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `shop_profiles`
--
ALTER TABLE `shop_profiles`
  ADD CONSTRAINT `fk_shop_profiles_industry` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `shop_profiles_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `shop_stations`
--
ALTER TABLE `shop_stations`
  ADD CONSTRAINT `fk_shop_stations_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `shop_tag_relations`
--
ALTER TABLE `shop_tag_relations`
  ADD CONSTRAINT `fk_shop_tag_relations_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `talk_blocks`
--
ALTER TABLE `talk_blocks`
  ADD CONSTRAINT `talk_blocks_cast_id_foreign` FOREIGN KEY (`cast_id`) REFERENCES `casts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `talk_blocks_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- --------------------------------------------------------

--
-- テーブルの構造 `shop_plan_subscriptions`（店舗Premiumプラン契約）
--   振込 + 運営の目視確認で有効化。1=入金待ち 2=有効 3=期間満了 4=キャンセル
--

CREATE TABLE IF NOT EXISTS `shop_plan_subscriptions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `plan` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'premium',
  `billing_cycle` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'monthly / yearly',
  `amount` int UNSIGNED NOT NULL COMMENT '請求額（税込円） monthly=20000 / yearly=200000',
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

--
-- ai_suggestion_templates : shop recruit editor "ランダム" auto-fill pool
--   category = 'catch_copy' | 'manager_message'
--

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

INSERT INTO `ai_suggestion_templates` (`category`, `body`, `is_enabled`, `created_at`, `updated_at`) VALUES
('catch_copy', '未経験でも安心！高時給5,000円スタート', 1, NOW(), NOW()),
('catch_copy', '働きやすさ抜群！シフト自由・当日でもOK', 1, NOW(), NOW()),
('catch_copy', '初心者歓迎♪ノルマなし・ペナルティなし', 1, NOW(), NOW()),
('catch_copy', '駅チカ徒歩3分！終電までのラクラク勤務', 1, NOW(), NOW()),
('catch_copy', '週1日〜OK！副業・Wワーク大歓迎', 1, NOW(), NOW()),
('catch_copy', 'アットホームな雰囲気で楽しく働けるお店', 1, NOW(), NOW()),
('catch_copy', '体入時給6,000円！日払い・全額バック', 1, NOW(), NOW()),
('catch_copy', '20代〜40代活躍中！年齢不問の優良店', 1, NOW(), NOW()),
('manager_message', '当店では未経験の方も安心して働けるよう、先輩キャストと店長が丁寧にサポートします。無理なノルマや厳しい罰金は一切ありません。まずは気軽に体験入店からどうぞ。', 1, NOW(), NOW()),
('manager_message', '駅から徒歩3分の好立地、終電までの勤務OKで通勤しやすさが自慢のお店です。シフトの融通も利くので、学業や家庭との両立を目指す方にもおすすめです。', 1, NOW(), NOW()),
('manager_message', 'アットホームな雰囲気で、キャスト同士の仲もとても良い当店。困ったときはすぐに相談できる環境なので、初めての夜職でも安心して長く働けます。', 1, NOW(), NOW()),
('manager_message', '当店は接客マナー・シャンパンコールなどの研修体制を整えているため、水商売未経験の方でも短期間で自信を持って接客できるようになります。まずは面接でお話ししましょう。', 1, NOW(), NOW()),
('manager_message', '週1日・4時間からのシフトOK。副業やWワークで働きたい方も大歓迎です。プライベート優先で無理なく続けられる働き方を一緒に考えます。', 1, NOW(), NOW()),
('manager_message', '20代〜40代まで幅広く活躍中。年齢や経験を問わず、あなたの魅力を活かせるステージがあります。まずは体験入店で当店の雰囲気を感じてください。', 1, NOW(), NOW());

--
-- 追加ダンプ `character_guide_settings`（深い階層ページの説明文集約 2026-07-19）
--

INSERT INTO `character_guide_settings` (`id`, `route_name`, `screen_label`, `is_enabled`, `message`, `created_at`, `updated_at`) VALUES
(10, 'cast.mypage.management', 'キャスト：採用・入金管理', 1, '採用が決まったお店のボーナス申請から、振込完了までの進み具合をここで確認・操作できるよ！', '2026-07-19 00:00:00', '2026-07-19 00:00:00'),
(11, 'shop.mypage.management', '店舗：採用・入金管理', 1, '採用が確定したキャストのボーナス申請から、入金・振込完了までの進み具合をここで確認・操作できます。', '2026-07-19 00:00:00', '2026-07-19 00:00:00'),
(12, 'cast.mypage.identity', 'キャスト：本人確認', 1, '安心して利用してもらうために、本人確認書類の提出をお願いしているよ。運営の承認が完了すると、すべての機能が使えるようになるよ！', '2026-07-19 00:00:00', '2026-07-19 00:00:00'),
(13, 'shop.mypage.documents.index', '店舗：許可証の提出・管理', 1, '掲載に必要な書類の提出状況です。すべて承認されると求人票を公開できます。', '2026-07-19 00:00:00', '2026-07-19 00:00:00'),
(14, 'setting.account', '共通：アカウント管理', 1, 'メールアドレス変更・パスワード変更・LINE連携・退会手続きはここからできるよ。', '2026-07-19 00:00:00', '2026-07-19 00:00:00'),
(15, 'setting.notification', '共通：通知設定', 1, 'ミセチョクからのリマインダー通知の受け取り方法を設定できるよ。', '2026-07-19 00:00:00', '2026-07-19 00:00:00'),
(16, 'subscription', '共通：プラン設定（店舗）', 1, 'Premiumプランの申し込み・支払い状況はここで確認できます。お支払いは銀行振込で、運営が入金を確認すると自動で機能が有効になります。', '2026-07-19 00:00:00', '2026-07-19 00:00:00'),
(17, 'support.form', '共通：問い合わせ窓口', 1, '質問・要望・不具合の連絡はここから送ってね。記入したメールアドレス宛に運営から返信するよ。', '2026-07-19 00:00:00', '2026-07-19 00:00:00'),
(18, 'support.column', '共通：お役立ちコラム（未ログイン）', 1, 'ミセチョクの使い方や活用のヒントをまとめたコラムだよ。', '2026-07-19 00:00:00', '2026-07-19 00:00:00'),
(19, 'cast.column.index', 'キャスト：お役立ちコラム', 1, 'ミセチョクの使い方や活用のヒントをまとめたコラムだよ。', '2026-07-19 00:00:00', '2026-07-19 00:00:00'),
(20, 'shop.column.index', '店舗：お役立ちコラム', 1, 'ミセチョクの使い方や活用のヒントをまとめたコラムです。', '2026-07-19 00:00:00', '2026-07-19 00:00:00'),
(21, 'support.notices', '共通：お知らせ', 1, '運営からのお知らせはここでチェックしてね。', '2026-07-19 00:00:00', '2026-07-19 00:00:00'),
(22, 'cast.htu', 'キャスト：ご利用ガイド', 1, '初めての方向けに、基本的な使い方をステップ形式でまとめたよ！', '2026-07-19 00:00:00', '2026-07-19 00:00:00'),
(23, 'shop.htu', '店舗：ご利用ガイド', 1, '初めての店舗さま向けに、基本的な使い方をステップ形式でまとめました。', '2026-07-19 00:00:00', '2026-07-19 00:00:00'),
(24, 'cast.mypage.reviews', 'キャスト：レビュー一覧', 1, 'お店に投稿したレビューの一覧だよ。', '2026-07-19 00:00:00', '2026-07-19 00:00:00'),
(25, 'shop.mypage.review.index', '店舗：レビュー一覧', 1, 'キャストから届いたレビューを確認・公開設定できます。', '2026-07-19 00:00:00', '2026-07-19 00:00:00'),
(26, 'shop.mypage.staff.index', '店舗：スタッフ管理', 1, '1つのお店で複数のログインアカウントを使えます。追加・削除ができるのはオーナー権限のみです。', '2026-07-19 00:00:00', '2026-07-19 00:00:00'),
(27, 'shop.mypage.staff.create', '店舗：スタッフを追加', 1, '新しい店舗ログインアカウントを発行します。メールアドレスとパスワードは追加するスタッフ本人に共有してください。', '2026-07-19 00:00:00', '2026-07-19 00:00:00'),
(28, 'shop.mypage.viewers.index', '店舗：閲覧キャスト一覧', 1, 'あなたのお店を閲覧したキャストの一覧です。気になるキャストにはスカウトを送ってみましょう！', '2026-07-19 00:00:00', '2026-07-19 00:00:00');

--
-- 運営権限・監査ログ（管理画面の権限制御と操作証跡）
--

CREATE TABLE IF NOT EXISTS `admin_role_permissions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `role` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_role_permissions_role_permission_unique` (`role`, `permission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
