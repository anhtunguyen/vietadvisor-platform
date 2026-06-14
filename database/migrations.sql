-- ==============================================================================
-- 🗄️ VIETADVISOR PLATFORM - DATABASE BLUEPRINT (MIGRATIONS)
-- ==============================================================================
-- 1. Xóa cấu trúc cũ để làm sạch hạ tầng nếu chạy lại
DROP TABLE IF EXISTS `contacts`;

DROP TABLE IF EXISTS `article_translations`;

DROP TABLE IF EXISTS `articles`;

DROP TABLE IF EXISTS `advisor_translations`;

DROP TABLE IF EXISTS `advisors`;

DROP TABLE IF EXISTS `users`;

-- 2. Khởi tạo Bảng người dùng dùng chung toàn sàn
CREATE TABLE `users` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(191) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('expat', 'advisor', 'admin') NOT NULL DEFAULT 'expat',
    -- 🔥 BỔ SUNG DÒNG NÀY: Lưu mảng đặc quyền dạng JSON (Ví dụ: ["access_admin_dashboard", "manage_insights"])
    `permissions` JSON NULL, 
    `status` ENUM('pending', 'active', 'suspended') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_role_status` (`role`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Khởi tạo Bảng thông số kỹ thuật Chuyên gia
CREATE TABLE `advisors` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `avatar` VARCHAR(255) NULL,
    `phone` VARCHAR(30) NULL,
    `rating` DECIMAL(3, 2) NOT NULL DEFAULT 5.00,
    `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `speak_russian` TINYINT(1) NOT NULL DEFAULT 0,
    `speak_english` TINYINT(1) NOT NULL DEFAULT 1,
    `speak_vietnamese` TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 4. Khởi tạo Bảng dịch thuật hồ sơ Chuyên gia (Đa quốc gia)
CREATE TABLE `advisor_translations` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `advisor_id` BIGINT UNSIGNED NOT NULL,
    `locale` CHAR(2) NOT NULL,
    `full_name` VARCHAR(191) NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `biography` TEXT NULL,
    UNIQUE KEY `uidx_advisor_lang` (`advisor_id`, `locale`),
    FOREIGN KEY (`advisor_id`) REFERENCES `advisors`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 5. Khởi tạo Bảng gốc Tin tức / Bài viết
CREATE TABLE `articles` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `thumbnail` VARCHAR(255) NULL,
    `author_id` BIGINT UNSIGNED NOT NULL,
    `status` ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    `views` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`author_id`) REFERENCES `users`(`id`),
    INDEX `idx_articles_status` (`status`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 6. Khởi tạo Bảng dịch thuật ruột nội dung bài viết
CREATE TABLE `article_translations` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `article_id` BIGINT UNSIGNED NOT NULL,
    `locale` CHAR(2) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `summary` TEXT NULL,
    `content` LONGTEXT NULL,
    UNIQUE KEY `uidx_article_lang` (`article_id`, `locale`),
    UNIQUE KEY `uidx_article_slug_lang` (`slug`, `locale`),
    FOREIGN KEY (`article_id`) REFERENCES `articles`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 7. Khởi tạo Bảng form liên hệ và phản hồi người dùng
CREATE TABLE `contacts` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(191) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `is_resolved` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 8. Khởi tạo Bảng lưu trữ Token ghi nhớ đăng nhập bảo mật (Remember Me 3 Chân)
CREATE TABLE `user_remember_tokens` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `selector` CHAR(16) NOT NULL UNIQUE,
    `validator_hash` CHAR(64) NOT NULL,
    `expires_at` TIMESTAMP NOT NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_selector` (`selector`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Khởi tạo Bảng quản lý Cấu hình hệ thống động đa ngôn ngữ
CREATE TABLE `site_settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    -- Trường 'value' dạng JSON lưu trữ dữ liệu dịch thuật (Ví dụ: {"vi":"Tên Việt", "en":"Tên Anh", "ru":"Tên Nga"})
    `setting_value` JSON NOT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;