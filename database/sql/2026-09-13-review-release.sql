ALTER TABLE `reviews`
  ADD COLUMN `release` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0:非公開, 1:公開'
  AFTER `is_anonymous`;
