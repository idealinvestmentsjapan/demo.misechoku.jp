-- 営業デモ初回投入用 / MySQL 8.0 / セット 1 / start
-- デモ専用DBで開発担当者が実行してください。既存のセットは変更しません。
-- 実行エラー時は ROLLBACK。mysql --force（エラー後の続行）は使用しないでください。
-- 文字列はSQLモードに左右されないUTF-8の16進数リテラルです。定義は SalesDemoFixture.php。
START TRANSACTION;
SET @sales_demo_can_create = (
  NOT EXISTS (SELECT 1 FROM `shops` WHERE `id` = _utf8mb4 X'733839303030303031')
  AND NOT EXISTS (SELECT 1 FROM `shops` WHERE `email` = _utf8mb4 X'7338393030303030314073616c65732d64656d6f2e696e76616c6964')
  AND NOT EXISTS (SELECT 1 FROM `shop_managers` WHERE `id` = _utf8mb4 X'6d3839303030303031')
  AND NOT EXISTS (SELECT 1 FROM `shop_managers` WHERE `email` = _utf8mb4 X'6d38393030303030314073616c65732d64656d6f2e696e76616c6964')
  AND NOT EXISTS (SELECT 1 FROM `shop_profiles` WHERE `id` = 8901000)
  AND NOT EXISTS (SELECT 1 FROM `shop_images` WHERE `id` = 8901000)
  AND NOT EXISTS (SELECT 1 FROM `shop_jobs` WHERE `id` = 8901000)
  AND NOT EXISTS (SELECT 1 FROM `casts` WHERE `id` = _utf8mb4 X'633839303031303031')
  AND NOT EXISTS (SELECT 1 FROM `casts` WHERE `email` = _utf8mb4 X'6338393030313030314073616c65732d64656d6f2e696e76616c6964')
  AND NOT EXISTS (SELECT 1 FROM `cast_profiles` WHERE `id` = 8901001)
  AND NOT EXISTS (SELECT 1 FROM `cast_images` WHERE `id` = 8901001)
  AND NOT EXISTS (SELECT 1 FROM `cast_posts` WHERE `id` = 8901001)
  AND NOT EXISTS (SELECT 1 FROM `cast_search_preferences` WHERE `id` = 8901001)
  AND NOT EXISTS (SELECT 1 FROM `cast_tag_relations` WHERE `id` = 8901010)
  AND NOT EXISTS (SELECT 1 FROM `cast_tag_relations` WHERE `id` = 8901011)
  AND NOT EXISTS (SELECT 1 FROM `cast_tag_relations` WHERE `id` = 8901012)
  AND NOT EXISTS (SELECT 1 FROM `casts` WHERE `id` = _utf8mb4 X'633839303031303032')
  AND NOT EXISTS (SELECT 1 FROM `casts` WHERE `email` = _utf8mb4 X'6338393030313030324073616c65732d64656d6f2e696e76616c6964')
  AND NOT EXISTS (SELECT 1 FROM `cast_profiles` WHERE `id` = 8901002)
  AND NOT EXISTS (SELECT 1 FROM `cast_images` WHERE `id` = 8901002)
  AND NOT EXISTS (SELECT 1 FROM `cast_posts` WHERE `id` = 8901002)
  AND NOT EXISTS (SELECT 1 FROM `cast_search_preferences` WHERE `id` = 8901002)
  AND NOT EXISTS (SELECT 1 FROM `cast_tag_relations` WHERE `id` = 8901013)
  AND NOT EXISTS (SELECT 1 FROM `cast_tag_relations` WHERE `id` = 8901014)
  AND NOT EXISTS (SELECT 1 FROM `cast_tag_relations` WHERE `id` = 8901015)
  AND NOT EXISTS (SELECT 1 FROM `casts` WHERE `id` = _utf8mb4 X'633839303031303033')
  AND NOT EXISTS (SELECT 1 FROM `casts` WHERE `email` = _utf8mb4 X'6338393030313030334073616c65732d64656d6f2e696e76616c6964')
  AND NOT EXISTS (SELECT 1 FROM `cast_profiles` WHERE `id` = 8901003)
  AND NOT EXISTS (SELECT 1 FROM `cast_images` WHERE `id` = 8901003)
  AND NOT EXISTS (SELECT 1 FROM `cast_posts` WHERE `id` = 8901003)
  AND NOT EXISTS (SELECT 1 FROM `cast_search_preferences` WHERE `id` = 8901003)
  AND NOT EXISTS (SELECT 1 FROM `cast_tag_relations` WHERE `id` = 8901016)
  AND NOT EXISTS (SELECT 1 FROM `cast_tag_relations` WHERE `id` = 8901017)
  AND NOT EXISTS (SELECT 1 FROM `cast_tag_relations` WHERE `id` = 8901018)
  AND NOT EXISTS (SELECT 1 FROM `notification_preferences` WHERE `id` = 8901000)
  AND NOT EXISTS (SELECT 1 FROM `notification_preferences` WHERE `id` = 8901001)
  AND NOT EXISTS (SELECT 1 FROM `notification_preferences` WHERE `id` = 8901002)
  AND NOT EXISTS (SELECT 1 FROM `notification_preferences` WHERE `id` = 8901003)
  AND NOT EXISTS (SELECT 1 FROM `favorites` WHERE `id` = 8901000)
  AND NOT EXISTS (SELECT 1 FROM `favorites` WHERE `id` = 8901001)
  AND NOT EXISTS (SELECT 1 FROM `messages` WHERE `id` = 8901020)
  AND NOT EXISTS (SELECT 1 FROM `messages` WHERE `id` = 8901021)
  AND EXISTS (SELECT 1 FROM `industries` WHERE `id` = 1 AND `del_flg` = 0)
  AND (SELECT COUNT(*) FROM `cast_tags` WHERE `id` IN (37,40,45) AND `del_flg` = 0) = 3
);
INSERT INTO `shops` (`id`, `email`, `status`, `license_status`, `business_license_status`, `entertainment_license_status`, `deleted_at`, `created_at`, `updated_at`)
SELECT _utf8mb4 X'733839303030303031', _utf8mb4 X'7338393030303030314073616c65732d64656d6f2e696e76616c6964', 1, 3, 3, 3, NULL, _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `shop_managers` (`id`, `shop_id`, `name`, `email`, `email_verified_at`, `password`, `role`, `status`, `last_login_at`, `created_at`, `updated_at`)
SELECT _utf8mb4 X'6d3839303030303031', _utf8mb4 X'733839303030303031', _utf8mb4 X'e5ae9fe6bc94e794a8e381aee5ba97e995b7', _utf8mb4 X'6d38393030303030314073616c65732d64656d6f2e696e76616c6964', _utf8mb4 X'323032362d30392d31322031343a35363a3434', NULL, 1, 1, _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `shop_profiles` (`id`, `shop_id`, `industry_id`, `industry_label`, `shop_name`, `pref`, `city`, `addr`, `tel`, `open_time`, `close_time`, `close_is_last`, `latitude`, `longitude`, `created_at`, `updated_at`)
SELECT 8901000, _utf8mb4 X'733839303030303031', 1, _utf8mb4 X'e382ade383a3e38390e382afe383a9', _utf8mb4 X'e5ae9fe6bc94e794a8e3839fe382bbe38381e383a7e382afe383a9e382a6e383b3e382b8203031', _utf8mb4 X'e69db1e4baace983bd', _utf8mb4 X'e696b0e5aebfe58cba', _utf8mb4 X'e5ae9fe6bc94e794a8e4bd8fe68980efbc88e69eb6e7a9baefbc89', NULL, _utf8mb4 X'32303a30303a3030', _utf8mb4 X'30313a30303a3030', 0, 35.6938, 139.7034, _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `shop_images` (`id`, `shop_id`, `image_path`, `type`, `is_main`, `main_order`, `created_at`, `updated_at`)
SELECT 8901000, _utf8mb4 X'733839303030303031', _utf8mb4 X'6173736574732f696d616765732f64656d6f2d73616c65732f73686f702e737667', 1, 1, 1, _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `shop_jobs` (`id`, `shop_id`, `pr`, `catch_copy`, `job_content`, `regular_status`, `regular_hourly_wage`, `regular_hourly_wage_max`, `norma_day`, `norma_hours`, `bonus_reward`, `bonus_remarks`, `bonus_condition`, `trial_hourly_wage`, `trial_status`, `has_help`, `help_hourly_wage`, `help_status`, `working_day`, `working_hours`, `regular_holiday`, `qualification`, `shift_time_start`, `shift_time_end`, `shift_end_is_last`, `deleted_at`, `created_at`, `updated_at`)
SELECT 8901000, _utf8mb4 X'733839303030303031', _utf8mb4 X'e890bde381a1e79d80e38184e3819fe4bc9ae8a9b1e38292e5a4a7e58887e381abe38199e3828be5ae9fe6bc94e794a8e381aee3818ae5ba97e381a7e38199e38082e7b58ce9a893e38284e587bae58ba4e381a7e3818de3828be69b9ce697a5e38292e38081e69cace4babae381a8e79bb4e68ea5e79bb8e8ab87e38197e381a6e6b1bae38281e381bee38199e38082', _utf8mb4 X'e68ea1e794a8e381aee3818ae98791e38292e38081e5838de3818fe69cace4babae381aee58a9be381abe38082', _utf8mb4 X'e3818ae5aea2e6a798e381a8e381aee4bc9ae8a9b1e381a8e38389e383aae383b3e382afe381aee68f90e4be9be38082e38199e381b9e381a6e69eb6e7a9bae381aee58b9fe99b86e69da1e4bbb6e381a7e38199e38082', 1, _utf8mb4 X'34303030', 5000, 30, 4, 100000, _utf8mb4 X'e5ae9fe6bc94e794a8e381aee98791e9a18d', _utf8mb4 X'e69cace585a5e5ba97e5be8c3330e697a5e99693e59ca8e7b18de38197e3808131e697a534e69982e99693e381aee58ba4e58b99e69da1e4bbb6e38292e6ba80e3819fe38197e3819fe5a0b4e59088e38082e5ae9fe6bc94e794a8e381aee69da1e4bbb6e381a7e38199e38082', _utf8mb4 X'34303030', 1, 1, _utf8mb4 X'33353030', 1, _utf8mb4 X'e980b132e3809c33e59b9ee3818be38289e79bb8e8ab87', _utf8mb4 X'32303a3030e3809ce7bf8c313a3030e381aee99693e381a7e79bb8e8ab87', _utf8mb4 X'e697a5e69b9ce697a5', _utf8mb4 X'3138e6adb3e4bba5e4b88aefbc88e9ab98e6a0a1e7949fe4b88de58fafefbc89e38082e69caae7b58ce9a893e381aee696b9e38282e79bb8e8ab87e381a7e3818de381bee38199e38082', _utf8mb4 X'32303a30303a3030', _utf8mb4 X'30313a30303a3030', 0, NULL, _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `casts` (`id`, `email`, `email_verified_at`, `password`, `status`, `identity_status`, `last_login_at`, `deleted_at`, `created_at`, `updated_at`)
SELECT _utf8mb4 X'633839303031303031', _utf8mb4 X'6338393030313030314073616c65732d64656d6f2e696e76616c6964', _utf8mb4 X'323032362d30392d31322031343a35363a3434', NULL, 1, 1, _utf8mb4 X'323032362d30392d31322031343a35363a3434', NULL, _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `cast_profiles` (`id`, `cast_id`, `industry_id`, `nickname`, `name`, `birthday`, `pref`, `city`, `profession`, `exp`, `pr`, `personality_type`, `latitude`, `longitude`, `available_declared_at`, `available_until`, `created_at`, `updated_at`)
SELECT 8901001, _utf8mb4 X'633839303031303031', 1, _utf8mb4 X'e38387e383a23031e383bbe38182e3818be3828a', _utf8mb4 X'e5ae9fe6bc94e794a820e38182e3818be3828a', _utf8mb4 X'323030322d30392d3132', _utf8mb4 X'e69db1e4baace983bd', _utf8mb4 X'e696b0e5aebfe58cba', _utf8mb4 X'e5ae9fe6bc94e794a8e38397e383ade38395e382a3e383bce383ab', 1, _utf8mb4 X'e890bde381a1e79d80e38184e3819fe68ea5e5aea2e3818ce5a5bde3818de381a7e38199e38082e980b132e3809c33e59b9ee38081e5a49ce381aee69982e99693e5b8afe381abe5838de38191e3828be3818ae5ba97e38292e68ea2e38197e381a6e38184e381bee38199e38082efbc88e69eb6e7a9bae381aee4babae789a9e381a7e38199efbc89', _utf8mb4 X'4953464a', 35.6939, 139.7035, _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31332030323a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `cast_images` (`id`, `cast_id`, `image_path`, `status`, `is_main`, `main_order`, `created_at`, `updated_at`)
SELECT 8901001, _utf8mb4 X'633839303031303031', _utf8mb4 X'6173736574732f696d616765732f64656d6f2d73616c65732f636173742d312e737667', 1, 1, 1, _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `cast_posts` (`id`, `cast_id`, `body`, `created_at`, `updated_at`)
SELECT 8901001, _utf8mb4 X'633839303031303031', _utf8mb4 X'e890bde381a1e79d80e38184e3819fe68ea5e5aea2e3818ce5a5bde3818de381a7e38199e38082e980b132e3809c33e59b9ee38081e5a49ce381aee69982e99693e5b8afe381abe5838de38191e3828be3818ae5ba97e38292e68ea2e38197e381a6e38184e381bee38199e38082', _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `cast_search_preferences` (`id`, `cast_id`, `mode`, `max_distance_km`, `shift_frequency`, `work_periods`, `hourly_wage_min`, `industry_ids`, `created_at`, `updated_at`)
SELECT 8901001, _utf8mb4 X'633839303031303031', _utf8mb4 X'70726f66696c65', 10, _utf8mb4 X'e980b133e59b9ee4bba5e4b88a', _utf8mb4 X'5b226e69676874225d', 3000, _utf8mb4 X'5b315d', _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `cast_tag_relations` (`id`, `cast_id`, `tag_id`, `tag_type`, `created_at`, `updated_at`)
SELECT 8901010, _utf8mb4 X'633839303031303031', 37, _utf8mb4 X'706572736f6e616c697479', _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `cast_tag_relations` (`id`, `cast_id`, `tag_id`, `tag_type`, `created_at`, `updated_at`)
SELECT 8901011, _utf8mb4 X'633839303031303031', 40, _utf8mb4 X'706572736f6e616c697479', _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `cast_tag_relations` (`id`, `cast_id`, `tag_id`, `tag_type`, `created_at`, `updated_at`)
SELECT 8901012, _utf8mb4 X'633839303031303031', 45, _utf8mb4 X'706572736f6e616c697479', _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `casts` (`id`, `email`, `email_verified_at`, `password`, `status`, `identity_status`, `last_login_at`, `deleted_at`, `created_at`, `updated_at`)
SELECT _utf8mb4 X'633839303031303032', _utf8mb4 X'6338393030313030324073616c65732d64656d6f2e696e76616c6964', _utf8mb4 X'323032362d30392d31322031343a35363a3434', NULL, 1, 1, _utf8mb4 X'323032362d30392d31322031343a35363a3434', NULL, _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `cast_profiles` (`id`, `cast_id`, `industry_id`, `nickname`, `name`, `birthday`, `pref`, `city`, `profession`, `exp`, `pr`, `personality_type`, `latitude`, `longitude`, `available_declared_at`, `available_until`, `created_at`, `updated_at`)
SELECT 8901002, _utf8mb4 X'633839303031303032', 1, _utf8mb4 X'e38387e383a23031e383bbe381bfe3818a', _utf8mb4 X'e5ae9fe6bc94e794a820e381bfe3818a', _utf8mb4 X'323030312d30392d3132', _utf8mb4 X'e69db1e4baace983bd', _utf8mb4 X'e696b0e5aebfe58cba', _utf8mb4 X'e5ae9fe6bc94e794a8e38397e383ade38395e382a3e383bce383ab', 1, _utf8mb4 X'e4babae381a8e8a9b1e38199e381aee3818ce5a5bde3818de381a7e38199e38082e4bd93e9a893e585a5e5ba97e381a7e3818ae5ba97e381aee99bb0e59bb2e6b097e38292e7a2bae8aa8de38197e3819fe38184e381a7e38199e38082efbc88e69eb6e7a9bae381aee4babae789a9e381a7e38199efbc89', _utf8mb4 X'454e4650', 35.6949, 139.7035, _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31332030323a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `cast_images` (`id`, `cast_id`, `image_path`, `status`, `is_main`, `main_order`, `created_at`, `updated_at`)
SELECT 8901002, _utf8mb4 X'633839303031303032', _utf8mb4 X'6173736574732f696d616765732f64656d6f2d73616c65732f636173742d322e737667', 1, 1, 1, _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `cast_posts` (`id`, `cast_id`, `body`, `created_at`, `updated_at`)
SELECT 8901002, _utf8mb4 X'633839303031303032', _utf8mb4 X'e4babae381a8e8a9b1e38199e381aee3818ce5a5bde3818de381a7e38199e38082e4bd93e9a893e585a5e5ba97e381a7e3818ae5ba97e381aee99bb0e59bb2e6b097e38292e7a2bae8aa8de38197e3819fe38184e381a7e38199e38082', _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `cast_search_preferences` (`id`, `cast_id`, `mode`, `max_distance_km`, `shift_frequency`, `work_periods`, `hourly_wage_min`, `industry_ids`, `created_at`, `updated_at`)
SELECT 8901002, _utf8mb4 X'633839303031303032', _utf8mb4 X'70726f66696c65', 10, _utf8mb4 X'e980b133e59b9ee4bba5e4b88a', _utf8mb4 X'5b226e69676874225d', 3000, _utf8mb4 X'5b315d', _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `cast_tag_relations` (`id`, `cast_id`, `tag_id`, `tag_type`, `created_at`, `updated_at`)
SELECT 8901013, _utf8mb4 X'633839303031303032', 37, _utf8mb4 X'706572736f6e616c697479', _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `cast_tag_relations` (`id`, `cast_id`, `tag_id`, `tag_type`, `created_at`, `updated_at`)
SELECT 8901014, _utf8mb4 X'633839303031303032', 40, _utf8mb4 X'706572736f6e616c697479', _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `cast_tag_relations` (`id`, `cast_id`, `tag_id`, `tag_type`, `created_at`, `updated_at`)
SELECT 8901015, _utf8mb4 X'633839303031303032', 45, _utf8mb4 X'706572736f6e616c697479', _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `casts` (`id`, `email`, `email_verified_at`, `password`, `status`, `identity_status`, `last_login_at`, `deleted_at`, `created_at`, `updated_at`)
SELECT _utf8mb4 X'633839303031303033', _utf8mb4 X'6338393030313030334073616c65732d64656d6f2e696e76616c6964', _utf8mb4 X'323032362d30392d31322031343a35363a3434', NULL, 1, 1, _utf8mb4 X'323032362d30392d31322031343a35363a3434', NULL, _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `cast_profiles` (`id`, `cast_id`, `industry_id`, `nickname`, `name`, `birthday`, `pref`, `city`, `profession`, `exp`, `pr`, `personality_type`, `latitude`, `longitude`, `available_declared_at`, `available_until`, `created_at`, `updated_at`)
SELECT 8901003, _utf8mb4 X'633839303031303033', 1, _utf8mb4 X'e38387e383a23031e383bbe38286e38184', _utf8mb4 X'e5ae9fe6bc94e794a820e38286e38184', _utf8mb4 X'323030302d30392d3132', _utf8mb4 X'e69db1e4baace983bd', _utf8mb4 X'e696b0e5aebfe58cba', _utf8mb4 X'e5ae9fe6bc94e794a8e38397e383ade38395e382a3e383bce383ab', 0, _utf8mb4 X'e69caae7b58ce9a893e381a7e38199e38082e4bb95e4ba8be381aee6b581e3828ce38292e69599e38188e381a6e38282e38289e38188e3828be3818ae5ba97e38292e68ea2e38197e381a6e38184e381bee38199e38082efbc88e69eb6e7a9bae381aee4babae789a9e381a7e38199efbc89', _utf8mb4 X'494e464a', 35.6959, 139.7035, _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31332030323a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `cast_images` (`id`, `cast_id`, `image_path`, `status`, `is_main`, `main_order`, `created_at`, `updated_at`)
SELECT 8901003, _utf8mb4 X'633839303031303033', _utf8mb4 X'6173736574732f696d616765732f64656d6f2d73616c65732f636173742d332e737667', 1, 1, 1, _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `cast_posts` (`id`, `cast_id`, `body`, `created_at`, `updated_at`)
SELECT 8901003, _utf8mb4 X'633839303031303033', _utf8mb4 X'e69caae7b58ce9a893e381a7e38199e38082e4bb95e4ba8be381aee6b581e3828ce38292e69599e38188e381a6e38282e38289e38188e3828be3818ae5ba97e38292e68ea2e38197e381a6e38184e381bee38199e38082', _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `cast_search_preferences` (`id`, `cast_id`, `mode`, `max_distance_km`, `shift_frequency`, `work_periods`, `hourly_wage_min`, `industry_ids`, `created_at`, `updated_at`)
SELECT 8901003, _utf8mb4 X'633839303031303033', _utf8mb4 X'70726f66696c65', 10, _utf8mb4 X'e980b133e59b9ee4bba5e4b88a', _utf8mb4 X'5b226e69676874225d', 3000, _utf8mb4 X'5b315d', _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `cast_tag_relations` (`id`, `cast_id`, `tag_id`, `tag_type`, `created_at`, `updated_at`)
SELECT 8901016, _utf8mb4 X'633839303031303033', 37, _utf8mb4 X'706572736f6e616c697479', _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `cast_tag_relations` (`id`, `cast_id`, `tag_id`, `tag_type`, `created_at`, `updated_at`)
SELECT 8901017, _utf8mb4 X'633839303031303033', 40, _utf8mb4 X'706572736f6e616c697479', _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `cast_tag_relations` (`id`, `cast_id`, `tag_id`, `tag_type`, `created_at`, `updated_at`)
SELECT 8901018, _utf8mb4 X'633839303031303033', 45, _utf8mb4 X'706572736f6e616c697479', _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `notification_preferences` (`id`, `user_type`, `user_id`, `push_enabled`, `line_enabled`, `interview_reminder_enabled`, `deadline_reminder_enabled`, `created_at`, `updated_at`)
SELECT 8901000, _utf8mb4 X'73686f70', _utf8mb4 X'6d3839303030303031', 0, 0, 0, 0, _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `notification_preferences` (`id`, `user_type`, `user_id`, `push_enabled`, `line_enabled`, `interview_reminder_enabled`, `deadline_reminder_enabled`, `created_at`, `updated_at`)
SELECT 8901001, _utf8mb4 X'63617374', _utf8mb4 X'633839303031303031', 0, 0, 0, 0, _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `notification_preferences` (`id`, `user_type`, `user_id`, `push_enabled`, `line_enabled`, `interview_reminder_enabled`, `deadline_reminder_enabled`, `created_at`, `updated_at`)
SELECT 8901002, _utf8mb4 X'63617374', _utf8mb4 X'633839303031303032', 0, 0, 0, 0, _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `notification_preferences` (`id`, `user_type`, `user_id`, `push_enabled`, `line_enabled`, `interview_reminder_enabled`, `deadline_reminder_enabled`, `created_at`, `updated_at`)
SELECT 8901003, _utf8mb4 X'63617374', _utf8mb4 X'633839303031303033', 0, 0, 0, 0, _utf8mb4 X'323032362d30392d31322031343a35363a3434', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `favorites` (`id`, `cast_id`, `shop_id`, `sender_type`, `action_type`, `created_at`)
SELECT 8901000, _utf8mb4 X'633839303031303032', _utf8mb4 X'733839303030303031', _utf8mb4 X'63617374', _utf8mb4 X'6b656570', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `favorites` (`id`, `cast_id`, `shop_id`, `sender_type`, `action_type`, `created_at`)
SELECT 8901001, _utf8mb4 X'633839303031303032', _utf8mb4 X'733839303030303031', _utf8mb4 X'73686f70', _utf8mb4 X'6b656570', _utf8mb4 X'323032362d30392d31322031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `messages` (`id`, `cast_id`, `shop_id`, `sender_type`, `type`, `content`, `is_read`, `deleted_at`, `created_at`, `updated_at`)
SELECT 8901020, _utf8mb4 X'633839303031303032', _utf8mb4 X'733839303030303031', 2, 1, _utf8mb4 X'e38090e5ae9fe6bc94e794a8e38091e38397e383ade38395e382a3e383bce383abe38292e68b9de8a68be38197e381bee38197e3819fe38082e4bd93e9a893e585a5e5ba97e381abe381a4e38184e381a6e3818ae8a9b1e38197e381a7e3818de381bee38199e3818befbc9f', 1, NULL, _utf8mb4 X'323032362d30392d31302031343a35363a3434', _utf8mb4 X'323032362d30392d31302031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
INSERT INTO `messages` (`id`, `cast_id`, `shop_id`, `sender_type`, `type`, `content`, `is_read`, `deleted_at`, `created_at`, `updated_at`)
SELECT 8901021, _utf8mb4 X'633839303031303032', _utf8mb4 X'733839303030303031', 1, 1, _utf8mb4 X'e38090e5ae9fe6bc94e794a8e38091e38182e3828ae3818ce381a8e38186e38194e38196e38184e381bee38199e38082e587bae58ba4e381a7e3818de3828be69b9ce697a5e3818be38289e79bb8e8ab87e38197e3819fe38184e381a7e38199e38082', 1, NULL, _utf8mb4 X'323032362d30392d31312031343a35363a3434', _utf8mb4 X'323032362d30392d31312031343a35363a3434' FROM DUAL WHERE @sales_demo_can_create = 1;
COMMIT;
SELECT IF(@sales_demo_can_create = 1, 'created', 'skipped: existing IDs or missing masters') AS sales_demo_result;
